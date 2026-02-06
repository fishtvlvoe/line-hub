<?php
/**
 * Email 輸入表單模板
 *
 * 當 LINE ID Token 沒有 Email 時，在回調頁面顯示此表單
 * 讓用戶手動輸入 Email 完成註冊，或選擇重新授權 Email 權限
 *
 * 可用變數：
 * @var string $temp_key    暫存資料的 key（由 LoginService::showEmailForm 傳入）
 * @var array  $user_data   LINE 用戶資料
 * @var string $reauth_url  強制重新授權 URL（AUTH-03）
 *
 * @package LineHub
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e('Complete Registration', 'line-hub'); ?></title>
    <?php wp_head(); ?>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .line-hub-email-form {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
            padding: 40px 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
        }
        .line-hub-email-form h2 {
            margin: 0 0 10px;
            color: #1f2937;
            font-size: 22px;
            font-weight: 600;
        }
        .line-hub-email-form .description {
            color: #6b7280;
            margin: 0 0 25px;
            font-size: 15px;
            line-height: 1.5;
        }
        .line-hub-email-form label {
            display: block;
            text-align: left;
            margin-bottom: 6px;
            color: #374151;
            font-size: 14px;
            font-weight: 500;
        }
        .line-hub-email-form input[type="email"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .line-hub-email-form input[type="email"]:focus {
            outline: none;
            border-color: #06C755;
            box-shadow: 0 0 0 3px rgba(6, 199, 85, 0.1);
        }
        .line-hub-email-form input[type="email"]::placeholder {
            color: #9ca3af;
        }
        .line-hub-email-form button {
            width: 100%;
            padding: 14px 24px;
            background: #06C755;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .line-hub-email-form button:hover {
            background: #05b34d;
        }
        .line-hub-email-form button:active {
            background: #049b44;
        }
        .line-hub-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 20px;
            object-fit: cover;
            border: 3px solid #e5e7eb;
        }
        .line-hub-reauth-link {
            display: block;
            margin-top: 20px;
            color: #6b7280;
            font-size: 14px;
            text-decoration: none;
            transition: color 0.2s;
        }
        .line-hub-reauth-link:hover {
            color: #06C755;
            text-decoration: underline;
        }
        .line-hub-site-name {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 12px;
        }
        @media (max-width: 480px) {
            .line-hub-email-form {
                padding: 30px 20px;
            }
            .line-hub-email-form h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="line-hub-email-form">
        <?php if (!empty($user_data['pictureUrl'])): ?>
            <img src="<?php echo esc_url($user_data['pictureUrl']); ?>"
                 alt="<?php esc_attr_e('Profile picture', 'line-hub'); ?>"
                 class="line-hub-avatar">
        <?php endif; ?>

        <h2><?php esc_html_e('Almost Done!', 'line-hub'); ?></h2>

        <?php if (!empty($user_data['displayName'])): ?>
            <p class="description"><?php printf(
                /* translators: %s: user display name from LINE */
                esc_html__('Hi %s, please enter your email to complete registration.', 'line-hub'),
                esc_html($user_data['displayName'])
            ); ?></p>
        <?php else: ?>
            <p class="description"><?php esc_html_e('Please enter your email to complete registration.', 'line-hub'); ?></p>
        <?php endif; ?>

        <form method="POST" action="<?php echo esc_url(home_url('/line-hub/auth/email-submit')); ?>">
            <?php wp_nonce_field('line_hub_email_submit', '_wpnonce'); ?>
            <input type="hidden" name="temp_key" value="<?php echo esc_attr($temp_key); ?>">

            <label for="line-hub-email"><?php esc_html_e('Email Address', 'line-hub'); ?></label>
            <input type="email"
                   name="email"
                   id="line-hub-email"
                   required
                   autocomplete="email"
                   placeholder="your@email.com">

            <button type="submit"><?php esc_html_e('Continue', 'line-hub'); ?></button>
        </form>

        <!-- AUTH-03: Force re-authorization link -->
        <a href="<?php echo esc_url($reauth_url); ?>" class="line-hub-reauth-link">
            <?php esc_html_e('Re-authorize with LINE to grant email access', 'line-hub'); ?>
        </a>

        <p class="line-hub-site-name"><?php echo esc_html(get_bloginfo('name')); ?></p>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
