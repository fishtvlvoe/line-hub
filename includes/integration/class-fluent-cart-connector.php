<?php
/**
 * FluentCart 產品頁整合
 *
 * 在 FluentCart 產品頁面為未登入用戶顯示 LINE 登入提示
 *
 * @package LineHub
 */

namespace LineHub\Integration;

use LineHub\Services\SettingsService;
use LineHub\Services\UserService;

if (!defined('ABSPATH')) {
    exit;
}

class FluentCartConnector {

    /**
     * 初始化
     */
    public static function init(): void {
        // 只在前端啟用
        if (is_admin()) {
            return;
        }

        // FluentCart 客戶入口 LINE 綁定區塊
        add_action('fluent_cart/customer_app', [self::class, 'renderBindingSection'], 90);

        // 注意：登入按鈕已移至 ButtonPositions 管理（改為結帳頁掛載）
    }

    /**
     * 在 FluentCart 產品頁顯示登入提示
     */
    public static function maybeShowLoginPrompt(): void {
        // 已登入不顯示
        if (is_user_logged_in()) {
            return;
        }

        // 檢查是否為 FluentCart 產品頁
        if (!self::isProductPage()) {
            return;
        }

        // 檢查 LINE Login 是否已設定
        $liff_id = SettingsService::get('general', 'liff_id', '');
        $channel_id = SettingsService::get('general', 'channel_id', '');

        if (empty($liff_id) && empty($channel_id)) {
            return;
        }

        // 注入登入提示
        add_action('wp_head', [self::class, 'injectLoginBanner'], 20);
    }

