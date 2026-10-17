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

  function all(root, selector) { return Array.from(root.querySelectorAll(selector)); }

  function bindFilterPanel(root) {
    var panel = root.querySelector('.ve-staff-filters');
    var trigger = root.querySelector('[data-ve-staff-filters-open]');
    if (!panel || !trigger) return;
    var media = window.matchMedia('(max-width: 767.98px)');
    var backdrop = document.createElement('div');
    backdrop.className = 've-staff-filters-backdrop';
    backdrop.hidden = true;
    // Keep the fallback backdrop in the panel's stacking context.
    panel.parentNode.insertBefore(backdrop, panel);
    var panelId = 've-staff-filters-' + Math.random().toString(36).slice(2);
    panel.id = panelId;
    trigger.setAttribute('aria-controls', panelId);
    trigger.setAttribute('data-ve-staff-filters-open', '#' + panelId);
    var heading = panel.querySelector('.ve-staff-filters-header h5');
    if (heading) {
      heading.id = panelId + '-label';
      panel.setAttribute('aria-labelledby', heading.id);
    }
    var previousFocus;
    function focusable() {
      return all(panel, 'button, input, select, textarea, a[href], [tabindex="0"]').filter(function (element) {
        return !element.disabled && element.getClientRects().length;
      });
    }
    function close(restoreFocus) {
      if (panel.hasAttribute('popover')) {
        panel.hidePopover();
        panel.removeAttribute('popover');
      }
      panel.classList.remove('ve-staff-filters--open');
      panel.removeAttribute('role');
      panel.removeAttribute('aria-modal');
      trigger.setAttribute('aria-expanded', 'false');
      backdrop.hidden = true;
      if (restoreFocus && previousFocus) previousFocus.focus();
    }
    root.addEventListener('click', function (event) {
      if (event.target.closest('[data-ve-staff-filters-open]')) {
        event.preventDefault();
        if (!media.matches) return;
        if (panel.classList.contains('ve-staff-filters--open')) { close(true); return; }
        previousFocus = event.target.closest('[data-ve-staff-filters-open]');
        panel.classList.add('ve-staff-filters--open');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        trigger.setAttribute('aria-expanded', 'true');
        backdrop.hidden = false;
        // Top-layer rendering escapes transformed/clipped host containers.
        // Leave the DOM in place so directory-scoped filter events still work.
        if (typeof panel.showPopover === 'function') {
          panel.setAttribute('popover', 'manual');
          panel.showPopover();
        }
        (focusable()[0] || panel).focus();
      } else if (event.target.closest('[data-ve-staff-filters-close]') || event.target === backdrop) {
        event.preventDefault();
        close(true);
      }
    });
    root.addEventListener('keydown', function (event) {
      if (!panel.classList.contains('ve-staff-filters--open')) return;
      if (event.key === 'Escape') { event.preventDefault(); close(true); }
      if (event.key === 'Tab') {
        var fields = focusable();
        var first = fields[0] || panel;
        var last = fields[fields.length - 1] || panel;
        if (event.shiftKey && event.target === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && event.target === last) { event.preventDefault(); first.focus(); }
      }
    });
    media.addEventListener('change', function () { close(false); });
  }

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
        bindFilterPanel(root);
      })
      .catch(function (error) {
        root.innerHTML = '<div role="alert">The staff directory could not be loaded. Please try again later.</div>';
        console.error('VE staff v2 embed failed', { endpoint: endpoint, error: error });
      });
  };

  document.querySelectorAll(SELECTOR).forEach(loadEmbed);
}());
