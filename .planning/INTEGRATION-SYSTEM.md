# LINE Hub 外掛串接系統設計

## 文件概述

**版本**: 1.0
**建立日期**: 2026-02-06
**目的**: 定義 LINE Hub 作為「整合中樞」的串接系統架構

---

## 設計理念

LINE Hub 不只是一個 LINE 登入+通知外掛，更是一個**整合中樞**，讓任何 WordPress 外掛都能輕鬆串接 LINE 功能。

### 核心概念

類似 **Zapier** 或 **n8n** 的串接邏輯，但針對 WordPress 優化：

```
觸發源               LINE Hub                 LINE Platform
(任何外掛)        (整合中樞)                  (訊息發送)
    │                   │                          │
    │ Hook 觸發          │                          │
    ├──────────────────>│                          │
    │                   │ 查詢綁定、套用模板        │
    │                   ├──┐                       │
    │                   │<─┘                       │
    │                   │ 發送訊息                  │
    │                   ├─────────────────────────>│
    │                   │                          │
    │                   │ 觸發完成 Hook              │
    │<──────────────────┤                          │
```

---

## 三種串接方式

### 1. 被動監聽（Listening）

LINE Hub 監聽其他外掛的 Hooks，自動發送通知。

**範例**：

```php
// LINE Hub 監聽 FluentCart 的訂單建立事件
add_action('fluent_cart/order_created', function($data) {
    $order = $data['order'];
    $user_id = $order->user_id;

    // 取得 LINE UID
    $line_uid = LineHub\get_line_uid($user_id);

    if ($line_uid) {
        // 發送通知
        LineHub\send_message($line_uid, '您的訂單已建立！');
    }
}, 20);
```

**適用場景**：
- 訂單通知（FluentCart、WooCommerce）
- 出貨通知（BuyGo Plus One）
- 會員通知（WordPress Core）

---

### 2. 主動觸發（Providing）

LINE Hub 提供 Hooks，讓其他外掛監聽 LINE 事件。

**範例**：

```php
// 其他外掛監聽 LINE Hub 的用戶登入事件
add_action('line_hub/user_logged_in', function($user_id, $line_uid, $profile) {
    // 同步用戶資料到你的外掛
    update_user_meta($user_id, 'my_plugin_line_uid', $line_uid);
    update_user_meta($user_id, 'my_plugin_line_avatar', $profile['picture_url']);

    // 觸發你的外掛邏輯
    do_action('my_plugin/line_user_synced', $user_id);
}, 10, 3);
```

**適用場景**：
- LINE 登入後同步會員資料
- Webhook 接收後觸發自訂邏輯
- LINE 訊息接收後進行回覆

---

### 3. API 調用（Invoking）

其他外掛直接調用 LINE Hub 的 API 發送訊息。

**範例**：

```php
// 方式 A: 使用 Hook（推薦）
do_action('line_hub/send_message', [
    'user_id' => $user_id,
    'message' => '你的課程已開通：' . $course_name,
    'context' => 'course_enrollment'
]);

// 方式 B: 直接調用服務類別
if (class_exists('LineHub\\Services\\MessagingService')) {
    $line_uid = LineHub\Services\UserService::get_line_uid($user_id);
    LineHub\Services\MessagingService::send_text($line_uid, '測試訊息');
}

// 方式 C: REST API
wp_remote_post(rest_url('line-hub/v1/notifications/send'), [
    'body' => json_encode([
        'line_uid' => $line_uid,
        'message' => ['type' => 'text', 'text' => '測試']
    ])
]);
```

**適用場景**：
- 課程報名通知（LMS 外掛）
- 預約提醒（預約系統）
- 自訂業務邏輯觸發通知

---

## Hook Registry（註冊中心）

管理所有對外提供和監聽的 Hooks。

### 架構

