/**
 * Bookexa Design System JS — Theme Toggle, 3D Card Tilt, Modals, Toasts, Reveal
 */
(function () {
    'use strict';

    // 1. Dark / Light Theme Toggle (respects system preference on first visit)
    const root = document.documentElement;
    const themeQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
    const savedTheme = localStorage.getItem('bookexa_theme');
    if (savedTheme === 'dark' || savedTheme === 'light') {
        root.setAttribute('data-theme', savedTheme);
    } else if (themeQuery) {
        root.setAttribute('data-theme', themeQuery.matches ? 'dark' : 'light');
    }
    if (themeQuery && themeQuery.addEventListener) {
        themeQuery.addEventListener('change', (ev) => {
            if (!localStorage.getItem('bookexa_theme')) {
                root.setAttribute('data-theme', ev.matches ? 'dark' : 'light');
            }
        });
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            localStorage.setItem('bookexa_theme', next);
        });
    });

    // 2. Mobile Slide Drawer
    const drawer = document.getElementById('bx-drawer');
    document.querySelectorAll('[data-drawer-open]').forEach((btn) => {
        btn.addEventListener('click', () => drawer && drawer.classList.add('open'));
    });
    document.querySelectorAll('[data-drawer-close]').forEach((btn) => {
        btn.addEventListener('click', () => drawer && drawer.classList.remove('open'));
    });
    if (drawer) {
        drawer.addEventListener('click', (e) => {
            if (e.target === drawer) drawer.classList.remove('open');
        });
    }

    // 3. CSS 3D Hover Tilt for Folder & Book Cards (Fine pointer / hover devices only)
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    function attach3dTilt(selector, innerSelector) {
        if (reducedMotion || !canHover) return;
        document.querySelectorAll(selector).forEach((card) => {
            const inner = innerSelector ? card.querySelector(innerSelector) : card;
            if (!inner) return;
            card.addEventListener('mousemove', (e) => {
                const rect = card.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width - 0.5;
                const y = (e.clientY - rect.top) / rect.height - 0.5;
                inner.style.setProperty('--rx', (-y * 10).toFixed(2) + 'deg');
                inner.style.setProperty('--ry', (x * 12).toFixed(2) + 'deg');
            });
            card.addEventListener('mouseleave', () => {
                inner.style.setProperty('--rx', '0deg');
                inner.style.setProperty('--ry', '0deg');
            });
        });
    }
    attach3dTilt('.bx-folder-3d', '.bx-folder-inner');
    attach3dTilt('.bx-book-3d', '.bx-book-card');

    // Real Wishlist Toggle (AJAX + CSRF; login required)
    const csrfMeta = document.querySelector('meta[name="bx-csrf"]')?.content || '';
    const loggedInMeta = document.querySelector('meta[name="bx-logged-in"]')?.content === '1';
    const baseMeta = document.querySelector('meta[name="bx-base"]')?.content || '';
    const cdnBaseMeta = (document.querySelector('meta[name="bx-cdn-base"]')?.content || '').replace(/\/+$/, '');

    // Automatic single-attempt CDN -> Local image fallback (zero inline onerror needed under strict CSP)
    document.addEventListener('error', (e) => {
        const img = e.target;
        if (!img || img.tagName !== 'IMG' || img.dataset.cdnFallbackDone === '1') return;
        const src = img.getAttribute('src') || img.src || '';
        if (!cdnBaseMeta || !src.startsWith(cdnBaseMeta + '/')) return;
        const rel = src.slice(cdnBaseMeta.length + 1);
        if (!rel.startsWith('uploads/')) return;
        img.dataset.cdnFallbackDone = '1';
        img.src = baseMeta + '/' + rel;
    }, true);

    function bindWishButtons(rootEl) {
        (rootEl || document).querySelectorAll('.bx-wish-btn[data-item-id]').forEach((btn) => {
            if (btn.dataset.wishBound === '1') return;
            btn.dataset.wishBound = '1';
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                const itemId = btn.getAttribute('data-item-id');
                if (!itemId) return;
                if (!loggedInMeta) {
                    const next = window.location.pathname + window.location.search;
                    window.location.href = baseMeta + '/login?next=' + encodeURIComponent(next);
                    return;
                }
                btn.disabled = true;
                try {
                    const res = await fetch(baseMeta + '/api/wishlist-toggle', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ _csrf: csrfMeta, item_id: itemId })
                    });
                    if (res.status === 401) {
                        const next = window.location.pathname + window.location.search;
                        window.location.href = baseMeta + '/login?next=' + encodeURIComponent(next);
                        return;
                    }
                    const data = await res.json();
                    if (data.ok) {
                        document.querySelectorAll(`.bx-wish-btn[data-item-id="${itemId}"]`).forEach((el) => {
                            el.classList.toggle('active', !!data.in_wishlist);
                            el.setAttribute('aria-pressed', data.in_wishlist ? 'true' : 'false');
                        });
                        window.bxToast(data.message || (data.in_wishlist ? 'Saved to wishlist' : 'Removed from wishlist'));
                    } else {
                        window.bxToast(data.message || 'Could not update wishlist', 'danger');
                    }
                } catch (err) {
                    window.bxToast('Network error updating wishlist', 'danger');
                } finally {
                    btn.disabled = false;
                }
            });
        });
    }
    bindWishButtons(document);

    // Header Notification Bell Dropdown & Mark Read Actions
    const notifBtn = document.getElementById('bx-notif-btn');
    const notifMenu = document.getElementById('bx-notif-menu');
    const notifBadge = document.getElementById('bx-notif-badge');
    function updateNotifBadge(cnt) {
        if (!notifBadge) return;
        const n = Number(cnt || 0);
        notifBadge.textContent = n > 99 ? '99+' : String(n);
        notifBadge.hidden = n <= 0;
    }
    if (notifBtn && notifMenu) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const open = notifMenu.classList.toggle('open');
            notifBtn.setAttribute('aria-expanded', String(open));
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.bx-notif-wrap')) {
                notifMenu.classList.remove('open');
                notifBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
    document.querySelectorAll('[data-notif-mark]').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            const nid = btn.getAttribute('data-notif-mark');
            try {
                const res = await fetch(baseMeta + '/api/notifications/read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf: csrfMeta, id: nid })
                });
                const data = await res.json();
                if (data.ok) {
                    const row = btn.closest('.bx-notif-item');
                    if (row) row.classList.remove('is-unread');
                    btn.remove();
                    updateNotifBadge(data.unread_count);
                }
            } catch (err) {}
        });
    });
    const readAllBtn = document.getElementById('bx-notif-read-all');
    if (readAllBtn) {
        readAllBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            try {
                const res = await fetch(baseMeta + '/api/notifications/read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf: csrfMeta, all: '1' })
                });
                const data = await res.json();
                if (data.ok) {
                    document.querySelectorAll('#bx-notif-list .bx-notif-item').forEach((row) => row.classList.remove('is-unread'));
                    document.querySelectorAll('#bx-notif-list [data-notif-mark]').forEach((b) => b.remove());
                    updateNotifBadge(0);
                }
            } catch (err) {}
        });
    }

    // Checkout Live Coupon Apply
    const couponBox = document.getElementById('bx-checkout-coupon-box');
    const couponBtn = document.getElementById('btn-apply-coupon');
    const couponInp = document.getElementById('coupon_input');
    const couponHid = document.getElementById('checkout_coupon_hidden');
    if (couponBox && couponBtn && couponInp) {
        const fmtBdt = (v) => '৳' + (Math.round(Number(v) * 100) % 100 === 0 ? Math.round(Number(v)) : Number(v).toFixed(2));
        const basePrice = parseFloat(couponBox.getAttribute('data-base-price') || '0');
        const syncManualPayVisibility = (finalAmt) => {
            const box = document.getElementById('bx-manual-pay-fields');
            const senderInp = document.getElementById('sender_number');
            const trxInp = document.getElementById('transaction_id');
            const methSel = document.getElementById('payment_method');
            const subBtn = document.getElementById('checkout-submit-btn');
            const isFree = Number(finalAmt) <= 0;
            if (box) box.style.display = isFree ? 'none' : '';
            if (senderInp) senderInp.required = !isFree;
            if (trxInp) trxInp.required = !isFree;
            if (methSel) methSel.required = !isFree;
            if (subBtn) {
                const span = subBtn.querySelector('span');
                const lbl = isFree ? subBtn.getAttribute('data-free-label') : subBtn.getAttribute('data-paid-label');
                if (span && lbl) span.textContent = lbl;
            }
        };
        const applyCoupon = async () => {
            const code = couponInp.value.trim();
            const fb = document.getElementById('coupon-feedback');
            const discRow = document.getElementById('sum-disc-row');
            const discEl = document.getElementById('sum-disc');
            const totEl = document.getElementById('sum-total');
            if (!code) {
                if (couponHid) couponHid.value = '';
                if (discRow) discRow.style.display = 'none';
                if (totEl) totEl.textContent = fmtBdt(basePrice);
                if (fb) { fb.textContent = ''; }
                syncManualPayVisibility(basePrice);
                return;
            }
            couponBtn.disabled = true;
            try {
                const res = await fetch(baseMeta + '/api/coupon-apply', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        _csrf: csrfMeta,
                        item_id: couponBox.getAttribute('data-item-id') || '',
                        code
                    })
                });
                const data = await res.json();
                if (data.ok) {
                    if (couponHid) couponHid.value = data.code || code;
                    if (discRow) discRow.style.display = 'flex';
                    if (discEl) discEl.textContent = '-' + fmtBdt(data.discount_amount);
                    if (totEl) totEl.textContent = fmtBdt(data.final_amount);
                    if (fb) { fb.textContent = data.message; fb.style.color = 'var(--teal-text)'; }
                    syncManualPayVisibility(data.final_amount);
                } else {
                    if (couponHid) couponHid.value = '';
                    if (discRow) discRow.style.display = 'none';
                    if (totEl) totEl.textContent = fmtBdt(basePrice);
                    if (fb) { fb.textContent = data.message || 'Invalid coupon'; fb.style.color = 'var(--rose-text)'; }
                    syncManualPayVisibility(basePrice);
                }
            } catch (err) {
                if (fb) { fb.textContent = 'Error checking coupon'; fb.style.color = 'var(--rose-text)'; }
            } finally {
                couponBtn.disabled = false;
            }
        };
        couponBtn.addEventListener('click', applyCoupon);
        couponInp.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); applyCoupon(); }
        });
    }

    // 4. Interactive Modal System
    document.querySelectorAll('[data-modal-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const modal = document.getElementById(trigger.getAttribute('data-modal-open'));
            if (modal) modal.classList.add('open');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const backdrop = btn.closest('.bx-modal-backdrop');
            if (backdrop) backdrop.classList.remove('open');
        });
    });
    document.querySelectorAll('.bx-modal-backdrop').forEach((backdrop) => {
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) backdrop.classList.remove('open');
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.bx-modal-backdrop.open').forEach((m) => m.classList.remove('open'));
        }
    });

    // 5. Toast Notifications (supports 'success' and 'danger'/'error')
    window.bxToast = function (message, type) {
        if (!message) return;
        let stack = document.getElementById('bx-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'bx-toast-stack';
            stack.className = 'bx-toast-stack';
            document.body.appendChild(stack);
        }
        const el = document.createElement('div');
        const kind = (type === 'danger' || type === 'error') ? 'is-danger' : 'is-success';
        el.className = 'bx-toast ' + kind;
        el.setAttribute('role', kind === 'is-danger' ? 'alert' : 'status');
        el.textContent = message;
        stack.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(10px)';
            el.style.transition = 'all 0.25s ease';
            setTimeout(() => el.remove(), 260);
        }, 3200);
    };

    document.querySelectorAll('[data-toast-trigger]').forEach((btn) => {
        btn.addEventListener('click', () => {
            window.bxToast(btn.getAttribute('data-toast-trigger') || 'Bookexa notification!');
        });
    });

    // 6. Scroll Reveal (eager rootMargin + jump-safe check so sections never stay faded)
    const reveals = Array.from(document.querySelectorAll('.bx-reveal'));
    if (reveals.length && 'IntersectionObserver' in window && !reducedMotion) {
        const revealVisible = () => {
            const vh = window.innerHeight || 800;
            reveals.forEach((el) => {
                if (el.classList.contains('is-visible')) return;
                const r = el.getBoundingClientRect();
                if (r.top <= vh + 80) el.classList.add('is-visible');
            });
        };
        const obs = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.01, rootMargin: '0px 0px 80px 0px' });
        reveals.forEach((el) => obs.observe(el));
        window.addEventListener('scroll', revealVisible, { passive: true });
        revealVisible();
    } else {
        reveals.forEach((el) => el.classList.add('is-visible'));
    }

    // -------------------------------------------------------------------------
    // Hero Slider, 3D Portal/Detail Tilt, Search Suggestions & Load More
    // -------------------------------------------------------------------------

    // Hero Slider
    const slider = document.querySelector('[data-hero-slider]');
    if (slider) {
        const slides = Array.from(slider.querySelectorAll('.bx-slide'));
        const dots = Array.from(slider.querySelectorAll('.bx-slider-dot'));
        const prevBtn = slider.querySelector('[data-slider-prev]');
        const nextBtn = slider.querySelector('[data-slider-next]');
        let idx = 0;
        const show = (nextIdx) => {
            idx = (nextIdx + slides.length) % slides.length;
            slides.forEach((s, i) => s.classList.toggle('active', i === idx));
            dots.forEach((d, i) => d.classList.toggle('active', i === idx));
        };
        dots.forEach((d, i) => d.addEventListener('click', () => show(i)));
        if (prevBtn) prevBtn.addEventListener('click', () => show(idx - 1));
        if (nextBtn) nextBtn.addEventListener('click', () => show(idx + 1));
        if (slides.length > 1) {
            setInterval(() => show(idx + 1), 5500);
        }
    }

    // 3D Tilt on Portal Cards & 3D Book Detail (hover devices only)
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    if (!reduced && finePointer) {
        document.querySelectorAll('.bx-portal-3d, .bx-detail-stage').forEach((wrap) => {
            const card = wrap.querySelector('.bx-portal-card, .bx-detail-book-3d');
            if (!card) return;
            wrap.addEventListener('mousemove', (e) => {
                const r = wrap.getBoundingClientRect();
                const x = (e.clientX - r.left) / r.width - 0.5;
                const y = (e.clientY - r.top) / r.height - 0.5;
                card.style.setProperty('--rx', (-y * 8).toFixed(2) + 'deg');
                card.style.setProperty('--ry', (x * 10).toFixed(2) + 'deg');
            });
            wrap.addEventListener('mouseleave', () => {
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
            });
        });
    }

    // Show subtle loading skeleton when navigating inside folders
    document.querySelectorAll('[data-folder-nav]').forEach((link) => {
        link.addEventListener('click', () => {
            const skel = document.getElementById('bx-folder-skeleton');
            const content = document.getElementById('bx-folder-content');
            if (skel && content) {
                setTimeout(() => {
                    content.style.display = 'none';
                    skel.style.display = 'grid';
                }, 120);
            }
        });
    });

    // Auto-scroll single-line breadcrumbs so current page is visible
    document.querySelectorAll('.bx-breadcrumb').forEach((bc) => {
        try { bc.scrollLeft = bc.scrollWidth; } catch (err) {}
    });

    // Password Show / Hide Toggle on auth forms
    document.querySelectorAll('[data-pw-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.getAttribute('data-pw-toggle') || '');
            if (!input) return;
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            btn.setAttribute('aria-label', btn.getAttribute(reveal ? 'data-label-hide' : 'data-label-show') || '');
            const openIcon = btn.querySelector('.bx-pw-eye-open');
            const closedIcon = btn.querySelector('.bx-pw-eye-closed');
            if (openIcon && closedIcon) {
                openIcon.hidden = reveal;
                closedIcon.hidden = !reveal;
            }
        });
    });

    // Submit Loading State for auth & public forms
    document.querySelectorAll('form[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"][data-loading-label]');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            btn.classList.add('is-loading');
            btn.textContent = btn.getAttribute('data-loading-label') || btn.textContent;
        });
    });

    // Print Invoice buttons & Public Confirm Prompts (strict CSP-safe)
    document.querySelectorAll('[data-print-btn]').forEach((btn) => {
        btn.addEventListener('click', () => window.print());
    });
    document.addEventListener('click', (e) => {
        const trg = e.target.closest('[data-confirm]');
        if (!trg) return;
        const msg = trg.getAttribute('data-confirm') || 'Are you sure?';
        if (!window.confirm(msg)) {
            e.preventDefault();
        }
    });

    // Live Search Suggestions (Debounced AJAX, max 6 results across folders & books)
    document.querySelectorAll('[data-suggest-input]').forEach((inp) => {
        const box = inp.closest('form')?.querySelector('.bx-suggest-dropdown');
        if (!box) return;
        let timer = null;
        let ctrl = null;

        function hideBox() { box.hidden = true; }
        function buildLink(label, subText, href, badgeText) {
            const a = document.createElement('a');
            a.className = 'bx-suggest-item';
            a.href = href;
            const left = document.createElement('span');
            left.style.minWidth = '0';
            const titleEl = document.createElement('strong');
            titleEl.textContent = label;
            left.appendChild(titleEl);
            if (subText) {
                const subEl = document.createElement('small');
                subEl.style.display = 'block';
                subEl.style.color = 'var(--text-muted)';
                subEl.textContent = subText;
                left.appendChild(subEl);
            }
            a.appendChild(left);
            if (badgeText) {
                const b = document.createElement('span');
                b.className = 'bx-badge';
                b.textContent = badgeText;
                a.appendChild(b);
            }
            return a;
        }

        inp.addEventListener('input', () => {
            clearTimeout(timer);
            const q = inp.value.trim();
            if (q.length < 1) { hideBox(); return; }
            timer = setTimeout(async () => {
                if (ctrl) ctrl.abort();
                ctrl = new AbortController();
                try {
                    const res = await fetch(baseMeta + '/api/search-suggest?q=' + encodeURIComponent(q), {
                        signal: ctrl.signal, credentials: 'same-origin'
                    });
                    const data = await res.json();
                    box.innerHTML = '';
                    const folders = (data && data.folders) || [];
                    const items = (data && data.items) || [];
                    if (!folders.length && !items.length) { hideBox(); return; }
                    if (folders.length) {
                        const hdr = document.createElement('div');
                        hdr.className = 'bx-suggest-group';
                        hdr.textContent = 'Folders';
                        box.appendChild(hdr);
                        folders.forEach((f) => box.appendChild(buildLink(f.title, '', f.url, 'Folder')));
                    }
                    if (items.length) {
                        const hdr = document.createElement('div');
                        hdr.className = 'bx-suggest-group';
                        hdr.textContent = 'PDFs';
                        box.appendChild(hdr);
                        items.forEach((it) => box.appendChild(buildLink(it.title, it.sub || '', it.url, it.badge || '')));
                    }
                    box.hidden = false;
                } catch (e) {}
            }, 220);
        });

        document.addEventListener('click', (e) => {
            if (!box.contains(e.target) && e.target !== inp) hideBox();
        });
    });

    // Load More Pagination (Folder, Search, Library, Wishlist)
    document.querySelectorAll('[data-load-more]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const nextUrl = btn.getAttribute('data-next-url');
            const gridSel = btn.getAttribute('data-target-grid');
            const grid = gridSel ? document.querySelector(gridSel) : null;
            if (!nextUrl || !grid || btn.disabled) return;
            btn.disabled = true;
            try {
                const res = await fetch(nextUrl, { credentials: 'same-origin' });
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const nextGrid = doc.querySelector(gridSel);
                if (nextGrid) {
                    Array.from(nextGrid.children).forEach((child) => {
                        child.classList.add('is-visible');
                        grid.appendChild(child);
                    });
                    bindWishButtons(grid);
                    attach3dTilt('.bx-book-3d', '.bx-book-card');
                }
                const nextBtn = doc.querySelector('[data-load-more]');
                if (nextBtn) {
                    btn.setAttribute('data-next-url', nextBtn.getAttribute('data-next-url') || '');
                    btn.textContent = nextBtn.textContent;
                    btn.disabled = false;
                } else {
                    btn.remove();
                }
            } catch (err) {
                btn.disabled = false;
                window.location.href = nextUrl;
            }
        });
    });

    // Recently Viewed Guest localStorage Tracking & Home Hydration
    const recentEl = document.querySelector('[data-recent-item]');
    if (recentEl) {
        const rid = parseInt(recentEl.getAttribute('data-recent-item') || '0', 10);
        if (rid > 0) {
            try {
                let list = JSON.parse(localStorage.getItem('bookexa_recent_views') || '[]');
                if (!Array.isArray(list)) list = [];
                list = [rid, ...list.filter((id) => Number(id) !== rid)].slice(0, 12);
                localStorage.setItem('bookexa_recent_views', JSON.stringify(list));
            } catch (e) {}
        }
    }
    const guestRecGrid = document.getElementById('bx-guest-recent-grid');
    const guestRecSec  = document.getElementById('bx-guest-recent-sec');
    if (guestRecGrid && guestRecSec) {
        try {
            const ids = JSON.parse(localStorage.getItem('bookexa_recent_views') || '[]');
            if (Array.isArray(ids) && ids.length > 0) {
                fetch(baseMeta + '/api/items-by-ids?ids=' + encodeURIComponent(ids.slice(0, 8).join(',')))
                    .then((r) => r.json())
                    .then((d) => {
                        if (d && d.ok && d.html) {
                            guestRecGrid.innerHTML = d.html;
                            guestRecGrid.querySelectorAll('.bx-reveal').forEach((el) => el.classList.add('is-visible'));
                            bindWishButtons(guestRecGrid);
                            attach3dTilt('.bx-book-3d', '.bx-book-card');
                            guestRecSec.hidden = false;
                        }
                    }).catch(() => {});
            }
        } catch (e) {}
    }

    // Mobile Soft Keyboard Focus Visibility (ensures sticky bottom bar / keyboard never hides active field)
    document.addEventListener('focusin', (e) => {
        if (window.innerWidth > 900) return;
        const el = e.target;
        if (!el || !el.matches('input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]), select, textarea')) return;
        setTimeout(() => {
            try { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (err) {}
        }, 280);
    }, { passive: true });
})();
