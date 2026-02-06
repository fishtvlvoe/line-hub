# LINE Hub API 端點規範

## 文件概述

**版本**: 1.0
**建立日期**: 2026-02-06
**基礎 URL**: `/wp-json/line-hub/v1`
**認證方式**: WordPress Cookie + Nonce, API Key

---

## API 端點總覽

| 端點 | 方法 | 用途 | 認證 | 優先級 |
|------|------|------|------|--------|
| `/webhook` | POST | 接收 LINE Webhook | HMAC | P0 |
| `/login/authorize` | GET | 取得 OAuth 授權 URL | 無 | P0 |
| `/login/callback` | GET | OAuth 回呼處理 | State Token | P0 |
| `/login/reauth` | POST | 強制重新授權 | Cookie + Nonce | P0 |
| `/binding/status` | GET | 查詢綁定狀態 | Cookie + Nonce | P0 |
| `/binding/link` | POST | 綁定 LINE 帳號 | Cookie + Nonce | P0 |
| `/binding/unlink` | POST | 解除綁定 | Cookie + Nonce | P0 |
| `/settings` | GET | 取得設定 | Cookie + Nonce | P0 |
| `/settings` | POST | 更新設定 | Cookie + Nonce | P0 |
| `/notifications/send` | POST | 發送測試訊息 | Cookie + Nonce | P0 |
| `/notifications/history` | GET | 查詢發送記錄 | Cookie + Nonce | P1 |
| `/webhooks/history` | GET | 查詢 Webhook 記錄 | Cookie + Nonce | P1 |
| `/integrations` | GET | 取得整合列表 | Cookie + Nonce | P1 |
| `/integrations/{key}` | POST | 更新整合設定 | Cookie + Nonce | P1 |

---

## 認證方式

### 1. HMAC 簽名驗證（Webhook）

**用途**: LINE Platform → LINE Hub

```php
// 驗證 LINE Webhook 簽名
function verify_webhook_signature($body, $signature) {
    $channel_secret = get_option('line_hub_channel_secret');
    $hash = hash_hmac('sha256', $body, $channel_secret, true);
    $calculated_signature = base64_encode($hash);

    return hash_equals($calculated_signature, $signature);
}
```

**Headers**:
```
X-Line-Signature: xxxxx...
```

### 2. WordPress Cookie + Nonce（後台 API）

**用途**: 後台 JavaScript → LINE Hub API

```php
// 驗證 Nonce
function verify_api_request() {
    if (!check_ajax_referer('line_hub_api', 'nonce', false)) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied'], 403);
    }
}
```

**Headers**:
```
X-WP-Nonce: xxxxx...
```

### 3. State Token（OAuth）

**用途**: OAuth 回呼驗證

```php
// 產生 State Token
$state = wp_generate_password(32, false);
set_transient('line_hub_state_' . $state, [
    'redirect_to' => $redirect_to,
    'created_at' => time()
], 600); // 10 分鐘過期

// 驗證 State Token
function verify_state_token($state) {
    $data = get_transient('line_hub_state_' . $state);
    if (!$data) {
        return false;
    }

    delete_transient('line_hub_state_' . $state);
    return $data;
}
```

---

## Webhook API

### POST /webhook

接收 LINE Platform 的 Webhook 事件

**Request**:

```
POST /wp-json/line-hub/v1/webhook
Headers:
  X-Line-Signature: xxxxx...
  Content-Type: application/json

Body:
{
  "destination": "Uxxxxxxx",
  "events": [
    {
      "type": "message",
      "message": {
        "type": "text",
        "id": "xxxxxxx",
        "text": "Hello"
      },
      "timestamp": 1626847848000,
      "source": {
        "type": "user",
        "userId": "Uabcd1234..."
      },
      "replyToken": "xxxxx",
      "mode": "active"
    }
  ]
}
```

**Response (Success - 200 OK)**:

```json
{
  "success": true,
  "message": "Webhook received",
  "processed": 1
}
```

**Response (Invalid Signature - 403 Forbidden)**:

```json
{
  "success": false,
  "message": "Invalid signature",
  "code": "invalid_signature"
}
```

**處理流程**:

1. ✅ 驗證 HMAC 簽名
2. ✅ 立即返回 200 OK（< 100ms）
3. ✅ 去重檢查（Transient）
4. ✅ 儲存到 `wp_line_hub_webhooks`
5. ✅ 排程背景任務處理

