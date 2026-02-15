# LINE Hub 交接文件 — Phase 4 工作指引

> 更新日期：2026-02-15
> 上一次 commit：`8c3dd33 feat: LIFF 登入完整實作 + 綁定 UI + 好友檢查`

---

## 核心設計原則（必須遵守）

> **BuyGo 說「什麼內容」，LineHub 管「怎麼送」**

- **LineHub** 只負責 LINE API 呼叫（傳輸層）
- **BuyGo** 負責業務邏輯：通知模板、觸發時機、訊息內容
- LineHub **不可以**知道「訂單」「出貨」「商品」等業務概念
- 兩個外掛透過 **WordPress hooks** 溝通，不直接互相呼叫

---

## Phase 4 在 LineHub 中只需要做 2 件事

### 1. MessagingService（已完成）

檔案：`includes/messaging/class-messaging-service.php`

已實作：
- `pushMessage($userId, $messages)` — 推播
- `pushText($userId, $text)` — 文字訊息
- `pushFlex($userId, $flexMessage)` — Flex 訊息
- `replyMessage($replyToken, $messages)` — 回覆
- `multicast($userIds, $messages)` — 群發
- `sendToMultiple($userIds, $messages)` — 逐一發送
- `validateToken()` — 驗證 token

**狀態：已完成，不需修改。**

### 2. FlexBuilder（需要建立）

檔案：`includes/messaging/class-flex-builder.php`

**注意：這是通用工具，不含任何業務內容。**

它只提供組裝 LINE Flex Message JSON 的便利方法：

```php
namespace LineHub\Messaging;

class FlexBuilder {
    // 建立 Flex Message 容器
    public static function bubble(array $header, array $body, array $footer = []): array;
    public static function carousel(array $bubbles): array;

    // 建立元件
    public static function text(string $text, array $options = []): array;
    public static function image(string $url, array $options = []): array;
    public static function button(string $label, string $uri, array $options = []): array;
    public static function separator(): array;
    public static function box(string $layout, array $contents, array $options = []): array;

    // 包裝成完整 Flex Message
    public static function flexMessage(string $altText, array $contents): array;
}
```

使用範例（BuyGo 端會這樣呼叫）：
```php
// BuyGo 的 NotificationTemplates 裡面
use LineHub\Messaging\FlexBuilder;

$bubble = FlexBuilder::bubble(
    FlexBuilder::box('vertical', [
        FlexBuilder::text('訂單通知', ['weight' => 'bold', 'size' => 'xl']),
    ]),
    FlexBuilder::box('vertical', [
        FlexBuilder::text('您的訂單 #123 已出貨'),
        FlexBuilder::separator(),
        FlexBuilder::text('商品：日本零食組合'),
    ]),
    FlexBuilder::box('vertical', [
        FlexBuilder::button('查看訂單', 'https://example.com/order/123'),
    ])
);
$message = FlexBuilder::flexMessage('訂單通知', $bubble);

// 呼叫 LineHub 發送
$service = new LineHub\Messaging\MessagingService();
$service->pushMessage($user_id, [$message]);
```

---

## 不要在 LineHub 建立的東西

| 不要建立 | 原因 | 正確歸屬 |
|---------|------|---------|
| TemplateEngine | 模板是業務內容 | BuyGo |
| NotificationTemplates | 通知文案是業務內容 | BuyGo |
| 訂單/出貨 Flex 模板 | 業務概念 | BuyGo |
| NotificationTrigger | 何時通知是業務決策 | BuyGo |
| 任何含有「order」「shipment」「product」的類別 | 業務概念 | BuyGo |

---

## 完成 FlexBuilder 後

1. 在 `class-plugin.php` 的 `load_dependencies()` 中載入 messaging 模組
2. 確認 autoloader 能正確載入 `LineHub\Messaging\*`
3. PHP syntax check：`php -l includes/messaging/*.php`
4. Git commit

---

## 環境資訊

- line-hub 原始碼：`/Users/fishtv/Development/line-hub/`
- buygo-plus-one 原始碼：`/Users/fishtv/Development/buygo-plus-one/`（參考用，不要修改）
- 測試網址：`https://test.buygo.me`
- 繁體中文回應
- 每個類別不超過 300 行
