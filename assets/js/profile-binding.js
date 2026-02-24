/**
 * LINE Hub - 個人資料頁 LINE 綁定/解除綁定互動
 *
 * 依賴 wp_localize_script 傳入的 lineHubProfileBinding 物件：
 * - confirmUnbind: 確認解除綁定的提示文字
 * - processing: 處理中文字
 * - unbindSuccess: 解除成功文字
 * - unbindFail: 解除失敗文字
 * - unbindLabel: 解除綁定按鈕文字
 * - networkError: 網路錯誤文字
 *
 * @package LineHub
 * @since 1.0.0
 */
(function () {
    var unbindBtn = document.getElementById('lineHubUnbindBtn');
    if (!unbindBtn) return;

    var i18n = window.lineHubProfileBinding || {};

    unbindBtn.addEventListener('click', function () {
        if (!confirm(i18n.confirmUnbind || 'Are you sure?')) {
            return;
        }

        var btn = this;
        var statusMsg = document.getElementById('lineHubStatusMsg');
        btn.disabled = true;
        btn.textContent = i18n.processing || 'Processing...';

        fetch(btn.dataset.restUrl, {
            method: 'DELETE',
            headers: {
                'X-WP-Nonce': btn.dataset.nonce,
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    statusMsg.className = 'line-hub-status-msg line-hub-status-success';
                    statusMsg.textContent = data.message || i18n.unbindSuccess || 'Unbound';
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    statusMsg.className = 'line-hub-status-msg line-hub-status-error';
                    statusMsg.textContent = data.message || i18n.unbindFail || 'Failed';
                    btn.disabled = false;
                    btn.textContent = i18n.unbindLabel || 'Unbind';
                }
            })
            .catch(function () {
                statusMsg.className = 'line-hub-status-msg line-hub-status-error';
                statusMsg.textContent = i18n.networkError || 'Network error';
                btn.disabled = false;
                btn.textContent = i18n.unbindLabel || 'Unbind';
            });
    });
})();
