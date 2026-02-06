# LINE Hub 架構設計文件

## 文件概述

**版本**: 1.0
**建立日期**: 2026-02-06
**作者**: Claude Code
**目的**: 定義 LINE Hub 的完整系統架構，包含 NSL 功能移植細節和外掛串接系統

---

## 系統架構總覽

### 6 層架構

```
┌─────────────────────────────────────────────────────────────┐
│                         展示層                                │
│  Admin Pages, Shortcodes, LIFF Pages                        │
├─────────────────────────────────────────────────────────────┤
│                       REST API 層                             │
│  Webhook API, Login API, Binding API, Notification API      │
├─────────────────────────────────────────────────────────────┤
│                        服務層                                 │
│  AuthService, UserService, MessagingService,                │
│  NotificationService, IntegrationService                     │
├─────────────────────────────────────────────────────────────┤
│                       整合層 ⭐                               │
│  Hook Dispatcher, Event Listener, Plugin Connector          │
├─────────────────────────────────────────────────────────────┤
│                       資料層                                  │
│  Database, Cache (Transient), External API (LINE)           │
├─────────────────────────────────────────────────────────────┤
│                      基礎設施層                               │
│  WordPress Core, wp-cron, REST API Framework                │
└─────────────────────────────────────────────────────────────┘
```

### 核心模組

| 模組 | 職責 | 對應服務 |
|------|------|---------|
| **Auth 認證模組** | LINE 登入、OAuth 流程 | `AuthService`, `OAuthClient` |
| **User 用戶模組** | 用戶綁定、資料同步 | `UserService`, `ProfileSync` |
| **Messaging 訊息模組** | LINE 訊息發送 | `MessagingService`, `TemplateEngine` |
| **Notification 通知模組** | 通知場景管理 | `NotificationService`, `SceneManager` |
| **Webhook 處理模組** | Webhook 接收和處理 | `WebhookService`, `EventDispatcher` |
| **Integration 整合模組** ⭐ | 外掛串接系統 | `IntegrationService`, `HookRegistry` |
| **Settings 設定模組** | 統一設定管理 | `SettingsService` |

---

## 核心流程設計

### 1. LINE 登入流程（從 NSL 移植）

```
用戶端                LINE Hub              LINE Platform        WordPress
  │                      │                       │                  │
  │ 1. 點擊登入按鈕      │                       │                  │
  ├─────────────────────>│                       │                  │
  │                      │ 2. 產生 State Token    │                  │
  │                      │    儲存到 Transient     │                  │
  │                      ├───────────────────────>│                  │
  │                      │ 3. 重導向授權 URL       │                  │
  │<─────────────────────┤    (含 state, scope)   │                  │
  │                      │                       │                  │
  │ 4. LINE 授權畫面     │                       │                  │
  ├──────────────────────┼──────────────────────>│                  │
  │                      │                       │                  │
  │                      │          5. 如果 force_reauth = true     │
  │                      │             每次都顯示授權畫面             │
  │                      │             (從 NSL 移植)                │
  │                      │                       │                  │
  │ 6. 授權成功，回呼    │                       │                  │
  │<──────────────────────┼───────────────────────┤                  │
  │                      │                       │                  │
  │ 7. Callback + code   │                       │                  │
  ├─────────────────────>│                       │                  │
  │                      │ 8. 驗證 State Token    │                  │
  │                      │ 9. Exchange code       │                  │
  │                      │    for Access Token    │                  │
  │                      ├───────────────────────>│                  │
  │                      │<───────────────────────┤                  │
  │                      │ 10. 取得 ID Token       │                  │
  │                      │ 11. 驗證 ID Token       │                  │
  │                      ├───────────────────────>│                  │
  │                      │<───────────────────────┤                  │
  │                      │ 12. 解析 Email          │                  │
  │                      │                       │                  │
  │                      │ ──┐                   │                  │
  │                      │   │ 13. Email 處理邏輯  │                  │
  │                      │   │ (從 NSL 移植)       │                  │
  │                      │   │                   │                  │
  │                      │   │ IF email 有效:     │                  │
  │                      │   │   → 繼續註冊流程   │                  │
  │                      │   │                   │                  │
  │                      │   │ IF email 無效:     │                  │
  │                      │   │   選項 A: 強制重新授權               │
  │                      │   │     (force_reauth)│                  │
  │                      │   │   選項 B: 顯示手動│                  │
  │                      │   │     輸入表單      │                  │
  │                      │<──┘                   │                  │
  │                      │                       │                  │
  │                      │ 14. 取得 Profile       │                  │
  │                      ├───────────────────────>│                  │
  │                      │<───────────────────────┤                  │
  │                      │                       │                  │
  │                      │ 15. 查詢/建立 WP User  │                  │
  │                      ├───────────────────────┼─────────────────>│
  │                      │                       │                  │
  │                      │ 16. 儲存綁定資料       │                  │
  │                      │     (line_uid, email, │                  │
  │                      │      avatar_url)      │                  │
  │                      │                       │                  │
  │                      │ 17. WordPress 登入     │                  │
  │                      ├───────────────────────┼─────────────────>│
  │                      │                       │                  │
  │                      │ 18. 觸發 Hook          │                  │
  │                      │     line_hub/user_logged_in             │
  │                      ├───────────────────────┼─────────────────>│
  │                      │                       │  (其他外掛可監聽)  │
  │                      │                       │                  │
  │ 19. 登入完成，重導向  │                       │                  │
  │<─────────────────────┤                       │                  │
```

