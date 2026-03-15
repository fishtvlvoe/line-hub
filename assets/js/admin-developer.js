/* global lineHubDev, ajaxurl */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    var testBtn = document.getElementById('lh-test-openclaw-btn');
    var resultDiv = document.getElementById('lh-test-openclaw-result');

    if (!testBtn) return;

    testBtn.addEventListener('click', function(e) {
        e.preventDefault();

        var url = document.getElementById('openclaw_webhook_url').value;
        var token = document.getElementById('openclaw_webhook_token').value;

        if (!url || !token) {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="notice notice-error"><p>' + lineHubDev.i18n.urlTokenRequired + '</p></div>';
            return;
        }

        testBtn.disabled = true;
        testBtn.innerText = lineHubDev.i18n.testing;

        var formData = new FormData();
        formData.append('action', 'line_hub_test_openclaw');
        formData.append('nonce', lineHubDev.nonce);
        formData.append('url', url);
        formData.append('token', token);

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            resultDiv.style.display = 'block';
            if (data.success) {
                resultDiv.innerHTML = '<div class="notice notice-success"><p>' + data.data.message + '</p></div>';
            } else {
                resultDiv.innerHTML = '<div class="notice notice-error"><p>' + data.data.message + '</p></div>';
            }
        })
        .catch(function(err) {
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = '<div class="notice notice-error"><p>' + lineHubDev.i18n.requestFailed + ': ' + err.message + '</p></div>';
        })
        .finally(function() {
            testBtn.disabled = false;
            testBtn.innerText = lineHubDev.i18n.testConnection;
        });
    });
});
