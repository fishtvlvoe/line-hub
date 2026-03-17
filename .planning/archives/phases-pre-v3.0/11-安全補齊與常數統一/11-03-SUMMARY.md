---
phase: 11-安全補齊與常數統一
plan: 03
status: complete
started: 2026-02-24
completed: 2026-02-24
---

# 11-03 Summary: LINE API URL 統一常數類別

## 完成內容

### Task 1: 建立 LineApiEndpoints 常數類別
- 建立 `includes/class-line-api-endpoints.php`
- 包含 6 個 LINE API URL 常數：OAUTH_TOKEN, OAUTH_VERIFY, OAUTH_ACCESS_TOKEN, PROFILE, BOT_MESSAGE, BOT_INFO
- Commit: `f71fac0`

### Task 2: 替換 4 個檔案中所有硬編碼 URL
- `class-oauth-client.php`：移除 3 個 private const，加入 use 語句，3 處引用
- `class-liff-handler.php`：移除 2 個 private const，加入 use 語句，2 處引用
- `class-messaging-service.php`：移除 1 個 private const + 1 處硬編碼 URL，加入 use 語句，2 處引用
- `class-settings-page.php`：替換 1 處硬編碼 URL，加入 use 語句，1 處引用
- Commit: `2e87051`

## 驗證結果

- PHP 語法檢查：5 個檔案全部通過
- `grep -r 'https://api.line.me' includes/`：只有 class-line-api-endpoints.php 有定義（6 處），其餘 0 處
- `grep -c 'LineApiEndpoints::' includes/`：auth(3) + liff(2) + messaging(2) + settings(1) = 8 處引用

## 決策記錄

- 使用 `use LineHub\LineApiEndpoints;` 語句保持程式碼可讀性（而非完整命名空間路徑）
- 常數命名移除 `_ENDPOINT` 後綴（類名 Endpoints 已表達語義）
- OAuthClient 中的 AUTH_ENDPOINT（access.line.me）保留為 private const，因為這是授權端點而非 API 端點
