(function() {
    'use strict';
    var form = document.getElementById('emailForm');
    var input = document.getElementById('email');
    var btn = document.getElementById('submitBtn');
    var errorEl = document.getElementById('emailError');

    if (!form || !input || !btn || !errorEl) return;

    // 即時驗證
    input.addEventListener('input', function() {
        input.classList.remove('has-error');
        errorEl.style.display = 'none';
    });

    // 表單提交時的 loading 狀態
    form.addEventListener('submit', function(e) {
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
}());
