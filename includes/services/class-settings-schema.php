<?php
/**
 * Settings Schema
 *
 * 設定 Schema 定義與查詢
 *
 * @package LineHub
 */

namespace LineHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsSchema {

    /**
     * 設定 Schema 定義
     */
    private static $schema = [
        'general' => [
            'channel_id' => [
                'type' => 'string', 'required' => true, 'encrypted' => false,
                'default' => '', 'description' => 'LINE Channel ID',
            ],
            'channel_secret' => [
                'type' => 'string', 'required' => true, 'encrypted' => true,
                'default' => '', 'description' => 'LINE Channel Secret',
            ],
            'access_token' => [
                'type' => 'string', 'required' => true, 'encrypted' => true,
                'default' => '', 'description' => 'LINE Channel Access Token',
            ],
            'login_channel_id' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '', 'description' => 'LINE Login Channel ID',
            ],
            'login_channel_secret' => [
                'type' => 'string', 'required' => false, 'encrypted' => true,
                'default' => '', 'description' => 'LINE Login Channel Secret',
            ],
            'liff_id' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '', 'description' => 'LIFF App ID',
            ],
            'nsl_compat_mode' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => false, 'description' => 'NSL 相容模式（同時從 wp_social_users 查詢）',
            ],
            'nsl_auto_migrate' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => false, 'description' => '自動遷移 NSL 用戶到 LineHub',
            ],
            'login_button_text' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '用 LINE 帳號登入', 'description' => '登入按鈕文字',
            ],
            'login_button_positions' => [
                'type' => 'array', 'required' => false, 'encrypted' => false,
                'default' => [], 'description' => '登入按鈕顯示位置',
            ],
            'login_button_size' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => 'medium', 'enum' => ['small', 'medium', 'large'],
                'description' => '登入按鈕大小',
            ],
            'require_email_verification' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => false, 'description' => '強制 Email 驗證',
            ],
            'allowed_email_domains' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '', 'description' => '允許的 Email 網域（逗號分隔）',
            ],
            'login_mode' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => 'auto', 'enum' => ['auto', 'oauth', 'liff'],
                'description' => '登入模式（auto=自動/oauth=LINE OA/liff=LIFF）',
            ],
            'username_prefix' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => 'line', 'description' => '新用戶註冊時用戶名前綴',
            ],
            'display_name_prefix' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => 'lineuser-', 'description' => '新用戶顯示名稱前綴（用戶名無效時使用）',
            ],
            'default_roles' => [
                'type' => 'array', 'required' => false, 'encrypted' => false,
                'default' => ['subscriber'], 'description' => '新用戶註冊後預設角色（可多選）',
            ],
            'auto_link_by_email' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => true, 'description' => '自動依 Email 連結現有帳戶',
            ],
            'login_redirect_fixed' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => false, 'description' => '啟用固定重定向 URL',
            ],
            'login_redirect_url' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '', 'description' => '登入後固定重定向 URL',
            ],
        ],
        'login' => [
            'force_reauth' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => false, 'description' => '強制重新授權（每次登入都顯示授權畫面）',
            ],
            'bot_prompt' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => 'normal', 'enum' => ['normal', 'aggressive'],
                'description' => 'LINE Bot 提示行為',
            ],
            'initial_amr' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '', 'enum' => ['', 'lineqr', 'lineautologin'],
                'description' => '初始登入方法',
            ],
            'switch_amr' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => true, 'description' => '允許切換登入方法',
            ],
            'allow_auto_login' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => false, 'description' => '允許自動登入',
            ],
        ],
        'notification' => [
            'order_created_enabled' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => true, 'description' => '訂單建立通知',
            ],
            'order_created_template' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '您的訂單 {order_id} 已建立！總金額：{order_total} 元',
                'description' => '訂單建立訊息模板',
            ],
            'shipment_enabled' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => true, 'description' => '出貨通知',
            ],
            'shipment_template' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '您的訂單 {order_id} 已出貨！物流單號：{tracking_number}',
                'description' => '出貨訊息模板',
            ],
        ],
        'integration' => [
            'fluentcart_enabled' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => true, 'description' => 'FluentCart 整合',
            ],
            'buygo_enabled' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => true, 'description' => 'BuyGo Plus One 整合',
            ],
            'woocommerce_enabled' => [
                'type' => 'boolean', 'required' => false, 'encrypted' => false,
                'default' => false, 'description' => 'WooCommerce 整合',
            ],
            'api_key_hash' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '', 'description' => 'API Key hash（不存明文）',
            ],
            'api_key_prefix' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '', 'description' => 'API Key 前 8 碼（用於顯示）',
            ],
            'api_key_created_at' => [
                'type' => 'string', 'required' => false, 'encrypted' => false,
                'default' => '', 'description' => 'API Key 建立時間',
            ],
        ],
    ];

    /**
     * 取得完整 Schema
     */
    public static function getAll(): array {
        return self::$schema;
    }

    /**
     * 取得群組 Schema
     */
    public static function getGroup(string $group): ?array {
        return self::$schema[$group] ?? null;
    }

    /**
     * 取得欄位 Schema
     */
    public static function getField(string $group, string $key): ?array {
        return self::$schema[$group][$key] ?? null;
    }

    /**
     * 取得所有群組名稱
     */
    public static function getAllGroups(): array {
        return array_keys(self::$schema);
    }

    /**
     * 檢查群組是否存在
     */
    public static function hasGroup(string $group): bool {
        return isset(self::$schema[$group]);
    }

    /**
     * 檢查欄位是否存在
     */
    public static function hasField(string $group, string $key): bool {
        return isset(self::$schema[$group][$key]);
    }
}
