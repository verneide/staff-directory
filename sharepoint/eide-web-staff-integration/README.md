# EideWeb Staff Directory Integration

An SPFx 1.18.2 web part that renders the existing Staff List and Simple List views directly inside a Shadow DOM, with no iframe. `supportsFullBleed: true` enables placement in a full-width section on a **communication site** modern page. Team sites do not offer that section type; the part fills its available column there.

## App Catalog metadata

- **Name:** EideWeb Staff Directory Integration
- **Publisher:** Vern Eide Marketing
- **Category:** Collaboration
- **Short description:** Find and connect with Vern Eide staff through searchable directories and Simple Lists in SharePoint.
- **Description:** Help teams find and connect with Vern Eide colleagues directly in SharePoint. Display searchable Staff Lists and Simple Lists, including birthdays and new or promoted employees, using the list settings managed at staff.eideweb.com. Supports full-width communication-site sections, staff photos, contact details, filters, and current directory styles without an iframe.
- **Icon:** [Vern Eide secondary black logo](https://vern-eide.s3.amazonaws.com/cdn/logos/VE_Secondary_Black_1x1_250px.png). The web part picker references this URL. `npm run package` downloads and validates the original PNG, resizes it to the required **96 × 96 pixels**, then includes it in the package as `assets/ve-staff-icon-v2.png` for the App Catalog. Packaging requires HTTPS access to the logo host and fails if the logo cannot be downloaded; it does not silently publish a missing icon. The downloaded copy is generated output, excluded from Git and hosting sync.

Replace and deploy the rebuilt `2.3.0.0` package in the App Catalog to apply these metadata changes. They do not update an already uploaded package automatically.

## Dropdown setup

Deploy the updated WordPress plugin before installing package 2.3.0.0. Edit the web part, choose **Staff List** or **Simple List**, then choose a named list. **Refresh lists** reloads published choices from WordPress. No WordPress credentials are required. For internal content, enable **Internal Page** in WordPress. Requests from the approved SharePoint tenant automatically select internal output; the existing **Internal-list embed API key** remains an alternative. Existing saved URLs remain usable; **Advanced → Manual list URL** preserves custom embed parameters. The embed key setting is applied when switching lists. Existing saved URLs migrate their `api` value into this setting. The explicit key overrides an `api` value in a manual URL; when the field is blank, any parameter already in the manual URL is retained. Remove it from both places to stop requesting internal content.

The catalog uses `/?type=sharepoint&ve_staff_lists=1&list_type=listing&page=1` (or `simple-listing`) with the existing exact-origin and embed checks. It returns only published, non-password-protected list IDs, titles, types and URLs, with pages of 100. Exclude these requests from page caching alongside list-view requests. This retains the existing embed access model; it does not add Microsoft 365 authentication.

## How it works

Select a named list in the property pane, or enter an existing `https://staff.eideweb.com/listing/<slug>/` or `https://staff.eideweb.com/simple-listing/<slug>/` URL under Advanced. Preserve any existing query parameters required by that list. The part requests it with `type=sharepoint` and receives a versioned JSON **view payload**:

```json
{"schemaVersion":1,"listId":123,"listType":"listing","html":"...","css":"..."}
```

The API deliberately uses the site's card templates rather than exposing all raw employee fields or duplicating date, visibility, location, tag, or naming rules in React. The web part sanitizes the HTML, creates the DOM and stylesheet, and initializes bundled directory behavior. It never executes scripts from the response. No SharePoint user name or email is sent.

Regular lists support name/extension searches, department/location/tag filters, reset, full-screen filters with removable active-filter pills, expandable contact details, biographies, and contact links. Simple Lists retain their server-defined selection and View More behavior. The repository has explicit birthday and new/promotion modes; any anniversary view must already be configured/rendered by the site's Simple List implementation. This integration does not invent a new anniversary query mode. Suggest an Edit opens an on-page modal, prepopulates the employee’s current values, and submits proposed changes to the existing WordPress review queue. It does not directly update employee records. The form requires a published internal list and a valid WordPress nonce; cross-origin submissions are limited to approved embed origins.

Each load fetches current `listing.css`, the list's `embed_custom_css`, and template output. Thus CSS, card markup and list configuration changes are reflected on the next page load or retry without repackaging. Existing WordPress staff-data transient caches still apply. Shared interaction JavaScript is bundled from theme source at build time; changes to interaction logic require a new `.sppkg`. There is no automatic polling while a page remains open.

## Install

1. Deploy the plugin/theme changes from this branch using the existing hosting workflow. Both must be deployed together.
2. The API is configured for `https://verneide.sharepoint.com`, including the supplied home page `https://verneide.sharepoint.com/SitePages/Home.aspx`. CORS uses only the origin, not `/SitePages/Home.aspx`. No separate configuration file is required for this tenant. To override the origin list later, copy `server/sharepoint-origins.example.php` into `wp-content/mu-plugins/ve-staff-sharepoint-origins.php` and edit its explicit HTTPS origins; wildcards are not accepted. That optional configuration is outside the theme/plugin sync destinations.
3. The repository's existing allowed URL list already includes `verneide.sharepoint.com`, covering **script** embed referrer checks. Preserve that entry if editing the site's access settings. The SharePoint path uses the browser's Origin header for that check, so a missing Referer does not randomly block it.
4. Exclude `type=sharepoint` responses from SiteGround/CDN/full-page caches and permit these GET/OPTIONS requests through any WAF. PHP sends `private, no-store` and `Vary: Origin`, but an upstream cache must not serve an old page before PHP runs.
5. Install Python 3 with Pillow for app-icon resizing. Use **Node 18.20.8** (the existing SPFx 1.18.2 toolchain requires Node 18):

   ```sh
   cd sharepoint/eide-web-staff-integration
   python3 -m pip install Pillow==12.0.0
   npm ci
   npm test
   npm run package
   ```

6. Upload `sharepoint/solution/eide-web-staff-integration.sppkg` from this project to the SharePoint tenant App Catalog and deploy it. For an existing installation, replace the previous package with version `2.3.0.0` and deploy the update. The solution and web part IDs remain the same, so this updates the existing app. No placeholder Entra API permission request is included.
7. Edit a communication-site page, add a **Full-width section**, insert **EideWeb Staff Directory Integration**, and choose the list type and named list in the property pane. Choose whether to show the list title. Add separate instances for regular, birthday, or other Simple Lists.
8. Verify as an ordinary reader: internal/public fields, images, mobile filters, biographies, contact details, two parts on one page, View More, and style changes after reload. A real tenant test is still required; local tests cannot validate tenant CSP or WAF settings.

## Access model

This preserves the site's existing embed access model and internal/public selection rules. A legacy `api` parameter in an existing embed URL remains a browser-visible value; it is **not a secret** and is not upgraded into user authentication by this web part. Do not place WordPress application passwords, Graph client secrets, or other service credentials in properties.

Exact-origin CORS controls which browser origins can read responses. It does not authenticate staff users: non-browser clients can spoof Origin. If directory content must require a signed-in Entra user, deploy a server-side token-validating API/proxy with issuer/audience/signature/expiry/scope validation before enabling this endpoint for such content. The old sample's optional token request did not implement that server validation and is not advertised as working authentication here. Requests omit WordPress cookies and do not depend on third-party-cookie access.

Removing the iframe avoids iframe width and frame-embedding restrictions. It does not bypass firewall, DNS, CSP, CORS, or service availability restrictions. Errors show an actionable message and Retry; requests time out after 30 seconds. Only temporary HTTP 429/5xx responses are retried automatically.

## Repository and deployment isolation

All SPFx source, dependency lockfile, tests, and example configuration live here. The hosting workflow only rsyncs `assets/plugins/ve-staff/` and `assets/themes/vern-eide-staff/`. Its path filters also prevent a SharePoint-only commit from triggering hosting deployment. Generated bundles, runtime copies, node_modules, and `.sppkg` output are ignored by Git. The separate SharePoint validation workflow builds an installable package artifact without deploying it.

`npm run prepare:runtime` bundles the existing theme's lazy-image and directory code into an instance-safe runtime. No global document initialization runs in SharePoint. Disposal removes filter portals, media listeners, and observers when a part unmounts. DOM tests cover instance isolation, filter/reset/View More behavior, contact expansion, disposal, request restrictions, response validation, and retries.

## Package identity and validation

`solution.name` is the stable machine identifier. Use `solution.title` for the display name with spaces; the web part picker has its own manifest title. Changing branding must not change the solution or component GUIDs.

CI opens the generated `.sppkg` and checks its actual manifest version, identity, display title, XML, relationships, icon, and client bundle before publishing. Artifacts include the verified version in their name and a `package-verification.json` report. Upload only the `.sppkg` inside the artifact ZIP. If SharePoint reports Disabled or an unexpected version after replacement, inspect the App Catalog's **App Package Error Message** field before deleting or reinstalling the existing app; build validation cannot replace tenant acceptance testing.

## Connection troubleshooting and native rendering

A browser “Failed to fetch” does not identify one server error: CORS, a redirect (requests intentionally reject redirects), CSP `connect-src`, TLS, a network failure or a firewall may all produce it. Inspect the browser Console and Network request to `staff.eideweb.com` from the actual SharePoint page. Confirm the plugin/theme deployment, exact allowed SharePoint origin, JSON Content-Type, and an `Access-Control-Allow-Origin` matching that origin. Exclude all `type=sharepoint` requests from hosting/CDN page caching, including the root catalog query. A valid embed key changes internal visibility; it cannot solve a failed network request.

Do not paste an entire script snippet into the web part. It requests `type=sharepoint` JSON from the same Staff List/Simple List templates, sanitizes their HTML, applies their CSS in a Shadow DOM, and runs the bundled directory interaction code. The legacy `type=script` mode writes into a normal page and is not executed as a remote script inside SharePoint. This keeps the site's list selection and internal visibility rules while avoiding iframe layout restrictions.

## V2 and origin diagnostics

Version 2.3.0.0 explicitly requests `type=sharepoint&embed_version=2`. Both Staff Lists and Simple Lists share their v2 HTML/CSS builder with the SharePoint JSON endpoint (`embedVersion: 2`). The web part runs bundled directory behavior instead of injecting the standalone v2 script, which initializes a normal document. Deploy the plugin and theme changes together before updating the package.

Opening a catalog URL in the address bar generally sends no `Origin` header and returns `missing_origin`. This is expected and does not test the SharePoint connection. Inspect the request made by the web part: its Origin must be `https://verneide.sharepoint.com`. `origin_not_allowed` means the received Origin did not match the configured list; inspect any MU-plugin overriding `ve_staff_sharepoint_allowed_origins`. Keep exact HTTPS origins, without a trailing slash or page path. Never disable this check to make address-bar navigation succeed. If the actual web part request has the correct Origin but the response is stale or missing CORS headers, check hosting/CDN caching and whether a proxy strips Origin.

## Internal lists in SharePoint

For lists with `internal_page` (Staff Lists) or `simple_internal_page` (Simple Lists) enabled, a `type=sharepoint` request from the exact configured SharePoint origin now selects internal output without an embed key. The existing embed key and privileged WordPress login remain alternatives. Public lists stay public. This is the requested browser-origin embed policy, not Entra authentication; non-browser clients can forge Origin. The existing page-access checks still run first.

The parent templates now share their computed internal flag, list ID, query settings and card options with nested template parts. Deploy the plugin and both templates together, then reload the SharePoint page. No new SPPKG is required for this change; the existing optional embed-key field remains supported.

## Interaction refresh (2.3.0.0)

Deploy both plugin and theme updates, then replace and deploy the 2.3.0.0 SPPKG from the validation workflow artifact. Reload existing pages to receive the new behavior. Native, script and v2 embeds use the shared theme interaction code; SharePoint bundles that same code. Full-screen filter dialogs retain selections when closed. Remove an individual filter pill to clear that field and immediately recalculate the cards, or use Reset to clear every field. Reset and Close Filters share dimensions. Soft shadows and inset fields provide a restrained neumorphic appearance with visible keyboard focus and wrapping contact buttons.

Suggestion submissions POST to `/wp-admin/admin-ajax.php?action=ve_staff_suggest_edit`. Permit this endpoint through the hosting WAF and the embedding site's connect-src policy. The form supplies its nonce and published internal list ID; no WordPress application password is needed. On an expired security token, reload the directory. Submitted edits continue through the existing administrator review and notification workflow.

## Responsive controls and compact layout (2.4.0.0)

Deploy the theme source update and replace/deploy the 2.4.0.0 SPPKG. Name and the existing internal Extension field stay visible in the toolbar. More filters opens a large centered modal on desktop (768px and up), and fills the viewport on mobile. Job title, phone and email filters are available on cards with internal contact details and search only values already included by the server. Phone search ignores punctuation. All criteria combine; Reset and removable pills update the same fields.

Cards / Compact switches layout within each web part without clearing filters. Compact uses circular photos, details to the right and the existing contact action at the right; it shows one column below 760px of directory width, two at 760px and three at 1200px. It keeps the original Simple List selection and does not add contact details to views that omit them. Font Awesome Free icons (https://fontawesome.com/license/free, CC BY 4.0) are embedded as SVG so contact labels render consistently inside Shadow DOM without an external icon font. Contact labels are muted above values, with shorter wrapping action buttons.

## Compact layout correction (2.4.1.0)

Deploy the theme update and replace/deploy the 2.4.1.0 SPPKG. Compact mode has an independent width variable so standard five-column card rules cannot override it: one column below 760px, two from 760px, three from 1800px of directory width. The employee title has no maximum width and the name has no bottom padding. Title, location, extension and email form a wrapping desktop summary and stack on mobile. Extension and email remain in the expanded contact panel, whose values form two columns on desktop and one on mobile. The compact contact action is an icon with a staff-specific accessible name; regular Cards mode keeps its text label. Icon and selected-layout colors use the original charcoal (#424242) and secondary gray (#808080), overridable through --ve-directory-primary and --ve-directory-secondary.