**關鍵細節（從 NSL 移植）**：

1. **State Token CSRF 防護**
   ```php
   // 產生 State Token
   $state = wp_generate_password(32, false);
   set_transient('line_hub_state_' . $state, [
       'redirect_to' => $redirect_to,
       'created_at' => time()
   ], 600); // 10 分鐘過期
   ```

2. **Force Reauth 機制**
   ```php
   // 從 NSL 移植
   if (get_option('line_hub_force_reauth') == 1) {
       $auth_url .= '&prompt=consent'; // 每次都要求授權
   }
   ```

3. **Email 驗證與重新授權**
   ```php
   // 從 ID Token 取得 Email
   $id_token = $response['id_token'];
   $decoded = verify_id_token($id_token);

   if (empty($decoded['email'])) {
       // Email 無效，觸發重新授權或手動輸入
       if ($force_reauth_enabled) {
           // 重新導向到授權頁面，要求 email scope
           redirect_to_reauth();
       } else {
           // 顯示手動輸入表單
           show_email_input_form();
       }
   }
   ```

4. **LINE 內部瀏覽器支援**
   ```php
   // 從 NSL 移植的特殊參數
   $params = [
       'bot_prompt' => get_option('line_hub_bot_prompt'), // normal/aggressive
       'initial_amr_display' => get_option('line_hub_initial_amr'), // lineqr/lineautologin
       'ui_locales' => 'zh-TW',
   ];

   if (get_option('line_hub_switch_amr') == 1) {
       $params['disable_auto_login'] = 'false'; // 允許切換登入方法
   }

   if (get_option('line_hub_allow_auto_login') == 1) {
       $params['allow_auto_login'] = 'true';
   }
   ```

### 2. Webhook 處理流程

```
LINE Platform        LINE Hub               WordPress        其他外掛
     │                   │                      │               │
     │ 1. 發送 Webhook   │                      │               │
     ├──────────────────>│                      │               │
     │                   │ 2. 驗證 HMAC 簽名     │               │
     │                   ├──┐                   │               │
     │                   │<─┘                   │               │
     │                   │                      │               │
     │                   │ 3. 立即返回 200 OK    │               │
     │<──────────────────┤    (< 100ms)         │               │
     │                   │                      │               │
     │                   │ 4. 去重檢查           │               │
     │                   │    (Transient)       │               │
     │                   ├──┐                   │               │
     │                   │<─┘                   │               │
     │                   │                      │               │
     │                   │ 5. 儲存到 Webhook 表  │               │
     │                   ├──────────────────────>│               │
     │                   │                      │               │
     │                   │ 6. 排程背景任務       │               │
     │                   │    wp_schedule_single_event()       │
     │                   ├──────────────────────>│               │
     │                   │                      │               │
     │                   │                      │ 7. Cron 執行   │
     │                   │<─────────────────────┤               │
     │                   │                      │               │
     │                   │ 8. 解析事件類型       │               │
     │                   ├──┐                   │               │
     │                   │<─┘                   │               │
     │                   │                      │               │
     │                   │ 9. 觸發對應 Hook      │               │
     │                   │    line_hub/webhook/{event_type}    │
     │                   ├──────────────────────┼──────────────>│
     │                   │                      │  (其他外掛監聽)│
```

**效能保證**：

