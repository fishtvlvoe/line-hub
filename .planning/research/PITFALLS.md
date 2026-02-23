# Pitfalls Research

**Domain:** WordPress LINE 整合外掛 — v2.0 重構與擴展（Phase A/B/C）
**Researched:** 2026-02-24
**Confidence:** HIGH（直接從現有程式碼分析，非推測）

---

## Critical Pitfalls

### Pitfall 1: SettingsService array 修復後的快取殘留問題

**Severity:** HIGH
**Phase:** A（A1 修復後立即影響）

**What goes wrong:**
修復 `set()` 方法後，資料庫的值已正確存為 JSON，但 transient 快取仍然存著修復前的錯誤值（`"Array"` 字串轉為空陣列 `[]`）。用戶儲存設定後刷新頁面，看到的仍然是空陣列，誤以為修復沒有生效，反覆重試。

**Why it happens:**
`get()` 方法先查 transient（`line_hub_setting_general_login_button_positions`），找到快取就直接返回，不再讀資料庫。修復前存入的舊快取 TTL 是 1 小時，在 TTL 內都不會更新。

**How to avoid:**
A1 修復 `set()` 同時，確認 `clear_cache()` 在每次 `set()` 成功後都被呼叫（現有程式碼 line 418-420 已有，但要驗證）。部署修復後，手動執行一次全群組快取清除，或直接在 WordPress 後台「工具 > 刪除快取」。

具體 checklist：
- [ ] A1 修復部署後，到後台刪除所有 transient（或等 1 小時）
- [ ] 確認 `clear_cache()` 在 `set()` 成功時一定被呼叫（line 418 `if ($result)` 內）
- [ ] 測試流程：儲存 → 刷新 → 確認值保留，不依賴快取 grace period

**Warning signs:**
- 儲存設定後值立即消失
- DB 裡的值正確但頁面顯示不對
- 在 wp_options 找不到 `_transient_line_hub_setting_*` 表示快取已被清除（好事）

**Phase to address:** Phase A（A1 完成時同步驗證）

---

### Pitfall 2: UsersColumn 每行觸發 N 次 `table_exists()` 查詢

**Severity:** HIGH
**Phase:** A（A2 UsersColumn 實作）

**What goes wrong:**
`render_column()` 每渲染一個用戶行，都呼叫 `get_binding_status()`，裡面對三張表各呼叫一次 `table_exists()`（`SHOW TABLES LIKE %s`）。用戶列表有 50 人，就執行 150 次 `SHOW TABLES`，另加 50-150 次 SELECT 查詢。頁面載入時間從不到 1 秒變成 5+ 秒。

**Why it happens:**
`table_exists()` 是靜態方法但沒有靜態快取，每次呼叫都真實查詢資料庫。WordPress 用戶列表預設每頁 20 人，但管理員通常會調到 50-100 人。

**How to avoid:**
兩個方向選其一：

方向一（推薦）：在 `table_exists()` 加靜態快取：
```php
private static array $table_cache = [];
private static function table_exists(string $table_name): bool {
    if (!isset(self::$table_cache[$table_name])) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        self::$table_cache[$table_name] = ($result === $table_name);
    }
    return self::$table_cache[$table_name];
}
```

方向二（更好）：用 `getBindingsBatch()` 一次查所有用戶的綁定狀態，從 N 次查詢降為 1 次。在 `manage_users_query` filter 時預先查詢並緩存，render 時直接取靜態陣列。

具體 checklist：
- [ ] `table_exists()` 加靜態快取（至少）
- [ ] 測試：用戶列表有 50 人時，Query Monitor 顯示的 SQL 查詢數 < 10
- [ ] 若實測超過 5 秒，改用批量查詢方案

**Warning signs:**
- `/wp-admin/users.php` 載入超過 2 秒
- Query Monitor 顯示大量 `SHOW TABLES LIKE` 查詢
- 每增加 20 個用戶，頁面載入時間線性增加

**Phase to address:** Phase A（A2 實作時）

---

### Pitfall 3: Tab 重構後 Nonce 動作名稱不同步

**Severity:** HIGH
**Phase:** B（B3 表單拆分）

**What goes wrong:**
B3 計畫每個 Tab 使用獨立 `<form>`，但目前所有 Tab 共用同一個 nonce action `'line_hub_save_settings'`。拆分後，如果各 Tab 的 form 用不同 action 名稱，但 `handle_save()` 仍用舊 action 名稱驗證，儲存操作將全部因 nonce 驗證失敗而中斷，silently 失敗（因為 `wp_die()` 只在 nonce 完全缺失時才觸發）。

