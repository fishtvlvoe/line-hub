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
<h3>登入按鈕</h3>
<table class="form-table">
    <tr>
        <th scope="row">
            <label for="login_button_text">按鈕文字</label>
        </th>
        <td>
            <input type="text" id="login_button_text"
                   name="login_button_text"
                   value="<?php echo esc_attr($settings_general['login_button_text'] ?? '用 LINE 帳號登入'); ?>"
                   class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row">按鈕大小</th>
        <td>
            <?php
            $size_options = ['small' => '小', 'medium' => '中', 'large' => '大'];
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
        <th scope="row">顯示位置</th>
        <td>
            <?php
            $positions = $settings_general['login_button_positions'] ?? [];
            $position_options = [
                'wp_login'            => 'WordPress 登入頁',
                'fluentcart_checkout' => 'FluentCart 結帳頁（未登入時顯示）',
                'fluent_community'    => 'FluentCommunity 登入表單',
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
                勾選後，LINE 登入按鈕會自動顯示在對應位置（僅未登入時）
            </p>
        </td>
    </tr>
</table>

<!-- 短代碼嵌入 -->
<h3>按鈕嵌入（短代碼）</h3>
<table class="form-table">
    <tr>
        <th scope="row">短代碼</th>
        <td>
            <code class="lh-code-display lh-code-display-lg">
                [line_hub_login]
            </code>
            <button type="button" class="button button-small line-hub-copy-btn lh-ml-8"
                    data-copy='[line_hub_login]'>複製</button>
            <p class="description">
                在任何頁面或文章中貼上此短代碼，即可顯示 LINE 登入按鈕。
            </p>
        </td>
    </tr>
    <tr>
        <th scope="row">自訂參數</th>
        <td>
            <code class="lh-code-display lh-code-display-sm">
                [line_hub_login text="立即登入" size="large" redirect="/my-account"]
            </code>
            <button type="button" class="button button-small line-hub-copy-btn lh-ml-8"
                    data-copy='[line_hub_login text="立即登入" size="large" redirect="/my-account"]'>複製</button>
            <p class="description">
                可選參數：<code>text</code>（按鈕文字）、<code>size</code>（small / medium / large）、<code>redirect</code>（登入後跳轉 URL）
            </p>
        </td>
    </tr>
</table>
