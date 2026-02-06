# Requirements: LINE Hub

**Defined:** 2026-02-07
**Core Value:** 成為 WordPress 的 LINE 整合中樞，提供完整的 LINE 登入、通知、Webhook 和第三方外掛串接功能

## v1 Requirements

Requirements for initial release (v1.0 - 3 weeks). Each maps to roadmap phases.

### Authentication (LINE 登入系統)

- [ ] **AUTH-01**: 用戶可以透過 LINE OAuth 2.0 登入
- [ ] **AUTH-02**: 系統可以從 ID Token 擷取用戶 Email
- [ ] **AUTH-03**: Email 無效時提供強制重新授權（force_reauth）
- [ ] **AUTH-04**: Email 無效時提供手動輸入選項
- [ ] **AUTH-05**: 自動建立 WordPress 帳號（username, email, display_name）
- [ ] **AUTH-06**: 同步 LINE 頭像到 WordPress
- [ ] **AUTH-07**: Access Token 安全儲存（加密）
- [ ] **AUTH-08**: 支援 LINE 內部瀏覽器特殊設定（bot_prompt, initial_amr_display）
- [ ] **AUTH-09**: State Token CSRF 防護（5 分鐘過期）
- [ ] **AUTH-10**: 登入後重定向到原始頁面

### User Management (用戶管理)

- [ ] **USER-01**: LINE UID ↔ WordPress User ID 綁定關係儲存
- [ ] **USER-02**: 用戶可以查詢自己的 LINE 綁定狀態
- [ ] **USER-03**: 用戶可以解除 LINE 綁定
- [ ] **USER-04**: 用戶資料同步（display_name, picture_url, email）
- [ ] **USER-05**: 防止重複綁定（UNIQUE 索引）

### Notifications (通知系統)

- [ ] **NOTIF-01**: 發送訂單通知（FluentCart）
- [ ] **NOTIF-02**: 發送出貨通知（BuyGo Plus One）
- [ ] **NOTIF-03**: 發送會員歡迎訊息
- [ ] **NOTIF-04**: 發送密碼重設通知
- [ ] **NOTIF-05**: 訊息模板系統（支援變數替換）
- [ ] **NOTIF-06**: 後台訊息模板編輯器
- [ ] **NOTIF-07**: 通知記錄表（最近 90 天）
- [ ] **NOTIF-08**: 通知成功率統計

### Webhook (Webhook 中心)

- [ ] **WEBHOOK-01**: 統一 Webhook 接收端點（`/wp-json/line-hub/v1/webhook`）
- [ ] **WEBHOOK-02**: HMAC 簽名驗證
- [ ] **WEBHOOK-03**: 事件分類處理（message, follow, unfollow, postback）
- [ ] **WEBHOOK-04**: Webhook 記錄表（最近 100 筆）
- [ ] **WEBHOOK-05**: 去重機制（避免重複處理）
- [ ] **WEBHOOK-06**: 非同步處理（立即返回 200 OK）
- [ ] **WEBHOOK-07**: 後台查看 Webhook 記錄

### Integration (外掛串接系統)

- [ ] **INTEG-01**: 提供標準化 Hook（`line_hub/user_logged_in`）
- [ ] **INTEG-02**: 提供標準化 Filter（`line_hub/message/before_send`）
- [ ] **INTEG-03**: 監聽 FluentCart Hook（`fluent_cart/order_created`）
- [ ] **INTEG-04**: 監聯 BuyGo Hook（`buygo/shipment/marked_as_shipped`）
- [ ] **INTEG-05**: 監聽 WordPress Hook（`user_register`, `retrieve_password`）
- [ ] **INTEG-06**: Hook 優先級策略（避免與 NSL 衝突）

### Settings (設定系統)

- [x] **SETT-01**: LINE Channel 設定（Channel ID, Secret, Access Token）
- [x] **SETT-02**: LINE Login 設定（Login Channel ID, Secret）
- [x] **SETT-03**: 敏感資料加密儲存（AES-256）
- [x] **SETT-04**: 設定 Schema 驗證
- [x] **SETT-05**: 設定快取機制（Transient 1 小時）
- [x] **SETT-06**: REST API 設定端點（GET/POST）

### Admin UI (後台介面)

- [ ] **ADMIN-01**: 入門 Tab（快速開始引導）
- [ ] **ADMIN-02**: 設定 Tab（LINE Channel 設定）
- [ ] **ADMIN-03**: 通知 Tab（通知場景管理）
- [ ] **ADMIN-04**: Webhook Tab（記錄和測試）
- [ ] **ADMIN-05**: 用法 Tab（Shortcode 和 API 文檔）
- [ ] **ADMIN-06**: Tab 導航系統（URL 路由 `?tab=xxx`）
- [ ] **ADMIN-07**: FluentCommunity/FluentCRM 風格設計
- [ ] **ADMIN-08**: 響應式設計（手機、平板、桌面）

### Security (安全機制)

- [ ] **SEC-01**: SQL Injection 防護（`$wpdb->prepare()`）
- [ ] **SEC-02**: XSS 防護（`esc_*()` 輸出轉義）
- [ ] **SEC-03**: CSRF 防護（Nonce 驗證）
- [ ] **SEC-04**: 權限檢查（`manage_options`）
- [ ] **SEC-05**: Rate Limiting（60 req/min）
- [ ] **SEC-06**: 敏感資料遮罩（GET API 回應）

### Performance (效能要求)

