(function (windowObject) {
  'use strict';

  var namespace = windowObject.VEStaffDirectory || {};
  var selector = 'img.ve-lazy[data-src], img.lazy[data-src], img[data-lazy-src], img[data-original]';
  var preloadMargin = '800px 0px';

  function imageSource(image) {
    return image.getAttribute('data-src') || image.getAttribute('data-lazy-src') || image.getAttribute('data-original');
  }

  function reveal(image) {
    var source = imageSource(image);
    if (!source || image.dataset.veStaffLazyState === 'loading' || image.dataset.veStaffLazyState === 'loaded') {
      return;
    }

    image.dataset.veStaffLazyState = 'loading';
    image.classList.add('ve-lazy-loading');
    image.addEventListener('load', function () {
      image.dataset.veStaffLazyState = 'loaded';
      image.classList.remove('lazy', 've-lazy', 'lazyload', 'lazyload-loading', 've-lazy-loading');
      image.classList.add('ve-lazy-loaded');
    }, { once: true });
    image.addEventListener('error', function () {
      image.dataset.veStaffLazyState = 'error';
      image.classList.remove('lazyload-loading', 've-lazy-loading');
      console.error('VE staff image failed to load', { src: source, alt: image.alt });
    }, { once: true });
    image.src = source;
    image.removeAttribute('data-src');
    image.removeAttribute('data-lazy-src');
    image.removeAttribute('data-original');
  }

  function initialize(root) {
    var observer = 'IntersectionObserver' in windowObject ? new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          observer.unobserve(entry.target);
          reveal(entry.target);
        }
      });
    }, { rootMargin: preloadMargin }) : null;

    function discover(scope) {
      var images = [];
      if (scope.matches && scope.matches(selector)) {
        images.push(scope);
      }
      Array.prototype.push.apply(images, scope.querySelectorAll ? scope.querySelectorAll(selector) : []);
      images.forEach(function (image) {
        if (observer) {
          observer.observe(image);
        } else {
          reveal(image);
        }
      });
    }

    discover(root);
    var mutations = new MutationObserver(function (records) {
      records.forEach(function (record) {
        Array.prototype.forEach.call(record.addedNodes, function (node) {
          if (node.nodeType === 1) {
            discover(node);
          }
        });
      });
    });
    mutations.observe(root, { childList: true, subtree: true });

    return function () {
      mutations.disconnect();
      if (observer) {
        observer.disconnect();
      }
    };
  }

  namespace.lazyLoad = { initialize: initialize, reveal: reveal };
  windowObject.VEStaffDirectory = namespace;

  function initializeDocument() {
    if (!document.documentElement.dataset.veStaffLazyInitialized) {
      document.documentElement.dataset.veStaffLazyInitialized = 'true';
      initialize(document);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDocument, { once: true });
  } else {
    initializeDocument();
  }
}(window));
