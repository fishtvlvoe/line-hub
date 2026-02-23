<?php
/**
 * Login Service
 *
 * 用戶登入/註冊服務，處理 OAuth 認證完成後的帳號建立和登入流程
 *
 * @package LineHub
 */

namespace LineHub\Services;

use LineHub\Auth\OAuthClient;
use LineHub\Auth\OAuthState;
use LineHub\Auth\SessionTransfer;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LoginService 類別
 *
 * 處理用戶登入/註冊的完整流程：
 * - 新用戶建立 WordPress 帳號
 * - 現有用戶綁定 LINE
 * - Email 缺失時顯示輸入表單
 * - 登入後同步 LINE 資料
 */
class LoginService {

    /**
     * Username 前綴
     */
    private const USER_PREFIX = 'line_';

    /**
     * Display name 無效時的 fallback 前綴
     */
    private const USER_FALLBACK = 'user_';

    /**
     * Email 暫存 Transient 前綴
     */
    private const EMAIL_TEMP_PREFIX = 'line_hub_email_temp_';

    /**
     * Email 暫存過期時間（10 分鐘）
     */
    private const EMAIL_TEMP_EXPIRATION = 600;

    /**
     * 主要入口點，處理用戶登入/註冊
     *
     * 流程：
     * 1. 檢查 LINE UID 是否已綁定 → 直接登入
     * 2. 檢查 Email 是否存在 → 沒有則顯示表單
     * 3. 檢查 Email 是否已被使用 → 綁定到現有帳號
     * 4. 建立新帳號 → 綁定並登入
     *
     * @param array $user_data LINE 用戶資料（userId, displayName, pictureUrl, email）
     * @param array $tokens    OAuth tokens（access_token, refresh_token, expires_in）
     * @return void
     */
    public function handleUser(array $user_data, array $tokens): void {
        $line_uid = $user_data['userId'] ?? '';
        $email = $user_data['email'] ?? '';

        if (empty($line_uid)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LoginService error: missing LINE userId');
            $this->redirectWithError(__('無法取得 LINE 用戶資料', 'line-hub'));
            return;
        }

        // Step 1: 檢查 LINE UID 是否已綁定到 WordPress 用戶
        $existing_user_id = UserService::getUserByLineUid($line_uid);
        if ($existing_user_id) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] User already bound, logging in user #' . $existing_user_id);
            $this->loginUser($existing_user_id, $user_data, $tokens);
            return;
        }

        // Step 2: 檢查 Email 是否存在
        if (empty($email)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] No email provided, showing email form');
            $this->showEmailForm($user_data, $tokens);
            return;
        }

        // Step 3: 檢查 Email 是否已被其他 WordPress 帳號使用
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            // 用戶決策：同 Email = 同人，綁定到現有帳號
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] Email exists, binding LINE to existing user #' . $existing_user->ID);

            $link_result = UserService::linkUser($existing_user->ID, $line_uid, [
                'displayName' => $user_data['displayName'] ?? '',
                'pictureUrl'  => $user_data['pictureUrl'] ?? '',
                'email'       => $email,
            ]);

            if (is_wp_error($link_result)) {
                $this->redirectWithError($link_result->get_error_message());
                return;
            }

            $this->loginUser($existing_user->ID, $user_data, $tokens);
            return;
        }

        // Step 4: 建立新 WordPress 帳號
        $new_user_id = $this->createUser($user_data);

        if (is_wp_error($new_user_id)) {
            $this->redirectWithError($new_user_id->get_error_message());
            return;
        }

        // 綁定 LINE 到新帳號
        $link_result = UserService::linkUser($new_user_id, $line_uid, [
            'displayName' => $user_data['displayName'] ?? '',
            'pictureUrl'  => $user_data['pictureUrl'] ?? '',
            'email'       => $email,
        ]);

        if (is_wp_error($link_result)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] Failed to link user: ' . $link_result->get_error_message());
            // 帳號已建立但綁定失敗，仍可登入
        }

        $this->loginUser($new_user_id, $user_data, $tokens);
    }

    /**
     * 處理 Email 表單提交
     *
     * @return void
     */
    public function handleEmailSubmit(): void {
        // 只接受 POST 請求
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        // 驗證 nonce
        if (!isset($_POST['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'line_hub_email_submit')) {
            $this->redirectWithError(__('安全驗證失敗，請重試', 'line-hub'));
            return;
        }

        // 取得暫存 key
        $temp_key = isset($_POST['temp_key']) ? sanitize_text_field(wp_unslash($_POST['temp_key'])) : '';
        if (empty($temp_key)) {
            $this->redirectWithError(__('登入逾時，請重新登入', 'line-hub'));
            return;
        }

        // 從 Transient 取得暫存資料
        $transient_key = self::EMAIL_TEMP_PREFIX . $temp_key;
        $temp_data = get_transient($transient_key);

        if (!$temp_data || !is_array($temp_data)) {
            $this->redirectWithError(__('登入逾時，請重新登入', 'line-hub'));
            return;
        }

        // 取得並驗證 Email
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if (empty($email) || !is_email($email)) {
            $this->redirectWithError(__('請輸入有效的 Email 地址', 'line-hub'));
            return;
        }

        // 清理 Transient
        delete_transient($transient_key);

        // 更新 user_data 並繼續流程
        $user_data = $temp_data['user_data'];
        $user_data['email'] = $email;
        $tokens = $temp_data['tokens'];

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] Email submitted: ' . $email);

        $this->handleUser($user_data, $tokens);
    }

    /**
     * 產生唯一的 WordPress username
     *
     * 參考 NSL 的 username 生成邏輯：
     * 1. 轉小寫、移除空格
     * 2. 使用 sanitize_user() 清理
     * 3. 加上前綴
     * 4. 如果結果為空，使用 fallback
     * 5. 檢查衝突並加數字後綴
     *
     * @param string $display_name LINE 顯示名稱
     * @return string 唯一的 username（最長 60 字元）
     */
    public static function generateUsername(string $display_name): string {
        // 移除空格並轉小寫
        $base = strtolower(str_replace(' ', '', $display_name));

        // 使用 sanitize_user 清理（第二個參數 true = strict mode）
        $base = sanitize_user($base, true);

        // 如果清理後為空（例如純中文名稱），使用 fallback
        if (empty($base)) {
            $base = self::USER_FALLBACK . substr(md5(uniqid('', true)), 0, 8);
        } else {
            $base = self::USER_PREFIX . $base;
        }

        // 確保不超過 60 字元（WordPress 限制）
        $base = substr($base, 0, 55);

        // 檢查 username 是否已存在
        $username = $base;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base . $counter;
            $counter++;

            // 防止無限迴圈
            if ($counter > 1000) {
                $username = self::USER_FALLBACK . substr(md5(uniqid('', true)), 0, 8);
                break;
            }
        }

        return $username;
    }

    /**
     * 建立新 WordPress 用戶
     *
     * @param array $user_data LINE 用戶資料
     * @return int|\WP_Error 成功返回 user_id，失敗返回 WP_Error
     */
    private function createUser(array $user_data): int|\WP_Error {
        $display_name = $user_data['displayName'] ?? '';
        $email = $user_data['email'] ?? '';

        // 產生 username
        $username = self::generateUsername($display_name);

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] Creating user: ' . $username . ' (' . $email . ')');

        // 使用 wp_insert_user 建立帳號
        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(12, false),
            'display_name' => !empty($display_name) ? $display_name : $username,
            'role'         => 'subscriber', // 用戶決策：預設 subscriber 角色
        ]);

        if (is_wp_error($user_id)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] Failed to create user: ' . $user_id->get_error_message());
            return $user_id;
        }

        // 設置密碼提示（讓用戶知道需要設定密碼）
        update_user_meta($user_id, 'default_password_nag', true);

        // 儲存 LINE 頭像 URL
        if (!empty($user_data['pictureUrl'])) {
            update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($user_data['pictureUrl']));
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] User created: #' . $user_id);

        /**
         * 新用戶註冊後觸發
         *
         * @param int   $user_id   WordPress 用戶 ID
         * @param array $user_data LINE 用戶資料
         */
        do_action('line_hub/user/registered', $user_id, $user_data);

        return $user_id;
    }

    /**
     * 登入用戶並重定向
     *
     * @param int   $user_id   WordPress 用戶 ID
     * @param array $user_data LINE 用戶資料
     * @param array $tokens    OAuth tokens
     * @return void
     */
    private function loginUser(int $user_id, array $user_data, array $tokens): void {
        // 取得用戶物件
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            $this->redirectWithError(__('用戶不存在', 'line-hub'));
            return;
        }

        // 更新 LINE 資料
        UserService::updateProfile($user_id, [
            'displayName' => $user_data['displayName'] ?? '',
            'pictureUrl'  => $user_data['pictureUrl'] ?? '',
            'email'       => $user_data['email'] ?? '',
        ]);

        // 儲存 LINE 頭像為 user_meta（AUTH-06）
        if (!empty($user_data['pictureUrl'])) {
            update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($user_data['pictureUrl']));
        }

        // 可選：儲存 access_token（根據設定）
        $store_token = SettingsService::get('login', 'store_access_token', false);
        if ($store_token && !empty($tokens['access_token'])) {
            update_user_meta($user_id, 'line_hub_access_token', $tokens['access_token']);
            if (!empty($tokens['expires_in'])) {
                update_user_meta($user_id, 'line_hub_token_expires', time() + intval($tokens['expires_in']));
            }
        }

        // 設置 WordPress 登入
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] User logged in: #' . $user_id . ' (' . $user->user_login . ')');

        /**
         * 用戶登入後觸發
         *
         * @param int   $user_id   WordPress 用戶 ID
         * @param array $user_data LINE 用戶資料
         * @param array $tokens    OAuth tokens
         */
        do_action('line_hub/user/logged_in', $user_id, $user_data, $tokens);

        /**
         * 觸發 WordPress 標準登入 hook
         *
         * @param string  $user_login Username
         * @param WP_User $user       WP_User object
         */
        do_action('wp_login', $user->user_login, $user);

        // 取得重定向 URL
        $redirect_url = OAuthState::getRedirect();
        if (empty($redirect_url)) {
            $redirect_url = home_url('/');
        }

        // 使用 Session Transfer Token 重定向
        // 解決跨瀏覽器 cookie 問題：LINE 內建瀏覽器設的 cookie 在外部瀏覽器無效
        $token = SessionTransfer::generate($user_id, $redirect_url);
        $transfer_url = SessionTransfer::buildRedirectUrl($token);

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] Session transfer token generated for user #' . $user_id);

        // 使用 wp_redirect（非 wp_safe_redirect），因為 URL 帶有 query param
        wp_redirect($transfer_url);
        exit;
    }

    /**
     * 顯示 Email 輸入表單
     *
     * @param array $user_data LINE 用戶資料
     * @param array $tokens    OAuth tokens
     * @return void
     */
    private function showEmailForm(array $user_data, array $tokens): void {
        // 產生唯一的暫存 key
        $temp_key = wp_generate_password(32, false);

        // 儲存用戶資料到 Transient（10 分鐘）
        $transient_key = self::EMAIL_TEMP_PREFIX . $temp_key;
        set_transient($transient_key, [
            'user_data' => $user_data,
            'tokens'    => $tokens,
        ], self::EMAIL_TEMP_EXPIRATION);

        // 產生重新授權 URL（AUTH-03）
        $oauth_client = new OAuthClient();
        $reauth_url = $oauth_client->createReauthUrl();

        // 載入 Email 表單模板
        include LINE_HUB_PATH . 'includes/auth/email-form-template.php';
        exit;
    }

    /**
     * 重定向到首頁並顯示錯誤訊息
     *
     * @param string $message 錯誤訊息
     * @return void
     */
    private function redirectWithError(string $message): void {
        // 儲存錯誤訊息到 transient（1 分鐘）
        set_transient('line_hub_login_error_' . get_current_user_id(), $message, 60);

        // 重定向到首頁
        if (wp_safe_redirect(home_url('/'))) {
            exit;
        }
    }
}