```php
namespace LineHub\Integrations;

class HookRegistry {
    /**
     * 對外提供的 Hooks（其他外掛可監聽）
     */
    private static $provided_hooks = [
        // 登入事件
        'line_hub/user_logged_in' => [
            'description' => 'LINE 登入完成',
            'params' => ['user_id', 'line_uid', 'profile_data'],
            'since' => '1.0.0'
        ],

        // Webhook 事件
        'line_hub/webhook/message' => [
            'description' => 'Webhook 接收到訊息',
            'params' => ['event', 'user_id'],
            'since' => '1.0.0'
        ],
        'line_hub/webhook/follow' => [
            'description' => 'Webhook 接收到關注事件',
            'params' => ['event', 'user_id'],
            'since' => '1.0.0'
        ],
        'line_hub/webhook/unfollow' => [
            'description' => 'Webhook 接收到取消關注事件',
            'params' => ['event', 'user_id'],
            'since' => '1.0.0'
        ],

        // 訊息事件
        'line_hub/message/sent' => [
            'description' => 'LINE 訊息發送完成',
            'params' => ['user_id', 'message', 'response'],
            'since' => '1.0.0'
        ],
        'line_hub/message/failed' => [
            'description' => 'LINE 訊息發送失敗',
            'params' => ['user_id', 'message', 'error'],
            'since' => '1.0.0'
        ],

        // 綁定事件
        'line_hub/binding/linked' => [
            'description' => 'LINE 帳號綁定完成',
            'params' => ['user_id', 'line_uid'],
            'since' => '1.0.0'
        ],
        'line_hub/binding/unlinked' => [
            'description' => 'LINE 帳號解除綁定',
            'params' => ['user_id', 'line_uid'],
            'since' => '1.0.0'
        ],
    ];

    /**
     * 監聽的外部 Hooks（LINE Hub 會監聽）
     */
    private static $listening_hooks = [
        // FluentCart
        'fluent_cart/order_created' => [
            'handler' => 'LineHub\\Integrations\\FluentCart::on_order_created',
            'priority' => 20,
            'enabled' => true
        ],
        'fluent_cart/order_completed' => [
            'handler' => 'LineHub\\Integrations\\FluentCart::on_order_completed',
            'priority' => 20,
            'enabled' => true
        ],

        // BuyGo Plus One
        'buygo/shipment/marked_as_shipped' => [
            'handler' => 'LineHub\\Integrations\\BuyGo::on_shipment',
            'priority' => 20,
            'enabled' => true
        ],
        'buygo/parent_order_completed' => [
            'handler' => 'LineHub\\Integrations\\BuyGo::on_parent_completed',
            'priority' => 20,
            'enabled' => true
        ],

        // WooCommerce
        'woocommerce_order_status_completed' => [
            'handler' => 'LineHub\\Integrations\\WooCommerce::on_order_completed',
            'priority' => 20,
            'enabled' => false // 預設關閉
        ],

        // WordPress Core
        'user_register' => [
            'handler' => 'LineHub\\Integrations\\WordPress::on_user_register',
            'priority' => 20,
            'enabled' => true
        ],
        'retrieve_password' => [
            'handler' => 'LineHub\\Integrations\\WordPress::on_password_reset',
            'priority' => 20,
            'enabled' => true
        ],
    ];

    /**
     * 註冊所有監聽的 Hooks
     */
    public static function register_listeners() {
        foreach (self::$listening_hooks as $hook => $config) {
            if ($config['enabled']) {
                add_action($hook, $config['handler'], $config['priority'], 10);
            }
        }
    }

    /**
     * 取得所有對外提供的 Hooks
     */
    public static function get_provided_hooks() {
        return self::$provided_hooks;
    }

    /**
     * 取得所有監聽的 Hooks
     */
    public static function get_listening_hooks() {
        return self::$listening_hooks;
    }
}
```

---

## Plugin Connector（外掛連接器）

每個外掛整合都有一個獨立的 Connector 類別。

### FluentCart Connector

