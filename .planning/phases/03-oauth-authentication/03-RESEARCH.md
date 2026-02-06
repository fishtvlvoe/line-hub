# Phase 3: OAuth Authentication - Research

**Researched:** 2026-02-07
**Domain:** LINE OAuth 2.0 / OpenID Connect, WordPress Authentication, CSRF Protection
**Confidence:** HIGH

## Summary

Phase 3 implements LINE OAuth 2.0 authentication for WordPress, enabling users to securely log in with their LINE accounts. Research reveals that NSL (Nextend Social Login) provides a proven, production-tested implementation that we should closely follow for security-critical aspects like State storage and CSRF protection.

Key findings:
1. **NSL State Storage**: Uses WordPress Transient API for logged-in users, PHP Session (via custom cookie + site_transient) for anonymous users
2. **State expiration**: NSL uses `HOUR_IN_SECONDS` (1 hour) by default via `nsl_persistent_expiration` filter; we'll use 5 minutes per user decision
3. **Username generation**: NSL sanitizes display_name, uses configurable prefix (`user_prefix`), falls back to random ID if empty, appends number suffix for conflicts
4. **CSRF protection**: State-only approach (no additional PKCE or nonce for web flow), 32-character random hex string
5. **LINE specifics**: ID Token verification via LINE API, email requires explicit permission scope application

**Primary recommendation:** Implement OAuth flow closely following NSL's proven patterns, adapting only where user decisions differ (5-minute state expiration, subscriber role). Do not hand-roll security mechanisms.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Core | 6.x | User management, authentication | Native platform |
| LINE Login v2.1 | - | OAuth 2.0 / OpenID Connect | Current LINE standard |
| `wp_remote_*` | - | HTTP requests to LINE API | WordPress standard |
| WordPress Transient API | - | State storage | Proven by NSL |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `wp_insert_user` | - | New user creation | Registration flow |
| `wp_set_auth_cookie` | - | Login session | Post-authentication |
| `wp_generate_password` | - | Random password | New user accounts |
| `sanitize_user` | - | Username sanitization | Username generation |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom HTTP client | Guzzle/cURL | WordPress HTTP is simpler, handles proxies/SSL |
| Database session storage | PHP Session | Transient handles multisite, load balancing |
| JWT library for ID Token | LINE API verification | LINE's verify endpoint is simpler and authoritative |

**Installation:**
No additional packages needed. WordPress core provides all required functionality.

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── auth/
│   ├── class-oauth-client.php       # OAuth 2.0 flow handler
│   ├── class-oauth-state.php        # State generation/validation
│   └── class-auth-callback.php      # Callback page handler
├── services/
│   ├── class-user-service.php       # (Phase 2, extended)
│   └── class-login-service.php      # Login/registration orchestration
└── class-plugin.php                 # Route registration
```

### Pattern 1: OAuth State Storage (NSL Pattern)
**What:** Dual storage strategy - Transient for logged-in users, Session-based for anonymous users
**When to use:** All OAuth state operations
**Example:**
```php
// Source: NSL/Persistent/Persistent.php + NSL/Persistent/Storage/Session.php
namespace LineHub\Auth;

class OAuthState {
    private const STATE_LENGTH = 32;
    private const EXPIRATION_SECONDS = 300; // 5 minutes (user decision)

    /**
     * Generate and store a new state parameter
     */
    public static function generate(): string {
        $state = self::generateRandomState();

        if (is_user_logged_in()) {
            // Use Transient API for logged-in users
            $key = 'line_hub_state_' . get_current_user_id();
            set_transient($key, $state, self::EXPIRATION_SECONDS);
        } else {
            // Use session-based storage for anonymous users
            $session_id = self::getOrCreateSessionId();
            set_site_transient('line_hub_session_' . $session_id, [
                'state' => $state,
            ], self::EXPIRATION_SECONDS);
        }

        return $state;
    }

