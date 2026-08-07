# Repository instructions

## CSS assets

- Whenever a non-minified CSS source in the WordPress theme or plugin is changed, regenerate and commit its existing minified counterpart in the same change. Never leave a tracked `*.min.css` counterpart out of sync with its source.
- Use these application stylesheet mappings:
  - `assets/themes/vern-eide-staff/inc/assets/css/listing.css` → `assets/themes/vern-eide-staff/inc/assets/css/listing-css.min.css`
  - `assets/themes/vern-eide-staff/inc/assets/css/listing-display.css` → `assets/themes/vern-eide-staff/inc/assets/css/ve-staff-display.min.css`
  - `assets/themes/vern-eide-staff/style.css` → `assets/themes/vern-eide-staff/wp-bootstrap-starter-style.min.css`
  - `assets/plugins/ve-staff/public/css/ve-staff-public.css` → `assets/plugins/ve-staff/public/css/ve-staff.min.css`
- Minify the complete source file and overwrite the mapped minified file; do not manually patch generated CSS.
- Do not regenerate third-party or vendor minified stylesheets unless their corresponding vendor source is intentionally upgraded.
