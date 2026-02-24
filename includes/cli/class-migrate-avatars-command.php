<?php
/**
 * WP-CLI 指令：將 NSL 頭像遷移到 LineHub
 *
 * 用法：wp line-hub migrate-avatars [--dry-run]
 *
 * @package LineHub\CLI
 */

namespace LineHub\CLI;

if (!defined('ABSPATH')) {
    exit;
}

class MigrateAvatarsCommand {

    /**
     * 將 NSL 存儲的頭像 URL 複製到 line_hub_avatar_url user meta
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : 只顯示會遷移的用戶，不實際寫入
     *
     * ## EXAMPLES
     *
     *     wp line-hub migrate-avatars --dry-run
     *     wp line-hub migrate-avatars
     *
     * @param array $args       位置參數
     * @param array $assoc_args 具名參數
     */
    public static function run(array $args, array $assoc_args): void {
        global $blog_id, $wpdb;

        $dry_run = isset($assoc_args['dry-run']);
        $prefix = $wpdb->get_blog_prefix($blog_id);
        $meta_key = $prefix . 'user_avatar';

        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT um.user_id, um.meta_value as attachment_id
             FROM {$wpdb->usermeta} um
             LEFT JOIN {$wpdb->usermeta} lh ON um.user_id = lh.user_id AND lh.meta_key = 'line_hub_avatar_url'
             WHERE um.meta_key = %s
             AND um.meta_value != ''
             AND (lh.meta_value IS NULL OR lh.meta_value = '')",
            $meta_key
        ));

        if (empty($users)) {
            \WP_CLI::success('沒有需要遷移的用戶。');
            return;
        }

        \WP_CLI::log(sprintf('找到 %d 個用戶需要遷移頭像。', count($users)));

        [$migrated, $skipped] = self::processUsers($users, $dry_run);

        $label = $dry_run ? '模擬完成' : '遷移完成';
        $verb = $dry_run ? '可遷移' : '已更新';
        \WP_CLI::success(sprintf('%s：%d 個用戶%s，%d 個跳過。', $label, $migrated, $verb, $skipped));
    }

    private static function processUsers(array $users, bool $dry_run): array {
        $migrated = 0;
        $skipped = 0;

        foreach ($users as $row) {
            $user_id = (int) $row->user_id;
            $attachment_id = (int) $row->attachment_id;

            if (!wp_attachment_is_image($attachment_id)) {
                \WP_CLI::log(sprintf('  跳過 用戶 #%d：attachment #%d 不是有效圖片', $user_id, $attachment_id));
                $skipped++;
                continue;
            }

            $image_src = wp_get_attachment_url($attachment_id);
            if (empty($image_src)) {
                \WP_CLI::log(sprintf('  跳過 用戶 #%d：attachment #%d 無法取得 URL', $user_id, $attachment_id));
                $skipped++;
                continue;
            }

            $user = get_user_by('ID', $user_id);
            $display = $user ? $user->display_name : "用戶 #{$user_id}";

            if ($dry_run) {
                \WP_CLI::log(sprintf('  [模擬] %s (#%d) → %s', $display, $user_id, $image_src));
            } else {
                update_user_meta($user_id, 'line_hub_avatar_url', esc_url_raw($image_src));
                \WP_CLI::log(sprintf('  ✓ %s (#%d) → %s', $display, $user_id, $image_src));
            }
            $migrated++;
        }

        return [$migrated, $skipped];
    }
}
