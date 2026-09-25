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

  function replaceSiteDomain(root) {
    var domain = windowObject.location.hostname.replace(/^www\./, '');
    all(root, '.employee-list a[href*="{{sitedomain}}"] ').forEach(function (link) {
      link.href = link.getAttribute('href').replace('{{sitedomain}}', domain);
    });
  }

  function modalTarget(root, button) {
    var selector = button.getAttribute('data-bs-target');
    var container = root.parentElement || root;
    return selector && container.querySelector(selector);
  }

  function closeModal(modal) {
    var backdrop = modal.__veStaffBackdrop;
    modal.classList.remove('veshow');
    modal.setAttribute('aria-hidden', 'true');
    modal.style.display = 'none';
    if (backdrop) {
      backdrop.remove();
      modal.__veStaffBackdrop = null;
    }
    document.body.classList.remove('vemodal-open');
    document.body.style.overflow = modal.__veStaffBodyOverflow || '';
    if (modal.__veStaffTrigger) {
      modal.__veStaffTrigger.focus();
      modal.__veStaffTrigger = null;
    }
  }

  function openModal(modal, button) {
    var modalContainer = modal.parentElement;
    var backdrop = document.createElement('div');
    backdrop.className = 'vemodal-backdrop vefade veshow';
    backdrop.addEventListener('click', function () {
      closeModal(modal);
    });
    modal.__veStaffTrigger = button;
    modal.__veStaffBackdrop = backdrop;
    modal.__veStaffBodyOverflow = document.body.style.overflow;
    modalContainer.appendChild(backdrop);
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('veshow');
    document.body.classList.add('vemodal-open');
    document.body.style.overflow = 'hidden';
    var focusTarget = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusTarget) {
      focusTarget.focus();
    }
  }

  function populateSuggestEdit(modal, button) {
    var form = modal.querySelector('#veSuggestEditForm');
    var fieldNames = ['email', 'extension', 'tracking_number', 'title', 'cell_phone', 'direct_office', 'other'];
    if (!form) {
      return;
    }
    form.reset();
    function setField(name, fieldValue) {
      var field = form.querySelector('[name="' + name + '"]');
      if (field) {
        field.value = fieldValue || '';
      }
    }
    setField('staff_post_id', button.getAttribute('data-staff-post-id'));
    setField('employee_name', button.getAttribute('data-employee-name'));
    setField('employee_name_display', button.getAttribute('data-employee-name'));
    fieldNames.forEach(function (fieldName) {
      var currentValue = button.getAttribute('data-current-' + fieldName.replace(/_/g, '-')) || '';
      var currentValueDisplay = modal.querySelector('[data-current-value="' + fieldName + '"]');
      setField('current_' + fieldName, currentValue);
      if (currentValueDisplay) {
        currentValueDisplay.value = currentValue || 'Not Set';
      }
    });
    var heading = modal.querySelector('[data-suggest-edit-employee]');
    if (heading) {
      heading.textContent = button.getAttribute('data-employee-name') || '';
    }
  }

  function submitSuggestEdit(form) {
    var status = form.querySelector('.ve-suggest-edit-status');
    var submitButton = form.querySelector('[type="submit"]');
    var config = windowObject.veStaffSuggestEdit || {};
    var data = new FormData(form);
    data.append('action', 've_staff_suggest_edit');
    data.append('nonce', config.nonce || '');
    if (!config.ajaxUrl) {
      if (status) {
        status.textContent = 'Unable to submit suggested edit.';
      }
      return;
    }
    if (status) {
      status.textContent = 'Submitting...';
    }
    if (submitButton) {
      submitButton.disabled = true;
    }
    fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
      .then(function (response) { return response.json(); })
      .then(function (response) {
        if (!response.success) {
          throw new Error(response.data && response.data.message ? response.data.message : 'Unable to submit suggested edit.');
        }
        if (status) {
          status.textContent = response.data.message;
        }
        windowObject.setTimeout(function () { closeModal(form.closest('.vemodal')); }, 1200);
      })
      .catch(function (error) {
        if (status) {
          status.textContent = error.message;
        }
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
        }
      });
  }

  function bindModals(root) {
    var container = root.parentElement || root;
    container.addEventListener('click', function (event) {
      var openButton = event.target.closest('[data-bs-toggle="vemodal"]');
      var closeButton = event.target.closest('[data-bs-dismiss="vemodal"], [data-suggest-edit-close]');
      var modal;
      if (openButton && root.contains(openButton)) {
        modal = modalTarget(root, openButton);
        if (modal) {
          event.preventDefault();
          event.stopPropagation();
          if (openButton.classList.contains('ve-suggest-edit-btn')) {
            populateSuggestEdit(modal, openButton);
          }
          openModal(modal, openButton);
        }
      } else if (closeButton) {
        modal = closeButton.closest('.vemodal');
        if (modal) {
          event.preventDefault();
          closeModal(modal);
        }
      }
    });
    container.addEventListener('submit', function (event) {
      if (event.target.matches('#veSuggestEditForm')) {
        event.preventDefault();
        submitSuggestEdit(event.target);
      }
    });
    container.addEventListener('keydown', function (event) {
      var open = container.querySelector('.vemodal.veshow');
      if (event.key === 'Escape' && open) {
        closeModal(open);
      }
    });
  }

  function initialize(root) {
    if (!root || root.__veStaffInitialized) {
      return;
    }
    root.__veStaffInitialized = true;
    setHidden(all(root, '.loading-section'), true);
    bindFilters(root);
    bindModals(root);
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
