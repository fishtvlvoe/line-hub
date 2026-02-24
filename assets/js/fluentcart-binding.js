/**
 * FluentCart 客戶入口 LINE 解綁互動邏輯
 *
 * 透過 wp_localize_script 接收 lineHubFcBinding 設定。
 *
 * @package LineHub
 * @since 3.0.0
 */
(function(){
    var btn = document.getElementById('lhFcUnbindBtn');
    if (!btn) return;

    var i18n = window.lineHubFcBinding || {};

    btn.addEventListener('click', function(){
        if (!confirm(i18n.confirmUnbind || 'Confirm?')) return;

        var el = this;
        var status = document.getElementById('lhFcStatus');
        el.disabled = true;
        el.textContent = i18n.processing || '...';

        fetch(el.dataset.restUrl, {
            method: 'DELETE',
            headers: { 'X-WP-Nonce': el.dataset.nonce, 'Content-Type': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (data.success) {
                status.className = 'lh-fc-status';
                status.style.cssText = 'display:block;background:#dcfce7;color:#166534;';
                status.textContent = data.message || i18n.unbindSuccess || '';
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                status.className = 'lh-fc-status';
                status.style.cssText = 'display:block;background:#fef2f2;color:#991b1b;';
                status.textContent = data.message || i18n.unbindFail || '';
                el.disabled = false;
                el.textContent = i18n.unbindLabel || '';
            }
        })
        .catch(function(){
            status.style.cssText = 'display:block;background:#fef2f2;color:#991b1b;';
            status.textContent = i18n.networkError || '';
            el.disabled = false;
            el.textContent = i18n.unbindLabel || '';
        });
    });
})();
