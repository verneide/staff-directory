# Eide Staff for SharePoint Online

An SPFx 1.18.2 web part that renders the existing Staff List and Simple List views directly inside a Shadow DOM, with no iframe. `supportsFullBleed: true` enables placement in a full-width section on a **communication site** modern page. Team sites do not offer that section type; the part fills its available column there.

## How it works

Paste an existing `https://staff.eideweb.com/listing/<slug>/` or `https://staff.eideweb.com/simple-listing/<slug>/` URL into the property pane. Preserve any existing query parameters required by that list. The part requests it with `type=sharepoint` and receives a versioned JSON **view payload**:

```json
{"schemaVersion":1,"listId":123,"listType":"listing","html":"...","css":"..."}
```

The API deliberately uses the site's card templates rather than exposing all raw employee fields or duplicating date, visibility, location, tag, or naming rules in React. The web part sanitizes the HTML, creates the DOM and stylesheet, and initializes bundled directory behavior. It never executes scripts from the response. No SharePoint user name or email is sent.

Regular lists support name/extension searches, department/location/tag filters, reset, mobile filters, expandable contact details, biographies, and contact links. Simple Lists retain their server-defined selection and View More behavior. The repository has explicit birthday and new/promotion modes; any anniversary view must already be configured/rendered by the site's Simple List implementation. This integration does not invent a new anniversary query mode. Suggest Edit opens the original WordPress listing in a new tab because that form is a separate write workflow.

Each load fetches current `listing.css`, the list's `embed_custom_css`, and template output. Thus CSS, card markup and list configuration changes are reflected on the next page load or retry without repackaging. Existing WordPress staff-data transient caches still apply. Shared interaction JavaScript is bundled from theme source at build time; changes to interaction logic require a new `.sppkg`. There is no automatic polling while a page remains open.

## Install

1. Deploy the plugin/theme changes from this branch using the existing hosting workflow. Both must be deployed together.
2. The API is configured for `https://verneide.sharepoint.com`, including the supplied home page `https://verneide.sharepoint.com/SitePages/Home.aspx`. CORS uses only the origin, not `/SitePages/Home.aspx`. No separate configuration file is required for this tenant. To override the origin list later, copy `server/sharepoint-origins.example.php` into `wp-content/mu-plugins/ve-staff-sharepoint-origins.php` and edit its explicit HTTPS origins; wildcards are not accepted. That optional configuration is outside the theme/plugin sync destinations.
3. The repository's existing allowed URL list already includes `verneide.sharepoint.com`, covering **script** embed referrer checks. Preserve that entry if editing the site's access settings. The SharePoint path uses the browser's Origin header for that check, so a missing Referer does not randomly block it.
4. Exclude `type=sharepoint` responses from SiteGround/CDN/full-page caches and permit these GET/OPTIONS requests through any WAF. PHP sends `private, no-store` and `Vary: Origin`, but an upstream cache must not serve an old page before PHP runs.
5. Use **Node 18.20.8** (the existing SPFx 1.18.2 toolchain requires Node 18):

   ```sh
   cd sharepoint/eide-web-staff-integration
   npm ci
   npm test
   npm run package
   ```

6. Upload `sharepoint/solution/eide-web-staff-integration.sppkg` from this project to the SharePoint tenant App Catalog and deploy it. No placeholder Entra API permission request is included.
7. Edit a communication-site page, add a **Full-width section**, insert **Eide Staff**, and paste the list URL in the property pane. Choose whether to show the list title. Add separate instances for regular, birthday, or other Simple Lists.
8. Verify as an ordinary reader: internal/public fields, images, mobile filters, biographies, contact details, two parts on one page, View More, and style changes after reload. A real tenant test is still required; local tests cannot validate tenant CSP or WAF settings.

## Access model

This preserves the site's existing embed access model and internal/public selection rules. A legacy `api` parameter in an existing embed URL remains a browser-visible value; it is **not a secret** and is not upgraded into user authentication by this web part. Do not place WordPress application passwords, Graph client secrets, or other service credentials in properties.

Exact-origin CORS controls which browser origins can read responses. It does not authenticate staff users: non-browser clients can spoof Origin. If directory content must require a signed-in Entra user, deploy a server-side token-validating API/proxy with issuer/audience/signature/expiry/scope validation before enabling this endpoint for such content. The old sample's optional token request did not implement that server validation and is not advertised as working authentication here. Requests omit WordPress cookies and do not depend on third-party-cookie access.

Removing the iframe avoids iframe width and frame-embedding restrictions. It does not bypass firewall, DNS, CSP, CORS, or service availability restrictions. Errors show an actionable message and Retry; requests time out after 30 seconds. Only temporary HTTP 429/5xx responses are retried automatically.

## Repository and deployment isolation

All SPFx source, dependency lockfile, tests, and example configuration live here. The hosting workflow only rsyncs `assets/plugins/ve-staff/` and `assets/themes/vern-eide-staff/`. Its path filters also prevent a SharePoint-only commit from triggering hosting deployment. Generated bundles, runtime copies, node_modules, and `.sppkg` output are ignored by Git. The separate SharePoint validation workflow builds an installable package artifact without deploying it.

`npm run prepare:runtime` bundles the existing theme's lazy-image and directory code into an instance-safe runtime. No global document initialization runs in SharePoint. Disposal removes filter portals, media listeners, and observers when a part unmounts. DOM tests cover instance isolation, filter/reset/View More behavior, contact expansion, disposal, request restrictions, response validation, and retries.
