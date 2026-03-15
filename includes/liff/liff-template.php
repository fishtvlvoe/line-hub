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
    <?php wp_head(); ?>
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

    <?php wp_footer(); ?>
</body>
</html>
