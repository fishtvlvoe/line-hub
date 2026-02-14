<?php
/**
 * User Service
 *
 * LINE 用戶綁定管理服務，提供查詢、建立、更新和刪除綁定關係
 *
 * @package LineHub
 */

namespace LineHub\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * UserService 類別
 *
 * 靜態類別，提供 LINE 用戶綁定的核心邏輯
 */
class UserService {

    /**
     * 透過 LINE UID 查詢對應的 WordPress User ID
     *
     * 包含 NSL fallback：先查 wp_line_hub_users，找不到再查 wp_social_users
     *
     * @param string $line_uid LINE 用戶唯一識別碼
     * @return int|null 找到返回 WordPress User ID，找不到返回 null
     */
    public static function getUserByLineUid(string $line_uid): ?int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        // 1. 先查詢 LINE Hub 用戶表
        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$table_name} WHERE line_uid = %s AND status = 'active' LIMIT 1",
                $line_uid
            )
        );

        if ($user_id) {
            return (int) $user_id;
        }

        // 2. Fallback: 查詢 NSL (Nextend Social Login) 的 wp_social_users 表
        $nsl_table = $wpdb->prefix . 'social_users';

        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $nsl_table
            )
        );

        if (!$table_exists) {
            return null;
        }

        $nsl_user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$nsl_table} WHERE identifier = %s AND type = 'line' LIMIT 1",
                $line_uid
            )
        );

        return $nsl_user_id ? (int) $nsl_user_id : null;
    }

    /**
     * 檢查用戶是否有直接的 LINE Hub 綁定（不含 NSL fallback）
     *
     * @param int $user_id WordPress 用戶 ID
     * @return bool
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
     * 透過 WordPress User ID 查詢 LINE 綁定狀態
     *
     * 包含 NSL (Nextend Social Login) fallback：
     * 如果 wp_line_hub_users 找不到，會嘗試查詢 wp_social_users 表
     *
     * @param int $user_id WordPress 用戶 ID
     * @return object|null 找到返回綁定記錄物件，找不到返回 null
     */
    public static function getBinding(int $user_id): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        // 先查詢 LINE Hub 用戶表
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

        // Fallback: 查詢 NSL (Nextend Social Login) 的 wp_social_users 表
        $nsl_table = $wpdb->prefix . 'social_users';

        // 先檢查 NSL 表是否存在
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $nsl_table
            )
        );

        if (!$table_exists) {
            return null;
        }

        // 查詢 NSL 表
        $nsl_binding = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID as id, user_id, identifier as line_uid,
                        NULL as display_name, NULL as picture_url,
                        NULL as email, 0 as email_verified, 'active' as status,
                        date as created_at, date as updated_at
                 FROM {$nsl_table}
                 WHERE user_id = %d AND type = 'line'
                 LIMIT 1",
                $user_id
            )
        );

        return $nsl_binding ?: null;
    }

    /**
     * 建立或更新 LINE 綁定關係
     *
     * @param int    $user_id  WordPress 用戶 ID
     * @param string $line_uid LINE 用戶唯一識別碼
     * @param array  $profile  LINE 用戶資料，可包含 displayName, pictureUrl, email
     * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
     */
    public static function linkUser(int $user_id, string $line_uid, array $profile = []) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        // 檢查 LINE UID 是否已被其他帳號綁定
        $existing_user_id = self::getUserByLineUid($line_uid);
        if ($existing_user_id && $existing_user_id !== $user_id) {
            return new \WP_Error(
                'line_uid_already_bound',
                __('此 LINE 帳號已綁定到其他 WordPress 帳號', 'line-hub'),
                ['status' => 409]
            );
        }

        // 檢查用戶是否已有綁定且 LINE UID 不同
        $existing_binding = self::getBinding($user_id);
        if ($existing_binding && $existing_binding->line_uid !== $line_uid) {
            return new \WP_Error(
                'user_already_bound',
                __('此 WordPress 帳號已綁定到其他 LINE 帳號，請先解除綁定', 'line-hub'),
                ['status' => 409]
            );
        }

        // 準備資料
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

        // 判斷是更新還是新增
        if ($existing_binding && isset($existing_binding->id)) {
            // 更新現有記錄
            $result = $wpdb->update(
                $table_name,
                $data,
                ['id' => $existing_binding->id],
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'],
                ['%d']
            );

            if ($result === false) {
                return new \WP_Error(
                    'update_failed',
                    __('更新綁定記錄失敗', 'line-hub'),
                    ['status' => 500]
                );
            }
        } else {
            // 新增記錄
            $data['register_date'] = current_time('mysql');
            $data['created_at'] = current_time('mysql');

            $result = $wpdb->insert(
                $table_name,
                $data,
                ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
            );

            if ($result === false) {
                return new \WP_Error(
                    'insert_failed',
                    __('建立綁定記錄失敗', 'line-hub'),
                    ['status' => 500]
                );
            }
        }

        /**
         * 綁定成功後觸發
         *
         * @param int    $user_id  WordPress 用戶 ID
         * @param string $line_uid LINE 用戶唯一識別碼
         * @param array  $profile  LINE 用戶資料
         */
        do_action('line_hub/user/linked', $user_id, $line_uid, $profile);

        return true;
    }

    /**
     * 解除 LINE 綁定（hard delete）
     *
     * @param int $user_id WordPress 用戶 ID
     * @return bool 成功返回 true，失敗返回 false
     */
    public static function unlinkUser(int $user_id): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        // 取得綁定資訊供 action hook 使用
        $binding = self::getBinding($user_id);

        if (!$binding) {
            return false;
        }

        /**
         * 解除綁定前觸發
         *
         * @param int $user_id WordPress 用戶 ID
         */
        do_action('line_hub/user/before_unlink', $user_id);

        // 執行刪除
        $result = $wpdb->delete(
            $table_name,
            ['user_id' => $user_id],
            ['%d']
        );

        if ($result !== false) {
            // 清除 LINE 相關 meta
            delete_user_meta($user_id, 'line_hub_avatar_url');
            delete_user_meta($user_id, 'line_hub_login_method');
            delete_user_meta($user_id, 'line_hub_is_friend');

            /**
             * 解除綁定後觸發
             *
             * @param int    $user_id  WordPress 用戶 ID
             * @param string $line_uid 被解除綁定的 LINE UID
             */
            do_action('line_hub/user/unlinked', $user_id, $binding->line_uid ?? '');

            return true;
        }

        return false;
    }

    /**
     * 更新已綁定用戶的 LINE 資料
     *
     * 用於 Phase 3 OAuth 登入時同步用戶資料
     *
     * @param int   $user_id WordPress 用戶 ID
     * @param array $profile LINE 用戶資料，可包含 displayName, pictureUrl, email, emailVerified
     * @return bool 成功返回 true，無綁定或失敗返回 false
     */
    public static function updateProfile(int $user_id, array $profile): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        // 檢查用戶是否有綁定
        $binding = self::getBinding($user_id);
        if (!$binding) {
            return false;
        }

        // 如果綁定來自 NSL 表，無法更新
        if (!isset($binding->id) || strval($binding->id) === strval($binding->user_id)) {
            // NSL fallback 的 id 是 wp_social_users 的 ID，不是 line_hub_users 的
            // 檢查是否真的存在於 line_hub_users
            $hub_binding = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE user_id = %d LIMIT 1",
                    $user_id
                )
            );
            if (!$hub_binding) {
                return false;
            }
            $binding_id = $hub_binding->id;
        } else {
            $binding_id = $binding->id;
        }

        // 準備更新資料
        $update_data = [
            'updated_at' => current_time('mysql'),
        ];

        $format = ['%s'];

        // 只更新有提供的欄位
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

        // 執行更新
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $binding_id],
            $format,
            ['%d']
        );

        if ($result !== false) {
            /**
             * 用戶資料更新後觸發
             *
             * @param int   $user_id WordPress 用戶 ID
             * @param array $profile 更新的資料
             */
            do_action('line_hub/user/profile_updated', $user_id, $profile);

            return true;
        }

        return false;
    }

    /**
     * 檢查用戶是否已綁定 LINE
     *
     * @param int $user_id WordPress 用戶 ID
     * @return bool 已綁定返回 true，未綁定返回 false
     */
    public static function isLinked(int $user_id): bool {
        return self::getBinding($user_id) !== null;
    }

    /**
     * 取得用戶的 LINE UID
     *
     * @param int $user_id WordPress 用戶 ID
     * @return string|null 找到返回 LINE UID，未綁定返回 null
     */
    public static function getLineUid(int $user_id): ?string {
        $binding = self::getBinding($user_id);

        return $binding ? $binding->line_uid : null;
    }

    /**
     * 取得用戶的 LINE 顯示名稱
     *
     * @param int $user_id WordPress 用戶 ID
     * @return string|null 找到返回顯示名稱，未綁定返回 null
     */
    public static function getDisplayName(int $user_id): ?string {
        $binding = self::getBinding($user_id);

        if (!$binding) {
            return null;
        }

        // 如果有儲存顯示名稱則返回，否則 fallback 到 WordPress 顯示名稱
        if (!empty($binding->display_name)) {
            return $binding->display_name;
        }

        $user = get_user_by('ID', $user_id);
        return $user ? $user->display_name : null;
    }

    /**
     * 取得用戶的 LINE 頭像 URL
     *
     * @param int $user_id WordPress 用戶 ID
     * @return string|null 找到返回頭像 URL，未綁定返回 null
     */
    public static function getPictureUrl(int $user_id): ?string {
        $binding = self::getBinding($user_id);

        if (!$binding) {
            return null;
        }

        // 如果有儲存頭像則返回，否則 fallback 到 WordPress Gravatar
        if (!empty($binding->picture_url)) {
            return $binding->picture_url;
        }

        return get_avatar_url($user_id);
    }

    /**
     * 批次取得多個用戶的綁定狀態
     *
     * @param array $user_ids WordPress 用戶 ID 陣列
     * @return array 關聯陣列，key 為 user_id，value 為綁定記錄或 null
     */
    public static function getBindingsBatch(array $user_ids): array {
        if (empty($user_ids)) {
            return [];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        // 清理並準備 ID 列表
        $user_ids = array_map('intval', $user_ids);
        $user_ids = array_filter($user_ids, function($id) { return $id > 0; });

        if (empty($user_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));

        // 查詢所有綁定記錄
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, line_uid, display_name, picture_url, email,
                        email_verified, status, created_at, updated_at
                 FROM {$table_name}
                 WHERE user_id IN ({$placeholders}) AND status = 'active'",
                ...$user_ids
            )
        );

        // 建立關聯陣列
        $bindings = [];
        foreach ($user_ids as $user_id) {
            $bindings[$user_id] = null;
        }

        foreach ($results as $row) {
            $bindings[(int) $row->user_id] = $row;
        }

        return $bindings;
    }

    /**
     * 統計已綁定用戶數量
     *
     * @return int 已綁定用戶數量
     */
    public static function countLinkedUsers(): int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'"
        );

        return (int) $count;
    }
}
