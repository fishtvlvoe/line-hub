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
        'notifications' => '通知',
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

        wp_enqueue_style(
            'line-hub-admin-tabs',
            $plugin_url . 'assets/css/admin-tabs.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'line-hub-admin-tabs',
            $plugin_url . 'assets/js/admin-tabs.js',
            [],
            '1.0.0',
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

                    case 'notifications':
                        $this->render_notifications_tab();
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
        ?>
        <div class="line-hub-card">
            <h2>🎉 歡迎使用 LINE Hub</h2>
            <p style="font-size: 16px; color: #666;">WordPress 的 LINE 整合中樞 — 統一管理 LINE Login、LIFF、Webhook 和通知推送</p>
        </div>

        <div class="line-hub-card">
            <h2>📋 快速開始（3 步驟）</h2>

            <div style="margin: 20px 0;">
                <h3 style="color: #06C755;">[ 1 ] 建立 LINE Login Channel</h3>
                <ul style="line-height: 1.8; color: #666;">
                    <li>前往 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a></li>
                    <li>建立 Provider 和 Login Channel</li>
                    <li>取得 <strong>Channel ID</strong> 和 <strong>Channel Secret</strong></li>
                </ul>
                <a href="https://developers.line.biz/en/docs/line-login/getting-started/" target="_blank" class="button">查看詳細教學</a>
            </div>

            <div style="margin: 20px 0; padding: 15px; background: #f5f5f5; border-left: 4px solid #06C755;">
                <h3 style="color: #06C755; margin-top: 0;">[ 2 ] 設定 Callback URL 和 LIFF</h3>
                <p><strong>Callback URL</strong>（填入 LINE Developers Console）：</p>
                <p>
                    <code style="background: #fff; padding: 8px 12px; display: inline-block; border: 1px solid #ddd;">
                        <?php echo esc_html($site_url . '/line-hub/auth/callback'); ?>
                    </code>
                    <button class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($site_url . '/line-hub/auth/callback'); ?>')">複製</button>
                </p>

                <p style="margin-top: 15px;"><strong>LIFF Endpoint URL</strong>（填入 LIFF App 設定）：</p>
                <p>
                    <code style="background: #fff; padding: 8px 12px; display: inline-block; border: 1px solid #ddd;">
                        <?php echo esc_html($site_url . '/line-hub/liff/'); ?>
                    </code>
                    <button class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($site_url . '/line-hub/liff/'); ?>')">複製</button>
                </p>
            </div>

            <div style="margin: 20px 0;">
                <h3 style="color: #06C755;">[ 3 ] 填入設定資訊</h3>
                <p style="color: #666;">前往「設定」Tab，填入 Channel ID、Secret、Access Token、LIFF ID，然後測試連線。</p>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'settings'], admin_url('admin.php'))); ?>" class="button button-primary">前往設定</a>
            </div>
        </div>

        <div class="line-hub-card">
            <h2>✅ 已完成功能</h2>
            <ul style="line-height: 2; color: #666;">
                <li>✓ LINE Login（OAuth 2.0 標準授權）</li>
                <li>✓ LIFF 登入（LINE 內瀏覽器整合）</li>
                <li>✓ 用戶綁定管理（LINE UID ⇄ WordPress User）</li>
                <li>✓ Email 收集和帳號合併</li>
                <li>✓ NSL（Nextend Social Login）相容模式</li>
                <li>✓ FluentCart 產品頁登入按鈕</li>
            </ul>
        </div>

        <div class="line-hub-card">
            <h2>🚧 即將推出</h2>
            <ul style="line-height: 2; color: #999;">
                <li>LINE 通知推送（Phase 4 — 訊息模板引擎）</li>
                <li>Webhook 接收和處理（Phase 5 — 關鍵字回應）</li>
                <li>BuyGo 整合（Phase 6 — 訂單通知）</li>
            </ul>
        </div>
        <?php
    }

    /**
     * 渲染「設定」Tab
     */
    private function render_settings_tab(): void {
        $settings = SettingsService::get_group('general');
        ?>
        <div class="card" style="max-width: 1000px;">
            <h2>LINE Messaging API 設定</h2>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('line_hub_save_settings', 'line_hub_nonce'); ?>
                <input type="hidden" name="action" value="line_hub_save_settings">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="channel_id">Channel ID</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="channel_id"
                                   name="channel_id"
                                   value="<?php echo esc_attr($settings['channel_id'] ?? ''); ?>"
                                   class="regular-text"
                                   placeholder="例：2008621590">
                            <p class="description">從 LINE Developers Console 的 Messaging API 頁面取得</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="channel_secret">Channel Secret</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="channel_secret"
                                   name="channel_secret"
                                   value="<?php echo esc_attr($settings['channel_secret'] ?? ''); ?>"
                                   class="regular-text"
                                   placeholder="32 位元字串">
                            <p class="description">用於 Webhook 簽名驗證（自動加密儲存）</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="access_token">Channel Access Token</label>
                        </th>
                        <td>
                            <textarea id="access_token"
                                      name="access_token"
                                      rows="3"
                                      class="large-text"
                                      placeholder="長期或短期 Access Token"><?php echo esc_textarea($settings['access_token'] ?? ''); ?></textarea>
                            <p class="description">用於發送訊息（自動加密儲存）</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="liff_id">LIFF ID</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="liff_id"
                                   name="liff_id"
                                   value="<?php echo esc_attr($settings['liff_id'] ?? ''); ?>"
                                   class="regular-text"
                                   placeholder="例：2008622068-iU4Z1lk4">
                            <p class="description">LIFF App ID（如有使用 LIFF 登入功能）</p>
                        </td>
                    </tr>
                </table>

                <h3>進階設定</h3>

                <!-- NSL 整合 -->
                <h4 style="margin-top: 20px;">NSL (Nextend Social Login) 整合</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">NSL 相容模式</th>
                        <td>
                            <label>
                                <input type="checkbox" name="nsl_compat_mode" value="1" <?php checked($settings['nsl_compat_mode'] ?? false); ?>>
                                啟用 NSL 相容模式（同時從 wp_social_users 查詢用戶）
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">自動遷移</th>
                        <td>
                            <label>
                                <input type="checkbox" name="nsl_auto_migrate" value="1" <?php checked($settings['nsl_auto_migrate'] ?? false); ?>>
                                自動遷移 NSL 用戶到 LineHub（新用戶登入時自動複製）
                            </label>
                        </td>
                    </tr>
                </table>

                <!-- 登入按鈕設定 -->
                <h4 style="margin-top: 20px;">登入按鈕設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="login_button_text">按鈕文字</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="login_button_text"
                                   name="login_button_text"
                                   value="<?php echo esc_attr($settings['login_button_text'] ?? '用 LINE 帳號登入'); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">按鈕位置</th>
                        <td>
                            <label>
                                <input type="checkbox" name="login_button_positions[]" value="fluentcart_product" <?php checked(in_array('fluentcart_product', $settings['login_button_positions'] ?? [])); ?>>
                                FluentCart 產品頁（未登入時顯示）
                            </label><br>
                            <label>
                                <input type="checkbox" name="login_button_positions[]" value="wp_login" <?php checked(in_array('wp_login', $settings['login_button_positions'] ?? [])); ?>>
                                WordPress 登入頁
                            </label><br>
                            <label>
                                <input type="checkbox" name="login_button_positions[]" value="fluent_community" <?php checked(in_array('fluent_community', $settings['login_button_positions'] ?? [])); ?>>
                                FluentCommunity 登入表單
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">按鈕大小</th>
                        <td>
                            <label><input type="radio" name="login_button_size" value="small" <?php checked($settings['login_button_size'] ?? 'medium', 'small'); ?>> 小</label>
                            <label style="margin-left: 15px;"><input type="radio" name="login_button_size" value="medium" <?php checked($settings['login_button_size'] ?? 'medium', 'medium'); ?>> 中</label>
                            <label style="margin-left: 15px;"><input type="radio" name="login_button_size" value="large" <?php checked($settings['login_button_size'] ?? 'medium', 'large'); ?>> 大</label>
                        </td>
                    </tr>
                </table>

                <!-- 安全性設定 -->
                <h4 style="margin-top: 20px;">安全性設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">Email 驗證</th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_email_verification" value="1" <?php checked($settings['require_email_verification'] ?? false); ?>>
                                強制 Email 驗證（新用戶必須驗證 Email 才能登入）
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="allowed_email_domains">限制網域</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="allowed_email_domains"
                                   name="allowed_email_domains"
                                   value="<?php echo esc_attr($settings['allowed_email_domains'] ?? ''); ?>"
                                   class="regular-text"
                                   placeholder="gmail.com, yahoo.com">
                            <p class="description">只允許特定 Email 網域註冊（逗號分隔，留空表示不限制）</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">儲存設定</button>
                </p>
            </form>
        </div>

        <!-- 測試連線區塊 -->
        <div class="card" style="max-width: 1000px; margin-top: 20px;">
            <h2>連線測試</h2>
            <?php $this->render_connection_status(); ?>
        </div>

        <!-- 說明文件 -->
        <div class="card" style="max-width: 1000px; margin-top: 20px;">
            <h2>設定說明</h2>
            <ol>
                <li>前往 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a></li>
                <li>選擇你的 Provider 和 Channel（建議使用 Messaging API Channel）</li>
                <li>從 <strong>Basic settings</strong> 頁面取得 <strong>Channel ID</strong> 和 <strong>Channel Secret</strong></li>
                <li>從 <strong>Messaging API</strong> 頁面取得 <strong>Channel Access Token</strong>（需先發行）</li>
                <li>在 <strong>Messaging API</strong> 頁面設定 Webhook URL 為上方顯示的網址</li>
                <li>啟用 <strong>Use webhook</strong> 開關</li>
                <li>點擊 <strong>Verify</strong> 按鈕測試 Webhook 連線</li>
            </ol>
        </div>
        <?php
    }

    /**
     * 渲染連線狀態區塊（Task 6）
     */
    private function render_connection_status(): void {
        $settings = SettingsService::get_group('general');

        $has_channel_id = !empty($settings['channel_id']);
        $has_channel_secret = !empty($settings['channel_secret']);
        $has_access_token = !empty($settings['access_token']);
        $has_liff_id = !empty($settings['liff_id']);

        ?>
        <div style="margin: 20px 0;">
            <h3>LINE Messaging API</h3>
            <ul style="list-style: none; padding-left: 0;">
                <li style="margin: 8px 0;">
                    <?php echo $has_channel_id ? '✓' : '✗'; ?> Channel ID <?php echo $has_channel_id ? '已設定' : '尚未設定'; ?>
                </li>
                <li style="margin: 8px 0;">
                    <?php echo $has_channel_secret ? '✓' : '✗'; ?> Channel Secret <?php echo $has_channel_secret ? '已設定' : '尚未設定'; ?>
                </li>
                <li style="margin: 8px 0;">
                    <?php echo $has_access_token ? '✓' : '✗'; ?> Access Token <?php echo $has_access_token ? '已設定' : '尚未設定'; ?>
                </li>
            </ul>

            <h3 style="margin-top: 20px;">LINE Login</h3>
            <ul style="list-style: none; padding-left: 0;">
                <li style="margin: 8px 0;">
                    <?php echo $has_channel_id ? '✓' : '✗'; ?> Channel ID <?php echo $has_channel_id ? '已設定' : '尚未設定'; ?>
                </li>
                <li style="margin: 8px 0;">
                    <?php echo $has_channel_secret ? '✓' : '✗'; ?> Channel Secret <?php echo $has_channel_secret ? '已設定' : '尚未設定'; ?>
                </li>
                <li style="margin: 8px 0;">
                    <?php echo $has_liff_id ? '✓' : '⚠'; ?> LIFF ID <?php echo $has_liff_id ? '已設定' : '尚未設定（選用）'; ?>
                </li>
            </ul>

            <h3 style="margin-top: 20px;">Webhook URL</h3>
            <p>
                <code style="background: #f5f5f5; padding: 8px 12px; display: inline-block;">
                    <?php echo esc_html(rest_url('line-hub/v1/webhook')); ?>
                </code>
            </p>
            <p class="description">請在 LINE Developers Console 設定此 Webhook URL</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 20px;">
                <?php wp_nonce_field('line_hub_test_connection', 'line_hub_test_nonce'); ?>
                <input type="hidden" name="action" value="line_hub_test_connection">
                <button type="submit" class="button button-secondary" <?php echo !$has_access_token ? 'disabled' : ''; ?>>
                    測試 Access Token
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * 渲染「通知」Tab（Phase 4，暫時留空）
     */
    private function render_notifications_tab(): void {
        ?>
        <div class="line-hub-card">
            <h2>🚧 通知模板管理</h2>
            <p style="color: #666;">此功能將在 <strong>Phase 4</strong> 開發完成。</p>
            <p>未來將支援：</p>
            <ul style="line-height: 2; color: #666;">
                <li>訂單建立通知模板</li>
                <li>出貨通知模板</li>
                <li>自訂變數（{order_id}, {product_name} 等）</li>
                <li>Flex Message 視覺化編輯器</li>
                <li>通知發送記錄</li>
            </ul>
        </div>
        <?php
    }

    /**
     * 渲染「Webhook」Tab
     */
    private function render_webhooks_tab(): void {
        $events = WebhookLogger::getRecent(20);
        $event_types = array_unique(array_column($events, 'event_type'));
        ?>
        <div class="line-hub-card">
            <h2>Webhook 事件記錄</h2>

            <?php if (empty($events)): ?>
                <p style="color: #999;">尚無 Webhook 事件記錄。當 LINE 用戶與您的 Bot 互動時，事件會顯示在這裡。</p>
            <?php else: ?>
                <p style="color: #666;">最近 <?php echo count($events); ?> 筆事件</p>

                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th style="width: 150px;">事件類型</th>
                            <th style="width: 200px;">LINE UID</th>
                            <th style="width: 180px;">時間</th>
                            <th style="width: 80px;">狀態</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td><?php echo esc_html($event['id']); ?></td>
                                <td><code><?php echo esc_html($event['event_type']); ?></code></td>
                                <td>
                                    <?php if (!empty($event['line_uid'])): ?>
                                        <code style="font-size: 11px;"><?php echo esc_html(substr($event['line_uid'], 0, 15) . '...'); ?></code>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $time_diff = human_time_diff(strtotime($event['received_at']), current_time('timestamp'));
                                    echo esc_html($time_diff . ' 前');
                                    ?>
                                </td>
                                <td>
                                    <?php if ($event['processed']): ?>
                                        <span style="color: #46b450;">✓</span>
                                    <?php else: ?>
                                        <span style="color: #999;">⋯</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="button button-small" onclick="togglePayload(<?php echo esc_js($event['id']); ?>)">查看 Payload</button>
                                    <div id="payload-<?php echo esc_attr($event['id']); ?>" style="display: none; margin-top: 10px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 3px;">
                                        <pre style="overflow-x: auto; font-size: 12px; max-height: 300px;"><?php echo esc_html(json_encode(json_decode($event['payload']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <script>
                function togglePayload(id) {
                    const el = document.getElementById('payload-' + id);
                    if (el) {
                        el.style.display = el.style.display === 'none' ? 'block' : 'none';
                    }
                }
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * 渲染「用法」Tab
     */
    private function render_usage_tab(): void {
        ?>
        <div class="line-hub-card">
            <h2>短代碼 (Shortcodes)</h2>

            <h3><code>[line_hub_login]</code></h3>
            <p style="color: #666;">顯示 LINE 登入按鈕</p>

            <h4 style="margin-top: 15px;">參數：</h4>
            <ul style="line-height: 1.8; color: #666;">
                <li><code>text</code> — 按鈕文字（預設：「用 LINE 帳號登入」）</li>
                <li><code>size</code> — 按鈕大小（small / medium / large，預設：medium）</li>
                <li><code>redirect</code> — 登入後重定向 URL（可選）</li>
            </ul>

            <h4 style="margin-top: 15px;">範例：</h4>
            <pre style="background: #f5f5f5; padding: 15px; border-left: 4px solid #06C755;">[line_hub_login text="立即登入" size="large" redirect="/my-account"]</pre>
        </div>

        <div class="line-hub-card">
            <h2>WordPress Hooks</h2>

            <h3 style="margin-top: 20px;">Actions</h3>

            <h4><code>line_hub/user_logged_in</code></h4>
            <p style="color: #666;">當用戶透過 LINE 登入時觸發</p>
            <p><strong>參數：</strong> <code>$user_id</code> (int), <code>$line_uid</code> (string)</p>
            <pre style="background: #f5f5f5; padding: 15px;">add_action('line_hub/user_logged_in', function($user_id, $line_uid) {
    error_log("User $user_id logged in via LINE: $line_uid");
}, 10, 2);</pre>

            <h4 style="margin-top: 20px;"><code>line_hub/webhook/message/text</code></h4>
            <p style="color: #666;">當收到文字訊息時觸發</p>
            <p><strong>參數：</strong> <code>$event</code> (array), <code>$line_uid</code> (string), <code>$user_id</code> (int|null), <code>$msg_id</code> (string)</p>

            <h3 style="margin-top: 30px;">Filters</h3>

            <h4><code>line_hub/login_redirect_url</code></h4>
            <p style="color: #666;">自訂登入後的重定向 URL</p>
            <p><strong>參數：</strong> <code>$url</code> (string), <code>$user</code> (WP_User)</p>
            <pre style="background: #f5f5f5; padding: 15px;">add_filter('line_hub/login_redirect_url', function($url, $user) {
    // VIP 用戶重定向到專屬頁面
    if (in_array('vip', $user->roles)) {
        return '/vip-dashboard';
    }
    return $url;
}, 10, 2);</pre>
        </div>

        <div class="line-hub-card">
            <h2>PHP 範例</h2>

            <h3>取得用戶的 LINE UID</h3>
            <pre style="background: #f5f5f5; padding: 15px;">$user_id = get_current_user_id();
$line_uid = \LineHub\Services\UserService::getLineUid($user_id);

if ($line_uid) {
    echo "當前用戶的 LINE UID: $line_uid";
}</pre>

            <h3 style="margin-top: 20px;">檢查用戶是否已綁定 LINE</h3>
            <pre style="background: #f5f5f5; padding: 15px;">$user_id = 123;
$is_linked = \LineHub\Services\UserService::isLinked($user_id);

if ($is_linked) {
    echo "用戶已綁定 LINE 帳號";
}</pre>

            <h3 style="margin-top: 20px;">透過 LINE UID 查詢 WordPress 用戶</h3>
            <pre style="background: #f5f5f5; padding: 15px;">$line_uid = 'U1234567890abcdef';
$user_id = \LineHub\Services\UserService::getUserByLineUid($line_uid);

if ($user_id) {
    $user = get_user_by('id', $user_id);
    echo "找到用戶: " . $user->display_name;
}</pre>
        </div>
        <?php
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
        SettingsService::set('general', 'login_button_positions', json_encode($positions));

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
        if (isset($_GET['updated'])) {
            $class = $_GET['updated'] === 'true' ? 'notice-success' : 'notice-error';
            $message = $_GET['updated'] === 'true' ? '設定已儲存' : '儲存設定時發生錯誤';
            printf('<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
        }

        // 測試連線結果
        if (isset($_GET['test_result'])) {
            if ($_GET['test_result'] === 'success') {
                echo '<div class="notice notice-success is-dismissible"><p>✅ Access Token 驗證成功！</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>❌ Access Token 驗證失敗，請檢查設定是否正確</p></div>';
            }
        }
    }
}
