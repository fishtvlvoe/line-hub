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
        return __('LINE Settings', 'buygo-hub-for-line');
    }

    public function render(): void {
        $settings = SettingsService::get_group('general');
        $site_url = home_url();
        require $this->get_view_path('tab-line-settings.php');
    }

    /**
     * 儲存 LINE 設定（按 section 隔離）
     */
    public function save(): bool {
        // Nonce 和權限已在 SettingsPage::handle_save() → verify_admin() 驗證
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in SettingsPage::handle_save()
        $section = sanitize_key($_POST['section'] ?? '');

        switch ($section) {
            case 'messaging':
                return $this->save_messaging_section();
            case 'login':
                return $this->save_login_section();
            case 'nsl':
                return $this->save_nsl_section();
            default:
                $this->save_messaging_section();
                $this->save_login_section();
                $this->save_nsl_section();
                return true;
        }
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in SettingsPage::handle_save()

    private function save_messaging_section(): bool {
        $success = true;
        foreach (['channel_id', 'channel_secret'] as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            if (!SettingsService::set('general', $field, $value)) {
                $success = false;
            }
        }
        $access_token = isset($_POST['access_token']) ? sanitize_textarea_field($_POST['access_token']) : '';
        if (!SettingsService::set('general', 'access_token', $access_token)) {
            $success = false;
        }
        return $success;
    }

    private function save_login_section(): bool {
        $success = true;
        foreach (['login_channel_id', 'login_channel_secret', 'liff_id'] as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            if (!SettingsService::set('general', $field, $value)) {
                $success = false;
            }
        }
        return $success;
    }

    private function save_nsl_section(): bool {
        foreach (['nsl_compat_mode', 'nsl_auto_migrate'] as $field) {
            $value = isset($_POST[$field]) && $_POST[$field] === '1';
            SettingsService::set('general', $field, $value);
        }
        return true;
    }

    // phpcs:enable WordPress.Security.NonceVerification.Missing
}