- Webhook 接收：< 100ms
- 背景處理：< 300ms（目標）
- 去重機制：Transient 快取 60 秒

### 3. 通知發送流程

```
觸發源              LINE Hub            LINE Platform
(BuyGo/FluentCart)     │                    │
     │                  │                    │
     │ 1. 觸發 Hook      │                    │
     │  buygo/shipment/ │                    │
     │  marked_as_shipped                    │
     ├─────────────────>│                    │
     │                  │ 2. 查詢用戶綁定     │
     │                  │    (line_uid)      │
     │                  ├──┐                 │
     │                  │<─┘                 │
     │                  │                    │
     │                  │ 3. 套用訊息模板     │
     │                  │    替換變數         │
     │                  ├──┐                 │
     │                  │<─┘                 │
     │                  │                    │
     │                  │ 4. 觸發 Filter      │
     │                  │    line_hub/message│
     │                  │    /before_send    │
     │                  │    (其他外掛可修改) │
     │                  ├──┐                 │
     │                  │<─┘                 │
     │                  │                    │
     │                  │ 5. 發送訊息         │
     │                  ├────────────────────>│
     │                  │                    │
     │                  │ 6. 記錄到 notifications 表
     │                  ├──┐                 │
     │                  │<─┘                 │
     │                  │                    │
     │                  │ 7. 觸發 Hook        │
     │                  │    line_hub/message│
     │                  │    /sent           │
     │                  ├──┐                 │
     │                  │<─┘                 │
```

---

## 命名空間與 Hook 策略

### 命名空間設計

```php
// 主命名空間
namespace LineHub;

// 認證模組
namespace LineHub\Auth;
class OAuthClient {}
class StateManager {}

// 服務層
namespace LineHub\Services;
class AuthService {}
class UserService {}
class MessagingService {}
class NotificationService {}
class IntegrationService {}  // ⭐ 新增：串接服務

// API 層
namespace LineHub\API;
class Webhook_API {}
class Login_API {}
class Binding_API {}
class Integration_API {}     // ⭐ 新增：串接 API

// 整合層 ⭐ 新增
namespace LineHub\Integrations;
class HookRegistry {}        // Hook 註冊中心
class EventDispatcher {}     // 事件分發器
class PluginConnector {}     // 外掛連接器

// 管理後台
namespace LineHub\Admin;
class Admin_Menu {}
class Settings_Page {}
```

### Hook 優先級策略

| Hook | 優先級 | 原因 |
|------|--------|------|
| `init` | 15 | 晚於 NSL (10)，確保不覆蓋 |
| `plugins_loaded` | 20 | 載入語言檔案 |
| 外部 Hook 監聽 | 20 | 讓其他外掛優先處理 |
| 內部 Hook 觸發 | 10 | 標準優先級 |

### 對外提供的 Hooks

```php
// ========================================
// Actions (do_action)
// ========================================

// 用戶登入完成
do_action('line_hub/user_logged_in', $user_id, $line_uid, $profile_data);
// 參數:
//   $user_id: WordPress User ID
//   $line_uid: LINE User ID
//   $profile_data: array (display_name, picture_url, email)

// Webhook 接收到訊息
do_action('line_hub/webhook/message', $event, $user_id);
do_action('line_hub/webhook/follow', $event, $user_id);
do_action('line_hub/webhook/unfollow', $event, $user_id);
do_action('line_hub/webhook/postback', $event, $user_id);

// 通知發送完成
do_action('line_hub/message/sent', $user_id, $message, $response);
do_action('line_hub/message/failed', $user_id, $message, $error);

// 綁定狀態變更
do_action('line_hub/binding/linked', $user_id, $line_uid);
do_action('line_hub/binding/unlinked', $user_id, $line_uid);

// ========================================
// Filters (apply_filters)
// ========================================

// 修改訊息內容（發送前）
$message = apply_filters('line_hub/message/before_send', $message, $user_id);

// 修改訊息模板
$template = apply_filters('line_hub/notification/template', $template, $scene);

// 修改綁定資料
$binding_data = apply_filters('line_hub/binding/data', $binding_data, $user_id);

// 是否允許發送通知
$allowed = apply_filters('line_hub/notification/allowed', true, $user_id, $scene);
```

### 監聽外部 Hooks

