(function () {
  'use strict';

  const SELECTOR = '[data-ve-staff-embed][data-version="2"]';

  const skeleton = '<div class="ve-v2-skeleton" role="status" aria-live="polite"><span class="ve-v2-sr">Loading staff directory</span><div class="ve-v2-heading"></div><div class="ve-v2-grid"><i></i><i></i><i></i><i></i></div></div>';

  const loadStyles = function (root, source) {
    const sourceStyle = source.querySelector('style[data-ve-staff-v2]');
    if (!sourceStyle) throw new Error('The staff response did not contain the v2 stylesheet.');
    const style = document.createElement('style');
    style.textContent = sourceStyle.textContent;
    root.appendChild(style);
  };

  const initializeImages = function (root) {
    root.querySelectorAll('img[data-src], img[data-lazy-src], img[data-original]').forEach(function (image) {
      const source = image.getAttribute('data-src') || image.getAttribute('data-lazy-src') || image.getAttribute('data-original');
      if (!source) return;
      image.addEventListener('load', function () {
        image.classList.remove('lazy', 've-lazy', 'lazyload', 'lazyload-loading');
        image.classList.add('ve-lazy-loaded');
      }, { once: true });
      image.addEventListener('error', function () {
        image.classList.remove('lazyload-loading');
        console.error('VE staff v2 image failed to load', { src: source, alt: image.alt });
      }, { once: true });
      image.src = source;
      image.removeAttribute('data-src');
      image.removeAttribute('data-lazy-src');
      image.removeAttribute('data-original');
    });
  };

  const initializeFilters = function (root) {
    const items = function () { return root.querySelectorAll('.employee-tile, .department-header'); };
    const value = function (selector) {
      const field = root.querySelector(selector);
      return field ? field.value : 'all';
    };
    const update = function () {
      const department = value('#departmentfilterselect');
      const location = value('#locationfilterselect');
      items().forEach(function (item) {
        const departmentMatches = department === 'all' || item.getAttribute('data-dept') === department;
        const locationMatches = location === 'all' || (item.getAttribute('data-loc') || '').includes(location);
        item.classList.toggle('ve-hidden', !departmentMatches || !locationMatches);
      });
    };
    root.addEventListener('change', function (event) {
      if (event.target.matches('#departmentfilterselect, #locationfilterselect')) update();
    });
    root.addEventListener('input', function (event) {
      if (!event.target.matches('#employeenamesearch, #employeeextsearch')) return;
      const attribute = event.target.matches('#employeenamesearch') ? 'data-employee-name' : 'data-ext';
      const query = event.target.value.toUpperCase();
      items().forEach(function (item) { item.classList.add('ve-hidden'); });
      root.querySelectorAll('.employee-tile').forEach(function (item) {
        if (!query || (item.getAttribute(attribute) || '').toUpperCase().includes(query)) item.classList.remove('ve-hidden');
      });
    });
  };

  const loadEmbed = function (host) {
    const endpoint = host.getAttribute('data-endpoint');
    if (!endpoint) throw new Error('The v2 embed requires a data-endpoint attribute.');
    const root = host.shadowRoot || host.attachShadow({ mode: 'open' });
    root.innerHTML = '<style>:host{display:block}.ve-v2-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.ve-v2-heading,.ve-v2-grid i{display:block;background:#eee;border-radius:8px;animation:vepulse 1.2s infinite alternate}.ve-v2-heading{height:48px;margin-bottom:18px}.ve-v2-grid i{height:260px}@keyframes vepulse{to{opacity:.45}}.ve-v2-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}@media(max-width:700px){.ve-v2-grid{grid-template-columns:repeat(2,1fr)}}</style>' + skeleton;
    fetch(endpoint, { cache: 'no-store', credentials: 'omit', headers: { Accept: 'text/html' } })
      .then(function (response) {
        if (!response.ok) throw new Error('Staff embed request failed with HTTP ' + response.status + '.');
        return response.text();
      })
      .then(function (html) {
        const documentResult = new DOMParser().parseFromString(html, 'text/html');
        const content = documentResult.querySelector('#veStaffList, #veStaffDisplay');
        if (!content) throw new Error('The staff response did not contain a supported staff container.');
        root.innerHTML = '';
        loadStyles(root, documentResult);
        root.appendChild(content);
        initializeImages(root);
        initializeFilters(root);
      })
      .catch(function (error) {
        root.innerHTML = '<div role="alert">The staff directory could not be loaded. Please try again later.</div>';
        console.error('VE staff v2 embed failed', { endpoint: endpoint, error: error });
      });
  };

  document.querySelectorAll(SELECTOR).forEach(loadEmbed);
}());
