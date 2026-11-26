/* Bookexa Service Worker — Static Assets Only (Zero protected API or logged-in HTML caching) */
'use strict';

const SW_VERSION = 'bx-sw-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== SW_VERSION).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // NEVER intercept HTML navigations, logged-in flows, admin, reader, or APIs
    if (req.mode === 'navigate') return;
    const accept = req.headers.get('accept') || '';
    if (accept.includes('text/html')) return;

    const path = url.pathname;
    if (
        path.includes('/api/') ||
        path.includes('/admin') ||
        path.includes('/reader') ||
        path.includes('/checkout') ||
        path.includes('/cart') ||
        path.includes('/login') ||
        path.includes('/library') ||
        path.includes('/profile') ||
        path.endsWith('/sw.js')
    ) {
        return;
    }

    const isStaticAsset =
        path.includes('/assets/') ||
        path.includes('/uploads/') ||
        path.endsWith('/manifest.webmanifest');
    if (!isStaticAsset) return;

    event.respondWith(
        caches.open(SW_VERSION).then((cache) =>
            cache.match(req).then((cached) => {
                const fetchPromise = fetch(req).then((netResp) => {
                    if (netResp && netResp.ok && netResp.type === 'basic') {
                        const ct = (netResp.headers.get('content-type') || '').toLowerCase();
                        if (!ct.includes('text/html')) {
                            cache.put(req, netResp.clone());
                        }
                    }
                    return netResp;
                }).catch(() => cached);
                return cached || fetchPromise;
            })
        )
    );
});
