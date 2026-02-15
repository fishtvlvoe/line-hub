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
 * - 立即回應 200 OK（1 秒內）
 * - 排隊到 WordPress Cron 處理事件
 */
class WebhookReceiver {
    /**
     * Cron Hook 名稱
     */
    private const CRON_HOOK = 'line_hub_process_webhook';

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
        if ($reply_token === str_repeat('0', 64)) {
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

        // 7. 立即回應 200 OK（LINE 要求 1 秒內回應）
        $response = new \WP_REST_Response(['success' => true], 200);

        // 8. 如果有事件需要處理，排隊到 Cron
        if (!empty($events_to_process)) {
            $this->scheduleProcessing($events_to_process);
        }

        return $response;
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
        // 取得 Channel Secret
        $channel_secret = SettingsService::get('general', 'channel_secret', '');

        // 開發環境：如果沒有設定 channel_secret 且 WP_DEBUG 開啟，跳過驗證但記錄 warning
        if (empty($channel_secret) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[LINE Hub Webhook] 警告：開發環境跳過簽名驗證（請設定 channel_secret）');
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

    /**
     * 排隊事件到 WordPress Cron 處理
     *
     * 立即排程（time()），不阻塞 HTTP 回應
     *
     * @param array $events 事件陣列
     */
    private function scheduleProcessing(array $events): void {
        // 使用 wp_schedule_single_event 立即排程
        // 這樣 Webhook 可以立即回應 200 OK，事件在背景處理
        wp_schedule_single_event(time(), self::CRON_HOOK, [$events]);
    }
}
