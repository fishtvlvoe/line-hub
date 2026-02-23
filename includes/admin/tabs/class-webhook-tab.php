<?php
/**
 * Webhook Tab
 *
 * Webhook 事件記錄。只讀，無 form。
 *
 * @package LineHub\Admin\Tabs
 * @since 2.0.0
 */

namespace LineHub\Admin\Tabs;

use LineHub\Webhook\WebhookLogger;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookTab extends AbstractTab {

    public function get_slug(): string {
        return 'webhook';
    }

    public function get_label(): string {
        return 'Webhook';
    }

    public function render(): void {
        $events = WebhookLogger::getRecent(20);
        require $this->get_view_path('tab-webhook.php');
    }
}
