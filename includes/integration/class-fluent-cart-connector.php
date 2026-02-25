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
            'bannerText' => esc_html__('Log in to track orders and receive shipping notifications.', 'line-hub'),
            'buttonText' => esc_html__('LINE Login', 'line-hub'),
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
        $data = self::getBindingData($user_id);
        if ($data === null) {
            return;
        }

        // 展開變數供模板使用
        $binding      = $data['binding'];
        $is_bound     = $data['is_bound'];
        $display_name = $data['display_name'];
        $picture_url  = $data['picture_url'];
        $linked_at    = $data['linked_at'];
        $bind_url     = $data['bind_url'];
        $nonce        = $data['nonce'];
        $rest_url     = $data['rest_url'];

        self::enqueueBindingAssets();
        include LINE_HUB_PATH . 'includes/templates/fluentcart-binding.php';
    }

    private static function getBindingData(int $user_id): ?array {
        $binding = UserService::getBinding($user_id);
        $liff_id = SettingsService::get('general', 'liff_id', '');
        $login_channel_id = SettingsService::get('general', 'login_channel_id', '');
        $has_login_configured = !empty($liff_id) || !empty($login_channel_id) || !empty(SettingsService::get('general', 'channel_id', ''));

        if (!$has_login_configured) {
            return null;
        }

        $bind_url = '';
        if (!empty($liff_id)) {
            $my_account_url = home_url('/my-account/');
            $bind_url = home_url('/line-hub/liff/?redirect=' . urlencode($my_account_url));
        }

        $is_bound = $binding && !empty($binding->line_uid);
        $picture_url = $is_bound && !empty($binding->picture_url) ? $binding->picture_url : '';
        if (empty($picture_url) && $is_bound) {
            $picture_url = get_user_meta($user_id, 'line_hub_avatar_url', true);
        }

        return [
            'binding'      => $binding,
            'is_bound'     => $is_bound,
            'display_name' => $is_bound && !empty($binding->display_name) ? $binding->display_name : '',
            'picture_url'  => $picture_url,
            'linked_at'    => $is_bound && !empty($binding->created_at) ? $binding->created_at : '',
            'bind_url'     => $bind_url,
            'nonce'        => wp_create_nonce('wp_rest'),
            'rest_url'     => rest_url('line-hub/v1/user/binding'),
        ];
    }

    private static function enqueueBindingAssets(): void {
        wp_enqueue_style('line-hub-fluentcart-binding', LINE_HUB_URL . 'assets/css/fluentcart-binding.css', [], LINE_HUB_VERSION);
        wp_enqueue_script('line-hub-fluentcart-binding', LINE_HUB_URL . 'assets/js/fluentcart-binding.js', [], LINE_HUB_VERSION, true);
        wp_localize_script('line-hub-fluentcart-binding', 'lineHubFcBinding', [
            'confirmUnbind' => __('Are you sure you want to unlink your LINE account?', 'line-hub'),
            'processing'    => __('Processing...', 'line-hub'),
            'unbindSuccess' => __('LINE account has been unlinked.', 'line-hub'),
            'unbindFail'    => __('Failed to unlink LINE account.', 'line-hub'),
            'unbindLabel'   => __('Unlink', 'line-hub'),
            'networkError'  => __('Network error. Please try again later.', 'line-hub'),
        ]);
    }
}
