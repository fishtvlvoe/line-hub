# LineHub 後台重構計畫 — 完整版

## Context

LineHub 的後台設定頁面目前有以下問題：

1. **登入按鈕不顯示**：勾選「按鈕位置」但沒有代碼把按鈕掛載到對應位置
2. **login group 設定無 UI**：Schema 定義了 5 個 login 設定（force_reauth、bot_prompt 等），後台完全看不到
3. **NSL 功能缺失**：新用戶角色、用戶名前綴、加好友行為、固定重定向等功能都沒有
4. **Tab 結構不合理**：URL 和操作說明混在一起、Webhook 對一般用戶無用
5. **getLoginUrl() 未帶參數**：OAuth fallback 沒有帶 LINE 登入參數

**目標**：重構為 3 個 Tab，仿 NSL 結構但更簡潔，所有設定可存可讀，登入按鈕實際顯示。

---

## 設計原則

### 熵減原則
- 每個 view 檔案 < 300 行（理想）
- PHP 檔案只做加載和路由，HTML 全部在 view 模板中
- 一個 class 只做一件事（SettingsPage = 路由 + 存儲，view = 渲染，ButtonPositions = 掛載）

### 隔離規範
- **LineHub 不包含任何業務外掛的模板**：按鈕位置只提供 hook，FluentCart/BuyGo 的具體整合由各自的 Connector 類處理
- **外掛間通訊只透過 WordPress hooks**：`do_action` / `apply_filters`，不直接引用
- **Settings Tab / Login Tab / Developer Tab 各有獨立 form**：互不影響，避免存 Tab A 時清空 Tab B 的值

### WordPress 規範
- 使用 `form-table` 標準排版
- Nonce 驗證 + `current_user_can('manage_options')` 權限檢查
- 所有輸入 `sanitize_text_field()` / `sanitize_key()`

---

## 新 Tab 結構：3 個 Tab

```
[ 設定 ]  [ 登入 ]  [ 開發者 ]
```

| Tab | 對象 | 內容 |
|-----|------|------|
| 設定 | 所有用戶 | LINE Channel 資訊 + 所有 URL + 連線測試 + 折疊指南 + NSL 整合 |
| 登入 | 所有用戶 | 登入模式 + LINE Login 行為 + 新用戶 + 按鈕 + 重定向 + 安全性 |
| 開發者 | 開發者 | Webhook 記錄 + Shortcodes + Integration Hooks + PHP 範例 |

移除原「入門」Tab（URL 併入「設定」，步驟說明改為折疊）
移除原「Webhook」Tab（併入「開發者」）

---

## Step 1：Schema 擴展

**檔案**：`includes/services/class-settings-service.php`
**行數影響**：+30 行

在 `general` group 新增 7 個欄位：

```php
'login_mode'           => ['type' => 'string', 'enum' => ['auto','oauth','liff'], 'default' => 'auto'],
'username_prefix'      => ['type' => 'string', 'default' => 'line'],
'display_name_prefix'  => ['type' => 'string', 'default' => 'lineuser-'],
'default_role'         => ['type' => 'string', 'default' => 'subscriber'],
'auto_link_by_email'   => ['type' => 'boolean', 'default' => true],
'login_redirect_url'   => ['type' => 'string', 'default' => ''],
'login_redirect_fixed' => ['type' => 'boolean', 'default' => false],
```

**驗證**：`SettingsService::get('general', 'login_mode')` 返回 `'auto'`

**Git 存檔**：`feat: Schema 新增 7 個 general 欄位（login_mode、username_prefix、default_role 等）`

---

## Step 2：Tab 結構改為 3 個 + handle_save 擴展

**檔案**：`includes/admin/class-settings-page.php`
**行數影響**：約 ±30 行

改動：
1. `TABS` 常量改為 3 個：`settings`、`login`、`developer`
2. `render_page()` switch/case 更新
3. 新增 `render_login_tab()` — 載入 `$settings_general + $settings_login`，require view
4. 新增 `render_developer_tab()` — 載入 `$events`，require view
5. `handle_save()` 新增：
   - 根據 `$_POST['tab']` 判斷儲存哪組設定（隔離，Tab A 不影響 Tab B）
   - `login` group 欄位：`force_reauth`, `bot_prompt`, `initial_amr`, `switch_amr`, `allow_auto_login`
   - `general` group 新欄位：`login_mode`, `username_prefix`, `display_name_prefix`, `default_role`, `auto_link_by_email`, `login_redirect_url`, `login_redirect_fixed`
