# LineHub 重構與擴展計畫

> 建立日期：2026-02-18
> 目標：將 LineHub 從「開發中半成品」升級為「可上架的 LINE 通訊平台」

---

## 現況分析

### LineHub 後台現狀（5 個 Tab）

| Tab | 狀態 | 問題 |
|-----|------|------|
| 入門 | 已完成 | 內容是教學步驟，但 Callback URL 等應該在「設定」Tab 裡 |
| 設定 | 已完成 | 包含 API 設定 + 登入按鈕 + 安全性，但缺少 LINE Login Channel 設定 |
| 通知 | 空殼 | 只有「待開發」文字，不應出現 |
| Webhook | 已完成 | 事件記錄瀏覽器，功能正常 |
| 用法 | 已完成 | 太偏開發者導向，一般用戶看不懂 |

### 已發現的 BUG

**登入按鈕位置 checkbox 儲存失敗**：
- 根因：`SettingsService::set()` 對 array 類型的值，經過 `cast_value()` 後仍是 PHP array
- 存入 `$wpdb->replace()` 時，`setting_value` 欄位是 longtext，PHP array 被轉為字串 `"Array"`
- 讀取時 `json_decode("Array")` 返回 null → 回傳空陣列 `[]`
- **修復方案**：在 `set()` 方法中，對 array 類型的值先 `json_encode()` 再存入

### 架構原則回顧

- **BGO**：純 ERP（產品、訂單、出貨管理）+ 通知模板（業務內容）
- **LineHub**：所有 LINE 功能（登入、LIFF、Webhook、訊息發送、用戶身份）
- LineHub 不存業務外掛的模板，只管通道（怎麼送）
- 外掛間通訊只透過 WordPress hooks

---

## 計畫總覽

```
Phase A：緊急修復（BUG + 缺失功能）
Phase B：後台 Tab 重構（UX 提升）
Phase C：擴展架構（Integration Platform）
```

---

## Phase A：緊急修復

> 預計改動：3 個檔案，新增 1 個檔案

### A1. 修復 SettingsService array 儲存 BUG

**檔案**：`line-hub/includes/services/class-settings-service.php`

**問題**：`set()` 方法行 310-324，array 類型的值直接傳入 `$wpdb->replace()`

**修復**：

```php
// set() 方法中，在 $wpdb->replace 之前
// 行 310 後，加入 array 序列化：
if ($config['type'] === 'array') {
    $stored_value = json_encode($value, JSON_UNESCAPED_UNICODE);
} elseif ($encrypted) {
    $stored_value = self::encrypt($value);
} else {
    $stored_value = $value;
}
```

**驗證**：勾選按鈕位置 → 儲存 → 重新載入頁面 → checkbox 保持勾選狀態

### A2. WordPress 用戶列表新增 LINE 綁定狀態欄

**新建檔案**：`line-hub/includes/admin/class-users-column.php`

**功能**：
- 在 `/wp-admin/users.php` 新增「LINE」欄位
- 查詢三張身份表（LineHub → NSL → buygo_line_users），顯示綁定來源
- 已綁定：顯示綠色 LINE icon + 來源標籤（LINE Hub / NSL）
- 未綁定：顯示灰色 dash

**技術實現**：
```php
namespace LineHub\Admin;

class UsersColumn {
    public static function init(): void {
        add_filter('manage_users_columns', [self::class, 'add_column']);
        add_filter('manage_users_custom_column', [self::class, 'render_column'], 10, 3);
        add_action('admin_head-users.php', [self::class, 'inline_css']);
    }

    public static function add_column($columns) {
        $columns['line_binding'] = 'LINE';
        return $columns;
    }

    public static function render_column($output, $column_name, $user_id) {
        if ($column_name !== 'line_binding') return $output;
        // 依序查詢：line_hub_users → social_users → buygo_line_users
        // 返回綁定狀態 HTML
    }
}
```

**載入方式**：在 `class-plugin.php` 的 `register_admin_hooks()` 中呼叫 `UsersColumn::init()`

