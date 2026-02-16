<?php
/**
 * LINE Messaging Service
 *
 * 封裝 LINE Messaging API，提供推播訊息的核心功能
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub\Messaging;

use LineHub\Services\SettingsService;
use LineHub\Services\UserService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MessagingService
 *
 * 負責：
 * - 呼叫 LINE Messaging API 發送訊息
 * - push message（對指定用戶發送）
 * - reply message（回覆 webhook 事件）
 * - multicast（群發）
 * - 錯誤處理和重試機制
 */
class MessagingService {
    /**
     * LINE Messaging API 端點
     */
    private const API_ENDPOINT = 'https://api.line.me/v2/bot/message';

    /**
     * Channel Access Token
     *
     * @var string|null
     */
    private ?string $channelAccessToken = null;

    /**
     * 建構函式
     */
    public function __construct() {
        $this->channelAccessToken = SettingsService::get('general', 'access_token', '');
    }

    /**
     * 發送推播訊息給單一用戶
     *
     * @param int   $userId      WordPress User ID
     * @param array $messages    LINE 訊息陣列（支援多則訊息）
     * @return array|WP_Error    成功返回 API 回應，失敗返回 WP_Error
     */
    public function pushMessage(int $userId, array $messages) {
        // 檢查 Channel Access Token
        if (empty($this->channelAccessToken)) {
            return new \WP_Error(
                'no_channel_access_token',
                __('LINE Channel Access Token 未設定', 'line-hub')
            );
        }

        // 取得用戶的 LINE UID
        $lineUid = UserService::getLineUid($userId);
        if (empty($lineUid)) {
            return new \WP_Error(
                'no_line_binding',
                sprintf(__('用戶 %d 尚未綁定 LINE', 'line-hub'), $userId)
            );
        }

        // 驗證訊息格式
        if (empty($messages) || !is_array($messages)) {
            return new \WP_Error(
                'invalid_messages',
                __('訊息格式不正確', 'line-hub')
            );
        }

        // 確保每則訊息都有 type 欄位
        foreach ($messages as $message) {
            if (!isset($message['type'])) {
                return new \WP_Error(
                    'invalid_message_type',
                    __('訊息缺少 type 欄位', 'line-hub')
                );
            }
        }

        // 構建 API 請求
        $body = [
            'to' => $lineUid,
            'messages' => $messages,
        ];

        return $this->sendRequest('push', $body);
    }

    /**
     * 發送文字訊息給單一用戶（便利方法）
     *
     * @param int    $userId WordPress User ID
     * @param string $text   文字訊息內容
     * @return array|\WP_Error
     */
    public function pushText(int $userId, string $text) {
        if (empty(trim($text))) {
            return new \WP_Error(
                'empty_text',
                __('文字訊息不能為空', 'line-hub')
            );
        }

        $messages = [
            [
                'type' => 'text',
                'text' => $text,
            ],
        ];

        return $this->pushMessage($userId, $messages);
    }

    /**
     * 發送 Flex Message 給單一用戶（便利方法）
     *
     * @param int   $userId      WordPress User ID
     * @param array $flexMessage Flex Message 結構
     * @return array|\WP_Error
     */
    public function pushFlex(int $userId, array $flexMessage) {
        if (empty($flexMessage)) {
            return new \WP_Error(
                'empty_flex',
                __('Flex Message 不能為空', 'line-hub')
            );
        }

        // 確保是完整的 Flex Message 格式
        if (!isset($flexMessage['type']) || $flexMessage['type'] !== 'flex') {
            return new \WP_Error(
                'invalid_flex_format',
                __('Flex Message 格式不正確', 'line-hub')
            );
        }

        $messages = [$flexMessage];

        return $this->pushMessage($userId, $messages);
    }

    /**
     * 回覆 Webhook 事件訊息
     *
     * @param string $replyToken Webhook 事件的 reply token
     * @param array  $messages   LINE 訊息陣列
     * @return array|\WP_Error
     */
    public function replyMessage(string $replyToken, array $messages) {
        // 檢查 Channel Access Token
        if (empty($this->channelAccessToken)) {
            return new \WP_Error(
                'no_channel_access_token',
                __('LINE Channel Access Token 未設定', 'line-hub')
            );
        }

        // 驗證 reply token
        if (empty($replyToken)) {
            return new \WP_Error(
                'invalid_reply_token',
                __('Reply token 不能為空', 'line-hub')
            );
        }

        // 驗證訊息格式
        if (empty($messages) || !is_array($messages)) {
            return new \WP_Error(
                'invalid_messages',
                __('訊息格式不正確', 'line-hub')
            );
        }

        // 構建 API 請求
        $body = [
            'replyToken' => $replyToken,
            'messages' => $messages,
        ];

        return $this->sendRequest('reply', $body);
    }

