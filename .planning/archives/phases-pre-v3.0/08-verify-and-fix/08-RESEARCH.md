# Phase 8 Research: 驗證與修復

**Phase:** 8 — 驗證與修復
**Researched:** 2026-02-24
**Requirements:** VERIFY-01, VERIFY-02, VERIFY-03, VERIFY-04, VERIFY-05
**Confidence:** HIGH（全部基於直接原始碼分析）

---

## 研究目標

回答：「要做好 Phase 8 的計畫，需要知道什麼？」

Phase 8 的核心目標是確認 v1.0 已實作功能真正可用，並修復三個已知安全與正確性問題。以下針對每個 VERIFY 需求進行原始碼層級的深度分析。

---

## VERIFY-01: SettingsService array 序列化

### 問題描述

管理員在登入設定勾選按鈕位置（`login_button_positions`）後儲存，重新載入頁面後勾選狀態是否保留？

### 涉及檔案

| 檔案 | 路徑 | 角色 |
|------|------|------|
| SettingsService | `includes/services/class-settings-service.php` | 核心：儲存和讀取設定值 |
| SettingsPage | `includes/admin/class-settings-page.php` | 處理表單提交 |
| tab-login.php | `includes/admin/views/tab-login.php` | 渲染 checkbox 表單 |
| ButtonPositions | `includes/integration/class-button-positions.php` | 讀取設定並掛載按鈕 |

### 原始碼分析

**1. Schema 定義（正確）**

`SettingsService::$schema` 中 `login_button_positions` 定義為 `'type' => 'array'`（第 88-94 行），`default_roles` 也是 `'type' => 'array'`（第 139-145 行）。Schema 正確。

**2. 儲存流程（已有 JSON 序列化）**

`SettingsService::set()` 方法（第 381-424 行）：

```php
// 第 395 行：cast_value() 會先驗證是 array
$value = self::cast_value($value, $config['type']);

// 第 398-400 行：array 類型序列化為 JSON
if ($config['type'] === 'array') {
    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
}
```

JSON 序列化邏輯已存在。`cast_value()` 對 array 類型的處理（第 574-579 行）：如果輸入已是 array 就原樣返回，如果是 string 會嘗試 `json_decode`。

**3. 讀取流程（有 JSON 反序列化）**

`SettingsService::get()` 方法在第 344 行呼叫 `cast_value()`，對 array 類型的字串會做 `json_decode($value, true)`。

**4. 表單提交處理（正確）**

`SettingsPage::save_login_tab()` 方法（第 288-330 行）：

```php
// 第 311-314 行：正確處理 checkbox array
$positions = isset($_POST['login_button_positions']) && is_array($_POST['login_button_positions'])
    ? array_map('sanitize_text_field', $_POST['login_button_positions'])
    : [];
SettingsService::set('general', 'login_button_positions', $positions);
```

傳入 `set()` 的是正確的 PHP array。

**5. 表單渲染（正確）**

`tab-login.php` 第 230-244 行讀取 `$settings_general['login_button_positions']`，用 `in_array()` 檢查是否勾選。

### 潛在問題分析

程式碼層面的序列化/反序列化邏輯看起來完整。但存在一個**潛在陷阱**：

- `set()` 成功後會呼叫 `clear_cache($group, $key)`（第 419 行）
- 但 `get()` 在快取命中時直接返回（第 317-320 行）：`if ($cached !== false) { return $cached; }`
- 如果舊的快取值是 `false`（因為 `get_transient()` 對不存在的 key 也返回 `false`），邏輯是正確的
- 但如果舊快取存的是錯誤格式的值（例如字串 `"Array"`），快取 TTL 1 小時內不會被更正

**關鍵判斷：需要實際環境測試。**

如果在 test.buygo.me 環境中勾選按鈕位置、儲存、重載後值保留，則 VERIFY-01 通過，不需修改任何程式碼。如果值丟失，需要檢查 transient 中儲存的值格式。

### 驗證方法

```bash
# 1. 在後台 LINE Hub > 登入 Tab，勾選所有按鈕位置並儲存
# 2. 重新載入頁面，確認勾選狀態保留
# 3. 用 WP-CLI 或 phpMyAdmin 檢查資料庫值：
wp eval "var_dump(\LineHub\Services\SettingsService::get('general', 'login_button_positions'));"
# 預期輸出：array(2) { [0]=> string(8) "wp_login" [1]=> string(19) "fluentcart_checkout" }

# 4. 檢查 transient：
wp eval "var_dump(get_transient('line_hub_setting_general_login_button_positions'));"
```

