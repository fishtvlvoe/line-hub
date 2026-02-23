<?php
/**
 * 開發者 Tab
 *
 * API Key 管理、REST API 端點、WordPress Hooks、Shortcodes、Webhook 事件記錄。
 *
 * @package LineHub\Admin\Tabs
 * @since 2.0.0
 */

namespace LineHub\Admin\Tabs;

use LineHub\Services\SettingsService;
use LineHub\Webhook\WebhookLogger;

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

    /**
     * 渲染開發者 Tab
     */
    public function render(): void {
        $settings_integration = SettingsService::get_group('integration');
        $events = WebhookLogger::getRecent(20);
        require $this->get_view_path('tab-developer.php');
    }

    // 開發者 Tab 無 form 儲存（API Key 操作用獨立 admin_post action）
}
