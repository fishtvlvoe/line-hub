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
<div class="card lh-card-narrow">
    <h2><?php esc_html_e('LINE Messaging API Settings', 'line-hub'); ?></h2>
    <p class="description"><?php
        printf(
            /* translators: %s: Messaging API */
            esc_html__('Used for sending messages and receiving Webhooks. Corresponds to the %s Channel in LINE Developers Console.', 'line-hub'),
            '<strong>Messaging API</strong>'
        );
    ?></p>

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
                           class="regular-text" placeholder="<?php esc_attr_e('e.g. 2008621590', 'line-hub'); ?>">
                    <p class="description"><?php esc_html_e('Channel ID of the Messaging API Channel', 'line-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="channel_secret">Channel Secret</label>
                </th>
                <td>
                    <input type="text" id="channel_secret" name="channel_secret"
                           value="<?php echo esc_attr($settings['channel_secret'] ?? ''); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e('32-character string', 'line-hub'); ?>">
                    <p class="description"><?php esc_html_e('Used for Webhook signature verification (automatically encrypted)', 'line-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="access_token">Channel Access Token</label>
                </th>
                <td>
                    <textarea id="access_token" name="access_token" rows="3"
                              class="large-text"
                              placeholder="<?php esc_attr_e('Long-lived or short-lived Access Token', 'line-hub'); ?>"><?php echo esc_textarea($settings['access_token'] ?? ''); ?></textarea>
                    <p class="description"><?php esc_html_e('Used for sending messages (automatically encrypted)', 'line-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">Webhook URL</th>
                <td>
                    <code class="lh-code-display">
                        <?php echo esc_html(rest_url('line-hub/v1/webhook')); ?>
                    </code>
                    <button type="button" class="button button-small line-hub-copy-btn"
                            data-copy="<?php echo esc_attr(rest_url('line-hub/v1/webhook')); ?>"><?php esc_html_e('Copy', 'line-hub'); ?></button>
                    <p class="description"><?php esc_html_e('Enter this in the Webhook URL field of the Messaging API Channel', 'line-hub'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'line-hub'); ?></button>
        </p>
    </form>

    <?php $has_access_token = !empty($settings['access_token']); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="lh-form-offset">
        <?php wp_nonce_field('line_hub_test_connection', 'line_hub_test_nonce'); ?>
        <input type="hidden" name="action" value="line_hub_test_connection">
        <button type="submit" class="button button-secondary"
                <?php echo !$has_access_token ? 'disabled' : ''; ?>>
            <?php esc_html_e('Test Connection', 'line-hub'); ?>
        </button>
    </form>
</div>

<!-- 區塊 B：LINE Login 設定 -->
<div class="card lh-card-narrow-spaced">
    <h2><?php esc_html_e('LINE Login Settings', 'line-hub'); ?></h2>
    <p class="description"><?php
        printf(
            /* translators: %s: LINE Login */
            esc_html__('Used for OAuth login and LIFF. Corresponds to the %s Channel in LINE Developers Console (different from the Messaging API Channel).', 'line-hub'),
            '<strong>LINE Login</strong>'
        );
    ?></p>

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
                           class="regular-text" placeholder="<?php esc_attr_e('e.g. 2008622068', 'line-hub'); ?>">
                    <p class="description"><?php esc_html_e('Channel ID of the LINE Login Channel', 'line-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="login_channel_secret">Channel Secret</label>
                </th>
                <td>
                    <input type="text" id="login_channel_secret" name="login_channel_secret"
                           value="<?php echo esc_attr($settings['login_channel_secret'] ?? ''); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e('32-character string', 'line-hub'); ?>">
                    <p class="description"><?php esc_html_e('Channel Secret of the LINE Login Channel (automatically encrypted)', 'line-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="liff_id">LIFF ID</label>
                </th>
                <td>
                    <input type="text" id="liff_id" name="liff_id"
                           value="<?php echo esc_attr($settings['liff_id'] ?? ''); ?>"
                           class="regular-text" placeholder="<?php esc_attr_e('e.g. 2008622068-iU4Z1lk4', 'line-hub'); ?>">
                    <p class="description"><?php esc_html_e('LIFF App ID (created under the LINE Login Channel)', 'line-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">Callback URL</th>
                <td>
                    <code class="lh-code-display">
                        <?php echo esc_html($site_url . '/line-hub/auth/callback'); ?>
                    </code>
                    <button type="button" class="button button-small line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($site_url . '/line-hub/auth/callback'); ?>"><?php esc_html_e('Copy', 'line-hub'); ?></button>
                    <p class="description"><?php esc_html_e('Register this URL in the Callback URL settings of the LINE Login Channel', 'line-hub'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">LIFF Endpoint URL</th>
                <td>
                    <code class="lh-code-display">
                        <?php echo esc_html($site_url . '/line-hub/liff/'); ?>
                    </code>
                    <button type="button" class="button button-small line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($site_url . '/line-hub/liff/'); ?>"><?php esc_html_e('Copy', 'line-hub'); ?></button>
                    <p class="description"><?php esc_html_e('Enter this as the Endpoint URL of the LIFF App', 'line-hub'); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'line-hub'); ?></button>
        </p>
    </form>

    <?php $has_login_credentials = !empty($settings['login_channel_id']) && !empty($settings['login_channel_secret']); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="lh-form-offset">
        <?php wp_nonce_field('line_hub_test_login', 'line_hub_test_login_nonce'); ?>
        <input type="hidden" name="action" value="line_hub_test_login">
        <button type="submit" class="button button-secondary"
                <?php echo !$has_login_credentials ? 'disabled' : ''; ?>>
            <?php esc_html_e('Test Connection', 'line-hub'); ?>
        </button>
    </form>
</div>

<!-- 區塊 C：NSL 整合 -->
<div class="card lh-card-narrow-spaced">
    <h2><?php esc_html_e('NSL (Nextend Social Login) Integration', 'line-hub'); ?></h2>
    <p class="description"><?php esc_html_e('If you previously used NSL for LINE login, enable compatibility mode for a smooth transition to LINE Hub.', 'line-hub'); ?></p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('line_hub_save_settings', 'line_hub_nonce'); ?>
        <input type="hidden" name="action" value="line_hub_save_settings">
        <input type="hidden" name="tab" value="line-settings">
        <input type="hidden" name="section" value="nsl">

        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('NSL Compatibility Mode', 'line-hub'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="nsl_compat_mode" value="1"
                               <?php checked($settings['nsl_compat_mode'] ?? false); ?>>
                        <?php esc_html_e('Enable NSL compatibility mode (also query users from wp_social_users)', 'line-hub'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Auto Migration', 'line-hub'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="nsl_auto_migrate" value="1"
                               <?php checked($settings['nsl_auto_migrate'] ?? false); ?>>
                        <?php esc_html_e('Automatically migrate NSL users to LINE Hub (auto-copy on new user login)', 'line-hub'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'line-hub'); ?></button>
        </p>
    </form>
</div>
