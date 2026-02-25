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
<div class="lh-dev-card lh-mt-0">
    <h2><?php esc_html_e('Developer Tools', 'line-hub'); ?></h2>
    <p class="description">
        <?php esc_html_e('All information needed to integrate with LINE Hub. Interact via REST API (external systems) or WordPress Hooks (same-host plugins).', 'line-hub'); ?>
    </p>
    <div class="lh-dev-section-nav">
        <a href="#lh-api-key"><?php esc_html_e('API Key Management', 'line-hub'); ?></a>
        <a href="#lh-rest-api"><?php esc_html_e('REST API Endpoints', 'line-hub'); ?></a>
        <a href="#lh-hooks"><?php esc_html_e('WordPress Hooks', 'line-hub'); ?></a>
        <a href="#lh-api-logs"><?php esc_html_e('API Usage Log', 'line-hub'); ?></a>
    </div>
</div>

<!-- API Key 管理 -->
<div class="lh-dev-card" id="lh-api-key">
    <h2><?php esc_html_e('API Key Management', 'line-hub'); ?></h2>
    <p class="description">
        <?php esc_html_e('External systems (e.g. SaaS, Zapier) authenticate via the HTTP Header', 'line-hub'); ?>
        <code>X-LineHub-API-Key</code>
        <?php esc_html_e('to call the REST API endpoints below.', 'line-hub'); ?>
    </p>

    <?php if ($new_api_key) : ?>
        <div class="lh-api-key-banner">
            <p class="lh-api-key-banner-title">
                <?php esc_html_e('API Key generated — please copy and save it now. This key will not be shown again!', 'line-hub'); ?>
            </p>
            <code class="lh-api-key-code">
                <?php echo esc_html($new_api_key); ?>
            </code>
            <button type="button" class="button button-small line-hub-copy-btn lh-ml-8"
                    data-copy="<?php echo esc_attr($new_api_key); ?>">
                <?php esc_html_e('Copy', 'line-hub'); ?>
            </button>
        </div>
    <?php endif; ?>

    <?php if ($has_api_key) : ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Current API Key', 'line-hub'); ?></th>
                <td>
                    <code><?php echo esc_html($api_key_prefix); ?>...<?php echo esc_html(str_repeat('*', 24)); ?></code>
                    <br>
                    <span class="description">
                        <?php
                        /* translators: %s: date when the API key was created */
                        printf(esc_html__('Created on: %s', 'line-hub'), esc_html($api_key_date));
                        ?>
                    </span>
                </td>
            </tr>
        </table>
        <form method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              class="lh-mt-10"
              onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to revoke this API Key? All external systems using this key will lose access.', 'line-hub')); ?>');">
            <?php wp_nonce_field('line_hub_api_key_action', 'line_hub_api_nonce'); ?>
            <input type="hidden" name="action" value="line_hub_revoke_api_key">
            <button type="submit" class="button button-secondary lh-text-danger">
                <?php esc_html_e('Revoke API Key', 'line-hub'); ?>
            </button>
        </form>
    <?php else : ?>
        <form method="post"
              action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              class="lh-mt-16">
            <?php wp_nonce_field('line_hub_api_key_action', 'line_hub_api_nonce'); ?>
            <input type="hidden" name="action" value="line_hub_generate_api_key">
            <button type="submit" class="button button-primary">
                <?php esc_html_e('Generate API Key', 'line-hub'); ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/developer-api-endpoints.php'; ?>

