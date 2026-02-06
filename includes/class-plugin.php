<?php
/**
 * 外掛主類別（單例模式）
 *
 * @package LineHub
 */

namespace LineHub;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin 類別
 *
 * 負責外掛的初始化和生命週期管理
 */
final class Plugin {
    /**
     * 單例實例
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * 取得單例實例
     *
     * @return Plugin
     */
    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 私有建構函式（單例模式）
     */
    private function __construct() {
        // 禁止外部實例化
    }

    /**
     * 初始化外掛
     */
    public function init(): void {
        // 註冊 WordPress hooks
        $this->register_hooks();

        // 載入相依性
        $this->load_dependencies();

        // 初始化服務
        $this->init_services();
    }

    /**
     * 註冊 WordPress hooks
     */
    private function register_hooks(): void {
        // WordPress 初始化
        add_action('init', [$this, 'on_init'], 15);

        // REST API 初始化
        add_action('rest_api_init', [$this, 'register_rest_routes'], 10);

        // Admin 初始化
        if (is_admin()) {
            add_action('admin_menu', [$this, 'register_admin_menu'], 30);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }

        // 前端初始化
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * 載入相依性
     */
    private function load_dependencies(): void {
        // 核心類別會透過 autoloader 自動載入
        // 這裡可以手動載入特殊檔案
    }

    /**
     * 初始化服務
     */
    private function init_services(): void {
        // 初始化各個服務（將在後續實作）
        // Services\SettingsService::init();
        // Services\AuthService::init();
        // Services\UserService::init();
        // Services\MessagingService::init();
        // Services\NotificationService::init();
        // Services\WebhookService::init();
    }

    /**
     * WordPress init hook
     */
    public function on_init(): void {
        // 註冊 shortcodes
        // add_shortcode('line_hub_login', [Shortcodes\Login::class, 'render']);
        // add_shortcode('line_hub_binding', [Shortcodes\Binding::class, 'render']);

        // 觸發自訂 Hook
        do_action('line_hub/init');
    }

    /**
     * 註冊 REST API 路由
     */
    public function register_rest_routes(): void {
        // 註冊 Settings API
        $settings_api = new API\Settings_API();
        $settings_api->register_routes();

        // 註冊 User API
        $user_api = new API\User_API();
        $user_api->register_routes();

        // 其他 REST API 端點將在後續實作
        // API\Webhook_API::register_routes();
        // API\Login_API::register_routes();
        // API\Binding_API::register_routes();
        // API\Notifications_API::register_routes();
    }

    /**
     * 註冊後台選單
     */
    public function register_admin_menu(): void {
        // 後台選單將在後續實作
        // Admin\Settings_Page::register_menu();
    }

    /**
     * 載入後台資源
     */
    public function enqueue_admin_assets(string $hook): void {
        // 只在 LINE Hub 頁面載入
        if (strpos($hook, 'line-hub') === false) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'line-hub-admin',
            LINE_HUB_URL . 'assets/css/admin.css',
            [],
            LINE_HUB_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'line-hub-admin',
            LINE_HUB_URL . 'assets/js/admin.js',
            ['jquery'],
            LINE_HUB_VERSION,
            true
        );

        // Localize script
        wp_localize_script('line-hub-admin', 'lineHubAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('line_hub_admin'),
            'rest_url' => rest_url('line-hub/v1'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    /**
     * 載入前端資源
     */
    public function enqueue_frontend_assets(): void {
        // CSS
        wp_enqueue_style(
            'line-hub-frontend',
            LINE_HUB_URL . 'assets/css/frontend.css',
            [],
            LINE_HUB_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'line-hub-frontend',
            LINE_HUB_URL . 'assets/js/frontend.js',
            ['jquery'],
            LINE_HUB_VERSION,
            true
        );

        // Localize script
        wp_localize_script('line-hub-frontend', 'lineHub', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('line-hub/v1'),
            'rest_nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