    /**
     * Validate state from callback
     */
    public static function validate(string $received_state): bool {
        if (empty($received_state)) {
            return false;
        }

        $stored_state = self::getStoredState();
        if ($stored_state === null) {
            return false;
        }

        // Timing-safe comparison
        return hash_equals($stored_state, $received_state);
    }

    /**
     * Generate cryptographically secure random state
     * Source: NSL oauth2.php generateRandomState()
     */
    private static function generateRandomState(): string {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(self::STATE_LENGTH));
        }
        // Fallback for older PHP (unlikely but safe)
        return wp_generate_password(self::STATE_LENGTH, false, false);
    }

    /**
     * Get or create session ID for anonymous users
     * Source: NSL Session.php pattern
     */
    private static function getOrCreateSessionId(): string {
        $cookie_name = 'line_hub_session';

        if (isset($_COOKIE[$cookie_name])) {
            return md5(SECURE_AUTH_KEY . $_COOKIE[$cookie_name]);
        }

        $unique = uniqid('linehub', true);
        setcookie($cookie_name, $unique, 0, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl());

        return md5(SECURE_AUTH_KEY . $unique);
    }
}
```

### Pattern 2: OAuth Client (LINE-specific)
**What:** Handle authorization URL creation and token exchange
**When to use:** OAuth flow initiation and callback processing
**Example:**
```php
// Source: NSL providers/line/line-client.php + oauth2.php adapted
namespace LineHub\Auth;

class OAuthClient {
    private const AUTH_ENDPOINT = 'https://access.line.me/oauth2/v2.1/authorize';
    private const TOKEN_ENDPOINT = 'https://api.line.me/oauth2/v2.1/token';
    private const VERIFY_ENDPOINT = 'https://api.line.me/oauth2/v2.1/verify';
    private const PROFILE_ENDPOINT = 'https://api.line.me/v2/profile';

    private string $client_id;
    private string $client_secret;
    private string $redirect_uri;

