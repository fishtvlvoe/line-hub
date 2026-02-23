<?php
/**
 * Tab 抽象基底類別
 *
 * 所有後台設定頁 Tab 的共用介面和輔助方法。
 *
 * @package LineHub\Admin\Tabs
 * @since 2.0.0
 */

namespace LineHub\Admin\Tabs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AbstractTab
 *
 * 定義 Tab 必須實作的方法：
 * - get_slug()  — URL slug
 * - get_label() — 顯示標籤
 * - render()    — 渲染內容
 *
 * 可選覆寫：
 * - save()      — 處理表單儲存（預設 return true）
 */
abstract class AbstractTab {

    /**
     * 取得 Tab URL slug
     *
     * @return string
     */
    abstract public function get_slug(): string;

    /**
     * 取得 Tab 顯示標籤
     *
     * @return string
     */
    abstract public function get_label(): string;

    /**
     * 渲染 Tab 內容
     *
     * @return void
     */
    abstract public function render(): void;

    /**
     * 處理表單儲存
     *
     * 子類別覆寫此方法來儲存 Tab 專屬欄位。
     * 無 form 的 Tab（如嚮導、Webhook）不需覆寫。
     *
     * @param array $post_data $_POST 資料
     * @return bool 儲存是否成功
     */
    public function save(array $post_data): bool {
        return true;
    }

    /**
     * 取得 View 檔案完整路徑
     *
     * @param string $filename View 檔案名稱（例如 'tab-wizard.php'）
     * @return string 完整檔案路徑
     */
    protected function get_view_path(string $filename): string {
        return dirname(__DIR__) . '/views/' . $filename;
    }
}
