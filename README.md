# Vern Eide staff directory

This repository contains the `ve-staff` WordPress plugin, its companion `vern-eide-staff` theme, and a SharePoint Framework integration.

## Embed versions

Existing `?type=script` snippets continue to run v1. The post editor also presents a v2 snippet containing `data-version="2"`; it renders a skeleton immediately and AJAX-loads the listing into an isolated Shadow DOM. Listing responses include only the rendered directory and inline listing CSS, avoiding cross-origin requests for unrelated WordPress assets. V2 requests use the stable listing URL with browser cache bypassing and are treated like script embeds by the legacy referrer access check. Public v2 embed responses allow all consumer origins by default. Sites that need to restrict consumers can return an explicit origin list through `ve_staff_v2_allowed_origins`.

V1 script responses are explicitly non-cacheable and all locally hosted listing scripts and styles carry their file modification time in a `ver` query parameter. Deploying a changed asset therefore produces a new URL, so SharePoint and the browser retrieve it without an end user clearing browser history. The generated v2 loader snippet uses the same versioning strategy; copy the refreshed snippet into SharePoint after changing the loader itself.

V1 and v2 embeds use the script access rules, while an external referrer remains available for URL allow-list validation. Listing behavior is initialized on DOM readiness rather than the host page's `load` event, so legacy scripts still initialize when an external site defers their execution until after the page has loaded.

## SharePoint

The production-oriented SPFx 1.18 sample is under `sharepoint/eide-web-staff-integration`. Set its `apiEndpoint` and optional `entraResource` web-part properties, update the tenant URL in `config/package-solution.json`, then run `npm install`, `gulp bundle --ship`, and `gulp package-solution --ship`. See that directory's README for Entra ID permission setup.

## Generated asset maintenance

Application stylesheets and JavaScript files have tracked minified counterparts because production serves those files through SiteGround Optimizer. Make changes to the source assets without manually updating the generated files. The deployment workflow minifies every complete application source into its mapped output immediately before uploading the plugin and theme. The required source-to-output mappings are maintained in [`AGENTS.md`](AGENTS.md). Vendor assets are excluded unless the vendor source itself is intentionally upgraded.

The theme registers `listing-css.min.css` as the public staff-listing stylesheet and versions its URL with that file's modification time. Deployment regenerates it from the complete `listing.css` source so the frontend receives current listing styles without relying on an optimizer-generated copy.

SiteGround SSH setup and the combined plugin/theme upload are retried up to three times with three-minute delays. If every attempt fails, the workflow creates a repository issue linking to the failed run so the failure is visible and actionable.

## Microsoft Azure staff synchronization

The plugin includes an optional Microsoft Graph integration under **Staff → Azure sync**. It polls the Graph users delta endpoint from the last saved delta link, accepts Graph change notifications at `/wp-json/ve-staff/v1/azure/webhook`, and sends mapped staff changes (including profile photos) back to Graph. ACF, grouped ACF, and taxonomy targets can be configured in a row-based editor with ordered transformation rules and an explicit source of truth for every field. For example, setting mobile phone to **WordPress → Azure** ensures an Azure phone value can never overwrite the site value.

Connection fields on the Azure sync screen save individually through authenticated AJAX when their value changes and the field loses focus. The main save button remains responsible for operation toggles, mappings, and transformation rules.

Keep mock mode enabled while validating mappings. The settings screen also provides a connection and permission test plus a staff dry-run that fetches current Graph data and displays exactly which destination values would change without writing to either system. Production and mock activity appears on each staff post, and the full log is automatically retained for 90 days.

Create a single-tenant Microsoft Entra app registration and use its directory ID, application ID, and client-secret **value** on the settings screen. No Redirect URI is needed because the integration uses the OAuth client credentials flow rather than browser sign-in. Grant tenant admin consent only for the Microsoft Graph **application** permissions the integration needs: `User.Read.All` enables Azure-to-WordPress user sync, `User.ReadWrite.All` enables WordPress-to-Azure user-field updates, and `ProfilePhoto.ReadWrite.All` enables WordPress-to-Azure photo updates. The connection test stores the granted permissions and reports each available capability. Permissions are rechecked at least daily while synchronization is enabled; operations without their required permission are skipped and logged without disabling permitted sync directions. Webhooks are optional; when used, configure the displayed REST URL and the same random client-state secret in the Graph subscription.
