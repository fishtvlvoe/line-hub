# Phase 3 Plan 01: OAuth Core Infrastructure Summary

## Frontmatter

```yaml
phase: 03-oauth-authentication
plan: 01
subsystem: auth
tags: [oauth, state, csrf, line-api]

dependency-graph:
  requires:
    - 01-settings-service (SettingsService for channel credentials)
  provides:
    - OAuthState class for CSRF protection
    - OAuthClient class for LINE API communication
  affects:
    - 03-02 (Login Service will use OAuthClient)
    - 03-03 (Auth Callback will use both)

tech-stack:
  added: []
  patterns:
    - NSL Persistent storage pattern for State management
    - Dual storage strategy (logged-in vs anonymous users)

file-tracking:
  created:
    - includes/auth/class-oauth-state.php
    - includes/auth/class-oauth-client.php
  modified: []

decisions:
  - id: 03-01-state-storage
    choice: Transient + Session cookie pattern
    reason: Follows NSL proven pattern, works with load balancers and multisite
  - id: 03-01-state-expiration
    choice: 5 minutes (300 seconds)
    reason: User decision for enhanced security
  - id: 03-01-id-token-verify
    choice: LINE verify endpoint (not local JWT decode)
    reason: Simpler and authoritative verification

metrics:
  duration: 1m 54s
  completed: 2026-02-07
```

## One-liner

OAuth State 管理（random_bytes + hash_equals + 5 分鐘過期）和 LINE OAuth 2.0 客戶端（授權 URL + token 交換 + ID Token 驗證）

## What Changed

### Task 1: OAuthState Class (293 lines)

Implemented `LineHub\Auth\OAuthState` static class for CSRF protection:

**Public Methods:**
- `generate()` - Creates 64-char hex state using `random_bytes()`, stores in Transient
- `validate()` - Timing-safe comparison with `hash_equals()`, deletes state after use
- `storeRedirect()` - Stores original page URL for post-login redirect
- `getRedirect()` - Retrieves and validates redirect URL (one-time use)

**Key Implementation Details:**
- Logged-in users: `set_transient('line_hub_state_{user_id}', ...)`
- Anonymous users: Session cookie + `set_site_transient('line_hub_session_{hash}', ...)`
- Cookie uses `COOKIEPATH`, `COOKIE_DOMAIN`, `is_ssl()` for proper WordPress integration
- Session ID hashed with `SECURE_AUTH_KEY` for added security

### Task 2: OAuthClient Class (314 lines)

Implemented `LineHub\Auth\OAuthClient` for LINE OAuth 2.0 API:

**Public Methods:**
- `createAuthUrl()` - Generates authorization URL with state, scope, bot_prompt options
- `createReauthUrl()` - Same as above but with `prompt=consent` for forced re-authorization
- `authenticate()` - Exchanges code for tokens via `wp_remote_post()`
- `verifyIdToken()` - Validates ID Token via LINE verify endpoint
- `getProfile()` - Retrieves user profile via `wp_remote_get()`
- `isConfigured()` - Checks if channel credentials are set
- `getClientIdMasked()` - Returns masked Channel ID for debugging

**LINE API Endpoints:**
- Auth: `https://access.line.me/oauth2/v2.1/authorize`
- Token: `https://api.line.me/oauth2/v2.1/token`
- Verify: `https://api.line.me/oauth2/v2.1/verify`
- Profile: `https://api.line.me/v2/profile`

**Error Handling:**
- All HTTP calls check `is_wp_error()` and `wp_remote_retrieve_response_code()`
- Proper exceptions with translated error messages
- 15-second timeout on all requests

## Decisions Made

| Decision | Choice | Rationale |
|----------|--------|-----------|
| State storage | Transient API | Works with multisite, load balancers; NSL proven pattern |
| State expiration | 5 minutes | User decision for enhanced security (NSL uses 1 hour) |
| Anonymous session | Cookie + site_transient | Consistent with NSL Session.php pattern |
| ID Token verification | LINE verify endpoint | Simpler than local JWT, authoritative |
| Auto-login | Disabled by default | Security - force manual authentication |

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

All success criteria met:

- [x] OAuthState::generate() produces 64-char hex string (32 bytes * 2)
- [x] OAuthState::validate() uses hash_equals and deletes state after check
- [x] OAuthClient::createAuthUrl() includes all required params (response_type, client_id, redirect_uri, state, scope)
- [x] OAuthClient::createReauthUrl() includes prompt=consent
- [x] All HTTP methods have complete error handling (is_wp_error + response_code check)
- [x] Both classes use namespace LineHub\Auth

## Commit Log

| Hash | Message |
|------|---------|
| 452d8c3 | feat(03-01): implement OAuthState class |
| aabddb0 | feat(03-01): implement OAuthClient class |

## Next Phase Readiness

Phase 3 Plan 02 prerequisites met:
- [x] OAuthState available for CSRF protection in login flow
- [x] OAuthClient available for authorization and token exchange
- [x] Both classes follow PSR-4 naming for autoloader compatibility

## Files Reference

```
includes/auth/
  class-oauth-state.php (293 lines)
  class-oauth-client.php (314 lines)
```

Total: 607 lines of new code
