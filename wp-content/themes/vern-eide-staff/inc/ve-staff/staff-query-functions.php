<?php
/// THESE FUNCTIONS PROVIDE STAFF QUERY RESULTS FOR VARIOUS USE IN THE APP //

////////////////////////////////////////////
///// NEW HIRED OR PROMOTION QUERIES ///////
////////////////////////////////////////////
function build_field_filters_group( $post_id ) {
    $argsfieldfiltersgroup = array('relation' => 'AND'); // Default relation

    // Check if the 'staff_display_field_filters' ACF field exists for the given post
    if (have_rows('staff_display_field_filters', $post_id)) {
        $fieldgroupscount = count(get_field('staff_display_field_filters', $post_id));
        $argsfieldfiltersgroup = array('relation' => $fieldgroupscount > 1 && get_field('staff_display_field_groups_relation', $post_id) ? get_field('staff_display_field_groups_relation', $post_id) : 'AND');

        // Loop through each group of field filters
        while (have_rows('staff_display_field_filters', $post_id)): the_row();
            $filtersrelation = get_sub_field('relation_condition_filters');
            $argsfieldfilters = array('relation' => $filtersrelation);

            // Add field filters to the filter group
            while (have_rows('condition_filters')): the_row();
                $filterArray = array(
                    'key'     => get_sub_field('filter_field'),
                    'value'   => get_sub_field('filter_value'),
                    'compare' => get_sub_field('filter_compare_option'),
                );

                if ($fieldgroupscount == 1) {
                    // Add filter directly to the main group if there's only one field group
                    $argsfieldfiltersgroup[] = $filterArray;
                } else {
                    // Otherwise, add it to the individual field filters
                    $argsfieldfilters[] = $filterArray;
                }
            endwhile;

            if ($fieldgroupscount > 1) {
                // Add the individual field filters to the main filter group if there's more than one field group
                $argsfieldfiltersgroup[] = $argsfieldfilters;
            }
        endwhile;
    }

    return $argsfieldfiltersgroup; // Return the array to be used in a meta query
}

function staff_get_filtered_staff_posts($post_id = null, $stafftags = array(), $displaylocations = array(), $displaydepartments = array(), $staffpgnumber = 1, $staffshown = 10 ) {

    // Step 1: Fetch posts based on staff tags
    $stafftags_post_ids = array(); // Initialize empty array to store post IDs from stafftags query
	
    if (!empty($stafftags)) {
        $stafftags_query = array(
            'relation' => 'OR',
            array(
                'taxonomy' => 'post_tag',
                'field'    => 'term_id',
                'terms'    => $stafftags,
                'operator' => 'IN',
            ),
        );

        // Query for posts matching the stafftags
        $stafftags_args = array(
            'post_type'      => 'staff',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Get all posts
            'tax_query'      => $stafftags_query,
        );

        $stafftags_query_obj = new WP_Query($stafftags_args);
        $staff_posts = $stafftags_query_obj->get_posts(); // Get the post objects
		
		// Check if the $staff_posts array is empty
		if (empty($staff_posts)) {
			return array(); // Early return if there are no posts found
		}
		
        $stafftags_post_ids = wp_list_pluck($staff_posts, 'ID'); // Extract IDs of matched posts
		
    }

    // Step 2: Set up location and department filtering
    $tax_query = array('relation' => 'AND');

    // Add location terms to tax_query if provided
    if (!empty($displaylocations)) {
        $tax_query[] = array(
            'taxonomy' => 'location',
            'field'    => 'term_id',
            'terms'    => $displaylocations,
            'operator' => 'IN',
        );
    }

    // Add department terms to tax_query if provided
    if (!empty($displaydepartments)) {
        $tax_query[] = array(
            'taxonomy' => 'department',
            'field'    => 'term_id',
            'terms'    => $displaydepartments,
            'operator' => 'IN',
        );
    }

    // Step 3: Meta query setup
    $meta_query = array();
    $meta_key = '';

    if (empty($stafftags)) {
        // Build field filters group using post ID
        if ($post_id) {
            $meta_query[] = build_field_filters_group($post_id); // Add field filters group to meta_query
        }

        // Add sticky_order logic if no stafftags
        $meta_query[] = array(
            'key'     => 'sticky_order',
            'compare' => 'EXISTS',
        );
        $meta_query[] = array(
            'key'     => 'sticky_order',
            'value'   => '',
            'compare' => '!=',
            'type'    => 'NUMERIC',
        );
        $meta_key = 'sticky_order'; // Sorting by sticky_order when no stafftags
    }

    // Step 4: Prepare final query arguments
    $paged = $staffpgnumber ? $staffpgnumber : 1;
    $posts_per_page = $staffshown ? $staffshown : 10;

    if (!empty($stafftags_post_ids)) {
        // If stafftags are set, limit by post__in
        $final_args = array(
            'post_type'      => 'staff',
            'post_status'    => 'publish',
            'paged'          => $paged,
            'posts_per_page' => $posts_per_page,
            'post__in'       => $stafftags_post_ids,
            'tax_query'      => $tax_query,
            'meta_query'     => $meta_query,
            'orderby'        => array(
                'meta_value_num' => 'ASC',
                'title'          => 'ASC',
            ),
            'meta_key'       => $meta_key,
            'order'          => 'ASC',
        );
    } else {
        // If no stafftags, query without post__in
        $final_args = array(
            'post_type'      => 'staff',
            'post_status'    => 'publish',
            'paged'          => $paged,
            'posts_per_page' => $posts_per_page,
            'tax_query'      => $tax_query,
            'meta_query'     => $meta_query,
            'orderby'        => array(
                'meta_value_num' => 'ASC',
                'title'          => 'ASC',
            ),
            'meta_key'       => $meta_key,
            'order'          => 'ASC',
        );
    }

    // Step 5: Run the final query and return the results
    $final_query = new WP_Query($final_args);
    return $final_query->get_posts();
}




