<?php
/**
 * LINE Hub Settings Page
 *
 * WordPress 後台設定頁面（3 Tab 導航版）
 * Tab：設定 / 登入 / 開發者
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub\Admin;

use LineHub\Services\SettingsService;
use LineHub\Messaging\MessagingService;
use LineHub\Webhook\WebhookLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsPage
 *
 * 負責：
 * - 註冊後台選單
 * - Tab 導航系統（設定 / 登入 / 開發者）
 * - 渲染各個 Tab（委託 view 檔案）
 * - 處理表單提交（按 Tab 隔離儲存）
 * - 測試連線功能
 */
class SettingsPage {
    /**
     * 可用的 Tabs
     */
    private const TABS = [
        'settings'  => '設定',
        'login'     => '登入',
        'developer' => '開發者',
    ];

    /**
     * 初始化
     */
    public static function init(): void {
        $instance = new self();
        add_action('admin_menu', [$instance, 'register_menu']);
        add_action('admin_post_line_hub_save_settings', [$instance, 'handle_save']);
        add_action('admin_post_line_hub_test_connection', [$instance, 'handle_test_connection']);
        add_action('admin_post_line_hub_generate_api_key', [$instance, 'handle_generate_api_key']);
        add_action('admin_post_line_hub_revoke_api_key', [$instance, 'handle_revoke_api_key']);
        add_action('admin_enqueue_scripts', [$instance, 'enqueue_assets']);
    }

    /**
     * 載入 CSS 和 JS
     */
    public function enqueue_assets($hook): void {
        if ($hook !== 'toplevel_page_line-hub-settings') {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        $version = defined('LINE_HUB_VERSION') ? LINE_HUB_VERSION : '1.0.0';

        wp_enqueue_style(
            'line-hub-admin-tabs',
            $plugin_url . 'assets/css/admin-tabs.css',
            [],
            $version
        );

        wp_enqueue_script(
            'line-hub-admin-tabs',
            $plugin_url . 'assets/js/admin-tabs.js',
            [],
            $version,
            true
        );
    }

    /**
     * 註冊後台選單
     */
    public function register_menu(): void {
        add_menu_page(
            'LINE Hub 設定',
            'LINE Hub',
            'manage_options',
            'line-hub-settings',
            [$this, 'render_page'],
            'dashicons-format-chat',
            30
        );
    }

    /**
     * 渲染設定頁面（主入口）
     */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有權限訪問此頁面', 'line-hub'));
        }

        $this->show_admin_notices();

        $current_tab = sanitize_key($_GET['tab'] ?? 'settings');
        if (!isset(self::TABS[$current_tab])) {
            $current_tab = 'settings';
        }

        ?>
        <div class="wrap">
            <h1>LINE Hub</h1>

