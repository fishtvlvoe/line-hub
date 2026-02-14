# LINE Hub 開發計畫

> 最後更新：2026-02-15

## 現況盤點

### 已完成（Phase 1-3 + LIFF）

| 功能 | 狀態 | 說明 |
|------|------|------|
| 設定系統 | ✅ | 加密儲存、快取、Schema 驗證 |
| 用戶綁定 | ✅ | LINE UID ↔ WP User、NSL fallback |
| OAuth 登入 | ✅ | LINE Login、Email 收集、帳號合併 |
| LIFF 登入 | ✅ | LINE 內部瀏覽器登入、Email 收集、帳號合併 |
| REST API | ✅ | 設定 CRUD、用戶綁定查詢 |
| 資料庫 | ✅ | 4 張表（users, webhooks, settings, notifications）|

### 未完成（需從 BuyGo 遷移）

| 功能 | BuyGo 檔案 | LineHub 對應 |
|------|-----------|-------------|
| LINE 訊息發送 | class-notification-service.php | Phase 4: MessagingService |
| 通知模板 | class-notification-templates.php | Phase 4: TemplateEngine |
| 訂單通知 | class-line-order-notifier.php | Phase 4: NotificationTrigger |
| 出貨通知 | class-notification-handler.php | Phase 4: NotificationTrigger |
| Webhook 接收 | buygo-line-notify 外掛 | Phase 5: WebhookReceiver |
| Webhook 處理 | class-line-webhook-handler.php | Phase 5: EventDispatcher |
| 關鍵字回應 | class-line-keyword-responder.php | Phase 5: KeywordResponder |
| Flex 訊息 | class-line-flex-templates.php | Phase 5: FlexBuilder |
| 身份識別 | class-identity-service.php | Phase 6: IdentityService |
| 產品上架 | class-product-data-parser.php | Phase 6: BuyGo Connector |
| 登入按鈕 | class-fluentcart-product-page.php | Phase 6: LoginButton |
| 後台介面 | (無) | Phase 7: AdminUI |

---

## 架構設計原則

### 1. 程式碼隔離

```
line-hub/
├── assets/
│   ├── css/
│   │   ├── admin.css           # 後台共用樣式
│   │   ├── login-button.css    # 登入按鈕元件
│   │   └── liff.css            # LIFF 頁面樣式（從模板抽出）
│   └── js/
│       ├── admin.js            # 後台共用 JS
│       ├── login-button.js     # 登入按鈕元件
│       └── liff.js             # LIFF 頁面 JS（從模板抽出）
├── includes/
│   ├── class-plugin.php        # 主載入器（只註冊 hooks）
│   ├── class-database.php      # 資料表管理
│   ├── auth/                   # 認證模組
│   ├── liff/                   # LIFF 模組
│   ├── services/               # 商業邏輯（純 PHP）
│   ├── api/                    # REST API 端點
│   ├── messaging/              # Phase 4: 訊息發送
│   │   ├── class-messaging-service.php
│   │   ├── class-template-engine.php
│   │   └── class-flex-builder.php
│   ├── webhook/                # Phase 5: Webhook 處理
│   │   ├── class-webhook-receiver.php
│   │   ├── class-event-dispatcher.php
│   │   └── class-keyword-responder.php
│   ├── integration/            # Phase 6: 外掛串接
│   │   ├── class-fluentcart-connector.php
│   │   ├── class-buygo-connector.php
│   │   └── class-login-button.php
│   ├── admin/                  # Phase 7: 後台 UI
│   │   ├── class-admin-page.php
│   │   └── views/
│   └── templates/              # 共用 HTML 模板
│       ├── login-button.php
│       └── email-form.php
└── tests/
```

### 2. 設計哲學：熵減

1. **一個功能 = 一個類別** — 不超過 300 行
2. **Service 層無狀態** — 靜態方法，不依賴實例
3. **模板與邏輯分離** — PHP 處理邏輯，模板只渲染
4. **CSS/JS 外部化** — 從 PHP 模板抽出，支援自訂覆蓋
5. **Hook 驅動** — 外掛間透過 WordPress hooks 通訊，不直接呼叫

### 3. LINE OA 登入模式

LINE Login 和 LINE OA（Official Account）的整合有三個層級：

```
層級 1：純登入（目前已完成）
  用戶 → LINE Login → WordPress 帳號
  ❌ 用戶不會成為 LINE OA 好友
  ❌ 無法發送 LINE 通知

層級 2：登入 + 加好友（需啟用）
  用戶 → LINE Login (bot_prompt=aggressive) → WordPress 帳號
  ✅ 同時加入 LINE OA 好友
  ✅ 可發送 LINE 通知

層級 3：雙向互動（Phase 5 Webhook）
  用戶 ↔ LINE OA 聊天 ↔ WordPress
  ✅ 用戶在 LINE 聊天中操作（上架、查訂單）
  ✅ 系統主動推送通知
```

**目前設定已支援 bot_prompt**（在 SettingsService 的 login group），但需要：
- LINE Login Channel 和 LINE OA 必須互相連結（在 LINE Developers Console）
- 啟用 `bot_prompt: aggressive`（強制顯示加好友提示）

