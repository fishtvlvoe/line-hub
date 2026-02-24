<?php
/**
 * LIFF Handler
 *
 * 處理 LIFF 登入流程：路由分發、頁面渲染
 * 用戶驗證和建帳邏輯委派給 LiffUserProcessor
 *
 * @package LineHub
 */

namespace LineHub\Liff;

use LineHub\Services\SettingsService;
use LineHub\Services\UserService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LiffHandler 類別
 *
 * LIFF 登入流程：
 * 1. GET  /line-hub/liff/           → 渲染 LIFF 頁面（含 LIFF SDK）
 * 2. POST /line-hub/liff/           → 驗證 Access Token（委派 LiffUserProcessor）
 * 3. POST /line-hub/liff/ (email)   → 收到 Email 後建立帳號（委派 LiffUserProcessor）
 */
class LiffHandler {

    private LiffUserProcessor $processor;

    public function __construct() {
        $this->processor = new LiffUserProcessor($this);
    }

    /**
     * 處理 LIFF 請求
     */
    public function handleRequest(): void {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF handleRequest: method=' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . ' redirect=' . ($_GET['redirect'] ?? $_POST['redirect'] ?? 'NONE') . ' logged_in=' . (is_user_logged_in() ? 'yes' : 'no'));

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($_POST['liff_email_token'])) {
                $this->processor->handleEmailSubmit();
                return;
            }
            $this->processor->handleVerify();
            return;
        }

        $this->renderPage();
    }

    /**
     * 渲染 LIFF 登入頁面
     */
    private function renderPage(): void {
        $liff_id = SettingsService::get('general', 'liff_id', '');

        if (empty($liff_id)) {
            wp_die(
                esc_html__('LIFF 尚未設定，請聯繫管理員', 'line-hub'),
                esc_html__('設定錯誤', 'line-hub'),
                ['response' => 500]
            );
        }

        $redirect = isset($_GET['redirect']) ? sanitize_text_field(wp_unslash($_GET['redirect'])) : '';
        if (empty($redirect) && !empty($_COOKIE['liff_redirect'])) {
            $redirect = sanitize_text_field(wp_unslash($_COOKIE['liff_redirect']));
            setcookie('liff_redirect', '', time() - 3600, '/', '', is_ssl(), true);
        }
        $redirect = $this->resolveRedirectUrl($redirect);

        // 已登入且已綁定 → 直接重定向
        if (is_user_logged_in()) {
            $current_user_id = get_current_user_id();
            if (UserService::hasDirectBinding($current_user_id)) {
                wp_safe_redirect($redirect);
                exit;
            }
        }

        $nonce = wp_create_nonce('line_hub_liff_verify');
        include LINE_HUB_PATH . 'includes/liff/liff-template.php';
        exit;
    }

    /**
     * 渲染 Email 收集表單
     */
    public function renderEmailForm(
        string $token,
        string $display_name,
        string $picture_url,
        string $redirect,
        string $error = ''
    ): void {
        $nonce = wp_create_nonce('line_hub_liff_email');
        include LINE_HUB_PATH . 'includes/liff/liff-email-template.php';
        exit;
    }

    /**
     * 將 redirect 參數轉為完整的絕對 URL
     */
    public function resolveRedirectUrl(string $redirect): string {
        $redirect = trim($redirect);

        if (empty($redirect)) {
            return home_url('/');
        }

        if (strpos($redirect, 'http') !== 0) {
            $redirect = home_url($redirect);
        }

        return wp_validate_redirect($redirect, home_url('/'));
    }

    /**
     * 回應錯誤
     */
    public function respondError(string $message): void {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF error: ' . $message);

        wp_die(
            esc_html($message),
            esc_html__('登入失敗', 'line-hub'),
            ['response' => 400, 'back_link' => true]
        );
    }
}
