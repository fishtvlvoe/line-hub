<?php
/**
 * 登入按鈕位置掛載器
 *
 * 讀取 login_button_positions 設定，用 WordPress hooks
 * 把 LINE 登入按鈕掛載到對應位置（僅未登入用戶）。
 *
 * Hook 名稱來源：
 * - WP 登入頁: login_form action（wp-login.php 原生）
 * - FluentCart: fluent_cart/before_checkout_form action（CheckoutRenderer.php，結帳頁表單外層）
 * - FluentCommunity: login_form_top filter（AuthHelper::nativeLoginForm 使用 WP 標準 filter）
 *
 * @package LineHub\Integration
 */

namespace LineHub\Integration;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class ButtonPositions {

    /**
     * 初始化：根據設定掛載按鈕到各位置
     */
    public static function init(): void {
        $positions = SettingsService::get('general', 'login_button_positions', []);

        if (empty($positions) || !is_array($positions)) {
            return;
        }

        if (in_array('wp_login', $positions, true)) {
            add_action('login_form', [self::class, 'render_on_login_form']);
        }

        if (in_array('fluentcart_checkout', $positions, true)) {
            // FluentCart 結帳頁（CheckoutRenderer.php 觸發，表單外層）
            add_action('fluent_cart/before_checkout_form', [self::class, 'render_on_checkout']);
        }

        if (in_array('fluent_community', $positions, true)) {
            // FluentCommunity 用 WP 標準 login_form_top filter（AuthHelper::nativeLoginForm）
            add_filter('login_form_top', [self::class, 'filter_login_form_top'], 10, 2);
        }
    }

    /**
     * WordPress 登入頁面（login_form action）
     */
    public static function render_on_login_form(): void {
        if (is_user_logged_in()) {
            return;
        }
        echo self::get_button_html();
    }

    /**
     * FluentCart 結帳頁面（fluent_cart/before_checkout_form action）
     */
    public static function render_on_checkout(): void {
        if (is_user_logged_in()) {
            return;
        }
        echo self::get_button_html();
    }

    /**
     * FluentCommunity 登入表單（login_form_top filter）
     * 也適用於其他使用 wp_login_form() 的頁面
     *
     * @param string $html 現有的 HTML 內容
     * @param array  $args login form 參數
     * @return string 附加登入按鈕後的 HTML
     */
    public static function filter_login_form_top(string $html, $args = []): string {
        if (is_user_logged_in()) {
            return $html;
        }
        return $html . self::get_button_html();
    }

    /**
     * 產生按鈕 HTML（委託 LoginButton::render）
     */
    private static function get_button_html(): string {
        return LoginButton::render();
    }
}
