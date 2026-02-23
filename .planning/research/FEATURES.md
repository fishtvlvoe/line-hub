# Feature Research

**Domain:** WordPress LINE 整合通訊平台（v2.0 重構與擴展）
**Researched:** 2026-02-24
**Confidence:** HIGH（核心需求來自既有計畫文件）/ MEDIUM（競品分析來自 WebSearch）

---

## 背景：v2.0 的三個工作區

v2.0 不是從頭打造，而是把半成品升級為可上架的成熟平台。三個工作區：

- **Phase A：緊急修復** — BUG 修復 + 空殼清除
- **Phase B：後台 Tab 重構** — UX 提升 + 熵減拆檔
- **Phase C：擴展架構** — Hook 標準化 + REST API + API Key

---

## Feature Landscape

### Table Stakes（用戶預期必備）

這些是「沒有就是壞掉」的功能，缺少任何一個都讓外掛看起來像半成品。

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **設定儲存正確性** | 任何設定外掛的基本保證 | LOW | A1：SettingsService array bug，存入 `"Array"` 字串而非 JSON |
| **後台 Tab 切換正常** | WordPress 外掛標準體驗 | LOW | 目前通知 Tab 是空殼，顯示「待開發」 |
| **用戶 LINE 綁定狀態可見** | 管理員需要了解誰綁了 LINE | LOW | A2：users.php 沒有 LINE 欄位 |
| **設定分組不互相干擾** | 改一個 Tab 不該清空另一個 Tab | MEDIUM | B3：目前所有設定共用一個 `<form>` |
| **設定頁 Tab 直覺分類** | 用戶能找到想找的設定 | MEDIUM | 舊 5 Tab 分類混亂，入門和設定重疊 |
| **空功能 Tab 必須移除** | 空 Tab 損害可信度 | LOW | 通知 Tab 是空殼，立即移除 |
| **連線狀態可驗證** | 設定後要知道有沒有成功接上 LINE | MEDIUM | 目前有連線測試但位置不直覺 |
| **登入按鈕實際顯示** | 設定了位置就要出現按鈕 | MEDIUM | B6：ButtonPositions 掛載器缺失 |

**信心度：HIGH** — 這些問題已在 `LINEHUB-RESTRUCTURE-PLAN.md` 和 `LINEHUB-ADMIN-RESTRUCTURE.md` 中明確記錄。

---

### Differentiators（競爭差異化）

這些是 LineHub 區別於其他 LINE 外掛的核心優勢。

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **標準化 Hook 介面** | 任何 WP 外掛只需一行 `do_action` 就能發 LINE 通知 | MEDIUM | C2：`line_hub/send/text`, `line_hub/send/flex`, `line_hub/send/broadcast` |
| **REST API + API Key 認證** | 外部 SaaS（Zapier、WebinarJam）直接呼叫 | HIGH | C3/C5：`POST /line-hub/v1/messages/*` + `X-LineHub-API-Key` |
| **雙通道整合架構** | WP 外掛用 Hook，外部 SaaS 用 REST API，LineHub 統一處理 | HIGH | 業界唯一提供雙通道的 LINE WP 外掛 |
| **三張身份表 Fallback** | NSL 舊系統用戶不需遷移，LineHub 自動查詢 | MEDIUM | 已完成，保持向後相容 |
| **設定 Tab 獨立 Form 隔離** | 改 LINE Channel 設定不會影響登入按鈕設定 | LOW | B3：每個 Tab 獨立 form + hidden tab 欄位 |
| **開發者 Tab 整合文件** | 開發者不用查外部文件，後台就有完整範例 | LOW | Shortcodes、Hooks、REST API curl 範例 |
| **API Key 一次顯示安全模式** | 符合業界安全標準（GitHub、Stripe 同款 UX） | LOW | 生成後只顯示一次，DB 只存 hash |

**信心度：MEDIUM** — Hook 介面設計和雙通道架構是 LineHub 的設計決策（來自計畫文件），競品分析顯示市場上沒有同等方案。

