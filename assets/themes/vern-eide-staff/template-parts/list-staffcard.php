<?php 
/** STAFF CARD **/
// ARGS
$deptstaff = $args['data']['staff'] ?? [];
$deptname = $args['data']['dept'] ?? '';
$internal = $args['type']['internal'] ?? false;
$group = $args['type']['group'] ?? false;
$debug = $args['settings']['debug'] ?? false;
$rowcolumns = $args['settings']['rowcolumns'] ?? 2.5;
$showbios = $args['settings']['showbios'] ?? false;
$showaptsbtn = $args['settings']['showaptsbtn'] ?? false;
$showfullname = $args['settings']['showfullname'] ?? false;
$showfulllastname = $args['settings']['showfulllastname'] ?? false;
$webvisiblebypass = $args['settings']['webvisiblebypass'] ?? false;


global $showloclogo;

if(isset($rowcolumns)){
	if($rowcolumns == 3){
		$rowcolumnsclass = 've-col-xl-4 ve-col-lg-4 ve-col-md-4 ve-col-sm-6 ve-col-xs-6';
	} elseif ($rowcolumns == 4){
		$rowcolumnsclass = 've-col-xl-3 ve-col-lg-3 ve-col-md-3 ve-col-sm-6 ve-col-xs-6';
	} elseif ($rowcolumns == 6){
		$rowcolumnsclass = 've-col-xl-2 ve-col-lg-2 ve-col-md-3 ve-col-sm-6 ve-col-xs-6';
	} else {
		$rowcolumnsclass = 've-col-xl-2-5 ve-col-lg-2-5 ve-col-md-3 ve-col-sm-6 ve-col-xs-6';
	}
} else {
	$rowcolumnsclass = 've-col-xl-2-5 ve-col-lg-2-5 ve-col-md-3 ve-col-sm-6 ve-col-xs-6';
}

// GLOBAL VARS
global $staffCardNum, $listid, $listingtype, $listingmodals; 

// LIST PAGE SECURITY TOKEN (USED IN VCARD LINKS)
$listtoken = get_staff_list_page_token($listid);

