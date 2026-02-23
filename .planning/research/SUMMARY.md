# Project Research Summary

**Project:** LineHub v2.0 重構與擴展
**Domain:** WordPress LINE 整合外掛（Messaging API + LINE Login + LIFF + REST API）
**Researched:** 2026-02-24
**Confidence:** HIGH（大量發現基於直接原始碼分析）

## Executive Summary

LineHub v2.0 是一個針對半完成外掛的升級計畫，不是從頭打造。研究最重要的發現是：**Phase A2（Users 列表 LINE 欄位）、A3（移除通知 Tab）、以及 Phase C 的所有核心功能（Hook 介面、REST API 端點、API Key 管理）都已在既有程式碼中完整實作完畢**，而非「待開發」。這大幅改變了工作範圍的估算——真正需要做的工作只剩 Phase A1（SettingsService array bug 驗證與修復）和 Phase B（後台 Tab 重構熵減）。

技術層面，v2.0 完全不需要引入任何新套件。所有技術需求（設定快取、REST API 認證、Rate Limiting、表單安全驗證）均由 WordPress Core 原生提供，唯一的升級是將 API Key 的 hash 比較從 `wp_hash()` 改為 `hash_equals()`（防止 Timing Attack），以及 broadcast endpoint 加入用戶數量上限（防止 SPAM）。這符合熵減原則。

主要風險集中在 Phase B 的 Tab 重構：重命名 Tab slug 後必須加入舊 slug redirect（否則書籤失效）、每個新 view 檔案加入後必須立即驗證沒有 Fatal Error、以及所有 Tab 的 nonce action 名稱必須統一。Phase A1 的 SettingsService bug 研究顯示 array 序列化邏輯在程式碼中已存在，實際問題可能在快取層——需要在真實環境測試驗證，而非直接改程式碼。

---

## Key Findings

### 推薦技術棧

LineHub v2.0 全程使用 WordPress Core 原生功能，零外部套件依賴。這不是「選哪個框架」的問題，而是「如何正確使用已有工具」。

**核心技術：**
- **PHP 8.0+ + WordPress 6.x**：外掛平台，所有業務邏輯運行於此
- **WordPress Transient API**：設定快取（已有）+ Rate Limiting 實作（新增）
- **WordPress REST API**：外部 API 端點，已完整實作
- **`password_hash()` / `hash_equals()`**：PHP 原生，API Key 安全比較（hash_equals 為新增需求）
- **`$wpdb->replace()`**：直接 DB 操作，SettingsService 已用此模式

詳細技術分析見 `STACK.md`。

### 功能邊界（真正需要做的工作）

研究揭示了功能完成狀態與計畫文件預期之間的差距。下表是**實際工作量**：

**確認需要做（Table Stakes）：**
- **A1：SettingsService array 序列化驗證與修復** — 程式碼層面序列化邏輯已存在，但需在實際環境驗證 `login_button_positions` 是否正確儲存（checkbox 值）。問題可能在快取層而非序列化層。
- **B：後台 Tab 重構** — `class-settings-page.php` 目前 441 行超過上限，需拆分至 <200 行。每個 Tab 需獨立 `<form>` 以防止跨 Tab 設定互相覆蓋（B6 ButtonPositions 掛載器需確認）。

**已完成，只需驗證（不需要重做）：**
- A2：Users 列表 LINE 綁定欄位 — `UsersColumn` 類別已存在且已在 Plugin 中呼叫
- A3：移除通知 Tab — TABS 常數已只有 3 個 Tab（settings/login/developer）
- C2：Hook 介面標準化 — `IntegrationHooks` 5 個 Hook 全部已實作
- C3：REST API 端點 — `PublicAPI` 5 個端點全部已實作，含 API Key 認證
- C5：API Key 管理 — 生成/撤銷/hash 儲存已完整實作

**v2.x 才做（不進 v2.0）：**
- 多個 API Key 管理
- 統計 API `/stats`
- 通知歷史查詢 UI
- Rate Limiting Headers（`X-RateLimit-*`）

**永不做（明確 Out of Scope）：**
- Rich Menu 視覺化編輯器、Flex Message 拖拉編輯器、AI 自動回覆、LINE Pay 整合、WebSocket 即時聊天

