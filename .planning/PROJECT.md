# LINE Hub - WordPress LINE 整合中樞

## 專案概述

**專案名稱**: LINE Hub
**專案類型**: WordPress 外掛
**目標**: 成為 WordPress 的 LINE 整合中樞，提供完整的 LINE 登入、通知、Webhook 和第三方外掛串接功能

## 這是什麼

LINE Hub 是一個專為 WordPress 打造的 LINE 整合中樞外掛，提供：

1. **LINE 登入系統**（取代 NSL）
   - OAuth 2.0 認證流程
   - Email 擷取與驗證
   - WordPress 帳號自動註冊
   - 強制重新授權機制
   - LINE 內部瀏覽器支援

2. **統一通知系統**
   - 訂單通知（FluentCart、WooCommerce）
   - 出貨通知（BuyGo Plus One）
   - 會員通知（註冊歡迎、密碼重設）
   - 自訂通知場景

3. **Webhook 中心**
   - 統一的 Webhook 接收端點
   - 事件記錄和除錯
   - 去重機制

4. **外掛串接系統** ⭐ 核心特色
   - Hook-based 整合架構
   - 支援任何 WordPress 外掛串接
   - 類似 Zapier/n8n 的串接邏輯

## 為什麼建立這個

### 問題背景

當前 WordPress LINE 整合面臨的問題：

1. **NSL（Nextend Social Login）功能有限**
   - 只提供基本登入，無通知功能
   - 設定複雜，不直覺
   - 支援 20+ 平台，過於臃腫

2. **buygo-line-notify 功能分散**
   - 只處理通知，無登入功能
   - 依賴 NSL 進行 LINE 登入
   - 無統一的 Webhook 管理

3. **LINE 內部瀏覽器限制未處理**
   - Email 擷取失敗時無重新授權機制
   - 缺少 LINE Browser 特殊處理

4. **外掛整合困難**
   - 每個外掛需要單獨開發整合
   - 缺少統一的整合介面
   - 無法快速支援新外掛

### 解決方案

LINE Hub 提供：

- ✅ **獨立的 LINE 登入**：不依賴 NSL，專注 LINE 整合
- ✅ **完整的 Email 處理**：自動擷取、驗證、重新授權
- ✅ **統一的通知中心**：所有通知場景集中管理
- ✅ **強大的串接系統**：Hook-based 架構，支援任何外掛
- ✅ **直覺的後台介面**：參考 FluentCommunity/FluentCRM 風格

## 關鍵功能

### 1. LINE 登入系統（從 NSL 移植）

**功能清單**：

| 功能 | NSL 原始實作 | LINE Hub 實作 |
|------|-------------|---------------|
| OAuth 2.0 認證 | ✅ | ✅ 移植 |
| Email 擷取 | ✅（從 ID Token） | ✅ 移植 + 增強 |
| Email 驗證 | ✅（總是已驗證） | ✅ 移植 |
| Email 手動輸入 | ✅ | ✅ 移植 |
| 強制重新授權 | ✅ `force_reauth` | ✅ 移植 + UI 化 |
| WordPress 帳號註冊 | ✅ | ✅ 移植 |
| Avatar 同步 | ✅ | ✅ 移植 |
| Access Token 儲存 | ✅ | ✅ 移植 |

**LINE 內部瀏覽器支援**：

從 NSL 移植的特殊設定：

- `bot_prompt` - 控制 LINE Bot 提示行為
- `initial_amr_display` - 初始登入方法選擇
- `switch_amr` - 允許切換登入方法
- `allow_auto_login` - 自動登入支援

**重新授權機制**：

```
登入流程:
1. 用戶點擊「LINE 登入」
2. OAuth 認證（取得 Access Token + ID Token）
3. 驗證 ID Token 並取得 Email
4. 如果 Email 無效:
   ├─ 選項 A: 強制重新授權（force_reauth）
   │           重新取得授權並要求 email scope
   └─ 選項 B: 手動輸入 Email
               顯示表單讓用戶輸入
5. 建立或更新 WordPress 帳號
6. 登入完成
```

### 2. 統一通知系統

**通知場景**：

- 訂單通知（FluentCart、WooCommerce）
- 出貨通知（BuyGo Plus One）
- 會員通知（註冊歡迎、密碼重設）
- 產品上架通知
- 自訂通知場景

**訊息模板系統**：

- 支援變數替換：`{order_id}`, `{order_total}`, `{customer_name}`
- 後台視覺化編輯器
- 預覽功能

### 3. Webhook 中心

**功能**：

- 統一接收端點：`/wp-json/line-hub/v1/webhook`
- HMAC 簽名驗證
- 事件分類處理（message, follow, unfollow, postback）
- Webhook 記錄表（最近 100 筆）
- 去重機制（避免重複處理）

