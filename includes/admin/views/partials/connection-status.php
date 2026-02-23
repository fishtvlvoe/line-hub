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

$has_channel_id         = !empty($settings['channel_id']);
$has_channel_secret     = !empty($settings['channel_secret']);
$has_access_token       = !empty($settings['access_token']);
$has_login_channel_id   = !empty($settings['login_channel_id']);
$has_login_channel_secret = !empty($settings['login_channel_secret']);
$has_liff_id            = !empty($settings['liff_id']);
?>

<div style="margin: 20px 0;">
    <h3>LINE Messaging API</h3>
    <ul style="list-style: none; padding-left: 0;">
        <li style="margin: 8px 0;">
            <?php echo $has_channel_id ? '&#10003;' : '&#10007;'; ?>
            Channel ID <?php echo $has_channel_id ? '已設定' : '尚未設定'; ?>
        </li>
        <li style="margin: 8px 0;">
            <?php echo $has_channel_secret ? '&#10003;' : '&#10007;'; ?>
            Channel Secret <?php echo $has_channel_secret ? '已設定' : '尚未設定'; ?>
        </li>
        <li style="margin: 8px 0;">
            <?php echo $has_access_token ? '&#10003;' : '&#10007;'; ?>
            Access Token <?php echo $has_access_token ? '已設定' : '尚未設定'; ?>
        </li>
    </ul>

    <h3 style="margin-top: 20px;">LINE Login</h3>
    <ul style="list-style: none; padding-left: 0;">
        <li style="margin: 8px 0;">
            <?php echo $has_login_channel_id ? '&#10003;' : '&#10007;'; ?>
            Channel ID <?php echo $has_login_channel_id ? '已設定' : '尚未設定'; ?>
            <?php if (!$has_login_channel_id && $has_channel_id): ?>
                <span style="color: #996800;">（將 fallback 到 Messaging API Channel ID）</span>
            <?php endif; ?>
        </li>
        <li style="margin: 8px 0;">
            <?php echo $has_login_channel_secret ? '&#10003;' : '&#10007;'; ?>
            Channel Secret <?php echo $has_login_channel_secret ? '已設定' : '尚未設定'; ?>
            <?php if (!$has_login_channel_secret && $has_channel_secret): ?>
                <span style="color: #996800;">（將 fallback 到 Messaging API Channel Secret）</span>
            <?php endif; ?>
        </li>
        <li style="margin: 8px 0;">
            <?php echo $has_liff_id ? '&#10003;' : '&#9888;'; ?>
            LIFF ID <?php echo $has_liff_id ? '已設定' : '尚未設定（選用）'; ?>
        </li>
    </ul>
</div>
