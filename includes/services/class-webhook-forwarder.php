<?php
/**
 * Webhook 轉發服務 — 原始 LINE Webhook → OpenClaw /line/webhook
 *
 * 掛在 line_hub/webhook/raw hook，將原始 LINE webhook body 連同簽章
 * 非阻塞轉發到 OpenClaw 的 LINE webhook 端點，讓 OpenClaw 的原生
 * LINE provider 處理事件並直接回覆用戶。
 *
 * @package LineHub\Services
 */

namespace LineHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookForwarder {

    /**
     * 初始化 webhook 轉發
     */
    public static function init(): void {
        add_action('line_hub/webhook/raw', [self::class, 'onWebhookRaw'], 10, 2);
    }

    /**
     * 轉發原始 LINE Webhook 到 OpenClaw
     *
     * @param string $raw_body  原始 LINE webhook JSON body
     * @param string $signature X-Line-Signature header 值
     */
    public static function onWebhookRaw(string $raw_body, string $signature): void {
        if (!SettingsService::get('integration', 'openclaw_enabled')) {
            return;
        }

        $base_url = SettingsService::get('integration', 'openclaw_webhook_url');
        if (empty($base_url)) {
            return;
        }

        // 從設定的 URL 取得 base（去掉 path），改打 /line/webhook
        $parsed = wp_parse_url($base_url);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? '127.0.0.1';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $target_url = "{$scheme}://{$host}{$port}/line/webhook";

        try {
            wp_remote_post($target_url, [
                'blocking'    => false,
                'timeout'     => 5,
                'redirection' => 0,
                'httpversion' => '1.1',
                'headers'     => [
                    'Content-Type'    => 'application/json',
                    'X-Line-Signature' => $signature,
                ],
                'body'        => $raw_body,
            ]);

            error_log("[LineHub] WebhookForwarder: 轉發原始 webhook 到 {$target_url}");
        } catch (\Throwable $e) {
            error_log('[LineHub] WebhookForwarder error: ' . $e->getMessage());
        }
    }
}
