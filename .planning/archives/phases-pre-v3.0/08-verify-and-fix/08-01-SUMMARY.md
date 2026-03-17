---
phase: 08-verify-and-fix
plan: 01
subsystem: api
tags: [security, hash-equals, timing-attack, broadcast-limit, rest-api, n-plus-one]

# Dependency graph
requires:
  - phase: 08-verify-and-fix
    provides: "RESEARCH.md 中發現的安全問題與 Bug 清單"
provides:
  - "PublicAPI hash_equals 恆定時間認證"
  - "broadcast 端點與 Hook 的 100 人上限"
  - "三個訊息端點的正確回應格式（is_wp_error）"
  - "UsersColumn table_exists 靜態快取（N+1 修復）"
affects: [08-02, 09-settings-tab]

# Tech tracking
tech-stack:
  added: []
  patterns: ["hash_equals 恆定時間比較模式", "靜態快取消除 N+1 查詢"]

key-files:
  created: []
  modified:
    - "includes/api/class-public-api.php"
    - "includes/services/class-integration-hooks.php"
    - "includes/admin/class-users-column.php"

key-decisions:
  - "hash_equals() 參數順序：已知值在前、用戶值在後"
  - "broadcast 上限設為 100（REST API 回 400、Hook 記 error_log 並 return）"
  - "回應格式用 is_wp_error() 替代 (bool) 強制轉型（LINE API 成功回空物件，PHP 轉 bool 為 false）"

patterns-established:
  - "API 認證用 hash_equals 防 timing attack"
  - "批量操作加上限防資源耗盡"
  - "資料表存在性用靜態快取消除重複查詢"

requirements-completed: [VERIFY-05]

# Metrics
duration: 2min
completed: 2026-02-24
---

# Phase 8 Plan 01: 安全修復與 Bug 修正 Summary

**PublicAPI hash_equals 恆定時間認證 + broadcast 100 人上限 + is_wp_error 回應格式修復 + UsersColumn N+1 快取**

## Performance

- **Duration:** 1m 40s
- **Started:** 2026-02-23T21:41:36Z
- **Completed:** 2026-02-23T21:43:16Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- API Key 認證改用 hash_equals() 恆定時間比較，防止 Timing Attack
- REST API broadcast 和 Hook broadcast 都加入 100 人上限檢查
- 三個訊息端點（send_text/send_flex/send_broadcast）的 success 回應改用 is_wp_error() 正確判斷
- UsersColumn 的 table_exists() 加入靜態快取，50 用戶場景 SHOW TABLES 從 150 次降為 3 次

## Task Commits

Each task was committed atomically:

1. **Task 1: 修復 PublicAPI -- hash_equals、broadcast 上限、回應格式** - `ffe6d66` (fix)
2. **Task 2: 修復 IntegrationHooks broadcast 上限 + UsersColumn N+1 快取** - `c10ec0a` (fix)

## Files Created/Modified
- `includes/api/class-public-api.php` - hash_equals 認證、broadcast 100 人上限、三個端點 is_wp_error 回應格式
- `includes/services/class-integration-hooks.php` - handle_broadcast 100 人上限檢查 + error_log
- `includes/admin/class-users-column.php` - table_exists 靜態快取屬性與邏輯

## Decisions Made
- hash_equals() 參數順序：第一個是已知值（stored_hash），第二個是用戶輸入（wp_hash(key)），符合 PHP 官方文件建議
- broadcast 上限設 100：REST API 回 HTTP 400 + 錯誤訊息，Hook 記 error_log 並 return（因為 action 無法回傳值）
- 用 is_wp_error() 替代 (bool) 轉型：LINE API 成功回應解碼後是空陣列 []，(bool)[] = false 會誤報失敗

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- 所有已知安全問題和 Bug 已修復，可進入 08-02 端到端驗證
- hash_equals、broadcast 上限、回應格式三項修復可透過 curl 測試驗證

## Self-Check: PASSED

- All 4 files exist (SUMMARY + 3 modified PHP files)
- Both commits found: ffe6d66, c10ec0a

---
*Phase: 08-verify-and-fix*
*Completed: 2026-02-24*
