<?php
/**
 * User Service
 *
 * LINE 用戶綁定管理服務，提供查詢、建立、更新和刪除綁定關係
 * Profile 更新和批次查詢委派給 UserProfileManager
 *
 * @package LineHub
 */

namespace LineHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

class UserService {

    /**
     * NSL 表列名對映快取（每個請求只檢測一次）
     */
    private static ?array $nsl_columns = null;

    /**
     * 動態檢測 NSL 表的列名
     */
    private static function detectNslColumns(): ?array {
        if (self::$nsl_columns !== null) {
            return self::$nsl_columns;
        }

        global $wpdb;
        $nsl_table = $wpdb->prefix . 'social_users';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME, $nsl_table
            )
        );

        if (!$table_exists) {
            self::$nsl_columns = [];
            return [];
        }

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$nsl_table}");
        $column_set = array_flip($columns);

        self::$nsl_columns = [
            'wp_user_id' => isset($column_set['user_id']) ? 'user_id' : 'ID',
            'created'    => isset($column_set['register_date']) ? 'register_date' : (isset($column_set['date']) ? 'date' : 'register_date'),
            'updated'    => isset($column_set['login_date']) ? 'login_date' : (isset($column_set['date']) ? 'date' : 'login_date'),
        ];

        return self::$nsl_columns;
    }

    /**
     * 透過 LINE UID 查詢對應的 WordPress User ID
     */
    public static function getUserByLineUid(string $line_uid): ?int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$table_name} WHERE line_uid = %s AND status = 'active' LIMIT 1",
                $line_uid
            )
        );

        if ($user_id) {
            return (int) $user_id;
        }

        // Fallback: NSL wp_social_users
        $nsl_cols = self::detectNslColumns();
        if (empty($nsl_cols)) {
            return null;
        }

        $nsl_table = $wpdb->prefix . 'social_users';
        $nsl_user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `{$nsl_cols['wp_user_id']}` FROM {$nsl_table} WHERE identifier = %s AND type = 'line' LIMIT 1",
                $line_uid
            )
        );

        return $nsl_user_id ? (int) $nsl_user_id : null;
    }

    /**
     * 檢查用戶是否有直接的 LINE Hub 綁定（不含 NSL fallback）
     */
    public static function hasDirectBinding(int $user_id): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND status = 'active'",
                $user_id
            )
        );
    }

    /**
     * 透過 WordPress User ID 查詢 LINE 綁定（含 NSL fallback）
     */
    public static function getBinding(int $user_id): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        $binding = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, user_id, line_uid, display_name, picture_url, email,
                        email_verified, status, created_at, updated_at
                 FROM {$table_name}
                 WHERE user_id = %d AND status = 'active'
                 LIMIT 1",
                $user_id
            )
        );

        if ($binding) {
            return $binding;
        }

        // Fallback: NSL wp_social_users
        $nsl_cols = self::detectNslColumns();
        if (empty($nsl_cols)) {
            return null;
        }

        $nsl_table = $wpdb->prefix . 'social_users';
        $uid_col = $nsl_cols['wp_user_id'];
        $created_col = $nsl_cols['created'];
        $updated_col = $nsl_cols['updated'];

        $nsl_binding = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT social_users_id as id, `{$uid_col}` as user_id, identifier as line_uid,
                        NULL as display_name, NULL as picture_url,
                        NULL as email, 0 as email_verified, 'active' as status,
                        `{$created_col}` as created_at, `{$updated_col}` as updated_at
                 FROM {$nsl_table}
                 WHERE `{$uid_col}` = %d AND type = 'line'
                 LIMIT 1",
                $user_id
            )
        );

        return $nsl_binding ?: null;
    }

    /**
     * 建立或更新 LINE 綁定關係
     */
    public static function linkUser(int $user_id, string $line_uid, array $profile = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        $existing_user_id = self::getUserByLineUid($line_uid);
        if ($existing_user_id && $existing_user_id !== $user_id) {
            return new \WP_Error('line_uid_already_bound',
                __('此 LINE 帳號已綁定到其他 WordPress 帳號', 'line-hub'), ['status' => 409]);
        }

        $existing_binding = self::getBinding($user_id);
        if ($existing_binding && $existing_binding->line_uid !== $line_uid) {
            return new \WP_Error('user_already_bound',
                __('此 WordPress 帳號已綁定到其他 LINE 帳號，請先解除綁定', 'line-hub'), ['status' => 409]);
        }

        $data = [
            'user_id'        => $user_id,
            'line_uid'       => $line_uid,
            'display_name'   => isset($profile['displayName']) ? sanitize_text_field($profile['displayName']) : null,
            'picture_url'    => isset($profile['pictureUrl']) ? esc_url_raw($profile['pictureUrl']) : null,
            'email'          => isset($profile['email']) ? sanitize_email($profile['email']) : null,
            'email_verified' => !empty($profile['email']) ? 1 : 0,
            'status'         => 'active',
            'link_date'      => current_time('mysql'),
            'updated_at'     => current_time('mysql'),
        ];

        if ($existing_binding && isset($existing_binding->id)) {
            $result = $wpdb->update($table_name, $data, ['id' => $existing_binding->id],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'], ['%d']);
            if ($result === false) {
                return new \WP_Error('update_failed', __('更新綁定記錄失敗', 'line-hub'), ['status' => 500]);
            }
        } else {
            $data['register_date'] = current_time('mysql');
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table_name, $data,
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']);
            if ($result === false) {
                return new \WP_Error('insert_failed', __('建立綁定記錄失敗', 'line-hub'), ['status' => 500]);
            }
        }

        do_action('line_hub/user/linked', $user_id, $line_uid, $profile);
        return true;
    }

    /**
     * 解除 LINE 綁定（hard delete）
     */
    public static function unlinkUser(int $user_id): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        $binding = self::getBinding($user_id);
        if (!$binding) {
            return false;
        }

        do_action('line_hub/user/before_unlink', $user_id);

        $result = $wpdb->delete($table_name, ['user_id' => $user_id], ['%d']);

        if ($result !== false) {
            delete_user_meta($user_id, 'line_hub_avatar_url');
            delete_user_meta($user_id, 'line_hub_login_method');
            delete_user_meta($user_id, 'line_hub_is_friend');
            do_action('line_hub/user/unlinked', $user_id, $binding->line_uid ?? '');
            return true;
        }
        return false;
    }

    /**
     * 檢查用戶是否已綁定 LINE
     */
    public static function isLinked(int $user_id): bool {
        return self::getBinding($user_id) !== null;
    }

    /**
     * 取得用戶的 LINE UID
     */
    public static function getLineUid(int $user_id): ?string {
        $binding = self::getBinding($user_id);
        return $binding ? $binding->line_uid : null;
    }

    // ── 向後相容委派（呼叫者仍用 UserService） ──────

    public static function updateProfile(int $user_id, array $profile): bool {
        return UserProfileManager::updateProfile($user_id, $profile);
    }

    public static function getPictureUrl(int $user_id): ?string {
        return UserProfileManager::getPictureUrl($user_id);
    }
}