    /**
     * 群發訊息給多個用戶
     *
     * @param array $userIds  WordPress User IDs
     * @param array $messages LINE 訊息陣列
     * @return array|\WP_Error
     */
    public function multicast(array $userIds, array $messages) {
        // 檢查 Channel Access Token
        if (empty($this->channelAccessToken)) {
            return new \WP_Error(
                'no_channel_access_token',
                __('LINE Channel Access Token 未設定', 'line-hub')
            );
        }

        // 驗證用戶 ID
        if (empty($userIds) || count($userIds) === 0) {
            return new \WP_Error(
                'no_recipients',
                __('收件人列表不能為空', 'line-hub')
            );
        }

        // LINE multicast API 限制：最多 500 個收件人
        if (count($userIds) > 500) {
            return new \WP_Error(
                'too_many_recipients',
                __('群發收件人數量超過 500 人限制', 'line-hub')
            );
        }

        // 取得所有用戶的 LINE UID
        $lineUids = [];
        foreach ($userIds as $userId) {
            $lineUid = UserService::getLineUid($userId);
            if (!empty($lineUid)) {
                $lineUids[] = $lineUid;
            }
        }

        if (empty($lineUids)) {
            return new \WP_Error(
                'no_line_users',
                __('所有收件人都未綁定 LINE', 'line-hub')
            );
        }

        // 驗證訊息格式
        if (empty($messages) || !is_array($messages)) {
            return new \WP_Error(
                'invalid_messages',
                __('訊息格式不正確', 'line-hub')
            );
        }

        // 構建 API 請求
        $body = [
            'to' => $lineUids,
            'messages' => $messages,
        ];

        return $this->sendRequest('multicast', $body);
    }

    /**
     * 發送 HTTP 請求到 LINE Messaging API
     *
     * @param string $endpoint API 端點類型（push/reply/multicast）
     * @param array  $body     請求 body
     * @return array|\WP_Error
     */
    private function sendRequest(string $endpoint, array $body) {
        // 構建完整 URL
        $url = self::API_ENDPOINT . '/' . $endpoint;

        // 構建請求參數
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->channelAccessToken,
            ],
            'body' => wp_json_encode($body),
            'timeout' => 30,
        ];

        // 發送請求
        $response = wp_remote_post($url, $args);

        // 檢查 HTTP 錯誤
        if (is_wp_error($response)) {
            return $response;
        }

        // 取得回應狀態碼
        $statusCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $responseData = json_decode($responseBody, true);

        // 檢查 LINE API 錯誤
        if ($statusCode !== 200) {
            $errorMessage = isset($responseData['message'])
                ? $responseData['message']
                : sprintf(__('LINE API 錯誤 (HTTP %d)', 'line-hub'), $statusCode);

            return new \WP_Error(
                'line_api_error',
                $errorMessage,
                [
                    'status_code' => $statusCode,
                    'response' => $responseData,
                ]
            );
        }

        // 記錄成功日誌（可選）
        do_action('line_hub/messaging/sent', [
            'endpoint' => $endpoint,
            'body' => $body,
            'response' => $responseData,
        ]);

        return $responseData ?? [];
    }

    /**
     * 發送訊息給多個用戶（逐一發送，而非 multicast）
     *
     * 適用於需要個別處理的場景（例如每個用戶訊息內容不同）
     *
     * @param array $userIds  WordPress User IDs
     * @param array $messages LINE 訊息陣列
     * @return array ['success' => int, 'failed' => int, 'skipped' => int]
     */
    public function sendToMultiple(array $userIds, array $messages): array {
        $result = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($userIds as $userId) {
            // 檢查是否有 LINE 綁定
            $lineUid = UserService::getLineUid($userId);
            if (empty($lineUid)) {
                $result['skipped']++;
                continue;
            }

            // 發送訊息
            $response = $this->pushMessage($userId, $messages);
            if (is_wp_error($response)) {
                $result['failed']++;
            } else {
                $result['success']++;
            }
        }

        return $result;
    }

    /**
     * 驗證 Channel Access Token 是否有效
     *
     * @return bool
     */
    public function validateToken(): bool {
        if (empty($this->channelAccessToken)) {
            return false;
        }

        // 使用 LINE Messaging API 的 /v2/bot/info 端點驗證 token
        $url = 'https://api.line.me/v2/bot/info';
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->channelAccessToken,
            ],
            'timeout' => 10,
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            error_log('[LINE Hub] Token 驗證失敗：' . $response->get_error_message());
            return false;
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        // 200 表示 token 有效
        if ($statusCode === 200) {
            return true;
        }

        // 記錄錯誤訊息以便除錯
        $body = wp_remote_retrieve_body($response);
        error_log('[LINE Hub] Token 驗證失敗 (HTTP ' . $statusCode . ')：' . $body);

        return false;
    }
}