### 風險等級：MEDIUM

程式碼邏輯看起來正確，但需要環境驗證。如果有 bug，最可能是快取殘留問題而非序列化邏輯錯誤。

---

## VERIFY-02: Transient 快取清除

### 問題描述

清除 Transient 快取後，新儲存的設定值是否立即反映？是否存在 1 小時快取過期才更新的問題？

### 涉及檔案

| 檔案 | 路徑 | 關鍵行數 |
|------|------|---------|
| SettingsService | `includes/services/class-settings-service.php` | 第 288, 316-320, 347-348, 417-420, 594-605 行 |

### 原始碼分析

**1. 快取 TTL**

```php
private static $cache_ttl = HOUR_IN_SECONDS;  // 第 288 行：1 小時
```

**2. 快取讀取**

```php
// get() 方法第 315-320 行
$cache_key = "line_hub_setting_{$group}_{$key}";
$cached = get_transient($cache_key);
if ($cached !== false) {
    return $cached;   // 快取命中就直接返回
}
```

**3. 快取寫入**

```php
// get() 方法第 347-348 行（從 DB 讀取後儲存到快取）
set_transient($cache_key, $value, self::$cache_ttl);
```

**4. 快取清除（在 set() 成功後）**

```php
// set() 方法第 417-420 行
if ($result) {
    self::clear_cache($group, $key);   // 成功寫入 DB 後立即清除快取
    return true;
}
```

**5. clear_cache() 實作**

```php
// 第 594-605 行
public static function clear_cache($group, $key = null) {
    if ($key) {
        delete_transient("line_hub_setting_{$group}_{$key}");
    } else {
        // 清除整個群組
        foreach (self::$schema[$group] as $k => $config) {
            delete_transient("line_hub_setting_{$group}_{$k}");
        }
    }
}
```

### 分析結論

**快取機制運作正常：**

1. `set()` 成功寫入 DB 後，立即呼叫 `clear_cache()` 刪除對應的 transient
2. 下次 `get()` 時因 transient 已刪除，會重新從 DB 讀取並建立新快取
3. 不需要等 1 小時快取過期

**但有一個邊際風險：**

如果 `$wpdb->replace()` 返回 `0`（表示資料相同沒有更新，但不算失敗），`$result` 的值是 `0`，而 `if ($result)` 會評估為 `false`，導致 `clear_cache()` 不被呼叫。不過 `$wpdb->replace()` 成功時返回影響行數（1 或 2），只有失敗才返回 `false`。相同資料的情況下返回 1（replace = delete + insert），所以這個邊際風險在 MySQL replace 語義下不成立。

### 驗證方法

```bash
# 1. 修改一個設定值並儲存
# 2. 立即重新載入頁面，確認新值立即顯示
# 3. 用 WP-CLI 確認 transient 已被清除：
wp transient get line_hub_setting_general_login_button_positions
# 預期：空或新值（不是舊值）
```

### 額外建議：提供手動清除機制

雖然快取清除邏輯正確，但作為防禦措施，Phase 8 應加入一個「清除所有 LineHub 快取」的管理按鈕或 WP-CLI 指令，讓管理員在異常情況下可以手動清除。

### 風險等級：LOW

程式碼邏輯正確，`set()` 後必定清除快取。只需在實際環境驗證行為符合預期。

---

## VERIFY-03: Hook 和 REST API 端點驗證

### 問題描述

5 個 Hook 和 REST API 端點是否端到端可用？

### 涉及檔案

| 檔案 | 路徑 | 功能 |
|------|------|------|
| IntegrationHooks | `includes/services/class-integration-hooks.php` | 5 個 Hook 的處理器 |
| PublicAPI | `includes/api/class-public-api.php` | 5 個 REST API 端點 |
| MessagingService | `includes/messaging/class-messaging-service.php` | LINE API 呼叫 |
| Plugin | `includes/class-plugin.php` | 初始化各服務 |

### Hook 分析（5 個）

**已註冊的 Hook（`IntegrationHooks::init()` 第 37-45 行）：**

| Hook 名稱 | 類型 | Handler |
|-----------|------|---------|
| `line_hub/send/text` | Action | `handle_send_text()` |
| `line_hub/send/flex` | Action | `handle_send_flex()` |
| `line_hub/send/broadcast` | Action | `handle_broadcast()` |
| `line_hub/user/is_linked` | Filter | `filter_is_linked()` |
| `line_hub/user/get_line_uid` | Filter | `filter_get_line_uid()` |

