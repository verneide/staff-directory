(function (windowObject) {
  'use strict';

  var namespace = windowObject.VEStaffDirectory || {};

  function all(root, selector) {
    return Array.prototype.slice.call(root.querySelectorAll(selector));
  }

  function setHidden(elements, hidden) {
    elements.forEach(function (element) {
      element.classList.toggle('ve-hidden', hidden);
    });
  }

  function value(root, selector) {
    var field = filterField(root, selector);
    return field ? field.value : 'all';
  }

  function resetFields(root, selectors) {
    selectors.forEach(function (selector) {
      var field = filterField(root, selector);
      if (field) {
        field.value = field.tagName === 'SELECT' ? field.options[0].value : '';
      }
    });
  }

  function updateEmptyState(root) {
    var oldMessage = root.querySelector('.ve-no-results');
    if (oldMessage) {
      oldMessage.remove();
    }
    if (root.querySelector('.employee-tile:not(.ve-hidden), .department-header:not(.ve-hidden)')) {
      return;
    }
    var row = root.querySelector('.employee-list .ve-row');
    if (row) {
      var message = document.createElement('h3');
      message.className = 've-no-results ve-text-center';
      message.textContent = 'No Results Found';
      row.appendChild(message);
    }
  }

  function filterSelects(root) {
    var department = value(root, '#departmentfilterselect');
    var location = value(root, '#locationfilterselect');
    all(root, '.employee-tile, .department-header').forEach(function (item) {
      var departmentMatches = department === 'all' || item.getAttribute('data-dept') === department;
      var locationMatches = location === 'all' || (item.getAttribute('data-loc') || '').indexOf(location) !== -1;
      item.classList.toggle('ve-hidden', !departmentMatches || !locationMatches);
    });
    updateEmptyState(root);
  }

  function filterText(root, field, attribute) {
    var query = field.value.toUpperCase();
    resetFields(root, ['#departmentfilterselect', '#locationfilterselect', '#tagfilterselect']);
    setHidden(all(root, '.employee-tile, .department-header'), query.length > 0);
    all(root, '.employee-tile').forEach(function (item) {
      if (!query || (item.getAttribute(attribute) || '').toUpperCase().indexOf(query) !== -1) {
        item.classList.remove('ve-hidden');
      }
    });
    updateEmptyState(root);
  }

  function bindFilters(root) {
    listenToDirectory(root, 'change', function (event) {
      if (event.target.matches('#departmentfilterselect, #locationfilterselect')) {
        resetFields(root, ['#employeenamesearch', '#employeeextsearch', '#tagfilterselect']);
        filterSelects(root);
      }
      if (event.target.matches('#tagfilterselect')) {
        var tag = event.target.value;
        resetFields(root, ['#departmentfilterselect', '#locationfilterselect', '#employeenamesearch', '#employeeextsearch']);
        setHidden(all(root, '.employee-tile, .department-header'), tag !== 'all');
        all(root, '.employee-tile').forEach(function (item) {
          if (tag === 'all' || (item.getAttribute('data-tags') || '').indexOf(tag) !== -1) {
            item.classList.remove('ve-hidden');
          }
        });
        updateEmptyState(root);
      }
    });
    listenToDirectory(root, 'input', function (event) {
      if (event.target.matches('#employeenamesearch')) {
        filterText(root, event.target, 'data-employee-name');
      }
      if (event.target.matches('#employeeextsearch')) {
        filterText(root, event.target, 'data-ext');
      }
    });
    listenToDirectory(root, 'click', function (event) {
      if (event.target.closest('#resetFilterBtn')) {
        resetFields(root, ['#departmentfilterselect', '#locationfilterselect', '#tagfilterselect', '#employeenamesearch', '#employeeextsearch']);
        setHidden(all(root, '.employee-tile, .department-header'), false);
        updateEmptyState(root);
      }
      if (event.target.closest('#viewMoreBtn')) {
        setHidden(all(root, '.employee-tile'), false);
        setHidden(all(root, '.viewMoreWrapper'), true);
      }
    });
  }

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

  function replaceSiteDomain(root) {
    var domain = windowObject.location.hostname.replace(/^www\./, '');
    all(root, '.employee-list a[href*="{{sitedomain}}"] ').forEach(function (link) {
      link.href = link.getAttribute('href').replace('{{sitedomain}}', domain);
    });
  }

  function initialize(root) {
    if (!root || root.__veStaffInitialized) {
      return;
    }
    root.__veStaffInitialized = true;
    setHidden(all(root, '.loading-section'), true);
    bindFilterPanel(root);
    bindFilters(root);
    replaceSiteDomain(root);
    if (namespace.lazyLoad) {
      namespace.lazyLoad.initialize(root);
    }
  }

  namespace.initialize = initialize;
  windowObject.VEStaffDirectory = namespace;

  function initializeDocument() {
    all(document, '#veStaffList, #veStaffDisplay').forEach(function (container) {
      initialize(container);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDocument, { once: true });
  } else {
    initializeDocument();
  }
}(window));
