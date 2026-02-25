<?php
/**
 * PHPUnit Bootstrap for Unit Tests
 *
 * 單元測試啟動檔 — 不需要 WordPress 環境，只測試純 PHP 邏輯
 * Mock 所有 WordPress 依賴函數和全域變數
 *
 * @package LineHub\Tests
 */

// Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// ── 常數定義 ──────────────────────────────────────────────

define('ABSPATH', dirname(__DIR__) . '/');
define('LINE_HUB_PATH', dirname(__DIR__) . '/');
define('LINE_HUB_VERSION', '1.0.0-test');
define('NONCE_KEY', 'test-nonce-key-for-encryption-32chars!!');
define('HOUR_IN_SECONDS', 3600);
define('DB_NAME', 'test_db');

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

// ── 可控 Mock 儲存 ────────────────────────────────────────

$GLOBALS['mock_transients'] = [];
$GLOBALS['mock_wpdb_results'] = [];
$GLOBALS['mock_http_responses'] = [];

// ── WordPress 函數 Mock ───────────────────────────────────

// Transient（可控：測試中可預設值）
if (!function_exists('get_transient')) {
    function get_transient($transient) {
        return $GLOBALS['mock_transients'][$transient] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        $GLOBALS['mock_transients'][$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        unset($GLOBALS['mock_transients'][$transient]);
        return true;
    }
}

// Hooks（no-op）
if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {}
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

// Error 檢查
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// 國際化
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

// 時間
if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        if ($type === 'mysql') {
            return gmdate('Y-m-d H:i:s');
        }
        return time();
    }
}

// 用戶 Meta
if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $meta_key, $meta_value = '') {
        return true;
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        return $single ? '' : [];
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value, $prev_value = '') {
        return true;
    }
}

// Sanitize / Escape
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars(strip_tags($str), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url, $protocols = null) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// JSON
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

// HTTP（可控：測試中可預設回應）
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        if (!empty($GLOBALS['mock_http_responses'])) {
            return array_shift($GLOBALS['mock_http_responses']);
        }
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        if (!empty($GLOBALS['mock_http_responses'])) {
            return array_shift($GLOBALS['mock_http_responses']);
        }
        return ['response' => ['code' => 200], 'body' => '{}'];
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return $response['response']['code'] ?? 200;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return $response['body'] ?? '';
    }
}

// ── WP_Error Mock Class ───────────────────────────────────

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data($code = '') {
            return $this->data;
        }
    }
}

// ── $wpdb Mock（可控佇列模式）──────────────────────────────

if (!isset($GLOBALS['wpdb'])) {
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $last_error = '';

        public function prepare($query, ...$args) {
            return vsprintf(str_replace(['%s', '%d'], ["'%s'", '%d'], $query), $args);
        }

        public function get_var($query) {
            if (!empty($GLOBALS['mock_wpdb_results'])) {
                return array_shift($GLOBALS['mock_wpdb_results']);
            }
            return null;
        }

        public function get_row($query, $output = OBJECT) {
            if (!empty($GLOBALS['mock_wpdb_results'])) {
                return array_shift($GLOBALS['mock_wpdb_results']);
            }
            return null;
        }

        public function get_col($query) {
            if (!empty($GLOBALS['mock_wpdb_results'])) {
                return array_shift($GLOBALS['mock_wpdb_results']);
            }
            return [];
        }

        public function get_results($query, $output = OBJECT) {
            if (!empty($GLOBALS['mock_wpdb_results'])) {
                return array_shift($GLOBALS['mock_wpdb_results']);
            }
            return [];
        }

        public function insert($table, $data, $format = null) {
            $this->insert_id = 1;
            return 1;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            return 1;
        }

        public function replace($table, $data, $format = null) {
            return 1;
        }

        public function query($query) {
            return true;
        }
    };
}

// ── 載入 LINE Hub 類別 ────────────────────────────────────

require_once LINE_HUB_PATH . 'includes/class-line-api-endpoints.php';
require_once LINE_HUB_PATH . 'includes/services/class-settings-schema.php';
require_once LINE_HUB_PATH . 'includes/services/class-settings-service.php';
require_once LINE_HUB_PATH . 'includes/services/class-user-profile-manager.php';
require_once LINE_HUB_PATH . 'includes/services/class-user-service.php';
require_once LINE_HUB_PATH . 'includes/messaging/class-messaging-service.php';
