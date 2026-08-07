(function () {
  'use strict';

  const SELECTOR = '[data-ve-staff-embed][data-version="2"]';

  const skeleton = '<div class="ve-v2-skeleton" role="status" aria-live="polite"><span class="ve-v2-sr">Loading staff directory</span><div class="ve-v2-heading"></div><div class="ve-v2-grid"><i></i><i></i><i></i><i></i></div></div>';

  const loadStyles = function (root, source) {
    const urls = Array.from(source.querySelectorAll('link[rel="stylesheet"]')).map(function (link) { return link.href; });
    return Promise.all(urls.map(function (url) {
      return new Promise(function (resolve, reject) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        link.onload = resolve;
        link.onerror = function () {
          reject(new Error('Stylesheet request failed: ' + url));
        };
        root.appendChild(link);
      });
    }));
  };

  const loadEmbed = function (host) {
    const endpoint = host.getAttribute('data-endpoint');
    if (!endpoint) throw new Error('The v2 embed requires a data-endpoint attribute.');
    const root = host.shadowRoot || host.attachShadow({ mode: 'open' });
    root.innerHTML = '<style>:host{display:block}.ve-v2-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.ve-v2-heading,.ve-v2-grid i{display:block;background:#eee;border-radius:8px;animation:vepulse 1.2s infinite alternate}.ve-v2-heading{height:48px;margin-bottom:18px}.ve-v2-grid i{height:260px}@keyframes vepulse{to{opacity:.45}}.ve-v2-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}@media(max-width:700px){.ve-v2-grid{grid-template-columns:repeat(2,1fr)}}</style>' + skeleton;
    const url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 't=' + Date.now();
    fetch(url, { cache: 'no-store', credentials: 'omit', headers: { Accept: 'text/html' } })
      .then(function (response) {
        if (!response.ok) throw new Error('Staff embed request failed with HTTP ' + response.status + '.');
        return response.text();
      })
      .then(function (html) {
        const documentResult = new DOMParser().parseFromString(html, 'text/html');
        const content = documentResult.querySelector('#veStaffList, #veStaffDisplay');
        if (!content) throw new Error('The staff response did not contain a supported staff container.');
        root.innerHTML = '';
        return loadStyles(root, documentResult).then(function () { root.appendChild(content); });
      })
      .catch(function (error) {
        root.innerHTML = '<div role="alert">The staff directory could not be loaded. Please try again later.</div>';
        console.error('VE staff v2 embed failed', { endpoint: endpoint, error: error });
      });
  };

  document.querySelectorAll(SELECTOR).forEach(loadEmbed);
}());
