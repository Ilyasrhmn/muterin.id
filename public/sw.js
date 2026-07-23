const CACHE_VERSION = 'v1';
const PRECACHE = `muterin-precache-${CACHE_VERSION}`;
const PAGES = `muterin-pages-${CACHE_VERSION}`;
const ASSETS = `muterin-assets-${CACHE_VERSION}`;
const DATA = `muterin-data-${CACHE_VERSION}`;
const TILES = `muterin-tiles-${CACHE_VERSION}`;

const CURRENT_CACHES = [PRECACHE, PAGES, ASSETS, DATA, TILES];

const TILE_LIMIT = 200;

const PRECACHE_URLS = [
  '/offline.html',
  '/manifest.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/icon-512-maskable.png',
  '/icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(PRECACHE).then((cache) => cache.addAll(PRECACHE_URLS))
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => !CURRENT_CACHES.includes(key))
          .map((key) => caches.delete(key))
      )
    ).then(() => self.clients.claim())
  );
});

async function trimCache(cacheName, maxEntries) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  if (keys.length > maxEntries) {
    await cache.delete(keys[0]);
    await trimCache(cacheName, maxEntries);
  }
}

async function networkFirst(request, cacheName, fallbackUrl) {
  const cache = await caches.open(cacheName);
  try {
    const response = await fetch(request);
    if (response && response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    const cached = await cache.match(request);
    if (cached) return cached;
    if (fallbackUrl) return caches.match(fallbackUrl);
    throw err;
  }
}

async function cacheFirst(request, cacheName, trimTo) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (response && response.ok) {
    cache.put(request, response.clone());
    if (trimTo) trimCache(cacheName, trimTo);
  }
  return response;
}

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // Page navigations: network-first -> cache -> offline fallback.
  if (request.mode === 'navigate') {
    event.respondWith(networkFirst(request, PAGES, '/offline.html'));
    return;
  }

  // OSM map tiles: cache-first with an entry cap.
  if (/tile\.openstreetmap\.org$/.test(url.hostname)) {
    event.respondWith(cacheFirst(request, TILES, TILE_LIMIT));
    return;
  }

  // Same-origin build assets and static JS: cache-first.
  if (url.origin === self.location.origin &&
      (url.pathname.startsWith('/build/') || url.pathname.startsWith('/js/'))) {
    event.respondWith(cacheFirst(request, ASSETS));
    return;
  }

  // Cross-origin static libs (fonts, Leaflet, Font Awesome CDNs): cache-first.
  if (url.origin !== self.location.origin) {
    event.respondWith(cacheFirst(request, ASSETS));
    return;
  }

  // Same-origin dynamic JSON data endpoints: network-first.
  if (url.pathname.includes('/data') || url.pathname.startsWith('/peta/') || url.pathname.startsWith('/map/')) {
    event.respondWith(networkFirst(request, DATA));
    return;
  }
});
