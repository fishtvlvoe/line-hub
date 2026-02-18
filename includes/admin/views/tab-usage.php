<?php
/**
 * 用法 Tab 模板（開發者文件）
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="line-hub-card">
    <h2>短代碼 (Shortcodes)</h2>

    <h3><code>[line_hub_login]</code></h3>
    <p style="color: #666;">顯示 LINE 登入按鈕</p>

    <h4 style="margin-top: 15px;">參數：</h4>
    <ul style="line-height: 1.8; color: #666;">
        <li><code>text</code> — 按鈕文字（預設：「用 LINE 帳號登入」）</li>
        <li><code>size</code> — 按鈕大小（small / medium / large，預設：medium）</li>
        <li><code>redirect</code> — 登入後重定向 URL（可選）</li>
    </ul>

    <h4 style="margin-top: 15px;">範例：</h4>
    <pre style="background: #f5f5f5; padding: 15px; border-left: 4px solid #06C755;">[line_hub_login text="立即登入" size="large" redirect="/my-account"]</pre>
</div>

<div class="line-hub-card">
    <h2>WordPress Hooks</h2>

    <h3 style="margin-top: 20px;">Actions</h3>

    <h4><code>line_hub/user_logged_in</code></h4>
    <p style="color: #666;">當用戶透過 LINE 登入時觸發</p>
    <p><strong>參數：</strong> <code>$user_id</code> (int), <code>$line_uid</code> (string)</p>
    <pre style="background: #f5f5f5; padding: 15px;">add_action('line_hub/user_logged_in', function($user_id, $line_uid) {
    // 處理 LINE 登入事件
}, 10, 2);</pre>

    <h4 style="margin-top: 20px;"><code>line_hub/webhook/message/text</code></h4>
    <p style="color: #666;">當收到文字訊息時觸發</p>
    <p><strong>參數：</strong>
        <code>$event</code> (array),
        <code>$line_uid</code> (string),
        <code>$user_id</code> (int|null),
        <code>$msg_id</code> (string)
    </p>

    <h3 style="margin-top: 30px;">Filters</h3>

    <h4><code>line_hub/login_redirect_url</code></h4>
    <p style="color: #666;">自訂登入後的重定向 URL</p>
    <p><strong>參數：</strong> <code>$url</code> (string), <code>$user</code> (WP_User)</p>
    <pre style="background: #f5f5f5; padding: 15px;">add_filter('line_hub/login_redirect_url', function($url, $user) {
    if (in_array('vip', $user->roles)) {
        return '/vip-dashboard';
    }
    return $url;
}, 10, 2);</pre>
</div>

<div class="line-hub-card">
    <h2>PHP 範例</h2>

    <h3>取得用戶的 LINE UID</h3>
    <pre style="background: #f5f5f5; padding: 15px;">$line_uid = \LineHub\Services\UserService::getLineUid(get_current_user_id());
if ($line_uid) {
    echo "LINE UID: $line_uid";
}</pre>

    <h3 style="margin-top: 20px;">檢查用戶是否已綁定 LINE</h3>
    <pre style="background: #f5f5f5; padding: 15px;">$is_linked = \LineHub\Services\UserService::isLinked($user_id);
if ($is_linked) {
    echo "用戶已綁定 LINE 帳號";
}</pre>

    <h3 style="margin-top: 20px;">透過 LINE UID 查詢 WordPress 用戶</h3>
    <pre style="background: #f5f5f5; padding: 15px;">$user_id = \LineHub\Services\UserService::getUserByLineUid('U1234567890abcdef');
if ($user_id) {
    $user = get_user_by('id', $user_id);
    echo "找到用戶: " . $user->display_name;
}</pre>
</div>
