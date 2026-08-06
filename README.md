# Vern Eide staff directory

This repository contains the `ve-staff` WordPress plugin, its companion `vern-eide-staff` theme, and a SharePoint Framework integration.

## Embed versions

Existing `?type=script` snippets continue to run v1. The post editor also presents a v2 snippet containing `data-version="2"`; it renders a skeleton immediately and AJAX-loads the listing into an isolated Shadow DOM. Configure permitted consumer origins through `ve_staff_v2_allowed_origins`.

## SharePoint

The production-oriented SPFx 1.18 sample is under `sharepoint/eide-web-staff-integration`. Set its `apiEndpoint` and optional `entraResource` web-part properties, update the tenant URL in `config/package-solution.json`, then run `npm install`, `gulp bundle --ship`, and `gulp package-solution --ship`. See that directory's README for Entra ID permission setup.
