<?php
/**
 * 設定 Tab 模板
 *
 * 可用變數：$settings (array) — SettingsService::get_group('general') 的結果
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
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
                    <?php
                    $positions = $settings['login_button_positions'] ?? [];
                    $position_options = [
                        'fluentcart_product' => 'FluentCart 產品頁（未登入時顯示）',
                        'wp_login'           => 'WordPress 登入頁',
                        'fluent_community'   => 'FluentCommunity 登入表單',
                    ];
                    foreach ($position_options as $value => $label) :
                    ?>
                        <label>
                            <input type="checkbox"
                                   name="login_button_positions[]"
                                   value="<?php echo esc_attr($value); ?>"
                                   <?php checked(in_array($value, $positions, true)); ?>>
                            <?php echo esc_html($label); ?>
                        </label><br>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">按鈕大小</th>
                <td>
                    <?php
                    $size_options = ['small' => '小', 'medium' => '中', 'large' => '大'];
                    $current_size = $settings['login_button_size'] ?? 'medium';
                    foreach ($size_options as $value => $label) :
                    ?>
                        <label <?php echo $value !== 'small' ? 'style="margin-left: 15px;"' : ''; ?>>
                            <input type="radio" name="login_button_size"
                                   value="<?php echo esc_attr($value); ?>"
                                   <?php checked($current_size, $value); ?>>
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
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
                        <input type="checkbox" name="require_email_verification" value="1"
                               <?php checked($settings['require_email_verification'] ?? false); ?>>
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
    <?php require __DIR__ . '/partials/connection-status.php'; ?>
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
