<?php
/**
 * 開發者 Tab 模板
 *
 * 可用變數：
 *   $settings_integration (array) — SettingsService::get_group('integration')
 *   $events (array) — WebhookLogger::getRecent(20)
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

$api_key_hash   = $settings_integration['api_key_hash'] ?? '';
$api_key_prefix = $settings_integration['api_key_prefix'] ?? '';
$api_key_date   = $settings_integration['api_key_created_at'] ?? '';
$has_api_key    = !empty($api_key_hash);

// 檢查是否剛產生新 Key
$new_api_key = get_transient('line_hub_new_api_key');
if ($new_api_key) {
    delete_transient('line_hub_new_api_key');
}

$rest_base = rest_url('line-hub/v1');
$site_url  = home_url();
?>

<!-- API Key 管理 -->
<div class="card" style="max-width: 1000px;">
    <h2>API Key 管理</h2>
    <p class="description">
        外部系統（如 SaaS、Zapier）透過 HTTP Header
        <code>X-LineHub-API-Key</code> 認證，呼叫下方 REST API 端點。
    </p>

    <?php if ($new_api_key) : ?>
        <div style="background: #dcfce7; border: 1px solid #86efac; border-radius: 6px;
                    padding: 16px; margin: 16px 0;">
            <p style="margin: 0 0 8px 0; font-weight: 600; color: #166534;">
                API Key 已產生 — 請立即複製保存，此 Key 不會再顯示！
            </p>
            <code style="font-size: 14px; background: #fff; padding: 8px 12px;
                         display: inline-block; border: 1px solid #86efac; user-select: all;">
                <?php echo esc_html($new_api_key); ?>
            </code>
            <button type="button" class="button button-small line-hub-copy-btn"
                    data-copy="<?php echo esc_attr($new_api_key); ?>"
                    style="margin-left: 8px;">
                複製
            </button>
        </div>
    <?php endif; ?>

    <?php if ($has_api_key) : ?>
        <table class="form-table">
            <tr>
                <th scope="row">目前 API Key</th>
                <td>
                    <code><?php echo esc_html($api_key_prefix); ?>...<?php echo str_repeat('*', 24); ?></code>
                    <br>
                    <span class="description">
                        建立於：<?php echo esc_html($api_key_date); ?>
                    </span>
                </td>
            </tr>
        </table>
        <form method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              style="margin-top: 10px;"
              onsubmit="return confirm('確定要撤銷此 API Key？撤銷後所有使用此 Key 的外部系統將無法連線。');">
            <?php wp_nonce_field('line_hub_api_key_action', 'line_hub_api_nonce'); ?>
            <input type="hidden" name="action" value="line_hub_revoke_api_key">
            <button type="submit" class="button button-secondary"
                    style="color: #dc2626;">
                撤銷 API Key
            </button>
        </form>
    <?php else : ?>
        <form method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              style="margin-top: 16px;">
            <?php wp_nonce_field('line_hub_api_key_action', 'line_hub_api_nonce'); ?>
            <input type="hidden" name="action" value="line_hub_generate_api_key">
            <button type="submit" class="button button-primary">
                產生 API Key
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- REST API 端點 -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <h2>REST API 端點</h2>
    <p class="description">
        外部系統透過 API Key 認證，呼叫以下端點與 LINE 用戶互動。
    </p>

    <h3 style="margin-top: 20px;">訊息發送</h3>

    <h4><code>POST <?php echo esc_html($rest_base); ?>/messages/text</code></h4>
    <p>發送文字訊息給指定用戶</p>
    <pre style="background: #f5f5f5; padding: 12px; overflow-x: auto; font-size: 12px;">curl -X POST <?php echo esc_html($rest_base); ?>/messages/text \
  -H "X-LineHub-API-Key: lhk_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 123, "message": "你好！"}'</pre>

    <h4 style="margin-top: 16px;">
        <code>POST <?php echo esc_html($rest_base); ?>/messages/flex</code>
    </h4>
    <p>發送 Flex 訊息</p>
    <pre style="background: #f5f5f5; padding: 12px; overflow-x: auto; font-size: 12px;">curl -X POST <?php echo esc_html($rest_base); ?>/messages/flex \
  -H "X-LineHub-API-Key: lhk_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 123, "alt_text": "通知", "contents": {...}}'</pre>

    <h4 style="margin-top: 16px;">
        <code>POST <?php echo esc_html($rest_base); ?>/messages/broadcast</code>
    </h4>
    <p>批量發送文字訊息</p>
    <pre style="background: #f5f5f5; padding: 12px; overflow-x: auto; font-size: 12px;">curl -X POST <?php echo esc_html($rest_base); ?>/messages/broadcast \
  -H "X-LineHub-API-Key: lhk_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"user_ids": [1, 2, 3], "message": "公告訊息"}'</pre>

    <h3 style="margin-top: 30px;">用戶查詢</h3>

    <h4>
        <code>GET <?php echo esc_html($rest_base); ?>/users/{id}/binding</code>
    </h4>
    <p>查詢指定用戶的 LINE 綁定狀態</p>
    <pre style="background: #f5f5f5; padding: 12px; overflow-x: auto; font-size: 12px;">curl <?php echo esc_html($rest_base); ?>/users/123/binding \
  -H "X-LineHub-API-Key: lhk_your_api_key"

# 回應範例
# {"is_linked": true, "line_uid": "U1234...", "display_name": "用戶名"}</pre>
</div>

<!-- WordPress Hooks -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <h2>WordPress Hooks</h2>
    <p class="description">同主機的 WordPress 外掛可透過標準 hooks 與 LineHub 互動。</p>

    <h3 style="margin-top: 20px;">Actions（發送訊息）</h3>

    <h4><code>line_hub/send/text</code></h4>
    <pre style="background: #f5f5f5; padding: 12px; font-size: 12px;">do_action('line_hub/send/text', [
    'user_id' => 123,
    'message' => '你的訂單已建立！',
]);</pre>

    <h4 style="margin-top: 16px;"><code>line_hub/send/flex</code></h4>
    <pre style="background: #f5f5f5; padding: 12px; font-size: 12px;">do_action('line_hub/send/flex', [
    'user_id'  => 123,
    'alt_text' => '訂單通知',
    'contents' => [ /* Flex Message JSON */ ],
]);</pre>

    <h4 style="margin-top: 16px;"><code>line_hub/send/broadcast</code></h4>
    <pre style="background: #f5f5f5; padding: 12px; font-size: 12px;">do_action('line_hub/send/broadcast', [
    'user_ids' => [1, 2, 3],
    'message'  => '公告訊息',
]);</pre>

    <h3 style="margin-top: 30px;">Filters（查詢用戶）</h3>

    <h4><code>line_hub/user/is_linked</code></h4>
    <pre style="background: #f5f5f5; padding: 12px; font-size: 12px;">$is_linked = apply_filters('line_hub/user/is_linked', false, $user_id);</pre>

    <h4 style="margin-top: 16px;"><code>line_hub/user/get_line_uid</code></h4>
    <pre style="background: #f5f5f5; padding: 12px; font-size: 12px;">$line_uid = apply_filters('line_hub/user/get_line_uid', '', $user_id);</pre>
