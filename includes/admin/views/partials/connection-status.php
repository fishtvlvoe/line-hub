<?php
/**
 * 連線狀態區塊 partial
 *
 * 可用變數：$settings (array) — SettingsService::get_group('general') 的結果
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$has_channel_id     = !empty($settings['channel_id']);
$has_channel_secret = !empty($settings['channel_secret']);
$has_access_token   = !empty($settings['access_token']);
$has_liff_id        = !empty($settings['liff_id']);
?>

<div style="margin: 20px 0;">
    <h3>LINE Messaging API</h3>
    <ul style="list-style: none; padding-left: 0;">
        <li style="margin: 8px 0;">
            <?php echo $has_channel_id ? '✓' : '✗'; ?>
            Channel ID <?php echo $has_channel_id ? '已設定' : '尚未設定'; ?>
        </li>
        <li style="margin: 8px 0;">
            <?php echo $has_channel_secret ? '✓' : '✗'; ?>
            Channel Secret <?php echo $has_channel_secret ? '已設定' : '尚未設定'; ?>
        </li>
        <li style="margin: 8px 0;">
            <?php echo $has_access_token ? '✓' : '✗'; ?>
            Access Token <?php echo $has_access_token ? '已設定' : '尚未設定'; ?>
        </li>
    </ul>

    <h3 style="margin-top: 20px;">LINE Login</h3>
    <ul style="list-style: none; padding-left: 0;">
        <li style="margin: 8px 0;">
            <?php echo $has_channel_id ? '✓' : '✗'; ?>
            Channel ID <?php echo $has_channel_id ? '已設定' : '尚未設定'; ?>
        </li>
        <li style="margin: 8px 0;">
            <?php echo $has_channel_secret ? '✓' : '✗'; ?>
            Channel Secret <?php echo $has_channel_secret ? '已設定' : '尚未設定'; ?>
        </li>
        <li style="margin: 8px 0;">
            <?php echo $has_liff_id ? '✓' : '⚠'; ?>
            LIFF ID <?php echo $has_liff_id ? '已設定' : '尚未設定（選用）'; ?>
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
