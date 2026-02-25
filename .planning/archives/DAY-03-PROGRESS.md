# LINE Hub - Day 3 進度報告

**日期**: 2026-02-07
**階段**: Day 3 - OAuth 認證服務實作
**狀態**: 🚧 進行中

---

## 📋 計畫任務

根據 LINE-HUB-ROADMAP.md Week 2 Day 6-7 的規劃，Day 3 聚焦於 OAuth 認證核心：

### 上午任務 (09:00-12:00)
- [ ] 分析 LINE OAuth 2.0 認證流程
- [ ] 建立 AuthService 類別
  - [ ] generateAuthUrl() - 產生授權 URL
  - [ ] handleCallback() - 處理授權回呼
  - [ ] exchangeToken() - 交換 Access Token
  - [ ] verifyIdToken() - 驗證 ID Token
  - [ ] getProfile() - 取得用戶資料
- [ ] 實作 State Token CSRF 防護
- [ ] Token 儲存機制（Transient）

### 下午任務 (14:00-17:00)
- [ ] 建立 UserService 類別
  - [ ] createOrUpdateUser() - 建立/更新 LINE 用戶
  - [ ] getUserByLineUid() - 查詢用戶
  - [ ] bindLineAccount() - 綁定帳號
  - [ ] unbindLineAccount() - 解除綁定
- [ ] 建立 Login_API 類別
  - [ ] GET /line-hub/v1/login/authorize - 取得授權 URL
  - [ ] GET /line-hub/v1/login/callback - OAuth 回呼
- [ ] 建立測試腳本
- [ ] 整合測試

---

## 🎯 目標

1. **完整的 OAuth 2.0 流程**
   - 符合 LINE Login v2.1 規範
   - PKCE（Proof Key for Code Exchange）支援
   - State Token CSRF 防護

2. **安全的 Token 管理**
   - Access Token 安全儲存
   - Token 過期檢查
   - Refresh Token 機制（選配）

3. **用戶資料管理**
   - LINE UID ↔ WordPress User ID 綁定
   - Display Name 和頭像同步
   - Email 擷取（如果可用）

---

## ✅ 實際完成

### 1. 建立 Day 3 進度報告 ✅
- 📄 DAY-03-PROGRESS.md 已建立
- 📝 任務清單已規劃

---

## 🔍 技術重點

### LINE OAuth 2.0 流程

```
1. 產生授權 URL
   └─ https://access.line.me/oauth2/v2.1/authorize
      ├─ response_type=code
      ├─ client_id={channel_id}
      ├─ redirect_uri={callback_url}
      ├─ state={csrf_token}
      ├─ scope=profile openid email
      └─ nonce={random_string}

2. 用戶授權
   └─ LINE 顯示授權頁面
   └─ 用戶點擊「同意」

3. 授權回呼
   └─ GET {redirect_uri}?code={auth_code}&state={csrf_token}
   └─ 驗證 state token

4. 交換 Access Token
   └─ POST https://api.line.me/oauth2/v2.1/token
      ├─ grant_type=authorization_code
      ├─ code={auth_code}
      ├─ redirect_uri={callback_url}
      ├─ client_id={channel_id}
      └─ client_secret={channel_secret}
   └─ 回應: {access_token, id_token, expires_in}

5. 驗證 ID Token
   └─ 解析 JWT
   └─ 驗證簽名
   └─ 取得 sub (LINE UID)

6. 取得用戶資料
   └─ GET https://api.line.me/v2/profile
      └─ Authorization: Bearer {access_token}
   └─ 回應: {userId, displayName, pictureUrl, statusMessage}
```

### 資料表設計

```sql
-- LINE 用戶表（已在 Day 1 定義）
CREATE TABLE wp_line_hub_users (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    line_uid varchar(255) NOT NULL,
    display_name varchar(255),
    picture_url varchar(500),
    email varchar(255),
    status_message text,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY line_uid (line_uid),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### State Token 防護

```php
// 產生 state token
$state = bin2hex(random_bytes(16));
set_transient('line_hub_oauth_state_' . $state, [
    'created_at' => time(),
    'redirect_to' => $redirect_url,
], 600); // 10 分鐘過期

// 驗證 state token
$saved_state = get_transient('line_hub_oauth_state_' . $state);
if (!$saved_state) {
    throw new Exception('Invalid or expired state token');
}
delete_transient('line_hub_oauth_state_' . $state);
```

---

## 📊 進度統計

| 任務 | 狀態 | 完成度 |
|------|------|--------|
| Day 3 進度報告 | ✅ | 100% |
| AuthService 類別 | ⏸️ | 0% |
| UserService 類別 | ⏸️ | 0% |
| Login API 端點 | ⏸️ | 0% |
| 測試腳本 | ⏸️ | 0% |
| 整合測試 | ⏸️ | 0% |

**總體進度**: 5%

---

## 🚀 下一步

1. 實作 AuthService 類別
2. 實作 UserService 類別
3. 建立 Login_API REST 端點
4. 建立測試腳本驗證功能

---

**報告建立時間**: 2026-02-07 00:54
**最後更新**: 2026-02-07 00:54
**預計完成**: 2026-02-07 17:00
