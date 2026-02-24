<?php
/**
 * Settings Service
 *
 * 統一的設定管理服務，支援分組、加密、快取
 * Schema 定義委派給 SettingsSchema
 *
 * @package LineHub
 */

namespace LineHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsService {

    private static $cache_ttl = HOUR_IN_SECONDS;

    /**
     * 取得設定值
     */
    public static function get($group, $key = null, $default = null) {
        if (!SettingsSchema::hasGroup($group)) {
            return $default;
        }

        if ($key === null) {
            return self::get_group($group);
        }

        $field = SettingsSchema::getField($group, $key);
        if (!$field) {
            return $default;
        }

        $cache_key = "line_hub_setting_{$group}_{$key}";
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_settings';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT setting_value, encrypted FROM {$table} WHERE setting_group = %s AND setting_key = %s",
            $group, $key
        ));

        if (!$row) {
            $value = $field['default'] ?? $default;
        } else {
            $value = $row->encrypted ? self::decrypt($row->setting_value) : $row->setting_value;
            $value = self::cast_value($value, $field['type']);
        }

        set_transient($cache_key, $value, self::$cache_ttl);
        return $value;
    }

    /**
     * 取得整個設定群組
     */
    public static function get_group($group) {
        $group_schema = SettingsSchema::getGroup($group);
        if (!$group_schema) {
            return [];
        }

        $result = [];
        foreach ($group_schema as $key => $config) {
            $result[$key] = self::get($group, $key);
        }
        return $result;
    }

    /**
     * 設定值
     */
    public static function set($group, $key, $value) {
        $config = SettingsSchema::getField($group, $key);
        if (!$config) {
            return false;
        }

        if (!self::validate_value($value, $config)) {
            return false;
        }

        $value = self::cast_value($value, $config['type']);

        if ($config['type'] === 'array') {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $encrypted = $config['encrypted'] ?? false;
        $stored_value = $encrypted ? self::encrypt($value) : $value;

        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_settings';
        $result = $wpdb->replace($table, [
            'setting_group' => $group,
            'setting_key'   => $key,
            'setting_value' => $stored_value,
            'encrypted'     => $encrypted ? 1 : 0,
        ]);

        if ($result) {
            self::clear_cache($group, $key);
            return true;
        }
        return false;
    }

    /**
     * 批次設定
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
     * 加密值（AES-256-CBC）
     */
    public static function encrypt($value) {
        if (empty($value)) {
            return '';
        }
        $key = defined('NONCE_KEY') ? NONCE_KEY : 'line-hub-default-key';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
        return $encrypted !== false ? base64_encode($iv . $encrypted) : '';
    }

    /**
     * 解密值
     */
    public static function decrypt($encrypted_value) {
        if (empty($encrypted_value)) {
            return '';
        }
        $data = base64_decode($encrypted_value);
        if ($data === false) {
            return '';
        }
        $key = defined('NONCE_KEY') ? NONCE_KEY : 'line-hub-default-key';
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * 驗證值
     */
    private static function validate_value($value, $config) {
        if (($config['required'] ?? false) && empty($value)) {
            return false;
        }

        $type = $config['type'] ?? 'string';
        switch ($type) {
            case 'boolean':
                break;
            case 'integer':
                if (!is_numeric($value)) return false;
                break;
            case 'string':
                if (!is_string($value) && !is_numeric($value)) return false;
                break;
            case 'array':
                if (!is_array($value)) return false;
                break;
        }

        if (isset($config['enum']) && !in_array($value, $config['enum'], true)) {
            return false;
        }
        return true;
    }

    /**
     * 轉換值類型
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
     */
    public static function clear_cache($group, $key = null) {
        if ($key) {
            delete_transient("line_hub_setting_{$group}_{$key}");
        } else {
            $group_schema = SettingsSchema::getGroup($group);
            if ($group_schema) {
                foreach ($group_schema as $k => $config) {
                    delete_transient("line_hub_setting_{$group}_{$k}");
                }
            }
        }
    }

    /**
     * 取得設定 Schema（委派到 SettingsSchema）
     */
    public static function get_schema($group = null) {
        return $group ? (SettingsSchema::getGroup($group) ?? []) : SettingsSchema::getAll();
    }

    /**
     * 取得所有設定群組（委派到 SettingsSchema）
     */
    public static function get_all_groups() {
        return SettingsSchema::getAllGroups();
    }

    /**
     * 刪除設定
     */
    public static function delete($group, $key) {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_settings';
        $result = $wpdb->delete($table, [
            'setting_group' => $group,
            'setting_key'   => $key,
        ]);
        if ($result) {
            self::clear_cache($group, $key);
            return true;
        }
        return false;
    }
}
