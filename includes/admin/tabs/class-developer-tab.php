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
        return __('Developer', 'buygo-hub-for-line');
    }

    public function render(): void {
        $settings_integration = SettingsService::get_group('integration');
        $api_endpoints = $this->get_api_endpoints();
        $hooks_data    = $this->get_hooks_data();
        $api_logs      = $this->get_api_logs();
        $openclaw_settings = $this->get_openclaw_settings();

        // 載入開發者頁面腳本
        $lh_ver = defined('LINE_HUB_VERSION') ? LINE_HUB_VERSION : '1.0.0';
        wp_enqueue_script(
            'line-hub-admin-developer',
            plugins_url('assets/js/admin-developer.js', dirname(dirname(dirname(__FILE__)))),
            [],
            $lh_ver,
            true
        );
        wp_localize_script('line-hub-admin-developer', 'lineHubDev', [
            'nonce' => wp_create_nonce('line_hub_openclaw_test'),
            'i18n'  => [
                'urlTokenRequired' => __('URL and Token are required', 'buygo-hub-for-line'),
                'testing'          => __('Testing...', 'buygo-hub-for-line'),
                'testConnection'   => __('Test Connection', 'buygo-hub-for-line'),
                'requestFailed'    => __('Request failed', 'buygo-hub-for-line'),
            ],
        ]);

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
                'title'       => __('Send Text Message', 'buygo-hub-for-line'),
                'description' => __('Send a text message to a specified WordPress user (lookup by user_id or email).', 'buygo-hub-for-line'),
                'params'      => [
                    ['name' => 'user_id', 'type' => 'int',    'required' => __('Either required', 'buygo-hub-for-line'), 'desc' => __('WordPress user ID', 'buygo-hub-for-line')],
                    ['name' => 'email',   'type' => 'string', 'required' => __('Either required', 'buygo-hub-for-line'), 'desc' => __('User email (system auto-lookup for user_id)', 'buygo-hub-for-line')],
                    ['name' => 'message', 'type' => 'string', 'required' => __('Required', 'buygo-hub-for-line'),        'desc' => __('Message text content', 'buygo-hub-for-line')],
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
                'title'       => __('Send Flex Message', 'buygo-hub-for-line'),
                'description' => __('Send a LINE Flex Message to a specified user. Suitable for order notifications, cards, and other structured messages.', 'buygo-hub-for-line'),
                'params'      => [
                    ['name' => 'user_id',  'type' => 'int',    'required' => __('Either required', 'buygo-hub-for-line'), 'desc' => __('WordPress user ID', 'buygo-hub-for-line')],
                    ['name' => 'email',    'type' => 'string', 'required' => __('Either required', 'buygo-hub-for-line'), 'desc' => __('User email', 'buygo-hub-for-line')],
                    ['name' => 'alt_text', 'type' => 'string', 'required' => __('Optional', 'buygo-hub-for-line'),        'desc' => __('Alt text (default: Notification)', 'buygo-hub-for-line')],
                    ['name' => 'contents', 'type' => 'object', 'required' => __('Required', 'buygo-hub-for-line'),        'desc' => __('Flex Message JSON structure', 'buygo-hub-for-line')],
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
                'title'       => __('Broadcast Messages', 'buygo-hub-for-line'),
                'description' => __('Send a text message to multiple users at once. Maximum 100 users per request.', 'buygo-hub-for-line'),
                'params'      => [
                    ['name' => 'user_ids', 'type' => 'int[]',  'required' => __('Required', 'buygo-hub-for-line'), 'desc' => __('Array of WordPress user IDs (max 100)', 'buygo-hub-for-line')],
                    ['name' => 'message',  'type' => 'string', 'required' => __('Required', 'buygo-hub-for-line'), 'desc' => __('Message text content', 'buygo-hub-for-line')],
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
                'title'       => __('Query User Binding Status', 'buygo-hub-for-line'),
                'description' => __('Query the LINE binding status and information for a specified WordPress user.', 'buygo-hub-for-line'),
                'params'      => [
                    ['name' => 'id', 'type' => 'int', 'required' => __('Required (URL param)', 'buygo-hub-for-line'), 'desc' => __('WordPress user ID', 'buygo-hub-for-line')],
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
                'title'       => __('Lookup User by Email', 'buygo-hub-for-line'),
                'description' => __('Look up a WordPress user and their LINE binding status by email address.', 'buygo-hub-for-line'),
                'params'      => [
                    ['name' => 'email', 'type' => 'string', 'required' => __('Required (query param)', 'buygo-hub-for-line'), 'desc' => __('User email address', 'buygo-hub-for-line')],
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
                    'description' => __('Send a text message to a specified user. Suitable for order notifications, welcome messages, etc.', 'buygo-hub-for-line'),
                    'params'      => [
                        ['name' => 'user_id', 'type' => 'int',    'desc' => __('WordPress user ID (required)', 'buygo-hub-for-line')],
                        ['name' => 'message', 'type' => 'string', 'desc' => __('Message text (required)', 'buygo-hub-for-line')],
                    ],
                    'example'     => "// 在訂單建立時發送通知\nadd_action('fluentcart/order/created', function(\$order) {\n    do_action('line_hub/send/text', [\n        'user_id' => \$order->user_id,\n        'message' => sprintf('您的訂單 #%s 已建立，感謝您的購買！', \$order->id),\n    ]);\n});",
                ],
                [
                    'hook'        => 'line_hub/send/flex',
                    'description' => __('Send a Flex Message to a specified user. Suitable for structured notification cards.', 'buygo-hub-for-line'),
                    'params'      => [
                        ['name' => 'user_id',  'type' => 'int',    'desc' => __('WordPress user ID (required)', 'buygo-hub-for-line')],
                        ['name' => 'alt_text', 'type' => 'string', 'desc' => __('Alt text (optional, default: "Notification")', 'buygo-hub-for-line')],
                        ['name' => 'contents', 'type' => 'array',  'desc' => __('Flex Message JSON structure (required)', 'buygo-hub-for-line')],
                    ],
                    'example'     => "do_action('line_hub/send/flex', [\n    'user_id'  => 123,\n    'alt_text' => '出貨通知',\n    'contents' => [\n        'type' => 'bubble',\n        'body' => [\n            'type'     => 'box',\n            'layout'   => 'vertical',\n            'contents' => [\n                ['type' => 'text', 'text' => '您的包裹已出貨！', 'weight' => 'bold'],\n                ['type' => 'text', 'text' => '物流單號：1234567890'],\n            ],\n        ],\n    ],\n]);",
                ],
                [
                    'hook'        => 'line_hub/send/broadcast',
                    'description' => __('Send a text message to multiple users at once. Maximum 100 users per request.', 'buygo-hub-for-line'),
                    'params'      => [
                        ['name' => 'user_ids', 'type' => 'int[]',  'desc' => __('Array of WordPress user IDs (required, max 100)', 'buygo-hub-for-line')],
                        ['name' => 'message',  'type' => 'string', 'desc' => __('Message text (required)', 'buygo-hub-for-line')],
                    ],
                    'example'     => "// 發送公告給所有管理員\n\$admins = get_users(['role' => 'administrator', 'fields' => 'ID']);\ndo_action('line_hub/send/broadcast', [\n    'user_ids' => \$admins,\n    'message'  => '系統維護通知：今晚 22:00 進行例行維護。',\n]);",
                ],
            ],
            'filters' => [
                [
                    'hook'        => 'line_hub/user/is_linked',
                    'description' => __('Check if a specified user has linked their LINE account.', 'buygo-hub-for-line'),
                    'params'      => [
                        ['name' => '$default', 'type' => 'bool', 'desc' => __('Default value (false)', 'buygo-hub-for-line')],
                        ['name' => '$user_id', 'type' => 'int',  'desc' => __('WordPress user ID', 'buygo-hub-for-line')],
                    ],
                    'example'     => "// 檢查用戶是否已綁定 LINE\n\$is_linked = apply_filters('line_hub/user/is_linked', false, \$user_id);\nif (\$is_linked) {\n    // 用戶已綁定，可以發送 LINE 通知\n    do_action('line_hub/send/text', [\n        'user_id' => \$user_id,\n        'message' => '歡迎回來！',\n    ]);\n}",
                ],
                [
                    'hook'        => 'line_hub/user/get_line_uid',
                    'description' => __('Get the LINE UID for a specified user.', 'buygo-hub-for-line'),
                    'params'      => [
                        ['name' => '$default', 'type' => 'string', 'desc' => __('Default value (empty string)', 'buygo-hub-for-line')],
                        ['name' => '$user_id', 'type' => 'int',    'desc' => __('WordPress user ID', 'buygo-hub-for-line')],
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

    /**
     * 取得 OpenClaw 設定資料
     *
     * @return array{enabled: bool, url: string, token: string}
     */
    private function get_openclaw_settings(): array {
        return [
            'enabled' => SettingsService::get('integration', 'openclaw_enabled') ?? false,
            'url'     => SettingsService::get('integration', 'openclaw_webhook_url') ?? '',
            'token'   => SettingsService::get('integration', 'openclaw_webhook_token') ?? '',
        ];
    }
}
