---
phase: 11-安全補齊與常數統一
plan: 01
subsystem: security
tags: [wordpress, uninstall, directory-protection, plugin-handbook]

# Dependency graph
requires:
  - phase: 10-開發者體驗
    provides: 完整的外掛功能基礎（資料表、options、user meta）
provides:
  - uninstall.php 外掛移除時完整資料清理
  - 20 個目錄 index.php 防止目錄瀏覽
affects: [12-內嵌清除, 13-樣式外部化]

# Tech tracking
tech-stack:
  added: []
  patterns: [WordPress Plugin Handbook 安全規範]

key-files:
  created:
    - uninstall.php
    - assets/index.php
    - assets/css/index.php
    - assets/images/index.php
    - assets/js/index.php
    - includes/index.php
    - includes/admin/index.php
    - includes/admin/tabs/index.php
    - includes/admin/views/index.php
    - includes/admin/views/partials/index.php
    - includes/api/index.php
    - includes/auth/index.php
    - includes/cli/index.php
    - includes/integration/index.php
    - includes/liff/index.php
    - includes/messaging/index.php
    - includes/services/index.php
    - includes/templates/index.php
    - includes/webhook/index.php
    - languages/index.php
    - tests/index.php
  modified: []

key-decisions:
  - "uninstall.php 使用 __DIR__ 取得路徑而非 LINE_HUB_PATH 常數，確保外掛停用後仍可正確載入"
  - "drop_tables() 後仍補刪 options，確保雙重保險"

patterns-established:
  - "目錄防護：所有子目錄均放置 index.php 防止目錄瀏覽"
  - "外掛清理：uninstall.php 按順序清理資料表 → options → transients → user meta"

requirements-completed: [SEC-08, SEC-09]

# Metrics
duration: 3min
completed: 2026-02-24
---

# Phase 11 Plan 01: 安全補齊與常數統一 Summary

**uninstall.php 完整資料清理（4 張資料表 + options + transients + user meta）+ 20 個目錄 index.php 防護檔**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-24T13:09:42Z
- **Completed:** 2026-02-24T13:12:30Z
- **Tasks:** 2
- **Files modified:** 21

## Accomplishments
- 建立 uninstall.php，外掛移除時完整清除 4 張資料表、所有 line_hub_* options、transients 和 user meta
- 為 20 個目錄建立 index.php 防止目錄瀏覽，符合 WordPress Plugin Handbook 安全要求
- 所有新增檔案通過 PHP 語法檢查

## Task Commits

Each task was committed atomically:

1. **Task 1: 建立 uninstall.php** - `66dc77b` (feat)
2. **Task 2: 為 20 個目錄建立 index.php** - `8ee4534` (chore)

**Plan metadata:** TBD (docs: complete plan)

## Files Created/Modified
- `uninstall.php` - 外掛移除時的完整資料清理腳本（WP_UNINSTALL_PLUGIN 安全檢查 + Database::drop_tables() + options/transients/user meta 清除）
- `assets/index.php` - 目錄瀏覽防護
- `assets/css/index.php` - 目錄瀏覽防護
- `assets/images/index.php` - 目錄瀏覽防護
- `assets/js/index.php` - 目錄瀏覽防護
- `includes/index.php` - 目錄瀏覽防護
- `includes/admin/index.php` - 目錄瀏覽防護
- `includes/admin/tabs/index.php` - 目錄瀏覽防護
- `includes/admin/views/index.php` - 目錄瀏覽防護
- `includes/admin/views/partials/index.php` - 目錄瀏覽防護
- `includes/api/index.php` - 目錄瀏覽防護
- `includes/auth/index.php` - 目錄瀏覽防護
- `includes/cli/index.php` - 目錄瀏覽防護
- `includes/integration/index.php` - 目錄瀏覽防護
- `includes/liff/index.php` - 目錄瀏覽防護
- `includes/messaging/index.php` - 目錄瀏覽防護
- `includes/services/index.php` - 目錄瀏覽防護
- `includes/templates/index.php` - 目錄瀏覽防護
- `includes/webhook/index.php` - 目錄瀏覽防護
- `languages/index.php` - 目錄瀏覽防護
- `tests/index.php` - 目錄瀏覽防護

## Decisions Made
- uninstall.php 使用 `__DIR__` 取得路徑而非 `LINE_HUB_PATH` 常數，因為外掛停用後常數可能未定義
- `Database::drop_tables()` 後仍補刪 `line_hub_version` 和 `line_hub_db_version`，確保雙重保險

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- 安全基礎已就位，可繼續 Plan 02（Open Redirect 漏洞修正 + 輸入 sanitize 補齊）
- 現有外掛功能不受影響（此 Plan 只新增檔案，不修改既有程式碼）

## Self-Check: PASSED

- All 21 created files exist on disk
- Both task commits (66dc77b, 8ee4534) exist in git history
- PHP syntax check passed for all 21 files

---
*Phase: 11-安全補齊與常數統一*
*Completed: 2026-02-24*
