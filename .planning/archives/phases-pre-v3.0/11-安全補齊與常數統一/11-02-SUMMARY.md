---
phase: 11-安全補齊與常數統一
plan: 02
subsystem: auth
tags: [open-redirect, sanitize, wp_validate_redirect, security, liff, oauth]

# Dependency graph
requires:
  - phase: 03-OAuth-Authentication
    provides: AuthCallback 和 OAuthState 類別
  - phase: 03-OAuth-Authentication
    provides: LiffHandler LIFF 登入流程
provides:
  - Open Redirect 漏洞修正（LIFF 和 OAuth 兩條登入路徑）
  - redirect 參數入口層 sanitize_text_field 清理
affects: [12-內嵌清除, 14-檔案瘦身與方法重構]

# Tech tracking
tech-stack:
  added: []
  patterns: [wp_validate_redirect 防止 Open Redirect, sanitize_text_field 入口層清理]

key-files:
  created: []
  modified:
    - includes/liff/class-liff-handler.php
    - includes/auth/class-auth-callback.php

key-decisions:
  - "resolveRedirectUrl 改用 wp_validate_redirect 取代 esc_url_raw，從根源阻擋外部跳轉"
  - "OAuth redirect 入口改用 sanitize_text_field 而非 esc_url_raw，保留相對路徑格式（如 /my-account），最終驗證由 OAuthState::storeRedirect 負責"

patterns-established:
  - "redirect 參數雙重防護：入口層 sanitize_text_field + 跳轉前 wp_validate_redirect"
  - "移除 phpcs:ignore suppress 註解，改為實際修正問題"

requirements-completed: [SEC-10]

# Metrics
duration: 3min
completed: 2026-02-24
---

# Phase 11 Plan 02: Open Redirect 漏洞修正 Summary

**LIFF 和 OAuth 登入流程的 redirect 參數加入 wp_validate_redirect 防護，阻擋外部網址跳轉攻擊**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-24T13:11:45Z
- **Completed:** 2026-02-24T13:15:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- LIFF Handler 的 resolveRedirectUrl() 改用 wp_validate_redirect()，攻擊者傳入 `redirect=https://evil.com` 將被導回首頁
- LIFF GET/POST redirect 參數加入 sanitize_text_field() 入口清理，移除 phpcs suppress 註解
- OAuth Callback 的 redirect 參數改用 sanitize_text_field()，配合 OAuthState::storeRedirect 的 wp_validate_redirect 形成雙重防護

## Task Commits

Each task was committed atomically:

1. **Task 1: 修正 LIFF Handler 的 Open Redirect 漏洞** - `5fbb233` (fix)
2. **Task 2: 修正 OAuth Callback 的 redirect sanitize** - `45c79ba` (fix)

## Files Created/Modified
- `includes/liff/class-liff-handler.php` - resolveRedirectUrl 改用 wp_validate_redirect、GET/POST redirect 加 sanitize_text_field
- `includes/auth/class-auth-callback.php` - initiateAuth redirect 改用 sanitize_text_field

## Decisions Made
- resolveRedirectUrl 使用 wp_validate_redirect 而非 esc_url_raw：esc_url_raw 只做格式清理不阻擋外部 URL，wp_validate_redirect 會驗證是否為本站 URL
- OAuth redirect 用 sanitize_text_field 而非 esc_url_raw：redirect 值可能是相對路徑（如 /my-account），sanitize_text_field 清理危險字元但保留格式，最終 URL 驗證由 OAuthState::storeRedirect 內的 wp_validate_redirect 負責

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Open Redirect 漏洞已修正，Phase 11 剩餘 Plan 03（LINE API URL 常數統一）可繼續執行
- 兩條登入路徑（LIFF + OAuth）都已加固，安全基線達標

---
*Phase: 11-安全補齊與常數統一*
*Completed: 2026-02-24*
