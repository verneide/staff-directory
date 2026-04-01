/** ve-lazy-load.js (iframe-safe drop-in) **/
(function () {
  var PLACEHOLDER =
    "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==";

  // Match both legacy and new class names
  var SELECTOR = "img.lazy[data-src], img.ve-lazy[data-src]";

  // Tunables
  var PRELOAD_PX = 600;           // how far ahead to load
  var START_DELAY_MS = 250;       // wait for layout settle (iframe injection)
  var POLL_MS = 350;              // safety-net polling interval
  var MAX_POLL_MS = 12000;        // stop polling after this (still listens to mutations)
  var SCROLL_THROTTLE_MS = 80;

  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function ensurePlaceholder(img) {
    if (!img.getAttribute("src")) img.setAttribute("src", PLACEHOLDER);
  }

  function markLoaded(img) {
    img.classList.remove("lazy", "ve-lazy", "lazyload-loading");
    img.classList.add("loaded");
  }

  function markError(img) {
    img.classList.remove("lazyload-loading");
  }

  function hasRealSrc(img) {
    var src = img.getAttribute("src") || "";
    return src && src.indexOf("data:image") !== 0;
  }

  function loadImg(img) {
    // already loaded or already has real src
    if (img.__veLazyDone) return;
    var src = img.getAttribute("data-src");
    if (!src) return;

    img.__veLazyDone = true;
    img.classList.add("lazyload-loading");

    img.addEventListener(
      "load",
      function () {
        markLoaded(img);
      },
      { once: true }
    );

    img.addEventListener(
      "error",
      function () {
        // don't leave it stuck hidden
        markError(img);
      },
      { once: true }
    );

    img.src = src;
  }

  function inView(el, preloadPx) {
    var r = el.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight || 0;
    // “Near view” (preload below and slightly above)
    return r.top <= vh + preloadPx && r.bottom >= -preloadPx;
  }

  function init(root) {
    root = root || document;

    var obs = null;
    var pollTimer = null;
    var pollStopTimer = null;
    var mo = null;
    var started = false;

    function scanAndPrime() {
      var imgs = toArray(root.querySelectorAll(SELECTOR));
      if (!imgs.length) return imgs;

      imgs.forEach(function (img) {
        ensurePlaceholder(img);

        // If it already loaded (cache), unstick classes
        try {
          if (img.complete && img.naturalWidth > 0) {
            markLoaded(img);
            img.__veLazyDone = true;
          } else if (hasRealSrc(img)) {
            // src set by something else
            img.__veLazyDone = true;
          }
        } catch (e) {}
      });

      return imgs;
    }

    function attachObserver(imgs) {
      if (!("IntersectionObserver" in window)) return;

      // If already created, just observe new ones
      if (!obs) {
        obs = new IntersectionObserver(
          function (entries) {
            entries.forEach(function (entry) {
              if (!entry.isIntersecting) return;
              loadImg(entry.target);
              try { obs.unobserve(entry.target); } catch (e) {}
            });
          },
          {
            root: null,
            rootMargin: "0px 0px " + PRELOAD_PX + "px 0px",
            threshold: 0.01,
          }
        );
      }

      imgs.forEach(function (img) {
        if (img.__veLazyDone) return;
        try { obs.observe(img); } catch (e) {}
      });
    }

    // Safety net that works even if parent scroll drives visibility (iframe)
    function pollLoadNearView() {
      var imgs = toArray(root.querySelectorAll(SELECTOR));
      imgs.forEach(function (img) {
        if (img.__veLazyDone) return;
        ensurePlaceholder(img);
        if (inView(img, PRELOAD_PX)) loadImg(img);
      });
    }

    function startPolling() {
      // frequent short polling right after init; then stop (MutationObserver keeps it fresh)
      if (pollTimer) return;
      pollTimer = setInterval(pollLoadNearView, POLL_MS);
      pollStopTimer = setTimeout(function () {
        try { clearInterval(pollTimer); } catch (e) {}
        pollTimer = null;
      }, MAX_POLL_MS);
    }

    function throttle(fn, ms) {
      var t = null;
      return function () {
        if (t) return;
        t = setTimeout(function () {
          t = null;
          fn();
        }, ms);
      };
    }

    function onScrollOrResize() {
      // If host page scroll doesn't propagate events into iframe reliably,
      // polling + mutation covers it — but these help on normal pages.
      pollLoadNearView();
    }

    var throttledScroll = throttle(onScrollOrResize, SCROLL_THROTTLE_MS);

    function start() {
      if (started) return;
      started = true;

      var imgs = scanAndPrime();
      attachObserver(imgs);
      pollLoadNearView();
      startPolling();

      // Listen (capture=true) so it works inside nested scroll containers
      window.addEventListener("scroll", throttledScroll, true);
      window.addEventListener("resize", throttledScroll, true);

      // Watch for new cards (View More / filters / ajax)
      if ("MutationObserver" in window) {
        mo = new MutationObserver(function () {
          var newImgs = scanAndPrime();
          attachObserver(newImgs);
          // kick a quick pass; this is cheap
          pollLoadNearView();
        });

        try {
          mo.observe(root === document ? document.body : root, {
            childList: true,
            subtree: true,
          });
        } catch (e) {}
      }
    }

    // Delay helps iframe injection + CSS load settle before calculating rects
    setTimeout(start, START_DELAY_MS);

    // Return an optional cleanup handle if you ever want it
    return function destroy() {
      try { if (obs) obs.disconnect(); } catch (e) {}
      try { if (mo) mo.disconnect(); } catch (e) {}
      try { if (pollTimer) clearInterval(pollTimer); } catch (e) {}
      try { if (pollStopTimer) clearTimeout(pollStopTimer); } catch (e) {}
      window.removeEventListener("scroll", throttledScroll, true);
      window.removeEventListener("resize", throttledScroll, true);
    };
  }

  // Expose for embed init
  window.veLazyLoadInit = init;

  // Auto-init for normal pages
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      init(document);
    });
  } else {
    init(document);
  }
})();