<?php
/**
 * Login Service — 用戶登入/註冊服務
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

class LoginService {

    private const USER_PREFIX = 'line_';
    private const USER_FALLBACK = 'user_';
    private const EMAIL_TEMP_PREFIX = 'line_hub_email_temp_';
    private const EMAIL_TEMP_EXPIRATION = 600;

    /**
     * 主要入口：處理 OAuth 認證完成後的帳號建立和登入
     */
    public function handleUser(array $user_data, array $tokens): void {
        $line_uid = $user_data['userId'] ?? '';
        $email = $user_data['email'] ?? '';

        if (empty($line_uid)) {
            error_log('[LINE Hub] LoginService error: missing LINE userId'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            $this->redirectWithError(__('無法取得 LINE 用戶資料', 'line-hub'));
            return;
        }

        // 已綁定 → 直接登入
        $existing_user_id = UserService::getUserByLineUid($line_uid);
        if ($existing_user_id) {
            error_log('[LINE Hub] User already bound, logging in user #' . $existing_user_id); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            $this->loginUser($existing_user_id, $user_data, $tokens);
            return;
        }

        // 無 Email → 顯示表單
        if (empty($email)) {
            error_log('[LINE Hub] No email provided, showing email form'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            $this->showEmailForm($user_data, $tokens);
            return;
        }

        // Email 已存在 → 綁定現有帳號
        $existing_user = get_user_by('email', $email);
        if ($existing_user) {
            $this->bindAndLogin($existing_user->ID, $line_uid, $user_data, $tokens);
            return;
        }

        // 建立新帳號
        $this->createAndLogin($line_uid, $user_data, $tokens);
    }

    /**
     * 處理 Email 表單提交
     */
    public function handleEmailSubmit(): void {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        if (!isset($_POST['_wpnonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'line_hub_email_submit')) {
            $this->redirectWithError(__('安全驗證失敗，請重試', 'line-hub'));
            return;
        }

        $temp_key = isset($_POST['temp_key']) ? sanitize_text_field(wp_unslash($_POST['temp_key'])) : '';
        if (empty($temp_key)) {
            $this->redirectWithError(__('登入逾時，請重新登入', 'line-hub'));
            return;
        }

        $temp_data = get_transient(self::EMAIL_TEMP_PREFIX . $temp_key);
        if (!$temp_data || !is_array($temp_data)) {
            $this->redirectWithError(__('登入逾時，請重新登入', 'line-hub'));
            return;
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if (empty($email) || !is_email($email)) {
            $this->redirectWithError(__('請輸入有效的 Email 地址', 'line-hub'));
            return;
        }

        delete_transient(self::EMAIL_TEMP_PREFIX . $temp_key);

        $user_data = $temp_data['user_data'];
        $user_data['email'] = $email;
        error_log('[LINE Hub] Email submitted: ' . $email); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

        $this->handleUser($user_data, $temp_data['tokens']);
    }

    /**
     * 產生唯一的 WordPress username
     */
    public static function generateUsername(string $display_name): string {
        $base = strtolower(str_replace(' ', '', $display_name));
        $base = sanitize_user($base, true);

        $base = empty($base)
            ? self::USER_FALLBACK . substr(md5(uniqid('', true)), 0, 8)
            : self::USER_PREFIX . $base;

        $base = substr($base, 0, 55);
        $username = $base;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base . $counter;
            if (++$counter > 1000) {
                $username = self::USER_FALLBACK . substr(md5(uniqid('', true)), 0, 8);
                break;
            }
        }

        return $username;
    }

    // ── Private helpers ──────────────────────────────────

    private function bindAndLogin(int $user_id, string $line_uid, array $user_data, array $tokens): void {
        error_log('[LINE Hub] Email exists, binding LINE to user #' . $user_id); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        $link_result = UserService::linkUser($user_id, $line_uid, [
            'displayName' => $user_data['displayName'] ?? '',
            'pictureUrl'  => $user_data['pictureUrl'] ?? '',
            'email'       => $user_data['email'] ?? '',
        ]);
        if (is_wp_error($link_result)) {
            $this->redirectWithError($link_result->get_error_message());
            return;
        }
        $this->loginUser($user_id, $user_data, $tokens);
    }

    private function createAndLogin(string $line_uid, array $user_data, array $tokens): void {
        $new_user_id = $this->createUser($user_data);
        if (is_wp_error($new_user_id)) {
            $this->redirectWithError($new_user_id->get_error_message());
            return;
        }

        UserService::linkUser($new_user_id, $line_uid, [
            'displayName' => $user_data['displayName'] ?? '',
            'pictureUrl'  => $user_data['pictureUrl'] ?? '',
            'email'       => $user_data['email'] ?? '',
        ]);

        $this->loginUser($new_user_id, $user_data, $tokens);
    }

    private function createUser(array $user_data): int|\WP_Error {
        $display_name = $user_data['displayName'] ?? '';
        $email = $user_data['email'] ?? '';
        $username = self::generateUsername($display_name);

        error_log('[LINE Hub] Creating user: ' . $username . ' (' . $email . ')'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

        $default_roles = SettingsService::get('general', 'default_roles', ['subscriber']);
        if (empty($default_roles) || !is_array($default_roles)) {
            $default_roles = ['subscriber'];
        }

        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => wp_generate_password(12, false),
            'display_name' => !empty($display_name) ? $display_name : $username,
            'first_name'   => !empty($display_name) ? $display_name : '',
            'nickname'     => !empty($display_name) ? $display_name : $username,
            'role'         => $default_roles[0],
        ]);

        if (is_wp_error($user_id)) {
            error_log('[LINE Hub] Failed to create user: ' . $user_id->get_error_message()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return $user_id;
        }

        if (count($default_roles) > 1) {
            $user = get_user_by('ID', $user_id);
            if ($user) {
                for ($i = 1; $i < count($default_roles); $i++) {
                    $user->add_role($default_roles[$i]);
                }
            }
        }

        update_user_meta($user_id, 'default_password_nag', true);
        if (!empty($user_data['pictureUrl'])) {
            update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($user_data['pictureUrl']));
        }

        error_log('[LINE Hub] User created: #' . $user_id); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        do_action('line_hub/user/registered', $user_id, $user_data);
        return $user_id;
    }

    private function loginUser(int $user_id, array $user_data, array $tokens): void {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            $this->redirectWithError(__('用戶不存在', 'line-hub'));
            return;
        }

        UserService::updateProfile($user_id, [
            'displayName' => $user_data['displayName'] ?? '',
            'pictureUrl'  => $user_data['pictureUrl'] ?? '',
            'email'       => $user_data['email'] ?? '',
        ]);

        if (!empty($user_data['pictureUrl'])) {
            update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($user_data['pictureUrl']));
        }

        $store_token = SettingsService::get('login', 'store_access_token', false);
        if ($store_token && !empty($tokens['access_token'])) {
            update_user_meta($user_id, 'line_hub_access_token', $tokens['access_token']);
            if (!empty($tokens['expires_in'])) {
                update_user_meta($user_id, 'line_hub_token_expires', time() + intval($tokens['expires_in']));
            }
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());

        error_log('[LINE Hub] User logged in: #' . $user_id . ' (' . $user->user_login . ')'); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        do_action('line_hub/user/logged_in', $user_id, $user_data, $tokens);
        do_action('wp_login', $user->user_login, $user);

        $redirect_url = OAuthState::getRedirect() ?: home_url('/');
        $token = SessionTransfer::generate($user_id, $redirect_url);

        error_log('[LINE Hub] Session transfer token generated for user #' . $user_id); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        wp_safe_redirect(SessionTransfer::buildRedirectUrl($token)); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        exit;
    }

    private function showEmailForm(array $user_data, array $tokens): void {
        $temp_key = wp_generate_password(32, false);
        set_transient(self::EMAIL_TEMP_PREFIX . $temp_key, [
            'user_data' => $user_data,
            'tokens'    => $tokens,
        ], self::EMAIL_TEMP_EXPIRATION);

        $oauth_client = new OAuthClient();
        $reauth_url = $oauth_client->createReauthUrl();
        include LINE_HUB_PATH . 'includes/auth/email-form-template.php';
        exit;
    }

    private function redirectWithError(string $message): void {
        set_transient('line_hub_login_error_' . get_current_user_id(), $message, 60);
        if (wp_safe_redirect(home_url('/'))) {
            exit;
        }
    }
}
