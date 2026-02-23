# Architecture Research

**Domain:** WordPress LINE 整合外掛（LineHub v2.0 重構與擴展）
**Researched:** 2026-02-24
**Confidence:** HIGH（基於實際原始碼分析，非假設）

---

## 現況架構快照（變更前）

> 這份文件的核心問題：Phase A/B/C 如何與現有架構整合？

### 現有系統概覽

```
┌─────────────────────────────────────────────────────────────────┐
│                     LINE Hub WordPress Plugin                    │
├──────────────────────────┬──────────────────────────────────────┤
│     後台 (Admin)          │         前台 / API                    │
│  ┌─────────────────────┐ │  ┌──────────────────────────────┐    │
│  │  SettingsPage       │ │  │  PublicAPI (messages/*)      │    │
│  │  (3 Tab: 設定/登入/ │ │  │  Settings_API / User_API     │    │
│  │   開發者)            │ │  │  WebhookReceiver             │    │
│  │  UsersColumn        │ │  └──────────────────────────────┘    │
│  └─────────────────────┘ │  ┌──────────────────────────────┐    │
├──────────────────────────┤  │  Auth (OAuth + LIFF)         │    │
│     服務層 (Services)     │  │  Integration (LoginButton)   │    │
│  SettingsService         │  └──────────────────────────────┘    │
│  UserService             │                                       │
│  MessagingService        │  ┌──────────────────────────────┐    │
│  IntegrationHooks  ←─────┼──│  WP Actions/Filters          │    │
│  LoginService            │  │  line_hub/send/text          │    │
│  ContentService          │  │  line_hub/send/flex          │    │
├──────────────────────────┤  │  line_hub/send/broadcast     │    │
│     資料層 (Database)     │  │  line_hub/user/is_linked     │    │
│  wp_line_hub_settings    │  └──────────────────────────────┘    │
│  wp_line_hub_users       │                                       │
│  wp_line_hub_webhooks    │                                       │
└──────────────────────────┴───────────────────────────────────────┘
```

### 現有元件狀態表

| 元件 | 檔案 | 行數 | 狀態 |
|------|------|------|------|
| `Plugin` | `includes/class-plugin.php` | ~777 | 穩定（不改） |
| `SettingsPage` | `includes/admin/class-settings-page.php` | 441 | Phase B 重構 |
| `SettingsService` | `includes/services/class-settings-service.php` | 653 | Phase A1 修復 |
| `IntegrationHooks` | `includes/services/class-integration-hooks.php` | 161 | 已完成 |
| `PublicAPI` | `includes/api/class-public-api.php` | 319 | 已完成 |
| `UsersColumn` | `includes/admin/class-users-column.php` | 139 | 已完成 |
| Views: tab-settings | `includes/admin/views/tab-settings.php` | 未知 | Phase B 重整 |
| Views: tab-login | `includes/admin/views/tab-login.php` | 未知 | Phase B 重整 |
| Views: tab-developer | `includes/admin/views/tab-developer.php` | 未知 | Phase B 保留 |

---

## Phase A 架構整合分析

### A1. SettingsService array 序列化 Bug

**問題根因（已確認於 class-settings-service.php）：**

```
set() 呼叫流程（修復前）：
  傳入 ['fluentcart', 'woocommerce']
      ↓ cast_value() — array 類型，直接回傳 array
      ↓ encrypt() — 不加密，跳過
      ↓ $wpdb->replace() — PHP array → 存入 "Array" 字串
      ↓ 下次 get() → json_decode("Array") → null → [] 空陣列
```

**修復後資料流：**

```
set() 呼叫流程（修復後）：
  傳入 ['fluentcart', 'woocommerce']
      ↓ cast_value() — 確保是 array
      ↓ json_encode() — 新增：轉為 JSON 字串
      ↓ encrypt() — 若 encrypted=true 則加密
      ↓ $wpdb->replace() — 存入 '["fluentcart","woocommerce"]'
      ↓ 下次 get() → cast_value() → json_decode() → array 正確回傳
```

**改動邊界：** 只改 `class-settings-service.php` 的 `set()` 方法，行 395-399 區塊。修復已實作（當前代碼行 398-399 已有 json_encode）。

**確認狀態：** 查閱現有代碼，array 序列化邏輯已實作（行 398: `$value = json_encode($value, JSON_UNESCAPED_UNICODE)`）。此 Bug 可能已在某次提交中修復，需驗證實際行為。

