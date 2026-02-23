<?php
/**
 * 登入設定 Tab
 *
 * 登入模式、LINE Login 行為、新用戶設定、登入按鈕、重定向、安全性。
 *
 * @package LineHub\Admin\Tabs
 * @since 2.0.0
 */

namespace LineHub\Admin\Tabs;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class LoginSettingsTab extends AbstractTab {

    public function get_slug(): string {
        return 'login-settings';
    }

    public function get_label(): string {
        return '登入設定';
    }

    public function render(): void {
        $settings_general = SettingsService::get_group('general');
        $settings_login = SettingsService::get_group('login');
        require $this->get_view_path('tab-login-settings.php');
    }

    /**
     * 儲存登入設定
     */
    public function save(array $post_data): bool {
        $success = true;

        // general group 的登入相關欄位
        $general_strings = [
            'login_mode', 'username_prefix', 'display_name_prefix',
            'login_redirect_url', 'login_button_text', 'login_button_size',
            'allowed_email_domains',
        ];
        foreach ($general_strings as $field) {
            $value = isset($post_data[$field]) ? sanitize_text_field($post_data[$field]) : '';
            SettingsService::set('general', $field, $value);
        }

        $general_booleans = ['auto_link_by_email', 'login_redirect_fixed', 'require_email_verification'];
        foreach ($general_booleans as $field) {
            $value = isset($post_data[$field]) && $post_data[$field] === '1';
            SettingsService::set('general', $field, $value);
        }

        // 預設角色（陣列，可多選）
        $default_roles = isset($post_data['default_roles']) && is_array($post_data['default_roles'])
            ? array_map('sanitize_key', $post_data['default_roles'])
            : ['subscriber'];
        SettingsService::set('general', 'default_roles', $default_roles);

        // 登入按鈕位置（陣列）
        $positions = isset($post_data['login_button_positions']) && is_array($post_data['login_button_positions'])
            ? array_map('sanitize_text_field', $post_data['login_button_positions'])
            : [];
        SettingsService::set('general', 'login_button_positions', $positions);

        // login group 欄位
        $login_strings = ['bot_prompt', 'initial_amr'];
        foreach ($login_strings as $field) {
            $value = isset($post_data[$field]) ? sanitize_text_field($post_data[$field]) : '';
            SettingsService::set('login', $field, $value);
        }

        $login_booleans = ['force_reauth', 'switch_amr', 'allow_auto_login'];
        foreach ($login_booleans as $field) {
            $value = isset($post_data[$field]) && $post_data[$field] === '1';
            SettingsService::set('login', $field, $value);
        }

        return $success;
    }
}
