# LineHub 熵減計畫

**建立日期**：2026-02-24
**目標**：在開始 WebinarGo 開發前，把 LineHub 的程式碼結構全面整理乾淨
**原則**：地基穩了才往上蓋，避免每開新外掛都要回來改 LineHub

---

## 現況評估

| 指標 | 現況 | 目標 |
|------|------|------|
| PHP 檔案數 | 47 | ~60（拆分後會增加） |
| 超過 500 行的檔案 | 4 個 | 0 個 |
| 超過 300 行的檔案 | 15 個 | 0 個 |
| 超過 50 行的方法 | 28 個 | < 5 個 |
| Class 中內嵌 `<style>` | 2 處 | 0 處 |
| Class 中內嵌 `<script>` | 3 處 | 0 處 |
| Class 中直接輸出 HTML | 8 處 | 0 處 |
| 目錄缺 index.php | 21 個 | 0 個 |
| uninstall.php | 不存在 | 存在 |
| 重複 LINE API 常數 | 4 處 | 0 處（統一管理） |
| 未 sanitize 的輸入 | 8 處 | 0 處 |
| 單元測試 | 0 個 | 核心服務覆蓋 |
| 熵減評分 | 52/100 | ≥ 85/100 |

---

## 執行階段

### Phase 1：安全補齊（基礎衛生）

**目標**：滿足 WordPress Plugin Handbook 的基本安全要求

| 項目 | 說明 |
|------|------|
| 1.1 | 建立 `uninstall.php`（清理資料表 + options + transients） |
| 1.2 | 21 個目錄加入 `index.php`（`<?php // Silence is golden.`） |
| 1.3 | 修正 8 處未 sanitize 的輸入（特別是 redirect URL 的 Open Redirect 風險） |

**預估**：~1 小時
**驗證**：所有目錄不可瀏覽、uninstall 可正確清理、輸入驗證無遺漏

---

### Phase 2：常數統一 + HTTP Client

**目標**：消除重複定義，建立統一的 LINE API 存取層

| 項目 | 說明 |
|------|------|
| 2.1 | 建立 `class-line-api-endpoints.php` 常數類別，集中管理所有 LINE API URL |
| 2.2 | 移除 `class-oauth-client.php` 和 `class-liff-handler.php` 的重複常數 |
| 2.3 | 消除 `class-settings-page.php` 和 `class-messaging-service.php` 的硬編碼 URL |

**預估**：~1 小時
**驗證**：grep 確認無硬編碼 LINE API URL、所有 API 呼叫正常

---

### Phase 3：Class 中的內嵌 CSS/JS/HTML 清除

**目標**：所有 Class 檔案零內嵌，HTML/CSS/JS 全部拆到獨立檔案

| 項目 | 要處理的檔案 | 內容 |
|------|-------------|------|
| 3.1 | `class-fluent-cart-connector.php` | `renderBindingSection()` 260 行 HTML+CSS+JS → template + CSS + JS |
| 3.2 | `class-fluent-cart-connector.php` | `injectLoginBanner()` 54 行 HTML+JS → template + JS |
| 3.3 | `class-plugin.php` | Toast JS（20 行）→ `assets/js/welcome-toast.js` |
| 3.4 | `class-plugin.php` | `override_avatar_with_line()` 60 行 → 獨立 service |
| 3.5 | `class-users-column.php` | echo `<style>` → `assets/css/users-column.css` |
| 3.6 | `class-auto-updater.php` | echo notice HTML → template |
| 3.7 | `class-auth-callback.php` | 錯誤頁面 HTML（含大量 inline style）→ template |
| 3.8 | `class-settings-page.php` | printf notice HTML → template 或 helper |
| 3.9 | `line-hub.php` | 啟動錯誤通知 HTML → template |

**預估**：~3 小時
**驗證**：grep 確認 Class 中無 `<style>`、`<script>`、大段 HTML echo

---

### Phase 4：大檔案拆分（超過 500 行）

**目標**：所有檔案 < 300 行（理想）、< 500 行（絕對上限）

#### 4.1 class-liff-handler.php（670 行 → 拆成 3~4 個檔案）