### A2. UsersColumn（已完成）

**整合點：** `class-plugin.php` 行 98 已有 `Admin\UsersColumn::init()` 呼叫。`UsersColumn` 類別已存在於 `includes/admin/class-users-column.php`（139 行）。**Phase A2 已完成，無需再做。**

### A3. 通知 Tab 移除（已完成）

**確認狀態：** 查閱現有 `class-settings-page.php`，TABS 常數只有：
```php
private const TABS = [
    'settings'  => '設定',
    'login'     => '登入',
    'developer' => '開發者',
];
```
通知 Tab 已不存在。**Phase A3 已完成，無需再做。**

---

## Phase B 架構整合分析

### 現況 vs 目標結構對比

**變更前（現在）：**

```
includes/admin/
├── class-settings-page.php      # 441 行，含 render 方法
├── class-users-column.php       # 139 行（已完成）
└── views/
    ├── tab-settings.php         # Tab 1: 設定
    ├── tab-login.php            # Tab 2: 登入
    └── tab-developer.php        # Tab 3: 開發者
```

**變更後（目標）：**

```
includes/admin/
├── class-settings-page.php      # 精簡至 <200 行（Tab 調度 + 表單儲存）
├── class-users-column.php       # 不動
└── views/
    ├── tab-wizard.php           # Tab 1: 設定嚮導（新）
    ├── tab-line-settings.php    # Tab 2: LINE 設定（從 tab-settings.php 重整）
    ├── tab-login-settings.php   # Tab 3: 登入設定（從 tab-login.php 重整）
    ├── tab-webhooks.php         # Tab 4: Webhook（新拆出）
    └── tab-developer.php        # Tab 5: 開發者（現有保留）
```

### B 的 Tab 變更對應表

| 舊 Tab | 新 Tab | 動作 |
|--------|--------|------|
| 設定 (settings) | 設定嚮導 (wizard) | 重命名 + 加入 Callback URL / 連線測試 |
| 設定 (settings) | LINE 設定 (line-settings) | 拆出 API 設定區塊 |
| 登入 (login) | 登入設定 (login-settings) | 重命名（內容大致保留） |
| — | Webhook (webhooks) | 新增（從「開發者」Tab 分離事件記錄） |
| 開發者 (developer) | 開發者 (developer) | 保留，加入 REST API 端點文件 |

### SettingsPage 重構後的責任分工

重構後 `class-settings-page.php` 只做 3 件事：

```
SettingsPage::init()
    ↓ register_menu()               — 選單註冊
    ↓ enqueue_assets()              — CSS/JS 載入
    ↓ render_page()
        → 渲染 Tab 導航列
        → switch($tab) → require view file
    ↓ handle_save()
        → 驗證 nonce
        → switch($tab) → 呼叫對應 save_*_tab()
    ↓ handle_test_connection()      — 保留
    ↓ handle_generate_api_key()     — 保留
    ↓ handle_revoke_api_key()       — 保留
```

每個 view 檔案只做 2 件事：

```
tab-*.php
    → $settings = SettingsService::get_group('...')
    → echo HTML (WordPress form-table 模式)
    → 每個 Tab 獨立 <form>，含 hidden tab 識別欄位
```

### B 的表單儲存架構

**變更前（共用一個 form）：**

```
<form action="admin-post.php">
    <!-- 所有 Tab 的所有欄位都在這裡 -->
    <input name="tab" value="settings">
    <!-- Tab 2 的欄位 -->
    <!-- Tab 3 的欄位 -->
    <!-- 儲存一次 = 處理全部欄位 = 互相干擾 -->
</form>
```

**變更後（每 Tab 獨立 form）：**

```
Tab 2 view:
<form action="admin-post.php" method="post">
    <input type="hidden" name="action" value="line_hub_save_settings">
    <input type="hidden" name="tab" value="line-settings">
    <input type="hidden" name="section" value="messaging">
    <!-- 只有 LINE API 設定欄位 -->
</form>

Tab 3 view:
<form action="admin-post.php" method="post">
    <input type="hidden" name="action" value="line_hub_save_settings">
    <input type="hidden" name="tab" value="login-settings">
    <!-- 只有登入按鈕設定欄位 -->
</form>
```

