# Roadmap: LINE Hub

## Milestones

- ✅ **v1.0 LINE 登入中樞** - Phases 1-7 (shipped 2026-02-07)
- ✅ **v2.0 重構與擴展** - Phases 8-10 (complete 2026-02-24)
- [ ] **v3.0 熵減重構** - Phases 11-16 (in progress)

## Phases

<details>
<summary>✅ v1.0 LINE 登入中樞 (Phases 1-7) - SHIPPED 2026-02-07</summary>

LINE Hub 從設定系統基礎出發，逐步建構完整的 LINE 整合中樞。v1.0 涵蓋設定管理、用戶綁定、OAuth 認證、通知系統、Webhook 中心、外掛串接與後台介面，提供完整的 LINE 登入、通知、Webhook 和第三方外掛串接功能。

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Settings Foundation** - 設定系統基礎建設（已完成）
- [x] **Phase 2: User Management** - 用戶綁定與資料管理（已完成）
- [x] **Phase 3: OAuth Authentication** - LINE 登入認證系統（已完成）
- [x] **Phase 4: Notification System** - 通知發送與模板管理（已完成）
- [x] **Phase 5: Webhook Center** - Webhook 接收與處理中心（已完成）
- [x] **Phase 6: Plugin Integration** - 外掛串接系統（已完成）
- [x] **Phase 7: Admin UI** - 後台管理介面（已完成）

### Phase 1: Settings Foundation
**Goal**: 建立穩固的設定管理基礎，支援所有後續功能模組
**Depends on**: Nothing (first phase)
**Requirements**: SETT-01, SETT-02, SETT-03, SETT-04, SETT-05, SETT-06
**Status**: COMPLETE (Day 1-2)
**Success Criteria** (what must be TRUE):
  1. 管理員可以在後台設定 LINE Channel ID、Secret、Access Token
  2. 管理員可以設定 LINE Login Channel ID 和 Secret
  3. 敏感資料（Channel Secret、Access Token）以加密形式儲存
  4. 設定 API 可正常讀取和更新設定值
  5. 設定值有快取機制，不會每次都查詢資料庫
**Plans**: 2 plans (completed)

Plans:
- [x] 01-01: Settings Schema and Service
- [x] 01-02: Settings REST API

### Phase 2: User Management
**Goal**: 用戶可以管理 LINE 綁定關係
**Depends on**: Phase 1
**Requirements**: USER-01, USER-02, USER-03, USER-04, USER-05
**Status**: COMPLETE (2026-02-07)
**Success Criteria** (what must be TRUE):
  1. 系統可以儲存 LINE UID 與 WordPress User ID 的綁定關係
  2. 用戶可以在前台查看自己的 LINE 綁定狀態（已綁定/未綁定）
  3. 用戶可以主動解除 LINE 綁定
  4. 同一個 LINE UID 不能綁定多個 WordPress 帳號（防止重複綁定）
  5. 用戶綁定後，display_name 和 picture_url 自動同步
**Plans**: 2 plans (completed)

Plans:
- [x] 02-01-PLAN.md — UserService 核心邏輯（查詢、綁定、解除綁定、NSL fallback）
- [x] 02-02-PLAN.md — 用戶綁定 REST API 端點（GET/DELETE binding）

### Phase 3: OAuth Authentication
**Goal**: 用戶可以透過 LINE 安全登入 WordPress
**Depends on**: Phase 2
**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09, AUTH-10
**Status**: COMPLETE (2026-02-07)
**Success Criteria** (what must be TRUE):
  1. 用戶可以點擊「LINE 登入」按鈕完成 OAuth 認證
  2. 系統可以從 ID Token 自動擷取用戶 Email
  3. Email 無效時，用戶可以選擇「強制重新授權」或「手動輸入 Email」
  4. 新用戶登入後自動建立 WordPress 帳號（含 username, email, display_name）
  5. 登入完成後自動重定向到原始頁面（非首頁或後台）
**Plans**: 3 plans (completed)

Plans:
- [x] 03-01-PLAN.md — OAuth 核心基礎設施（OAuthState、OAuthClient）
- [x] 03-02-PLAN.md — 認證流程處理器和路由（AuthCallback）
- [x] 03-03-PLAN.md — 用戶登入/註冊服務（LoginService、Email 表單）

