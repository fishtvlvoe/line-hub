<?php
/**
 * User REST API
 *
 * 提供用戶 LINE 綁定管理的 REST API 端點
 *
 * @package LineHub
 */

namespace LineHub\API;

use LineHub\Services\UserService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * UserAPI 類別
 *
 * 提供用戶綁定查詢和管理的 REST API 端點
 */
class UserAPI {
    /**
     * API 命名空間
     *
     * @var string
     */
    private $namespace = 'line-hub/v1';

    /**
     * 註冊 REST API 路由
     *
     * @return void
     */
    public function register_routes() {
        // GET /line-hub/v1/user/binding - 查詢當前用戶的綁定狀態
        // DELETE /line-hub/v1/user/binding - 解除當前用戶的綁定
        register_rest_route($this->namespace, '/user/binding', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_current_user_binding'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_current_user_binding'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
        ]);

        // GET /line-hub/v1/user/{user_id}/binding - 管理員查詢指定用戶的綁定狀態
        register_rest_route($this->namespace, '/user/(?P<user_id>\d+)/binding', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_user_binding'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'user_id' => [
                        'required' => true,
                        'type' => 'integer',
                        'description' => 'WordPress 用戶 ID',
                        'validate_callback' => [$this, 'validate_user_id'],
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    /**
     * 取得當前登入用戶的綁定狀態
     *
     * GET /line-hub/v1/user/binding
     *
     * @param \WP_REST_Request $request 請求物件
     * @return \WP_REST_Response
     */
    public function get_current_user_binding($request) {
        $user_id = get_current_user_id();

        return $this->get_binding_response($user_id);
    }

    /**
     * 解除當前登入用戶的 LINE 綁定
     *
     * DELETE /line-hub/v1/user/binding
     *
     * @param \WP_REST_Request $request 請求物件
     * @return \WP_REST_Response
     */
    public function delete_current_user_binding($request) {
        $user_id = get_current_user_id();

        // 檢查是否有綁定
        $binding = UserService::getBinding($user_id);
        if (!$binding) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('您尚未綁定 LINE 帳號', 'line-hub'),
            ], 400);
        }

        // 執行解除綁定
        $result = UserService::unlinkUser($user_id);

        if ($result) {
            return new \WP_REST_Response([
                'success' => true,
                'message' => __('LINE 綁定已解除', 'line-hub'),
            ], 200);
        }

        return new \WP_REST_Response([
            'success' => false,
            'message' => __('解除綁定失敗，請稍後再試', 'line-hub'),
        ], 500);
    }

    /**
     * 管理員取得指定用戶的綁定狀態
     *
     * GET /line-hub/v1/user/{user_id}/binding
     *
     * @param \WP_REST_Request $request 請求物件
     * @return \WP_REST_Response
     */
    public function get_user_binding($request) {
        $user_id = (int) $request->get_param('user_id');

        // 檢查用戶是否存在
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('用戶不存在', 'line-hub'),
            ], 404);
        }

        return $this->get_binding_response($user_id);
    }

    /**
     * 建構綁定狀態回應
     *
     * @param int $user_id WordPress 用戶 ID
     * @return \WP_REST_Response
     */
    private function get_binding_response(int $user_id): \WP_REST_Response {
        $binding = UserService::getBinding($user_id);

        if (!$binding) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'is_bound' => false,
                    'binding' => null,
                ],
            ], 200);
        }

        // 判斷資料來源（LINE Hub 或 NSL fallback）
        // 如果 display_name 為 null 且有 line_uid，可能是 NSL fallback
        $source = 'line_hub';
        if (empty($binding->display_name) && !empty($binding->line_uid)) {
            // 檢查是否在 line_hub_users 表中有記錄
            global $wpdb;
            $table_name = $wpdb->prefix . 'line_hub_users';
            $hub_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
                    $user_id
                )
            );
            if (!$hub_exists) {
                $source = 'nsl';
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'is_bound' => true,
                'binding' => [
                    'line_uid' => $this->mask_line_uid($binding->line_uid),
                    'display_name' => $binding->display_name ?? null,
                    'picture_url' => $binding->picture_url ?? null,
                    'email' => $binding->email ?? null,
                    'email_verified' => (bool) ($binding->email_verified ?? false),
                    'linked_at' => $binding->created_at ?? null,
                    'source' => $source,
                ],
            ],
        ], 200);
    }

    /**
     * 驗證用戶 ID 參數
     *
     * @param mixed $value 要驗證的值
     * @param \WP_REST_Request $request 請求物件
     * @param string $param 參數名稱
     * @return bool|\WP_Error
     */
    public function validate_user_id($value, $request, $param) {
        $user_id = (int) $value;

        if ($user_id <= 0) {
            return new \WP_Error(
                'invalid_user_id',
                __('無效的用戶 ID', 'line-hub'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * 權限檢查 - 一般用戶
     *
     * 只需登入即可存取
     *
     * @param \WP_REST_Request $request 請求物件
     * @return bool
     */
    public function check_user_permission($request) {
        return is_user_logged_in();
    }

    /**
     * 權限檢查 - 管理員
     *
     * 需要 manage_options 權限
     *
     * @param \WP_REST_Request $request 請求物件
     * @return bool
     */
    public function check_admin_permission($request) {
        return current_user_can('manage_options');
    }

    /**
     * 遮罩 LINE UID
     *
     * 將 LINE UID 遮罩為 Uxxxx...xxxx 格式（前4後4顯示，中間遮罩）
     *
     * @param string $line_uid LINE 用戶唯一識別碼
     * @return string 遮罩後的 LINE UID
     */
    private function mask_line_uid(string $line_uid): string {
        $length = strlen($line_uid);

        if ($length <= 8) {
            // 太短無法有效遮罩，全部遮罩
            return str_repeat('*', $length);
        }

        $prefix = substr($line_uid, 0, 4);
        $suffix = substr($line_uid, -4);

        return $prefix . str_repeat('*', $length - 8) . $suffix;
    }
}
