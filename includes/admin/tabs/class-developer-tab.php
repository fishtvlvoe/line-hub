<?php
/**
 * 開發者 Tab
 *
 * API Key 管理、REST API 端點、WordPress Hooks、Shortcodes、PHP 範例。
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
        require $this->get_view_path('tab-developer.php');
    }
}