### A3. 移除通知 Tab 的「待開發」空殼

**檔案**：`line-hub/includes/admin/class-settings-page.php`

**改動**：
- 從 `TABS` 常數移除 `'notifications' => '通知'`
- 刪除 `render_notifications_tab()` 方法（行 495-509）
- 待 Phase 4 通知系統實際完成後再加回

---

## Phase B：後台 Tab 重構

> 預計改動：1 個大檔案拆分為多個小檔案

### 目標：仿照 NSL 的設計哲學

NSL 的優點：
1. 設定頁面使用標準 WordPress `form-table` 模式
2. 每個提供商有獨立的「入門指南」（step-by-step + 截圖）
3. 用戶列表直接顯示綁定狀態
4. 設定項目按功能分類，一目瞭然

### B1. Tab 結構重新設計

**舊 Tab**：入門 → 設定 → 通知 → Webhook → 用法

**新 Tab**：

| Tab | 名稱 | 用途 |
|-----|------|------|
| 1 | 設定嚮導 | 首次設定引導（Callback URL、LIFF URL 顯示 + 複製）|
| 2 | LINE 設定 | Channel ID / Secret / Access Token / LIFF ID |
| 3 | 登入設定 | 登入按鈕（文字/位置/大小）、LINE Login 行為、安全性 |
| 4 | Webhook | 事件記錄瀏覽器（現有功能）|
| 5 | 開發者 | Hooks/API 文件（原「用法」Tab，改名更清晰）|

**各 Tab 詳細設計**：

#### Tab 1：設定嚮導（Getting Started）
- 3 步驟快速開始（保留現有內容）
- Callback URL + LIFF URL 的「複製」按鈕
- 連線狀態檢查（從原「設定」Tab 的連線測試區塊移入）
- 完成後顯示「前往設定 →」按鈕

#### Tab 2：LINE 設定（LINE Settings）
- Channel ID
- Channel Secret（自動加密）
- Channel Access Token（自動加密）
- LIFF ID
- NSL 相容模式 checkbox
- NSL 自動遷移 checkbox

#### Tab 3：登入設定（Login Settings）
- **登入按鈕區塊**：
  - 按鈕文字
  - 按鈕位置（FluentCart / WP 登入頁 / FluentCommunity）
  - 按鈕大小（小/中/大）
- **LINE Login 行為區塊**（從 `login` 設定群組）：
  - 強制重新授權
  - 加好友行為（normal / aggressive）
  - 初始登入方法（QR Code / 自動登入 / Email+密碼）
  - 允許切換登入方法
  - 允許自動登入
- **安全性區塊**：
  - Email 驗證
  - 限制網域

#### Tab 4：Webhook（保持現有）
- 事件記錄瀏覽器（最近 20 筆）

#### Tab 5：開發者（Developer）
- Shortcodes 範例
- WordPress Hooks 文件
- PHP 範例程式碼
- REST API 端點列表

### B2. 檔案結構拆分（熵減）

現在 `class-settings-page.php` 有 **34,219 bytes**，嚴重超過 500 行上限。

**拆分方案**：

```
line-hub/includes/admin/
├── class-settings-page.php      # 主頁面載入器（Tab 導航 + dispatch）
├── views/
│   ├── tab-wizard.php           # Tab 1: 設定嚮導
│   ├── tab-line-settings.php    # Tab 2: LINE 設定
│   ├── tab-login-settings.php   # Tab 3: 登入設定
│   ├── tab-webhooks.php         # Tab 4: Webhook
│   └── tab-developer.php        # Tab 5: 開發者
```

`class-settings-page.php` 只負責：
1. 註冊選單
2. 渲染 Tab 導航列
3. `require` 對應的 view 檔案
4. 處理表單儲存（`handle_save()`）

每個 view 檔案：
- 純 PHP 模板，不包含 class
- 從 `SettingsService::get_group()` 取得設定值
- HTML + WordPress form-table 標準模式

### B3. 設定表單拆分