### Phase 4: Notification System
**Goal**: 系統可以發送各種場景的 LINE 通知
**Depends on**: Phase 2
**Requirements**: NOTIF-01, NOTIF-02, NOTIF-03, NOTIF-04
**Status**: COMPLETE
**Success Criteria** (what must be TRUE):
  1. 當 FluentCart 訂單建立時，用戶收到 LINE 通知
  2. 當 BuyGo 出貨時，用戶收到 LINE 通知
  3. 用戶註冊後收到歡迎訊息
  4. 管理員可以在後台編輯通知訊息模板（支援 {order_id} 等變數）
  5. 系統保留最近 90 天的通知記錄
**Plans**: 3 plans (completed)

Plans:
- [x] 04-01: MessagingService and template engine
- [x] 04-02: Notification scenes and triggers
- [x] 04-03: Notification logs and statistics

### Phase 5: Webhook Center
**Goal**: 統一接收和處理所有 LINE Webhook 事件
**Depends on**: Phase 2
**Requirements**: WEBHOOK-01, WEBHOOK-02, WEBHOOK-03
**Status**: COMPLETE
**Success Criteria** (what must be TRUE):
  1. Webhook 端點 `/wp-json/line-hub/v1/webhook` 可正常接收事件
  2. 只有通過 HMAC 簽名驗證的 Webhook 才會被處理
  3. 系統可以分類處理 message、follow、unfollow、postback 事件
  4. 管理員可以在後台查看最近 100 筆 Webhook 記錄
  5. 重複的 Webhook 事件不會被重複處理（去重機制）
**Plans**: 3 plans (completed)

Plans:
- [x] 05-01: Webhook receiver and HMAC validation
- [x] 05-02: Event dispatcher and deduplication
- [x] 05-03: Webhook logs admin view

### Phase 6: Plugin Integration
**Goal**: 其他外掛可以輕鬆串接 LINE Hub
**Depends on**: Phase 4, Phase 5
**Requirements**: INTEG-01, INTEG-02, INTEG-03, INTEG-04, INTEG-05
**Status**: COMPLETE
**Success Criteria** (what must be TRUE):
  1. 其他外掛可以監聽 `line_hub/user_logged_in` Hook
  2. 其他外掛可以透過 `line_hub/message/before_send` Filter 修改訊息
  3. FluentCart 訂單建立時自動觸發通知（透過 Hook 監聽）
  4. BuyGo 出貨時自動觸發通知（透過 Hook 監聯）
  5. LINE Hub 的 Hook 優先級不會與 NSL 衝突
**Plans**: 3 plans (completed)

Plans:
- [x] 06-01: Hook registry and event dispatcher
- [x] 06-02: FluentCart and BuyGo connectors
- [x] 06-03: WordPress core hooks (user_register, retrieve_password)

### Phase 7: Admin UI
**Goal**: 管理員可以透過直覺的後台介面管理所有功能
**Depends on**: Phase 3, Phase 4, Phase 5, Phase 6
**Requirements**: ADMIN-01, ADMIN-02, ADMIN-03
**Status**: COMPLETE
**Success Criteria** (what must be TRUE):
  1. 後台有完整的 Tab 導航（設定、登入、開發者）
  2. 管理員可以在後台設定 LINE Channel 並即時儲存
  3. 後台介面採用 WordPress 標準設計
  4. 所有後台操作都有適當的權限檢查（manage_options）
  5. WordPress 用戶列表顯示 LINE 綁定狀態欄位
**Plans**: 5 plans (completed)

Plans:
- [x] 07-01: Tab navigation system and routing
- [x] 07-02: Settings and Login tabs
- [x] 07-03: Developer tab
- [x] 07-04: Users column LINE binding status
- [x] 07-05: Security hardening and final polish

</details>

---

<details>
<summary>✅ v2.0 重構與擴展 (Phases 8-10) - COMPLETE 2026-02-24</summary>

**Milestone Goal:** 將 LineHub 從開發中的半成品升級為可上架的成熟 LINE 通訊平台——修復已知 bug、拆解過胖的設定頁、補齊開發者工具，讓 BuyGo/WebinarGo 等外掛可以無縫串接。

- [x] **Phase 8: 驗證與修復** - 確認現況並修復安全問題
- [x] **Phase 9: Tab 重構** - 後台設定頁拆分與介面重組
- [x] **Phase 10: 開發者體驗** - 補齊文件與 API 使用記錄

