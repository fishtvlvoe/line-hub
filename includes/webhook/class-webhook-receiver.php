<?php
/**
 * Webhook Receiver
 *
 * LINE Webhook REST API 端點，負責接收、驗證簽名、去重和排隊處理
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub\Webhook;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WebhookReceiver
 *
 * 職責：
 * - 註冊 REST API 端點 /wp-json/line-hub/v1/webhook
 * - HMAC-SHA256 簽名驗證
 * - 處理 LINE Verify Event
 * - 事件去重（webhook_event_id）
 * - 同步處理事件（確保即時回應）
 */
class WebhookReceiver {
    /**
     * 註冊 REST API 路由
     */
    public function registerRoutes(): void {
        register_rest_route('line-hub/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handleWebhook'],
            'permission_callback' => '__return_true', // 公開端點，靠簽名驗證
        ]);
    }

    /**
     * 處理 Webhook 請求
     *
     * @param \WP_REST_Request $request REST API 請求物件
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleWebhook(\WP_REST_Request $request) {
        // 1. 取得 raw body（必須在解析之前）
        $raw_body = $request->get_body();

        // 2. 驗證 HMAC 簽名
        $signature = $this->extractSignature($request);
        if (!$this->verifySignature($raw_body, $signature)) {
            return new \WP_Error(
                'invalid_signature',
                __('HMAC 簽名驗證失敗', 'line-hub'),
                ['status' => 401]
            );
        }

        // 3. 解析 JSON body
        $body = json_decode($raw_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error(
                'invalid_json',
                __('JSON 格式錯誤', 'line-hub'),
                ['status' => 400]
            );
        }

        // 4. 提取事件列表
        $events = $body['events'] ?? [];
        if (empty($events)) {
            // 空事件列表也是合法的（LINE 會發送空陣列測試）
            return new \WP_REST_Response(['success' => true], 200);
        }

        // 5. 檢查是否為 Verify Event（replyToken 全是 0）
        $first_event = $events[0] ?? [];
        $reply_token = $first_event['replyToken'] ?? '';
        if ($reply_token === str_repeat('0', 32) || $reply_token === str_repeat('0', 64)) {
            // Verify Event，直接回應成功
            return new \WP_REST_Response(['success' => true], 200);
        }

        // 6. 事件去重和記錄
        $events_to_process = [];
        foreach ($events as $event) {
            $event_id = $event['webhookEventId'] ?? '';

            // 檢查是否重複
            if (!empty($event_id) && $this->isDuplicate($event_id)) {
                continue;
            }

            // 先記錄到資料庫（標記為未處理）
            $this->logEvent($event);

            $events_to_process[] = $event;
        }

        // 7. 直接同步處理事件（不依賴 WP Cron）
        if (!empty($events_to_process)) {
            $dispatcher = new EventDispatcher();
            $dispatcher->processEvents($events_to_process);
        }

        // 8. 回應 200 OK
        return new \WP_REST_Response(['success' => true], 200);
    }

    /**
     * 從 Request Headers 提取簽名
     *
     * 嘗試順序：x-line-signature → X-LINE-Signature → HTTP_X_LINE_SIGNATURE
     *
     * @param \WP_REST_Request $request
     * @return string
     */
    private function extractSignature(\WP_REST_Request $request): string {
        // 先嘗試標準 header
        $signature = $request->get_header('x-line-signature');

        if (empty($signature)) {
            $signature = $request->get_header('X-LINE-Signature');
        }

        // Fallback: 從 $_SERVER 讀取
        if (empty($signature) && isset($_SERVER['HTTP_X_LINE_SIGNATURE'])) {
            $signature = $_SERVER['HTTP_X_LINE_SIGNATURE'];
        }

        return $signature ?: '';
    }

    /**
     * HMAC-SHA256 簽名驗證
     *
     * @param string $body      Request body（raw）
     * @param string $signature Header 中的簽名
     * @return bool
     */
    private function verifySignature(string $body, string $signature): bool {
        // 空簽名直接拒絕
        if (empty($signature)) {
            error_log('[LINE Hub Webhook] 錯誤：缺少 X-Line-Signature header');
            return false;
        }

        // 取得 Channel Secret
        $channel_secret = SettingsService::get('general', 'channel_secret', '');

        // 開發環境：明確設定 LINE_HUB_SKIP_SIGNATURE_VERIFY 才跳過驗證
        // 注意：不使用 WP_DEBUG，因為正式站台可能遺忘關閉
        if (empty($channel_secret) && defined('LINE_HUB_SKIP_SIGNATURE_VERIFY') && LINE_HUB_SKIP_SIGNATURE_VERIFY) {
            error_log('[LINE Hub Webhook] 警告：跳過簽名驗證（LINE_HUB_SKIP_SIGNATURE_VERIFY 已啟用）');
            return true;
        }

        // 正式環境：必須有 channel_secret
        if (empty($channel_secret)) {
            error_log('[LINE Hub Webhook] 錯誤：Channel Secret 未設定');
            return false;
        }

        // 計算 HMAC-SHA256
        $hash = hash_hmac('sha256', $body, $channel_secret, true);
        $expected_signature = base64_encode($hash);

        // 時序安全比較
        return hash_equals($expected_signature, $signature);
    }

    /**
     * 檢查事件是否重複
     *
     * 使用 webhook_event_id 去重
     *
     * @param string $event_id Webhook Event ID
     * @return bool 重複返回 true，不重複返回 false
     */
    private function isDuplicate(string $event_id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE webhook_event_id = %s",
                $event_id
            )
        );

        return $count > 0;
    }

    /**
     * 記錄事件到資料庫
     *
     * 委託給 WebhookLogger 統一處理（含自動清理舊記錄）
     *
     * @param array $event 事件資料
     */
    private function logEvent(array $event): void {
        $event_type = $event['type'] ?? 'unknown';
        $line_uid = $event['source']['userId'] ?? null;

        WebhookLogger::log($event_type, $event, $line_uid);
    }

}
