<?php
/**
 * User Profile Manager
 *
 * 用戶 Profile 更新、批次查詢、顯示名稱/頭像
 *
 * @package LineHub
 */

namespace LineHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class UserProfileManager {

    /**
     * 更新已綁定用戶的 LINE 資料
     *
     * @param int   $user_id WordPress 用戶 ID
     * @param array $profile LINE 用戶資料
     * @return bool
     */
    public static function updateProfile(int $user_id, array $profile): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        $binding = UserService::getBinding($user_id);
        if (!$binding) {
            return false;
        }

        // 如果綁定來自 NSL 表，需確認 line_hub_users 中的記錄
        $binding_id = self::resolveBindingId($user_id, $binding);
        if (!$binding_id) {
            return false;
        }

        $update_data = ['updated_at' => current_time('mysql')];
        $format = ['%s'];

        if (isset($profile['displayName'])) {
            $update_data['display_name'] = sanitize_text_field($profile['displayName']);
            $format[] = '%s';
        }
        if (isset($profile['pictureUrl'])) {
            $update_data['picture_url'] = esc_url_raw($profile['pictureUrl']);
            $format[] = '%s';
        }
        if (isset($profile['email'])) {
            $update_data['email'] = sanitize_email($profile['email']);
            $format[] = '%s';
        }
        if (isset($profile['emailVerified'])) {
            $update_data['email_verified'] = $profile['emailVerified'] ? 1 : 0;
            $format[] = '%d';
        }

        $result = $wpdb->update($table_name, $update_data, ['id' => $binding_id], $format, ['%d']);

        if ($result !== false) {
            do_action('line_hub/user/profile_updated', $user_id, $profile);
            return true;
        }
        return false;
    }

    /**
     * 取得用戶的 LINE 顯示名稱
     */
    public static function getDisplayName(int $user_id): ?string {
        $binding = UserService::getBinding($user_id);
        if (!$binding) {
            return null;
        }
        if (!empty($binding->display_name)) {
            return $binding->display_name;
        }
        $user = get_user_by('ID', $user_id);
        return $user ? $user->display_name : null;
    }

    /**
     * 取得用戶的 LINE 頭像 URL
     */
    public static function getPictureUrl(int $user_id): ?string {
        $binding = UserService::getBinding($user_id);
        return ($binding && !empty($binding->picture_url)) ? $binding->picture_url : null;
    }

    /**
     * 批次取得多個用戶的綁定狀態
     */
    public static function getBindingsBatch(array $user_ids): array {
        if (empty($user_ids)) {
            return [];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        $user_ids = array_map('intval', $user_ids);
        $user_ids = array_filter($user_ids, function ($id) { return $id > 0; });
        if (empty($user_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, line_uid, display_name, picture_url, email,
                        email_verified, status, created_at, updated_at
                 FROM {$table_name}
                 WHERE user_id IN ({$placeholders}) AND status = 'active'",
                ...$user_ids
            )
        );

        $bindings = [];
        foreach ($user_ids as $uid) {
            $bindings[$uid] = null;
        }
        foreach ($results as $row) {
            $bindings[(int) $row->user_id] = $row;
        }
        return $bindings;
    }

    /**
     * 統計已綁定用戶數量
     */
    public static function countLinkedUsers(): int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE status = %s", 'active')
        );
    }

    // ── Private helpers ──────────────────────────────────

    /**
     * 解析綁定記錄 ID（處理 NSL fallback 的情況）
     */
    private static function resolveBindingId(int $user_id, object $binding): ?int {
        if (!isset($binding->id) || strval($binding->id) === strval($binding->user_id)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'line_hub_users';
            $hub_binding = $wpdb->get_row(
                $wpdb->prepare("SELECT id FROM {$table_name} WHERE user_id = %d LIMIT 1", $user_id)
            );
            return $hub_binding ? (int) $hub_binding->id : null;
        }
        return (int) $binding->id;
    }
}
