/**
 * LINE Hub Admin Tabs JavaScript
 *
 * Tab 切換邏輯（純 Vanilla JS）
 *
 * @package LineHub
 * @since 1.0.0
 */

(function() {
    'use strict';

    /**
     * 初始化 Tab 系統
     */
    function initTabs() {
        const tabs = document.querySelectorAll('.line-hub-tab a');

        if (!tabs.length) {
            return;
        }

        // 監聽 Tab 點擊
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                // 如果是導航到其他頁面，允許預設行為
                // 這裡只是加上視覺回饋
                const parent = tab.closest('.line-hub-tab');

                // 移除所有 active 狀態
                document.querySelectorAll('.line-hub-tab').forEach(function(t) {
                    t.classList.remove('active');
                });

                // 加上當前 active 狀態
                if (parent) {
                    parent.classList.add('active');
                }
            });
        });

        // 根據 URL 參數設定當前 Tab
        const urlParams = new URLSearchParams(window.location.search);
        const currentTab = urlParams.get('tab') || 'getting-started';

        // 設定對應的 Tab 為 active
        tabs.forEach(function(tab) {
            const href = tab.getAttribute('href');
            if (href && href.includes('tab=' + currentTab)) {
                const parent = tab.closest('.line-hub-tab');
                if (parent) {
                    parent.classList.add('active');
                }
            }
        });
    }

    // DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabs);
    } else {
        initTabs();
    }
})();
