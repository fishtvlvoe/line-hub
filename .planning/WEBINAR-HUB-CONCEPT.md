# Webinar Hub — 架構概念

## 定位

Webinar Hub 是一個 **中間層外掛**，把各種 Webinar 平台統一成標準格式，
再透過 LineHub API 發送 LINE 通知給用戶。

## 架構圖

```
┌─────────────── Webinar Hub 外掛 ───────────────┐
│                                                  │
│  [WebinarJam Adapter]  ← webhook/API             │
│  [Webinarkit Adapter]  ← webhook/API             │
│  [Zoom Adapter]        ← webhook/API             │
│  [Google Meet Adapter] ← webhook/API   ┌──────┐  │
│  [YouTube Adapter]     ← webhook/API → │ 核心 │  │
│  [Facebook Adapter]    ← webhook/API   │ 引擎 │  │
│                                        └──┬───┘  │
└───────────────────────────────────────────┼──────┘
                                            │
                         do_action('line_hub/send/text')
                         POST /line-hub/v1/messages/flex
                                            │
                                            ▼
                                   ┌──────────────┐
                                   │   LineHub     │
                                   │ (LINE 通道)   │
                                   └──────────────┘
```

## 核心概念：Adapter Pattern

每個平台實現同一個接口（AdapterInterface）：

- `verifyWebhook(payload)` — 驗證 webhook 簽名
- `normalizeEvent(payload)` — 把各平台數據轉為統一格式
- `getEventDetails(event_id)` — 取得研討會詳情

## 統一事件格式（NormalizedEvent）

```
type:            registered | reminder | started | ended | replay_ready
platform:        webinarjam | zoom | youtube | ...
event_title:     研討會標題
event_url:       參加連結
start_time:      開始時間
replay_url:      重播連結（可選）
attendee_email:  參加者 Email
attendee_name:   參加者姓名（可選）
```

## 通知流程

```
Webinar 平台 webhook 進來
  → Adapter::normalizeEvent()
  → NormalizedEvent { type: 'registered', email: 'user@example.com', ... }
  → NotificationSender::send($event)
      → 用 email 查 LineHub：GET /line-hub/v1/users/lookup?email=...
      → 找到 → POST /line-hub/v1/messages/flex（發送 Flex 通知）
      → 找不到 → 記錄 log（用戶未綁定 LINE）
```

## 用戶對應：用 Email 做橋梁

```
Webinar 平台的註冊 Email
  → LineHub lookup API
  → WordPress 用戶
  → LINE 用戶
```

## 目錄結構

```
webinar-hub/
├── webinar-hub.php
├── includes/
│   ├── class-plugin.php               # 載入器
│   ├── class-event-normalizer.php     # 統一事件格式
│   ├── class-notification-sender.php  # 調用 LineHub 發通知
│   ├── adapters/                      # 每個平台一個 Adapter
│   │   ├── class-adapter-interface.php
│   │   ├── class-webinarjam-adapter.php
│   │   ├── class-zoom-adapter.php
│   │   └── ...
│   ├── webhooks/                      # 接收各平台回調
│   │   └── class-webhook-receiver.php
│   └── templates/                     # LINE Flex 通知模板
│       ├── event-reminder.php
│       ├── event-started.php
│       └── event-replay-ready.php
└── admin/
    └── class-settings-page.php        # 後台：選平台、填 API Key
```

## 通知模板歸屬

- **模板（說什麼）放在 Webinar Hub** — 研討會提醒、開始、重播通知
- **LineHub 只管通道（怎麼送）** — 接收結構化資料 → Flex 訊息 → LINE API

## 時間排程（提前提醒）

- Webinar Hub 用 `wp_schedule_single_event()` 排程
- 到時間觸發 → 調用 LineHub API 發送提醒

## 開發順序建議

1. 先做 WebinarJam（一個平台做到完整可用）
2. 開發過程中抽象出 AdapterInterface + NormalizedEvent
3. 第二個平台（Zoom）驗證架構通用性
4. 後續平台 = 實現一個 Adapter + 測試

## 設計決策

| 問題 | 決定 |
|------|------|
| 一個大外掛 vs 每平台獨立？ | 一個外掛 + Adapter（共用邏輯太多） |
| 通知模板放哪？ | Webinar Hub（業務內容歸業務外掛） |
| 用戶對應方式？ | Email 做橋梁（webinar email → WP user → LINE） |
| LineHub 需要改動嗎？ | 不需要（REST API + Hooks 已足夠） |
