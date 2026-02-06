# Phase 2: User Management - Research

**Researched:** 2026-02-07
**Domain:** WordPress User Binding, LINE Profile API, REST API Endpoints
**Confidence:** HIGH

## Summary

Phase 2 focuses on implementing the user management system for LINE Hub, enabling storage and management of LINE UID to WordPress User ID bindings. The research reveals that the database schema is already partially implemented in Phase 1 (`wp_line_hub_users` table), so this phase primarily involves building the `UserService` class and REST API endpoints.

Key findings:
1. The `wp_line_hub_users` table already exists with appropriate fields (line_uid, user_id, display_name, picture_url, email, email_verified, status)
2. Existing implementations in `buygo-line-notify` provide proven patterns for user binding with NSL fallback
3. WordPress REST API best practices recommend using permission callbacks with `current_user_can()` and proper parameter validation
4. LINE Profile API provides `userId`, `displayName`, `pictureUrl`, and `statusMessage` fields

**Primary recommendation:** Build `UserService` following the proven pattern from `buygo-line-notify/LineUserService`, then create REST API endpoints for binding status and unbind operations. Use the existing database schema from Phase 1.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Core | 6.x | User management, REST API | Native platform |
| `$wpdb` | - | Database operations | WordPress standard for direct SQL |
| REST API | v2 | Client-server communication | WordPress standard |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress Transient API | - | Caching binding status | Performance optimization |
| `WP_REST_Response` | - | API responses | Always for REST endpoints |
| `WP_Error` | - | Error handling | All error cases |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom table | user_meta only | user_meta lacks UNIQUE constraints, can't prevent duplicate bindings |
| Static methods | Instance methods | Static is simpler for utility services, instance better for DI/testing |

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── services/
│   └── class-user-service.php      # Core binding logic
├── api/
│   └── class-user-api.php          # REST API endpoints
└── class-database.php              # Already exists (Phase 1)
```

### Pattern 1: Service Layer Pattern
**What:** Separate business logic (UserService) from API layer (User_API)
**When to use:** All user binding operations
**Example:**
```php
// Source: buygo-line-notify implementation pattern
namespace LineHub\Services;

class UserService {
    /**
     * Get WordPress User ID by LINE UID
     */
    public static function getUserByLineUid(string $line_uid): ?int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'line_hub_users';

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$table_name} WHERE line_uid = %s AND status = 'active' LIMIT 1",
                $line_uid
            )
        );

        return $user_id ? (int) $user_id : null;
    }
}
```

### Pattern 2: REST API Controller Pattern
**What:** Use WordPress REST API with proper permission callbacks
**When to use:** All API endpoints
**Example:**
```php
// Source: WordPress REST API Handbook
namespace LineHub\API;

class User_API {
    public function register_routes(): void {
        register_rest_route('line-hub/v1', '/user/binding', [
            'methods' => 'GET',
            'callback' => [$this, 'get_binding_status'],
            'permission_callback' => [$this, 'check_user_permission'],
        ]);
    }