////////////////////////////////////////////
///// NEW HIRED OR PROMOTION QUERIES ///////
////////////////////////////////////////////
function build_staff_new_promotion_hire_transient($months_prior = 0) {
    // Query all staff posts
    $args = array(
        'post_type' => 'staff',
        'post_status' => 'publish',
        'nopaging' => true,
        'fields' => 'ids', // We only need the post IDs
    );

    $query = new WP_Query($args);
    $posts = $query->posts;

    // Build the promotion and hire array
    $new_promo_hire_array = array();

    // Date calculations for range
    $compare_date = date('Y-m-01', strtotime("$months_prior months")); // First day of month based on months_prior

    foreach ($posts as $post_id) {
        // Retrieve the ACF promotion and start date fields
        $staff_start_date = get_field('start_date', $post_id);
        $staff_promotion_date = get_field('promotion_date', $post_id);

        // If no start date, use the published date
        $staff_published_date = date('Y-m-d', strtotime(get_the_date('Y-m-d', $post_id)));
        if (empty($staff_start_date)) {
            $staff_start_date = $staff_published_date;
        }

        // Format promotion date if exists
        $staff_promotion_date = !empty($staff_promotion_date) ? date('Y-m-d', strtotime($staff_promotion_date)) : '';

        // Check if start date or promotion date is within range
        if ($staff_start_date >= $compare_date && $staff_start_date <= date('Y-m-d')) {
            $new_promo_hire_array[$post_id] = array(
                'date' => $staff_start_date,
                'type' => 'new',
            );
        } elseif (!empty($staff_promotion_date) && $staff_promotion_date >= $compare_date && $staff_promotion_date <= date('Y-m-d')) {
            $new_promo_hire_array[$post_id] = array(
                'date' => $staff_promotion_date,
                'type' => 'promotion',
            );
        }
    }

    // Store the promotion and hire array as a transient for the rest of the day
    $expiration = strtotime('tomorrow') - time(); // Expire at midnight
    set_transient('staff_query_new_promo_hire_data', $new_promo_hire_array, $expiration);

    return $new_promo_hire_array;
}

function staff_get_new_promo_hire_posts( $months_prior = 0, $displaylocations = array(), $displaydepartments = array()) {
	
    // Try to get the transient first
    $new_promo_hire_array = get_transient('staff_query_new_promo_hire_data');
    
    // If the transient doesn't exist, build it
    if (!$new_promo_hire_array) {
        $new_promo_hire_array = build_staff_new_promotion_hire_transient($months_prior);
    }

    // Initialize an array for matched posts
    $matched_posts = array();

    // Loop through the new promo hire array and collect the matched posts
    foreach ($new_promo_hire_array as $post_id => $promo_hire_data) {
        $matched_posts[] = $post_id; // Collect matched post IDs
    }

    // Now query for the posts with the matched IDs and add the location and department filters
    if (!empty($matched_posts)) {
        $args = array(
            'post_type' => 'staff',
            'post__in' => $matched_posts,
            'nopaging' => true,
            'post_status' => 'publish',
        );

        // Set up tax_query to filter by both locations and departments
        $tax_query = array('relation' => 'AND');

        // Add location terms to tax_query if $displaylocations is set
        if (!empty($displaylocations)) {
            $tax_query[] = array(
                'taxonomy' => 'location', // The location taxonomy
                'field'    => 'term_id',
                'terms'    => $displaylocations, // The location term IDs
                'operator' => 'IN', // Match posts that have any of the location term IDs
            );
        }

        // Add department terms to tax_query if $displaydepartments is set
        if (!empty($displaydepartments)) {
            $tax_query[] = array(
                'taxonomy' => 'department', // The department taxonomy
                'field'    => 'term_id',
                'terms'    => $displaydepartments, // The department term IDs
                'operator' => 'IN', // Match posts that have any of the department term IDs
            );
        }

        // Add tax_query to args if there are filters
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        // Execute the WP_Query with the filtered args
        $query = new WP_Query($args);
        return $query->get_posts(); // Returns array of post objects
    } else {
        return array(); // No matching posts
    }
}