**錯誤代碼**:

| Code | 說明 |
|------|------|
| `invalid_signature` | HMAC 簽名驗證失敗 |
| `invalid_payload` | JSON 格式錯誤 |
| `duplicate_event` | 重複的事件（已處理） |

---

## Login API（LINE 登入）

### GET /login/authorize

取得 LINE OAuth 授權 URL

**Request**:

```
GET /wp-json/line-hub/v1/login/authorize?redirect_to=/my-account
```

**Query Parameters**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `redirect_to` | string | No | 登入後重導向 URL（預設首頁） |

**Response (200 OK)**:

```json
{
  "success": true,
  "auth_url": "https://access.line.me/oauth2/v2.1/authorize?response_type=code&client_id=xxx&redirect_uri=xxx&state=xxx&scope=profile%20openid%20email",
  "state": "abc123...",
  "expires_at": "2026-02-06T20:30:00+08:00"
}
```

**使用方式**:

```javascript
// 前端 JavaScript
fetch('/wp-json/line-hub/v1/login/authorize?redirect_to=/my-account')
  .then(res => res.json())
  .then(data => {
    // 重導向到 LINE 授權頁面
    window.location.href = data.auth_url;
  });
```

---

### GET /login/callback

OAuth 回呼處理（LINE Platform 回呼）

**Request**:

```
GET /wp-json/line-hub/v1/login/callback?code=xxx&state=abc123...
```

**Query Parameters**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `code` | string | Yes | OAuth Authorization Code |
| `state` | string | Yes | State Token（CSRF 防護） |

**Response (Success - 302 Redirect)**:

重導向到 `redirect_to` URL（登入成功）

**Response (Error - 400 Bad Request)**:

```json
{
  "success": false,
  "message": "Invalid state token",
  "code": "invalid_state"
}
```

**處理流程**:

1. ✅ 驗證 State Token
2. ✅ Exchange Code for Access Token
3. ✅ 驗證 ID Token，取得 Email
4. ✅ 如果 Email 無效：
   - 選項 A: 強制重新授權（`force_reauth` 啟用）
   - 選項 B: 顯示手動輸入表單
5. ✅ 取得 LINE Profile
6. ✅ 查詢或建立 WordPress User
7. ✅ 儲存綁定資料
8. ✅ WordPress 登入
9. ✅ 觸發 Hook: `do_action('line_hub/user_logged_in', $user_id, $line_uid, $profile)`
10. ✅ 重導向到 `redirect_to`

**錯誤代碼**:

| Code | 說明 |
|------|------|
| `invalid_state` | State Token 無效或過期 |
| `invalid_code` | Authorization Code 無效 |
| `token_exchange_failed` | 無法取得 Access Token |
| `email_missing` | 無法取得 Email（需重新授權或手動輸入） |
| `profile_fetch_failed` | 無法取得 LINE Profile |
| `user_creation_failed` | WordPress 用戶建立失敗 |

---

### POST /login/reauth

強制重新授權（重新取得 Email）

**Request**:

```
POST /wp-json/line-hub/v1/login/reauth
Headers:
  X-WP-Nonce: xxxxx...
  Content-Type: application/json

Body:
{
  "user_id": 123,
  "reason": "email_missing"
}
```

**Response (200 OK)**:

```json
{
  "success": true,
  "auth_url": "https://access.line.me/oauth2/v2.1/authorize?...&prompt=consent",
  "message": "Please complete re-authorization"
}
```

**使用場景**:

當 Email 擷取失敗時，引導用戶重新授權

---

## Binding API（綁定管理）

### GET /binding/status

查詢當前用戶的 LINE 綁定狀態

**Request**:

```
GET /wp-json/line-hub/v1/binding/status
Headers:
  X-WP-Nonce: xxxxx...
```

**Response (已綁定 - 200 OK)**:

```json
{
  "success": true,
  "bound": true,
  "data": {
    "line_uid": "Uabcd1234...",
    "display_name": "張三",
    "picture_url": "https://profile.line-scdn.net/...",
    "email": "user@example.com",
    "bound_at": "2026-01-15T10:30:00+08:00"
  }
}
```

**Response (未綁定 - 200 OK)**:

```json
{
  "success": true,
  "bound": false,
  "data": null
}
```

---

### POST /binding/link

手動綁定 LINE 帳號

**Request**:

```
POST /wp-json/line-hub/v1/binding/link
Headers:
  X-WP-Nonce: xxxxx...
  Content-Type: application/json

Body:
{
  "line_uid": "Uabcd1234...",
  "display_name": "張三",
  "picture_url": "https://...",
  "email": "user@example.com"
}
```

**Response (200 OK)**:

```json
{
  "success": true,
  "message": "LINE account linked successfully"
}
```

**Response (Conflict - 409)**:

```json
{
  "success": false,
  "message": "This LINE account is already linked to another user",
  "code": "already_linked"
}
```

---

### POST /binding/unlink

解除 LINE 綁定

**Request**:

```
POST /wp-json/line-hub/v1/binding/unlink
Headers:
  X-WP-Nonce: xxxxx...
```

**Response (200 OK)**:

```json
{
  "success": true,
  "message": "LINE account unlinked successfully"
}
```

**觸發 Hook**:

```php
do_action('line_hub/binding/unlinked', $user_id, $line_uid);
```

---

## Settings API（設定管理）

### GET /settings

取得所有設定

**Request**:

```
GET /wp-json/line-hub/v1/settings?group=general
Headers:
  X-WP-Nonce: xxxxx...
```

**Query Parameters**:

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `group` | string | No | 設定群組 (general, login, notification, integration) |

**Response (200 OK)**:

```json
{
  "success": true,
  "data": {
    "general": {
      "channel_id": "1234567890",
      "channel_secret": "***",
      "access_token": "***"
    },
    "login": {
      "force_reauth": true,
      "bot_prompt": "aggressive",
      "initial_amr": "lineqr",
      "switch_amr": true,
      "allow_auto_login": false
    },
    "notification": {
      "order_created": {
        "enabled": true,
        "template": "您的訂單 {order_id} 已建立！"
      },
      "shipment": {
        "enabled": true,
        "template": "您的訂單 {order_id} 已出貨！"
      }
    }
  }
}
```

**敏感資料處理**:

- `channel_secret` 和 `access_token` 返回時遮罩：`***`
- 只有在更新時才接受完整值

---

### POST /settings

更新設定

**Request**:

```
POST /wp-json/line-hub/v1/settings
Headers:
  X-WP-Nonce: xxxxx...
  Content-Type: application/json

Body:
{
  "group": "login",
  "settings": {
    "force_reauth": true,
    "bot_prompt": "aggressive",
    "initial_amr": "lineqr"
  }
}
```

**Response (200 OK)**:

```json
{
  "success": true,
  "message": "Settings updated successfully"
}
```

**Response (Validation Error - 400)**:

```json
{
  "success": false,
  "message": "Invalid setting value",
  "code": "validation_error",
  "errors": {
    "bot_prompt": "Must be 'normal' or 'aggressive'"
  }
}
```

**驗證規則**:

```php
$validation_rules = [
    'login' => [
        'force_reauth' => 'boolean',
        'bot_prompt' => ['normal', 'aggressive'],
        'initial_amr' => ['lineqr', 'lineautologin'],
        'switch_amr' => 'boolean',
        'allow_auto_login' => 'boolean',
    ],
    'general' => [
        'channel_id' => 'required|numeric',
        'channel_secret' => 'required|min:20',
        'access_token' => 'required|min:100',
    ]
];
```

---

## Notification API（通知管理）

### POST /notifications/send

發送測試訊息

**Request**:

```
POST /wp-json/line-hub/v1/notifications/send
Headers:
  X-WP-Nonce: xxxxx...
  Content-Type: application/json

Body:
{
  "line_uid": "Uabcd1234...",
  "message": {
    "type": "text",
    "text": "測試訊息"
  }
}
```

**Response (200 OK)**:

```json
{
  "success": true,
  "message_id": "xxxxxxx",
  "sent_at": "2026-02-06T18:00:00+08:00"
}
```

**Response (Failed - 500)**:

```json
{
  "success": false,
  "message": "Failed to send message",
  "code": "send_failed",
  "error": "LINE API error: Invalid access token"
}
```

---

### GET /notifications/history

查詢訊息發送記錄

**Request**:

```
GET /wp-json/line-hub/v1/notifications/history?days=7&scene=order_created&limit=50
Headers:
  X-WP-Nonce: xxxxx...
```

**Query Parameters**:

| 參數 | 類型 | 必填 | 預設 | 說明 |
|------|------|------|------|------|
| `days` | int | No | 7 | 查詢幾天內的記錄 |
| `scene` | string | No | all | 通知場景篩選 |
| `limit` | int | No | 50 | 每頁筆數 |
| `offset` | int | No | 0 | 分頁偏移 |