6. 移除 `render_getting_started_tab()`、`render_webhooks_tab()`、`render_usage_tab()`

**驗證**：後台頁面顯示 3 個 Tab，可正常切換

**Git 存檔**：`refactor: Tab 結構改為 3 個（設定/登入/開發者）+ handle_save 隔離儲存`

---

## Step 3：重寫設定 Tab（合併入門 URL）

**檔案**：
- 重寫：`views/tab-settings.php`（目標 < 200 行）
- 刪除：`views/tab-getting-started.php`
- 更新：`views/partials/connection-status.php`

**Tab 內容**：
```
LINE Channel 設定
├── Channel ID / Secret / Access Token / LIFF ID（form-table）
├── 重要網址（Callback URL / LIFF URL / Webhook URL + 複製按鈕）
├── 連線狀態 + 三組測試按鈕
│     ├── [ 測試 Access Token ] → 打 LINE Bot Info API（Messaging API）
│     ├── [ 測試 LINE OA 登入 ] → 新視窗跳 OAuth，成功顯示 LINE UID
│     └── [ 測試 LIFF 登入 ]   → 新視窗跳 LIFF App，成功顯示驗證結果
├── <details> 設定步驟說明 </details>（折疊）
└── NSL 整合（相容模式 / 自動遷移）
```

LINE OA 測試流程：後台點按鈕 → 新視窗 `/line-hub/auth/?test=1` → 完成授權 → 回到後台顯示「LINE OA 測試成功 ✓」
LIFF 測試流程：後台點按鈕 → 新視窗 `liff.line.me/{ID}?test=1` → LIFF 初始化 → 回到後台顯示「LIFF 測試成功 ✓」
（兩個測試都用 `target="_blank"` 開新視窗，測試結果透過 query string `test_result=success` 回傳）

**隔離**：此 form 只儲存 `general` group 的 Channel 相關 + NSL 欄位
- form 帶 `<input type="hidden" name="tab" value="settings">`
- handle_save 根據 `tab=settings` 只寫 Channel + NSL 欄位

**驗證**：
1. 3 個 URL 顯示正確且複製按鈕可用
2. 3 組測試按鈕各自可點，結果正確顯示
3. 儲存後值保持
4. 步驟說明可展開/折疊

**Git 存檔**：`refactor: 設定 Tab 合併入門 URL + 折疊說明 + 表單隔離`

---

## Step 4：新建登入 Tab

**檔案**：
- 新建：`views/tab-login.php`（目標 < 280 行）

**Tab 內容**（5 個區塊）：
```
登入設定
├── 登入模式（auto / oauth / liff 三選一）
├── LINE Login 行為（初始方法、強制重授權、自動登入、加好友行為）
├── 新用戶設定（用戶名前綴、預設角色、Email 自動連結）
├── 登入按鈕（文字、大小、顯示位置 — 見下方說明）
├── 重定向（返回原頁面 / 固定 URL）
└── 安全性（Email 驗證、網域限制）
```

**隔離**：此 form 儲存 `general` group 的登入相關 + `login` group 的全部
- form 帶 `<input type="hidden" name="tab" value="login">`
- handle_save 根據 `tab=login` 只寫登入相關欄位

**驗證**：
1. 所有 radio / checkbox / text 設定可見
2. 儲存 → 重載 → 值保持
3. `login` group 的 `bot_prompt=aggressive` 儲存後可正確讀取

**Git 存檔**：`feat: 登入 Tab — 登入模式切換 + NSL 功能移植（角色/前綴/加好友/重定向）`

---

## Step 5：新建開發者 Tab + API Key + REST API 端點

**檔案**：
- 新建：`views/tab-developer.php`（目標 < 280 行）
- 新建：`includes/api/class-public-api.php`（REST API 端點，目標 < 200 行）
- 刪除：`views/tab-webhooks.php`
- 刪除：`views/tab-usage.php`