```php
// ========================================
// FluentCart 整合
// ========================================
add_action('fluent_cart/order_created', 'LineHub\Integrations\FluentCart::on_order_created', 20);
add_action('fluent_cart/order_completed', 'LineHub\Integrations\FluentCart::on_order_completed', 20);
add_action('fluent_cart/order_failed', 'LineHub\Integrations\FluentCart::on_order_failed', 20);

// ========================================
// BuyGo Plus One 整合
// ========================================
add_action('buygo/shipment/marked_as_shipped', 'LineHub\Integrations\BuyGo::on_shipment', 20, 2);
add_action('buygo/parent_order_completed', 'LineHub\Integrations\BuyGo::on_parent_completed', 20);

// ========================================
// WooCommerce 整合
// ========================================
add_action('woocommerce_order_status_completed', 'LineHub\Integrations\WooCommerce::on_order_completed', 20);
add_action('woocommerce_order_status_failed', 'LineHub\Integrations\WooCommerce::on_order_failed', 20);

// ========================================
// WordPress Core 整合
// ========================================
add_action('user_register', 'LineHub\Integrations\WordPress::on_user_register', 20);
add_action('retrieve_password', 'LineHub\Integrations\WordPress::on_password_reset', 20);
add_action('password_reset', 'LineHub\Integrations\WordPress::on_password_changed', 20);
```

---

## 外掛串接系統架構 ⭐

### 設計理念

LINE Hub 作為「整合中樞」，提供三種串接方式：

1. **被動監聽**：監聽其他外掛的 Hook
2. **主動觸發**：提供 Hook 讓其他外掛監聽
3. **API 調用**：提供 REST API 讓外掛直接調用

### 串接架構

```
┌─────────────────────────────────────────────────────────┐
│                      LINE Hub 核心                       │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │           Hook Registry (註冊中心)                 │  │
│  │  • 記錄所有對外提供的 Hooks                        │  │
│  │  • 記錄監聽的外部 Hooks                           │  │
│  │  • 動態載入/卸載整合模組                          │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │         Event Dispatcher (事件分發器)              │  │
│  │  • 分發內部事件到外部 Hooks                        │  │
│  │  • 處理外部事件並分發到內部模組                    │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │        Plugin Connector (外掛連接器)               │  │
│  │  • FluentCart Connector                           │  │
│  │  • BuyGo Connector                                │  │
│  │  • WooCommerce Connector                          │  │
│  │  • Custom Connector (用戶自訂)                    │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
└─────────────────────────────────────────────────────────┘
         ▲                    │                    ▲
         │                    │                    │
         │                    ▼                    │
    ┌────┴────┐          ┌────────┐          ┌────┴────┐
    │FluentCart│         │ BuyGo  │          │  其他    │
    │         │         │ Plus 1 │          │  外掛    │
    └─────────┘          └────────┘          └─────────┘
```

### HookRegistry 實作

```php
namespace LineHub\Integrations;

class HookRegistry {
    /**
     * 所有可用的整合模組
     */
    private static $available_integrations = [
        'fluentcart' => [
            'class' => 'LineHub\\Integrations\\FluentCart',
            'name' => 'FluentCart',
            'hooks' => [
                'fluent_cart/order_created',
                'fluent_cart/order_completed',
            ],
            'enabled' => true,
        ],
        'buygo' => [
            'class' => 'LineHub\\Integrations\\BuyGo',
            'name' => 'BuyGo Plus One',
            'hooks' => [
                'buygo/shipment/marked_as_shipped',
                'buygo/parent_order_completed',
            ],
            'enabled' => true,
        ],
        'woocommerce' => [
            'class' => 'LineHub\\Integrations\\WooCommerce',
            'name' => 'WooCommerce',
            'hooks' => [
                'woocommerce_order_status_completed',
            ],
            'enabled' => false, // 預設關閉
        ],
    ];

    /**
     * 對外提供的 Hooks
     */
    private static $provided_hooks = [
        'line_hub/user_logged_in' => [
            'description' => '用戶透過 LINE 登入完成',
            'params' => ['user_id', 'line_uid', 'profile_data'],
        ],
        'line_hub/webhook/message' => [
            'description' => 'Webhook 接收到訊息',
            'params' => ['event', 'user_id'],
        ],
        'line_hub/message/sent' => [
            'description' => 'LINE 訊息發送完成',
            'params' => ['user_id', 'message', 'response'],
        ],
    ];

    /**
     * 初始化所有啟用的整合
     */
    public static function init() {
        foreach (self::$available_integrations as $key => $integration) {
            if ($integration['enabled']) {
                self::load_integration($key);
            }
        }
    }

    /**
     * 載入特定整合模組
     */
    public static function load_integration($key) {
        if (!isset(self::$available_integrations[$key])) {
            return false;
        }

        $integration = self::$available_integrations[$key];
        $class = $integration['class'];

        if (class_exists($class)) {
            $class::register_hooks();
            return true;
        }

        return false;
    }

    /**
     * 取得所有可用整合
     */
    public static function get_available_integrations() {
        return self::$available_integrations;
    }

    /**
     * 取得所有對外提供的 Hooks
     */
    public static function get_provided_hooks() {
        return self::$provided_hooks;
    }
}
```