```php
namespace LineHub\Integrations;

class FluentCart {
    /**
     * 註冊 Hooks
     */
    public static function register_hooks() {
        add_action('fluent_cart/order_created', [self::class, 'on_order_created'], 20);
        add_action('fluent_cart/order_completed', [self::class, 'on_order_completed'], 20);
        add_action('fluent_cart/order_failed', [self::class, 'on_order_failed'], 20);
    }

    /**
     * 處理訂單建立事件
     */
    public static function on_order_created($data) {
        // FluentCart 傳遞陣列格式
        $order = $data['order'] ?? null;
        if (!$order) {
            return;
        }

        // 檢查是否啟用此通知場景
        if (!self::is_scene_enabled('order_created')) {
            return;
        }

        // 取得用戶 LINE UID
        $user_id = $order->user_id;
        $line_uid = \LineHub\Services\UserService::get_line_uid($user_id);

        if (!$line_uid) {
            return; // 用戶未綁定 LINE
        }

        // 取得訊息模板
        $template = self::get_template('order_created');

        // 替換變數
        $message = self::replace_variables($template, [
            'order_id' => $order->id,
            'order_total' => $order->total,
            'customer_name' => $order->customer->first_name,
            'order_date' => $order->created_at,
        ]);

        // 允許其他外掛修改訊息
        $message = apply_filters('line_hub/message/before_send', $message, $user_id, 'order_created');

        // 發送通知
        $result = \LineHub\Services\MessagingService::send_text($line_uid, $message);

        // 記錄到資料庫
        \LineHub\Services\NotificationService::log([
            'user_id' => $user_id,
            'line_uid' => $line_uid,
            'scene' => 'order_created',
            'message' => $message,
            'status' => $result ? 'sent' : 'failed',
        ]);

        // 觸發完成 Hook
        if ($result) {
            do_action('line_hub/message/sent', $user_id, $message, $result);
        } else {
            do_action('line_hub/message/failed', $user_id, $message, null);
        }
    }

    /**
     * 檢查場景是否啟用
     */
    private static function is_scene_enabled($scene) {
        return get_option("line_hub_fluentcart_{$scene}_enabled", true);
    }

    /**
     * 取得訊息模板
     */
    private static function get_template($scene) {
        $default_templates = [
            'order_created' => '您的訂單 {order_id} 已建立！總金額：{order_total} 元',
            'order_completed' => '您的訂單 {order_id} 已完成！',
            'order_failed' => '您的訂單 {order_id} 處理失敗，請聯繫客服。',
        ];

        return get_option(
            "line_hub_fluentcart_{$scene}_template",
            $default_templates[$scene]
        );
    }

    /**
     * 替換變數
     */
    private static function replace_variables($template, $vars) {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }
}
```

### BuyGo Connector

```php
namespace LineHub\Integrations;

class BuyGo {
    public static function register_hooks() {
        add_action('buygo/shipment/marked_as_shipped', [self::class, 'on_shipment'], 20, 2);
        add_action('buygo/parent_order_completed', [self::class, 'on_parent_completed'], 20);
    }

    public static function on_shipment($shipment_id, $order_id) {
        if (!self::is_scene_enabled('shipment')) {
            return;
        }

        // 取得訂單資訊
        $order = \LineHub\Services\OrderService::get_order($order_id);
        $user_id = $order->user_id;
        $line_uid = \LineHub\Services\UserService::get_line_uid($user_id);

        if (!$line_uid) {
            return;
        }

        // 取得出貨資訊
        $shipment = \LineHub\Services\ShipmentService::get_shipment($shipment_id);

        // 套用模板
        $template = self::get_template('shipment');
        $message = self::replace_variables($template, [
            'order_id' => $order_id,
            'tracking_number' => $shipment->tracking_number ?? '無',
            'shipping_company' => $shipment->shipping_company ?? '無',
        ]);

        // 發送通知
        \LineHub\Services\MessagingService::send_text($line_uid, $message);
    }

    private static function is_scene_enabled($scene) {
        return get_option("line_hub_buygo_{$scene}_enabled", true);
    }

    private static function get_template($scene) {
        $defaults = [
            'shipment' => '您的訂單 {order_id} 已出貨！物流單號：{tracking_number}',
        ];
        return get_option("line_hub_buygo_{$scene}_template", $defaults[$scene]);
    }

    private static function replace_variables($template, $vars) {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }
}
```

