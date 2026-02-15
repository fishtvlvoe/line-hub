# LINE Hub — Phase 5：Webhook 接收中心

> 更新日期：2026-02-15
> 前置完成：Phase 4 通知系統、Phase 4B BuyGo 接入

---

## 核心設計原則（必須遵守）

> **LineHub 只做「接收、驗證、分發」，業務處理透過 WordPress hooks 讓外部外掛接手**

- LineHub 不知道什麼是「商品上架」「訂單」「圖片處理」
- LineHub 收到 Webhook 事件後，透過 `do_action()` 廣播，BuyGo 自己監聽處理
- LineHub 自己只處理：follow/unfollow（更新好友狀態）、基礎指令（/help, /id）

---

## 目標架構

```
LINE 伺服器
  → POST /wp-json/line-hub/v1/webhook
    → WebhookReceiver（驗證 HMAC 簽名、立即回 200）
      → EventDispatcher（分類事件、透過 WordPress hooks 分發）
        → line_hub/webhook/message/text   → BuyGo 監聽（商品上架等）
        → line_hub/webhook/message/image  → BuyGo 監聽（圖片上傳）
        → line_hub/webhook/postback       → BuyGo 監聯（Flex 按鈕）
        → line_hub/webhook/follow         → LineHub 自己處理（更新好友狀態）
        → line_hub/webhook/unfollow       → LineHub 自己處理（標記取消好友）
```

---

## 要建立的檔案（全部在 line-hub 內）

### 1. `line-hub/includes/webhook/class-webhook-receiver.php`

**職責**：REST API 端點 + HMAC 簽名驗證

```php
namespace LineHub\Webhook;

class WebhookReceiver {
    // 註冊 REST API 端點
    public function registerRoutes(): void {
        register_rest_route('line-hub/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [$this, 'handleWebhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    // 處理 Webhook 請求
    public function handleWebhook(\WP_REST_Request $request) {
        // 1. 取得 request body（raw）
        // 2. 驗證 HMAC 簽名（channel_secret 從 SettingsService 讀取）
        // 3. 解析 JSON body
        // 4. 檢查 verify event（replyToken 全是 0）→ 直接回 200
        // 5. 立即回 200 OK（LINE 要求 1 秒內回應）
        // 6. 排隊到 WordPress Cron 處理事件（避免阻塞回應）
    }

    // HMAC-SHA256 簽名驗證
    private function verifySignature(string $body, string $signature): bool {
        $channelSecret = SettingsService::get('general', 'channel_secret', '');
        $hash = hash_hmac('sha256', $body, $channelSecret, true);
        return hash_equals(base64_encode($hash), $signature);
    }
}
```

**HMAC 簽名驗證規則**：
- Header 名稱嘗試順序：`x-line-signature` → `X-LINE-Signature` → `HTTP_X_LINE_SIGNATURE`
- channel_secret 從 `SettingsService::get('general', 'channel_secret')` 讀取
- 正式環境必須驗證；開發環境（`WP_DEBUG=true`）如果沒有 channel_secret 可跳過但要記 warning log

**Cron 排隊機制**：
- Hook 名稱：`line_hub_process_webhook`
- 使用 `wp_schedule_single_event(time(), 'line_hub_process_webhook', [$events])`
- 這樣 LINE 能在 1 秒內收到 200 OK

### 2. `line-hub/includes/webhook/class-event-dispatcher.php`

**職責**：事件分類 + WordPress hooks 分發

```php
namespace LineHub\Webhook;

class EventDispatcher {
    // 處理事件陣列（由 Cron 觸發）
    public function processEvents(array $events): void {
        foreach ($events as $event) {
            $this->dispatchEvent($event);
        }
    }

    // 分發單一事件
    private function dispatchEvent(array $event): void {
        $type = $event['type'] ?? '';
        $replyToken = $event['replyToken'] ?? '';
        $source = $event['source'] ?? [];

        // 通用 hook（所有事件都觸發）
        do_action('line_hub/webhook/event', $event);

        switch ($type) {
            case 'message':
                $this->dispatchMessage($event);
                break;
            case 'follow':
                $this->handleFollow($event);
                do_action('line_hub/webhook/follow', $event);
                break;
            case 'unfollow':
                $this->handleUnfollow($event);
                do_action('line_hub/webhook/unfollow', $event);
                break;
            case 'postback':
                do_action('line_hub/webhook/postback', $event);
                break;
        }
    }

    // 訊息事件再細分
    private function dispatchMessage(array $event): void {
        $messageType = $event['message']['type'] ?? '';

        // 通用訊息 hook
        do_action('line_hub/webhook/message', $event);

        // 細分類型 hook
        switch ($messageType) {
            case 'text':
                do_action('line_hub/webhook/message/text', $event);
                break;
            case 'image':
                do_action('line_hub/webhook/message/image', $event);
                break;
            case 'sticker':
                do_action('line_hub/webhook/message/sticker', $event);
                break;
            // 其他類型...
        }
    }

    // LineHub 自己處理 follow（更新好友狀態）
    private function handleFollow(array $event): void {
        $lineUid = $event['source']['userId'] ?? '';
        if (empty($lineUid)) return;

        // 透過 UserService 查找 WP User ID
        $userId = UserService::getUserIdByLineUid($lineUid);
        if ($userId) {
            update_user_meta($userId, 'line_hub_is_friend', '1');
        }
    }

    // LineHub 自己處理 unfollow（標記取消好友）
    private function handleUnfollow(array $event): void {
        $lineUid = $event['source']['userId'] ?? '';
        if (empty($lineUid)) return;

        $userId = UserService::getUserIdByLineUid($lineUid);
        if ($userId) {
            update_user_meta($userId, 'line_hub_is_friend', '0');
        }
    }
}
```

