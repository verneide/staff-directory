# Vern Eide staff directory

This repository contains the `ve-staff` WordPress plugin, its companion `vern-eide-staff` theme, and a SharePoint Framework integration.

## Embed versions

Existing `?type=script` snippets continue to run v1. The post editor also presents a v2 snippet containing `data-version="2"`; it renders a skeleton immediately and AJAX-loads the listing into an isolated Shadow DOM. Listing responses include only the rendered directory and inline listing CSS, avoiding cross-origin requests for unrelated WordPress assets. V2 requests use the stable listing URL with browser cache bypassing and are treated like script embeds by the legacy referrer access check. Public v2 embed responses allow all consumer origins by default. Sites that need to restrict consumers can return an explicit origin list through `ve_staff_v2_allowed_origins`.

V1 script responses are explicitly non-cacheable and all locally hosted listing scripts and styles carry their file modification time in a `ver` query parameter. Deploying a changed asset therefore produces a new URL, so SharePoint and the browser retrieve it without an end user clearing browser history. The generated v2 loader snippet uses the same versioning strategy; copy the refreshed snippet into SharePoint after changing the loader itself.

V1 and v2 embeds use the script access rules, while an external referrer remains available for URL allow-list validation. Listing behavior is initialized on DOM readiness rather than the host page's `load` event, so legacy scripts still initialize when an external site defers their execution until after the page has loaded.

## SharePoint

The production-oriented SPFx 1.18 sample is under `sharepoint/eide-web-staff-integration`. Set its `apiEndpoint` and optional `entraResource` web-part properties, update the tenant URL in `config/package-solution.json`, then run `npm install`, `gulp bundle --ship`, and `gulp package-solution --ship`. See that directory's README for Entra ID permission setup.

## CSS asset maintenance

Application stylesheets have tracked minified counterparts because production serves those files through SiteGround Optimizer. Whenever a source stylesheet changes, minify the complete source and commit the updated minified file in the same change. The required source-to-output mappings are maintained in [`AGENTS.md`](AGENTS.md). Vendor CSS is excluded unless the vendor asset itself is intentionally upgraded.
