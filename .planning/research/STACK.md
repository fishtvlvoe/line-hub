# Technology Stack

**Project:** LineHub v2.0 重構與擴展
**Researched:** 2026-02-24
**Scope:** Phase A（Bug 修復）、Phase B（Tab 重構）、Phase C（REST API + API Key 管理）

---

## 摘要：不需要新增任何外部套件

LineHub v2.0 的三個 Phase 所需的所有技術，WordPress Core 已原生提供。
這不是「要選哪個框架」的問題，而是「如何正確使用已有工具」的問題。

---

## Phase A：Bug 修復 — SettingsService array 序列化

### 問題根因分析

**[HIGH confidence — 直接閱讀原始碼確認]**

`class-settings-service.php` 的 `set()` 方法（第 398-399 行）已經正確序列化：

```php
if ($config['type'] === 'array') {
    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
}
```

問題在 `get()` 方法的快取機制（第 314-320 行）：

```php
$cached = get_transient($cache_key);
if ($cached !== false) {
    return $cached;  // 快取命中時直接回傳，跳過 cast_value()
}
```

當 array 值**第一次**寫入後讀取：
1. 快取未命中 → 從資料庫讀取 JSON 字串 → 執行 `cast_value()` → 正確解碼為 array → 存入快取
2. 快取命中 → 直接回傳快取的 array → 正確

但問題發生在**舊資料**或**其他程式碼路徑**存入的情況：若某處呼叫 `set()` 時 array 沒有先通過 `validate_value()`（validate 在 set() 第 389 行），`validate_value()` 第 541-544 行要求 `is_array($value) === true`，若傳入的是字串（例如已序列化的 JSON 字串），validate 會回傳 false，整個 `set()` 直接回傳 false 而不儲存。

**實際 Bug 路徑**：

表單提交時（`save_login_tab()`，第 305-313 行）：
- `$_POST['default_roles']` 是 array → 正確
- `$_POST['login_button_positions']` 是 array → 正確

但如果 `$_POST` 中沒有這些欄位（checkbox 全不選），程式使用 `[]` 空陣列呼叫 `set()`，`json_encode([])` 結果是 `"[]"` 字串，這是正確的。

**真正的問題可能是**：`SettingsService::get()` 回傳快取時，Transient 在不同 PHP 請求間序列化方式不同。WordPress 的 `set_transient()` 在沒有 Object Cache 時，使用 `maybe_serialize()` 儲存到 `wp_options`，array 會被 PHP `serialize()` 序列化。`get_transient()` 回傳時 WordPress 會自動 `unserialize()`。這個路徑是正確的。

**需要測試驗證的假設**：`SettingsService::get()` 在第一次讀取（快取未命中）執行 `cast_value()` 後，把 array 存入 transient，下次讀取快取命中回傳 array，這個路徑理論上沒問題。需要在實際環境印出中間值確認。

### 修復技術：純 WordPress Core

| 工具 | 說明 | 信心度 |
|------|------|--------|
| `json_encode()` / `json_decode()` | PHP 原生，陣列序列化標準方式 | HIGH |
| `set_transient()` / `get_transient()` | WordPress Transient API，自動處理 PHP serialize | HIGH |
| `maybe_serialize()` / `maybe_unserialize()` | WordPress 輔助函數，可替代手動判斷 | HIGH |

**建議修復方式**：在 `set()` 儲存到快取前，確保 array 存為 array（而不是 JSON 字串）：

```php
// 目前做法：先 json_encode 再存快取（快取值是字串，與資料庫格式相同）
// 問題：若快取命中，回傳字串而非 array

// 正確做法：分離「資料庫存儲格式」和「快取格式」
// 資料庫：存 JSON 字串
// 快取：存原始 array（讓 WordPress 的 serialize 處理）
```

不需要引入新套件。

---

## Phase B：Tab 重構 + 熵減

### 現況診斷

**[HIGH confidence — 直接閱讀原始碼]**

- `class-settings-page.php`：441 行，超過 300 行上限
- 目前 3 Tab：`settings`、`login`、`developer`
- 渲染委託給 `views/tab-*.php`（已正確分離）
- 表單提交已按 Tab 隔離（`handle_save()` 依 `$_POST['tab']` 分流）

### Tab 架構模式

**WordPress 官方推薦方式**（無 Settings API 依賴，已符合現有架構）：

```php
// 每個 Tab 有獨立的 nonce action
wp_nonce_field('line_hub_save_' . $tab, 'line_hub_nonce');

// 後台 GET 參數切換 Tab
$current_tab = sanitize_key($_GET['tab'] ?? 'settings');

// admin_post_{action} 處理提交
add_action('admin_post_line_hub_save_settings', [$this, 'handle_save']);
```

現有架構**已經正確**，問題只是 `class-settings-page.php` 本身太大。

### 熵減拆檔策略

