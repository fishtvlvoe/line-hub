<?php
/**
 * OAuth State
 *
 * OAuth 2.0 State 參數管理，提供 CSRF 防護和重定向儲存
 * 參考 NSL (Nextend Social Login) 的 Persistent 模式
 *
 * @package LineHub
 */

namespace LineHub\Auth;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OAuthState 靜態類別
 *
 * 使用 WordPress Transient API 儲存 State，
 * 登入用戶使用 user-based Transient，
 * 匿名用戶使用 session-based Transient（cookie + site_transient）
 */
class OAuthState {

    /**
     * State 字串長度（產生 64 字元 hex 字串）
     */
    private const STATE_LENGTH = 32;

    /**
     * State 過期時間（5 分鐘）
     * 用戶決策：使用較短的過期時間提高安全性
     */
    private const EXPIRATION_SECONDS = 300;

    /**
     * Session cookie 名稱
     */
    private const SESSION_COOKIE_NAME = 'line_hub_session';

    /**
     * Transient 前綴
     */
    private const TRANSIENT_PREFIX = 'line_hub_state_';

    /**
     * Session Transient 前綴
     */
    private const SESSION_TRANSIENT_PREFIX = 'line_hub_session_';

    /**
     * 重定向 Transient 後綴
     */
    private const REDIRECT_SUFFIX = '_redirect';

    /**
     * 產生並儲存新的 State 參數
     *
     * @return string 產生的 State 字串（64 字元 hex）
     */
    public static function generate(): string {
        $state = self::generateRandomState();

        if (is_user_logged_in()) {
            // 登入用戶：使用 user-based Transient
            $key = self::TRANSIENT_PREFIX . get_current_user_id();
            set_transient($key, $state, self::EXPIRATION_SECONDS);
        } else {
            // 匿名用戶：使用 session-based Transient
            $session_id = self::getOrCreateSessionId();
            $key = self::SESSION_TRANSIENT_PREFIX . $session_id;
            set_site_transient($key, ['state' => $state], self::EXPIRATION_SECONDS);
        }

        return $state;
    }

    /**
     * 驗證 State 參數
     *
     * 使用時序安全比較，驗證後立即刪除 State 防止重用
     *
     * @param string $received_state 從 callback 收到的 State
     * @return bool 驗證結果
     */
    public static function validate(string $received_state): bool {
        if (empty($received_state)) {
            return false;
        }

        $stored_state = self::getStoredState();

        if ($stored_state === null) {
            return false;
        }

        // 時序安全比較
        $valid = hash_equals($stored_state, $received_state);

        // 驗證後立即刪除 State（無論成功與否）
        self::deleteState();

        return $valid;
    }

    /**
     * 儲存原始頁面 URL 用於登入後重定向
     *
     * @param string $url 重定向目標 URL
     * @return void
     */
    public static function storeRedirect(string $url): void {
        // 驗證 URL 安全性
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
     * 取得儲存的重定向 URL
     *
     * 取得後刪除（一次性使用）
     *
     * @return string|null 重定向 URL，不存在返回 null
     */
    public static function getRedirect(): ?string {
        $url = null;

        if (is_user_logged_in()) {
            $key = self::TRANSIENT_PREFIX . get_current_user_id() . self::REDIRECT_SUFFIX;
            $url = get_transient($key);
            delete_transient($key);
        } else {
            $session_id = self::getSessionId();
            if ($session_id) {
                $key = self::SESSION_TRANSIENT_PREFIX . $session_id . self::REDIRECT_SUFFIX;
                $url = get_site_transient($key);
                delete_site_transient($key);
            }
        }

        if (empty($url)) {
            return null;
        }

        // 再次驗證 URL 安全性
        $validated = wp_validate_redirect($url, home_url('/'));

        return $validated ?: null;
    }

    /**
     * 產生加密安全隨機 State
     *
     * 使用 random_bytes 產生密碼學安全的隨機數
     *
     * @return string 64 字元 hex 字串
     */
    private static function generateRandomState(): string {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(self::STATE_LENGTH));
        }

        // Fallback（PHP < 7.0 不太可能，但保持向後相容）
        return wp_generate_password(self::STATE_LENGTH * 2, false, false);
    }

    /**
     * 取得或建立 Session ID
     *
     * 匿名用戶使用 cookie 儲存 session ID，
     * 然後用 hash 後的 ID 作為 Transient key
     *
     * 參考 NSL Session.php 模式
     *
     * @return string Hash 後的 session ID
     */
    private static function getOrCreateSessionId(): string {
        // 檢查現有 cookie
        if (isset($_COOKIE[self::SESSION_COOKIE_NAME])) {
            return self::hashSessionId($_COOKIE[self::SESSION_COOKIE_NAME]);
        }

        // 建立新的唯一 ID
        $unique = uniqid('linehub_', true);

        // 設置 cookie（使用 WordPress 標準設定）
        $secure = is_ssl();
        $cookie_path = defined('COOKIEPATH') ? COOKIEPATH : '/';
        $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';

        // Cookie 只在當前 session 有效（瀏覽器關閉即失效）
        // 但 Transient 有獨立的過期時間
        setcookie(
            self::SESSION_COOKIE_NAME,
            $unique,
            0, // Session cookie
            $cookie_path,
            $cookie_domain,
            $secure,
            true // HttpOnly
        );

        // 同時設置到 $_COOKIE 供當前請求使用
        $_COOKIE[self::SESSION_COOKIE_NAME] = $unique;

        return self::hashSessionId($unique);
    }

    /**
     * 取得現有的 Session ID（不建立新的）
     *
     * @return string|null Hash 後的 session ID，不存在返回 null
     */
    private static function getSessionId(): ?string {
        if (isset($_COOKIE[self::SESSION_COOKIE_NAME])) {
            return self::hashSessionId($_COOKIE[self::SESSION_COOKIE_NAME]);
        }

        return null;
    }

    /**
     * Hash session ID
     *
     * 使用 SECURE_AUTH_KEY 增加安全性
     *
     * @param string $session_id 原始 session ID
     * @return string Hash 後的 ID
     */
    private static function hashSessionId(string $session_id): string {
        $salt = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'line-hub-fallback-salt';
        return md5($salt . $session_id);
    }

    /**
     * 取得儲存的 State
     *
     * @return string|null 儲存的 State，不存在返回 null
     */
    private static function getStoredState(): ?string {
        if (is_user_logged_in()) {
            $key = self::TRANSIENT_PREFIX . get_current_user_id();
            $state = get_transient($key);
            return $state !== false ? $state : null;
        }

        // 匿名用戶
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

    /**
     * 刪除已使用的 State
     *
     * @return void
     */
    private static function deleteState(): void {
        if (is_user_logged_in()) {
            $key = self::TRANSIENT_PREFIX . get_current_user_id();
            delete_transient($key);
        } else {
            $session_id = self::getSessionId();
            if ($session_id) {
                $key = self::SESSION_TRANSIENT_PREFIX . $session_id;
                delete_site_transient($key);
            }
        }
    }
}
