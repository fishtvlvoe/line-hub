<?php
/**
 * 登入設定 Tab 模板
 *
 * 可用變數：
 *   $settings_general (array) — SettingsService::get_group('general')
 *   $settings_login   (array) — SettingsService::get_group('login')
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="card lh-card-narrow">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('line_hub_save_settings', 'line_hub_nonce'); ?>
        <input type="hidden" name="action" value="line_hub_save_settings">
        <input type="hidden" name="tab" value="login-settings">

        <!-- 登入模式 -->
        <h2>登入模式</h2>
        <table class="form-table">
            <tr>
                <th scope="row">登入方式</th>
                <td>
                    <?php $login_mode = $settings_general['login_mode'] ?? 'auto'; ?>
                    <fieldset>
                        <label class="lh-label-block">
                            <input type="radio" name="login_mode" value="auto"
                                   <?php checked($login_mode, 'auto'); ?>>
                            <strong>自動</strong> — 有 LIFF ID 時用 LIFF，否則用 LINE OA 登入
                        </label>
                        <label class="lh-label-block">
                            <input type="radio" name="login_mode" value="oauth"
                                   <?php checked($login_mode, 'oauth'); ?>>
                            <strong>僅 LINE OA</strong> — 使用 OAuth 2.0 標準授權流程
                        </label>
                        <label class="lh-label-block">
                            <input type="radio" name="login_mode" value="liff"
                                   <?php checked($login_mode, 'liff'); ?>>
                            <strong>僅 LIFF</strong> — 透過 LIFF App 登入（需設定 LIFF ID）
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>

        <!-- LINE Login 行為 -->
        <h3>LINE Login 行為</h3>
        <table class="form-table">
            <tr>
                <th scope="row">初始登入方法</th>
                <td>
                    <?php $initial_amr = $settings_login['initial_amr'] ?? ''; ?>
                    <select name="initial_amr">
                        <option value="" <?php selected($initial_amr, ''); ?>>
                            預設（LINE 自動決定）
                        </option>
                        <option value="lineqr" <?php selected($initial_amr, 'lineqr'); ?>>
                            QR Code 掃碼登入
                        </option>
                        <option value="lineautologin" <?php selected($initial_amr, 'lineautologin'); ?>>
                            自動登入（LINE App 已安裝時）
                        </option>
                    </select>
                    <p class="description">
                        控制 LINE Login 畫面首先顯示的登入方式
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">強制重新授權</th>
                <td>
                    <label>
                        <input type="checkbox" name="force_reauth" value="1"
                               <?php checked($settings_login['force_reauth'] ?? false); ?>>
                        每次登入都要求用戶重新授權（不使用已授權的 session）
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">允許切換方法</th>
                <td>
                    <label>
                        <input type="checkbox" name="switch_amr" value="1"
                               <?php checked($settings_login['switch_amr'] ?? true); ?>>
                        允許用戶在登入畫面切換登入方法（QR Code / Email）
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">自動登入</th>
                <td>
                    <label>
                        <input type="checkbox" name="allow_auto_login" value="1"
                               <?php checked($settings_login['allow_auto_login'] ?? false); ?>>
                        允許自動登入（已登入 LINE 時自動認證，不顯示登入畫面）
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">加好友行為</th>
                <td>
                    <?php $bot_prompt = $settings_login['bot_prompt'] ?? 'normal'; ?>
                    <fieldset>
                        <label class="lh-label-block">
                            <input type="radio" name="bot_prompt" value="normal"
                                   <?php checked($bot_prompt, 'normal'); ?>>
                            <strong>normal</strong> — 登入後顯示加好友選項（用戶可跳過）
                        </label>
                        <label class="lh-label-block">
                            <input type="radio" name="bot_prompt" value="aggressive"
                                   <?php checked($bot_prompt, 'aggressive'); ?>>
                            <strong>aggressive</strong> — 登入時強制顯示加好友提示
                        </label>
                    </fieldset>
                    <p class="description">
                        需在 LINE Developers Console 啟用「Linked OA」才有效
                    </p>
                </td>
            </tr>
        </table>

        <!-- 新用戶設定 -->
        <h3>新用戶設定</h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="username_prefix">用戶名前綴</label>
                </th>
                <td>
                    <input type="text" id="username_prefix" name="username_prefix"
                           value="<?php echo esc_attr($settings_general['username_prefix'] ?? 'line'); ?>"
                           class="regular-text" placeholder="line">
                    <p class="description">
                        新用戶 WordPress 用戶名前綴，例如 line_U1234
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="display_name_prefix">顯示名稱前綴</label>
                </th>
                <td>
                    <input type="text" id="display_name_prefix"
                           name="display_name_prefix"
                           value="<?php echo esc_attr($settings_general['display_name_prefix'] ?? 'lineuser-'); ?>"
                           class="regular-text" placeholder="lineuser-">
                    <p class="description">
                        當 LINE 暱稱無法作為用戶名時，使用此前綴 + 隨機碼
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">預設角色</th>
                <td>
                    <?php
                    $current_roles = $settings_general['default_roles'] ?? ['subscriber'];
                    if (!is_array($current_roles)) {
                        $current_roles = [$current_roles];
                    }
                    $all_roles = wp_roles()->get_names();
                    foreach ($all_roles as $role_value => $role_name) :
                    ?>
                        <label class="lh-label-block-sm">
                            <input type="checkbox"
                                   name="default_roles[]"
                                   value="<?php echo esc_attr($role_value); ?>"
                                   <?php checked(in_array($role_value, $current_roles, true)); ?>>
                            <?php echo esc_html(translate_user_role($role_name)); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description">
                        LINE 新用戶註冊後自動獲得的角色（可多選）
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">Email 自動連結</th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_link_by_email" value="1"
                               <?php checked($settings_general['auto_link_by_email'] ?? true); ?>>
                        如果 LINE 帳號的 Email 和既有 WordPress 帳號相同，自動綁定
                    </label>
                </td>
            </tr>
        </table>

        <?php require __DIR__ . '/partials/login-button-positions.php'; ?>

        <!-- 重定向 -->
        <h3>登入後重定向</h3>
        <table class="form-table">
            <tr>
                <th scope="row">重定向方式</th>
                <td>
                    <label>
                        <input type="checkbox" name="login_redirect_fixed" value="1"
                               <?php checked($settings_general['login_redirect_fixed'] ?? false); ?>>
                        使用固定重定向 URL（不勾選則返回原頁面）
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="login_redirect_url">固定 URL</label>
                </th>
                <td>
                    <input type="text" id="login_redirect_url"
                           name="login_redirect_url"
                           value="<?php echo esc_attr($settings_general['login_redirect_url'] ?? ''); ?>"
                           class="large-text"
                           placeholder="例：/my-account 或 https://example.com/dashboard">
                    <p class="description">
                        啟用固定重定向後，所有 LINE 登入完成後都會導向此 URL
                    </p>
                </td>
            </tr>
        </table>

        <!-- 安全性 -->
        <h3>安全性設定</h3>
        <table class="form-table">
            <tr>
                <th scope="row">Email 驗證</th>
                <td>
                    <label>
                        <input type="checkbox" name="require_email_verification"
                               value="1"
                               <?php checked($settings_general['require_email_verification'] ?? false); ?>>
                        強制 Email 驗證（新用戶必須驗證 Email 才能登入）
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allowed_email_domains">限制網域</label>
                </th>
                <td>
                    <input type="text" id="allowed_email_domains"
                           name="allowed_email_domains"
                           value="<?php echo esc_attr($settings_general['allowed_email_domains'] ?? ''); ?>"
                           class="regular-text"
                           placeholder="gmail.com, yahoo.com">
                    <p class="description">
                        只允許特定 Email 網域註冊（逗號分隔，留空表示不限制）
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary">儲存設定</button>
        </p>
    </form>
</div>
