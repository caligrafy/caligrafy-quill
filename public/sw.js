/*
 * Caligrafy Quill service worker (optional, off by default; Notify.enable() of public/js/notify.js registers it for push)
 * Enabled by PWA_SERVICE_WORKER=true in .env: the default layout then registers it with the scope of the
 * application root ({$home}); .htaccess sends the Service-Worker-Allowed header that allows that scope.
 *
 * It caches one file only: the offline page (public/offline.html). Pages, API routes, POST requests and
 * signed-in pages always go to the network and are never stored; when a page cannot be loaded because the
 * device is offline, the offline page is shown instead.
 * Change CACHE when you change offline.html so that installed apps get the new copy.
 */

const CACHE = 'caligrafy-offline-v1';
const OFFLINE_URL = new URL('offline.html', self.location).href;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('caligrafy-') && key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    // Only page loads (GET navigations) get the offline fallback. Everything else (API calls, POST/PUT/DELETE,
    // scripts, images, other origins) is not intercepted at all and keeps the browser's normal behavior.
    if (request.method !== 'GET' || request.mode !== 'navigate') return;
    event.respondWith(
        fetch(request).catch(() => caches.match(OFFLINE_URL).then((response) => response || Response.error()))
    );
});

// Notifications (2.15.0, Caligrafy\Notify): a push shows its message even with every page of the app closed; a tap
// focuses an open tab of the same address, or opens one. Apps keep their own handlers next to these.
self.addEventListener('push', (event) => {
    let message = {};
    try { message = event.data ? event.data.json() : {}; } catch (e) { message = { title: event.data ? event.data.text() : '' }; }
    const title = message.title || 'New notification';
    event.waitUntil(self.registration.showNotification(title, {
        body: message.body || '',
        icon: message.icon || undefined,
        tag: message.tag || undefined,
        renotify: Boolean(message.tag),
        data: { url: message.url || self.registration.scope }
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL((event.notification.data && event.notification.data.url) || self.registration.scope, self.registration.scope).href;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((tabs) => {
        const open = tabs.find((tab) => tab.url === url);
        return open ? open.focus() : self.clients.openWindow(url);
    }));
});
