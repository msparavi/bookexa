(function () {
    'use strict';
    var root = document.documentElement;
    var activateLink = function (lnk) {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(function () { lnk.media = 'all'; }, { timeout: 500 });
        } else {
            setTimeout(function () { lnk.media = 'all'; }, 60);
        }
    };
    var links = document.querySelectorAll('link[data-async-css]');
    for (var i = 0; i < links.length; i++) {
        (function (lnk) {
            if (lnk.sheet) {
                activateLink(lnk);
            } else {
                lnk.addEventListener('load', function () { activateLink(lnk); }, { once: true });
            }
        })(links[i]);
    }
    try {
        var t = localStorage.getItem('bookexa_theme');
        if (t !== 'dark' && t !== 'light') {
            t = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        }
        if (root.getAttribute('data-theme') !== t) {
            root.setAttribute('data-theme', t);
        }

        if (localStorage.getItem('bookexa_welcomed') === '1' || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
            root.classList.add('bx-skip-loader');
        }

        if (root.classList.contains('bx-reader-html')) {
            var srv = document.querySelector('meta[name="bx-reader-theme"]')?.content || '';
            var uid = document.querySelector('meta[name="bx-reader-uid"]')?.content || '';
            var rt = (srv === 'light' || srv === 'sepia' || srv === 'dark') ? srv : (uid ? localStorage.getItem('bookexa_reader_theme_u' + uid) : '');
            if (rt !== 'light' && rt !== 'sepia' && rt !== 'dark') {
                rt = (t === 'light' || t === 'dark') ? t : 'dark';
            }
            root.setAttribute('data-reader-theme', rt);
        }
    } catch (e) {}
})();
