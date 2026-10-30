<?php
// Exercise the production request guards without WordPress, mail or a database.
function wp_parse_url($s) { return parse_url($s); }
function home_url($s) { return 'https://staff.eideweb.com/'; }
function apply_filters($name, $value) { return $value; }
function get_template_directory() { return __DIR__ . '/../../../assets/themes/vern-eide-staff'; }
function ve_staff_sharepoint_origin_allowed($origin) { return $origin === 'https://verneide.sharepoint.com'; }
function wp_send_json_error($data, $status) { throw new Exception($data['message'], $status); }
function nocache_headers() {}
function sanitize_text_field($s) { return $s; }
function wp_unslash($s) { return $s; }
function wp_verify_nonce($s, $action) { return $s === 'fixture' && $action === 've_staff_suggest_edit'; }
function absint($s) { return abs((int)$s); }
function get_post_type($id) { return $id === 12 ? 'listing' : 'staff'; }
function get_field($field, $id) { return $GLOBALS['internal']; }
function get_post_status($id) { return $GLOBALS['published'] ? 'publish' : 'draft'; }
function sanitize_email($s) { return $s; }
function is_email($s) { return filter_var($s, FILTER_VALIDATE_EMAIL); }
function ve_staff_suggest_edit_allowed_fields() { return array('title' => 'Title'); }
// Extract only the two handlers; loading theme bootstrapping needs WordPress.
$source = file_get_contents(__DIR__ . '/../../../assets/themes/vern-eide-staff/functions.php');
$start = strpos($source, 'function ve_staff_suggest_edit_cors()');
$end = strpos($source, "add_action('wp_ajax_ve_staff_suggest_edit'", $start);
if ($start === false || $end === false) throw new Error('Handlers not found');
eval(substr($source, $start, $end - $start));
function rejected($expected, $message) {
    try { ve_staff_handle_suggest_edit_submission(); throw new Error('Submission unexpectedly continued'); }
    catch (Exception $e) { if ($e->getCode() !== $expected || strpos($e->getMessage(), $message) === false) throw $e; }
}
$_SERVER = array('HTTP_ORIGIN'=>'https://untrusted.example', 'REQUEST_METHOD'=>'POST');
$_POST = array();
rejected(403, 'origin');
$_SERVER['HTTP_ORIGIN']='https://verneide.sharepoint.com';
$_SERVER['REQUEST_METHOD']='GET'; rejected(405, 'POST');
$_SERVER['REQUEST_METHOD']='POST'; rejected(403, 'token');
$_POST['nonce']='fixture'; rejected(403, 'internal');
$_POST['internal_listing']='1'; $_POST['list_id']=12;
$GLOBALS['internal']=false; $GLOBALS['published']=true; rejected(403, 'published internal');
$GLOBALS['internal']=true; $GLOBALS['published']=false; rejected(403, 'published internal');
$GLOBALS['published']=true; rejected(400, 'required fields');
$_POST += array('staff_post_id'=>34, 'employee_name'=>'Alice', 'requester_name'=>'Test', 'requester_email'=>'test@example.com');
rejected(400, 'at least one');
echo "Suggestion origin, nonce, method, internal-list and required-field guards passed\n";