**Why it happens:**
現有程式碼（`class-settings-page.php` line 182）：
```php
if (!isset($_POST['line_hub_nonce']) || !wp_verify_nonce($_POST['line_hub_nonce'], 'line_hub_save_settings')) {
    wp_die(__('安全驗證失敗', 'line-hub'));
}
```
開發者在 view 檔案改 nonce action，忘記同步更新 `handle_save()` 的驗證。

**How to avoid:**
保持統一 nonce action 名稱 `'line_hub_save_settings'`，所有 Tab 的 form 都用：
```php
wp_nonce_field('line_hub_save_settings', 'line_hub_nonce');
```
Tab 隔離靠 `<input type="hidden" name="tab" value="...">` 和 `<input type="hidden" name="section" value="...">` 實現，不靠不同的 nonce action。

具體 checklist：
- [ ] 所有新 view 檔案的 `wp_nonce_field()` 使用相同 action：`'line_hub_save_settings'`
- [ ] B3 完成後，逐一測試每個 Tab 的儲存，不能靠「看起來沒報錯」就算過
- [ ] 確認 `handle_save()` 中 nonce 驗證的 action 名稱沒有被修改

**Warning signs:**
- 儲存後 redirect 回來但設定值沒變
- `?updated=false` 出現在 URL
- 後台沒有報錯訊息（因為 nonce 失敗有 `wp_die`，但空儲存沒有）

**Phase to address:** Phase B（B3 表單拆分時）

---

### Pitfall 4: 大檔案拆分後 View 引用路徑錯誤導致 Fatal Error

**Severity:** HIGH
**Phase:** B（B2 檔案結構拆分）

**What goes wrong:**
現有 `class-settings-page.php` 中已有 view 檔案引用（line 153、162、171）使用 `__DIR__` 相對路徑。B2 拆分後，若 `class-settings-page.php` 的路徑或 views 子目錄的路徑與現有不同，`require __DIR__ . '/views/tab-settings.php'` 會因為路徑不存在而產生 Fatal Error，導致整個後台設定頁白屏。

**Why it happens:**
B2 計畫新建 `line-hub/includes/admin/views/` 目錄（現有的，不是問題），但若計畫中誤將 `class-settings-page.php` 搬到不同目錄（如 `admin/` 根目錄外），`__DIR__` 指向的位置就會錯。現有程式碼已有這個模式，要確保拆分不破壞現有路徑。

**How to avoid:**
- `class-settings-page.php` 保持在 `includes/admin/` 目錄
- View 檔案保持在 `includes/admin/views/` 目錄
- 新增 view 時用完整相對路徑 `__DIR__ . '/views/tab-wizard.php'`
- 拆分前先確認現有 4 個 view 路徑全部正常載入（tab-settings.php、tab-login.php、tab-developer.php 已存在）

具體 checklist：
- [ ] B2 拆分時不移動 `class-settings-page.php` 的位置
- [ ] 每個新 view 檔案加入後，立即刷新後台頁面確認沒有 PHP Fatal Error
- [ ] 新 view 頂部必須有 `if (!defined('ABSPATH')) { exit; }` 防直接存取

**Warning signs:**
- 後台頁面出現白屏或 PHP Parse/Fatal Error
- WordPress debug.log 出現 `require(): Failed opening required`
- Tab 點擊後頁面空白（其他 Tab 正常）

**Phase to address:** Phase B（B2 拆分時每個步驟驗證）

---

### Pitfall 5: 舊 Tab 移除後殘留 `handle_save()` 路由未清理

**Severity:** MEDIUM
**Phase:** A（A3 移除通知 Tab）+ B（Tab 重構）

**What goes wrong:**
A3 從 `TABS` 常數移除 `'notifications'`，但 `handle_save()` 中的 switch-case 若有 `case 'notifications':` 分支，用戶直接 POST 到這個 action 時仍然會進入（雖然 Tab 不存在，但 URL 是公開的）。Phase B Tab 重組後，若把 Tab ID 從 `'settings'` 改名為 `'line-settings'`，舊的 `save_settings_tab()` 方法名稱不變，但 switch 判斷的 key 改變，導致儲存靜默失敗。

