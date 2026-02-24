<?php
/**
 * OAuth State — CSRF 防護和重定向儲存
 *
 * @package LineHub
 */

namespace LineHub\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class OAuthState {

    private const STATE_LENGTH = 32;
    private const EXPIRATION_SECONDS = 300;
    private const SESSION_COOKIE_NAME = 'line_hub_session';
    private const TRANSIENT_PREFIX = 'line_hub_state_';
    private const SESSION_TRANSIENT_PREFIX = 'line_hub_session_';
    private const REDIRECT_SUFFIX = '_redirect';

    /**
     * 產生並儲存新的 State 參數
     */
    public static function generate(): string {
        $state = self::generateRandomState();

        if (is_user_logged_in()) {
            $key = self::TRANSIENT_PREFIX . get_current_user_id();
            set_transient($key, $state, self::EXPIRATION_SECONDS);
        } else {
            $session_id = self::getOrCreateSessionId();
            $key = self::SESSION_TRANSIENT_PREFIX . $session_id;
            set_site_transient($key, ['state' => $state], self::EXPIRATION_SECONDS);
        }

        return $state;
    }

    /**
     * 驗證 State 參數（時序安全比較，驗證後刪除）
     */
    public static function validate(string $received_state): bool {
        if (empty($received_state)) {
            return false;
        }

        $stored_state = self::getStoredState();
        if ($stored_state === null) {
            return false;
        }

        $valid = hash_equals($stored_state, $received_state);
        self::deleteState();

        return $valid;
    }

    /**
     * 儲存重定向 URL
     */
    public static function storeRedirect(string $url): void {
        $url = wp_validate_redirect($url, '');
        if (empty($url)) {
            return;
        }

        if (is_user_logged_in()) {
            $key = self::TRANSIENT_PREFIX . get_current_user_id() . self::REDIRECT_SUFFIX;
            set_transient($key, $url, self::EXPIRATION_SECONDS);
        } else {
            $session_id = self::getOrCreateSessionId();
            $key = self::SESSION_TRANSIENT_PREFIX . $session_id . self::REDIRECT_SUFFIX;
            set_site_transient($key, $url, self::EXPIRATION_SECONDS);
        }
    }

    /**
     * 取得並刪除儲存的重定向 URL
     */
    public static function getRedirect(): ?string {
        $url = null;

        if (is_user_logged_in()) {
            $key = self::TRANSIENT_PREFIX . get_current_user_id() . self::REDIRECT_SUFFIX;
            $url = get_transient($key);
            if ($url !== false) {
                delete_transient($key);
            }
        }

        // user-based 沒找到 → 查 session-based
        if (empty($url)) {
            $session_id = self::getSessionId();
            if ($session_id) {
                $key = self::SESSION_TRANSIENT_PREFIX . $session_id . self::REDIRECT_SUFFIX;
                $url = get_site_transient($key);
                if ($url !== false) {
                    delete_site_transient($key);
                }
            }
        }

        if (empty($url)) {
            return null;
        }

        $validated = wp_validate_redirect($url, home_url('/'));
        return $validated ?: null;
    }

    // ── Private ──────────────────────────────────────────

    private static function generateRandomState(): string {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(self::STATE_LENGTH));
        }
        return wp_generate_password(self::STATE_LENGTH * 2, false, false);
    }

    /**
     * 取得或建立 Session ID（匿名用戶用 cookie + hash）
     */
    private static function getOrCreateSessionId(): string {
        if (isset($_COOKIE[self::SESSION_COOKIE_NAME])) {
            return self::hashSessionId($_COOKIE[self::SESSION_COOKIE_NAME]);
        }

        $unique = uniqid('linehub_', true);
        $cookie_path = defined('COOKIEPATH') ? COOKIEPATH : '/';
        $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

        setcookie(self::SESSION_COOKIE_NAME, $unique, 0, $cookie_path, $cookie_domain, is_ssl(), true);
        $_COOKIE[self::SESSION_COOKIE_NAME] = $unique;

        return self::hashSessionId($unique);
    }

    private static function getSessionId(): ?string {
        if (isset($_COOKIE[self::SESSION_COOKIE_NAME])) {
            return self::hashSessionId($_COOKIE[self::SESSION_COOKIE_NAME]);
        }
        return null;
    }

    private static function hashSessionId(string $session_id): string {
        $salt = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'line-hub-fallback-salt';
        return md5($salt . $session_id);
    }

    private static function getStoredState(): ?string {
        if (is_user_logged_in()) {
            $key = self::TRANSIENT_PREFIX . get_current_user_id();
            $state = get_transient($key);
            return $state !== false ? $state : null;
        }

        $session_id = self::getSessionId();
        if (!$session_id) {
            return null;
        }

        $key = self::SESSION_TRANSIENT_PREFIX . $session_id;
        $data = get_site_transient($key);

        if ($data === false || !is_array($data) || !isset($data['state'])) {
            return null;
        }

        return $data['state'];
    }

    private static function deleteState(): void {
        if (is_user_logged_in()) {
            delete_transient(self::TRANSIENT_PREFIX . get_current_user_id());
        } else {
            $session_id = self::getSessionId();
            if ($session_id) {
                delete_site_transient(self::SESSION_TRANSIENT_PREFIX . $session_id);
            }
        }
    }
}
