<?php
/**
 * OAuth Client
 *
 * LINE OAuth 2.0 客戶端，封裝 LINE Login API 通訊
 *
 * @package LineHub
 */

namespace LineHub\Auth;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OAuthClient 類別
 *
 * 負責 LINE OAuth 2.0 流程：
 * - 產生授權 URL
 * - 交換 authorization code 取得 tokens
 * - 驗證 ID Token
 * - 取得用戶 Profile
 */
class OAuthClient {

    /**
     * LINE 授權端點
     */
    private const AUTH_ENDPOINT = 'https://access.line.me/oauth2/v2.1/authorize';

    /**
     * LINE Token 端點
     */
    private const TOKEN_ENDPOINT = 'https://api.line.me/oauth2/v2.1/token';

    /**
     * LINE ID Token 驗證端點
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
     * OAuth 授權範圍
     */
    private const DEFAULT_SCOPE = 'profile openid email';

    /**
     * LINE Channel ID
     *
     * @var string
     */
    private string $client_id;

    /**
     * LINE Channel Secret
     *
     * @var string
     */
    private string $client_secret;

    /**
     * OAuth Callback URL
     *
     * @var string
     */
    private string $redirect_uri;

    /**
     * 建構函式
     *
     * 從 SettingsService 取得 LINE Login 設定
     */
    public function __construct() {
        // 優先使用 LINE Login 專用設定，若為空則 fallback 到 Messaging API 設定（向下兼容）
        $login_id = SettingsService::get('general', 'login_channel_id', '');
        $this->client_id = !empty($login_id) ? $login_id : SettingsService::get('general', 'channel_id', '');

        $login_secret = SettingsService::get('general', 'login_channel_secret', '');
        $this->client_secret = !empty($login_secret) ? $login_secret : SettingsService::get('general', 'channel_secret', '');

        $this->redirect_uri = $this->getRedirectUri();
    }

    /**
     * 產生 LINE 授權 URL
     *
     * @param array $options 可選參數：
     *   - bot_prompt: 'normal' | 'aggressive' - LINE Official Account 好友新增提示
     *   - initial_amr_display: 'lineqr' | 'lineautologin' - 初始登入方法
     * @return string 完整的授權 URL
     */
    public function createAuthUrl(array $options = []): string {
        // 產生並儲存 State
        $state = OAuthState::generate();

        // 組裝基本參數
        $params = [
            'response_type' => 'code',
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'state'         => $state,
            'scope'         => self::DEFAULT_SCOPE,
        ];

        // 從設定取得 bot_prompt（如果未在 options 指定）
        $bot_prompt = $options['bot_prompt'] ?? SettingsService::get('login', 'bot_prompt', '');
        if (!empty($bot_prompt)) {
            $params['bot_prompt'] = $bot_prompt;
        }

        // 初始登入方法（QR code 或自動登入）
        $initial_amr = $options['initial_amr_display'] ?? SettingsService::get('login', 'initial_amr', '');
        if (!empty($initial_amr)) {
            $params['initial_amr_display'] = $initial_amr;
        }

        // 預設停用自動登入，強制手動認證
        $allow_auto_login = SettingsService::get('login', 'allow_auto_login', false);
        if (!$allow_auto_login) {
            $params['disable_auto_login'] = 'true';
            $params['disable_ios_auto_login'] = 'true';
        }

        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * 產生強制重新授權 URL
     *
     * 用於用戶需要重新授權 Email 權限等情況（AUTH-03）
     *
     * @return string 包含 prompt=consent 的授權 URL
     */
    public function createReauthUrl(): string {
        // 產生並儲存 State
        $state = OAuthState::generate();

        $params = [
            'response_type' => 'code',
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'state'         => $state,
            'scope'         => self::DEFAULT_SCOPE,
            'prompt'        => 'consent', // 強制顯示授權畫面
        ];

        // 停用自動登入
        $params['disable_auto_login'] = 'true';
        $params['disable_ios_auto_login'] = 'true';

        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * 使用 authorization code 交換 tokens
     *
     * @param string $code Authorization code from LINE callback
     * @return array Tokens 陣列，包含 access_token, id_token, refresh_token
     * @throws \Exception 當 HTTP 請求失敗或 LINE 返回錯誤時
     */
    public function authenticate(string $code): array {
        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'timeout' => self::REQUEST_TIMEOUT,
            'body'    => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->redirect_uri,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
            ],
        ]);

        // 檢查 WP HTTP 錯誤
        if (is_wp_error($response)) {
            throw new \Exception(
                sprintf(
                    /* translators: %s: error message */
                    __('LINE API 連線失敗：%s', 'line-hub'),
                    $response->get_error_message()
                )
            );
        }

        // 檢查 HTTP 狀態碼
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_description = $body['error_description'] ?? $body['error'] ?? __('Token 交換失敗', 'line-hub');
            throw new \Exception($error_description);
        }

        return $body;
    }

    /**
     * 驗證 ID Token 並取得用戶資料
     *
     * 使用 LINE verify endpoint 驗證 ID Token 的有效性
     *
     * @param string $id_token LINE ID Token
     * @return array 驗證結果，包含 sub, name, email 等；失敗返回空陣列
     */
    public function verifyIdToken(string $id_token): array {
        $response = wp_remote_post(self::VERIFY_ENDPOINT, [
            'timeout' => self::REQUEST_TIMEOUT,
            'body'    => [
                'id_token'  => $id_token,
                'client_id' => $this->client_id,
            ],
        ]);

        // HTTP 錯誤時返回空陣列
        if (is_wp_error($response)) {
            return [];
        }

        // 非 200 回應時返回空陣列（ID Token 無效或過期）
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($body) ? $body : [];
    }

    /**
     * 取得用戶 Profile
     *
     * @param string $access_token LINE Access Token
     * @return array 用戶資料，包含 userId, displayName, pictureUrl
     * @throws \Exception 當 HTTP 請求失敗時
     */
    public function getProfile(string $access_token): array {
        $response = wp_remote_get(self::PROFILE_ENDPOINT, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        // 檢查 WP HTTP 錯誤
        if (is_wp_error($response)) {
            throw new \Exception(
                sprintf(
                    /* translators: %s: error message */
                    __('LINE Profile API 連線失敗：%s', 'line-hub'),
                    $response->get_error_message()
                )
            );
        }

        // 檢查 HTTP 狀態碼
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            throw new \Exception(
                sprintf(
                    /* translators: %d: HTTP status code */
                    __('LINE Profile API 回應錯誤（HTTP %d）', 'line-hub'),
                    $status_code
                )
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return is_array($body) ? $body : [];
    }

    /**
     * 取得 OAuth Callback URL
     *
     * @return string Callback URL
     */
    private function getRedirectUri(): string {
        return home_url('/line-hub/auth/callback');
    }

    /**
     * 檢查客戶端設定是否完整
     *
     * @return bool true 如果 channel_id 和 channel_secret 都已設定
     */
    public function isConfigured(): bool {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    /**
     * 取得 Channel ID（用於除錯）
     *
     * @return string Channel ID（遮罩處理）
     */
    public function getClientIdMasked(): string {
        if (empty($this->client_id)) {
            return '';
        }

        $length = strlen($this->client_id);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($this->client_id, 0, 4) . str_repeat('*', $length - 8) . substr($this->client_id, -4);
    }
}
