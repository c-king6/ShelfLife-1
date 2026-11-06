// ShelfLife Service Worker
// Caches static assets so the app works offline.
// Never caches PHP responses (they're user-specific).

const CACHE_VERSION = 'shelflife-v13';
const STATIC_ASSETS = [
    'manifest.json',
    'style.css',
    'offline.html',
    'https://unpkg.com/lucide@latest',
    'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap'
];

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_VERSION).then(function (cache) {
            return cache.addAll(STATIC_ASSETS).catch(function () {});
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (names) {
            return Promise.all(
                names.filter(function (n) { return n !== CACHE_VERSION; })
                     .map(function (n) { return caches.delete(n); })
            );
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    const req = event.request;
    const url = new URL(req.url);

    // Only handle GET
    if (req.method !== 'GET') return;

    // Skip SSE + polling endpoints entirely
    if (url.pathname.endsWith('stream_messages.php')) return;
    if (url.pathname.endsWith('get_messages.php')) return;

    // Page navigations → network-first, offline.html as fallback
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(function () {
                return caches.match('offline.html').then(function (r) {
                    return r || new Response('Offline', { status: 503 });
                });
            })
        );
        return;
    }

    // Static assets → cache-first, network fallback
    event.respondWith(
        caches.match(req).then(function (cached) {
            if (cached) return cached;
            return fetch(req).catch(function () {
                // Let the browser show its default error rather than crashing the page
                return new Response('', { status: 504, statusText: 'Offline' });
            }).then(function (res) {
                if (!res) return res;
                const sameOrigin = url.origin === self.location.origin;
                const fromCDN    = url.hostname === 'unpkg.com'
                                || url.hostname === 'fonts.googleapis.com'
                                || url.hostname === 'fonts.gstatic.com'
                                || url.hostname === 'cdn.jsdelivr.net'
                                || url.hostname === 'cdnjs.cloudflare.com';
                if (res && res.ok && (sameOrigin || fromCDN) && !url.pathname.endsWith('.php')) {
                    const clone = res.clone();
                    caches.open(CACHE_VERSION).then(function (c) { c.put(req, clone); }).catch(function () {});
                }
                return res;
            });
        })
    );
});