目前所有設定都在一個 `<form>` 裡。改為每個 Tab 獨立 `<form>`，避免 Tab 2 修改 LINE 設定時意外清空 Tab 3 的登入按鈕設定。

**改動**：
- `handle_save()` 改為根據提交的 `tab` 值，只儲存該 Tab 的欄位
- 每個 Tab 的 form 加入 `<input type="hidden" name="tab" value="line-settings">`

---

## Phase C：擴展架構（Integration Platform）

> 目標：讓任何 WordPress 外掛都能透過 LineHub 發送 LINE 通知

### C1. 核心概念：Hooks + REST API 雙通道

```
┌──────────────┐    WordPress Hook     ┌───────────┐    LINE API    ┌──────────┐
│  BuyGo       │ ───────────────────→ │  LineHub   │ ─────────────→│  LINE    │
│  FluentCart   │                      │  通訊中心   │               │  Server  │
│  WooCommerce │                      │            │               │          │
│  外部 SaaS   │ ── REST API ──────→ │            │               │          │
└──────────────┘                      └───────────┘               └──────────┘
```

### C2. Hook 介面（WordPress 外掛使用）

**發送文字訊息**：
```php
do_action('line_hub/send/text', [
    'user_id'  => 123,           // WordPress User ID
    'message'  => '訂單已建立',
]);
```

**發送 Flex 訊息**：
```php
do_action('line_hub/send/flex', [
    'user_id'  => 123,
    'alt_text' => '訂單通知',
    'contents' => [ /* Flex Message JSON */ ],
]);
```

**批量發送**：
```php
do_action('line_hub/send/broadcast', [
    'user_ids' => [123, 456, 789],
    'message'  => '全站公告',
]);
```

**查詢用戶綁定**：
```php
$is_linked = apply_filters('line_hub/user/is_linked', false, $user_id);
$line_uid  = apply_filters('line_hub/user/get_line_uid', '', $user_id);
```

### C3. REST API 介面（外部 SaaS 使用）

**端點設計**：

| 方法 | 端點 | 用途 |
|------|------|------|
| POST | `/line-hub/v1/messages/text` | 發送文字訊息 |
| POST | `/line-hub/v1/messages/flex` | 發送 Flex 訊息 |
| POST | `/line-hub/v1/messages/broadcast` | 批量發送 |
| GET  | `/line-hub/v1/users/{id}/binding` | 查詢綁定狀態 |
| GET  | `/line-hub/v1/stats` | 統計資料 |

**認證方式**：
- WordPress 內部：Cookie + Nonce（管理員權限）
- 外部 SaaS：API Key 認證
  - 在後台產生 API Key（儲存在 `wp_line_hub_settings`，加密）
  - 請求時帶 `X-LineHub-API-Key` header

### C4. 新建檔案清單

```
line-hub/includes/
├── api/
│   ├── class-messages-api.php     # 訊息發送 REST API
│   └── class-stats-api.php        # 統計 REST API
├── services/
│   └── class-integration-hooks.php # Hook 註冊與處理
```

**`IntegrationHooks` 類別**：
- `init()` — 註冊所有 `line_hub/*` action/filter hooks
- `handle_send_text($args)` — 處理文字訊息發送
- `handle_send_flex($args)` — 處理 Flex 訊息發送
- `handle_broadcast($args)` — 處理批量發送
- `filter_is_linked($default, $user_id)` — 回傳綁定狀態
- `filter_get_line_uid($default, $user_id)` — 回傳 LINE UID

### C5. API Key 管理

**後台界面**（加入 Tab 5「開發者」）：
- 產生 API Key 按鈕
- 顯示已產生的 Key（遮罩顯示，可複製）
- 撤銷 Key 功能
- 使用記錄（最近 20 次 API 呼叫）

**Schema 新增**：
```php
'integration' => [
    'api_key' => [
        'type' => 'string',
        'encrypted' => true,
        'default' => '',
    ],
    'api_key_created_at' => [
        'type' => 'string',
        'encrypted' => false,
        'default' => '',
    ],
]
```

---

## 執行順序與依賴

