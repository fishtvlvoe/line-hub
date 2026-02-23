<?php
/**
 * LINE 設定 Tab 模板
 *
 * Messaging API、LINE Login Channel、NSL 整合設定。
 *
 * 可用變數：
 *   $settings (array) — SettingsService::get_group('general') 的結果
 *   $site_url (string) — home_url()
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- 區塊 A：LINE Messaging API 設定 -->
<div class="card" style="max-width: 1000px;">
    <h2>LINE Messaging API 設定</h2>
    <p class="description">用於發送訊息、Webhook 接收。對應 LINE Developers Console 的 <strong>Messaging API</strong> Channel。</p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('line_hub_save_settings', 'line_hub_nonce'); ?>
        <input type="hidden" name="action" value="line_hub_save_settings">
        <input type="hidden" name="tab" value="line-settings">
        <input type="hidden" name="section" value="messaging">

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="channel_id">Channel ID</label>
                </th>
                <td>
                    <input type="text" id="channel_id" name="channel_id"
                           value="<?php echo esc_attr($settings['channel_id'] ?? ''); ?>"
                           class="regular-text" placeholder="例：2008621590">
                    <p class="description">Messaging API Channel 的 Channel ID</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="channel_secret">Channel Secret</label>
                </th>
                <td>
                    <input type="text" id="channel_secret" name="channel_secret"
                           value="<?php echo esc_attr($settings['channel_secret'] ?? ''); ?>"
                           class="regular-text" placeholder="32 位元字串">
                    <p class="description">用於 Webhook 簽名驗證（自動加密儲存）</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="access_token">Channel Access Token</label>
                </th>
                <td>
                    <textarea id="access_token" name="access_token" rows="3"
                              class="large-text"
                              placeholder="長期或短期 Access Token"><?php echo esc_textarea($settings['access_token'] ?? ''); ?></textarea>
                    <p class="description">用於發送訊息（自動加密儲存）</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Webhook URL</th>
                <td>
                    <code style="background: #f5f5f5; padding: 8px 12px; display: inline-block;">
                        <?php echo esc_html(rest_url('line-hub/v1/webhook')); ?>
                    </code>
                    <button type="button" class="button button-small line-hub-copy-btn"
                            data-copy="<?php echo esc_attr(rest_url('line-hub/v1/webhook')); ?>">複製</button>
                    <p class="description">填入 Messaging API Channel 的 Webhook URL</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">儲存設定</button>
        </p>
    </form>

    <?php $has_access_token = !empty($settings['access_token']); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin: -10px 0 0 0;">
        <?php wp_nonce_field('line_hub_test_connection', 'line_hub_test_nonce'); ?>
        <input type="hidden" name="action" value="line_hub_test_connection">
        <button type="submit" class="button button-secondary"
                <?php echo !$has_access_token ? 'disabled' : ''; ?>>
            測試連線
        </button>
    </form>
</div>

<!-- 區塊 B：LINE Login 設定 -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <h2>LINE Login 設定</h2>
    <p class="description">用於 OAuth 登入和 LIFF。對應 LINE Developers Console 的 <strong>LINE Login</strong> Channel（與 Messaging API 是不同的 Channel）。</p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('line_hub_save_settings', 'line_hub_nonce'); ?>
        <input type="hidden" name="action" value="line_hub_save_settings">
        <input type="hidden" name="tab" value="line-settings">
        <input type="hidden" name="section" value="login">

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="login_channel_id">Channel ID</label>
                </th>
                <td>
                    <input type="text" id="login_channel_id" name="login_channel_id"
                           value="<?php echo esc_attr($settings['login_channel_id'] ?? ''); ?>"
                           class="regular-text" placeholder="例：2008622068">
                    <p class="description">LINE Login Channel 的 Channel ID</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="login_channel_secret">Channel Secret</label>
                </th>
                <td>
                    <input type="text" id="login_channel_secret" name="login_channel_secret"
                           value="<?php echo esc_attr($settings['login_channel_secret'] ?? ''); ?>"
                           class="regular-text" placeholder="32 位元字串">
                    <p class="description">LINE Login Channel 的 Channel Secret（自動加密儲存）</p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="liff_id">LIFF ID</label>
                </th>
                <td>
                    <input type="text" id="liff_id" name="liff_id"
                           value="<?php echo esc_attr($settings['liff_id'] ?? ''); ?>"
                           class="regular-text" placeholder="例：2008622068-iU4Z1lk4">
                    <p class="description">LIFF App ID（建立在 LINE Login Channel 下）</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Callback URL</th>
                <td>
                    <code style="background: #f5f5f5; padding: 8px 12px; display: inline-block;">
                        <?php echo esc_html($site_url . '/line-hub/auth/callback'); ?>
                    </code>
                    <button type="button" class="button button-small line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($site_url . '/line-hub/auth/callback'); ?>">複製</button>
                    <p class="description">請在 LINE Login Channel 的 Callback URL 中註冊此網址</p>
                </td>
            </tr>
            <tr>
                <th scope="row">LIFF Endpoint URL</th>
                <td>
                    <code style="background: #f5f5f5; padding: 8px 12px; display: inline-block;">
                        <?php echo esc_html($site_url . '/line-hub/liff/'); ?>
                    </code>
                    <button type="button" class="button button-small line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($site_url . '/line-hub/liff/'); ?>">複製</button>
                    <p class="description">填入 LIFF App 的 Endpoint URL</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">儲存設定</button>
        </p>
    </form>
</div>

<!-- 區塊 C：NSL 整合 -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <h2>NSL (Nextend Social Login) 整合</h2>
    <p class="description">如果之前使用 NSL 做 LINE 登入，可啟用相容模式平滑過渡到 LineHub。</p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('line_hub_save_settings', 'line_hub_nonce'); ?>
        <input type="hidden" name="action" value="line_hub_save_settings">
        <input type="hidden" name="tab" value="line-settings">
        <input type="hidden" name="section" value="nsl">

        <table class="form-table">
            <tr>
                <th scope="row">NSL 相容模式</th>
                <td>
                    <label>
                        <input type="checkbox" name="nsl_compat_mode" value="1"
                               <?php checked($settings['nsl_compat_mode'] ?? false); ?>>
                        啟用 NSL 相容模式（同時從 wp_social_users 查詢用戶）
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">自動遷移</th>
                <td>
                    <label>
                        <input type="checkbox" name="nsl_auto_migrate" value="1"
                               <?php checked($settings['nsl_auto_migrate'] ?? false); ?>>
                        自動遷移 NSL 用戶到 LineHub（新用戶登入時自動複製）
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">儲存設定</button>
        </p>
    </form>
</div>
