# Requirements: LINE Hub

**Defined:** 2026-02-07
**Core Value:** 讓任何 WordPress 外掛都能透過標準化的 Hook 或 REST API 發送 LINE 通知給用戶

## v1.0 Requirements (SHIPPED)

<details>
<summary>v1.0 — LINE 登入中樞（已完成，50 項需求）</summary>

### Authentication (LINE 登入系統)

- [x] **AUTH-01**: 用戶可以透過 LINE OAuth 2.0 登入
- [x] **AUTH-02**: 系統可以從 ID Token 擷取用戶 Email
- [x] **AUTH-03**: Email 無效時提供強制重新授權（force_reauth）
- [x] **AUTH-04**: Email 無效時提供手動輸入選項
- [x] **AUTH-05**: 自動建立 WordPress 帳號（username, email, display_name）
- [x] **AUTH-06**: 同步 LINE 頭像到 WordPress
- [x] **AUTH-07**: Access Token 安全儲存（加密）
- [x] **AUTH-08**: 支援 LINE 內部瀏覽器特殊設定（bot_prompt, initial_amr_display）
- [x] **AUTH-09**: State Token CSRF 防護（5 分鐘過期）
- [x] **AUTH-10**: 登入後重定向到原始頁面

### User Management (用戶管理)

- [x] **USER-01**: LINE UID ↔ WordPress User ID 綁定關係儲存
- [x] **USER-02**: 用戶可以查詢自己的 LINE 綁定狀態
- [x] **USER-03**: 用戶可以解除 LINE 綁定
- [x] **USER-04**: 用戶資料同步（display_name, picture_url, email）
- [x] **USER-05**: 防止重複綁定（UNIQUE 索引）

### Settings (設定系統)

- [x] **SETT-01**: LINE Channel 設定（Channel ID, Secret, Access Token）
- [x] **SETT-02**: LINE Login 設定（Login Channel ID, Secret）
- [x] **SETT-03**: 敏感資料加密儲存（AES-256）
- [x] **SETT-04**: 設定 Schema 驗證
- [x] **SETT-05**: 設定快取機制（Transient 1 小時）
- [x] **SETT-06**: REST API 設定端點（GET/POST）

### Integration (外掛串接系統)

- [x] **INTEG-01**: 標準化 Hook（line_hub/send/text, /flex, /broadcast）
- [x] **INTEG-02**: 標準化 Filter（line_hub/user/is_linked, /get_line_uid）
- [x] **INTEG-03**: REST API 訊息端點（/messages/text, /flex, /broadcast）
- [x] **INTEG-04**: API Key 生成與撤銷
- [x] **INTEG-05**: X-LineHub-API-Key Header 認證

### Admin UI (後台介面)

- [x] **ADMIN-01**: 後台設定頁（3 Tab：設定、登入、開發者）
- [x] **ADMIN-02**: Tab 導航系統（URL 路由 ?tab=xxx）
- [x] **ADMIN-03**: WordPress 用戶列表 LINE 綁定欄位

### LIFF (LINE 前端框架)

- [x] **LIFF-01**: LIFF 登入頁面
- [x] **LIFF-02**: LIFF Email 收集表單
- [x] **LIFF-03**: LIFF 帳號合併（同 Email → 綁定既有帳號）

### Security (安全機制)

- [x] **SEC-01**: SQL Injection 防護（$wpdb->prepare()）
- [x] **SEC-02**: XSS 防護（esc_*() 輸出轉義）
- [x] **SEC-03**: CSRF 防護（Nonce 驗證）
- [x] **SEC-04**: 權限檢查（manage_options）

</details>

## v2.0 Requirements

Requirements for milestone v2.0（重構與擴展）。根據研究確認 Phase A2/A3/C 已實作，聚焦在驗證、Tab 重構、開發者體驗。

### Verify (驗證與修復)

