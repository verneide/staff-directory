/**
 * ve-security.js (DROP-IN)
 * Debug is read ONLY from the host page URL (window.location.search)
 * - If ?debug=vetest is on the page, security is disabled.
 * - Prevents double-run.
 */

(function () {
  // Prevent double execution
  if (window.__VE_SECURITY_RAN__) return;
  window.__VE_SECURITY_RAN__ = true;

  const DEBUG_VALUES = new Set(['vetest', 'verneide', 'staff', 'true', '1', 'yes', 'on']);

  function getHostDebugValue() {
    try {
      const params = new URLSearchParams(window.location.search || '');
      const v = params.get('debug');
      return (v || '').toString().trim().toLowerCase();
    } catch (e) {
      return '';
    }
  }

  const debugValue = getHostDebugValue();
  const debugEnabled =
    DEBUG_VALUES.has(debugValue) ||
    (debugValue && debugValue.indexOf('vetest') !== -1);

  // If debug enabled, do nothing (no alerts—just a console note)
  if (debugEnabled) {
    console.log('[ve-security] Debug enabled from host URL:', debugValue || '(empty)');
    return;
  }

  // --- Lockdown path (only when debug is NOT enabled) ---

  function onContextMenu(e) {
    e.preventDefault();
  }

  function ctrlShiftKey(e, key) {
    return e.ctrlKey && e.shiftKey && e.keyCode === key.charCodeAt(0);
  }

  function onKeyDown(e) {
    if (
      e.keyCode === 123 || // F12
      ctrlShiftKey(e, 'I') ||
      ctrlShiftKey(e, 'J') ||
      ctrlShiftKey(e, 'C') ||
      (e.ctrlKey && e.keyCode === 'U'.charCodeAt(0))
    ) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Load disable-devtool once
    if (!document.querySelector('script[data-ve-disable-devtool]')) {
      const script = document.createElement('script');
      script.setAttribute('disable-devtool', '');
      script.setAttribute('data-ve-disable-devtool', '1');
      script.src = 'https://cdn.jsdelivr.net/npm/disable-devtool@latest';
      document.head.appendChild(script);
    }

    // Disable right-click + hotkeys
    document.addEventListener('contextmenu', onContextMenu);
    document.addEventListener('keydown', onKeyDown, true);
  });
})();