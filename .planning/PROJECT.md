# LINE Hub - WordPress LINE 整合中樞

## What This Is

LINE Hub 是一個專為 WordPress 打造的 LINE 整合中樞外掛，提供 LINE 登入（OAuth + LIFF）、通知發送、Webhook 處理和第三方外掛串接功能。它是 BuyGo 生態系的底層通訊平台，WebinarGo、PayGo 等外掛都透過 LineHub 的 Hook/API 發送 LINE 通知。

## Core Value

讓任何 WordPress 外掛都能透過標準化的 Hook 或 REST API 發送 LINE 通知給用戶，不需要自己處理 LINE API。

## Current Milestone: v2.0 重構與擴展

**Goal:** 將 LineHub 從開發中的半成品升級為可上架的成熟 LINE 通訊平台

**Target features:**
- Bug 修復（SettingsService 陣列存儲、用戶列表 LINE 欄位、移除空 Tab）
- 後台 Tab 重構（5 Tab 重分類 + 熵減拆檔）
- 擴張架構（標準 Hook 介面 + REST API + API Key 管理）

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

<!-- v2.0 Milestone scope -->

- [ ] Bug 修復（SettingsService、用戶列表、空 Tab）
- [ ] 後台 Tab 重構 + 熵減
- [ ] 標準化 Hook 介面
- [ ] REST API 訊息端點
- [ ] API Key 管理

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

### 已知問題（v2.0 要修）

- SettingsService `set()` 對 array 類型存入 `"Array"` 字串
- WordPress 用戶列表沒有 LINE 綁定狀態欄位
- 通知 Tab 是空殼（只有「待開發」文字）
- `class-settings-page.php` 34KB，嚴重超過 300 行上限
- Tab 分類不直覺（設定混雜、入門和設定重疊）
- 各 Tab 共用一個 form，儲存時會互相干擾
- Hook 介面未標準化（各外掛各自呼叫，沒有統一格式）
- 無 REST API 給外部服務使用
- 無 API Key 認證機制

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
| v2.0 先做完 A+B+C 再開 WebinarGo | LineHub 要先成熟，WebinarGo 用戶才不會看到半成品 | — Pending |

---
*Last updated: 2026-02-24 after v2.0 milestone start*
