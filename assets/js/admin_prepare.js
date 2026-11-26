/**
 * Bookexa Admin Protected Page Export Tool
 * Streams PDF to authenticated admin browser via local PDF.js, renders each page at ~1400px wide WebP/JPEG (q=0.80),
 * and uploads one-by-one into protected storage/pages/{item_id}/ with resume & retry support.
 */
(function () {
    'use strict';
    let cfg = window.BX_PREPARE_CFG || null;
    if (!cfg) {
        try {
            const rawCfg = document.getElementById('bx-prepare-config')?.textContent || '';
            if (rawCfg) cfg = JSON.parse(rawCfg);
        } catch (err) {}
    }
    let queueCfg = null;
    try {
        const rawQ = document.getElementById('bx-queue-config')?.textContent || '';
        if (rawQ) queueCfg = JSON.parse(rawQ);
    } catch (err) {}
    if ((!cfg && !queueCfg) || !window.pdfjsLib) return;

    const workerUrl = (cfg && cfg.workerUrl) || (queueCfg && queueCfg.workerUrl) || '';
    if (workerUrl) pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;

    function detectWebpMime() {
        const c = document.createElement('canvas');
        c.width = 4; c.height = 4;
        return c.toDataURL('image/webp').startsWith('data:image/webp') ? 'image/webp' : 'image/jpeg';
    }
    const exportMime = detectWebpMime();

    function canvasToBlob(canvas, mime, quality) {
        return new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (blob) resolve(blob);
                else reject(new Error('Failed to encode rendered page image.'));
            }, mime, quality);
        });
    }

    async function renderCover3x4Blob(srcCanvas) {
        const cov = document.createElement('canvas');
        cov.width = 450;
        cov.height = 600;
        const cctx = cov.getContext('2d', { alpha: false });
        cctx.fillStyle = '#ffffff';
        cctx.fillRect(0, 0, cov.width, cov.height);
        const targetRatio = cov.width / cov.height;
        const srcRatio = srcCanvas.width / Math.max(1, srcCanvas.height);
        let drawW = cov.width;
        let drawH = cov.height;
        let offsetX = 0;
        let offsetY = 0;
        if (srcRatio > targetRatio) {
            drawW = cov.height * srcRatio;
            offsetX = (cov.width - drawW) / 2;
        } else {
            drawH = cov.width / Math.max(0.01, srcRatio);
            offsetY = 0;
        }
        cctx.drawImage(srcCanvas, offsetX, offsetY, drawW, drawH);
        return await canvasToBlob(cov, exportMime, 0.85);
    }

    async function uploadAutoCover(autoCoverUrl, csrfToken, itemId, blob, overwrite) {
        if (!autoCoverUrl || !blob) return null;
        const fd = new FormData();
        fd.append('_csrf', csrfToken);
        fd.append('item_id', String(itemId));
        fd.append('overwrite', overwrite ? '1' : '0');
        fd.append('cover', blob, 'auto-cover.' + (exportMime === 'image/webp' ? 'webp' : 'jpg'));
        const res = await fetch(autoCoverUrl, { method: 'POST', credentials: 'same-origin', body: fd });
        return await res.json();
    }

    async function extractOutlineEntries(pdf) {
        const out = [];
        try {
            const raw = await pdf.getOutline();
            if (!Array.isArray(raw) || !raw.length) return out;
            async function walk(items, level) {
                for (const node of items) {
                    if (out.length >= 400) break;
                    let pageNum = null;
                    try {
                        let dest = node.dest;
                        if (typeof dest === 'string') dest = await pdf.getDestination(dest);
                        if (Array.isArray(dest) && dest[0]) {
                            const idx = await pdf.getPageIndex(dest[0]);
                            if (typeof idx === 'number' && idx >= 0) pageNum = idx + 1;
                        }
                    } catch (e) {}
                    const title = String(node.title || '').trim();
                    if (title && pageNum && pageNum >= 1) {
                        out.push({ title: title.slice(0, 180), page: pageNum, level });
                    }
                    if (Array.isArray(node.items) && node.items.length) {
                        await walk(node.items, Math.min(4, level + 1));
                    }
                }
            }
            await walk(raw, 0);
        } catch (e) {}
        return out;
    }

    // Queue Runner Mode (/admin/prepare-queue)
    if (queueCfg && Array.isArray(queueCfg.items)) {
        const btnStart = document.getElementById('btn-queue-start');
        const btnPause = document.getElementById('btn-queue-pause');
        const ovLabel  = document.getElementById('queue-overall-label');
        const ovPct    = document.getElementById('queue-overall-pct');
        const ovFill   = document.getElementById('queue-overall-fill');
        const bkLabel  = document.getElementById('queue-book-label');
        const bkPct    = document.getElementById('queue-book-pct');
        const bkFill   = document.getElementById('queue-book-fill');
        const logBox   = document.getElementById('queue-log');
        let qRunning = false;
        let qPaused = false;

        function appendLog(line) {
            if (!logBox) return;
            const ts = new Date().toTimeString().slice(0, 8);
            logBox.textContent += `[${ts}] ${line}\n`;
            logBox.scrollTop = logBox.scrollHeight;
        }
        async function waitWhilePaused() {
            while (qPaused) {
                await new Promise((r) => setTimeout(r, 250));
            }
        }
        function setRowBadge(itemId, text, cls) {
            const pill = document.querySelector(`#queue-row-${itemId} [data-queue-status]`);
            if (pill) {
                pill.textContent = text;
                pill.className = 'bx-status-pill ' + cls;
            }
        }

        btnPause?.addEventListener('click', () => {
            qPaused = !qPaused;
            btnPause.textContent = qPaused ? 'Resume' : 'Pause';
            appendLog(qPaused ? 'Queue paused by admin.' : 'Queue resumed.');
        });

        btnStart?.addEventListener('click', async () => {
            if (qRunning) return;
            qRunning = true;
            qPaused = false;
            btnStart.disabled = true;
            if (btnPause) { btnPause.disabled = false; btnPause.textContent = 'Pause'; }
            const items = queueCfg.items;
            let doneCount = 0;

            for (const book of items) {
                await waitWhilePaused();
                setRowBadge(book.id, 'Processing...', 'st-pending');
                appendLog(`Starting #${book.id}: ${book.title}`);
                if (bkLabel) bkLabel.textContent = `Book #${book.id}: ${book.title}`;
                try {
                    const probe = await fetch(book.streamUrl, { cache: 'no-store', credentials: 'same-origin', headers: { Range: 'bytes=0-1023' } });
                    const probeCt = (probe.headers.get('content-type') || '').toLowerCase();
                    if (!probe.ok || probeCt.includes('application/json')) {
                        let msg = `HTTP ${probe.status}`;
                        try { const errJ = await probe.json(); if (errJ && errJ.message) msg = errJ.message; } catch (e) {}
                        throw new Error(msg);
                    }
                    const pdf = await pdfjsLib.getDocument({
                        url: book.streamUrl, withCredentials: true, disableAutoFetch: true, disableStream: false, rangeChunkSize: 131072
                    }).promise;
                    const total = pdf.numPages;
                    const tocEntries = await extractOutlineEntries(pdf);
                    const workCanvas = document.createElement('canvas');

                    for (let p = 1; p <= total; p++) {
                        await waitWhilePaused();
                        const page = await pdf.getPage(p);
                        const unscaled = page.getViewport({ scale: 1 });
                        const scale = Math.max(1.0, Math.min(3.5, 1400 / Math.max(1, unscaled.width)));
                        const viewport = page.getViewport({ scale });
                        workCanvas.width = Math.max(1, Math.round(viewport.width));
                        workCanvas.height = Math.max(1, Math.round(viewport.height));
                        const ctx = workCanvas.getContext('2d', { alpha: false });
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, workCanvas.width, workCanvas.height);
                        await page.render({ canvasContext: ctx, viewport }).promise;

                        let pageText = '';
                        try {
                            const tc = await page.getTextContent();
                            pageText = (tc.items || []).map((it) => it.str || '').join(' ').replace(/\s+/g, ' ').trim();
                        } catch (e) {}

                        if (p === 1 && !book.hasCover) {
                            try {
                                const covBlob = await renderCover3x4Blob(workCanvas);
                                const covRes = await uploadAutoCover(queueCfg.autoCoverUrl, queueCfg.csrfToken, book.id, covBlob, false);
                                if (covRes && covRes.ok) appendLog(`  → Auto-generated 3:4 cover synced.`);
                            } catch (ce) {}
                        }

                        const blob = await canvasToBlob(workCanvas, exportMime, 0.80);
                        const fd = new FormData();
                        fd.append('_csrf', queueCfg.csrfToken);
                        fd.append('item_id', String(book.id));
                        fd.append('page_num', String(p));
                        fd.append('reset_staging', p === 1 ? '1' : '0');
                        fd.append('page_text', pageText);
                        fd.append('page_image', blob, `page-${p}.${exportMime === 'image/webp' ? 'webp' : 'jpg'}`);
                        const upRes = await fetch(queueCfg.uploadUrl, { method: 'POST', credentials: 'same-origin', body: fd });
                        const upData = await upRes.json();
                        if (!upRes.ok || !upData.ok) throw new Error((upData && upData.message) || `Page ${p} upload failed`);

                        const bPct = Math.round((p / total) * 100);
                        if (bkPct) bkPct.textContent = bPct + '%';
                        if (bkFill) bkFill.style.width = bPct + '%';
                        if (typeof page.cleanup === 'function') { try { page.cleanup(); } catch (e) {} }
                    }

                    const finRes = await fetch(queueCfg.finalizeUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            _csrf: queueCfg.csrfToken,
                            item_id: String(book.id),
                            total_pages: String(total),
                            toc_json: JSON.stringify(tocEntries)
                        })
                    });
                    const finData = await finRes.json();
                    if (!finRes.ok || !finData.ok) throw new Error((finData && finData.message) || 'Finalize failed');
                    if (typeof pdf.destroy === 'function') { try { pdf.destroy(); } catch (e) {} }

                    setRowBadge(book.id, `Ready (${total}p)`, 'st-active');
                    appendLog(`Completed #${book.id} (${total} pages).`);
                } catch (err) {
                    const msg = (err && err.message) || 'Failed';
                    setRowBadge(book.id, 'Failed', 'st-rejected');
                    appendLog(`ERROR on #${book.id}: ${msg}`);
                    try {
                        await fetch(queueCfg.discardUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ _csrf: queueCfg.csrfToken, item_id: String(book.id) })
                        });
                    } catch (de) {}
                }
                doneCount++;
                const oPct = Math.round((doneCount / items.length) * 100);
                if (ovPct) ovPct.textContent = oPct + '%';
                if (ovFill) ovFill.style.width = oPct + '%';
                if (ovLabel) ovLabel.textContent = `Processed ${doneCount} of ${items.length} book(s)`;
            }
            appendLog('Queue run finished.');
            if (window.bxToast) window.bxToast('Prepare queue finished!', 'success');
            qRunning = false;
            btnStart.disabled = false;
            if (btnPause) btnPause.disabled = true;
        });
        return;
    }

    if (!cfg) return;

    const btnStart      = document.getElementById('btn-start-prepare');
    const btnLocalStart = document.getElementById('btn-start-local-prepare');
    const localFileIn   = document.getElementById('prep-local-file');
    const btnReprocess  = document.getElementById('btn-reprocess-all');
    const btnRetry      = document.getElementById('btn-retry-prepare');
    const fillEl        = document.getElementById('prep-progress-fill');
    const counterEl     = document.getElementById('prep-counter');
    const percentEl     = document.getElementById('prep-percent');
    const statusEl      = document.getElementById('prep-status-text');
    const badgeEl       = document.getElementById('prep-status-badge');
    const previewCvs    = document.getElementById('prep-preview-canvas');
    const autoCovImg    = document.getElementById('auto-cover-preview');
    const autoCovNote   = document.getElementById('auto-cover-note');
    const btnApplyCov   = document.getElementById('btn-apply-auto-cover');

    let pdfDoc = null;
    let currentSourceMode = 'drive'; // 'drive' | 'local'
    let localObjUrl = null;
    let nextPageToProcess = Math.max(1, cfg.resumeFrom || 1);
    let running = false;
    let lastPage1CoverBlob = null;

    function setProgress(done, total, msg) {
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        if (fillEl) fillEl.style.width = pct + '%';
        if (percentEl) percentEl.textContent = pct + '%';
        if (counterEl && total > 0) counterEl.textContent = `Rendered & saved page ${done} of ${total}`;
        if (statusEl && msg) statusEl.textContent = msg;
    }

    btnApplyCov?.addEventListener('click', async () => {
        if (!lastPage1CoverBlob || !cfg.autoCoverUrl) return;
        btnApplyCov.disabled = true;
        try {
            const res = await uploadAutoCover(cfg.autoCoverUrl, cfg.csrfToken, cfg.itemId, lastPage1CoverBlob, true);
            if (res && res.ok) {
                if (autoCovImg && res.cover_url) { autoCovImg.src = res.cover_url; autoCovImg.style.display = 'block'; }
                if (autoCovNote) autoCovNote.textContent = res.message || 'Cover replaced with Page 1 (3:4) & synced to CDN.';
                if (window.bxToast) window.bxToast(res.message || 'Cover updated!', 'success');
            }
        } catch (err) {
            if (window.bxToast) window.bxToast('Could not update cover.', 'danger');
        } finally {
            btnApplyCov.disabled = false;
        }
    });

    function resetLoadedDoc() {
        if (pdfDoc && typeof pdfDoc.destroy === 'function') {
            try { pdfDoc.destroy(); } catch (e) {}
        }
        pdfDoc = null;
        if (localObjUrl) {
            URL.revokeObjectURL(localObjUrl);
            localObjUrl = null;
        }
    }

    async function loadSourcePdf(mode) {
        if (pdfDoc && currentSourceMode === mode && mode === 'drive') return pdfDoc;
        resetLoadedDoc();
        currentSourceMode = mode;

        if (mode === 'local') {
            const file = localFileIn && localFileIn.files && localFileIn.files[0];
            if (!file) {
                throw new Error('Please choose a .pdf file from your computer first.');
            }
            const mb = (file.size / 1048576).toFixed(1);
            setProgress(0, 0, `Reading local PDF (${mb} MB) in slices...`);
            if (typeof pdfjsLib.PDFDataRangeTransport === 'function') {
                class LocalFileRangeTransport extends pdfjsLib.PDFDataRangeTransport {
                    constructor(f) {
                        super(f.size, null);
                        this.file = f;
                    }
                    requestDataRange(begin, end) {
                        this.file.slice(begin, end).arrayBuffer().then((buf) => {
                            this.onDataRange(begin, new Uint8Array(buf));
                        }).catch((err) => {
                            this.abort();
                            console.error(err);
                        });
                    }
                }
                const range = new LocalFileRangeTransport(file);
                pdfDoc = await pdfjsLib.getDocument({
                    range,
                    disableAutoFetch: true,
                    rangeChunkSize: 131072
                }).promise;
            } else {
                const buf = await file.arrayBuffer();
                pdfDoc = await pdfjsLib.getDocument({ data: new Uint8Array(buf) }).promise;
            }
            return pdfDoc;
        }

        if (!cfg.streamUrl) {
            throw new Error('No Drive link is configured for this book. Use "Pick PDF Directly from Computer".');
        }
        setProgress(0, 0, 'Streaming source PDF via HTTP Range into admin browser...');
        // Quick byte-0 probe so any server/Drive JSON error message is caught cleanly before PDF.js opens the stream
        const probe = await fetch(cfg.streamUrl, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { Range: 'bytes=0-1023' }
        });
        if (!probe.ok && probe.status !== 206) {
            let errMsg = `Could not stream source PDF (${probe.status})`;
            try {
                const j = await probe.json();
                if (j && j.message) errMsg = j.message;
            } catch (e) {}
            throw new Error(errMsg);
        }
        if (probe.body && typeof probe.body.cancel === 'function') {
            try { probe.body.cancel(); } catch (e) {}
        }

        pdfDoc = await pdfjsLib.getDocument({
            url: cfg.streamUrl,
            withCredentials: true,
            disableAutoFetch: true,
            rangeChunkSize: 131072
        }).promise;
        return pdfDoc;
    }

    async function uploadPageWithRetry(pageNum, blob, maxTries, resetStaging, pageText) {
        const ext = exportMime === 'image/webp' ? 'webp' : 'jpg';
        let lastErr = null;
        for (let attempt = 1; attempt <= maxTries; attempt++) {
            try {
                const fd = new FormData();
                fd.append('_csrf', cfg.csrfToken);
                fd.append('item_id', String(cfg.itemId));
                fd.append('page_num', String(pageNum));
                if (resetStaging && attempt === 1) {
                    fd.append('reset_staging', '1');
                }
                if (typeof pageText === 'string') {
                    fd.append('page_text', pageText);
                }
                fd.append('page_blob', blob, `page-${pageNum}.${ext}`);
                const res = await fetch(cfg.uploadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    throw new Error((data && data.message) || `Upload failed on page ${pageNum}`);
                }
                return;
            } catch (err) {
                lastErr = err;
                if (attempt < maxTries) {
                    await new Promise((r) => setTimeout(r, 450 * attempt));
                }
            }
        }
        throw lastErr || new Error('Page upload failed.');
    }

    async function runExport(startAt, mode) {
        if (running) return;
        running = true;
        if (btnStart) btnStart.disabled = true;
        if (btnLocalStart) btnLocalStart.disabled = true;
        if (btnReprocess) btnReprocess.disabled = true;
        if (btnRetry) btnRetry.style.display = 'none';

        try {
            const pdf = await loadSourcePdf(mode || currentSourceMode);
            const total = pdf.numPages;
            const tocEntries = await extractOutlineEntries(pdf);
            const workCanvas = document.createElement('canvas');

            for (let p = Math.max(1, startAt); p <= total; p++) {
                nextPageToProcess = p;
                setProgress(p - 1, total, `Rendering page ${p} of ${total}...`);
                const page = await pdf.getPage(p);
                const unscaled = page.getViewport({ scale: 1 });
                const scale = Math.max(1.0, Math.min(3.5, 1400 / Math.max(1, unscaled.width)));
                const viewport = page.getViewport({ scale });

                workCanvas.width = Math.max(1, Math.round(viewport.width));
                workCanvas.height = Math.max(1, Math.round(viewport.height));
                const ctx = workCanvas.getContext('2d', { alpha: false });
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, workCanvas.width, workCanvas.height);

                await page.render({ canvasContext: ctx, viewport }).promise;

                let pageText = '';
                try {
                    const tc = await page.getTextContent();
                    pageText = (tc.items || []).map((it) => it.str || '').join(' ').replace(/\s+/g, ' ').trim();
                } catch (e) {}

                if (previewCvs) {
                    previewCvs.width = Math.min(260, workCanvas.width);
                    previewCvs.height = Math.round(previewCvs.width * (workCanvas.height / workCanvas.width));
                    const pctx = previewCvs.getContext('2d');
                    pctx.drawImage(workCanvas, 0, 0, previewCvs.width, previewCvs.height);
                }

                if (p === 1 && cfg.autoCoverUrl) {
                    try {
                        lastPage1CoverBlob = await renderCover3x4Blob(workCanvas);
                        if (autoCovImg) {
                            autoCovImg.src = URL.createObjectURL(lastPage1CoverBlob);
                            autoCovImg.style.display = 'block';
                        }
                        if (btnApplyCov) btnApplyCov.style.display = 'inline-flex';
                        if (!cfg.hasCover) {
                            const covRes = await uploadAutoCover(cfg.autoCoverUrl, cfg.csrfToken, cfg.itemId, lastPage1CoverBlob, false);
                            if (covRes && covRes.ok) {
                                cfg.hasCover = true;
                                if (autoCovImg && covRes.cover_url) autoCovImg.src = covRes.cover_url;
                                if (autoCovNote) autoCovNote.textContent = covRes.message || 'Auto cover (3:4) generated from Page 1 & synced.';
                            }
                        }
                    } catch (covErr) {}
                }

                const blob = await canvasToBlob(workCanvas, exportMime, 0.80);
                setProgress(p - 1, total, `Uploading page ${p} of ${total} (${Math.round(blob.size / 1024)} KB)...`);
                await uploadPageWithRetry(p, blob, 3, p === 1 && startAt === 1, pageText);
                setProgress(p, total, `Saved page ${p} of ${total}.`);

                if (typeof page.cleanup === 'function') {
                    try { page.cleanup(); } catch (e) {}
                }
            }

            setProgress(total, total, 'Finalizing protected pages...');
            const finRes = await fetch(cfg.finalizeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    _csrf: cfg.csrfToken,
                    item_id: String(cfg.itemId),
                    total_pages: String(total),
                    toc_json: JSON.stringify(tocEntries)
                })
            });
            const finData = await finRes.json();
            if (!finRes.ok || !finData.ok) {
                throw new Error((finData && finData.message) || 'Finalize failed');
            }

            if (badgeEl) {
                badgeEl.className = 'bx-badge bx-badge-success';
                badgeEl.textContent = `READY • ${total}p stored`;
            }
            if (statusEl) statusEl.textContent = `All ${total} pages rendered and locked in protected storage.`;
            if (window.bxToast) window.bxToast(finData.message || 'Protected pages ready!', 'success');
        } catch (err) {
            const msg = (err && err.message) || 'Preparation interrupted.';
            if (cfg.discardUrl) {
                try {
                    await fetch(cfg.discardUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            _csrf: cfg.csrfToken,
                            item_id: String(cfg.itemId)
                        })
                    });
                } catch (discardErr) {}
            }
            if (statusEl) statusEl.textContent = `Stopped at page ${nextPageToProcess}: ${msg} (Staging discarded; existing live pages remain untouched.)`;
            nextPageToProcess = 1;
            if (btnRetry) btnRetry.style.display = 'inline-flex';
            if (window.bxToast) window.bxToast(msg, 'danger');
        } finally {
            running = false;
            if (btnStart) btnStart.disabled = false;
            if (btnLocalStart) btnLocalStart.disabled = false;
            if (btnReprocess) btnReprocess.disabled = false;
        }
    }

    btnStart?.addEventListener('click', () => runExport(cfg.processingStatus === 'processing' ? cfg.resumeFrom : 1, 'drive'));
    btnLocalStart?.addEventListener('click', () => {
        resetLoadedDoc();
        runExport(cfg.processingStatus === 'processing' ? cfg.resumeFrom : 1, 'local');
    });
    btnReprocess?.addEventListener('click', () => runExport(1, (localFileIn && localFileIn.files && localFileIn.files[0]) ? 'local' : 'drive'));
    btnRetry?.addEventListener('click', () => runExport(nextPageToProcess, currentSourceMode));
})();
