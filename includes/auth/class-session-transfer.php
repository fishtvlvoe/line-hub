<?php
/**
 * Session Transfer
 *
 * 解決跨瀏覽器登入問題：
 * 手機外部瀏覽器 → LINE app 授權 → callback 在 LINE 瀏覽器開啟
 * → cookie 設在 LINE 瀏覽器 → 原始瀏覽器沒有 cookie → 登入失敗
 *
 * 解法：用一次性 token 取代直接設 cookie，
 * redirect 時帶 token 參數 → 任何瀏覽器都能用 token 交換 session
 *
 * @package LineHub
 */

namespace LineHub\Auth;

if (!defined('ABSPATH')) {
    exit;
}

class SessionTransfer {

    /**
     * Token 長度（產生 48 字元 hex）
     */
    private const TOKEN_LENGTH = 24;

    /**
     * Token 過期時間（60 秒，僅供一次性交換）
     */
    private const TOKEN_EXPIRATION = 60;

    /**
     * Transient 前綴
     */
    private const TRANSIENT_PREFIX = 'line_hub_st_';

    /**
     * Query parameter 名稱
     */
    public const QUERY_PARAM = 'line_hub_token';

    /**
     * 產生 Session Transfer Token
     *
     * @param int    $user_id      WordPress 用戶 ID
     * @param string $redirect_url 最終重定向目標
     * @return string 產生的 token（48 字元 hex）
     */
    public static function generate(int $user_id, string $redirect_url): string {
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));

        // 驗證 redirect URL 安全性（防止 Open Redirect）
        $redirect_url = wp_validate_redirect($redirect_url, home_url('/'));

        $data = [
            'user_id'      => $user_id,
            'redirect_url' => $redirect_url,
            'created_at'   => time(),
        ];

        // 用 site_transient 儲存（不依賴用戶 session）
        set_site_transient(
            self::TRANSIENT_PREFIX . hash('sha256', $token),
            $data,
            self::TOKEN_EXPIRATION
        );

        return $token;
    }

    /**
     * 驗證並消費 Token（一次性）
     *
     * @param string $token 收到的 token
     * @return array|null 成功返回 ['user_id' => int, 'redirect_url' => string]，失敗返回 null
     */
    public static function validate(string $token): ?array {
        if (empty($token) || strlen($token) !== self::TOKEN_LENGTH * 2) {
            return null;
        }

        $key = self::TRANSIENT_PREFIX . hash('sha256', $token);
        $data = get_site_transient($key);

        // 立即刪除（一次性使用）
        delete_site_transient($key);

        if (!is_array($data) || empty($data['user_id'])) {
            return null;
        }

        // 檢查用戶是否仍存在
        $user = get_user_by('ID', $data['user_id']);
        if (!$user) {
            return null;
        }

        return [
            'user_id'      => (int) $data['user_id'],
            'redirect_url' => $data['redirect_url'] ?? home_url('/'),
        ];
    }

    /**
     * 建立帶 token 的重定向 URL
     *
     * Token 交換端點固定為首頁 + query param，
     * 這樣無論 rewrite rules 狀態如何都能運作
     *
     * @param string $token Session transfer token
     * @return string 完整的重定向 URL
     */
    public static function buildRedirectUrl(string $token): string {
        return add_query_arg(self::QUERY_PARAM, $token, home_url('/'));
    }

    /**
     * 處理 Token 交換請求
     *
     * 在 template_redirect 中呼叫，驗證 token → 設 cookie → 重定向到乾淨 URL
     *
     * @return bool 是否處理了請求
     */
    public static function handleExchange(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token = isset($_GET[self::QUERY_PARAM])
            ? sanitize_text_field(wp_unslash($_GET[self::QUERY_PARAM]))
            : '';

        if (empty($token)) {
            return false;
        }

        // 驗證 token（不論是否已登入都要讀取 redirect URL）
        $result = self::validate($token);

        // 如果已經登入（同瀏覽器 OAuth 流程會在 callback 時設 cookie）
        if (is_user_logged_in()) {
            // 從 token 取得 redirect URL，而非直接導向首頁
            $redirect = ($result && !empty($result['redirect_url']))
                ? $result['redirect_url']
                : home_url('/');

            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] Session transfer: already logged in, redirecting to ' . $redirect);

            wp_safe_redirect($redirect);
            exit;
        }

        if (!$result) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] Session transfer failed: invalid or expired token');

            // Token 無效 → 重定向到首頁（不顯示錯誤，避免混淆）
            wp_safe_redirect(home_url('/'));
            exit;
        }

        // 設置 WordPress 登入 cookie
        wp_set_current_user($result['user_id']);
        wp_set_auth_cookie($result['user_id'], true, is_ssl());

        // 設置歡迎 cookie（觸發 Toast 通知）
        setcookie('line_hub_welcome', '1', 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false);

        $user = get_user_by('ID', $result['user_id']);
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(sprintf(
            '[LINE Hub] Session transfer success: user #%d (%s)',
            $result['user_id'],
            $user ? $user->user_login : 'unknown'
        ));

        /**
         * Session transfer 完成後觸發
         *
         * @param int    $user_id      WordPress 用戶 ID
         * @param string $redirect_url 最終重定向目標
         */
        do_action('line_hub/session_transfer/completed', $result['user_id'], $result['redirect_url']);

        // 重定向到目標 URL
        wp_safe_redirect($result['redirect_url']);
        exit;
    }
}
