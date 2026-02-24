<?php
/**
 * Flex Message Builder — 容器元件
 *
 * 元素元件（text, image, button 等）已移至 FlexElements
 *
 * @package LineHub
 */

namespace LineHub\Messaging;

if (!defined('ABSPATH')) {
    exit;
}

class FlexBuilder {

    /**
     * 建立 Bubble 容器
     */
    public static function bubble(array $body, array $header = [], array $footer = [], array $hero = [], array $styles = []): array {
        $bubble = ['type' => 'bubble', 'body' => $body];

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
     * 建立 Carousel 容器
     */
    public static function carousel(array $bubbles): array {
        return ['type' => 'carousel', 'contents' => $bubbles];
    }

    /**
     * 建立 Box 容器
     */
    public static function box(string $layout, array $contents, array $options = []): array {
        return array_merge([
            'type' => 'box', 'layout' => $layout, 'contents' => $contents,
        ], $options);
    }

    /**
     * 包裝成完整的 Flex Message
     */
    public static function flexMessage(string $altText, array $contents): array {
        return ['type' => 'flex', 'altText' => $altText, 'contents' => $contents];
    }
}
