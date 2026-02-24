# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-02-24)

**Core value:** 讓任何 WordPress 外掛都能透過標準化的 Hook 或 REST API 發送 LINE 通知給用戶
**Current focus:** v3.0 熵減重構 — 為 WebinarGo 開發打穩地基

## Current Position

Phase: 11 - 安全補齊與常數統一
Plan: 2 of 3
Status: In progress
Last activity: 2026-02-24 — 11-01 uninstall.php + index.php 防護完成

Progress: ░░░░░░░░░░░░░░░░░░░░ 0/6 phases (Phase 11: 2/3 plans)

## Performance Metrics

**v1.0 Velocity:**
- Total plans completed: 7 (Phase 1: 2 + Phase 2: 2 + Phase 3: 3)
- Phase 4-7: completed manually (not tracked by GSD)
- Average duration: 1m 58s (Phase 2-3, GSD tracked)

**v2.0 Scope:**
- Total phases: 3 (Phase 8, 9, 10)
- Total requirements: 13 — ALL COMPLETE
- Total plans: 7 (Phase 8: 2, Phase 9: 3, Phase 10: 2)

**v3.0 Scope:**
- Total phases: 6 (Phase 11-16)
- Total requirements: 18
- Entropy score target: 52/100 → 85/100

## Accumulated Context

### Decisions

- [v1.0]: Phase 1-3 completed via GSD automation
- [v1.0]: Phase 4-6 completed manually (通知系統、Webhook、外掛串接)
- [v1.0]: LIFF 登入、登入按鈕、NSL 頭像 fallback 作為修補完成
- [v2.0]: 版本號選擇 v2.0（重構 + 新 API 介面 = 重大變更）
- [v2.0]: A+B+C 全部做完再開 WebinarGo（用戶決策）
- [v2.0]: Phase 8 先驗證再修復（研究發現大量功能已實作，先確認現況）
- [v2.0]: Phase 9 依賴 Phase 8（設定儲存正確才有意義重構表單隔離）
- [v2.0]: Tab 重構從 3 Tab 擴展為 5 Tab（設定嚮導、LINE 設定、登入設定、Webhook、開發者）
- [v2.0]: 所有 Tab form 統一使用 `'line_hub_save_settings'` nonce action，隔離靠 hidden[name=tab]
- [08-01]: hash_equals 參數順序：已知值在前、用戶值在後
- [08-01]: broadcast 上限 100（REST API 回 400、Hook 記 error_log 並 return）
- [08-01]: 回應格式用 is_wp_error() 替代 (bool) 強制轉型（LINE API 成功回空物件問題）
- [08-02]: 5 個 VERIFY 項目全部 PASS（array 序列化、快取清除、REST API、hash_equals、broadcast 上限）
- [08-02]: 使用 curl + cookie 認證替代 Playwright 做設定頁面測試
- [09-01]: AbstractTab 抽象類別定義 Tab 介面（get_slug/get_label/render/save）
- [09-01]: 主類別用 verify_admin() 共用方法減少權限檢查重複碼
- [09-02]: 舊 slug 映射用 SLUG_REDIRECTS 常數：settings → line-settings, login → login-settings
- [09-02]: Webhook 事件記錄從 developer Tab 獨立出來成為 webhook Tab
- [09-02]: 設定嚮導 Tab（wizard）整合連線狀態和設定步驟說明
- [10-01]: DeveloperTab 用結構化資料（array）驅動 view 模板，不在模板中硬編碼
- [10-02]: ApiLogger 用 wp_options 儲存（避免建新資料表），保留 100 筆
- [10-02]: 僅記錄 API Key 認證的呼叫（管理員 Cookie 不記錄），避免 log 膨脹
- [v3.0]: 熵減重構優先於 WebinarGo — 地基穩了才往上蓋（用戶決策）
- [v3.0]: 掃描結果：熵減評分 52/100，目標 ≥ 85/100
- [v3.0]: profile-binding 已完成拆分（CSS + JS + Template），作為 v3.0 前置工作
- [v3.0]: Phase 結構：安全+常數(11) → 內嵌清除(12) → 樣式外部化(13) → 檔案瘦身+方法重構(14) → 命名整理(15) → 測試(16)
- [v3.0]: 常數統一放在 Phase 11 而非獨立 Phase — 因為拆分檔案時會用到新常數類別，必須先就位
- [11-02]: resolveRedirectUrl 改用 wp_validate_redirect 取代 esc_url_raw，從根源阻擋外部跳轉
- [11-02]: OAuth redirect 入口改用 sanitize_text_field 而非 esc_url_raw，保留相對路徑格式

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-02-24
Stopped at: Completed 11-02-PLAN.md (Open Redirect 漏洞修正)
Resume file: None
Next action: 執行 11-03-PLAN.md — LINE API URL 統一常數類別
