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
        return __('Developer', 'line-hub');
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
                'title'       => __('Send Text Message', 'line-hub'),
                'description' => __('Send a text message to a specified WordPress user (lookup by user_id or email).', 'line-hub'),
                'params'      => [
                    ['name' => 'user_id', 'type' => 'int',    'required' => __('Either required', 'line-hub'), 'desc' => __('WordPress user ID', 'line-hub')],
                    ['name' => 'email',   'type' => 'string', 'required' => __('Either required', 'line-hub'), 'desc' => __('User email (system auto-lookup for user_id)', 'line-hub')],
                    ['name' => 'message', 'type' => 'string', 'required' => __('Required', 'line-hub'),        'desc' => __('Message text content', 'line-hub')],
                ],
                'curl' => sprintf(
                    "curl -X POST %s/messages/text \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"user_id\": 123, \"message\": \"你好！\"}'",
                    $base
                ),
                'response' => '{"success": true, "message": "Message sent"}',
            ],
            [
                'method'      => 'POST',
                'path'        => '/messages/flex',
                'title'       => __('Send Flex Message', 'line-hub'),
                'description' => __('Send a LINE Flex Message to a specified user. Suitable for order notifications, cards, and other structured messages.', 'line-hub'),
                'params'      => [
                    ['name' => 'user_id',  'type' => 'int',    'required' => __('Either required', 'line-hub'), 'desc' => __('WordPress user ID', 'line-hub')],
                    ['name' => 'email',    'type' => 'string', 'required' => __('Either required', 'line-hub'), 'desc' => __('User email', 'line-hub')],
                    ['name' => 'alt_text', 'type' => 'string', 'required' => __('Optional', 'line-hub'),        'desc' => __('Alt text (default: Notification)', 'line-hub')],
                    ['name' => 'contents', 'type' => 'object', 'required' => __('Required', 'line-hub'),        'desc' => __('Flex Message JSON structure', 'line-hub')],
                ],
                'curl' => sprintf(
                    "curl -X POST %s/messages/flex \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"user_id\": 123, \"alt_text\": \"訂單通知\", \"contents\": {\"type\": \"bubble\", \"body\": {\"type\": \"box\", \"layout\": \"vertical\", \"contents\": [{\"type\": \"text\", \"text\": \"訂單已建立\"}]}}}'",
                    $base
                ),
                'response' => '{"success": true, "message": "Flex message sent"}',
            ],
            [
                'method'      => 'POST',
                'path'        => '/messages/broadcast',
                'title'       => __('Broadcast Messages', 'line-hub'),
                'description' => __('Send a text message to multiple users at once. Maximum 100 users per request.', 'line-hub'),
                'params'      => [
                    ['name' => 'user_ids', 'type' => 'int[]',  'required' => __('Required', 'line-hub'), 'desc' => __('Array of WordPress user IDs (max 100)', 'line-hub')],
                    ['name' => 'message',  'type' => 'string', 'required' => __('Required', 'line-hub'), 'desc' => __('Message text content', 'line-hub')],
                ],
                'curl' => sprintf(
                    "curl -X POST %s/messages/broadcast \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"user_ids\": [1, 2, 3], \"message\": \"公告訊息\"}'",
                    $base
                ),
                'response' => '{"success": true, "message": "Broadcast sent", "count": 3}',
            ],
            [
                'method'      => 'GET',
                'path'        => '/users/{id}/binding',
                'title'       => __('Query User Binding Status', 'line-hub'),
                'description' => __('Query the LINE binding status and information for a specified WordPress user.', 'line-hub'),
                'params'      => [
                    ['name' => 'id', 'type' => 'int', 'required' => __('Required (URL param)', 'line-hub'), 'desc' => __('WordPress user ID', 'line-hub')],
                ],
                'curl' => sprintf(
                    "curl %s/users/123/binding \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\"",
                    $base
                ),
                'response' => '{"success": true, "user_id": 123, "is_linked": true, "line_uid": "U1234...", "display_name": "Username", "picture_url": "https://..."}',
            ],
            [
                'method'      => 'GET',
                'path'        => '/users/lookup',
                'title'       => __('Lookup User by Email', 'line-hub'),
                'description' => __('Look up a WordPress user and their LINE binding status by email address.', 'line-hub'),
                'params'      => [
                    ['name' => 'email', 'type' => 'string', 'required' => __('Required (query param)', 'line-hub'), 'desc' => __('User email address', 'line-hub')],
                ],
                'curl' => sprintf(
                    "curl \"%s/users/lookup?email=user@example.com\" \\\n  -H \"X-LineHub-API-Key: lhk_your_api_key\"",
                    $base
                ),
                'response' => '{"success": true, "user_id": 123, "display_name": "Username", "email": "user@example.com", "is_linked": true, "line_uid": "U1234..."}',
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
                    'description' => __('Send a text message to a specified user. Suitable for order notifications, welcome messages, etc.', 'line-hub'),
                    'params'      => [
                        ['name' => 'user_id', 'type' => 'int',    'desc' => __('WordPress user ID (required)', 'line-hub')],
                        ['name' => 'message', 'type' => 'string', 'desc' => __('Message text (required)', 'line-hub')],
                    ],
                    'example'     => "// 在訂單建立時發送通知\nadd_action('fluentcart/order/created', function(\$order) {\n    do_action('line_hub/send/text', [\n        'user_id' => \$order->user_id,\n        'message' => sprintf('您的訂單 #%s 已建立，感謝您的購買！', \$order->id),\n    ]);\n});",
                ],
                [
                    'hook'        => 'line_hub/send/flex',
                    'description' => __('Send a Flex Message to a specified user. Suitable for structured notification cards.', 'line-hub'),
                    'params'      => [
                        ['name' => 'user_id',  'type' => 'int',    'desc' => __('WordPress user ID (required)', 'line-hub')],
                        ['name' => 'alt_text', 'type' => 'string', 'desc' => __('Alt text (optional, default: "Notification")', 'line-hub')],
                        ['name' => 'contents', 'type' => 'array',  'desc' => __('Flex Message JSON structure (required)', 'line-hub')],
                    ],
                    'example'     => "do_action('line_hub/send/flex', [\n    'user_id'  => 123,\n    'alt_text' => '出貨通知',\n    'contents' => [\n        'type' => 'bubble',\n        'body' => [\n            'type'     => 'box',\n            'layout'   => 'vertical',\n            'contents' => [\n                ['type' => 'text', 'text' => '您的包裹已出貨！', 'weight' => 'bold'],\n                ['type' => 'text', 'text' => '物流單號：1234567890'],\n            ],\n        ],\n    ],\n]);",
                ],
                [
                    'hook'        => 'line_hub/send/broadcast',
                    'description' => __('Send a text message to multiple users at once. Maximum 100 users per request.', 'line-hub'),
                    'params'      => [
                        ['name' => 'user_ids', 'type' => 'int[]',  'desc' => __('Array of WordPress user IDs (required, max 100)', 'line-hub')],
                        ['name' => 'message',  'type' => 'string', 'desc' => __('Message text (required)', 'line-hub')],
                    ],
                    'example'     => "// 發送公告給所有管理員\n\$admins = get_users(['role' => 'administrator', 'fields' => 'ID']);\ndo_action('line_hub/send/broadcast', [\n    'user_ids' => \$admins,\n    'message'  => '系統維護通知：今晚 22:00 進行例行維護。',\n]);",
                ],
            ],
            'filters' => [
                [
                    'hook'        => 'line_hub/user/is_linked',
                    'description' => __('Check if a specified user has linked their LINE account.', 'line-hub'),
                    'params'      => [
                        ['name' => '$default', 'type' => 'bool', 'desc' => __('Default value (false)', 'line-hub')],
                        ['name' => '$user_id', 'type' => 'int',  'desc' => __('WordPress user ID', 'line-hub')],
                    ],
                    'example'     => "// 檢查用戶是否已綁定 LINE\n\$is_linked = apply_filters('line_hub/user/is_linked', false, \$user_id);\nif (\$is_linked) {\n    // 用戶已綁定，可以發送 LINE 通知\n    do_action('line_hub/send/text', [\n        'user_id' => \$user_id,\n        'message' => '歡迎回來！',\n    ]);\n}",
                ],
                [
                    'hook'        => 'line_hub/user/get_line_uid',
                    'description' => __('Get the LINE UID for a specified user.', 'line-hub'),
                    'params'      => [
                        ['name' => '$default', 'type' => 'string', 'desc' => __('Default value (empty string)', 'line-hub')],
                        ['name' => '$user_id', 'type' => 'int',    'desc' => __('WordPress user ID', 'line-hub')],
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