**Tab 內容**：
```
開發者 / API 整合
│
├── API Key 管理
│     ├── [ 產生 API Key ] → 顯示 lhk_xxxxxxxxxx（只顯示一次）
│     ├── 已產生的 Key：lhk_xxxx...xxxx（遮罩）[ 撤銷 ]
│     └── 說明：外部系統用 Header X-LineHub-API-Key 認證
│
├── REST API 端點（公開 API，外部 SaaS / Zapier 用）
│     ├── POST /line-hub/v1/messages/text    → 發送文字訊息
│     ├── POST /line-hub/v1/messages/flex    → 發送 Flex 訊息
│     ├── POST /line-hub/v1/messages/broadcast → 批量發送
│     ├── GET  /line-hub/v1/users/{id}/binding → 查詢綁定狀態
│     └── 每個端點附帶 curl 範例和參數說明
│
├── WordPress Hooks（同主機 WP 外掛用）
│     ├── do_action('line_hub/send/text', [...])
│     ├── do_action('line_hub/send/flex', [...])
│     ├── apply_filters('line_hub/user/is_linked', ...)
│     └── apply_filters('line_hub/user/get_line_uid', ...)
│
├── Shortcodes
│     └── [line_hub_login] 參數說明
│
├── PHP 範例
│     └── 取得 LINE UID、檢查綁定、查詢用戶
│
└── Webhook 事件記錄（最近 20 筆，含 payload 展開）
```

### API Key 管理

**Schema 新增**（在 `integration` group）：
```php
'api_key_hash'       => ['type' => 'string', 'encrypted' => false, 'default' => ''],
'api_key_created_at' => ['type' => 'string', 'default' => ''],
'api_key_prefix'     => ['type' => 'string', 'default' => ''],  // 前 8 碼，用於顯示
```

流程：
1. 用戶點「產生 API Key」→ 生成 `lhk_` + 32 位隨機字串
2. **完整 Key 只顯示一次**（頁面上提示「請立即複製」）
3. 資料庫只存 hash（`wp_hash()`），不存明文
4. 後續顯示 `lhk_xxxx...xxxx`（前 8 碼 + 遮罩）
5. 撤銷 = 清除 hash

### REST API 端點

**新建檔案**：`includes/api/class-public-api.php`

```php
class PublicAPI {
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void {
        // 訊息發送
        // POST /line-hub/v1/messages/text        → 發送文字訊息
        // POST /line-hub/v1/messages/flex        → 發送 Flex 訊息
        // POST /line-hub/v1/messages/broadcast   → 批量發送

        // 用戶查詢
        // GET  /line-hub/v1/users/{id}/binding   → 用 user_id 查綁定狀態
        // GET  /line-hub/v1/users/lookup?email=x  → 用 email 查用戶（Connector 用）

        // messages/text 和 messages/flex 也接受 email 參數
        // 如果傳 email 而非 user_id，自動查找對應用戶
    }

    // 認證中間件：檢查 X-LineHub-API-Key header
    public static function authenticate(WP_REST_Request $request) {
        $key = $request->get_header('X-LineHub-API-Key');
        $stored_hash = SettingsService::get('integration', 'api_key_hash', '');
        if (empty($key) || wp_hash($key) !== $stored_hash) {
            return new WP_Error('unauthorized', '無效的 API Key', ['status' => 401]);
        }
        return true;
    }
}
```

認證方式：
- **WordPress 內部**（管理員）：Cookie + Nonce（現有）
- **外部 SaaS**：`X-LineHub-API-Key: lhk_xxxxx` header

### 串接範例（顯示在 Tab 上）

**外部 SaaS（如 WebinarJam）串接範例**：
```bash
# 發送文字訊息給用戶
curl -X POST https://your-site.com/wp-json/line-hub/v1/messages/text \
  -H "X-LineHub-API-Key: lhk_your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 123, "message": "研討會即將開始！"}'

# 查詢用戶是否已綁定 LINE
curl https://your-site.com/wp-json/line-hub/v1/users/123/binding \
  -H "X-LineHub-API-Key: lhk_your_api_key_here"
```

**驗證**：
1. 產生 API Key → Key 顯示且可複製
2. 撤銷 API Key → 舊 Key 失效
3. 用 curl 呼叫 REST API + 正確 Key → 200 OK
4. 用 curl 呼叫 REST API + 錯誤 Key → 401 Unauthorized
5. Webhook 記錄表格正常顯示
6. 所有文件內容完整

