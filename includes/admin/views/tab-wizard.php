<?php
/**
 * 設定嚮導 Tab 模板
 *
 * 連線狀態總覽、設定步驟說明、快速連結。
 *
 * 可用變數：
 *   $settings (array) — SettingsService::get_group('general') 的結果
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- 連線狀態總覽 -->
<div class="card lh-card-narrow">
    <h2><?php esc_html_e('Connection Status Overview', 'line-hub'); ?></h2>
    <?php require __DIR__ . '/partials/connection-status.php'; ?>

    <div class="lh-mt-20">
        <a href="<?php echo esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'line-settings'], admin_url('admin.php'))); ?>"
           class="button button-primary">
            <?php esc_html_e('Go to LINE Settings', 'line-hub'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'login-settings'], admin_url('admin.php'))); ?>"
           class="button button-secondary lh-ml-8">
            <?php esc_html_e('Go to Login Settings', 'line-hub'); ?>
        </a>
    </div>
</div>

<!-- 設定步驟說明 -->
<div class="card lh-card-narrow-spaced">
    <details open>
        <summary class="lh-wizard-summary">
            <?php esc_html_e('Setup Instructions', 'line-hub'); ?>
        </summary>
        <ol class="lh-wizard-steps">
            <li><?php
                printf(
                    /* translators: %s: LINE Developers Console link */
                    esc_html__('Go to %s', 'line-hub'),
                    '<a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a>'
                );
            ?></li>
            <li><?php
                printf(
                    /* translators: 1: Messaging API, 2: LINE Login */
                    esc_html__('You need two Channels: %1$s (for sending messages) and %2$s (for login).', 'line-hub'),
                    '<strong>Messaging API</strong>',
                    '<strong>LINE Login</strong>'
                );
            ?></li>
            <li><?php
                $line_settings_url = esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'line-settings'], admin_url('admin.php')));
                printf(
                    /* translators: 1: LINE Settings link */
                    esc_html__('In the Messaging API Channel Basic settings, get the Channel ID and Channel Secret, then enter them in "%1$s".', 'line-hub'),
                    '<a href="' . $line_settings_url . '">' . esc_html__('LINE Settings', 'line-hub') . '</a>'
                );
            ?></li>
            <li><?php esc_html_e('In the Messaging API Channel, issue a Channel Access Token and enter it in "LINE Settings".', 'line-hub'); ?></li>
            <li><?php esc_html_e('In the Messaging API Channel, set the Webhook URL to the address shown on the "LINE Settings" page and enable "Use webhook".', 'line-hub'); ?></li>
            <li><?php esc_html_e('In the LINE Login Channel Basic settings, get the Channel ID and Channel Secret, then enter them in "LINE Settings".', 'line-hub'); ?></li>
            <li><?php esc_html_e('In the LINE Login Channel Callback URL settings, add the Callback URL shown on the "LINE Settings" page.', 'line-hub'); ?></li>
            <li><?php esc_html_e('If using LIFF, create a LIFF App under the LINE Login Channel, set the Endpoint URL to the address shown on the "LINE Settings" page, and enter the LIFF ID.', 'line-hub'); ?></li>
        </ol>
    </details>
</div>
