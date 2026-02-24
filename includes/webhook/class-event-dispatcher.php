<?php
/**
 * Event Dispatcher
 *
 * 事件分類和分發，透過 WordPress hooks 廣播給外部外掛處理
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub\Webhook;

use LineHub\Services\UserService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EventDispatcher
 *
 * 職責：
 * - 處理由 Cron 觸發的事件陣列
 * - 分類事件類型（message/follow/unfollow/postback）
 * - 透過 do_action() 廣播事件
 * - LineHub 自己處理：follow/unfollow（更新好友狀態）
 * - 其他業務邏輯：由外部外掛（如 BuyGo）監聽處理
 */
class EventDispatcher {
    /**
     * 處理事件陣列（由 Cron 觸發）
     *
     * @param array $events 事件陣列
     */
    public function processEvents(array $events): void {
        foreach ($events as $event) {
            try {
                $this->dispatchEvent($event);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[LINE Hub EventDispatcher] 事件處理失敗: %s (event_id: %s)',
                    $e->getMessage(),
                    $event['webhookEventId'] ?? 'unknown'
                ));
            }
        }
    }

    /**
     * 分發單一事件
     *
     * @param array $event 事件資料
     */
    private function dispatchEvent(array $event): void {
        $type = $event['type'] ?? '';

        if (empty($type)) {
            return;
        }

        // 通用 hook（所有事件都觸發）
        do_action('line_hub/webhook/event', $event);

        // 根據事件類型分發
        match ($type) {
            'message'  => $this->dispatchMessage($event),
            'follow'   => $this->dispatchFollow($event),
            'unfollow' => $this->dispatchUnfollow($event),
            'postback' => $this->dispatchPostback($event),
            default    => $this->dispatchSimpleEvent($type, $event),
        };

        // 事件分發完成後才標記為已處理（確保失敗的事件可重試）
        $this->markAsProcessed($event);
    }

    private function dispatchFollow(array $event): void {
        $this->handleFollow($event);
        do_action('line_hub/webhook/follow', $event);
    }

    private function dispatchUnfollow(array $event): void {
        $this->handleUnfollow($event);
        do_action('line_hub/webhook/unfollow', $event);
    }

    private function dispatchPostback(array $event): void {
        $line_uid = $event['source']['userId'] ?? '';
        $user_id = !empty($line_uid) ? UserService::getUserByLineUid($line_uid) : null;
        do_action('line_hub/webhook/postback', $event, $line_uid, $user_id);
    }

    private function dispatchSimpleEvent(string $type, array $event): void {
        $hook_map = [
            'join' => 'join', 'leave' => 'leave',
            'memberJoined' => 'member_joined', 'memberLeft' => 'member_left',
            'accountLink' => 'account_link',
        ];
        $hook = $hook_map[$type] ?? 'unknown';
        do_action('line_hub/webhook/' . $hook, $event);
    }

    /**
     * 訊息事件再細分
     *
     * @param array $event 事件資料
     */
    private function dispatchMessage(array $event): void {
        $message_type = $event['message']['type'] ?? '';

        if (empty($message_type)) {
            return;
        }

        // 通用訊息 hook
        do_action('line_hub/webhook/message', $event);

        // text 和 image 傳 4 個參數（與 BuyGo handler 相容）
        $full_param_types = ['text', 'image'];
        if (in_array($message_type, $full_param_types, true)) {
            [$line_uid, $user_id, $message_id] = $this->resolveMessageContext($event);
            do_action("line_hub/webhook/message/{$message_type}", $event, $line_uid, $user_id, $message_id);
            return;
        }

        // 其他類型只傳 event
        $valid_types = ['video', 'audio', 'file', 'location', 'sticker'];
        $hook_type = in_array($message_type, $valid_types, true) ? $message_type : 'unknown';
        do_action("line_hub/webhook/message/{$hook_type}", $event);
    }

    private function resolveMessageContext(array $event): array {
        $line_uid = $event['source']['userId'] ?? '';
        $user_id = !empty($line_uid) ? UserService::getUserByLineUid($line_uid) : null;
        $message_id = $event['message']['id'] ?? '';
        return [$line_uid, $user_id, $message_id];
    }

    /**
     * LineHub 自己處理 follow（更新好友狀態）
     *
     * @param array $event 事件資料
     */
    private function handleFollow(array $event): void {
        $line_uid = $event['source']['userId'] ?? '';

        if (empty($line_uid)) {
            return;
        }

        // 透過 UserService 查找 WP User ID
        $user_id = UserService::getUserByLineUid($line_uid);

        if ($user_id) {
            // 更新好友狀態
            update_user_meta($user_id, 'line_hub_is_friend', '1');
            update_user_meta($user_id, 'line_hub_followed_at', current_time('mysql'));

            // 記錄 log
            if (defined('LINE_HUB_DEBUG') && LINE_HUB_DEBUG) {
                error_log(sprintf(
                    '[LINE Hub] User %d (LINE UID: %s) 加為好友',
                    $user_id,
                    $line_uid
                ));
            }
        }
    }

    /**
     * LineHub 自己處理 unfollow（標記取消好友）
     *
     * @param array $event 事件資料
     */
    private function handleUnfollow(array $event): void {
        $line_uid = $event['source']['userId'] ?? '';

        if (empty($line_uid)) {
            return;
        }

        // 透過 UserService 查找 WP User ID
        $user_id = UserService::getUserByLineUid($line_uid);

        if ($user_id) {
            // 標記取消好友（不刪除綁定）
            update_user_meta($user_id, 'line_hub_is_friend', '0');
            update_user_meta($user_id, 'line_hub_unfollowed_at', current_time('mysql'));

            // 記錄 log
            if (defined('LINE_HUB_DEBUG') && LINE_HUB_DEBUG) {
                error_log(sprintf(
                    '[LINE Hub] User %d (LINE UID: %s) 取消好友',
                    $user_id,
                    $line_uid
                ));
            }
        }
    }

    /**
     * 標記事件為已處理
     *
     * @param array $event 事件資料
     */
    private function markAsProcessed(array $event): void {
        $event_id = $event['webhookEventId'] ?? '';

        if (empty($event_id)) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        $wpdb->update(
            $table,
            [
                'processed' => 1,
                'processed_at' => current_time('mysql'),
            ],
            ['webhook_event_id' => $event_id],
            ['%d', '%s'],
            ['%s']
        );
    }
}