### Plugin Connector 範例

```php
namespace LineHub\Integrations;

class FluentCart {
    /**
     * 註冊 Hooks
     */
    public static function register_hooks() {
        add_action('fluent_cart/order_created', [self::class, 'on_order_created'], 20);
        add_action('fluent_cart/order_completed', [self::class, 'on_order_completed'], 20);
    }

    /**
     * 處理訂單建立事件
     */
    public static function on_order_created($data) {
        // FluentCart 傳遞陣列
        $order = $data['order'] ?? null;
        if (!$order) {
            return;
        }

        // 取得用戶 LINE UID
        $user_id = $order->user_id;
        $line_uid = \LineHub\Services\UserService::get_line_uid($user_id);

        if (!$line_uid) {
            return; // 用戶未綁定 LINE
        }

        // 檢查是否啟用此通知場景
        $enabled = get_option('line_hub_notify_order_created', true);
        if (!$enabled) {
            return;
        }

        // 取得訊息模板
        $template = get_option('line_hub_template_order_created', '您的訂單 {order_id} 已建立！');

        // 替換變數
        $message = str_replace([
            '{order_id}',
            '{order_total}',
            '{customer_name}',
        ], [
            $order->id,
            $order->total,
            $order->customer->first_name,
        ], $template);

        // 允許其他外掛修改訊息
        $message = apply_filters('line_hub/message/before_send', $message, $user_id);

        // 發送通知
        \LineHub\Services\MessagingService::send_text($line_uid, $message);

        // 觸發完成 Hook
        do_action('line_hub/message/sent', $user_id, $message, null);
    }

    /**
     * 處理訂單完成事件
     */
    public static function on_order_completed($data) {
        // 類似實作...
    }
}
```

### 自訂整合範例

其他外掛可以這樣整合 LINE Hub：

```php
// 在你的外掛中
add_action('plugins_loaded', function() {
    // 檢查 LINE Hub 是否啟用
    if (!class_exists('LineHub\\Plugin')) {
        return;
    }

    // 監聽 LINE Hub 的用戶登入事件
    add_action('line_hub/user_logged_in', function($user_id, $line_uid, $profile) {
        // 你的邏輯：例如同步用戶資料到你的外掛
        update_user_meta($user_id, 'my_plugin_line_uid', $line_uid);
    }, 10, 3);

    // 或者觸發 LINE 通知
    add_action('my_plugin_course_enrolled', function($user_id, $course_id) {
        // 取得 LINE UID
        $line_uid = apply_filters('line_hub/get_line_uid', null, $user_id);

        if ($line_uid) {
            // 發送通知
            do_action('line_hub/send_message', [
                'line_uid' => $line_uid,
                'message' => '您已成功報名課程！',
                'context' => 'course_enrollment'
            ]);
        }
    }, 10, 2);
});
```

---

## NSL 功能移植細節

### 移植功能對照表

| NSL 功能 | NSL 實作位置 | LINE Hub 實作位置 | 狀態 |
|---------|-------------|------------------|------|
| OAuth Client | `line-client.php` | `LineHub\Auth\OAuthClient` | ⏸️ 待移植 |
| ID Token 驗證 | `line.php:145` | `LineHub\Auth\TokenValidator` | ⏸️ 待移植 |
| Email 擷取 | `line.php:148-150` | `LineHub\Services\AuthService::extractEmail()` | ⏸️ 待移植 |
| Force Reauth | `line.php:101-103` | `LineHub\Services\AuthService::forceReauth()` | ⏸️ 待移植 |
| Email 手動輸入 | `register-email-field.php` | `LineHub\Admin\EmailInputForm` | ⏸️ 待移植 |
| Avatar 同步 | `line.php:185-190` | `LineHub\Services\UserService::syncAvatar()` | ⏸️ 待移植 |
| Access Token 儲存 | `line.php:192-194` | `LineHub\Services\UserService::storeToken()` | ⏸️ 待移植 |
| Bot Prompt | `line.php:105-108` | `LineHub\Services\AuthService::setBotPrompt()` | ⏸️ 待移植 |
| Initial AMR | `line.php:110-113` | `LineHub\Services\AuthService::setInitialAMR()` | ⏸️ 待移植 |
| Switch AMR | `line.php:115-117` | `LineHub\Services\AuthService::setSwitchAMR()` | ⏸️ 待移植 |
| Auto Login | `line.php:119-121` | `LineHub\Services\AuthService::setAutoLogin()` | ⏸️ 待移植 |

