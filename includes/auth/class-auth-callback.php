<?php
/**
 * Auth Callback
 *
 * OAuth 認證流程處理器，處理發起認證、接收回調、驗證 State、顯示錯誤
 *
 * @package LineHub
 */

namespace LineHub\Auth;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AuthCallback 類別
 *
 * 處理 OAuth 認證流程的完整生命週期：
 * - 發起認證（重定向到 LINE）
 * - 處理回調（驗證 State、交換 Token）
 * - 顯示錯誤（友善的用戶訊息）
 */
class AuthCallback {

    /**
     * 錯誤訊息對應表
     * LINE OAuth 錯誤碼 => 用戶友善訊息
     *
     * @var array<string, string>
     */
    private const ERROR_MESSAGES = [
        'access_denied'   => '您已取消登入',
        'invalid_request' => '登入請求無效，請重試',
        'server_error'    => 'LINE 伺服器暫時無法連線，請稍後再試',
        'state_expired'   => '登入逾時，請重新登入',
    ];

    /**
     * 主要入口點，處理 /line-hub/auth/ 相關請求
     *
     * 路由邏輯：
     * 1. 有 error 參數 → handleError()
     * 2. 有 code + state 參數 → processCallback()
     * 3. 其他情況 → initiateAuth()
     *
     * @return void
     */
    public function handleRequest(): void {
        try {
            // 檢查是否有 LINE 回傳的錯誤
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $error = isset($_GET['error']) ? sanitize_text_field(wp_unslash($_GET['error'])) : '';
            if (!empty($error)) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $error_description = isset($_GET['error_description'])
                    ? sanitize_text_field(wp_unslash($_GET['error_description']))
                    : $error;
                $this->handleError($error, $error_description);
                return;
            }

            // 檢查是否是 OAuth 回調（有 code 和 state）
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';

            if (!empty($code) && !empty($state)) {
                $this->processCallback($code, $state);
                return;
            }

            // 預設：發起 OAuth 認證
            $this->initiateAuth();

        } catch (\Exception $e) {
            // 記錄錯誤並顯示友善訊息
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] AuthCallback error: ' . $e->getMessage());
            $this->handleError('exception', $e->getMessage());
        }
    }

    /**
     * 發起 OAuth 認證
     *
     * 儲存重定向 URL（如果有），然後重定向到 LINE 授權頁面
     *
     * @return void
     */
    public function initiateAuth(): void {
        // 儲存原始頁面 URL（用於登入後重定向）
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $redirect = isset($_GET['redirect']) ? esc_url_raw(wp_unslash($_GET['redirect'])) : '';
        if (!empty($redirect)) {
            OAuthState::storeRedirect($redirect);
        }

        // 建立 OAuthClient 並取得授權 URL
        $client = new OAuthClient();

        // 檢查設定是否完整
        if (!$client->isConfigured()) {
            $this->handleError(
                'not_configured',
                __('LINE 登入尚未設定，請聯繫網站管理員。', 'line-hub')
            );
            return;
        }

        // 取得授權 URL（OAuthClient::createAuthUrl 會自動產生並儲存 State）
        $auth_url = $client->createAuthUrl();

        // 記錄認證發起
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] Initiating OAuth, redirecting to LINE');

        // 重定向到 LINE 授權頁面
        if (wp_redirect($auth_url)) {
            exit;
        }
    }

    /**
     * 處理 OAuth 回調
     *
     * 驗證 State → 交換 Token → 驗證 ID Token → 取得 Profile
     *
     * @param string $code Authorization code from LINE
     * @param string $state State parameter for CSRF validation
     * @return void
     * @throws \Exception 當驗證失敗時
     */
    public function processCallback(string $code, string $state): void {
        // 驗證 State（CSRF 防護）
        if (!OAuthState::validate($state)) {
            throw new \Exception(self::ERROR_MESSAGES['state_expired']);
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] State validated, exchanging code for tokens');

        // 建立 OAuthClient
        $client = new OAuthClient();

        // 交換 code 取得 tokens
        $tokens = $client->authenticate($code);

        // 確認有必要的 tokens
        if (empty($tokens['access_token'])) {
            throw new \Exception(__('Token 交換失敗，請重試', 'line-hub'));
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] Tokens received, verifying ID token and getting profile');

        // 驗證 ID Token 取得用戶資料（可能包含 email）
        $id_token_data = [];
        if (!empty($tokens['id_token'])) {
            $id_token_data = $client->verifyIdToken($tokens['id_token']);
        }

        // 取得 LINE Profile
        $profile = $client->getProfile($tokens['access_token']);

        if (empty($profile['userId'])) {
            throw new \Exception(__('無法取得 LINE 用戶資料，請重試', 'line-hub'));
        }

        // 合併資料準備給 LoginService
        $user_data = [
            'userId'      => $profile['userId'],
            'displayName' => $profile['displayName'] ?? '',
            'pictureUrl'  => $profile['pictureUrl'] ?? '',
            'email'       => $id_token_data['email'] ?? '',
        ];

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] OAuth complete for user: ' . substr($profile['userId'], 0, 4) . '****' . substr($profile['userId'], -4));

        // 儲存 tokens 供 LoginService 使用
        $tokens_for_service = [
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? '',
            'expires_in'    => $tokens['expires_in'] ?? 0,
        ];

        // TODO: 由 Plan 03-03 實作 - 呼叫 LoginService::handleUser($user_data, $tokens)
        // $login_service = new \LineHub\Services\LoginService();
        // $login_service->handleUser($user_data, $tokens_for_service);

        // 臨時處理：顯示除錯資訊（等待 LoginService 實作）
        $this->showDebugPage($user_data);
    }

    /**
     * 處理錯誤
     *
     * 顯示用戶友善的錯誤頁面，提供重新登入連結
     *
     * @param string $error_code 錯誤代碼
     * @param string $error_description 詳細錯誤描述（用於日誌）
     * @return void
     */
    private function handleError(string $error_code, string $error_description = ''): void {
        // 記錄錯誤到 debug.log
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(sprintf(
            '[LINE Hub] OAuth error: code=%s, description=%s',
            $error_code,
            $error_description
        ));

        // 取得用戶友善訊息
        $user_message = self::ERROR_MESSAGES[$error_code] ?? self::ERROR_MESSAGES['server_error'] ?? __('登入時發生錯誤，請重試', 'line-hub');

        // 產生重新登入連結
        $retry_url = home_url('/line-hub/auth/');

        // 顯示錯誤頁面
        $html = $this->renderErrorPage($user_message, $retry_url);

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_die(
            $html,
            __('登入錯誤', 'line-hub'),
            [
                'response'  => 400,
                'back_link' => false,
            ]
        );
    }

    /**
     * 渲染錯誤頁面 HTML
     *
     * @param string $message 錯誤訊息
     * @param string $retry_url 重試連結
     * @return string HTML 內容
     */
    private function renderErrorPage(string $message, string $retry_url): string {
        $home_url = home_url('/');

        return sprintf(
            '<div style="max-width: 400px; margin: 50px auto; padding: 30px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
                <div style="width: 60px; height: 60px; margin: 0 auto 20px; border-radius: 50%%; background: #fee2e2; display: flex; align-items: center; justify-content: center;">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                </div>
                <h2 style="margin: 0 0 10px; color: #1f2937; font-size: 20px; font-weight: 600;">%s</h2>
                <p style="margin: 0 0 25px; color: #6b7280; font-size: 14px;">%s</p>
                <a href="%s" style="display: inline-block; padding: 12px 24px; background: #00B900; color: white; text-decoration: none; border-radius: 6px; font-weight: 500; margin-right: 10px;">%s</a>
                <a href="%s" style="display: inline-block; padding: 12px 24px; background: #f3f4f6; color: #374151; text-decoration: none; border-radius: 6px; font-weight: 500;">%s</a>
            </div>',
            esc_html__('登入失敗', 'line-hub'),
            esc_html($message),
            esc_url($retry_url),
            esc_html__('重新登入', 'line-hub'),
            esc_url($home_url),
            esc_html__('返回首頁', 'line-hub')
        );
    }

    /**
     * 顯示除錯頁面（臨時，等待 LoginService 實作）
     *
     * @param array $user_data 用戶資料
     * @return void
     */
    private function showDebugPage(array $user_data): void {
        $masked_id = substr($user_data['userId'], 0, 4) . '****' . substr($user_data['userId'], -4);
        $display_name = esc_html($user_data['displayName'] ?: 'N/A');
        $email = esc_html($user_data['email'] ?: '（未提供）');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_die(
            sprintf(
                '<div style="max-width: 500px; margin: 50px auto; padding: 30px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
                    <div style="background: #d1fae5; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <h2 style="margin: 0 0 15px; color: #065f46;">OAuth 流程完成</h2>
                        <p style="margin: 0; color: #047857;">等待 LoginService 實作（Plan 03-03）</p>
                    </div>
                    <div style="background: #f3f4f6; border-radius: 8px; padding: 20px;">
                        <h3 style="margin: 0 0 15px; color: #1f2937;">用戶資料</h3>
                        <table style="width: 100%%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; color: #6b7280;">LINE User ID</td>
                                <td style="padding: 8px 0; color: #1f2937; font-family: monospace;">%s</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #6b7280;">顯示名稱</td>
                                <td style="padding: 8px 0; color: #1f2937;">%s</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; color: #6b7280;">Email</td>
                                <td style="padding: 8px 0; color: #1f2937;">%s</td>
                            </tr>
                        </table>
                    </div>
                    <p style="margin-top: 20px; text-align: center;">
                        <a href="%s" style="color: #2563eb; text-decoration: none;">返回首頁</a>
                    </p>
                </div>',
                $masked_id,
                $display_name,
                $email,
                esc_url(home_url('/'))
            ),
            __('OAuth 完成', 'line-hub'),
            ['response' => 200]
        );
    }
}