//////////////////////////////
///// BIRTHDAY QUERIES ///////
//////////////////////////////
function build_staff_birthday_transient() {
    // Get the current year, previous year, and next year
    $curyear = date('Y');
    $prevyear = date('Y', strtotime('-1 year'));
    $nextyear = date('Y', strtotime('+1 year'));

    // Query all staff posts
    $args = array(
        'post_type' => 'staff',
        'post_status' => 'publish',
        'nopaging' => true,
        'fields' => 'ids', // We only need the post IDs
    );
    
    $query = new WP_Query($args);
    $posts = $query->posts;

    // Build the birthday array
    $birthday_array = array();

    foreach ($posts as $post_id) {
		// Retrieve the ACF birthday group field
		$intbirthdayinfo = get_field('birthday', $post_id); // 'birthday' is the ACF group field

		// Check if the birthday group field, month, and day are set and not empty
		if ($intbirthdayinfo && !empty($intbirthdayinfo['birthday_month']) && !empty($intbirthdayinfo['birthday_day'])) {
			$bday_month = $intbirthdayinfo['birthday_month'];
			$bday_day = $intbirthdayinfo['birthday_day'];

			// Format the birthday for current, previous, and next years
			$bdaycuryr = "$curyear-$bday_month-$bday_day";
			$bdaycuryrdate = date('Y-m-d', strtotime($bdaycuryr));

			$bdaynextyr = "$nextyear-$bday_month-$bday_day";
			$bdaynextyrdate = date('Y-m-d', strtotime($bdaynextyr));

			$bdayprevyr = "$prevyear-$bday_month-$bday_day";
			$bdayprevyrdate = date('Y-m-d', strtotime($bdayprevyr));

			// Store the data in the array
			$birthday_array[$post_id] = array(
				'birthday_current_year' => $bdaycuryrdate,
				'birthday_next_year' => $bdaynextyrdate,
				'birthday_previous_year' => $bdayprevyrdate,
			);
		} else {
			// Skip the post and log an error
			// error_log('Post ID ' . $post_id . ' does not have valid birthday information.');
		}
	}

    // Store the birthday array as a transient for the rest of the day
    $expiration = strtotime('tomorrow') - time(); // Expire at midnight
    set_transient('staff_query_birthday_data', $birthday_array, $expiration);
    
    return $birthday_array;
}