詳細功能分析見 `FEATURES.md`。

### 現有架構概況

LineHub 採用分層架構，各層職責清晰。關鍵發現是 Phase C 的元件已超前實作，架構已具備雙通道整合能力（WordPress Hook + REST API），在同類競品中是唯一具備此能力的外掛。

**主要元件（含完成狀態）：**
1. `SettingsPage`（441行）— 設定後台，Phase B 重構目標
2. `SettingsService`（653行）— 設定儲存/快取，Phase A1 驗證目標
3. `IntegrationHooks`（161行）— 已完成，5 個標準化 Hook
4. `PublicAPI`（319行）— 已完成，5 個 REST 端點含 API Key 認證
5. `UsersColumn`（139行）— 已完成，三張 LINE 身份表 Fallback 查詢

**已存在的對外整合點：**
- `do_action('line_hub/send/text|flex|broadcast', $args)` — 其他 WP 外掛呼叫
- `apply_filters('line_hub/user/is_linked|get_line_uid', ...)` — 查詢用戶狀態
- `POST /line-hub/v1/messages/*` — 外部 SaaS 透過 REST API 呼叫
- `GET /line-hub/v1/users/*` — 用戶資訊查詢

詳細架構分析見 `ARCHITECTURE.md`。

### 關鍵風險（前 5 項）

1. **SettingsService 快取殘留**（HIGH）— A1 修復後，舊的錯誤快取（TTL 1小時）可能讓設定看起來沒修好。修復部署後必須清除所有 Transient，並驗證 `clear_cache()` 在 `set()` 成功後確實被呼叫。

2. **UsersColumn N+1 查詢**（HIGH）— `get_binding_status()` 對三張表各執行一次 `SHOW TABLES LIKE`，50 個用戶 = 150 次 SHOW TABLES。解法：在 `table_exists()` 加靜態快取，或改用批量查詢。

3. **Tab 重構後 Nonce 不同步**（HIGH）— B3 表單拆分時，每個 Tab 獨立 `<form>` 若用不同 nonce action，但 `handle_save()` 驗證的是舊 action，會 silently 失敗（設定不存，無報錯）。解法：所有 Tab 統一使用 `'line_hub_save_settings'` action，Tab 隔離靠 `hidden[name=tab]` 實現。

4. **broadcast 無上限，可被當 SPAM 工具**（HIGH）— `/messages/broadcast` 的 `user_ids` 無數量限制，API Key 洩漏時風險極大。解法：硬性上限 100 個 user_id，回傳 HTTP 400。

5. **API Key hash 比較 Timing Attack**（HIGH）— `authenticate()` 用 `!==` 做 hash 字串比較，有時序漏洞。解法：改用 `hash_equals($stored_hash, wp_hash($key))`，一行修改。

詳細風險分析見 `PITFALLS.md`。

---

## Implications for Roadmap

基於研究，特別是「Phase C 已完成」這個關鍵發現，建議的 Roadmap 結構如下：

### Phase 1: 驗證現況（Verify Current State）

**Rationale:** 研究發現大量功能已實作，但「已實作」不等於「正確運作」。必須先驗證再重構，否則 Phase 2 的重構工作可能破壞已正常運作的功能。

**Delivers:**
- 確認 A1 array bug 是否真的存在（可能已修復）
- 確認 A2（UsersColumn）在實際環境是否正確顯示
- 確認 A3（無通知 Tab）
- 確認 C 系列（Hook、REST API、API Key）是否端到端可用

**測試方法:**
- 後台登入設定 → 勾選位置 → 儲存 → 重載 → 確認值保留
- `/wp-admin/users.php` 確認 LINE 欄位顯示
- curl 測試 `POST /line-hub/v1/messages/text` + API Key header
- `do_action('line_hub/send/text', ...)` 確認 error_log 有記錄

**Avoids:** 在已完成的功能上浪費開發時間；在未驗證的基礎上進行重構

**Research flag:** 不需要額外研究，執行驗證腳本即可

---

### Phase 2: 緊急修復（Emergency Fixes）

**Rationale:** 只修真正損壞的東西。Phase 1 驗證後才知道有哪些需要修復，不要假設。