---

## 整合後台管理

### 「串接」Tab 設計

顯示所有可用的外掛整合，並允許啟用/停用和設定。

```php
namespace LineHub\Admin;

class Integrations_Page {
    public function render() {
        $integrations = \LineHub\Integrations\HookRegistry::get_available_integrations();
        ?>
        <div class="wrap line-hub-integrations">
            <h1>外掛整合</h1>

            <div class="integration-grid">
                <?php foreach ($integrations as $key => $integration): ?>
                    <div class="integration-card <?php echo $integration['enabled'] ? 'enabled' : 'disabled'; ?>">
                        <div class="integration-header">
                            <h3><?php echo esc_html($integration['name']); ?></h3>
                            <label class="toggle">
                                <input type="checkbox"
                                       name="integration_enabled[<?php echo $key; ?>]"
                                       <?php checked($integration['enabled']); ?>
                                       data-integration="<?php echo $key; ?>">
                                <span class="slider"></span>
                            </label>
                        </div>

                        <p class="integration-description">
                            <?php echo esc_html($integration['description']); ?>
                        </p>

                        <div class="integration-hooks">
                            <strong>監聽 Hooks:</strong>
                            <ul>
                                <?php foreach ($integration['hooks'] as $hook): ?>
                                    <li><code><?php echo esc_html($hook); ?></code></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <?php if ($integration['enabled']): ?>
                            <div class="integration-settings">
                                <button class="button"
                                        data-integration="<?php echo $key; ?>">
                                    設定通知訊息
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 自訂整合說明 -->
            <div class="custom-integration-guide">
                <h2>自訂整合</h2>
                <p>你的外掛也可以輕鬆整合 LINE Hub！</p>

                <div class="code-example">
                    <h3>範例：監聽 LINE 登入事件</h3>
                    <pre><code>add_action('line_hub/user_logged_in', function($user_id, $line_uid, $profile) {
    // 你的邏輯
}, 10, 3);</code></pre>
                </div>

                <div class="code-example">
                    <h3>範例：觸發 LINE 通知</h3>
                    <pre><code>do_action('line_hub/send_message', [
    'user_id' => $user_id,
    'message' => '你的課程已開通！',
    'context' => 'course_enrollment'
]);</code></pre>
                </div>

                <a href="https://github.com/fishtvlvoe/line-hub/wiki/Integration-Guide"
                   class="button button-primary"
                   target="_blank">
                    查看完整文檔
                </a>
            </div>
        </div>
        <?php
    }
}
```

---

## 訊息模板系統

### 變數替換

支援動態變數替換：

```php
namespace LineHub\Services;

class TemplateEngine {
    /**
     * 支援的變數
     */
    private static $available_variables = [
        'order' => [
            'order_id',
            'order_total',
            'order_status',
            'order_date',
            'payment_method',
            'customer_name',
            'customer_email',
        ],
        'shipment' => [
            'tracking_number',
            'shipping_company',
            'shipment_date',
            'estimated_delivery',
        ],
        'user' => [
            'user_name',
            'user_email',
            'user_display_name',
        ],
    ];

    /**
     * 渲染模板
     */
    public static function render($template, $data) {
        // 替換變數
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        // 允許其他外掛修改
        $template = apply_filters('line_hub/template/rendered', $template, $data);

        return $template;
    }

    /**
     * 取得可用變數列表
     */
    public static function get_available_variables($context) {
        return self::$available_variables[$context] ?? [];
    }

    /**
     * 驗證模板
     */
    public static function validate($template) {
        // 檢查是否有未關閉的 {}
        preg_match_all('/\{([^}]+)\}/', $template, $matches);

        $errors = [];
        foreach ($matches[1] as $var) {
            if (!self::is_valid_variable($var)) {
                $errors[] = "Unknown variable: {{$var}}";
            }
        }

        return empty($errors) ? true : $errors;
    }

    private static function is_valid_variable($var) {
        foreach (self::$available_variables as $vars) {
            if (in_array($var, $vars)) {
                return true;
            }
        }
        return false;
    }
}
```

