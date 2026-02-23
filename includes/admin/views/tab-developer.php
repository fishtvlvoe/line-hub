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
