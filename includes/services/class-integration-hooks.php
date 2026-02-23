<?php
/**
 * Integration Hooks — 外掛間通訊介面
 *
 * 提供標準 WordPress hooks，讓任何外掛透過 do_action / apply_filters
 * 呼叫 LineHub 的訊息發送與用戶查詢功能，無需直接依賴 LineHub 類別。
 *
 * 發送文字訊息：
 *   do_action('line_hub/send/text', ['user_id' => 123, 'message' => '你好'])
 *
 * 發送 Flex 訊息：
 *   do_action('line_hub/send/flex', ['user_id' => 123, 'alt_text' => '通知', 'contents' => [...]])
 *
 * 批量發送：
 *   do_action('line_hub/send/broadcast', ['user_ids' => [1, 2, 3], 'message' => '公告'])
 *
 * 查詢綁定狀態：
 *   $is_linked = apply_filters('line_hub/user/is_linked', false, $user_id)
 *   $line_uid  = apply_filters('line_hub/user/get_line_uid', '', $user_id)
 *
 * @package LineHub\Services
 */

namespace LineHub\Services;

use LineHub\Messaging\MessagingService;

if (!defined('ABSPATH')) {
    exit;
}

class IntegrationHooks {

    /**
     * 初始化所有 integration hooks
     */
    public static function init(): void {
        // 發送類 actions
        add_action('line_hub/send/text',      [self::class, 'handle_send_text'],      10, 1);
        add_action('line_hub/send/flex',      [self::class, 'handle_send_flex'],      10, 1);
        add_action('line_hub/send/broadcast', [self::class, 'handle_broadcast'],      10, 1);

        // 查詢類 filters
        add_filter('line_hub/user/is_linked',    [self::class, 'filter_is_linked'],    10, 2);
        add_filter('line_hub/user/get_line_uid', [self::class, 'filter_get_line_uid'], 10, 2);
    }

    /**
     * 處理 line_hub/send/text action
     *
     * @param array $args {
     *   @type int    $user_id  WordPress 用戶 ID（必填）
     *   @type string $message  訊息文字（必填）
     * }
     */
    public static function handle_send_text(array $args): void {
        $user_id = isset($args['user_id']) ? (int) $args['user_id'] : 0;
        $message = isset($args['message']) ? (string) $args['message'] : '';

        if ($user_id <= 0 || $message === '') {
            error_log("[LineHub] handle_send_text: SKIP — invalid user_id($user_id) or empty message");
            return;
        }

        error_log("[LineHub] handle_send_text: user_id=$user_id, message_len=" . mb_strlen($message));

        $messaging = new MessagingService();
        $result = $messaging->pushText($user_id, $message);

        if (is_wp_error($result)) {
            error_log("[LineHub] handle_send_text: FAILED — " . $result->get_error_code() . ': ' . $result->get_error_message());
        } else {
            error_log("[LineHub] handle_send_text: SUCCESS");
        }
    }

    /**
     * 處理 line_hub/send/flex action
     *
     * @param array $args {
     *   @type int    $user_id  WordPress 用戶 ID（必填）
     *   @type string $alt_text 替代文字（必填）
     *   @type array  $contents Flex Message contents JSON（必填）
     * }
     */
    public static function handle_send_flex(array $args): void {
        $user_id  = isset($args['user_id'])  ? (int)    $args['user_id']  : 0;
        $alt_text = isset($args['alt_text']) ? (string) $args['alt_text'] : '';
        $contents = isset($args['contents']) && is_array($args['contents']) ? $args['contents'] : [];

        if ($user_id <= 0 || empty($contents)) {
            return;
        }

        $flex_message = [
            'type'     => 'flex',
            'altText'  => $alt_text ?: '通知',
            'contents' => $contents,
        ];

        $messaging = new MessagingService();
        $messaging->pushFlex($user_id, $flex_message);
    }

    /**
     * 處理 line_hub/send/broadcast action
     *
     * @param array $args {
     *   @type int[]  $user_ids  WordPress 用戶 ID 陣列（必填）
     *   @type string $message   訊息文字（必填）
     * }
     */
    public static function handle_broadcast(array $args): void {
        $user_ids = isset($args['user_ids']) && is_array($args['user_ids']) ? $args['user_ids'] : [];
        $message  = isset($args['message'])  ? (string) $args['message']  : '';

        if (empty($user_ids) || $message === '') {
            return;
        }

        $user_ids = array_filter(array_map('intval', $user_ids), fn($id) => $id > 0);

        if (empty($user_ids)) {
            return;
        }

        $messages  = [['type' => 'text', 'text' => $message]];
        $messaging = new MessagingService();
        $messaging->sendToMultiple(array_values($user_ids), $messages);
    }

    /**
     * Filter：查詢用戶是否已綁定 LINE
     *
     * @param bool $default  預設值（false）
     * @param int  $user_id  WordPress 用戶 ID
     * @return bool
     */
    public static function filter_is_linked(bool $default, int $user_id): bool {
        if ($user_id <= 0) {
            return $default;
        }

        return UserService::isLinked($user_id);
    }

    /**
     * Filter：取得用戶的 LINE UID
     *
     * @param string $default  預設值（空字串）
     * @param int    $user_id  WordPress 用戶 ID
     * @return string LINE UID 或空字串
     */
    public static function filter_get_line_uid(string $default, int $user_id): string {
        if ($user_id <= 0) {
            return $default;
        }

        return UserService::getLineUid($user_id) ?? $default;
    }
}
