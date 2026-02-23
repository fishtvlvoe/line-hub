<?php
/**
 * Public REST API — 外部系統整合端點
 *
 * 提供 API Key 認證的 REST API，讓外部 SaaS（如 WebinarJam、Zapier）
 * 透過 HTTP 呼叫 LineHub 的訊息發送與用戶查詢功能。
 *
 * 認證方式：Header X-LineHub-API-Key: lhk_xxxxx
 *
 * @package LineHub\API
 */

namespace LineHub\API;

use LineHub\Services\SettingsService;
use LineHub\Services\UserService;
use LineHub\Services\ApiLogger;
use LineHub\Messaging\MessagingService;

if (!defined('ABSPATH')) {
    exit;
}

class PublicAPI {

    /**
     * 初始化
     */
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * 註冊 REST API 路由
     */
    public static function register_routes(): void {
        $namespace = 'line-hub/v1';

        // 訊息發送
        register_rest_route($namespace, '/messages/text', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'send_text'],
            'permission_callback' => [self::class, 'authenticate'],
        ]);

        register_rest_route($namespace, '/messages/flex', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'send_flex'],
            'permission_callback' => [self::class, 'authenticate'],
        ]);

        register_rest_route($namespace, '/messages/broadcast', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'send_broadcast'],
            'permission_callback' => [self::class, 'authenticate'],
        ]);

        // 用戶查詢
        register_rest_route($namespace, '/users/(?P<id>\d+)/binding', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_user_binding'],
            'permission_callback' => [self::class, 'authenticate'],
        ]);

        register_rest_route($namespace, '/users/lookup', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'lookup_user'],
            'permission_callback' => [self::class, 'authenticate'],
        ]);
    }

    /**
     * API Key 認證
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public static function authenticate(\WP_REST_Request $request) {
        // 允許已登入的管理員（Cookie + Nonce）
        if (current_user_can('manage_options')) {
            return true;
        }

        $key = $request->get_header('X-LineHub-API-Key');
        if (empty($key)) {
            return new \WP_Error(
                'missing_api_key',
                '缺少 API Key，請在 Header 中提供 X-LineHub-API-Key',
                ['status' => 401]
            );
        }

        $stored_hash = SettingsService::get('integration', 'api_key_hash', '');
        if (empty($stored_hash) || !hash_equals($stored_hash, wp_hash($key))) {
            return new \WP_Error(
                'invalid_api_key',
                '無效的 API Key',
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * 發送文字訊息
     *
     * POST /line-hub/v1/messages/text
     * Body: {"user_id": 123, "message": "你好"}
     *   或: {"email": "user@example.com", "message": "你好"}
     */
    public static function send_text(\WP_REST_Request $request): \WP_REST_Response {
        $params  = $request->get_json_params();
        $user_id = self::resolve_user_id($params);
        $message = sanitize_text_field($params['message'] ?? '');

        if (!$user_id) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => '找不到用戶，請提供有效的 user_id 或 email'],
                400
            );
        }

        if (empty($message)) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => '缺少 message 參數'],
                400
            );
        }

        $messaging = new MessagingService();
        $result = $messaging->pushText($user_id, $message);

        $success = !is_wp_error($result);
        self::log_call('POST', '/messages/text', $success);
        return new \WP_REST_Response([
            'success' => $success,
            'message' => $success ? '訊息已發送' : '發送失敗',
        ]);
    }

    /**
     * 發送 Flex 訊息
     *
     * POST /line-hub/v1/messages/flex
     * Body: {"user_id": 123, "alt_text": "通知", "contents": {...}}
     */
    public static function send_flex(\WP_REST_Request $request): \WP_REST_Response {
        $params   = $request->get_json_params();
        $user_id  = self::resolve_user_id($params);
        $alt_text = sanitize_text_field($params['alt_text'] ?? '通知');
        $contents = $params['contents'] ?? [];

        if (!$user_id) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => '找不到用戶'],
                400
            );
        }

        if (empty($contents) || !is_array($contents)) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => '缺少 contents 參數'],
                400
            );
        }

        $flex_message = [
            'type'     => 'flex',
            'altText'  => $alt_text,
            'contents' => $contents,
        ];

        $messaging = new MessagingService();
        $result = $messaging->pushFlex($user_id, $flex_message);

        $success = !is_wp_error($result);
        self::log_call('POST', '/messages/flex', $success);
        return new \WP_REST_Response([
            'success' => $success,
            'message' => $success ? 'Flex 訊息已發送' : '發送失敗',
        ]);
    }

    /**
     * 批量發送
     *
     * POST /line-hub/v1/messages/broadcast
     * Body: {"user_ids": [1, 2, 3], "message": "公告"}
     */
    public static function send_broadcast(\WP_REST_Request $request): \WP_REST_Response {
        $params   = $request->get_json_params();
        $user_ids = $params['user_ids'] ?? [];
        $message  = sanitize_text_field($params['message'] ?? '');

        if (empty($user_ids) || !is_array($user_ids)) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => '缺少 user_ids 參數'],
                400
            );
        }

        if (empty($message)) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => '缺少 message 參數'],
                400
            );
        }

        $user_ids = array_filter(array_map('intval', $user_ids), fn($id) => $id > 0);

        // 安全限制：單次 broadcast 最多 100 個用戶
        if (count($user_ids) > 100) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => 'user_ids 數量超過上限（最多 100 個）'],
                400
            );
        }

        if (empty($user_ids)) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => 'user_ids 中沒有有效的用戶 ID'],
                400
            );
        }

        $messages  = [['type' => 'text', 'text' => $message]];
        $messaging = new MessagingService();
        $result = $messaging->sendToMultiple(array_values($user_ids), $messages);

        $success = !is_wp_error($result);
        self::log_call('POST', '/messages/broadcast', $success);
        return new \WP_REST_Response([
            'success' => $success,
            'message' => $success ? '批量訊息已發送' : '發送失敗',
            'count'   => count($user_ids),
        ]);
    }

    /**
     * 查詢用戶綁定狀態
     *
     * GET /line-hub/v1/users/{id}/binding
     */
    public static function get_user_binding(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = (int) $request->get_param('id');

        if ($user_id <= 0) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => '無效的用戶 ID'],
                400
            );
        }

        $is_linked = UserService::isLinked($user_id);
        $line_uid  = UserService::getLineUid($user_id);

        $data = [
            'success'   => true,
            'user_id'   => $user_id,
            'is_linked' => $is_linked,
            'line_uid'  => $line_uid ?: null,
        ];

        // 如果已綁定，附加更多資訊
        if ($is_linked) {
            $binding = UserService::getBinding($user_id);
            if ($binding) {
                $data['display_name'] = $binding->display_name ?? null;
                $data['picture_url']  = $binding->picture_url ?? null;
            }
        }

        self::log_call('GET', '/users/' . $user_id . '/binding', true);
        return new \WP_REST_Response($data);
    }

    /**
     * 用 email 查詢用戶
     *
     * GET /line-hub/v1/users/lookup?email=user@example.com
     */
    public static function lookup_user(\WP_REST_Request $request): \WP_REST_Response {
        $email = sanitize_email($request->get_param('email') ?? '');

        if (empty($email)) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => '缺少 email 參數'],
                400
            );
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            return new \WP_REST_Response(
                ['success' => false, 'message' => '找不到此 Email 對應的用戶'],
                404
            );
        }

        $is_linked = UserService::isLinked($user->ID);
        $line_uid  = UserService::getLineUid($user->ID);

        self::log_call('GET', '/users/lookup', true);
        return new \WP_REST_Response([
            'success'      => true,
            'user_id'      => $user->ID,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'is_linked'    => $is_linked,
            'line_uid'     => $line_uid ?: null,
        ]);
    }

    /**
     * 從參數中解析用戶 ID（支援 user_id 或 email）
     *
     * @param array $params 請求參數
     * @return int 用戶 ID（0 表示找不到）
     */
    /**
     * 記錄 API 呼叫（僅 API Key 認證，不記錄管理員 Cookie 認證）
     *
     * @param string $method   HTTP 方法
     * @param string $endpoint 端點路徑
     * @param bool   $success  是否成功
     */
    private static function log_call(string $method, string $endpoint, bool $success): void {
        // 管理員 Cookie 認證不記錄，只記錄 API Key 認證的呼叫
        if (current_user_can('manage_options') && empty($_SERVER['HTTP_X_LINEHUB_API_KEY'])) {
            return;
        }
        ApiLogger::log($method, $endpoint, $success ? 'success' : 'error');
    }

    private static function resolve_user_id(array $params): int {
        // 優先使用 user_id
        if (!empty($params['user_id'])) {
            return (int) $params['user_id'];
        }

        // 其次用 email 查找
        if (!empty($params['email'])) {
            $user = get_user_by('email', sanitize_email($params['email']));
            if ($user) {
                return $user->ID;
            }
        }

        return 0;
    }
}
