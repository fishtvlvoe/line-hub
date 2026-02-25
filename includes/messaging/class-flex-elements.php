<?php
/**
 * Flex Elements — LINE Flex Message 元素元件
 *
 * @package LineHub
 */

namespace LineHub\Messaging;

if (!defined('ABSPATH')) {
    exit;
}

class FlexElements {

    /**
     * 文字元件
     */
    public static function text(string $text, array $options = []): array {
        return array_merge(['type' => 'text', 'text' => $text], $options);
    }

    /**
     * 圖片元件
     */
    public static function image(string $url, array $options = []): array {
        return array_merge(['type' => 'image', 'url' => $url], $options);
    }

    /**
     * 按鈕元件
     */
    public static function button(string $label, string $action, array $options = []): array {
        $button = ['type' => 'button', 'action' => self::parseAction($action)];
        if (!isset($options['style'])) {
            $button['style'] = 'primary';
        }
        $button = array_merge($button, $options);
        if (!isset($button['action']['label'])) {
            $button['action']['label'] = $label;
        }
        return $button;
    }

    /**
     * 分隔線
     */
    public static function separator(array $options = []): array {
        return array_merge(['type' => 'separator'], $options);
    }

    /**
     * 空白間隔
     */
    public static function spacer(string $size = 'md'): array {
        return ['type' => 'spacer', 'size' => $size];
    }

    /**
     * 彈性空白
     */
    public static function filler(): array {
        return ['type' => 'filler'];
    }

    /**
     * Icon 元件
     */
    public static function icon(string $url, array $options = []): array {
        return array_merge(['type' => 'icon', 'url' => $url], $options);
    }

    /**
     * Hero 圖片區塊
     */
    public static function hero(string $imageUrl, array $options = []): array {
        return array_merge([
            'type' => 'image', 'url' => $imageUrl,
            'size' => 'full', 'aspectRatio' => '20:13', 'aspectMode' => 'cover',
        ], $options);
    }

    /**
     * 標題文字（常用樣式）
     */
    public static function heading(string $text, array $options = []): array {
        return self::text($text, array_merge([
            'weight' => 'bold', 'size' => 'xl', 'color' => '#111827',
        ], $options));
    }

    /**
     * 副標題文字
     */
    public static function subheading(string $text, array $options = []): array {
        return self::text($text, array_merge([
            'size' => 'md', 'color' => '#6b7280',
        ], $options));
    }

    /**
     * 標籤文字
     */
    public static function label(string $text, array $options = []): array {
        return self::text($text, array_merge([
            'size' => 'sm', 'color' => '#9ca3af', 'flex' => 0,
        ], $options));
    }

    /**
     * 內容文字
     */
    public static function content(string $text, array $options = []): array {
        return self::text($text, array_merge([
            'size' => 'sm', 'color' => '#111111', 'wrap' => true,
        ], $options));
    }

    /**
     * Key-Value 行
     */
    public static function keyValue(string $key, string $value, array $options = []): array {
        return FlexBuilder::box('baseline', [
            self::label($key),
            self::content($value, ['flex' => 1, 'margin' => 'lg']),
        ], array_merge(['margin' => 'sm'], $options));
    }

    /**
     * 解析動作參數（支援 URL/postback/message 簡化寫法）
     */
    public static function parseAction($action): array {
        if (is_array($action)) {
            return $action;
        }
        if (filter_var($action, FILTER_VALIDATE_URL)) {
            return ['type' => 'uri', 'uri' => $action];
        }
        if (strpos($action, 'postback:') === 0) {
            return ['type' => 'postback', 'data' => substr($action, 9)];
        }
        return ['type' => 'message', 'text' => $action];
    }
}
