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

    // 將命名空間轉換為檔案路徑（目錄名稱小寫）
    $parts = explode('\\', $relative_class);

    // 轉換目錄名稱為小寫（除了最後一個類別名稱）
    for ($i = 0; $i < count($parts) - 1; $i++) {
        $parts[$i] = strtolower($parts[$i]);
    }

    // 轉換類別名稱為 class-kebab-case.php 格式
    $class_name = array_pop($parts);
    $class_name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name));
    $class_name = 'class-' . $class_name . '.php';

    // 組合完整路徑
    $file = LINE_HUB_PATH . 'includes/';
    if (!empty($parts)) {
        $file .= implode('/', $parts) . '/';
    }
    $file .= $class_name;

    // 如果檔案存在，載入
    if (file_exists($file)) {
        require_once $file;
    }
});
