<?php
/**
 * Partial: REST API 端點文件
 *
 * 母模板：tab-developer.php
 * 共用變數：$rest_base, $api_endpoints
 *
 * @package LineHub\Admin
 */
?>

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
                                <th class="lh-col-120">參數</th>
                                <th class="lh-col-80">型別</th>
                                <th class="lh-col-120">必填</th>
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
