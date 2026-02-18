<?php
/**
 * 入門 Tab 模板
 *
 * 可用變數：$site_url (string)
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="line-hub-card">
    <h2>歡迎使用 LINE Hub</h2>
    <p style="font-size: 16px; color: #666;">WordPress 的 LINE 整合中樞 — 統一管理 LINE Login、LIFF、Webhook 和通知推送</p>
</div>

<div class="line-hub-card">
    <h2>快速開始（3 步驟）</h2>

    <div style="margin: 20px 0;">
        <h3 style="color: #06C755;">[ 1 ] 建立 LINE Login Channel</h3>
        <ul style="line-height: 1.8; color: #666;">
            <li>前往 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a></li>
            <li>建立 Provider 和 Login Channel</li>
            <li>取得 <strong>Channel ID</strong> 和 <strong>Channel Secret</strong></li>
        </ul>
        <a href="https://developers.line.biz/en/docs/line-login/getting-started/" target="_blank" class="button">查看詳細教學</a>
    </div>

    <div style="margin: 20px 0; padding: 15px; background: #f5f5f5; border-left: 4px solid #06C755;">
        <h3 style="color: #06C755; margin-top: 0;">[ 2 ] 設定 Callback URL 和 LIFF</h3>
        <p><strong>Callback URL</strong>（填入 LINE Developers Console）：</p>
        <p>
            <code style="background: #fff; padding: 8px 12px; display: inline-block; border: 1px solid #ddd;">
                <?php echo esc_html($site_url . '/line-hub/auth/callback'); ?>
            </code>
            <button class="button button-small line-hub-copy-btn"
                    data-copy="<?php echo esc_attr($site_url . '/line-hub/auth/callback'); ?>">複製</button>
        </p>

        <p style="margin-top: 15px;"><strong>LIFF Endpoint URL</strong>（填入 LIFF App 設定）：</p>
        <p>
            <code style="background: #fff; padding: 8px 12px; display: inline-block; border: 1px solid #ddd;">
                <?php echo esc_html($site_url . '/line-hub/liff/'); ?>
            </code>
            <button class="button button-small line-hub-copy-btn"
                    data-copy="<?php echo esc_attr($site_url . '/line-hub/liff/'); ?>">複製</button>
        </p>
    </div>

    <div style="margin: 20px 0;">
        <h3 style="color: #06C755;">[ 3 ] 填入設定資訊</h3>
        <p style="color: #666;">前往「設定」Tab，填入 Channel ID、Secret、Access Token、LIFF ID，然後測試連線。</p>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'settings'], admin_url('admin.php'))); ?>"
           class="button button-primary">前往設定</a>
    </div>
</div>

<div class="line-hub-card">
    <h2>已完成功能</h2>
    <ul style="line-height: 2; color: #666;">
        <li>✓ LINE Login（OAuth 2.0 標準授權）</li>
        <li>✓ LIFF 登入（LINE 內瀏覽器整合）</li>
        <li>✓ 用戶綁定管理（LINE UID ⇄ WordPress User）</li>
        <li>✓ Email 收集和帳號合併</li>
        <li>✓ NSL（Nextend Social Login）相容模式</li>
        <li>✓ FluentCart 產品頁登入按鈕</li>
    </ul>
</div>

<div class="line-hub-card">
    <h2>即將推出</h2>
    <ul style="line-height: 2; color: #999;">
        <li>LINE 通知推送（Phase 4 — 訊息模板引擎）</li>
        <li>Webhook 接收和處理（Phase 5 — 關鍵字回應）</li>
        <li>BuyGo 整合（Phase 6 — 訂單通知）</li>
    </ul>
</div>
