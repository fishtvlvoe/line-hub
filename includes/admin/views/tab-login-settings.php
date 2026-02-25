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
        <h2><?php esc_html_e('Login Mode', 'line-hub'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Login Method', 'line-hub'); ?></th>
                <td>
                    <?php $login_mode = $settings_general['login_mode'] ?? 'auto'; ?>
                    <fieldset>
                        <label class="lh-label-block">
                            <input type="radio" name="login_mode" value="auto"
                                   <?php checked($login_mode, 'auto'); ?>>
                            <strong><?php esc_html_e('Auto', 'line-hub'); ?></strong> — <?php esc_html_e('Use LIFF when LIFF ID is available, otherwise use LINE OA login', 'line-hub'); ?>
                        </label>
                        <label class="lh-label-block">
                            <input type="radio" name="login_mode" value="oauth"
                                   <?php checked($login_mode, 'oauth'); ?>>
                            <strong><?php esc_html_e('LINE OA Only', 'line-hub'); ?></strong> — <?php esc_html_e('Use OAuth 2.0 standard authorization flow', 'line-hub'); ?>
                        </label>
                        <label class="lh-label-block">
                            <input type="radio" name="login_mode" value="liff"
                                   <?php checked($login_mode, 'liff'); ?>>
                            <strong><?php esc_html_e('LIFF Only', 'line-hub'); ?></strong> — <?php esc_html_e('Login via LIFF App (requires LIFF ID)', 'line-hub'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>

        <!-- LINE Login 行為 -->
        <h3><?php esc_html_e('LINE Login Behavior', 'line-hub'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Initial Login Method', 'line-hub'); ?></th>
                <td>
                    <?php $initial_amr = $settings_login['initial_amr'] ?? ''; ?>
                    <select name="initial_amr">
                        <option value="" <?php selected($initial_amr, ''); ?>>
                            <?php esc_html_e('Default (LINE auto-detect)', 'line-hub'); ?>
                        </option>
                        <option value="lineqr" <?php selected($initial_amr, 'lineqr'); ?>>
                            <?php esc_html_e('QR Code scan login', 'line-hub'); ?>
                        </option>
                        <option value="lineautologin" <?php selected($initial_amr, 'lineautologin'); ?>>
                            <?php esc_html_e('Auto login (when LINE App is installed)', 'line-hub'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Controls which login method is displayed first on the LINE Login screen', 'line-hub'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Force Re-authorization', 'line-hub'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="force_reauth" value="1"
                               <?php checked($settings_login['force_reauth'] ?? false); ?>>
                        <?php esc_html_e('Require re-authorization on every login (do not use existing authorized session)', 'line-hub'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Allow Method Switching', 'line-hub'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="switch_amr" value="1"
                               <?php checked($settings_login['switch_amr'] ?? true); ?>>
                        <?php esc_html_e('Allow users to switch login methods on the login screen (QR Code / Email)', 'line-hub'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Auto Login', 'line-hub'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="allow_auto_login" value="1"
                               <?php checked($settings_login['allow_auto_login'] ?? false); ?>>
                        <?php esc_html_e('Allow auto login (automatically authenticate when already logged into LINE, without showing login screen)', 'line-hub'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Add Friend Behavior', 'line-hub'); ?></th>
                <td>
                    <?php $bot_prompt = $settings_login['bot_prompt'] ?? 'normal'; ?>
                    <fieldset>
                        <label class="lh-label-block">
                            <input type="radio" name="bot_prompt" value="normal"
                                   <?php checked($bot_prompt, 'normal'); ?>>
                            <strong>normal</strong> — <?php esc_html_e('Show add friend option after login (user can skip)', 'line-hub'); ?>
                        </label>
                        <label class="lh-label-block">
                            <input type="radio" name="bot_prompt" value="aggressive"
                                   <?php checked($bot_prompt, 'aggressive'); ?>>
                            <strong>aggressive</strong> — <?php esc_html_e('Force display add friend prompt during login', 'line-hub'); ?>
                        </label>
                    </fieldset>
                    <p class="description">
                        <?php esc_html_e('Requires "Linked OA" to be enabled in LINE Developers Console', 'line-hub'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <!-- 新用戶設定 -->
        <h3><?php esc_html_e('New User Settings', 'line-hub'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="username_prefix"><?php esc_html_e('Username Prefix', 'line-hub'); ?></label>
                </th>
                <td>
                    <input type="text" id="username_prefix" name="username_prefix"
                           value="<?php echo esc_attr($settings_general['username_prefix'] ?? 'line'); ?>"
                           class="regular-text" placeholder="line">
                    <p class="description">
                        <?php esc_html_e('WordPress username prefix for new users, e.g. line_U1234', 'line-hub'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="display_name_prefix"><?php esc_html_e('Display Name Prefix', 'line-hub'); ?></label>
                </th>
                <td>
                    <input type="text" id="display_name_prefix"
                           name="display_name_prefix"
                           value="<?php echo esc_attr($settings_general['display_name_prefix'] ?? 'lineuser-'); ?>"
                           class="regular-text" placeholder="lineuser-">
                    <p class="description">
                        <?php esc_html_e('When LINE display name cannot be used as username, this prefix + random code is used', 'line-hub'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Default Roles', 'line-hub'); ?></th>
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
                        <?php esc_html_e('Roles automatically assigned to new LINE users after registration (multiple selections allowed)', 'line-hub'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Auto Link by Email', 'line-hub'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_link_by_email" value="1"
                               <?php checked($settings_general['auto_link_by_email'] ?? true); ?>>
                        <?php esc_html_e('If the LINE account email matches an existing WordPress account, automatically link them', 'line-hub'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php require __DIR__ . '/partials/login-button-positions.php'; ?>

        <!-- 重定向 -->
        <h3><?php esc_html_e('Post-Login Redirect', 'line-hub'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Redirect Method', 'line-hub'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="login_redirect_fixed" value="1"
                               <?php checked($settings_general['login_redirect_fixed'] ?? false); ?>>
                        <?php esc_html_e('Use a fixed redirect URL (if unchecked, returns to the original page)', 'line-hub'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="login_redirect_url"><?php esc_html_e('Fixed URL', 'line-hub'); ?></label>
                </th>
                <td>
                    <input type="text" id="login_redirect_url"
                           name="login_redirect_url"
                           value="<?php echo esc_attr($settings_general['login_redirect_url'] ?? ''); ?>"
                           class="large-text"
                           placeholder="<?php esc_attr_e('e.g. /my-account or https://example.com/dashboard', 'line-hub'); ?>">
                    <p class="description">
                        <?php esc_html_e('When fixed redirect is enabled, all LINE logins will redirect to this URL after completion', 'line-hub'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <!-- 安全性 -->
        <h3><?php esc_html_e('Security Settings', 'line-hub'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Email Verification', 'line-hub'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="require_email_verification"
                               value="1"
                               <?php checked($settings_general['require_email_verification'] ?? false); ?>>
                        <?php esc_html_e('Require email verification (new users must verify email before login)', 'line-hub'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="allowed_email_domains"><?php esc_html_e('Allowed Domains', 'line-hub'); ?></label>
                </th>
                <td>
                    <input type="text" id="allowed_email_domains"
                           name="allowed_email_domains"
                           value="<?php echo esc_attr($settings_general['allowed_email_domains'] ?? ''); ?>"
                           class="regular-text"
                           placeholder="gmail.com, yahoo.com">
                    <p class="description">
                        <?php esc_html_e('Only allow registration from specific email domains (comma-separated, leave empty for no restriction)', 'line-hub'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'line-hub'); ?></button>
        </p>
    </form>
</div>
