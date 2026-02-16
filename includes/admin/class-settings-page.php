<?php
/**
 * LINE Hub Settings Page
 *
 * WordPress 後台設定頁面
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub\Admin;

use LineHub\Services\SettingsService;
use LineHub\Messaging\MessagingService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class SettingsPage
 *
 * 負責：
 * - 註冊後台選單
 * - 渲染設定頁面
 * - 處理表單提交
 * - 測試連線功能
 */
class SettingsPage {
    /**
     * 初始化
     */
    public static function init(): void {
        $instance = new self();
        add_action('admin_menu', [$instance, 'register_menu']);
        add_action('admin_post_line_hub_save_settings', [$instance, 'handle_save']);
        add_action('admin_post_line_hub_test_connection', [$instance, 'handle_test_connection']);
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
     * 渲染設定頁面
     */
    public function render_page(): void {
        // 檢查權限
        if (!current_user_can('manage_options')) {
            wp_die(__('您沒有權限訪問此頁面', 'line-hub'));
        }

        // 取得目前設定
        $settings = SettingsService::get_group('general');

        // 顯示訊息
        $this->show_admin_notices();

        ?>
        <div class="wrap">
            <h1>LINE Hub 設定</h1>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
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

                    <p class="submit">
                        <button type="submit" class="button button-primary">儲存設定</button>
                    </p>
                </form>
            </div>

            <!-- 測試連線區塊 -->
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>連線測試</h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">Webhook URL</th>
                        <td>
                            <code><?php echo esc_html(rest_url('line-hub/v1/webhook')); ?></code>
                            <p class="description">請在 LINE Developers Console 設定此 Webhook URL</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Access Token 驗證</th>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                <?php wp_nonce_field('line_hub_test_connection', 'line_hub_test_nonce'); ?>
                                <input type="hidden" name="action" value="line_hub_test_connection">
                                <button type="submit" class="button">測試 Access Token</button>
                            </form>
                            <p class="description">驗證 Access Token 是否有效</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 說明文件 -->
            <div class="card" style="max-width: 800px; margin-top: 20px;">
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
        </div>

        <style>
            .line-hub-status {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 3px;
                font-weight: 500;
                margin-left: 10px;
            }
            .line-hub-status.success {
                background: #d4edda;
                color: #155724;
            }
            .line-hub-status.error {
                background: #f8d7da;
                color: #721c24;
            }
        </style>
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

        // 儲存設定
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

        // 重新導向回設定頁面
        $redirect_url = add_query_arg(
            ['page' => 'line-hub-settings', 'updated' => $success ? 'true' : 'false'],
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