### Phase 8: 驗證與修復
**Goal**: 確認所有「已實作」功能真正可用，並修復三個已知安全與正確性問題
**Depends on**: Phase 7
**Requirements**: VERIFY-01, VERIFY-02, VERIFY-03, VERIFY-04, VERIFY-05
**Success Criteria** (what must be TRUE):
  1. 管理員在登入設定中勾選按鈕位置後儲存，重新載入頁面後勾選狀態保留（SettingsService array 序列化驗證通過）
  2. 清除 Transient 快取後，新儲存的設定值立即反映，不需等待 1 小時快取過期
  3. 使用 curl 呼叫 `POST /line-hub/v1/messages/text` 帶有效 API Key，訊息成功發送到 LINE（5 個 Hook 和 REST 端點端到端驗證通過）
  4. API Key 認證改用 `hash_equals()` 後，舊的 API Key 仍可正常驗證（向後相容）
  5. 發送超過 100 個 user_id 的 broadcast 請求時，API 回傳 HTTP 400 而非靜默執行
**Status**: COMPLETE (2026-02-24)
**Plans**: 2 plans (completed)

Plans:
- [x] 08-01-PLAN.md — 安全修復與程式碼修正（hash_equals、broadcast 上限、回應格式 bug、N+1 快取）
- [x] 08-02-PLAN.md — 端到端驗證（設定存取、Hook、REST API、向後相容測試）

### Phase 9: Tab 重構
**Goal**: 後台設定頁從 441 行的單一大型類別重構為每個 Tab 獨立檔案，各 Tab 使用獨立表單互不干擾
**Depends on**: Phase 8
**Requirements**: TAB-01, TAB-02, TAB-03, TAB-04, TAB-05
**Success Criteria** (what must be TRUE):
  1. 後台設定頁顯示 5 個 Tab（設定嚮導、LINE 設定、登入設定、Webhook、開發者），Tab 切換正常運作
  2. 在「LINE 設定」Tab 修改 Channel Secret 並儲存，不會影響「登入設定」Tab 的 Login Channel 設定（各 Tab 獨立 form 隔離）
  3. 使用舊 Tab URL（`?tab=settings`）訪問後台，自動 redirect 到對應的新 Tab slug，不出現 404 或空白頁
  4. `class-settings-page.php` 主類別在 200 行以內，各 Tab 邏輯分散到 `includes/admin/tabs/` 子目錄
  5. 重構後所有現有功能正常運作：LINE 登入按鈕出現在指定位置、LIFF 可正常使用、設定儲存成功顯示通知
**Status**: COMPLETE (2026-02-24)
**Plans**: 3 plans (completed)

Plans:
- [x] 09-01: Tab 架構拆分（主類別瘦身 441→188 行、tabs/ 子目錄建立）
- [x] 09-02: 5 Tab 重組（新 slug、獨立 form、舊 slug redirect）
- [x] 09-03: 整合驗證（語法檢查、結構驗證、form 隔離驗證）

### Phase 10: 開發者體驗
**Goal**: 開發者可以在後台直接找到 LINE Hub 整合所需的所有資訊，不需查閱外部文件
**Depends on**: Phase 9
**Requirements**: DEV-01, DEV-02, DEV-03
**Success Criteria** (what must be TRUE):
  1. 開發者 Tab 顯示所有 REST API 端點清單，每個端點有對應的 curl 指令可直接複製貼上測試
  2. 開發者 Tab 顯示 Hook 使用說明，包含 `do_action('line_hub/send/text', ...)` 的完整參數範例，複製後可直接貼入外掛程式碼執行
  3. 開發者可以在後台查看最近 20 次 API 呼叫記錄（時間、來源 IP、端點、成功/失敗），快速確認外掛串接是否正常
**Status**: COMPLETE (2026-02-24)
**Plans**: 2 plans (completed)

Plans:
- [x] 10-01: REST API 文件 Tab（端點清單、curl 範例、API Key 生成提示）
- [x] 10-02: Hook 文件與 API 使用記錄（PHP 範例、呼叫 log 顯示）

</details>

---

### v3.0 熵減重構 (Phases 11-16)

**Milestone Goal:** 全面整理程式碼結構，將熵減評分從 52/100 提升至 85/100 以上，為 WebinarGo 開發打穩地基。所有檔案 < 300 行、Class 零內嵌、統一常數管理、安全補齊、基礎測試覆蓋。

