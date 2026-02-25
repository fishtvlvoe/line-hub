<?php
/**
 * Settings REST API — 設定管理端點
 *
 * @package LineHub
 */

namespace LineHub\API;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsAPI {

    /** @var string */
    private $namespace = 'line-hub/v1';

    /** @var array 敏感欄位（需遮罩） */
    private $sensitive_fields = ['channel_secret', 'access_token'];

    /**
     * 註冊 REST API 路由
     */
    public function register_routes() {
        // GET /settings
        register_rest_route($this->namespace, '/settings', [[
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_settings'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'group' => [
                    'required' => false, 'type' => 'string',
                    'description' => '設定群組（不提供則返回所有群組）',
                    'validate_callback' => [$this, 'validate_group'],
                ],
            ],
        ]]);

        // POST /settings
        register_rest_route($this->namespace, '/settings', [[
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'update_settings'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'group' => [
                    'required' => true, 'type' => 'string',
                    'description' => '設定群組',
                    'validate_callback' => [$this, 'validate_group'],
                ],
                'settings' => [
                    'required' => true, 'type' => 'object',
                    'description' => '設定值（鍵值對）',
                ],
            ],
        ]]);

        // GET /settings/schema
        register_rest_route($this->namespace, '/settings/schema', [[
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'get_schema'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'group' => [
                    'required' => false, 'type' => 'string',
                    'description' => '設定群組',
                    'validate_callback' => [$this, 'validate_group'],
                ],
            ],
        ]]);
    }

    /**
     * 驗證設定群組是否有效
     */
    public function validate_group($value, $request, $param) {
        if (empty($value)) {
            return true;
        }

        $valid_groups = SettingsService::get_all_groups();
        if (!in_array($value, $valid_groups, true)) {
            return new \WP_Error('invalid_group',
                sprintf('無效的設定群組。有效值：%s', implode(', ', $valid_groups)),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * GET /settings — 取得設定值
     */
    public function get_settings($request) {
        $group = $request->get_param('group');

        try {
            if ($group) {
                $settings = $this->mask_sensitive_data(SettingsService::get_group($group), $group);
                return new \WP_REST_Response([
                    'success' => true,
                    'data' => ['group' => $group, 'settings' => $settings],
                ], 200);
            }

            $all_settings = [];
            foreach (SettingsService::get_all_groups() as $g) {
                $all_settings[$g] = $this->mask_sensitive_data(SettingsService::get_group($g), $g);
            }

            return new \WP_REST_Response(['success' => true, 'data' => $all_settings], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /settings — 更新設定值
     */
    public function update_settings($request) {
        $group = $request->get_param('group');
        $settings = $request->get_param('settings');

        if (!is_array($settings) && !is_object($settings)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'settings 必須是物件或陣列'], 400);
        }

        $settings = (array) $settings;
        $schema = SettingsService::get_schema($group);
        $invalid_keys = array_diff(array_keys($settings), array_keys($schema));

        if (!empty($invalid_keys)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '無效的設定鍵：' . implode(', ', $invalid_keys),
            ], 400);
        }

        try {
            $result = SettingsService::set_group($group, $settings);

            if ($result) {
                $updated = $this->mask_sensitive_data(SettingsService::get_group($group), $group);
                return new \WP_REST_Response([
                    'success' => true, 'message' => '設定已更新',
                    'data' => ['group' => $group, 'settings' => $updated],
                ], 200);
            }

            return new \WP_REST_Response(['success' => false, 'message' => '設定更新失敗'], 500);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /settings/schema — 取得設定 Schema
     */
    public function get_schema($request) {
        $group = $request->get_param('group');

        try {
            return new \WP_REST_Response(['success' => true, 'data' => SettingsService::get_schema($group)], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * 權限檢查 — 需 manage_options
     */
    public function check_permission($request) {
        return current_user_can('manage_options');
    }

    /**
     * 遮罩敏感資料
     */
    private function mask_sensitive_data($settings, $group) {
        $schema = SettingsService::get_schema($group);

        foreach ($settings as $key => $value) {
            if (in_array($key, $this->sensitive_fields, true) ||
                (!empty($schema[$key]['encrypted']))) {
                $settings[$key] = !empty($value) ? '******' : '';
            }
        }

        return $settings;
    }
}
