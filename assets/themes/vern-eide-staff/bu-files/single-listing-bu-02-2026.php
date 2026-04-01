<?php
/**
 * The template for displaying staff listings
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package VE_Staff
 */

/* GET URL PARMS */
$type = $_GET['type'];
$showpgtitle = $_GET['title'];
$group = $_GET['group'];
$debug = $_GET['debug'];

// PAGE ACCESS CHECK
if (!ve_staff_check_page_access()) {
	$access_error = "Error: Unauthorized - Contact Support";
	if ($type == "script"){
		Header("content-type: application/x-javascript");
		echo 'document.write('.$access_error.');';
		exit;
	} else {
    	echo $access_error;
    	exit; // Stop further execution of the script
	}
}

// PAGE ASSETS
$htmljs = '';

if ($type == "script"){
	ob_start();
	// GET HEAD AND STORE IN VAR
	wp_head();
	$head_output = ob_get_clean();
	$htmljs .= "<script type=\"text/javascript\" src=\"" . get_template_directory_uri() . "/inc/assets/js/listing.js\"></script>";
	$htmljs .= "<script type=\"text/javascript\" src=\"" . get_template_directory_uri() . "/inc/assets/js/ve-lazy-load.js\"></script>";
	$htmljs .= "<script type=\"text/javascript\" src=\"" . get_template_directory_uri() . "/inc/assets/js/ga-events.js\"></script>";
} else {
	wp_enqueue_script( 've-lazy-load' );
	wp_enqueue_style( 've-staff-listing' );
	wp_enqueue_script( 'listing-js' );
	wp_enqueue_script( 've-ga-events' );
	if (!headers_sent()) {
		// Set Cache-Control header to no-cache
		header("Cache-Control: no-cache, must-revalidate");
		// Set Pragma header to no-cache
		header("Pragma: no-cache");
	}
}

$jquerylink = '<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js" integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI=" crossorigin="anonymous"></script>';

$bootstrapscript = "<script type=\"text/javascript\" src=\"" . get_template_directory_uri() . "/inc/assets/js/bootstrap.bundle.js\"></script>";

$listingcss = get_staff_css_link_tag('ve-staff-listing');
if($listingcss){
	$htmlcss = $listingcss;
}

if ( ! is_user_logged_in() ) {
$htmljs .= "<script type=\"text/javascript\" src=\"" . get_template_directory_uri() . "/inc/assets/js/ve-security.js\"></script>";
}

$htmljs .= "<script src=\"https://www.youtube.com/iframe_api\"></script>";

date_default_timezone_set('America/Chicago');

// GET POST VARS
$listid = get_the_ID();
$listinglocations = get_field('listing_locations');
$excludeddepartments = get_field('listing_excluded_departments');
$showlocnames = get_field('listing_show_locations');
if ($showlocnames == TRUE){
    $group = 'TRUE';
}
$showloclogo = get_field('listing_show_location_logo');
$showfilters = get_field('listing_show_filters');
$showdepartments = get_field('listing_show_departments');
$showfullname = get_field('listing_show_full_name');
$showfulllastname = get_field('listing_show_full_last_name');
$showtitles = get_field('listing_show_titles');
$showaptsbtn = get_field('listing_show_apts_btns');
$showbios = get_field('listing_show_bio');
$webvisiblebypass = get_field('listing_web_visible_bypass');
$internalpage = get_field('internal_page');
$rowcolumns = get_field('listing_row_columns');
if ( $showpgtitle != '0' ){
	$listingpgtitle = the_title( '<h1 class="entry-title">', '</h1>',false );
}
$listingtype = 'standard';
$listingmodals;
$embedcustomcss = get_field('embed_custom_css');
if($embedcustomcss){
	$embedcustomcss = '<style>'.$embedcustomcss.'</style>';
}


// GET USER INFO & LOGGED IN STATUS
if ( current_user_can( 'manage_options' ) || current_user_can( 'create_staff' ) ) {
	$loggedin = TRUE;
}

/* INTERNAL VARS */
$apikey = '37462087-e53d-45a0-9e1f-628a125412bd';
$api = $_GET['api'];
if ($internalpage == TRUE && ($api == $apikey || $loggedin) ){ //SETS INTERNAL TO TRUE IF PAGE HAS INTERNAL INFO ON AND API KEY IS PRESENT OR A USER IS LOGGED IN
	$internal = TRUE;
}

if ($internal || $webvisiblebypass){
	$notvisible = 0; //IF INTERNAL GET NOT WEB VISIBLE STAFF
} else {
	$notvisible = 1; //ELSE ONLY GET WEB VISIBLE STAFF
}

// INITIAL CSS & JS
// GENERATE CODE FOR SCRIPT EMBED
if ($type == "script"){
	Header("content-type: application/x-javascript");
	$initaldependencies = $jquerylink .' '. $bootstrapscript . ' ' . ' ' . $embedcustomcss . ' ' . $htmljs . ' ' . $htmlcss .' '. $listloader;
	echo 'document.write('.json_encode(veMinifyHtml($initaldependencies)).');';
} 

// GET ALL THE STAFF BY DEPARTMENT
$depttaxonomy = 'department';
$args = array(
	'orderby' => 'id',
	'order' => 'ASC',
	'exclude' => $excludeddepartments,
);
$dept_taxonomy_terms = get_terms($depttaxonomy, $args);

/* GET ALL TERM IDS OF LOCATIONS IF LOCATION NOT SET */
$locationtaxonomy = 'location';
    if (empty($listinglocations)) {
        $listinglocations = get_terms( $locationtaxonomy, array(
                'hide_empty' => 0,
                'fields' => 'ids'
        ) );
    };

//
// STAFF LIST QUERY OUTPUT
// 

if($internal){
	get_template_part( 
				'template-parts/list-output-internal', 
				null, 
				array( 
					
				)
	);
} else {
	get_template_part( 
				'template-parts/list-output-external', 
				null, 
				array( 
					
				)
	);
}

// END OUTPUT

if ($type == "JSON" || $type == "json" && $loggedin){
	/* OUTPUT JSON */
	echo json_encode( $output );
} else {
	if ($internal){
		$pgtypecssid = "id=\"internalList\"";
	} elseif ($group == "TRUE") {
		$pgtypecssid = "id=\"groupList\"";
}

if($internal){
	get_template_part( 
				'template-parts/list-html-internal', 
				null, 
				array( 
					
				)
	);
} else {
	get_template_part( 
				'template-parts/list-html-external', 
				null, 
				array( 
					
				)
	);
}
	
/* SETS THE ABOVE HTML TO PHP VARIABLE */
    global $htmlcontent;
    if ($type == "script") {
        /* COMBINE HTML, CSS & JS READY FOR OUTPUT */
		$scripthtml = $htmlcontent;
        echo 'document.write('.json_encode(veMinifyHtml($scripthtml)).');';
    } else{
		get_header();
		echo $listingcss;
        echo $jquerylink;
        echo $htmljs;
		echo $listingpgtitle;
		echo $htmlcontent;
		get_footer();
    } 
}

?>