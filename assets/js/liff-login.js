/* global liff, lineHubLiffConfig */
(function() {
    'use strict';

    var LIFF_ID = lineHubLiffConfig.liffId;
    var LIFF_REDIRECT = lineHubLiffConfig.redirect;
    var DEBUG_MODE = new URLSearchParams(window.location.search).has('debug');

    // Debug 模式時顯示面板
    if (DEBUG_MODE) {
        document.getElementById('liffDebug').style.display = 'block';
    }

    // Debug logger
    function dbg(msg, type) {
        type = type || 'info';
        console.log('[LIFF]', msg);
        if (!DEBUG_MODE) return;
        var cls = 'dbg-' + type;
        var el = document.getElementById('dbgLog');
        var ts = new Date().toLocaleTimeString('zh-TW', {hour12:false});
        el.innerHTML += '<div class="' + cls + '">[' + ts + '] ' + msg + '</div>';
        el.parentElement.scrollTop = el.parentElement.scrollHeight;
    }

    // 頁面載入時記錄環境
    dbg('PHP redirect = ' + LIFF_REDIRECT, 'info');
    dbg('URL = ' + window.location.href, 'info');
    dbg('sessionStorage.liff_redirect = ' + sessionStorage.getItem('liff_redirect'), 'info');

    function updateStatus(text) {
        document.getElementById('liffStatus').textContent = text;
    }

    function showError(text) {
        document.getElementById('liffSpinner').style.display = 'none';
        document.getElementById('liffError').style.display = 'block';
        document.getElementById('liffError').textContent = text;
        document.getElementById('liffRetry').style.display = 'block';
        updateStatus('');
    }

    function showSuccess() {
        document.getElementById('liffSpinner').style.display = 'none';
        document.getElementById('liffSuccess').style.display = 'flex';
        updateStatus('登入成功，正在跳轉...');
    }

    function showProfile(name, pictureUrl) {
        if (name) {
            document.getElementById('liffName').textContent = name;
            if (pictureUrl) {
                document.getElementById('liffAvatar').src = pictureUrl;
            }
            document.getElementById('liffProfile').style.display = 'block';
        }
    }

    async function startLiff() {
        // Reset UI
        document.getElementById('liffSpinner').style.display = 'block';
        document.getElementById('liffError').style.display = 'none';
        document.getElementById('liffRetry').style.display = 'none';
        document.getElementById('liffSuccess').style.display = 'none';
        updateStatus('正在初始化...');

        try {
            // Step 1: Initialize LIFF
            dbg('Step 1: liff.init({ liffId: ' + LIFF_ID + ' })...', 'info');
            await liff.init({ liffId: LIFF_ID });
            dbg('Step 1: OK. isInClient=' + liff.isInClient() + ', OS=' + liff.getOS(), 'ok');
            updateStatus('正在連線 LINE...');

            // Step 2: Check login status
            var loggedIn = liff.isLoggedIn();
            dbg('Step 2: isLoggedIn = ' + loggedIn, loggedIn ? 'ok' : 'warn');

            if (!loggedIn) {
                updateStatus('正在導向 LINE 登入...');
                // 儲存 redirect 到 sessionStorage，避免 LIFF login 過程中遺失
                if (LIFF_REDIRECT) {
                    sessionStorage.setItem('liff_redirect', LIFF_REDIRECT);
                    dbg('Saved redirect to sessionStorage: ' + LIFF_REDIRECT, 'info');
                }
                var loginUri = window.location.origin + '/line-hub/liff/';
                dbg('Calling liff.login({ redirectUri: ' + loginUri + ' })', 'info');
                liff.login({ redirectUri: loginUri });
                return;
            }

            // Step 3: Get profile
            dbg('Step 3: liff.getProfile()...', 'info');
            updateStatus('正在取得用戶資料...');
            var profile = await liff.getProfile();
            dbg('Step 3: OK. name=' + profile.displayName, 'ok');
            showProfile(profile.displayName, profile.pictureUrl);

            // Step 4: Get access token
            var accessToken = liff.getAccessToken();
            dbg('Step 4: accessToken = ' + (accessToken ? accessToken.substring(0,10) + '...' : 'NULL'), accessToken ? 'ok' : 'err');
            if (!accessToken) {
                showError('無法取得 Access Token');
                dbg('FAILED: No access token', 'err');
                return;
            }

            // Step 5: Check friendship status
            var isFriend = false;
            try {
                dbg('Step 5: getFriendship()...', 'info');
                var friendship = await liff.getFriendship();
                isFriend = friendship.friendFlag;
                dbg('Step 5: friend=' + isFriend, 'ok');
            } catch (e) {
                dbg('Step 5: getFriendship failed: ' + (e.message || e), 'warn');
            }

            // Step 6: Submit to server
            updateStatus('正在建立帳號...');
            document.getElementById('liffToken').value = accessToken;
            document.getElementById('liffIsFriend').value = isFriend ? '1' : '0';

            // 從 sessionStorage 恢復 redirect（LIFF login 過程中 PHP 端可能遺失）
            var savedRedirect = sessionStorage.getItem('liff_redirect');
            var formRedirect = document.querySelector('#liffForm input[name="redirect"]').value;
            dbg('Step 6: form.redirect = ' + formRedirect, 'info');
            dbg('Step 6: sessionStorage.redirect = ' + savedRedirect, 'info');

            if (savedRedirect) {
                var redirectInput = document.querySelector('#liffForm input[name="redirect"]');
                if (redirectInput && (!redirectInput.value || redirectInput.value === window.location.origin + '/')) {
                    redirectInput.value = savedRedirect;
                    dbg('Step 6: Restored redirect from sessionStorage: ' + savedRedirect, 'ok');
                }
                sessionStorage.removeItem('liff_redirect');
            }

            var finalRedirect = document.querySelector('#liffForm input[name="redirect"]').value;
            dbg('Step 6: FINAL redirect = ' + finalRedirect, 'ok');
            dbg('Step 6: Submitting form...', 'info');

            showSuccess();
            document.getElementById('liffForm').submit();

        } catch (err) {
            console.error('LIFF Error:', err);
            var msg = err.message || '未知錯誤';
            dbg('CATCH ERROR: ' + msg, 'err');
            dbg('Error stack: ' + (err.stack || 'N/A'), 'err');

            // Token 過期或被撤銷 → 清除快取，重新登入
            if (msg.indexOf('revoked') !== -1 || msg.indexOf('expired') !== -1 || msg.indexOf('invalid') !== -1) {
                updateStatus('登入已過期，正在重新登入...');
                dbg('Token expired/revoked, re-login...', 'warn');
                if (LIFF_REDIRECT) {
                    sessionStorage.setItem('liff_redirect', LIFF_REDIRECT);
                }
                liff.logout();
                setTimeout(function() {
                    liff.login({ redirectUri: window.location.origin + '/line-hub/liff/' });
                }, 500);
                return;
            }

            showError('LIFF 初始化失敗：' + msg);
        }
    }

    // Auto-start
    dbg('Auto-start startLiff()...', 'info');
    startLiff();
}());