    /**
     * Create authorization URL
     */
    public function createAuthUrl(array $options = []): string {
        $state = OAuthState::generate();

        $params = [
            'response_type' => 'code',
            'client_id'     => urlencode($this->client_id),
            'redirect_uri'  => urlencode($this->redirect_uri),
            'state'         => urlencode($state),
            'scope'         => urlencode('profile openid email'),
        ];

        // Optional: bot_prompt for LINE Official Account friend add
        if (!empty($options['bot_prompt'])) {
            $params['bot_prompt'] = urlencode($options['bot_prompt']);
        }

        // Optional: initial_amr_display for QR code login
        if (!empty($options['initial_amr_display'])) {
            $params['initial_amr_display'] = $options['initial_amr_display'];
        }

        // Disable auto-login to force manual authentication
        $params['disable_auto_login'] = 'true';
        $params['disable_ios_auto_login'] = 'true';

        return self::AUTH_ENDPOINT . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens
     */
    public function authenticate(string $code): array {
        $response = wp_remote_post(self::TOKEN_ENDPOINT, [
            'timeout' => 15,
            'body'    => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $this->redirect_uri,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            throw new \Exception($body['error_description'] ?? 'Token exchange failed');
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Verify ID token and extract user data
     * Source: NSL line.php getCurrentUserInfo()
     */
    public function verifyIdToken(string $id_token): array {
        $response = wp_remote_post(self::VERIFY_ENDPOINT, [
            'timeout' => 15,
            'body'    => [
                'id_token'  => $id_token,
                'client_id' => $this->client_id,
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            return []; // ID token invalid or expired
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Get user profile from LINE API
     */
    public function getProfile(string $access_token): array {
        $response = wp_remote_get(self::PROFILE_ENDPOINT, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
```

### Pattern 3: Username Generation (NSL Pattern)
**What:** Generate valid WordPress username from LINE display_name
**When to use:** New user registration
**Example:**
```php
// Source: NSL user.php sanitizeUserName() + register()
namespace LineHub\Services;

class LoginService {
    private const USER_PREFIX = 'line_';  // Configurable in settings
    private const USER_FALLBACK = 'user_'; // Fallback prefix

    /**
     * Generate WordPress-compliant username from LINE profile
     * Source: NSL user.php sanitizeUserName() + register()
     */
    public static function generateUsername(string $display_name): string {
        // Try to use display_name first
        $username = self::sanitizeUsername($display_name);

        // Fallback: use random ID if display_name invalid
        if (empty($username)) {
            $username = sanitize_user(self::USER_FALLBACK . md5(uniqid(rand())), true);
        }

        // Ensure uniqueness by appending number
        $base_username = $username;
        $i = 1;
        while (username_exists($username)) {
            $username = $base_username . $i;
            $i++;
        }

        return $username;
    }

    /**
     * Sanitize username following NSL pattern
     * - Convert to lowercase
     * - Remove whitespace
     * - Add prefix
     * - Validate length (max 60 chars)
     */
    private static function sanitizeUsername(string $name): ?string {
        if (empty($name)) {
            return null;
        }

        // Lowercase and remove spaces
        $username = strtolower($name);
        $username = preg_replace('/\s+/', '', $username);

        // Sanitize without prefix first to check validity
        if (empty(sanitize_user($username, true))) {
            return null; // Invalid characters only
        }

        // Add prefix and final sanitization
        $prefixed = sanitize_user(self::USER_PREFIX . $username, true);

        // Check length (WordPress max is 60)
        if (mb_strlen($prefixed) > 60) {
            $prefixed = mb_substr($prefixed, 0, 60);
        }

        if (empty($prefixed) || !validate_username($prefixed)) {
            return null;
        }

        return $prefixed;
    }
}
```

### Pattern 4: Redirect Storage (NSL Pattern)
**What:** Store original page URL for post-login redirect
**When to use:** Before OAuth redirect
**Example:**
```php
// Source: NSL provider.php liveConnectRedirect()
public function storeRedirectUrl(): void {
    if (!empty($_GET['redirect'])) {
        // Store in same session/transient as state
        $this->setPersistent('redirect', sanitize_url($_GET['redirect']));
    }
}

public function getRedirectUrl(): string {
    $stored = $this->getPersistent('redirect');

    // Priority 1: Stored redirect from login button
    if (!empty($stored)) {
        $this->deletePersistent('redirect');
        return wp_validate_redirect($stored, home_url('/'));
    }

    // Priority 2: Admin-configured fixed redirect
    $fixed = get_option('line_hub_login_redirect');
    if (!empty($fixed)) {
        return $fixed;
    }

    // Priority 3: Home page
    return home_url('/');
}
```

### Anti-Patterns to Avoid
- **Hand-rolling CSRF protection:** Use State parameter with proper storage, don't invent new schemes
- **Storing tokens in cookies:** Use server-side storage (Transient) for sensitive data
- **Trusting client-provided data:** Always verify ID token with LINE API
- **Skipping duplicate binding check:** Check if LINE UID already linked before creating account
- **Using md5() for state generation:** Use `random_bytes()` for cryptographic randomness

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Session storage | Custom $_SESSION | Transient + Cookie (NSL pattern) | Works with load balancers, multisite |
| CSRF protection | Custom token system | OAuth State parameter | Standard, well-tested |
| JWT decoding | Custom base64 decode | LINE verify endpoint | Validates signature, expiry |
| Password generation | Custom random string | `wp_generate_password(12, false)` | Cryptographically secure |
| User creation | Direct SQL insert | `wp_insert_user()` | Handles hooks, sanitization |
| Login session | Set cookies manually | `wp_set_auth_cookie()` | Secure cookies, expiry |

**Key insight:** OAuth authentication is security-critical. NSL has been production-tested for years across thousands of sites. Copying their patterns for State storage and CSRF protection eliminates security vulnerabilities.

## Common Pitfalls

### Pitfall 1: State Expiration Too Long
**What goes wrong:** State tokens remain valid for hours, increasing CSRF attack window
**Why it happens:** Using default WordPress transient expiration (HOUR_IN_SECONDS)
**How to avoid:**
1. Set 5-minute expiration (user decision)
2. Delete state immediately after validation
3. Show friendly error "Login session expired, please try again" for timeouts
**Warning signs:** Security audits flagging long-lived tokens

### Pitfall 2: Email Not in ID Token
**What goes wrong:** Registration fails silently, or user sees cryptic error
**Why it happens:** EMAIL permission not approved in LINE Developers Console, or user declined email scope
**How to avoid:**
1. Check if email exists in ID Token response
2. If missing, display inline email input form on callback page
3. Validate entered email, check for conflicts
**Warning signs:** Users complaining about "stuck" on login

### Pitfall 3: Session Cookie Not Set Before Redirect
**What goes wrong:** State validation fails because session ID not stored
**Why it happens:** Cookie set in same request as redirect, not sent back
**How to avoid:**
1. Use session-less approach (encode session ID in state or use existing WP session)
2. Or use JavaScript redirect after cookie is set
3. NSL pattern: Store transient with hashed cookie value, cookie value itself not critical
**Warning signs:** "Invalid state" errors on first login attempt

### Pitfall 4: LINE UID Already Linked to Different User
**What goes wrong:** Login fails without explanation
**Why it happens:** User previously linked this LINE account to a different WordPress account
**How to avoid:**
1. Before registration, check if LINE UID exists in `wp_line_hub_users`
2. If linked to different user, show clear message: "This LINE account is linked to another user"
3. Provide option to unlink (if logged into that account) or contact support
**Warning signs:** Users creating multiple WordPress accounts

### Pitfall 5: Chinese Display Name Becomes Empty Username
**What goes wrong:** Username is just prefix + random hash
**Why it happens:** `sanitize_user()` with strict mode removes all non-ASCII characters
**How to avoid:**
1. Accept this as expected behavior (NSL does the same)
2. Username becomes `line_5f4dcc3b5aa765d61d8327deb882cf99`
3. Display name is still preserved correctly in WordPress profile
**Warning signs:** None - this is correct behavior

### Pitfall 6: Redirect Loop on Callback Page
**What goes wrong:** Page keeps reloading or user stuck on loading
**Why it happens:** Callback page triggered multiple times, or state already consumed
**How to avoid:**
1. Delete state immediately after first validation
2. Redirect to final destination, not back to callback
3. Handle duplicate callback requests gracefully (check if user already logged in)
**Warning signs:** Browser showing "too many redirects" error

## Code Examples

Verified patterns from official sources:

### Complete OAuth Flow Entry Point
```php
// Source: NSL provider.php connect() + doAuthenticate()
namespace LineHub\Auth;

class AuthCallback {
    public function handleRequest(): void {
        try {
            // Check for errors from LINE
            if (!empty($_GET['error'])) {
                $error_description = sanitize_text_field($_GET['error_description'] ?? 'Login cancelled');
                $this->handleError($error_description);
                return;
            }

            // Handle authorization code callback
            if (!empty($_GET['code']) && !empty($_GET['state'])) {
                $this->processCallback($_GET['code'], $_GET['state']);
                return;
            }

            // Initiate OAuth flow
            $this->initiateAuth();

        } catch (\Exception $e) {
            $this->handleError($e->getMessage());
        }
    }

    private function initiateAuth(): void {
        // Store original page for redirect
        if (!empty($_GET['redirect'])) {
            OAuthState::storeRedirect(sanitize_url($_GET['redirect']));
        }

        $client = new OAuthClient();
        $auth_url = $client->createAuthUrl([
            'bot_prompt' => get_option('line_hub_bot_prompt', ''),
            'initial_amr_display' => get_option('line_hub_initial_amr_display', ''),
        ]);

        // Redirect to LINE authorization
        header('Location: ' . $auth_url);
        exit;
    }

    private function processCallback(string $code, string $state): void {
        // Validate state (CSRF protection)
        if (!OAuthState::validate($state)) {
            throw new \Exception(__('Login session expired. Please try again.', 'line-hub'));
        }

        // Exchange code for tokens
        $client = new OAuthClient();
        $tokens = $client->authenticate($code);

        // Extract user info from ID token
        $id_token_data = [];
        if (!empty($tokens['id_token'])) {
            $id_token_data = $client->verifyIdToken($tokens['id_token']);
        }

        // Get full profile
        $profile = $client->getProfile($tokens['access_token']);

        // Merge email from ID token into profile
        $user_data = [
            'userId'      => $profile['userId'],
            'displayName' => $profile['displayName'] ?? '',
            'pictureUrl'  => $profile['pictureUrl'] ?? '',
            'email'       => $id_token_data['email'] ?? '',
        ];

        // Handle login/registration
        $login_service = new LoginService();
        $login_service->handleUser($user_data, $tokens);
    }
}
```

### Login/Registration Orchestration
```php
// Source: NSL user.php liveConnectGetUserProfile() + prepareRegister()
namespace LineHub\Services;

class LoginService {
    public function handleUser(array $user_data, array $tokens): void {
        $line_uid = $user_data['userId'];

        // Check if LINE UID already linked to a WordPress user
        $existing_user_id = UserService::getUserByLineUid($line_uid);

        if ($existing_user_id) {
            // Existing user - log them in
            $this->loginUser($existing_user_id, $user_data, $tokens);
            return;
        }

        // New user flow
        $email = $user_data['email'];

        // Check if email is missing
        if (empty($email)) {
            // Show email input form
            $this->showEmailForm($user_data, $tokens);
            return;
        }

        // Check if email already exists
        $email_user_id = email_exists($email);
        if ($email_user_id) {
            // Auto-link to existing account (same email = same person)
            UserService::linkUser($email_user_id, $line_uid, $user_data);
            $this->loginUser($email_user_id, $user_data, $tokens);
            return;
        }

        // Create new user
        $user_id = $this->createUser($user_data);
        UserService::linkUser($user_id, $line_uid, $user_data);
        $this->loginUser($user_id, $user_data, $tokens);
    }

    private function createUser(array $user_data): int {
        $username = self::generateUsername($user_data['displayName']);

        $user_id = wp_insert_user([
            'user_login'   => $username,
            'user_email'   => $user_data['email'],
            'user_pass'    => wp_generate_password(12, false),
            'display_name' => $user_data['displayName'],
            'role'         => 'subscriber', // User decision: subscriber role
        ]);

        if (is_wp_error($user_id)) {
            throw new \Exception($user_id->get_error_message());
        }

        // Notify user to set password
        update_user_option($user_id, 'default_password_nag', true, true);

        // Fire registration hook
        do_action('line_hub/user/registered', $user_id);

        return $user_id;
    }

    private function loginUser(int $user_id, array $user_data, array $tokens): void {
        // Update profile (sync on every login)
        UserService::updateProfile($user_id, $user_data);

        // Store access token if configured
        if (get_option('line_hub_store_access_token', false)) {
            update_user_meta($user_id, 'line_hub_access_token', $tokens['access_token']);
        }

        // Set WordPress login session
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true, is_ssl());

        // Fire login hook
        do_action('line_hub/user/logged_in', $user_id);
        do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));

        // Redirect to original page or home
        $redirect_url = OAuthState::getRedirect() ?: home_url('/');
        wp_safe_redirect($redirect_url);
        exit;
    }

    private function showEmailForm(array $user_data, array $tokens): void {
        // Store user data in transient for form submission
        $temp_key = 'line_hub_pending_' . md5($user_data['userId']);
        set_transient($temp_key, [
            'user_data' => $user_data,
            'tokens'    => $tokens,
        ], 10 * MINUTE_IN_SECONDS);

        // Render email input form
        // This will be a simple inline form on the callback page
        include LINE_HUB_PATH . '/includes/auth/email-form-template.php';
        exit;
    }
}
```

### Email Form Template
```php
// includes/auth/email-form-template.php
// Source: Custom implementation following user decisions
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e('Complete Your Registration', 'line-hub'); ?></title>
    <?php wp_head(); ?>
</head>
<body>
    <div class="line-hub-email-form">
        <h2><?php esc_html_e('Almost done!', 'line-hub'); ?></h2>
        <p><?php esc_html_e('Please enter your email address to complete registration.', 'line-hub'); ?></p>

        <form method="POST" action="<?php echo esc_url(home_url('/line-hub/auth/email-submit')); ?>">
            <?php wp_nonce_field('line_hub_email_submit', '_wpnonce'); ?>
            <input type="hidden" name="temp_key" value="<?php echo esc_attr($temp_key); ?>">

            <label for="email"><?php esc_html_e('Email Address', 'line-hub'); ?></label>
            <input type="email" name="email" id="email" required>

            <button type="submit"><?php esc_html_e('Continue', 'line-hub'); ?></button>
        </form>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| LINE Login v2.0 | LINE Login v2.1 | 2020 | OpenID Connect, ID token |
| Implicit flow | Authorization code flow | OAuth 2.1 | More secure |
| Email in profile API | Email in ID token only | LINE v2.1 | Must use OpenID Connect |
| PHP Session | Transient + Cookie | WordPress evolution | Multisite compatible |

**Deprecated/outdated:**
- LINE Login v1.0 - End of life
- LINE Login v2.0 - Deprecated, no email support
- Using `$_SESSION` directly - WordPress prefers Transient API
- Decoding JWT client-side - Use LINE verify endpoint instead

## Open Questions

Things that couldn't be fully resolved:

1. **Access Token Storage**
   - What we know: NSL stores access_token if `store_access_token` setting enabled
   - What's unclear: Will LineHub need access_token for other features?
   - Recommendation: Add setting option, default to disabled, Phase 4/5 may need it for Messaging API

2. **Email Verification Flow**
   - What we know: User decision says admin can enable/disable email verification
   - What's unclear: Full verification flow implementation (send email, verify token)
   - Recommendation: Implement basic toggle now, full email verification as sub-task or Phase 4

3. **NSL Coexistence**
   - What we know: User may have NSL installed simultaneously
   - What's unclear: Should LineHub detect and use NSL's LINE channel if configured?
   - Recommendation: Operate independently, don't share credentials with NSL

## Sources

### Primary (HIGH confidence)
- NSL Source Code: `/Users/fishtv/Local Sites/buygo/app/public/wp-content/plugins/nextend-facebook-connect/`
  - `includes/oauth2.php` - State generation, CSRF validation, token exchange
  - `includes/provider.php` - User login, registration, redirect handling
  - `includes/user.php` - Username generation, user creation
  - `NSL/Persistent/Persistent.php` - State storage abstraction
  - `NSL/Persistent/Storage/Session.php` - Anonymous user session handling
  - `providers/line/line.php` - LINE-specific profile handling
  - `providers/line/line-client.php` - LINE OAuth endpoints
- [LINE Login Integration Guide](https://developers.line.biz/en/docs/line-login/integrate-line-login/)
- [LINE OAuth Endpoints](https://logto.io/oauth-providers-explorer/line)

### Secondary (MEDIUM confidence)
- [WordPress OAuth 2.0 API Documentation](https://developer.wordpress.com/docs/api/oauth2/)
- [OAuth State CSRF Protection Best Practices](https://codesignal.com/learn/courses/oauth-fundamentals-mock-implementation-1/lessons/oauth-state-and-csrf-protection)
- [MiniOrange Enforce State Parameters](https://developers.miniorange.com/docs/oauth/wordpress/server/enforce-state-parameters)

### Tertiary (LOW confidence)
- [Auth0 WordPress Invalid State Troubleshooting](https://auth0.com/docs/customize/integrations/cms/wordpress-plugin/troubleshoot-wordpress-plugin-invalid-state-errors)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Using NSL proven patterns and WordPress core
- Architecture: HIGH - Direct adaptation of NSL implementation
- Pitfalls: HIGH - Based on NSL edge case handling and common OAuth issues
- LINE specifics: HIGH - Official documentation verified

**Research date:** 2026-02-07
**Valid until:** 2026-03-07 (30 days - OAuth protocols stable)