<!-- WordPress Hooks -->
<div class="lh-dev-card" id="lh-hooks">
    <h2><?php esc_html_e('WordPress Hooks', 'line-hub'); ?></h2>
    <p class="description">
        <?php esc_html_e('WordPress plugins on the same host can interact with LINE Hub via standard Hooks, no API Key required.', 'line-hub'); ?>
    </p>

    <h3><?php esc_html_e('Actions (Send Messages)', 'line-hub'); ?></h3>

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
                            <th class="lh-col-120"><?php esc_html_e('Parameter', 'line-hub'); ?></th>
                            <th class="lh-col-80"><?php esc_html_e('Type', 'line-hub'); ?></th>
                            <th><?php esc_html_e('Description', 'line-hub'); ?></th>
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

                <div class="lh-dev-response-label"><?php esc_html_e('Usage Example', 'line-hub'); ?></div>
                <div class="lh-dev-code-block lh-dev-code-block-light">
                    <button type="button" class="lh-dev-copy-btn lh-dev-copy-btn-light line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($action['example']); ?>"><?php esc_html_e('Copy', 'line-hub'); ?></button>
<?php echo esc_html($action['example']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <h3><?php esc_html_e('Filters (Query Users)', 'line-hub'); ?></h3>

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
                            <th class="lh-col-120"><?php esc_html_e('Parameter', 'line-hub'); ?></th>
                            <th class="lh-col-80"><?php esc_html_e('Type', 'line-hub'); ?></th>
                            <th><?php esc_html_e('Description', 'line-hub'); ?></th>
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

                <div class="lh-dev-response-label"><?php esc_html_e('Usage Example', 'line-hub'); ?></div>
                <div class="lh-dev-code-block lh-dev-code-block-light">
                    <button type="button" class="lh-dev-copy-btn lh-dev-copy-btn-light line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($filter['example']); ?>"><?php esc_html_e('Copy', 'line-hub'); ?></button>
<?php echo esc_html($filter['example']); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- API 使用記錄 -->
<div class="lh-dev-card" id="lh-api-logs">
    <h2><?php esc_html_e('API Usage Log', 'line-hub'); ?></h2>
    <p class="description">
        <?php esc_html_e('Recent 20 REST API calls (API Key authenticated). Use this to verify external system integration.', 'line-hub'); ?>
    </p>

    <?php if (empty($api_logs)) : ?>
        <p class="lh-text-muted lh-mt-16">
            <?php esc_html_e('No API call records yet. Records will appear here when external systems call REST API endpoints using an API Key.', 'line-hub'); ?>
        </p>
    <?php else : ?>
        <table class="lh-dev-log-table lh-mt-16">
            <thead>
                <tr>
                    <th class="lh-col-160"><?php esc_html_e('Time', 'line-hub'); ?></th>
                    <th class="lh-col-130"><?php esc_html_e('Source IP', 'line-hub'); ?></th>
                    <th class="lh-col-80"><?php esc_html_e('Method', 'line-hub'); ?></th>
                    <th><?php esc_html_e('Endpoint', 'line-hub'); ?></th>
                    <th class="lh-col-80"><?php esc_html_e('Result', 'line-hub'); ?></th>
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
                                /* translators: %s: human-readable time difference */
                                printf(esc_html__('%s ago', 'line-hub'), esc_html($diff));
                            } else {
                                echo esc_html($log['time'] ?? '-');
                            }
                            ?>
                        </td>
                        <td>
                            <code class="lh-code-ip"><?php echo esc_html($log['ip'] ?? '-'); ?></code>
                        </td>
                        <td>
                            <span class="lh-dev-method lh-dev-method-<?php echo esc_attr(strtolower($log['method'] ?? 'get')); ?>">
                                <?php echo esc_html($log['method'] ?? '-'); ?>
                            </span>
                        </td>
                        <td>
                            <code class="lh-code-ip"><?php echo esc_html($log['endpoint'] ?? '-'); ?></code>
                        </td>
                        <td>
                            <?php if (($log['status'] ?? '') === 'success') : ?>
                                <span class="lh-dev-status-badge lh-dev-status-success"><?php esc_html_e('Success', 'line-hub'); ?></span>
                            <?php else : ?>
                                <span class="lh-dev-status-badge lh-dev-status-error"><?php esc_html_e('Failed', 'line-hub'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
