<?php
global $staff, $listid;
//delete_transient('simplelist_'.$listid.'_output_internal_transient');
$cached_output = get_transient('simplelist_'.$listid.'_output_internal_transient');

if ($cached_output !== false) {
    // USE CACHE DATA
    $staff = $cached_output;
} else {

// GENERATE OUTPUT AND STORE CACHE
global $debug, $listinglocations, $locationtaxonomy, $notvisible, $internal, $birthdaylist, $newpromotionlist, $newmonthsprior, $bddaysbeforetoday, $bddaysaftertoday, $comparedatemonthyear;

/* STAFF QUERY ARGS */
if($debug){
	console_log('Locations Listed:');
	console_log($listinglocations);
}
        $args = array( 
            'post_type' => 'staff', 
            'post_status' => 'publish', 
            'nopaging' => true,
            'tax_query' => array(
                array(
                    'taxonomy' => $locationtaxonomy,
                    'field' => 'term_id',
                    'terms' => $listinglocations // The term id of the locations Ex: "Vern Eide Honda is 2"
                    )
            ),
			'meta_query'=> array(
				'relation' => 'OR', 
                        array(
                              'key'     => 'website_visible',
                              'value'   => '1',
                              'compare' => '=',
                              ),
				        array(
                              'key'     => 'website_visible',
                              'value'   => 'null',
                              'compare' => 'NOT EXISTS',
                              ),
						array(
                              'key'     => 'website_visible',
                              'value'   =>  $notvisible, //IF INTERNAL THIS VALUE WILL BE 0 TO INCLUDE THOSE WHO HAVE WEBSITE VISIBLE SET TO NOT VISIBLE
                              'compare' => '=',
                              ),
			),
        );
        $query = new WP_Query( $args ); // $query is the WP_Query Object
        $posts = $query->get_posts();   // $posts contains the post objects
        $staff = array();

        foreach( $posts as $post ) {    // Pluck the id and title attributes
			/* Reset Post Vars */
			unset($photo); 
			unset($photosized);
			unset($photofull);
			unset($phototitle);
			unset($photoalt);
			unset($photocaption);
			
			
            /* Custom Post Field Info */
            $first_name = get_field('first_name');
            $last_name = get_field('last_name');
            $full_name = $first_name . ' ' . $last_name;
            $department = get_field('department');
            $location = get_field('primary_location');
			$locationsall = get_field('listed_locations');
			$webvisible = get_field('website_visible');
			$title_term = get_field('title');
            $title = $title_term && !is_wp_error($title_term) ? $title_term->name : '';
            $phone = get_field('phone');
            $phonetype = get_field('phone_type');
            $email = get_field('email');
            $photo = get_field('photo');
                if ($photo){
                    // Image variables.
                    $photosize = 'medium';
					$photosized = $photo['sizes'][ $photosize ];
                    $photofull = $photo['url'];
                    $phototitle = $photo['title'];
                    $photoalt = $photo['alt'];
                    $photocaption = $photo['caption'];
                } else {
                    $photofull = get_stylesheet_directory_uri() . '/inc/assets/img/default-no-photo.jpg';
					$photosized = get_stylesheet_directory_uri() . '/inc/assets/img/default-no-photo.jpg';
                }
			
			if($internal){
			$int_info = array();
			$intcontactinfo = get_field('office_contact_info');	
			$intbirthdayinfo = get_field('birthday');
				//BIRTHDAY LISTS
						If($birthdaylist){
							// BIRTHDAY VARS
							// FILTERS
							$prevyear = date("Y")-1;
							$curyear = date("Y");
							$nextyear = date("Y")+1;
							$dateforward = date('Y-m-d', strtotime("+$bddaysaftertoday day"));
							$datebackward = date('Y-m-d', strtotime("$bddaysbeforetoday day"));
							
							// BIRTHDAY INFO
							$bdaymonth = $intbirthdayinfo['birthday_month'];
							$bdayday = $intbirthdayinfo['birthday_day'];
							$bdayprevyr = $prevyear."-".$bdaymonth."-".$bdayday;
							$bdayprevyrdate =  date('Y-m-d',strtotime($bdayprevyr));
							$bdaycuryr = $curyear."-".$bdaymonth."-".$bdayday;
							$bdaycuryrdate =  date('Y-m-d',strtotime($bdaycuryr));
							$bdaynextyr = $nextyear."-".$bdaymonth."-".$bdayday;
							$bdaynextyrdate =  date('Y-m-d',strtotime($bdaynextyr));
							$bdaymerge = $bdaymonth . "/" . $bdayday . "/" . $curyear;
							$bdayformatted = date('F d',strtotime($bdaymerge));
							
							// BIRTHDAY CHECK IF DATE IS BETWEEN FILTERS
							if(($bdaycuryrdate >= $datebackward) && ($bdaycuryrdate <= $dateforward)){
								$birthdaymatch = TRUE;
								$birthdaydate = $bdaycuryrdate;
							} elseif(($bdaynextyrdate >= $datebackward) && ($bdaynextyrdate <= $dateforward)){
								$birthdaymatch = TRUE;
								$birthdaydate = $bdaynextyrdate;
							} elseif(($bdayprevyrdate >= $datebackward) && ($bdayprevyrdate <= $dateforward)){
								$birthdaymatch = TRUE;
								$birthdaydate = $bdayprevyrdate;
							} else {
								$birthdaymatch = FALSE;
							}
							
							// BIRTHDAY DATA ADDITIONAL TO ARRAY
							$birthdaydetail = array(
											'birthday_date' => $birthdaydate,
											'birthday_month_day' => $bdayformatted,
											'birthday_filter_match' => $birthdaymatch,
							);
						
							$intbirthdayinfo += $birthdaydetail; //ADDS THE DETAIL TO END OF INFO ARRAY
						}
				//NEW HIRE OR NEW PROMOTION LIST
						If($newpromotionlist){
							// NEW HIRE OR NEW PROMOTION VARS
							// FILTERS
							$prevyear = date("Y")-1;
							$curyear = date("Y");
							$nextyear = date("Y")+1;
							$monthsbackward = date('Y-m-d', strtotime("$newmonthsprior months"));
							
							// NEW HIRE OR NEW PROMOTION INFO
							$staffpublisheddate = date(get_the_date( 'Y-m-d' ));
							$staffstartdate = get_field('start_date');
							if(empty($staffstartdate)){$staffstartdate = $staffpublisheddate;};
							$staffpromotiondate = date('Y-m-d', strtotime(get_field('promotion_date')));
							
							// Date to compare
							$comparedate = date('Y-m-01', strtotime("$newmonthsprior months")); //Gets first day of month from current month
							$comparedatemonthyear = date('F Y', strtotime("$newmonthsprior months"));
						
							// CHECK IF START DATE OR PROMOTION DATE IS BETWEEN FILTERS
							if($staffstartdate >= $comparedate && $staffstartdate <= date('Y-m-d')){
								$newpromomatch = TRUE;
								$newpromodate = $staffstartdate;
								$newpromotype = 'new';
							} elseif($staffpromotiondate >= $comparedate && $staffpromotiondate <= date('Y-m-d')){
								$newpromomatch = TRUE;
								$newpromodate = $staffpromotiondate;
								$newpromotype = 'promotion';
							} else {
								$newpromomatch = FALSE;
							}
							
							// BIRTHDAY DATA ADDITIONAL TO ARRAY
							$newpromodetail = array(
											'newpromo_type' => $newpromotype,
											'newpromo_date' => $newpromodate,
											'newpromo_filter_match' => $birthdaymatch,
							);
						};
				
			$int_info[] = array(
								'contact_info' => $intcontactinfo,
								'birthday' => $intbirthdayinfo,
								'newpromo' => $newpromodetail,
			);
			}
			
			// SIMPLE LISTING FILTER RESULTS - USED TO SHOW STAFF ON SIMPLE LISTING PAGES	
			if($birthdaymatch || $newpromomatch){
				$simplelistingvisible = TRUE;
			} ELSE {
				$simplelistingvisible = FALSE;
			}
			
			// SKIP EMPLOYEES THAT DON'T MATCH CRITERIA SET 
			If(!$simplelistingvisible){
				continue; //SKIP TO NEXT
			}
			
			// SET WHAT DATE FIELD SHOULD BE USED FOR SORT_DATE
			if($birthdaylist){
				$sortdate = $birthdaydate;
			} elseif ($newpromotionlist){
				$sortdate = $newpromodate;
			}
			
			$locationslisted = wp_get_post_terms( $post->ID, 'location', array( 'fields' => 'ids' ) );
			
			$emplocationslisted = array();
			foreach ($locationslisted as $emplocation){
				$emplocationslisted[] = str_pad($emplocation, 3, '0', STR_PAD_LEFT); //Makes the locations at least 3 characters in length for search
			}

            $staff[] = array( 
                              'id' => $post->ID, 
                              'full_name' => $full_name,
                              'first_name' => $first_name,  
                              'last_name' => $last_name, 
                              'department' => $department->name,
                              'department_id' => $department->term_id,
                              'department_desc' => $department->description,
                              'department_order' => $department->term_order,
                              'location' => $location->name,
							  'locations_listed' => $emplocationslisted,
                              'title' => $title,
                              'phone' => $phone,
                              'phone_type' => $phonetype,
                              'email' => $email,
                              'photo_full' => $photofull,
							  'photo_sized' => $photosized,
                              'staff_url' => $post->guid,
							  'int_info' => $int_info,
							  'website_visible' => $webvisible,
							  'simple_listing_visible' => $simplelistingvisible,
							  'sort_date' => $sortdate,

            );
			
        } //END FOR EACH POST (STAFF)

//SORT ARRAY FOR LIST IF BIRTHDAY
// Comparison function
if($birthdaylist){
	function sort_date_compare($element1, $element2) {
		$datetime1 = strtotime($element1['sort_date']);
		$datetime2 = strtotime($element2['sort_date']);
		return $datetime1 - $datetime2;
	} 

	// Sort the staff array 
	usort($staff, 'sort_date_compare');
	
} elseif ($newpromotionlist){
	function sort_date_compare($element1, $element2) {
		$datetime1 = strtotime($element1['sort_date']);
		$datetime2 = strtotime($element2['sort_date']);
		return $datetime2 - $datetime1;
	} 

	// Sort the staff array 
	usort($staff, 'sort_date_compare');
}	// END GENERATE OUTPUT
	
// STORE CACHE
	// Data has expired or doesn't exist, so regenerate the data and update the transient
    set_transient('simplelist_'.$listid.'_output_internal_transient', $staff, 12 * 60 * 60); // Lasts for 12 hours
} // END CACHE CHECK