**Delivers:**
- A1 修復（若 Phase 1 驗證有問題）：`SettingsService::set()` 確保 array 在資料庫層和快取層格式一致
- N+1 查詢修復：`table_exists()` 加靜態快取
- `hash_equals()` 替換：`authenticate()` 防 Timing Attack（一行修改）
- broadcast 上限：`user_ids` 硬性上限 100

**Uses:** 純 PHP/WordPress Core，零新套件

**Avoids:**
- Pitfall 1（快取殘留）：修復後立即清除所有 Transient 並驗證
- Pitfall 6（hash 比較）：同步修復
- Pitfall 7（broadcast 無限制）：同步加防護

**Research flag:** 無需額外研究，直接執行修復

---

### Phase 3: 後台 Tab 重構（Admin Tab Refactor）

**Rationale:** Phase B 是唯一大型開發工作。依賴 Phase 2 確保設定儲存正確，才有意義重構表單結構。

**Delivers:**
- `class-settings-page.php` 從 441 行精簡至 <200 行
- 3 個 Tab 保持清晰（設定 / 登入 / 開發者），每個 Tab 有獨立 `<form>`
- 舊 Tab slug redirect 機制（向後相容）
- B6：ButtonPositions 掛載器（若 Phase 2 確認 A1 修復正確後，按鈕應實際出現）

**實作順序（必須按序）:**
1. 確認現有 3 Tab 結構（slug: settings/login/developer）
2. 每個 Tab 改為獨立 `<form>`，統一 nonce action
3. 精簡 `handle_save()`，對應每 Tab 的 switch case
4. 精簡主類別至 <200 行
5. 驗證：每個 Tab 獨立儲存不影響其他 Tab

**Avoids:**
- Pitfall 3（Nonce 不同步）：所有 form 統一 `'line_hub_save_settings'` action
- Pitfall 4（View 路徑錯誤）：保持 `includes/admin/` 目錄位置不變
- Pitfall 5（舊路由殘留）：grep 確認無殘留

**Research flag:** 無需額外研究，架構決策已在 ARCHITECTURE.md 中確定。B6 ButtonPositions 需確認 FluentCart 結帳頁的正確 hook 名稱（執行前實測）。

---

### Phase 4: 開發者 Tab 補充文件（Developer Tab Documentation）

**Rationale:** Phase C 功能已實作但文件不完整。這是低風險、高價值的工作，讓開發者（BuyGo 等）不需查外部文件就能整合。

**Delivers:**
- `tab-developer.php` 加入完整 REST API 端點列表和 curl 範例
- Hook 使用範例（`do_action('line_hub/send/text', ...)` 參數說明）
- API Key 一次顯示的醒目提示（紅色警告框）
- 確認 API Key 使用記錄顯示功能是否完整

**Avoids:** 通知 Tab 移除後，開發者找不到整合說明（UX Pitfall）

**Research flag:** 無需研究，內容來自已實作的 API 設計

---

### Phase 順序邏輯

- **Phase 1 先做**：驗證現況，避免在錯誤假設上建構計畫
- **Phase 2 次之**：只修損壞的，確保基礎穩固
- **Phase 3 依賴 Phase 2**：設定儲存正確才有意義重構表單隔離
- **Phase 4 最後**：補充文件是錦上添花，不是基礎工程

### Research Flags

需要深入研究（執行 `/gsd:research-phase`）：
- **無** — v2.0 的技術選型和架構決策已有充分研究依據

標準模式（跳過研究）：
- **Phase 1**：純驗證，執行腳本即可，不需研究
- **Phase 2**：直接修復，技術方案已確定
- **Phase 3**：WordPress Plugin Handbook 標準模式，已有充分文件
- **Phase 4**：內部文件補充，無技術研究需求

---

## Confidence Assessment

| 面向 | 信心度 | 說明 |
|------|--------|------|
| Stack | HIGH | 直接閱讀原始碼確認，所有技術 WordPress Core 已有，零外部套件 |
| Features | HIGH（已完成功能）/ MEDIUM（待做功能） | A2/A3/C 系列確認已完成；A1 bug 真實性需環境驗證 |
| Architecture | HIGH | 基於直接原始碼分析，非假設；元件邊界、資料流均確認 |
| Pitfalls | HIGH | 大部分 Pitfall 來自直接程式碼分析，非推測 |