---

### Anti-Features（常被要求但應明確拒絕）

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| **Rich Menu 視覺化編輯器** | 看起來很完整 | UI 複雜度高，維護成本極大，LINE API 限制多 | 提供 JSON 模板 + 開發者 Tab 文件 |
| **Flex Message 拖拉編輯器** | 用戶想自訂外觀 | 開發成本 2-3 個 Phase，且 LINE Flex 規格複雜 | 提供 JSON 模板 + 預覽功能（TEXT ONLY） |
| **AI 自動回覆** | 看起來智能 | 需要 OpenAI API 依賴，增加維護風險，不是核心 | Webhook 事件 + 關鍵字回應（已有）|
| **多個 API Key** | 不同整合用不同 Key | 目前單 Key 已足夠，多 Key 管理介面複雜度 x3 | 若需求明確才加，v2.0 只做單 Key |
| **LINE Pay 整合** | 金流完整性 | 由 PayGo 外掛處理，LineHub 不應承擔金流責任 | 透過 Hook 讓 PayGo 觸發通知即可 |
| **即時聊天（WebSocket）** | 雙向溝通 | 需要 WebSocket 基礎設施，WordPress 環境不適合 | Webhook 接收 + 關鍵字回應即可 |
| **產品頁登入按鈕** | 更多曝光點 | 用戶在瀏覽時不需要登入，干擾體驗，已改為結帳頁 | 只在真正需要登入的場景顯示（WP 登入頁、結帳頁）|
| **WordPress Application Passwords 取代 API Key** | WordPress 內建支援 | Application Passwords 需要 WP 用戶帳號，不適合 SaaS 對 SaaS | 自訂 API Key（Header 認證）更適合外部服務整合 |

**信心度：HIGH** — Anti-features 來自既有計畫文件的明確 Out of Scope 聲明。WordPress Application Passwords vs API Key 的判斷基於官方文件研究（MEDIUM）。

---

## Feature Dependencies

```
[A1 SettingsService Bug Fix]
    └──required-by──> [B3 Tab Form 隔離]（儲存正確才能測試 Tab 獨立性）
    └──required-by──> [C5 API Key 儲存]（api_key 是 string 類型，不受 array bug 影響，但修完更安全）

[A2 Users 列表 LINE 欄位]
    ──independent──（無依賴，可平行執行）

[A3 移除空 Tab]
    ──independent──（無依賴，可平行執行）

[B1 Tab 結構設計]
    └──required-by──> [B2 檔案拆分]
    └──required-by──> [B3 Form 隔離]

[B2 檔案拆分（熵減）]
    └──requires──> [B1 Tab 結構確定]

[B3 Form 隔離]
    └──requires──> [A1 Bug Fix]（儲存正確）
    └──requires──> [B2 檔案拆分]（各 Tab 有獨立 view 檔案）

[B6 ButtonPositions 掛載器]
    └──requires──> [A1 Bug Fix]（array 儲存正確才能讀取 positions）
    └──requires──> [B3/B4 Login Tab]（設定 UI 要先存在）

[C2 Hook 介面標準化]
    └──requires──> [Phase B 完成]（Tab 穩定，設定可靠）

[C3 REST API 端點]
    └──requires──> [C2 Hook 介面]（REST API 底層呼叫 Hook 層）

[C5 API Key 管理]
    └──requires──> [C3 REST API]（Key 用於驗證 REST API 請求）
    └──requires──> [B5 Developer Tab]（API Key UI 在開發者 Tab 裡）
```

### Dependency Notes

- **A1 是整個 v2.0 的解鎖器：** array bug 不修，所有涉及 array 類型設定的功能都不可靠
- **Phase A 三個任務可平行：** A1/A2/A3 互相獨立，可以同時執行
- **Phase B 依賴 Phase A1：** Tab 重構要確保設定儲存正確才有意義驗證
- **Phase C 依賴 Phase B 穩定：** 擴展架構建立在後台穩定基礎上
- **B6 ButtonPositions 依賴 A1：** login_button_positions 是 array 類型，A1 不修則按鈕永遠不出現

