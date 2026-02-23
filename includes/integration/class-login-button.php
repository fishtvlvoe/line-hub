<?php
/**
 * LINE 登入按鈕元件
 *
 * 提供可重用的 LINE 登入按鈕，支援多種顯示模式
 *
 * 使用方式：
 * 1. Shortcode:  [line_hub_login text="LINE 登入" size="large"]
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

        // defaults 從設定讀取
        $defaults = [
            'text'        => SettingsService::get('general', 'login_button_text', '用 LINE 帳號登入'),
            'size'        => SettingsService::get('general', 'login_button_size', 'medium'),
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
        $is_line_browser = self::isLineBrowser();

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
            'text'        => '',
            'size'        => '',
            'style'       => 'button',
            'banner_text' => '',
            'redirect'    => '',
            'class'       => '',
        ], $atts, 'line_hub_login');

        // 移除空值，讓 render() 的 defaults 生效
        $atts = array_filter($atts, fn($v) => $v !== '');

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
     * 取得登入 URL（根據 login_mode 設定決定）
     *
     * @param string $redirect 登入後重定向 URL
     * @return string 登入 URL
     */
    public static function getLoginUrl(string $redirect = ''): string {
        // 處理重定向 URL
        $redirect = self::resolveRedirect($redirect);

        $login_mode = SettingsService::get('general', 'login_mode', 'auto');
        $liff_id    = SettingsService::get('general', 'liff_id', '');

        // 根據 login_mode 決定
        if ($login_mode === 'liff' && !empty($liff_id)) {
            if (self::isLineBrowser()) {
                return home_url('/line-hub/liff/?' . http_build_query(['redirect' => $redirect]));
            }
            return self::buildLiffUrl($liff_id, $redirect);
        }

        if ($login_mode === 'oauth') {
            return self::buildOAuthUrl($redirect);
        }

        // auto: 偵測用戶環境 — LINE 瀏覽器直接跳到 LIFF 處理頁，外部瀏覽器用 OAuth
        if (!empty($liff_id) && self::isLineBrowser()) {
            // 直接跳到我們的 LIFF 頁面（在當前 WebView 內），避免 liff.line.me 開新的 overlay 視窗
            return home_url('/line-hub/liff/?' . http_build_query(['redirect' => $redirect]));
        }

        // 外部瀏覽器（Safari/Chrome 等）或沒有 LIFF ID → OAuth
        return self::buildOAuthUrl($redirect);
    }

    /**
     * 解析重定向 URL
     */
    private static function resolveRedirect(string $redirect): string {
        // 如果啟用固定重定向，使用設定值
        $fixed = SettingsService::get('general', 'login_redirect_fixed', false);
        if ($fixed) {
            $fixed_url = SettingsService::get('general', 'login_redirect_url', '');
            if (!empty($fixed_url)) {
                return $fixed_url;
            }
        }

        // 使用傳入值或當前頁面
        if (!empty($redirect)) {
            return $redirect;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    }

    /**
     * 建構 LIFF URL
     */
    private static function buildLiffUrl(string $liff_id, string $redirect): string {
        return "https://liff.line.me/{$liff_id}?redirect=" . urlencode($redirect);
    }

    /**
     * 建構 OAuth URL（帶 bot_prompt / initial_amr 參數）
     */
    private static function buildOAuthUrl(string $redirect): string {
        $params = ['redirect' => $redirect];

        $bot_prompt = SettingsService::get('login', 'bot_prompt', 'normal');
        if ($bot_prompt !== 'normal') {
            $params['bot_prompt'] = $bot_prompt;
        }

        $initial_amr = SettingsService::get('login', 'initial_amr', '');
        if (!empty($initial_amr)) {
            $params['initial_amr'] = $initial_amr;
        }

        return home_url('/line-hub/auth/?' . http_build_query($params));
    }

    /**
     * 偵測是否在 LINE 內建瀏覽器中
     *
     * LINE 內建瀏覽器的 User-Agent 包含 "Line/" 字串
     *
     * @return bool
     */
    private static function isLineBrowser(): bool {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
        return stripos($ua, 'Line/') !== false;
    }
}
