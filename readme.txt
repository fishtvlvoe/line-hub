=== LINE Hub ===
Contributors: buygo
Tags: line, login, messaging, webhook, liff
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

LINE integration hub for WordPress — LINE Login, email capture, account registration, multi-scenario messaging, and unified Webhook management.

== Description ==

LINE Hub is an all-in-one LINE integration plugin for WordPress. It connects your WordPress site to the LINE ecosystem, providing seamless user login, account binding, automated messaging, and centralized Webhook management.

**Key Features:**

* **LINE Login** — Allow users to log in or register using their LINE account via OAuth 2.0
* **LIFF Integration** — In-app login experience within the LINE app using LINE Front-end Framework
* **Email Capture** — Collect email addresses from LINE users during registration for account merging
* **Account Binding** — Link existing WordPress accounts to LINE profiles
* **Multi-Scenario Messaging** — Send targeted notifications through LINE Messaging API
* **Webhook Hub** — Centralized Webhook receiver and event dispatcher for LINE events
* **Avatar Sync** — Automatically use LINE profile pictures as WordPress avatars
* **FluentCart Integration** — Add LINE Login buttons to FluentCart product pages
* **WP Profile Integration** — LINE binding section on WordPress user profile pages

**For Developers:**

* Extensible hook system (`line_hub/init`, `line_hub_send_notification`, etc.)
* REST API endpoints for settings, users, and Webhook management
* PSR-4 autoloading with clean namespace architecture
* Fully translatable with complete zh_TW translation included

== Installation ==

1. Upload the `line-hub` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to LINE Hub > Settings to configure your LINE Channel credentials
4. Set up LINE Login Channel and LIFF app in the LINE Developers Console
5. Configure Webhook URL in your LINE Official Account settings

**Requirements:**

* WordPress 6.5 or higher
* PHP 8.2 or higher
* LINE Developers account with Messaging API and Login channels

== Frequently Asked Questions ==

= Do I need a LINE Developers account? =

Yes. You need to create a LINE Developers account and set up at least a Messaging API channel. For LINE Login features, you also need a LINE Login channel.

= Can users log in with LINE on the frontend? =

Yes. LINE Hub supports both standard OAuth login (redirect flow) and LIFF-based login (in-app experience within the LINE app).

= Does it work with FluentCart? =

Yes. LINE Hub can automatically add LINE Login buttons to FluentCart product pages and checkout flows.

= Is it compatible with other social login plugins? =

LINE Hub can coexist with Nextend Social Login (NSL) and other social login plugins. A compatibility notice will appear if potential conflicts are detected.

== Screenshots ==

1. Settings page — Configure LINE Channel credentials
2. User binding — LINE account linked on WordPress profile
3. Login button — LINE Login on the frontend

== Changelog ==

= 1.0.0 =
* Initial release
* LINE Login via OAuth 2.0
* LIFF integration for in-app login
* Email capture and account merging
* Multi-scenario messaging via Messaging API
* Centralized Webhook receiver and event dispatcher
* Avatar sync from LINE profile
* FluentCart integration
* WordPress profile LINE binding section
* Complete zh_TW translation (280 strings)
* Auto-update from GitHub Releases

== Upgrade Notice ==

= 1.0.0 =
Initial release. Configure your LINE Channel credentials after activation.
