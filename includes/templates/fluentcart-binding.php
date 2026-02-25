<?php
/**
 * FluentCart 客戶入口 LINE 綁定區塊模板
 *
 * 可用變數：
 * @var bool   $is_bound     是否已綁定
 * @var string $display_name LINE 顯示名稱
 * @var string $picture_url  LINE 頭像 URL
 * @var object $binding      綁定資料物件
 * @var string $linked_at    綁定時間
 * @var string $nonce        REST API nonce
 * @var string $rest_url     REST API URL
 * @var string $bind_url     LIFF 綁定 URL
 *
 * @package LineHub
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="lh-fc-binding" id="lineHubFcBinding">
    <h3 class="lh-fc-binding-title">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="#06C755"><path d="M24 10.304C24 4.974 18.629.607 12 .607S0 4.974 0 10.304c0 4.8 4.27 8.834 10.035 9.602.391.084.922.258 1.058.592.12.3.079.77.038 1.08l-.164 1.02c-.045.3-.24 1.17 1.049.638 1.291-.532 6.916-4.07 9.436-6.97C23.176 14.393 24 12.458 24 10.304"/></svg>
        <?php esc_html_e('LINE Account Binding', 'line-hub'); ?>
    </h3>

    <?php if ($is_bound) : ?>
        <div class="lh-fc-binding-card">
            <?php if (!empty($picture_url)) : ?>
                <img src="<?php echo esc_url($picture_url); ?>"
                     alt="<?php echo esc_attr($display_name); ?>"
                     class="lh-fc-avatar">
            <?php else : ?>
                <div class="lh-fc-avatar-empty">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="#9ca3af"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                </div>
            <?php endif; ?>

            <div class="lh-fc-details">
                <?php if (!empty($display_name)) : ?>
                    <p class="lh-fc-name"><?php echo esc_html($display_name); ?></p>
                <?php endif; ?>
                <div class="lh-fc-uid">LINE UID: <?php echo esc_html($binding->line_uid); ?></div>
                <?php if (!empty($linked_at)) : ?>
                    <!-- translators: %s: date when the LINE account was linked -->
                    <div class="lh-fc-date"><?php printf(esc_html__('Linked on: %s', 'line-hub'), esc_html($linked_at)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="lh-fc-actions">
            <button type="button" class="lh-fc-btn lh-fc-btn-unbind" id="lhFcUnbindBtn"
                    data-rest-url="<?php echo esc_url($rest_url); ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                <?php esc_html_e('Unlink', 'line-hub'); ?>
            </button>
        </div>

        <div class="lh-fc-status" id="lhFcStatus"></div>

    <?php else : ?>
        <div class="lh-fc-unbound">
            <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" style="flex-shrink:0"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            <?php esc_html_e('Link your LINE account to receive order notifications and shipping updates.', 'line-hub'); ?>
        </div>

        <?php if (!empty($bind_url)) : ?>
            <div class="lh-fc-actions">
                <a href="<?php echo esc_url($bind_url); ?>" class="lh-fc-btn lh-fc-btn-bind">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M24 10.304C24 4.974 18.629.607 12 .607S0 4.974 0 10.304c0 4.8 4.27 8.834 10.035 9.602.391.084.922.258 1.058.592.12.3.079.77.038 1.08l-.164 1.02c-.045.3-.24 1.17 1.049.638 1.291-.532 6.916-4.07 9.436-6.97C23.176 14.393 24 12.458 24 10.304"/></svg>
                    <?php esc_html_e('Link LINE Account', 'line-hub'); ?>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