**[HIGH confidence — 基於 WordPress Plugin Handbook 規範]**

```
includes/admin/
├── class-settings-page.php      # 只做路由：Tab 切換 + action 分發（< 150 行）
├── tabs/
│   ├── class-tab-settings.php   # 「設定」Tab 的 save 邏輯（< 100 行）
│   ├── class-tab-login.php      # 「登入」Tab 的 save 邏輯（< 100 行）
│   └── class-tab-developer.php  # 「開發者」Tab 的 save + API Key 邏輯（< 150 行）
└── views/
    ├── tab-settings.php         # HTML 模板（已存在）
    ├── tab-login.php            # HTML 模板（已存在）
    └── tab-developer.php        # HTML 模板（已存在）
```

不需要引入新套件。WordPress 的 `add_action()` 本身就是依賴注入。

### 不建議引入的方案

| 方案 | 為何不用 |
|------|----------|
| WordPress Settings API | 過度耦合，限制 UI 自由度，適合簡單設定頁而非多 Tab 複雜頁面 |
| CMB2 / ACF | 外部依賴，增加維護負擔，LineHub 不需要自訂欄位類型 |
| React/Vue 前端 | 計畫外，改寫成本過高，純 PHP + HTML 已足夠 |

---

## Phase C：REST API + API Key 管理

### 現況診斷

**[HIGH confidence — 直接閱讀原始碼]**

`includes/api/class-public-api.php` 已實作：
- `POST /line-hub/v1/messages/text` — 文字訊息
- `POST /line-hub/v1/messages/flex` — Flex 訊息
- `POST /line-hub/v1/messages/broadcast` — 批量發送
- `GET /line-hub/v1/users/{id}/binding` — 用戶綁定查詢
- `GET /line-hub/v1/users/lookup` — Email 查詢

認證機制（`authenticate()` 方法）：
- 已登入管理員：直接通過
- API Key：Header `X-LineHub-API-Key: lhk_xxxxx`，驗證方式為 `wp_hash($key) === stored_hash`

API Key 生成（`handle_generate_api_key()`）：
- 格式：`lhk_` + 32 位 hex（共 36 字元）
- 儲存：只存 hash（`wp_hash()`）和前綴（前 8 碼）

**Phase C 主要缺口**：**Rate Limiting 缺失**。

### Rate Limiting 技術選型

**[HIGH confidence — 多來源確認]**

**選擇：WordPress Transient API（原生）**，不引入外部套件。

理由：
1. 無外部依賴，符合熵減原則
2. WordPress Transient 在有 Object Cache（Redis）時自動升級為高效能實作
3. 對 API Key 認證的限流場景（每分鐘 60 次），Transient 效能足夠

實作模式：

```php
// 在 authenticate() 中加入 rate limiting
private static function check_rate_limit(string $identifier): bool {
    $key   = 'line_hub_rl_' . md5($identifier);
    $count = (int) get_transient($key);
    $limit = 60; // 每分鐘 60 次
    $window = 60; // 秒

    if ($count === 0) {
        set_transient($key, 1, $window);
        return true;
    }

    if ($count >= $limit) {
        return false; // 觸發 429
    }

    set_transient($key, $count + 1, $window);
    return true;
}
```

注意事項：
- Rate limit 的 key 用 API Key prefix（而非 IP），避免動態 IP 問題
- 超出限制回傳 `WP_Error` with HTTP 429

**不採用的方案**：

| 方案 | 為何不採用 |
|------|-----------|
| WP REST Cop plugin | 外部依賴，對 API Key 場景過度複雜 |
| Redis + 自訂實作 | 需要 Redis 環境，增加部署複雜度 |
| Server-level（Nginx rate limit） | InstaWP 主機無法自訂 Nginx 設定 |

### API Key 認證安全強化

**[HIGH confidence — 基於 WordPress 6.8 更新和安全最佳實踐]**

**問題**：現有的 `wp_hash($key)` 使用 WordPress 的 `hash_hmac('md5', ...)` 實作，安全性不如 `password_hash()`。

**建議**：升級為 `password_hash($key, PASSWORD_BCRYPT)`，配合 `password_verify()` 驗證。

WordPress 6.8（2025年4月）已將 `wp_hash_password()` 升級為 bcrypt，代表 PHP 環境已保證支援。

```php
// 生成時（handle_generate_api_key）
$hash = password_hash($raw_key, PASSWORD_BCRYPT);
SettingsService::set('integration', 'api_key_hash', $hash);

// 驗證時（authenticate）
$stored_hash = SettingsService::get('integration', 'api_key_hash', '');
if (!password_verify($key, $stored_hash)) {
    return new \WP_Error('invalid_api_key', '...', ['status' => 401]);
}
```

**為何不用 WordPress Application Passwords**：

