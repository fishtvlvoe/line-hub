<?php
/**
 * LINE Hub Settings Page
 *
 * 後台設定頁面主控器 — 負責選單註冊、Tab 導航、表單路由。
 * 各 Tab 的渲染和儲存邏輯委託給 tabs/ 子目錄的獨立類別。
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub\Admin;

use LineHub\Admin\Tabs\AbstractTab;
use LineHub\LineApiEndpoints;
use LineHub\Services\SettingsService;
use LineHub\Messaging\MessagingService;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage {

    /** @var AbstractTab[] Tab 物件（slug => AbstractTab） */
    private array $tabs = [];

    public static function init(): void {
        $instance = new self();
        $instance->register_tabs();
        add_action('admin_menu', [$instance, 'register_menu']);
        add_action('admin_post_line_hub_save_settings', [$instance, 'handle_save']);
        add_action('admin_post_line_hub_test_connection', [$instance, 'handle_test_connection']);
        add_action('admin_post_line_hub_test_login', [$instance, 'handle_test_login']);
        add_action('admin_post_line_hub_generate_api_key', [$instance, 'handle_generate_api_key']);
        add_action('admin_post_line_hub_revoke_api_key', [$instance, 'handle_revoke_api_key']);
        add_action('admin_enqueue_scripts', [$instance, 'enqueue_assets']);
    }

    /** 舊 slug → 新 slug 映射（向後相容） */
    private const SLUG_REDIRECTS = [
        'settings' => 'line-settings',
        'login'    => 'login-settings',
    ];

    private function register_tabs(): void {
        foreach ([
            new Tabs\WizardTab(),
            new Tabs\LineSettingsTab(),
            new Tabs\LoginSettingsTab(),
            new Tabs\WebhookTab(),
            new Tabs\DeveloperTab(),
        ] as $tab) {
            $this->tabs[$tab->get_slug()] = $tab;
        }
    }

    public function enqueue_assets($hook): void {
        if ($hook !== 'toplevel_page_line-hub-settings') {
            return;
        }
        $url = plugin_dir_url(dirname(dirname(__FILE__)));
        $ver = defined('LINE_HUB_VERSION') ? LINE_HUB_VERSION : '1.0.0';
        wp_enqueue_style('line-hub-admin-tabs', $url . 'assets/css/admin-tabs.css', [], $ver);
        wp_enqueue_style('line-hub-developer-tab', $url . 'assets/css/developer-tab.css', [], $ver);
        wp_enqueue_style('line-hub-admin-views', $url . 'assets/css/admin-views.css', [], $ver);
        wp_enqueue_script('line-hub-admin-tabs', $url . 'assets/js/admin-tabs.js', [], $ver, true);
    }

    public function register_menu(): void {
        add_menu_page(__('LINE Hub Settings', 'line-hub'), 'LINE Hub', 'manage_options', 'line-hub-settings', [$this, 'render_page'], 'dashicons-format-chat', 30);
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'line-hub'));
        }
        // 舊 slug redirect（向後相容書籤和快取的 URL）
        $requested_tab = sanitize_key($_GET['tab'] ?? '');
        if (isset(self::SLUG_REDIRECTS[$requested_tab])) {
            wp_redirect(add_query_arg(['page' => 'line-hub-settings', 'tab' => self::SLUG_REDIRECTS[$requested_tab]], admin_url('admin.php')));
            exit;
        }

        $this->show_admin_notices();
        $current_tab = $requested_tab;
        if (!isset($this->tabs[$current_tab])) {
            $current_tab = array_key_first($this->tabs);
        }
        ?>
        <div class="wrap">
            <h1>LINE Hub</h1>
            <nav class="line-hub-tabs">
                <ul class="line-hub-tabs-wrapper">
                    <?php foreach ($this->tabs as $tab): ?>
                        <li class="line-hub-tab <?php echo $current_tab === $tab->get_slug() ? 'active' : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => $tab->get_slug()], admin_url('admin.php'))); ?>">
                                <?php echo esc_html($tab->get_label()); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <div class="line-hub-tab-content">
                <?php $this->tabs[$current_tab]->render(); ?>
            </div>
        </div>
        <?php
    }

    /** 處理設定儲存（委託給對應 Tab） */
    public function handle_save(): void {
        $this->verify_admin('line_hub_nonce', 'line_hub_save_settings');
        $tab_slug = sanitize_key($_POST['tab'] ?? '');
        $success = isset($this->tabs[$tab_slug]) ? $this->tabs[$tab_slug]->save($_POST) : true;
        wp_redirect(add_query_arg(
            ['page' => 'line-hub-settings', 'tab' => $tab_slug, 'updated' => $success ? 'true' : 'false'],
            admin_url('admin.php')
        ));
        exit;
    }

    /** 處理產生 API Key */
    public function handle_generate_api_key(): void {
        $this->verify_admin('line_hub_api_nonce', 'line_hub_api_key_action');
        $raw_key = 'lhk_' . bin2hex(random_bytes(16));
        SettingsService::set('integration', 'api_key_hash', wp_hash($raw_key));
        SettingsService::set('integration', 'api_key_prefix', substr($raw_key, 0, 8));
        SettingsService::set('integration', 'api_key_created_at', current_time('mysql'));
        set_transient('line_hub_new_api_key', $raw_key, 60);
        wp_redirect(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'developer', 'api_key_generated' => '1'], admin_url('admin.php')));
        exit;
    }

    /** 處理撤銷 API Key */
    public function handle_revoke_api_key(): void {
        $this->verify_admin('line_hub_api_nonce', 'line_hub_api_key_action');
        SettingsService::set('integration', 'api_key_hash', '');
        SettingsService::set('integration', 'api_key_prefix', '');
        SettingsService::set('integration', 'api_key_created_at', '');
        wp_redirect(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'developer', 'api_key_revoked' => '1'], admin_url('admin.php')));
        exit;
    }

    /** 處理 Messaging API 測試連線 */
    public function handle_test_connection(): void {
        $this->verify_admin('line_hub_test_nonce', 'line_hub_test_connection');
        $is_valid = (new MessagingService())->validateToken();
        wp_redirect(add_query_arg([
            'page' => 'line-hub-settings', 'tab' => 'line-settings',
            'test_result' => $is_valid ? 'success' : 'error',
        ], admin_url('admin.php')));
        exit;
    }

    /** 處理 LINE Login 測試連線 */
    public function handle_test_login(): void {
        $this->verify_admin('line_hub_test_login_nonce', 'line_hub_test_login');

        $settings       = SettingsService::get_group('general');
        $channel_id     = $settings['login_channel_id'] ?? '';
        $channel_secret = $settings['login_channel_secret'] ?? '';

        if (empty($channel_id) || empty($channel_secret)) {
            wp_redirect(add_query_arg([
                'page' => 'line-hub-settings', 'tab' => 'line-settings',
                'login_test_result' => 'empty',
            ], admin_url('admin.php')));
            exit;
        }

        $response = wp_remote_post(LineApiEndpoints::OAUTH_ACCESS_TOKEN, [
            'body' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $channel_id,
                'client_secret' => $channel_secret,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $result = 'network_error';
        } elseif (wp_remote_retrieve_response_code($response) === 200) {
            $result = 'success';
        } else {
            $result = 'error';
        }

        wp_redirect(add_query_arg([
            'page' => 'line-hub-settings', 'tab' => 'line-settings',
            'login_test_result' => $result,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * 共用權限和 nonce 驗證
     *
     * @param string $nonce_field POST 欄位名稱
     * @param string $nonce_action Nonce action
     */
    private function verify_admin(string $nonce_field, string $nonce_action): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'line-hub'));
        }
        if (!isset($_POST[$nonce_field]) || !wp_verify_nonce($_POST[$nonce_field], $nonce_action)) {
            wp_die(__('Security verification failed.', 'line-hub'));
        }
    }

    /** 顯示後台通知訊息 */
    private function show_admin_notices(): void {
        $updated = sanitize_key($_GET['updated'] ?? '');
        if ($updated !== '') {
            $class = $updated === 'true' ? 'notice-success' : 'notice-error';
            $msg = $updated === 'true'
                ? __('Settings saved.', 'line-hub')
                : __('An error occurred while saving settings.', 'line-hub');
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($msg));
        }
        $test = sanitize_key($_GET['test_result'] ?? '');
        if ($test !== '') {
            $class = $test === 'success' ? 'notice-success' : 'notice-error';
            $msg = $test === 'success'
                ? __('Messaging API verification successful.', 'line-hub')
                : __('Messaging API verification failed. Please check if the Access Token is correct.', 'line-hub');
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($msg));
        }
        $login_test = sanitize_key($_GET['login_test_result'] ?? '');
        if ($login_test !== '') {
            $messages = [
                'success'       => __('LINE Login verification successful — Channel ID and Secret are correct.', 'line-hub'),
                'error'         => __('LINE Login verification failed — please check your Channel ID and Secret.', 'line-hub'),
                'empty'         => __('Please enter your LINE Login Channel ID and Channel Secret first.', 'line-hub'),
                'network_error' => __('Unable to connect to LINE API. Please try again later.', 'line-hub'),
            ];
            $class = $login_test === 'success' ? 'notice-success' : 'notice-error';
            $msg = $messages[$login_test] ?? __('Unknown error.', 'line-hub');
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($msg));
        }
        if (isset($_GET['api_key_generated'])) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__('API Key has been generated. Please copy and save it now.', 'line-hub'));
        }
        if (isset($_GET['api_key_revoked'])) {
            printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html__('API Key has been revoked.', 'line-hub'));
        }
    }
}
