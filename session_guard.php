<?php
// Included on every protected page, right after <body>.
//
// 1) Fresh-session detection
// 2) CSRF token — exposed to JS + auto-injected into every POST form
// 3) PWA — manifest link, theme color, iOS support, service worker
?>
<script>
(function () {
    // ---------- 1) Fresh session detection ----------
    var persistent = <?php echo !empty($_SESSION['persistent']) ? 'true' : 'false'; ?>;
    var justLogged = <?php echo !empty($_SESSION['just_logged_in']) ? 'true' : 'false'; ?>;
    var hasMarker  = sessionStorage.getItem('shelflife_active') === '1';

    if (justLogged) {
        sessionStorage.setItem('shelflife_active', '1');
        <?php unset($_SESSION['just_logged_in']); ?>
    } else if (!persistent && !hasMarker) {
        window.location.replace('force_logout.php');
        return;
    }

    // ---------- 2) CSRF ----------
    var token = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    window.SHELFLIFE_CSRF = token;

    function injectCSRF() {
        document.querySelectorAll('form').forEach(function (f) {
            var method = (f.getAttribute('method') || 'get').toLowerCase();
            if (method !== 'post') return;
            if (f.querySelector('input[name="csrf_token"]')) return;
            var i = document.createElement('input');
            i.type  = 'hidden';
            i.name  = 'csrf_token';
            i.value = token;
            f.appendChild(i);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectCSRF);
    } else {
        injectCSRF();
    }

    // ---------- Toast system ----------
    // Creates a floating notification instead of the ugly native alert().
    // Also overrides window.alert() so existing code auto-upgrades.

    if (!document.getElementById('sl-toast-styles')) {
        var slStyles = document.createElement('style');
        slStyles.id = 'sl-toast-styles';
        slStyles.textContent = `
            .sl-toast-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
                max-width: calc(100vw - 40px);
            }
            @media (max-width: 768px) {
                .sl-toast-container {
                    top: 12px;
                    right: 12px;
                    left: 12px;
                    max-width: none;
                    align-items: center;
                }
            }
            .sl-toast {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 12px 16px;
                background: #ffffff;
                border: 1px solid #ebe7df;
                border-radius: 12px;
                box-shadow: 0 8px 28px rgba(26,31,28,0.16);
                font-family: 'Inter', system-ui, sans-serif;
                font-size: 13.5px;
                font-weight: 500;
                color: #2a302c;
                line-height: 1.4;
                pointer-events: auto;
                max-width: 380px;
                min-width: 260px;
                animation: slToastIn 0.28s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            .sl-toast.closing {
                animation: slToastOut 0.22s ease forwards;
            }
            .sl-toast .sl-toast-icon {
                flex-shrink: 0;
                width: 22px;
                height: 22px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-top: 0;
            }
            .sl-toast .sl-toast-icon svg {
                width: 13px;
                height: 13px;
                stroke-width: 2.5;
                fill: none;
                stroke: currentColor;
                stroke-linecap: round;
                stroke-linejoin: round;
            }
            .sl-toast .sl-toast-msg {
                flex-grow: 1;
                word-break: break-word;
                padding-top: 1px;
            }
            .sl-toast .sl-toast-close {
                background: none;
                border: none;
                cursor: pointer;
                padding: 2px;
                margin-left: 2px;
                color: #8a9088;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
                border-radius: 4px;
            }
            .sl-toast .sl-toast-close:hover { color: #2a302c; background: #f5f3ee; }
            .sl-toast .sl-toast-close svg {
                width: 14px; height: 14px;
                stroke-width: 2.5; fill: none; stroke: currentColor;
                stroke-linecap: round; stroke-linejoin: round;
            }
            .sl-toast.success { border-left: 3px solid #2d5a3d; }
            .sl-toast.success .sl-toast-icon { background: #f2f6f1; color: #1a3823; }
            .sl-toast.error { border-left: 3px solid #b54a3f; }
            .sl-toast.error .sl-toast-icon { background: #fbeeec; color: #8a3730; }
            .sl-toast.warning { border-left: 3px solid #c77b3f; }
            .sl-toast.warning .sl-toast-icon { background: #fdf3e8; color: #9c5a26; }
            .sl-toast.info { border-left: 3px solid #4a7c9e; }
            .sl-toast.info .sl-toast-icon { background: #eaf2fa; color: #2d5a7f; }
            @keyframes slToastIn {
                from { transform: translateX(120%); opacity: 0; }
                to   { transform: translateX(0); opacity: 1; }
            }
            @keyframes slToastOut {
                from { transform: translateX(0); opacity: 1; }
                to   { transform: translateX(120%); opacity: 0; }
            }
            @media (max-width: 768px) {
                .sl-toast {
                    animation: slToastInMobile 0.28s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                    width: 100%;
                }
                .sl-toast.closing { animation: slToastOutMobile 0.22s ease forwards; }
            }
            @keyframes slToastInMobile {
                from { transform: translateY(-24px); opacity: 0; }
                to   { transform: translateY(0); opacity: 1; }
            }
            @keyframes slToastOutMobile {
                from { transform: translateY(0); opacity: 1; }
                to   { transform: translateY(-24px); opacity: 0; }
            }
        `;
        document.head.appendChild(slStyles);
    }

    if (!document.getElementById('sl-toast-container')) {
        var slContainer = document.createElement('div');
        slContainer.id = 'sl-toast-container';
        slContainer.className = 'sl-toast-container';
        document.body.appendChild(slContainer);
    }

    var SL_TOAST_ICONS = {
        success: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        warning: '<svg viewBox="0 0 24 24"><line x1="12" y1="9" x2="12" y2="14"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info:    '<svg viewBox="0 0 24 24"><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
    };

    function slDismissToast(toast) {
        if (!toast || toast.classList.contains('closing')) return;
        toast.classList.add('closing');
        setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 240);
    }

    window.showToast = function (message, type, duration) {
        type = type || 'info';
        duration = duration || 3500;
        var container = document.getElementById('sl-toast-container');
        if (!container) return;

        var toast = document.createElement('div');
        toast.className = 'sl-toast ' + type;

        var icon = document.createElement('div');
        icon.className = 'sl-toast-icon';
        icon.innerHTML = SL_TOAST_ICONS[type] || SL_TOAST_ICONS.info;

        var msg = document.createElement('div');
        msg.className = 'sl-toast-msg';
        msg.textContent = String(message);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'sl-toast-close';
        close.innerHTML = '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        close.addEventListener('click', function () { slDismissToast(toast); });

        toast.appendChild(icon);
        toast.appendChild(msg);
        toast.appendChild(close);
        container.appendChild(toast);

        var timer = setTimeout(function () { slDismissToast(toast); }, duration);
        toast.addEventListener('mouseenter', function () { clearTimeout(timer); });
        toast.addEventListener('mouseleave', function () {
            timer = setTimeout(function () { slDismissToast(toast); }, 1500);
        });

        return toast;
    };

    // ---- Override native alert() app-wide ----
    // Any existing alert('...') call becomes a toast automatically.
    // Legacy calls default to 'info'; new code can use showToast(msg, type).
    (function () {
        var nativeAlert = window.alert;
        window.alert = function (message) {
            // Heuristic: common error words → treat as error style
            var text = String(message || '');
            var lower = text.toLowerCase();
            var type = 'info';
            if (/fail|could not|error|invalid|wrong|denied|expired|missing/.test(lower)) type = 'error';
            else if (/success|done|saved|updated|added|sent|welcome|verified|complete/.test(lower)) type = 'success';
            else if (/please|warning|must|limit|under \d|too /.test(lower)) type = 'warning';
            window.showToast(text, type);
            // NOTE: we intentionally don't call nativeAlert here.
            // If you ever need the blocking behavior, use window.alert._native(msg).
        };
        window.alert._native = nativeAlert;
    })();

    // ---------- Favicon ----------
    if (!document.querySelector('link[rel="icon"]')) {
        var f16 = document.createElement('link');
        f16.rel   = 'icon';
        f16.type  = 'image/png';
        f16.sizes = '16x16';
        f16.href  = 'favicon-16x16.png';
        document.head.appendChild(f16);

        var f32 = document.createElement('link');
        f32.rel   = 'icon';
        f32.type  = 'image/png';
        f32.sizes = '32x32';
        f32.href  = 'favicon-32x32.png';
        document.head.appendChild(f32);

        var fi = document.createElement('link');
        fi.rel  = 'icon';
        fi.type = 'image/png';
        fi.href = 'icons/icon-192.png';
        document.head.appendChild(fi);

        var fs = document.createElement('link');
        fs.rel  = 'shortcut icon';
        fs.href = 'favicon.ico';
        document.head.appendChild(fs);
    }
    // ---------- 3) PWA ----------
    if (!document.querySelector('link[rel="manifest"]')) {
        var link = document.createElement('link');
        link.rel  = 'manifest';
        link.href = 'manifest.json';
        document.head.appendChild(link);
    }

    if (!document.querySelector('meta[name="theme-color"]')) {
        var tc = document.createElement('meta');
        tc.name    = 'theme-color';
        tc.content = '#2d5a3d';
        document.head.appendChild(tc);
    }

    if (!document.querySelector('link[rel="apple-touch-icon"]')) {
        var ati = document.createElement('link');
        ati.rel  = 'apple-touch-icon';
        ati.href = 'icons/icon-192.png';
        document.head.appendChild(ati);
    }

        var mw = document.createElement('meta');
        mw.name    = 'mobile-web-app-capable';
        mw.content = 'yes';
        document.head.appendChild(mw);

    if (!document.querySelector('meta[name="apple-mobile-web-app-capable"]')) {
        var am = document.createElement('meta');
        am.name    = 'apple-mobile-web-app-capable';
        am.content = 'yes';
        document.head.appendChild(am);

        var ams = document.createElement('meta');
        ams.name    = 'apple-mobile-web-app-status-bar-style';
        ams.content = 'black-translucent';
        document.head.appendChild(ams);

        var amt = document.createElement('meta');
        amt.name    = 'apple-mobile-web-app-title';
        amt.content = 'ShelfLife';
        document.head.appendChild(amt);
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js').catch(function () {});
        });
    }
})();
</script>