    /**
     * 注入登入提示到產品頁
     */
    public static function injectLoginBanner(): void {
        // 載入 CSS
        wp_enqueue_style(
            'line-hub-login-button',
            LINE_HUB_URL . 'assets/css/login-button.css',
            [],
            LINE_HUB_VERSION
        );

        // 取得登入 URL
        $redirect = isset($_SERVER['REQUEST_URI'])
            ? wp_unslash($_SERVER['REQUEST_URI']) // phpcs:ignore
            : '/';
        $login_url = LoginButton::getLoginUrl($redirect);

        if (empty($login_url)) {
            return;
        }

        $banner_text = esc_html__('登入後可追蹤訂單、接收出貨通知', 'line-hub');
        $button_text = esc_html__('LINE 登入', 'line-hub');
        $login_url_escaped = esc_url($login_url);

        // 透過 JS 注入到產品頁（相容各種主題和模板）
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            // 尋找 FluentCart 產品容器
            var targets = [
                '.fcs_product_single',
                '.fluent-product-page',
                '.fc-product-single',
                '[data-fluent-cart-product]',
                '.entry-content'
            ];
            var container = null;
            for (var i = 0; i < targets.length; i++) {
                container = document.querySelector(targets[i]);
                if (container) break;
            }
            if (!container) return;

            var banner = document.createElement('div');
            banner.className = 'line-hub-login-banner';
            banner.innerHTML = '<span class="line-hub-login-banner-text"><?php echo $banner_text; ?></span>' +
                '<a href="<?php echo $login_url_escaped; ?>" class="line-hub-login-btn">' +
                '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C6.48 2 2 5.81 2 10.5c0 2.49 1.31 4.71 3.37 6.26.14.1.23.28.23.47l-.05 1.76c-.02.63.55 1.11 1.14.96l1.96-.52c.15-.04.31-.03.46.02.98.35 2.04.55 3.14.55h.5c-.03-.25-.05-.51-.05-.77 0-3.83 3.55-6.95 7.93-6.95.34 0 .68.02 1.01.06C21.17 5.36 17.02 2 12 2z"/></svg>' +
                '<?php echo $button_text; ?></a>';

            container.insertBefore(banner, container.firstChild);
        });
        </script>
        <?php
    }

    /**
     * 偵測是否為 FluentCart 產品頁
     *
     * @return bool
     */
    private static function isProductPage(): bool {
        if (!is_singular()) {
            return false;
        }

        $post = get_post();
        if (!$post) {
            return false;
        }

        // FluentCart 產品 post type
        return $post->post_type === 'fluent-products';
    }

    /**
     * 在 FluentCart 客戶入口頁面渲染 LINE 綁定區塊
     */
    public static function renderBindingSection(): void {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $binding = UserService::getBinding($user_id);
        $liff_id = SettingsService::get('general', 'liff_id', '');
        $has_login_configured = !empty($liff_id) || !empty(SettingsService::get('general', 'channel_id', ''));

        if (!$has_login_configured) {
            return;
        }

        // LIFF 綁定 URL
        $bind_url = '';
        if (!empty($liff_id)) {
            $my_account_url = home_url('/my-account/');
            $bind_url = home_url('/line-hub/liff/?redirect=' . urlencode($my_account_url));
        }

        $nonce = wp_create_nonce('wp_rest');
        $rest_url = rest_url('line-hub/v1/user/binding');

        // 判斷資料
        $is_bound = $binding && !empty($binding->line_uid);
        $display_name = $is_bound && !empty($binding->display_name) ? $binding->display_name : '';
        $picture_url = $is_bound && !empty($binding->picture_url) ? $binding->picture_url : '';
        $linked_at = $is_bound && !empty($binding->created_at) ? $binding->created_at : '';

        if (empty($picture_url) && $is_bound) {
            $picture_url = get_user_meta($user_id, 'line_hub_avatar_url', true);
        }
        ?>
        <style>
            .lh-fc-binding {
                margin: 24px 0;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 24px;
            }
            .lh-fc-binding-title {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 16px;
                font-weight: 600;
                color: #1f2937;
                margin: 0 0 16px 0;
            }
            .lh-fc-binding-title svg {
                flex-shrink: 0;
            }
            .lh-fc-binding-card {
                display: flex;
                align-items: center;
                gap: 16px;
                padding: 16px;
                background: #f9fafb;
                border-radius: 10px;
            }
            .lh-fc-avatar {
                width: 56px;
                height: 56px;
                border-radius: 50%;
                border: 3px solid #06C755;
                object-fit: cover;
                flex-shrink: 0;
            }
            .lh-fc-avatar-empty {
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            .lh-fc-details {
                flex: 1;
                min-width: 0;
            }
            .lh-fc-name {
                font-size: 15px;
                font-weight: 600;
                color: #1f2937;
                margin: 0 0 2px 0;
            }
            .lh-fc-uid {
                font-size: 12px;
                color: #9ca3af;
                font-family: ui-monospace, SFMono-Regular, monospace;
                word-break: break-all;
            }
            .lh-fc-date {
                font-size: 12px;
                color: #9ca3af;
                margin-top: 2px;
            }
            .lh-fc-actions {
                margin-top: 16px;
            }
            .lh-fc-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 10px 24px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                text-decoration: none;
                cursor: pointer;
                border: none;
                transition: all 0.2s;
            }
            .lh-fc-btn-bind {
                background: #fff;
                color: #06C755;
                border: 1.5px solid #06C755;
            }
            .lh-fc-btn-bind:hover {
                background: #06C755;
                color: #fff;
            }
            .lh-fc-btn-unbind {
                background: #fff;
                color: #ef4444;
                border: 1px solid #fca5a5;
            }
            .lh-fc-btn-unbind:hover {
                background: #fef2f2;
                color: #ef4444;
            }
            .lh-fc-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            .lh-fc-unbound {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 16px;
                background: #f0fdf4;
                border: 1px solid #bbf7d0;
                border-radius: 8px;
                color: #166534;
                font-size: 14px;
            }
            .lh-fc-status {
                margin-top: 12px;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 13px;
                display: none;
            }
        </style>

        <div class="lh-fc-binding" id="lineHubFcBinding">
            <h3 class="lh-fc-binding-title">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="#06C755"><path d="M24 10.304C24 4.974 18.629.607 12 .607S0 4.974 0 10.304c0 4.8 4.27 8.834 10.035 9.602.391.084.922.258 1.058.592.12.3.079.77.038 1.08l-.164 1.02c-.045.3-.24 1.17 1.049.638 1.291-.532 6.916-4.07 9.436-6.97C23.176 14.393 24 12.458 24 10.304"/></svg>
                <?php esc_html_e('LINE 帳號綁定', 'line-hub'); ?>
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
                            <div class="lh-fc-date"><?php printf(esc_html__('綁定於：%s', 'line-hub'), esc_html($linked_at)); ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lh-fc-actions">
                    <button type="button" class="lh-fc-btn lh-fc-btn-unbind" id="lhFcUnbindBtn"
                            data-rest-url="<?php echo esc_url($rest_url); ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>">
                        <?php esc_html_e('解除綁定', 'line-hub'); ?>
                    </button>
                </div>

                <div class="lh-fc-status" id="lhFcStatus"></div>

            <?php else : ?>
                <div class="lh-fc-unbound">
                    <svg viewBox="0 0 20 20" width="20" height="20" fill="currentColor" style="flex-shrink:0"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                    <?php esc_html_e('綁定 LINE 帳號即可接收訂單通知和出貨追蹤', 'line-hub'); ?>
                </div>

                <?php if (!empty($bind_url)) : ?>
                    <div class="lh-fc-actions">
                        <a href="<?php echo esc_url($bind_url); ?>" class="lh-fc-btn lh-fc-btn-bind">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M24 10.304C24 4.974 18.629.607 12 .607S0 4.974 0 10.304c0 4.8 4.27 8.834 10.035 9.602.391.084.922.258 1.058.592.12.3.079.77.038 1.08l-.164 1.02c-.045.3-.24 1.17 1.049.638 1.291-.532 6.916-4.07 9.436-6.97C23.176 14.393 24 12.458 24 10.304"/></svg>
                            <?php esc_html_e('綁定 LINE 帳號', 'line-hub'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <script>
        (function(){
            var btn = document.getElementById('lhFcUnbindBtn');
            if (!btn) return;

            btn.addEventListener('click', function(){
                if (!confirm('<?php echo esc_js(__('確定要解除 LINE 綁定嗎？', 'line-hub')); ?>')) return;

                var el = this;
                var status = document.getElementById('lhFcStatus');
                el.disabled = true;
                el.textContent = '<?php echo esc_js(__('處理中...', 'line-hub')); ?>';

                fetch(el.dataset.restUrl, {
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': el.dataset.nonce, 'Content-Type': 'application/json' },
                    credentials: 'same-origin'
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        status.className = 'lh-fc-status';
                        status.style.cssText = 'display:block;background:#dcfce7;color:#166534;';
                        status.textContent = data.message || '<?php echo esc_js(__('LINE 綁定已解除', 'line-hub')); ?>';
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        status.className = 'lh-fc-status';
                        status.style.cssText = 'display:block;background:#fef2f2;color:#991b1b;';
                        status.textContent = data.message || '<?php echo esc_js(__('解除綁定失敗', 'line-hub')); ?>';
                        el.disabled = false;
                        el.textContent = '<?php echo esc_js(__('解除綁定', 'line-hub')); ?>';
                    }
                })
                .catch(function(){
                    status.style.cssText = 'display:block;background:#fef2f2;color:#991b1b;';
                    status.textContent = '<?php echo esc_js(__('網路錯誤，請稍後再試', 'line-hub')); ?>';
                    el.disabled = false;
                    el.textContent = '<?php echo esc_js(__('解除綁定', 'line-hub')); ?>';
                });
            });
        })();
        </script>
        <?php
    }
}
