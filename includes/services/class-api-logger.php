<?php
/**
 * API 呼叫記錄服務
 *
 * 記錄透過 API Key 認證的 REST API 呼叫，
 * 儲存在 wp_options（JSON 陣列），避免建立新資料表。
 *
 * @package LineHub\Services
 * @since 2.0.0
 */

namespace LineHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class ApiLogger {

    /** @var string wp_options key */
    private const OPTION_KEY = 'line_hub_api_logs';

    /** @var int 保留的最大記錄數 */
    private const MAX_LOGS = 100;

    /**
     * 記錄一次 API 呼叫
     *
     * @param string $method   HTTP 方法（GET/POST）
     * @param string $endpoint 端點路徑（例如 /messages/text）
     * @param string $status   結果（success/error）
     * @param string $message  補充說明（選填）
     */
    public static function log(
        string $method,
        string $endpoint,
        string $status,
        string $message = ''
    ): void {
        $logs = self::get_all();

        $entry = [
            'time'     => current_time('mysql'),
            'ip'       => self::get_client_ip(),
            'method'   => strtoupper($method),
            'endpoint' => $endpoint,
            'status'   => $status,
            'message'  => $message,
        ];

        // 新記錄放在最前面
        array_unshift($logs, $entry);

        // 保留最多 MAX_LOGS 筆
        if (count($logs) > self::MAX_LOGS) {
            $logs = array_slice($logs, 0, self::MAX_LOGS);
        }

        update_option(self::OPTION_KEY, wp_json_encode($logs), false);
    }

    /**
     * 取得最近 N 筆記錄
     *
     * @param int $limit 要取回的筆數（預設 20）
     * @return array
     */
    public static function get_recent(int $limit = 20): array {
        $logs = self::get_all();
        return array_slice($logs, 0, $limit);
    }

    /**
     * 取得所有記錄
     *
     * @return array
     */
    private static function get_all(): array {
        $raw = get_option(self::OPTION_KEY, '[]');

        if (!is_string($raw)) {
            return [];
        }

        $logs = json_decode($raw, true);

        if (!is_array($logs)) {
            return [];
        }

        return $logs;
    }

    /**
     * 清除所有記錄
     */
    public static function clear(): void {
        delete_option(self::OPTION_KEY);
    }

    /**
     * 取得客戶端 IP
     *
     * @return string
     */
    private static function get_client_ip(): string {
        // Cloudflare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
        }

        // 標準 proxy header（僅信任 Cloudflare 場景）
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return sanitize_text_field(trim($ips[0]));
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }

        return 'unknown';
    }
}
