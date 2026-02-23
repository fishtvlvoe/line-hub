<?php
/**
 * 開發者 Tab 模板
 *
 * 可用變數：
 *   $settings_integration (array)  — SettingsService::get_group('integration')
 *   $api_endpoints        (array)  — 結構化的 REST API 端點資料
 *   $hooks_data           (array)  — 結構化的 Hook 文件資料
 *   $api_logs             (array)  — 最近 20 筆 API 呼叫記錄
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
?>

<style>
    /* 開發者 Tab 專用樣式 */
    .lh-dev-card {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px 24px;
        max-width: 1000px;
        margin-top: 20px;
    }
    .lh-dev-card h2 {
        margin: 0 0 12px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
        font-size: 16px;
    }
    .lh-dev-card h3 {
        margin: 24px 0 12px 0;
        font-size: 14px;
        color: #1d2327;
    }
    .lh-dev-card h4 {
        margin: 16px 0 8px 0;
        font-size: 13px;
    }
    .lh-dev-endpoint {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin: 16px 0;
        overflow: hidden;
    }
    .lh-dev-endpoint-header {
        background: #f9fafb;
        padding: 12px 16px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .lh-dev-method {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        font-family: ui-monospace, SFMono-Regular, monospace;
        letter-spacing: 0.5px;
    }
    .lh-dev-method-get {
        background: #dbeafe;
        color: #1e40af;
    }
    .lh-dev-method-post {
        background: #dcfce7;
        color: #166534;
    }
    .lh-dev-endpoint-path {
        font-family: ui-monospace, SFMono-Regular, monospace;
        font-size: 13px;
        font-weight: 600;
        color: #1d2327;
    }
    .lh-dev-endpoint-body {
        padding: 16px;
    }
    .lh-dev-endpoint-desc {
        color: #50575e;
        margin: 0 0 12px 0;
        font-size: 13px;
    }
    .lh-dev-params-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        margin: 8px 0 16px 0;
    }
    .lh-dev-params-table th {
        text-align: left;
        padding: 6px 10px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        font-weight: 600;
        font-size: 12px;
        color: #374151;
    }
    .lh-dev-params-table td {
        padding: 6px 10px;
        border: 1px solid #e5e7eb;
        vertical-align: top;
    }
    .lh-dev-params-table code {
        background: #f3f4f6;
        padding: 1px 5px;
        border-radius: 3px;
        font-size: 12px;
    }
    .lh-dev-code-block {
        position: relative;
        background: #1e293b;
        color: #e2e8f0;
        padding: 14px 16px;
        border-radius: 6px;
        font-size: 12px;
        font-family: ui-monospace, SFMono-Regular, monospace;
        line-height: 1.6;
        overflow-x: auto;
        white-space: pre;
        margin: 8px 0;
    }
    .lh-dev-code-block-light {
        background: #f8fafc;
        color: #334155;
        border: 1px solid #e2e8f0;
    }
    .lh-dev-copy-btn {
        position: absolute;
        top: 8px;
        right: 8px;
        padding: 4px 10px;
        font-size: 11px;
        background: rgba(255,255,255,0.15);
        color: #94a3b8;
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .lh-dev-copy-btn:hover {
        background: rgba(255,255,255,0.25);
        color: #fff;
    }
    .lh-dev-copy-btn-light {
        background: #fff;
        color: #64748b;
        border-color: #cbd5e1;
    }
    .lh-dev-copy-btn-light:hover {
        background: #f1f5f9;
        color: #334155;
    }
    .lh-dev-response-label {
        font-size: 11px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 12px 0 4px 0;
    }
    .lh-dev-section-nav {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin: 12px 0 0 0;
    }
    .lh-dev-section-nav a {
        display: inline-block;
        padding: 4px 12px;
        background: #f3f4f6;
        color: #374151;
        border-radius: 4px;
        text-decoration: none;
        font-size: 13px;
        transition: all 0.2s;
    }
    .lh-dev-section-nav a:hover {
        background: #06C755;
        color: #fff;
    }
    .lh-dev-hook-card {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        margin: 16px 0;
        overflow: hidden;
    }
    .lh-dev-hook-header {
        background: #fefce8;
        padding: 10px 16px;
        border-bottom: 1px solid #e5e7eb;
    }
    .lh-dev-hook-header code {
        font-size: 13px;
        font-weight: 600;
        color: #854d0e;
    }
    .lh-dev-hook-body {
        padding: 16px;
    }
    .lh-dev-filter-header {
        background: #eff6ff;
    }
    .lh-dev-filter-header code {
        color: #1e40af;
    }
    /* API Log 表格 */
    .lh-dev-log-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .lh-dev-log-table th {
        text-align: left;
        padding: 8px 10px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        font-weight: 600;
        font-size: 12px;
    }
    .lh-dev-log-table td {
        padding: 8px 10px;
        border: 1px solid #e5e7eb;
        vertical-align: top;
    }
    .lh-dev-log-table tr:nth-child(even) td {
        background: #fafbfc;
    }
    .lh-dev-status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
    }
    .lh-dev-status-success {
        background: #dcfce7;
        color: #166534;
    }
    .lh-dev-status-error {
        background: #fee2e2;
        color: #991b1b;
    }