**Why it happens:**
前端 Tab 和後端 handler 是兩個獨立的地方，改動其中一個容易忘記同步另一個。

**How to avoid:**
- A3 完成後，搜尋整個 `class-settings-page.php` 確認沒有孤立的 `'notifications'` 字串
- Phase B 改 Tab ID 時，用全域搜尋確認所有引用位置（`TABS` 常數、switch-case、view 的 hidden input、redirect URL）
- Tab ID 命名保持一致：如果 TABS 常數用 `'settings'`，hidden input 的 value、redirect URL 的 query param 都用 `'settings'`

具體 checklist：
- [ ] A3 完成後：`grep -r "notifications" includes/admin/` 確認沒有殘留
- [ ] Phase B Tab ID 變更時：確認 TABS 常數、switch、view hidden input、redirect URL 四個地方同步
- [ ] 每個 Tab 的儲存流程做完整測試（不只看 UI，要看資料庫實際值）

**Phase to address:** Phase A（A3）和 Phase B（B1/B3）

---

### Pitfall 6: API Key 驗證競速條件 — `wp_hash()` vs `hash_equals()`

**Severity:** HIGH
**Phase:** C（REST API 認證）

**What goes wrong:**
現有 `PublicAPI::authenticate()` 使用 `wp_hash($key) !== $stored_hash` 做字串比較。`!==` 是一般字串比較，在某些 PHP 版本和執行環境下可能受到時序攻擊（Timing Attack）。攻擊者透過測量回應時間差可以逐字元猜測 API Key 的 hash 值。

**Why it happens:**
直覺上 `!==` 已經足夠，開發者通常不會想到 hash 比較的時序問題。

**How to avoid:**
用恆定時間比較函數：
```php
// 改為
if (!hash_equals($stored_hash, wp_hash($key))) {
    return new \WP_Error('invalid_api_key', ...);
}
```
`hash_equals()` 是 PHP 5.6+ 原生函數，恆定時間比較，WordPress 不需要額外引入。

具體 checklist：
- [ ] `authenticate()` 方法中的 hash 比較改為 `hash_equals()`
- [ ] 確認空 `$stored_hash` 時先拒絕（現有 `empty($stored_hash)` 檢查已有，保留）
- [ ] 不存明文 Key（現有設計已正確，只存 `wp_hash()` 結果）

**Warning signs:**
- 程式碼審查發現 hash 比較用 `===` 或 `!==`
- API Key 洩漏事件後才發現比較方式有問題

**Phase to address:** Phase C（C3 REST API 認證實作時）

---

### Pitfall 7: broadcast API 無 Rate Limiting，可被當 SPAM 工具使用

**Severity:** HIGH
**Phase:** C（C3 REST API）

**What goes wrong:**
`/line-hub/v1/messages/broadcast` 接受 `user_ids` 陣列，沒有上限驗證。攻擊者（或誤操作）傳入 10,000 個 user_id，LineHub 向 LINE API 發出大量請求，觸發 LINE API Rate Limit（每分鐘上限），導致後續正常通知全部失敗。更嚴重的是，若 API Key 外洩，第三方可以把整個 WordPress 用戶庫的 user_id 全部填入，造成大量 SPAM。

**Why it happens:**
實作訊息 API 時聚焦在「能不能送到」，忽略「每次最多能送多少」。

**How to avoid:**
在 `send_broadcast()` 加入硬性限制：
```php
$max_recipients = 100; // LINE API 建議批次上限
if (count($user_ids) > $max_recipients) {
    return new \WP_REST_Response(
        ['success' => false, 'message' => "每次最多發送 {$max_recipients} 人"],
        400
    );
}
```

同時在 `authenticate()` 或 route 層加 Rate Limiting：
- 每個 API Key 每分鐘最多 60 次請求
- 可用 WordPress transient 實作簡單的滑動視窗計數

具體 checklist：
- [ ] broadcast endpoint 加入 `user_ids` 數量上限（建議 100）
- [ ] Rate Limiting：用 transient `line_hub_rate_{key_prefix}` 計數每分鐘請求數
- [ ] LINE API 本身的限制：每分鐘 1000 則訊息，每月 200 則免費，確認不超量

**Warning signs:**
- API 響應時間突然變長（LINE API 被打爆）
- LINE OA 收到「已達發送上限」錯誤
- 用戶收到非預期的重複通知

**Phase to address:** Phase C（C3 REST API 設計時）

