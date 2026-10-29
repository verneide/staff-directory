const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const ts = require('typescript');
const { JSDOM } = require('jsdom');
const vm = require('node:vm');
function load(file, globals = {}) {
  const code = ts.transpileModule(fs.readFileSync(file, 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2018 }
  }).outputText;
  const context = { exports: {}, ...globals };
  vm.runInNewContext(code, context);
  return context.exports;
}
const apiFile = 'src/webparts/eideWebStaffIntegration/components/staffApi.ts';
test('only approved listing URLs; remove conflicting modes and preserve existing options', () => {
  const { listingEndpoint } = load(apiFile, { URL });
  const url = listingEndpoint('https://staff.eideweb.com/simple-listing/birthdays/?api=fixture&embed_version=2&debug=1', false);
  assert.equal(url.searchParams.get('type'), 'sharepoint');
  assert.equal(url.searchParams.get('api'), 'fixture');
  assert.equal(url.searchParams.get('title'), '0');
  assert.equal(url.searchParams.get('embed_version'), '2');
  assert.equal(url.searchParams.has('debug'), false);
  for (const bad of ['', 'http://staff.eideweb.com/listing/a/', 'https://evil.example/listing/a/', 'https://staff.eideweb.com/wp-admin/', 'https://user:secret@staff.eideweb.com/listing/a/']) {
    assert.throws(() => listingEndpoint(bad, true));
  }
});
test('reject HTML/login pages and invalid JSON; do not retry 403', async () => {
  let calls = 0;
  let response = { ok: false, status: 403 };
  const { requestPayload } = load(apiFile, { URL, fetch: async () => { calls++; return response; } });
  const signal = new AbortController().signal;
  const run = () => requestPayload('https://staff.eideweb.com/listing/team/', true, signal);
  await assert.rejects(run, /HTTP 403/);
  assert.equal(calls, 1);
  response = { ok: true, headers: { get: () => 'text/html' } };
  await assert.rejects(run, /did not return JSON/);
  response = { ok: true, headers: { get: () => 'application/json' }, json: async () => ({ schemaVersion: 9 }) };
  await assert.rejects(run, /unsupported response/);
});
test('retry temporary failure then accept JSON without cookies or identity disclosure', async () => {
  let calls = 0;
  const payload = { schemaVersion: 1, listId: 2, listType: 'simple-listing', html: '', css: '' };
  const { requestPayload } = load(apiFile, { URL, window: { setTimeout: callback => callback() }, fetch: async (_, options) => {
    assert.equal(options.credentials, 'omit');
    assert.equal(options.method, 'GET');
    assert.equal(options.cache, 'no-store');
    calls++;
    return calls === 1 ? { status: 503 } : { ok: true, headers: { get: () => 'application/json' }, json: async () => payload };
  } });
  assert.equal(await requestPayload('https://staff.eideweb.com/listing/team/', true, new AbortController().signal), payload);
  assert.equal(calls, 2);
});
test('shared runtime isolates instances, filters, resets, expands simple lists, disposes overlays', () => {
  const dom = new JSDOM('<div id="one"></div><div id="two"></div>', { url: 'https://example.sharepoint.com' });
  const { window } = dom;
  let listeners = 0;
  window.matchMedia = () => ({ matches: true, addEventListener: () => listeners++, removeEventListener: () => listeners-- });
  const { createDirectoryRuntime } = load('src/runtime/directory.ts', { window, document: window.document, MutationObserver: window.MutationObserver });
  const runtime = createDirectoryRuntime();
  function fixture(id) {
    const root = window.document.getElementById(id).attachShadow({ mode: 'open' });
    root.innerHTML = `<style data-ve-staff></style><div id="veStaffList">
    <button data-ve-staff-filters-open>Filters</button><div class="ve-staff-filters"><input id="employeenamesearch"><button id="resetFilterBtn">Reset</button></div>
    <div class="employee-list"><div class="ve-row"><div class="employee-tile" data-employee-name="Alice"></div><div class="employee-tile" data-employee-name="Bob"></div></div></div>
    <img class="ve-lazy" data-src="https://staff.eideweb.com/photo.jpg"><div class="viewMoreWrapper"><button id="viewMoreBtn">More</button></div></div>`;
    runtime.initialize(root);
    return root;
  }
  const one = fixture('one'); const two = fixture('two');
  const photo = one.querySelector('img');
  assert.equal(photo.src, 'https://staff.eideweb.com/photo.jpg');
  photo.dispatchEvent(new window.Event('load'));
  assert.ok(photo.classList.contains('ve-lazy-loaded'));
  const input = one.querySelector('input'); input.value = 'alice'; input.dispatchEvent(new window.Event('input', { bubbles: true }));
  assert.equal(one.querySelectorAll('.employee-tile.ve-hidden').length, 1);
  assert.equal(two.querySelectorAll('.employee-tile.ve-hidden').length, 0);
  one.querySelector('#resetFilterBtn').click();
  assert.equal(one.querySelectorAll('.employee-tile.ve-hidden').length, 0);
  one.querySelector('.employee-tile').classList.add('ve-hidden');
  one.querySelector('#viewMoreBtn').click();
  assert.equal(one.querySelectorAll('.employee-tile.ve-hidden').length, 0);
  assert.ok(one.querySelector('.viewMoreWrapper.ve-hidden'));
  one.querySelector('[data-ve-staff-filters-open]').click();
  assert.equal(window.document.querySelectorAll('.ve-staff-filter-portal').length, 1);
  runtime.dispose(one); runtime.dispose(two);
  assert.equal(listeners, 0);
  assert.equal(window.document.querySelectorAll('.ve-staff-filter-portal').length, 0);
  dom.window.close();
});
test('contact details toggle independently and suggestions link to the source site', () => {
  const dom = new JSDOM('<div id="host"></div>');
  const root = dom.window.document.getElementById('host').attachShadow({ mode: 'open' });
  root.innerHTML = '<button data-toggle="collapse" data-target="#collapse1"></button><div id="collapse1" class="collapse"></div><button class="ve-suggest-edit-btn">Suggest Edit</button>';
  const { bindCardActions } = load('src/webparts/eideWebStaffIntegration/components/cardActions.ts', { document: dom.window.document, URL });
  const dispose = bindCardActions(root, 'https://staff.eideweb.com/listing/team/?type=embed');
  const button = root.querySelector('button');
  button.click();
  assert.equal(button.getAttribute('aria-expanded'), 'true');
  assert.ok(root.getElementById('collapse1').classList.contains('veshow'));
  button.click();
  assert.equal(button.getAttribute('aria-expanded'), 'false');
  assert.equal(root.querySelector('a').href, 'https://staff.eideweb.com/listing/team/');
  dispose(); dom.window.close();
});