**整體信心度：HIGH**

### 需要後續確認的缺口

1. **A1 bug 的真實範圍**：array 序列化邏輯程式碼已存在（`json_encode` 在 set() 的 line 398-399），但實際環境的 `login_button_positions` checkbox 是否正確儲存尚未驗證。**處理方式：Phase 1 驗證時直接測試**，若行為正確則 A1 不需要做任何修改。

2. **B6 ButtonPositions 掛載器的 FluentCart hook 名稱**：計畫文件寫 `fluentcart/checkout/before_customer_info`，但 FluentCart 的實際 hook 名稱需要確認。**處理方式：Phase 3 執行 B6 前，在本機安裝 FluentCart 並 `do_action` 搜尋確認**。

3. **UsersColumn N+1 是否在測試環境已經是問題**：研究分析了理論上的 N+1，但實際是否已有效能問題取決於用戶數量。**處理方式：Phase 1 驗證時同時用 Query Monitor 確認 SQL 查詢數**。

4. **Phase C 的 Rate Limiting**：功能已存在 PublicAPI，但 FEATURES.md 和 STACK.md 的建議是增加 Transient 計數器 Rate Limiting。研究顯示目前沒有 Rate Limiting，broadcast 無上限是 HIGH 風險。**處理方式：Phase 2 加入 broadcast 上限（簡單），Rate Limiting Headers 移到 v2.x**。

---

## Sources

### Primary（HIGH confidence — 直接原始碼分析）
- `/Users/fishtv/Development/line-hub/includes/services/class-settings-service.php` — array 序列化邏輯、快取機制
- `/Users/fishtv/Development/line-hub/includes/admin/class-settings-page.php` — Tab 結構、nonce 模式
- `/Users/fishtv/Development/line-hub/includes/api/class-public-api.php` — REST API 端點、認證機制
- `/Users/fishtv/Development/line-hub/includes/services/class-integration-hooks.php` — Hook 介面實作狀態
- `/Users/fishtv/Development/line-hub/includes/admin/class-users-column.php` — UsersColumn 實作狀態
- 計畫文件 `.planning/LINEHUB-RESTRUCTURE-PLAN.md` — 重構計畫和已知風險
- 計畫文件 `.planning/LINEHUB-ADMIN-RESTRUCTURE.md` — Tab 重構 8 步驟計畫

### Secondary（HIGH confidence — 官方文件）
- WordPress REST API 認證文件：https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- WordPress 6.8 bcrypt 密碼雜湊公告：https://make.wordpress.org/core/2025/02/17/wordpress-6-8-will-use-bcrypt-for-password-hashing/
- WordPress Plugin Handbook — Custom Settings Page
- PHP 官方文件：`hash_equals()` 恆定時間比較

### Secondary（MEDIUM confidence — WebSearch 競品分析）
- WordPress LINE Connect（泰國）/ WooCommerce Line Notify / WP LINE Login / NSL — 競品功能分析
- WP REST API Key Authentication 外掛 — API Key UX 模式參考
- LINE Messaging API Rate Limits — 每分鐘 1000 則（需確認當前方案限制）

---

## 關鍵結論（給 Roadmapper 的直接建議）

1. **不要按照計畫文件的 Phase A/B/C 分法直接做**。Phase A2/A3 和整個 Phase C 已完成，不需要重做。

2. **先驗證再動手**。Phase 1 花 1-2 小時測試所有「已完成」的功能，會讓後續計畫更精確。

3. **真正的工作只有一件大事**：Phase B Tab 重構（2-4 小時）。這是唯一需要寫大量程式碼的工作。

4. **A1 可能不需要修**。如果 Phase 1 驗證顯示 checkbox 值正確儲存，A1 就不是 bug，不用動。

5. **3 個安全小修（Phase 2）一定要做**：`hash_equals()` 替換（1行）、broadcast 上限（5行）、table_exists 靜態快取（5行）。這些風險真實、修復成本極低。

---
*Research completed: 2026-02-24*
*Ready for roadmap: yes*
