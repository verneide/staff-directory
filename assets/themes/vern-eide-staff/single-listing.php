<?php
/**
 * Template for staff listings.
 * - Updated 02/06/2026
 *
 */

/** -----------------------------
 *  URL PARAMS (sanitized)
 *  ----------------------------- */
$type        = isset($_GET['type'])  ? sanitize_text_field(wp_unslash($_GET['type']))  : '';
$showpgtitle = isset($_GET['title']) ? sanitize_text_field(wp_unslash($_GET['title'])) : '';
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
 *  PAGE ASSETS (normal page mode)
 *  ----------------------------- */
if (!$is_script) {
	//wp_enqueue_script('ve-lazy-load');
	wp_enqueue_style('ve-staff-listing');
	wp_enqueue_script('listing-js');
	wp_enqueue_script('ve-ga-events');

	if (!headers_sent()) {
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
	}
}

/** -----------------------------
 *  Timezone
 *  ----------------------------- */
date_default_timezone_set('America/Chicago');

/** -----------------------------
 *  GET POST / ACF VARS
 *  ----------------------------- */
$listid               = get_the_ID();
$listinglocations     = get_field('listing_locations');
$excludeddepartments  = get_field('listing_excluded_departments');
$showlocnames         = get_field('listing_show_locations');
if ($showlocnames == true) { $group = 'TRUE'; }
$showloclogo          = get_field('listing_show_location_logo');
$showfilters          = get_field('listing_show_filters');
$showdepartments      = get_field('listing_show_departments');
$showfullname         = get_field('listing_show_full_name');
$showfulllastname     = get_field('listing_show_full_last_name');
$showtitles           = get_field('listing_show_titles');
$showaptsbtn          = get_field('listing_show_apts_btns');
$showbios             = get_field('listing_show_bio');
$webvisiblebypass     = get_field('listing_web_visible_bypass');
$internalpage         = get_field('internal_page');
$rowcolumns           = get_field('listing_row_columns');

$listingpgtitle = '';
if ($showpgtitle !== '0') {
	$listingpgtitle = the_title('<h1 class="entry-title">', '</h1>', false);
}

$embedcustomcss = get_field('embed_custom_css');
$inline_css = $embedcustomcss ? (string)$embedcustomcss : '';

/** -----------------------------
 *  LOGGED IN / INTERNAL FLAGS
 *  ----------------------------- */
$loggedin = false;
if (current_user_can('manage_options') || current_user_can('create_staff')) {
	$loggedin = true;
}

$apikey = '37462087-e53d-45a0-9e1f-628a125412bd';
$internal = false;

if ($internalpage == true && (($api === $apikey) || $loggedin)) {
	$internal = true;
}

$notvisible = ($internal || $webvisiblebypass) ? 0 : 1;

/** -----------------------------
 *  TAXONOMIES / TERMS
 *  ----------------------------- */
$depttaxonomy = 'department';
$args = [
	'orderby' => 'id',
	'order'   => 'ASC',
	'exclude' => $excludeddepartments,
];
$dept_taxonomy_terms = get_terms($depttaxonomy, $args);

/* GET ALL TERM IDS OF LOCATIONS IF LOCATION NOT SET */
$locationtaxonomy = 'location';
if (empty($listinglocations)) {
	$listinglocations = get_terms($locationtaxonomy, [
		'hide_empty' => 0,
		'fields'     => 'ids',
	]);
}

/** -----------------------------
 *  STAFF LIST QUERY OUTPUT
 *  (your template parts should populate $output and/or $htmlcontent)
 *  ----------------------------- */
if ($internal) {
	get_template_part('template-parts/list-output-internal', null, []);
} else {
	get_template_part('template-parts/list-output-external', null, []);
}

/** -----------------------------
 *  JSON OUTPUT (fixes precedence)
 *  ----------------------------- */
if ($is_json && $loggedin) {
	// Assumes $output is set by template-parts/list-output-*
	echo wp_json_encode($output ?? []);
	exit;
}

/** -----------------------------
 *  PAGE TYPE CSS ID (if needed by your html template parts)
 *  ----------------------------- */
$pgtypecssid = '';
if ($internal) {
	$pgtypecssid = 'id="internalList"';
} elseif ($group === "TRUE") {
	$pgtypecssid = 'id="groupList"';
}

/** -----------------------------
 *  HTML BUILD (template parts set global $htmlcontent)
 *  ----------------------------- */
if ($internal) {
	get_template_part('template-parts/list-html-internal', null, []);
} else {
	get_template_part('template-parts/list-html-external', null, []);
}

global $htmlcontent;
$htmlcontent = (string)($htmlcontent ?? '');


/**
 * IF SCRIPT EMBED
 */