            <nav class="line-hub-tabs">
                <ul class="line-hub-tabs-wrapper">
                    <?php foreach (self::TABS as $tab_id => $tab_label): ?>
                        <li class="line-hub-tab <?php echo $current_tab === $tab_id ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => $tab_id], admin_url('admin.php'))); ?>">
                                <?php echo esc_html($tab_label); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <div class="line-hub-tab-content">
                <?php
                switch ($current_tab) {
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'login':
                        $this->render_login_tab();
                        break;
                    case 'developer':
                        $this->render_developer_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染「設定」Tab
     */
    private function render_settings_tab(): void {
        $settings = SettingsService::get_group('general');
        $site_url = home_url();
        require __DIR__ . '/views/tab-settings.php';
    }

    /**
     * 渲染「登入」Tab
     */
    private function render_login_tab(): void {
        $settings_general = SettingsService::get_group('general');
        $settings_login = SettingsService::get_group('login');
        require __DIR__ . '/views/tab-login.php';
    }

    /**
     * 渲染「開發者」Tab
     */
    private function render_developer_tab(): void {
        $settings_integration = SettingsService::get_group('integration');
        $events = WebhookLogger::getRecent(20);
        require __DIR__ . '/views/tab-developer.php';
    }

    /**
     * 處理設定儲存（按 Tab 隔離）
     */
    public function handle_save(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有權限執行此操作', 'line-hub'));
        }

        if (!isset($_POST['line_hub_nonce']) || !wp_verify_nonce($_POST['line_hub_nonce'], 'line_hub_save_settings')) {
            wp_die(__('安全驗證失敗', 'line-hub'));
        }

        $tab = sanitize_key($_POST['tab'] ?? 'settings');
        $success = true;

        switch ($tab) {
            case 'settings':
                $success = $this->save_settings_tab();
                break;
            case 'login':
                $success = $this->save_login_tab();
                break;
        }

        $redirect_url = add_query_arg(
            ['page' => 'line-hub-settings', 'tab' => $tab, 'updated' => $success ? 'true' : 'false'],
            admin_url('admin.php')
        );
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * 儲存「設定」Tab 的欄位
     */
    private function save_settings_tab(): bool {
        $success = true;

        // Channel 基本設定
        $channel_fields = ['channel_id', 'channel_secret', 'liff_id'];
        foreach ($channel_fields as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            if (!SettingsService::set('general', $field, $value)) {
                $success = false;
            }
        }

        // access_token 可能是多行
        $access_token = isset($_POST['access_token']) ? sanitize_textarea_field($_POST['access_token']) : '';
        if (!SettingsService::set('general', 'access_token', $access_token)) {
            $success = false;
        }

        // NSL 整合
        $nsl_booleans = ['nsl_compat_mode', 'nsl_auto_migrate'];
        foreach ($nsl_booleans as $field) {
            $value = isset($_POST[$field]) && $_POST[$field] === '1';
            SettingsService::set('general', $field, $value);
        }

        return $success;
    }

    /**
     * 儲存「登入」Tab 的欄位
     */
    private function save_login_tab(): bool {
        $success = true;

        // general group 的登入相關欄位
        $general_strings = ['login_mode', 'username_prefix', 'display_name_prefix', 'login_redirect_url', 'login_button_text', 'login_button_size', 'allowed_email_domains'];
        foreach ($general_strings as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            SettingsService::set('general', $field, $value);
        }

        $general_booleans = ['auto_link_by_email', 'login_redirect_fixed', 'require_email_verification'];
        foreach ($general_booleans as $field) {
            $value = isset($_POST[$field]) && $_POST[$field] === '1';
            SettingsService::set('general', $field, $value);
        }

        // 預設角色（陣列，可多選）
        $default_roles = isset($_POST['default_roles']) && is_array($_POST['default_roles'])
            ? array_map('sanitize_key', $_POST['default_roles'])
            : ['subscriber'];
        SettingsService::set('general', 'default_roles', $default_roles);

        // 登入按鈕位置（陣列）
        $positions = isset($_POST['login_button_positions']) && is_array($_POST['login_button_positions'])
            ? array_map('sanitize_text_field', $_POST['login_button_positions'])
            : [];
        SettingsService::set('general', 'login_button_positions', $positions);

        // login group 欄位
        $login_strings = ['bot_prompt', 'initial_amr'];
        foreach ($login_strings as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            SettingsService::set('login', $field, $value);
        }

        $login_booleans = ['force_reauth', 'switch_amr', 'allow_auto_login'];
        foreach ($login_booleans as $field) {
            $value = isset($_POST[$field]) && $_POST[$field] === '1';
            SettingsService::set('login', $field, $value);
        }

        return $success;
    }

    /**
     * 處理產生 API Key
     */
    public function handle_generate_api_key(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有權限執行此操作', 'line-hub'));
        }

        if (!isset($_POST['line_hub_api_nonce']) || !wp_verify_nonce($_POST['line_hub_api_nonce'], 'line_hub_api_key_action')) {
            wp_die(__('安全驗證失敗', 'line-hub'));
        }

        // 產生 lhk_ + 32 位隨機字串
        $raw_key = 'lhk_' . bin2hex(random_bytes(16));
        $prefix = substr($raw_key, 0, 8);

        // 儲存 hash（不存明文）
        SettingsService::set('integration', 'api_key_hash', wp_hash($raw_key));
        SettingsService::set('integration', 'api_key_prefix', $prefix);
        SettingsService::set('integration', 'api_key_created_at', current_time('mysql'));

        // 透過 transient 傳遞完整 Key（只顯示一次）
        set_transient('line_hub_new_api_key', $raw_key, 60);

        $redirect_url = add_query_arg(
            ['page' => 'line-hub-settings', 'tab' => 'developer', 'api_key_generated' => '1'],
            admin_url('admin.php')
        );
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * 處理撤銷 API Key
     */
    public function handle_revoke_api_key(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有權限執行此操作', 'line-hub'));
        }

        if (!isset($_POST['line_hub_api_nonce']) || !wp_verify_nonce($_POST['line_hub_api_nonce'], 'line_hub_api_key_action')) {
            wp_die(__('安全驗證失敗', 'line-hub'));
        }

        SettingsService::set('integration', 'api_key_hash', '');
        SettingsService::set('integration', 'api_key_prefix', '');
        SettingsService::set('integration', 'api_key_created_at', '');

        $redirect_url = add_query_arg(
            ['page' => 'line-hub-settings', 'tab' => 'developer', 'api_key_revoked' => '1'],
            admin_url('admin.php')
        );
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * 處理測試連線
     */
    public function handle_test_connection(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有權限執行此操作', 'line-hub'));
        }

        if (!isset($_POST['line_hub_test_nonce']) || !wp_verify_nonce($_POST['line_hub_test_nonce'], 'line_hub_test_connection')) {
            wp_die(__('安全驗證失敗', 'line-hub'));
        }

        $messaging_service = new MessagingService();
        $is_valid = $messaging_service->validateToken();

        $redirect_url = add_query_arg(
            [
                'page' => 'line-hub-settings',
                'tab' => 'settings',
                'test_result' => $is_valid ? 'success' : 'error',
            ],
            admin_url('admin.php')
        );
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * 顯示後台通知訊息
     */
    private function show_admin_notices(): void {
        $updated = sanitize_key($_GET['updated'] ?? '');
        if ($updated !== '') {
            $class = $updated === 'true' ? 'notice-success' : 'notice-error';
            $message = $updated === 'true' ? '設定已儲存' : '儲存設定時發生錯誤';
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
        }

        $test_result = sanitize_key($_GET['test_result'] ?? '');
        if ($test_result !== '') {
            $class = $test_result === 'success' ? 'notice-success' : 'notice-error';
            $message = $test_result === 'success' ? 'Access Token 驗證成功' : 'Access Token 驗證失敗，請檢查設定是否正確';
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
        }

        // API Key 通知
        if (isset($_GET['api_key_generated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>API Key 已產生，請立即複製保存。</p></div>';
        }
        if (isset($_GET['api_key_revoked'])) {
            echo '<div class="notice notice-warning is-dismissible"><p>API Key 已撤銷。</p></div>';
        }
    }
}
