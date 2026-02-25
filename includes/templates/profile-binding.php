<?php
/**
 * LINE Hub - 個人資料頁 LINE 綁定區塊模板
 *
 * 可用變數（由呼叫端傳入）：
 * @var object|null $binding       LINE 綁定資料
 * @var \WP_User    $user          用戶物件
 * @var bool        $has_login_configured 是否已設定 LINE Login
 * @var string      $bind_url      LIFF 綁定 URL
 * @var string      $rest_url      REST API 解除綁定端點
 * @var string      $nonce         WP REST nonce
 *
 * @package LineHub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="line-hub-profile-section">
    <h2>
        <svg viewBox="0 0 24 24" width="24" height="24" fill="#06C755"><path d="M24 10.304C24 4.974 18.629.607 12 .607S0 4.974 0 10.304c0 4.8 4.27 8.834 10.035 9.602.391.084.922.258 1.058.592.12.3.079.77.038 1.08l-.164 1.02c-.045.3-.24 1.17 1.049.638 1.291-.532 6.916-4.07 9.436-6.97C23.176 14.393 24 12.458 24 10.304"/></svg>
        <?php esc_html_e('LINE Account Binding', 'line-hub'); ?>
    </h2>

    <div class="line-hub-binding-card">
        <?php if ($binding && !empty($binding->line_uid)) : ?>
            <?php
            // 判斷資料來源
            $is_nsl = empty($binding->display_name) && !\LineHub\Services\UserService::hasDirectBinding($user->ID);
            $display_name = !empty($binding->display_name) ? $binding->display_name : __('Unknown', 'line-hub');
            $picture_url = !empty($binding->picture_url) ? $binding->picture_url : '';
            $linked_at = !empty($binding->created_at) ? $binding->created_at : '';

            // 也嘗試從 user meta 取頭像
            if (empty($picture_url)) {
                $picture_url = get_user_meta($user->ID, 'line_hub_avatar_url', true);
            }
            ?>
            <div class="line-hub-binding-info">
                <?php if (!empty($picture_url)) : ?>
                    <img src="<?php echo esc_url($picture_url); ?>"
                         alt="<?php echo esc_attr($display_name); ?>"
                         class="line-hub-binding-avatar">
                <?php else : ?>
                    <div class="line-hub-binding-avatar-placeholder">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                <?php endif; ?>

                <div class="line-hub-binding-details">
                    <p class="line-hub-binding-name">
                        <?php echo esc_html($display_name); ?>
                        <?php if ($is_nsl) : ?>
                            <span class="line-hub-binding-source line-hub-source-nsl">NSL</span>
                        <?php else : ?>
                            <span class="line-hub-binding-source line-hub-source-line-hub">LINE Hub</span>
                        <?php endif; ?>
                    </p>
                    <div class="line-hub-binding-uid">
                        LINE UID: <?php echo esc_html($binding->line_uid); ?>
                    </div>
                    <?php if (!empty($linked_at)) : ?>
                        <div class="line-hub-binding-date">
                            <?php
                            /* translators: %s: date */
                            /* translators: %s: date when the LINE account was linked */
                            printf(esc_html__('Linked on: %s', 'line-hub'), esc_html($linked_at));
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="line-hub-binding-actions">
                <button type="button" class="line-hub-btn line-hub-btn-unbind" id="lineHubUnbindBtn"
                        data-rest-url="<?php echo esc_url($rest_url); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>">
                    <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    <?php esc_html_e('Unlink', 'line-hub'); ?>
                </button>
            </div>

            <div class="line-hub-status-msg" id="lineHubStatusMsg"></div>

        <?php elseif ($has_login_configured) : ?>
            <div class="line-hub-unbound-notice">
                <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" style="color:#f59e0b;flex-shrink:0"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                <?php esc_html_e('LINE account is not linked. Link your account to receive order notifications and shipping updates.', 'line-hub'); ?>
            </div>

            <?php if (!empty($bind_url)) : ?>
                <div class="line-hub-binding-actions">
                    <a href="<?php echo esc_url($bind_url); ?>" class="line-hub-btn line-hub-btn-bind">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M24 10.304C24 4.974 18.629.607 12 .607S0 4.974 0 10.304c0 4.8 4.27 8.834 10.035 9.602.391.084.922.258 1.058.592.12.3.079.77.038 1.08l-.164 1.02c-.045.3-.24 1.17 1.049.638 1.291-.532 6.916-4.07 9.436-6.97C23.176 14.393 24 12.458 24 10.304"/></svg>
                        <?php esc_html_e('Link LINE Account', 'line-hub'); ?>
                    </a>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <p style="color:#6b7280;margin:0;"><?php esc_html_e('LINE Login is not configured. Please contact the administrator.', 'line-hub'); ?></p>
        <?php endif; ?>
    </div>
</div>
