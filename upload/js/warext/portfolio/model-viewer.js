(() => {
'use strict';
const current = document.currentScript;
if (!current || !current.src) return;
const currentUrl = new URL(current.src, window.location.href);
const version = currentUrl.searchParams.get('v') || '1000070';
const base = currentUrl.href.slice(0, currentUrl.href.lastIndexOf('/model-viewer.js'));
const load = (name) => new Promise((resolve, reject) => {
  const script = document.createElement('script');
  script.src = `${base}/${name}?v=${encodeURIComponent(version)}`;
  script.async = false;
  script.onload = resolve;
  script.onerror = () => reject(new Error(`Viewer modülü yüklenemedi: ${name}`));
  document.head.appendChild(script);
});
load('model-viewer-core.js')
  .then(() => load('model-viewer-scene.js'))
  .then(() => load('model-viewer-main.js'))
  .catch((error) => {
    const target = document.querySelector('[data-wrxt-model-viewer] [data-error]');
    if (target) {
      target.textContent = error instanceof Error ? error.message : '3D görüntüleyici yüklenemedi.';
      target.classList.add('is-active');
    }
  });
})();