---

### Pitfall 8: IntegrationHooks 的 `do_action` 在 LINE 未設定時靜默失敗

**Severity:** MEDIUM
**Phase:** C（C2 Hook 介面）

**What goes wrong:**
BGO 執行 `do_action('line_hub/send/text', ['user_id' => 123, 'message' => '...'])` 時，若 LineHub 未啟用或 Channel Access Token 未設定，`handle_send_text()` 內部呼叫 `MessagingService->pushText()` 會失敗。但 BGO 側只執行 `do_action`，不知道是否成功，沒有任何回饋。這在正式環境會造成「訂單已建立但 LINE 通知沒收到」的問題，且很難 debug。

**Why it happens:**
`do_action()` 設計上是 fire-and-forget，沒有回傳值。開發者習慣 hook 是輔助功能，失敗可以接受，但 LINE 通知對業務很重要。

**How to avoid:**
三個層次的防護：

1. `handle_send_text()` 已有 `error_log()` 記錄（現有程式碼已實作，保留）
2. 在 `MessagingService::pushText()` 失敗時，確保記錄到 `wp_line_hub_webhook_events` 或專用 log 表
3. 考慮提供一個 filter 讓呼叫方可以選擇性接收結果：
```php
// 選用 filter pattern（不強制要求）
$result = apply_filters('line_hub/send/text/result', null, $user_id, $message);
```

具體 checklist：
- [ ] `handle_send_text()` 的 `error_log` 保留，確認格式：`[LineHub] handle_send_text: FAILED`
- [ ] LINE API 呼叫失敗時確認日誌能被找到（WordPress debug.log 或自訂 log）
- [ ] 文件說明：BGO 使用 hook 時，如何判斷通知是否成功（看 log，不是回傳值）

**Warning signs:**
- 用戶抱怨沒收到 LINE 通知，但後台顯示訂單正常
- `error_log` 中出現 `[LineHub] handle_send_text: FAILED`
- Channel Access Token 過期但沒有告警機制

**Phase to address:** Phase C（C2 Hook 實作時）

---

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| `class-settings-page.php` 保持 handle_save() 在同一個大檔案 | 不用拆 controller 層 | B 完成後檔案仍然 >300 行 | B2 前可接受，B2 完成後必須拆 |
| UsersColumn 每行 SELECT 3 張表 | 程式碼簡單 | 大用戶量頁面超慢 | 用戶數 < 200 時可接受，超過必須用批量查詢 |
| API Key 只存一組（不支援多組） | 實作簡單 | 無法針對不同外掛給予不同權限 | v2.0 可接受，v3.0 需要 multi-key |
| IntegrationHooks 的 broadcast 一次送完 | 程式碼簡單 | 大量用戶時阻塞 PHP 執行 | 用戶數 < 100 可接受，超過需要 queue |
| `error_log()` 代替正式 log 系統 | 零依賴 | 正式環境 debug.log 不一定開啟 | v2.0 可接受，v3.0 需要 Admin log UI |

---

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| WordPress hooks 通訊 | BGO 直接 `new LineHub\Services\MessagingService()` | 只透過 `do_action('line_hub/send/text', ...)` |
| LINE API webhook | 不驗證 `X-Line-Signature` header | 用 Channel Secret 做 HMAC-SHA256 驗證（現有 WebhookReceiver 已實作）|
| SettingsService 加密 | 加密後的值意外被再次加密 | `set()` 流程：先 JSON（array）→ 再 encrypt（encrypted），不可顛倒 |
| WordPress admin-post.php | redirect 前沒有 exit | `wp_redirect()` 後必須 `exit`（現有程式碼正確，維護時勿刪） |
| REST API nonce | 用 `line_hub_admin` nonce 驗證 REST 請求 | REST 請求用 `wp_rest` nonce（`X-WP-Nonce` header）|

---

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| UsersColumn N+1 查詢 | users.php 載入超慢 | `table_exists()` 靜態快取 + 批量查詢 | 用戶列表 > 30 人 |
| SettingsService 每個 key 獨立 transient | 設定頁載入時查詢 20+ transient | get_group() 考慮用單一 transient 存整個群組 | 設定項目 > 10 個時 |
| broadcast 同步送出 | PHP 執行超時（30秒）| 大批量改用 WP Cron 非同步 | user_ids > 50 人 |
| LINE API 無 retry 機制 | 偶發網路問題導致通知永久丟失 | 失敗時加入 transient queue，cron 重試 | 正式環境 LINE API 偶爾超時 |

