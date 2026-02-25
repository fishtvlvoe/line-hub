<?php
/**
 * 設定嚮導 Tab
 *
 * 連線狀態總覽和設定步驟說明。只讀，無 form。
 *
 * @package LineHub\Admin\Tabs
 * @since 2.0.0
 */

namespace LineHub\Admin\Tabs;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class WizardTab extends AbstractTab {

    public function get_slug(): string {
        return 'wizard';
    }

    public function get_label(): string {
        return __('Setup Wizard', 'line-hub');
    }

    public function render(): void {
        $settings = SettingsService::get_group('general');
        require $this->get_view_path('tab-wizard.php');
    }
}
