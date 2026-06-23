var CACHE = 'gp-v1';
var BASE  = self.location.pathname.replace(/\/sw\.js$/, '');

self.addEventListener('install', function(e) {
    self.skipWaiting();
    e.waitUntil(
        caches.open(CACHE).then(function(c) {
            return Promise.all([
                BASE + '/assets/css/core.css',
                BASE + '/assets/img/gp-icon.svg',
            ].map(function(u) { return c.add(u).catch(function(){}); }));
        })
    );
});

self.addEventListener('activate', function(e) {
    e.waitUntil(
        caches.keys().then(function(keys) {
            return Promise.all(
                keys.filter(function(k) { return k !== CACHE; })
                    .map(function(k) { return caches.delete(k); })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', function(e) {
    var req = e.request;
    if (req.method !== 'GET') return;
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    var path = url.pathname;
    if (path.endsWith('.php') || path === BASE + '/' || path === '/') {
        e.respondWith(fetch(req).catch(function() { return caches.match(req); }));
        return;
    }
    e.respondWith(
        caches.match(req).then(function(hit) {
            if (hit) return hit;
            return fetch(req).then(function(resp) {
                if (resp && resp.ok) {
                    var clone = resp.clone();
                    caches.open(CACHE).then(function(c) { c.put(req, clone); });
                }
                return resp;
            });
        })
    );
});
