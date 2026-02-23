<?php
/**
 * 開發者 Tab
 *
 * API Key 管理、REST API 端點文件、WordPress Hooks 文件、API 使用記錄。
 *
 * @package LineHub\Admin\Tabs
 * @since 2.0.0
 */

namespace LineHub\Admin\Tabs;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class DeveloperTab extends AbstractTab {

    public function get_slug(): string {
        return 'developer';
    }

    public function get_label(): string {
        return '開發者';
    }

    public function render(): void {
        $settings_integration = SettingsService::get_group('integration');
        $api_endpoints = $this->get_api_endpoints();
        $hooks_data    = $this->get_hooks_data();
        $api_logs      = $this->get_api_logs();

        require $this->get_view_path('tab-developer.php');
    }

    /**
     * 取得 REST API 端點清單（結構化資料）
     *
     * @return array
     */
    private function get_api_endpoints(): array {
        $base = rest_url('line-hub/v1');

        return [
            [
                'method'      => 'POST',
                'path'        => '/messages/text',
                'title'       => '發送文字訊息',
                'description' => '發送一則文字訊息給指定的 WordPress 用戶（透過 user_id 或 email 查找）。',
                'params'      => [
                    ['name' => 'user_id', 'type' => 'int',    'required' => '擇一必填', 'desc' => 'WordPress 用戶 ID'],
                    ['name' => 'email',   'type' => 'string', 'required' => '擇一必填', 'desc' => '用戶 Email（系統自動查找對應 user_id）'],
                    ['name' => 'message', 'type' => 'string', 'required' => '必填',     'desc' => '訊息文字內容'],
                ],
                'curl' => sprintf(
                    "curl -X POST %s/messages/text \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"user_id\": 123, \"message\": \"你好！\"}'",
                    $base
                ),
                'response' => '{"success": true, "message": "訊息已發送"}',
            ],
            [
                'method'      => 'POST',
                'path'        => '/messages/flex',
                'title'       => '發送 Flex 訊息',
                'description' => '發送 LINE Flex Message 給指定用戶，適用於訂單通知、卡片等結構化訊息。',
                'params'      => [
                    ['name' => 'user_id',  'type' => 'int',    'required' => '擇一必填', 'desc' => 'WordPress 用戶 ID'],
                    ['name' => 'email',    'type' => 'string', 'required' => '擇一必填', 'desc' => '用戶 Email'],
                    ['name' => 'alt_text', 'type' => 'string', 'required' => '選填',     'desc' => '替代文字（預設：通知）'],
                    ['name' => 'contents', 'type' => 'object', 'required' => '必填',     'desc' => 'Flex Message JSON 結構'],
                ],
                'curl' => sprintf(
                    "curl -X POST %s/messages/flex \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"user_id\": 123, \"alt_text\": \"訂單通知\", \"contents\": {\"type\": \"bubble\", \"body\": {\"type\": \"box\", \"layout\": \"vertical\", \"contents\": [{\"type\": \"text\", \"text\": \"訂單已建立\"}]}}}'",
                    $base
                ),
                'response' => '{"success": true, "message": "Flex 訊息已發送"}',
            ],
            [
                'method'      => 'POST',
                'path'        => '/messages/broadcast',
                'title'       => '批量發送訊息',
                'description' => '一次發送文字訊息給多位用戶，單次上限 100 人。',
                'params'      => [
                    ['name' => 'user_ids', 'type' => 'int[]',  'required' => '必填', 'desc' => 'WordPress 用戶 ID 陣列（上限 100）'],
                    ['name' => 'message',  'type' => 'string', 'required' => '必填', 'desc' => '訊息文字內容'],
                ],
                'curl' => sprintf(
                    "curl -X POST %s/messages/broadcast \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"user_ids\": [1, 2, 3], \"message\": \"公告訊息\"}'",
                    $base
                ),
                'response' => '{"success": true, "message": "批量訊息已發送", "count": 3}',
            ],
            [
                'method'      => 'GET',
                'path'        => '/users/{id}/binding',
                'title'       => '查詢用戶綁定狀態',
                'description' => '查詢指定 WordPress 用戶的 LINE 綁定狀態與資訊。',
                'params'      => [
                    ['name' => 'id', 'type' => 'int', 'required' => '必填（URL 參數）', 'desc' => 'WordPress 用戶 ID'],
                ],
                'curl' => sprintf(
                    "curl %s/users/123/binding \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\"",
                    $base
                ),
                'response' => '{"success": true, "user_id": 123, "is_linked": true, "line_uid": "U1234...", "display_name": "用戶名", "picture_url": "https://..."}',
            ],
            [
                'method'      => 'GET',
                'path'        => '/users/lookup',
                'title'       => '用 Email 查詢用戶',
                'description' => '透過 Email 地址查詢 WordPress 用戶及其 LINE 綁定狀態。',
                'params'      => [
                    ['name' => 'email', 'type' => 'string', 'required' => '必填（Query 參數）', 'desc' => '用戶 Email 地址'],
                ],
                'curl' => sprintf(
                    "curl \"%s/users/lookup?email=user@example.com\" \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\"",
                    $base
                ),
                'response' => '{"success": true, "user_id": 123, "display_name": "用戶名", "email": "user@example.com", "is_linked": true, "line_uid": "U1234..."}',
            ],
        ];
    }