**初始化確認：** `Plugin::init_services()` 第 126 行呼叫 `Services\IntegrationHooks::init()`。

**訊息發送流程：**

1. Hook handler 建立 `new MessagingService()`
2. `MessagingService::__construct()` 從 `SettingsService::get('general', 'access_token')` 取得 Channel Access Token
3. `pushText()` → `pushMessage()` → `sendRequest('push', ...)` → `wp_remote_post()` 呼叫 LINE API

**潛在問題：**

- `handle_send_text()` 的 `pushText()` 需要 WordPress User ID（不是 LINE UID），內部再透過 `UserService::getLineUid()` 查找。這是正確的設計。
- `handle_broadcast()` 使用 `sendToMultiple()`（逐一推播），不是 `multicast()`（LINE multicast API）。這意味著 10 個用戶 = 10 次 LINE API 呼叫。效能上不理想，但功能正確。

### REST API 分析（5 個端點）

**已註冊的端點（`PublicAPI::register_routes()` 第 35-68 行）：**

| 端點 | 方法 | Handler | 認證 |
|------|------|---------|------|
| `/line-hub/v1/messages/text` | POST | `send_text()` | API Key |
| `/line-hub/v1/messages/flex` | POST | `send_flex()` | API Key |
| `/line-hub/v1/messages/broadcast` | POST | `send_broadcast()` | API Key |
| `/line-hub/v1/users/{id}/binding` | GET | `get_user_binding()` | API Key |
| `/line-hub/v1/users/lookup` | GET | `lookup_user()` | API Key |

**初始化確認：** `Plugin::init_services()` 第 129 行呼叫 `API\PublicAPI::init()`，在 `rest_api_init` hook 時註冊路由。

**重要發現：REST API 的 send_text() 回應格式有 bug**

```php
// class-public-api.php 第 133-136 行
return new \WP_REST_Response([
    'success' => (bool) $result,   // $result 可能是 WP_Error 物件
    'message' => $result ? '訊息已發送' : '發送失敗',
]);
```

`$result` 是 `pushText()` 的返回值。`pushText()` 返回 `array|\WP_Error`：
- 成功時返回 LINE API 的回應陣列（空陣列 `[]`）
- 失敗時返回 `WP_Error` 物件

問題：`(bool) $result` 對空陣列 `[]` 會是 `false`，導致成功發送後回傳 `{'success': false, 'message': '發送失敗'}`。

**修復建議：** 應改為 `!is_wp_error($result)` 檢查。

同樣的問題存在於 `send_flex()`（第 174-177 行）和 `send_broadcast()`（第 217-221 行）。

### 驗證方法

```bash
# Hook 驗證（需在 WordPress 環境執行）：
wp eval "do_action('line_hub/send/text', ['user_id' => 1, 'message' => 'Phase 8 驗證測試']);"
# 檢查 debug.log 是否有 [LineHub] handle_send_text 記錄

# REST API 驗證：
curl -X POST https://test.buygo.me/wp-json/line-hub/v1/messages/text \
  -H "X-LineHub-API-Key: lhk_xxxxx" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "message": "Phase 8 API 測試"}'

# Filter 驗證：
wp eval "var_dump(apply_filters('line_hub/user/is_linked', false, 1));"
wp eval "var_dump(apply_filters('line_hub/user/get_line_uid', '', 1));"
```

### 風險等級：MEDIUM

Hook 和 REST API 已完整實作，但 `send_text()` 的回應格式有 bug（空陣列被判為 false）。需要修復。

---

## VERIFY-04: API Key hash_equals 認證

### 問題描述

API Key 認證是否有 Timing Attack 漏洞？改用 `hash_equals()` 後是否向後相容？

### 涉及檔案

| 檔案 | 路徑 | 關鍵行數 |
|------|------|---------|
| PublicAPI | `includes/api/class-public-api.php` | 第 77-102 行 |
| SettingsPage | `includes/admin/class-settings-page.php` | 第 335-361 行（生成 API Key） |

### 原始碼分析

**目前的認證邏輯（第 92-93 行）：**

```php
$stored_hash = SettingsService::get('integration', 'api_key_hash', '');
if (empty($stored_hash) || wp_hash($key) !== $stored_hash) {
```

**問題：** 使用 `!==` 進行字串比較，存在 Timing Attack 風險。攻擊者可以透過測量回應時間，逐字元猜出 hash 值。