---

## MVP Definition

這是 v2.0 milestone，不是全新專案。MVP = 達到「可上架的成熟外掛」標準。

### Phase A 必須完成（v2.0 入門條件）

- [x] A1：SettingsService array 儲存 BUG 修復 — 設定不正確等於外掛損壞
- [x] A2：Users 列表 LINE 欄位 — 管理員基本需求
- [x] A3：移除通知 Tab 空殼 — 空 Tab 直接損害可信度

### Phase B 必須完成（v2.0 核心）

- [x] B1：Tab 結構重設計（3 Tab：設定、登入、開發者）
- [x] B2：檔案結構拆分（class-settings-page.php 從 34KB 降到 <300 行）
- [x] B3：設定 Tab 重寫（合併入門 URL + 連線測試 + 折疊指南）
- [x] B4：登入 Tab 新建（所有登入相關設定集中）
- [x] B5：開發者 Tab 新建（API Key + REST API 文件 + Webhook 記錄合併）
- [x] B6：ButtonPositions 掛載器（登入按鈕實際出現在設定的位置）
- [x] B7：LoginButton 修復（根據 login_mode 決定 URL）

### Phase C 必須完成（v2.0 差異化）

- [x] C2：Hook 介面標準化（`line_hub/send/text`, `line_hub/send/flex`, `line_hub/send/broadcast`）
- [x] C3：REST API 端點（`/messages/text`, `/messages/flex`, `/messages/broadcast`, `/users/{id}/binding`）
- [x] C5：API Key 管理（生成、遮罩顯示、撤銷、hash 儲存）

### v2.x 加入（確認 v2.0 穩定後）

- [ ] 多個 API Key — 當有明確多整合需求時才加
- [ ] 統計 API (`/stats`) — 當有報表需求時
- [ ] 通知歷史查詢 UI — 當 BuyGo 串接穩定後
- [ ] Rate Limiting Headers (`X-RateLimit-*`) — 當有外部 SaaS 大量呼叫時

### 明確 Out of Scope（永不做）

- Rich Menu 視覺化編輯器
- Flex Message 拖拉編輯器
- AI 自動回覆
- LINE Pay 整合
- WebSocket 即時聊天

---

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| A1 SettingsService Bug | HIGH | LOW | P1 |
| A2 Users LINE 欄位 | MEDIUM | LOW | P1 |
| A3 移除空 Tab | HIGH | LOW | P1 |
| B Tab 結構重構 | HIGH | MEDIUM | P1 |
| B6 ButtonPositions | HIGH | MEDIUM | P1 |
| C2 Hook 標準化 | HIGH | MEDIUM | P1 |
| C3 REST API 端點 | HIGH | MEDIUM | P1 |
| C5 API Key 管理 | HIGH | LOW | P1 |
| 統計 API `/stats` | MEDIUM | LOW | P2 |
| 多個 API Key | LOW | MEDIUM | P3 |
| Rate Limiting Headers | LOW | LOW | P2 |
| 通知歷史查詢 UI | MEDIUM | MEDIUM | P2 |

**Priority key:**
- P1: v2.0 必須完成
- P2: v2.x 加入，有具體需求才做
- P3: 未來考慮，不排進近期計畫

---

## Competitor Feature Analysis

### 競品概況