```
Phase A（緊急修復）
├── A1. SettingsService BUG 修復     ← 無依賴，立即可做
├── A2. 用戶列表 LINE 欄位           ← 無依賴，可與 A1 平行
└── A3. 移除通知 Tab 空殼            ← 無依賴，可與 A1 平行

Phase B（Tab 重構）← 依賴 A1（設定儲存正常）
├── B1. Tab 結構重新設計              ← 設計階段
├── B2. 檔案結構拆分                  ← 依賴 B1 設計
└── B3. 設定表單拆分                  ← 依賴 B2 拆分完成

Phase C（擴展架構）← 依賴 B 完成（Tab 穩定）
├── C1-C2. Hook 介面設計             ← 設計階段
├── C3. REST API 端點                ← 依賴 C2 Hook 層
├── C4. 新建檔案                     ← 實作階段
└── C5. API Key 管理                 ← 依賴 C4 完成
```

---

## 風險與注意事項

1. **SettingsService BUG 影響範圍**：目前只有 `login_button_positions` 是 array 類型，修復後需確認其他類型的設定不受影響

2. **用戶列表查詢效能**：三張身份表的 LEFT JOIN 可能在大量用戶時變慢，建議使用 `getBindingsBatch()` 方法（LineHub UserService 已提供）

3. **Tab 重構的回歸測試**：
   - 所有現有設定值在重構後必須保持不變
   - 測試連線功能移到「設定嚮導」Tab 後仍正常運作
   - Webhook 記錄瀏覽器不受影響

4. **API Key 安全性**：
   - Key 必須使用 AES-256-CBC 加密儲存（已有基礎設施）
   - Rate limiting（建議每分鐘 60 次）
   - 每次 API 呼叫記錄 IP 和時間戳

5. **向後相容**：
   - 現有的 `[line_hub_login]` shortcode 不變
   - 現有的 `line_hub/user_logged_in` hook 不變
   - 只新增 hook，不修改已有的

---

## 驗證清單

### Phase A 驗證
- [ ] 勾選登入按鈕位置 → 儲存 → 重新載入 → checkbox 保持勾選
- [ ] `/wp-admin/users.php` 顯示 LINE 欄位
- [ ] 已綁定用戶顯示綠色 icon + 來源（LINE Hub / NSL）
- [ ] 通知 Tab 已移除

### Phase B 驗證
- [ ] 5 個新 Tab 正常切換
- [ ] 各 Tab 設定獨立儲存，互不影響
- [ ] 連線測試功能在「設定嚮導」Tab 正常運作
- [ ] Webhook 記錄正常顯示
- [ ] `class-settings-page.php` < 300 行
- [ ] 每個 view 檔案 < 300 行

### Phase C 驗證
- [ ] `do_action('line_hub/send/text', ...)` 成功發送 LINE 訊息
- [ ] `do_action('line_hub/send/flex', ...)` 成功發送 Flex 訊息
- [ ] REST API 用 API Key 認證成功
- [ ] REST API 無 Key 時返回 401
- [ ] 後台可產生/撤銷 API Key

---

## 與現有計畫的關係

### 已有計畫
- **熵減計畫**（`buygo-plus-one/.planning/ENTROPY-REDUCTION-PLAN.md`）：BGO 側已完成 Step 1-5
- **移除 buygo-line-notify 依賴**（`.claude/plans/declarative-tinkering-gosling.md`）：BGO 側移植到 LineHub
- **LineHub 開發計畫**（`line-hub/.planning/DEVELOPMENT-PLAN.md`）：Phase 1-3 已完成

### 本計畫定位
本計畫是 LineHub 的 **Phase 3.5+**（介於 Phase 3 和 Phase 4 之間），聚焦在：
- 修復現有 BUG（Phase A）
- 後台 UX 提升（Phase B）
- 為 Phase 4-6 奠定擴展基礎（Phase C）

Phase C 完成後，任何外掛（包括 BGO）都可以透過標準 Hook 或 REST API 發送 LINE 通知，不需要在 LineHub 中寫業務邏輯。