| 面向 | Application Passwords | 自訂 API Key |
|------|----------------------|-------------|
| 目標用戶 | 需要 WordPress user 帳號的應用 | 外部 SaaS（無 WP 帳號）|
| 權限範圍 | 綁定 WP 用戶角色 | 自訂（只限 LineHub API）|
| 管理界面 | 需進用戶個人資料頁 | LineHub 後台統一管理 |
| 適合 LineHub 嗎？ | 否，過度複雜 | 是，簡單直接 |

**結論**：維持自訂 API Key 方案，只需升級 hash 演算法。

---

## 各 Phase 技術決策摘要

### Phase A — SettingsService Bug 修復

| 項目 | 決策 |
|------|------|
| 技術 | 純 PHP / WordPress Core |
| 新增套件 | 無 |
| 修改範圍 | `class-settings-service.php` 的 `set()` 和 `get()` 方法 |
| 核心問題 | 確認 array 在快取層和資料庫層的序列化路徑是否一致 |

### Phase B — Tab 重構

| 項目 | 決策 |
|------|------|
| 技術 | WordPress 原生 `add_action()` + PHP include |
| 新增套件 | 無 |
| 修改範圍 | 拆分 `class-settings-page.php` 到 `tabs/` 子目錄 |
| 核心原則 | View（HTML）已分離，只需分離 Controller 邏輯 |

### Phase C — REST API + API Key

| 項目 | 決策 |
|------|------|
| 技術 | WordPress REST API + Transient API + PHP `password_hash()` |
| 新增套件 | 無 |
| 修改範圍 | `class-public-api.php` 加入 rate limiting + 升級 hash |
| 注意事項 | hash 升級需遷移舊 API Key（舊 key 作廢，要求重新生成）|

---

## 完整技術棧（v2.0）

### 已存在，無需變更

| 技術 | 版本 | 用途 |
|------|------|------|
| PHP | 8.0+ | 後端語言 |
| WordPress | 6.x | 外掛平台 |
| WordPress Transient API | Core | 設定快取、Rate Limiting |
| WordPress REST API | Core | API 端點 |
| openssl_encrypt (AES-256-CBC) | PHP 原生 | 設定加密 |
| wp_hash() | WordPress Core | 目前 API Key hash（待升級）|
| wp_verify_nonce() | WordPress Core | 表單安全驗證 |

### v2.0 引入（PHP 原生，無外部套件）

| 技術 | 用途 | 改動範圍 |
|------|------|----------|
| `password_hash()` / `password_verify()` | API Key hash 升級 | `class-settings-page.php` + `class-public-api.php` |
| Transient + 計數器模式 | REST API Rate Limiting | `class-public-api.php` |
| Tab Controller 拆分（PHP class） | 熵減 | 新增 `includes/admin/tabs/` |

---

## 明確排除清單（What NOT to Add）

| 套件/方案 | 排除原因 |
|----------|----------|
| WordPress Settings API | 限制 UI 自由度，不適合複雜 Tab 頁 |
| CMB2 / ACF | 外部依賴，超出需求 |
| JWT 認證 | 複雜度過高，API Key 已足夠 |
| OAuth 2.0（伺服器端） | 完全超出範圍，針對不同用例 |
| Redis（直接使用） | InstaWP 主機環境不保證，Transient 自動使用 Object Cache 已足夠 |
| Composer 新套件 | 任何外部套件都違反最小依賴原則 |
| React/Vue 後台重寫 | 超出 v2.0 範圍 |

---

## 信心評估

| 面向 | 信心度 | 依據 |
|------|--------|------|
| Phase A Bug 根因 | MEDIUM | 程式碼分析，需實際環境驗證 |
| Phase B 拆檔策略 | HIGH | 直接讀取現有程式碼，策略清晰 |
| Phase C Rate Limiting | HIGH | WordPress Transient 模式已有文件和社群實作 |
| Phase C API Key hash 升級 | HIGH | WordPress 6.8 官方文件確認 bcrypt 支援 |
| 不引入外部套件 | HIGH | 所有需求 WordPress Core 已覆蓋 |

---

## 來源

- WordPress REST API 認證官方文件：https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- WordPress 6.8 bcrypt 密碼雜湊公告：https://make.wordpress.org/core/2025/02/17/wordpress-6-8-will-use-bcrypt-for-password-hashing/
- WordPress Nonce 文件：https://developer.wordpress.org/apis/security/nonces/
- WordPress Transient 限流實作：https://wp-tutorials.tech/optimise-wordpress/rate-limit-wordpress-api-calls/
- WordPress REST API 限流參考：https://www.sitebox.io/how-to-build-an-api-rate-limiter-in-wordpress/
- 直接程式碼分析：`/Users/fishtv/Development/line-hub/includes/services/class-settings-service.php`
- 直接程式碼分析：`/Users/fishtv/Development/line-hub/includes/admin/class-settings-page.php`
- 直接程式碼分析：`/Users/fishtv/Development/line-hub/includes/api/class-public-api.php`
