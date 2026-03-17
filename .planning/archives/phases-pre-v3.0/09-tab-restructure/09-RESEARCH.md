# Phase 9 Research: Tab 重構

**Phase:** 9 — Tab 重構
**Researched:** 2026-02-24
**Requirements:** TAB-01, TAB-02, TAB-03, TAB-04, TAB-05
**Confidence:** HIGH（全部基於直接原始碼分析）

---

## 研究目標

回答：「要做好 Phase 9 的 Tab 重構，需要知道什麼？」

Phase 9 的核心目標是將 441 行的 `class-settings-page.php` 拆分為 Tab 獨立檔案，從 3 Tab 重組為 5 Tab，各 Tab 使用獨立表單互不干擾。

---

## 現有架構分析

### 1. class-settings-page.php（441 行）

**職責過多：**
- 註冊選單（`register_menu`）
- 載入 CSS/JS（`enqueue_assets`）
- 渲染頁面入口（`render_page`）+ Tab 導航 HTML
- 3 個 Tab 渲染方法（`render_settings_tab`, `render_login_tab`, `render_developer_tab`）
- 表單儲存總入口（`handle_save`）
- 5 個 section 儲存方法（`save_settings_tab`, `save_messaging_section`, `save_login_section`, `save_nsl_section`, `save_login_tab`）
- API Key 操作（`handle_generate_api_key`, `handle_revoke_api_key`）
- 測試連線（`handle_test_connection`）
- 後台通知（`show_admin_notices`）

### 2. 現有 Tab 結構

| Tab slug | 標籤 | View 檔案 | 儲存方法 |
|----------|------|-----------|----------|
| settings | 設定 | views/tab-settings.php | save_settings_tab() → 3 sections |
| login | 登入 | views/tab-login.php | save_login_tab() |
| developer | 開發者 | views/tab-developer.php | 無（只有 API Key 操作） |

### 3. 現有 Nonce / Form 結構

**統一 nonce：** 所有 Tab 共用 `line_hub_save_settings` nonce action
**Tab 隔離靠 hidden field：** `<input type="hidden" name="tab" value="xxx">`
**Section 隔離靠 hidden field：** settings Tab 內部用 `<input type="hidden" name="section" value="xxx">`

**API Key 操作用獨立 nonce：** `line_hub_api_key_action`
**測試連線用獨立 nonce：** `line_hub_test_connection`

### 4. View 檔案行數

| 檔案 | 行數 | 說明 |
|------|------|------|
| tab-settings.php | 230 | 4 個 card（Messaging、Login、NSL、連線狀態） |
| tab-login.php | 319 | 1 個大 form（登入設定全部） |
| tab-developer.php | 295 | 5 個 card（API Key、REST API、Hooks、Shortcodes、Webhook） |
| connection-status.php | 61 | Partial：連線狀態列表 |

### 5. CSS/JS 資源

- `assets/css/admin-tabs.css`（129 行）— Tab 導航 + 卡片 + 狀態指示器
- `assets/js/admin-tabs.js`（59 行）— Tab 切換回饋 + 複製按鈕 + Payload 展開

CSS 結構已與 BGO 的 `admin-tabs.css` 對齊（BGO 用 `.bgo-*` 前綴，LineHub 用 `.line-hub-*` 前綴）。

---

## 目標架構（5 Tab）

### 新 Tab 規劃

| 新 slug | 新標籤 | 來源 | 說明 |
|---------|--------|------|------|
| wizard | 設定嚮導 | 新增 | 從 settings Tab 的「連線狀態」+ 「設定步驟說明」抽取 |
| line-settings | LINE 設定 | settings Tab | Messaging API + Login Channel + NSL |
| login-settings | 登入設定 | login Tab | 登入行為、新用戶設定、按鈕、重定向、安全性 |
| webhook | Webhook | developer Tab 的 Webhook 部分 | Webhook 事件記錄 |
| developer | 開發者 | developer Tab 剩餘部分 | API Key、REST API、Hooks、Shortcodes、PHP 範例 |

### 舊 slug → 新 slug 映射

| 舊 slug | 新 slug |
|---------|---------|
| settings | line-settings |
| login | login-settings |
| developer | developer（不變） |

