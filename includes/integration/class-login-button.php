<?php
/**
 * LINE 登入按鈕元件
 *
 * 提供可重用的 LINE 登入按鈕，支援多種顯示模式
 *
 * 使用方式：
 * 1. Shortcode:  [line_hub_login text="LINE 登入" style="button"]
 * 2. PHP:        LoginButton::render(['style' => 'banner'])
 * 3. Hook:       do_action('line_hub/render_login_button')
 *
 * @package LineHub
 */

namespace LineHub\Integration;

use LineHub\Services\SettingsService;

if (!defined('ABSPATH')) {
    exit;
}

class LoginButton {

    /**
     * 初始化（註冊 shortcode 和 hooks）
     */
    public static function init(): void {
        add_shortcode('line_hub_login', [self::class, 'shortcodeHandler']);
        add_action('line_hub/render_login_button', [self::class, 'actionHandler'], 10, 1);
    }

    /**
     * 渲染登入按鈕
     *
     * @param array $args 參數
     * @return string HTML
     */
    public static function render(array $args = []): string {
        // 已登入用戶不顯示
        if (is_user_logged_in()) {
            return '';
        }

        $defaults = [
            'text'        => __('用 LINE 帳號登入', 'line-hub'),
            'style'       => 'button',
            'banner_text' => __('登入後可追蹤訂單、接收出貨通知', 'line-hub'),
            'redirect'    => '',
            'class'       => '',
        ];

        $args = wp_parse_args($args, $defaults);

        // 決定登入 URL
        $login_url = self::getLoginUrl($args['redirect']);
        if (empty($login_url)) {
            return '';
        }

        // 載入 CSS
        wp_enqueue_style(
            'line-hub-login-button',
            LINE_HUB_URL . 'assets/css/login-button.css',
            [],
            LINE_HUB_VERSION
        );

        // 渲染模板
        $button_text = $args['text'];
        $style = $args['style'];
        $banner_text = $args['banner_text'];
        $class = $args['class'];

        ob_start();
        include LINE_HUB_PATH . 'includes/templates/login-button.php';
        return ob_get_clean();
    }

    /**
     * Shortcode handler: [line_hub_login]
     *
     * @param array|string $atts Shortcode 屬性
     * @return string HTML
     */
    public static function shortcodeHandler($atts): string {
        $atts = shortcode_atts([
            'text'        => __('用 LINE 帳號登入', 'line-hub'),
            'style'       => 'button',
            'banner_text' => __('登入後可追蹤訂單、接收出貨通知', 'line-hub'),
            'redirect'    => '',
            'class'       => '',
        ], $atts, 'line_hub_login');

        return self::render($atts);
    }

    /**
     * Action handler: do_action('line_hub/render_login_button', $args)
     *
     * @param array $args 參數
     * @return void
     */
    public static function actionHandler(array $args = []): void {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo self::render($args);
    }

    /**
     * 取得登入 URL（自動判斷 LIFF 或 OAuth）
     *
     * @param string $redirect 登入後重定向 URL
     * @return string 登入 URL
     */
    public static function getLoginUrl(string $redirect = ''): string {
        if (empty($redirect)) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $redirect = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        }

        $liff_id = SettingsService::get('general', 'liff_id', '');

        // 優先使用 LIFF（LINE 內部瀏覽器支援更好）
        if (!empty($liff_id)) {
            $encoded_redirect = urlencode($redirect);
            return "https://liff.line.me/{$liff_id}?redirect={$encoded_redirect}";
        }

        // Fallback: OAuth 登入
        return home_url('/line-hub/auth/?redirect=' . urlencode($redirect));
    }
}
