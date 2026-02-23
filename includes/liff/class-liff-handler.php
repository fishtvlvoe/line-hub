<?php
/**
 * LIFF Handler
 *
 * 處理 LIFF 登入流程：頁面渲染、Access Token 驗證、Email 收集
 *
 * @package LineHub
 */

namespace LineHub\Liff;

use LineHub\Services\LoginService;
use LineHub\Services\SettingsService;
use LineHub\Services\UserService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LiffHandler 類別
 *
 * LIFF 登入流程：
 * 1. GET  /line-hub/liff/           → 渲染 LIFF 頁面（含 LIFF SDK）
 * 2. POST /line-hub/liff/           → 驗證 Access Token
 *    - 既有用戶 → 直接登入
 *    - 新用戶   → 顯示 Email 表單
 * 3. POST /line-hub/liff/ (email)   → 收到 Email 後建立帳號並登入
 */
class LiffHandler {

    /**
     * LINE Access Token 驗證端點
     */
    private const VERIFY_ENDPOINT = 'https://api.line.me/oauth2/v2.1/verify';

    /**
     * LINE Profile 端點
     */
    private const PROFILE_ENDPOINT = 'https://api.line.me/v2/profile';

    /**
     * HTTP 請求超時時間（秒）
     */
    private const REQUEST_TIMEOUT = 15;

    /**
     * Transient 過期時間（秒）：Email 表單有效期 10 分鐘
     */
    private const EMAIL_TOKEN_EXPIRY = 600;

    /**
     * 處理 LIFF 請求
     *
     * @return void
     */
    public function handleRequest(): void {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF handleRequest: method=' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . ' redirect=' . ($_GET['redirect'] ?? $_POST['redirect'] ?? 'NONE') . ' logged_in=' . (is_user_logged_in() ? 'yes' : 'no'));

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Email 表單提交（帶 liff_email_token）
            if (!empty($_POST['liff_email_token'])) {
                $this->handleEmailSubmit();
                return;
            }

            // LIFF Access Token 驗證
            $this->handleVerify();
            return;
        }