`handle_save()` 根據 `$_POST['tab']` 只處理對應的欄位，互不干擾。

---

## Phase C 架構整合分析

### C 的現況確認

查閱實際代碼後，**Phase C 的核心元件已完整實作**：

| 目標 | 狀態 | 檔案 |
|------|------|------|
| `line_hub/send/text` hook | 已完成 | `class-integration-hooks.php` |
| `line_hub/send/flex` hook | 已完成 | `class-integration-hooks.php` |
| `line_hub/send/broadcast` hook | 已完成 | `class-integration-hooks.php` |
| `line_hub/user/is_linked` filter | 已完成 | `class-integration-hooks.php` |
| `line_hub/user/get_line_uid` filter | 已完成 | `class-integration-hooks.php` |
| REST API `/messages/text` | 已完成 | `class-public-api.php` |
| REST API `/messages/flex` | 已完成 | `class-public-api.php` |
| REST API `/messages/broadcast` | 已完成 | `class-public-api.php` |
| REST API `/users/{id}/binding` | 已完成 | `class-public-api.php` |
| API Key 產生/撤銷 | 已完成 | `class-settings-page.php` |
| API Key 認證（`authenticate()`） | 已完成 | `class-public-api.php` |

**結論：Phase C 已提前實作完畢。** 只剩「開發者 Tab」中的使用記錄 UI 和文件更新尚待確認。

### C 的 API Key 認證資料流

```
外部請求
    ↓ HTTP Header: X-LineHub-API-Key: lhk_xxxxx
    ↓ PublicAPI::authenticate()
        → current_user_can('manage_options')? → 是 → 通過
        → 取 header X-LineHub-API-Key
        → SettingsService::get('integration', 'api_key_hash')
        → wp_hash($key) === $stored_hash? → 是 → 通過 / 否 → 401
    ↓ 呼叫 callback（send_text / send_flex / send_broadcast）
        → MessagingService::pushText / pushFlex / sendToMultiple
            → UserService::getLineUid($user_id)
            → LINE Messaging API HTTP 請求
    ↓ WP_REST_Response
```

### C 的 Hook 架構資料流

```
業務外掛（BuyGo / WebinarGo）
    ↓ do_action('line_hub/send/text', $args)
    ↓ IntegrationHooks::handle_send_text($args)
        → 驗證 user_id + message
        → MessagingService::pushText($user_id, $message)
            → UserService::getLineUid($user_id)   ← 查 line_hub_users 表
            → LINE Messaging API HTTP 請求
            → 回傳 true/false 或 WP_Error
        → error_log 記錄結果
```

---

## 資料流圖：變更前後對比

### 設定儲存流（Phase A + B 影響）

**變更前：**

```
用戶在 Tab 2 修改 LINE Channel ID
    ↓ 點「儲存」→ POST 所有欄位（含 Tab 3 的登入按鈕設定）
    ↓ handle_save() → save_settings_tab() + save_login_tab()
    ↓ SettingsService::set('general', 'login_button_positions', ['fluentcart'])
        → cast_value() → array 型別
        → json_encode()（此步驟已存在）
        → $wpdb->replace() → OK
    ↓ 但：若 Tab 2 表單沒帶登入按鈕欄位 → 意外覆寫為空陣列
```

**變更後：**

```
用戶在 Tab 2（LINE 設定）修改 LINE Channel ID
    ↓ 點「儲存」→ POST 只含 Tab 2 的欄位 + tab=line-settings
    ↓ handle_save() → 只呼叫 save_line_settings_tab()
    ↓ 只儲存 channel_id / channel_secret / access_token / liff_id
    ↓ 登入按鈕設定完全不受影響（分屬 Tab 3 的 form）
```

### 用戶列表 LINE 欄位流（Phase A2 已完成）

```
/wp-admin/users.php 載入
    ↓ UsersColumn::add_column() → 加入 'line_binding' 欄位
    ↓ 每列用戶 → UsersColumn::render_column($output, 'line_binding', $user_id)
        → get_binding_status($user_id)
            → 查 wp_line_hub_users (status='active')   → 找到 → 顯示 LINE Hub
            → 查 wp_social_users (type='line')          → 找到 → 顯示 NSL
            → 查 wp_buygo_line_users                    → 找到 → 顯示 Legacy
            → 都沒找到 → 顯示 "—"
        → 回傳 HTML（綠色勾選 + 來源標籤 / 灰色 dash）
```

