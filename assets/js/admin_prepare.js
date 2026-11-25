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
    if (!cfg || !window.pdfjsLib) return;

    pdfjsLib.GlobalWorkerOptions.workerSrc = cfg.workerUrl;

    const btnStart     = document.getElementById('btn-start-prepare');
    const btnLocalStart= document.getElementById('btn-start-local-prepare');
    const localFileIn  = document.getElementById('prep-local-file');
    const btnReprocess = document.getElementById('btn-reprocess-all');
    const btnRetry     = document.getElementById('btn-retry-prepare');
    const fillEl       = document.getElementById('prep-progress-fill');
    const counterEl    = document.getElementById('prep-counter');
    const percentEl    = document.getElementById('prep-percent');
    const statusEl     = document.getElementById('prep-status-text');
    const badgeEl      = document.getElementById('prep-status-badge');
    const previewCvs   = document.getElementById('prep-preview-canvas');

    let pdfDoc = null;
    let currentSourceMode = 'drive'; // 'drive' | 'local'
    let localObjUrl = null;
    let nextPageToProcess = Math.max(1, cfg.resumeFrom || 1);
    let running = false;

    function setProgress(done, total, msg) {
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        if (fillEl) fillEl.style.width = pct + '%';
        if (percentEl) percentEl.textContent = pct + '%';
        if (counterEl && total > 0) counterEl.textContent = `Rendered & saved page ${done} of ${total}`;
        if (statusEl && msg) statusEl.textContent = msg;
    }

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

    async function uploadPageWithRetry(pageNum, blob, maxTries) {
        const ext = exportMime === 'image/webp' ? 'webp' : 'jpg';
        let lastErr = null;
        for (let attempt = 1; attempt <= maxTries; attempt++) {
            try {
                const fd = new FormData();
                fd.append('_csrf', cfg.csrfToken);
                fd.append('item_id', String(cfg.itemId));
                fd.append('page_num', String(pageNum));
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

                if (previewCvs) {
                    previewCvs.width = Math.min(260, workCanvas.width);
                    previewCvs.height = Math.round(previewCvs.width * (workCanvas.height / workCanvas.width));
                    const pctx = previewCvs.getContext('2d');
                    pctx.drawImage(workCanvas, 0, 0, previewCvs.width, previewCvs.height);
                }

                const blob = await canvasToBlob(workCanvas, exportMime, 0.80);
                setProgress(p - 1, total, `Uploading page ${p} of ${total} (${Math.round(blob.size / 1024)} KB)...`);
                await uploadPageWithRetry(p, blob, 3);
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
                    total_pages: String(total)
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
            if (statusEl) statusEl.textContent = `Stopped at page ${nextPageToProcess}: ${msg}`;
            if (btnRetry) btnRetry.style.display = 'inline-flex';
            if (window.bxToast) window.bxToast(msg, 'danger');
        } finally {
            running = false;
            if (btnStart) btnStart.disabled = false;
            if (btnLocalStart) btnLocalStart.disabled = false;
            if (btnReprocess) btnReprocess.disabled = false;
        }
    }

    btnStart?.addEventListener('click', () => runExport(cfg.pagesStatus === 'processing' ? cfg.resumeFrom : 1, 'drive'));
    btnLocalStart?.addEventListener('click', () => {
        resetLoadedDoc();
        runExport(cfg.pagesStatus === 'processing' ? cfg.resumeFrom : 1, 'local');
    });
    btnReprocess?.addEventListener('click', () => runExport(1, (localFileIn && localFileIn.files && localFileIn.files[0]) ? 'local' : 'drive'));
    btnRetry?.addEventListener('click', () => runExport(nextPageToProcess, currentSourceMode));
})();
