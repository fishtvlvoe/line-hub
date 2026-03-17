---
phase: 02-user-management
plan: 01
subsystem: api
tags: [wordpress, line, user-binding, wpdb, rest-api]

# Dependency graph
requires:
  - phase: 01-settings-foundation
    provides: wp_line_hub_users table schema, database initialization
provides:
  - UserService class with LINE binding CRUD operations
  - getUserByLineUid() for LINE UID to WordPress User ID lookup
  - getBinding() with NSL fallback support
  - linkUser() with duplicate binding prevention
  - unlinkUser() with action hooks
  - updateProfile() for OAuth data sync
affects: [02-user-management-plan-02, 03-auth-system, 05-notification-integration]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Static service class pattern with $wpdb queries
    - NSL fallback for legacy binding support
    - WordPress action hooks for extensibility

key-files:
  created:
    - includes/services/class-user-service.php
  modified: []

key-decisions:
  - "Static class pattern for UserService - simpler API without dependency injection"
  - "Hard delete for unlinkUser - matches NSL behavior and user expectations"
  - "NSL fallback read-only - no auto-migration, just compatibility"

patterns-established:
  - "Service layer pattern: Static methods accessing $wpdb directly"
  - "Hook naming: line_hub/user/{action} format"
  - "Input sanitization: sanitize_text_field, sanitize_email, esc_url_raw"

# Metrics
duration: 1min
completed: 2026-02-07
---

# Phase 2 Plan 1: UserService Summary

**LINE 用戶綁定服務類別，提供完整的查詢、建立、更新、刪除綁定關係功能，含 NSL fallback 和 WordPress action hooks**

## Performance

- **Duration:** 1 min 21 sec
- **Started:** 2026-02-06T17:34:32Z
- **Completed:** 2026-02-06T17:35:53Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- 實作 UserService 靜態類別，包含 11 個公開方法
- 核心 CRUD 操作：getUserByLineUid, getBinding, linkUser, unlinkUser, updateProfile
- NSL (Nextend Social Login) 的 wp_social_users 表 fallback 查詢
- WordPress action hooks 用於擴充性：line_hub/user/linked, before_unlink, unlinked, profile_updated
- 批次查詢支援：getBindingsBatch 用於效能優化
- 輔助方法：isLinked, getLineUid, getDisplayName, getPictureUrl, countLinkedUsers

## Task Commits

Each task was committed atomically:

1. **Task 1: 建立 UserService 類別** - `0c087e5` (feat)

## Files Created/Modified
- `includes/services/class-user-service.php` - LINE 用戶綁定服務核心邏輯（463 行）

## Decisions Made
- **靜態類別設計**：參考 SettingsService 模式，使用靜態方法而非實例方法，簡化呼叫方式
- **Hard delete 策略**：unlinkUser 使用硬刪除而非軟刪除，與 NSL 行為一致且符合用戶預期
- **NSL fallback 唯讀**：只提供讀取相容性，不自動遷移資料到 line_hub_users 表
- **額外方法**：新增 isLinked, getLineUid, getDisplayName, getPictureUrl, getBindingsBatch, countLinkedUsers 輔助方法，提升使用便利性

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- UserService 完成，可供 Plan 2 的 User_API REST 端點使用
- 提供的方法完全符合 REST API 需求
- updateProfile 方法已為 Phase 3 OAuth 登入做好準備

---
*Phase: 02-user-management*
*Completed: 2026-02-07*
