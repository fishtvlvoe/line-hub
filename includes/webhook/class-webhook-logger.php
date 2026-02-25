<?php
/**
 * Webhook Logger
 *
 * 記錄 Webhook 事件到資料庫（除錯和審計用）
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub\Webhook;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WebhookLogger
 *
 * 職責：
 * - 記錄事件到 wp_line_hub_webhooks 表
 * - 自動清理超過 200 筆的舊記錄
 * - 提供查詢和統計方法
 */
class WebhookLogger {
    /**
     * 保留記錄數量上限
     */
    private const MAX_RECORDS = 200;

    /**
     * 記錄事件到資料庫
     *
     * @param string      $event_type  事件類型（message/follow/unfollow 等）
     * @param array       $event_data  完整事件資料
     * @param string|null $line_uid    LINE UID（可選）
     * @return int|false 插入的記錄 ID，失敗返回 false
     */
    public static function log(string $event_type, array $event_data, ?string $line_uid = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        // 如果沒有提供 line_uid，嘗試從 event_data 提取
        if ($line_uid === null) {
            $line_uid = $event_data['source']['userId'] ?? null;
        }

        // 嘗試找出對應的 WP User ID
        $user_id = null;
        if (!empty($line_uid)) {
            $user_id = \LineHub\Services\UserService::getUserByLineUid($line_uid);
        }

        // 插入記錄（INSERT IGNORE 防止 webhook_event_id 重複時報錯）
        $webhook_event_id = $event_data['webhookEventId'] ?? '';
        $payload = wp_json_encode($event_data);
        $received_at = current_time('mysql');

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table}
            (webhook_event_id, event_type, line_uid, user_id, payload, processed, received_at)
            VALUES (%s, %s, %s, %s, %s, %d, %s)",
            $webhook_event_id ?: null,
            $event_type,
            $line_uid,
            $user_id,
            $payload,
            0,
            $received_at
        ));

        if ($wpdb->insert_id === 0 && !empty($webhook_event_id)) {
            // INSERT IGNORE 跳過了重複記錄
            return false;
        }

        // 清理舊記錄
        self::cleanup();

        return $wpdb->insert_id;
    }

    /**
     * 清理超過 MAX_RECORDS 筆的舊記錄
     */
    private static function cleanup(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        // 計算總記錄數
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE id > %d", 0)
        );

        if ($count > self::MAX_RECORDS) {
            $delete_count = $count - self::MAX_RECORDS;

            // 刪除最舊的記錄
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
                    $delete_count
                )
            );

            // 記錄清理動作（如果開啟 debug）
            if (defined('LINE_HUB_DEBUG') && LINE_HUB_DEBUG) {
                error_log(sprintf(
                    '[LINE Hub WebhookLogger] 已清理 %d 筆舊記錄（保留最新 %d 筆）',
                    $delete_count,
                    self::MAX_RECORDS
                ));
            }
        }
    }

    /**
     * 取得最近的事件記錄
     *
     * @param int    $limit      限制數量（預設 20）
     * @param string $event_type 事件類型篩選（可選）
     * @return array 事件記錄陣列
     */
    public static function getRecent(int $limit = 20, string $event_type = ''): array {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        $limit = max(1, min($limit, 200)); // 限制在 1-200 之間

        if (!empty($event_type)) {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE event_type = %s ORDER BY id DESC LIMIT %d",
                    $event_type,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
                    $limit
                ),
                ARRAY_A
            );
        }

        return $results ?: [];
    }

    /**
     * 取得特定用戶的事件記錄
     *
     * @param int $user_id WordPress User ID
     * @param int $limit   限制數量（預設 20）
     * @return array 事件記錄陣列
     */
    public static function getByUserId(int $user_id, int $limit = 20): array {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        $limit = max(1, min($limit, 200));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d",
                $user_id,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * 取得特定 LINE UID 的事件記錄
     *
     * @param string $line_uid LINE UID
     * @param int    $limit    限制數量（預設 20）
     * @return array 事件記錄陣列
     */
    public static function getByLineUid(string $line_uid, int $limit = 20): array {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        $limit = max(1, min($limit, 200));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE line_uid = %s ORDER BY id DESC LIMIT %d",
                $line_uid,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * 統計事件類型分布
     *
     * @param int $days 統計天數（預設 7 天）
     * @return array 事件類型統計 [event_type => count]
     */
    public static function getEventTypeStats(int $days = 7): array {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        $since = wp_date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT event_type, COUNT(*) as count
                 FROM {$table}
                 WHERE received_at >= %s
                 GROUP BY event_type
                 ORDER BY count DESC",
                $since
            ),
            ARRAY_A
        );

        $stats = [];
        foreach ($results as $row) {
            $stats[$row['event_type']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * 取得未處理的事件數量
     *
     * @return int
     */
    public static function getUnprocessedCount(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE processed = %d", 0)
        );
    }

    /**
     * 清除所有記錄（管理用）
     *
     * @return int 刪除的記錄數
     */
    public static function clear(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        $count = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id > %d", 0)
        );

        if ($count > 0) {
            $wpdb->query(
                $wpdb->prepare("DELETE FROM {$table} WHERE id > %d", 0)
            );
        }

        return $count;
    }
}
