(function () {
  'use strict';
  var runtimes = {};
  function loadRuntime(url) {
    if (!runtimes[url]) runtimes[url] = new Promise(function (resolve, reject) {
      var script = document.createElement('script'); script.src = url;
      script.onload = resolve; script.onerror = function () { delete runtimes[url]; script.remove(); reject(new Error('Directory controls could not be loaded.')); };
      document.head.appendChild(script);
    });
    return runtimes[url];
  }
  async function loadEmbed(host) {
    var root = host.shadowRoot || host.attachShadow({mode: 'open'});
    root.innerHTML = '<div role="status">Loading staff directory…</div>';
    try {
      var endpoint = new URL(host.getAttribute('data-endpoint'), window.location.href);
      var response = await fetch(endpoint.toString(), {cache: 'no-store', credentials: 'omit', headers: {Accept: 'text/html'}});
      if (!response.ok) throw new Error('Staff embed request failed with HTTP ' + response.status);
      var doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      var content = doc.querySelector('#veStaffList, #veStaffDisplay');
      var sourceStyle = doc.querySelector('style[data-ve-staff-v2]');
      var runtimeMeta = doc.querySelector('meta[name="ve-staff-runtime"]');
      // Older v2 responses used this same site-owned runtime path.
      var runtimeUrl = new URL(runtimeMeta ? runtimeMeta.content : '/assets/themes/vern-eide-staff/inc/assets/js/listing.js', endpoint);
      if (runtimeUrl.origin !== endpoint.origin || !/^https?:$/.test(runtimeUrl.protocol)) throw new Error('Invalid directory runtime URL.');
      if (!content || !sourceStyle) throw new Error('The response did not contain a v2 staff view.');
      var wrapper = document.createElement('div'); wrapper.className = 've-staff-embed';
      // Remote script tags must not run; behavior comes from the reviewed shared runtime.
      doc.querySelectorAll('script, iframe, object, embed').forEach(function (node) { node.remove(); });
      wrapper.appendChild(content);
      var modals = doc.querySelector('#listingmodals'); if (modals) wrapper.appendChild(modals);
      var style = document.createElement('style'); style.setAttribute('data-ve-staff-v2', ''); style.textContent = sourceStyle.textContent;
      root.replaceChildren(style, wrapper);
      root.querySelectorAll('img[data-src], img[data-lazy-src], img[data-original]').forEach(function (img) {
        img.src = img.getAttribute('data-src') || img.getAttribute('data-lazy-src') || img.getAttribute('data-original');
        img.classList.add('ve-lazy-loaded');
      });
      await loadRuntime(runtimeUrl.toString());
      window.VEStaffDirectory.initialize(content);
    } catch (error) {
      root.innerHTML = '<div role="alert">The staff directory could not be loaded. Reload the page to try again.</div>';
      console.error('VE staff v2 embed failed', error);
    }
  }
  document.querySelectorAll('[data-ve-staff-embed][data-version="2"]').forEach(loadEmbed);
}());