- [ ] **PERF-01**: Webhook 接收 < 100ms
- [ ] **PERF-02**: 背景處理 < 300ms
- [ ] **PERF-03**: 資料庫索引優化
- [ ] **PERF-04**: API 快取（Transient 1 小時）
- [ ] **PERF-05**: 資料清理策略（Webhook 30 天、通知 90 天）

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### LIFF 功能

- **LIFF-01**: LIFF 登入頁面
- **LIFF-02**: LIFF 內嵌網頁
- **LIFF-03**: LIFF SDK 整合

### Rich Menu 管理

- **MENU-01**: 後台建立 Rich Menu
- **MENU-02**: 視覺化編輯器
- **MENU-03**: Rich Menu 發布和管理

### Flex Message 編輯器

- **FLEX-01**: 後台 Flex Message 編輯器
- **FLEX-02**: Flex Message 模板庫
- **FLEX-03**: Flex Message 預覽

### AI 自動回覆

- **AI-01**: 整合 OpenAI API
- **AI-02**: 自動回覆規則設定
- **AI-03**: 學習對話歷史

### WooCommerce 整合

- **WOO-01**: WooCommerce 訂單通知
- **WOO-02**: WooCommerce 出貨通知
- **WOO-03**: WooCommerce 產品上架通知

### FluentCRM 整合

- **CRM-01**: FluentCRM 行銷自動化通知
- **CRM-02**: FluentCRM 標籤同步
- **CRM-03**: FluentCRM 聯絡人同步

## Out of Scope

Explicitly excluded. Documented to prevent scope creep.

| Feature | Reason |
|---------|--------|
| 多語言支援 | v1 僅支援繁體中文，國際化功能延後到 v2 |
| LINE Pay 整合 | 金流功能複雜度高，不在 MVP 範圍 |
| LINE Points 整合 | 需要企業級帳號，不適合一般用戶 |
| LINE Beacon | 硬體需求，使用場景有限 |
| LINE Things | IoT 整合超出外掛範圍 |
| 即時聊天功能 | 需要 WebSocket，技術複雜度高 |
| 群組訊息發送 | 需要 LINE Official Account Manager API，超出範圍 |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| SETT-01 | Phase 1 | Complete |
| SETT-02 | Phase 1 | Complete |
| SETT-03 | Phase 1 | Complete |
| SETT-04 | Phase 1 | Complete |
| SETT-05 | Phase 1 | Complete |
| SETT-06 | Phase 1 | Complete |
| USER-01 | Phase 2 | Complete |
| USER-02 | Phase 2 | Complete |
| USER-03 | Phase 2 | Complete |
| USER-04 | Phase 2 | Complete |
| USER-05 | Phase 2 | Complete |
| AUTH-01 | Phase 3 | Pending |
| AUTH-02 | Phase 3 | Pending |
| AUTH-03 | Phase 3 | Pending |
| AUTH-04 | Phase 3 | Pending |
| AUTH-05 | Phase 3 | Pending |
| AUTH-06 | Phase 3 | Pending |
| AUTH-07 | Phase 3 | Pending |
| AUTH-08 | Phase 3 | Pending |
| AUTH-09 | Phase 3 | Pending |
| AUTH-10 | Phase 3 | Pending |
| NOTIF-01 | Phase 4 | Pending |
| NOTIF-02 | Phase 4 | Pending |
| NOTIF-03 | Phase 4 | Pending |
| NOTIF-04 | Phase 4 | Pending |
| NOTIF-05 | Phase 4 | Pending |
| NOTIF-06 | Phase 4 | Pending |
| NOTIF-07 | Phase 4 | Pending |
| NOTIF-08 | Phase 4 | Pending |
| WEBHOOK-01 | Phase 5 | Pending |
| WEBHOOK-02 | Phase 5 | Pending |
| WEBHOOK-03 | Phase 5 | Pending |
| WEBHOOK-04 | Phase 5 | Pending |
| WEBHOOK-05 | Phase 5 | Pending |
| WEBHOOK-06 | Phase 5 | Pending |
| WEBHOOK-07 | Phase 5 | Pending |
| INTEG-01 | Phase 6 | Pending |
| INTEG-02 | Phase 6 | Pending |
| INTEG-03 | Phase 6 | Pending |
| INTEG-04 | Phase 6 | Pending |
| INTEG-05 | Phase 6 | Pending |
| INTEG-06 | Phase 6 | Pending |
| ADMIN-01 | Phase 7 | Pending |
| ADMIN-02 | Phase 7 | Pending |
| ADMIN-03 | Phase 7 | Pending |
| ADMIN-04 | Phase 7 | Pending |
| ADMIN-05 | Phase 7 | Pending |
| ADMIN-06 | Phase 7 | Pending |
| ADMIN-07 | Phase 7 | Pending |
| ADMIN-08 | Phase 7 | Pending |
| SEC-01 | Phase 7 | Pending |
| SEC-02 | Phase 7 | Pending |
| SEC-03 | Phase 7 | Pending |
| SEC-04 | Phase 7 | Pending |
| SEC-05 | Phase 7 | Pending |
| SEC-06 | Phase 7 | Pending |
| PERF-01 | Phase 7 | Pending |
| PERF-02 | Phase 7 | Pending |
| PERF-03 | Phase 7 | Pending |
| PERF-04 | Phase 7 | Pending |
| PERF-05 | Phase 7 | Pending |

**Coverage:**
- v1 requirements: 50 total
- Mapped to phases: 50/50
- Unmapped: 0

---
*Requirements defined: 2026-02-07*
*Last updated: 2026-02-07 after Phase 3 plan revision*
