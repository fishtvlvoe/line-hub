/* global lineHubUsersColumn */
document.addEventListener('click', function(e) {
    'use strict';
    var btn = e.target.closest('.line-hub-admin-unbind');
    if (!btn) return;

    if (!confirm(btn.dataset.confirm)) return;

    btn.disabled = true;
    btn.textContent = '...';

    fetch(btn.dataset.restUrl, {
        method: 'DELETE',
        headers: {
            'X-WP-Nonce': btn.dataset.nonce,
            'Content-Type': 'application/json',
        },
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var cell = btn.closest('td');
            cell.innerHTML = '<span class="line-hub-binding-none">\u2014</span>';
        } else {
            alert(data.message || 'Failed');
            btn.disabled = false;
            btn.textContent = lineHubUsersColumn.unlinkLabel;
        }
    })
    .catch(function() {
        alert('Network error');
        btn.disabled = false;
        btn.textContent = lineHubUsersColumn.unlinkLabel;
    });
});