test('list discovery follows pages, sends no credentials, and validates every URL', async () => {
  let calls = 0;
  const list = { id: 1, title: 'Team', url: 'https://staff.eideweb.com/listing/team/', listType: 'listing' };
  let body;
  const { requestLists } = load(apiFile, { URL, fetch: async (url, options) => {
    calls++;
    assert.equal(options.credentials, 'omit');
    assert.equal(options.redirect, 'error');
    assert.equal(new URL(url).searchParams.get('ve_staff_lists'), '1');
    return { ok: true, headers: { get: () => 'application/json' }, json: async () => body || ({schemaVersion: 1, lists: [list], nextPage: calls === 1 ? 2 : null}) };
  } });
  const signal = new AbortController().signal;
  assert.equal((await requestLists('listing', signal)).length, 2);
  assert.equal(calls, 2);
  for (const url of ['https://evil.example/listing/team/', 'https://staff.eideweb.com/simple-listing/team/']) {
    body = {schemaVersion: 1, lists: [{...list, url}], nextPage: null};
    await assert.rejects(() => requestLists('listing', signal));
  }
  body = {schemaVersion: 1, lists: [], nextPage: 1};
  await assert.rejects(() => requestLists('listing', signal), /Invalid list catalog/);
  body = {schemaVersion: 1, lists: [], nextPage: null};
  assert.equal((await requestLists('simple-listing', signal)).length, 0);
  await assert.rejects(() => requestLists('staff', signal), /valid list type/);
});

test('list discovery reports access denial and undeployed endpoint', async () => {
  let response = { ok: false, status: 403 };
  const { requestLists } = load(apiFile, { URL, fetch: async () => response });
  const run = () => requestLists('listing', new AbortController().signal);
  await assert.rejects(run, /HTTP 403/);
  response = {ok: true, headers: {get: () => 'text/html'}};
  await assert.rejects(run, /Deploy the updated WordPress plugin/);
});

test('explicit embed key survives list changes and cannot be sent to another origin', () => {
  const { withEmbedApiKey } = load(apiFile, { URL });
  for (const path of ['listing/team/', 'simple-listing/birthdays/']) {
    const url = new URL(withEmbedApiKey('https://staff.eideweb.com/' + path, ' fixture-key '));
    assert.equal(url.searchParams.get('api'), 'fixture-key');
    assert.equal(url.searchParams.get('type'), 'sharepoint');
  }
  const legacy = 'https://staff.eideweb.com/listing/team/?api=old&group=TRUE';
  assert.equal(withEmbedApiKey(legacy, ''), legacy);
  const updated = new URL(withEmbedApiKey(legacy, 'new'));
  assert.equal(updated.searchParams.get('api'), 'new');
  assert.equal(updated.searchParams.get('group'), 'TRUE');
  assert.throws(() => withEmbedApiKey('https://evil.example/listing/team/', 'fixture-key'));
});