### 4. 外掛串接系統 ⭐

**核心設計理念**：

LINE Hub 作為「整合中樞」，提供標準化的 Hook 和 Filter，讓其他外掛可以輕鬆串接。

**對外提供的 Hooks**：

```php
// 用戶登入完成
do_action('line_hub/user_logged_in', $user_id, $line_uid, $profile_data);

// Webhook 接收到訊息
do_action('line_hub/webhook/message', $event, $user_id);

// 準備發送通知（可修改訊息內容）
apply_filters('line_hub/message/before_send', $message, $user_id);

// 通知發送完成
do_action('line_hub/message/sent', $user_id, $message, $response);
```

**監聽外部 Hooks**：

```php
// FluentCart 訂單
add_action('fluent_cart/order_created', 'LineHub\notify_order', 20);
add_action('fluent_cart/order_completed', 'LineHub\notify_order', 20);

// BuyGo Plus One 出貨
add_action('buygo/shipment/marked_as_shipped', 'LineHub\notify_shipment', 20);

// WooCommerce
add_action('woocommerce_order_status_completed', 'LineHub\notify_woo_order', 20);

// 會員
add_action('user_register', 'LineHub\notify_welcome', 20);
add_action('retrieve_password', 'LineHub\notify_password_reset', 20);
```

**串接範例**：

其他外掛可以這樣整合 LINE Hub：

```php
// 在你的外掛中觸發 LINE 通知
do_action('line_hub/send_message', [
    'user_id' => $user_id,
    'message' => '你的課程已開通：' . $course_name,
    'context' => 'course_enrollment'
]);

// 或監聽 LINE Hub 事件
add_action('line_hub/user_logged_in', function($user_id, $line_uid, $profile) {
    // 用戶透過 LINE 登入後，執行你的邏輯
    update_user_meta($user_id, 'line_profile', $profile);
});
```

## 技術架構

### 命名空間設計（避免衝突）

```php
// LINE Hub（新外掛）
namespace LineHub;
namespace LineHub\Auth;         // LINE 登入認證
namespace LineHub\Services;     // 商業邏輯服務
namespace LineHub\API;          // REST API
namespace LineHub\Admin;        // 後台管理
namespace LineHub\Integrations; // 外掛串接

// BuyGo LINE Notify（舊外掛，可共存）
namespace BuygoLineNotify;

// NSL（可共存）
class NextendSocialLogin;
```

### Hook 優先級策略

```php
// LINE Hub 的 init Hook 優先級設為 15
add_action('init', 'LineHub\Plugin::init', 15);  // NSL 是 10

// 監聽外部 Hook 時使用優先級 20
add_action('fluent_cart/order_created', 'LineHub\notify', 20);
```

### 資料表設計

| 資料表 | 用途 | 關鍵欄位 |
|--------|------|---------|
| `wp_line_hub_users` | LINE 用戶綁定 | line_uid, user_id, email, display_name, picture_url |
| `wp_line_hub_webhooks` | Webhook 記錄 | event_type, payload, processed, created_at |
| `wp_line_hub_settings` | 進階設定 | setting_group, setting_key, setting_value, encrypted |
| `wp_line_hub_notifications` | 通知記錄 | user_id, message, status, sent_at |

## 與現有系統的關係

### vs NSL（Nextend Social Login）

| 項目 | NSL | LINE Hub |
|------|-----|----------|
| 用途 | 多平台社交登入（20+ 平台） | 專注 LINE 整合 |
| 複雜度 | 高 | 低（只處理 LINE） |
| 通知功能 | ❌ 無 | ✅ 完整 |
| Webhook | ❌ 無 | ✅ 統一中心 |
| Email 重新授權 | ✅ `force_reauth` | ✅ 移植 + UI 化 |
| 外掛串接 | ❌ 無 | ✅ Hook-based 架構 |
| **可否共存** | ✅ 可以 | ✅ 可以（命名空間隔離） |

### vs buygo-line-notify

| 項目 | buygo-line-notify | LINE Hub |
|------|------------------|----------|
| LINE 登入 | ❌ 依賴 NSL | ✅ 獨立實現 |
| 通知功能 | ✅ 訂單、出貨 | ✅ 訂單、出貨、會員、自訂 |
| Webhook | ✅ 基本處理 | ✅ 統一中心、記錄、除錯 |
| 外掛串接 | ❌ 無 | ✅ Hook-based 架構 |
| **遷移計畫** | 3 階段遷移 | 最終取代 |

### 與 BuyGo Plus One 整合

LINE Hub 將成為 BuyGo Plus One 的通知發送引擎：

