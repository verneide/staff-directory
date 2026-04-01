<?php
/**
 * The template for displaying staff simple listings
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package VE_Staff
 */
date_default_timezone_set('America/Chicago');

// URL PARMS
$showpgtitle = $_GET['title'];
$type = $_GET['type'];
$group = $_GET['group'];
$debug = $_GET['debug'];

// GET USER INFO & LOGGED IN STATUS
if ( current_user_can( 'manage_options' ) || current_user_can( 'create_staff' ) ) {
	$loggedin = TRUE;
}

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
	$htmljs .= "<script type=\"text/javascript\" src=\"" . get_template_directory_uri() . "/inc/assets/js/ve-lazy-load.js\"></script>";
	$htmljs .= "<script type=\"text/javascript\" src=\"" . get_template_directory_uri() . "/inc/assets/js/listing.js\"></script>";
} else {
	wp_enqueue_script( 've-lazy-load' );
	wp_enqueue_style( 've-staff-listing' );
	wp_enqueue_script( 'listing-js' );
	if (!headers_sent()) {
		// Set Cache-Control header to no-cache
		header("Cache-Control: no-cache, must-revalidate");
		// Set Pragma header to no-cache
		header("Pragma: no-cache");
	}
}
$jquerylink = '<script src="https://code.jquery.com/jquery-3.6.0.slim.min.js" integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI=" crossorigin="anonymous"></script>';
$bootstrapscript = '<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>';
$htmlcss = "<link rel=\"stylesheet\" href=\"". get_template_directory_uri() . '/inc/assets/css/listing-css.min.css' ."\">";

if ( ! is_user_logged_in() ) {
	$htmljs .= "<script type=\"text/javascript\" src=\"" . get_template_directory_uri() . "/inc/assets/js/ve-security.js\"></script>";
}

// GET POST VARS
$listid = get_the_ID();
$listinglocations = get_field('simple_listing_locations');
$listingtype = get_field('simple_listing_type');
$birthdaygroup = get_field('birthday_listing');
if($listingtype == 'birthday'){
	$birthdaylist = TRUE;
	if ($birthdaygroup){
		$bddaysbeforetoday = get_field('birthday_listing_days_shown_before');
		$bddaysaftertoday = get_field('birthday_listing_days_shown_after');
	}
}
if($listingtype == 'newpromo'){ //New Staff or New Promotions List
	$newpromogroup = get_field('new_promotion_listing');
	$newpromotionlist = TRUE;
	if ($newpromogroup){
		$newmonthsprior = get_field('new_promotion_listing_months_before_current');
	}
}
$showlocnames = get_field('listing_show_locations');
$internalpage = get_field('simple_internal_page');
$rowcolumns = get_field('simple_listing_row_columns');
$custompgtitle = get_field('simple_listing_title');
if ( $showpgtitle != '0' ){
	if(isset($custompgtitle)){
		$listingpgtitle = '<h1 class="entry-title ve-container-fluid">'.$custompgtitle.'</h1>';
	}else{
		$listingpgtitle = the_title( '<h1 class="entry-title ve-container-fluid">', '</h1>',false );
	}
}
$staffshown = get_field('staff_shown_on_load');
$embedcustomcss = get_field('embed_custom_css');
if($embedcustomcss){
	$embedcustomcss = '<style>'.$embedcustomcss.'</style>';
}

if ($showlocnames == TRUE){
    $group = 'TRUE';
}

/* INTERNAL VARS */
$apikey = '37462087-e53d-45a0-9e1f-628a125412bd';
$api = $_GET['api'];
if ($internalpage == TRUE && ($api == $apikey || $loggedin) ){ //SETS INTERNAL TO TRUE IF PAGE HAS INTERNAL INFO ON AND API KEY IS PRESENT OR A USER IS LOGGED IN
	$internal = TRUE;
}

if ($internal){
	$notvisible = 0; //IF INTERNAL GET NOT WEB VISIBLE STAFF
} else {
	$notvisible = 1; //ELSE ONLY GET WEB VISIBLE STAFF
}

// INITIAL CSS & JS
// GENERATE CODE FOR SCRIPT EMBED
if ($type == "script"){
	Header("content-type: application/x-javascript");
	$initaldependencies = $jquerylink .' '. $bootstrapscript . ' ' . ' ' . $embedcustomcss . ' ' . $htmljs . ' ' . $htmlcss .' '. $listloader;
} else {
	if (!headers_sent()) {
		// Set Cache-Control header to no-cache
		header("Cache-Control: no-cache, must-revalidate");
		// Set Pragma header to no-cache
		header("Pragma: no-cache");
	}
}

