# Roadmap: LINE Hub

## Milestones

- ✅ **v1.0 LINE 登入中樞** - Phases 1-7 (shipped 2026-02-07)
- 🚧 **v2.0 重構與擴展** - Phases 8-10 (in progress)

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
  1. 系統可以儲存 LINE UID 與 WordPress User ID 的綁定關係 ✓
  2. 用戶可以在前台查看自己的 LINE 綁定狀態（已綁定/未綁定） ✓
  3. 用戶可以主動解除 LINE 綁定 ✓
  4. 同一個 LINE UID 不能綁定多個 WordPress 帳號（防止重複綁定） ✓
  5. 用戶綁定後，display_name 和 picture_url 自動同步 ✓
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
  1. 用戶可以點擊「LINE 登入」按鈕完成 OAuth 認證 ✓
  2. 系統可以從 ID Token 自動擷取用戶 Email ✓
  3. Email 無效時，用戶可以選擇「強制重新授權」或「手動輸入 Email」 ✓
  4. 新用戶登入後自動建立 WordPress 帳號（含 username, email, display_name）✓
  5. 登入完成後自動重定向到原始頁面（非首頁或後台）✓
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
  4. BuyGo 出貨時自動觸發通知（透過 Hook 監聽）
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

### 🚧 v2.0 重構與擴展 (In Progress)

**Milestone Goal:** 將 LineHub 從開發中的半成品升級為可上架的成熟 LINE 通訊平台——修復已知 bug、拆解過胖的設定頁、補齊開發者工具，讓 BuyGo/WebinarGo 等外掛可以無縫串接。

- [ ] **Phase 8: 驗證與修復** - 確認現況並修復安全問題
- [ ] **Phase 9: Tab 重構** - 後台設定頁拆分與介面重組
- [ ] **Phase 10: 開發者體驗** - 補齊文件與 API 使用記錄

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
**Plans**: TBD

Plans:
- [ ] 08-01: 驗證腳本執行（array bug、Hook、REST API、UsersColumn）
- [ ] 08-02: 安全修復（hash_equals、broadcast 上限、快取清除機制）

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
**Plans**: TBD

Plans:
- [ ] 09-01: Tab 架構拆分（主類別瘦身、tabs/ 子目錄建立）
- [ ] 09-02: 5 Tab 重組（新 slug、獨立 form、舊 slug redirect）
- [ ] 09-03: 整合驗證（所有現有功能回歸測試）

### Phase 10: 開發者體驗
**Goal**: 開發者可以在後台直接找到 LINE Hub 整合所需的所有資訊，不需查閱外部文件
**Depends on**: Phase 9
**Requirements**: DEV-01, DEV-02, DEV-03
**Success Criteria** (what must be TRUE):
  1. 開發者 Tab 顯示所有 REST API 端點清單，每個端點有對應的 curl 指令可直接複製貼上測試
  2. 開發者 Tab 顯示 Hook 使用說明，包含 `do_action('line_hub/send/text', ...)` 的完整參數範例，複製後可直接貼入外掛程式碼執行
  3. 開發者可以在後台查看最近 20 次 API 呼叫記錄（時間、來源 IP、端點、成功/失敗），快速確認外掛串接是否正常
**Plans**: TBD

Plans:
- [ ] 10-01: REST API 文件 Tab（端點清單、curl 範例、API Key 生成提示）
- [ ] 10-02: Hook 文件與 API 使用記錄（PHP 範例、呼叫 log 顯示）

## Progress

**Execution Order:**
v1.0: 1 → 2 → 3 → 4 → 5 → 6 → 7 (done)
v2.0: 8 → 9 → 10

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Settings Foundation | v1.0 | 2/2 | Complete | 2026-02-06 |
| 2. User Management | v1.0 | 2/2 | Complete | 2026-02-07 |
| 3. OAuth Authentication | v1.0 | 3/3 | Complete | 2026-02-07 |
| 4. Notification System | v1.0 | 3/3 | Complete | 2026-02-07 |
| 5. Webhook Center | v1.0 | 3/3 | Complete | 2026-02-07 |
| 6. Plugin Integration | v1.0 | 3/3 | Complete | 2026-02-07 |
| 7. Admin UI | v1.0 | 5/5 | Complete | 2026-02-07 |
| 8. 驗證與修復 | v2.0 | 0/2 | Not started | - |
| 9. Tab 重構 | v2.0 | 0/3 | Not started | - |
| 10. 開發者體驗 | v2.0 | 0/2 | Not started | - |
