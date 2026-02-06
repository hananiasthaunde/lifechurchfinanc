<link rel="manifest" href="/lifechurchfinanc-main/manifest.json">
<meta name="theme-color" content="#1976D2">
<link rel="apple-touch-icon" href="/lifechurchfinanc-main/assets/images/icon-512.png">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/lifechurchfinanc-main/service-worker.js').then(function(registration) {
      console.log('ServiceWorker registration successful with scope: ', registration.scope);
    }, function(err) {
      console.log('ServiceWorker registration failed: ', err);
    });
  });
}
</script>
