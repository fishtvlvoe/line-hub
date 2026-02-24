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

        // Session Transfer Token 交換（優先級最高，在其他 template_redirect 之前）
        add_action('template_redirect', [Auth\SessionTransfer::class, 'handleExchange'], 1);

        // OAuth 認證路由
        add_action('init', [$this, 'register_auth_routes'], 15);
        add_action('template_redirect', [$this, 'handle_auth_requests'], 10);

        // LIFF 路由（parse_request 攔截 + rewrite rule 備用）
        add_action('init', [$this, 'register_liff_routes'], 15);
        add_action('parse_request', [$this, 'intercept_liff_requests'], 1);
        add_action('template_redirect', [$this, 'handle_liff_requests'], 10);

        // Rewrite rules 自動刷新
        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 99);

        // REST API 初始化
        add_action('rest_api_init', [$this, 'register_rest_routes'], 10);

        // Webhook Cron 處理（Phase 5）
        add_action('line_hub_process_webhook', [$this, 'process_webhook_events'], 10, 1);

        // Admin 初始化
        if (is_admin()) {
            add_action('admin_menu', [$this, 'register_admin_menu'], 30);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // 個人資料頁 LINE 綁定區塊
            add_action('show_user_profile', [$this, 'render_profile_binding_section'], 30);
            add_action('edit_user_profile', [$this, 'render_profile_binding_section'], 30);

            // 用戶列表 LINE 綁定狀態欄
            Admin\UsersColumn::init();
        }

        // 前端初始化
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // LINE 頭像覆蓋 WordPress 預設 Gravatar
        add_filter('pre_get_avatar_data', [$this, 'override_avatar_with_line'], 10, 2);

        // WP-CLI 指令
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('line-hub migrate-avatars', [CLI\MigrateAvatarsCommand::class, 'run']);
        }
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
        // 外掛整合 hooks（do_action / apply_filters 介面）
        Services\IntegrationHooks::init();

        // 公開 REST API（API Key 認證）
        API\PublicAPI::init();

        // 登入按鈕元件（shortcode + hook）
        Integration\LoginButton::init();

        // 登入按鈕位置掛載（wp_login / fluentcart_checkout / fluent_community）
        Integration\ButtonPositions::init();

        // FluentCart 整合（客戶入口綁定區塊）
        Integration\FluentCartConnector::init();

        // 後台設定頁面 + 自動更新
        if (is_admin()) {
            Admin\SettingsPage::init();

            // 初始化自動更新檢測
            $api_url = defined('LINE_HUB_UPDATE_API_URL')
                ? LINE_HUB_UPDATE_API_URL
                : 'https://buygo-plugin-updater.your-subdomain.workers.dev';

            new Auto_Updater(LINE_HUB_VERSION, $api_url);
        }
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
     * 註冊 OAuth 認證路由
     *
     * 路由：
     * - /line-hub/auth/              - 發起 OAuth 認證
     * - /line-hub/auth/callback      - LINE 回調端點
     * - /line-hub/auth/email-submit  - Email 表單提交
     */
    public function register_auth_routes(): void {
        // 添加 rewrite rules
        add_rewrite_rule(
            '^line-hub/auth/?$',
            'index.php?line_hub_auth=1',
            'top'
        );
        add_rewrite_rule(
            '^line-hub/auth/callback/?$',
            'index.php?line_hub_auth=callback',
            'top'
        );
        // Email 表單提交路由
        add_rewrite_rule(
            '^line-hub/auth/email-submit/?$',
            'index.php?line_hub_auth=email-submit',
            'top'
        );

        // 註冊 query vars
        add_filter('query_vars', function ($vars) {
            $vars[] = 'line_hub_auth';
            return $vars;
        });
    }

    /**
     * 處理 OAuth 認證請求
     *
     * 在 template_redirect 時檢查是否為認證請求
     */
    public function handle_auth_requests(): void {
        $auth_action = get_query_var('line_hub_auth');

        if (empty($auth_action)) {
            return;
        }

        // 處理 Email 表單提交
        if ($auth_action === 'email-submit') {
            $login_service = new Services\LoginService();
            $login_service->handleEmailSubmit();
            exit;
        }

        // 處理認證請求（發起認證、callback）
        $callback = new Auth\AuthCallback();
        $callback->handleRequest();
        exit;
    }

    /**
     * 註冊 LIFF 路由
     *
     * 路由：
     * - /line-hub/liff/  - LIFF 登入頁面（GET: 渲染 / POST: 驗證）
     */
    public function register_liff_routes(): void {
        add_rewrite_rule(
            '^line-hub/liff/?$',
            'index.php?line_hub_liff=1',
            'top'
        );

        add_filter('query_vars', function ($vars) {
            $vars[] = 'line_hub_liff';
            return $vars;
        });
    }

    /**
     * 在 parse_request 階段攔截 LIFF 請求
     *
     * 直接比對 URL 路徑，不依賴 rewrite rules（防止 rewrite rules 被清除時 LIFF 404）
     *
     * @param \WP $wp WordPress 主物件
     */
    public function intercept_liff_requests(\WP $wp): void {
        $request = trim($wp->request, '/');

        if ($request !== 'line-hub/liff') {
            return;
        }

        // 設定 query var，讓 handle_liff_requests 也能正常工作
        $wp->query_vars['line_hub_liff'] = '1';

        // 直接處理 LIFF 請求
        $handler = new Liff\LiffHandler();
        $handler->handleRequest();
        exit;
    }

    /**
     * 處理 LIFF 請求（rewrite rules 備用路徑）
     */
    public function handle_liff_requests(): void {
        $liff_action = get_query_var('line_hub_liff');

        if (empty($liff_action)) {
            return;
        }

        $handler = new Liff\LiffHandler();
        $handler->handleRequest();
        exit;
    }

    /**
     * 自動刷新 rewrite rules
     *
     * 當外掛版本更新或 rewrite rules 尚未註冊時自動刷新
     */
    public function maybe_flush_rewrite_rules(): void {
        $stored_version = get_option('line_hub_rewrite_version', '');

        if ($stored_version !== LINE_HUB_VERSION) {
            flush_rewrite_rules();
            update_option('line_hub_rewrite_version', LINE_HUB_VERSION, true);
        }
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

        // 註冊 Webhook API（Phase 5）
        $webhook_receiver = new Webhook\WebhookReceiver();
        $webhook_receiver->registerRoutes();

        // 其他 REST API 端點將在後續實作
        // API\Login_API::register_routes();
        // API\Binding_API::register_routes();
        // API\Notifications_API::register_routes();
    }

    /**
     * 註冊後台選單
     */
    public function register_admin_menu(): void {
        // 選單由各個 Admin 類別自己註冊
        // 這個 hook 主要用於確保執行時機正確
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
        // LIFF 登入歡迎 Toast（透過 wp_head 注入，相容所有模板）
        if (isset($_COOKIE['line_hub_welcome']) && is_user_logged_in()) {
            $user = wp_get_current_user();
            $display_name = esc_js($user->display_name);
            add_action('wp_head', function () use ($display_name) {
                ?>
                <script>
                document.addEventListener('DOMContentLoaded', function(){
                    var d = document.createElement('div');
                    d.id = 'lineHubToast';
                    d.innerHTML = '已以 <strong><?php echo esc_html($display_name); ?></strong> 登入';
                    d.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:999999;background:#06C755;color:#fff;text-align:center;padding:12px 16px;font-size:15px;font-weight:500;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;transform:translateY(-100%);transition:transform .3s ease;';
                    document.body.insertBefore(d, document.body.firstChild);
                    setTimeout(function(){ d.style.transform = 'translateY(0)'; }, 300);
                    setTimeout(function(){ d.style.transform = 'translateY(-100%)'; }, 4000);
                    setTimeout(function(){ d.remove(); }, 4500);
                    document.cookie = 'line_hub_welcome=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                });
                </script>
                <?php
            }, 999);
        }
    }

    /**
     * 用 LINE 頭像覆蓋 WordPress 預設 Gravatar
     *
     * @param array $args     Avatar 參數
     * @param mixed $id_or_email 用戶 ID、Email 或 WP_Comment
     * @return array 修改後的 avatar 參數
     */
    private static bool $avatar_override_active = false;

    public function override_avatar_with_line(array $args, $id_or_email): array {
        // 防止無限遞迴：getPictureUrl() fallback 會調用 get_avatar_url() → 再觸發此 filter
        if (self::$avatar_override_active) {
            return $args;
        }

        $user_id = 0;

        if (is_numeric($id_or_email)) {
            $user_id = (int) $id_or_email;
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                $user_id = $user->ID;
            }
        } elseif ($id_or_email instanceof \WP_User) {
            $user_id = $id_or_email->ID;
        } elseif ($id_or_email instanceof \WP_Post) {
            $user_id = (int) $id_or_email->post_author;
        } elseif ($id_or_email instanceof \WP_Comment) {
            if (!empty($id_or_email->user_id)) {
                $user_id = (int) $id_or_email->user_id;
            }
        }

        if ($user_id <= 0) {
            return $args;
        }

        self::$avatar_override_active = true;

        // 先查 user_meta（最快）
        $line_avatar = get_user_meta($user_id, 'line_hub_avatar_url', true);

        // 如果 meta 沒有，從 line_hub_users 表查
        if (empty($line_avatar)) {
            $line_avatar = Services\UserService::getPictureUrl($user_id);
        }

        // Fallback: 查 NSL 留下的 wp_user_avatar（本機存儲的 attachment）
        if (empty($line_avatar)) {
            global $blog_id, $wpdb;
            $attachment_id = get_user_meta($user_id, $wpdb->get_blog_prefix($blog_id) . 'user_avatar', true);
            if ($attachment_id && wp_attachment_is_image($attachment_id)) {
                $image_src = wp_get_attachment_image_src($attachment_id, 'thumbnail');
                if ($image_src) {
                    $line_avatar = $image_src[0];
                }
            }
        }

        self::$avatar_override_active = false;

        if (!empty($line_avatar) && filter_var($line_avatar, FILTER_VALIDATE_URL)) {
            $args['url'] = $line_avatar;
            $args['found_avatar'] = true;
        }

        return $args;
    }

    /**
     * 在 WordPress 個人資料頁面渲染 LINE 綁定區塊
     *
     * @param \WP_User $user 用戶物件
     */
    public function render_profile_binding_section(\WP_User $user): void {
        // 載入 CSS
        wp_enqueue_style(
            'line-hub-profile-binding',
            LINE_HUB_URL . 'assets/css/profile-binding.css',
            [],
            LINE_HUB_VERSION
        );

        // 載入 JS + i18n 字串
        wp_enqueue_script(
            'line-hub-profile-binding',
            LINE_HUB_URL . 'assets/js/profile-binding.js',
            [],
            LINE_HUB_VERSION,
            true
        );
        wp_localize_script('line-hub-profile-binding', 'lineHubProfileBinding', [
            'confirmUnbind' => __('確定要解除 LINE 綁定嗎？解除後將無法接收 LINE 通知。', 'line-hub'),
            'processing'    => __('處理中...', 'line-hub'),
            'unbindSuccess' => __('LINE 綁定已解除', 'line-hub'),
            'unbindFail'    => __('解除綁定失敗', 'line-hub'),
            'unbindLabel'   => __('解除綁定', 'line-hub'),
            'networkError'  => __('網路錯誤，請稍後再試', 'line-hub'),
        ]);

        // 準備模板變數
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

        // 載入模板
        include LINE_HUB_PATH . 'includes/templates/profile-binding.php';
    }

    /**
     * 處理 Webhook 事件（Cron Handler）
     *
     * 由 wp_schedule_single_event 觸發
     *
     * @param array $events 事件陣列
     */
    public function process_webhook_events(array $events): void {
        $dispatcher = new Webhook\EventDispatcher();
        $dispatcher->processEvents($events);
    }
}
