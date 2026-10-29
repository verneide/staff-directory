<?php
function add_action(...$args) {}
function wp_next_scheduled(...$args) { return true; }
function apply_filters($name, $value) { return $value; }
function sanitize_text_field($value) { return $value; }
function wp_unslash($value) { return $value; }
function is_user_logged_in() { return false; }
function get_field(...$args) { return array(); }
function get_the_title($post) { return 'Team &amp; Friends'; }
function wp_strip_all_tags($s) { return strip_tags($s); }
function get_permalink($post) { return 'https://staff.eideweb.com/listing/team/'; }
// Match WordPress: omitted status retains the status set by the main page query.
function wp_send_json($body, $status=null) { throw new Exception(json_encode(array($status ?? http_response_code(), $body))); }
class WP_Query {
    public $posts; public $max_num_pages = 2;
    public function __construct($args) {
        if ($args['post_status'] !== 'publish' || $args['has_password'] !== false || $args['posts_per_page'] !== 100) throw new Error('Unsafe discovery query');
        $this->posts = array((object)array('ID'=>12));
    }
}
require __DIR__ . '/../../../assets/plugins/ve-staff/public/partials/ve-staff-public-functions.php';
function check($origin, $type, $page, $expected) {
    http_response_code(404); // The homepage/page query may already have failed.
    $_SERVER['HTTP_ORIGIN']=$origin;
    $_GET=array('type'=>'sharepoint','ve_staff_lists'=>'1','list_type'=>$type,'page'=>$page);
    try { ve_staff_sharepoint_catalog(); throw new Error('No response'); }
    catch (Exception $e) {
        $result=json_decode($e->getMessage(),true);
        if ($result[0] !== $expected) throw new Error($e->getMessage());
        if ($expected === 200 && ($result[1]['lists'][0]['title'] !== 'Team & Friends' || $result[1]['nextPage'] !== ($page===1 ? 2 : null))) throw new Error('Bad catalog');
    }
}
check('https://verneide.sharepoint.com','listing',1,200);
check('https://verneide.sharepoint.com','simple-listing',2,200);
check('https://untrusted.example','listing',1,403);
check('','listing',1,403);
check('https://verneide.sharepoint.com','staff',1,400);
check('https://verneide.sharepoint.com','listing',0,400);


function is_singular($types) { return false; }
function nocache_headers() {}
function get_template_directory() { return dirname(__DIR__, 3) . '/assets/themes/vern-eide-staff'; }
function get_the_ID() { return 12; }
function get_post_type() { return 'listing'; }
foreach (array('' => 'missing_origin', 'https://untrusted.example' => 'origin_not_allowed') as $origin => $code) {
    $_SERVER['HTTP_ORIGIN'] = $origin;
    try { ve_staff_sharepoint_headers(); throw new Error('Expected origin rejection'); }
    catch (Exception $e) {
        $result = json_decode($e->getMessage(), true);
        if ($result[0] !== 403 || $result[1]['code'] !== $code) throw new Error('Incorrect origin diagnostic');
    }
}
$_SERVER['HTTP_ORIGIN'] = 'https://verneide.sharepoint.com';
ve_staff_sharepoint_headers(); // The actual browser origin must pass.
$view = ve_staff_build_v2_view('<div id="veStaffList">Team</div>', '.custom{color:red}', '<h1>Staff</h1>');
try { ve_staff_send_sharepoint_view('<div id="veStaffList">Team</div>', '.custom{color:red}', '<h1>Staff</h1>'); }
catch (Exception $e) {
    $payload = json_decode($e->getMessage(), true)[1];
    if ($payload['html'] !== $view['html'] || $payload['css'] !== $view['css'] || $payload['embedVersion'] !== 2) throw new Error('SharePoint differs from v2 view');
}
echo "Catalog, origin and v2 view checks passed\n";