### 模板編輯器（後台）

```php
<div class="template-editor">
    <label for="template">訊息模板</label>
    <textarea id="template" name="template" rows="5"><?php echo esc_textarea($template); ?></textarea>

    <div class="available-variables">
        <strong>可用變數：</strong>
        <?php foreach ($variables as $var): ?>
            <code class="variable-tag"
                  data-variable="<?php echo $var; ?>">
                {<?php echo $var; ?>}
            </code>
        <?php endforeach; ?>
    </div>

    <div class="template-preview">
        <strong>預覽：</strong>
        <div class="preview-content"></div>
    </div>
</div>

<script>
// 點擊變數標籤插入到 textarea
document.querySelectorAll('.variable-tag').forEach(tag => {
    tag.addEventListener('click', () => {
        const textarea = document.getElementById('template');
        const variable = '{' + tag.dataset.variable + '}';
        textarea.value += variable;
        updatePreview();
    });
});

// 即時預覽
function updatePreview() {
    const template = document.getElementById('template').value;
    const preview = document.querySelector('.preview-content');

    // 模擬資料
    const sampleData = {
        order_id: '12345',
        order_total: '1,234',
        customer_name: '張三'
    };

    let rendered = template;
    for (const [key, value] of Object.entries(sampleData)) {
        rendered = rendered.replace(new RegExp(`\\{${key}\\}`, 'g'), value);
    }

    preview.textContent = rendered;
}
</script>
```

---

## 開發者文檔

### 快速開始指南

**步驟 1: 檢查 LINE Hub 是否啟用**

```php
if (!class_exists('LineHub\\Plugin')) {
    // LINE Hub 未啟用
    return;
}
```

**步驟 2: 監聽 LINE Hub 事件**

```php
add_action('line_hub/user_logged_in', function($user_id, $line_uid, $profile) {
    // LINE 登入完成後的處理
    update_user_meta($user_id, 'my_plugin_line_uid', $line_uid);
}, 10, 3);
```

**步驟 3: 觸發 LINE 通知**

```php
// 方式 A: 使用 Hook（推薦）
do_action('line_hub/send_message', [
    'user_id' => $user_id,
    'message' => '通知內容',
    'context' => 'my_plugin_event'
]);

// 方式 B: 直接調用服務
$line_uid = LineHub\Services\UserService::get_line_uid($user_id);
LineHub\Services\MessagingService::send_text($line_uid, '通知內容');
```

### Hook 參考

**監聽 LINE Hub 事件：**

```php
// LINE 登入
add_action('line_hub/user_logged_in', callable, 10, 3);
// 參數: $user_id, $line_uid, $profile_data

// Webhook 訊息
add_action('line_hub/webhook/message', callable, 10, 2);
// 參數: $event, $user_id

// 訊息發送完成
add_action('line_hub/message/sent', callable, 10, 3);
// 參數: $user_id, $message, $response

// 綁定完成
add_action('line_hub/binding/linked', callable, 10, 2);
// 參數: $user_id, $line_uid
```

**修改 LINE Hub 行為：**

```php
// 修改發送前的訊息
add_filter('line_hub/message/before_send', function($message, $user_id, $scene) {
    // 可以修改訊息內容
    return $message;
}, 10, 3);

// 修改訊息模板
add_filter('line_hub/template/rendered', function($template, $data) {
    // 可以修改渲染後的模板
    return $template;
}, 10, 2);
```

---

## 整合範例

### 範例 1: LMS 外掛（課程報名通知）

```php
// 在你的 LMS 外掛中
add_action('lms_course_enrolled', function($user_id, $course_id) {
    // 取得課程資訊
    $course = get_post($course_id);

    // 觸發 LINE 通知
    do_action('line_hub/send_message', [
        'user_id' => $user_id,
        'message' => sprintf(
            '恭喜您成功報名課程：%s！請到會員中心查看課程內容。',
            $course->post_title
        ),
        'context' => 'course_enrolled'
    ]);
}, 10, 2);
```