| 拆分目標 | 內容 |
|----------|------|
| `class-liff-handler.php` | 入口路由、request 分派（< 150 行） |
| `class-liff-verifier.php` | `handleVerify()` 128 行 → token 驗證和用戶查詢 |
| `class-liff-email-handler.php` | `handleEmailSubmit()` 116 行 → email 綁定邏輯 |
| `class-liff-user-factory.php` | `createNewUser()` + 用戶建立相關邏輯 |

#### 4.2 class-settings-service.php（653 行 → 拆成 3 個檔案）

| 拆分目標 | 內容 |
|----------|------|
| `class-settings-service.php` | get/set/delete（< 200 行） |
| `class-settings-schema.php` | Schema 定義（fields、tabs、sections） |
| `class-settings-encryption.php` | encrypt/decrypt 邏輯 |

#### 4.3 class-user-service.php（549 行 → 拆成 2~3 個檔案）

| 拆分目標 | 內容 |
|----------|------|
| `class-user-service.php` | 查詢和綁定檢查（< 250 行） |
| `class-user-linker.php` | `linkUser()` 86 行 + `unlinkUser()` → 綁定/解綁 |
| `class-user-profile-sync.php` | `updateProfile()` 79 行 → LINE 資料同步 |

#### 4.4 class-plugin.php（520 行 → < 300 行）

Phase 3 已拆出 toast 和 avatar，加上：

| 拆分目標 | 內容 |
|----------|------|
| 移出 `render_profile_binding_section()` | 已完成（Phase 0） |
| 移出 `register_liff_endpoint()` 相關 | → `class-liff-handler.php` 自行處理 |
| 移出前端資源載入邏輯 | → `class-frontend-assets.php` |

**預估**：~4 小時
**驗證**：所有檔案 < 300 行、功能測試通過、autoloader 載入正確

---

### Phase 5：中型檔案瘦身（300~500 行）

**目標**：將 11 個 300~500 行的檔案降到 300 行以下

| 檔案 | 行數 | 策略 |
|------|------|------|
| `class-login-service.php` | 418 | 拆出 `handleEmailSubmit()` 到 email handler |
| `class-fluent-cart-connector.php` | 403 | Phase 3 已拆出大段 HTML，剩餘應 < 200 行 |
| `class-messaging-service.php` | 397 | 拆出 `sendRequest()` 為 HTTP client |
| `liff-template.php` | 391 | 拆出 JS 為獨立檔案 `liff-app.js` |
| `class-flex-builder.php` | 363 | 檢視是否有重複邏輯可抽取 |
| `class-public-api.php` | 352 | 檢視方法長度，可能拆出驗證邏輯 |
| `tab-login-settings.php` | 349 | inline style → CSS 檔案 |
| `class-oauth-client.php` | 319 | Phase 2 已移除重複常數，檢查方法長度 |
| `class-settings-api.php` | 318 | 檢視 `update_settings()` 61 行，可能拆出驗證 |
| `tab-developer.php` | 317 | inline style → CSS 檔案（developer-tab.css 已存在，合併） |
| `class-oauth-state.php` | 301 | 邊界值，檢視是否需處理 |

**預估**：~3 小時
**驗證**：所有檔案 < 300 行

---

### Phase 6：Admin Views inline style 清理

**目標**：view 模板中的 inline style 全部移到 CSS 檔案

| 檔案 | inline style 數量 | 處理方式 |
|------|-------------------|---------|
| `tab-developer.php` | ~20 處 | → `developer-tab.css`（已存在） |
| `tab-login-settings.php` | ~15 處 | → 新建 `login-settings-tab.css` |
| `tab-line-settings.php` | ~8 處 | → 新建 `line-settings-tab.css` |
| `tab-webhook.php` | ~10 處 | → 新建 `webhook-tab.css` |
| `connection-status.php` | ~10 處 | → 新建 `connection-status.css` |
| `tab-wizard.php` | ~5 處 | → 新建 `wizard-tab.css` |
| `liff-template.php` | 內嵌 `<style>` | → 新建 `assets/css/liff.css` |
| `liff-email-template.php` | 內嵌 `<style>` | → 新建 `assets/css/liff-email.css` |
| `email-form-template.php` | 內嵌 `<style>` | → 新建 `assets/css/email-form.css` |

**預估**：~2 小時
**驗證**：grep 確認 view 中無 `style="` 和 `<style>`

---

### Phase 7：長方法重構

**目標**：所有方法 < 50 行

Phase 3~5 的拆分已經處理掉最嚴重的長方法。此階段處理剩餘的：