/** EMPLOYEE LOOP **/
foreach ($deptstaff as $k => $v) {
	// SKIP EMPLOYEE IF WEB VISIBLE FALSE BUT NOT INTERNAL
	$webvisible = $v["website_visible"];
	$webvisiblestatus = $webvisible;
	if ($internal && $webvisiblestatus == TRUE){
		$webvisibleclass = 'webvisible';
	} else {
		$webvisibleclass = '';
	}
	if (is_null($webvisible) || $internal || $webvisiblebypass) {
		$webvisible = TRUE; //SET INITIAL STAFF WITH NULL TO TRUE TO MAKE VISIBLE
	}
	if ($webvisible == FALSE){
		continue; //SKIP TO NEXT
	}

	// CONTINUED LOOP
	// RESET VARS
	unset($photourl); 

	//SET VARS
	$staffid = $v["id"];
	$name = $v["full_name"];
	$fname = $v["first_name"];
	$lname = $v["last_name"];
	$title = $v["title"];
	$department = $v["department"];
	$location = $v["location"];
	$location_id = $v["location_id"];
	$locationsall = $v["locations_listed"];
	$stafftags = $v["tags"];
	$photourl = $v["photo_sized"];
	if(empty($internal)){
		$email = $v["email"];
		$phone = preg_replace('/\D+/', '', $v["phone"]);
		$phonetype = $v["phone_type"];
		$phoneformatted = substr($phone, 0, 3).'-'.substr($phone, 3, 3).'-'.substr($phone, 6, 4);
	}
	$intinfo = $v["int_info"];

	$emplocationslisted = array();
		foreach ($locationsall as $emplocation){
			$emplocationslisted[] = str_pad($emplocation, 3, '0', STR_PAD_LEFT); //Makes the locations at least 3 characters in length for search
		}

	// Biography
	if($showbios){
		$empbio = $v["bio"];
	}
	// Schedule Appointment
	if($showaptsbtn){
		$appointmentsurl = $v["appointments_url"];
	}

	//INTERNAL
	if($internal){
		foreach ($intinfo as $k => $v) {
			//Unset vars from previous employee
			unset($contactinfo);
			unset($birthdayinfo);
			unset($phoneext);
			unset($dataext);
			unset($phoneprefix);
			unset($phoneother);
			unset($addphones);
			unset($yearsofservice);

			//Internal Array VARS
			$contactinfo = $v["contact_info"];
			$birthdayinfo = $v["birthday"];
			$yearsofservice = $v["yearsofservice"];
			$location_data = staff_get_location_data($location_id);

			// CONTACT INFO VARS
			$phoneprefix = $contactinfo["office_phone_prefix"];
			$phoneext = $contactinfo["office_extension"];
			$phoneother = $contactinfo["office_other_direct"];
			$addphones = $contactinfo["additional_numbers"];

			if($phoneext){
				$dataext = 'data-ext="'.$phoneext.'"';
			}

			unset($phoneformatted); //RESET VAR DURING LOOP
			if (isset($phoneprefix) || isset($phoneother)){
					if($phoneother){
							$phone = preg_replace('/\D+/', '', $phoneother);
					} else {
							$phone = preg_replace('/\D+/', '', $phoneprefix) . $phoneext;
					}
							$phoneformatted = substr($phone, 0, 3).'-'.substr($phone, 3, 3).'-'.substr($phone, 6, 4);
			};

			unset($phonecellformatted); //RESET VAR DURING LOOP
			unset($cellphone);
			$cellphonevisible = $contactinfo["office_cell_phone_visible"];
			if($cellphonevisible == 1){
					$cellphone = $contactinfo["office_cell_phone"];
					if(isset($cellphone)){
						$phonecell = preg_replace('/\D+/', '', $cellphone);
								$phonecellformatted = substr($phonecell, 0, 3).'-'.substr($phonecell, 3, 3).'-'.substr($phonecell, 6, 4);
						};
					};

					$email = $contactinfo["office_email"];

					// BIRTHDAY INFO VARS
					$curyear = date("Y");
					$birthday = $curyear."-".$birthdayinfo["birthday_month"]."-".$birthdayinfo["birthday_day"];
					$birthdaydate =  date('Y-m-d',strtotime($birthday));

					if($birthdaydate == date('Y-m-d')){
						$birthdaytoday = TRUE;
					} else {
						$birthdaytoday = FALSE;
					}

		};
					
				// INTERNAL CARD CODE 
?>
				<div class="<?php echo $rowcolumnsclass ?> ve-margin-bottom-xl employee-tile ve-width-100 dept<?php echo $deptid ?>" data-card-num="<?php echo $staffCardNum;?>" data-loc="<?php echo htmlspecialchars(json_encode($emplocationslisted), ENT_QUOTES, 'UTF-8'); ?>" data-dept="<?php echo $deptname ?>" data-dept-id="<?php echo $deptid ?>" data-employee-name='<?php echo htmlspecialchars(strtoupper($name), ENT_QUOTES, "UTF-8"); ?>' <?php if(isset($dataext)){echo $dataext;} ?> data-tags="<?php echo htmlspecialchars(json_encode(array_column($stafftags, 'id')), ENT_QUOTES, 'UTF-8'); ?>">
					<?php 
					if ($stafftags) {
						// Filter tags where 'card_visible' equals 1
						$visible_tags = array_filter($stafftags, function($tag) {
							return isset($tag['card_visible']) && $tag['card_visible'] == '1';
						});
						// Extract 'name' values from each sub-array in visible tags
						$tagnames = array_column($visible_tags, 'name');
						// Join the names into a comma-separated string
						$stafftagname = implode(', ', $tagnames);
						// Display the tag names
						if (count($tagnames) > 0) {
							echo '<span class="ve-badge bg-primary">' . $stafftagname . '</span>';
						}
					}
					?>
					<div class="ve-col-sm-12 ve-rounded-3 border-radius-20 ve-pad-none ve-bg-grey">
						<div class="ve-pad-left-none ve-pad-right-none border-thick border-radius-20">
							<div class="ve-pad-left-none ve-pad-right-none employee-wrapper">
								<div class="employee-tile-img ve-text-center ve-valign-top ve-margin-bottom-sm">
									<?php
										  $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

										  // Internal lists should never “stall” in iframe — always give them a real src.
										  // Public lists keep your data-src lazy pattern for embed performance.
										  $is_internal_list = !empty($internal);

										  // Still keep eager for first N on public (LCP friendly)
										  $eager_public = ($staffCardNum <= 20);

										  if ($is_internal_list) {
											// ✅ Internal: real src always, browser-native lazy does the rest
											?>
											<img
											  alt="<?php echo esc_attr($name . ' at ' . $location); ?>"
											  src="<?php echo esc_url($photourl); ?>"
											  loading="lazy"
											  decoding="async"
											>
											<?php
										  } else {
											// ✅ Public: your existing embed-safe lazy behavior
											if ($eager_public) { ?>
											  <img
												alt="<?php echo esc_attr($name . ' at ' . $location); ?>"
												src="<?php echo esc_url($photourl); ?>"
												loading="eager"
												decoding="async"
											  >
											<?php } else { ?>
											  <img
												alt="<?php echo esc_attr($name . ' at ' . $location); ?>"
												src="<?php echo esc_url($placeholder); ?>"
												data-src="<?php echo esc_url($photourl); ?>"
												class="ve-lazy"
												loading="lazy"
												decoding="async"
											  >
											<?php }
										  }
										?>
								</div>
								<div class="employee-details-wrapper">
									<div class="employee-details ve-pad-top-md ve-pad-bottom-none ve-m-2" >
										<h3 class="employee-name ve-bolder ve-font-16 ve-black ve-block"><?php echo $name ?></h3>
										<span class="employee-title ve-font-14 ve-black ve-block"><?php echo $title ?></span>
										<?php if ($group || $internal){?>
										<hr>
										<span class="employee-location ve-font-12 ve-black ve-block" style="padding-top: 5px;"><i><?php echo $location ?></i></span>
										<?php 
										if ($showloclogo == TRUE){
											if (!empty($location_data['brand_logo'])) { ?>
													<div class="location-logo <?php echo 'location_'.$location_id;?>">
														<img src="<?php echo $location_data['brand_logo']['sizes']['medium']; ?>" alt="<?php echo $location ?>" style="">
													</div>
												<?php 
											} // End Location Logo
										} //End Show Location Logo
										} //End Group/Internal
										?>
									</div> <!-- EMP DETAILS -->
								</div> <!-- EMP DETAILS WRAPPER -->
									
								<?php 
								//SHOW INTERNAL INFO 
								if($internal){ ?>
									<div class="employee-internal-details ve-md-block ve-text-center">
									<?php if(!empty($empbio)){$contactinfostyle = ' style="margin-bottom: 3px;" ';} else {unset($contactinfostyle);} ?>
									<button class="ve-primary-button-black ve-contact-info-btn"<?php echo $contactinfostyle ?>type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $staffid ?>" aria-expanded="false" aria-controls="collapse<?php echo $staffid ?>" >View More Info</button>
										<div id="collapse<?php echo $staffid ?>" class="collapse ve-pad-none">
											<div class="ve-pad-md ve-contact-wrap ve-text-left">
												<?php if (strlen($phone) >= 10) { //SHOW IF DIRECT PHONE LISTED ?>
													<span class="ve-block">
														<span class="ve-bolder">Direct: </span><a target="_self" href="tel:<?php echo $phone ?>" class="" style="text-decoration: none !important;"><?php echo $phoneformatted ?></a>
													</span>
												<?php } ?>
												<?php if ($phoneext) { //SHOW IF EXTENSION LISTED ?>
													<span class="ve-block">
														<span class="ve-bolder">Ext: </span><?php echo $phoneext?>
													</span>
												<?php } ?>
												<?php
												// Initialize the array for additional phone numbers
												$additionalPhones = [];

												// ADDITIONAL PHONES LOOP
												unset($addphonetype, $addphonelabel, $addphonenumber, $addphoneext);

												if ($addphones) {
													foreach ($addphones as $k => $v) {
														$addphonetype = $v["type"];
														$addphonelabel = $v["phone_label"];
														$addphonenumber = $v["phone"];
														$addphoneext = $v["extension"];

														if ($addphonetype == 'Extension') {
															// Handle Extensions (for display purposes, if needed)
															?>
															<span class="ve-block">
																<span class="ve-bolder"><?php echo $addphonelabel ?> Ext: </span><?php echo $addphoneext ?>
															</span>
															<?php
														} elseif ($addphonetype == 'Phone') {
															// Handle additional phone numbers
															$addphonenum = preg_replace('/\D+/', '', $addphonenumber); // Remove non-digits
															$addphonenumformatted = substr($addphonenum, 0, 3) . '-' . substr($addphonenum, 3, 3) . '-' . substr($addphonenum, 6, 4); // Format number

															// Add phone details to the additionalPhones array
															$additionalPhones[] = [
																'type' => 'OTHER',
																'number' => $addphonenum
															];

															// Display phone details (optional)
															?>
															<span class="ve-block">
																<span class="ve-bolder"><?php echo $addphonelabel ?>:</span> 
																<br><a target="_self" href="tel:<?php echo $addphonenum ?>" class="" style="text-decoration: none !important;"><?php echo $addphonenumformatted ?></a>
															</span>
															<?php
														}
													}
												}
												?>
												<?php if ($cellphone) { //SHOW IF CELL PHONE LISTED ?>
													<span class="ve-block">
														<span class="ve-bolder">Cell: </span><a target="_self" href="tel:<?php echo $phonecell ?>" class="" style="text-decoration: none !important;"><?php echo $phonecellformatted ?></a>
													</span>
												<?php } ?>
												<?php if ($email) { //SHOW IF EMAIL LISTED ?>
													<span class="ve-block">
														<span class="ve-bolder">Email: </span><a target="_self" href="mailto:<?php echo $email ?>" class="" style="text-decoration: none !important;"><?php echo $email ?></a>
													</span>
												<?php } ?>
												<?php
													if (isset($yearsofservice)) {
														if ($yearsofservice == 0) {
															$service_text = "First Year of Service";
														} elseif ($yearsofservice == 1) {
															$service_text = "1 Year of Service";
														} else {
															$service_text = $yearsofservice . " Years of Service";
														}
														echo '<span class="ve-block years-service" style="text-align: center; font-style: italic;padding-top: 5px;padding-bottom: 5px;">' . esc_html($service_text) . '</span>';
													}
													?>
												<?php
												// Check if at least one phone number is provided
												$hasPhoneNumber = !empty($phone) || !empty($phonecell) || !empty($additionalPhones); ?>
												<?php if ($hasPhoneNumber): ?>
													<a href="<?php echo esc_url(get_permalink($staffid) . '?vcard=download&token=' . $listtoken . '&list_id=' . $listid); ?>" 
													   target="_blank" 
													   class="ve-primary-button-black ve-contact-info-btn">
														Download vCard (Save)
													</a>
												<?php endif; ?>
											</div> <!-- END COLLAPSE CONTENT WRAP -->
										</div> <!-- END COLLAPSE -->
										<?php
										// SHOWS IF BIRTHDAY IS TODAY
										if($birthdaytoday){ ?>
										<div class="ve-birthday">
											<span class="ve-font-15 ve-block ve-boldest" style="">Happy Birthday <?php echo $fname ?>!</span>
										</div>
										<?php } //END BIRTHDAY TODAY ?>
									</div> <!-- END EMP INTERNAL DETAILS -->
								
									<?php // Website Listed Indicator
										if($internal){ ?>
											<div class="cornermark <?php echo $webvisibleclass ?>">
											</div>
									<?php } ?>
								<?php } // END INTERNAL ?>
								
								<?php if(!empty($empbio)){ ?>
									<div class="ve-md-block ve-text-center">
										<button class="ve-primary-button-black ve-contact-info-btn" type="button" data-bs-toggle="vemodal" data-bs-target="#biomodal<?php echo $staffid ?>" aria-expanded="false" aria-controls="biomodal<?php echo $staffid ?>" >About <?php echo $fname ?></button>
									</div>
								<?php } ?>
								
							</div>
						</div>
					</div>
					<?php if(!empty(trim($empbio))){ ?>
						<?php ob_start(); ?>
							<div class="vemodal vefade" id="biomodal<?php echo $staffid ?>" tabindex="-1" role="dialog" aria-labelledby="biomodal<?php echo $staffid ?>Label" aria-hidden="true" data-bs-backdrop="false">
									<div class="vemodal-dialog vemodal-lg vemodal-dialog-centered vemodal-dialog-scrollable" role="document">
										<div class="vemodal-content shadow-lg rounded" style="border-radius: 10px;">
											 <div class="vemodal-header" style="background-color: #373737; color: #fff;">
												<h3 class="vemodal-title" id="biomodal<?php echo $staffid ?>Label" style="color: #fff;">About <?php echo $name ?></h3>
													<button type="button" class="close" data-bs-dismiss="vemodal" aria-label="Close" style="color: #fff;">
														  <span aria-hidden="true">&times;</span>
													</button>
											 </div>
										  <div class="vemodal-body">
											 <div class="ve-row">
											  <div class="ve-col-12 employee-bio-content">
												  <div class="employee-bio-img ve-valign-top ve-float-start">
													  	<img class="ratio ratio-1x1" alt="<?php echo $name ?> at <?php echo $location ?>" src="<?php echo $photourl ?>">
													  </div>
													  <?php echo $empbio; ?>
											  </div>
											</div>
											<div class="employee-bio-vid ve-pad-top-md">
												<?php echo yt_bio_video($staffid); ?>
											</div>
										  </div>
										  <div class="vemodal-footer">
											<button type="button" class="btn btn-secondary" data-bs-dismiss="vemodal">Close</button>
										  </div>
										</div>
								  </div>
							</div>
						<?php $listingmodals .= ob_get_clean();?>
					<?php } ?>
				</div>	
<?php	// PUBLIC CARD CODE
	} else { 
		
		// Show Full Name or First Name
		if($showfullname == FALSE ){
			$name = $fname;
		} elseif ($showfullname == TRUE && $showfulllastname == FALSE){
			$name = $fname.' '.substr($lname, 0, 1).'.';
		}
?>
			
				<div class="<?php echo $rowcolumnsclass ?> ve-margin-bottom-xl employee-tile ve-width-100 dept<?php echo $deptid ?>" data-card-num="<?php echo $staffCardNum;?>" data-loc="<?php echo htmlspecialchars(json_encode($emplocationslisted), ENT_QUOTES, 'UTF-8'); ?>" data-dept="<?php echo $deptname ?>" data-employee-name='<?php echo htmlspecialchars(strtoupper($name), ENT_QUOTES, "UTF-8"); ?>' data-tags="<?php echo htmlspecialchars(json_encode(array_column($stafftags, 'id')), ENT_QUOTES, 'UTF-8'); ?>" <?php if(isset($dataext)){echo $dataext;} ?> >
					<?php 
					if ($stafftags) {
						// Filter tags where 'card_visible' equals 1
						$visible_tags = array_filter($stafftags, function($tag) {
							return isset($tag['card_visible']) && $tag['card_visible'] == '1';
						});
						// Extract 'name' values from each sub-array in visible tags
						$tagnames = array_column($visible_tags, 'name');
						// Join the names into a comma-separated string
						$stafftagname = implode(', ', $tagnames);
						// Display the tag names
						if (count($tagnames) > 0) {
							echo '<span class="ve-badge bg-primary">' . $stafftagname . '</span>';
						}
					}
				
					?>
					<div class="ve-col-sm-12 ve-rounded-3 border-radius-20 ve-pad-none ve-bg-grey">
						<div class="ve-pad-left-none ve-pad-right-none border-thick border-radius-20">
							<div class="ve-text-left ve-col-sm-12 ve-pad-left-none ve-pad-right-none employee-wrapper">
								<div class="employee-tile-img ve-text-center ve-valign-top ve-margin-bottom-sm">
									<?php
										  $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

										  // Internal lists should never “stall” in iframe — always give them a real src.
										  // Public lists keep your data-src lazy pattern for embed performance.
										  $is_internal_list = !empty($internal);

										  // Still keep eager for first N on public (LCP friendly)
										  $eager_public = ($staffCardNum <= 20);

										  if ($is_internal_list) {
											// ✅ Internal: real src always, browser-native lazy does the rest
											?>
											<img
											  alt="<?php echo esc_attr($name . ' at ' . $location); ?>"
											  src="<?php echo esc_url($photourl); ?>"
											  loading="lazy"
											  decoding="async"
											>
											<?php
										  } else {
											// ✅ Public: your existing embed-safe lazy behavior
											if ($eager_public) { ?>
											  <img
												alt="<?php echo esc_attr($name . ' at ' . $location); ?>"
												src="<?php echo esc_url($photourl); ?>"
												loading="eager"
												decoding="async"
											  >
											<?php } else { ?>
											  <img
												alt="<?php echo esc_attr($name . ' at ' . $location); ?>"
												src="<?php echo esc_url($placeholder); ?>"
												data-src="<?php echo esc_url($photourl); ?>"
												class="ve-lazy"
												loading="lazy"
												decoding="async"
											  >
											<?php }
										  }
										?>
								</div>
								<div class="employee-details-wrapper">
									<div class="employee-details ve-pad-top-md ve-pad-bottom-none ve-m-2" >
										<h3 class="employee-name ve-bolder ve-font-16 ve-black ve-block"><?php echo $name ?></h3>
										<span class="employee-title ve-font-14 ve-black ve-block"><?php echo $title ?></span>
										<?php if ($group || $internal){?>
										<hr>
										<span class="employee-location ve-font-12 ve-black ve-block" style="padding-top: 5px;"><i><?php echo $location ?></i></span>
										<?php 
										if ($showloclogo == TRUE){
											$locationlogo = get_field('location_brand_logo', 'location_'.$location_id);
												if($locationlogo){ ?>
													<div class="location-logo <?php echo 'location_'.$location_id;?>">
														<img src="<?php echo $locationlogo['sizes']['medium']; ?>" alt="<?php echo $location ?>" style="">
													</div>
												<?php 
												} // End Location Logo
										} //End Show Location Logo
										} //End Group/Internal
										?>
									</div> <!-- END EMP DETAILS -->
								</div> <!-- END EMP DETAILS WRAPPER -->
								
						  <?php //CONTACT SECTION ?>
								<div class="ve-pad-md ve-pad-left-sm ve-pad-right-sm ve-text-center ve-contact-wrap">
									<?php 
									// PHONE NUMBER IS PROVIDED
									if ($phone && $phonetype <> "--Not Listed" && !empty($phonetype)) {  
										// If the phone type provided is mobile, then a text button will be shown.
										if ($phonetype == "Mobile") {
											$phonebtnclass = "ve-mobile-phone";
											$phonebtnwrapclass = "ve-mobile-phone-wrap";
										}
									?>
									<?php //MOBILE CONTACT SECTION ?>
									<div class="d-block d-md-none ve-text-center">
										<div class="<?php echo $phonebtnwrapclass ?>">
											<a target="_self" href="tel:<?php echo $phone ?>" class="ve-primary-button-black ve-cta-btn <?php echo $phonebtnclass ?>" style="text-decoration: none !important;">Call</a>
											<?php 
											// If the phone type provided is mobile, then a text button will be shown.
											if ($phonetype == "Mobile") { ?>
											<a target="_self" href="sms:<?php echo $phone ?>?body=Hi%20there%20<?php echo $fname ?>%20this%20is" class="ve-primary-button-black ve-cta-btn <?php echo $phonebtnclass ?>" style="text-decoration: none !important;">Text</a>
											<?php } // END IF MOBILE PHONE ?>
										</div>
									</div>
									<?php //DESKTOP CONTACT SECTION ?>
									<div class="d-none d-md-block ve-text-center">
										<a target="_self" href="tel:<?php echo $phone ?>" class="ve-primary-button-black ve-cta-btn" style="text-decoration: none !important;"><?php echo $phoneformatted ?></a><br>
									</div>
									<?php } // END IF PHONE PROVIDED ?>
									<?php 
									if(!empty($appointmentsurl)){ 
										// Check if the URL already has query parameters
										if (strpos($appointmentsurl, '?') !== false) {
											// URL already has query parameters, so append with '&'
											$aptstrackingurl = $appointmentsurl . "&utm_source={{sitedomain}}&utm_medium=Staff%20Directory%20Button";
										} else {
											// URL does not have query parameters, so add with '?'
											$aptstrackingurl = $appointmentsurl . "?utm_source={{sitedomain}}&utm_medium=Staff%20Directory%20Button";
										}
										?>
										<div class="d-md-block ve-text-center">
											<a target="_self" href="<?php echo $aptstrackingurl ?>" class="ve-primary-button-black ve-contact-info-btn appointment" style="text-decoration: none !important;">Book Appointment</a>
										</div>
									<?php } // END IF APPOINTMENTS URL BUTTON ?>
									
									<?php if(!empty($empbio)){ ?>
									<div class="ve-md-block ve-text-center">
										<button class="ve-primary-button-black ve-contact-info-btn" type="button" data-bs-toggle="vemodal" data-bs-target="#biomodal<?php echo $staffid ?>" aria-expanded="false" aria-controls="biomodal<?php echo $staffid ?>" >About <?php echo $fname ?></button>
									</div>
								<?php } // END IF BIO BUTTON?>
									
								</div> <!-- END CONTACT WRAP -->
							</div> <!-- EMP WRAPPER -->
						</div>
					</div>
					<?php if(!empty(trim($empbio))){ ?>
						<?php ob_start(); ?>
							<div class="vemodal vefade" id="biomodal<?php echo $staffid ?>" tabindex="-1" role="dialog" aria-labelledby="biomodal<?php echo $staffid ?>Label" aria-hidden="true" data-bs-backdrop="true">
									<div class="vemodal-dialog vemodal-lg vemodal-dialog-centered vemodal-dialog-scrollable" role="document">
										<div class="vemodal-content shadow-lg rounded" style="border-radius: 10px;">
											 <div class="vemodal-header" style="background-color: #373737; color: #fff;">
												<h3 class="vemodal-title" id="biomodal<?php echo $staffid ?>Label" style="color: #fff;">About <?php echo $name ?></h3>
													<button type="button" class="close" data-bs-dismiss="vemodal" aria-label="Close" style="color: #fff;">
														  <span aria-hidden="true">&times;</span>
													</button>
											 </div>
										  <div class="vemodal-body">
											 <div class="ve-row">
											  <div class="ve-col-12 employee-bio-content">
												  <div class="employee-bio-img ve-valign-top ve-float-start">
													  	<img class="ratio ratio-1x1" alt="<?php echo $name ?> at <?php echo $location ?>" src="<?php echo $photourl ?>">
													  </div>
													  <?php echo $empbio; ?>
											  </div>
											</div>
											<div class="employee-bio-vid ve-pad-top-md">
												<?php echo yt_bio_video($staffid); ?>
											</div>
										  </div>
										  <div class="vemodal-footer">
											<button type="button" class="btn btn-secondary" data-bs-dismiss="vemodal">Close</button>
										  </div>
										</div>
								  </div>
							</div>
							<?php $listingmodals .= ob_get_clean();?>
						<?php ; ?>
					<?php } // END IF BIO MODAL?>
			</div>  <!-- END EMPLOYEE TILE -->
<?php } //END IF INTERNAL OR PUBLIC 
	// Increase the value of the Staff Card by 1
	$staffCardNum++;
} //END EMPLOYEE LOOP ?>