</div>

<!-- Shortcodes -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <h2>短代碼 (Shortcodes)</h2>

    <h3><code>[line_hub_login]</code></h3>
    <p style="color: #666;">在任何頁面插入 LINE 登入按鈕</p>

    <h4 style="margin-top: 10px;">參數：</h4>
    <ul style="line-height: 1.8; color: #666;">
        <li><code>text</code> — 按鈕文字（預設：使用「登入」Tab 的設定值）</li>
        <li><code>size</code> — 按鈕大小（small / medium / large）</li>
        <li><code>redirect</code> — 登入後重定向 URL（可選）</li>
    </ul>

    <h4 style="margin-top: 10px;">範例：</h4>
    <pre style="background: #f5f5f5; padding: 12px; border-left: 4px solid #06C755;">[line_hub_login text="立即登入" size="large" redirect="/my-account"]</pre>
</div>

<!-- PHP 範例 -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <h2>PHP 範例</h2>

    <h3>取得用戶的 LINE UID</h3>
    <pre style="background: #f5f5f5; padding: 12px;">$line_uid = \LineHub\Services\UserService::getLineUid(get_current_user_id());
if ($line_uid) {
    echo "LINE UID: $line_uid";
}</pre>

    <h3 style="margin-top: 20px;">檢查用戶是否已綁定 LINE</h3>
    <pre style="background: #f5f5f5; padding: 12px;">$is_linked = \LineHub\Services\UserService::isLinked($user_id);
if ($is_linked) {
    echo "用戶已綁定 LINE 帳號";
}</pre>

    <h3 style="margin-top: 20px;">透過 LINE UID 查詢 WordPress 用戶</h3>
    <pre style="background: #f5f5f5; padding: 12px;">$user_id = \LineHub\Services\UserService::getUserByLineUid('U1234567890abcdef');
if ($user_id) {
    $user = get_user_by('id', $user_id);
    echo "找到用戶: " . $user->display_name;
}</pre>
</div>

<!-- Webhook 事件記錄 -->
<div class="card" style="max-width: 1000px; margin-top: 20px;">
    <h2>Webhook 事件記錄</h2>

    <?php if (empty($events)) : ?>
        <p style="color: #999;">
            尚無 Webhook 事件記錄。當 LINE 用戶與您的 Bot 互動時，事件會顯示在這裡。
        </p>
    <?php else : ?>
        <p style="color: #666;">最近 <?php echo count($events); ?> 筆事件</p>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th style="width: 60px;">ID</th>
                    <th style="width: 140px;">事件類型</th>
                    <th style="width: 180px;">LINE UID</th>
                    <th style="width: 160px;">時間</th>
                    <th style="width: 60px;">狀態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event) : ?>
                    <tr>
                        <td><?php echo esc_html($event['id']); ?></td>
                        <td>
                            <code><?php echo esc_html($event['event_type']); ?></code>
                        </td>
                        <td>
                            <?php if (!empty($event['line_uid'])) : ?>
                                <code style="font-size: 11px;">
                                    <?php echo esc_html(substr($event['line_uid'], 0, 15) . '...'); ?>
                                </code>
                            <?php else : ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $time_diff = human_time_diff(
                                strtotime($event['received_at']),
                                time()
                            );
                            echo esc_html($time_diff . ' 前');
                            ?>
                        </td>
                        <td>
                            <?php if ($event['processed']) : ?>
                                <span style="color: #46b450;">&#10003;</span>
                            <?php else : ?>
                                <span style="color: #999;">&#8943;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small"
                                    data-toggle-payload="<?php echo esc_attr($event['id']); ?>">
                                查看 Payload
                            </button>
                            <div id="payload-<?php echo esc_attr($event['id']); ?>"
                                 style="display:none; margin-top:10px; padding:10px;
                                        background:#f5f5f5; border:1px solid #ddd;
                                        border-radius:3px;">
                                <pre style="overflow-x:auto; font-size:12px; max-height:300px;"><?php
                                    echo esc_html(json_encode(
                                        json_decode($event['payload']),
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                                    ));
                                ?></pre>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
