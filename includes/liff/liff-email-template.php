<?php
/**
 * LIFF Email 收集表單模板
 *
 * 在 LIFF 登入後、新用戶建立前顯示
 * 讓用戶填寫 Email 以建立 WordPress 帳號
 *
 * 變數：
 * - $token        string 暫存 token（用於取回 LINE 資料）
 * - $display_name string LINE 顯示名稱
 * - $picture_url  string LINE 頭像 URL
 * - $redirect     string 登入後重定向 URL
 * - $nonce        string WordPress nonce
 * - $error        string 錯誤訊息（可選）
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
    <title><?php echo esc_html(get_bloginfo('name')); ?> - 完成註冊</title>
    <?php wp_head(); ?>
</head>
<body>
    <div class="email-container">
        <!-- LINE Profile -->
        <div class="profile-section">
            <?php if (!empty($picture_url)) : ?>
            <div class="profile-avatar">
                <img src="<?php echo esc_url($picture_url); ?>" alt="">
            </div>
            <?php endif; ?>
            <div class="profile-name"><?php echo esc_html($display_name); ?></div>
            <span class="profile-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#06C755"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                LINE 帳號已驗證
            </span>
        </div>

        <p class="form-hint">
            請輸入您的 Email 以完成註冊<br>
            用於接收訂單通知和出貨資訊
        </p>

        <!-- Email 表單 -->
        <form id="emailForm" method="POST" action="">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="liff_email_token" value="<?php echo esc_attr($token); ?>">
            <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>">

            <div class="form-section">
                <label class="form-label" for="email">Email 信箱</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-input <?php echo !empty($error) ? 'has-error' : ''; ?>"
                    placeholder="example@email.com"
                    autocomplete="email"
                    inputmode="email"
                    required
                >
                <div class="form-error <?php echo !empty($error) ? 'is-visible' : ''; ?>" id="emailError"><?php echo esc_html($error); ?></div>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">
                建立帳號
            </button>

            <!-- 跳過 Email -->
            <button type="submit" name="skip_email" value="1" class="btn btn-skip">
                略過，之後再設定
            </button>
        </form>
    </div>

    <?php wp_footer(); ?>
</body>
</html>