| 外掛 | Channel | 通知方式 | 設定頁複雜度 | 外部整合介面 | API Key |
|------|---------|---------|-------------|-------------|---------|
| **WooCommerce Line Notify** | LINE Notify（已廢棄） | 文字 only | 單一頁面 | 無 | 無 |
| **WordPress LINE Connect（泰國）** | Messaging API + LINE Notify | 文字 + Flex | 多功能但混亂 | PHP 函數呼叫 | 無 |
| **WP LINE Login（日本）** | LINE Login only | 無通知 | 簡單 3 步驟 | Hook | 無 |
| **NSL（Next Social Login）** | LINE Login | 無通知 | 標準 Tab 結構，清晰 | 無 | 無 |
| **LineHub v2.0（我們）** | Messaging API + LINE Login + LIFF | 文字 + Flex + 廣播 | 3 Tab 清晰分組 | Hook + REST API | 有（hash 儲存）|

### 關鍵差異

**設定頁 UX：**
- NSL 是業界標竿：每個 Tab 有明確用途，入門 Tab 做步驟指引，不混放 URL
- LineHub v2.0 仿 NSL 結構：設定 Tab（Channel 資訊）、登入 Tab（行為設定）、開發者 Tab（整合文件）
- 競品常見問題：入門步驟和 API 設定混在同一 Tab（現在的 LineHub 也有此問題，Phase B 解決）

**API Key 管理 UX（業界標準）：**
- GitHub、Stripe 模式：生成後只顯示一次，DB 存 hash，遮罩顯示前 8 碼
- WordPress REST API Key Authentication 外掛：相同模式，`password_hash()` 儲存
- LineHub 計畫：`wp_hash()` + 前綴 `lhk_` + 只顯示一次 — 符合業界標準

**外部整合架構：**
- 所有競品都缺乏標準化外部整合介面
- WordPress LINE Connect 只提供 PHP 函數，需要同主機、知道函數名稱
- LineHub v2.0 同時提供 Hook（同主機）和 REST API（跨系統），是市場唯一

**信心度：MEDIUM** — 競品分析基於 WebSearch 結果，未直接安裝測試。NSL 分析基於計畫文件中的描述（相對可靠）。

---

## WordPress 設定頁 UX 模式研究

### 業界標準做法（HIGH 信心度）

基於 WordPress Plugin Handbook 和成熟外掛（NSL、Yoast、WooCommerce）的研究：

**Tab 結構原則：**
- 每個 Tab 對應一個邏輯功能域，不要因為欄位少就把不相關的塞在一起
- 入門/嚮導 Tab 只做「首次設定引導」，不應混放進階設定
- 開發者導向的內容（Webhook 記錄、Hook 文件）應集中在一個 Tab，避免一般用戶困惑

**獨立 Form per Tab 模式（業界最佳實踐）：**
```
每個 Tab 的 <form> 包含：
  - <input type="hidden" name="tab" value="settings">
  - nonce field
  - 只有此 Tab 相關的欄位

handle_save() 根據 $_POST['tab'] 分流：
  - tab=settings → 只存 Channel + NSL 欄位
  - tab=login    → 只存登入行為欄位
  - tab=developer → 只存 API Key 操作
```

**連線測試 UX：**
- 測試按鈕應在「設定」Tab 旁邊，不應在獨立頁面
- 測試結果即時顯示（AJAX），不要整頁重載
- 三個測試點：Access Token、LINE OA 登入、LIFF 登入

### API Key 管理 UX（HIGH 信心度）

基於 WordPress REST API Key Authentication 外掛和業界標準研究：

| 步驟 | UX 模式 | 安全考量 |
|------|---------|---------|
| 生成 | 點「產生 API Key」按鈕 | 使用密碼學安全的隨機數生成器（`wp_generate_password(32, false)` 或 `random_bytes()`）|
| 顯示 | 立即顯示完整 Key（附「請立即複製」警告），之後永不再顯示 | 完整 Key 只在記憶體中存在，立即 hash 後寫 DB |
| 儲存 | DB 只存 hash（`wp_hash()` 或 `password_hash()`）+ 前綴（前 8 碼用於識別）| Hash 不可逆，避免 DB 洩露導致 Key 外洩 |
| 顯示（後續）| `lhk_xxxx...xxxx`（前 8 碼 + 遮罩）| 管理員知道有 Key 但無法取得完整值 |
| 撤銷 | 點「撤銷」→ 清除 DB 中的 hash → Key 立即失效 | 無過渡期，撤銷即時生效 |