    public function check_user_permission(): bool {
        return is_user_logged_in();
    }
}
```

### Pattern 3: Dual Index Lookup
**What:** Support lookup by both LINE UID and WordPress User ID efficiently
**When to use:** All binding queries
**Example:**
```php
// Existing schema already has UNIQUE indexes on both line_uid and user_id
// This prevents duplicate bindings automatically
```

### Anti-Patterns to Avoid
- **user_meta only storage:** Cannot enforce UNIQUE constraint, allows duplicate bindings
- **Hardcoded table names:** Always use `$wpdb->prefix . 'table_name'`
- **No NSL fallback:** Existing users may have bindings in NSL's `wp_social_users` table
- **Returning array from REST callbacks:** Use `rest_ensure_response()` or `WP_REST_Response`

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| User authentication | Custom auth check | `is_user_logged_in()`, `wp_get_current_user()` | WordPress handles session, cookies |
| Permission check | Role string comparison | `current_user_can()` | Handles capability inheritance |
| Input sanitization | regex validation | `sanitize_text_field()`, `absint()` | WordPress security standards |
| Date formatting | `date()` | `current_time('mysql')` | Timezone aware |
| Database escaping | Manual escaping | `$wpdb->prepare()` | SQL injection prevention |

**Key insight:** WordPress provides secure, tested implementations for common operations. Custom solutions introduce security vulnerabilities and maintenance burden.

## Common Pitfalls

### Pitfall 1: Duplicate Binding Prevention
**What goes wrong:** Same LINE UID bound to multiple WordPress accounts
**Why it happens:** Not checking existing bindings before insert, or using INSERT instead of INSERT IGNORE
**How to avoid:**
1. Database has UNIQUE index on `line_uid` column (already in place)
2. Service layer checks `getUserByLineUid()` before binding
3. Return clear error message when LINE UID already bound
**Warning signs:** Database errors on insert, users complaining about "account already linked"

### Pitfall 2: Missing Permission Callback
**What goes wrong:** WordPress 5.5+ throws "rest_no_route" or deprecation notices
**Why it happens:** Forgetting `permission_callback` in `register_rest_route()`
**How to avoid:** Always include `permission_callback`, use `__return_true` for public endpoints
**Warning signs:** REST API returns 401 or deprecation notices in logs

### Pitfall 3: NSL Data Migration
**What goes wrong:** Users who logged in via NSL (Nextend Social Login) show as "not bound"
**Why it happens:** Only checking `wp_line_hub_users`, not `wp_social_users`
**How to avoid:** Implement fallback query to NSL's `wp_social_users` table
**Warning signs:** Long-time users showing "not linked" despite using LINE login previously

### Pitfall 4: Profile Data Stale
**What goes wrong:** User's `display_name` or `picture_url` outdated
**Why it happens:** Only storing profile once at binding time, no sync on login
**How to avoid:** Update profile data on each successful LINE login/token refresh
**Warning signs:** User complaints about wrong name or outdated avatar

### Pitfall 5: Unbind Without Cleanup
**What goes wrong:** Orphaned data after unbind (user_meta, related records)
**Why it happens:** Only deleting from binding table, not cleaning user_meta
**How to avoid:** Unbind method should clean all related data:
- `wp_line_hub_users` record
- Related `user_meta` keys
- Fire action hook for other plugins to cleanup
**Warning signs:** "Ghost" LINE data appearing after rebind

## Code Examples

Verified patterns from official sources and existing codebase:

### Get Binding Status
```php
// Source: buygo-line-notify/LineUserService pattern adapted for LineHub
public static function getBinding(int $user_id): ?object {
    global $wpdb;
    $table_name = $wpdb->prefix . 'line_hub_users';

    $binding = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, user_id, line_uid, display_name, picture_url, email,
                    email_verified, status, created_at, updated_at
             FROM {$table_name}
             WHERE user_id = %d AND status = 'active'
             LIMIT 1",
            $user_id
        )
    );

    // Fallback: Check NSL wp_social_users table
    if (!$binding) {
        $nsl_table = $wpdb->prefix . 'social_users';
        // Check if table exists
        $nsl_binding = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID as id, user_id, identifier as line_uid,
                        NULL as display_name, NULL as picture_url,
                        NULL as email, 0 as email_verified, 'active' as status,
                        date as created_at, date as updated_at
                 FROM {$nsl_table}
                 WHERE user_id = %d AND type = 'line'
                 LIMIT 1",
                $user_id
            )
        );
        if ($nsl_binding) {
            return $nsl_binding;
        }
    }

    return $binding ?: null;
}
```

### Link User to LINE
```php
// Source: WordPress best practices + existing pattern
public static function linkUser(int $user_id, string $line_uid, array $profile = []): bool {
    global $wpdb;
    $table_name = $wpdb->prefix . 'line_hub_users';

    // Check if LINE UID already bound to another user
    $existing_user_id = self::getUserByLineUid($line_uid);
    if ($existing_user_id && $existing_user_id !== $user_id) {
        return false; // LINE UID already bound
    }

    // Check if user already has a binding
    $existing_binding = self::getBinding($user_id);
    if ($existing_binding && $existing_binding->line_uid !== $line_uid) {
        return false; // User already bound to different LINE
    }

    // Insert or update
    $data = [
        'user_id'        => $user_id,
        'line_uid'       => $line_uid,
        'display_name'   => $profile['displayName'] ?? null,
        'picture_url'    => $profile['pictureUrl'] ?? null,
        'email'          => $profile['email'] ?? null,
        'email_verified' => !empty($profile['email']) ? 1 : 0,
        'status'         => 'active',
        'link_date'      => current_time('mysql'),
        'updated_at'     => current_time('mysql'),
    ];

    if ($existing_binding) {
        // Update existing
        return $wpdb->update($table_name, $data, ['id' => $existing_binding->id]) !== false;
    } else {
        // Insert new
        $data['register_date'] = current_time('mysql');
        $data['created_at'] = current_time('mysql');
        return $wpdb->insert($table_name, $data) !== false;
    }
}
```

### Unlink User
```php
// Source: buygo-line-notify pattern (hard delete)
public static function unlinkUser(int $user_id): bool {
    global $wpdb;
    $table_name = $wpdb->prefix . 'line_hub_users';

    // Fire action before unbind for cleanup
    do_action('line_hub/user/before_unlink', $user_id);

    $result = $wpdb->delete(
        $table_name,
        ['user_id' => $user_id],
        ['%d']
    );

    if ($result !== false) {
        // Fire action after unbind
        do_action('line_hub/user/unlinked', $user_id);
        return true;
    }

    return false;
}
```

### REST API Endpoint Registration
```php
// Source: WordPress REST API Handbook
public function register_routes(): void {
    $namespace = 'line-hub/v1';

    // Get current user's binding status
    register_rest_route($namespace, '/user/binding', [
        [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_binding_status'],
            'permission_callback' => [$this, 'check_user_permission'],
        ],
        [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'unbind_user'],
            'permission_callback' => [$this, 'check_user_permission'],
        ],
    ]);

    // Admin: Get any user's binding
    register_rest_route($namespace, '/user/(?P<user_id>\d+)/binding', [
        'methods'             => 'GET',
        'callback'            => [$this, 'get_user_binding'],
        'permission_callback' => [$this, 'check_admin_permission'],
        'args'                => [
            'user_id' => [
                'required'          => true,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
}

public function check_user_permission(): bool {
    return is_user_logged_in();
}

public function check_admin_permission(): bool {
    return current_user_can('manage_options');
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `wp_users` meta only | Custom table + meta | WordPress 5.0+ | Better indexing, UNIQUE constraints |
| `permission_callback` optional | Required | WordPress 5.5 | Security enforcement |
| `wp_send_json()` in REST | Return data/WP_REST_Response | WordPress 5.5 | Proper response handling |

**Deprecated/outdated:**
- Using `wp_send_json()` or `die()` in REST API callbacks - causes deprecation notices
- Omitting `permission_callback` - required since WP 5.5
- Manual SQL queries without `$wpdb->prepare()` - security risk

## Open Questions

Things that couldn't be fully resolved:

1. **Profile Sync Timing**
   - What we know: Profile data (displayName, pictureUrl) can change on LINE
   - What's unclear: Should we sync on every login, or just on initial bind?
   - Recommendation: Sync on every login via AUTH phase (Phase 3), for Phase 2 just store initial data

2. **NSL Migration Strategy**
   - What we know: Some users may have LINE bindings only in NSL's `wp_social_users` table
   - What's unclear: Should we auto-migrate to `wp_line_hub_users` on first access?
   - Recommendation: Read-only fallback for Phase 2; migration can be Phase 3 task when user re-authenticates

3. **Soft Delete vs Hard Delete**
   - What we know: `buygo-line-notify` uses hard delete, database has `status` column
   - What's unclear: Should unbind be hard delete or soft delete (set status='inactive')?
   - Recommendation: Hard delete for consistency with NSL and user expectations; `status` field useful for future audit logging

## Sources

### Primary (HIGH confidence)
- WordPress REST API Handbook - Custom endpoints, permission callbacks: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- LINE Developers - Profile endpoint: https://developers.line.biz/en/reference/line-login/
- LINE Developers - User profile info: https://developers.line.biz/en/docs/basics/user-profile/
- Existing codebase: `buygo-line-notify/includes/services/class-line-user-service.php`
- Existing codebase: `buygo-line-notify/includes/services/class-user-service.php`
- LINE Hub Phase 1: `line-hub/includes/class-database.php` (existing schema)

### Secondary (MEDIUM confidence)
- WP Rocket - API endpoint security: https://wp-rocket.me/blog/wordpress-api-endpoints/
- Kinsta - Custom REST API development: https://kinsta.com/blog/wp-rest-api-custom-endpoint/

### Tertiary (LOW confidence)
- Community discussions on WordPress foreign key support (historical context only)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using WordPress core APIs with well-documented patterns
- Architecture: HIGH - Proven patterns from existing `buygo-line-notify` implementation
- Pitfalls: HIGH - Based on real issues encountered in existing codebase (see CLAUDE.md notes)

**Research date:** 2026-02-07
**Valid until:** 2026-03-07 (30 days - stable domain)
