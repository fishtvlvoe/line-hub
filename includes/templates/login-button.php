<?php
/**
 * LINE 登入按鈕模板
 *
 * 變數：
 * - $login_url       string  登入 URL（LIFF 或 OAuth）
 * - $button_text     string  按鈕文字
 * - $style           string  樣式模式：'button' | 'banner'
 * - $banner_text     string  橫幅說明文字（僅 banner 模式）
 * - $class           string  額外 CSS class
 * - $is_line_browser bool    是否在 LINE 內部瀏覽器（預留供模板判斷）
 *
 * @package LineHub
 */

if (!defined('ABSPATH')) {
    exit;
}

$button_text = $button_text ?? __('Log in with LINE', 'line-hub');
$style = $style ?? 'button';
$class = $class ?? '';
$banner_text = $banner_text ?? __('Log in to track orders and receive shipping notifications.', 'line-hub');
$is_line_browser = $is_line_browser ?? false;

// LINE logo SVG
$line_icon = '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 5.81 2 10.5c0 2.49 1.31 4.71 3.37 6.26.14.1.23.28.23.47l-.05 1.76c-.02.63.55 1.11 1.14.96l1.96-.52c.15-.04.31-.03.46.02.98.35 2.04.55 3.14.55h.5c-.03-.25-.05-.51-.05-.77 0-3.83 3.55-6.95 7.93-6.95.34 0 .68.02 1.01.06C21.17 5.36 17.02 2 12 2z"/></svg>';

if ($style === 'banner') : ?>
    <div class="line-hub-login-banner <?php echo esc_attr($class); ?>">
        <span class="line-hub-login-banner-text"><?php echo esc_html($banner_text); ?></span>
        <a href="<?php echo esc_url($login_url); ?>" class="line-hub-login-btn" style="color:#fff;text-decoration:none;">
            <?php echo $line_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo esc_html($button_text); ?>
        </a>
    </div>
<?php else : ?>
    <div class="line-hub-login-center <?php echo esc_attr($class); ?>">
        <a href="<?php echo esc_url($login_url); ?>" class="line-hub-login-btn" style="color:#fff;text-decoration:none;">
            <?php echo $line_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php echo esc_html($button_text); ?>
        </a>
    </div>
<?php endif; ?>
