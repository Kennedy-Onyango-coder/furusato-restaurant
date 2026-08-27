/**
 * Service Worker — Furusato Restaurant
 * FIXED: Response.clone() before body consumption
 */

const CACHE_NAME = 'furusato-v4';
const STATIC_CACHE = 'furusato-static-v4';

// Assets to cache on install
const STATIC_ASSETS = [
  '/',
  '/index.php',
  '/menu.php',
  '/our-story.php',
  '/contact.php',
  '/assets/css/style.css',
  '/assets/css/menu.css',
  '/assets/css/animations.css',
  '/assets/js/main.js',
  '/assets/js/menu.js',
  '/assets/js/contact.js',
  '/assets/images/furusato-logo.png'
];

// External origins to skip
const EXTERNAL_ORIGINS = [
  'https://fonts.googleapis.com',
  'https://fonts.gstatic.com',
  'https://cdnjs.cloudflare.com',
  'https://images.unsplash.com'
];

function isExternalOrigin(url) {
  return EXTERNAL_ORIGINS.some(origin => url.indexOf(origin) === 0);
}

function shouldBypassCache(url) {
  const bypassPatterns = ['/admin/', '/api/', '/data/', 'auth.php'];
  return bypassPatterns.some(pattern => url.indexOf(pattern) !== -1);
}

// INSTALL
self.addEventListener('install', (event) => {
  console.log('[SW] Installing');
  event.waitUntil(
    caches.open(STATIC_CACHE).then(async (cache) => {
      const promises = STATIC_ASSETS.map(async (asset) => {
        try {
          const response = await fetch(asset);
          if (response && response.ok) {
            await cache.put(asset, response);
          }
        } catch (err) {
          console.log('[SW] Failed cache:', asset);
        }
      });
      await Promise.allSettled(promises);
    })
  );
  self.skipWaiting();
});

// ACTIVATE
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating');
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME && key !== STATIC_CACHE) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// ============================================================
// FETCH - FIXED: Clone BEFORE consuming body
// ============================================================
self.addEventListener('fetch', (event) => {
  const url = event.request.url;
  const request = event.request;
  
  if (isExternalOrigin(url) || shouldBypassCache(url) || request.method !== 'GET') {
    return;
  }
  
  // Handle manifest
  if (url.endsWith('/site.webmanifest')) {
    event.respondWith(
      fetch(request).catch(() => {
        return new Response(JSON.stringify({
          name: "Furusato Restaurant",
          short_name: "Furusato",
          start_url: "/",
          display: "standalone",
          theme_color: "#0d1b2a"
        }), { headers: { 'Content-Type': 'application/json' } });
      })
    );
    return;
  }
  
  // Handle favicon requests
  if (url.includes('/favicon')) {
    event.respondWith(
      fetch('/assets/images/furusato-logo.png').catch(() => {
        return new Response('', { status: 404 });
      })
    );
    return;
  }
  
  // HTML pages: Network-first
  if (url.endsWith('.php') || url.endsWith('/')) {
    event.respondWith(
      fetch(request).then((response) => {
        if (response && response.ok) {
          // FIX: Clone BEFORE caching
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, responseClone));
        }
        return response;
      }).catch(async () => {
        const cached = await caches.match(request);
        return cached || new Response('Offline', { status: 503 });
      })
    );
    return;
  }
  
  // Static assets: Cache-first with proper clone ordering
  const staticExtensions = /\.(css|js|jpg|jpeg|png|gif|webp|svg|woff2?)$/i;
  if (staticExtensions.test(url)) {
    event.respondWith(
      caches.match(request).then((cachedResponse) => {
        if (cachedResponse) {
          // Update cache in background
          fetch(request.clone()).then((networkResponse) => {
            if (networkResponse && networkResponse.ok) {
              // FIX: Clone BEFORE caching
              const cloneForCache = networkResponse.clone();
              caches.open(STATIC_CACHE).then(cache => cache.put(request, cloneForCache));
            }
          }).catch(() => {});
          return cachedResponse;
        }
        
        return fetch(request).then((networkResponse) => {
          if (networkResponse && networkResponse.ok) {
            // FIX: Clone BEFORE caching
            const cloneForCache = networkResponse.clone();
            caches.open(STATIC_CACHE).then(cache => cache.put(request, cloneForCache));
          }
          return networkResponse;
        }).catch(() => {
          if (url.match(/\.(png|jpg|jpeg|gif|webp)$/i)) {
            return fetch('/assets/images/furusato-logo.png');
          }
          return new Response('', { status: 404 });
        });
      })
    );
    return;
  }
  
  // Everything else
  event.respondWith(
    fetch(request).catch(() => caches.match(request).then(cached => cached || new Response('', { status: 404 })))
  );
});

// MESSAGE HANDLING
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
    if (event.source && event.source.postMessage) {
      event.source.postMessage({ type: 'SKIP_WAITING_COMPLETE' });
    }
  }
  if (event.source && event.source.postMessage) {
    event.source.postMessage({ type: 'ACK' });
  }
});