**Git 存檔**：`feat: 開發者 Tab — API Key 管理 + REST API 端點 + 合併 Webhook/Hooks 文件`

---

## Step 6：按鈕位置掛載器（修復按鈕不顯示）

**新建檔案**：`includes/integration/class-button-positions.php`（目標 < 80 行）

職責：讀取 `login_button_positions` 設定，用 WordPress hooks 把按鈕掛載到對應位置

**按鈕位置設計原則**（UX）：
- 按鈕只出現在「用戶需要登入」的場景，不在瀏覽場景強制顯示
- 產品頁不放登入按鈕（使用者只是瀏覽，還沒決定要買）
- 結帳頁才放登入按鈕（使用者已決定要買，此時登入才合理）

**按鈕位置選項**（更新）：

| 位置 | Hook | UX 說明 |
|------|------|---------|
| WordPress 登入頁 | `login_form` | 用戶主動要登入，放按鈕合理 |
| FluentCart 結帳頁 | `fluentcart/checkout/before_form` | 用戶要結帳，需要登入 |
| FluentCommunity 登入表單 | `fluent_community/auth_form_footer` | 社群登入場景 |

**注意**：原本的 `fluentcart_product`（產品頁）改為 `fluentcart_checkout`（結帳頁）

```php
class ButtonPositions {
    public static function init(): void {
        $positions = SettingsService::get('general', 'login_button_positions', []);

        if (in_array('wp_login', $positions)) {
            add_action('login_form', [self::class, 'render_on_login_form']);
        }
        if (in_array('fluentcart_checkout', $positions)) {
            // hook 到 FluentCart 結帳頁（需確認實際 hook 名稱）
            add_action('fluentcart/checkout/before_customer_info', [self::class, 'render_on_checkout']);
        }
        if (in_array('fluent_community', $positions)) {
            add_action('fluent_community/auth_form_footer', [self::class, 'render_on_fluent_community']);
        }
    }
}
```

**修改**：`includes/class-plugin.php`（`init_services()` 新增 `ButtonPositions::init()`）

**修改**：`includes/integration/class-fluent-cart-connector.php`
- 移除原有的產品頁按鈕邏輯（或改為讀設定判斷）

**Schema 更新**：`login_button_positions` 的選項從 `fluentcart_product` 改為 `fluentcart_checkout`

**驗證**：
1. 登出 → `/wp-login.php` → LINE 登入按鈕出現
2. 登出 → FluentCart 結帳頁 → 按鈕出現（如已勾選）
3. 登出 → FluentCart 產品頁 → 按鈕不出現（瀏覽場景不干擾）
4. 已登入 → 所有位置按鈕不出現（`is_user_logged_in()` 判斷）

**Git 存檔**：`feat: ButtonPositions — 登入按鈕自動掛載到 WP 登入頁 / FluentCart / FluentCommunity`

---

## Step 7：LoginButton 修復

**檔案**：`includes/integration/class-login-button.php`

改動 1：`render()` defaults 自動從 settings 讀取
```php
$defaults = [
    'text' => SettingsService::get('general', 'login_button_text', '用 LINE 帳號登入'),
    'size' => SettingsService::get('general', 'login_button_size', 'medium'),
    // ...
];
```

改動 2：`getLoginUrl()` 根據 `login_mode` 決定行為
```php
$mode = SettingsService::get('general', 'login_mode', 'auto');
if ($mode === 'liff' || ($mode === 'auto' && !empty($liff_id))) {
    return "https://liff.line.me/{$liff_id}?redirect=...";
}
// OAuth — 帶上 bot_prompt, initial_amr 參數
$bot_prompt = SettingsService::get('login', 'bot_prompt', 'normal');
$initial_amr = SettingsService::get('login', 'initial_amr', '');
return home_url('/line-hub/auth/?redirect=...&bot_prompt=' . $bot_prompt . '&initial_amr=' . $initial_amr);
```

**驗證**：
1. 設定「僅 LIFF」→ 按鈕 URL 是 `liff.line.me`
2. 設定「僅 LINE OA」→ 按鈕 URL 是 `/line-hub/auth/`
3. 設定 `bot_prompt=aggressive` → OAuth URL 帶 `bot_prompt=aggressive`