function staff_get_birthday_range_posts( $days_behind, $days_ahead, $displaylocations = array(), $displaydepartments = array() ) {
    // Try to get the transient first
    $birthday_array = get_transient('staff_query_birthday_data');
    
    // If the transient doesn't exist, build it
    if (!$birthday_array) {
        $birthday_array = build_staff_birthday_transient();
    }

    // Get today's date and calculate the backward and forward range
    $current_date = date('Y-m-d');
    $datebackward = date('Y-m-d', strtotime("-$days_behind days"));
    $dateforward = date('Y-m-d', strtotime("+$days_ahead days"));

    // Initialize an array for matched posts
    $matched_posts = array();

    // Loop through the birthday array and check if the birthday falls within the range
    foreach ($birthday_array as $post_id => $birthday_data) {
        $bdaycuryrdate = $birthday_data['birthday_current_year'];
        $bdaynextyrdate = $birthday_data['birthday_next_year'];
        $bdayprevyrdate = $birthday_data['birthday_previous_year'];

        // Check if the birthday is within the past or future range
        if (($bdaycuryrdate >= $datebackward && $bdaycuryrdate <= $dateforward) ||
            ($bdaynextyrdate >= $datebackward && $bdaynextyrdate <= $dateforward) ||
            ($bdayprevyrdate >= $datebackward && $bdayprevyrdate <= $dateforward)) {
            $matched_posts[] = $post_id;
        }
    }
    
    // Now query for the posts with the matched IDs and add the location and department filters
    if (!empty($matched_posts)) {
        $tax_query = array('relation' => 'AND');

        // Add location terms to tax_query if $displaylocations is set
        if (!empty($displaylocations)) {
            $tax_query[] = array(
                'taxonomy' => 'location', // Replace with your actual location taxonomy
                'field'    => 'term_id',
                'terms'    => $displaylocations,
                'operator' => 'IN',
            );
        }

        // Add department terms to tax_query if $displaydepartments is set
        if (!empty($displaydepartments)) {
            $tax_query[] = array(
                'taxonomy' => 'department', // Replace with your actual department taxonomy
                'field'    => 'term_id',
                'terms'    => $displaydepartments,
                'operator' => 'IN',
            );
        }

        $args = array(
            'post_type' => 'staff',
            'post__in' => $matched_posts,
            'nopaging' => true,
            'post_status' => 'publish',
            'tax_query' => $tax_query, // Include the tax_query for filtering
        );
        
        $query = new WP_Query($args);
        return $query->get_posts(); // Returns array of post objects
    } else {
        return array(); // No matching posts
    }
}

// LOCATION DATA //
function staff_get_location_data($location_id) {
    // Initialize an array for the location data
    $location_data = [];

    // Retrieve the location address group field
    $address = get_field('location_address', 'location_' . $location_id);

    // Extract address components, handling missing values gracefully
    $location_data['street_address'] = $address['street_address'] ?? '';
    $location_data['city'] = $address['city'] ?? '';
    $location_data['state'] = $address['state'] ?? '';
    $location_data['postal_code'] = $address['postal_code'] ?? '';
    $location_data['country'] = $address['country'] ?? '';

    // Retrieve the location website field
    $location_data['website'] = get_field('location_website', 'location_' . $location_id) ?? '';

    // Optional: Add any additional fields you'd like to retrieve
    $location_data['brand_logo'] = get_field('location_brand_logo', 'location_' . $location_id) ?? '';

    return $location_data;
}

////////////////////////////////////////////
///// ANNIVERSARY MILESTONE QUERIES ////////
////////////////////////////////////////////
function ve_staff_get_anniversary_tag_terms_safe(): array {
	if ( ! class_exists( 'Ve_Staff_Admin' ) ) {
		@include_once WP_PLUGIN_DIR . '/ve-staff/admin/class-ve-staff-admin.php';
	}

	// Try static first
	if ( class_exists( 'Ve_Staff_Admin' ) && method_exists( 'Ve_Staff_Admin', 'get_anniversary_tag_terms' ) ) {
		try {
			$maybe = @Ve_Staff_Admin::get_anniversary_tag_terms();
			if ( is_array( $maybe ) && ! empty( $maybe ) ) {
				return $maybe; // years => WP_Term
			}
		} catch (Throwable $e) {}
	}

	// Try instance without constructor
	if ( class_exists( 'Ve_Staff_Admin' ) && method_exists( 'Ve_Staff_Admin', 'get_anniversary_tag_terms' ) ) {
		try {
			$ref  = new ReflectionClass( 'Ve_Staff_Admin' );
			$inst = $ref->newInstanceWithoutConstructor();
			$terms = $inst->get_anniversary_tag_terms();
			if ( is_array( $terms ) ) return $terms;
		} catch (Throwable $e) {}
	}

	// Fallback: map and resolve slugs
	$map = [];
	if ( class_exists( 'Ve_Staff_Admin' ) && method_exists( 'Ve_Staff_Admin', 'get_anniversary_tag_map' ) ) {
		try {
			$map = @Ve_Staff_Admin::get_anniversary_tag_map();
			if ( empty( $map ) ) {
				$ref = new ReflectionClass( 'Ve_Staff_Admin' );
				$inst = $ref->newInstanceWithoutConstructor();
				$map  = $inst->get_anniversary_tag_map();
			}
		} catch (Throwable $e) {}
	}
	if ( empty( $map ) ) {
		$map = [1=>'1-year',3=>'3-year',5=>'5-year',10=>'10-year',15=>'15-year',20=>'20-year',25=>'25-year',30=>'30-year',35=>'35-year',40=>'40-year',45=>'45-year',50=>'50-year',55=>'55-year'];
	}

	$out = [];
	foreach ( $map as $years => $slug ) {
		$term = get_term_by( 'slug', $slug, 'post_tag' );
		if ( $term && ! is_wp_error( $term ) ) $out[$years] = $term;
	}
	return $out;
}

