<?php
/**
 * Template: Staff Simple Listings (embed + page + json)
 * Drop-in replacement for single-simple-listing.php
 * Updated: 02/06/2026
 */

date_default_timezone_set('America/Chicago');

/** -----------------------------
 *  URL PARAMS (sanitized)
 *  ----------------------------- */
$showpgtitle = isset($_GET['title']) ? sanitize_text_field(wp_unslash($_GET['title'])) : '';
$type        = isset($_GET['type'])  ? sanitize_text_field(wp_unslash($_GET['type']))  : '';
$group       = isset($_GET['group']) ? sanitize_text_field(wp_unslash($_GET['group'])) : '';
$debug       = isset($_GET['debug']) ? sanitize_text_field(wp_unslash($_GET['debug'])) : '';
$api         = isset($_GET['api'])   ? sanitize_text_field(wp_unslash($_GET['api']))   : '';

$is_script = (strtolower($type) === 'script');
$is_json   = (strtolower($type) === 'json');

/** -----------------------------
 *  SCRIPT RESPONSE HEADERS
 *  ----------------------------- */
if ($is_script && !headers_sent()) {
	header('Content-Type: application/javascript; charset=UTF-8');
	header('Cache-Control: no-cache, must-revalidate, max-age=0');
	header('Pragma: no-cache');
}

/** -----------------------------
 *  ACCESS CHECK
 *  ----------------------------- */
if (!ve_staff_check_page_access()) {
	$access_error = "Error: Unauthorized - Contact Support";

	if ($is_script) {
		echo "(function(){console.error(" . wp_json_encode($access_error) . ");})();";
		exit;
	}

	echo esc_html($access_error);
	exit;
}

/** -----------------------------
 *  LOGGED IN FLAG
 *  ----------------------------- */
$loggedin = false;
if (current_user_can('manage_options') || current_user_can('create_staff')) {
	$loggedin = true;
}

/** -----------------------------
 *  PAGE ASSETS (normal page mode)
 *  ----------------------------- */
if (!$is_script) {
	wp_enqueue_style('ve-staff-listing');
	wp_enqueue_script('listing-js');
	wp_enqueue_script('ve-ga-events');
	wp_enqueue_script('ve-lazy-load');

	if (!headers_sent()) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
	}
}

/** -----------------------------
 *  GET POST / ACF VARS
 *  ----------------------------- */
$listid           = get_the_ID();
$listinglocations = get_field('simple_listing_locations');
$listingtype      = get_field('simple_listing_type'); // birthday | newpromo | etc
$birthdaygroup    = get_field('birthday_listing');
$newpromogroup    = get_field('new_promotion_listing');

$showlocnames  = get_field('listing_show_locations'); // note: legacy field name
$internalpage  = get_field('simple_internal_page');
$rowcolumns    = get_field('simple_listing_row_columns');
$custompgtitle = get_field('simple_listing_title');
$staffshown    = get_field('staff_shown_on_load');

$embedcustomcss = get_field('embed_custom_css');
$inline_css     = $embedcustomcss ? (string)$embedcustomcss : '';

/** -----------------------------
 *  GROUP FLAG (if show locations enabled)
 *  ----------------------------- */
if ($showlocnames === true) {
	$group = 'TRUE';
}

/** -----------------------------
 *  INTERNAL FLAG
 *  ----------------------------- */
$apikey   = '37462087-e53d-45a0-9e1f-628a125412bd';
$internal = false;

if ($internalpage === true && (($api === $apikey) || $loggedin)) {
	$internal = true;
}

$notvisible = $internal ? 0 : 1;

/** -----------------------------
 *  LIST TITLE
 *  ----------------------------- */
$listingpgtitle = '';
if ($showpgtitle !== '0') {
	if (!empty($custompgtitle)) {
		$listingpgtitle = '<h1 class="entry-title ve-container-fluid">' . esc_html($custompgtitle) . '</h1>';
	} else {
		$listingpgtitle = the_title('<h1 class="entry-title ve-container-fluid">', '</h1>', false);
	}
}

/** -----------------------------
 *  LIST TYPE FLAGS
 *  ----------------------------- */
$birthdaylist      = false;
$newpromotionlist  = false;

if ($listingtype === 'birthday') {
	$birthdaylist = true;
	// Kept for compatibility (not required for embed changes)
	if ($birthdaygroup) {
		$bddaysbeforetoday = (int) get_field('birthday_listing_days_shown_before');
		$bddaysaftertoday  = (int) get_field('birthday_listing_days_shown_after');
	}
}

if ($listingtype === 'newpromo') {
	$newpromotionlist = true;
	if ($newpromogroup) {
		$newmonthsprior = (int) get_field('new_promotion_listing_months_before_current');
	}
}

