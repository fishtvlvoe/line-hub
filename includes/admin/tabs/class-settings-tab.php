<?php
/**
 * 設定 Tab
 *
 * LINE Messaging API、LINE Login、NSL 整合設定。
 *
 * @package LineHub\Admin\Tabs
 * @since 2.0.0
 */

namespace LineHub\Admin\Tabs;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsTab extends AbstractTab {

    public function get_slug(): string {
        return 'settings';
    }

    public function get_label(): string {
        return '設定';
    }

    /**
     * 渲染設定 Tab
     */
    public function render(): void {
        $settings = SettingsService::get_group('general');
        $site_url = home_url();
        require $this->get_view_path('tab-settings.php');
    }

    /**
     * 儲存設定 Tab（按 section 隔離）
     *
     * @param array $post_data $_POST 資料
     * @return bool
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
                // 向下兼容：沒帶 section 的舊表單，儲存全部
                $this->save_messaging_section($post_data);
                $this->save_login_section($post_data);
                $this->save_nsl_section($post_data);
                return true;
        }
    }

    /**
     * 儲存 Messaging API 區塊
     */
    private function save_messaging_section(array $post_data): bool {
        $success = true;

        $fields = ['channel_id', 'channel_secret'];
        foreach ($fields as $field) {
            $value = isset($post_data[$field]) ? sanitize_text_field($post_data[$field]) : '';
            if (!SettingsService::set('general', $field, $value)) {
                $success = false;
            }
        }

        // access_token 可能是多行
        $access_token = isset($post_data['access_token']) ? sanitize_textarea_field($post_data['access_token']) : '';
        if (!SettingsService::set('general', 'access_token', $access_token)) {
            $success = false;
        }

        return $success;
    }

    /**
     * 儲存 LINE Login 區塊
     */
    private function save_login_section(array $post_data): bool {
        $success = true;

        $fields = ['login_channel_id', 'login_channel_secret', 'liff_id'];
        foreach ($fields as $field) {
            $value = isset($post_data[$field]) ? sanitize_text_field($post_data[$field]) : '';
            if (!SettingsService::set('general', $field, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * 儲存 NSL 整合區塊
     */
    private function save_nsl_section(array $post_data): bool {
        $nsl_booleans = ['nsl_compat_mode', 'nsl_auto_migrate'];
        foreach ($nsl_booleans as $field) {
            $value = isset($post_data[$field]) && $post_data[$field] === '1';
            SettingsService::set('general', $field, $value);
        }

        return true;
    }
}