/* GET ALL TERM IDS OF LOCATIONS IF LOCATION NOT SET */
$locationtaxonomy = 'location';
    if (empty($listinglocations)) {
        $listinglocations = get_terms( $locationtaxonomy, array(
                'hide_empty' => 0,
                'fields' => 'ids'
        ) );
    }

//
// STAFF SIMPLE LIST QUERY OUTPUT
// 

if($internal){
	get_template_part( 
				'template-parts/simplelist-output-internal', 
				null, 
				array( 
					
				)
	);
} else {
	get_template_part( 
				'template-parts/simplelist-output-external', 
				null, 
				array( 
					
				)
	);
}

// END OUTPUT
global $staff;

if ($type == "JSON" || $type == "json"){
/* OUTPUT JSON */
echo json_encode( $staff );

} else {

if ($internal){
	$pgtypecssid = "id=\"internalList\"";
} elseif ($group == "TRUE") {
	$pgtypecssid = "id=\"groupList\"";
}

/* START GENERATING HTML FOR SCRIPT */
ob_start();
	// EMBED
	if ($type == 'embed'){ ?>
		<style>
				#content.site-content {
    				padding-bottom: 30px !important;
    				padding-top: 0 !important;
				}
		</style>
	<?php }
	
	//DEBUG INFO SHOWN IF DEBUG SET IN URL
	if ($debug == true){ ?>
	<div id="debugInfo" style="width: 100%; display: block;">
		<p><strong>DEBUG MODE INFO:</strong></p>
		<?php 
		echo "Current date/time: ". date("Y-m-d h:i:sa");
		echo "<br>";
		echo "User Can Manage Options: ";
		echo $loggedin ? 'true' : 'false'; 
		echo "<br>";
		echo "Total Staff Shown: ".count($staff);
		?>
	</div>
	<?php } // END DEBUG INFO
	