---

## 元件邊界：新增 vs 修改

### Phase A

| 操作 | 元件 | 說明 |
|------|------|------|
| 驗證（非修改） | `class-settings-service.php` | array 序列化已存在，需驗證實際功能 |
| 已完成 | `class-users-column.php` | 已建立，已在 Plugin 中呼叫 |
| 已完成 | `class-settings-page.php` | 通知 Tab 已不存在 |

### Phase B

| 操作 | 元件 | 說明 |
|------|------|------|
| 修改 | `class-settings-page.php` | 精簡主類別，TABS 常數更新為 5 Tab |
| 修改 | `includes/admin/views/tab-settings.php` | 重命名並重組為設定嚮導（Tab 1） |
| 新增 | `includes/admin/views/tab-line-settings.php` | LINE API 設定（從 tab-settings 分拆） |
| 修改 | `includes/admin/views/tab-login.php` | 重命名為 tab-login-settings.php |
| 新增 | `includes/admin/views/tab-webhooks.php` | Webhook 事件記錄（從開發者 Tab 分拆） |
| 保留 | `includes/admin/views/tab-developer.php` | 開發者文件，加入 API 端點說明 |
| 修改 | `class-settings-page.php::handle_save()` | 新增 5 Tab 的 switch case |
| 新增 | `class-settings-page.php::save_line_settings_tab()` | 新 Tab 的儲存方法 |

### Phase C

| 操作 | 元件 | 說明 |
|------|------|------|
| 已完成 | `class-integration-hooks.php` | Hook 介面全部實作 |
| 已完成 | `class-public-api.php` | REST API 全部實作 |
| 驗證 | `includes/admin/views/tab-developer.php` | 確認 API Key UI 和使用記錄功能完整 |

---

## 建議實作順序（含依賴分析）

```
Step 1：驗證 Phase A（1 小時）
  1a. 測試 login_button_positions checkbox 儲存是否正常
      → 後台登入設定 → 勾選位置 → 儲存 → 重載頁面 → 確認勾選保留
  1b. 確認 /wp-admin/users.php 顯示 LINE 欄位
  1c. 確認通知 Tab 不存在

Step 2：驗證 Phase C（1 小時）
  2a. 用 curl 測試 REST API（需先在後台產生 API Key）
      POST /line-hub/v1/messages/text + X-LineHub-API-Key header
  2b. 測試無 Key 時回傳 401
  2c. 測試 do_action('line_hub/send/text', ...) 發送成功

Step 3：執行 Phase B（主要工作，2-4 小時）
  ← 依賴 Step 1（設定儲存正常才能重構表單）
  3a. 更新 TABS 常數為 5 個 Tab
  3b. 建立 tab-wizard.php（從 tab-settings.php 的嚮導部分）
  3c. 建立 tab-line-settings.php（API 設定部分）
  3d. 重整 tab-login.php → tab-login-settings.php
  3e. 建立 tab-webhooks.php（事件記錄）
  3f. 精簡 class-settings-page.php 主體
  3g. 每個 Tab view 改為獨立 <form>
  3h. handle_save() 新增 5 Tab 的 switch case
  3i. 驗證：所有現有設定值不受影響

Step 4：完成 Phase C 補充（如有需要，30 分鐘）
  ← 依賴 Step 3（Tab 穩定後才補充開發者文件）
  4a. tab-developer.php 加入 REST API 端點列表
  4b. 確認 API Key 使用記錄顯示（如尚未實作）
```

---

## 整合點清單

### 現有整合點（不動）

| 整合點 | 位置 | 說明 |
|--------|------|------|
| `line_hub/init` | `Plugin::on_init()` | 外掛初始化事件，其他外掛可 hook |
| `line_hub/user_logged_in` | 不明（需確認） | 登入完成事件，向後相容 |
| `[line_hub_login]` shortcode | 已停用（被 Integration\LoginButton 取代） | 向後相容保留 |
| `pre_get_avatar_data` | `Plugin::override_avatar_with_line()` | LINE 頭像覆蓋 |
| `line_hub_process_webhook` | WP-Cron | Webhook 非同步處理 |

### 新增整合點（Phase C 已完成）