- [x] **Phase 11: 安全補齊與常數統一** - 基礎衛生和 LINE API URL 集中管理
- [x] **Phase 12: 內嵌清除** - Class 中 CSS/JS/HTML 全部拆到獨立檔案
- [x] **Phase 13: 樣式外部化** - Admin View 和 LIFF 模板的 inline style 移到 CSS 檔案
- [ ] **Phase 14: 檔案瘦身與方法重構** - 大檔案拆分 + 長方法縮短
- [ ] **Phase 15: 命名統一與根目錄整理** - 類名 CamelCase 統一 + 開發文件歸檔
- [ ] **Phase 16: 測試框架** - composer + phpunit + 核心服務基本覆蓋

## Phase Details

### Phase 11: 安全補齊與常數統一
**Goal**: 外掛滿足 WordPress Plugin Handbook 基本安全要求，且所有 LINE API URL 由統一常數類別管理
**Depends on**: Phase 10
**Requirements**: SEC-08, SEC-09, SEC-10, CONST-01, CONST-02
**Success Criteria** (what must be TRUE):
  1. 停用並刪除外掛後，`wp_line_hub_users` 資料表、所有 `line_hub_*` options、所有相關 transients 被完整清除（uninstall.php 生效）
  2. 直接訪問任何外掛子目錄的 URL（如 `/wp-content/plugins/line-hub/includes/`）回傳空白頁而非目錄列表（20 個 index.php 到位）
  3. 在 LINE 登入 callback URL 中注入外部網址（如 `redirect_to=https://evil.com`），系統拒絕跳轉並導回首頁（Open Redirect 修正）
  4. 用 `grep -r "https://api.line.me" includes/` 搜尋，結果為零（所有 LINE API URL 集中在常數類別）
  5. 現有功能（LINE 登入、LIFF、通知發送、Webhook）在安全補齊後全部正常運作
**Plans**: 3 plans in 2 waves

Plans:
- [x] 11-01-PLAN.md — uninstall.php 清理邏輯 + 20 個目錄 index.php 防護
- [x] 11-02-PLAN.md — Open Redirect 漏洞修正 + 輸入 sanitize 補齊
- [x] 11-03-PLAN.md — LINE API URL 統一常數類別 + 4 個檔案 URL 替換

### Phase 12: 內嵌清除
**Goal**: 所有 Class 檔案零內嵌 CSS/JS/HTML，前端資源全部透過 WordPress 標準機制載入
**Depends on**: Phase 11
**Requirements**: INLINE-01, INLINE-02, INLINE-03
**Success Criteria** (what must be TRUE):
  1. 用 `grep -rn '<style' includes/` 搜尋 Class 檔案，結果為零（CSS 全部使用 wp_enqueue_style）
  2. 用 `grep -rn '<script' includes/` 搜尋 Class 檔案，結果為零（JS 全部使用 wp_enqueue_script）
  3. Class 檔案中無大段 HTML echo/printf，HTML 全部移到 `includes/templates/` 或 `includes/views/` 模板
  4. FluentCart 產品頁的 LINE 綁定區塊正常顯示且功能不變（renderBindingSection 拆分後驗證）
  5. 後台設定頁、用戶列表欄位、歡迎 Toast 等 UI 元素外觀和行為不變
**Status**: COMPLETE (2026-02-25)
**Plans**: 2 plans in 1 wave (completed)

Plans:
- [x] 12-01-PLAN.md — UsersColumn CSS + Plugin Toast JS 提取到獨立檔案
- [x] 12-02-PLAN.md — FluentCartConnector CSS/JS/HTML 全部提取（403→185 行）

### Phase 13: 樣式外部化
**Goal**: 所有 view 模板和 LIFF 模板的 inline style 移到獨立 CSS 檔案，實現樣式集中管理
**Depends on**: Phase 12
**Requirements**: STYLE-01, STYLE-02
**Success Criteria** (what must be TRUE):
  1. 用 `grep -rn 'style="' includes/admin/ includes/views/` 搜尋 Admin view 模板，結果為零（inline style 全部移到 CSS class）
  2. LIFF 登入頁面（`liff-template.php`）和 Email 表單頁面載入獨立 CSS 檔案，頁面中無 `<style>` 區塊
  3. 後台 5 個 Tab（嚮導、LINE 設定、登入設定、Webhook、開發者）的視覺排版與重構前完全一致
  4. LIFF 在 LINE App 內開啟，登入頁面和 Email 表單的樣式正常顯示
**Status**: COMPLETE (2026-02-25)
**Plans**: 2 plans in 1 wave (completed)

