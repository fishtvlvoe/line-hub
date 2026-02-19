<?php
/**
 * 設定 Tab 模板
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

<!-- LINE Channel 設定 -->
<div class="card" style="max-width: 1000px;">
    <h2>LINE Channel 設定</h2>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('line_hub_save_settings', 'line_hub_nonce'); ?>
        <input type="hidden" name="action" value="line_hub_save_settings">
        <input type="hidden" name="tab" value="settings">

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="channel_id">Channel ID</label>
                </th>
                <td>
                    <input type="text" id="channel_id" name="channel_id"
                           value="<?php echo esc_attr($settings['channel_id'] ?? ''); ?>"
                           class="regular-text" placeholder="例：2008621590">
                    <p class="description">從 LINE Developers Console 的 Messaging API 頁面取得</p>
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
                    <p class="description">用於 Webhook 簽名驗證和 OAuth 認證（自動加密儲存）</p>
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
                <th scope="row">
                    <label for="liff_id">LIFF ID</label>
                </th>
                <td>
                    <input type="text" id="liff_id" name="liff_id"
                           value="<?php echo esc_attr($settings['liff_id'] ?? ''); ?>"
                           class="regular-text" placeholder="例：2008622068-iU4Z1lk4">
                    <p class="description">LIFF App ID（如有使用 LIFF 登入功能）</p>
                </td>
            </tr>
        </table>

        <!-- NSL 整合 -->
        <h3>NSL (Nextend Social Login) 整合</h3>
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

<!-- 重要網址 -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <h2>重要網址</h2>
    <p class="description">以下網址需要填入 LINE Developers Console 對應的設定欄位。</p>

    <table class="form-table">
        <tr>
            <th scope="row">Callback URL</th>
            <td>
                <code style="background: #f5f5f5; padding: 8px 12px; display: inline-block;">
                    <?php echo esc_html($site_url . '/line-hub/auth/callback'); ?>
                </code>
                <button type="button" class="button button-small line-hub-copy-btn"
                        data-copy="<?php echo esc_attr($site_url . '/line-hub/auth/callback'); ?>">複製</button>
                <p class="description">填入 LINE Login Channel 的 Callback URL</p>
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
</div>

<!-- 連線測試 -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <h2>連線測試</h2>
    <?php require __DIR__ . '/partials/connection-status.php'; ?>
</div>

<!-- 設定步驟說明（折疊） -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <details>
        <summary style="cursor: pointer; font-weight: 600; font-size: 14px; padding: 8px 0;">
            設定步驟說明（點擊展開）
        </summary>
        <ol style="margin-top: 15px; line-height: 2;">
            <li>前往 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a></li>
            <li>選擇你的 Provider 和 Channel（建議使用 Messaging API Channel）</li>
            <li>從 <strong>Basic settings</strong> 頁面取得 <strong>Channel ID</strong> 和 <strong>Channel Secret</strong></li>
            <li>從 <strong>Messaging API</strong> 頁面取得 <strong>Channel Access Token</strong>（需先發行）</li>
            <li>在 <strong>LINE Login</strong> Channel 的 <strong>Callback URL</strong> 設定中加入上方的 Callback URL</li>
            <li>在 <strong>Messaging API</strong> 頁面設定 Webhook URL 為上方顯示的網址</li>
            <li>啟用 <strong>Use webhook</strong> 開關</li>
            <li>如使用 LIFF，在 <strong>LIFF</strong> 頁面建立 App，並將 Endpoint URL 設為上方顯示的網址</li>
        </ol>
    </details>
</div>
