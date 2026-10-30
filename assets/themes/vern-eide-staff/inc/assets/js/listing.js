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

  var filterDefinitions = [
    ['employeenamesearch', 'Name', 'data-employee-name'],
    ['employeeextsearch', 'Extension', 'data-ext'],
    ['departmentfilterselect', 'Department', 'data-dept'],
    ['locationfilterselect', 'Location', 'data-loc'],
    ['tagfilterselect', 'Tag', 'data-tags']
  ];

  function activeFilters(root) {
    return filterDefinitions.map(function (definition) {
      var field = filterField(root, '#' + definition[0]);
      if (!field || !field.value.trim() || field.value === 'all') return null;
      return { definition: definition, field: field, value: field.value.trim() };
    }).filter(Boolean);
  }

  function matchesFilter(card, filter) {
    var raw = card.getAttribute(filter.definition[2]) || '';
    if (filter.field.tagName !== 'SELECT') return raw.toLowerCase().indexOf(filter.value.toLowerCase()) !== -1;
    if (filter.definition[2] === 'data-dept') return raw === filter.value;
    var values;
    try { values = JSON.parse(raw); } catch (_) { values = [raw]; }
    if (!Array.isArray(values)) values = [values];
    return values.some(function (entry) { return String(entry).replace(/^0+(?=\d)/, '') === filter.value.replace(/^0+(?=\d)/, ''); });
  }

  function renderFilterPills(root, filters) {
    var pills = root.querySelector('.ve-filter-pills');
    if (!pills) {
      pills = document.createElement('div');
      pills.className = 've-filter-pills';
      pills.setAttribute('aria-label', 'Active staff filters');
      var trigger = root.querySelector('[data-ve-staff-filters-open]');
      var panel = root.__veStaffFilterPanel;
      if (trigger) trigger.insertAdjacentElement('afterend', pills);
      else if (panel) panel.insertAdjacentElement('afterend', pills);
      else root.prepend(pills);
    }
    pills.replaceChildren();
    filters.forEach(function (filter) {
      var label = filter.field.tagName === 'SELECT' ? filter.field.options[filter.field.selectedIndex].text : filter.value;
      var button = document.createElement('button');
      button.type = 'button'; button.className = 've-filter-pill';
      button.setAttribute('data-remove-filter', filter.definition[0]);
      button.setAttribute('aria-label', 'Remove ' + filter.definition[1] + ' filter: ' + label);
      button.textContent = filter.definition[1] + ': ' + label + ' ×';
      pills.appendChild(button);
    });
    pills.hidden = !filters.length;
  }

  function applyFilters(root) {
    var filters = activeFilters(root);
    var cards = all(root, '.employee-tile');
    cards.forEach(function (card) { card.classList.toggle('ve-hidden', !filters.every(function (filter) { return matchesFilter(card, filter); })); });
    all(root, '.department-header').forEach(function (heading) {
      heading.classList.toggle('ve-hidden', !cards.some(function (card) { return !card.classList.contains('ve-hidden') && card.getAttribute('data-dept') === heading.getAttribute('data-dept'); }));
    });
    renderFilterPills(root, filters);
    updateEmptyState(root);
  }

  function bindFilters(root) {
    ['input', 'change'].forEach(function (type) {
      listenToDirectory(root, type, function (event) {
        if (filterDefinitions.some(function (definition) { return event.target.id === definition[0]; })) applyFilters(root);
      });
    });
    listenToDirectory(root, 'click', function (event) {
      var pill = event.target.closest('[data-remove-filter]');
      if (pill) {
        var field = filterField(root, '#' + pill.getAttribute('data-remove-filter'));
        if (field) field.value = field.tagName === 'SELECT' ? field.options[0].value : '';
        applyFilters(root);
        var next = root.querySelector('.ve-filter-pill, [data-ve-staff-filters-open]');
        if (next) next.focus();
      }
      if (event.target.closest('#resetFilterBtn')) {
        event.preventDefault();
        resetFields(root, filterDefinitions.map(function (definition) { return '#' + definition[0]; }));
        applyFilters(root);
      }
      if (event.target.closest('#viewMoreBtn')) {
        applyFilters(root);
        setHidden(all(root, '.viewMoreWrapper'), true);
      }
    });
    renderFilterPills(root, activeFilters(root));
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
    if (!panel) {
      var fields = root.querySelector('#employeeHeadingWrapper');
      if (!fields) return;
      panel = document.createElement('div'); panel.className = 've-staff-filters'; panel.tabIndex = -1;
      fields.before(panel); panel.appendChild(fields);
      var header = document.createElement('div'); header.className = 've-staff-filters-header';
      header.innerHTML = '<h5>Staff filters</h5>';
      panel.prepend(header);
      var closeButton = document.createElement('button'); closeButton.type = 'button';
      closeButton.setAttribute('data-ve-staff-filters-close', ''); closeButton.textContent = 'Close Filters';
      panel.appendChild(closeButton);
    }
    if (!trigger) {
      trigger = document.createElement('button'); trigger.type = 'button'; trigger.className = 've-secondary-button-grey';
      trigger.setAttribute('data-ve-staff-filters-open', ''); trigger.textContent = 'Staff Filters'; panel.before(trigger);
    }
    root.classList.add('ve-directory-enhanced');
    filterDefinitions.forEach(function (definition) {
      var field = panel.querySelector('#' + definition[0]);
      if (field && !field.getAttribute('aria-label')) field.setAttribute('aria-label', definition[1]);
    });
    root.__veStaffFilterPanel = panel;

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
      portal = document.createElement('dialog');
      portal.className = 've-staff-filter-portal';
      portal.setAttribute('aria-label', 'Staff filters');
      portal.addEventListener('cancel', function (event) { event.preventDefault(); close(true); });
      // Only our body child is styled; the host document and Bootstrap stay untouched.
      portal.style.cssText = 'all:initial!important;position:fixed!important;inset:0!important;margin:0!important;padding:0!important;border:0!important;width:100%!important;height:100%!important;max-width:none!important;max-height:none!important;background:#e9eef3!important;z-index:2147483647!important;pointer-events:auto!important;visibility:visible!important;opacity:1!important;transform:none!important;';
      var shadowHost = document.createElement('div');
      portal.appendChild(shadowHost);
      var shadow = shadowHost.attachShadow({ mode: 'open' });
      var source = root.getRootNode();
      all(source, 'style[data-ve-staff-v2], style[data-ve-staff], link[data-ve-staff], link#ve-staff-listing-css').forEach(function (style) {
        shadow.appendChild(style.cloneNode(true));
      });
      var style = document.createElement('style');
      // Critical overlay geometry also works before external listing CSS has loaded.
      style.textContent = '.ve-staff-filter-surface{font:14px/1.5 Arial,sans-serif;color:#222;pointer-events:none}' +
        '.ve-staff-filters-backdrop{position:fixed;inset:0;z-index:0!important;background:rgba(0,0,0,.45);pointer-events:auto}' +
        '.ve-staff-filters.ve-staff-filters--open{display:block!important;position:fixed!important;inset:0!important;z-index:1!important;margin:0!important;width:auto!important;max-width:none!important;height:100%!important;max-height:none!important;box-sizing:border-box!important;padding:clamp(20px,4vw,64px)!important;border:0!important;background:#e9eef3!important;color:#222!important;visibility:visible!important;opacity:1!important;transform:none!important;overflow:auto!important;pointer-events:auto!important;box-shadow:0 8px 24px rgba(0,0,0,.2)}' +
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
      if (portal && portal.open) portal.close();
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
        if (panel.classList.contains('ve-staff-filters--open')) { close(true); return; }
        previousFocus = trigger;
        if (!portal) createPortal();
        surface.appendChild(panel);
        panel.classList.add('ve-staff-filters--open');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.appendChild(portal);
        portal.showModal();
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

    root.__veStaffDisposePanel = function () {
      close(false);

    };
  }

  function replaceSiteDomain(root) {
    var domain = windowObject.location.hostname.replace(/^www\./, '');
    all(root, '.employee-list a[href*="{{sitedomain}}"] ').forEach(function (link) {
      link.href = link.getAttribute('href').replace('{{sitedomain}}', domain);
    });
  }

  function bindSuggestions(root) {
    var dialog;
    var opener;
    var request;
    function close() {
      if (request) { request.abort(); request = null; }
      if (dialog) { if (dialog.open) dialog.close(); dialog.remove(); dialog = null; }
      if (opener && opener.isConnected) opener.focus();
    }
    function onClick(event) {
      var button = event.target.closest('.ve-suggest-edit-btn');
      if (!button) return;
      event.preventDefault(); event.stopPropagation();
      var scope = root.closest('.ve-staff-embed') || root.parentNode;
      var template = scope.querySelector('.ve-suggest-edit-modal .vemodal-content');
      if (!template) return;
      close(); opener = button;
      dialog = document.createElement('dialog');
      dialog.className = 've-suggest-dialog';
      dialog.setAttribute('aria-label', 'Suggest an Edit');
      dialog.appendChild(template.cloneNode(true));
      // Avoid duplicate IDs with the hidden template or another directory instance.
      var idPrefix = 've-suggestion-' + Math.random().toString(36).slice(2) + '-';
      all(dialog, '[id]').forEach(function (element) {
        var oldId = element.id;
        all(dialog, 'label[for]').forEach(function (label) {
          if (label.htmlFor === oldId) label.htmlFor = idPrefix + oldId;
        });
        element.id = idPrefix + oldId;
      });
      var form = dialog.querySelector('form');
      if (!form) { close(); return; }
      var employee = button.getAttribute('data-employee-name') || '';
      dialog.querySelector('[data-suggest-edit-employee]').textContent = employee;
      form.elements.namedItem('staff_post_id').value = button.getAttribute('data-staff-post-id') || '';
      form.elements.namedItem('employee_name').value = employee;
      form.elements.namedItem('employee_name_display').value = employee;
      all(form, '[data-current-value]').forEach(function (field) {
        var key = field.getAttribute('data-current-value');
        var current = button.getAttribute('data-current-' + key.replace(/_/g, '-')) || '';
        field.value = current || 'Not set';
        form.elements.namedItem('current_' + key).value = current;
      });
      dialog.addEventListener('cancel', function (event) { event.preventDefault(); close(); });
      dialog.addEventListener('click', function (event) {
        if (event.target.closest('[data-suggest-edit-close]')) { event.preventDefault(); close(); }
      });
      form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (request || form.hasAttribute('data-submitted') || !form.reportValidity()) return;
        var status = form.querySelector('.ve-suggest-edit-status');
        var submit = form.querySelector('[type="submit"]');
        var endpoint;
        try {
          endpoint = new URL(form.getAttribute('data-submit-url'));
          if (endpoint.origin !== 'https://staff.eideweb.com' && endpoint.origin !== windowObject.location.origin) throw new Error();
          if (!endpoint.pathname.endsWith('/admin-ajax.php')) throw new Error();
        } catch (_) { status.textContent = 'Suggestion service is unavailable. Reload the directory and try again.'; return; }
        var data = new URLSearchParams(new FormData(form));
        data.set('action', 've_staff_suggest_edit');
        data.set('nonce', form.getAttribute('data-submit-nonce') || '');
        data.set('list_id', root.getAttribute('data-list-id') || '');
        request = new AbortController();
        var currentRequest = request;
        var timeout = windowObject.setTimeout(function () { currentRequest.abort(); }, 30000);
        submit.disabled = true; status.textContent = 'Submitting your suggestion…';
        try {
          var response = await fetch(endpoint.toString(), { method: 'POST', body: data, credentials: 'same-origin', redirect: 'error', signal: request.signal });
          var result = await response.json();
          if (!response.ok || !result.success) throw new Error(result.data && result.data.message || 'Unable to submit your suggestion. Reload the directory and try again.');
          status.textContent = result.data.message;
          all(form, 'input:not([type="hidden"])').forEach(function (field) { field.disabled = true; });
          form.setAttribute('data-submitted', 'true');
        } catch (error) {
          status.textContent = error instanceof TypeError || error.name === 'AbortError' ? 'Could not confirm submission. Please check before trying again.' : error.message;
        } finally {
          windowObject.clearTimeout(timeout);
          if (request === currentRequest) request = null;
          submit.disabled = form.hasAttribute('data-submitted');
        }
      });
      root.appendChild(dialog);
      dialog.showModal();
      form.elements.namedItem('requester_name').focus();
    }
    // Capture before host Bootstrap handlers can attempt to open the hidden template.
    root.addEventListener('click', onClick, true);
    all(root, '.ve-suggest-edit-btn').forEach(function (button) { button.textContent = 'Suggest an Edit'; });
    root.__veStaffDisposeSuggestions = function () { root.removeEventListener('click', onClick, true); close(); };
  }

  function initialize(root) {
    if (!root) return;
    // Embed callbacks pass a wrapper, while document startup finds the listing.
    // Always bind to the listing itself so bubbling cannot toggle a panel twice.
    if (!root.matches || !root.matches('#veStaffList, #veStaffDisplay')) {
      all(root, '#veStaffList, #veStaffDisplay').forEach(initialize);
      return;
    }
    if (root.__veStaffInitialized) {
      return;
    }
    root.__veStaffInitialized = true;
    setHidden(all(root, '.loading-section'), true);
    bindFilterPanel(root);
    bindFilters(root);
    bindSuggestions(root);
    replaceSiteDomain(root);
    if (namespace.lazyLoad) {
      root.__veStaffDisposeImages = namespace.lazyLoad.initialize(root);
    }
  }

  namespace.initialize = initialize;
  namespace.dispose = function (scope) {
    var roots = scope.matches && scope.matches('#veStaffList, #veStaffDisplay') ? [scope] : all(scope, '#veStaffList, #veStaffDisplay');
    roots.forEach(function (root) {
      if (root.__veStaffDisposePanel) root.__veStaffDisposePanel();
      if (root.__veStaffDisposeSuggestions) root.__veStaffDisposeSuggestions();
      if (root.__veStaffDisposeImages) root.__veStaffDisposeImages();
    });
  };
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
