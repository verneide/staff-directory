# EideWeb Staff Directory Integration

An SPFx 1.18.2 web part that renders the existing Staff List and Simple List views directly inside a Shadow DOM, with no iframe. `supportsFullBleed: true` enables placement in a full-width section on a **communication site** modern page. Team sites do not offer that section type; the part fills its available column there.

## App Catalog metadata

- **Name:** EideWeb Staff Directory Integration
- **Publisher:** Vern Eide Marketing
- **Category:** Collaboration
- **Short description:** Find and connect with Vern Eide staff through searchable directories and Simple Lists in SharePoint.
- **Description:** Help teams find and connect with Vern Eide colleagues directly in SharePoint. Display searchable Staff Lists and Simple Lists, including birthdays and new or promoted employees, using the list settings managed at staff.eideweb.com. Supports full-width communication-site sections, staff photos, contact details, filters, and current directory styles without an iframe.
- **Icon:** [Vern Eide secondary black logo](https://vern-eide.s3.amazonaws.com/cdn/logos/VE_Secondary_Black_1x1_250px.png). The web part picker references this URL. `npm run package` downloads and validates the original PNG, resizes it to the required **96 × 96 pixels**, then includes it in the package as `assets/ve-staff-icon-v2.png` for the App Catalog. Packaging requires HTTPS access to the logo host and fails if the logo cannot be downloaded; it does not silently publish a missing icon. The downloaded copy is generated output, excluded from Git and hosting sync.

Replace and deploy the rebuilt `2.2.0.0` package in the App Catalog to apply these metadata changes. They do not update an already uploaded package automatically.

## Dropdown setup

Deploy the updated WordPress plugin before installing package 2.2.0.0. Edit the web part, choose **Staff List** or **Simple List**, then choose a named list. **Refresh lists** reloads published choices from WordPress. No credentials are required. Existing saved URLs remain usable; **Advanced → Manual list URL** preserves custom embed parameters. Selecting a different list uses its canonical URL and does not copy internal embed parameters from the previous list.

The catalog uses `/?type=sharepoint&ve_staff_lists=1&list_type=listing&page=1` (or `simple-listing`) with the existing exact-origin and embed checks. It returns only published, non-password-protected list IDs, titles, types and URLs, with pages of 100. Exclude these requests from page caching alongside list-view requests. This retains the existing embed access model; it does not add Microsoft 365 authentication.

## How it works

Select a named list in the property pane, or enter an existing `https://staff.eideweb.com/listing/<slug>/` or `https://staff.eideweb.com/simple-listing/<slug>/` URL under Advanced. Preserve any existing query parameters required by that list. The part requests it with `type=sharepoint` and receives a versioned JSON **view payload**:

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
5. Install Python 3 with Pillow for app-icon resizing. Use **Node 18.20.8** (the existing SPFx 1.18.2 toolchain requires Node 18):

   ```sh
   cd sharepoint/eide-web-staff-integration
   python3 -m pip install Pillow==12.0.0
   npm ci
   npm test
   npm run package
   ```

6. Upload `sharepoint/solution/eide-web-staff-integration.sppkg` from this project to the SharePoint tenant App Catalog and deploy it. For an existing installation, replace the previous package with version `2.2.0.0` and deploy the update. The solution and web part IDs remain the same, so this updates the existing app. No placeholder Entra API permission request is included.
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
