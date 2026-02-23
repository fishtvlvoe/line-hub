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

        // 後台設定頁面
        if (is_admin()) {
            Admin\SettingsPage::init();
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
        $binding = Services\UserService::getBinding($user->ID);
        $liff_id = Services\SettingsService::get('general', 'liff_id', '');
        $login_channel_id = Services\SettingsService::get('general', 'login_channel_id', '');
        $channel_id = Services\SettingsService::get('general', 'channel_id', '');
        $has_login_configured = !empty($liff_id) || !empty($login_channel_id) || !empty($channel_id);

        // LIFF 綁定 URL
        $bind_url = '';
        if (!empty($liff_id)) {
            $redirect = admin_url('profile.php');
            $bind_url = home_url('/line-hub/liff/?redirect=' . urlencode($redirect));
        }

        $nonce = wp_create_nonce('wp_rest');
        $rest_url = rest_url('line-hub/v1/user/binding');
        ?>
        <style>
            .line-hub-profile-section {
                margin-top: 24px;
            }
            .line-hub-profile-section h2 {
                display: flex;
                align-items: center;
                gap: 8px;
                color: #06C755;
                font-size: 1.3em;
                padding-bottom: 8px;
                border-bottom: 2px solid #06C755;
            }
            .line-hub-profile-section h2 svg {
                flex-shrink: 0;
            }
            .line-hub-binding-card {
                background: #f9fafb;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 20px 24px;
                margin-top: 16px;
            }
            .line-hub-binding-info {
                display: flex;
                align-items: center;
                gap: 16px;
            }
            .line-hub-binding-avatar {
                width: 64px;
                height: 64px;
                border-radius: 50%;
                border: 3px solid #06C755;
                object-fit: cover;
                flex-shrink: 0;
            }
            .line-hub-binding-avatar-placeholder {
                width: 64px;
                height: 64px;
                border-radius: 50%;
                background: #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .line-hub-binding-avatar-placeholder svg {
                width: 32px;
                height: 32px;
                color: #9ca3af;
            }
            .line-hub-binding-details {
                flex: 1;
                min-width: 0;
            }
            .line-hub-binding-name {
                font-size: 16px;
                font-weight: 600;
                color: #1f2937;
                margin: 0 0 4px 0;
            }
            .line-hub-binding-uid {
                font-size: 13px;
                color: #6b7280;
                font-family: ui-monospace, SFMono-Regular, monospace;
                word-break: break-all;
            }
            .line-hub-binding-date {
                font-size: 13px;
                color: #9ca3af;
                margin-top: 4px;
            }
            .line-hub-binding-source {
                display: inline-block;
                font-size: 11px;
                padding: 2px 8px;
                border-radius: 10px;
                margin-left: 8px;
                font-weight: 500;
            }
            .line-hub-source-line-hub {
                background: #dcfce7;
                color: #166534;
            }
            .line-hub-source-nsl {
                background: #fef3c7;
                color: #92400e;
            }
            .line-hub-binding-actions {
                margin-top: 16px;
                display: flex;
                gap: 12px;
                align-items: center;
            }
            .line-hub-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                text-decoration: none;
                cursor: pointer;
                border: none;
                transition: all 0.2s;
            }
            .line-hub-btn-bind {
                background: #fff;
                color: #06C755;
                border: 1.5px solid #06C755;
            }
            .line-hub-btn-bind:hover {
                background: #06C755;
                color: #fff;
            }
            .line-hub-btn-unbind {
                background: #fff;
                color: #ef4444;
                border: 1px solid #fca5a5;
            }
            .line-hub-btn-unbind:hover {
                background: #fef2f2;
            }
            .line-hub-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            .line-hub-unbound-notice {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 16px;
                background: #fffbeb;
                border: 1px solid #fde68a;
                border-radius: 8px;
                color: #92400e;
                font-size: 14px;
            }
            .line-hub-status-msg {
                margin-top: 12px;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 13px;
                display: none;
            }
            .line-hub-status-success {
                background: #dcfce7;
                color: #166534;
                display: block;
            }
            .line-hub-status-error {
                background: #fef2f2;
                color: #991b1b;
                display: block;
            }
        </style>

        <div class="line-hub-profile-section">
            <h2>
                <svg viewBox="0 0 24 24" width="24" height="24" fill="#06C755"><path d="M24 10.304C24 4.974 18.629.607 12 .607S0 4.974 0 10.304c0 4.8 4.27 8.834 10.035 9.602.391.084.922.258 1.058.592.12.3.079.77.038 1.08l-.164 1.02c-.045.3-.24 1.17 1.049.638 1.291-.532 6.916-4.07 9.436-6.97C23.176 14.393 24 12.458 24 10.304"/></svg>
                <?php esc_html_e('LINE 帳號綁定', 'line-hub'); ?>
            </h2>

            <div class="line-hub-binding-card">
                <?php if ($binding && !empty($binding->line_uid)) : ?>
                    <?php
                    // 判斷資料來源
                    $is_nsl = empty($binding->display_name) && !Services\UserService::hasDirectBinding($user->ID);
                    $display_name = !empty($binding->display_name) ? $binding->display_name : __('未知', 'line-hub');
                    $picture_url = !empty($binding->picture_url) ? $binding->picture_url : '';
                    $linked_at = !empty($binding->created_at) ? $binding->created_at : '';

                    // 也嘗試從 user meta 取頭像
                    if (empty($picture_url)) {
                        $picture_url = get_user_meta($user->ID, 'line_hub_avatar_url', true);
                    }
                    ?>
                    <div class="line-hub-binding-info">
                        <?php if (!empty($picture_url)) : ?>
                            <img src="<?php echo esc_url($picture_url); ?>"
                                 alt="<?php echo esc_attr($display_name); ?>"
                                 class="line-hub-binding-avatar">
                        <?php else : ?>
                            <div class="line-hub-binding-avatar-placeholder">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                            </div>
                        <?php endif; ?>

                        <div class="line-hub-binding-details">
                            <p class="line-hub-binding-name">
                                <?php echo esc_html($display_name); ?>
                                <?php if ($is_nsl) : ?>
                                    <span class="line-hub-binding-source line-hub-source-nsl">NSL</span>
                                <?php else : ?>
                                    <span class="line-hub-binding-source line-hub-source-line-hub">LINE Hub</span>
                                <?php endif; ?>
                            </p>
                            <div class="line-hub-binding-uid">
                                LINE UID: <?php echo esc_html($binding->line_uid); ?>
                            </div>
                            <?php if (!empty($linked_at)) : ?>
                                <div class="line-hub-binding-date">
                                    <?php
                                    /* translators: %s: date */
                                    printf(esc_html__('綁定於：%s', 'line-hub'), esc_html($linked_at));
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="line-hub-binding-actions">
                        <button type="button" class="line-hub-btn line-hub-btn-unbind" id="lineHubUnbindBtn"
                                data-rest-url="<?php echo esc_url($rest_url); ?>"
                                data-nonce="<?php echo esc_attr($nonce); ?>">
                            <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                            <?php esc_html_e('解除綁定', 'line-hub'); ?>
                        </button>
                    </div>

                    <div class="line-hub-status-msg" id="lineHubStatusMsg"></div>

                <?php elseif ($has_login_configured) : ?>
                    <div class="line-hub-unbound-notice">
                        <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" style="color:#f59e0b;flex-shrink:0"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        <?php esc_html_e('尚未綁定 LINE 帳號。綁定後可接收訂單通知和出貨追蹤。', 'line-hub'); ?>
                    </div>

                    <?php if (!empty($bind_url)) : ?>
                        <div class="line-hub-binding-actions">
                            <a href="<?php echo esc_url($bind_url); ?>" class="line-hub-btn line-hub-btn-bind">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M24 10.304C24 4.974 18.629.607 12 .607S0 4.974 0 10.304c0 4.8 4.27 8.834 10.035 9.602.391.084.922.258 1.058.592.12.3.079.77.038 1.08l-.164 1.02c-.045.3-.24 1.17 1.049.638 1.291-.532 6.916-4.07 9.436-6.97C23.176 14.393 24 12.458 24 10.304"/></svg>
                                <?php esc_html_e('綁定 LINE 帳號', 'line-hub'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                <?php else : ?>
                    <p style="color:#6b7280;margin:0;"><?php esc_html_e('LINE Login 尚未設定，請聯繫管理員。', 'line-hub'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <script>
        (function(){
            var unbindBtn = document.getElementById('lineHubUnbindBtn');
            if (!unbindBtn) return;

            unbindBtn.addEventListener('click', function(){
                if (!confirm('<?php echo esc_js(__('確定要解除 LINE 綁定嗎？解除後將無法接收 LINE 通知。', 'line-hub')); ?>')) {
                    return;
                }

                var btn = this;
                var statusMsg = document.getElementById('lineHubStatusMsg');
                btn.disabled = true;
                btn.textContent = '<?php echo esc_js(__('處理中...', 'line-hub')); ?>';

                fetch(btn.dataset.restUrl, {
                    method: 'DELETE',
                    headers: {
                        'X-WP-Nonce': btn.dataset.nonce,
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        statusMsg.className = 'line-hub-status-msg line-hub-status-success';
                        statusMsg.textContent = data.message || '<?php echo esc_js(__('LINE 綁定已解除', 'line-hub')); ?>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        statusMsg.className = 'line-hub-status-msg line-hub-status-error';
                        statusMsg.textContent = data.message || '<?php echo esc_js(__('解除綁定失敗', 'line-hub')); ?>';
                        btn.disabled = false;
                        btn.textContent = '<?php echo esc_js(__('解除綁定', 'line-hub')); ?>';
                    }
                })
                .catch(function(err){
                    statusMsg.className = 'line-hub-status-msg line-hub-status-error';
                    statusMsg.textContent = '<?php echo esc_js(__('網路錯誤，請稍後再試', 'line-hub')); ?>';
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js(__('解除綁定', 'line-hub')); ?>';
                });
            });
        })();
        </script>
        <?php
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