if ($is_script) {

	$css_urls = [];
	$js_urls  = [];

	// --- CSS ---
	$staff_css = function_exists('get_staff_css_src_url') ? get_staff_css_src_url('ve-staff-listing') : false;
	if ($staff_css) {
		$css_urls[] = $staff_css;
	} else {
		$css_urls[] = get_template_directory_uri() . '/inc/assets/css/listing.css';
	}

	// --- JS (order matters) ---
		// --- JS (order matters) ---
	// IMPORTANT: don't inject a second jQuery if the host already has one.
	// We'll conditionally load it in the loader instead.
	$jquery_url = 'https://code.jquery.com/jquery-3.6.0.min.js';

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

	// Build list WITHOUT jQuery first; loader will inject it only if needed.
	$js_urls[] = $bootstrap_js;
	$js_urls[] = $listing_js;
	$js_urls[] = $lazy_js;
	$js_urls[] = $ga_js;

	// OPTIONAL: avoid injecting security script into client sites unless required
	// if (!is_user_logged_in()) {
	// 	$js_urls[] = get_template_directory_uri() . '/inc/assets/js/ve-security.js';
	// }

	// OPTIONAL: only include YouTube API if your html actually contains YT embeds
	// $js_urls[] = 'https://www.youtube.com/iframe_api';

	$css_urls = array_values(array_unique(array_filter($css_urls)));
	$js_urls  = array_values(array_unique(array_filter($js_urls)));

	$payload = [
		'html'      => veMinifyHtml($htmlcontent),
		'css'       => $css_urls,
		'js'        => $js_urls,
		'inlineCss' => $inline_css,
		'jquery'    => $jquery_url,
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

	// Helpers
	echo "  function markImgState(img){\n";
	echo "    try {\n";
	echo "      if (!img) return;\n";
	echo "      // If loaded successfully, strip any loading classes\n";
	echo "      if (img.complete && img.naturalWidth > 0) {\n";
	echo "        img.classList.remove('lazyload-loading','lazyload','lazyloaded','lazy');\n";
	echo "        img.classList.add('loaded');\n";
	echo "        return;\n";
	echo "      }\n";
	echo "      // If failed, at least remove loading class so it doesn't stay hidden forever\n";
	echo "      if (img.complete && img.naturalWidth === 0) {\n";
	echo "        img.classList.remove('lazyload-loading');\n";
	echo "      }\n";
	echo "    } catch(e) {}\n";
	echo "  }\n";

	echo "  function bindImgListeners(root){\n";
	echo "    try {\n";
	echo "      var imgs = (root||d).querySelectorAll('img');\n";
	echo "      imgs.forEach(function(img){\n";
	echo "        if (img.__veStaffBound) return;\n";
	echo "        img.__veStaffBound = true;\n";
	echo "        img.addEventListener('load', function(){ markImgState(img); }, { once:false });\n";
	echo "        img.addEventListener('error', function(){ markImgState(img); }, { once:false });\n";
	echo "        markImgState(img);\n";
	echo "      });\n";
	echo "    } catch(e) {}\n";
	echo "  }\n";

	echo "  function initLazy(){\n";
	echo "    try {\n";
	echo "      bindImgListeners(container);\n";
	echo "      if (window.veLazyLoadInit) window.veLazyLoadInit(container);\n";
	echo "      setTimeout(function(){ try { if (window.veLazyLoadInit) window.veLazyLoadInit(container); bindImgListeners(container); } catch(e) {} }, 250);\n";
	echo "      setTimeout(function(){ try { if (window.veLazyLoadInit) window.veLazyLoadInit(container); bindImgListeners(container); } catch(e) {} }, 1000);\n";
	echo "    } catch(e) {}\n";
	echo "  }\n";

	// Script loader
	echo "  function loadOne(u, cb){\n";
	echo "    if(!u) return cb();\n";
	echo "    // Already loaded?\n";
	echo "    if (d.querySelector('script[data-ve-staff][src=\"'+u.replace(/\"/g,'\\\\\"')+'\"]')) return cb();\n";
	echo "    var sc = d.createElement('script');\n";
	echo "    sc.src = u;\n";
	echo "    sc.async = false;\n";
	echo "    sc.defer = false;\n";
	echo "    sc.setAttribute('data-ve-staff','1');\n";
	echo "    sc.onload = cb;\n";
	echo "    sc.onerror = cb;\n";
	echo "    (d.body || d.documentElement).appendChild(sc);\n";
	echo "  }\n";

	echo "  function loadSeq(urls, i){\n";
	echo "    urls = urls || [];\n";
	echo "    if(i >= urls.length) { initLazy(); return; }\n";
	echo "    loadOne(urls[i], function(){ loadSeq(urls, i+1); });\n";
	echo "  }\n";

	// Conditionally load jQuery (only if missing)
	echo "  function start(){\n";
	echo "    if (window.jQuery) {\n";
	echo "      loadSeq(p.js, 0);\n";
	echo "      return;\n";
	echo "    }\n";
	echo "    // No jQuery on host: load ours first, then continue\n";
	echo "    loadOne(p.jquery, function(){ loadSeq(p.js, 0); });\n";
	echo "  }\n";
	echo "  start();\n";

	echo "})();\n";
	exit;
}

/** -----------------------------
 *  NORMAL PAGE MODE OUTPUT
 *  ----------------------------- */
if (!$is_script) {
	get_header();

	$listingcss = get_staff_css_link_tag('ve-staff-listing');
	if ($listingcss) {
		echo $listingcss;
	}

	if (!empty($inline_css)) {
		echo '<style>' . $inline_css . '</style>';
	}

	echo $listingpgtitle;
	echo $htmlcontent;

	get_footer();
}