---

## 開發階段

### Phase 3.5：前端登入整合（可立即執行）

**目標**：在 FluentCart 產品頁和 FluentCommunity 加入 LINE 登入按鈕

**不需要等測試，因為：**
- 登入按鈕是獨立元件，不影響現有功能
- CSS/JS 可獨立開發和預覽
- 可用已有帳號直接測試

**工作項目：**

1. **登入按鈕元件**（`class-login-button.php` + `login-button.css`）
   - 可自訂文字（預設：「用 LINE 帳號登入」）
   - 可自訂位置（產品頁上方 / 結帳頁 / shortcode）
   - 置中設計，LINE 綠色品牌色
   - 支援 `[line_hub_login]` shortcode
   - 支援 `do_action('line_hub/render_login_button')` hook

2. **FluentCart 產品頁整合**（`class-fluentcart-connector.php`）
   - 未登入用戶顯示登入提示橫幅
   - 點擊後導向 LIFF（LINE 內）或 OAuth（外部瀏覽器）
   - 登入後自動回到原產品頁

3. **FluentCommunity 整合**
   - 在登入表單下方加入「LINE 登入」按鈕
   - 與 WordPress 原生登入頁整合

**交付物：**
- `assets/css/login-button.css`
- `assets/js/login-button.js`
- `includes/integration/class-login-button.php`
- `includes/integration/class-fluentcart-connector.php`
- `includes/templates/login-button.php`

### Phase 4：通知系統

**目標**：系統可以透過 LINE OA 發送通知

**前置條件**：LINE OA Channel 已設定

**工作項目：**
1. MessagingService — 封裝 LINE Messaging API（push/reply/multicast）
2. TemplateEngine — 訊息模板管理（變數替換、Flex 支援）
3. FlexBuilder — Flex Message 建構器（訂單卡片、出貨追蹤）
4. NotificationTrigger — 事件監聽（訂單建立、出貨、狀態變更）
5. NotificationLogger — 發送記錄和重試佇列

### Phase 5：Webhook 中心

**目標**：統一接收和處理 LINE Webhook 事件

**重點**：取代 `buygo-line-notify` 外掛的 webhook 功能

**工作項目：**
1. WebhookReceiver — HMAC 簽名驗證、事件接收
2. EventDispatcher — 事件分類和分發
3. KeywordResponder — 用戶指令處理（/ID, /help, /綁定）
4. WebhookLogger — 事件記錄（除錯用）

### Phase 6：外掛串接

**目標**：BuyGo 和 FluentCart 透過 hooks 與 LineHub 整合

**工作項目：**
1. BuyGo Connector — 產品上架、出貨通知的事件橋接
2. FluentCart Connector — 訂單事件監聽
3. Identity Bridge — 統一身份識別（seller/helper/buyer）
4. Migration Service — 從 buygo-line-notify 遷移資料

### Phase 7：後台管理介面

**目標**：一頁式設定頁，熵減設計

**設計方向**：
- WordPress 原生 Admin 樣式 + BuyGo 品牌色
- Tab 導航：基本設定 | 登入設定 | 通知管理
- 連線狀態即時回饋
- 不做 SPA，用原生 PHP 模板

---

## 優先執行順序

```
可立即執行（不需等測試）：
┌──────────────────────────────────────┐
│  1. 資產檔案結構建立                   │  ← 程式碼隔離基礎
│  2. LIFF CSS/JS 外部化                │  ← 從模板抽出
│  3. 登入按鈕元件                       │  ← Phase 3.5
│  4. FluentCart 產品頁整合              │  ← Phase 3.5
└──────────────────────────────────────┘

需要 LINE OA Channel 設定：
┌──────────────────────────────────────┐
│  5. MessagingService                  │  ← Phase 4
│  6. 通知模板和觸發器                   │  ← Phase 4
└──────────────────────────────────────┘

需要 buygo-line-notify 替換：
┌──────────────────────────────────────┐
│  7. Webhook 接收和處理                 │  ← Phase 5
│  8. BuyGo 連接器                      │  ← Phase 6
└──────────────────────────────────────┘

最後：
┌──────────────────────────────────────┐
│  9. Admin UI                          │  ← Phase 7
└──────────────────────────────────────┘
```

---

## 與 BuyGo 的整合時間線

### 短期（現在）
- LineHub 獨立處理登入（OAuth + LIFF）
- BuyGo 繼續使用 buygo-line-notify 處理訊息和通知
- 兩個系統共存，透過 `wp_line_hub_users` 表同步身份

### 中期（Phase 4-5 完成後）
- LineHub 接管訊息發送和 Webhook 接收
- BuyGo 的 LINE 功能透過 LineHub hooks 呼叫
- buygo-line-notify 外掛可停用

### 長期（Phase 6-7 完成後）
- BuyGo 完全不包含 LINE 程式碼
- 所有 LINE 功能由 LineHub 提供
- 一個後台介面管理所有 LINE 設定