/** -----------------------------
 *  GET ALL TERM IDS OF LOCATIONS IF LOCATION NOT SET
 *  ----------------------------- */
$locationtaxonomy = 'location';
if (empty($listinglocations)) {
	$listinglocations = get_terms($locationtaxonomy, [
		'hide_empty' => 0,
		'fields'     => 'ids',
	]);
}

/** -----------------------------
 *  STAFF QUERY OUTPUT (template parts must set global $staff)
 *  ----------------------------- */
if ($internal) {
	get_template_part('template-parts/simplelist-output-internal', null, []);
} else {
	get_template_part('template-parts/simplelist-output-external', null, []);
}

global $staff;
$staff = is_array($staff) ? $staff : [];

/** -----------------------------
 *  JSON OUTPUT
 *  ----------------------------- */
if ($is_json) {
	// Keep it gated like your other endpoint: only logged in users get JSON
	if (!$loggedin) {
		echo wp_json_encode([]);
		exit;
	}
	echo wp_json_encode($staff);
	exit;
}

/** -----------------------------
 *  PAGE TYPE CSS ID
 *  ----------------------------- */
$pgtypecssid = '';
if ($internal) {
	$pgtypecssid = 'id="internalList"';
} elseif ($group === 'TRUE') {
	$pgtypecssid = 'id="groupList"';
}

/** -----------------------------
 *  BUILD HTML
 *  ----------------------------- */
$staffcount = 0;

ob_start();

// Debug info block (unchanged behavior)
if (!empty($debug)) { ?>
	<div id="debugInfo" style="width:100%;display:block;">
		<p><strong>DEBUG MODE INFO:</strong></p>
		<?php
			echo "Current date/time: " . esc_html(date("Y-m-d h:i:sa")) . "<br>";
			echo "User Can Manage Options: " . ($loggedin ? 'true' : 'false') . "<br>";
			echo "Total Staff Shown: " . esc_html(count($staff));
		?>
	</div>
<?php } ?>

<!-- STAFF WRAP -->
<div id="veStaffList"
	 class="ve ve-container-fluid velist-<?php echo esc_attr($listid); ?>"
	 data-list-type="<?php echo esc_attr($listingtype); ?>"
	 data-list-id="<?php echo esc_attr($listid); ?>">

	<div class="ve-block ve-clear-fix ve-pad-top-md" id="pageContainer">

		<div class="section-container ve-pad-none ve-pad-bottom-lg ve-clear-fix ve-text-left ve-col-sm-12"
			 <?php echo $pgtypecssid; ?>
			 style="background-color:white;">

			<?php
			// Filters
			get_template_part(
				'template-parts/simplelist-filter',
				null,
				[
					'embedloc' => [
						'group'    => $group,
						'internal' => $internal,
					],
					'type' => [
						'birthday' => $birthdaylist,
						'newpromo' => $newpromotionlist,
					],
					'data' => [
						'staff' => $staff,
					],
				]
			);
			?>

			<!-- STAFF LIST -->
			<div class="ve-pad-none ve-pad-top-md" style="margin:auto;">
				<div class="ve-pad-none employee-list" style="margin:auto;">
					<div class="ve-row">
						<?php
						if (count($staff) >= 1) {
							foreach ($staff as $k => $v) {

								// Skip if web-visible false (unless internal)
								$webvisible = $v['website_visible'] ?? true;
								if (!$internal && $webvisible === false) {
									continue;
								}

								// Skip if not visible in simple listing
								$simplelistvisible = $v['simple_listing_visible'] ?? true;
								if ($simplelistvisible === false) {
									continue;
								}

								// Vars expected by template part
								$staffid      = $v['id'] ?? 0;
								$name         = $v['full_name'] ?? '';
								$fname        = $v['first_name'] ?? '';
								$title        = $v['title'] ?? '';
								$location     = $v['location'] ?? '';
								$locationsall = $v['locations_listed'] ?? [];
								$photourl     = $v['photo_sized'] ?? '';

								$email          = $v['email'] ?? '';
								$phone          = preg_replace('/\D+/', '', (string)($v['phone'] ?? ''));
								$phonetype      = $v['phone_type'] ?? '';
								$phoneformatted = (strlen($phone) >= 10)
									? (substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6, 4))
									: '';

								$contactinfo  = [];
								$birthdayinfo = [];
								$newpromoinfo = [];

								if ($internal) {
									$intinfo = $v['int_info'] ?? [];
									if (is_array($intinfo)) {
										foreach ($intinfo as $ik => $iv) {
											$contactinfo  = $iv['contact_info'] ?? $contactinfo;
											$birthdayinfo = $iv['birthday'] ?? $birthdayinfo;
											$newpromoinfo = $iv['newpromo'] ?? $newpromoinfo;
										}
									}
								}

								$staffcount++;

								// Staff Card (make sure THIS template uses the same ve-lazy markup you updated)
								get_template_part(
									'template-parts/simplelist-staffcard',
									null,
									[
										'embedloc' => [
											'group'    => $group,
											'internal' => $internal,
										],
										'type' => [
											'birthday' => $birthdaylist,
											'newpromo' => $newpromotionlist,
										],
										'data' => [
											'totalStaff'      => count($staff),
											'staffcountnum'   => $staffcount,
											'staffshown'      => $staffshown,
											'contactinfo'     => $contactinfo,
											'birthdayinfo'    => $birthdayinfo,
											'newpromoinfo'    => $newpromoinfo,
											'location'        => $location,
											'locationsall'    => $locationsall,
											'name'            => $name,
											'fname'           => $fname,
											'email'           => $email,
											'phone'           => $phone,
											'phoneformatted'  => $phoneformatted,
											'photourl'        => $photourl,
											'staffid'         => $staffid,
											'title'           => $title,
										],
										'settings' => [
											'rowcolumns' => $rowcolumns,
										],
									]
								);
							}
						} else {
							echo '<h4><center>NO RESULTS FOUND</center></h4>';
						}
						?>
					</div>
				</div>
			</div>

			<?php if (!empty($staffshown) && (int)$staffshown !== 0 && $staffcount > (int)$staffshown) { ?>
				<div class="viewMoreWrapper">
					<div class="ve-valign-middle" id="viewMore">
						<button class="ve-primary-button-black ve-viewmore-btn" type="button" id="viewMoreBtn">VIEW MORE</button>
					</div>
				</div>
			<?php } ?>

		</div>

		<p class="ve-text-center ve-block ve-poweredby"><small>Powered by Vern Eide Marketing</small></p>

	</div>
