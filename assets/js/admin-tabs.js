/**
 * LINE Hub Admin Tabs JavaScript
 *
 * Tab 切換視覺回饋、複製按鈕、Payload 展開
 *
 * @package LineHub
 * @since 1.0.0
 */

(function() {
    'use strict';

    function init() {
        // Tab 點擊視覺回饋（頁面導航前的瞬間 active 效果）
        document.querySelectorAll('.line-hub-tab a').forEach(function(tab) {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.line-hub-tab').forEach(function(t) {
                    t.classList.remove('active');
                });
                var parent = tab.closest('.line-hub-tab');
                if (parent) {
                    parent.classList.add('active');
                }
            });
        });

        // 複製按鈕（data-copy 屬性）
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.line-hub-copy-btn');
            if (btn && btn.dataset.copy) {
                navigator.clipboard.writeText(btn.dataset.copy).then(function() {
                    var original = btn.textContent;
                    btn.textContent = '已複製';
                    setTimeout(function() { btn.textContent = original; }, 1500);
                });
            }
        });

        // Webhook Payload 展開/收合（data-toggle-payload 屬性）
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-toggle-payload]');
            if (btn) {
                var id = btn.dataset.togglePayload;
                var el = document.getElementById('payload-' + id);
                if (el) {
                    el.style.display = el.style.display === 'none' ? 'block' : 'none';
                }
            }
        });
    }

    // DOM Ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
