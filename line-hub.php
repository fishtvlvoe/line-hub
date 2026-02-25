<?php
/**
 * Plugin Name: LINE Hub
 * Plugin URI: https://github.com/fishtvlvoe/line-hub
 * Description: LINE integration hub for WordPress — LINE Login, email capture, account registration, multi-scenario messaging, and unified Webhook management.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Author: BuyGo
 * Author URI: https://buygo.me
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: line-hub
 * Domain Path: /languages
 *
 * @package LineHub
 */

namespace LineHub;

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

// 定義常數
define('LINE_HUB_VERSION', '1.0.0');
define('LINE_HUB_FILE', __FILE__);
define('LINE_HUB_PATH', plugin_dir_path(__FILE__));
define('LINE_HUB_URL', plugin_dir_url(__FILE__));
define('LINE_HUB_BASENAME', plugin_basename(__FILE__));

// 自動載入器
require_once LINE_HUB_PATH . 'includes/autoload.php';

/**
 * 外掛啟用時執行
 */
register_activation_hook(__FILE__, function() {
    // 建立資料表
    \LineHub\Database::init();

    // 設定預設選項
    if (!get_option('line_hub_version')) {
        add_option('line_hub_version', LINE_HUB_VERSION);
    }

    // 清除重寫規則快取
    flush_rewrite_rules();
});

/**
 * 外掛停用時執行
 */
register_deactivation_hook(__FILE__, function() {
    // 清除排程任務
    wp_clear_scheduled_hook('line_hub_cleanup_webhooks');
    wp_clear_scheduled_hook('line_hub_cleanup_notifications');

    // 清除重寫規則快取
    flush_rewrite_rules();
});

/**
 * 外掛載入後初始化
 */
add_action('plugins_loaded', function() {
    // 載入語言檔案
    load_plugin_textdomain('line-hub', false, dirname(LINE_HUB_BASENAME) . '/languages');

    // 初始化外掛
    Plugin::instance()->init();
}, 20);

/**
 * 在外掛列表頁面加入「設定」連結
 */
add_filter('plugin_action_links_' . LINE_HUB_BASENAME, function($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=line-hub-settings'),
        __('Settings', 'line-hub')
    );
    array_unshift($links, $settings_link);
    return $links;
});

/**
 * 顯示管理員通知（如果有衝突）
 */
add_action('admin_notices', function() {
    // 檢查 PHP 版本
    if (version_compare(PHP_VERSION, '8.2', '<')) {
        echo '<div class="notice notice-error"><p>';
        /* translators: %s: current PHP version */
        echo '<strong>LINE Hub:</strong> ' . sprintf(esc_html__('Requires PHP 8.2 or higher. Current version: %s', 'line-hub'), PHP_VERSION);
        echo '</p></div>';
        return;
    }

    // 檢查 WordPress 版本
    global $wp_version;
    if (version_compare($wp_version, '6.5', '<')) {
        echo '<div class="notice notice-error"><p>';
        /* translators: %s: current WordPress version */
        echo '<strong>LINE Hub:</strong> ' . sprintf(esc_html__('Requires WordPress 6.5 or higher. Current version: %s', 'line-hub'), $wp_version);
        echo '</p></div>';
        return;
    }

    // 檢查與其他外掛的衝突
    $warnings = [];

    // 檢查 NSL
    if (class_exists('NextendSocialLogin')) {
        $warnings[] = __('Nextend Social Login (NSL) is active. Both plugins can coexist, but please ensure LINE OAuth settings do not conflict.', 'line-hub');
    }

    // 檢查 buygo-line-notify
    if (class_exists('BuygoLineNotify\\Plugin')) {
        $warnings[] = __('BuyGo LINE Notify is active. We recommend disabling it after completing data migration.', 'line-hub');
    }

    if (!empty($warnings)) {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>' . esc_html__('LINE Hub Compatibility Notice:', 'line-hub') . '</strong><br>';
        echo implode('<br>', array_map('esc_html', $warnings));
        echo '</p></div>';
    }
});