### 核心程式碼移植

#### 1. OAuth Client (從 NSL 移植)

```php
namespace LineHub\Auth;

class OAuthClient {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $prompt = null; // 'consent' for force_reauth
    private $bot_prompt = null;
    private $initial_amr = null;
    private $ui_locales = 'zh-TW';

    /**
     * 取得授權 URL
     * (從 NSL line-client.php 移植)
     */
    public function getAuthorizationUrl($state) {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'state' => $state,
            'scope' => 'profile openid email',
            'ui_locales' => $this->ui_locales,
        ];

        // Force Reauth 支援
        if ($this->prompt) {
            $params['prompt'] = $this->prompt;
        }

        // LINE 內部瀏覽器支援
        if ($this->bot_prompt) {
            $params['bot_prompt'] = $this->bot_prompt;
        }

        if ($this->initial_amr) {
            $params['initial_amr_display'] = $this->initial_amr;
        }

        return 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params);
    }

    /**
     * 取得 Access Token
     */
    public function getAccessToken($code) {
        $response = wp_remote_post('https://api.line.me/oauth2/v2.1/token', [
            'body' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->redirect_uri,
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
            ]
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * 驗證 ID Token
     * (從 NSL line.php:145 移植)
     */
    public function verifyIdToken($id_token) {
        $response = wp_remote_post('https://api.line.me/oauth2/v2.1/verify', [
            'body' => [
                'id_token' => $id_token,
                'client_id' => $this->client_id,
            ]
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
```

#### 2. Email 處理邏輯 (從 NSL 移植)

```php
namespace LineHub\Services;

class AuthService {
    /**
     * 處理 Email 擷取
     * (從 NSL line.php:148-150 移植)
     */
    public function handleEmail($id_token_data, $profile_data) {
        // 優先從 ID Token 取得 Email
        $email = $id_token_data['email'] ?? '';

        // LINE 回傳的 Email 總是已驗證
        if (!empty($email) && is_email($email)) {
            return [
                'email' => $email,
                'verified' => true,
                'source' => 'id_token'
            ];
        }

        // Email 無效，檢查是否啟用強制重新授權
        $force_reauth = get_option('line_hub_force_reauth', 0);

        if ($force_reauth) {
            // 重新導向到授權頁面
            return [
                'action' => 'reauth',
                'reason' => 'email_missing'
            ];
        }

        // 顯示手動輸入表單
        return [
            'action' => 'manual_input',
            'reason' => 'email_missing'
        ];
    }

    /**
     * 強制重新授權
     */
    public function forceReauth() {
        $oauth_client = new \LineHub\Auth\OAuthClient();
        $oauth_client->setPrompt('consent'); // 強制顯示授權畫面

        $state = $this->generateState();
        $auth_url = $oauth_client->getAuthorizationUrl($state);

        wp_redirect($auth_url);
        exit;
    }
}
```

---

## 後台頁面結構

### Tab 導航

| Tab | 路由 | 優先級 | 功能 |
|-----|------|--------|------|
| 入門 | `?page=line-hub&tab=getting-started` | P0 | 快速開始引導 |
| 設定 | `?page=line-hub&tab=settings` | P0 | LINE Channel 設定 |
| 登入 | `?page=line-hub&tab=login` | P0 | LINE 登入設定（force_reauth 等）|
| 通知 | `?page=line-hub&tab=notifications` | P0 | 通知場景管理 |
| 串接 | `?page=line-hub&tab=integrations` | P1 | 外掛整合設定 ⭐ |
| Webhook | `?page=line-hub&tab=webhook` | P1 | Webhook 記錄和測試 |
| 用法 | `?page=line-hub&tab=usage` | P1 | Shortcode 和 API 文檔 |
| 進階 | `?page=line-hub&tab=advanced` | P2 | 效能、除錯設定 |

