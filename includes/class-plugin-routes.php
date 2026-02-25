<?php
/**
 * Plugin Routes
 *
 * Auth 路由、LIFF 路由的註冊和處理
 *
 * @package LineHub
 */

namespace LineHub;

if (!defined('ABSPATH')) {
    exit;
}

class PluginRoutes {

    /**
     * 註冊所有路由相關 hooks
     */
    public function register(): void {
        add_action('init', [$this, 'register_auth_routes'], 15);
        add_action('template_redirect', [$this, 'handle_auth_requests'], 10);
        add_action('init', [$this, 'register_liff_routes'], 15);
        add_action('parse_request', [$this, 'intercept_liff_requests'], 1);
        add_action('template_redirect', [$this, 'handle_liff_requests'], 10);
        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 99);
    }

    /**
     * 註冊 OAuth 認證路由
     */
    public function register_auth_routes(): void {
        add_rewrite_rule('^line-hub/auth/?$', 'index.php?line_hub_auth=1', 'top');
        add_rewrite_rule('^line-hub/auth/callback/?$', 'index.php?line_hub_auth=callback', 'top');
        add_rewrite_rule('^line-hub/auth/email-submit/?$', 'index.php?line_hub_auth=email-submit', 'top');

        add_filter('query_vars', function ($vars) {
            $vars[] = 'line_hub_auth';
            return $vars;
        });
    }

    /**
     * 處理 OAuth 認證請求
     */
    public function handle_auth_requests(): void {
        $auth_action = get_query_var('line_hub_auth');
        if (empty($auth_action)) {
            return;
        }

        if ($auth_action === 'email-submit') {
            $login_service = new Services\LoginService();
            $login_service->handleEmailSubmit();
            exit;
        }

        $callback = new Auth\AuthCallback();
        $callback->handleRequest();
        exit;
    }

    /**
     * 註冊 LIFF 路由
     */
    public function register_liff_routes(): void {
        add_rewrite_rule('^line-hub/liff/?$', 'index.php?line_hub_liff=1', 'top');

        add_filter('query_vars', function ($vars) {
            $vars[] = 'line_hub_liff';
            return $vars;
        });
    }

    /**
     * 在 parse_request 階段攔截 LIFF 請求（不依賴 rewrite rules）
     */
    public function intercept_liff_requests(\WP $wp): void {
        $request = trim($wp->request, '/');
        if ($request !== 'line-hub/liff') {
            return;
        }

        $wp->query_vars['line_hub_liff'] = '1';
        $handler = new Liff\LiffHandler();
        $handler->handleRequest();
        exit;
    }

    /**
     * 處理 LIFF 請求（rewrite rules 備用路徑）
     */
    public function handle_liff_requests(): void {
        $liff_action = get_query_var('line_hub_liff');
        if (empty($liff_action)) {
            return;
        }

        $handler = new Liff\LiffHandler();
        $handler->handleRequest();
        exit;
    }

    /**
     * 自動刷新 rewrite rules（版本更新時）
     */
    public function maybe_flush_rewrite_rules(): void {
        $stored_version = get_option('line_hub_rewrite_version', '');
        if ($stored_version !== LINE_HUB_VERSION) {
            flush_rewrite_rules();
            update_option('line_hub_rewrite_version', LINE_HUB_VERSION, true);
        }
    }
}
