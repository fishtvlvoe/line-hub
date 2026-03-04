<?php
/**
 * Webhook 轉發服務 — LINE Event → OpenClaw
 *
 * 掛在 line_hub/webhook/event hook，檢查 OpenClaw 設定是否啟用
 * 如果啟用，將 LINE 事件非阻塞轉發到 OpenClaw Webhook URL
 *
 * 轉發 Payload 格式（符合 OpenClaw /hooks/agent 規格）：
 * {
 *   "message": "LINE 事件摘要",
 *   "agentId": "可選，由設定決定",
 *   "sessionKey": "line:{line_uid}"
 * }
 *
 * @package LineHub\Services
 */

namespace LineHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookForwarder {

    /**
     * 初始化 webhook 轉發
     */
    public static function init(): void {
        add_action('line_hub/webhook/event', [self::class, 'onWebhookEvent'], 10, 1);
    }

    /**
     * 處理 LINE Webhook 事件，並轉發到 OpenClaw
     *
     * @param array $event LINE 事件陣列（Webhook payload）
     */
    public static function onWebhookEvent(array $event): void {
        // 檢查 OpenClaw 是否啟用
        $settings = SettingsService::getInstance();
        if (!$settings->get('integration', 'openclaw_enabled')) {
            return;
        }

        // 取得 OpenClaw 設定
        $webhook_url = $settings->get('integration', 'openclaw_webhook_url');
        $webhook_token = $settings->get('integration', 'openclaw_webhook_token');

        if (empty($webhook_url) || empty($webhook_token)) {
            error_log('[LineHub] WebhookForwarder: OpenClaw 配置不完整（URL 或 Token 缺失）');
            return;
        }

        try {
            // 組裝 Payload
            $payload = self::buildPayload($event);
            if (empty($payload)) {
                return;
            }

            // 非阻塞轉發
            self::post($webhook_url, $webhook_token, $payload);
        } catch (\Throwable $e) {
            error_log('[LineHub] WebhookForwarder error: ' . $e->getMessage());
        }
    }

    /**
     * 組裝轉發 Payload
     *
     * @param array $event LINE Webhook 事件
     * @return array|null Payload 陣列或 null（事件不轉發）
     */
    private static function buildPayload(array $event): ?array {
        // 取得事件類型
        $type = $event['type'] ?? '';
        if (empty($type)) {
            return null;
        }

        // 根據事件類型組裝訊息摘要
        $message = self::buildMessage($event);
        if (empty($message)) {
            return null;
        }

        // 提取 LINE UID（source.userId）
        $line_uid = '';
        if (!empty($event['source']['userId'])) {
            $line_uid = $event['source']['userId'];
        }

        // 組裝 OpenClaw /hooks/agent payload
        return [
            'message'   => $message,
            'sessionKey' => !empty($line_uid) ? "line:{$line_uid}" : '',
            'agentId'   => '', // 可由 OpenClaw 側設定默認 agentId
        ];
    }

    /**
     * 根據事件類型組裝訊息摘要
     *
     * @param array $event LINE Webhook 事件
     * @return string 訊息摘要或空字串
     */
    private static function buildMessage(array $event): string {
        $type = $event['type'] ?? '';

        switch ($type) {
            case 'message':
                // 文字訊息
                if ($event['message']['type'] === 'text') {
                    return '用戶訊息：' . substr($event['message']['text'] ?? '', 0, 100);
                }
                return '用戶傳送：' . $event['message']['type'];

            case 'postback':
                // 回傳事件（按鈕、菜單等）
                return '用戶操作：' . substr($event['postback']['data'] ?? '', 0, 100);

            case 'follow':
                // 追蹤機器人
                return '用戶追蹤了機器人';

            case 'unfollow':
                // 取消追蹤
                return '用戶取消追蹤';

            case 'join':
                // 加入群組/聊天室
                return '機器人被加入' . $event['source']['type'];

            case 'leave':
                // 離開群組/聊天室
                return '機器人被移除' . $event['source']['type'];

            default:
                return '事件：' . $type;
        }
    }

    /**
     * 非阻塞 POST 到 OpenClaw Webhook URL
     *
     * @param string $url     OpenClaw Webhook URL
     * @param string $token   OpenClaw Webhook Token
     * @param array  $payload 轉發資料
     */
    private static function post(string $url, string $token, array $payload): void {
        wp_remote_post($url, [
            'blocking'    => false,     // 非阻塞：不等待回應
            'timeout'     => 5,
            'redirection' => 0,
            'httpversion' => '1.1',
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
            'body'        => wp_json_encode($payload),
        ]);

        error_log('[LineHub] WebhookForwarder: 轉發事件到 OpenClaw（非阻塞）');
    }
}
