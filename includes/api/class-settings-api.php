<?php
/**
 * Settings REST API
 *
 * 提供設定管理的 REST API 端點
 *
 * @package LineHub
 */

namespace LineHub\API;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class Settings_API {
    /**
     * API 命名空間
     *
     * @var string
     */
    private $namespace = 'line-hub/v1';

    /**
     * 敏感欄位清單（需要遮罩）
     *
     * @var array
     */
    private $sensitive_fields = [
        'channel_secret',
        'access_token',
    ];

    /**
     * 註冊 REST API 路由
     *
     * @return void
     */
    public function register_routes() {
        // GET /line-hub/v1/settings
        register_rest_route($this->namespace, '/settings', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'group' => [
                        'required' => false,
                        'type' => 'string',
                        'description' => '設定群組（不提供則返回所有群組）',
                        'enum' => SettingsService::get_all_groups(),
                    ],
                ],
            ],
        ]);

        // POST /line-hub/v1/settings
        register_rest_route($this->namespace, '/settings', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'group' => [
                        'required' => true,
                        'type' => 'string',
                        'description' => '設定群組',
                        'enum' => SettingsService::get_all_groups(),
                    ],
                    'settings' => [
                        'required' => true,
                        'type' => 'object',
                        'description' => '設定值（鍵值對）',
                    ],
                ],
            ],
        ]);

        // GET /line-hub/v1/settings/schema
        register_rest_route($this->namespace, '/settings/schema', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_schema'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => [
                    'group' => [
                        'required' => false,
                        'type' => 'string',
                        'description' => '設定群組（不提供則返回所有群組的 schema）',
                        'enum' => SettingsService::get_all_groups(),
                    ],
                ],
            ],
        ]);
    }

    /**
     * 取得設定值
     *
     * GET /line-hub/v1/settings
     * GET /line-hub/v1/settings?group=general
     *
     * @param \WP_REST_Request $request 請求物件
     * @return \WP_REST_Response
     */
    public function get_settings($request) {
        $group = $request->get_param('group');

        try {
            if ($group) {
                // 取得特定群組
                $settings = SettingsService::get_group($group);

                // 遮罩敏感欄位
                $settings = $this->mask_sensitive_data($settings, $group);

                return new \WP_REST_Response([
                    'success' => true,
                    'data' => [
                        'group' => $group,
                        'settings' => $settings,
                    ],
                ], 200);
            } else {
                // 取得所有群組
                $all_groups = SettingsService::get_all_groups();
                $all_settings = [];

                foreach ($all_groups as $g) {
                    $settings = SettingsService::get_group($g);
                    $all_settings[$g] = $this->mask_sensitive_data($settings, $g);
                }

                return new \WP_REST_Response([
                    'success' => true,
                    'data' => $all_settings,
                ], 200);
            }
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 更新設定值
     *
     * POST /line-hub/v1/settings
     * Body: {
     *   "group": "general",
     *   "settings": {
     *     "channel_id": "xxxxx",
     *     "channel_secret": "xxxxx"
     *   }
     * }
     *
     * @param \WP_REST_Request $request 請求物件
     * @return \WP_REST_Response
     */
    public function update_settings($request) {
        $group = $request->get_param('group');
        $settings = $request->get_param('settings');

        if (!is_array($settings) && !is_object($settings)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'settings 必須是物件或陣列',
            ], 400);
        }

        // 轉換為陣列
        $settings = (array) $settings;

        // 驗證每個設定是否存在於 schema
        $schema = SettingsService::get_schema($group);
        $invalid_keys = [];

        foreach ($settings as $key => $value) {
            if (!isset($schema[$key])) {
                $invalid_keys[] = $key;
            }
        }

        if (!empty($invalid_keys)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => '無效的設定鍵：' . implode(', ', $invalid_keys),
            ], 400);
        }

        // 批次更新
        try {
            $result = SettingsService::set_group($group, $settings);

            if ($result) {
                // 返回更新後的設定（遮罩敏感欄位）
                $updated = SettingsService::get_group($group);
                $updated = $this->mask_sensitive_data($updated, $group);

                return new \WP_REST_Response([
                    'success' => true,
                    'message' => '設定已更新',
                    'data' => [
                        'group' => $group,
                        'settings' => $updated,
                    ],
                ], 200);
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => '設定更新失敗',
                ], 500);
            }
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 取得設定 Schema
     *
     * GET /line-hub/v1/settings/schema
     * GET /line-hub/v1/settings/schema?group=general
     *
     * @param \WP_REST_Request $request 請求物件
     * @return \WP_REST_Response
     */
    public function get_schema($request) {
        $group = $request->get_param('group');

        try {
            $schema = SettingsService::get_schema($group);

            return new \WP_REST_Response([
                'success' => true,
                'data' => $schema,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 權限檢查
     *
     * 只有擁有 manage_options 權限的使用者才能存取
     *
     * @param \WP_REST_Request $request 請求物件
     * @return bool
     */
    public function check_permission($request) {
        return current_user_can('manage_options');
    }

    /**
     * 遮罩敏感資料
     *
     * 將敏感欄位的值替換為 '******'
     *
     * @param array $settings 設定陣列
     * @param string $group 設定群組
     * @return array
     */
    private function mask_sensitive_data($settings, $group) {
        $schema = SettingsService::get_schema($group);

        foreach ($settings as $key => $value) {
            // 檢查是否為敏感欄位
            if (in_array($key, $this->sensitive_fields, true)) {
                // 如果有值，顯示為 '******'
                $settings[$key] = !empty($value) ? '******' : '';
            } elseif (isset($schema[$key]['encrypted']) && $schema[$key]['encrypted']) {
                // 或者根據 schema 的 encrypted 屬性判斷
                $settings[$key] = !empty($value) ? '******' : '';
            }
        }

        return $settings;
    }
}
