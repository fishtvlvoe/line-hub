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
        $login_channel_id = SettingsService::get('general', 'login_channel_id', '');
        $channel_id = SettingsService::get('general', 'channel_id', '');

        if (empty($liff_id) && empty($login_channel_id) && empty($channel_id)) {
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

        // 透過 JS 注入到產品頁（相容各種主題和模板）
        wp_enqueue_script(
            'line-hub-fluentcart-login-banner',
            LINE_HUB_URL . 'assets/js/fluentcart-login-banner.js',
            [],
            LINE_HUB_VERSION,
            true
        );
        wp_localize_script('line-hub-fluentcart-login-banner', 'lineHubLoginBanner', [
            'bannerText' => esc_html__('登入後可追蹤訂單、接收出貨通知', 'line-hub'),
            'buttonText' => esc_html__('LINE 登入', 'line-hub'),
            'loginUrl'   => esc_url($login_url),
        ]);
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
        $login_channel_id = SettingsService::get('general', 'login_channel_id', '');
        $has_login_configured = !empty($liff_id) || !empty($login_channel_id) || !empty(SettingsService::get('general', 'channel_id', ''));

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

        // 載入 CSS
        wp_enqueue_style(
            'line-hub-fluentcart-binding',
            LINE_HUB_URL . 'assets/css/fluentcart-binding.css',
            [],
            LINE_HUB_VERSION
        );

        // 載入解綁 JS + i18n
        wp_enqueue_script(
            'line-hub-fluentcart-binding',
            LINE_HUB_URL . 'assets/js/fluentcart-binding.js',
            [],
            LINE_HUB_VERSION,
            true
        );
        wp_localize_script('line-hub-fluentcart-binding', 'lineHubFcBinding', [
            'confirmUnbind' => __('確定要解除 LINE 綁定嗎？', 'line-hub'),
            'processing'    => __('處理中...', 'line-hub'),
            'unbindSuccess' => __('LINE 綁定已解除', 'line-hub'),
            'unbindFail'    => __('解除綁定失敗', 'line-hub'),
            'unbindLabel'   => __('解除綁定', 'line-hub'),
            'networkError'  => __('網路錯誤，請稍後再試', 'line-hub'),
        ]);

        // 載入 HTML 模板
        include LINE_HUB_PATH . 'includes/templates/fluentcart-binding.php';
    }
}
