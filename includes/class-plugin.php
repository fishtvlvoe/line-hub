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

final class Plugin {

    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * 初始化外掛
     */
    public function init(): void {
        $this->register_hooks();
        $this->init_services();
    }

    /**
     * 註冊 WordPress hooks
     */
    private function register_hooks(): void {
        add_action('init', [$this, 'on_init'], 15);

        // Session Transfer Token 交換
        add_action('template_redirect', [Auth\SessionTransfer::class, 'handleExchange'], 1);

        // 路由（委派 PluginRoutes）
        $routes = new PluginRoutes();
        $routes->register();

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes'], 10);

        // Webhook Cron
        add_action('line_hub_process_webhook', [$this, 'process_webhook_events'], 10, 1);

        // Admin
        if (is_admin()) {
            add_action('admin_menu', [$this, 'register_admin_menu'], 30);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('show_user_profile', [$this, 'render_profile_binding_section'], 30);
            add_action('edit_user_profile', [$this, 'render_profile_binding_section'], 30);
            Admin\UsersColumn::init();
        }

        // 前端
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // LINE 頭像覆蓋 Gravatar
        add_filter('pre_get_avatar_data', [$this, 'override_avatar_with_line'], 10, 2);

        // WP-CLI
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('line-hub migrate-avatars', [CLI\MigrateAvatarsCommand::class, 'run']);
        }
    }

    /**
     * 初始化服務
     */
    private function init_services(): void {
        Services\IntegrationHooks::init();
        API\PublicAPI::init();
        Integration\LoginButton::init();
        Integration\ButtonPositions::init();
        Integration\FluentCartConnector::init();

        if (is_admin()) {
            Admin\SettingsPage::init();
            new Updater(LINE_HUB_FILE);
        }
    }

    public function on_init(): void {
        do_action('line_hub/init');
    }

    /**
     * 註冊 REST API 路由
     */
    public function register_rest_routes(): void {
        (new API\SettingsAPI())->register_routes();
        (new API\UserAPI())->register_routes();
        (new Webhook\WebhookReceiver())->registerRoutes();
    }

    public function register_admin_menu(): void {
        // 選單由各 Admin 類別自己註冊
    }

    /**
     * 載入後台資源（預留 hook，目前後台樣式由各 Tab PHP 模板內嵌）
     */
    public function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'line-hub') === false) {
            return;
        }
    }

    /**
     * 載入前端資源
     */
    public function enqueue_frontend_assets(): void {
        if (!isset($_COOKIE['line_hub_welcome']) || !is_user_logged_in()) {
            return;
        }
        $user = wp_get_current_user();
        wp_enqueue_script('line-hub-welcome-toast', LINE_HUB_URL . 'assets/js/welcome-toast.js', [], LINE_HUB_VERSION, true);
        wp_localize_script('line-hub-welcome-toast', 'lineHubWelcomeToast', [
            'displayName' => $user->display_name,
            'message'     => __('Logged in as {name}', 'line-hub'),
        ]);
    }

    /**
     * 用 LINE 頭像覆蓋 WordPress 預設 Gravatar
     */
    private static bool $avatar_override_active = false;

    public function override_avatar_with_line(array $args, $id_or_email): array {
        if (self::$avatar_override_active) {
            return $args;
        }

        $user_id = self::resolveUserId($id_or_email);
        if ($user_id <= 0) {
            return $args;
        }

        self::$avatar_override_active = true;
        $line_avatar = self::findLineAvatar($user_id);
        self::$avatar_override_active = false;

        if (!empty($line_avatar) && filter_var($line_avatar, FILTER_VALIDATE_URL)) {
            $args['url'] = $line_avatar;
            $args['found_avatar'] = true;
        }
        return $args;
    }

    /**
     * 在 WordPress 個人資料頁面渲染 LINE 綁定區塊
     */
    public function render_profile_binding_section(\WP_User $user): void {
        wp_enqueue_style('line-hub-profile-binding', LINE_HUB_URL . 'assets/css/profile-binding.css', [], LINE_HUB_VERSION);
        wp_enqueue_script('line-hub-profile-binding', LINE_HUB_URL . 'assets/js/profile-binding.js', [], LINE_HUB_VERSION, true);
        wp_localize_script('line-hub-profile-binding', 'lineHubProfileBinding', [
            'confirmUnbind' => __('Are you sure you want to unlink your LINE account? You will no longer receive LINE notifications.', 'line-hub'),
            'processing'    => __('Processing...', 'line-hub'),
            'unbindSuccess' => __('LINE account has been unlinked.', 'line-hub'),
            'unbindFail'    => __('Failed to unlink account.', 'line-hub'),
            'unbindLabel'   => __('Unlink', 'line-hub'),
            'networkError'  => __('Network error. Please try again later.', 'line-hub'),
        ]);

        $binding = Services\UserService::getBinding($user->ID);
        $liff_id = Services\SettingsService::get('general', 'liff_id', '');
        $login_channel_id = Services\SettingsService::get('general', 'login_channel_id', '');
        $channel_id = Services\SettingsService::get('general', 'channel_id', '');
        $has_login_configured = !empty($liff_id) || !empty($login_channel_id) || !empty($channel_id);

        $bind_url = '';
        if (!empty($liff_id)) {
            $redirect = admin_url('profile.php');
            $bind_url = home_url('/line-hub/liff/?redirect=' . urlencode($redirect));
        }

        $nonce = wp_create_nonce('wp_rest');
        $rest_url = rest_url('line-hub/v1/user/binding');
        include LINE_HUB_PATH . 'includes/templates/profile-binding.php';
    }

    public function process_webhook_events(array $events): void {
        (new Webhook\EventDispatcher())->processEvents($events);
    }

    // ── Private helpers ──────────────────────────────────

    private static function resolveUserId($id_or_email): int {
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        }
        if (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? $user->ID : 0;
        }
        if ($id_or_email instanceof \WP_User) {
            return $id_or_email->ID;
        }
        if ($id_or_email instanceof \WP_Post) {
            return (int) $id_or_email->post_author;
        }
        if ($id_or_email instanceof \WP_Comment && !empty($id_or_email->user_id)) {
            return (int) $id_or_email->user_id;
        }
        return 0;
    }

    private static function findLineAvatar(int $user_id): string {
        $avatar = get_user_meta($user_id, 'line_hub_avatar_url', true);
        if (!empty($avatar)) {
            return $avatar;
        }

        $avatar = Services\UserService::getPictureUrl($user_id);
        if (!empty($avatar)) {
            return $avatar;
        }

        // Fallback: NSL wp_user_avatar attachment
        global $blog_id, $wpdb;
        $attachment_id = get_user_meta($user_id, $wpdb->get_blog_prefix($blog_id) . 'user_avatar', true);
        if ($attachment_id && wp_attachment_is_image($attachment_id)) {
            $image_src = wp_get_attachment_image_src($attachment_id, 'thumbnail');
            if ($image_src) {
                return $image_src[0];
            }
        }
        return '';
    }
}
