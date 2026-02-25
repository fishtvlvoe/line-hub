<?php
/**
 * LIFF API Client
 *
 * LINE API 呼叫和用戶建立邏輯
 *
 * @package LineHub
 */

namespace LineHub\Liff;

use LineHub\LineApiEndpoints;
use LineHub\Services\LoginService;
use LineHub\Services\SettingsService;
use LineHub\Services\UserService;

if (!defined('ABSPATH')) {
    exit;
}

class LiffApiClient {

    private const REQUEST_TIMEOUT = 15;

    /**
     * 驗證 Access Token 並取得 Profile
     */
    public function verifyAndGetProfile(string $access_token): array|\WP_Error {
        $verify = $this->verifyAccessToken($access_token);
        if (is_wp_error($verify)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF token verify failed: ' . $verify->get_error_message());
            return new \WP_Error('verify_failed', __('Access Token verification failed.', 'line-hub'));
        }

        $profile = $this->getProfile($access_token);
        if (is_wp_error($profile)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF profile fetch failed: ' . $profile->get_error_message());
            return new \WP_Error('profile_failed', __('Unable to retrieve LINE user profile.', 'line-hub'));
        }

        return $profile;
    }

    /**
     * 驗證 LIFF Access Token
     */
    public function verifyAccessToken(string $access_token): bool|\WP_Error {
        $response = wp_remote_get(
            LineApiEndpoints::OAUTH_VERIFY . '?access_token=' . urlencode($access_token),
            ['timeout' => self::REQUEST_TIMEOUT]
        );

        if (is_wp_error($response)) {
            return new \WP_Error('verify_failed', $response->get_error_message());
        }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new \WP_Error('token_invalid', 'Access Token 無效或已過期');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (($body['expires_in'] ?? 0) <= 0) {
            return new \WP_Error('token_expired', 'Access Token 已過期');
        }

        return true;
    }

    /**
     * 取得 LINE Profile
     */
    public function getProfile(string $access_token): array|\WP_Error {
        $response = wp_remote_get(LineApiEndpoints::PROFILE, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
        ]);

        if (is_wp_error($response)) {
            return new \WP_Error('profile_failed', $response->get_error_message());
        }
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new \WP_Error('profile_error', 'LINE Profile API 回應錯誤');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : new \WP_Error('profile_parse', '無法解析回應');
    }

    /**
     * 建立新 WordPress 用戶並綁定 LINE
     */
    public function createNewUser(string $line_uid, string $display_name, string $picture_url, string $email): int|\WP_Error {
        $username = LoginService::generateUsername($display_name);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF creating user: ' . $username . ' (' . $email . ')');

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
            return $user_id;
        }

        $this->setupNewUser($user_id, $line_uid, $display_name, $picture_url, $default_roles);
        return $user_id;
    }

    private function setupNewUser(int $user_id, string $line_uid, string $display_name, string $picture_url, array $default_roles): void {
        // 指派額外角色
        if (count($default_roles) > 1) {
            $user = get_user_by('ID', $user_id);
            if ($user) {
                for ($i = 1; $i < count($default_roles); $i++) {
                    $user->add_role($default_roles[$i]);
                }
            }
        }

        // 設定 meta 和綁定
        update_user_meta($user_id, 'line_hub_login_method', 'liff');
        update_user_meta($user_id, 'default_password_nag', true);
        if (!empty($picture_url)) {
            update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($picture_url));
        }

        $link_result = UserService::linkUser($user_id, $line_uid, [
            'displayName' => $display_name, 'pictureUrl' => $picture_url,
        ]);
        if (is_wp_error($link_result)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF link failed: ' . $link_result->get_error_message());
        }

        do_action('line_hub/user/registered', $user_id, [
            'userId' => $line_uid, 'displayName' => $display_name, 'pictureUrl' => $picture_url,
        ]);
    }

    /**
     * 自動遷移 NSL 綁定
     */
    public function migrateBindingIfNeeded(int $user_id, string $line_uid, string $display_name, string $picture_url): void {
        if (UserService::hasDirectBinding($user_id)) {
            return;
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF: migrating NSL binding for user #' . $user_id);

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'line_hub_users',
            [
                'user_id' => $user_id, 'line_uid' => $line_uid,
                'display_name' => sanitize_text_field($display_name),
                'picture_url' => esc_url_raw($picture_url),
                'status' => 'active', 'register_date' => current_time('mysql'),
                'link_date' => current_time('mysql'), 'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * 登入用戶並重定向
     */
    public function loginAndRedirect(int $user_id, array $profile, string $access_token, string $redirect): void {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());

        UserService::updateProfile($user_id, [
            'displayName' => $profile['displayName'] ?? '',
            'pictureUrl'  => $profile['pictureUrl'] ?? '',
        ]);

        $picture_url = $profile['pictureUrl'] ?? '';
        if (!empty($picture_url)) {
            update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($picture_url));
        }

        $user = get_user_by('ID', $user_id);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF login success: #' . $user_id . ' (' . ($user ? $user->user_login : 'unknown') . ')');

        do_action('line_hub/liff/logged_in', $user_id, $profile, $access_token);
        do_action('line_hub/user/logged_in', $user_id, $profile, ['access_token' => $access_token]);
        if ($user) {
            do_action('wp_login', $user->user_login, $user);
        }

        setcookie('line_hub_welcome', '1', time() + 30, '/', '', is_ssl(), false);
        wp_safe_redirect($redirect);
        exit;
    }
}