</div>

<?php
$htmlcontent = (string) ob_get_clean();

/** -----------------------------
 *  SCRIPT EMBED MODE (payload injection)
 *  ----------------------------- */
if ($is_script) {

	$css_urls = [];
	$js_urls  = [];

	// --- CSS ---
	$staff_css = function_exists('get_staff_css_src_url') ? get_staff_css_src_url('ve-staff-listing') : false;
	if ($staff_css) {
		$css_urls[] = $staff_css;
	} else {
		// Simple list used listing-css.min.css previously; keep it
		$css_urls[] = get_template_directory_uri() . '/inc/assets/css/listing-css.min.css';
	}

	// --- JS (order matters) ---
	$js_urls[] = 'https://code.jquery.com/jquery-3.6.0.min.js'; // full jQuery in embed to avoid slim/plugin issues

	$bootstrap_js = get_template_directory_uri() . '/inc/assets/js/bootstrap.bundle.js';
	$listing_js   = get_template_directory_uri() . '/inc/assets/js/listing.js';
	$lazy_js      = get_template_directory_uri() . '/inc/assets/js/ve-lazy-load.js';
	$ga_js        = get_template_directory_uri() . '/inc/assets/js/ga-events.js';

	if (function_exists('get_staff_js_src_url')) {
		$tmp = get_staff_js_src_url('bootstrap-bundle'); if ($tmp) $bootstrap_js = $tmp;
		$tmp = get_staff_js_src_url('listing-js');      if ($tmp) $listing_js   = $tmp;
		$tmp = get_staff_js_src_url('ve-lazy-load');    if ($tmp) $lazy_js      = $tmp;
		$tmp = get_staff_js_src_url('ve-ga-events');    if ($tmp) $ga_js        = $tmp;
	}

	$js_urls[] = $bootstrap_js;
	$js_urls[] = $listing_js;
	$js_urls[] = $lazy_js;
	$js_urls[] = $ga_js;

	if (!is_user_logged_in()) {
		$js_urls[] = get_template_directory_uri() . '/inc/assets/js/ve-security.js';
	}

	$css_urls = array_values(array_unique(array_filter($css_urls)));
	$js_urls  = array_values(array_unique(array_filter($js_urls)));

	$payload = [
		'html'      => veMinifyHtml($htmlcontent),
		'css'       => $css_urls,
		'js'        => $js_urls,
		'inlineCss' => $inline_css,
	];

	echo "(function(){\n";
	echo "  var p = " . wp_json_encode($payload) . ";\n";
	echo "  var d = document;\n";
	echo "  var s = (function(){\n";
	echo "    try { if (d.currentScript && d.currentScript.parentNode) return d.currentScript; } catch(e) {}\n";
	echo "    try {\n";
	echo "      var scripts = d.getElementsByTagName('script');\n";
	echo "      for (var i=scripts.length-1; i>=0; i--) {\n";
	echo "        if (scripts[i] && scripts[i].parentNode) return scripts[i];\n";
	echo "      }\n";
	echo "    } catch(e) {}\n";
	echo "    return null;\n";
	echo "  })();\n";
	echo "  var host = (s && s.parentNode) ? s.parentNode : (d.body || d.documentElement);\n";
	echo "  var container = d.createElement('div');\n";
	echo "  container.className = 've-staff-embed';\n";
	echo "  host.insertBefore(container, s || null);\n";

	// Inline CSS
	echo "  if (p.inlineCss) {\n";
	echo "    var st = d.createElement('style');\n";
	echo "    st.setAttribute('data-ve-staff','1');\n";
	echo "    st.appendChild(d.createTextNode(p.inlineCss));\n";
	echo "    (d.head || d.getElementsByTagName('head')[0]).appendChild(st);\n";
	echo "  }\n";

	// External CSS
	echo "  (p.css||[]).forEach(function(href){\n";
	echo "    if(!href) return;\n";
	echo "    if (d.querySelector('link[data-ve-staff][href=\"'+href.replace(/\"/g,'\\\\\"')+'\"]')) return;\n";
	echo "    var l = d.createElement('link');\n";
	echo "    l.rel='stylesheet'; l.href=href; l.setAttribute('data-ve-staff','1');\n";
	echo "    (d.head || d.getElementsByTagName('head')[0]).appendChild(l);\n";
	echo "  });\n";

	// HTML
	echo "  container.innerHTML = p.html || '';\n";

	// Load JS sequentially then init lazy
	echo "  function loadSeq(urls, i){\n";
	echo "    urls = urls || [];\n";
	echo "    if(i >= urls.length) {\n";
	echo "      try {\n";
	echo "        // Normalize any host-lazy leftovers + kick our lazy loader\n";
	echo "        (function fixStuckImages(root){\n";
	echo "          if(!root) return;\n";
	echo "          var imgs = root.querySelectorAll('img');\n";
	echo "          imgs.forEach(function(img){\n";
	echo "            var src = img.getAttribute('src') || '';\n";
	echo "            var ds  = img.getAttribute('data-src') || img.getAttribute('data-lazy-src') || img.getAttribute('data-original') || '';\n";
	echo "            if (src && src.indexOf('data:image') !== 0) {\n";
	echo "              img.classList.remove('lazyload-loading','lazyload','lazyloaded','lazy');\n";
	echo "              img.classList.add('loaded');\n";
	echo "              return;\n";
	echo "            }\n";
	echo "            if (ds) {\n";
	echo "              img.classList.remove('lazyload-loading','lazyload','lazyloaded','loaded');\n";
	echo "              img.classList.add('lazy');\n";
	echo "            }\n";
	echo "          });\n";
	echo "        })(container);\n";
	echo "        if (window.veLazyLoadInit) window.veLazyLoadInit(container);\n";
	echo "        setTimeout(function(){ try { if (window.veLazyLoadInit) window.veLazyLoadInit(container); } catch(e) {} }, 250);\n";
	echo "      } catch(e) {}\n";
	echo "      return;\n";
	echo "    }\n";
	echo "    var u = urls[i];\n";
	echo "    if(!u) return loadSeq(urls, i+1);\n";
	echo "    if (d.querySelector('script[data-ve-staff][src=\"'+u.replace(/\"/g,'\\\\\"')+'\"]')) return loadSeq(urls, i+1);\n";
	echo "    var sc = d.createElement('script');\n";
	echo "    sc.src = u; sc.async = false; sc.defer = false; sc.setAttribute('data-ve-staff','1');\n";
	echo "    sc.onload = function(){ loadSeq(urls, i+1); };\n";
	echo "    sc.onerror = function(){ loadSeq(urls, i+1); };\n";
	echo "    (d.body || d.documentElement).appendChild(sc);\n";
	echo "  }\n";
	echo "  loadSeq(p.js, 0);\n";

	echo "})();\n";
	exit;
}

/** -----------------------------
 *  NORMAL PAGE MODE OUTPUT
 *  ----------------------------- */
get_header();

$listingcss = function_exists('get_staff_css_link_tag') ? get_staff_css_link_tag('ve-staff-listing') : '';
if (!empty($listingcss)) {
	echo $listingcss;
}

if (!empty($inline_css)) {
	echo '<style>' . $inline_css . '</style>';
}

echo $listingpgtitle;
echo $htmlcontent;

get_footer();