# Phase 14 Research: 檔案瘦身與方法重構

## 現況掃描（2026-02-25）

### 超標檔案（> 300 行）— 共 13 個

| # | 檔案 | 行數 | 類型 | 長方法數 |
|---|------|------|------|----------|
| 1 | `includes/liff/class-liff-handler.php` | 678 | Class | 3 |
| 2 | `includes/services/class-settings-service.php` | 653 | Class | 1 |
| 3 | `includes/services/class-user-service.php` | 549 | Class | 2 |
| 4 | `includes/class-plugin.php` | 513 | Class | 1 |
| 5 | `includes/services/class-login-service.php` | 437 | Class | 3 |
| 6 | `includes/messaging/class-messaging-service.php` | 393 | Class | 2 |
| 7 | `includes/messaging/class-flex-builder.php` | 363 | Class | 0 |
| 8 | `includes/api/class-public-api.php` | 352 | Class | 0 |
| 9 | `includes/admin/views/tab-login-settings.php` | 347 | View | 0 |
| 10 | `includes/api/class-settings-api.php` | 318 | Class | 1 |
| 11 | `includes/admin/views/tab-developer.php` | 313 | View | 0 |
| 12 | `includes/auth/class-oauth-client.php` | 306 | Class | 0 |
| 13 | `includes/auth/class-oauth-state.php` | 301 | Class | 0 |

### 長方法（> 50 行）— 共 26 個

| 行數 | 檔案 | 方法 |
|------|------|------|
| 128 | liff/class-liff-handler.php:123 | handleVerify() |
| 116 | liff/class-liff-handler.php:257 | handleEmailSubmit() |
| 93 | class-auto-updater.php:95 | check_update() |
| 86 | services/class-user-service.php:199 | linkUser() |
| 85 | services/class-content-service.php:31 | downloadAndUpload() |
| 82 | admin/tabs/class-developer-tab.php:43 | get_api_endpoints() |
| 79 | services/class-user-service.php:346 | updateProfile() |
| 73 | services/class-login-service.php:64 | handleUser() |
| 71 | services/class-login-service.php:323 | loginUser() |
| 71 | liff/class-liff-handler.php:497 | createNewUser() |
| 65 | webhook/class-event-dispatcher.php:54 | dispatchEvent() |
| 65 | services/class-login-service.php:249 | createUser() |
| 64 | cli/class-migrate-avatars-command.php:34 | run() |
| 63 | webhook/class-webhook-receiver.php:47 | handleWebhook() |
| 63 | integration/class-fluent-cart-connector.php:122 | renderBindingSection() |
| 63 | auth/class-session-transfer.php:125 | handleExchange() |
| 61 | api/class-settings-api.php:192 | update_settings() |
| 60 | class-plugin.php:390 | override_avatar_with_line() |
| 58 | auth/class-auth-callback.php:136 | processCallback() |
| 57 | messaging/class-messaging-service.php:199 | multicast() |
| 57 | api/class-settings-api.php:41 | register_routes() |
| 54 | services/class-settings-service.php:298 | get() |
| 54 | class-auto-updater.php:199 | plugin_info() |
| 54 | admin/tabs/class-developer-tab.php:131 | get_hooks_data() |
| 53 | webhook/class-event-dispatcher.php:125 | dispatchMessage() |
| 53 | messaging/class-messaging-service.php:264 | sendRequest() |

### Autoloader

PSR-4 風格（`includes/autoload.php`）：
- `LineHub\Namespace\ClassName` → `includes/namespace/class-class-name.php`
- 新類別只要放對目錄和檔名，自動載入，無需手動註冊

## 拆分策略

### 策略 A：類別提取（> 500 行的 Class）

**class-liff-handler.php (678)**
- 提取 `LiffUserProcessor`（handleVerify 中的用戶處理 + createNewUser + migrateBindingIfNeeded）
- LiffHandler 只保留路由分發和頁面渲染
- 預期：LiffHandler ~280、LiffUserProcessor ~250

**class-settings-service.php (653)**
- 提取 `SettingsSchema`（$schema 陣列 22-281 行，約 260 行）
- SettingsService 只保留 CRUD + 加密方法
- 預期：SettingsService ~290、SettingsSchema ~280

**class-user-service.php (549)**
- 提取 `UserProfileManager`（updateProfile + batch + NSL migration）
- UserService 只保留 link/unlink/query 核心方法
- 預期：UserService ~280、UserProfileManager ~250

**class-plugin.php (513)**
- 提取 `PluginRoutes`（auth routes + LIFF routes + handle/intercept）
- Plugin 只保留 singleton + init + hooks + assets
- 預期：Plugin ~280、PluginRoutes ~220

### 策略 B：方法重構（300-500 行 Class）

**class-login-service.php (437)** — 方法重構不拆類別
**class-messaging-service.php (393)** — 方法重構不拆類別
**class-public-api.php (352)** — 方法重構不拆類別
**class-settings-api.php (318)** — 方法重構不拆類別

### 策略 C：類別提取（特殊：方法多但都短）

**class-flex-builder.php (363)**
- 提取 `FlexElements`（text/image/button/separator 等 12 個元素方法）
- FlexBuilder 保留 bubble/carousel/box 容器方法
- 預期：FlexBuilder ~160、FlexElements ~200

### 策略 D：View Partial 提取

**tab-login-settings.php (347)** — 提取按鈕位置設定 + Shortcode 說明區塊
**tab-developer.php (313)** — 提取 API 端點表格 + Hook 說明區塊

### 策略 E：邊界檔案（301-306 行）

**class-oauth-client.php (306)** — 方法重構可降到 ~280
**class-oauth-state.php (301)** — 清理冗餘註解即可

### 策略 F：獨立長方法（檔案 < 300 但方法 > 50 行）

9 個檔案中的 13 個方法需要拆短：
- class-auto-updater.php: check_update() 93 行、plugin_info() 54 行
- class-content-service.php: downloadAndUpload() 85 行
- class-developer-tab.php: get_api_endpoints() 82 行、get_hooks_data() 54 行
- class-event-dispatcher.php: dispatchEvent() 65 行、dispatchMessage() 53 行
- class-webhook-receiver.php: handleWebhook() 63 行
- class-fluent-cart-connector.php: renderBindingSection() 63 行
- class-session-transfer.php: handleExchange() 63 行
- class-auth-callback.php: processCallback() 58 行
- class-migrate-avatars-command.php: run() 64 行

## 風險與注意事項

1. **Autoloader 無需修改**：新類別遵循命名規則即自動載入
2. **命名空間一致**：提取的類別放在同一命名空間目錄下
3. **向後相容**：原類別 public 方法簽名不變，內部委派到新類別
4. **View 模板不走 autoloader**：用 `require` 載入 partial
5. **測試**：Phase 16 才建測試框架，本 Phase 用 PHP syntax check + grep 驗證

## RESEARCH COMPLETE