- [x] **VERIFY-01**: 驗證 SettingsService array 序列化功能是否正常運作，若有 Bug 則修復
- [x] **VERIFY-02**: 修復後清除 Transient 快取殘留，確保新值立即生效
- [x] **VERIFY-03**: 完整測試已實作的 5 個 Hook（send/text, send/flex, send/broadcast, user/is_linked, user/get_line_uid）
- [x] **VERIFY-04**: 完整測試已實作的 REST API 端點（/messages/text, /messages/flex, /messages/broadcast, /users/*/status）
- [x] **VERIFY-05**: API Key 認證改用 hash_equals() 取代 !== 比較（防止 Timing Attack）

### Tab Restructure (Tab 重構)

- [x] **TAB-01**: 後台設定頁從 3 Tab 重組為 5 Tab（設定嚮導、LINE 設定、登入設定、Webhook、開發者）
- [x] **TAB-02**: class-settings-page.php 拆分到 includes/admin/tabs/ 子目錄，主類別 < 200 行（188 行）
- [x] **TAB-03**: 每個 Tab 使用獨立 `<form>` 和正確的 tab hidden field，避免儲存互相干擾
- [x] **TAB-04**: 舊 Tab slug（settings, login）自動 redirect 到新 slug（developer 不變）
- [x] **TAB-05**: 拆分後所有現有功能正常運作（LINE 登入按鈕、LIFF、設定儲存）

### Developer Experience (開發者體驗)

- [x] **DEV-01**: 開發者 Tab 顯示完整 REST API 端點清單（含 curl 範例）
- [x] **DEV-02**: 開發者 Tab 顯示 Hook 使用範例（PHP 程式碼片段）
- [x] **DEV-03**: API 使用記錄：儲存並顯示最近 20 次 API 呼叫（時間、來源 IP、端點、結果）

## Future Requirements

延後到未來里程碑。已追蹤但不在 v2.0 Roadmap 中。

### Notifications (通知系統)

- **NOTIF-01**: 發送訂單通知（FluentCart）
- **NOTIF-02**: 發送出貨通知（BuyGo Plus One）
- **NOTIF-03**: 訊息模板系統（支援變數替換）
- **NOTIF-04**: 通知記錄表（最近 90 天）

### Webhook (Webhook 中心)

- **WEBHOOK-01**: 統一 Webhook 接收端點
- **WEBHOOK-02**: HMAC 簽名驗證
- **WEBHOOK-03**: 事件分類處理（message, follow, unfollow, postback）

### Security Enhancement (安全強化)

- **SEC-05**: broadcast API Rate Limiting（上限 100 人/次）
- **SEC-06**: UsersColumn N+1 查詢快取優化
- **SEC-07**: API Key 改用 bcrypt 儲存（現用 wp_hash）

## Out of Scope

明確排除，防止範圍擴張。

| Feature | Reason |
|---------|--------|
| 多語言支援 | v2 僅支援繁體中文 |
| LINE Pay 整合 | 金流功能複雜度高 |
| Rich Menu 管理 | 需要 LINE OA Manager API |
| Flex Message 編輯器 | UI 複雜度高，延後 |
| AI 自動回覆 | 超出核心範圍 |
| 即時聊天功能 | 需要 WebSocket |
| 通知模板 | 屬於業務外掛（PayGo/WebinarGo），不放在 LineHub |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| VERIFY-01 | Phase 8 | Complete |
| VERIFY-02 | Phase 8 | Complete |
| VERIFY-03 | Phase 8 | Complete |
| VERIFY-04 | Phase 8 | Complete |
| VERIFY-05 | Phase 8 | Complete |
| TAB-01 | Phase 9 | Complete |
| TAB-02 | Phase 9 | Complete |
| TAB-03 | Phase 9 | Complete |
| TAB-04 | Phase 9 | Complete |
| TAB-05 | Phase 9 | Complete |
| DEV-01 | Phase 10 | Complete |
| DEV-02 | Phase 10 | Complete |
| DEV-03 | Phase 10 | Complete |

**Coverage:**
- v2.0 requirements: 13 total
- Mapped to phases: 13 (Phase 8: 5, Phase 9: 5, Phase 10: 3)
- Unmapped: 0

---
*Requirements defined: 2026-02-07*
*Last updated: 2026-02-24 — v2.0 milestone complete (13/13 requirements)*
