<?php
global $type, $staff, $debug, $displayratio, $displayid, $displaytype, $displayprimcolor, 
       $displaytitle, $birthdaydisplay, $newpromotiondisplay, $newmonthsprior, 
       $comparedatemonthyear, $displaylogo, $staffcount, $staffshown, $staffperrow, $staffnum, 
       $group, $internal, $displayseccolor, $infodisplayed, $post_type, $jquerylink, 
       $htmljs, $initaldependencies, $showloclogo, $displayrowheight;

if ($type == "JSON" || $type == "json"){
	$cached_output_json = get_transient('display_'.$displayid.'_output_json_transient');

	if ($cached_output_json !== false) {
		// USE CACHE DATA
		$output = $cached_output_json;
	} else {
		/* OUTPUT JSON */
		$output = json_encode( $staff );
		set_transient('display_'.$displayid.'_output_json_transient', $output, 12 * 60 * 60); // Lasts for 12 hours
		echo $output;
	}

} else {
	
// NON-JSON REQUEST
$htmlcontent;
	
// CACHED DATA
if($type == "script"){
	$cached_output_script = get_transient('display_'.$displayid.'_output_script_transient');
	if ($cached_output_script !== false) {
		// USE CACHE DATA
		$htmlcontent = $cached_output_script;
	}
} else {
	$cached_output = get_transient('display_'.$displayid.'_output_transient');
	if ($cached_output !== false) {
		// USE CACHE DATA
		$htmlcontent = $cached_output;
	}
}

if(empty($htmlcontent)){
	/* START GENERATING HTML FOR VARIABLE */		
	ob_start();

		// GENERATE CODE FOR SCRIPT EMBED
		if ($type == "script"){
			Header("content-type: application/x-javascript");
		}

		if ($debug == true){
			console_log("Current date/time: ". date("Y-m-d h:i:sa"));
		}

	echo '<style>.bg-primary {background: ' . $displayprimcolor . ' !important;}</style>';
	?>
	<!-- STAFF DISPLAY WRAP -->
	<div id="veStaffDisplay" class="ve ratio ratio-<?php echo $displayratio ?> velist-<?php echo $displayid ?>" data-list-type="<?php echo $displaytype ?>" data-list-id="<?php echo $displayid ?>">
		<div class="ve-display-container">
		<!-- DISPLAY TITLE -->
		<div class="ve-display-title-bar">
			<div class="skew-background ve-border-thick-right-black" style="background-color: <?php echo $displayprimcolor ?>"></div>
			<div class="content ve-d-flex ve-align-items-center">
				<div class="ve-display-title-wrap">
				<?php echo $displaytitle ?>
				<?php if($birthdaydisplay){ //SHOW IF BIRTHDAY LIST ?>
					<?php if ($type == "script") { echo '<h3>'.$displaytitle.'</h3>'; }?>
				<?php } elseif ($newpromotiondisplay){ ?>
					<?php if ($type == "script") { echo '<h3>'.$displaytitle.'</h3>'; }?>
					<?php if($newmonthsprior != 0){ ?>
						<h5 class="dates-shown-title"><i><?php echo $comparedatemonthyear . ' to ' . date('F Y'); ?></i></h5>
					<?php } else { ?>
						<h5 class="dates-shown-title"><i><?php echo date('F Y'); ?></i></h5>
					<?php } ?>
				<?php } ?>
				</div>
				<?php
				if( !empty( $displaylogo ) ): ?>
					<div class="ve-display-logo-wrap">
						<img src="<?php echo esc_url($displaylogo['url']); ?>" alt="<?php echo esc_attr($displaylogo['alt']); ?>" width="auto" height="100">
					</div>
				<?php endif; ?>
			</div>
		</div>
					
		<?php 
		// Check if $staff has values to display, if not show no results on screen.
		if (empty($staff)){?>
			<!-- MAIN CONTAINER -->
			<div class="container-fluid d-flex justify-content-center align-items-center" data-display-type="<?php echo $displaytype ?>" style="background-color: white;">
				<div class="text-center">
					<h1>No Results Found</h1>
					<p>Please check back later.</p>
				</div>
			</div>
		<?php }else{ ?>
			<!-- MAIN CONTAINER -->
			<div class="container-fluid align-content-center ve-clear-fix ve-text-left ve-col-xs-12" data-display-type="<?php echo $displaytype ?>" style="background-color: white;">
			<!-- STAFF DISPLAY GRID -->
				<div class="ve-row ve-pad-none employee-list" style="margin: auto;">
						<?php /** EMPLOYEE LOOP **/

						foreach ($staff as $k => $v) {
							// SKIP EMPLOYEE IF DOESN'T PASS SIMPLE LIST FILER
							$displayvisible = $v["display_visible"];
							if ($displayvisible == FALSE || $staffcount >= $staffshown){
								continue; //SKIP TO NEXT
							}

							//Generate Count of Staff to be Displayed
							$staffcount++;
						}

						// Set Profile Card Size Based on Count Displayed
						$staffcountsizes = array(
							1  => 've-col ve-display-1 ve-display-1-row',
							2  => 've-col ve-display-2 ve-display-1-row',
							3  => 've-col ve-display-3 ve-display-1-row',
							4  => 've-col ve-display-4 ve-display-1-row',
							5  => 've-col ve-display-5 ve-display-1-row',
							6  => 've-col ve-display-6 ve-display-1-row',
							7  => 've-col ve-display-6 ve-display-2-rows',
							8  => 've-col ve-display-6 ve-display-2-rows',
							9  => 've-col ve-display-6 ve-display-2-rows',
							10 => 've-col ve-display-6 ve-display-2-rows',
							11 => 've-col ve-display-6 ve-display-2-rows',
							12 => 've-col ve-display-6 ve-display-2-rows'
						);

						if ($staffcount <= 12) {
							$staffcardcss = $staffcountsizes[$staffcount];
						} else {
							$staffcardcss = 've-col-md-2 ve-display-6 ve-display-2-rows';
						}

						// If display row height is normal, replace 1-row with 2-rows
						if (isset($displayrowheight) && $displayrowheight === 'normal') {
							$staffcardcss = str_replace('ve-display-1-row', 've-display-2-rows', $staffcardcss);
						}

						// Set CSS profiles per row
						$staffperrowsizes = array(
							6 => 've-6-per-row',
							5 => 've-5-per-row',
							4 => 've-4-per-row',
						);

						if (in_array($staffperrow, [6, 5, 4])) {
							$staffperrowcss = $staffperrowsizes[$staffperrow];
						} else {
							$staffperrowcss = 've-6-per-row'; // Default class
						}
	
						// Append the additional string with a space
						$staffcardcss .= ' ' . $staffperrowcss;

						foreach ($staff as $k => $v) {
							// SKIP EMPLOYEE IF DOESN'T PASS SIMPLE LIST FILER
							$displayvisible = $v["display_visible"];
							if ($displayvisible == FALSE || $staffnum >= $staffshown){
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
							$location_id = $v["location_id"];
							$locationsall = $v["locations_listed"];
							$photourl = $v["photo_sized"];
							$intinfo = $v["int_info"];
							$customizations = $v["customizations"];
							$stafftags = $v["tags"];

							if($displaytype == 'birthday' || $displaytype == 'newpromo'){
							//INTERNAL INFO AND VARS
								foreach ($intinfo as $k => $v) {
									$contactinfo = $v["contact_info"];
									$birthdayinfo = $v["birthday"];
									$newpromoinfo = $v["newpromo"];

								};
							};

							// Staff Card
								get_template_part( 
										'template-parts/display-staffcard', 
										null, 
										array( 
											'embedloc' => array(
														'group' => $group,
														'internal' => $internal,
											),
											'type' => array(
														'type' 	   => $displaytype,
														'birthday' => $birthdaydisplay,
														'newpromo' => $newpromotiondisplay,
											),
											'data' 	   => array(
														'staffcountnum'		=> $staffcount,
														'staffshown'		=> $staffshown,
														'birthdayinfo'		=> $birthdayinfo,
														'newpromoinfo'		=> $newpromoinfo,
														'stafftags'			=> $stafftags,
														'location' 			=> $location,
														'location_id'		=> $location_id,
														'locationsall' 		=> $locationsall,
														'deptname' 			=> $department,
														'name' 				=> $name,
														'fname' 			=> $fname,
														'photourl'			=> $photourl,
														'staffid'			=> $staffid,
														'title'				=> $title,
											),
											'settings'	=> array(
														'primarycolor' => $displayprimcolor,
														'secondarycolor' => $displayseccolor,
														'showloclogo' => $showloclogo,
														'customizations' => $customizations,
														'staffcardcss'	=> $staffcardcss,
														'infodisplayed'	=> $infodisplayed,
											)
										)
									); 
								$staffnum++;

						} //END EMPLOYEE LOOP ?>
					</div>
			</div> <!-- END MAIN CONTAINER -->
		<?php } // END IF EMPTY STAFF?>
			<p class="ve-text-center ve-block ve-poweredby">Powered by Vern Eide Marketing</p>
		</div> <!-- END DISPLAY CONTAINER -->
	</div> <!-- END DISPLAY WRAP -->
	<?php
	$htmlcontent = ob_get_clean();
	
	// STORE CACHE DATA
	if ($type == "script") { 
		/* COMBINE HTML, CSS & JS READY FOR OUTPUT */
		$scripthtml = $initaldependencies .' '. $htmlcontent;
		$output = json_encode(veMinifyHtml($scripthtml));
		set_transient('display_'.$displayid.'_output_script_transient', $output, 12 * 60 * 60); // Lasts for 12 hours
	} else {
		$output = $htmlcontent;
		set_transient('display_'.$displayid.'_output_transient', $output, 12 * 60 * 60); // Lasts for 12 hours
	}
// END HTML CONTENT
} else { 
	// SET OUTPUT TO CACHED HTMLCONTENT
	$output = $htmlcontent;
}
	
/* SETS THE ABOVE HTML TO PHP VARIABLE */
    if ($type == "script") { 
        $scripthtml = $initaldependencies .' '. $htmlcontent;
        echo 'document.write('.$output.');';
    } elseif ($post_type == 'display') {
		// Local Site Viewing (Admin View)
		//Ve_Staff_Public::ve_staff_dequeue_scripts();
        get_header();
        echo $jquerylink;
		echo $output;
        echo $htmljs;
		get_footer();
    } else {
		get_header();
        echo $jquerylink;
        echo $htmljs;
		echo $displaytitle;
		echo $output;
		get_footer();
    } 
	
} // END NON-JSON REQUEST