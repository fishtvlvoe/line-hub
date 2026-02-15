<?php
/**
 * Flex Message Builder
 *
 * 通用的 LINE Flex Message JSON 組裝工具
 * 不含任何業務邏輯，純粹提供 Flex Message 結構建構方法
 *
 * @package LineHub
 * @since 1.0.0
 */

namespace LineHub\Messaging;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class FlexBuilder
 *
 * 提供靜態方法來建構 LINE Flex Message 的各種元件和容器
 * 遵循 LINE Flex Message Simulator 規範
 *
 * @link https://developers.line.biz/en/docs/messaging-api/using-flex-messages/
 */
class FlexBuilder {
    /**
     * 建立 Flex Message Bubble 容器
     *
     * @param array $body   Body 區塊（必填）
     * @param array $header Header 區塊（可選）
     * @param array $footer Footer 區塊（可選）
     * @param array $hero   Hero 圖片區塊（可選）
     * @param array $styles Bubble 樣式（可選）
     * @return array Bubble 結構
     */
    public static function bubble(array $body, array $header = [], array $footer = [], array $hero = [], array $styles = []): array {
        $bubble = [
            'type' => 'bubble',
            'body' => $body,
        ];

        if (!empty($header)) {
            $bubble['header'] = $header;
        }

        if (!empty($footer)) {
            $bubble['footer'] = $footer;
        }

        if (!empty($hero)) {
            $bubble['hero'] = $hero;
        }

        if (!empty($styles)) {
            $bubble['styles'] = $styles;
        }

        return $bubble;
    }

    /**
     * 建立 Flex Message Carousel 容器
     *
     * @param array $bubbles Bubble 陣列
     * @return array Carousel 結構
     */
    public static function carousel(array $bubbles): array {
        return [
            'type' => 'carousel',
            'contents' => $bubbles,
        ];
    }

    /**
     * 建立 Box 容器
     *
     * @param string $layout   布局類型（vertical/horizontal/baseline）
     * @param array  $contents 子元件陣列
     * @param array  $options  選項（spacing/margin/padding 等）
     * @return array Box 結構
     */
    public static function box(string $layout, array $contents, array $options = []): array {
        $box = [
            'type' => 'box',
            'layout' => $layout,
            'contents' => $contents,
        ];

        return array_merge($box, $options);
    }

    /**
     * 建立文字元件
     *
     * @param string $text    文字內容
     * @param array  $options 選項（size/weight/color/wrap 等）
     * @return array Text 元件
     */
    public static function text(string $text, array $options = []): array {
        $element = [
            'type' => 'text',
            'text' => $text,
        ];

        return array_merge($element, $options);
    }

    /**
     * 建立圖片元件
     *
     * @param string $url     圖片 URL
     * @param array  $options 選項（size/aspectRatio/aspectMode 等）
     * @return array Image 元件
     */
    public static function image(string $url, array $options = []): array {
        $element = [
            'type' => 'image',
            'url' => $url,
        ];

        return array_merge($element, $options);
    }

    /**
     * 建立按鈕元件
     *
     * @param string $label   按鈕文字
     * @param string $action  動作類型或 URI（uri/message/postback）
     * @param array  $options 選項（style/color/height 等）
     * @return array Button 元件
     */
    public static function button(string $label, string $action, array $options = []): array {
        $button = [
            'type' => 'button',
            'action' => self::parseAction($action),
        ];

        // 如果沒有指定 style，設為 primary
        if (!isset($options['style'])) {
            $button['style'] = 'primary';
        }

        // 加入 options
        $button = array_merge($button, $options);

        // action 中的 label 使用 button label
        if (!isset($button['action']['label'])) {
            $button['action']['label'] = $label;
        }

        return $button;
    }

    /**
     * 建立分隔線元件
     *
     * @param array $options 選項（margin/color 等）
     * @return array Separator 元件
     */
    public static function separator(array $options = []): array {
        $element = [
            'type' => 'separator',
        ];

        return array_merge($element, $options);
    }

    /**
     * 建立 Spacer 元件（空白間隔）
     *
     * @param string $size 間隔大小（xs/sm/md/lg/xl/xxl）
     * @return array Spacer 元件
     */
    public static function spacer(string $size = 'md'): array {
        return [
            'type' => 'spacer',
            'size' => $size,
        ];
    }

