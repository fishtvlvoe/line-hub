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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .email-container {
            background: #fff;
            border-radius: 16px;
            padding: 32px 24px;
            max-width: 360px;
            width: 100%;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .profile-section {
            margin-bottom: 24px;
        }
        .profile-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            margin: 0 auto 12px;
            overflow: hidden;
            background: #e0e0e0;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: #06C755;
            background: #e8f8ee;
            padding: 4px 10px;
            border-radius: 12px;
        }
        .form-section {
            text-align: left;
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }
        .form-hint {
            font-size: 13px;
            color: #888;
            margin-bottom: 16px;
            text-align: center;
            line-height: 1.5;
        }
        .form-input {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
            -webkit-appearance: none;
        }
        .form-input:focus {
            border-color: #06C755;
        }
        .form-input.has-error {
            border-color: #e74c3c;
        }
        .form-error {
            color: #e74c3c;
            font-size: 13px;
            margin-top: 8px;
            display: <?php echo !empty($error) ? 'block' : 'none'; ?>;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            border: none;
            margin-bottom: 10px;
            transition: opacity 0.2s;
        }
        .btn:active { opacity: 0.8; }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-primary {
            background: #06C755;
            color: #fff;
        }
        .btn-skip {
            background: transparent;
            color: #999;
            font-size: 13px;
            font-weight: 400;
            padding: 8px;
            margin-bottom: 0;
        }
        .btn-skip:hover {
            color: #666;
        }
    </style>
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
                <div class="form-error" id="emailError"><?php echo esc_html($error); ?></div>
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

    <script>
    (function(){
        var form = document.getElementById('emailForm');
        var input = document.getElementById('email');
        var btn = document.getElementById('submitBtn');
        var errorEl = document.getElementById('emailError');

        // 即時驗證
        input.addEventListener('input', function(){
            input.classList.remove('has-error');
            errorEl.style.display = 'none';
        });

        // 表單提交時的 loading 狀態
        form.addEventListener('submit', function(e){
            var clickedBtn = e.submitter;

            // 如果是跳過按鈕，不驗證 email
            if (clickedBtn && clickedBtn.name === 'skip_email') {
                input.removeAttribute('required');
                btn.disabled = true;
                clickedBtn.textContent = '處理中...';
                return;
            }

            // 驗證 email
            if (!input.value.trim()) {
                e.preventDefault();
                input.classList.add('has-error');
                errorEl.textContent = '請輸入 Email 信箱';
                errorEl.style.display = 'block';
                input.focus();
                return;
            }

            btn.disabled = true;
            btn.textContent = '建立中...';
        });
    })();
    </script>
</body>
</html>
