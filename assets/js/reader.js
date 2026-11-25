/**
 * Bookexa Hardened Protected Reader (Page-Image Mode + Legacy Free PDF.js Fallback)
 * - Part A: SVG toolbar UI, smart blur/devtools shield (touch-friendly), per-user theme/zoom/page persistence
 * - Part B: Zero full-PDF delivery for protected/paid items — fetches dynamically GD-watermarked pages via /api/page
 */
(function () {
    'use strict';

    let cfg = window.BX_READER_CONFIG || null;
    if (!cfg) {
        try {
            const rawCfg = document.getElementById('bx-reader-config')?.textContent || '';
            if (rawCfg) cfg = JSON.parse(rawCfg);
        } catch (err) {}
    }
    if (!cfg) return;

    const shellEl       = document.getElementById('reader-shell');
    const loaderEl      = document.getElementById('reader-loader');
    const loaderStatus  = document.getElementById('loader-status');
    const renderSkel    = document.getElementById('page-render-skel');
    const spreadEl      = document.getElementById('page-spread');
    const canvas1       = document.getElementById('pdf-canvas-1');
    const canvas2       = document.getElementById('pdf-canvas-2');
    const card2         = document.getElementById('page-card-2');
    const stageEl       = document.getElementById('reader-stage');
    const thumbSidebar  = document.getElementById('thumb-sidebar');
    const thumbList     = document.getElementById('thumb-list');
    const btnThumbs     = document.getElementById('btn-thumbs');
    const btnThumbsCls  = document.getElementById('btn-thumbs-close');
    const jumpInput     = document.getElementById('page-jump');
    const pageCountEl   = document.getElementById('page-count');
    const progressFill  = document.getElementById('bx-read-progress-fill');
    const btnPrev       = document.getElementById('btn-prev');
    const btnNext       = document.getElementById('btn-next');
    const btnSpread     = document.getElementById('btn-spread');
    const btnZoomOut    = document.getElementById('btn-zoom-out');
    const btnFitWidth   = document.getElementById('btn-fit-width');
    const btnZoomIn     = document.getElementById('btn-zoom-in');
    const btnTheme      = document.getElementById('btn-reader-theme');
    const btnFull       = document.getElementById('btn-fullscreen');
    const settingsSheet = document.getElementById('settings-sheet');
    const btnOpenSet    = document.getElementById('btn-open-settings');
    const btnCloseSet   = document.getElementById('btn-close-settings');
    const shieldEl      = document.getElementById('reader-shield');
    const shieldMsgEl   = document.getElementById('shield-message');
    const btnResume     = document.getElementById('btn-resume');

    const userPrefThemeKey = 'bookexa_reader_theme_u' + (cfg.userId || 0);
    const userPrefZoomKey  = 'bookexa_reader_zoom_u' + (cfg.userId || 0);
    const userPrefPageKey  = 'bookexa_reader_page_u' + (cfg.userId || 0) + '_' + (cfg.itemId || 0);

    let pdfDoc = null;
    let readerReady = false;
    let currentPage = 1;
    let allowedPages = 1;
    let doublePage = false;
    let fitScale = 1.0;
    let zoomFactor = 1.0;
    let rendering = false;
    let pendingRender = false;
    let sessionKicked = false;
    let shieldedByBlur = false;
    let shieldedByResize = false;
    let blurTimer = null;
    let prefSyncTimer = null;
    let thumbsInitialized = false;
    let thumbObs = null;

    let lastWinW = window.innerWidth;
    let lastWinH = window.innerHeight;
    let lastOuterW = window.outerWidth;
    let lastOuterH = window.outerHeight;

    // Bounded in-memory LRU bitmap cache (max 5 pages) for Protected Page Mode
    const pageBitmapCache = new Map();

    const isTouchDevice = () => window.matchMedia('(hover: none), (pointer: coarse)').matches;
    const isNarrowViewport = () => window.innerWidth < 1024;

    // Load saved per-user zoom
    (function initSavedZoom() {
        let z = parseFloat(cfg.initialZoom || '');
        if (!z || isNaN(z)) {
            try { z = parseFloat(localStorage.getItem(userPrefZoomKey) || ''); } catch (e) {}
        }
        if (z && !isNaN(z)) {
            zoomFactor = Math.max(0.65, Math.min(3.0, z));
        }
    })();

    function queuePrefsSync(themeVal, zoomVal) {
        clearTimeout(prefSyncTimer);
        prefSyncTimer = setTimeout(() => {
            const payload = { _csrf: cfg.csrfToken, item_id: String(cfg.itemId), is_sample: cfg.isSample ? '1' : '0' };
            if (themeVal) payload.reader_theme = themeVal;
            if (zoomVal) payload.reader_zoom = Number(zoomVal).toFixed(2);
            fetch(cfg.progressUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(payload)
            }).catch(() => {});
        }, 350);
    }

    // 1. Themes (Dark / Sepia / Light) persisted per user
    const themes = ['dark', 'sepia', 'light'];
    function applyTheme(next, persistServer) {
        if (!themes.includes(next)) next = 'dark';
        document.documentElement.setAttribute('data-reader-theme', next);
        try {
            localStorage.setItem(userPrefThemeKey, next);
            localStorage.setItem('bookexa_reader_theme', next);
        } catch (e) {}
        document.querySelectorAll('[data-set-theme]').forEach((b) => {
            b.classList.toggle('is-active-theme', b.getAttribute('data-set-theme') === next);
        });
        if (persistServer) {
            queuePrefsSync(next, null);
        }
    }
    applyTheme(cfg.initialTheme || document.documentElement.getAttribute('data-reader-theme') || 'dark', false);

    btnTheme?.addEventListener('click', () => {
        const cur = document.documentElement.getAttribute('data-reader-theme') || 'dark';
        applyTheme(themes[(themes.indexOf(cur) + 1) % themes.length], true);
    });
    document.querySelectorAll('[data-set-theme]').forEach((b) => {
        b.addEventListener('click', () => applyTheme(b.getAttribute('data-set-theme'), true));
    });

    // Settings & Thumbnails Sheets
    function closeSheets() {
        if (settingsSheet) settingsSheet.classList.remove('open');
        if (thumbSidebar && (isTouchDevice() || isNarrowViewport())) thumbSidebar.classList.remove('open');
    }
    btnOpenSet?.addEventListener('click', () => {
        if (thumbSidebar && (isTouchDevice() || isNarrowViewport())) thumbSidebar.classList.remove('open');
        settingsSheet?.classList.add('open');
    });
    btnCloseSet?.addEventListener('click', () => settingsSheet?.classList.remove('open'));
    settingsSheet?.addEventListener('click', (e) => {
        if (e.target === settingsSheet) settingsSheet.classList.remove('open');
    });

    // 2. Canvas Watermark (used ONLY in legacy free PDF.js fallback; Protected Page Mode burns watermark via PHP GD on server)
    function burnFallbackWatermark(canvas, cssW, cssH, dpr, isThumb) {
        const ctx = canvas.getContext('2d');
        ctx.save();
        ctx.scale(dpr, dpr);
        const fontSize = isThumb ? 7 : Math.max(10, Math.min(18, Math.round(cssW * 0.026)));
        const stamp = cfg.watermarkText + (isThumb ? '' : (' • ' + new Date().toISOString().slice(0, 16).replace('T', ' ')));
        ctx.font = `600 ${fontSize}px "Hind Siliguri", sans-serif`;
        ctx.fillStyle = isThumb ? 'rgba(180, 42, 32, 0.16)' : 'rgba(180, 42, 32, 0.20)';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        const stepX = isThumb ? 110 : Math.max(150, Math.round(cssW * 0.52));
        const stepY = isThumb ? 70 : Math.max(95, Math.round(cssH * 0.22));
        ctx.translate(cssW / 2, cssH / 2);
        ctx.rotate(-28 * Math.PI / 180);
        for (let y = -cssH; y <= cssH; y += stepY) {
            for (let x = -cssW; x <= cssW; x += stepX) {
                ctx.fillText(stamp, x, y);
            }
        }
        ctx.restore();
    }

    function clearCanvasMemory(c) {
        if (!c) return;
        const ctx = c.getContext('2d');
        if (ctx) ctx.clearRect(0, 0, c.width, c.height);
        c.width = 0;
        c.height = 0;
    }

    function releaseBitmapCache() {
        pageBitmapCache.forEach((bmp) => {
            if (bmp && typeof bmp.close === 'function') {
                try { bmp.close(); } catch (e) {}
            }
        });
        pageBitmapCache.clear();
    }

    function clearCanvases() {
        clearCanvasMemory(canvas1);
        clearCanvasMemory(canvas2);
    }

    function logSecurityEvent(eventName) {
        fetch(cfg.secEventUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _csrf: cfg.csrfToken, item_id: String(cfg.itemId), event: eventName })
        }).catch(() => {});
    }

    function showShield(msg, permanent, reason) {
        clearCanvases();
        if (permanent) releaseBitmapCache();
        shieldedByBlur = (reason === 'blur');
        shieldedByResize = (reason === 'resize');
        shieldMsgEl.textContent = msg;
        shieldEl.classList.add('active');
        if (permanent) {
            sessionKicked = true;
            btnResume.textContent = 'OK';
            btnResume.onclick = () => { window.location.href = cfg.backUrl; };
        }
    }

    function hideShieldAndResume() {
        if (sessionKicked || shieldedByResize) return;
        shieldedByBlur = false;
        shieldEl.classList.remove('active');
        if (readerReady) {
            renderVisiblePages(true);
        }
    }

    function saveProgress(page, total) {
        try {
            if (!cfg.isSample) localStorage.setItem(userPrefPageKey, String(page));
        } catch (e) {}
        if (cfg.isSample) return;
        fetch(cfg.progressUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                _csrf: cfg.csrfToken,
                item_id: String(cfg.itemId),
                last_page: String(page),
                total_pages: String(total)
            })
        }).catch(() => {});
    }

    function computeSafeDpr(cssW, cssH) {
        const raw = window.devicePixelRatio || 1;
        const maxCap = (navigator.deviceMemory && navigator.deviceMemory <= 4) || window.innerWidth < 768 ? 1.75 : 2.0;
        const maxTotalPixels = 3800000;
        const memDpr = Math.sqrt(maxTotalPixels / Math.max(1, cssW * cssH));
        return Math.max(1, Math.min(raw, maxCap, memDpr));
    }

    // 3A. Protected Image Page Loader (zero PDF bytes sent to user browser)
    async function fetchProtectedPageBitmap(pageNum) {
        if (pageBitmapCache.has(pageNum)) {
            const existing = pageBitmapCache.get(pageNum);
            pageBitmapCache.delete(pageNum);
            pageBitmapCache.set(pageNum, existing);
            return existing;
        }
        const sep = cfg.pageApiBase.includes('?') ? '&' : '?';
        const url = `${cfg.pageApiBase}${sep}page=${pageNum}`;
        const resp = await fetch(url, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { 'X-Bookexa-Reader': '1' }
        });
        if (resp.status === 409) {
            showShield(cfg.kickedMessage, true, 'kick');
            throw new Error('SESSION_KICKED');
        }
        if (!resp.ok) {
            let msg = cfg.loadErrorMsg;
            try {
                const j = await resp.json();
                if (j && j.message) msg = j.message;
            } catch (e) {}
            const err = new Error(msg);
            err.httpStatus = resp.status;
            throw err;
        }

        const blob = await resp.blob();
        let bmp;
        if (typeof window.createImageBitmap === 'function') {
            bmp = await createImageBitmap(blob);
        } else {
            bmp = await new Promise((resolve, reject) => {
                const img = new Image();
                const objUrl = URL.createObjectURL(blob);
                img.onload = () => { URL.revokeObjectURL(objUrl); resolve(img); };
                img.onerror = () => { URL.revokeObjectURL(objUrl); reject(new Error(cfg.loadErrorMsg)); };
                img.src = objUrl;
            });
        }

        if (pageBitmapCache.size >= 5) {
            const oldestKey = pageBitmapCache.keys().next().value;
            const oldBmp = pageBitmapCache.get(oldestKey);
            pageBitmapCache.delete(oldestKey);
            if (oldBmp && typeof oldBmp.close === 'function') {
                try { oldBmp.close(); } catch (e) {}
            }
        }
        pageBitmapCache.set(pageNum, bmp);
        return bmp;
    }

    function drawProtectedBitmapToCanvas(bmp, targetCanvas, isThumb) {
        const useSpread = doublePage && window.innerWidth >= 1024 && allowedPages > 1;
        let cssW, cssH;
        if (isThumb) {
            cssW = 180;
            cssH = Math.max(100, Math.round(cssW * (bmp.height / Math.max(1, bmp.width))));
            targetCanvas.width = cssW;
            targetCanvas.height = cssH;
            targetCanvas.style.width = '100%';
            targetCanvas.style.height = 'auto';
        } else {
            const pad = window.innerWidth < 600 ? 24 : 40;
            const availW = Math.max(220, stageEl.clientWidth - pad);
            const baseW = useSpread ? Math.max(200, (availW - 14) / 2) : Math.min(availW, 920);
            cssW = Math.max(180, Math.round(baseW * zoomFactor));
            cssH = Math.max(200, Math.round(cssW * (bmp.height / Math.max(1, bmp.width))));
            const dpr = computeSafeDpr(cssW, cssH);
            targetCanvas.width = Math.max(1, Math.floor(cssW * dpr));
            targetCanvas.height = Math.max(1, Math.floor(cssH * dpr));
            targetCanvas.style.width = cssW + 'px';
            targetCanvas.style.height = cssH + 'px';
            const ctx = targetCanvas.getContext('2d', { alpha: false });
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.drawImage(bmp, 0, 0, cssW, cssH);
            return;
        }
        const ctx = targetCanvas.getContext('2d', { alpha: false });
        ctx.drawImage(bmp, 0, 0, cssW, cssH);
    }

    // 3B. Legacy Free PDF.js Renderer (only when free_pdfjs_fallback=1 and pages not yet prepared)
    async function computePdfJsFitScale(pageNum) {
        const page = await pdfDoc.getPage(pageNum);
        const unscaled = page.getViewport({ scale: 1 });
        const useSpread = doublePage && window.innerWidth >= 1024 && allowedPages > 1;
        const pad = window.innerWidth < 600 ? 24 : 40;
        const availW = Math.max(220, stageEl.clientWidth - pad);
        const targetW = useSpread ? Math.max(200, (availW - 14) / 2) : Math.min(availW, 920);
        return Math.max(0.35, Math.min(2.6, targetW / unscaled.width));
    }

    async function renderPdfJsCanvas(pageNum, targetCanvas, renderScale, isThumb) {
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({ scale: renderScale });
        const dpr = isThumb ? 1 : computeSafeDpr(viewport.width, viewport.height);
        targetCanvas.width = Math.max(1, Math.floor(viewport.width * dpr));
        targetCanvas.height = Math.max(1, Math.floor(viewport.height * dpr));
        if (isThumb) {
            targetCanvas.style.width = '100%';
            targetCanvas.style.height = 'auto';
        } else {
            targetCanvas.style.width = Math.floor(viewport.width) + 'px';
            targetCanvas.style.height = Math.floor(viewport.height) + 'px';
        }
        const ctx = targetCanvas.getContext('2d', { alpha: false });
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        await page.render({ canvasContext: ctx, viewport }).promise;
        burnFallbackWatermark(targetCanvas, viewport.width, viewport.height, dpr, isThumb);
        if (typeof page.cleanup === 'function') {
            try { page.cleanup(); } catch (e) {}
        }
    }

    let preloadTimer = null;
    async function renderVisiblePages(recomputeFit) {
        if (!readerReady || sessionKicked) return;
        if (rendering) {
            pendingRender = true;
            return;
        }
        rendering = true;
        spreadEl.classList.add('turning');
        renderSkel.style.display = 'flex';

        try {
            const useSpread = doublePage && window.innerWidth >= 1024 && allowedPages > 1;
            stageEl.classList.toggle('is-zoomed', zoomFactor > 1.03);

            if (cfg.mode === 'pages') {
                const bmp1 = await fetchProtectedPageBitmap(currentPage);
                drawProtectedBitmapToCanvas(bmp1, canvas1, false);
                if (useSpread && currentPage + 1 <= allowedPages) {
                    card2.style.display = 'block';
                    const bmp2 = await fetchProtectedPageBitmap(currentPage + 1);
                    drawProtectedBitmapToCanvas(bmp2, canvas2, false);
                } else {
                    card2.style.display = 'none';
                    clearCanvasMemory(canvas2);
                }
                clearTimeout(preloadTimer);
                const nextToPreload = useSpread ? currentPage + 2 : currentPage + 1;
                if (nextToPreload <= allowedPages) {
                    preloadTimer = setTimeout(() => {
                        if (!sessionKicked && !shieldEl.classList.contains('active')) {
                            fetchProtectedPageBitmap(nextToPreload).catch(() => {});
                        }
                    }, 550);
                }
            } else if (cfg.mode === 'pdfjs' && pdfDoc) {
                if (recomputeFit !== false) {
                    fitScale = await computePdfJsFitScale(currentPage);
                }
                const activeScale = Math.max(0.3, Math.min(3.5, fitScale * zoomFactor));
                await renderPdfJsCanvas(currentPage, canvas1, activeScale, false);
                if (useSpread && currentPage + 1 <= allowedPages) {
                    card2.style.display = 'block';
                    await renderPdfJsCanvas(currentPage + 1, canvas2, activeScale, false);
                } else {
                    card2.style.display = 'none';
                    clearCanvasMemory(canvas2);
                }
            }

            if (zoomFactor <= 1.03) stageEl.scrollLeft = 0;
            jumpInput.value = String(currentPage);
            btnPrev.disabled = currentPage <= 1;
            btnNext.disabled = useSpread ? (currentPage + 1 >= allowedPages) : (currentPage >= allowedPages);

            if (progressFill) {
                const pct = Math.min(100, Math.max(0, (currentPage / Math.max(1, allowedPages)) * 100));
                progressFill.style.width = pct.toFixed(1) + '%';
            }

            document.querySelectorAll('.bx-thumb-item').forEach((el) => {
                const p = parseInt(el.getAttribute('data-page'), 10);
                el.classList.toggle('active', p === currentPage || (useSpread && p === currentPage + 1));
            });

            saveProgress(currentPage, allowedPages);
        } catch (err) {
            if (!sessionKicked && err && err.message !== 'SESSION_KICKED') {
                renderErrorState(err.message || cfg.loadErrorMsg, err.httpStatus === 401 || err.httpStatus === 403);
            }
        } finally {
            rendering = false;
            renderSkel.style.display = 'none';
            spreadEl.classList.remove('turning');
            if (pendingRender) {
                pendingRender = false;
                renderVisiblePages(true);
            }
        }
    }

    // 4. Lazy Thumbnails (initialized on demand when Pages drawer opens so opening reader never bursts /api/page)
    function ensureThumbnails() {
        if (thumbsInitialized) return;
        thumbsInitialized = true;
        if (thumbObs) thumbObs.disconnect();
        thumbList.innerHTML = '';

        thumbObs = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const item = entry.target;
                const pNum = parseInt(item.dataset.page, 10);
                const c = item.querySelector('canvas');
                if (entry.isIntersecting) {
                    if (item.dataset.rendered === '1') return;
                    item.dataset.rendered = '1';
                    if (cfg.mode === 'pages') {
                        fetchProtectedPageBitmap(pNum).then((bmp) => drawProtectedBitmapToCanvas(bmp, c, true)).catch(() => {});
                    } else if (pdfDoc) {
                        renderPdfJsCanvas(pNum, c, 0.22, true).catch(() => {});
                    }
                } else if (item.dataset.rendered === '1' && Math.abs(pNum - currentPage) > 6) {
                    clearCanvasMemory(c);
                    item.dataset.rendered = '0';
                }
            });
        }, { root: thumbList, rootMargin: '160px 0px', threshold: 0.01 });

        for (let p = 1; p <= allowedPages; p++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bx-thumb-item';
            btn.dataset.page = String(p);
            btn.innerHTML = '<canvas></canvas><span>' + p + '</span>';
            btn.addEventListener('click', () => {
                goToPage(p);
                if (isTouchDevice() || isNarrowViewport()) thumbSidebar.classList.remove('open');
            });
            thumbList.appendChild(btn);
            thumbObs.observe(btn);
        }
    }

    async function fitToWidth() {
        zoomFactor = 1.0;
        try { localStorage.setItem(userPrefZoomKey, '1.00'); } catch (e) {}
        queuePrefsSync(null, 1.0);
        await renderVisiblePages(true);
    }

    function goToPage(p) {
        const target = Math.max(1, Math.min(p, allowedPages));
        if (target !== currentPage) {
            currentPage = target;
            stageEl.scrollTop = 0;
            renderVisiblePages(true);
        } else {
            jumpInput.value = String(currentPage);
        }
    }

    function renderErrorState(msg, isAuthOrExpired, hideRetry) {
        loaderEl.style.display = 'flex';
        spreadEl.style.display = 'none';
        if (hideRetry) {
            loaderStatus.innerHTML =
                `<p style="color:#F4A62A;margin:0 0 14px;font-weight:600;">${msg}</p>` +
                `<a class="bx-rbtn bx-rbtn-accent" href="${cfg.backUrl}">OK</a>`;
            return;
        }
        const btnLabel = isAuthOrExpired ? cfg.reloadLabel : cfg.retryLabel;
        loaderStatus.innerHTML =
            `<p style="color:#F47296;margin:0 0 14px;font-weight:600;">${msg}</p>` +
            `<button type="button" class="bx-rbtn bx-rbtn-accent" id="btn-retry-pdf">${btnLabel}</button>`;
        document.getElementById('btn-retry-pdf')?.addEventListener('click', () => {
            if (isAuthOrExpired) window.location.reload();
            else {
                loaderStatus.innerHTML = '<p style="margin:0;">Loading...</p>';
                initReader();
            }
        });
    }

    function sanitizeReaderMsg(rawMsg) {
        const m = String(rawMsg || '').trim();
        if (!m || /drive|google|googleapis|curl|http\s+\d{3}/i.test(m)) {
            return cfg.loadErrorMsg;
        }
        return m;
    }

    async function initReader() {
        if (cfg.mode === 'unavailable') {
            renderErrorState(sanitizeReaderMsg(cfg.unavailableMsg || cfg.loadErrorMsg), false, true);
            return;
        }

        try {
            if (cfg.mode === 'pages') {
                allowedPages = Math.max(1, cfg.maxPages || 1);
            } else {
                if (!window.pdfjsLib) {
                    renderErrorState(cfg.loadErrorMsg, false, true);
                    return;
                }
                pdfjsLib.GlobalWorkerOptions.workerSrc = cfg.workerUrl;
                const resp = await fetch(cfg.streamUrl, {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: { 'X-Bookexa-Reader': '1', Range: 'bytes=0-65535' }
                });
                if (resp.status === 409) {
                    showShield(cfg.kickedMessage, true, 'kick');
                    return;
                }
                if (!resp.ok && resp.status !== 206) {
                    let errMsg = cfg.loadErrorMsg;
                    try {
                        const j = await resp.json();
                        if (j && j.message) errMsg = sanitizeReaderMsg(j.message);
                    } catch (e) {}
                    renderErrorState(errMsg, resp.status === 401 || resp.status === 403);
                    return;
                }
                if (resp.status === 206 && resp.headers.get('Accept-Ranges') === 'bytes') {
                    if (resp.body && typeof resp.body.cancel === 'function') {
                        try { resp.body.cancel(); } catch (e) {}
                    }
                    pdfDoc = await pdfjsLib.getDocument({
                        url: cfg.streamUrl,
                        withCredentials: true,
                        httpHeaders: { 'X-Bookexa-Reader': '1' },
                        disableAutoFetch: true,
                        rangeChunkSize: 65536
                    }).promise;
                } else {
                    const buffer = await resp.arrayBuffer();
                    pdfDoc = await pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                }
                allowedPages = cfg.isSample && cfg.maxPages > 0
                    ? Math.min(pdfDoc.numPages, cfg.maxPages)
                    : pdfDoc.numPages;
            }

            jumpInput.max = String(allowedPages);
            pageCountEl.textContent = String(allowedPages);

            let startPage = cfg.initialPage || 1;
            if (!cfg.isSample && startPage <= 1) {
                try {
                    const cachedP = parseInt(localStorage.getItem(userPrefPageKey) || '1', 10);
                    if (cachedP > 1) startPage = cachedP;
                } catch (e) {}
            }
            currentPage = Math.max(1, Math.min(startPage, allowedPages));

            readerReady = true;
            loaderEl.style.display = 'none';
            spreadEl.style.display = 'flex';
            await renderVisiblePages(true);
        } catch (err) {
            if (!sessionKicked) {
                renderErrorState((err && err.message) || cfg.loadErrorMsg, false);
            }
        }
    }

    // 5. Toolbar & Bottom Sheet Controls
    btnPrev.addEventListener('click', () => {
        const step = (doublePage && window.innerWidth >= 1024) ? 2 : 1;
        goToPage(currentPage - step);
    });
    btnNext.addEventListener('click', () => {
        const step = (doublePage && window.innerWidth >= 1024) ? 2 : 1;
        goToPage(currentPage + step);
    });
    jumpInput.addEventListener('change', () => goToPage(parseInt(jumpInput.value, 10) || 1));

    function toggleSpread() {
        doublePage = !doublePage;
        if (btnSpread) btnSpread.classList.toggle('is-double', doublePage);
        renderVisiblePages(true);
    }
    function zoomBy(delta) {
        zoomFactor = Math.max(0.65, Math.min(3.0, Math.round((zoomFactor + delta) * 100) / 100));
        try { localStorage.setItem(userPrefZoomKey, zoomFactor.toFixed(2)); } catch (e) {}
        queuePrefsSync(null, zoomFactor);
        renderVisiblePages(false);
    }

    btnSpread?.addEventListener('click', toggleSpread);
    btnZoomIn?.addEventListener('click', () => zoomBy(0.25));
    btnZoomOut?.addEventListener('click', () => zoomBy(-0.25));
    btnFitWidth?.addEventListener('click', fitToWidth);

    document.getElementById('sheet-zoom-in')?.addEventListener('click', () => zoomBy(0.25));
    document.getElementById('sheet-zoom-out')?.addEventListener('click', () => zoomBy(-0.25));
    document.getElementById('sheet-fit-width')?.addEventListener('click', () => { fitToWidth(); closeSheets(); });
    document.getElementById('sheet-toggle-spread')?.addEventListener('click', toggleSpread);

    btnThumbs?.addEventListener('click', () => {
        settingsSheet?.classList.remove('open');
        const opening = !thumbSidebar.classList.contains('open');
        thumbSidebar.classList.toggle('open');
        if (opening) ensureThumbnails();
    });
    btnThumbsCls?.addEventListener('click', () => thumbSidebar.classList.remove('open'));

    btnFull?.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
        } else {
            document.exitFullscreen().catch(() => {});
        }
    });

    btnResume.addEventListener('click', () => {
        if (sessionKicked) return;
        shieldedByBlur = false;
        shieldedByResize = false;
        shieldEl.classList.remove('active');
        lastWinW = window.innerWidth;
        lastWinH = window.innerHeight;
        renderVisiblePages(true);
    });

    // 6. Touch Gestures: Pinch-to-Zoom, Double-Tap Zoom, Pan, Swipe, Center Tap Bar Toggle
    let pinchStartDist = 0, pinchStartFactor = 1, isPinching = false;
    let panStartX = 0, panStartY = 0, panScrollL = 0, panScrollT = 0;
    let touchMoved = false, singleTapTimer = null;
    let lastTapTime = 0, lastTapX = 0, lastTapY = 0, lastTouchTime = 0;

    function touchDist(t1, t2) {
        return Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
    }

    stageEl.addEventListener('touchstart', (e) => {
        if (e.touches.length === 2) {
            isPinching = true;
            pinchStartDist = touchDist(e.touches[0], e.touches[1]);
            pinchStartFactor = zoomFactor;
            clearTimeout(singleTapTimer);
        } else if (e.touches.length === 1 && !isPinching) {
            const t = e.touches[0];
            panStartX = t.clientX; panStartY = t.clientY;
            panScrollL = stageEl.scrollLeft; panScrollT = stageEl.scrollTop;
            touchMoved = false;
        }
    }, { passive: true });

    stageEl.addEventListener('touchmove', (e) => {
        if (e.touches.length === 2 && isPinching) {
            e.preventDefault();
            const d = touchDist(e.touches[0], e.touches[1]);
            if (pinchStartDist > 10) {
                const ratio = Math.max(0.6, Math.min(2.6, d / pinchStartDist));
                spreadEl.style.transform = `scale(${ratio.toFixed(3)})`;
            }
        } else if (e.touches.length === 1 && !isPinching) {
            const t = e.touches[0];
            const dx = t.clientX - panStartX;
            const dy = t.clientY - panStartY;
            if (Math.abs(dx) > 8 || Math.abs(dy) > 8) touchMoved = true;
            if (zoomFactor > 1.03) {
                stageEl.scrollLeft = panScrollL - dx;
                stageEl.scrollTop  = panScrollT - dy;
            }
        }
    }, { passive: false });

    stageEl.addEventListener('touchend', (e) => {
        lastTouchTime = Date.now();
        if (isPinching && e.touches.length < 2) {
            const match = /scale\(([^)]+)\)/.exec(spreadEl.style.transform || '');
            spreadEl.style.transform = '';
            isPinching = false;
            if (match) {
                const ratio = parseFloat(match[1]) || 1;
                zoomFactor = Math.max(0.65, Math.min(3.0, Math.round(pinchStartFactor * ratio * 100) / 100));
                try { localStorage.setItem(userPrefZoomKey, zoomFactor.toFixed(2)); } catch (er) {}
                queuePrefsSync(null, zoomFactor);
                renderVisiblePages(false);
            }
            return;
        }
        if (!e.changedTouches || !e.changedTouches[0]) return;
        const t = e.changedTouches[0];
        const dx = t.clientX - panStartX;
        const dy = t.clientY - panStartY;

        if (Math.abs(dx) > 60 && Math.abs(dy) < 55 && zoomFactor <= 1.05) {
            clearTimeout(singleTapTimer);
            if (dx < 0) goToPage(currentPage + 1);
            else goToPage(currentPage - 1);
            return;
        }
        if (!touchMoved && Math.abs(dx) < 10 && Math.abs(dy) < 10) {
            const now = Date.now();
            if (now - lastTapTime < 280 && Math.hypot(t.clientX - lastTapX, t.clientY - lastTapY) < 32) {
                clearTimeout(singleTapTimer);
                lastTapTime = 0;
                zoomFactor = zoomFactor > 1.15 ? 1.0 : 1.75;
                try { localStorage.setItem(userPrefZoomKey, zoomFactor.toFixed(2)); } catch (er) {}
                queuePrefsSync(null, zoomFactor);
                renderVisiblePages(zoomFactor === 1.0);
                return;
            }
            lastTapTime = now; lastTapX = t.clientX; lastTapY = t.clientY;
            singleTapTimer = setTimeout(() => {
                const rect = stageEl.getBoundingClientRect();
                if (t.clientX > rect.left + rect.width * 0.18 && t.clientX < rect.right - rect.width * 0.18 && shellEl) {
                    shellEl.classList.toggle('is-bars-hidden');
                }
            }, 260);
        }
    }, { passive: true });

    stageEl.addEventListener('click', (e) => {
        if (Date.now() - lastTouchTime < 500) return;
        if (e.target.closest('button, a, input')) return;
        if (shellEl) shellEl.classList.toggle('is-bars-hidden');
    });

    // 7. Smart Anti-Piracy Shield & Keyboard Controls
    document.addEventListener('contextmenu', (e) => e.preventDefault());
    document.addEventListener('dragstart', (e) => e.preventDefault());
    document.addEventListener('copy', (e) => e.preventDefault());
    document.addEventListener('cut', (e) => e.preventDefault());

    document.addEventListener('keydown', (e) => {
        const key = (e.key || '').toLowerCase();
        const mod = e.ctrlKey || e.metaKey;
        if (
            (mod && ['p', 's', 'c', 'u', 'a'].includes(key)) ||
            key === 'f12' ||
            (mod && e.shiftKey && ['i', 'j', 'c'].includes(key))
        ) {
            e.preventDefault();
            e.stopPropagation();
            if (mod && key === 'p') logSecurityEvent('print_attempt');
            return false;
        }
        if (document.activeElement !== jumpInput) {
            if (key === 'arrowright') goToPage(currentPage + 1);
            else if (key === 'arrowleft') goToPage(currentPage - 1);
            else if (key === 'f' && btnFull) btnFull.click();
            else if (key === 'escape') closeSheets();
        }
    }, true);

    window.addEventListener('beforeprint', () => {
        logSecurityEvent('print_attempt');
        showShield(cfg.blurMessage, false, 'print');
    });

    // Visibility hidden (tab switch / minimize) triggers shield across all devices
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && !sessionKicked && readerReady) {
            showShield(cfg.blurMessage, false, 'blur');
        }
    });

    // Desktop-only delayed window blur (700ms) + auto-restore when focus returns; never trigger blur on touch devices
    window.addEventListener('blur', () => {
        if (isTouchDevice() || isNarrowViewport() || sessionKicked || !readerReady) return;
        clearTimeout(blurTimer);
        blurTimer = setTimeout(() => {
            if (!document.hasFocus() && !sessionKicked && readerReady) {
                showShield(cfg.blurMessage, false, 'blur');
            }
        }, 700);
    });

    window.addEventListener('focus', () => {
        clearTimeout(blurTimer);
        if (shieldedByBlur && !sessionKicked) {
            hideShieldAndResume();
        }
    });

    // Desktop-only DevTools docked resize check; ignores mobile address-bar & orientation changes
    let resizeTimer = null;
    function handleViewportChange() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (sessionKicked || !readerReady) return;
            const isOrientationFlip = Math.abs(window.innerWidth - lastWinH) <= 24 && Math.abs(window.innerHeight - lastWinW) <= 24;
            const outerUnchanged = window.outerWidth === lastOuterW && window.outerHeight === lastOuterH;
            const shrankInsideFixedWindow = outerUnchanged && ((lastWinW - window.innerWidth > 260) || (lastWinH - window.innerHeight > 260));
            if (!isTouchDevice() && window.innerWidth >= 1024 && !isOrientationFlip && shrankInsideFixedWindow) {
                logSecurityEvent('devtools_resize');
                showShield(cfg.resizeMessage, false, 'resize');
            }
            lastWinW = window.innerWidth;
            lastWinH = window.innerHeight;
            lastOuterW = window.outerWidth;
            lastOuterH = window.outerHeight;
            renderVisiblePages(true);
        }, 200);
    }
    window.addEventListener('resize', handleViewportChange, { passive: true });
    window.addEventListener('orientationchange', handleViewportChange, { passive: true });

    // 8. Single active reading session heartbeat
    setInterval(async () => {
        if (sessionKicked || !readerReady) return;
        try {
            const res = await fetch(cfg.heartbeatUrl, { cache: 'no-store', credentials: 'same-origin' });
            if (res.status === 409) {
                if (pdfDoc) { try { pdfDoc.destroy(); } catch (e) {} }
                showShield(cfg.kickedMessage, true, 'kick');
            }
        } catch (e) {}
    }, 15000);

    initReader();
})();