### 3. `line-hub/includes/webhook/class-webhook-logger.php`

**職責**：記錄 Webhook 事件（除錯用）

```php
namespace LineHub\Webhook;

class WebhookLogger {
    // 記錄事件到 wp_line_hub_webhooks 表
    public static function log(string $eventType, array $eventData, ?string $lineUid = null): void {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';

        $wpdb->insert($table, [
            'event_type' => $eventType,
            'event_id' => $eventData['webhookEventId'] ?? '',
            'line_uid' => $lineUid ?? ($eventData['source']['userId'] ?? ''),
            'payload' => wp_json_encode($eventData),
            'created_at' => current_time('mysql'),
        ]);

        // 保留最近 200 筆，清理舊記錄
        self::cleanup();
    }

    // 清理超過 200 筆的舊記錄
    private static function cleanup(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'line_hub_webhooks';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 200) {
            $delete_count = $count - 200;
            $wpdb->query("DELETE FROM {$table} ORDER BY id ASC LIMIT {$delete_count}");
        }
    }
}
```

### 4. 修改 `line-hub/includes/class-plugin.php`

**新增內容**：
- `require_once` 載入 webhook 目錄下的 3 個類別
- 在 `rest_api_init` hook 中註冊 WebhookReceiver 路由
- 註冊 Cron hook：`add_action('line_hub_process_webhook', [EventDispatcher, 'processEvents'])`

---

## 不要做的事

- **不要搬移 BuyGo 的 LineWebhookHandler**（那是 BuyGo 的業務邏輯）
- **不要搬移 BuyGo 的 LineKeywordResponder**（那是 BuyGo 的關鍵字回應）
- **不要在 LineHub 裡處理商品上架、圖片上傳等業務**
- **不要修改 BuyGo 的任何檔案**（Phase 5 只建 LineHub 側）
- **不要建立 Admin UI**（那是 Phase 7）
- **不要去重新實作 BuyGo 已有的功能**

---

## 去重機制

使用 Webhook event ID 去重：
- LINE 每個 Webhook 事件都帶有 `webhookEventId`
- 在 `handleWebhook()` 中，先檢查這個 ID 是否已在 `wp_line_hub_webhooks` 表中
- 已存在則跳過，不存在才處理

```php
private function isDuplicate(string $eventId): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'line_hub_webhooks';
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE event_id = %s", $eventId
    ));
}
```

---

## wp_line_hub_webhooks 表結構

這張表在 Phase 1 資料庫建立時已經存在，確認結構：

```sql
CREATE TABLE wp_line_hub_webhooks (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL,
    event_id VARCHAR(100) DEFAULT '',
    line_uid VARCHAR(50) DEFAULT '',
    payload LONGTEXT,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY event_type (event_type),
    KEY event_id (event_id),
    KEY created_at (created_at)
);
```

---

## 執行步驟

1. **讀取** LineHub 的 SettingsService 確認 channel_secret 讀取方式
   - `/Users/fishtv/Development/line-hub/includes/services/class-settings-service.php`

2. **讀取** LineHub 的 UserService 確認 getUserIdByLineUid 方法
   - `/Users/fishtv/Development/line-hub/includes/services/class-user-service.php`

3. **讀取** LineHub 的 Database 確認 webhooks 表結構
   - `/Users/fishtv/Development/line-hub/includes/class-database.php`

4. **讀取** LineHub 的 Plugin 載入器了解 autoloader 和 hook 註冊方式
   - `/Users/fishtv/Development/line-hub/includes/class-plugin.php`

5. **建立** `class-webhook-receiver.php`
6. **建立** `class-event-dispatcher.php`
7. **建立** `class-webhook-logger.php`
8. **修改** `class-plugin.php` 加入 webhook 模組的載入和 hook 註冊

9. PHP syntax check：`php -l` 所有新建和修改的檔案
10. Git commit（在 line-hub 目錄）

---

## Hook 參考表（BuyGo 未來要監聽的）

| Hook 名稱 | 觸發時機 | 參數 |
|-----------|---------|------|
| `line_hub/webhook/event` | 所有事件 | `$event` (array) |
| `line_hub/webhook/message` | 所有訊息 | `$event` (array) |
| `line_hub/webhook/message/text` | 文字訊息 | `$event` (array) |
| `line_hub/webhook/message/image` | 圖片訊息 | `$event` (array) |
| `line_hub/webhook/message/sticker` | 貼圖 | `$event` (array) |
| `line_hub/webhook/follow` | 加好友 | `$event` (array) |
| `line_hub/webhook/unfollow` | 移除好友 | `$event` (array) |
| `line_hub/webhook/postback` | Postback | `$event` (array) |

---

## LineHub 可用的服務

```php
// 設定
use LineHub\Services\SettingsService;
SettingsService::get('general', 'channel_secret');
SettingsService::get('general', 'access_token');

// 用戶
use LineHub\Services\UserService;
UserService::getUserIdByLineUid(string $lineUid): ?int;
UserService::getLineUid(int $userId): ?string;

// 訊息發送
use LineHub\Messaging\MessagingService;
$service = new MessagingService();
$service->replyMessage(string $replyToken, array $messages);
```

---

## 環境資訊

- line-hub：`/Users/fishtv/Development/line-hub/`
- buygo-plus-one：`/Users/fishtv/Development/buygo-plus-one/`（參考用，不修改）
- Autoloader 命名規則：CamelCase 類名 → `class-kebab-case.php`
  - 例：`WebhookReceiver` → `class-webhook-receiver.php`
  - 例：`EventDispatcher` → `class-event-dispatcher.php`
- 繁體中文回應
