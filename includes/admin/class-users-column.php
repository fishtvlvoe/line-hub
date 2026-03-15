<?php
/**
 * 用戶列表 LINE 綁定狀態欄
 *
 * 在 /wp-admin/users.php 新增「LINE」欄位
 * 查詢 line_hub_users / social_users / buygo_line_users 三張表
 *
 * @package LineHub\Admin
 */

namespace LineHub\Admin;

if (!defined('ABSPATH')) {
    exit;
}

class UsersColumn {

    /**
     * 資料表存在性快取
     * @var array<string, bool>
     */
    private static array $table_cache = [];

    /**
     * 初始化 hooks
     */
    public static function init(): void {
        add_filter('manage_users_columns', [self::class, 'add_column']);
        add_filter('manage_users_custom_column', [self::class, 'render_column'], 10, 3);
        add_action('admin_head-users.php', [self::class, 'inline_css']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);
    }

    /**
     * 載入管理後台腳本
     */
    public static function enqueue_scripts(string $hook): void {
        if ($hook !== 'users.php') {
            return;
        }
        $lh_ver = defined('LINE_HUB_VERSION') ? LINE_HUB_VERSION : '1.0.0';
        wp_enqueue_script(
            'line-hub-admin-users',
            plugins_url('assets/js/admin-users-column.js', dirname(dirname(__FILE__))),
            [],
            $lh_ver,
            true
        );
        wp_localize_script('line-hub-admin-users', 'lineHubUsersColumn', [
            'unlinkLabel' => __('Unlink', 'buygo-hub-for-line'),
        ]);
    }

    /**
     * 新增 LINE 欄位到用戶列表
     *
     * @param array $columns 現有欄位
     * @return array
     */
    public static function add_column(array $columns): array {
        $columns['line_binding'] = 'LINE';
        return $columns;
    }

    /**
     * 渲染 LINE 綁定狀態
     *
     * 查詢順序：LineHub → NSL → buygo_line_users
     *
     * @param string $output 現有輸出
     * @param string $column_name 欄位名稱
     * @param int $user_id 用戶 ID
     * @return string
     */
    public static function render_column(string $output, string $column_name, int $user_id): string {
        if ($column_name !== 'line_binding') {
            return $output;
        }

        $binding = self::get_binding_status($user_id);

        if ($binding === null) {
            return '<span class="line-hub-binding-none" title="' . esc_attr__('Not linked', 'buygo-hub-for-line') . '">—</span>';
        }

        $source_label = esc_html($binding['source']);
        $display_name = esc_html($binding['display_name']);
        $title = $display_name ? "{$display_name} ({$source_label})" : $source_label;

        // 只有 LINE Hub 來源才支援 API 解除綁定
        $unbind_btn = '';
        if ($binding['source'] === 'LINE Hub') {
            $rest_url  = esc_url(rest_url('line-hub/v1/user/' . $user_id . '/binding'));
            $nonce     = esc_attr(wp_create_nonce('wp_rest'));
            $confirm   = esc_js(__('Unlink this user\'s LINE account?', 'buygo-hub-for-line'));
            $unbind_btn = '<br><button type="button" class="button-link line-hub-admin-unbind"'
                        . ' data-rest-url="' . $rest_url . '"'
                        . ' data-nonce="' . $nonce . '"'
                        . ' data-confirm="' . $confirm . '">'
                        . esc_html__('Unlink', 'buygo-hub-for-line')
                        . '</button>';
        }

        return '<span class="line-hub-binding-linked" title="' . esc_attr($title) . '">&#x2714;</span>'
             . '<small class="line-hub-binding-source">' . $source_label . '</small>'
             . $unbind_btn;
    }

    /**
     * 查詢用戶的 LINE 綁定狀態
     *
     * @param int $user_id
     * @return array|null ['source' => string, 'display_name' => string] 或 null
     */
    private static function get_binding_status(int $user_id): ?array {
        global $wpdb;

        // 1. LineHub（最高優先）
        $hub_table = $wpdb->prefix . 'line_hub_users';
        if (self::table_exists($hub_table)) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT display_name FROM {$hub_table} WHERE user_id = %d AND status = 'active' LIMIT 1",
                $user_id
            ));
            if ($row) {
                return ['source' => 'LINE Hub', 'display_name' => $row->display_name ?? ''];
            }
        }

        // 2. NSL（Nextend Social Login）
        $nsl_table = $wpdb->prefix . 'social_users';
        if (self::table_exists($nsl_table)) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT identifier FROM {$nsl_table} WHERE ID = %d AND type = 'line' LIMIT 1",
                $user_id
            ));
            if ($row) {
                return ['source' => 'NSL', 'display_name' => ''];
            }
        }

        // 3. buygo_line_users（舊 LINE Notify，無 display_name 欄位）
        $bgo_table = $wpdb->prefix . 'buygo_line_users';
        if (self::table_exists($bgo_table)) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$bgo_table} WHERE user_id = %d LIMIT 1",
                $user_id
            ));
            if ($exists) {
                return ['source' => 'Legacy', 'display_name' => ''];
            }
        }

        return null;
    }

    /**
     * 檢查資料表是否存在
     *
     * @param string $table_name
     * @return bool
     */
    private static function table_exists(string $table_name): bool {
        if (isset(self::$table_cache[$table_name])) {
            return self::$table_cache[$table_name];
        }

        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        self::$table_cache[$table_name] = ($result === $table_name);

        return self::$table_cache[$table_name];
    }

    /**
     * 載入用戶列表 LINE 欄位樣式
     */
    public static function inline_css(): void {
        wp_enqueue_style(
            'line-hub-users-column',
            LINE_HUB_URL . 'assets/css/users-column.css',
            [],
            LINE_HUB_VERSION
        );
    }


}