```php
// BuyGo Plus One 觸發出貨通知
do_action('buygo/shipment/marked_as_shipped', $shipment_id, $order_id);

// LINE Hub 監聽並發送通知
add_action('buygo/shipment/marked_as_shipped', function($shipment_id, $order_id) {
    $user_id = get_order_user_id($order_id);
    $line_uid = LineHub\Services\UserService::get_line_uid($user_id);

    if ($line_uid) {
        LineHub\Services\MessagingService::send_message($line_uid, [
            'type' => 'text',
            'text' => "您的訂單 #{$order_id} 已出貨！"
        ]);
    }
}, 20, 2);
```

## 開發規範

### WordPress 規範

- ✅ 符合 WordPress Coding Standards
- ✅ 使用 `$wpdb->prepare()` 防止 SQL Injection
- ✅ 使用 `sanitize_*()` 驗證輸入
- ✅ 使用 `esc_*()` 輸出轉義
- ✅ 使用 Transient API 快取
- ✅ 使用 Cron API 排程任務

### 安全機制

| 機制 | 應用場景 |
|------|---------|
| HMAC 簽名驗證 | Webhook 接收 |
| State Token | OAuth 認證（CSRF 防護） |
| Nonce 驗證 | REST API 請求 |
| AES-256 加密 | Channel Secret、Access Token |
| Rate Limiting | API 端點（60 req/min） |

### 效能要求

⚡ **0.3 秒處理目標**

所有訊息處理必須在 0.3 秒內完成：

- Webhook 接收：立即返回 200 OK（< 100ms）
- 背景處理：使用 `wp_schedule_single_event()`
- 資料庫查詢：加入適當索引
- API 快取：使用 Transient（1 小時）

## 後台設計

### 參考設計

- **風格**: FluentCommunity/FluentCRM
- **邏輯**: Zapier/n8n 串接概念

### Tab 結構

| Tab | 用途 | 優先級 |
|-----|------|--------|
| 入門 | 快速開始引導 | P0 |
| 設定 | LINE Channel 設定 | P0 |
| 登入 | LINE 登入設定（重新授權等） | P0 |
| 通知 | 通知場景管理 | P0 |
| 串接 | 外掛整合設定 | P1 |
| Webhook | Webhook 記錄和測試 | P1 |
| 用法 | Shortcode 和 API 文檔 | P1 |
| 進階 | 效能、除錯設定 | P2 |

## 關鍵決策記錄

| 決策 | 選擇 | 理由 |
|------|------|------|
| 命名空間 | `LineHub` | 與 `BuygoLineNotify`、NSL 完全隔離 |
| 資料表前綴 | `wp_line_hub_` | 獨立管理，不依賴舊外掛 |
| OAuth 實作 | 獨立實現（移植 NSL） | 不依賴第三方，完全掌控 |
| Hook 優先級 | 15（init）、20（外部） | 晚於 NSL（10），確保相容 |
| Webhook 處理 | 非同步（立即返回 200） | 避免 LINE 超時重發 |
| 外掛串接 | Hook-based 架構 | 最大化彈性和擴展性 |
| Email 重新授權 | `force_reauth` + 手動輸入 | 從 NSL 移植並增強 UI |
| 效能目標 | 0.3 秒處理完成 | 確保良好用戶體驗 |

## 專案範圍

### v1.0 範圍（3 週）

**包含**：

- ✅ LINE 登入（完整移植 NSL 功能）
- ✅ Email 擷取與重新授權
- ✅ WordPress 帳號註冊
- ✅ 訂單/出貨/會員通知
- ✅ Webhook 中心
- ✅ 外掛串接系統（Hook-based）
- ✅ 直覺後台介面

**不包含**（v2+）：

- ❌ LIFF 功能
- ❌ Rich Menu 管理
- ❌ Flex Message 編輯器
- ❌ AI 自動回覆
- ❌ 多語言支援

### 成功標準

**技術標準**：

- [ ] 所有 API 回應時間 < 300ms
- [ ] Webhook 處理成功率 > 99%
- [ ] 與 NSL 共存無衝突
- [ ] 與 BuyGo Plus One 整合成功

**使用者標準**：

- [ ] LINE 登入流程 < 3 步驟
- [ ] 後台設定 < 5 分鐘完成
- [ ] 通知發送成功率 > 95%
- [ ] 客戶驗收通過

## 下一步

1. ✅ 建立 ARCHITECTURE.md（詳細架構設計）
2. ✅ 建立 DATABASE-SCHEMA.md（資料庫設計）
3. ✅ 建立 API-ENDPOINTS.md（API 規範）
4. ✅ 建立 INTEGRATION-SYSTEM.md（串接系統設計）
5. ⏸️ 執行 GSD new-project 流程
6. ⏸️ 開始 Week 1 開發

---

**文件版本**: 1.0
**建立日期**: 2026-02-06
**最後更新**: 2026-02-06 - 專案初始化