**API Key 生成邏輯（第 345-349 行）：**

```php
$raw_key = 'lhk_' . bin2hex(random_bytes(16));  // 36 字元的隨機 key
$prefix = substr($raw_key, 0, 8);
SettingsService::set('integration', 'api_key_hash', wp_hash($raw_key));
```

### 修復方案

**修改一行程式碼：**

```php
// 修改前：
if (empty($stored_hash) || wp_hash($key) !== $stored_hash) {

// 修改後：
if (empty($stored_hash) || !hash_equals($stored_hash, wp_hash($key))) {
```

**向後相容性分析：**

1. `hash_equals()` 只是比較方式改變（恆定時間 vs 短路比較），不改變 hash 演算法
2. `wp_hash($key)` 的輸出不變——相同的 key 還是產生相同的 hash
3. 已生成的 API Key 不受影響，不需要重新生成
4. PHP 5.6.0+ 原生支持 `hash_equals()`，LINE Hub 要求 PHP 8.0+，完全相容

### 風險等級：LOW

一行修改，零風險。向後完全相容。

---

## VERIFY-05: broadcast 上限

### 問題描述

發送超過 100 個 user_id 的 broadcast 請求時，是否有數量限制？

### 涉及檔案

| 檔案 | 路徑 | 角色 |
|------|------|------|
| PublicAPI | `includes/api/class-public-api.php` | REST API broadcast 端點 |
| IntegrationHooks | `includes/services/class-integration-hooks.php` | Hook broadcast 處理器 |
| MessagingService | `includes/messaging/class-messaging-service.php` | 發送邏輯 |

### 原始碼分析

**REST API broadcast（`PublicAPI::send_broadcast()`，第 186-222 行）：**

```php
$user_ids = $params['user_ids'] ?? [];
// ... 驗證非空和陣列
$user_ids = array_filter(array_map('intval', $user_ids), fn($id) => $id > 0);
// ... 直接呼叫 sendToMultiple()，無數量限制
$messaging->sendToMultiple(array_values($user_ids), $messages);
```

**沒有任何數量限制。**

**Hook broadcast（`IntegrationHooks::handle_broadcast()`，第 113-130 行）：**

同樣沒有數量限制，直接呼叫 `sendToMultiple()`。

**MessagingService::sendToMultiple()（第 331-356 行）：**

逐一對每個 user_id 呼叫 `pushMessage()`，沒有限制。

**MessagingService::multicast()（第 203-259 行）：**

LINE multicast API 本身有 500 人限制（第 221-226 行有檢查），但 `send_broadcast` 端點用的是 `sendToMultiple()` 而非 `multicast()`。

### 安全風險

如果 API Key 被洩漏，攻擊者可以：
1. 傳入 10,000 個 user_id
2. 系統逐一呼叫 LINE API 10,000 次
3. 耗盡 LINE API 的 Rate Limit（每分鐘 1,000 則）
4. 對所有綁定用戶發送垃圾訊息

### 修復方案

在 **REST API 端點** 和 **Hook 處理器** 兩處都加入限制：

**PublicAPI::send_broadcast()：**
```php
// 在 array_filter 之後、sendToMultiple 之前加入
if (count($user_ids) > 100) {
    return new \WP_REST_Response(
        ['success' => false, 'message' => 'user_ids 數量超過上限（最多 100 個）'],
        400
    );
}
```

**IntegrationHooks::handle_broadcast()：**
```php
// 在 array_filter 之後加入
if (count($user_ids) > 100) {
    error_log("[LineHub] handle_broadcast: REJECTED — user_ids count " . count($user_ids) . " exceeds limit 100");
    return;
}
```

### 風險等級：HIGH

API Key 洩漏時無任何防護，可造成大量垃圾訊息。修復成本極低（各加 5 行）。

---

## 額外發現

### 發現 1：REST API send_text/flex/broadcast 回應格式 Bug

如 VERIFY-03 中分析，`(bool) $result` 對空陣列 `[]` 會是 `false`。LINE API 成功回應是 `{}`（JSON 空物件），解碼後是 `[]`（PHP 空陣列），`(bool) []` = `false`。

**影響：** API 呼叫實際成功但回傳 `{'success': false}`，讓呼叫端誤以為失敗。

**修復：** 所有三個端點的回應判斷改為 `!is_wp_error($result)`。

### 發現 2：UsersColumn N+1 查詢效能問題

