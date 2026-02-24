<?php
/**
 * LIFF User Processor
 *
 * 處理 LIFF 登入的用戶流程分發（驗證、Email 收集、綁定）
 * API 呼叫和用戶建立委派給 LiffApiClient
 *
 * @package LineHub
 */

namespace LineHub\Liff;

use LineHub\Services\UserService;

if (!defined('ABSPATH')) {
    exit;
}

class LiffUserProcessor {

    private const EMAIL_TOKEN_EXPIRY = 600;

    private LiffHandler $handler;
    private LiffApiClient $api;

    public function __construct(LiffHandler $handler) {
        $this->handler = $handler;
        $this->api = new LiffApiClient();
    }

    /**
     * 驗證 LIFF Access Token 並處理登入/註冊
     */
    public function handleVerify(): void {
        if (!$this->verifyNonce('line_hub_liff_verify')) {
            $this->handler->respondError(__('安全驗證失敗，請重試', 'line-hub'));
            return;
        }

        $access_token = $this->getPostField('liff_access_token');
        if (empty($access_token)) {
            $this->handler->respondError(__('缺少 Access Token', 'line-hub'));
            return;
        }

        $redirect = $this->resolvePostRedirect();
        $profile = $this->api->verifyAndGetProfile($access_token);
        if (is_wp_error($profile)) {
            $this->handler->respondError($profile->get_error_message());
            return;
        }

        $line_uid = $profile['userId'] ?? '';
        if (empty($line_uid)) {
            $this->handler->respondError(__('無法取得 LINE 用戶 ID', 'line-hub'));
            return;
        }

        $display_name = $profile['displayName'] ?? '';
        $picture_url = $profile['pictureUrl'] ?? '';
        $is_friend = isset($_POST['liff_is_friend']) && $_POST['liff_is_friend'] === '1'; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF login: ' . $display_name . ' (' . substr($line_uid, 0, 8) . '...) friend=' . ($is_friend ? 'yes' : 'no'));

        // 已登入 → 綁定模式
        if (is_user_logged_in()) {
            $this->bindToLoggedInUser($line_uid, $display_name, $picture_url, $is_friend, $redirect);
            return;
        }

        // 既有用戶 → 登入
        $existing_user_id = UserService::getUserByLineUid($line_uid);
        if ($existing_user_id) {
            $this->api->migrateBindingIfNeeded($existing_user_id, $line_uid, $display_name, $picture_url);
            update_user_meta($existing_user_id, 'line_hub_is_friend', $is_friend ? '1' : '0');
            $this->api->loginAndRedirect($existing_user_id, $profile, $access_token, $redirect);
            return;
        }

        // 新用戶 → Email 表單
        $token = wp_generate_password(32, false);
        set_transient('line_hub_liff_' . $token, [
            'line_uid' => $line_uid, 'display_name' => $display_name,
            'picture_url' => $picture_url, 'redirect' => $redirect,
            'access_token' => $access_token, 'is_friend' => $is_friend,
        ], self::EMAIL_TOKEN_EXPIRY);

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF new user, showing email form for: ' . $display_name);
        $this->handler->renderEmailForm($token, $display_name, $picture_url, $redirect);
    }