?>
<!-- STAFF EMBED WRAP -->
<div id="veStaffList" class="ve ve-container-fluid velist-<?php echo $listid ?>" data-list-type="<?php echo $listingtype ?>" data-list-id="<?php echo $listid ?>">
	<!-- STAFF EMBED CONTAINER -->
    <div class="ve-block ve-clear-fix ve-pad-top-md" id="pageContainer">
		<?php if($birthdaylist && $api){ //SHOW IF BIRTHDAY LIST ?>
			<?php if ($type == "script") { echo '<h3>'.$listingpgtitle.'</h3>'; }?>
		<?php } elseif ($newpromotionlist && $api){ ?>
			<?php if ($type == "script") { echo '<h3>'.$listingpgtitle.'</h3>'; }?>
			<?php if($newmonthsprior != 0){ ?>
				<?php global $comparedatemonthyear; ?>
				<h5 style="margin:0;"><i><?php echo $comparedatemonthyear . ' to ' . date('F Y'); ?></i></h5>
			<?php } else { ?>
				<h5><i><?php echo date('F Y'); ?></i></h5>
			<?php } ?>
			
		<?php }?>
		<!-- MAIN CONTAINER -->
        <div class="section-container ve-pad-none ve-pad-bottom-lg ve-clear-fix ve-text-left ve-col-sm-12" <?php echo $pgtypecssid ?> style="background-color: white;">
		<!-- STAFF FILTERS -->
		<?php // Page Filter
		get_template_part( 
				'template-parts/simplelist-filter', 
				null, 
				array( 
					'embedloc' => array(
								'group' => $group,
								'internal' => $internal,
					),
					'type' => array(
								'birthday' => $birthdaylist,
								'newpromo' => $newpromotionlist,
					),
					'data' 	   => array(
								'staff' => $staff,
					),
				)
			); ?>
		<!-- END STAFF FILTERS -->	
			
		<!-- STAFF LIST -->
        <div class="ve-pad-none ve-pad-top-md" style="margin: auto;">
            <div class="ve-pad-none employee-list" style="margin: auto;">
				<div class="ve-row">
					<?php /** EMPLOYEE LOOP **/
					if(count($staff) >= 1){
						foreach ($staff as $k => $v) {
							// SKIP EMPLOYEE IF WEB VISIBLE FALSE BUT NOT INTERNAL
							$webvisible = $v["website_visible"];
							if (is_null($webvisible) || isset($internal)){
								$webvisible = TRUE; //SET INITIAL STAFF WITH NULL TO TRUE TO MAKE VISIBLE
							}
							if ($webvisible == FALSE){
								continue; //SKIP TO NEXT
							}

							// SKIP EMPLOYEE IF DOESN'T PASS SIMPLE LIST FILER
							$simplelistvisible = $v["simple_listing_visible"];
							if ($simplelistvisible == FALSE){
								continue; //SKIP TO NEXT
							}

							// CONTINUED LOOP
							// RESET VARS
							unset($photourl); 

							//SET VARS
							$staffid = $v["id"];
							$name = $v["full_name"];
							$fname = $v["first_name"];
							$title = $v["title"];
							$department = $v["department"];
							$location = $v["location"];
							$locationsall = $v["locations_listed"];
							$photourl = $v["photo_sized"];
							if(empty($internal)){
							$email = $v["email"];
							$phone = preg_replace('/\D+/', '', $v["phone"]);
							$phonetype = $v["phone_type"];
							$phoneformatted = substr($phone, 0, 3).'-'.substr($phone, 3, 3).'-'.substr($phone, 6, 4);
							}
							$intinfo = $v["int_info"];

							if($internal){
							//INTERNAL INFO AND VARS
								foreach ($intinfo as $k => $v) {
									$contactinfo = $v["contact_info"];
									$birthdayinfo = $v["birthday"];
									$newpromoinfo = $v["newpromo"];

								};
							};

							//Staff Counts
							$staffcount++;

							// Staff Card
							get_template_part( 
									'template-parts/simplelist-staffcard', 
									null, 
									array( 
										'embedloc' => array(
													'group' => $group,
													'internal' => $internal,
										),
										'type' => array(
													'birthday' => $birthdaylist,
													'newpromo' => $newpromotionlist,
										),
										'data' 	   => array(
													'totalStaff'		=> count($staff),
													'staffcountnum'		=> $staffcount,
													'staffshown'		=> $staffshown,
													'contactinfo'		=> $contactinfo,
													'birthdayinfo'		=> $birthdayinfo,
													'newpromoinfo'		=> $newpromoinfo,
													'location' 			=> $location,
													'locationsall' 		=> $locationsall,
													'deptname' 			=> $deptname,
													'name' 				=> $name,
													'fname' 			=> $fname,
													'email'				=> $email,
													'phone' 			=> $phone,
													'phoneformatted' 	=> $phoneformatted,
													'photourl'			=> $photourl,
													'staffid'			=> $staffid,
													'title'				=> $title,
										),
										'settings'	=> array(
													'rowcolumns' => $rowcolumns,
										),
									)
								); 

						} //END EMPLOYEE LOOP
					}else{
						echo '<h4><center>NO RESULTS FOUND</center></h4>';
					}
 					?>
					</div> <?php //DEPARTMENT EMPLOYEE COLUMNS ROW ?>
        		</div>
    		</div> <!-- END STAFF LIST -->
			<?php if(isset($staffshown) && $staffshown != 0 && $staffcount > $staffshown){ ?>
			<div class="viewMoreWrapper">
				<div class="ve-valign-middle" id="viewMore">
					<button class="ve-primary-button-black ve-viewmore-btn" type="button" id="viewMoreBtn">VIEW MORE</button>
				</div>
			</div>
			<?php } ?>
		</div> <!-- END MAIN CONTAINER -->
			<p class="ve-text-center ve-block ve-poweredby"><small>Powered by Vern Eide Marketing</small></p>
	</div> <!-- END STAFF EMBED CONTAINER -->
</div> <!-- END STAFF EMBED WRAP -->
<?php 
/* SETS THE ABOVE HTML TO PHP VARIABLE */
    $htmlcontent = ob_get_clean();
    IF ($type == "script") { 
        /* COMBINE HTML, CSS & JS READY FOR OUTPUT */
        $scripthtml = $initaldependencies .' '. $htmlcontent;
        echo 'document.write('.json_encode(veMinifyHtml($scripthtml)).');';
    } else {
		get_header();
        echo $jquerylink;
        echo $htmljs;
		echo $listingpgtitle;
		echo $htmlcontent;
		get_footer();
    } 
}

?>