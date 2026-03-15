<?php
/**
 * Partial: REST API 端點文件
 *
 * 母模板：tab-developer.php
 * 共用變數：$rest_base, $api_endpoints
 *
 * @package LineHub\Admin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- REST API 端點 -->
<div class="lh-dev-card" id="lh-rest-api">
    <h2><?php esc_html_e('REST API Endpoints', 'buygo-hub-for-line'); ?></h2>
    <p class="description">
        <?php esc_html_e('Base URL:', 'buygo-hub-for-line'); ?> <code><?php echo esc_html($rest_base); ?></code><br>
        <?php esc_html_e('Authentication:', 'buygo-hub-for-line'); ?> HTTP Header <code>X-LineHub-API-Key: lhk_your_api_key</code>
    </p>

    <?php foreach ($api_endpoints as $idx => $ep) : ?>
        <div class="lh-dev-endpoint">
            <div class="lh-dev-endpoint-header">
                <span class="lh-dev-method lh-dev-method-<?php echo esc_attr(strtolower($ep['method'])); ?>">
                    <?php echo esc_html($ep['method']); ?>
                </span>
                <span class="lh-dev-endpoint-path"><?php echo esc_html($ep['path']); ?></span>
                <span class="lh-endpoint-title">
                    <?php echo esc_html($ep['title']); ?>
                </span>
            </div>
            <div class="lh-dev-endpoint-body">
                <p class="lh-dev-endpoint-desc"><?php echo esc_html($ep['description']); ?></p>

                <?php if (!empty($ep['params'])) : ?>
                    <table class="lh-dev-params-table">
                        <thead>
                            <tr>
                                <th class="lh-col-120"><?php esc_html_e('Parameter', 'buygo-hub-for-line'); ?></th>
                                <th class="lh-col-80"><?php esc_html_e('Type', 'buygo-hub-for-line'); ?></th>
                                <th class="lh-col-120"><?php esc_html_e('Required', 'buygo-hub-for-line'); ?></th>
                                <th><?php esc_html_e('Description', 'buygo-hub-for-line'); ?></th>
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

                <div class="lh-dev-response-label"><?php esc_html_e('curl Example', 'buygo-hub-for-line'); ?></div>
                <div class="lh-dev-code-block">
                    <button type="button" class="lh-dev-copy-btn line-hub-copy-btn"
                            data-copy="<?php echo esc_attr($ep['curl']); ?>"><?php esc_html_e('Copy', 'buygo-hub-for-line'); ?></button>
<?php echo esc_html($ep['curl']); ?>
                </div>

                <?php if (!empty($ep['response'])) : ?>
                    <div class="lh-dev-response-label"><?php esc_html_e('Response Example', 'buygo-hub-for-line'); ?></div>
                    <div class="lh-dev-code-block lh-dev-code-block-light">
<?php echo esc_html($ep['response']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
