(function () {
  'use strict';

  const SELECTOR = '[data-ve-staff-embed][data-version="2"]';

  const skeleton = '<div class="ve-v2-skeleton" role="status" aria-live="polite"><span class="ve-v2-sr">Loading staff directory</span><div class="ve-v2-heading"></div><div class="ve-v2-grid"><i></i><i></i><i></i><i></i></div></div>';

  const loadStyles = function (root, source) {
    const sourceStyle = source.querySelector('style[data-ve-staff-v2]');
    if (!sourceStyle) throw new Error('The staff response did not contain the v2 stylesheet.');
    const style = document.createElement('style');
    style.setAttribute('data-ve-staff-v2', '');
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

  // Filter controls may be temporarily outside the listing in the body overlay.
  function filterField(root, selector) {
    return root.querySelector(selector) || (root.__veStaffFilterPanel && root.__veStaffFilterPanel.querySelector(selector));
  }

  function listenToDirectory(root, type, handler) {
    root.addEventListener(type, handler);
    var panel = root.__veStaffFilterPanel;
    if (panel) panel.addEventListener(type, function (event) {
      if (!root.contains(panel)) handler(event);
    });
  }

  function bindFilterPanel(root) {
    var panel = root.querySelector('.ve-staff-filters');
    var trigger = root.querySelector('[data-ve-staff-filters-open]');
    if (!panel || !trigger) return;
    root.__veStaffFilterPanel = panel;
    var media = window.matchMedia('(max-width: 767.98px)');
    var home = document.createComment('Staff filters inline position');
    panel.parentNode.insertBefore(home, panel);
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
    var portal;
    var backdrop;
    var surface;

    function createPortal() {
      portal = document.createElement('div');
      portal.className = 've-staff-filter-portal';
      // Only our body child is styled; the host document and Bootstrap stay untouched.
      portal.style.cssText = 'all:initial!important;position:fixed!important;inset:0!important;margin:0!important;padding:0!important;border:0!important;width:100%!important;height:100%!important;max-width:none!important;max-height:none!important;background:transparent!important;z-index:2147483647!important;pointer-events:none!important;visibility:visible!important;opacity:1!important;transform:none!important;';
      var shadow = portal.attachShadow({ mode: 'open' });
      var source = root.getRootNode();
      all(source, 'style[data-ve-staff-v2], style[data-ve-staff], link[data-ve-staff], link#ve-staff-listing-css').forEach(function (style) {
        shadow.appendChild(style.cloneNode(true));
      });
      var style = document.createElement('style');
      // Critical overlay geometry also works before external listing CSS has loaded.
      style.textContent = '.ve-staff-filter-surface{font:14px/1.5 Arial,sans-serif;color:#222;pointer-events:none}' +
        '.ve-staff-filters-backdrop{position:fixed;inset:0;z-index:0!important;background:rgba(0,0,0,.45);pointer-events:auto}' +
        '.ve-staff-filters.ve-staff-filters--open{display:block!important;position:fixed!important;inset:0 0 auto!important;z-index:1!important;margin:0!important;width:auto!important;max-width:none!important;height:auto!important;max-height:85vh!important;box-sizing:border-box!important;padding:16px!important;border:0!important;background:#fff!important;color:#222!important;visibility:visible!important;opacity:1!important;transform:none!important;overflow:auto!important;pointer-events:auto!important;box-shadow:0 8px 24px rgba(0,0,0,.2)}' +
        '.ve-staff-filters-header{display:block!important}.ve-staff-filters-header h5{margin:0 0 12px;font-size:18px}' +
        '[data-ve-staff-filters-close]{display:inline-block!important;cursor:pointer}';
      shadow.appendChild(style);
      surface = document.createElement('div');
      surface.className = 've-staff-embed ve ve-staff-filter-surface';
      backdrop = document.createElement('div');
      backdrop.className = 've-staff-filters-backdrop';
      backdrop.addEventListener('click', function () { close(true); });
      surface.appendChild(backdrop);
      shadow.appendChild(surface);
    }

    function focusable() {
      return all(panel, 'button, input, select, textarea, a[href], [tabindex="0"]').filter(function (element) {
        return !element.disabled && element.getClientRects().length;
      });
    }
    function close(restoreFocus) {
      if (portal && portal.hasAttribute('popover')) {
        portal.hidePopover();
        portal.removeAttribute('popover');
      }
      panel.classList.remove('ve-staff-filters--open');
      panel.removeAttribute('role');
      panel.removeAttribute('aria-modal');
      trigger.setAttribute('aria-expanded', 'false');
      // Move the original controls back, retaining their values and listeners.
      if (portal) {
        home.parentNode.insertBefore(panel, home.nextSibling);
        portal.remove();
      }
      if (restoreFocus && previousFocus) previousFocus.focus();
    }
    listenToDirectory(root, 'click', function (event) {
      if (event.target.closest('[data-ve-staff-filters-open]') === trigger) {
        event.preventDefault();
        if (!media.matches) return;
        if (panel.classList.contains('ve-staff-filters--open')) { close(true); return; }
        previousFocus = trigger;
        if (!portal) createPortal();
        surface.appendChild(panel);
        panel.classList.add('ve-staff-filters--open');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.appendChild(portal);
        // Use the top layer when available, with the body-level portal as fallback.
        if (typeof portal.showPopover === 'function') {
          try {
            portal.setAttribute('popover', 'manual');
            portal.showPopover();
          } catch (error) {
            portal.removeAttribute('popover');
          }
        }
        (focusable()[0] || panel).focus();
      } else if (event.target.closest('[data-ve-staff-filters-close]')) {
        event.preventDefault();
        close(true);
      }
    });
    listenToDirectory(root, 'keydown', function (event) {
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
      const field = filterField(root, selector);
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
    listenToDirectory(root, 'change', function (event) {
      if (event.target.matches('#departmentfilterselect, #locationfilterselect')) update();
    });
    listenToDirectory(root, 'input', function (event) {
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
        bindFilterPanel(root);
        initializeFilters(root);
      })
      .catch(function (error) {
        root.innerHTML = '<div role="alert">The staff directory could not be loaded. Please try again later.</div>';
        console.error('VE staff v2 embed failed', { endpoint: endpoint, error: error });
      });
  };

  document.querySelectorAll(SELECTOR).forEach(loadEmbed);
}());