    /**
     * 取得 Hook 文件資料（結構化）
     *
     * @return array
     */
    private function get_hooks_data(): array {
        return [
            'actions' => [
                [
                    'hook'        => 'line_hub/send/text',
                    'description' => '發送文字訊息給指定用戶。適用於訂單通知、歡迎訊息等場景。',
                    'params'      => [
                        ['name' => 'user_id', 'type' => 'int',    'desc' => 'WordPress 用戶 ID（必填）'],
                        ['name' => 'message', 'type' => 'string', 'desc' => '訊息文字（必填）'],
                    ],
                    'example'     => "// 在訂單建立時發送通知\nadd_action('fluentcart/order/created', function(\$order) {\n    do_action('line_hub/send/text', [\n        'user_id' => \$order->user_id,\n        'message' => sprintf('您的訂單 #%s 已建立，感謝您的購買！', \$order->id),\n    ]);\n});",
                ],
                [
                    'hook'        => 'line_hub/send/flex',
                    'description' => '發送 Flex Message 給指定用戶。適用於結構化的通知卡片。',
                    'params'      => [
                        ['name' => 'user_id',  'type' => 'int',    'desc' => 'WordPress 用戶 ID（必填）'],
                        ['name' => 'alt_text', 'type' => 'string', 'desc' => '替代文字（選填，預設「通知」）'],
                        ['name' => 'contents', 'type' => 'array',  'desc' => 'Flex Message JSON 結構（必填）'],
                    ],
                    'example'     => "do_action('line_hub/send/flex', [\n    'user_id'  => 123,\n    'alt_text' => '出貨通知',\n    'contents' => [\n        'type' => 'bubble',\n        'body' => [\n            'type'     => 'box',\n            'layout'   => 'vertical',\n            'contents' => [\n                ['type' => 'text', 'text' => '您的包裹已出貨！', 'weight' => 'bold'],\n                ['type' => 'text', 'text' => '物流單號：1234567890'],\n            ],\n        ],\n    ],\n]);",
                ],
                [
                    'hook'        => 'line_hub/send/broadcast',
                    'description' => '批量發送文字訊息給多位用戶，單次上限 100 人。',
                    'params'      => [
                        ['name' => 'user_ids', 'type' => 'int[]',  'desc' => 'WordPress 用戶 ID 陣列（必填，上限 100）'],
                        ['name' => 'message',  'type' => 'string', 'desc' => '訊息文字（必填）'],
                    ],
                    'example'     => "// 發送公告給所有管理員\n\$admins = get_users(['role' => 'administrator', 'fields' => 'ID']);\ndo_action('line_hub/send/broadcast', [\n    'user_ids' => \$admins,\n    'message'  => '系統維護通知：今晚 22:00 進行例行維護。',\n]);",
                ],
            ],
            'filters' => [
                [
                    'hook'        => 'line_hub/user/is_linked',
                    'description' => '查詢指定用戶是否已綁定 LINE 帳號。',
                    'params'      => [
                        ['name' => '$default', 'type' => 'bool', 'desc' => '預設值（false）'],
                        ['name' => '$user_id', 'type' => 'int',  'desc' => 'WordPress 用戶 ID'],
                    ],
                    'example'     => "// 檢查用戶是否已綁定 LINE\n\$is_linked = apply_filters('line_hub/user/is_linked', false, \$user_id);\nif (\$is_linked) {\n    // 用戶已綁定，可以發送 LINE 通知\n    do_action('line_hub/send/text', [\n        'user_id' => \$user_id,\n        'message' => '歡迎回來！',\n    ]);\n}",
                ],
                [
                    'hook'        => 'line_hub/user/get_line_uid',
                    'description' => '取得指定用戶的 LINE UID。',
                    'params'      => [
                        ['name' => '$default', 'type' => 'string', 'desc' => '預設值（空字串）'],
                        ['name' => '$user_id', 'type' => 'int',    'desc' => 'WordPress 用戶 ID'],
                    ],
                    'example'     => "// 取得用戶的 LINE UID\n\$line_uid = apply_filters('line_hub/user/get_line_uid', '', \$user_id);\nif (!empty(\$line_uid)) {\n    error_log('用戶 LINE UID: ' . \$line_uid);\n}",
                ],
            ],
        ];
    }

    /**
     * 取得 API 使用記錄
     *
     * @return array
     */
    private function get_api_logs(): array {
        if (!class_exists('\\LineHub\\Services\\ApiLogger')) {
            return [];
        }
        return \LineHub\Services\ApiLogger::get_recent(20);
    }
}
