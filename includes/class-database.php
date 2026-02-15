<?php
/**
 * 資料庫管理類別
 *
 * @package LineHub
 */

namespace LineHub;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database 類別
 *
 * 管理 LINE Hub 資料表的建立與版本控制
 */
class Database {
    /**
     * 資料庫版本
     */
    const DB_VERSION = '1.0.2';

    /**
     * 初始化資料庫
     *
     * 檢查資料庫版本，如果需要則建立或升級資料表
     */
    public static function init(): void {
        $current_version = get_option('line_hub_db_version', '0.0.0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_users_table();
            self::create_webhooks_table();
            self::create_settings_table();
            self::create_notifications_table();

            update_option('line_hub_db_version', self::DB_VERSION);
        }
    }

    /**
     * 建立用戶綁定表
     */
    private static function create_users_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'line_hub_users';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            line_uid varchar(255) NOT NULL,
            display_name varchar(255) DEFAULT NULL,
            picture_url varchar(500) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            email_verified tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'active',
            register_date datetime DEFAULT NULL,
            link_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY line_uid (line_uid),
            UNIQUE KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 建立 Webhook 記錄表
     */
    private static function create_webhooks_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'line_hub_webhooks';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            webhook_event_id varchar(100) DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            line_uid varchar(255) DEFAULT NULL,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            payload longtext DEFAULT NULL,
            processed tinyint(1) DEFAULT 0,
            error_message text DEFAULT NULL,
            received_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY line_uid (line_uid),
            KEY user_id (user_id),
            UNIQUE KEY webhook_event_id (webhook_event_id),
            KEY received_at (received_at),
            KEY processed (processed)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 建立進階設定表
     */
    private static function create_settings_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'line_hub_settings';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_group varchar(50) NOT NULL,
            setting_key varchar(100) NOT NULL,
            setting_value longtext NOT NULL,
            encrypted tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_group_key (setting_group, setting_key),
            KEY setting_group (setting_group)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 建立訊息發送記錄表
     */
    private static function create_notifications_table(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'line_hub_notifications';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            line_uid varchar(255) NOT NULL,
            notification_type varchar(50) NOT NULL,
            message_type varchar(20) DEFAULT 'text',
            message_preview text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            error_code varchar(50) DEFAULT NULL,
            error_message text DEFAULT NULL,
            related_id bigint(20) DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY line_uid (line_uid),
            KEY notification_type (notification_type),
            KEY status (status),
            KEY sent_at (sent_at),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 刪除資料表（外掛移除時使用）
     */
    public static function drop_tables(): void {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'line_hub_users',
            $wpdb->prefix . 'line_hub_webhooks',
            $wpdb->prefix . 'line_hub_settings',
            $wpdb->prefix . 'line_hub_notifications',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option('line_hub_db_version');
        delete_option('line_hub_version');
    }
}
