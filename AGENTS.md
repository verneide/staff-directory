# Repository instructions

## Generated assets

- Edit and commit application CSS and JavaScript source files only. Do not manually update or commit their minified counterparts; the deploy workflow regenerates them immediately before upload.
- Use these application stylesheet mappings:
  - `assets/themes/vern-eide-staff/inc/assets/css/listing.css` → `assets/themes/vern-eide-staff/inc/assets/css/listing-css.min.css`
  - `assets/themes/vern-eide-staff/inc/assets/css/listing-display.css` → `assets/themes/vern-eide-staff/inc/assets/css/ve-staff-display.min.css`
  - `assets/themes/vern-eide-staff/style.css` → `assets/themes/vern-eide-staff/wp-bootstrap-starter-style.min.css`
  - `assets/plugins/ve-staff/public/css/ve-staff-public.css` → `assets/plugins/ve-staff/public/css/ve-staff.min.css`
- The deploy workflow must minify each complete source file and overwrite its mapped minified file; do not manually patch generated assets.
- Use these application JavaScript mappings:
  - `assets/themes/vern-eide-staff/inc/assets/js/theme-script.js` → `assets/themes/vern-eide-staff/inc/assets/js/theme-script.min.js`
  - `assets/themes/vern-eide-staff/inc/assets/js/skip-link-focus-fix.js` → `assets/themes/vern-eide-staff/inc/assets/js/skip-link-focus-fix.min.js`
- Do not regenerate third-party or vendor minified stylesheets unless their corresponding vendor source is intentionally upgraded.
