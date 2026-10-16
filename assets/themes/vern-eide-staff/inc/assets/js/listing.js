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
    var field = root.querySelector(selector);
    return field ? field.value : 'all';
  }

  function resetFields(root, selectors) {
    selectors.forEach(function (selector) {
      var field = root.querySelector(selector);
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
    root.addEventListener('change', function (event) {
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
    root.addEventListener('input', function (event) {
      if (event.target.matches('#employeenamesearch')) {
        filterText(root, event.target, 'data-employee-name');
      }
      if (event.target.matches('#employeeextsearch')) {
        filterText(root, event.target, 'data-ext');
      }
    });
    root.addEventListener('click', function (event) {
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

  function bindFilterPanel(root) {
    var panel = root.querySelector('.ve-staff-filters');
    var trigger = root.querySelector('[data-ve-staff-filters-open]');
    if (!panel || !trigger) return;
    var media = window.matchMedia('(max-width: 767.98px)');
    var backdrop = document.createElement('div');
    backdrop.className = 've-staff-filters-backdrop';
    backdrop.hidden = true;
    root.appendChild(backdrop);
    var previousFocus;
    function focusable() {
      return all(panel, 'button, input, select, textarea, a[href], [tabindex="0"]').filter(function (element) {
        return !element.disabled && element.getClientRects().length;
      });
    }
    function close(restoreFocus) {
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
    bindFilters(root);
    bindFilterPanel(root);
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
