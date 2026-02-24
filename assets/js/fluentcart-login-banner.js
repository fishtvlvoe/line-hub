/**
 * FluentCart 產品頁 LINE 登入提示橫幅注入
 *
 * 透過 wp_localize_script 接收 lineHubLoginBanner 設定。
 *
 * @package LineHub
 * @since 3.0.0
 */
(function(){
    document.addEventListener('DOMContentLoaded', function(){
        var config = window.lineHubLoginBanner || {};
        if (!config.bannerText || !config.loginUrl) return;

        var targets = [
            '.fcs_product_single',
            '.fluent-product-page',
            '.fc-product-single',
            '[data-fluent-cart-product]',
            '.entry-content'
        ];
        var container = null;
        for (var i = 0; i < targets.length; i++) {
            container = document.querySelector(targets[i]);
            if (container) break;
        }
        if (!container) return;

        var banner = document.createElement('div');
        banner.className = 'line-hub-login-banner';
        banner.innerHTML = '<span class="line-hub-login-banner-text">' + config.bannerText + '</span>' +
            '<a href="' + config.loginUrl + '" class="line-hub-login-btn">' +
            '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C6.48 2 2 5.81 2 10.5c0 2.49 1.31 4.71 3.37 6.26.14.1.23.28.23.47l-.05 1.76c-.02.63.55 1.11 1.14.96l1.96-.52c.15-.04.31-.03.46.02.98.35 2.04.55 3.14.55h.5c-.03-.25-.05-.51-.05-.77 0-3.83 3.55-6.95 7.93-6.95.34 0 .68.02 1.01.06C21.17 5.36 17.02 2 12 2z"/></svg>' +
            config.buttonText + '</a>';

        container.insertBefore(banner, container.firstChild);
    });
})();