**Header 格式選擇：**
- `X-LineHub-API-Key: lhk_xxxxx`（我們的選擇） — 語意清晰，避免與 WordPress 自身認證混淆
- `Authorization: Bearer lhk_xxxxx` — 更標準但可能與其他認證衝突
- 結論：自訂 Header `X-LineHub-API-Key` 更安全（不會被 WordPress 核心攔截）

---

## Phase 複雜度評估

| Phase | 主要任務 | 預估難度 | 需要特別研究的地方 |
|-------|---------|---------|-----------------|
| **Phase A** | Bug 修復 + 空 Tab 移除 | LOW | A1 修復後需測試所有 array 類型設定 |
| **Phase B** | Tab 重構 + 熵減拆檔 | MEDIUM | B6 ButtonPositions 需確認 FluentCart 結帳頁的正確 hook 名稱 |
| **Phase C** | Hook 標準化 + REST API + API Key | MEDIUM-HIGH | C3 REST API 認證需驗證 `wp_hash()` vs `password_hash()` 效能差異 |

### Phase B 特別注意

**FluentCart 結帳頁 Hook 名稱：**
- 計畫文件寫 `fluentcart/checkout/before_customer_info`
- 但 FluentCart hook 名稱需要實際驗證（可能是 `fluentcart_before_checkout_form` 之類）
- **建議：Phase B6 執行前先確認 hook 名稱，避免按鈕不出現**

**熵減驗收標準：**
- `class-settings-page.php` < 300 行（目前 ~34KB）
- 每個 view 檔案 < 300 行
- PHP 檔案零內嵌 HTML（全部用 `require` 載入 view）

### Phase C 特別注意

**WordPress Application Passwords vs 自訂 API Key：**
- WordPress 5.6+ 有 Application Passwords，但需要 WP 用戶帳號
- 外部 SaaS 用 Application Password 需要提供 WP 用戶名稱，不適合 SaaS 對 SaaS 整合
- **結論：自訂 API Key（`X-LineHub-API-Key` Header）對外部整合更適合（MEDIUM 信心度）**

**Rate Limiting 實作：**
- 計畫用 WordPress Transients 實作 Rate Limiting
- 60 req/min 上限適合 Zapier/WebinarJam 這類低頻整合
- 注意：Transients 在高流量下有 race condition 問題，v2.0 可暫不實作，記錄為 v2.x 待辦

---

## Sources

- [WordPress Plugin Handbook - Custom Settings Page](https://developer.wordpress.org/plugins/settings/custom-settings-page/)
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/)
- [WP REST API Key Authentication Plugin](https://wordpress.org/plugins/rest-api-key-authentication/)
- [API Key Management Best Practices 2025 - MultitaskAI](https://multitaskai.com/blog/api-key-management-best-practices/)
- [WordPress Application Passwords Integration Guide](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/)
- [WordPress LINE Connect（泰國）](https://wpdevthai.com/product/line-connect/)
- [WooCommerce Line Notify Plugin](https://wordpress.org/plugins/woo-line-notify/)
- [API Key UX Pattern - password_hash storage](https://wordpress.org/plugins/rest-api-key-authentication/)（verified via WebFetch）
- `.planning/LINEHUB-RESTRUCTURE-PLAN.md`（HIGH 信心度，詳細計畫）
- `.planning/LINEHUB-ADMIN-RESTRUCTURE.md`（HIGH 信心度，8 步驟完整計畫）
- `.planning/API-ENDPOINTS.md`（HIGH 信心度，v1.0 設計基準）
- `.planning/INTEGRATION-SYSTEM.md`（HIGH 信心度，Hook 架構設計）

---

*Feature research for: LineHub v2.0 WordPress LINE 通訊平台重構與擴展*
*Researched: 2026-02-24*