| 方法 | 行數 | 檔案 | 策略 |
|------|------|------|------|
| `check_update` | 93 | auto-updater | 拆出 API 請求和快取邏輯 |
| `downloadAndUpload` | 85 | content-service | 拆出下載和上傳為獨立步驟 |
| `get_api_endpoints` | 82 | developer-tab | 資料結構移到常數或設定 |
| `dispatchEvent` | 65 | event-dispatcher | 檢視是否可用查詢表簡化 |
| `run` | 64 | migrate-avatars | CLI 命令，可接受較長 |
| `handleWebhook` | 63 | webhook-receiver | 拆出驗證和分派 |
| `handleExchange` | 63 | session-transfer | 拆出驗證步驟 |
| `update_settings` | 61 | settings-api | 拆出驗證邏輯 |
| `processCallback` | 58 | auth-callback | 拆出各步驟 |
| 其餘 50~57 行的 | 9 個 | 各檔案 | 逐一評估 |

**預估**：~2 小時
**驗證**：grep/靜態分析確認無超過 50 行的方法

---

### Phase 8：類名統一 + 根目錄整理

**目標**：命名一致、根目錄乾淨

| 項目 | 說明 |
|------|------|
| 8.1 | 類名統一為純 CamelCase（移除底線風格如 `Auto_Updater` → `AutoUpdater`） |
| 8.2 | 根目錄的 `DAY-01/02/03-PROGRESS.md`、`LINE-HUB-ROADMAP.md` 移到 `.planning/` |
| 8.3 | `CONFLICT-FIX-REPORT.md` 歸檔或移除 |
| 8.4 | `assets/images/` 空目錄清理或放入 placeholder |
| 8.5 | `languages/` 空目錄加入 `.gitkeep` |
| 8.6 | `.zipignore` 和 `build-release.sh` 確認排除規則完整 |

**預估**：~1 小時
**驗證**：autoloader 載入正確、目錄結構乾淨

---

### Phase 9：基礎測試框架

**目標**：建立測試基礎設施 + 核心服務的基本覆蓋

| 項目 | 說明 |
|------|------|
| 9.1 | 建立 `composer.json`（phpunit 依賴） |
| 9.2 | 建立 `tests/bootstrap-unit.php` |
| 9.3 | `UserService` 基本測試（綁定查詢、link/unlink） |
| 9.4 | `SettingsService` 基本測試（get/set/encrypt） |
| 9.5 | `MessagingService` 基本測試（發送邏輯） |

**預估**：~2 小時
**驗證**：`composer test` 通過

---

## 執行規範

### Git 策略
- 主分支：`feature/entropy-reduction-profile-binding`（已存在，繼續使用或建新分支）
- 每個 Phase 完成後做一次 commit
- 每個 Step 內如果有獨立完整的改動也做 commit

### 測試驗證
- 每個 Phase 完成後在 test.buygo.me 實測
- 重點測試：LINE 登入、LIFF 綁定、個人資料頁、後台設定頁、Webhook 接收

### 熵減檢查清單（每個 Phase 完成後）
- [ ] 所有修改的檔案 < 300 行？
- [ ] 沒有新增的內嵌 CSS/JS/HTML？
- [ ] autoloader 載入正確？
- [ ] 功能測試通過？

---

## 時間估算總覽

| Phase | 內容 | 預估 |
|-------|------|------|
| 1 | 安全補齊 | ~1 小時 |
| 2 | 常數統一 | ~1 小時 |
| 3 | 內嵌清除 | ~3 小時 |
| 4 | 大檔案拆分 | ~4 小時 |
| 5 | 中型檔案瘦身 | ~3 小時 |
| 6 | inline style 清理 | ~2 小時 |
| 7 | 長方法重構 | ~2 小時 |
| 8 | 命名整理 | ~1 小時 |
| 9 | 測試框架 | ~2 小時 |
| **合計** | | **~19 小時** |

---

## 完成後的預期狀態

- 所有檔案 < 300 行
- Class 中零內嵌 CSS/JS/HTML
- 統一的 LINE API 常數管理
- 完整的安全防護（uninstall + index.php + 輸入驗證）
- 核心服務有基本測試覆蓋
- 熵減評分 ≥ 85/100
- **WebinarGo 開發時不需要回來改 LineHub 結構**