| 整合點 | 類型 | 用途 |
|--------|------|------|
| `line_hub/send/text` | `do_action` | 其他外掛發送文字訊息 |
| `line_hub/send/flex` | `do_action` | 其他外掛發送 Flex 訊息 |
| `line_hub/send/broadcast` | `do_action` | 其他外掛批量發送 |
| `line_hub/user/is_linked` | `apply_filters` | 查詢用戶是否綁定 LINE |
| `line_hub/user/get_line_uid` | `apply_filters` | 取得用戶 LINE UID |
| `POST /line-hub/v1/messages/text` | REST API | 外部 SaaS 發送文字 |
| `POST /line-hub/v1/messages/flex` | REST API | 外部 SaaS 發送 Flex |
| `POST /line-hub/v1/messages/broadcast` | REST API | 外部 SaaS 批量發送 |
| `GET /line-hub/v1/users/{id}/binding` | REST API | 查詢綁定狀態 |
| `GET /line-hub/v1/users/lookup` | REST API | 用 email 查用戶 |

### 向後相容保護邊界

- 現有 `SettingsService::get() / set()` 介面不變
- `wp_line_hub_settings` 資料表結構不變
- `wp_line_hub_users` 資料表結構不變
- Tab URL 參數（`?page=line-hub-settings&tab=settings`）若重命名需要 redirect 處理

---

## 反模式警告

### 反模式 1：Phase B 改名 Tab 後遺忘 URL redirect

**問題：** 舊 Tab slug（`settings`, `login`）改名後，書籤或其他外掛生成的連結會 404。

**解決：** 在 `render_page()` 加入舊 slug 到新 slug 的 redirect 映射：

```php
$slug_aliases = [
    'settings' => 'wizard',
    'login'    => 'login-settings',
];
if (isset($slug_aliases[$current_tab])) {
    wp_redirect(add_query_arg('tab', $slug_aliases[$current_tab]));
    exit;
}
```

### 反模式 2：Phase B 重構中的設定群組混淆

**問題：** 現有設定分散在 `general` 和 `login` 兩個群組，但 Tab 結構不對應群組邊界。

**說明：** `general` 群組包含登入按鈕設定（應在 Tab 3），`login` 群組包含 OAuth 行為（也在 Tab 3）。Tab 重構不改 Schema，只改 view 中讀取哪個 group 的哪些 key。

**解決：** Tab 3（登入設定）的 view 同時讀取 `general` 和 `login` 兩個 group 的設定，`save_login_settings_tab()` 也同時存入兩個 group。

### 反模式 3：過早新增新 Schema Group

**問題：** Phase B 重構時想為新 Tab 建新設定群組。

**解決：** 不要。現有 `general / login / notification / integration` Schema 足夠，Phase B 只做 UI 重組，不加 Schema。Phase C 如需新設定才加。

### 反模式 4：在 view 檔案中寫儲存邏輯

**問題：** 為了方便把 save 邏輯寫在 view 裡。

**解決：** view 只輸出 HTML，所有 POST 處理留在 `SettingsPage::handle_save()` 的對應方法中。

---

## Scaling Considerations

（LineHub 作為 WordPress 外掛，Scale 主要指「支援多租戶或高流量通知」）

| Scale | 架構調整 |
|-------|----------|
| 單站 < 1000 通知/天 | 現有 MessagingService 直接呼叫已足夠 |
| 單站 > 5000 通知/天 | 改為 WP-Cron 批次佇列（Webhook 已有此模式） |
| 多外掛共用 LineHub | 現有 Hook 架構已支援，無需修改 |
| 外部 SaaS 整合 | 現有 PublicAPI + API Key 架構已支援 |

---

## 來源

- 實際原始碼分析（HIGH confidence）：
  - `includes/class-plugin.php`（主載入器）
  - `includes/admin/class-settings-page.php`（設定頁）
  - `includes/services/class-settings-service.php`（設定服務）
  - `includes/api/class-public-api.php`（公開 API）
  - `includes/services/class-integration-hooks.php`（Hook 介面）
  - `includes/admin/class-users-column.php`（用戶列表欄位）
- 重構計畫文件：`.planning/LINEHUB-RESTRUCTURE-PLAN.md`
- 專案上下文：`.planning/PROJECT.md`

---

*Architecture research for: LineHub v2.0 WordPress LINE Integration Plugin*
*Researched: 2026-02-24*
