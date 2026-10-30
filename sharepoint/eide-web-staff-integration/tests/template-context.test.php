<?php
// Exercise the actual parent templates in function scope, as WordPress load_template does.
function add_action(...$args) {}
function wp_next_scheduled(...$args) { return true; }
function apply_filters($name, $value) { return $value; }
function sanitize_text_field($value) { return $value; }
function wp_unslash($value) { return $value; }
function is_user_logged_in() { return false; }
function current_user_can($capability) { return false; }
function get_the_ID() { return 42; }
function the_title(...$args) { return '<h1>Fixture</h1>'; }
function get_terms(...$args) { return array(7); }
function get_field($name, $context = null) {
    if ($context === 'option') return array();
    if (in_array($name, array('internal_page', 'simple_internal_page'), true)) return $GLOBALS['fixture_internal'];
    if (strpos($name, 'locations') !== false) return array(7);
    if (strpos($name, 'show_') !== false) return true;
    if ($name === 'simple_listing_type') return 'birthday';
    return false;
}
function wp_send_json($body, $status=null) { throw new Error('Unexpected JSON error'); }
class TemplateProbe extends Exception {}
function get_template_part($slug, $name = null, $args = array()) {
    $want = $GLOBALS['fixture_internal'];
    if ($GLOBALS['internal'] !== $want || $GLOBALS['notvisible'] !== ($want ? 0 : 1)) throw new Error('Internal selection did not reach query template');
    if ($GLOBALS['listid'] !== 42 || $GLOBALS['listinglocations'] !== array(7)) throw new Error('Query lost list settings');
    if (strpos($slug, $want ? '-internal' : '-external') === false) throw new Error('Wrong output template');
    if (strpos($slug, '/list-output') !== false) {
        foreach (array('showfilters', 'showtitles', 'showbios', 'showfullname', 'showloclogo') as $setting) {
            if ($GLOBALS[$setting] !== true) throw new Error('Card option not shared: ' . $setting);
        }
    } elseif ($GLOBALS['birthdaylist'] !== true) throw new Error('Birthday settings not shared');
    throw new TemplateProbe('Validated query context');
}
require __DIR__ . '/../../../assets/plugins/ve-staff/public/partials/ve-staff-public-functions.php';
function load_parent($file) { include $file; }
$_GET = array('type' => 'sharepoint', 'embed_version' => '2');
$_SERVER['HTTP_ORIGIN'] = 'https://verneide.sharepoint.com';
foreach (array('single-listing.php', 'single-simple-listing.php') as $template) {
    foreach (array(true, false) as $flag) {
        $GLOBALS['fixture_internal'] = $flag;
        try { load_parent(__DIR__ . '/../../../assets/themes/vern-eide-staff/' . $template); throw new Error('Template did not reach query'); }
        catch (TemplateProbe $e) {}
    }
}
echo "Parent template internal/public context checks passed\n";
