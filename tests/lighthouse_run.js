#!/usr/bin/env node
/**
 * Bookexa Core Web Vitals & Mobile Performance Runner (Playwright + Lighthouse / CDP Performance APIs)
 *
 * Usage:
 *   node tests/lighthouse_run.js [--base=http://localhost/bookexa] [--label=before] [--out=tests/cwv-before.json]
 *
 * Audits Home, Folder, Book, Guide, and Shop pages under mobile emulation (390x844, Slow 4G + 4x CPU throttle).
 * Uses Playwright + Lighthouse when available, and always collects real PerformanceObserver LCP, CLS, INP,
 * FCP, TTFB, Transfer bytes, and Blocking resources via Chromium/Edge CDP.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const http = require('http');
const https = require('https');
const { URL } = require('url');

function parseArgs(argv) {
    const out = {
        base: process.env.BOOKEXA_BASE_URL || 'http://localhost/bookexa',
        label: 'run',
        outFile: '',
    };
    for (const a of argv.slice(2)) {
        if (a.startsWith('--base=')) out.base = a.slice(7).replace(/\/+$/, '');
        else if (a.startsWith('--label=')) out.label = a.slice(8);
        else if (a.startsWith('--out=')) out.outFile = a.slice(6);
    }
    return out;
}

function tryRequire(name) {
    const candidates = [
        name,
        path.join(process.cwd(), 'node_modules', name),
        path.join(process.env.LOCALAPPDATA || '', 'Programs', 'bx-perf-tools', 'node_modules', name),
        path.join(process.env.APPDATA || '', 'npm', 'node_modules', name),
    ];
    for (const c of candidates) {
        try {
            return require(c);
        } catch (_) {}
    }
    return null;
}

function fetchText(url) {
    return new Promise((resolve) => {
        const mod = url.startsWith('https://') ? https : http;
        const req = mod.get(url, { timeout: 10000 }, (res) => {
            if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
                const nextUrl = res.headers.location.startsWith('http') ? res.headers.location : new URL(res.headers.location, url).href;
                fetchText(nextUrl).then(resolve);
                return;
            }
            let buf = '';
            res.setEncoding('utf8');
            res.on('data', (d) => { buf += d; });
            res.on('end', () => resolve({ code: res.statusCode || 0, body: buf, headers: res.headers }));
        });
        req.on('error', () => resolve({ code: 0, body: '', headers: {} }));
    });
}

async function discoverAuditTargets(base) {
    const basePrefix = new URL(base).pathname.replace(/\/+$/, '');
    const relPath = (u) => {
        let p = new URL(u).pathname;
        if (basePrefix && p.startsWith(basePrefix)) p = p.slice(basePrefix.length) || '/';
        return p;
    };
    const targets = [
        { key: 'home', path: '/' },
        { key: 'folder', path: '/pdf' },
        { key: 'book', path: '/pdf' },
        { key: 'guide', path: '/guides' },
        { key: 'shop', path: '/shop' },
    ];

    const folderSm = await fetchText(`${base}/sitemap-folders.xml`);
    if (folderSm.code === 200) {
        const m = folderSm.body.match(/<loc>(https?:\/\/[^<]+?\/pdf\/[^<]+)<\/loc>/);
        if (m) targets[1].path = relPath(m[1]);
    }

    const catJson = await fetchText(`${base}/catalog.json`);
    if (catJson.code === 200) {
        try {
            const parsed = JSON.parse(catJson.body);
            const first = parsed.items && parsed.items[0];
            if (first && first.url_bn) targets[2].path = relPath(first.url_bn);
        } catch (_) {}
    }
    if (targets[2].path === '/pdf') {
        const bookSm = await fetchText(`${base}/sitemap-books.xml`);
        if (bookSm.code === 200) {
            const m = bookSm.body.match(/<loc>(https?:\/\/[^<]+?\/book\/[^<]+)<\/loc>/);
            if (m) targets[2].path = relPath(m[1]);
        }
    }

    const gdJson = await fetchText(`${base}/guides.json`);
    if (gdJson.code === 200) {
        try {
            const parsed = JSON.parse(gdJson.body);
            const first = parsed.guides && parsed.guides[0];
            if (first && first.url_bn) targets[3].path = relPath(first.url_bn);
        } catch (_) {}
    }

    const shopCheck = await fetchText(`${base}/shop`);
    if (shopCheck.code !== 200) {
        targets[4] = { key: 'faq', path: '/faq' };
    }
    return targets;
}

function estimateLighthouseScore(m) {
    // Weighted Lighthouse v10+ style performance approximation:
    // FCP 10%, SI 10%, LCP 25%, TBT 30%, CLS 25%
    const clamp01 = (v) => Math.max(0, Math.min(1, v));
    const logNorm = (val, p10, median) => {
        if (val <= 0) return 1;
        // smooth log-normal curve fit matching Lighthouse thresholds
        if (val <= p10) return 0.90 + 0.10 * (1 - val / Math.max(1, p10));
        if (val <= median) return 0.50 + 0.40 * (1 - (val - p10) / Math.max(1, median - p10));
        return Math.max(0.05, 0.50 * Math.exp(-(val - median) / Math.max(1, median)));
    };
    const clsScore = m.cls <= 0.1 ? (0.90 + 0.10 * (1 - m.cls / 0.1)) : Math.max(0.05, 0.90 - (m.cls - 0.1) * 2.5);
    const s = 0.10 * logNorm(m.fcpMs, 1800, 3000)
        + 0.10 * logNorm(Math.round((m.fcpMs + m.lcpMs) / 2), 3400, 5800)
        + 0.25 * logNorm(m.lcpMs, 2500, 4000)
        + 0.30 * logNorm(Math.max(m.tbtMs, Math.max(0, m.inpMs - 40)), 200, 600)
        + 0.25 * clamp01(clsScore);
    return Math.round(clamp01(s) * 100);
}

function findEdgeOrChrome() {
    const candidates = [
        process.env.CHROME_PATH,
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
        'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/usr/bin/chromium-browser',
    ].filter(Boolean);
    for (const p of candidates) {
        if (fs.existsSync(p)) return p;
    }
    return null;
}

async function run() {
    const opts = parseArgs(process.argv);
    const pw = tryRequire('playwright') || tryRequire('playwright-core');
    const lhMod = tryRequire('lighthouse');
    const lighthouse = lhMod ? (lhMod.default || lhMod) : null;
    if (!pw) {
        console.error('Playwright / playwright-core not found. Install in NODE_PATH or ./node_modules.');
        process.exit(1);
    }

    const targets = await discoverAuditTargets(opts.base);
    const execPath = findEdgeOrChrome();
    const debugPort = 9229;
    const browser = await pw.chromium.launch({
        executablePath: execPath || undefined,
        headless: true,
        args: [
            `--remote-debugging-port=${debugPort}`,
            '--disable-gpu',
            '--no-sandbox',
            '--disable-dev-shm-usage',
        ],
    });

    const results = [];
    try {
        for (const tgt of targets) {
            const fullUrl = `${opts.base}${tgt.path}`;
            let lhScore = null;
            let lhMetrics = null;

            if (lighthouse) {
                try {
                    const runnerRes = await lighthouse(fullUrl, {
                        port: debugPort,
                        output: 'json',
                        logLevel: 'error',
                        onlyCategories: ['performance'],
                        formFactor: 'mobile',
                        screenEmulation: { mobile: true, width: 390, height: 844, deviceScaleFactor: 2, disabled: false },
                        throttling: {
                            rttMs: 150,
                            throughputKbps: 1638.4,
                            cpuSlowdownMultiplier: 4,
                            requestLatencyMs: 150,
                            downloadThroughputKbps: 1638.4,
                            uploadThroughputKbps: 750,
                        },
                    });
                    if (runnerRes && runnerRes.lhr) {
                        const lhr = runnerRes.lhr;
                        lhScore = Math.round((lhr.categories.performance.score || 0) * 100);
                        const a = lhr.audits || {};
                        lhMetrics = {
                            lcpMs: Math.round(a['largest-contentful-paint']?.numericValue || 0),
                            cls: Number((a['cumulative-layout-shift']?.numericValue || 0).toFixed(4)),
                            tbtMs: Math.round(a['total-blocking-time']?.numericValue || 0),
                            fcpMs: Math.round(a['first-contentful-paint']?.numericValue || 0),
                            ttfbMs: Math.round(a['server-response-time']?.numericValue || 0),
                        };
                    }
                } catch (_) {}
            }

            // Always run real Slow-4G Playwright + CDP PerformanceObserver (collects INP + LCP + CLS cleanly)
            const ctx = await browser.newContext({
                viewport: { width: 390, height: 844 },
                deviceScaleFactor: 2,
                isMobile: true,
                hasTouch: true,
                userAgent: 'Mozilla/5.0 (Linux; Android 13; Moto G Power) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
            });
            const page = await ctx.newPage();
            const cdp = await ctx.newCDPSession(page);
            await cdp.send('Network.enable');
            // Slow 4G profile (~1.6 Mbps down, 750 Kbps up, 150ms RTT) + 4x CPU slowdown
            await cdp.send('Network.emulateNetworkConditions', {
                offline: false,
                latency: 150,
                downloadThroughput: Math.floor((1600 * 1024) / 8),
                uploadThroughput: Math.floor((750 * 1024) / 8),
                connectionType: 'cellular4g',
            });
            await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });

            let transferBytes = 0;
            let reqCount = 0;
            cdp.on('Network.loadingFinished', (ev) => {
                transferBytes += Number(ev.encodedDataLength || 0);
            });
            cdp.on('Network.requestWillBeSent', () => { reqCount++; });

            await page.addInitScript(() => {
                window.__bxCwv = { lcp: 0, cls: 0, fcp: 0, inp: 0, tbt: 0 };
                try {
                    new PerformanceObserver((list) => {
                        for (const e of list.getEntries()) {
                            window.__bxCwv.lcp = Math.max(window.__bxCwv.lcp, e.startTime);
                        }
                    }).observe({ type: 'largest-contentful-paint', buffered: true });
                } catch (_) {}
                try {
                    new PerformanceObserver((list) => {
                        for (const e of list.getEntries()) {
                            if (!e.hadRecentInput) window.__bxCwv.cls += e.value;
                        }
                    }).observe({ type: 'layout-shift', buffered: true });
                } catch (_) {}
                try {
                    new PerformanceObserver((list) => {
                        for (const e of list.getEntries()) {
                            if (e.name === 'first-contentful-paint') window.__bxCwv.fcp = e.startTime;
                        }
                    }).observe({ type: 'paint', buffered: true });
                } catch (_) {}
                try {
                    new PerformanceObserver((list) => {
                        for (const e of list.getEntries()) {
                            if (e.duration > window.__bxCwv.inp) window.__bxCwv.inp = e.duration;
                        }
                    }).observe({ type: 'event', durationThreshold: 16, buffered: true });
                } catch (_) {}
                try {
                    new PerformanceObserver((list) => {
                        for (const e of list.getEntries()) {
                            if (e.duration > 50) window.__bxCwv.tbt += (e.duration - 50);
                        }
                    }).observe({ type: 'longtask', buffered: true });
                } catch (_) {}
            });

            const resp = await page.goto(fullUrl, { waitUntil: 'load', timeout: 45000 });
            const status = resp ? resp.status() : 0;
            await page.waitForTimeout(800);

            // Trigger real user tap on a safe interactive element to record real INP
            try {
                const toggleBtn = page.locator('[data-theme-toggle], [data-drawer-open], summary').first();
                if (await toggleBtn.count()) {
                    await toggleBtn.click({ timeout: 2000 });
                    await page.waitForTimeout(250);
                }
            } catch (_) {}

            const perf = await page.evaluate(() => {
                const nav = performance.getEntriesByType('navigation')[0];
                const ttfb = nav ? Math.max(0, nav.responseStart - nav.requestStart) : 0;
                const dcl = nav ? Math.max(0, nav.domContentLoadedEventEnd) : 0;
                const loadEnd = nav ? Math.max(0, nav.loadEventEnd) : 0;
                const c = window.__bxCwv || {};
                const blockingLinks = document.querySelectorAll('head link[rel="stylesheet"]:not([media="print"]), head script:not([defer]):not([async]):not([type="application/ld+json"])').length;
                return {
                    ttfbMs: Math.round(ttfb),
                    fcpMs: Math.round(c.fcp || dcl),
                    lcpMs: Math.round(c.lcp || loadEnd || dcl),
                    cls: Number((c.cls || 0).toFixed(4)),
                    inpMs: Math.round(c.inp || 0),
                    tbtMs: Math.round(c.tbt || 0),
                    blockingHeadAssets: blockingLinks,
                };
            });

            const merged = {
                key: tgt.key,
                path: tgt.path,
                status,
                ttfbMs: lhMetrics ? lhMetrics.ttfbMs : perf.ttfbMs,
                fcpMs: lhMetrics ? lhMetrics.fcpMs : perf.fcpMs,
                lcpMs: lhMetrics ? lhMetrics.lcpMs : perf.lcpMs,
                cls: lhMetrics ? Math.max(lhMetrics.cls, perf.cls) : perf.cls,
                inpMs: perf.inpMs,
                tbtMs: lhMetrics ? Math.max(lhMetrics.tbtMs, perf.tbtMs) : perf.tbtMs,
                transferKb: Math.round(transferBytes / 1024),
                requests: reqCount,
                blockingHeadAssets: perf.blockingHeadAssets,
                lighthouseScore: lhScore,
            };
            merged.perfScore = lhScore !== null ? lhScore : estimateLighthouseScore(merged);
            results.push(merged);
            await ctx.close();
        }
    } finally {
        await browser.close();
    }

    const report = {
        label: opts.label,
        base: opts.base,
        timestamp: new Date().toISOString(),
        lighthouseUsed: Boolean(lighthouse),
        pages: results,
    };

    if (opts.outFile) {
        fs.mkdirSync(path.dirname(path.resolve(opts.outFile)), { recursive: true });
        fs.writeFileSync(opts.outFile, JSON.stringify(report, null, 2));
    }

    console.log(`\n=== Bookexa Mobile Slow-4G CWV Report (${opts.label}, LH=${report.lighthouseUsed ? 'yes' : 'perf-api'}) ===`);
    for (const p of results) {
        console.log(
            `  [${p.key.padEnd(6)}] ${p.path} -> HTTP ${p.status} | Score=${p.perfScore}/100 | LCP=${p.lcpMs}ms | INP=${p.inpMs}ms | CLS=${p.cls} | TTFB=${p.ttfbMs}ms | ${p.transferKb}KB (${p.requests} reqs)`
        );
    }
}

run().catch((err) => {
    console.error(err);
    process.exit(1);
});
