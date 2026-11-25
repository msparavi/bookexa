/**
 * Bookexa Mobile-First Admin JS
 * - Off-canvas drawer (<1024px) with backdrop, outside-tap & nav close, scroll lock
 * - Touch + Mouse drag-and-drop folder tree & banner reorder + Move Up/Down + Move To modal
 * - Three-dot kebab menus, bottom-sheet filters & forms, confirmation modal, responsive charts
 */
(function () {
    'use strict';

    const adminWrap  = document.getElementById('bx-admin-wrap');
    const sidebar    = document.getElementById('bx-admin-sidebar');
    const backdrop   = document.getElementById('bx-admin-backdrop');
    const csrfToken  = document.querySelector('meta[name="bx-csrf"]')?.content || '';

    // 0. Fire Toast Notifications for every server flash (success or danger)
    const flashEl = document.getElementById('bx-flash-toasts');
    if (flashEl && window.bxToast) {
        try {
            const list = JSON.parse(flashEl.textContent || '[]');
            list.forEach((f) => window.bxToast(f.message, f.type));
        } catch (err) {}
    }

    // 1. Collapsible Desktop Sidebar + Off-Canvas Mobile Drawer (<1024px)
    if (adminWrap && localStorage.getItem('bookexa_admin_collapsed') === '1') {
        adminWrap.classList.add('is-collapsed');
    }
    document.querySelectorAll('[data-sidebar-collapse]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!adminWrap) return;
            const collapsed = adminWrap.classList.toggle('is-collapsed');
            localStorage.setItem('bookexa_admin_collapsed', collapsed ? '1' : '0');
        });
    });

    function setDrawerOpen(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('open', open);
        if (backdrop) backdrop.classList.toggle('open', open);
        document.body.classList.toggle('bx-admin-drawer-open', open);
        document.querySelectorAll('[data-admin-menu-open]').forEach((b) => {
            b.setAttribute('aria-expanded', String(open));
        });
    }

    document.querySelectorAll('[data-admin-menu-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const isOpen = sidebar ? !sidebar.classList.contains('open') : false;
            setDrawerOpen(isOpen);
        });
    });
    document.querySelectorAll('[data-admin-menu-close]').forEach((btn) => {
        btn.addEventListener('click', () => setDrawerOpen(false));
    });
    if (backdrop) {
        backdrop.addEventListener('click', () => setDrawerOpen(false));
    }
    if (sidebar) {
        sidebar.querySelectorAll('.bx-admin-nav a').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) setDrawerOpen(false);
            });
        });
    }

    // Mobile Topbar Search Toggle
    const adminSearchForm = document.getElementById('bx-admin-search-form');
    const adminSearch = document.getElementById('bx-admin-global-search');
    document.querySelectorAll('[data-topbar-search-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!adminSearchForm) return;
            const open = adminSearchForm.classList.toggle('is-mobile-open');
            if (open && adminSearch) adminSearch.focus();
        });
    });

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k' && adminSearch) {
            e.preventDefault();
            if (adminSearchForm) adminSearchForm.classList.add('is-mobile-open');
            adminSearch.focus();
        }
        if (e.key === 'Escape') {
            setDrawerOpen(false);
            closeAllMenus();
            closeAllSheets();
        }
    });

    // 2. Profile Dropdown & Three-Dot Kebab Action Menus
    const profBtn  = document.getElementById('bx-profile-btn');
    const profMenu = document.getElementById('bx-profile-menu');
    function closeAllMenus() {
        if (profMenu) profMenu.classList.remove('open');
        document.querySelectorAll('.bx-kebab-menu.open').forEach((m) => m.classList.remove('open'));
    }
    if (profBtn && profMenu) {
        profBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const wasOpen = profMenu.classList.contains('open');
            closeAllMenus();
            if (!wasOpen) profMenu.classList.add('open');
        });
    }
    document.querySelectorAll('[data-kebab-toggle]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const menu = btn.parentElement?.querySelector('.bx-kebab-menu');
            const wasOpen = menu ? menu.classList.contains('open') : false;
            closeAllMenus();
            if (menu && !wasOpen) menu.classList.add('open');
        });
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.bx-kebab-wrap') && !e.target.closest('.bx-profile-wrap')) {
            closeAllMenus();
        }
    });

    // 3. Bottom Sheets & Modals (Filters Sheet, Folder Form Sheet, Move-To Modal)
    function closeAllSheets() {
        document.querySelectorAll('.bx-sheet-backdrop.open, .bx-filter-sheet-wrap.open, .bx-folder-sheet-wrap.open')
            .forEach((s) => s.classList.remove('open'));
    }
    document.querySelectorAll('[data-open-sheet]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.getAttribute('data-open-sheet'));
            if (target) target.classList.add('open');
        });
    });
    document.querySelectorAll('[data-close-sheet]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const sheet = btn.closest('.bx-sheet-backdrop, .bx-filter-sheet-wrap, .bx-folder-sheet-wrap');
            if (sheet) sheet.classList.remove('open');
        });
    });
    document.querySelectorAll('.bx-sheet-backdrop, .bx-filter-sheet-wrap, .bx-folder-sheet-wrap').forEach((wrap) => {
        wrap.addEventListener('click', (e) => {
            if (e.target === wrap) wrap.classList.remove('open');
        });
    });

    // 4. Confirmation Modal (replaces native confirm())
    const confirmModal = document.getElementById('bx-confirm-modal');
    const confirmMsg   = document.getElementById('bx-confirm-msg');
    const confirmOk    = document.getElementById('bx-confirm-ok');
    let pendingForm    = null;

    document.querySelectorAll('form[data-confirm-msg]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            pendingForm = form;
            if (confirmMsg) confirmMsg.textContent = form.getAttribute('data-confirm-msg') || 'Are you sure?';
            if (confirmModal) confirmModal.classList.add('open');
        });
    });
    if (confirmOk) {
        confirmOk.addEventListener('click', () => {
            if (!pendingForm) return;
            pendingForm.dataset.confirmed = '1';
            if (confirmModal) confirmModal.classList.remove('open');
            pendingForm.submit();
        });
    }

    // 5. Unsaved-Changes Guard
    let isDirty = false;
    document.querySelectorAll('form.bx-track-dirty').forEach((form) => {
        form.addEventListener('input', () => { isDirty = true; });
        form.addEventListener('change', () => { isDirty = true; });
        form.addEventListener('submit', () => { isDirty = false; });
    });
    window.addEventListener('beforeunload', (e) => {
        if (isDirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // 6. Live Image Preview + Client-Side 4:3 Crop/Resize
    document.querySelectorAll('input[type="file"][data-preview-target]').forEach((input) => {
        input.addEventListener('change', () => {
            const targetId = input.getAttribute('data-preview-target');
            const img = document.getElementById(targetId);
            if (!input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                if (img) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                }
                const cropTargetId = input.getAttribute('data-crop-hidden');
                if (cropTargetId) {
                    const hidden = document.getElementById(cropTargetId);
                    const tempImg = new Image();
                    tempImg.onload = () => {
                        const dstW = 600, dstH = 390;
                        const canvas = document.createElement('canvas');
                        canvas.width = dstW; canvas.height = dstH;
                        const ctx = canvas.getContext('2d');
                        const srcRatio = tempImg.width / tempImg.height;
                        const dstRatio = dstW / dstH;
                        let sx = 0, sy = 0, sw = tempImg.width, sh = tempImg.height;
                        if (srcRatio > dstRatio) { sw = tempImg.height * dstRatio; sx = (tempImg.width - sw) / 2; }
                        else { sh = tempImg.width / dstRatio; sy = (tempImg.height - sh) / 2; }
                        ctx.drawImage(tempImg, sx, sy, sw, sh, 0, 0, dstW, dstH);
                        const dataUrl = canvas.toDataURL('image/webp', 0.86);
                        if (hidden) hidden.value = dataUrl;
                        if (img) img.src = dataUrl;
                    };
                    tempImg.src = e.target.result;
                }
            };
            reader.readAsDataURL(input.files[0]);
        });
    });

    // 7. Folder Tree: Expand/Collapse, Inline Rename, Touch + Mouse Drag & Move Controls
    document.querySelectorAll('[data-tree-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const node = btn.closest('.bx-tree-node');
            if (!node) return;
            const collapsed = node.classList.toggle('is-collapsed');
            btn.setAttribute('aria-expanded', String(!collapsed));
        });
    });
    document.getElementById('btn-expand-all')?.addEventListener('click', () => {
        document.querySelectorAll('.bx-tree-node').forEach((n) => n.classList.remove('is-collapsed'));
        document.querySelectorAll('[data-tree-toggle]').forEach((b) => b.setAttribute('aria-expanded', 'true'));
    });
    document.getElementById('btn-collapse-all')?.addEventListener('click', () => {
        document.querySelectorAll('.bx-tree-node').forEach((n) => n.classList.add('is-collapsed'));
        document.querySelectorAll('[data-tree-toggle]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
    });

    document.querySelectorAll('[data-inline-rename]').forEach((el) => {
        el.addEventListener('click', () => {
            if (el.querySelector('input')) return;
            const folderId = el.getAttribute('data-inline-rename');
            const origText = el.textContent.trim();
            const inp = document.createElement('input');
            inp.type = 'text'; inp.className = 'bx-input';
            inp.style.padding = '4px 8px'; inp.style.maxWidth = '190px';
            inp.value = origText;
            el.textContent = ''; el.appendChild(inp); inp.focus();

            const save = async () => {
                const next = inp.value.trim();
                if (!next || next === origText) { el.textContent = origText; return; }
                const endpoint = el.getAttribute('data-endpoint');
                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ _csrf: csrfToken, id: folderId, name_en: next })
                    });
                    const data = await res.json();
                    el.textContent = data.ok ? next : origText;
                    if (window.bxToast) window.bxToast(data.message || 'Renamed');
                } catch (err) { el.textContent = origText; }
            };
            inp.addEventListener('blur', save);
            inp.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
                if (e.key === 'Escape') { el.textContent = origText; }
            });
        });
    });

    // Move Up / Move Down Buttons
    document.querySelectorAll('[data-folder-step]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-folder-step');
            const dir = btn.getAttribute('data-dir');
            const endpoint = btn.getAttribute('data-endpoint');
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ _csrf: csrfToken, id, dir })
            });
            const data = await res.json();
            if (data.ok) window.location.reload();
        });
    });

    // "Move To..." Modal for Touch Accessibility
    const moveModal = document.getElementById('bx-folder-move-modal');
    const moveIdIn  = document.getElementById('bx-move-folder-id');
    const moveName  = document.getElementById('bx-move-folder-name');
    const moveSel   = document.getElementById('bx-move-parent-select');
    document.querySelectorAll('[data-move-modal-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            closeAllMenus();
            if (!moveModal || !moveIdIn || !moveSel) return;
            const fid = btn.getAttribute('data-move-modal-open');
            const fname = btn.getAttribute('data-folder-name') || '';
            moveIdIn.value = fid;
            if (moveName) moveName.textContent = fname;
            Array.from(moveSel.options).forEach((opt) => { opt.disabled = (opt.value === fid); });
            moveModal.classList.add('open');
        });
    });

    // Folder Reparent via Desktop Drag AND Mobile Touch Drag Handle
    const treeRoot = document.getElementById('bx-folder-tree-root');
    if (treeRoot) {
        let draggedFolderId = null;
        let touchHoverRow = null;

        async function moveFolderInto(srcId, targetId) {
            if (!srcId || !targetId || srcId === targetId) return;
            const endpoint = treeRoot.getAttribute('data-move-endpoint');
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ _csrf: csrfToken, id: srcId, parent_id: targetId })
            });
            const data = await res.json();
            if (data.ok) window.location.reload();
            else if (window.bxToast) window.bxToast(data.message || 'Move failed');
        }

        treeRoot.querySelectorAll('.bx-tree-row[data-folder-id]').forEach((row) => {
            row.addEventListener('dragstart', (e) => {
                draggedFolderId = row.getAttribute('data-folder-id');
                row.classList.add('is-dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            row.addEventListener('dragend', () => {
                row.classList.remove('is-dragging');
                treeRoot.querySelectorAll('.is-dragover').forEach((el) => el.classList.remove('is-dragover'));
            });
            row.addEventListener('dragover', (e) => {
                if (!draggedFolderId || draggedFolderId === row.getAttribute('data-folder-id')) return;
                e.preventDefault();
                row.classList.add('is-dragover');
            });
            row.addEventListener('dragleave', () => row.classList.remove('is-dragover'));
            row.addEventListener('drop', (e) => {
                e.preventDefault();
                row.classList.remove('is-dragover');
                moveFolderInto(draggedFolderId, row.getAttribute('data-folder-id'));
            });

            // Touch Handle Support
            const handle = row.querySelector('.bx-drag-handle');
            if (handle) {
                handle.addEventListener('touchstart', () => {
                    draggedFolderId = row.getAttribute('data-folder-id');
                    row.classList.add('is-dragging');
                }, { passive: true });
                handle.addEventListener('touchmove', (e) => {
                    if (!draggedFolderId) return;
                    e.preventDefault();
                    const t = e.touches[0];
                    const el = document.elementFromPoint(t.clientX, t.clientY)?.closest('.bx-tree-row[data-folder-id]');
                    if (touchHoverRow && touchHoverRow !== el) touchHoverRow.classList.remove('is-dragover');
                    touchHoverRow = (el && el !== row) ? el : null;
                    if (touchHoverRow) touchHoverRow.classList.add('is-dragover');
                }, { passive: false });
                handle.addEventListener('touchend', () => {
                    row.classList.remove('is-dragging');
                    if (touchHoverRow) {
                        touchHoverRow.classList.remove('is-dragover');
                        const targetId = touchHoverRow.getAttribute('data-folder-id');
                        touchHoverRow = null;
                        moveFolderInto(draggedFolderId, targetId);
                    }
                    draggedFolderId = null;
                });
            }
        });
    }

    // Banner Reorder (Mouse + Touch)
    const bannerTableBody = document.getElementById('bx-banner-tbody');
    if (bannerTableBody) {
        let dragRow = null;
        async function saveBannerOrder() {
            const ids = Array.from(bannerTableBody.querySelectorAll('.bx-drag-row')).map((r) => r.getAttribute('data-banner-id'));
            const endpoint = bannerTableBody.getAttribute('data-reorder-endpoint');
            await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ _csrf: csrfToken, order: ids.join(',') })
            });
            if (window.bxToast) window.bxToast('Banner order saved!');
        }
        bannerTableBody.querySelectorAll('.bx-drag-row').forEach((row) => {
            row.addEventListener('dragstart', () => { dragRow = row; row.classList.add('is-dragging'); });
            row.addEventListener('dragend', () => { row.classList.remove('is-dragging'); saveBannerOrder(); });
            row.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (!dragRow || dragRow === row) return;
                const rect = row.getBoundingClientRect();
                const after = (e.clientY - rect.top) > rect.height / 2;
                bannerTableBody.insertBefore(dragRow, after ? row.nextSibling : row);
            });
            const handle = row.querySelector('.bx-drag-handle');
            if (handle) {
                handle.addEventListener('touchstart', () => { dragRow = row; row.classList.add('is-dragging'); }, { passive: true });
                handle.addEventListener('touchmove', (e) => {
                    if (!dragRow) return;
                    e.preventDefault();
                    const t = e.touches[0];
                    const over = document.elementFromPoint(t.clientX, t.clientY)?.closest('.bx-drag-row');
                    if (over && over !== dragRow) {
                        const rect = over.getBoundingClientRect();
                        const after = (t.clientY - rect.top) > rect.height / 2;
                        bannerTableBody.insertBefore(dragRow, after ? over.nextSibling : over);
                    }
                }, { passive: false });
                handle.addEventListener('touchend', () => {
                    if (dragRow) { dragRow.classList.remove('is-dragging'); dragRow = null; saveBannerOrder(); }
                });
            }
        });
    }

    // 8. PDF Live 3D Preview + Test Drive Link + Bulk Select + Auto-Slug
    const isFreeCb  = document.getElementById('pdf-is-free');
    const priceIn   = document.getElementById('pdf-price');
    const titleEnIn = document.getElementById('pdf-title-en');
    const titleBnIn = document.getElementById('pdf-title-bn');
    const authEnIn  = document.getElementById('pdf-author-en');

    function syncLivePdfPreview() {
        const pvTitle  = document.getElementById('pv-pdf-title');
        const pvAuthor = document.getElementById('pv-pdf-author');
        const pvPrice  = document.getElementById('pv-pdf-price');
        const pvRibbon = document.getElementById('pv-pdf-ribbon');
        if (!pvTitle) return;
        pvTitle.textContent = (titleBnIn?.value || titleEnIn?.value || 'Sample PDF Title').trim();
        if (pvAuthor) pvAuthor.textContent = (authEnIn?.value || 'Bookexa Author').trim();
        const free = isFreeCb ? isFreeCb.checked : false;
        const pTxt = free ? 'Free' : ('৳' + (priceIn?.value || '0'));
        if (pvPrice) pvPrice.textContent = pTxt;
        if (pvRibbon) { pvRibbon.textContent = pTxt; pvRibbon.classList.toggle('free', free); }
    }
    if (isFreeCb && priceIn) {
        const syncPrice = () => { priceIn.disabled = isFreeCb.checked; if (isFreeCb.checked) priceIn.value = '0'; syncLivePdfPreview(); };
        isFreeCb.addEventListener('change', syncPrice);
        syncPrice();
    }
    [titleEnIn, titleBnIn, authEnIn, priceIn].forEach((el) => el && el.addEventListener('input', syncLivePdfPreview));

    const btnTestDrive = document.getElementById('btn-test-drive');
    const driveInput   = document.getElementById('pdf-drive-link');
    const driveResult  = document.getElementById('drive-test-result');
    if (btnTestDrive && driveInput && driveResult) {
        btnTestDrive.addEventListener('click', async () => {
            driveResult.textContent = 'Checking reachability...';
            driveResult.style.color = 'var(--text-muted)';
            try {
                const res = await fetch(btnTestDrive.getAttribute('data-endpoint'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf: csrfToken, drive_link: driveInput.value.trim() })
                });
                const data = await res.json();
                driveResult.textContent = data.message || (data.ok ? 'Reachable!' : 'Failed');
                driveResult.style.color = data.ok ? 'var(--teal)' : 'var(--rose)';
            } catch (err) {
                driveResult.textContent = 'Network check error.';
                driveResult.style.color = 'var(--rose)';
            }
        });
    }

    const bulkSelectAll = document.getElementById('bx-bulk-select-all');
    if (bulkSelectAll) {
        bulkSelectAll.addEventListener('change', () => {
            document.querySelectorAll('.bx-bulk-cb').forEach((cb) => { cb.checked = bulkSelectAll.checked; });
        });
    }

    const nameEnSrc = document.querySelector('[data-slug-source]');
    const slugTgt   = document.querySelector('[data-slug-target]');
    if (nameEnSrc && slugTgt) {
        let manual = slugTgt.value.trim() !== '';
        slugTgt.addEventListener('input', () => { manual = slugTgt.value.trim() !== ''; });
        nameEnSrc.addEventListener('input', () => {
            if (manual) return;
            slugTgt.value = nameEnSrc.value.toLowerCase().trim().replace(/[^a-z0-9\s-]+/g, '').replace(/[\s-]+/g, '-').replace(/^-+|-+$/g, '');
        });
    }

    // 9. Responsive Vanilla-JS SVG Bar Charts (7-day / 30-day switcher + empty state)
    function renderBarChart(svg) {
        const range = svg.getAttribute('data-active-range') || '';
        const labelsRaw = range && svg.hasAttribute('data-labels-' + range)
            ? svg.getAttribute('data-labels-' + range)
            : svg.getAttribute('data-labels');
        const valuesRaw = range && svg.hasAttribute('data-values-' + range)
            ? svg.getAttribute('data-values-' + range)
            : svg.getAttribute('data-values');
        const labels = JSON.parse(labelsRaw || '[]');
        const values = JSON.parse(valuesRaw || '[]');
        const sum = values.reduce((a, b) => a + Number(b || 0), 0);
        const wrap = svg.closest('.bx-chart-wrap');
        const emptyBox = wrap ? wrap.querySelector('.bx-chart-empty') : null;
        if (!values.length || sum <= 0) {
            svg.style.display = 'none';
            if (emptyBox) emptyBox.style.display = 'flex';
            return;
        }
        svg.style.display = 'block';
        if (emptyBox) emptyBox.style.display = 'none';

        const maxVal = Math.max(1, ...values);
        const cw = Math.max(240, Math.min(540, svg.parentElement?.clientWidth || 400));
        const h = cw < 320 ? 155 : 175;
        const padX = 14;
        const padB = 26;
        const step = (cw - padX * 2) / Math.max(1, values.length);
        const barW = Math.max(6, Math.min(36, Math.floor(step * 0.58)));
        const maxChars = values.length > 10 ? 5 : (cw < 320 ? 5 : 8);
        const showLabelEvery = values.length > 15 ? 5 : 1;
        let inner = '<defs><linearGradient id="bxChartGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="var(--primary)"/><stop offset="100%" stop-color="var(--teal-text)"/></linearGradient></defs>';
        values.forEach((v, i) => {
            const barH = v > 0 ? Math.max(6, Math.round((v / maxVal) * (h - padB - 24))) : 2;
            const x = Math.round(padX + i * step + (step - barW) / 2);
            const y = h - padB - barH;
            const rawLbl = String(labels[i] || '');
            const lbl = rawLbl.length > maxChars ? rawLbl.slice(0, maxChars - 1) + '…' : rawLbl;
            inner += `<rect class="bx-chart-bar" x="${x}" y="${y}" width="${barW}" height="${barH}" rx="${Math.min(7, Math.ceil(barW / 2))}"></rect>`;
            if (v > 0 || values.length <= 10) {
                inner += `<text x="${x + barW / 2}" y="${y - 6}" text-anchor="middle" font-size="12" font-weight="700" fill="var(--text)">${v}</text>`;
            }
            if (i % showLabelEvery === 0 || i === values.length - 1) {
                inner += `<text x="${x + barW / 2}" y="${h - 6}" text-anchor="middle" font-size="12" fill="var(--text-muted)">${lbl}</text>`;
            }
        });
        svg.setAttribute('viewBox', `0 0 ${cw} ${h}`);
        svg.innerHTML = inner;
    }

    function renderAllCharts() {
        document.querySelectorAll('svg[data-bar-chart]').forEach(renderBarChart);
    }
    document.querySelectorAll('[data-chart-range]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const range = btn.getAttribute('data-chart-range');
            const card = btn.closest('section');
            const svg = card?.querySelector('svg[data-bar-chart]');
            if (!svg) return;
            card.querySelectorAll('[data-chart-range]').forEach((b) => b.classList.toggle('active', b === btn));
            svg.setAttribute('data-active-range', range);
            const countBadge = card.querySelector('[data-chart-total]');
            if (countBadge) {
                const rawVals = JSON.parse(svg.getAttribute('data-values-' + range) || '[]');
                const tot = rawVals.reduce((a, b) => a + Number(b || 0), 0);
                countBadge.textContent = tot;
            }
            renderBarChart(svg);
        });
    });
    renderAllCharts();
    let chartTimer;
    const scheduleChartRedraw = () => { clearTimeout(chartTimer); chartTimer = setTimeout(renderAllCharts, 140); };
    window.addEventListener('resize', scheduleChartRedraw);
    window.addEventListener('orientationchange', scheduleChartRedraw);

    // 10. Coupon Scope Toggle + SMTP Test Email
    const couponAllCb = document.getElementById('bx-coupon-all-pdfs');
    const couponItemsBox = document.getElementById('bx-coupon-items-box');
    if (couponAllCb && couponItemsBox) {
        const syncCouponScope = () => { couponItemsBox.style.display = couponAllCb.checked ? 'none' : 'block'; };
        couponAllCb.addEventListener('change', syncCouponScope);
        syncCouponScope();
    }

    const btnTestSmtp = document.getElementById('btn-send-test-smtp');
    const smtpRes = document.getElementById('smtp-test-result');
    if (btnTestSmtp && smtpRes) {
        btnTestSmtp.addEventListener('click', async () => {
            const to = document.getElementById('smtp_test_to')?.value.trim() || '';
            if (!to) {
                smtpRes.textContent = 'Enter a recipient email address first.';
                smtpRes.style.color = 'var(--rose)';
                return;
            }
            btnTestSmtp.disabled = true;
            smtpRes.textContent = 'Sending test email...';
            smtpRes.style.color = 'var(--text-muted)';
            try {
                const payload = new URLSearchParams({
                    _csrf: csrfToken,
                    to,
                    smtp_host: document.getElementById('smtp_host')?.value.trim() || '',
                    smtp_port: document.getElementById('smtp_port')?.value.trim() || '587',
                    smtp_encryption: document.getElementById('smtp_encryption')?.value || 'tls',
                    smtp_user: document.getElementById('smtp_user')?.value.trim() || '',
                    smtp_pass: document.getElementById('smtp_pass')?.value || '',
                    smtp_from_name: document.getElementById('smtp_from_name')?.value.trim() || '',
                    smtp_from_email: document.getElementById('smtp_from_email')?.value.trim() || ''
                });
                const res = await fetch(btnTestSmtp.getAttribute('data-endpoint'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: payload
                });
                const data = await res.json();
                if (data.ok) {
                    smtpRes.textContent = data.message || 'Sent!';
                    smtpRes.style.color = 'var(--teal-text)';
                } else {
                    smtpRes.textContent = data.message || 'Failed';
                    smtpRes.style.color = 'var(--rose-text)';
                }
            } catch (err) {
                smtpRes.textContent = 'Request failed.';
                smtpRes.style.color = 'var(--rose-text)';
            } finally {
                btnTestSmtp.disabled = false;
            }
        });
    }

    // 11. Media & CDN Admin Panel Controls (Test, Batch Sync Progress Bar, Retry, Recheck, Pull)
    const cdnStatusEl = document.getElementById('cdn-sync-status');
    const cdnPctEl    = document.getElementById('cdn-sync-pct');
    const cdnFillEl   = document.getElementById('cdn-sync-progress-fill');

    async function postCdnAction(btn, bodyParams) {
        const endpoint = btn?.getAttribute('data-endpoint');
        if (!endpoint) return null;
        if (btn) btn.disabled = true;
        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(Object.assign({ _csrf: csrfToken }, bodyParams || {}))
            });
            return await res.json();
        } catch (err) {
            return { ok: false, message: 'Network error' };
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    document.getElementById('btn-cdn-test')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        if (cdnStatusEl) cdnStatusEl.textContent = 'Testing CDN write/read/delete...';
        const data = await postCdnAction(btn);
        if (cdnStatusEl) cdnStatusEl.textContent = (data && data.message) || 'Check complete.';
        if (window.bxToast) window.bxToast((data && data.message) || 'Check complete', data && data.ok ? 'success' : 'danger');
    });

    async function runBatchCdnSync(btn, retryFailedOnly) {
        const endpoint = btn?.getAttribute('data-endpoint');
        if (!endpoint) return;
        btn.disabled = true;
        let totalDone = 0;
        let initialPending = parseInt(document.getElementById('cdn-stat-pending')?.textContent || '0', 10) || 1;
        try {
            for (let loop = 0; loop < 60; loop++) {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf: csrfToken, retry_failed: retryFailedOnly ? '1' : '0' })
                });
                const data = await res.json();
                if (!data || !data.ok) {
                    if (cdnStatusEl) cdnStatusEl.textContent = (data && data.message) || 'CDN sync error.';
                    if (window.bxToast) window.bxToast((data && data.message) || 'CDN sync error', 'danger');
                    break;
                }
                totalDone += Number(data.synced || 0);
                const rem = Number(data.remaining || 0);
                const pct = rem === 0 ? 100 : Math.min(99, Math.round((totalDone / Math.max(1, initialPending)) * 100));
                if (cdnPctEl) cdnPctEl.textContent = pct + '%';
                if (cdnFillEl) cdnFillEl.style.width = pct + '%';
                if (cdnStatusEl) cdnStatusEl.textContent = `Synced ${totalDone} file(s), ${rem} pending...`;
                if (rem === 0 || Number(data.processed || 0) === 0) {
                    if (window.bxToast) window.bxToast(`CDN sync finished (${totalDone} synced).`, 'success');
                    setTimeout(() => window.location.reload(), 650);
                    break;
                }
            }
        } finally {
            btn.disabled = false;
        }
    }

    document.getElementById('btn-cdn-sync-all')?.addEventListener('click', (e) => runBatchCdnSync(e.currentTarget, false));
    document.getElementById('btn-cdn-retry-failed')?.addEventListener('click', (e) => runBatchCdnSync(e.currentTarget, true));

    document.getElementById('btn-cdn-recheck')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        if (cdnStatusEl) cdnStatusEl.textContent = 'Checking files on CDN...';
        const data = await postCdnAction(btn);
        if (window.bxToast) window.bxToast((data && data.message) || 'Recheck done', data && data.ok ? 'success' : 'danger');
        if (data && data.ok) setTimeout(() => window.location.reload(), 550);
    });

    document.getElementById('btn-cdn-pull')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        if (cdnStatusEl) cdnStatusEl.textContent = 'Pulling files from CDN to local /uploads...';
        const data = await postCdnAction(btn);
        if (window.bxToast) window.bxToast((data && data.message) || 'Pull finished', data && data.ok ? 'success' : 'danger');
        if (data && data.ok) setTimeout(() => window.location.reload(), 550);
    });
})();
