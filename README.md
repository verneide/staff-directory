# Vern Eide staff directory

This repository contains the `ve-staff` WordPress plugin, its companion `vern-eide-staff` theme, and a SharePoint Framework integration.

## Embed versions

Existing `?type=script` snippets continue to run v1. The post editor also presents a v2 snippet containing `data-version="2"`; it renders a skeleton immediately and AJAX-loads the listing into an isolated Shadow DOM. Public v2 embed responses allow all consumer origins by default. Sites that need to restrict consumers can return an explicit origin list through `ve_staff_v2_allowed_origins`.

## SharePoint

The production-oriented SPFx 1.18 sample is under `sharepoint/eide-web-staff-integration`. Set its `apiEndpoint` and optional `entraResource` web-part properties, update the tenant URL in `config/package-solution.json`, then run `npm install`, `gulp bundle --ship`, and `gulp package-solution --ship`. See that directory's README for Entra ID permission setup.

## CSS asset maintenance

Application stylesheets have tracked minified counterparts because production serves those files through SiteGround Optimizer. Whenever a source stylesheet changes, minify the complete source and commit the updated minified file in the same change. The required source-to-output mappings are maintained in [`AGENTS.md`](AGENTS.md). Vendor CSS is excluded unless the vendor asset itself is intentionally upgraded.
