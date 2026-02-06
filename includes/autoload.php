<?php
/**
 * PSR-4 自動載入器
 *
 * @package LineHub
 */

namespace LineHub;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 自動載入類別
 *
 * @param string $class 類別名稱
 */
spl_autoload_register(function($class) {
    // 只處理 LineHub 命名空間
    $prefix = 'LineHub\\';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // 取得相對類別名稱
    $relative_class = substr($class, $len);

    // 將命名空間轉換為檔案路徑
    $file = LINE_HUB_PATH . 'includes/' . str_replace('\\', '/', $relative_class) . '.php';

    // 特殊處理：class-*.php 格式
    $parts = explode('/', $file);
    $last = array_pop($parts);
    $last = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $last));
    $last = 'class-' . $last;
    $parts[] = $last;
    $file = implode('/', $parts);

    // 如果檔案存在，載入
    if (file_exists($file)) {
        require_once $file;
    }
});
