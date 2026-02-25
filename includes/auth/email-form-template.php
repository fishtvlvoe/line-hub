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
    <?php $lh_ver = defined('LINE_HUB_VERSION') ? LINE_HUB_VERSION : '1.0.0'; ?>
    <link rel="stylesheet" href="<?php echo esc_url(plugins_url('assets/css/auth-email-form.css', dirname(dirname(__FILE__)))); ?>?ver=<?php echo esc_attr($lh_ver); ?>">
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