**Git 存檔**：`fix: LoginButton 自動讀 settings + OAuth 帶 bot_prompt/initial_amr 參數`

---

## Step 8：完整測試 + 最終存檔

### 功能測試（在 https://test.buygo.me 執行）
1. **Tab 結構**：只有「設定 / 登入 / 開發者」3 個 Tab
2. **設定 Tab**：
   - 4 個 Channel 欄位 + 3 個 URL + 連線測試
   - 步驟說明可折疊
   - 儲存 → 重載 → 值保持
3. **登入 Tab**：
   - 登入模式 radio 可切換
   - LINE Login 行為 checkbox 可勾選
   - 新用戶設定（角色下拉選單、前綴輸入）
   - 按鈕位置 checkbox
   - 重定向設定
   - 儲存 → 重載 → 所有值保持
4. **登入按鈕顯示**：
   - 登出 → `/wp-login.php` → 按鈕出現
   - 登出 → shortcode 頁面 → 按鈕出現
   - 已登入 → 按鈕不出現
5. **開發者 Tab**：
   - API Key 可產生、可複製、可撤銷
   - REST API 端點文件 + curl 範例顯示完整
   - Webhook 記錄正常
   - Hooks + PHP 範例完整
6. **API 測試**：
   - `curl -H "X-LineHub-API-Key: lhk_xxx" POST /line-hub/v1/messages/text` → 200
   - 無 Key 或錯誤 Key → 401
7. **隔離測試**：設定 Tab 儲存不影響登入 Tab 的值，反之亦然

### 熵減驗收
- `class-settings-page.php` < 300 行
- 每個 view 檔案 < 300 行
- `class-button-positions.php` < 80 行
- PHP 檔案零內嵌 HTML

### 隔離驗收
- 各 Tab form 帶 `name="tab"` hidden field
- `handle_save()` 根據 tab 值分流儲存
- LineHub 不直接引用 BuyGo/FluentCart 業務邏輯

**最終 Git 存檔**：如有修正則再提交 `fix: ...`，否則 Step 7 的 commit 即為最終版

---

## 檔案變更總清單

| 動作 | 檔案路徑 | Step |
|------|----------|------|
| 修改 | `includes/services/class-settings-service.php` | 1 |
| 修改 | `includes/admin/class-settings-page.php` | 2 |
| 重寫 | `includes/admin/views/tab-settings.php` | 3 |
| 刪除 | `includes/admin/views/tab-getting-started.php` | 3 |
| 更新 | `includes/admin/views/partials/connection-status.php` | 3 |
| 新建 | `includes/admin/views/tab-login.php` | 4 |
| 新建 | `includes/admin/views/tab-developer.php` | 5 |
| 新建 | `includes/api/class-public-api.php` | 5 |
| 刪除 | `includes/admin/views/tab-webhooks.php` | 5 |
| 刪除 | `includes/admin/views/tab-usage.php` | 5 |
| 新建 | `includes/integration/class-button-positions.php` | 6 |
| 修改 | `includes/class-plugin.php` | 5, 6 |
| 修改 | `includes/integration/class-login-button.php` | 7 |
| 修改 | `includes/integration/class-fluent-cart-connector.php` | 6 |

**新建 4 個 + 修改 5 個 + 重寫 1 個 + 更新 1 個 + 刪除 3 個 = 14 個檔案操作**

---

## Git 存檔策略（每個 Step 一次 commit）

| Step | Commit 訊息 |
|------|-------------|
| 1 | `feat: Schema 新增 7 個 general 欄位` |
| 2 | `refactor: Tab 結構改為 3 個 + handle_save 隔離儲存` |
| 3 | `refactor: 設定 Tab 合併入門 URL + 折疊說明` |
| 4 | `feat: 登入 Tab — 登入模式切換 + NSL 功能移植` |
| 5 | `feat: 開發者 Tab — API Key 管理 + REST API + 合併 Webhook/Hooks` |
| 6 | `feat: ButtonPositions — 登入按鈕自動掛載` |
| 7 | `fix: LoginButton 自動讀 settings + OAuth 帶參數` |
| 8 | `fix: ...`（如有測試修正） |
