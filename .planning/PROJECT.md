# LINE Hub - WordPress LINE 整合中樞

## What This Is

LINE Hub 是一個專為 WordPress 打造的 LINE 整合中樞外掛，提供 LINE 登入（OAuth + LIFF）、通知發送、Webhook 處理和第三方外掛串接功能。它是 BuyGo 生態系的底層通訊平台，WebinarGo、PayGo 等外掛都透過 LineHub 的 Hook/API 發送 LINE 通知。

## Core Value

讓任何 WordPress 外掛都能透過標準化的 Hook 或 REST API 發送 LINE 通知給用戶，不需要自己處理 LINE API。

## Current Milestone: v3.0 熵減重構

**Goal:** 全面整理程式碼結構，為 WebinarGo 開發打穩地基。所有檔案 < 300 行、Class 零內嵌、統一常數管理、安全補齊、基礎測試覆蓋。

**Target features:**
- 安全補齊（uninstall.php + index.php + 輸入驗證）
- 常數統一（LINE API URL 集中管理）
- 內嵌清除（Class 中的 CSS/JS/HTML 全部拆到獨立檔案）
- 大檔案拆分（4 個 500+ 行、11 個 300~500 行全部縮減）
- Admin Views inline style 清理
- 長方法重構（28 個 50+ 行方法拆短）
- 命名統一 + 根目錄整理
- 基礎測試框架

## Requirements

### Validated

<!-- Shipped and confirmed valuable. v1.0 Phase 1-7 -->

- ✓ **SETT-01~06**: 設定系統（加密儲存、快取、Schema 驗證、REST API）— v1.0 Phase 1
- ✓ **USER-01~05**: 用戶管理（LINE UID 綁定、查詢、解除、同步、防重複）— v1.0 Phase 2
- ✓ **AUTH-01~10**: OAuth 登入（LINE Login、Email 擷取、強制重新授權、CSRF 防護）— v1.0 Phase 3
- ✓ LIFF 登入（LINE 內部瀏覽器、Email 表單、帳號合併）— v1.0 Phase 3.5
- ✓ 登入按鈕元件（FluentCart 產品頁整合、CSS/JS 隔離）— v1.0 Phase 3.5
- ✓ 通知系統（MessagingService、模板引擎、Flex 訊息）— v1.0 Phase 4
- ✓ Webhook 中心（接收、分發、關鍵字回應、事件記錄）— v1.0 Phase 5
- ✓ 外掛串接（BuyGo/FluentCart 連接器、Hook 通訊）— v1.0 Phase 6
- ✓ 基本後台 UI（5 Tab 設定頁）— v1.0 Phase 7
- ✓ NSL 頭像 fallback + CLI 遷移指令 — v1.0 修補

### Active

<!-- v3.0 Milestone scope -->

- [ ] 安全補齊（uninstall.php、index.php、輸入 sanitize）
- [ ] LINE API 常數統一管理
- [ ] Class 中內嵌 CSS/JS/HTML 清除（9 處）
- [ ] 大檔案拆分（15 個超過 300 行）
- [ ] Admin Views inline style → CSS 檔案
- [ ] 長方法重構（28 個超過 50 行）
- [ ] 類名統一 + 根目錄整理
- [ ] 基礎測試框架（composer + phpunit）

### Out of Scope

<!-- Explicit boundaries. -->

- Rich Menu 管理 — UI 複雜度高，非核心功能
- Flex Message 視覺化編輯器 — 開發成本高，用 JSON 模板足夠
- AI 自動回覆 — 需要額外 API 依賴（OpenAI），非核心
- LINE Pay 整合 — 金流由 PayGo 處理，不放在 LineHub
- 多語言支援 — v1 僅支援繁體中文
- 拖拉式表單編輯器 — WebinarGo 表單用 Shortcode + CSS 繼承
- 即時聊天功能 — 需要 WebSocket，技術複雜度高

## Context

### 生態系定位

```
FluentCart（電商引擎）
├── PayGo（金流 + 電子發票）
├── BuyGo（賣場 ERP）
└── LineHub（LINE 通訊中心）← 底層平台

WebinarGo（研討會通知）← 獨立外掛，透過 LineHub 發通知
```

### 技術環境

- WordPress 6.x + PHP 8.0+
- FluentCart、BuyGo Plus One 生態整合
- 本機開發：test.buygo.me（Cloudflare Tunnel）
- 雲端正式：buygo.me、one.buygo.me（InstaWP）
- LINE Login Channel + LINE Messaging API

### LINE 三張身份表

- `wp_social_users`（NSL）— 舊系統，LineHub 有 fallback 查詢
- `wp_buygo_line_users`（LINE Notify）— 已被 LineHub 取代
- `wp_line_hub_users`（LineHub）— 主要系統

### 已知問題（v3.0 要修）

- 4 個檔案超過 500 行（最嚴重 670 行）
- 11 個檔案介於 300~500 行
- 28 個方法超過 50 行
- Class 中 5 處 `<style>` + 5 處 `<script>` 內嵌
- 8 處 Class 直接輸出 HTML
- LINE API URL 重複定義 4 處
- 21 個目錄缺 index.php、缺 uninstall.php
- 8 處輸入未 sanitize（含 Open Redirect 風險）
- 類名風格不統一（底線 vs CamelCase）
- 0 個單元測試
- 91 處 error_log 殘留

## Constraints

- **熵減原則**: 單一檔案 < 300 行（理想）、< 500 行（上限），PHP 零內嵌 HTML
- **WordPress 規範**: WP Plugin Handbook 目錄結構、全域名稱加 `line_hub_` 前綴
- **向後相容**: 現有的 `[line_hub_login]` shortcode 和 `line_hub/user_logged_in` hook 不變
- **安全性**: AES-256 加密、HMAC 驗證、Rate Limiting、Nonce 驗證
- **隔離**: 外掛間只透過 WordPress hooks 通訊，不直接引用業務邏輯

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| 獨立實現 OAuth（不依賴 NSL） | 完全掌控 LINE 登入流程 | ✓ Good |
| Hook-based 外掛串接架構 | 最大化彈性和擴展性 | ✓ Good |
| NSL fallback 唯讀 | 不自動遷移 NSL 資料，避免破壞性操作 | ✓ Good |
| 通知模板歸業務外掛 | LineHub 不存業務模板，避免膨脹 | ✓ Good |
| PHP inline include 繞過 WAF | InstaWP 目錄權限 700 導致 403 | ⚠️ Revisit（已修正權限） |
| getPictureUrl() 不回傳 Gravatar | 避免 Gravatar URL 阻擋 NSL 頭像 fallback | ✓ Good |
| v2.0 先做完 A+B+C 再開 WebinarGo | LineHub 要先成熟，WebinarGo 用戶才不會看到半成品 | ✓ Good（v2.0 已完成） |
| v3.0 熵減優先於 WebinarGo | 地基穩了才往上蓋，避免每開新外掛都回來改 LineHub | — Active |

---
*Last updated: 2026-02-24 after v3.0 milestone start*
