<?php
/**
 * LIFF 登入頁面模板
 *
 * 變數：
 * - $liff_id  string LIFF App ID
 * - $redirect string 登入後重定向 URL
 * - $nonce    string WordPress nonce
 *
 * @package LineHub
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - LINE 登入</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .liff-container {
            background: #fff;
            border-radius: 16px;
            padding: 40px 32px;
            max-width: 360px;
            width: 90%;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .liff-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            background: #06C755;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .liff-logo svg {
            width: 36px;
            height: 36px;
            fill: #fff;
        }
        .liff-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .liff-subtitle {
            font-size: 14px;
            color: #888;
            margin-bottom: 32px;
        }
        .liff-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #e0e0e0;
            border-top-color: #06C755;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .liff-status {
            font-size: 14px;
            color: #666;
            min-height: 20px;
        }
        .liff-error {
            color: #e74c3c;
            background: #fdf0ef;
            border-radius: 8px;
            padding: 12px 16px;
            margin-top: 16px;
            font-size: 13px;
            display: none;
        }
        .liff-retry {
            display: none;
            margin-top: 16px;
        }
        .liff-retry button {
            background: #06C755;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 32px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
        }
        .liff-retry button:active {
            background: #05a847;
        }
        .liff-profile {
            display: none;
            margin-bottom: 16px;
        }
        .liff-profile img {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            margin-bottom: 8px;
        }
        .liff-profile-name {
            font-size: 16px;
            font-weight: 500;
            color: #333;
        }
        .liff-success-icon {
            display: none;
            width: 48px;
            height: 48px;
            margin: 0 auto 12px;
            background: #06C755;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
        }
        .liff-success-icon svg {
            width: 24px;
            height: 24px;
            fill: #fff;
        }
        /* Debug panel */
        .liff-debug {
            background: #1a1a2e;
            color: #0f0;
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
            max-width: 360px;
            width: 90%;
            text-align: left;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.6;
            max-height: 300px;
            overflow-y: auto;
        }
        .liff-debug .dbg-title {
            color: #ff0;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .liff-debug .dbg-ok { color: #0f0; }
        .liff-debug .dbg-err { color: #f66; }
        .liff-debug .dbg-info { color: #6cf; }
        .liff-debug .dbg-warn { color: #fc6; }
    </style>
</head>
<body>
    <div class="liff-container">
        <!-- LINE Logo -->
        <div class="liff-logo">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 5.81 2 10.5c0 2.49 1.31 4.71 3.37 6.26.14.1.23.28.23.47l-.05 1.76c-.02.63.55 1.11 1.14.96l1.96-.52c.15-.04.31-.03.46.02.98.35 2.04.55 3.14.55h.5c-.03-.25-.05-.51-.05-.77 0-3.83 3.55-6.95 7.93-6.95.34 0 .68.02 1.01.06C21.17 5.36 17.02 2 12 2z"/></svg>
        </div>

        <h1 class="liff-title"><?php echo esc_html(get_bloginfo('name')); ?></h1>
        <p class="liff-subtitle">LINE 快速登入</p>

        <!-- Profile (shown after getting profile) -->
        <div class="liff-profile" id="liffProfile">
            <img id="liffAvatar" src="" alt="">
            <div class="liff-profile-name" id="liffName"></div>
        </div>

        <!-- Loading spinner -->
        <div class="liff-spinner" id="liffSpinner"></div>

        <!-- Success icon -->
        <div class="liff-success-icon" id="liffSuccess">
            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>

        <!-- Status message -->
        <div class="liff-status" id="liffStatus">正在初始化...</div>

        <!-- Error message -->
        <div class="liff-error" id="liffError"></div>

        <!-- Retry button -->
        <div class="liff-retry" id="liffRetry">
            <button onclick="startLiff()">重新嘗試</button>
        </div>

        <!-- Hidden form for POST -->
        <form id="liffForm" method="POST" action="" style="display:none;">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="liff_access_token" id="liffToken" value="">
            <input type="hidden" name="liff_is_friend" id="liffIsFriend" value="">
            <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>">
        </form>
    </div>

    <!-- Debug Panel（URL 加 ?debug=1 顯示）-->
    <div class="liff-debug" id="liffDebug" style="display:none;">
        <div class="dbg-title">LIFF Debug Log</div>
        <div id="dbgLog"></div>
    </div>

    <script src="https://static.line-scdn.net/liff/edge/versions/2.24.0/sdk.js"></script>
    <script>
        const LIFF_ID = <?php echo wp_json_encode($liff_id); ?>;
        const LIFF_REDIRECT = <?php echo wp_json_encode($redirect); ?>;
        const DEBUG_MODE = new URLSearchParams(window.location.search).has('debug');

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
                const profile = await liff.getProfile();
                dbg('Step 3: OK. name=' + profile.displayName, 'ok');
                showProfile(profile.displayName, profile.pictureUrl);

                // Step 4: Get access token
                const accessToken = liff.getAccessToken();
                dbg('Step 4: accessToken = ' + (accessToken ? accessToken.substring(0,10) + '...' : 'NULL'), accessToken ? 'ok' : 'err');
                if (!accessToken) {
                    showError('無法取得 Access Token');
                    dbg('FAILED: No access token', 'err');
                    return;
                }

                // Step 5: Check friendship status
                let isFriend = false;
                try {
                    dbg('Step 5: getFriendship()...', 'info');
                    const friendship = await liff.getFriendship();
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
    </script>
</body>
</html>
