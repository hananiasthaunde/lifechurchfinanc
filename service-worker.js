const CACHE_NAME = 'lifechurch-v1';
const urlsToCache = [
  '/lifechurchfinanc-main/admin/dashboard.php',
  '/lifechurchfinanc-main/assets/css/styles.css', // Assuming existence or generic
  '/lifechurchfinanc-main/manifest.json',
  'https://cdn.tailwindcss.com/3.4.1',
  'https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});
