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
            $this->dispatchEvent($event);
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

        // 更新資料庫記錄為已處理
        $this->markAsProcessed($event);

        // 通用 hook（所有事件都觸發）
        do_action('line_hub/webhook/event', $event);

        // 根據事件類型分發
        switch ($type) {
            case 'message':
                $this->dispatchMessage($event);
                break;

            case 'follow':
                $this->handleFollow($event);
                do_action('line_hub/webhook/follow', $event);
                break;

            case 'unfollow':
                $this->handleUnfollow($event);
                do_action('line_hub/webhook/unfollow', $event);
                break;

            case 'postback':
                do_action('line_hub/webhook/postback', $event);
                break;

            case 'join':
                do_action('line_hub/webhook/join', $event);
                break;

            case 'leave':
                do_action('line_hub/webhook/leave', $event);
                break;

            case 'memberJoined':
                do_action('line_hub/webhook/member_joined', $event);
                break;

            case 'memberLeft':
                do_action('line_hub/webhook/member_left', $event);
                break;

            case 'accountLink':
                do_action('line_hub/webhook/account_link', $event);
                break;

            default:
                // 未知事件類型，觸發 unknown hook
                do_action('line_hub/webhook/unknown', $event);
                break;
        }
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

        // 細分類型 hook
        switch ($message_type) {
            case 'text':
                do_action('line_hub/webhook/message/text', $event);
                break;

            case 'image':
                do_action('line_hub/webhook/message/image', $event);
                break;

            case 'video':
                do_action('line_hub/webhook/message/video', $event);
                break;

            case 'audio':
                do_action('line_hub/webhook/message/audio', $event);
                break;

            case 'file':
                do_action('line_hub/webhook/message/file', $event);
                break;

            case 'location':
                do_action('line_hub/webhook/message/location', $event);
                break;

            case 'sticker':
                do_action('line_hub/webhook/message/sticker', $event);
                break;

            default:
                do_action('line_hub/webhook/message/unknown', $event);
                break;
        }
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
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
            if (defined('WP_DEBUG') && WP_DEBUG) {
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
