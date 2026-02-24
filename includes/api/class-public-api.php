<?php
/**
 * Public REST API — 外部系統整合端點（API Key 認證）
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

    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        $ns = 'line-hub/v1';
        $auth = [self::class, 'authenticate'];

        register_rest_route($ns, '/messages/text', [
            'methods' => 'POST', 'callback' => [self::class, 'send_text'], 'permission_callback' => $auth,
        ]);
        register_rest_route($ns, '/messages/flex', [
            'methods' => 'POST', 'callback' => [self::class, 'send_flex'], 'permission_callback' => $auth,
        ]);
        register_rest_route($ns, '/messages/broadcast', [
            'methods' => 'POST', 'callback' => [self::class, 'send_broadcast'], 'permission_callback' => $auth,
        ]);
        register_rest_route($ns, '/users/(?P<id>\d+)/binding', [
            'methods' => 'GET', 'callback' => [self::class, 'get_user_binding'], 'permission_callback' => $auth,
        ]);
        register_rest_route($ns, '/users/lookup', [
            'methods' => 'GET', 'callback' => [self::class, 'lookup_user'], 'permission_callback' => $auth,
        ]);
    }

    /**
     * API Key 認證
     */
    public static function authenticate(\WP_REST_Request $request) {
        if (current_user_can('manage_options')) {
            return true;
        }
        $key = $request->get_header('X-LineHub-API-Key');
        if (empty($key)) {
            return new \WP_Error('missing_api_key', '缺少 API Key，請在 Header 中提供 X-LineHub-API-Key', ['status' => 401]);
        }
        $stored_hash = SettingsService::get('integration', 'api_key_hash', '');
        if (empty($stored_hash) || !hash_equals($stored_hash, wp_hash($key))) {
            return new \WP_Error('invalid_api_key', '無效的 API Key', ['status' => 401]);
        }
        return true;
    }

    /**
     * POST /messages/text — 發送文字訊息
     */
    public static function send_text(\WP_REST_Request $request): \WP_REST_Response {
        $params  = $request->get_json_params();
        $user_id = self::resolve_user_id($params);
        $message = sanitize_text_field($params['message'] ?? '');

        if (!$user_id) {
            return new \WP_REST_Response(['success' => false, 'message' => '找不到用戶，請提供有效的 user_id 或 email'], 400);
        }
        if (empty($message)) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少 message 參數'], 400);
        }

        $result = (new MessagingService())->pushText($user_id, $message);
        $success = !is_wp_error($result);
        self::log_call('POST', '/messages/text', $success);
        return new \WP_REST_Response(['success' => $success, 'message' => $success ? '訊息已發送' : '發送失敗']);
    }

    /**
     * POST /messages/flex — 發送 Flex 訊息
     */
    public static function send_flex(\WP_REST_Request $request): \WP_REST_Response {
        $params   = $request->get_json_params();
        $user_id  = self::resolve_user_id($params);
        $alt_text = sanitize_text_field($params['alt_text'] ?? '通知');
        $contents = $params['contents'] ?? [];

        if (!$user_id) {
            return new \WP_REST_Response(['success' => false, 'message' => '找不到用戶'], 400);
        }
        if (empty($contents) || !is_array($contents)) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少 contents 參數'], 400);
        }

        $result = (new MessagingService())->pushFlex($user_id, [
            'type' => 'flex', 'altText' => $alt_text, 'contents' => $contents,
        ]);
        $success = !is_wp_error($result);
        self::log_call('POST', '/messages/flex', $success);
        return new \WP_REST_Response(['success' => $success, 'message' => $success ? 'Flex 訊息已發送' : '發送失敗']);
    }

    /**
     * POST /messages/broadcast — 批量發送
     */
    public static function send_broadcast(\WP_REST_Request $request): \WP_REST_Response {
        $params   = $request->get_json_params();
        $user_ids = $params['user_ids'] ?? [];
        $message  = sanitize_text_field($params['message'] ?? '');

        if (empty($user_ids) || !is_array($user_ids)) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少 user_ids 參數'], 400);
        }
        if (empty($message)) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少 message 參數'], 400);
        }

        $user_ids = array_filter(array_map('intval', $user_ids), fn($id) => $id > 0);
        if (count($user_ids) > 100) {
            return new \WP_REST_Response(['success' => false, 'message' => 'user_ids 數量超過上限（最多 100 個）'], 400);
        }
        if (empty($user_ids)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'user_ids 中沒有有效的用戶 ID'], 400);
        }

        $result = (new MessagingService())->sendToMultiple(array_values($user_ids), [['type' => 'text', 'text' => $message]]);
        $success = !is_wp_error($result);
        self::log_call('POST', '/messages/broadcast', $success);
        return new \WP_REST_Response([
            'success' => $success, 'message' => $success ? '批量訊息已發送' : '發送失敗', 'count' => count($user_ids),
        ]);
    }

    /**
     * GET /users/{id}/binding — 查詢用戶綁定狀態
     */
    public static function get_user_binding(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = (int) $request->get_param('id');
        if ($user_id <= 0) {
            return new \WP_REST_Response(['success' => false, 'message' => '無效的用戶 ID'], 400);
        }

        $data = [
            'success' => true, 'user_id' => $user_id,
            'is_linked' => UserService::isLinked($user_id),
            'line_uid' => UserService::getLineUid($user_id) ?: null,
        ];

        if ($data['is_linked']) {
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
     * GET /users/lookup — 用 email 查詢用戶
     */
    public static function lookup_user(\WP_REST_Request $request): \WP_REST_Response {
        $email = sanitize_email($request->get_param('email') ?? '');
        if (empty($email)) {
            return new \WP_REST_Response(['success' => false, 'message' => '缺少 email 參數'], 400);
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            return new \WP_REST_Response(['success' => false, 'message' => '找不到此 Email 對應的用戶'], 404);
        }

        self::log_call('GET', '/users/lookup', true);
        return new \WP_REST_Response([
            'success' => true, 'user_id' => $user->ID, 'display_name' => $user->display_name,
            'email' => $user->user_email, 'is_linked' => UserService::isLinked($user->ID),
            'line_uid' => UserService::getLineUid($user->ID) ?: null,
        ]);
    }

    // ── Private ──────────────────────────────────────────

    private static function log_call(string $method, string $endpoint, bool $success): void {
        if (current_user_can('manage_options') && empty($_SERVER['HTTP_X_LINEHUB_API_KEY'])) {
            return;
        }
        ApiLogger::log($method, $endpoint, $success ? 'success' : 'error');
    }

    private static function resolve_user_id(array $params): int {
        if (!empty($params['user_id'])) {
            return (int) $params['user_id'];
        }
        if (!empty($params['email'])) {
            $user = get_user_by('email', sanitize_email($params['email']));
            return $user ? $user->ID : 0;
        }
        return 0;
    }
}