**Response (200 OK)**:

```json
{
  "success": true,
  "total": 123,
  "data": [
    {
      "id": 456,
      "user_id": 123,
      "line_uid": "Uabcd1234...",
      "scene": "order_created",
      "message": {
        "type": "text",
        "text": "您的訂單 #789 已建立！"
      },
      "status": "sent",
      "sent_at": "2026-02-06T18:00:00+08:00"
    }
  ],
  "stats": {
    "total": 123,
    "sent": 118,
    "failed": 5,
    "success_rate": 95.9
  }
}
```

---

## Webhook History API

### GET /webhooks/history

查詢 Webhook 記錄

**Request**:

```
GET /wp-json/line-hub/v1/webhooks/history?days=7&event_type=message&limit=100
Headers:
  X-WP-Nonce: xxxxx...
```

**Query Parameters**:

| 參數 | 類型 | 必填 | 預設 | 說明 |
|------|------|------|------|------|
| `days` | int | No | 7 | 查詢幾天內的記錄 |
| `event_type` | string | No | all | 事件類型篩選 |
| `limit` | int | No | 100 | 每頁筆數 |
| `offset` | int | No | 0 | 分頁偏移 |

**Response (200 OK)**:

```json
{
  "success": true,
  "total": 567,
  "data": [
    {
      "id": 890,
      "webhook_event_id": "xxx123",
      "event_type": "message",
      "line_uid": "Uabcd1234...",
      "user_id": 123,
      "payload": {
        "type": "message",
        "message": {
          "type": "text",
          "text": "Hello"
        }
      },
      "processed": true,
      "processed_at": "2026-02-06T18:01:00+08:00",
      "created_at": "2026-02-06T18:00:00+08:00"
    }
  ]
}
```

---

## Integration API（整合管理）⭐

### GET /integrations

取得所有外掛整合列表

**Request**:

```
GET /wp-json/line-hub/v1/integrations
Headers:
  X-WP-Nonce: xxxxx...
```

**Response (200 OK)**:

```json
{
  "success": true,
  "data": [
    {
      "key": "fluentcart",
      "name": "FluentCart",
      "enabled": true,
      "hooks": [
        "fluent_cart/order_created",
        "fluent_cart/order_completed"
      ],
      "settings": {
        "notify_order_created": true,
        "notify_order_completed": true,
        "template_order_created": "您的訂單 {order_id} 已建立！"
      }
    },
    {
      "key": "buygo",
      "name": "BuyGo Plus One",
      "enabled": true,
      "hooks": [
        "buygo/shipment/marked_as_shipped"
      ],
      "settings": {
        "notify_shipment": true,
        "template_shipment": "您的訂單 {order_id} 已出貨！"
      }
    },
    {
      "key": "woocommerce",
      "name": "WooCommerce",
      "enabled": false,
      "hooks": [
        "woocommerce_order_status_completed"
      ]
    }
  ]
}
```

---

### POST /integrations/{key}

更新特定整合的設定

**Request**:

```
POST /wp-json/line-hub/v1/integrations/fluentcart
Headers:
  X-WP-Nonce: xxxxx...
  Content-Type: application/json

Body:
{
  "enabled": true,
  "settings": {
    "notify_order_created": true,
    "template_order_created": "您的訂單 {order_id} 已建立，總金額 {order_total} 元！"
  }
}
```

**Response (200 OK)**:

```json
{
  "success": true,
  "message": "Integration settings updated successfully"
}
```

**Response (Not Found - 404)**:

```json
{
  "success": false,
  "message": "Integration not found",
  "code": "integration_not_found"
}
```

---

## 錯誤代碼統一規範

### HTTP Status Codes

| Status | 用途 |
|--------|------|
| 200 | 成功 |
| 400 | 請求參數錯誤 |
| 401 | 未認證 |
| 403 | 權限不足 |
| 404 | 資源不存在 |
| 409 | 衝突（例如重複綁定） |
| 500 | 伺服器錯誤 |

### Error Response 格式

```json
{
  "success": false,
  "message": "人類可讀的錯誤訊息",
  "code": "machine_readable_code",
  "errors": {
    "field_name": "欄位錯誤訊息"
  }
}
```

### Error Codes 列表

