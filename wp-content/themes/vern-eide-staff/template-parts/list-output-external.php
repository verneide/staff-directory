<?php
global $output, $listid;
$cached_output = get_transient('list_'.$listid.'_output_external_transient');

if ($cached_output !== false) {
    // USE CACHE DATA
    $output = $cached_output;
} else {

// GENERATE OUTPUT AND STORE CACHE
global $output, $dept_taxonomy_terms, $depttaxonomy, $locationtaxonomy, $listinglocations, $notvisible, $showdepartments, $showtitles, $internal, $showbios, $showaptsbtn, $listid, $listingtype;

$output = array(); //SET THE OUTPUT ARRAY

// IF THERE ARE STAFF IN DEPARTMENTS LOOP THROUGH DEPTARTMENTS
if($dept_taxonomy_terms) {
    foreach($dept_taxonomy_terms as $dept_taxonomy_term) {
        /* STAFF QUERY ARGS */
        // First Query for posts with 'sticky_order'
		$args_with_sticky_order = array(
			'post_type' => 'staff',
			'post_status' => 'publish',
			'nopaging' => true,
			"$depttaxonomy" => $dept_taxonomy_term->slug,
			'tax_query' => array(
				array(
					'taxonomy' => $locationtaxonomy,
					'field' => 'term_id',
					'terms' => $listinglocations,
				)
			),
			'meta_query' => array(
				'relation' => 'AND',
						array(
							'key' => 'sticky_order',
							'compare' => 'EXISTS', // Check if 'sticky_order' key exists
						),
						array(
							'key' => 'sticky_order',
							'value' => '', // Check if 'sticky_order' value is not blank
							'compare' => '!=',
							'type' => 'CHAR',
						),
						array(
							'relation' => 'OR',
							array(
								'key' => 'website_visible',
								'value' => '1',
								'compare' => '=',
							),
							array(
								'key' => 'website_visible',
								'value' => 'null',
								'compare' => 'NOT EXISTS',
							),
							array(
								'key' => 'website_visible',
								'value' => $notvisible, //IF INTERNAL THIS VALUE WILL BE 0 TO INCLUDE THOSE WHO HAVE WEBSITE VISIBLE SET TO NOT VISIBLE
								'compare' => '=',
							),
						),
			),
			'orderby' => array(
				'meta_value_num' => 'ASC', // First, order by the numeric value of 'sticky_order'
				'post_name' => 'ASC' // Then, order by post name
			),
			'meta_key' => 'sticky_order', // Specify the meta key for ordering
			'order' => 'ASC', // Order in ascending order
		);

		$query_with_sticky_order = new WP_Query($args_with_sticky_order);
		$posts_with_sticky_order = $query_with_sticky_order->get_posts();

		// Second Query for posts without 'sticky_order'
		$args_without_sticky_order = array(
			'post_type' => 'staff',
			'post_status' => 'publish',
			'nopaging' => true,
			"$depttaxonomy" => $dept_taxonomy_term->slug,
			'tax_query' => array(
				array(
					'taxonomy' => $locationtaxonomy,
					'field' => 'term_id',
					'terms' => $listinglocations,
				)
			),
			'meta_query' => array(
				'relation' => 'AND',
						array(
							'key' => 'sticky_order',
							'compare' => 'EXISTS', // Check if 'sticky_order' key exists
						),
						array(
							'key' => 'sticky_order',
							'value' => '', // Check if 'sticky_order' value is blank
							'compare' => '=',
							'type' => 'CHAR',
						),
						array(
							'relation' => 'OR',
							array(
								'key' => 'website_visible',
								'value' => '1',
								'compare' => '=',
							),
							array(
								'key' => 'website_visible',
								'value' => 'null',
								'compare' => 'NOT EXISTS',
							),
							array(
								'key' => 'website_visible',
								'value' => $notvisible, //IF INTERNAL THIS VALUE WILL BE 0 TO INCLUDE THOSE WHO HAVE WEBSITE VISIBLE SET TO NOT VISIBLE
								'compare' => '=',
							),
						),
			),
			'orderby' => 'post_name', // Order by post_name
    		'order' => 'ASC', // Order in ascending order
		);

		$query_without_sticky_order = new WP_Query($args_without_sticky_order);
		$posts_without_sticky_order = $query_without_sticky_order->get_posts();

		// Combine the results
		$posts = array_merge($posts_with_sticky_order, $posts_without_sticky_order);
        $deptstaff = array();

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
			if ($showdepartments !== FALSE){
				$department = get_field('department');
			}
            $location = get_field('primary_location');
			$webvisible = get_field('website_visible');
			if ($showtitles !== FALSE){
				$title_term = get_field('title');
				$title = $title_term && !is_wp_error($title_term) ? $title_term->name : '';
			}
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

				$int_info[] = array(
									'contact_info' => $intcontactinfo,
									'birthday' => $intbirthdayinfo
				);
			}
			
			if($showbios){
				$bio = get_field('biography'); 
			}
			if($showaptsbtn){
				$appointmentsurl = get_field('schedule_appointment_url'); 
			}
			
			$locationslisted = wp_get_post_terms( $post->ID, 'location', array( 'fields' => 'ids' ) );

			// Get the tag IDs
			$stafftags = wp_get_post_tags($post->ID, array('fields' => 'ids'));

			// Prepare an array to hold tag data
			$tag_data = [];

			foreach ($stafftags as $tag_id) {
				// Get the tag object
				$tag = get_tag($tag_id);

				// Get custom fields for the tag
				$card_visible = get_term_meta($tag_id, 'card_visible', true);
				$public_visible = get_term_meta($tag_id, 'public_visible', true);
				$public_filter = get_term_meta($tag_id, 'public_filter', true);

				// Check if $public_visible is equal to 1
				if ($public_visible == '1') {
					// Add the data to the array
					$tag_data[] = array(
						'id' => $tag_id,
						'name' => $tag->name,
						'card_visible' => $card_visible,
						'public_visible' => $public_visible,
						'public_filter' => $public_filter
					);
				}
			}

            $deptstaff[] = array( 
                              'id' => $post->ID, 
                              'full_name' => $full_name,
                              'first_name' => $first_name,  
                              'last_name' => $last_name, 
                              'department' => $department->name,
                              'department_id' => $department->term_id,
                              'department_desc' => $department->description,
                              'department_order' => $department->term_order,
                              'location' => $location->name,
							  'location_id' => $location->term_id,
							  'locations_listed' => $locationslisted,
							  'tags' => $tag_data,
                              'title' => $title,
                              'phone' => $phone,
                              'phone_type' => $phonetype,
                              'email' => $email,
                              'photo_full' => $photofull,
							  'photo_sized' => $photosized,
                              'staff_url' => $post->guid,
							  'int_info' => $int_info,
							  'appointments_url' => $appointmentsurl,
							  'bio' => $bio,
							  'website_visible' => $webvisible

            );
        }
        if ($deptstaff){
			if ($showfilters !== FALSE){
				$output[] = array(
					'dept_id' => $dept_taxonomy_term->term_id,
					'dept_order' => $dept_taxonomy_term->term_order,
					'dept_name' => $dept_taxonomy_term->name,
					'dept_staff' => $deptstaff
				);
			} else {
				$output[] = array(
					'dept_staff' => $deptstaff
				);
			}
        }
    }
} // END GENERATE OUTPUT
	
// STORE CACHE
	// Data has expired or doesn't exist, so regenerate the data and update the transient
    set_transient('list_'.$listid.'_output_external_transient', $output, 12 * 60 * 60); // Lasts for 12 hours
} // END CACHE CHECK