</style>

<!-- 快速導航 -->
<div class="lh-dev-card" style="margin-top: 0;">
    <h2>開發者工具</h2>
    <p class="description">
        整合 LINE Hub 所需的所有資訊。透過 REST API（外部系統）或 WordPress Hooks（同主機外掛）與 LINE Hub 互動。
    </p>
    <div class="lh-dev-section-nav">
        <a href="#lh-api-key">API Key 管理</a>
        <a href="#lh-rest-api">REST API 端點</a>
        <a href="#lh-hooks">WordPress Hooks</a>
        <a href="#lh-shortcodes">短代碼</a>
        <a href="#lh-api-logs">API 使用記錄</a>
    </div>
</div>

<!-- API Key 管理 -->
<div class="lh-dev-card" id="lh-api-key">
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
<div class="lh-dev-card" id="lh-rest-api">
    <h2>REST API 端點</h2>
    <p class="description">
        基礎 URL：<code><?php echo esc_html($rest_base); ?></code><br>
        認證方式：HTTP Header <code>X-LineHub-API-Key: lhk_your_api_key</code>
    </p>

    <?php foreach ($api_endpoints as $idx => $ep) : ?>
        <div class="lh-dev-endpoint">
            <div class="lh-dev-endpoint-header">
                <span class="lh-dev-method lh-dev-method-<?php echo esc_attr(strtolower($ep['method'])); ?>">
                    <?php echo esc_html($ep['method']); ?>
                </span>
                <span class="lh-dev-endpoint-path"><?php echo esc_html($ep['path']); ?></span>
                <span style="margin-left: auto; font-size: 13px; color: #6b7280;">
                    <?php echo esc_html($ep['title']); ?>
                </span>
            </div>
            <div class="lh-dev-endpoint-body">
                <p class="lh-dev-endpoint-desc"><?php echo esc_html($ep['description']); ?></p>

                <?php if (!empty($ep['params'])) : ?>
                    <table class="lh-dev-params-table">
                        <thead>
                            <tr>
                                <th style="width: 120px;">參數</th>
                                <th style="width: 80px;">型別</th>
                                <th style="width: 120px;">必填</th>
                                <th>說明</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ep['params'] as $param) : ?>
                                <tr>
                                    <td><code><?php echo esc_html($param['name']); ?></code></td>
                                    <td><code><?php echo esc_html($param['type']); ?></code></td>
                                    <td><?php echo esc_html($param['required']); ?></td>
                                    <td><?php echo esc_html($param['desc']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="lh-dev-response-label">curl 範例</div>
                <div class="lh-dev-code-block">
                    <button type="button" class="lh-dev-copy-btn line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($ep['curl']); ?>">複製</button>
<?php echo esc_html($ep['curl']); ?>
                </div>

                <?php if (!empty($ep['response'])) : ?>
                    <div class="lh-dev-response-label">回應範例</div>
                    <div class="lh-dev-code-block lh-dev-code-block-light">
<?php echo esc_html($ep['response']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- WordPress Hooks -->
<div class="lh-dev-card" id="lh-hooks">
    <h2>WordPress Hooks</h2>
    <p class="description">
        同主機的 WordPress 外掛可透過標準 Hooks 與 LINE Hub 互動，無需 API Key。
    </p>

    <h3>Actions（發送訊息）</h3>

    <?php foreach ($hooks_data['actions'] as $action) : ?>
        <div class="lh-dev-hook-card">
            <div class="lh-dev-hook-header">
                <code><?php echo esc_html($action['hook']); ?></code>
            </div>
            <div class="lh-dev-hook-body">
                <p class="lh-dev-endpoint-desc"><?php echo esc_html($action['description']); ?></p>

                <table class="lh-dev-params-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">參數</th>
                            <th style="width: 80px;">型別</th>
                            <th>說明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($action['params'] as $param) : ?>
                            <tr>
                                <td><code><?php echo esc_html($param['name']); ?></code></td>
                                <td><code><?php echo esc_html($param['type']); ?></code></td>
                                <td><?php echo esc_html($param['desc']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="lh-dev-response-label">使用範例</div>
                <div class="lh-dev-code-block lh-dev-code-block-light">
                    <button type="button" class="lh-dev-copy-btn lh-dev-copy-btn-light line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($action['example']); ?>">複製</button>
<?php echo esc_html($action['example']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <h3>Filters（查詢用戶）</h3>

    <?php foreach ($hooks_data['filters'] as $filter) : ?>
        <div class="lh-dev-hook-card">
            <div class="lh-dev-hook-header lh-dev-filter-header">
                <code><?php echo esc_html($filter['hook']); ?></code>
            </div>
            <div class="lh-dev-hook-body">
                <p class="lh-dev-endpoint-desc"><?php echo esc_html($filter['description']); ?></p>

                <table class="lh-dev-params-table">
                    <thead>
                        <tr>
                            <th style="width: 120px;">參數</th>
                            <th style="width: 80px;">型別</th>
                            <th>說明</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filter['params'] as $param) : ?>
                            <tr>
                                <td><code><?php echo esc_html($param['name']); ?></code></td>
                                <td><code><?php echo esc_html($param['type']); ?></code></td>
                                <td><?php echo esc_html($param['desc']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="lh-dev-response-label">使用範例</div>
                <div class="lh-dev-code-block lh-dev-code-block-light">
                    <button type="button" class="lh-dev-copy-btn lh-dev-copy-btn-light line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($filter['example']); ?>">複製</button>
<?php echo esc_html($filter['example']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- 短代碼 -->
<div class="lh-dev-card" id="lh-shortcodes">
    <h2>短代碼 (Shortcodes)</h2>

    <div class="lh-dev-endpoint">
        <div class="lh-dev-endpoint-header">
            <span class="lh-dev-endpoint-path">[line_hub_login]</span>
            <span style="margin-left: auto; font-size: 13px; color: #6b7280;">LINE 登入按鈕</span>
        </div>
        <div class="lh-dev-endpoint-body">
            <p class="lh-dev-endpoint-desc">在任何頁面插入 LINE 登入按鈕。</p>
            <table class="lh-dev-params-table">
                <thead>
                    <tr>
                        <th style="width: 120px;">參數</th>
                        <th style="width: 80px;">型別</th>
                        <th>說明</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>text</code></td>
                        <td><code>string</code></td>
                        <td>按鈕文字（預設：使用「登入」Tab 的設定值）</td>
                    </tr>
                    <tr>
                        <td><code>size</code></td>
                        <td><code>string</code></td>
                        <td>按鈕大小：small / medium / large</td>
                    </tr>
                    <tr>
                        <td><code>redirect</code></td>
                        <td><code>string</code></td>
                        <td>登入後重定向 URL（選填）</td>
                    </tr>
                </tbody>
            </table>
            <div class="lh-dev-response-label">範例</div>
            <div class="lh-dev-code-block lh-dev-code-block-light">
                <button type="button" class="lh-dev-copy-btn lh-dev-copy-btn-light line-hub-copy-btn"
                        data-copy='[line_hub_login text="立即登入" size="large" redirect="/my-account"]'>複製</button>
[line_hub_login text="立即登入" size="large" redirect="/my-account"]</div>
        </div>
    </div>
</div>

<!-- API 使用記錄 -->
<div class="lh-dev-card" id="lh-api-logs">
    <h2>API 使用記錄</h2>
    <p class="description">
        最近 20 次透過 REST API（API Key 認證）的呼叫記錄。用於確認外部系統串接是否正常。
    </p>

    <?php if (empty($api_logs)) : ?>
        <p style="color: #999; margin-top: 16px;">
            尚無 API 呼叫記錄。當外部系統透過 API Key 呼叫 REST API 端點時，記錄會顯示在這裡。
        </p>
    <?php else : ?>
        <table class="lh-dev-log-table" style="margin-top: 16px;">
            <thead>
                <tr>
                    <th style="width: 160px;">時間</th>
                    <th style="width: 130px;">來源 IP</th>
                    <th style="width: 80px;">方法</th>
                    <th>端點</th>
                    <th style="width: 80px;">結果</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($api_logs as $log) : ?>
                    <tr>
                        <td>
                            <?php
                            $log_time = strtotime($log['time'] ?? '');
                            if ($log_time) {
                                $diff = human_time_diff($log_time, time());
                                echo esc_html($diff . ' 前');
                            } else {
                                echo esc_html($log['time'] ?? '-');
                            }
                            ?>
                        </td>
                        <td>
                            <code style="font-size: 12px;"><?php echo esc_html($log['ip'] ?? '-'); ?></code>
                        </td>
                        <td>
                            <span class="lh-dev-method lh-dev-method-<?php echo esc_attr(strtolower($log['method'] ?? 'get')); ?>">
                                <?php echo esc_html($log['method'] ?? '-'); ?>
                            </span>
                        </td>
                        <td>
                            <code style="font-size: 12px;"><?php echo esc_html($log['endpoint'] ?? '-'); ?></code>
                        </td>
                        <td>
                            <?php if (($log['status'] ?? '') === 'success') : ?>
                                <span class="lh-dev-status-badge lh-dev-status-success">成功</span>
                            <?php else : ?>
                                <span class="lh-dev-status-badge lh-dev-status-error">失敗</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