    /**
     * 建立 Filler 元件（彈性空白）
     *
     * @return array Filler 元件
     */
    public static function filler(): array {
        return [
            'type' => 'filler',
        ];
    }

    /**
     * 建立 Icon 元件
     *
     * @param string $url     Icon URL
     * @param array  $options 選項（size/aspectRatio 等）
     * @return array Icon 元件
     */
    public static function icon(string $url, array $options = []): array {
        $element = [
            'type' => 'icon',
            'url' => $url,
        ];

        return array_merge($element, $options);
    }

    /**
     * 包裝成完整的 Flex Message
     *
     * @param string $altText  替代文字（通知預覽）
     * @param array  $contents Flex 容器（bubble 或 carousel）
     * @return array 完整的 Flex Message
     */
    public static function flexMessage(string $altText, array $contents): array {
        return [
            'type' => 'flex',
            'altText' => $altText,
            'contents' => $contents,
        ];
    }

    /**
     * 解析動作參數（支援簡化寫法）
     *
     * @param string|array $action 動作參數
     * @return array Action 結構
     */
    private static function parseAction($action): array {
        // 如果已經是陣列，直接返回
        if (is_array($action)) {
            return $action;
        }

        // 字串簡化寫法：自動判斷類型
        if (filter_var($action, FILTER_VALIDATE_URL)) {
            // 是 URL，使用 uri action
            return [
                'type' => 'uri',
                'uri' => $action,
            ];
        } elseif (strpos($action, 'postback:') === 0) {
            // 以 postback: 開頭，使用 postback action
            $data = substr($action, 9);
            return [
                'type' => 'postback',
                'data' => $data,
            ];
        } else {
            // 其他情況使用 message action
            return [
                'type' => 'message',
                'text' => $action,
            ];
        }
    }

    /**
     * 建立預設的 Hero 圖片區塊
     *
     * @param string $imageUrl 圖片 URL
     * @param array  $options  選項（aspectRatio/aspectMode/size 等）
     * @return array Hero 圖片結構
     */
    public static function hero(string $imageUrl, array $options = []): array {
        $hero = [
            'type' => 'image',
            'url' => $imageUrl,
            'size' => 'full',
            'aspectRatio' => '20:13',
            'aspectMode' => 'cover',
        ];

        return array_merge($hero, $options);
    }

    /**
     * 建立標題文字（常用樣式）
     *
     * @param string $text    標題文字
     * @param array  $options 額外選項
     * @return array 標題文字元件
     */
    public static function heading(string $text, array $options = []): array {
        $defaults = [
            'weight' => 'bold',
            'size' => 'xl',
            'color' => '#111827',
        ];

        return self::text($text, array_merge($defaults, $options));
    }

    /**
     * 建立副標題文字（常用樣式）
     *
     * @param string $text    副標題文字
     * @param array  $options 額外選項
     * @return array 副標題文字元件
     */
    public static function subheading(string $text, array $options = []): array {
        $defaults = [
            'size' => 'md',
            'color' => '#6b7280',
        ];

        return self::text($text, array_merge($defaults, $options));
    }

    /**
     * 建立標籤文字（常用樣式）
     *
     * @param string $text    標籤文字
     * @param array  $options 額外選項
     * @return array 標籤文字元件
     */
    public static function label(string $text, array $options = []): array {
        $defaults = [
            'size' => 'sm',
            'color' => '#9ca3af',
            'flex' => 0,
        ];

        return self::text($text, array_merge($defaults, $options));
    }

    /**
     * 建立內容文字（常用樣式）
     *
     * @param string $text    內容文字
     * @param array  $options 額外選項
     * @return array 內容文字元件
     */
    public static function content(string $text, array $options = []): array {
        $defaults = [
            'size' => 'sm',
            'color' => '#111111',
            'wrap' => true,
        ];

        return self::text($text, array_merge($defaults, $options));
    }

    /**
     * 建立 Key-Value 行（常用布局）
     *
     * @param string $key     鍵名
     * @param string $value   值
     * @param array  $options 額外選項
     * @return array Baseline Box 結構
     */
    public static function keyValue(string $key, string $value, array $options = []): array {
        $defaults = [
            'margin' => 'sm',
        ];

        return self::box('baseline', [
            self::label($key),
            self::content($value, ['flex' => 1, 'margin' => 'lg']),
        ], array_merge($defaults, $options));
    }
}
