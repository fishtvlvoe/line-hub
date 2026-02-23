<?php
/**
 * LINE 設定 Tab
 *
 * Messaging API、LINE Login Channel、NSL 整合設定。
 *
 * @package LineHub\Admin\Tabs
 * @since 2.0.0
 */

namespace LineHub\Admin\Tabs;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class LineSettingsTab extends AbstractTab {

    public function get_slug(): string {
        return 'line-settings';
    }

    public function get_label(): string {
        return 'LINE 設定';
    }

    public function render(): void {
        $settings = SettingsService::get_group('general');
        $site_url = home_url();
        require $this->get_view_path('tab-line-settings.php');
    }

    /**
     * 儲存 LINE 設定（按 section 隔離）
     */
    public function save(array $post_data): bool {
        $section = sanitize_key($post_data['section'] ?? '');

        switch ($section) {
            case 'messaging':
                return $this->save_messaging_section($post_data);
            case 'login':
                return $this->save_login_section($post_data);
            case 'nsl':
                return $this->save_nsl_section($post_data);
            default:
                $this->save_messaging_section($post_data);
                $this->save_login_section($post_data);
                $this->save_nsl_section($post_data);
                return true;
        }
    }

    private function save_messaging_section(array $post_data): bool {
        $success = true;
        foreach (['channel_id', 'channel_secret'] as $field) {
            $value = isset($post_data[$field]) ? sanitize_text_field($post_data[$field]) : '';
            if (!SettingsService::set('general', $field, $value)) {
                $success = false;
            }
        }
        $access_token = isset($post_data['access_token']) ? sanitize_textarea_field($post_data['access_token']) : '';
        if (!SettingsService::set('general', 'access_token', $access_token)) {
            $success = false;
        }
        return $success;
    }

    private function save_login_section(array $post_data): bool {
        $success = true;
        foreach (['login_channel_id', 'login_channel_secret', 'liff_id'] as $field) {
            $value = isset($post_data[$field]) ? sanitize_text_field($post_data[$field]) : '';
            if (!SettingsService::set('general', $field, $value)) {
                $success = false;
            }
        }
        return $success;
    }

    private function save_nsl_section(array $post_data): bool {
        foreach (['nsl_compat_mode', 'nsl_auto_migrate'] as $field) {
            $value = isset($post_data[$field]) && $post_data[$field] === '1';
            SettingsService::set('general', $field, $value);
        }
        return true;
    }
}