---

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| API Key 明文出現在 logs | Key 洩漏 | `error_log` 中只記錄 `api_key_prefix`，不記錄完整 Key |
| `authenticate()` 用 `!==` 比較 hash | Timing Attack | 改用 `hash_equals()` |
| broadcast 不限 user_ids 數量 | 被用作 SPAM 工具 | 硬性上限 100，Rate Limiting |
| view 檔案可直接 HTTP 存取 | 暴露後台 HTML | 每個 view 頂部加 `if (!defined('ABSPATH')) { exit; }` |
| API Key 產生後存在 transient 60 秒（`line_hub_new_api_key`） | 高權限用戶可能截取 | Transient 已設為 60 秒，合理；但顯示後立即刪除（現有程式碼已有 `delete_transient`）|
| `manage_options` 是唯一的後台存取控制 | 管理員帳號被盜 = 全部 API 權限 | 短期可接受；長期考慮 2FA 或 IP 白名單 |

---

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| 移除通知 Tab 後沒有說明 | 用戶找不到通知設定，誤以為功能消失 | A3 移除 Tab 時，在「開發者」Tab 加一行說明：通知透過 BGO 的通知模板管理 |
| Tab 切換後設定值消失 | 用戶以為儲存了但實際沒有 | 確保每個 Tab 的 form 都有各自的「儲存」按鈕，不依賴全局儲存 |
| API Key 只顯示一次 | 用戶沒看到就關頁，Key 永遠找不回來 | 現有 transient 60 秒設計合理；加上更醒目的提示（紅色警告框）|
| 連線測試失敗但沒說原因 | 用戶不知道哪個欄位填錯 | 測試失敗時回傳具體原因（Token 無效 vs 網路錯誤）|
| Tab 重構後設定項位置改變 | 舊用戶找不到設定 | B1 設計時繪製舊新 Tab 對照表，考慮在舊位置加導引提示 |

---

## "Looks Done But Isn't" Checklist

- [ ] **A1 array 修復：** 確認 `default_roles` 和 `login_button_positions` 兩個 array 欄位都已驗證，不只測試其中一個
- [ ] **A2 UsersColumn：** 確認在 NSL 已安裝但 LineHub 未綁定的用戶顯示 NSL 來源（不是空），以及只安裝 LineHub 沒有 NSL 的情況也正常
- [ ] **A3 移除通知 Tab：** 確認 `handle_save()` 中的 switch 沒有 `case 'notifications'`，且不會因 URL 直接帶 `?tab=notifications` 而報錯（應 fallback 到 default tab）
- [ ] **B2 拆分後：** `class-settings-page.php` 實際行數 < 300，用 `wc -l` 驗證
- [ ] **B3 表單拆分：** 在「設定」Tab 儲存後，「登入」Tab 的設定值不受影響（手動驗證兩 Tab 分別儲存）
- [ ] **C2 Hook 介面：** 在 BGO 執行 `do_action('line_hub/send/text', ...)` 後，LineHub 側的 `error_log` 有記錄（不只是「看起來沒報錯」）
- [ ] **C3 REST API：** 無 API Key 的請求收到 401（不是 200 或 403）
- [ ] **C5 API Key 管理：** 產生 Key 後刷新頁面，只顯示 prefix + 星號，不顯示完整 Key

---

## Recovery Strategies

| Pitfall | Recovery Cost | Recovery Steps |
|---------|---------------|----------------|
| 快取殘留導致設定看起來沒修復 | LOW | 到 WP 後台「工具 > 刪除 Transient」或用 WP-CLI `wp transient delete --all` |
| 大檔案拆分後頁面白屏 | LOW | `git revert` 到拆分前的 commit，逐步重做 |
| Tab 重構後舊設定值消失 | MEDIUM | 確認 `handle_save()` 對應的 key 名稱沒有改變，重新手動填入設定值 |
| API Key 洩漏 | LOW | 後台「撤銷 API Key」，立即產生新 Key，更新外部系統 |
| broadcast 造成 LINE API Rate Limit | MEDIUM | 等 1 分鐘（LINE API 每分鐘重設），降低 `user_ids` 數量，加入上限 |
| Nonce 驗證失敗所有設定無法儲存 | LOW | 檢查 view 檔案的 `wp_nonce_field()` action 名稱是否與 `handle_save()` 驗證的一致 |

