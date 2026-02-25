<?php
/**
 * LINE Messaging Service — 封裝 LINE Messaging API
 *
 * @package LineHub
 */

namespace LineHub\Messaging;

use LineHub\LineApiEndpoints;
use LineHub\Services\SettingsService;
use LineHub\Services\UserService;

if (!defined('ABSPATH')) {
    exit;
}

class MessagingService {

    private ?string $channelAccessToken = null;

    public function __construct() {
        $this->channelAccessToken = SettingsService::get('general', 'access_token', '');
    }

    /**
     * 發送推播訊息給單一用戶
     */
    public function pushMessage(int $userId, array $messages) {
        if (empty($this->channelAccessToken)) {
            return new \WP_Error('no_channel_access_token', __('LINE Channel Access Token is not configured.', 'line-hub'));
        }

        $lineUid = UserService::getLineUid($userId);
        if (empty($lineUid)) {
            /* translators: %d: WordPress user ID */
            return new \WP_Error('no_line_binding', sprintf(__('User %d has not linked a LINE account.', 'line-hub'), $userId));
        }

        if (empty($messages) || !is_array($messages)) {
            return new \WP_Error('invalid_messages', __('Invalid message format.', 'line-hub'));
        }

        foreach ($messages as $message) {
            if (!isset($message['type'])) {
                return new \WP_Error('invalid_message_type', __('Message is missing the type field.', 'line-hub'));
            }
        }

        return $this->sendRequest('push', ['to' => $lineUid, 'messages' => $messages]);
    }

    /**
     * 發送文字訊息（便利方法）
     */
    public function pushText(int $userId, string $text) {
        if (empty(trim($text))) {
            return new \WP_Error('empty_text', __('Text message cannot be empty.', 'line-hub'));
        }
        return $this->pushMessage($userId, [['type' => 'text', 'text' => $text]]);
    }

    /**
     * 發送 Flex Message（便利方法）
     */
    public function pushFlex(int $userId, array $flexMessage) {
        if (empty($flexMessage)) {
            return new \WP_Error('empty_flex', __('Flex Message cannot be empty.', 'line-hub'));
        }
        if (!isset($flexMessage['type']) || $flexMessage['type'] !== 'flex') {
            return new \WP_Error('invalid_flex_format', __('Invalid Flex Message format.', 'line-hub'));
        }
        return $this->pushMessage($userId, [$flexMessage]);
    }

    /**
     * 回覆 Webhook 事件訊息
     */
    public function replyMessage(string $replyToken, array $messages) {
        if (empty($this->channelAccessToken)) {
            return new \WP_Error('no_channel_access_token', __('LINE Channel Access Token is not configured.', 'line-hub'));
        }
        if (empty($replyToken)) {
            return new \WP_Error('invalid_reply_token', __('Reply token cannot be empty.', 'line-hub'));
        }
        if (empty($messages) || !is_array($messages)) {
            return new \WP_Error('invalid_messages', __('Invalid message format.', 'line-hub'));
        }
        return $this->sendRequest('reply', ['replyToken' => $replyToken, 'messages' => $messages]);
    }

    /**
     * 群發訊息給多個用戶
     */
    public function multicast(array $userIds, array $messages) {
        if (empty($this->channelAccessToken)) {
            return new \WP_Error('no_channel_access_token', __('LINE Channel Access Token is not configured.', 'line-hub'));
        }
        if (empty($userIds)) {
            return new \WP_Error('no_recipients', __('Recipient list cannot be empty.', 'line-hub'));
        }
        if (count($userIds) > 500) {
            return new \WP_Error('too_many_recipients', __('Multicast recipients exceed the 500 user limit.', 'line-hub'));
        }

        $lineUids = [];
        foreach ($userIds as $userId) {
            $lineUid = UserService::getLineUid($userId);
            if (!empty($lineUid)) {
                $lineUids[] = $lineUid;
            }
        }

        if (empty($lineUids)) {
            return new \WP_Error('no_line_users', __('None of the recipients have linked a LINE account.', 'line-hub'));
        }
        if (empty($messages) || !is_array($messages)) {
            return new \WP_Error('invalid_messages', __('Invalid message format.', 'line-hub'));
        }

        return $this->sendRequest('multicast', ['to' => $lineUids, 'messages' => $messages]);
    }

    /**
     * 逐一發送給多個用戶（每個用戶可有不同訊息）
     */
    public function sendToMultiple(array $userIds, array $messages): array {
        $result = ['success' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($userIds as $userId) {
            $lineUid = UserService::getLineUid($userId);
            if (empty($lineUid)) {
                $result['skipped']++;
                continue;
            }
            $response = $this->pushMessage($userId, $messages);
            is_wp_error($response) ? $result['failed']++ : $result['success']++;
        }

        return $result;
    }

    /**
     * 驗證 Channel Access Token 是否有效
     */
    public function validateToken(): bool {
        if (empty($this->channelAccessToken)) {
            return false;
        }

        $response = wp_remote_get(LineApiEndpoints::BOT_INFO, [
            'headers' => ['Authorization' => 'Bearer ' . $this->channelAccessToken],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            error_log('[LINE Hub] Token 驗證失敗：' . $response->get_error_message()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return false;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            error_log('[LINE Hub] Token 驗證失敗 (HTTP ' . $statusCode . ')：' . wp_remote_retrieve_body($response)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            return false;
        }
        return true;
    }

    // ── Private ──────────────────────────────────────────

    private function sendRequest(string $endpoint, array $body) {
        $url = LineApiEndpoints::BOT_MESSAGE . '/' . $endpoint;
        $response = wp_remote_post($url, [
            'method'  => 'POST',
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->channelAccessToken,
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseData = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode !== 200) {
            return new \WP_Error('line_api_error',
                /* translators: %d: HTTP status code */
                $responseData['message'] ?? sprintf(__('LINE API error (HTTP %d)', 'line-hub'), $statusCode),
                ['status_code' => $statusCode, 'response' => $responseData]
            );
        }

        do_action('line_hub/messaging/sent', [
            'endpoint' => $endpoint, 'body' => $body, 'response' => $responseData,
        ]);

        return $responseData ?? [];
    }
}
