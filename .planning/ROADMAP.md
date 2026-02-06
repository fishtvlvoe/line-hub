# Roadmap: LINE Hub

## Overview

LINE Hub 將從已建立的設定系統基礎（Phase 1 已完成），逐步建構完整的 LINE 整合中樞。開發路線依照「用戶管理 → 認證系統 → 通知系統 → Webhook 中心 → 外掛串接 → 後台介面」的順序，確保每個 Phase 都交付可驗證的功能。最終目標是提供完整的 LINE 登入、通知、Webhook 和第三方外掛串接功能。

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [x] **Phase 1: Settings Foundation** - 設定系統基礎建設（已完成）
- [ ] **Phase 2: User Management** - 用戶綁定與資料管理
- [ ] **Phase 3: OAuth Authentication** - LINE 登入認證系統
- [ ] **Phase 4: Notification System** - 通知發送與模板管理
- [ ] **Phase 5: Webhook Center** - Webhook 接收與處理中心
- [ ] **Phase 6: Plugin Integration** - 外掛串接系統
- [ ] **Phase 7: Admin UI** - 後台管理介面

## Phase Details

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
**Success Criteria** (what must be TRUE):
  1. 系統可以儲存 LINE UID 與 WordPress User ID 的綁定關係
  2. 用戶可以在前台查看自己的 LINE 綁定狀態（已綁定/未綁定）
  3. 用戶可以主動解除 LINE 綁定
  4. 同一個 LINE UID 不能綁定多個 WordPress 帳號（防止重複綁定）
  5. 用戶綁定後，display_name 和 picture_url 自動同步
**Plans**: TBD

Plans:
- [ ] 02-01: Database schema and UserService
- [ ] 02-02: Binding API endpoints

### Phase 3: OAuth Authentication
**Goal**: 用戶可以透過 LINE 安全登入 WordPress
**Depends on**: Phase 2
**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09, AUTH-10
**Success Criteria** (what must be TRUE):
  1. 用戶可以點擊「LINE 登入」按鈕完成 OAuth 認證
  2. 系統可以從 ID Token 自動擷取用戶 Email
  3. Email 無效時，用戶可以選擇「強制重新授權」或「手動輸入 Email」
  4. 新用戶登入後自動建立 WordPress 帳號（含 username, email, display_name）
  5. 登入完成後自動重定向到原始頁面（非首頁或後台）
**Plans**: TBD

Plans:
- [ ] 03-01: OAuth Client and State Manager
- [ ] 03-02: Email handling and force_reauth
- [ ] 03-03: WordPress account creation and login

### Phase 4: Notification System
**Goal**: 系統可以發送各種場景的 LINE 通知
**Depends on**: Phase 2
**Requirements**: NOTIF-01, NOTIF-02, NOTIF-03, NOTIF-04, NOTIF-05, NOTIF-06, NOTIF-07, NOTIF-08
**Success Criteria** (what must be TRUE):
  1. 當 FluentCart 訂單建立時，用戶收到 LINE 通知
  2. 當 BuyGo 出貨時，用戶收到 LINE 通知
  3. 用戶註冊後收到歡迎訊息
  4. 管理員可以在後台編輯通知訊息模板（支援 {order_id} 等變數）
  5. 系統保留最近 90 天的通知記錄
**Plans**: TBD

Plans:
- [ ] 04-01: MessagingService and template engine
- [ ] 04-02: Notification scenes and triggers
- [ ] 04-03: Notification logs and statistics

### Phase 5: Webhook Center
**Goal**: 統一接收和處理所有 LINE Webhook 事件
**Depends on**: Phase 2
**Requirements**: WEBHOOK-01, WEBHOOK-02, WEBHOOK-03, WEBHOOK-04, WEBHOOK-05, WEBHOOK-06, WEBHOOK-07
**Success Criteria** (what must be TRUE):
  1. Webhook 端點 `/wp-json/line-hub/v1/webhook` 可正常接收事件
  2. 只有通過 HMAC 簽名驗證的 Webhook 才會被處理
  3. 系統可以分類處理 message、follow、unfollow、postback 事件
  4. 管理員可以在後台查看最近 100 筆 Webhook 記錄
  5. 重複的 Webhook 事件不會被重複處理（去重機制）
**Plans**: TBD

Plans:
- [ ] 05-01: Webhook receiver and HMAC validation
- [ ] 05-02: Event dispatcher and deduplication
- [ ] 05-03: Webhook logs admin view

### Phase 6: Plugin Integration
**Goal**: 其他外掛可以輕鬆串接 LINE Hub
**Depends on**: Phase 4, Phase 5
**Requirements**: INTEG-01, INTEG-02, INTEG-03, INTEG-04, INTEG-05, INTEG-06
**Success Criteria** (what must be TRUE):
  1. 其他外掛可以監聽 `line_hub/user_logged_in` Hook
  2. 其他外掛可以透過 `line_hub/message/before_send` Filter 修改訊息
  3. FluentCart 訂單建立時自動觸發通知（透過 Hook 監聽）
  4. BuyGo 出貨時自動觸發通知（透過 Hook 監聯）
  5. LINE Hub 的 Hook 優先級不會與 NSL 衝突
**Plans**: TBD

Plans:
- [ ] 06-01: Hook registry and event dispatcher
- [ ] 06-02: FluentCart and BuyGo connectors
- [ ] 06-03: WordPress core hooks (user_register, retrieve_password)

### Phase 7: Admin UI
**Goal**: 管理員可以透過直覺的後台介面管理所有功能
**Depends on**: Phase 3, Phase 4, Phase 5, Phase 6
**Requirements**: ADMIN-01, ADMIN-02, ADMIN-03, ADMIN-04, ADMIN-05, ADMIN-06, ADMIN-07, ADMIN-08, SEC-01, SEC-02, SEC-03, SEC-04, SEC-05, SEC-06, PERF-01, PERF-02, PERF-03, PERF-04, PERF-05
**Success Criteria** (what must be TRUE):
  1. 後台有完整的 Tab 導航（入門、設定、通知、Webhook、用法）
  2. 入門 Tab 有清楚的快速開始引導（3 個步驟）
  3. 後台介面採用 FluentCommunity/FluentCRM 風格設計
  4. 所有後台操作都有適當的權限檢查（manage_options）
  5. 後台在手機、平板、桌面都能正常顯示（響應式設計）
**Plans**: TBD

Plans:
- [ ] 07-01: Tab navigation system and routing
- [ ] 07-02: Getting Started and Settings tabs
- [ ] 07-03: Notifications and Webhook tabs
- [ ] 07-04: Usage tab and security hardening
- [ ] 07-05: Performance optimization and final polish

## Progress

**Execution Order:**
Phases execute in numeric order: 1 (done) -> 2 -> 3 -> 4 -> 5 -> 6 -> 7

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Settings Foundation | 2/2 | Complete | 2026-02-06 |
| 2. User Management | 0/2 | Not started | - |
| 3. OAuth Authentication | 0/3 | Not started | - |
| 4. Notification System | 0/3 | Not started | - |
| 5. Webhook Center | 0/3 | Not started | - |
| 6. Plugin Integration | 0/3 | Not started | - |
| 7. Admin UI | 0/5 | Not started | - |