`UsersColumn::table_exists()` 沒有靜態快取，每個用戶行渲染會觸發 3 次 `SHOW TABLES LIKE` 查詢。50 個用戶 = 150 次不必要的資料庫查詢。

**修復：** 在 `table_exists()` 加入靜態陣列快取。

雖然這不在 VERIFY-01~05 的範圍內，但建議一併修復（成本極低，5 行程式碼）。

### 發現 3：快取的 false 值歧義

`SettingsService::get()` 使用 `get_transient()` 快取設定值。WordPress transient 對「不存在」返回 `false`。如果某個設定的實際值是 `false`（boolean 類型），快取命中和快取不存在無法區分。

具體影響：boolean 類型的設定（如 `force_reauth`），其值為 `false` 時，每次讀取都會穿透快取去查 DB。這不是 bug 而是效能問題，但在大量讀取場景下可能有影響。

**建議：** 快取時包裝為陣列 `['v' => $value]`，讀取時解包。但這是 v2.x 優化項，不在 Phase 8 範圍。

---

## 工作量評估

| VERIFY | 類型 | 預估工作量 | 說明 |
|--------|------|-----------|------|
| VERIFY-01 | 驗證 + 可能修復 | 30 分鐘 | 實際環境測試，若正常則 0 修改 |
| VERIFY-02 | 驗證 | 15 分鐘 | 程式碼邏輯正確，只需環境確認 |
| VERIFY-03 | 驗證 + 修復 | 45 分鐘 | 端到端測試 + 修復回應格式 bug |
| VERIFY-04 | 修復 | 5 分鐘 | 一行程式碼修改 |
| VERIFY-05 | 修復 | 15 分鐘 | 兩處加入數量限制 |
| 額外：N+1 修復 | 修復 | 10 分鐘 | table_exists 靜態快取 |

**總計：約 2 小時**

---

## 建議的 Plan 拆分

### Plan 08-01: 驗證腳本執行

**目標：** 確認所有已實作功能的現況
**前置條件：** test.buygo.me 環境可連線、有管理員帳號

**步驟：**
1. 登入 test.buygo.me 後台
2. VERIFY-01：在登入 Tab 勾選按鈕位置 → 儲存 → 重載 → 確認值保留
3. VERIFY-01：用 WP-CLI 或 DB 直查 `line_hub_settings` 表確認 JSON 格式
4. VERIFY-02：修改設定值 → 立即重載 → 確認新值反映（不需等 1 小時）
5. VERIFY-03：用 curl 測試 5 個 REST API 端點（需先產生 API Key）
6. VERIFY-03：用 WP-CLI 測試 5 個 Hook
7. 記錄所有驗證結果（通過/失敗/意外行為）

**產出：** 驗證報告，列出哪些需要修復

### Plan 08-02: 安全修復

**目標：** 修復驗證中發現的問題 + 3 個已知安全問題
**依賴：** Plan 08-01 完成

**已知必修項目：**
1. VERIFY-04：`PublicAPI::authenticate()` 改用 `hash_equals()`（1 行）
2. VERIFY-05：`PublicAPI::send_broadcast()` 和 `IntegrationHooks::handle_broadcast()` 加入 100 人上限（各 5 行）
3. REST API 回應格式修復：`(bool) $result` 改為 `!is_wp_error($result)`（3 處）
4. `UsersColumn::table_exists()` 加靜態快取（5 行）

**可能追加項目（依 08-01 結果）：**
5. VERIFY-01：如果 array 序列化有問題，修復 `SettingsService::set()` 或 `get()`
6. VERIFY-02：如果快取清除有問題，修復 `clear_cache()` 邏輯

**驗證步驟：**
- 修復後重新執行 08-01 的所有測試
- 確認舊 API Key 仍可驗證（向後相容）
- 確認超過 100 user_ids 的 broadcast 返回 HTTP 400

---

## 環境準備清單

Phase 8 執行前需確認：

- [ ] test.buygo.me 可正常連線
- [ ] WordPress 管理員帳號可登入
- [ ] LINE Hub 外掛已啟用
- [ ] LINE Channel Access Token 已設定（用於 REST API 測試）
- [ ] 至少一個用戶已綁定 LINE（用於 send_text 測試）
- [ ] WP-CLI 可用（用於 Hook 測試和 DB 查詢）
- [ ] debug.log 可查看（用於確認 Hook handler 記錄）

---

*Research completed: 2026-02-24*
*Ready for planning: yes*
