<?php
/**
 * OAuth Client — LINE OAuth 2.0 客戶端
 *
 * @package LineHub
 */

namespace LineHub\Auth;

use LineHub\LineApiEndpoints;
use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class OAuthClient {

    private const AUTH_ENDPOINT = 'https://access.line.me/oauth2/v2.1/authorize';
    private const REQUEST_TIMEOUT = 15;
    private const DEFAULT_SCOPE = 'profile openid email';

    /** @var string LINE Channel ID */
    private string $client_id;
    /** @var string LINE Channel Secret */
    private string $client_secret;
    /** @var string OAuth Callback URL */
    private string $redirect_uri;

    public function __construct() {
        // 優先使用 LINE Login 專用設定，若為空則 fallback 到 Messaging API 設定
        $login_id = SettingsService::get('general', 'login_channel_id', '');
        $this->client_id = !empty($login_id) ? $login_id : SettingsService::get('general', 'channel_id', '');

        $login_secret = SettingsService::get('general', 'login_channel_secret', '');
        $this->client_secret = !empty($login_secret) ? $login_secret : SettingsService::get('general', 'channel_secret', '');

        $this->redirect_uri = home_url('/line-hub/auth/callback');
    }

    /**
     * 產生 LINE 授權 URL
     */
    public function createAuthUrl(array $options = []): string {
        $state = OAuthState::generate();

        $params = [
            'response_type' => 'code',
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'state'         => $state,
            'scope'         => self::DEFAULT_SCOPE,
        ];

        $bot_prompt = $options['bot_prompt'] ?? SettingsService::get('login', 'bot_prompt', '');
        if (!empty($bot_prompt)) {
            $params['bot_prompt'] = $bot_prompt;
        }

        $initial_amr = $options['initial_amr_display'] ?? SettingsService::get('login', 'initial_amr', '');
        if (!empty($initial_amr)) {
            $params['initial_amr_display'] = $initial_amr;
        }

        // 預設停用自動登入
        if (!SettingsService::get('login', 'allow_auto_login', false)) {
            $params['disable_auto_login'] = 'true';
            $params['disable_ios_auto_login'] = 'true';
        }

        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * 產生強制重新授權 URL（AUTH-03）
     */
    public function createReauthUrl(): string {
        $state = OAuthState::generate();

        $params = [
            'response_type' => 'code',
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'state'         => $state,
            'scope'         => self::DEFAULT_SCOPE,
            'prompt'        => 'consent',
            'disable_auto_login'     => 'true',
            'disable_ios_auto_login' => 'true',
        ];

        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * 使用 authorization code 交換 tokens
     *
     * @throws \Exception 當 HTTP 請求失敗或 LINE 返回錯誤時
     */
    public function authenticate(string $code): array {
        $response = wp_remote_post(LineApiEndpoints::OAUTH_TOKEN, [
            'timeout' => self::REQUEST_TIMEOUT,
            'body'    => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->redirect_uri,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(sprintf(
                /* translators: %s: error message */
                __('LINE API 連線失敗：%s', 'line-hub'), $response->get_error_message()
            ));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            throw new \Exception($body['error_description'] ?? $body['error'] ?? __('Token 交換失敗', 'line-hub'));
        }

        return $body;
    }

    /**
     * 驗證 ID Token 並取得用戶資料
     */
    public function verifyIdToken(string $id_token): array {
        $response = wp_remote_post(LineApiEndpoints::OAUTH_VERIFY, [
            'timeout' => self::REQUEST_TIMEOUT,
            'body'    => ['id_token' => $id_token, 'client_id' => $this->client_id],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : [];
    }

    /**
     * 取得用戶 Profile
     *
     * @throws \Exception 當 HTTP 請求失敗時
     */
    public function getProfile(string $access_token): array {
        $response = wp_remote_get(LineApiEndpoints::PROFILE, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
        ]);

        if (is_wp_error($response)) {
            throw new \Exception(sprintf(
                /* translators: %s: error message */
                __('LINE Profile API 連線失敗：%s', 'line-hub'), $response->get_error_message()
            ));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            throw new \Exception(sprintf(
                /* translators: %d: HTTP status code */
                __('LINE Profile API 回應錯誤（HTTP %d）', 'line-hub'), $status_code
            ));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($body) ? $body : [];
    }

    /**
     * 檢查客戶端設定是否完整
     */
    public function isConfigured(): bool {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    /**
     * 取得遮罩後的 Channel ID（除錯用）
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