### 範例 2: 預約系統（預約提醒）

```php
// 在你的預約外掛中
add_action('booking_reminder', function($booking_id) {
    $booking = get_booking($booking_id);
    $user_id = $booking->user_id;

    // 發送預約提醒
    $line_uid = apply_filters('line_hub/get_line_uid', null, $user_id);

    if ($line_uid && class_exists('LineHub\\Services\\MessagingService')) {
        LineHub\Services\MessagingService::send_text(
            $line_uid,
            sprintf(
                '您明天 %s 有預約：%s，請準時赴約！',
                $booking->time,
                $booking->service_name
            )
        );
    }
});
```

### 範例 3: 會員系統（積分變動通知）

```php
// 監聽 LINE 登入，同步積分系統
add_action('line_hub/user_logged_in', function($user_id, $line_uid, $profile) {
    // 檢查是否為新用戶
    $is_new_member = get_user_meta($user_id, 'member_initialized', true) != '1';

    if ($is_new_member) {
        // 發放新會員積分
        add_user_points($user_id, 100);

        // 標記已初始化
        update_user_meta($user_id, 'member_initialized', '1');

        // 發送歡迎訊息（LINE Hub 會自動處理）
    }
}, 10, 3);

// 積分變動時通知
add_action('member_points_changed', function($user_id, $points, $reason) {
    $line_uid = LineHub\Services\UserService::get_line_uid($user_id);

    if ($line_uid) {
        $message = sprintf(
            '您的積分 %s %d 點，原因：%s。目前積分：%d',
            $points > 0 ? '增加' : '減少',
            abs($points),
            $reason,
            get_user_points($user_id)
        );

        LineHub\Services\MessagingService::send_text($line_uid, $message);
    }
}, 10, 3);
```

---

## 進階功能

### 條件式通知

```php
// 只在訂單金額超過 1000 時發送
add_filter('line_hub/notification/allowed', function($allowed, $user_id, $scene, $data) {
    if ($scene === 'order_created' && $data['order_total'] < 1000) {
        return false; // 不發送
    }
    return $allowed;
}, 10, 4);
```

### 自訂訊息格式（Flex Message）

```php
// 發送 Flex Message
do_action('line_hub/send_message', [
    'user_id' => $user_id,
    'message' => [
        'type' => 'flex',
        'altText' => '訂單詳情',
        'contents' => [
            'type' => 'bubble',
            'body' => [
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => [
                    [
                        'type' => 'text',
                        'text' => '訂單 #12345',
                        'weight' => 'bold',
                        'size' => 'xl'
                    ],
                    [
                        'type' => 'text',
                        'text' => '總金額：1,234 元',
                        'size' => 'md',
                        'color' => '#666666'
                    ]
                ]
            ]
        ]
    ],
    'context' => 'order_detail'
]);
```

---

## 測試與除錯

### 測試整合是否正常

```php
// 測試 Hook 是否被監聽
function test_line_hub_integration() {
    // 觸發測試 Hook
    do_action('fluent_cart/order_created', [
        'order' => (object)[
            'id' => 999,
            'user_id' => 1,
            'total' => 1234,
            'customer' => (object)['first_name' => '測試用戶']
        ]
    ]);

    // 檢查 Log
    $logs = LineHub\Services\NotificationService::get_recent_logs(1);
    if (!empty($logs)) {
        echo '整合正常運作！';
    } else {
        echo '整合失敗，請檢查設定。';
    }
}
```

### Debug Mode

```php
// 啟用 Debug 模式
define('LINE_HUB_DEBUG', true);

// Debug Log
if (defined('LINE_HUB_DEBUG') && LINE_HUB_DEBUG) {
    error_log('[LINE Hub] Message sent: ' . $message);
}
```

---

**文件版本**: 1.0
**建立日期**: 2026-02-06
**最後更新**: 2026-02-06 - 初版完成