---

## 拆分策略

### Tab 類別介面

每個 Tab 類別需要：
1. `get_slug(): string` — Tab URL slug
2. `get_label(): string` — Tab 顯示標籤
3. `render(): void` — 渲染 Tab 內容
4. `save(array $post_data): bool` — 處理表單儲存

### 主類別職責（< 200 行）

重構後 `class-settings-page.php` 只負責：
- 註冊選單
- 載入 CSS/JS
- Tab 導航渲染（迴圈 Tab 物件）
- 表單儲存路由（根據 `$_POST['tab']` 委託給對應 Tab）
- 舊 slug redirect
- 後台通知

### 檔案結構

```
includes/admin/
├── class-settings-page.php          # 主類別（< 200 行）
├── tabs/
│   ├── class-wizard-tab.php         # 設定嚮導 Tab
│   ├── class-line-settings-tab.php  # LINE 設定 Tab
│   ├── class-login-settings-tab.php # 登入設定 Tab
│   ├── class-webhook-tab.php        # Webhook Tab
│   └── class-developer-tab.php      # 開發者 Tab
└── views/
    ├── tab-wizard.php               # 嚮導 view（新）
    ├── tab-line-settings.php        # LINE 設定 view（從 tab-settings.php 拆出）
    ├── tab-login-settings.php       # 登入設定 view（重命名）
    ├── tab-webhook.php              # Webhook view（從 tab-developer.php 拆出）
    ├── tab-developer.php            # 開發者 view（去除 Webhook 部分）
    └── partials/
        └── connection-status.php    # 連線狀態 partial（保留）
```

---

## Nonce 策略

**維持現有統一 nonce：** `line_hub_save_settings`
**Tab 隔離靠 hidden field：** `<input type="hidden" name="tab" value="slug">`

理由：
1. 現有架構已用統一 nonce + hidden tab 做隔離
2. 改為每 Tab 獨立 nonce 需要修改所有 view 和 handler，風險增大
3. 隔離的關鍵不是 nonce 分離，而是 save 方法只處理該 Tab 的欄位

但每個 Tab 的 `save()` 方法必須確保：
- 只讀取和儲存該 Tab 的欄位
- 不觸及其他 Tab 的設定值
- checkbox 未勾選時明確設為 false（不忽略）

---

## 風險分析

| 風險 | 影響 | 緩解 |
|------|------|------|
| Autoloader 載入 tabs/ 子目錄 | Tab 類別無法載入 | 確認 PSR-4 autoloader 支援子命名空間 |
| 舊 URL 書籤失效 | 用戶 404 | redirect 機制處理 |
| API Key 操作跨 Tab | 功能中斷 | handle_generate/revoke 保留在主類別 |
| view 檔案路徑改變 | require 失敗 | Tab 類別用 `__DIR__` 相對路徑 |

---

## Autoloader 確認

LINE Hub 使用 PSR-4 autoloader（`class-autoloader.php`）。需確認：
- 命名空間 `LineHub\Admin\Tabs` 對應 `includes/admin/tabs/`
- 檔名格式：`WizardTab` → `class-wizard-tab.php`

---

## 建議的 Plan 拆分

### Plan 09-01: Tab 架構拆分
- 建立 `includes/admin/tabs/` 目錄
- 建立 Tab 基礎介面/抽象類
- 將現有 3 Tab 邏輯從 SettingsPage 搬到獨立 Tab 類別
- 主類別瘦身到 < 200 行
- 此步驟保持 3 Tab 不變，只做結構拆分

### Plan 09-02: 5 Tab 重組
- 將 settings Tab 拆分為 wizard + line-settings
- 將 developer Tab 拆分為 webhook + developer
- 重命名 login → login-settings
- 舊 slug redirect 機制
- View 檔案對應調整

### Plan 09-03: 整合驗證
- 所有 5 Tab 渲染正常
- 各 Tab 儲存互不干擾
- 舊 URL redirect 正確
- LINE 登入按鈕 / LIFF / 設定通知 正常運作

---

*Research completed: 2026-02-24*
*Ready for planning: yes*