// Extract year from slug like "5-year" => 5
function ve_staff_get_year_from_tag_slug( $slug ) {
	if ( preg_match( '/^(\d+)/', $slug, $m ) ) {
		return (int) $m[1];
	}
	return 0;
}

/**
 * Anniversary posts with paging and strict ordering.
 */
function staff_get_anniversary_milestone_posts(
	$displayid = null,
	$displaylocations = [],
	$displaydepartments = [],
	$staffpgnumber = 1,
	$staffshown = 10
) {
	// --- Normalize taxonomy filters to arrays of TERM IDS ---
	$norm_terms = function($maybe_terms) {
		if ( empty($maybe_terms) ) return [];
		$ids = [];
		foreach ((array)$maybe_terms as $t) {
			if ( is_object($t) && isset($t->term_id) )      $ids[] = (int)$t->term_id;
			elseif ( is_numeric($t) )                        $ids[] = (int)$t;
		}
		return array_values(array_unique(array_filter($ids)));
	};
	$displaylocations   = $norm_terms($displaylocations);
	$displaydepartments = $norm_terms($displaydepartments);

	// --- Build cache key ---
	$id_cache_key = 'staff_anniversary_all_ids_' . md5( serialize( [ $displayid, $displaylocations, $displaydepartments ] ) );
	$all_ids = get_transient( $id_cache_key );

	if ( $all_ids === false ) {

		// --- Get all anniversary tag terms ---
		$tag_terms = ve_staff_get_anniversary_tag_terms_safe();
		if ( empty( $tag_terms ) ) return [];

		// --- Sort tags numerically by year from slug (descending: 55, 50, ..., 1) ---
		uasort( $tag_terms, function( $a, $b ) {
			$ay = ve_staff_get_year_from_tag_slug( $a->slug );
			$by = ve_staff_get_year_from_tag_slug( $b->slug );
			return $by <=> $ay;
		});

		// --- Shared location/department filters ---
		$loc_dept = [];
		if ( ! empty( $displaylocations ) || ! empty( $displaydepartments ) ) {
			$loc_dept = [ 'relation' => 'AND' ];
			if ( ! empty( $displaylocations ) ) {
				$loc_dept[] = [
					'taxonomy' => 'location',
					'field'    => 'term_id',
					'terms'    => $displaylocations,
					'operator' => 'IN',
				];
			}
			if ( ! empty( $displaydepartments ) ) {
				$loc_dept[] = [
					'taxonomy' => 'department',
					'field'    => 'term_id',
					'terms'    => $displaydepartments,
					'operator' => 'IN',
				];
			}
		}

		// --- Build the full ordered list of IDs ---
		$all_ids = [];
		$seen    = [];

		foreach ( $tag_terms as $term ) {
			$tax_query = array_merge(
				[
					[
						'taxonomy' => 'post_tag',
						'field'    => 'term_id',
						'terms'    => [ $term->term_id ],
					],
				],
				$loc_dept
			);

			$q = new WP_Query( [
				'post_type'      => 'staff',
				'post_status'    => 'publish',
				'nopaging'       => true,
				'tax_query'      => $tax_query,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			] );

			if ( ! empty( $q->posts ) ) {
				foreach ( $q->posts as $id ) {
					if ( ! isset( $seen[ $id ] ) ) {
						$all_ids[]   = $id;
						$seen[ $id ] = true;
					}
				}
			}
		}

		// --- Cache until midnight ---
		$expiration = max( 60, strtotime( 'tomorrow' ) - time() );
		set_transient( $id_cache_key, $all_ids, $expiration );
	}

	// --- Pagination ---
	if ( empty( $all_ids ) ) return [];

	$posts_per_page = max( 1, (int) $staffshown );
	$current_page   = max( 1, (int) $staffpgnumber );
	$offset         = ( $current_page - 1 ) * $posts_per_page;

	$paged_ids = array_slice( $all_ids, $offset, $posts_per_page );
	if ( empty( $paged_ids ) ) return [];

	// --- Retrieve post objects for current page ---
	$q = new WP_Query( [
		'post_type'      => 'staff',
		'post__in'       => $paged_ids,
		'post_status'    => 'publish',
		'orderby'        => 'post__in',
		'posts_per_page' => count( $paged_ids ),
	] );

	return $q->get_posts();
}
