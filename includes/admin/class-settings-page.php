<?php
/**
 * LINE Hub Settings Page
 *
 * WordPress 後台設定頁面（Tab 導航版）
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
 * - Tab 導航系統
 * - 渲染各個 Tab 頁面
 * - 處理表單提交
 * - 測試連線功能
 */
class SettingsPage {
    /**
     * 可用的 Tabs
     */
    private const TABS = [
        'getting-started' => '入門',
        'settings' => '設定',
        'webhooks' => 'Webhook',
        'usage' => '用法',
    ];

    /**
     * 初始化
     */
    public static function init(): void {
        $instance = new self();
        add_action('admin_menu', [$instance, 'register_menu']);
        add_action('admin_post_line_hub_save_settings', [$instance, 'handle_save']);
        add_action('admin_post_line_hub_test_connection', [$instance, 'handle_test_connection']);
        add_action('admin_enqueue_scripts', [$instance, 'enqueue_assets']);
    }

    /**
     * 載入 CSS 和 JS
     */
    public function enqueue_assets($hook): void {
        // 只在 LINE Hub 設定頁面載入
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
            'LINE Hub 設定',           // Page title
            'LINE Hub',                 // Menu title
            'manage_options',           // Capability
            'line-hub-settings',        // Menu slug
            [$this, 'render_page'],     // Callback
            'dashicons-format-chat',    // Icon
            30                          // Position
        );
    }

    /**
     * 渲染設定頁面（主入口）
     */
    public function render_page(): void {
        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有權限訪問此頁面', 'line-hub'));
        }

        // 顯示訊息
        $this->show_admin_notices();

        // 取得當前 Tab
        $current_tab = sanitize_key($_GET['tab'] ?? 'getting-started');

        // 驗證 Tab 是否有效
        if (!isset(self::TABS[$current_tab])) {
            $current_tab = 'getting-started';
        }

        ?>
        <div class="wrap">
            <h1>LINE Hub</h1>

            <!-- Tab 導航 -->
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

            <!-- Tab 內容 -->
            <div class="line-hub-tab-content">
                <?php
                switch ($current_tab) {
                    case 'getting-started':
                        $this->render_getting_started_tab();
                        break;

                    case 'settings':
                        $this->render_settings_tab();
                        break;

                    case 'webhooks':
                        $this->render_webhooks_tab();
                        break;

                    case 'usage':
                        $this->render_usage_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * 渲染「入門」Tab
     */
    private function render_getting_started_tab(): void {
        $site_url = home_url();
        require __DIR__ . '/views/tab-getting-started.php';
    }

    /**
     * 渲染「設定」Tab
     */
    private function render_settings_tab(): void {
        $settings = SettingsService::get_group('general');
        require __DIR__ . '/views/tab-settings.php';
    }

    /**
     * 渲染「Webhook」Tab
     */
    private function render_webhooks_tab(): void {
        $events = WebhookLogger::getRecent(20);
        require __DIR__ . '/views/tab-webhooks.php';
    }

    /**
     * 渲染「用法」Tab
     */
    private function render_usage_tab(): void {
        require __DIR__ . '/views/tab-usage.php';
    }

    /**
     * 處理設定儲存
     */
    public function handle_save(): void {
        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有權限執行此操作', 'line-hub'));
        }

        // 驗證 nonce
        if (!isset($_POST['line_hub_nonce']) || !wp_verify_nonce($_POST['line_hub_nonce'], 'line_hub_save_settings')) {
            wp_die(__('安全驗證失敗', 'line-hub'));
        }

        // 儲存基本設定
        $fields = ['channel_id', 'channel_secret', 'access_token', 'liff_id'];
        $success = true;

        foreach ($fields as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';

            // 特殊處理：access_token 可能是多行
            if ($field === 'access_token') {
                $value = isset($_POST[$field]) ? sanitize_textarea_field($_POST[$field]) : '';
            }

            $result = SettingsService::set('general', $field, $value);
            if (!$result) {
                $success = false;
            }
        }

        // 儲存進階設定（Task 3）
        $advanced_fields = [
            'nsl_compat_mode' => 'boolean',
            'nsl_auto_migrate' => 'boolean',
            'login_button_text' => 'string',
            'login_button_size' => 'string',
            'require_email_verification' => 'boolean',
            'allowed_email_domains' => 'string',
        ];

        foreach ($advanced_fields as $field => $type) {
            if ($type === 'boolean') {
                $value = isset($_POST[$field]) && $_POST[$field] === '1';
            } elseif ($type === 'string') {
                $value = isset($_POST[$field]) ? sanitize_text_field($_POST[$field]) : '';
            } else {
                $value = '';
            }

            SettingsService::set('general', $field, $value);
        }

        // 儲存登入按鈕位置（陣列）
        $positions = isset($_POST['login_button_positions']) && is_array($_POST['login_button_positions'])
            ? array_map('sanitize_text_field', $_POST['login_button_positions'])
            : [];
        SettingsService::set('general', 'login_button_positions', $positions);

        // 重新導向回設定頁面
        $redirect_url = add_query_arg(
            ['page' => 'line-hub-settings', 'tab' => 'settings', 'updated' => $success ? 'true' : 'false'],
            admin_url('admin.php')
        );
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * 處理測試連線
     */
    public function handle_test_connection(): void {
        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有權限執行此操作', 'line-hub'));
        }

        // 驗證 nonce
        if (!isset($_POST['line_hub_test_nonce']) || !wp_verify_nonce($_POST['line_hub_test_nonce'], 'line_hub_test_connection')) {
            wp_die(__('安全驗證失敗', 'line-hub'));
        }

        // 測試 Access Token
        $messaging_service = new MessagingService();
        $is_valid = $messaging_service->validateToken();

        // 重新導向回設定頁面
        $redirect_url = add_query_arg(
            [
                'page' => 'line-hub-settings',
                'tab' => 'settings',
                'test_result' => $is_valid ? 'success' : 'error'
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
        // 儲存成功/失敗訊息
        $updated = sanitize_key($_GET['updated'] ?? '');
        if ($updated !== '') {
            $class = $updated === 'true' ? 'notice-success' : 'notice-error';
            $message = $updated === 'true' ? '設定已儲存' : '儲存設定時發生錯誤';
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
        }

        // 測試連線結果
        $test_result = sanitize_key($_GET['test_result'] ?? '');
        if ($test_result !== '') {
            if ($test_result === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>Access Token 驗證成功</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Access Token 驗證失敗，請檢查設定是否正確</p></div>';
            }
        }
    }
}
