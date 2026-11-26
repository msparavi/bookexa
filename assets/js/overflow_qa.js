(function () {
    'use strict';

    const qaCfg = JSON.parse(document.getElementById('bx-qa-config')?.textContent || '{}');
    const BASE = qaCfg.base || '';
    const ROUTES = {
        public: [
            '/',
            '/pdf',
            '/pdf/academic/ssc-class-9-10/bangla',
            '/book/ssc-bangla-masternote',
            '/shop',
            '/shop/hsc-master-bundle',
            '/cart',
            '/shop/track',
            '/search?q=Bangla',
            '/about',
            '/contact',
            '/terms',
            '/privacy',
            '/refund-policy',
            '/copyright',
            '/styleguide',
            '/login',
            '/register'
        ],
        user: [
            '/library',
            '/wishlist',
            '/notifications',
            '/orders',
            '/profile',
            '/reader/ssc-bangla-masternote?sample=1'
        ],
        admin: [
            '/admin',
            '/admin/banners',
            '/admin/folders',
            '/admin/pdfs',
            '/admin/import-csv',
            '/admin/prepare-queue',
            '/admin/orders',
            '/admin/shop-categories',
            '/admin/shop-products',
            '/admin/shop-orders',
            '/admin/delivery-settings',
            '/admin/inbox',
            '/admin/coupons',
            '/admin/reviews',
            '/admin/reports',
            '/admin/media',
            '/admin/users',
            '/admin/logs',
            '/admin/system-health',
            '/admin/updates',
            '/admin/settings',
            '/admin/admins',
            '/admin/account-security'
        ]
    };
    const WIDTHS = [320, 360, 390, 412, 768, 1280];

    const iframe = document.getElementById('sandbox-frame');
    const tbody  = document.getElementById('qa-tbody');
    const statusEl = document.getElementById('qa-status');

    function buildUrl(route, lang) {
        const sep = route.includes('?') ? '&' : '?';
        return BASE + route + sep + 'lang=' + encodeURIComponent(lang);
    }

    function loadInIframe(url, width, theme) {
        return new Promise((resolve) => {
            iframe.style.width = width + 'px';
            iframe.style.height = (width >= 768 ? 1024 : 780) + 'px';

            const onLoaded = () => {
                clearTimeout(timer);
                setTimeout(() => {
                    try {
                        const doc = iframe.contentDocument;
                        const win = iframe.contentWindow;
                        if (doc && doc.documentElement) {
                            try {
                                win.localStorage.setItem('bookexa_welcomed', '1');
                                win.localStorage.setItem('bookexa_theme', theme);
                                doc.documentElement.setAttribute('data-theme', theme);
                                if (doc.documentElement.classList.contains('bx-reader-html')) {
                                    doc.documentElement.setAttribute('data-reader-theme', theme === 'dark' ? 'dark' : 'light');
                                }
                            } catch (e) {}
                            doc.querySelectorAll('.bx-reveal').forEach((el) => el.classList.add('is-visible'));
                            const firstLoader = doc.getElementById('bx-first-loader');
                            if (firstLoader) firstLoader.remove();
                            win.dispatchEvent(new Event('resize'));
                        }
                        setTimeout(() => resolve({ ok: true, doc: iframe.contentDocument, win: iframe.contentWindow }), 280);
                    } catch (err) {
                        resolve({ ok: false, error: err.message });
                    }
                }, 260);
            };

            const timer = setTimeout(() => {
                iframe.onload = null;
                resolve({ ok: false, error: 'Timeout' });
            }, 15000);
            iframe.onload = onLoaded;
            iframe.onerror = () => {
                clearTimeout(timer);
                resolve({ ok: false, error: 'Network/frame error' });
            };
            iframe.src = url;
        });
    }

    function isVisible(el, win) {
        const st = win.getComputedStyle(el);
        if (st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity) === 0) return false;
        const r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0;
    }

    function inspectDocument(doc, win, vw) {
        const issues = { overflow: [], smallText: [], smallTap: [], overlaps: [] };
        const root = doc.documentElement;
        if (root.scrollWidth > root.clientWidth + 1) {
            issues.overflow.push(`root scrollWidth ${root.scrollWidth} > clientWidth ${root.clientWidth}`);
        }

        doc.querySelectorAll('body *').forEach((el) => {
            if (!isVisible(el, win)) return;
            if (el.closest('.bx-table-wrap, .bx-table-scroll, .bx-tree-scroll, .bx-skel-shimmer, .bx-reader-stage.is-zoomed, .bx-modal-backdrop:not(.open), .bx-drawer:not(.open), .bx-admin-sidebar:not(.open), .bx-rsheet-backdrop:not(.open), .bx-filter-sheet-wrap:not(.open), .bx-folder-sheet-wrap:not(.open)')) {
                return;
            }
            const r = el.getBoundingClientRect();
            const st = win.getComputedStyle(el);
            if (st.position === 'fixed' && r.left < -10) return;
            if (r.right > vw + 2) {
                const tag = el.tagName.toLowerCase() + (el.id ? '#' + el.id : (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/)[0] : ''));
                if (issues.overflow.length < 6) issues.overflow.push(`${tag} right=${Math.round(r.right)}`);
            }
        });

        doc.querySelectorAll('body *').forEach((el) => {
            const tag = el.tagName.toLowerCase();
            if (tag === 'script' || tag === 'style' || tag === 'svg' || el.closest('svg')) return;
            if (!isVisible(el, win)) return;
            let hasDirectText = false;
            for (const n of el.childNodes) {
                if (n.nodeType === 3 && n.textContent.trim().length > 0) { hasDirectText = true; break; }
            }
            if (!hasDirectText) return;
            const fs = parseFloat(win.getComputedStyle(el).fontSize || '16');
            if (fs < 11.9) {
                if (issues.smallText.length < 8) {
                    issues.smallText.push(`${tag}.${(el.className || '').toString().split(' ')[0]} (${fs.toFixed(1)}px): "${el.textContent.trim().slice(0, 22)}"`);
                }
            }
        });

        doc.querySelectorAll('button, [role="button"], input:not([type="hidden"]), select, textarea, nav a, header a, .bx-btn, .bx-pill-btn, .bx-rbtn, .bx-thumb-item, .bx-slider-dot').forEach((el) => {
            if (!isVisible(el, win)) return;
            if (el.closest('.bx-drawer:not(.open), .bx-admin-sidebar:not(.open), .bx-modal-backdrop:not(.open), .bx-rsheet-backdrop:not(.open), .bx-kebab-menu:not(.open), .bx-profile-menu:not(.open)')) return;
            if (el.type === 'checkbox' || el.type === 'radio') return;
            const r = el.getBoundingClientRect();
            if (r.width < 39.5 || r.height < 39.5) {
                if (issues.smallTap.length < 8) {
                    const lbl = (el.getAttribute('aria-label') || el.textContent || el.tagName).trim().slice(0, 20);
                    issues.smallTap.push(`${el.tagName.toLowerCase()} "${lbl}" (${Math.round(r.width)}x${Math.round(r.height)})`);
                }
            }
        });

        const topHdr = doc.querySelector('.bx-header, .bx-admin-topbar, #reader-topbar');
        const mainArea = doc.querySelector('main, .bx-admin-content, .bx-reader-stage');
        if (topHdr && mainArea && isVisible(topHdr, win)) {
            const hr = topHdr.getBoundingClientRect();
            const firstH1 = mainArea.querySelector('h1, h2');
            if (firstH1 && isVisible(firstH1, win)) {
                const h1r = firstH1.getBoundingClientRect();
                if (h1r.top < hr.bottom - 2 && h1r.bottom > hr.top) {
                    issues.overlaps.push(`Top header overlaps heading (${Math.round(h1r.top)} < ${Math.round(hr.bottom)})`);
                }
            }
        }
        const botNav = doc.querySelector('.bx-mobile-bottom-nav, #reader-bottombar');
        if (botNav && isVisible(botNav, win)) {
            win.scrollTo({ top: root.scrollHeight, behavior: 'instant' });
            const br = botNav.getBoundingClientRect();
            const footerLinks = doc.querySelectorAll('.bx-footer a');
            footerLinks.forEach((a) => {
                if (!isVisible(a, win)) return;
                const ar = a.getBoundingClientRect();
                if (ar.bottom > br.top + 2 && ar.top < br.bottom) {
                    if (issues.overlaps.length < 4) issues.overlaps.push(`Bottom nav covers footer link "${a.textContent.trim()}"`);
                }
            });
        }

        return issues;
    }

    async function runAudit() {
        const btn = document.getElementById('btn-run-qa');
        btn.disabled = true;
        tbody.innerHTML = '';

        const lang  = document.getElementById('qa-lang').value;
        const theme = document.getElementById('qa-theme').value;
        const suite = document.getElementById('qa-suite').value;
        const routes = suite === 'all'
            ? [...ROUTES.public, ...ROUTES.user, ...ROUTES.admin]
            : (ROUTES[suite] || ROUTES.public);

        let total = 0, pass = 0, ovCnt = 0, txtCnt = 0, tapCnt = 0, ovlCnt = 0;
        const reportRows = [];

        for (const route of routes) {
            for (const w of WIDTHS) {
                total++;
                statusEl.textContent = `Testing ${route} @ ${w}px (${lang}/${theme}) [${total}/${routes.length * WIDTHS.length}]`;
                const res = await loadInIframe(buildUrl(route, lang), w, theme);
                let issues = { overflow: [res.error ? `Load failed (${res.error})` : 'Load failed'], smallText: [], smallTap: [], overlaps: [] };
                if (res.ok && res.doc) {
                    issues = inspectDocument(res.doc, res.win, w);
                }
                const anyIssue = issues.overflow.length || issues.smallText.length || issues.smallTap.length || issues.overlaps.length;
                if (!anyIssue) pass++;
                ovCnt += issues.overflow.length;
                txtCnt += issues.smallText.length;
                tapCnt += issues.smallTap.length;
                ovlCnt += issues.overlaps.length;

                reportRows.push({ route, width: w, issues });

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><code>${route}</code></td>
                    <td>${w}px</td>
                    <td class="${issues.overflow.length ? 'fail' : 'ok'}">${issues.overflow.length}</td>
                    <td class="${issues.smallText.length ? 'fail' : 'ok'}">${issues.smallText.length}</td>
                    <td class="${issues.smallTap.length ? 'fail' : 'ok'}">${issues.smallTap.length}</td>
                    <td class="${issues.overlaps.length ? 'fail' : 'ok'}">${issues.overlaps.length}</td>
                    <td style="font-size:0.78rem;color:#B6C0DC;">
                        ${[...issues.overflow, ...issues.smallText, ...issues.smallTap, ...issues.overlaps].join(' | ') || 'OK'}
                    </td>
                `;
                tbody.appendChild(tr);

                document.getElementById('s-total').textContent = String(total);
                document.getElementById('s-pass').textContent  = String(pass);
                document.getElementById('s-ov').textContent    = String(ovCnt);
                document.getElementById('s-txt').textContent   = String(txtCnt);
                document.getElementById('s-tap').textContent   = String(tapCnt);
                document.getElementById('s-ovl').textContent   = String(ovlCnt);
            }
        }

        statusEl.textContent = `Completed ${total} checks: ${pass} passed clean.`;
        btn.disabled = false;
        window.__BX_LAST_REPORT__ = { total, pass, ovCnt, txtCnt, tapCnt, ovlCnt, rows: reportRows };
        return window.__BX_LAST_REPORT__;
    }

    document.getElementById('btn-run-qa').addEventListener('click', runAudit);
    window.__BX_RUN_AUDIT__ = runAudit;
    if (new URLSearchParams(window.location.search).get('autorun') === '1') {
        runAudit();
    }
})();