### 串接 Tab 設計 ⭐

```
┌─────────────────────────────────────────────────────────┐
│ LINE Hub > 串接                                          │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  可用的外掛整合                                           │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │ FluentCart                                 [✓]  │  │
│  │ 自動發送訂單通知                                  │  │
│  │                                                  │  │
│  │ 監聽 Hooks:                                      │  │
│  │ • fluent_cart/order_created                     │  │
│  │ • fluent_cart/order_completed                   │  │
│  │                                                  │  │
│  │ [設定通知訊息]                                    │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │ BuyGo Plus One                             [✓]  │  │
│  │ 自動發送出貨通知                                  │  │
│  │                                                  │  │
│  │ 監聽 Hooks:                                      │  │
│  │ • buygo/shipment/marked_as_shipped              │  │
│  │ • buygo/parent_order_completed                  │  │
│  │                                                  │  │
│  │ [設定通知訊息]                                    │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │ WooCommerce                                [  ]  │  │
│  │ WooCommerce 訂單通知                             │  │
│  │                                                  │  │
│  │ [啟用] [設定]                                     │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
│  ┌──────────────────────────────────────────────────┐  │
│  │ + 新增自訂整合                                     │  │
│  │   使用 Hooks 串接你的外掛                         │  │
│  │   [查看文檔]                                      │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 安全機制

### 1. OAuth 安全

- ✅ State Token CSRF 防護（10 分鐘過期）
- ✅ PKCE 支援（未來實作）
- ✅ Redirect URI 白名單驗證

### 2. Webhook 安全

- ✅ HMAC-SHA256 簽名驗證
- ✅ IP 白名單（LINE Platform IPs）
- ✅ Replay Attack 防護（Transient 去重）

### 3. API 安全

- ✅ WordPress Nonce 驗證
- ✅ 權限檢查（`manage_options`）
- ✅ Rate Limiting（60 req/min）
- ✅ Input Sanitization

### 4. 資料安全

- ✅ Channel Secret 加密儲存（AES-256）
- ✅ Access Token 加密儲存
- ✅ 敏感資料遮罩（Log 輸出）

---

## 效能優化

### 目標：0.3 秒處理完成

| 操作 | 目標時間 | 優化策略 |
|------|---------|---------|
| Webhook 接收 | < 100ms | 立即返回 200 OK |
| 背景處理 | < 300ms | wp_schedule_single_event() |
| API 查詢 | < 200ms | 資料庫索引、Transient 快取 |
| 訊息發送 | < 300ms | 非同步處理、批次發送 |

### 快取策略

```php
// 用戶綁定快取（1 小時）
$line_uid = get_transient('line_hub_binding_' . $user_id);
if (!$line_uid) {
    $line_uid = $wpdb->get_var(...);
    set_transient('line_hub_binding_' . $user_id, $line_uid, HOUR_IN_SECONDS);
}

// Webhook 去重（60 秒）
$key = 'line_hub_webhook_' . $event_id;
if (get_transient($key)) {
    return; // 重複事件
}
set_transient($key, true, 60);
```

---

## 驗收標準

### 功能驗收

- [ ] LINE 登入流程完整（含 force_reauth）
- [ ] Email 擷取與重新授權正常
- [ ] WordPress 帳號自動註冊成功
- [ ] 所有通知場景可正常發送
- [ ] Webhook 接收和處理正常
- [ ] 外掛串接系統運作正常
- [ ] 後台介面直覺易用

### 效能驗收

- [ ] Webhook 接收 < 100ms
- [ ] 背景處理 < 300ms
- [ ] API 回應 < 300ms
- [ ] 資料庫查詢已優化

### 安全驗收

- [ ] HMAC 簽名驗證正常
- [ ] State Token CSRF 防護正常
- [ ] 敏感資料已加密
- [ ] 無 SQL Injection 漏洞
- [ ] 無 XSS 漏洞

### 相容性驗收

- [ ] 與 NSL 共存無衝突
- [ ] 與 BuyGo Plus One 整合成功
- [ ] 與 FluentCart 整合成功
- [ ] 常見外掛組合測試通過

---

**文件版本**: 1.0
**建立日期**: 2026-02-06
**最後更新**: 2026-02-06 - 初版完成
