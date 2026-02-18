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
     * 初始化 hooks
     */
    public static function init(): void {
        add_filter('manage_users_columns', [self::class, 'add_column']);
        add_filter('manage_users_custom_column', [self::class, 'render_column'], 10, 3);
        add_action('admin_head-users.php', [self::class, 'inline_css']);
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
            return '<span class="line-hub-binding-none" title="未綁定">—</span>';
        }

        $source_label = esc_html($binding['source']);
        $display_name = esc_html($binding['display_name']);
        $title = $display_name ? "{$display_name} ({$source_label})" : $source_label;

        return '<span class="line-hub-binding-linked" title="' . esc_attr($title) . '">&#x2714;</span>'
             . '<small class="line-hub-binding-source">' . $source_label . '</small>';
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

        // 3. buygo_line_users（舊 LINE Notify）
        $bgo_table = $wpdb->prefix . 'buygo_line_users';
        if (self::table_exists($bgo_table)) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT display_name FROM {$bgo_table} WHERE user_id = %d LIMIT 1",
                $user_id
            ));
            if ($row) {
                return ['source' => 'Legacy', 'display_name' => $row->display_name ?? ''];
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
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        return $result === $table_name;
    }

    /**
     * 內嵌最小 CSS
     */
    public static function inline_css(): void {
        echo '<style>
            .column-line_binding { width: 60px; text-align: center; }
            .line-hub-binding-linked { color: #06C755; font-size: 16px; }
            .line-hub-binding-none { color: #ccc; }
            .line-hub-binding-source { display: block; color: #888; font-size: 11px; }
        </style>';
    }
}
