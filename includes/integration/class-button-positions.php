<?php
/**
 * 登入按鈕位置掛載器
 *
 * 讀取 login_button_positions 設定，用 WordPress hooks
 * 把 LINE 登入按鈕掛載到對應位置（僅未登入用戶）。
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
            add_action('fluentcart/checkout/before_customer_info', [self::class, 'render_on_checkout']);
        }

        if (in_array('fluent_community', $positions, true)) {
            add_action('fluent_community/auth_form_footer', [self::class, 'render_on_community']);
        }
    }

    /**
     * WordPress 登入頁面
     */
    public static function render_on_login_form(): void {
        if (is_user_logged_in()) {
            return;
        }
        echo self::get_button_html();
    }

    /**
     * FluentCart 結帳頁面
     */
    public static function render_on_checkout(): void {
        if (is_user_logged_in()) {
            return;
        }
        echo self::get_button_html();
    }

    /**
     * FluentCommunity 登入表單
     */
    public static function render_on_community(): void {
        if (is_user_logged_in()) {
            return;
        }
        echo self::get_button_html();
    }

    /**
     * 產生按鈕 HTML（委託 LoginButton::render）
     */
    private static function get_button_html(): string {
        return LoginButton::render();
    }
}
