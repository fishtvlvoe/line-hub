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
    <h2>連線狀態總覽</h2>
    <?php require __DIR__ . '/partials/connection-status.php'; ?>

    <div class="lh-mt-20">
        <a href="<?php echo esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'line-settings'], admin_url('admin.php'))); ?>"
           class="button button-primary">
            前往 LINE 設定
        </a>
        <a href="<?php echo esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'login-settings'], admin_url('admin.php'))); ?>"
           class="button button-secondary lh-ml-8">
            前往登入設定
        </a>
    </div>
</div>

<!-- 設定步驟說明 -->
<div class="card lh-card-narrow-spaced">
    <details open>
        <summary class="lh-wizard-summary">
            設定步驟說明
        </summary>
        <ol class="lh-wizard-steps">
            <li>前往 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a></li>
            <li>你需要兩個 Channel：<strong>Messaging API</strong>（發訊息用）和 <strong>LINE Login</strong>（登入用）</li>
            <li>在 <strong>Messaging API Channel</strong> 的 Basic settings 取得 Channel ID 和 Channel Secret，填入「<a href="<?php echo esc_url(add_query_arg(['page' => 'line-hub-settings', 'tab' => 'line-settings'], admin_url('admin.php'))); ?>">LINE 設定</a>」</li>
            <li>在 <strong>Messaging API Channel</strong> 發行 Channel Access Token，填入「LINE 設定」</li>
            <li>在 <strong>Messaging API Channel</strong> 設定 Webhook URL 為「LINE 設定」頁面顯示的網址，並啟用 Use webhook</li>
            <li>在 <strong>LINE Login Channel</strong> 的 Basic settings 取得 Channel ID 和 Channel Secret，填入「LINE 設定」</li>
            <li>在 <strong>LINE Login Channel</strong> 的 Callback URL 設定中加入「LINE 設定」頁面的 Callback URL</li>
            <li>如使用 LIFF，在 <strong>LINE Login Channel</strong> 建立 LIFF App，將 Endpoint URL 設為「LINE 設定」頁面顯示的網址，並將 LIFF ID 填入</li>
        </ol>
    </details>
</div>
