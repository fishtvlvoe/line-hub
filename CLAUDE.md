# CLAUDE.md — Line Hub

<gates>
trigger: 任何程式碼修改前
action: 確認只修改 `wp-plugins/line-hub/` 目錄內的檔案

trigger: 呼叫 LINE API
action: 一律透過 `includes/services/class-messaging-service.php` 封裝，禁止直接呼叫 LINE SDK

trigger: 需要對外提供功能給其他外掛
action: 只透過 WordPress hooks（`do_action` / `apply_filters`），禁止讓外部直接 `new` 本外掛 class

trigger: Webhook 收到事件
action: `includes/webhook/` 只做事件分派，複雜邏輯一律放到 `includes/services/`

trigger: 處理 LINE 金鑰或 OAuth Token
action: 一律從 WordPress options 或 wp-config.php 常數讀取，禁止硬編碼在程式碼中
</gates>

<rules>
## 架構規範
policy: LINE API 呼叫只能透過 `includes/services/class-messaging-service.php`
policy: Flex Message 建構只用 `includes/messaging/class-flex-builder.php`
policy: LIFF 登入流程只走 `includes/liff/` + `includes/auth/`
policy: Webhook 事件分派走 `includes/webhook/class-event-dispatcher.php`
policy: 外掛設定讀寫走 `includes/services/class-settings-service.php`
banned: 在 Webhook handler 中寫商業邏輯
banned: 在 `includes/api/` 中寫商業邏輯
banned: 硬編碼 LINE Channel Secret / Access Token

## 外掛間通訊
policy: 對外功能只透過 WordPress hooks 暴露
format: hook 命名格式 `line_hub/{action}/{type}`

## 測試規範（TDD）
policy: 先寫測試，再寫實作
policy: 測試只測純 PHP 邏輯，不依賴 WordPress 運行環境

## Git 規範
policy: 功能用 `feature/xxx` 分支，修 bug 用 `fix/xxx` 分支
banned: 直接 push main 分支，必須建 PR
</rules>

<conn>
## 對外提供的 Hooks（供其他外掛使用）
```php
// 發送文字訊息
do_action('line_hub/send/text', $line_uid, $message);

// 發送 Flex Message
do_action('line_hub/send/flex', $line_uid, $flex_content);

// REST API（需 API Key）
GET  /line-hub/v1/users/lookup?email={email}
POST /line-hub/v1/messages/flex   →  Header: X-LineHub-API-Key: {key}
```

## 常用指令
```bash
cd /Users/fishtv/Development/wp-plugins/line-hub
composer test                        # 所有測試
composer test -- --filter "Webhook"  # 只測 webhook
```

## 本機錯誤日誌
```bash
tail -f /Users/fishtv/Local\ Sites/buygo/app/logs/php-error.log
```
</conn>

<ref>
## 目錄結構
```
line-hub/
├── line-hub.php                      # 主入口
├── includes/
│   ├── class-plugin.php              # 外掛載入器
│   ├── class-database.php            # 資料庫初始化
│   ├── class-updater.php             # 自動更新
│   ├── autoload.php
│   ├── messaging/                    # LINE 訊息層
│   │   ├── class-messaging-service.php   # ⭐ LINE API 唯一入口
│   │   ├── class-flex-builder.php        # Flex Message 建構
│   │   └── class-flex-elements.php       # Flex 元素工廠
│   ├── webhook/                      # Webhook 接收與分派
│   │   ├── class-webhook-receiver.php
│   │   ├── class-event-dispatcher.php
│   │   └── class-webhook-logger.php
│   ├── auth/                         # OAuth / LIFF 登入
│   │   ├── class-oauth-client.php
│   │   ├── class-auth-callback.php
│   │   ├── class-oauth-state.php
│   │   └── class-session-transfer.php
│   ├── liff/                         # LIFF 頁面處理
│   │   ├── class-liff-handler.php
│   │   ├── class-liff-api-client.php
│   │   └── class-liff-user-processor.php
│   ├── services/                     # 商業邏輯層
│   │   ├── class-user-service.php
│   │   ├── class-login-service.php
│   │   ├── class-settings-service.php
│   │   ├── class-content-service.php
│   │   ├── class-integration-hooks.php
│   │   └── class-user-profile-manager.php
│   ├── api/                          # REST API 端點
│   │   ├── class-public-api.php
│   │   ├── class-user-api.php
│   │   └── class-settings-api.php
│   └── integration/                  # 與其他外掛整合
├── tests/                            # PHPUnit 測試
└── composer.json
```

## 相關文檔
- 根目錄指引：`../../CLAUDE.md`
- 系統架構：`../docs/architecture.md`
</ref>