| Code | 說明 |
|------|------|
| `invalid_nonce` | Nonce 驗證失敗 |
| `permission_denied` | 權限不足 |
| `invalid_signature` | HMAC 簽名錯誤 |
| `invalid_state` | State Token 無效 |
| `validation_error` | 資料驗證失敗 |
| `already_linked` | LINE 帳號已被綁定 |
| `not_linked` | LINE 帳號未綁定 |
| `send_failed` | 訊息發送失敗 |
| `integration_not_found` | 整合模組不存在 |

---

## Rate Limiting

所有 API 端點（除 Webhook）實施 Rate Limiting：

**限制**：
- 60 requests / minute / IP
- 300 requests / hour / IP

**Response Headers**:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1626848400
```

**超過限制回應 (429 Too Many Requests)**:

```json
{
  "success": false,
  "message": "Rate limit exceeded",
  "code": "rate_limit_exceeded",
  "retry_after": 60
}
```

**實作**:

```php
function check_rate_limit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = 'line_hub_rate_limit_' . md5($ip);

    $requests = get_transient($key) ?: [];
    $now = time();

    // 清除超過 1 分鐘的記錄
    $requests = array_filter($requests, function($timestamp) use ($now) {
        return $now - $timestamp < 60;
    });

    if (count($requests) >= 60) {
        wp_send_json_error([
            'message' => 'Rate limit exceeded',
            'code' => 'rate_limit_exceeded',
            'retry_after' => 60 - ($now - min($requests))
        ], 429);
    }

    $requests[] = $now;
    set_transient($key, $requests, 60);

    // 設定 Headers
    header('X-RateLimit-Limit: 60');
    header('X-RateLimit-Remaining: ' . (60 - count($requests)));
    header('X-RateLimit-Reset: ' . ($now + 60));
}
```

---

## CORS 設定

允許特定來源的跨域請求：

```php
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $allowed_origins = [
            'https://test.buygo.me',
            'https://buygo.me',
        ];

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce');
        }

        return $value;
    });
});
```

---

## API 使用範例

### JavaScript (前端)

```javascript
// 取得綁定狀態
async function getBindingStatus() {
  const response = await fetch('/wp-json/line-hub/v1/binding/status', {
    headers: {
      'X-WP-Nonce': wpApiSettings.nonce
    }
  });

  const data = await response.json();
  return data;
}

// 發送測試訊息
async function sendTestMessage(lineUid, message) {
  const response = await fetch('/wp-json/line-hub/v1/notifications/send', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
      line_uid: lineUid,
      message: {
        type: 'text',
        text: message
      }
    })
  });

  const data = await response.json();
  return data;
}
```

### PHP (外掛整合)

```php
// 觸發 LINE 通知
do_action('line_hub/send_message', [
    'user_id' => $user_id,
    'message' => '你的課程已開通：' . $course_name,
    'context' => 'course_enrollment'
]);

// 或使用 REST API
$response = wp_remote_post(rest_url('line-hub/v1/notifications/send'), [
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce('wp_rest'),
        'Content-Type' => 'application/json',
    ],
    'body' => json_encode([
        'line_uid' => $line_uid,
        'message' => [
            'type' => 'text',
            'text' => '測試訊息'
        ]
    ])
]);
```

---

## API 測試

### Postman Collection

可匯入的 Postman Collection：

```json
{
  "info": {
    "name": "LINE Hub API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Get Binding Status",
      "request": {
        "method": "GET",
        "header": [
          {
            "key": "X-WP-Nonce",
            "value": "{{nonce}}"
          }
        ],
        "url": {
          "raw": "{{base_url}}/wp-json/line-hub/v1/binding/status",
          "host": ["{{base_url}}"],
          "path": ["wp-json", "line-hub", "v1", "binding", "status"]
        }
      }
    }
  ]
}
```

### cURL 範例

```bash
# 取得設定
curl -X GET "https://test.buygo.me/wp-json/line-hub/v1/settings?group=general" \
  -H "X-WP-Nonce: xxxxx"

# 發送測試訊息
curl -X POST "https://test.buygo.me/wp-json/line-hub/v1/notifications/send" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: xxxxx" \
  -d '{
    "line_uid": "Uabcd1234...",
    "message": {
      "type": "text",
      "text": "測試訊息"
    }
  }'
```

---

**文件版本**: 1.0
**建立日期**: 2026-02-06
**最後更新**: 2026-02-06 - 初版完成