        // GET 請求：渲染 LIFF 頁面
        $this->renderPage();
    }

    /**
     * 渲染 LIFF 登入頁面
     *
     * @return void
     */
    private function renderPage(): void {
        $liff_id = SettingsService::get('general', 'liff_id', '');

        if (empty($liff_id)) {
            wp_die(
                esc_html__('LIFF 尚未設定，請聯繫管理員', 'line-hub'),
                esc_html__('設定錯誤', 'line-hub'),
                ['response' => 500]
            );
        }

        // 取得重定向 URL（優先級：GET 參數 > cookie > 預設首頁）
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $redirect = isset($_GET['redirect']) ? wp_unslash($_GET['redirect']) : '';
        if (empty($redirect) && !empty($_COOKIE['liff_redirect'])) {
            // LIFF login 過程中 URL 參數丟失，從 cookie 恢復
            $redirect = sanitize_text_field(wp_unslash($_COOKIE['liff_redirect']));
            // 清除 cookie
            setcookie('liff_redirect', '', time() - 3600, '/', '', is_ssl(), true);
        }
        $redirect = $this->resolveRedirectUrl($redirect);

        // 如果用戶已登入且已綁定 LINE，直接重定向
        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            $has_binding = UserService::hasDirectBinding($current_user_id);

            if ($has_binding) {
                wp_safe_redirect($redirect);
                exit;
            }
            // 已登入但未綁定 → 繼續顯示 LIFF 頁面，讓用戶綁定 LINE
        }

        // 產生 nonce
        $nonce = wp_create_nonce('line_hub_liff_verify');

        // 載入模板
        include LINE_HUB_PATH . 'includes/liff/liff-template.php';
        exit;
    }

    /**
     * 驗證 LIFF Access Token
     *
     * 既有用戶：直接登入
     * 新用戶：顯示 Email 表單
     *
     * @return void
     */
    private function handleVerify(): void {
        // 驗證 nonce
        if (!isset($_POST['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'line_hub_liff_verify')) {
            $this->respondError(__('安全驗證失敗，請重試', 'line-hub'));
            return;
        }

        // 取得 Access Token
        $access_token = isset($_POST['liff_access_token'])
            ? sanitize_text_field(wp_unslash($_POST['liff_access_token']))
            : '';

        if (empty($access_token)) {
            $this->respondError(__('缺少 Access Token', 'line-hub'));
            return;
        }

        // 取得重定向 URL（優先級：POST 參數 > cookie > 預設首頁）
        $redirect = isset($_POST['redirect'])
            ? wp_unslash($_POST['redirect'])
            : '';
        if (empty($redirect) && !empty($_COOKIE['liff_redirect'])) {
            $redirect = sanitize_text_field(wp_unslash($_COOKIE['liff_redirect']));
        }
        // 清除 cookie（不論來源）
        if (!empty($_COOKIE['liff_redirect'])) {
            setcookie('liff_redirect', '', time() - 3600, '/', '', is_ssl(), true);
        }
        $redirect = $this->resolveRedirectUrl($redirect);

        // Step 1: 驗證 Access Token
        $verify_result = $this->verifyAccessToken($access_token);
        if (is_wp_error($verify_result)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF token verify failed: ' . $verify_result->get_error_message());
            $this->respondError(__('Access Token 驗證失敗', 'line-hub'));
            return;
        }

        // Step 2: 取得 LINE Profile
        $profile = $this->getProfile($access_token);
        if (is_wp_error($profile)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF profile fetch failed: ' . $profile->get_error_message());
            $this->respondError(__('無法取得 LINE 用戶資料', 'line-hub'));
            return;
        }

        $line_uid = $profile['userId'] ?? '';
        $display_name = $profile['displayName'] ?? '';
        $picture_url = $profile['pictureUrl'] ?? '';

        if (empty($line_uid)) {
            $this->respondError(__('無法取得 LINE 用戶 ID', 'line-hub'));
            return;
        }

        // 取得好友狀態
        $is_friend = isset($_POST['liff_is_friend']) && $_POST['liff_is_friend'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF login: ' . $display_name . ' (' . substr($line_uid, 0, 8) . '...) friend=' . ($is_friend ? 'yes' : 'no'));

        // Step 3a: 如果用戶已登入（綁定模式），直接綁定 LINE 到當前帳號
        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();

            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF binding mode: linking LINE to logged-in user #' . $current_user_id);

            $link_result = UserService::linkUser($current_user_id, $line_uid, [
                'displayName' => $display_name,
                'pictureUrl'  => $picture_url,
            ]);

            if (is_wp_error($link_result)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[LINE Hub] LIFF binding failed: ' . $link_result->get_error_message());
                $this->respondError($link_result->get_error_message());
                return;
            }

            // 更新頭像和好友狀態
            if (!empty($picture_url)) {
                update_user_meta($current_user_id, 'line_hub_avatar_url', esc_url_raw($picture_url));
            }
            update_user_meta($current_user_id, 'line_hub_login_method', 'liff');
            update_user_meta($current_user_id, 'line_hub_is_friend', $is_friend ? '1' : '0');

            // 設定歡迎 cookie
            setcookie('line_hub_welcome', '1', time() + 30, '/', '', is_ssl(), false);

            wp_safe_redirect($redirect);
            exit;
        }

        // Step 3b: 檢查是否為既有用戶（透過 LINE UID 查詢）
        $existing_user_id = UserService::getUserByLineUid($line_uid);

        if ($existing_user_id) {
            // 自動遷移 NSL 綁定
            $this->migrateBindingIfNeeded($existing_user_id, $line_uid, $display_name, $picture_url);

            // 更新好友狀態
            update_user_meta($existing_user_id, 'line_hub_is_friend', $is_friend ? '1' : '0');

            // 直接登入
            $this->loginAndRedirect($existing_user_id, $profile, $access_token, $redirect);
            return;
        }

        // Step 4: 新用戶 → 儲存資料到 transient，顯示 Email 表單
        $token = wp_generate_password(32, false);
        set_transient('line_hub_liff_' . $token, [
            'line_uid'     => $line_uid,
            'display_name' => $display_name,
            'picture_url'  => $picture_url,
            'redirect'     => $redirect,
            'access_token' => $access_token,
            'is_friend'    => $is_friend,
        ], self::EMAIL_TOKEN_EXPIRY);

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF new user, showing email form for: ' . $display_name);

        $this->renderEmailForm($token, $display_name, $picture_url, $redirect);
    }

    /**
     * 處理 Email 表單提交
     *
     * @return void
     */
    private function handleEmailSubmit(): void {
        // 驗證 nonce
        if (!isset($_POST['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'line_hub_liff_email')) {
            $this->respondError(__('安全驗證失敗，請重試', 'line-hub'));
            return;
        }

        // 取得 token
        $token = isset($_POST['liff_email_token'])
            ? sanitize_text_field(wp_unslash($_POST['liff_email_token']))
            : '';

        if (empty($token)) {
            $this->respondError(__('無效的請求', 'line-hub'));
            return;
        }

        // 從 transient 取回資料
        $data = get_transient('line_hub_liff_' . $token);
        if (empty($data)) {
            $this->respondError(__('連結已過期，請重新登入', 'line-hub'));
            return;
        }

        $line_uid     = $data['line_uid'];
        $display_name = $data['display_name'];
        $picture_url  = $data['picture_url'];
        $redirect     = $data['redirect'];
        $access_token = $data['access_token'];
        $is_friend    = !empty($data['is_friend']);

        // 取得 Email（可選填，跳過時為空）
        $email = isset($_POST['email'])
            ? sanitize_email(wp_unslash($_POST['email']))
            : '';
        $skip_email = !empty($_POST['skip_email']);

        // 驗證 Email
        if (!$skip_email) {
            if (empty($email)) {
                // 重新顯示表單並帶錯誤訊息
                $this->renderEmailForm($token, $display_name, $picture_url, $redirect, __('請輸入 Email 信箱', 'line-hub'));
                return;
            }

            if (!is_email($email)) {
                $this->renderEmailForm($token, $display_name, $picture_url, $redirect, __('Email 格式不正確', 'line-hub'));
                return;
            }

            // 檢查 Email 是否已被使用 → 帳號合併（綁定 LINE 到既有帳號）
            $existing_user_id = email_exists($email);
            if ($existing_user_id) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('[LINE Hub] LIFF merging: linking LINE to existing user #' . $existing_user_id . ' (' . $email . ')');

                // 刪除 transient
                delete_transient('line_hub_liff_' . $token);

                // 綁定 LINE 到既有帳號
                $link_result = UserService::linkUser($existing_user_id, $line_uid, [
                    'displayName' => $display_name,
                    'pictureUrl'  => $picture_url,
                ]);

                if (is_wp_error($link_result)) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('[LINE Hub] LIFF merge link failed: ' . $link_result->get_error_message());
                }

                // 標記登入方式和好友狀態
                update_user_meta($existing_user_id, 'line_hub_login_method', 'liff');
                update_user_meta($existing_user_id, 'line_hub_is_friend', $is_friend ? '1' : '0');

                $profile = [
                    'userId'      => $line_uid,
                    'displayName' => $display_name,
                    'pictureUrl'  => $picture_url,
                ];

                $this->loginAndRedirect($existing_user_id, $profile, $access_token, $redirect);
                return;
            }
        }

        // 決定最終 Email
        if ($skip_email || empty($email)) {
            $email = 'liff_' . substr(md5($line_uid), 0, 12) . '@line.local';
        }

        // 刪除 transient（一次性使用）
        delete_transient('line_hub_liff_' . $token);

        // 建立用戶
        $user_id = $this->createNewUser($line_uid, $display_name, $picture_url, $email);

        if (is_wp_error($user_id)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF user creation failed: ' . $user_id->get_error_message());
            $this->respondError($user_id->get_error_message());
            return;
        }

        // 儲存好友狀態
        update_user_meta($user_id, 'line_hub_is_friend', $is_friend ? '1' : '0');

        $profile = [
            'userId'      => $line_uid,
            'displayName' => $display_name,
            'pictureUrl'  => $picture_url,
        ];

        // 登入並重定向
        $this->loginAndRedirect($user_id, $profile, $access_token, $redirect);
    }

    /**
     * 渲染 Email 收集表單
     *
     * @param string $token        暫存 token
     * @param string $display_name LINE 顯示名稱
     * @param string $picture_url  LINE 頭像 URL
     * @param string $redirect     登入後重定向 URL
     * @param string $error        錯誤訊息（可選）
     * @return void
     */
    private function renderEmailForm(
        string $token,
        string $display_name,
        string $picture_url,
        string $redirect,
        string $error = ''
    ): void {
        $nonce = wp_create_nonce('line_hub_liff_email');

        include LINE_HUB_PATH . 'includes/liff/liff-email-template.php';
        exit;
    }

    /**
     * 登入並重定向（既有用戶和新用戶共用）
     *
     * @param int    $user_id      WordPress 用戶 ID
     * @param array  $profile      LINE 用戶資料
     * @param string $access_token LIFF Access Token
     * @param string $redirect     重定向 URL
     * @return void
     */
    private function loginAndRedirect(int $user_id, array $profile, string $access_token, string $redirect): void {
        $display_name = $profile['displayName'] ?? '';
        $picture_url = $profile['pictureUrl'] ?? '';

        // 登入
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());

        // 更新 LINE 資料
        UserService::updateProfile($user_id, [
            'displayName' => $display_name,
            'pictureUrl'  => $picture_url,
        ]);

        // 儲存 LINE 頭像
        if (!empty($picture_url)) {
            update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($picture_url));
        }

        $user = get_user_by('ID', $user_id);

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF login success: #' . $user_id . ' (' . ($user ? $user->user_login : 'unknown') . ')');

        /**
         * LIFF 登入成功後觸發
         *
         * @param int    $user_id      WordPress 用戶 ID
         * @param array  $profile      LINE 用戶資料
         * @param string $access_token LIFF Access Token
         */
        do_action('line_hub/liff/logged_in', $user_id, $profile, $access_token);
        do_action('line_hub/user/logged_in', $user_id, $profile, ['access_token' => $access_token]);

        if ($user) {
            do_action('wp_login', $user->user_login, $user);
        }

        // 設定歡迎 cookie（前端用來顯示 Toast）
        setcookie('line_hub_welcome', '1', time() + 30, '/', '', is_ssl(), false);

        // 重定向
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * 自動遷移 NSL 綁定到 wp_line_hub_users
     *
     * @param int    $user_id      WordPress 用戶 ID
     * @param string $line_uid     LINE 用戶 ID
     * @param string $display_name LINE 顯示名稱
     * @param string $picture_url  LINE 頭像 URL
     * @return void
     */
    private function migrateBindingIfNeeded(int $user_id, string $line_uid, string $display_name, string $picture_url): void {
        if (UserService::hasDirectBinding($user_id)) {
            return;
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF: migrating NSL binding for user #' . $user_id);

        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';
        $wpdb->insert(
            $table_name,
            [
                'user_id'       => $user_id,
                'line_uid'      => $line_uid,
                'display_name'  => sanitize_text_field($display_name),
                'picture_url'   => esc_url_raw($picture_url),
                'status'        => 'active',
                'register_date' => current_time('mysql'),
                'link_date'     => current_time('mysql'),
                'created_at'    => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * 建立新 WordPress 用戶並綁定 LINE
     *
     * @param string $line_uid     LINE 用戶 ID
     * @param string $display_name LINE 顯示名稱
     * @param string $picture_url  LINE 頭像 URL
     * @param string $email        Email 信箱
     * @return int|\WP_Error 成功返回 user_id，失敗返回 WP_Error
     */
    private function createNewUser(string $line_uid, string $display_name, string $picture_url, string $email): int|\WP_Error {
        $username = LoginService::generateUsername($display_name);

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF creating user: ' . $username . ' (' . $email . ')');

        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(12, false),
            'display_name' => !empty($display_name) ? $display_name : $username,
            'role'         => 'subscriber',
        ]);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // 標記為 LIFF 建立的帳號
        update_user_meta($user_id, 'line_hub_login_method', 'liff');
        update_user_meta($user_id, 'default_password_nag', true);

        // 儲存 LINE 頭像
        if (!empty($picture_url)) {
            update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($picture_url));
        }

        // 綁定 LINE
        $link_result = UserService::linkUser($user_id, $line_uid, [
            'displayName' => $display_name,
            'pictureUrl'  => $picture_url,
        ]);

        if (is_wp_error($link_result)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF link failed: ' . $link_result->get_error_message());
        }

        /**
         * LIFF 用戶註冊後觸發
         *
         * @param int   $user_id WordPress 用戶 ID
         * @param array $profile LINE 用戶資料
         */
        do_action('line_hub/user/registered', $user_id, [
            'userId'      => $line_uid,
            'displayName' => $display_name,
            'pictureUrl'  => $picture_url,
        ]);

        return $user_id;
    }

    /**
     * 驗證 LIFF Access Token
     *
     * @param string $access_token LIFF Access Token
     * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
     */
    private function verifyAccessToken(string $access_token) {
        $response = wp_remote_get(
            self::VERIFY_ENDPOINT . '?access_token=' . urlencode($access_token),
            ['timeout' => self::REQUEST_TIMEOUT]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('verify_failed', $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new \WP_Error('token_invalid', 'Access Token 無效或已過期');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        // 驗證 client_id（LIFF token 的 client_id 是 Login Channel ID）
        $login_channel_id = SettingsService::get('general', 'login_channel_id', '');
        $expected_client_id = !empty($login_channel_id) ? $login_channel_id : SettingsService::get('general', 'channel_id', '');
        $actual_client_id = $body['client_id'] ?? '';
        if (!empty($expected_client_id) && $actual_client_id !== $expected_client_id) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF token client_id mismatch: expected=' . $expected_client_id . ' actual=' . $actual_client_id);
        }

        // 檢查是否過期
        if (($body['expires_in'] ?? 0) <= 0) {
            return new \WP_Error('token_expired', 'Access Token 已過期');
        }

        return true;
    }

    /**
     * 使用 Access Token 取得 LINE Profile
     *
     * @param string $access_token LIFF Access Token
     * @return array|\WP_Error 成功返回 profile 陣列，失敗返回 WP_Error
     */
    private function getProfile(string $access_token): array|\WP_Error {
        $response = wp_remote_get(self::PROFILE_ENDPOINT, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('profile_failed', $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new \WP_Error('profile_error', 'LINE Profile API 回應錯誤（HTTP ' . $status_code . '）');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($body) ? $body : new \WP_Error('profile_parse', '無法解析 LINE Profile 回應');
    }

    /**
     * 將 redirect 參數轉為完整的絕對 URL
     *
     * @param string $redirect 原始 redirect 值（可能是相對路徑或完整 URL）
     * @return string 完整的絕對 URL
     */
    private function resolveRedirectUrl(string $redirect): string {
        $redirect = trim($redirect);

        if (empty($redirect)) {
            return home_url('/');
        }

        // 相對路徑轉絕對 URL（例如 /item/871 → https://example.com/item/871）
        if (strpos($redirect, 'http') !== 0) {
            $redirect = home_url($redirect);
        }

        $redirect = esc_url_raw($redirect);

        return !empty($redirect) ? $redirect : home_url('/');
    }

    /**
     * 回應錯誤
     *
     * @param string $message 錯誤訊息
     * @return void
     */
    private function respondError(string $message): void {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF error: ' . $message);

        wp_die(
            esc_html($message),
            esc_html__('登入失敗', 'line-hub'),
            [
                'response'  => 400,
                'back_link' => true,
            ]
        );
    }
}
