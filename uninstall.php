<?php
/**
 * 外掛移除時執行的清理腳本
 *
 * 當用戶從 WordPress 後台刪除外掛時，
 * 完整清除所有資料表、選項、transients 和 user meta。
 *
 * @package LineHub
 */

// 安全檢查：只能透過 WordPress 卸載流程觸發
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// 1. 載入 Database 類別並刪除 4 張資料表
require_once __DIR__ . '/includes/autoload.php';

// autoload 需要 LINE_HUB_PATH 常數
if (!defined('LINE_HUB_PATH')) {
    define('LINE_HUB_PATH', plugin_dir_path(__FILE__));
}

\LineHub\Database::drop_tables();

// 2. 刪除 wp_options 中所有 line_hub_* 選項
// drop_tables() 已刪除 line_hub_db_version 和 line_hub_version，但為保險仍補刪
delete_option('line_hub_version');
delete_option('line_hub_db_version');
delete_option('line_hub_rewrite_version');
delete_option('line_hub_api_logs');

// 3. 刪除所有 line_hub_* transients
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE %s
            OR option_name LIKE %s",
        '_transient_line_hub_%',
        '_transient_timeout_line_hub_%'
    )
);

// 4. 刪除所有 line_hub_* site transients
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE %s
            OR option_name LIKE %s",
        '_site_transient_line_hub_%',
        '_site_transient_timeout_line_hub_%'
    )
);

// 5. 刪除所有 line_hub_* user meta
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->usermeta}
         WHERE meta_key LIKE %s",
        'line_hub_%'
    )
);
