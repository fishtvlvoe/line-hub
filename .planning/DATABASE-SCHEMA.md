# LINE Hub 資料庫設計文件

## 文件概述

**版本**: 1.0
**建立日期**: 2026-02-06
**目的**: 定義 LINE Hub 的完整資料庫結構，確保高效能和可擴展性

---

## 資料表概覽

LINE Hub 使用 4 個獨立資料表，前綴為 `wp_line_hub_`：

| 資料表 | 用途 | 預估資料量 | 保留期 |
|--------|------|-----------|--------|
| `wp_line_hub_users` | LINE 用戶綁定 | 10K-100K | 永久 |
| `wp_line_hub_webhooks` | Webhook 記錄 | 1M+ | 30 天 |
| `wp_line_hub_settings` | 進階設定 | < 100 | 永久 |
| `wp_line_hub_notifications` | 訊息記錄 | 100K-1M | 90 天 |

---

## 資料表詳細設計

### 1. wp_line_hub_users - LINE 用戶綁定表

**用途**: 儲存 LINE UID 與 WordPress User ID 的綁定關係

```sql
CREATE TABLE wp_line_hub_users (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL COMMENT 'WordPress User ID',
    line_uid varchar(255) NOT NULL COMMENT 'LINE User ID',
    display_name varchar(255) DEFAULT NULL COMMENT 'LINE 顯示名稱',
    picture_url varchar(500) DEFAULT NULL COMMENT 'LINE 頭像 URL',
    email varchar(255) DEFAULT NULL COMMENT 'LINE Email (已驗證)',
    email_verified tinyint(1) DEFAULT 1 COMMENT 'Email 是否已驗證 (LINE 回傳的總是已驗證)',
    access_token text DEFAULT NULL COMMENT 'LINE Access Token (加密)',
    refresh_token text DEFAULT NULL COMMENT 'LINE Refresh Token (加密)',
    token_expires_at datetime DEFAULT NULL COMMENT 'Token 過期時間',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY line_uid (line_uid),
    KEY user_id (user_id),
    KEY email (email),
    KEY updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**欄位說明**:

| 欄位 | 類型 | 說明 | 來源 |
|------|------|------|------|
| `id` | bigint(20) | 主鍵 | - |
| `user_id` | bigint(20) | WordPress User ID | 綁定時取得 |
| `line_uid` | varchar(255) | LINE User ID | OAuth Profile API |
| `display_name` | varchar(255) | LINE 顯示名稱 | OAuth Profile API |
| `picture_url` | varchar(500) | LINE 頭像 URL | OAuth Profile API |
| `email` | varchar(255) | LINE Email | ID Token (已驗證) |
| `email_verified` | tinyint(1) | Email 驗證狀態 | 總是 1（LINE 保證） |
| `access_token` | text | Access Token (加密) | OAuth Token Exchange |
| `refresh_token` | text | Refresh Token (加密) | OAuth Token Exchange |
| `token_expires_at` | datetime | Token 過期時間 | OAuth Response |
| `created_at` | datetime | 建立時間 | 自動 |
| `updated_at` | datetime | 更新時間 | 自動 |

**索引策略**:

- `UNIQUE KEY line_uid`: 防止重複綁定同一個 LINE 帳號
- `KEY user_id`: 快速查詢「某個 WordPress 用戶的 LINE UID」
- `KEY email`: 支援 Email 搜尋
- `KEY updated_at`: 支援「最近更新」查詢

**資料範例**:

```
+----+---------+--------------+--------------+-------------------------+-------------------+----------------+
| id | user_id | line_uid     | display_name | picture_url             | email             | email_verified |
+----+---------+--------------+--------------+-------------------------+-------------------+----------------+
| 1  | 123     | Uabcd1234... | 張三         | https://line.me/...     | user@example.com  | 1              |
| 2  | 456     | Uefgh5678... | 李四         | https://line.me/...     | test@test.com     | 1              |
+----+---------+--------------+--------------+-------------------------+-------------------+----------------+
```

---

### 2. wp_line_hub_webhooks - Webhook 記錄表

**用途**: 記錄所有 LINE Webhook 事件，用於去重、審計和除錯

```sql
CREATE TABLE wp_line_hub_webhooks (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    webhook_event_id varchar(255) DEFAULT NULL COMMENT 'LINE Webhook Event ID (去重用)',
    event_type varchar(50) NOT NULL COMMENT '事件類型 (message, follow, unfollow, postback)',
    line_uid varchar(255) DEFAULT NULL COMMENT '觸發事件的 LINE User ID',
    user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT '對應的 WordPress User ID (如果已綁定)',
    payload longtext NOT NULL COMMENT '完整的 Webhook Payload (JSON)',
    processed tinyint(1) DEFAULT 0 COMMENT '是否已處理',
    processed_at datetime DEFAULT NULL COMMENT '處理完成時間',
    error_message text DEFAULT NULL COMMENT '處理錯誤訊息 (如果有)',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY webhook_event_id (webhook_event_id),
    KEY event_type (event_type),
    KEY line_uid (line_uid),
    KEY user_id (user_id),
    KEY processed (processed),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**欄位說明**:

| 欄位 | 類型 | 說明 |
|------|------|------|
| `webhook_event_id` | varchar(255) | LINE 提供的事件 ID（去重用） |
| `event_type` | varchar(50) | message, follow, unfollow, postback, beacon, accountLink, things |
| `line_uid` | varchar(255) | 觸發事件的 LINE User ID |
| `user_id` | bigint(20) | 對應的 WordPress User ID（如果已綁定） |
| `payload` | longtext | 完整的 Webhook JSON |
| `processed` | tinyint(1) | 0=未處理, 1=已處理 |
| `processed_at` | datetime | 處理完成時間 |
| `error_message` | text | 處理失敗的錯誤訊息 |

**索引策略**:

- `UNIQUE KEY webhook_event_id`: 防止重複處理同一事件
- `KEY event_type`: 支援「按事件類型查詢」
- `KEY created_at`: 支援「時間範圍查詢」和資料清理

**資料清理策略**:

```php
// 每日 cron 清理 30 天前的記錄
wp_schedule_event(time(), 'daily', 'line_hub_cleanup_webhooks');

add_action('line_hub_cleanup_webhooks', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'line_hub_webhooks';
    $wpdb->query("DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
});
```

---

### 3. wp_line_hub_settings - 進階設定表

**用途**: 儲存複雜設定，支援 JSON 格式和加密

```sql
CREATE TABLE wp_line_hub_settings (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_group varchar(50) NOT NULL COMMENT '設定群組 (general, notification, integration, login)',
    setting_key varchar(100) NOT NULL COMMENT '設定鍵',
    setting_value longtext NOT NULL COMMENT '設定值 (支援 JSON)',
    encrypted tinyint(1) DEFAULT 0 COMMENT '是否加密儲存',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY group_key (setting_group, setting_key),
    KEY setting_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**設定群組分類**:

| 群組 | 用途 | 範例設定 |
|------|------|---------|
| `general` | 基本設定 | channel_id, channel_secret, access_token |
| `login` | 登入設定 | force_reauth, bot_prompt, initial_amr, switch_amr, allow_auto_login |
| `notification` | 通知設定 | 各場景的訊息模板、開關 |
| `integration` | 整合設定 | 啟用的外掛整合、Hook 配置 |
| `advanced` | 進階設定 | webhook_timeout, rate_limit, cache_ttl |

**資料範例**:

```
+----+----------------+--------------------+---------------------------+-----------+
| id | setting_group  | setting_key        | setting_value             | encrypted |
+----+----------------+--------------------+---------------------------+-----------+
| 1  | general        | channel_id         | 1234567890                | 0         |
| 2  | general        | channel_secret     | abc123...encrypted...     | 1         |
| 3  | login          | force_reauth       | 1                         | 0         |
| 4  | login          | bot_prompt         | aggressive                | 0         |
| 5  | notification   | order_created      | {"enabled":true,"temp..." | 0         |
| 6  | integration    | fluentcart         | {"enabled":true,"hook..." | 0         |
+----+----------------+--------------------+---------------------------+-----------+
```

**加密儲存範例**:

```php
// 儲存敏感資料
function set_encrypted_setting($group, $key, $value) {
    $encrypted_value = encrypt_value($value);

    global $wpdb;
    $wpdb->replace($wpdb->prefix . 'line_hub_settings', [
        'setting_group' => $group,
        'setting_key' => $key,
        'setting_value' => $encrypted_value,
        'encrypted' => 1,
    ]);
}

// 讀取敏感資料
function get_encrypted_setting($group, $key) {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT setting_value, encrypted FROM {$wpdb->prefix}line_hub_settings
         WHERE setting_group = %s AND setting_key = %s",
        $group, $key
    ));

    if ($row && $row->encrypted) {
        return decrypt_value($row->setting_value);
    }

    return $row ? $row->setting_value : null;
}

// AES-256 加密
function encrypt_value($value) {
    $key = NONCE_KEY; // WordPress 常數
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}
```

---

### 4. wp_line_hub_notifications - 訊息記錄表

**用途**: 追蹤所有發送的 LINE 訊息，用於統計和除錯

```sql
CREATE TABLE wp_line_hub_notifications (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED DEFAULT NULL COMMENT 'WordPress User ID',
    line_uid varchar(255) NOT NULL COMMENT 'LINE User ID',
    scene varchar(50) NOT NULL COMMENT '通知場景 (order_created, shipment, welcome)',
    message_type varchar(20) DEFAULT 'text' COMMENT '訊息類型 (text, flex, template)',
    message longtext NOT NULL COMMENT '訊息內容 (JSON)',
    status varchar(20) DEFAULT 'pending' COMMENT '發送狀態 (pending, sent, failed)',
    response longtext DEFAULT NULL COMMENT 'LINE API 回應 (JSON)',
    error_message text DEFAULT NULL COMMENT '錯誤訊息 (如果失敗)',
    sent_at datetime DEFAULT NULL COMMENT '實際發送時間',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY line_uid (line_uid),
    KEY scene (scene),
    KEY status (status),
    KEY sent_at (sent_at),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**欄位說明**:

| 欄位 | 類型 | 說明 |
|------|------|------|
| `scene` | varchar(50) | order_created, order_completed, shipment, welcome, password_reset, custom |
| `message_type` | varchar(20) | text, flex, template, imagemap, video, audio |
| `message` | longtext | 訊息內容（JSON 格式） |
| `status` | varchar(20) | pending, sent, failed |
| `response` | longtext | LINE API 回應（包含 message_id） |
| `error_message` | text | 失敗原因 |

**索引策略**:

- `KEY scene`: 支援「按場景統計」
- `KEY status`: 支援「失敗訊息查詢」
- `KEY sent_at`: 支援「時間範圍統計」

**資料清理策略**:

```php
// 每週清理 90 天前的記錄
wp_schedule_event(time(), 'weekly', 'line_hub_cleanup_notifications');

add_action('line_hub_cleanup_notifications', function() {
    global $wpdb;
    $table = $wpdb->prefix . 'line_hub_notifications';
    $wpdb->query("DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
});
```

**統計查詢範例**:

```php
// 計算發送成功率
function get_success_rate($scene = null, $days = 7) {
    global $wpdb;
    $table = $wpdb->prefix . 'line_hub_notifications';

    $where = "created_at > DATE_SUB(NOW(), INTERVAL {$days} DAY)";
    if ($scene) {
        $where .= $wpdb->prepare(" AND scene = %s", $scene);
    }

    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
    $sent = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where} AND status = 'sent'");

    return $total > 0 ? ($sent / $total) * 100 : 0;
}
```

---

## 常見查詢範例

### 1. 查詢用戶的 LINE UID

```php
function get_line_uid($user_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT line_uid FROM {$wpdb->prefix}line_hub_users WHERE user_id = %d",
        $user_id
    ));
}
```

**快取優化版本**:

```php
function get_line_uid_cached($user_id) {
    $cache_key = 'line_hub_uid_' . $user_id;
    $line_uid = get_transient($cache_key);

    if (!$line_uid) {
        global $wpdb;
        $line_uid = $wpdb->get_var($wpdb->prepare(
            "SELECT line_uid FROM {$wpdb->prefix}line_hub_users WHERE user_id = %d",
            $user_id
        ));

        if ($line_uid) {
            set_transient($cache_key, $line_uid, HOUR_IN_SECONDS);
        }
    }

    return $line_uid;
}
```

### 2. 查詢 WordPress User ID（反向查詢）

```php
function get_user_id_by_line_uid($line_uid) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->prefix}line_hub_users WHERE line_uid = %s",
        $line_uid
    ));
}
```

### 3. 查詢最近的 Webhook 記錄

```php
function get_recent_webhooks($limit = 100, $event_type = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'line_hub_webhooks';

    $sql = "SELECT * FROM {$table}";

    if ($event_type) {
        $sql .= $wpdb->prepare(" WHERE event_type = %s", $event_type);
    }

    $sql .= " ORDER BY created_at DESC LIMIT %d";

    return $wpdb->get_results($wpdb->prepare($sql, $limit));
}
```

### 4. 查詢失敗的通知

```php
function get_failed_notifications($days = 7) {
    global $wpdb;
    $table = $wpdb->prefix . 'line_hub_notifications';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE status = 'failed'
         AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
         ORDER BY created_at DESC",
        $days
    ));
}
```

### 5. 統計通知發送數量（按場景）

```php
function get_notification_stats($days = 30) {
    global $wpdb;
    $table = $wpdb->prefix . 'line_hub_notifications';

    return $wpdb->get_results($wpdb->prepare(
        "SELECT
            scene,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
         FROM {$table}
         WHERE created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
         GROUP BY scene
         ORDER BY total DESC",
        $days
    ));
}
```

---

## 資料庫版本管理

### 版本號系統

使用 WordPress Option API 追蹤資料庫版本：

```php
define('LINE_HUB_DB_VERSION', '1.0');

// 檢查是否需要更新
function line_hub_check_db_version() {
    $installed_version = get_option('line_hub_db_version', '0');

    if (version_compare($installed_version, LINE_HUB_DB_VERSION, '<')) {
        line_hub_install_database();
    }
}

add_action('plugins_loaded', 'line_hub_check_db_version');
```

### Migration 腳本範例

```php
function line_hub_migrate_1_0_to_1_1() {
    global $wpdb;

    // 範例：新增 email_verified 欄位（v1.1）
    $table = $wpdb->prefix . 'line_hub_users';

    $column_exists = $wpdb->get_results(
        "SHOW COLUMNS FROM {$table} LIKE 'email_verified'"
    );

    if (empty($column_exists)) {
        $wpdb->query(
            "ALTER TABLE {$table}
             ADD COLUMN email_verified tinyint(1) DEFAULT 1 AFTER email"
        );
    }

    update_option('line_hub_db_version', '1.1');
}
```

---

## 效能優化建議

### 1. 索引優化

✅ **已實作的索引**:

- `wp_line_hub_users`: 4 個索引（UNIQUE line_uid, user_id, email, updated_at）
- `wp_line_hub_webhooks`: 6 個索引（UNIQUE webhook_event_id, event_type, line_uid, user_id, processed, created_at）
- `wp_line_hub_settings`: 2 個索引（UNIQUE group_key, setting_group）
- `wp_line_hub_notifications`: 6 個索引（user_id, line_uid, scene, status, sent_at, created_at）

### 2. 快取策略

```php
// 用戶綁定快取（1 小時）
function get_binding_cached($user_id) {
    $cache_key = 'line_hub_binding_' . $user_id;
    $data = get_transient($cache_key);

    if (!$data) {
        global $wpdb;
        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}line_hub_users WHERE user_id = %d",
            $user_id
        ));

        if ($data) {
            set_transient($cache_key, $data, HOUR_IN_SECONDS);
        }
    }

    return $data;
}

// 清除快取
function clear_binding_cache($user_id) {
    delete_transient('line_hub_binding_' . $user_id);
}
```

### 3. 批次查詢

```php
// 批次取得多個用戶的 LINE UID
function get_line_uids_batch($user_ids) {
    if (empty($user_ids)) {
        return [];
    }

    global $wpdb;
    $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, line_uid FROM {$wpdb->prefix}line_hub_users
         WHERE user_id IN ({$placeholders})",
        ...$user_ids
    ));

    // 轉換為 user_id => line_uid 陣列
    $map = [];
    foreach ($results as $row) {
        $map[$row->user_id] = $row->line_uid;
    }

    return $map;
}
```

### 4. 資料清理排程

```php
// 註冊清理任務
register_activation_hook(__FILE__, function() {
    if (!wp_next_scheduled('line_hub_cleanup_webhooks')) {
        wp_schedule_event(time(), 'daily', 'line_hub_cleanup_webhooks');
    }

    if (!wp_next_scheduled('line_hub_cleanup_notifications')) {
        wp_schedule_event(time(), 'weekly', 'line_hub_cleanup_notifications');
    }
});

// 清理 Webhook 記錄（30 天）
add_action('line_hub_cleanup_webhooks', function() {
    global $wpdb;
    $deleted = $wpdb->query(
        "DELETE FROM {$wpdb->prefix}line_hub_webhooks
         WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    error_log("LINE Hub: Cleaned {$deleted} webhook records");
});

// 清理通知記錄（90 天）
add_action('line_hub_cleanup_notifications', function() {
    global $wpdb;
    $deleted = $wpdb->query(
        "DELETE FROM {$wpdb->prefix}line_hub_notifications
         WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
    );
    error_log("LINE Hub: Cleaned {$deleted} notification records");
});
```

---

## 資料遷移計畫（從 buygo-line-notify）

### 遷移對照表

| buygo-line-notify 表 | LINE Hub 表 | 遷移策略 |
|---------------------|------------|---------|
| `wp_buygo_line_users` | `wp_line_hub_users` | 完整複製 + 欄位對應 |
| `wp_buygo_line_bindings` | `wp_line_hub_users` | 合併到 users 表 |
| - | `wp_line_hub_webhooks` | 新表，無需遷移 |
| - | `wp_line_hub_settings` | 新表，從 wp_options 遷移 |
| - | `wp_line_hub_notifications` | 新表，無需遷移舊資料 |

### 遷移腳本

```php
function line_hub_migrate_from_buygo_line_notify() {
    global $wpdb;

    // 檢查舊表是否存在
    $old_table = $wpdb->prefix . 'buygo_line_users';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$old_table}'") != $old_table) {
        return false; // 舊表不存在，無需遷移
    }

    // 複製用戶綁定資料
    $wpdb->query("
        INSERT INTO {$wpdb->prefix}line_hub_users
        (user_id, line_uid, display_name, picture_url, email, created_at, updated_at)
        SELECT
            user_id,
            line_uid,
            display_name,
            picture_url,
            email,
            created_at,
            updated_at
        FROM {$old_table}
        WHERE user_id IS NOT NULL
        ON DUPLICATE KEY UPDATE
            display_name = VALUES(display_name),
            picture_url = VALUES(picture_url),
            email = VALUES(email),
            updated_at = VALUES(updated_at)
    ");

    // 遷移設定（從 wp_options）
    $old_settings = [
        'buygo_line_notify_channel_id' => ['general', 'channel_id', false],
        'buygo_line_notify_channel_secret' => ['general', 'channel_secret', true],
        'buygo_line_notify_access_token' => ['general', 'access_token', true],
    ];

    foreach ($old_settings as $option_name => list($group, $key, $encrypted)) {
        $value = get_option($option_name);
        if ($value) {
            $wpdb->replace($wpdb->prefix . 'line_hub_settings', [
                'setting_group' => $group,
                'setting_key' => $key,
                'setting_value' => $encrypted ? encrypt_value($value) : $value,
                'encrypted' => $encrypted ? 1 : 0,
            ]);
        }
    }

    update_option('line_hub_migration_completed', true);
    return true;
}
```

---

## 安全考量

### 1. SQL Injection 防護

✅ **使用 `$wpdb->prepare()`**

```php
// ❌ 錯誤示範
$user_id = $_GET['user_id'];
$wpdb->get_var("SELECT line_uid FROM wp_line_hub_users WHERE user_id = {$user_id}");

// ✅ 正確做法
$user_id = intval($_GET['user_id']);
$wpdb->get_var($wpdb->prepare(
    "SELECT line_uid FROM {$wpdb->prefix}line_hub_users WHERE user_id = %d",
    $user_id
));
```

### 2. 敏感資料加密

✅ **加密 Access Token 和 Channel Secret**

```php
// 儲存時加密
$access_token = 'xxx...';
$encrypted = encrypt_value($access_token);

$wpdb->update($wpdb->prefix . 'line_hub_users', [
    'access_token' => $encrypted
], ['id' => $user_binding_id]);

// 讀取時解密
$encrypted_token = $wpdb->get_var("SELECT access_token FROM ...");
$access_token = decrypt_value($encrypted_token);
```

### 3. 資料清理

✅ **定期清理敏感記錄**

- Webhook Payload 包含用戶訊息內容 → 30 天清理
- 通知記錄包含發送內容 → 90 天清理

---

## 備份與還原

### 備份腳本

```bash
#!/bin/bash
# LINE Hub 資料庫備份

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/path/to/backups/line-hub"
DB_NAME="wordpress"
DB_USER="root"
DB_PASS="password"

mkdir -p $BACKUP_DIR

# 只備份 LINE Hub 相關表
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME \
    wp_line_hub_users \
    wp_line_hub_webhooks \
    wp_line_hub_settings \
    wp_line_hub_notifications \
    > "$BACKUP_DIR/line_hub_$DATE.sql"

echo "Backup completed: line_hub_$DATE.sql"
```

### 還原腳本

```bash
#!/bin/bash
# LINE Hub 資料庫還原

BACKUP_FILE=$1
DB_NAME="wordpress"
DB_USER="root"
DB_PASS="password"

if [ -z "$BACKUP_FILE" ]; then
    echo "Usage: restore.sh <backup_file>"
    exit 1
fi

mysql -u$DB_USER -p$DB_PASS $DB_NAME < $BACKUP_FILE

echo "Restore completed from: $BACKUP_FILE"
```

---

## 附錄：完整建表 SQL

```sql
-- LINE Hub 完整資料庫 Schema
-- 版本: 1.0
-- 日期: 2026-02-06

-- 1. 用戶綁定表
CREATE TABLE IF NOT EXISTS wp_line_hub_users (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED NOT NULL,
    line_uid varchar(255) NOT NULL,
    display_name varchar(255) DEFAULT NULL,
    picture_url varchar(500) DEFAULT NULL,
    email varchar(255) DEFAULT NULL,
    email_verified tinyint(1) DEFAULT 1,
    access_token text DEFAULT NULL,
    refresh_token text DEFAULT NULL,
    token_expires_at datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY line_uid (line_uid),
    KEY user_id (user_id),
    KEY email (email),
    KEY updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Webhook 記錄表
CREATE TABLE IF NOT EXISTS wp_line_hub_webhooks (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    webhook_event_id varchar(255) DEFAULT NULL,
    event_type varchar(50) NOT NULL,
    line_uid varchar(255) DEFAULT NULL,
    user_id bigint(20) UNSIGNED DEFAULT NULL,
    payload longtext NOT NULL,
    processed tinyint(1) DEFAULT 0,
    processed_at datetime DEFAULT NULL,
    error_message text DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY webhook_event_id (webhook_event_id),
    KEY event_type (event_type),
    KEY line_uid (line_uid),
    KEY user_id (user_id),
    KEY processed (processed),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 設定表
CREATE TABLE IF NOT EXISTS wp_line_hub_settings (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_group varchar(50) NOT NULL,
    setting_key varchar(100) NOT NULL,
    setting_value longtext NOT NULL,
    encrypted tinyint(1) DEFAULT 0,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY group_key (setting_group, setting_key),
    KEY setting_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 訊息記錄表
CREATE TABLE IF NOT EXISTS wp_line_hub_notifications (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id bigint(20) UNSIGNED DEFAULT NULL,
    line_uid varchar(255) NOT NULL,
    scene varchar(50) NOT NULL,
    message_type varchar(20) DEFAULT 'text',
    message longtext NOT NULL,
    status varchar(20) DEFAULT 'pending',
    response longtext DEFAULT NULL,
    error_message text DEFAULT NULL,
    sent_at datetime DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY line_uid (line_uid),
    KEY scene (scene),
    KEY status (status),
    KEY sent_at (sent_at),
    KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

**文件版本**: 1.0
**建立日期**: 2026-02-06
**最後更新**: 2026-02-06 - 初版完成