    /**
     * 處理 Email 表單提交
     */
    public function handleEmailSubmit(): void {
        if (!$this->verifyNonce('line_hub_liff_email')) {
            $this->handler->respondError(__('安全驗證失敗，請重試', 'line-hub'));
            return;
        }

        $token = $this->getPostField('liff_email_token');
        if (empty($token)) {
            $this->handler->respondError(__('無效的請求', 'line-hub'));
            return;
        }

        $data = get_transient('line_hub_liff_' . $token);
        if (empty($data)) {
            $this->handler->respondError(__('連結已過期，請重新登入', 'line-hub'));
            return;
        }

        $line_uid     = $data['line_uid'];
        $display_name = $data['display_name'];
        $picture_url  = $data['picture_url'];
        $redirect     = $data['redirect'];
        $access_token = $data['access_token'];
        $is_friend    = !empty($data['is_friend']);

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $skip_email = !empty($_POST['skip_email']);

        // Email 驗證與帳號合併
        if (!$skip_email) {
            $result = $this->validateAndMergeEmail(
                $email, $token, $line_uid, $display_name, $picture_url,
                $redirect, $access_token, $is_friend
            );
            if ($result !== null) {
                return;
            }
        }

        $final_email = ($skip_email || empty($email))
            ? 'liff_' . substr(md5($line_uid), 0, 12) . '@line.local'
            : $email;

        delete_transient('line_hub_liff_' . $token);

        $user_id = $this->api->createNewUser($line_uid, $display_name, $picture_url, $final_email);
        if (is_wp_error($user_id)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF user creation failed: ' . $user_id->get_error_message());
            $this->handler->respondError($user_id->get_error_message());
            return;
        }

        update_user_meta($user_id, 'line_hub_is_friend', $is_friend ? '1' : '0');
        $profile = ['userId' => $line_uid, 'displayName' => $display_name, 'pictureUrl' => $picture_url];
        $this->api->loginAndRedirect($user_id, $profile, $access_token, $redirect);
    }

    // ── Private helpers ──────────────────────────────────

    private function bindToLoggedInUser(string $line_uid, string $display_name, string $picture_url, bool $is_friend, string $redirect): void {
        $user_id = get_current_user_id();
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('[LINE Hub] LIFF binding mode: linking LINE to user #' . $user_id);

        $link_result = UserService::linkUser($user_id, $line_uid, [
            'displayName' => $display_name, 'pictureUrl' => $picture_url,
        ]);

        if (is_wp_error($link_result)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF binding failed: ' . $link_result->get_error_message());
            $this->handler->respondError($link_result->get_error_message());
            return;
        }

        if (!empty($picture_url)) {
            update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($picture_url));
        }
        update_user_meta($user_id, 'line_hub_login_method', 'liff');
        update_user_meta($user_id, 'line_hub_is_friend', $is_friend ? '1' : '0');
        setcookie('line_hub_welcome', '1', time() + 30, '/', '', is_ssl(), false);

        wp_safe_redirect($redirect);
        exit;
    }

    private function validateAndMergeEmail(string $email, string $token, string $line_uid, string $display_name, string $picture_url, string $redirect, string $access_token, bool $is_friend): ?bool {
        if (empty($email)) {
            $this->handler->renderEmailForm($token, $display_name, $picture_url, $redirect, __('請輸入 Email 信箱', 'line-hub'));
            return true;
        }
        if (!is_email($email)) {
            $this->handler->renderEmailForm($token, $display_name, $picture_url, $redirect, __('Email 格式不正確', 'line-hub'));
            return true;
        }

        $existing_user_id = email_exists($email);
        if ($existing_user_id) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('[LINE Hub] LIFF merging: linking LINE to user #' . $existing_user_id . ' (' . $email . ')');
            delete_transient('line_hub_liff_' . $token);

            UserService::linkUser($existing_user_id, $line_uid, [
                'displayName' => $display_name, 'pictureUrl' => $picture_url,
            ]);
            update_user_meta($existing_user_id, 'line_hub_login_method', 'liff');
            update_user_meta($existing_user_id, 'line_hub_is_friend', $is_friend ? '1' : '0');

            $profile = ['userId' => $line_uid, 'displayName' => $display_name, 'pictureUrl' => $picture_url];
            $this->api->loginAndRedirect($existing_user_id, $profile, $access_token, $redirect);
            return true;
        }

        return null;
    }

    // ── Input helpers ────────────────────────────────────

    private function verifyNonce(string $action): bool {
        return isset($_POST['_wpnonce']) &&
            wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), $action);
    }

    private function getPostField(string $name): string {
        return isset($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : '';
    }

    private function resolvePostRedirect(): string {
        $redirect = $this->getPostField('redirect');
        if (empty($redirect) && !empty($_COOKIE['liff_redirect'])) {
            $redirect = sanitize_text_field(wp_unslash($_COOKIE['liff_redirect']));
        }
        if (!empty($_COOKIE['liff_redirect'])) {
            setcookie('liff_redirect', '', time() - 3600, '/', '', is_ssl(), true);
        }
        return $this->handler->resolveRedirectUrl($redirect);
    }
}