Plans:
- [x] 13-01-PLAN.md — Admin view 模板 inline style 外部化到 admin-views.css（68 個 CSS class）
- [x] 13-02-PLAN.md — LIFF/Auth 模板 `<style>` 區塊提取到 3 個獨立 CSS 檔案

### Phase 14: 檔案瘦身與方法重構
**Goal**: 所有 PHP 檔案控制在 300 行以內（零 500+ 行違規），所有方法控制在 50 行以內
**Depends on**: Phase 13
**Requirements**: SIZE-01, SIZE-02, METHOD-01
**Success Criteria** (what must be TRUE):
  1. 用靜態分析掃描所有 PHP 檔案，零個超過 500 行（絕對上限達標）
  2. 用靜態分析掃描所有 PHP 檔案，零個超過 300 行（理想目標達標，或僅餘極少數 301-310 行邊界案例）
  3. 用靜態分析掃描所有方法，零個超過 50 行（28 個長方法全部重構完成）
  4. Autoloader 正確載入所有拆分後的新類別（無 class not found 錯誤）
  5. LINE 登入完整流程（OAuth callback → 帳號建立 → 重定向）在拆分後正常運作
**Plans**: TBD

### Phase 15: 命名統一與根目錄整理
**Goal**: PHP 類名風格統一為純 CamelCase，根目錄乾淨無散落開發文件
**Depends on**: Phase 14
**Requirements**: NAME-01, NAME-02
**Success Criteria** (what must be TRUE):
  1. 所有 PHP 類名使用純 CamelCase（如 `AutoUpdater`），無底線風格（如 `Auto_Updater`）
  2. Autoloader 命名規則對應正確（CamelCase 類名 → `class-kebab-case.php` 檔名）
  3. 根目錄無散落的開發日誌或文件（`DAY-*.md`、`LINE-HUB-ROADMAP.md`、`CONFLICT-FIX-REPORT.md` 等全部歸入 `.planning/` 或刪除）
  4. 外掛啟動、LINE 登入、後台設定頁在命名變更後正常運作
**Plans**: TBD

### Phase 16: 測試框架
**Goal**: 建立測試基礎設施，核心服務有基本單元測試覆蓋，`composer test` 一鍵可執行
**Depends on**: Phase 15
**Requirements**: TEST-01, TEST-02
**Success Criteria** (what must be TRUE):
  1. 執行 `composer test` 成功跑完所有測試，零失敗
  2. UserService 測試覆蓋：綁定查詢、linkUser、unlinkUser 的基本場景
  3. SettingsService 測試覆蓋：get/set/delete 和加密解密的基本場景
  4. MessagingService 測試覆蓋：訊息發送邏輯的基本場景（mock LINE API）
**Plans**: TBD

## Progress

**Execution Order:**
v1.0: 1 → 2 → 3 → 4 → 5 → 6 → 7 (done)
v2.0: 8 → 9 → 10 (done)
v3.0: 11 → 12 → 13 → 14 → 15 → 16

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Settings Foundation | v1.0 | 2/2 | Complete | 2026-02-06 |
| 2. User Management | v1.0 | 2/2 | Complete | 2026-02-07 |
| 3. OAuth Authentication | v1.0 | 3/3 | Complete | 2026-02-07 |
| 4. Notification System | v1.0 | 3/3 | Complete | 2026-02-07 |
| 5. Webhook Center | v1.0 | 3/3 | Complete | 2026-02-07 |
| 6. Plugin Integration | v1.0 | 3/3 | Complete | 2026-02-07 |
| 7. Admin UI | v1.0 | 5/5 | Complete | 2026-02-07 |
| 8. 驗證與修復 | v2.0 | 2/2 | Complete | 2026-02-24 |
| 9. Tab 重構 | v2.0 | 3/3 | Complete | 2026-02-24 |
| 10. 開發者體驗 | v2.0 | 2/2 | Complete | 2026-02-24 |
| 11. 安全補齊與常數統一 | v3.0 | 3/3 | Complete | 2026-02-24 |
| 12. 內嵌清除 | v3.0 | 2/2 | Complete | 2026-02-25 |
| 13. 樣式外部化 | v3.0 | 2/2 | Complete | 2026-02-25 |
| 14. 檔案瘦身與方法重構 | v3.0 | 0/? | Not started | - |
| 15. 命名統一與根目錄整理 | v3.0 | 0/? | Not started | - |
| 16. 測試框架 | v3.0 | 0/? | Not started | - |
