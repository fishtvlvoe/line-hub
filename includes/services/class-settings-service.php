<?php
/**
 * Settings Service
 *
 * 統一的設定管理服務，支援分組、加密、快取
 *
 * @package LineHub
 */

namespace LineHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsService {
    /**
     * 設定 Schema 定義
     *
     * @var array
     */
    private static $schema = [
        'general' => [
            'channel_id' => [
                'type' => 'string',
                'required' => true,
                'encrypted' => false,
                'default' => '',
                'description' => 'LINE Channel ID',
            ],
            'channel_secret' => [
                'type' => 'string',
                'required' => true,
                'encrypted' => true,
                'default' => '',
                'description' => 'LINE Channel Secret',
            ],
            'access_token' => [
                'type' => 'string',
                'required' => true,
                'encrypted' => true,
                'default' => '',
                'description' => 'LINE Channel Access Token',
            ],
            'liff_id' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '',
                'description' => 'LIFF App ID',
            ],
            // Task 3: 進階設定欄位
            'nsl_compat_mode' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => false,
                'description' => 'NSL 相容模式（同時從 wp_social_users 查詢）',
            ],
            'nsl_auto_migrate' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => false,
                'description' => '自動遷移 NSL 用戶到 LineHub',
            ],
            'login_button_text' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '用 LINE 帳號登入',
                'description' => '登入按鈕文字',
            ],
            'login_button_positions' => [
                'type' => 'array',
                'required' => false,
                'encrypted' => false,
                'default' => [],
                'description' => '登入按鈕顯示位置',
            ],
            'login_button_size' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => 'medium',
                'enum' => ['small', 'medium', 'large'],
                'description' => '登入按鈕大小',
            ],
            'require_email_verification' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => false,
                'description' => '強制 Email 驗證',
            ],
            'allowed_email_domains' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '',
                'description' => '允許的 Email 網域（逗號分隔）',
            ],
            'login_mode' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => 'auto',
                'enum' => ['auto', 'oauth', 'liff'],
                'description' => '登入模式（auto=自動/oauth=LINE OA/liff=LIFF）',
            ],
            'username_prefix' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => 'line',
                'description' => '新用戶註冊時用戶名前綴',
            ],
            'display_name_prefix' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => 'lineuser-',
                'description' => '新用戶顯示名稱前綴（用戶名無效時使用）',
            ],
            'default_roles' => [
                'type' => 'array',
                'required' => false,
                'encrypted' => false,
                'default' => ['subscriber'],
                'description' => '新用戶註冊後預設角色（可多選）',
            ],
            'auto_link_by_email' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => true,
                'description' => '自動依 Email 連結現有帳戶',
            ],
            'login_redirect_fixed' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => false,
                'description' => '啟用固定重定向 URL',
            ],
            'login_redirect_url' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '',
                'description' => '登入後固定重定向 URL',
            ],
        ],
        'login' => [
            'force_reauth' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => false,
                'description' => '強制重新授權（每次登入都顯示授權畫面）',
            ],
            'bot_prompt' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => 'normal',
                'enum' => ['normal', 'aggressive'],
                'description' => 'LINE Bot 提示行為',
            ],
            'initial_amr' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '',
                'enum' => ['', 'lineqr', 'lineautologin'],
                'description' => '初始登入方法',
            ],
            'switch_amr' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => true,
                'description' => '允許切換登入方法',
            ],
            'allow_auto_login' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => false,
                'description' => '允許自動登入',
            ],
        ],
        'notification' => [
            'order_created_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => true,
                'description' => '訂單建立通知',
            ],
            'order_created_template' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '您的訂單 {order_id} 已建立！總金額：{order_total} 元',
                'description' => '訂單建立訊息模板',
            ],
            'shipment_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => true,
                'description' => '出貨通知',
            ],
            'shipment_template' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '您的訂單 {order_id} 已出貨！物流單號：{tracking_number}',
                'description' => '出貨訊息模板',
            ],
        ],
        'integration' => [
            'fluentcart_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => true,
                'description' => 'FluentCart 整合',
            ],
            'buygo_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => true,
                'description' => 'BuyGo Plus One 整合',
            ],
            'woocommerce_enabled' => [
                'type' => 'boolean',
                'required' => false,
                'encrypted' => false,
                'default' => false,
                'description' => 'WooCommerce 整合',
            ],
            'api_key_hash' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '',
                'description' => 'API Key hash（不存明文）',
            ],
            'api_key_prefix' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '',
                'description' => 'API Key 前 8 碼（用於顯示）',
            ],
            'api_key_created_at' => [
                'type' => 'string',
                'required' => false,
                'encrypted' => false,
                'default' => '',
                'description' => 'API Key 建立時間',
            ],
        ],
    ];

    /**
     * 快取 TTL（1 小時）
     *
     * @var int
     */
    private static $cache_ttl = HOUR_IN_SECONDS;

    /**
     * 取得設定值
     *
     * @param string $group 設定群組
     * @param string $key 設定鍵（可選，不提供則返回整個群組）
     * @param mixed $default 預設值
     * @return mixed
     */
    public static function get($group, $key = null, $default = null) {
        // 檢查群組是否存在
        if (!isset(self::$schema[$group])) {
            return $default;
        }

        // 如果沒有指定 key，返回整個群組
        if ($key === null) {
            return self::get_group($group);
        }

        // 檢查 key 是否存在於 schema
        if (!isset(self::$schema[$group][$key])) {
            return $default;
        }

        // 嘗試從快取取得
        $cache_key = "line_hub_setting_{$group}_{$key}";
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // 從資料庫取得
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_settings';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT setting_value, encrypted FROM {$table} WHERE setting_group = %s AND setting_key = %s",
            $group,
            $key
        ));

        if (!$row) {
            // 使用 schema 中的預設值
            $value = self::$schema[$group][$key]['default'] ?? $default;
        } else {
            $value = $row->setting_value;

            // 如果是加密的，解密
            if ($row->encrypted) {
                $value = self::decrypt($value);
            }

            // 根據類型轉換
            $value = self::cast_value($value, self::$schema[$group][$key]['type']);
        }

        // 儲存到快取
        set_transient($cache_key, $value, self::$cache_ttl);

        return $value;
    }

    /**
     * 取得整個設定群組
     *
     * @param string $group 設定群組
     * @return array
     */
    public static function get_group($group) {
        if (!isset(self::$schema[$group])) {
            return [];
        }

        $result = [];

        foreach (self::$schema[$group] as $key => $config) {
            $result[$key] = self::get($group, $key);
        }

        return $result;
    }

    /**
     * 設定值
     *
     * @param string $group 設定群組
     * @param string $key 設定鍵
     * @param mixed $value 設定值
     * @return bool
     */
    public static function set($group, $key, $value) {
        // 檢查設定是否存在於 schema
        if (!isset(self::$schema[$group][$key])) {
            return false;
        }

        $config = self::$schema[$group][$key];

        // 驗證值
        if (!self::validate_value($value, $config)) {
            return false;
        }

        // 轉換類型
        $value = self::cast_value($value, $config['type']);

        // 序列化 array 類型（資料庫欄位為 longtext，需轉 JSON）
        if ($config['type'] === 'array') {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        // 如果需要加密
        $encrypted = $config['encrypted'] ?? false;
        $stored_value = $encrypted ? self::encrypt($value) : $value;

        // 儲存到資料庫
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_settings';

        $result = $wpdb->replace($table, [
            'setting_group' => $group,
            'setting_key' => $key,
            'setting_value' => $stored_value,
            'encrypted' => $encrypted ? 1 : 0,
        ]);

        if ($result) {
            // 清除快取
            self::clear_cache($group, $key);
            return true;
        }

        return false;
    }

    /**
     * 批次設定
     *
     * @param string $group 設定群組
     * @param array $settings 設定陣列 [key => value]
     * @return bool
     */
    public static function set_group($group, $settings) {
        if (!is_array($settings)) {
            return false;
        }

        foreach ($settings as $key => $value) {
            self::set($group, $key, $value);
        }

        return true;
    }

    /**
     * 加密值
     *
     * 使用 AES-256-CBC 加密
     *
     * @param string $value 原始值
     * @return string 加密後的值（Base64）
     */
    public static function encrypt($value) {
        if (empty($value)) {
            return '';
        }

        // 使用 WordPress NONCE_KEY 作為加密金鑰
        $key = defined('NONCE_KEY') ? NONCE_KEY : 'line-hub-default-key';

        // 產生隨機 IV
        $iv = openssl_random_pseudo_bytes(16);

        // 加密
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);

        if ($encrypted === false) {
            return '';
        }

        // IV + 加密內容，然後 Base64 編碼
        return base64_encode($iv . $encrypted);
    }

    /**
     * 解密值
     *
     * @param string $encrypted_value 加密的值（Base64）
     * @return string 解密後的值
     */
    public static function decrypt($encrypted_value) {
        if (empty($encrypted_value)) {
            return '';
        }

        // Base64 解碼
        $data = base64_decode($encrypted_value);

        if ($data === false) {
            return '';
        }

        // 使用 WordPress NONCE_KEY 作為加密金鑰
        $key = defined('NONCE_KEY') ? NONCE_KEY : 'line-hub-default-key';

        // 提取 IV（前 16 bytes）
        $iv = substr($data, 0, 16);

        // 提取加密內容
        $encrypted = substr($data, 16);

        // 解密
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * 驗證值
     *
     * @param mixed $value 值
     * @param array $config 設定配置
     * @return bool
     */
    private static function validate_value($value, $config) {
        // 檢查必填
        if (($config['required'] ?? false) && empty($value)) {
            return false;
        }

        // 檢查類型
        $type = $config['type'] ?? 'string';

        switch ($type) {
            case 'boolean':
                // boolean 可以接受 0, 1, true, false, 'true', 'false'
                break;

            case 'integer':
                if (!is_numeric($value)) {
                    return false;
                }
                break;

            case 'string':
                if (!is_string($value) && !is_numeric($value)) {
                    return false;
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    return false;
                }
                break;
        }

        // 檢查 enum
        if (isset($config['enum']) && !in_array($value, $config['enum'], true)) {
            return false;
        }

        return true;
    }

    /**
     * 轉換值類型
     *
     * @param mixed $value 值
     * @param string $type 目標類型
     * @return mixed
     */
    private static function cast_value($value, $type) {
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);

            case 'integer':
                return intval($value);

            case 'float':
                return floatval($value);

            case 'array':
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return is_array($decoded) ? $decoded : [];
                }
                return is_array($value) ? $value : [];

            case 'string':
            default:
                return strval($value);
        }
    }

    /**
     * 清除快取
     *
     * @param string $group 設定群組
     * @param string $key 設定鍵（可選）
     * @return void
     */
    public static function clear_cache($group, $key = null) {
        if ($key) {
            delete_transient("line_hub_setting_{$group}_{$key}");
        } else {
            // 清除整個群組的快取
            if (isset(self::$schema[$group])) {
                foreach (self::$schema[$group] as $k => $config) {
                    delete_transient("line_hub_setting_{$group}_{$k}");
                }
            }
        }
    }

    /**
     * 取得設定 Schema
     *
     * @param string $group 設定群組（可選）
     * @return array
     */
    public static function get_schema($group = null) {
        if ($group) {
            return self::$schema[$group] ?? [];
        }

        return self::$schema;
    }

    /**
     * 取得所有設定群組
     *
     * @return array
     */
    public static function get_all_groups() {
        return array_keys(self::$schema);
    }

    /**
     * 刪除設定
     *
     * @param string $group 設定群組
     * @param string $key 設定鍵
     * @return bool
     */
    public static function delete($group, $key) {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_settings';

        $result = $wpdb->delete($table, [
            'setting_group' => $group,
            'setting_key' => $key,
        ]);

        if ($result) {
            self::clear_cache($group, $key);
            return true;
        }

        return false;
    }
}
