/**
 * LIFF 登入歡迎 Toast 通知
 *
 * 透過 wp_localize_script 接收 lineHubWelcomeToast 設定。
 *
 * @package LineHub
 * @since 3.0.0
 */
(function(){
    document.addEventListener('DOMContentLoaded', function(){
        var config = window.lineHubWelcomeToast || {};
        var displayName = config.displayName || '';
        if (!displayName) return;

        var d = document.createElement('div');
        d.id = 'lineHubToast';
        d.innerHTML = config.message.replace('{name}', '<strong>' + displayName + '</strong>');
        d.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:999999;background:#06C755;color:#fff;text-align:center;padding:12px 16px;font-size:15px;font-weight:500;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;transform:translateY(-100%);transition:transform .3s ease;';
        document.body.insertBefore(d, document.body.firstChild);
        setTimeout(function(){ d.style.transform = 'translateY(0)'; }, 300);
        setTimeout(function(){ d.style.transform = 'translateY(-100%)'; }, 4000);
        setTimeout(function(){ d.remove(); }, 4500);
        document.cookie = 'line_hub_welcome=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    });
})();
