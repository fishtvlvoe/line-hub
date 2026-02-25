<?php
/**
 * Partial: 登入按鈕設定 + 短代碼嵌入
 *
 * 母模板：tab-login-settings.php
 * 共用變數：$settings_general
 *
 * @package LineHub\Admin
 */
?>

<!-- 登入按鈕 -->
<h3><?php esc_html_e('Login Button', 'line-hub'); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row">
            <label for="login_button_text"><?php esc_html_e('Button Text', 'line-hub'); ?></label>
        </th>
        <td>
            <input type="text" id="login_button_text"
                   name="login_button_text"
                   value="<?php echo esc_attr($settings_general['login_button_text'] ?? __('Log in with LINE', 'line-hub')); ?>"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Button Size', 'line-hub'); ?></th>
        <td>
            <?php
            $size_options = [
                'small'  => __('Small', 'line-hub'),
                'medium' => __('Medium', 'line-hub'),
                'large'  => __('Large', 'line-hub'),
            ];
            $current_size = $settings_general['login_button_size'] ?? 'medium';
            foreach ($size_options as $value => $label) :
            ?>
                <label class="<?php echo $value !== 'small' ? 'lh-radio-inline-spaced' : ''; ?>">
                    <input type="radio" name="login_button_size"
                           value="<?php echo esc_attr($value); ?>"
                           <?php checked($current_size, $value); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Display Positions', 'line-hub'); ?></th>
        <td>
            <?php
            $positions = $settings_general['login_button_positions'] ?? [];
            $position_options = [
                'wp_login'            => __('WordPress login page', 'line-hub'),
                'fluentcart_checkout' => __('FluentCart checkout page (shown when not logged in)', 'line-hub'),
                'fluent_community'    => __('FluentCommunity login form', 'line-hub'),
            ];
            foreach ($position_options as $value => $label) :
            ?>
                <label class="lh-label-block-md">
                    <input type="checkbox"
                           name="login_button_positions[]"
                           value="<?php echo esc_attr($value); ?>"
                           <?php checked(in_array($value, (array) $positions, true)); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
            <p class="description">
                <?php esc_html_e('When checked, the LINE login button will automatically appear at the corresponding position (only when not logged in)', 'line-hub'); ?>
            </p>
        </td>
    </tr>
</table>

<!-- 短代碼嵌入 -->
<h3><?php esc_html_e('Button Embed (Shortcode)', 'line-hub'); ?></h3>
<table class="form-table">
    <tr>
        <th scope="row"><?php esc_html_e('Shortcode', 'line-hub'); ?></th>
        <td>
            <code class="lh-code-display lh-code-display-lg">
                [line_hub_login]
            </code>
            <button type="button" class="button button-small line-hub-copy-btn lh-ml-8"
                    data-copy='[line_hub_login]'><?php esc_html_e('Copy', 'line-hub'); ?></button>
            <p class="description">
                <?php esc_html_e('Paste this shortcode in any page or post to display the LINE login button.', 'line-hub'); ?>
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Custom Parameters', 'line-hub'); ?></th>
        <td>
            <code class="lh-code-display lh-code-display-sm">
                [line_hub_login text="Login Now" size="large" redirect="/my-account"]
            </code>
            <button type="button" class="button button-small line-hub-copy-btn lh-ml-8"
                    data-copy='[line_hub_login text="Login Now" size="large" redirect="/my-account"]'><?php esc_html_e('Copy', 'line-hub'); ?></button>
            <p class="description">
                <?php
                printf(
                    /* translators: 1: text param, 2: size param, 3: redirect param */
                    esc_html__('Optional parameters: %1$s (button text), %2$s (small / medium / large), %3$s (redirect URL after login)', 'line-hub'),
                    '<code>text</code>',
                    '<code>size</code>',
                    '<code>redirect</code>'
                );
                ?>
            </p>
        </td>
    </tr>
</table>