---

## Pitfall-to-Phase Mapping

| Pitfall | 嚴重程度 | Prevention Phase | Verification |
|---------|---------|------------------|--------------|
| SettingsService 快取殘留 | HIGH | Phase A（A1 完成後） | 儲存值 → 清快取 → 刷新 → 值保留 |
| UsersColumn N+1 查詢 | HIGH | Phase A（A2 實作時） | Query Monitor 確認 SQL 數 < 10 |
| Nonce 動作名稱不同步 | HIGH | Phase B（B3 表單拆分） | 每個 Tab 獨立儲存測試 |
| View 引用路徑錯誤 | HIGH | Phase B（B2 拆分時） | 每個新 view 加入後立即刷新頁面 |
| 舊 Tab 殘留路由 | MEDIUM | Phase A（A3）+ Phase B | `grep` 搜尋殘留字串 |
| API Key hash 比較時序 | HIGH | Phase C（C3 認證） | Code review 確認 `hash_equals()` |
| broadcast 無限制 | HIGH | Phase C（C3 API 設計） | 傳入 200 個 user_id 應返回 400 |
| Hook 失敗靜默 | MEDIUM | Phase C（C2 Hook 介面） | 故意讓 LINE API 失敗，確認 error_log 有記錄 |

---

## Cross-Phase Integration Risks（跨 Phase 影響）

這些是三個 Phase 之間會互相影響的特殊風險：

### Risk 1: A1 修復破壞 B3 的 array 儲存邏輯
A1 修復 `set()` 後，B3 拆分表單時要處理 `default_roles`（array）的儲存。若 B3 重新實作儲存邏輯時沒有使用 `SettingsService::set()`（而是直接 `$wpdb->replace()`），就會繞過 A1 修復，讓 bug 重現。
**防範：** B3 的所有儲存操作都必須通過 `SettingsService::set()`，不允許直接 DB 操作。

### Risk 2: B 重構破壞已上線的 LINE 登入和 LIFF 流程
`class-settings-page.php` 目前載入 `SettingsService::get_group('general')` 提供設定值給登入按鈕、LIFF URL 等功能。B2 拆分後，若 `render_login_tab()` 傳入 view 的變數名稱改變，但 view 檔案引用的仍是舊變數名，登入按鈕設定將讀不到值，用戶 LINE 登入按鈕可能消失。
**防範：** B2 拆分時，每個 view 頂部加 PHPDoc 說明可用變數，拆分後立即在前端測試 LINE 登入按鈕是否正常顯示。

### Risk 3: C 的 API Key 設計與 A1 的 SettingsService 修復有依賴
`api_key_hash` 是 `string` 型態（已存在 schema），`handle_generate_api_key()` 用 `SettingsService::set('integration', 'api_key_hash', ...)` 儲存。這條路徑不受 A1 影響（A1 只改 array 類型）。但若 Phase C 新增 `api_keys` 為 array 類型（如多組 Key），必須依賴 A1 已修復的 array 序列化，否則多組 Key 只能存到 `"Array"` 字串。
**防範：** Phase C 設計多組 Key 功能前，確認 A1 已部署並驗證通過。v2.0 只實作單組 Key，不需要 array 型態。

---

## Sources

- 直接程式碼分析：`/Users/fishtv/Development/line-hub/includes/services/class-settings-service.php`（line 381-424 set() 方法）
- 直接程式碼分析：`/Users/fishtv/Development/line-hub/includes/admin/class-users-column.php`（get_binding_status N+1）
- 直接程式碼分析：`/Users/fishtv/Development/line-hub/includes/admin/class-settings-page.php`（nonce 驗證模式）
- 直接程式碼分析：`/Users/fishtv/Development/line-hub/includes/api/class-public-api.php`（authenticate hash 比較）
- 計畫文件：`/Users/fishtv/Development/line-hub/.planning/LINEHUB-RESTRUCTURE-PLAN.md`（已知風險第 1-5 項）
- WordPress 最佳實踐：`hash_equals()` for constant-time comparison（PHP 官方文件，HIGH confidence）
- LINE Messaging API Rate Limits：每分鐘 1000 則（LINE 官方文件，MEDIUM confidence — 需確認當前方案限制）

---
*Pitfalls research for: LineHub WordPress 外掛 v2.0 重構（Phase A/B/C）*
*Researched: 2026-02-24*
