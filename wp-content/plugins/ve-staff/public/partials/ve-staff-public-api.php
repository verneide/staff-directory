<?php
// Custom API connection for fetching staff listings and information. 

class Ve_Staff_API {

  public function __construct() {
    add_action( 'rest_api_init', array( $this, 'register_ve_api_routes' ) );
	add_action('template_redirect', array($this, 'handle_staff_photo_redirect'));
  }

  public function register_ve_api_routes() {
    register_rest_route( 've/v1', '/staff/', array(
      'methods' => 'GET',
      'callback' => array( $this, 'get_staff' ),
      'permission_callback' => array( $this, 've_api_permissions_check' )
    ));
	register_rest_route('ve/v1', '/staff/basic/email/(?P<email>.+)', array(
		'methods' => 'GET',
		'callback' => array($this, 'get_basic_staff_email'),
		'permission_callback' => '__return_true', // No permission check
		'args' => array(
			'email' => array(
				'required' => true,
				'validate_callback' => function($param, $request, $key) {
					// Decode the email before validating
					$decoded_email = urldecode($param);
					return is_email($decoded_email);
				}
			)
		)
	));
	register_rest_route('ve/v1', '/staff/email/(?P<email>[^/]+)', array(
		'methods' => 'GET',
		'callback' => array($this, 'get_staff_by_email'),
		'permission_callback' => array($this, 've_api_permissions_check'),
		'args' => array(
			'email' => array(
				'required' => true,
				'validate_callback' => function($param) {
					return is_email(urldecode($param));
				}
			),
			'type' => array(
				'required' => false,
				'default' => 'default',
				'validate_callback' => function($param) {
					return in_array($param, ['default', 'basic', 'detail', 'internal', 'external', 'all']);
				}
			)
		)
	));
    register_rest_route('ve/v1', '/staff/id/(?P<id>\d+)', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_staff'),
        'permission_callback' => array($this, 've_api_permissions_check'),
        'args' => array(
            'id' => array(
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            )
        )
    ));
    register_rest_route( 've/v1', '/locations/', array(
	  'methods' => 'GET',
	  'callback' => array( $this, 'get_locations' ),
	  'permission_callback' => '__return_true' // Makes it public
	));
    register_rest_route( 've/v1', '/departments/', array(
      'methods' => 'GET',
      'callback' => array( $this, 'get_departments' ),
      'permission_callback' => array( $this, 've_api_permissions_check' )
    ));
	register_rest_route('ve/v1', '/staff/contact/', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_staff_contact'),
        'permission_callback' => array($this, 've_api_permissions_check')
    ));
	register_rest_route('ve/v1', '/lists/(?P<id>\d+)', array(
		'methods' => 'GET',
		'callback' => array($this, 'get_listed_staff'),
		'permission_callback' => array($this, 've_api_permissions_check')
	));
	register_rest_route('ve/v1', '/staff-photo/', array(
		'methods' => 'GET',
		'callback' => array($this, 'get_staff_photo'),
		'permission_callback' => '__return_true', // Open to public access
		'args' => array(
			'email' => array(
				'required' => false, // Make email optional as staff_id can also be used
				'validate_callback' => function ($param) {
					// Decode the email before validating
					$decoded_email = urldecode($param);
					return is_email($decoded_email);
				},
			),
			'staff_id' => array(
				'required' => false, // Optional as email can also be used
				'validate_callback' => function ($param) {
					// Decode and ensure staff_id is numeric
					$decoded_id = urldecode($param);
					return is_numeric($decoded_id);
				},
			),
			'size' => array(
				'required' => false,
				'validate_callback' => function ($param) {
					return in_array($param, ['thumbnail', 'medium','medium_large', 'large']);
				},
				'default' => 'medium',
			),
		),
	));
  }
	
  public function ve_api_permissions_check( $request ) {
    // Assuming the Application Passwords are set up, this should handle the authentication
    return current_user_can('edit_others_posts');
  }
	
	public function get_staff($request) {
		// Check if the request is for a specific ID or email
		$id = $request->get_param('id');
		$email = $request->get_param('email');
		$fields = $request->get_param('fields') ?? 'default';

		// If the request is to get staff by ID
		if (!is_null($id)) {
			$post = get_post($id);
			if ($post && $post->post_type === 'staff') {
				return new WP_REST_Response($this->prepare_staff_data($post, $fields), 200);
			} else {
				return new WP_REST_Response(['message' => 'Staff not found'], 404);
			}
		}

		// If the request is to get staff by email
		if (!is_null($email)) {
			$args = [
				'post_type' => 'staff',
				'meta_query' => [
					[
						'key' => 'email', // Adjust this to your actual custom field key for the email
						'value' => $email,
						'compare' => '='
					]
				],
				'posts_per_page' => 1
			];
			$posts = get_posts($args);
			if (!empty($posts)) {
				return new WP_REST_Response($this->prepare_staff_data($posts[0], $fields), 200);
			} else {
				return new WP_REST_Response(['message' => 'Staff not found'], 404);
			}
		}

		// Extract parameters from Normal Staff Request (Not ID or Email)
		$locations = $request->get_param('locations') ?? 'all';
		$excludedlocations = $request->get_param('excludedlocations') ?? 'none';
		$departments = $request->get_param('departments') ?? 'all';
		$excludedepartments = $request->get_param('excludedepartments') ?? 'none';
		$orderby = $request->get_param('orderby') ?? 'default';
		$cache = $request->get_param('cache') ?? 'true';
		
		if($cache == 'true'){
			// Create a unique key for caching
			$unique_key = 'staff_api_request_' . md5("fields={$fields}_locations={$locations}_excludedlocations={$excludedlocations}_departments={$departments}_excludedepartments={$excludeddepartments}_orderby={$orderby}");

			// Check for cached data
			$cached_data = get_transient($unique_key);
			if ($cached_data) {
				return new WP_REST_Response($cached_data, 200);
			}
		}
		
		// Convert comma-separated string to arrays
		$location_ids = $locations !== 'all' ? explode(',', $locations) : [];
		$excluded_location_ids = $excludedlocations !== 'none' ? explode(',', $excludedlocations) : [];
		$department_ids = $departments !== 'all' ? explode(',', $departments) : [];
		$excluded_department_ids = $excludedepartments !== 'none' ? explode(',', $excludedepartments) : [];

		$args = array(
			'post_type' => 'staff',
			'posts_per_page' => -1,
			'tax_query' => array('relation' => 'AND')
		);

		// Add filters
		if (!empty($location_ids)) {
			$args['tax_query'][] = array(
				'taxonomy' => 'location',
				'field' => 'term_id',
				'terms' => $location_ids,
				'include_children' => false
			);
		}
		if (!empty($excluded_location_ids)) {
			$args['tax_query'][] = array(
				'taxonomy' => 'location',
				'field' => 'term_id',
				'terms' => $excluded_location_ids,
				'operator' => 'NOT IN'
			);
		}
		if (!empty($department_ids)) {
			$args['tax_query'][] = array(
				'taxonomy' => 'department',
				'field' => 'term_id',
				'terms' => $department_ids,
				'include_children' => false
			);
		}
		if (!empty($excluded_department_ids)) {
			$args['tax_query'][] = array(
				'taxonomy' => 'department',
				'field' => 'term_id',
				'terms' => $excluded_department_ids,
				'operator' => 'NOT IN'
			);
		}

		// Remove tax query if no filters are set
		if (empty($location_ids) && empty($excluded_location_ids) && empty($department_ids) && empty($excluded_department_ids)) {
			unset($args['tax_query']);
		}

		$posts = get_posts($args);
		$data = array();

		foreach ($posts as $post) {
			$data[] = $this->prepare_staff_data($post, $fields);
		}
		
		$orderby = $request->get_param('orderby');
		$orderby_criteria = !empty($orderby) ? explode(',', $orderby) : [];
		if (!empty($orderby)) {
			usort($data, function($a, $b) use ($orderby_criteria) {
				foreach ($orderby_criteria as $criteria) {
					switch ($criteria) {
						case 'name':
							$result = strcmp($a['full_name'] ?? '', $b['full_name'] ?? '');
							break;
						case 'location':
							$locA = is_array($a['primary_location']) ? ($a['primary_location']['name'] ?? '') : ($a['primary_location'] ?? '');
							$locB = is_array($b['primary_location']) ? ($b['primary_location']['name'] ?? '') : ($b['primary_location'] ?? '');
							$result = strcmp($locA, $locB);
							break;
						case 'department':
							$deptA = is_array($a['department']) ? ($a['department']['name'] ?? '') : ($a['department'] ?? '');
							$deptB = is_array($b['department']) ? ($b['department']['name'] ?? '') : ($b['department'] ?? '');
							$result = strcmp($deptA, $deptB);
							break;
						default:
							$result = 0;
							break;
					}
					if ($result !== 0) {
						return $result;
					}
				}
				return 0; // If all criteria are equal
			});
		}
		
		// Cache the response
		if($cache == 'true'){
    		set_transient($unique_key, $data, HOUR_IN_SECONDS); // Adjust the duration as needed
		}
		
		return new WP_REST_Response($data, 200);
	}
	
	public function get_listed_staff($request) {
		$list_id = $request['id'];
		$list_post = get_post($list_id);

		// Get Listing Post Data
		$list_title = $list_post->post_title;
		$list_settings = get_fields($list_id);

		// Prepare additional parameters for get_staff based on list settings
		$additional_params = [];
		if (isset($list_settings['listing_locations'])) {
			$additional_params['locations'] = implode(',', $list_settings['listing_locations']);
		}
		if (isset($list_settings['listing_excluded_departments'])) {
			$additional_params['departments'] = implode(',', $list_settings['listing_excluded_departments']);
		}

		// Adjust internal or external data visibility based on 'internal_page' field
		$fields = $list_settings['internal_page'] ? 'internal' : 'external';

		// Fetch staff data
		$staff_data = $this->get_lists_staff_data($additional_params, $fields);

		// Include list settings at the beginning of the response
		$response_data = array(
			'title' => $list_title,
			'list_settings' => $list_settings,
			'staff' => $staff_data
		);

		return new WP_REST_Response($response_data, 200);
	}

	private function get_lists_staff_data($post_id, $additional_params) {
		$list_settings = get_fields($post_id);
		$fields = $list_settings['internal_page'] ? 'internal' : 'external';

		$args = [
			'post_type' => 'staff',
			'posts_per_page' => -1, // Fetch all posts
			'tax_query' => array('relation' => 'AND') // Initialize tax query
		];

		// Add location filter if provided
		if (!empty($additional_params['locations'])) {
			$args['tax_query'][] = array(
				'taxonomy' => 'location',
				'field' => 'term_id',
				'terms' => explode(',', $additional_params['locations']),
				'include_children' => false
			);
		}

		// Handle excluded departments
		if ($list_settings['listing_excluded_departments']) {
			$excluded_departments = $list_settings['listing_excluded_departments'];
			$args['tax_query'][] = [
				'taxonomy' => 'department',
				'field' => 'term_id',
				'terms' => $excluded_departments,
				'operator' => 'NOT IN'
			];
		}

		$posts = get_posts($args);
		$data = array();

		foreach ($posts as $post) {
			$staff_data = $this->prepare_staff_data($post, $fields);

			// Apply list settings modifications
			if (isset($list_settings['listing_show_locations']) && !$list_settings['listing_show_locations']) {
				$staff_data['primary_location'] = null;
				$staff_data['listed_locations'] = null;
			}
			if (isset($list_settings['listing_show_departments']) && !$list_settings['listing_show_departments']) {
				$staff_data['department'] = null;
			}
			if (isset($list_settings['listing_show_full_name']) && !$list_settings['listing_show_full_name']) {
				$staff_data['full_name'] = null;
				// Modify 'full_name' to only show the first name
			}
			if (isset($list_settings['listing_show_full_last_name']) && !$list_settings['listing_show_full_last_name']) {
				// Split full_name to get first and last names
				$names = explode(' ', $staff_data['full_name']);
				$firstName = $names[0];
				$lastName = $names[1] ?? '';

				// Modify last_name to only show the first letter followed by a period
				$modifiedLastName = $lastName ? substr($lastName, 0, 1) . '.' : '';

				// Replace last_name in full_name with modified last name
				$staff_data['full_name'] = $firstName . ' ' . $modifiedLastName;

				// Update last_name field
				$staff_data['last_name'] = $modifiedLastName;
			}
			if (isset($list_settings['listing_show_titles']) && !$list_settings['listing_show_titles']) {
				$staff_data['title'] = null;
			}
			if (isset($list_settings['listing_web_visible_bypass']) && $list_settings['listing_web_visible_bypass']) {
				$staff_data['website_visible'] = true;
			}

			$data[] = $staff_data;
		}

		return $data;
	}
	
	// Get Staff Details by Email with type param support (all, internal, external, detail, basic, default)
	public function get_staff_by_email($request) {
		$email = urldecode($request->get_param('email'));

		// Get the 'type' query param or default to 'default'
		$type = $request->get_param('type') ?? 'default';

		// Optional: Validate allowed types
		$allowed_types = ['default', 'basic', 'detail', 'internal', 'external', 'all'];
		if (!in_array($type, $allowed_types)) {
			return new WP_REST_Response(['message' => 'Invalid type parameter'], 400);
		}

		$args = [
			'post_type' => 'staff',
			'meta_query' => [
				[
					'key' => 'office_contact_info_office_email',
					'value' => $email,
					'compare' => '='
				]
			],
			'posts_per_page' => 1
		];
		$posts = get_posts($args);

		if (empty($posts)) {
			return new WP_REST_Response(['message' => 'Staff not found'], 404);
		}

		$post = $posts[0];
		$data = $this->prepare_staff_data($post, $type);

		return new WP_REST_Response($data, 200);
	}
	
	public function get_staff_by_email_all($request) {
		return $this->get_staff_by_email($request,'all');
	}
	public function get_staff_by_email_basic($request) {
		return $this->get_staff_by_email($request,'basic');
	}
	public function get_staff_by_email_detail($request) {
		return $this->get_staff_by_email($request,'detail');
	}
	public function get_staff_by_email_internal($request) {
		return $this->get_staff_by_email($request,'internal');
	}
	public function get_staff_by_email_external($request) {
		return $this->get_staff_by_email($request,'external');
	}
	
	public function get_basic_staff_email($request) {
		$email = urldecode($request->get_param('email'));

		// Query staff post by email using the correct key
		$args = [
			'post_type' => 'staff',
			'meta_query' => [
				[
					'key' => 'office_contact_info_office_email', // Correct meta key for the email field
					'value' => $email,
					'compare' => '='
				]
			],
			'posts_per_page' => 1
		];
		$posts = get_posts($args);

		if (empty($posts)) {
			return new WP_REST_Response(['message' => 'Staff not found'], 404);
		}

		$post = $posts[0];

		// Use the prepare_staff_data function to format the response
		$data = $this->prepare_staff_data($post, 'basic');

		return new WP_REST_Response($data, 200);
	}
	
	public function get_staff_photo($request) {
		$email = $request->get_param('email');
		$staff_id = $request->get_param('staff_id'); // Correct usage of staff_id
		$size = $request->get_param('size') ?? 'large';

		// Build query args based on provided parameter
		$args = [
			'post_type' => 'staff',
			'posts_per_page' => 1
		];

		if ($email) {
			$args['meta_query'] = [
				[
					'key' => 'office_contact_info_office_email', // Correct key for email
					'value' => $email,
					'compare' => '='
				]
			];
		} elseif ($staff_id) { // Correctly use staff_id
			$args['p'] = intval($staff_id); // Query by post ID
		} else {
			return new WP_REST_Response(['error' => 'Missing parameter: staff_id or email required'], 400);
		}

		$posts = get_posts($args);

		if (empty($posts)) {
			return new WP_REST_Response(['error' => 'Staff not found'], 404);
		}

		$post = $posts[0];
		$acf_fields = get_fields($post->ID);

		// Retrieve the photo field
		$photo_data = $acf_fields['photo'] ?? null;

		if (!$photo_data || !isset($photo_data['sizes'])) {
			return new WP_REST_Response(['error' => 'Photo not found or invalid metadata'], 404);
		}

		// Add staff_id to photo_data
		$photo_data['staff_id'] = $post->ID;

		// Extract sizes array
		$sizes = $photo_data['sizes'];

		// Check if the requested size exists
		$image_url = $sizes[$size] ?? $sizes['large'] ?? null; // Fallback to large size

		if (!$image_url) {
			return new WP_REST_Response([
				'error' => 'Invalid image size',
				'requested_size' => $size,
				'available_sizes' => array_keys($sizes),
				'attachment_id' => $photo_data['ID'] ?? null
			], 400);
		}

		// Construct the dynamic link for the template redirect
		$dynamic_image_url = home_url('/staff-photo/') . '?staff_id=' . urlencode($post->ID) . '&size=' . $size;

		// Fetch the actual file path from the image URL
		$upload_dir = wp_upload_dir();
		$image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);

		// Default to null
		$base64 = null;

		if (file_exists($image_path)) {
			$image_data = file_get_contents($image_path);
			$image_mime = mime_content_type($image_path);
			$base64 = 'data:' . $image_mime . ';base64,' . base64_encode($image_data);
		}

		return new WP_REST_Response([
			'image_url' => $image_url,
			'dynamic_image_url' => $dynamic_image_url,
			'base64' => $base64 // ✅ new base64 version of image
		], 200);
	}

	private function prepare_staff_data($post, $fields = '') {
		if (!$post) return null;

		// Full name handling
		$acf_fields = get_fields($post->ID);
		$full_name = trim(($acf_fields['first_name'] ?? '') . ' ' . ($acf_fields['last_name'] ?? ''));

		$post_data = [
			'ID' => $post->ID,
			'full_name' => $full_name,
		];

		// Determine allowed fields by mode
		$field_map = [
			'all' => '__all__', // Special case: all fields
			'internal' => [
				'title', 'first_name', 'last_name', 'primary_location', 'department',
				'start_date', 'promotion_date', 'photo', 'website_visible', 'phone', 'phone_type',
				'listed_locations', 'biography', 'bio_yt_video_id', 'office_contact_info',
				'birthday', 'profile_customizations', 'sticky_order'
			],
			'external' => [
				'title', 'first_name', 'last_name', 'primary_location', 'department',
				'photo', 'website_visible', 'phone', 'phone_type', 'listed_locations',
				'biography', 'bio_yt_video_id', 'profile_customizations', 'sticky_order'
			],
			'detail' => [
				'title', 'first_name', 'last_name', 'primary_location', 'listed_locations',
				'department', 'office_contact_info', 'photo'
			],
			'basic' => [
				'title', 'first_name', 'last_name', 'primary_location',
				'department', 'office_contact_info'
			],
			'default' => [
				'title', 'first_name', 'last_name', 'primary_location',
				'department', 'photo'
			]
		];

		$allowed_fields = $field_map[$fields] ?? [];

		// Handle "all" case
		if ($allowed_fields === '__all__') {
			$allowed_fields = array_keys($acf_fields);
		}

		foreach ($allowed_fields as $field_name) {
			$value = array_key_exists($field_name, $acf_fields)
				? $acf_fields[$field_name]
				: get_field($field_name, $post->ID);

			switch ($field_name) {
				case 'phone':
					$post_data['web_listed_phone'] = $value;
					break;

				case 'phone_type':
					$post_data['web_phone_type'] = $value;
					break;

				case 'photo':
					$post_data['photo'] = $this->prepare_photo_field($value, $post->ID);
					break;

				case 'title':
				case 'department':
				case 'primary_location':
					$post_data[$field_name] = $this->extract_field_name($value);
					break;

				case 'office_contact_info':
					if (is_array($value)) {
						$contact_info = $this->prepare_contact_info($value);
						$post_data['office_phone'] = $contact_info['office_phone'] ?? '';
						$post_data['office_phone_tracking'] = $contact_info['office_phone_tracking'] ?? null;
					} else {
						$post_data['office_phone'] = '';
						$post_data['office_phone_tracking'] = null;
					}
					break;

				case 'listed_locations':
					if (is_array($value)) {
						$names = array_map(fn($loc) => $this->extract_field_name($loc), $value);
						$post_data['listed_locations'] = implode(', ', $names);
					} else {
						$post_data['listed_locations'] = '';
					}
					break;

				default:
					$post_data[$field_name] = $value;
					break;
			}
		}

		return $post_data;
	}

  public function get_locations() {
    return $this->get_taxonomy_terms('location');
  }

  public function get_departments() {
    return $this->get_taxonomy_terms('department');
  }

  public function get_titles() {
    return $this->get_taxonomy_terms('staff-title');
  }

  private function get_taxonomy_terms($taxonomy) {
		$terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => false));
		$data = array();

		if($taxonomy == 'location'){
			foreach ($terms as $term) {
				// Fetch custom fields for each term
				$public_filter = get_term_meta($term->term_id, 'public_filter', true);
				
				// Location/Brand Logo
				$location_brand_logo_field = get_field('location_brand_logo', 'location_' . $term->term_id);
				$location_brand_logo_id = $location_brand_logo_field['ID'] ?? '';
				$location_brand_logo_url = $location_brand_logo_field['url'] ?? '';
				
				// Location Address
				$location_address_group = get_field('location_address', 'location_' . $term->term_id);
				$location_address_raw = $location_address_group ?: [];

				$location_address = '';
				if ($location_address_group) {
					$parts = array_filter([
						$location_address_group['street_address'] ?? '',
						$location_address_group['city'] ?? '',
						$location_address_group['state'] ?? '',
						$location_address_group['postal_code'] ?? ''
					]);

					// Join city/state/zip separately for proper formatting
					$location_address = $location_address_group['street_address'] ?? '';
					if (!empty($location_address_group['city']) || !empty($location_address_group['state']) || !empty($location_address_group['postal_code'])) {
						$location_address .= ', ' . trim(implode(', ', array_filter([
							$location_address_group['city'] ?? '',
							$location_address_group['state'] ?? ''
						]))) . ' ' . ($location_address_group['postal_code'] ?? '');
					}
				}
				
				// Locations Hours
				$location_hours = get_field('location_hours', 'location_' . $term->term_id);
				$formatted_hours = [];
				$raw_hours = [];
				$signature_hours = [];
				$html_hours = [];
				
				if ($location_hours) {
					foreach ($location_hours as $dept) {
						$department = $dept['department_type'];
						$days = $dept['daily_hours'];
						$department_hours = [];
						$department_raw = [];
						$summary_map = [];

						foreach ($days as $day => $info) {
							$day_name = ucfirst($day);
							$is_closed = !empty($info['is_closed']);
							$open = $info['open_time'] ?? '';
							$close = $info['close_time'] ?? '';

							if ($is_closed) {
								$department_hours[$day_name] = 'Closed';
								$department_raw[$day_name] = ['open' => null, 'close' => null];
								$summary_map['Closed'][] = $day_name;
							} else {
								$pretty = $this->format_pretty_time($open) . ' - ' . $this->format_pretty_time($close);
								$department_hours[$day_name] = $pretty;
								$department_raw[$day_name] = ['open' => $open, 'close' => $close];
								$summary_map[$pretty][] = $day_name;
							}
						}

						// Signature format
						$signature_string = ucfirst($department) . ' Hours';

						$open_hours = [];
						$closed_hours = [];

						foreach ($summary_map as $hours => $days) {
							if ($hours === 'Closed') {
								$closed_hours = $days;
							} else {
								$open_hours[$hours] = $days;
							}
						}

						// Order of the days for reference
						$day_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

						// Function to sort days properly
						$sort_days = function($days) use ($day_order) {
							usort($days, function($a, $b) use ($day_order) {
								return array_search($a, $day_order) - array_search($b, $day_order);
							});
							return $days;
						};

						// Output Open Days first (in Monday-Sunday order)
						foreach ($open_hours as $hours => $days) {
							$days_sorted = $sort_days($days);
							$label = $this->format_day_range($days_sorted);
							$signature_string .= " | {$label}: " . $hours;
						}

						// Output Closed Days last
						if (!empty($closed_hours)) {
							$days_sorted = $sort_days($closed_hours);
							$label = $this->format_day_range($days_sorted);
							$signature_string .= " | {$label}: CLOSED";
						}

						// HTML format
						$html = '<ul class="hours">';
						foreach ($summary_map as $hours => $days) {
							$label = $this->format_day_range($days);
							$html .= '<li><span class="day">' . $label . '</span><span class="right">' . ($hours === 'Closed' ? 'Closed' : $hours) . '</span><br><span class="disclaimer"></span></li>';
						}
						$html .= '</ul>';

						// Final payloads
						$formatted_hours[$department] = $department_hours;
						$raw_hours[$department] = $department_raw;
						$signature_hours["hours" . ucfirst($department)] = $signature_string;
						$html_hours[$department] = $html; // ✅ Add this line
					}
				}
				
				// Signature Details
				$location_signature_logo = get_field('location_signature_logo', 'location_' . $term->term_id) ?: '';
				$location_signature_footer = get_field('location_signature_footer', 'location_' . $term->term_id) ?: '';
				
				// Location URLs
				$location_website = get_field('location_website', 'location_' . $term->term_id) ?: '';
				$location_google_maps = get_field('location_google_maps', 'location_' . $term->term_id) ?: '';


				$data[] = array(
					'id' => $term->term_id,
					'label' => $term->name,
					'public_filter' => $public_filter,
					'location_brand_logo' => $location_brand_logo_id,
					'location_brand_logo_url' => $location_brand_logo_url,
					'location_address' => $location_address,
					'location_address_raw' => $location_address_raw,
					'location_hours' => $formatted_hours,
					'location_hours_raw' => $raw_hours,
					'location_hours_signature' => $signature_hours,
					'location_hours_html' => $html_hours,
					'location_signature_logo' => $location_signature_logo,
					'location_website' => $location_website,
					'location_google_maps' => $location_google_maps,
					'location_signature_footer' => $location_signature_footer,
				);
			}
		}else{
			foreach ($terms as $term) {
				$data[] = array(
					'id' => $term->term_id, 
					'label' => $term->name
				);
			}
		}
		
		// Sort the array alphabetically by label
		usort($data, function($a, $b) {
			return strcmp($a['label'], $b['label']);
		});

		return new WP_REST_Response($data, 200);
	}
	
	public function get_staff_contact($request) {
		// Extract parameters
		$fields = $request->get_param('fields') ?? 'default';
		$locations = $request->get_param('locations') ?? 'all';
		$excludedlocations = $request->get_param('excludedlocations') ?? 'none';
		$departments = $request->get_param('departments') ?? 'all';
		$excludedepartments = $request->get_param('excludedepartments') ?? 'none';
		$orderby = $request->get_param('orderby') ?? 'default';
		$cache = $request->get_param('cache') ?? 'true';
		
		if($cache == 'true'){
			// Create a unique key for caching
			$unique_key = 'staff_contact_api_request_' . md5("fields={$fields}_locations={$locations}_excludedlocations={$excludedlocations}_departments={$departments}_excludedepartments={$excludeddepartments}_orderby={$orderby}");

			// Check for cached data
			$cached_data = get_transient($unique_key);
			if ($cached_data) {
				return new WP_REST_Response($cached_data, 200);
			}
		}
		
		// Convert comma-separated string to arrays
		$location_ids = $locations !== 'all' ? explode(',', $locations) : [];
		$excluded_location_ids = $excludedlocations !== 'none' ? explode(',', $excludedlocations) : [];
		$department_ids = $departments !== 'all' ? explode(',', $departments) : [];
		$excluded_department_ids = $excludedepartments !== 'none' ? explode(',', $excludedepartments) : [];

		$args = array(
			'post_type' => 'staff',
			'posts_per_page' => -1,
			'tax_query' => array('relation' => 'AND')
		);

		// Add filters
		if (!empty($location_ids)) {
			$args['tax_query'][] = array(
				'taxonomy' => 'location',
				'field' => 'term_id',
				'terms' => $location_ids,
				'include_children' => false
			);
		}
		if (!empty($excluded_location_ids)) {
			$args['tax_query'][] = array(
				'taxonomy' => 'location',
				'field' => 'term_id',
				'terms' => $excluded_location_ids,
				'operator' => 'NOT IN'
			);
		}
		if (!empty($department_ids)) {
			$args['tax_query'][] = array(
				'taxonomy' => 'department',
				'field' => 'term_id',
				'terms' => $department_ids,
				'include_children' => false
			);
		}
		if (!empty($excluded_department_ids)) {
			$args['tax_query'][] = array(
				'taxonomy' => 'department',
				'field' => 'term_id',
				'terms' => $excluded_department_ids,
				'operator' => 'NOT IN'
			);
		}

		// Remove tax query if no filters are set
		if (empty($location_ids) && empty($excluded_location_ids) && empty($department_ids) && empty($excluded_department_ids)) {
			unset($args['tax_query']);
		}

		$posts = get_posts($args);
		$data = array();

		foreach ($posts as $post) {
			$data[] = $this->prepare_staff_contact_data($post, $fields);
		}
		
		$orderby = $request->get_param('orderby');
		$orderby_criteria = !empty($orderby) ? explode(',', $orderby) : [];
		if (!empty($orderby)) {
			usort($data, function($a, $b) use ($orderby_criteria) {
				foreach ($orderby_criteria as $criteria) {
					switch ($criteria) {
						case 'name':
							$result = strcmp($a['full_name'] ?? '', $b['full_name'] ?? '');
							break;
						case 'location':
							$locA = is_array($a['primary_location']) ? ($a['primary_location']['name'] ?? '') : ($a['primary_location'] ?? '');
							$locB = is_array($b['primary_location']) ? ($b['primary_location']['name'] ?? '') : ($b['primary_location'] ?? '');
							$result = strcmp($locA, $locB);
							break;
						case 'department':
							$deptA = is_array($a['department']) ? ($a['department']['name'] ?? '') : ($a['department'] ?? '');
							$deptB = is_array($b['department']) ? ($b['department']['name'] ?? '') : ($b['department'] ?? '');
							$result = strcmp($deptA, $deptB);
							break;
						default:
							$result = 0;
							break;
					}
					if ($result !== 0) {
						return $result;
					}
				}
				return 0; // If all criteria are equal
			});
		}
		
		// Cache the response
		if($cache == 'true'){
    		set_transient($unique_key, $data, HOUR_IN_SECONDS); // Adjust the duration as needed
		}
		
		return new WP_REST_Response($data, 200);
	}
	
	private function prepare_staff_contact_data($post, $fields = 'default') {
		if (!$post) return null;

		$title_value = get_field('title', $post->ID);
		$title = '';

		// Normalize title value
		if (is_array($title_value)) {
			$first = reset($title_value);
			if (is_object($first) && isset($first->name)) {
				$title = $first->name;
			} elseif (is_numeric($first)) {
				$term = get_term($first);
				$title = $term && !is_wp_error($term) ? $term->name : '';
			}
		} elseif (is_object($title_value) && isset($title_value->name)) {
			$title = $title_value->name;
		} elseif (is_numeric($title_value)) {
			$term = get_term($title_value);
			$title = $term && !is_wp_error($term) ? $term->name : '';
		} elseif (is_string($title_value)) {
			$title = $title_value;
		}

		// Initialize $post_data with all keys in the specified order, using appropriate default values
		$post_data = [
			'ID' => $post->ID,
			'full_name' => '',
			'first_name' => '',
			'last_name' => '',
			'primary_location' => '',
			'department' => '',
			'title' => $title,
			'listed_locations' => '',
			'email' => '',
			'cell_phone' => '',
			'office_phone' => '',
			'office_extension' => ''
		];

		$acf_fields = get_fields($post->ID);

		// Populate 'full_name' based on available 'first_name' and 'last_name'
		if (isset($acf_fields['first_name']) && isset($acf_fields['last_name'])) {
			$post_data['full_name'] = trim($acf_fields['first_name'] . ' ' . $acf_fields['last_name']);
			$post_data['first_name'] = $acf_fields['first_name'];
			$post_data['last_name'] = $acf_fields['last_name'];
		}

		// Iterate over ACF fields and populate $post_data accordingly
		foreach ($acf_fields as $field_name => $value) {
			if ($field_name === 'office_contact_info') {
				$contact_info = $this->prepare_contact_info($value);
				foreach ($contact_info as $contact_key => $contact_value) {
					$post_data[$contact_key] = $contact_value;
				}
			} elseif ($field_name === 'department' || $field_name === 'primary_location') {
				$post_data[$field_name] = is_array($value) && isset($value['name']) ? $value['name'] : (is_object($value) && isset($value->name) ? $value->name : 'N/A');
			} elseif ($field_name === 'listed_locations') {
				$location_names = is_array($value) ? array_map(function($location) {
					return is_array($location) && isset($location['name']) ? $location['name'] : (is_object($location) && isset($location->name) ? $location->name : '');
				}, $value) : [];
				$post_data['listed_locations'] = implode(', ', $location_names);
			} elseif ($field_name === 'title') {
				// Skip — already handled
				continue;
			} elseif (array_key_exists($field_name, $post_data)) {
				$post_data[$field_name] = $value;
			}
		}

		return $post_data;
	}

	private function prepare_contact_info($contact_info) {
		if (!$contact_info) return null;

		$office_phone = '';
		if (!empty($contact_info['office_other_direct'])) {
			// Use office_other_direct if available, remove dashes
			$office_phone = str_replace('-', '', $contact_info['office_other_direct']);
		} elseif (!empty($contact_info['office_phone_prefix'])) {
			// Combine prefix and extension, remove dashes
			$office_phone = str_replace('-', '', $contact_info['office_phone_prefix']) . 
							(!empty($contact_info['office_extension']) ? $contact_info['office_extension'] : '');
		}

		// Extract office_phone_tracking and ensure digits-only
		$office_phone_tracking = !empty($contact_info['office_phone_tracking'])
			? preg_replace('/\D/', '', $contact_info['office_phone_tracking'])
			: null;

		return array(
			'email' => $contact_info['office_email'] ?? '',
			'cell_phone' => $contact_info['office_cell_phone'] ?? '',
			'office_phone' => $office_phone,
			'office_phone_tracking' => $office_phone_tracking,
			'office_extension' => $contact_info['office_extension'] ?? '',
		);
	}
	
	private function prepare_photo_field($photo_data, $staff_id = null) {
		if (!$photo_data) return null;

		// Extract necessary fields
		$created = $photo_data['date'] ?? null;
		$modified = $photo_data['modified'] ?? null;

		// Get available sizes as an array of values, filtering out '-width' and '-height'
		$sizes = isset($photo_data['sizes']) 
			? array_values(array_filter(array_keys($photo_data['sizes']), function($size) {
				return !str_ends_with($size, '-width') && !str_ends_with($size, '-height');
			})) 
			: [];

		// Get the large image URL for actual file
		$image_actual_file_url = $photo_data['sizes']['large'] ?? null;

		// Handle dynamic URL generation based on staff_id
		$dynamic_image_url = $staff_id 
			? home_url('/staff-photo/') . '?id=' . urlencode($staff_id) . '&size=large' 
			: null;

		// Return the new structure
		return [
			'created' => $created,
			'modified' => $modified,
			'sizes' => $sizes,
			'image_dynamic_url' => $dynamic_image_url,
			'image_actual_file_url' => $image_actual_file_url,
		];
	}
	
	public function handle_staff_photo_redirect() {
		if (strpos($_SERVER['REQUEST_URI'], '/staff-photo/') !== false) {
			$staff_id = isset($_GET['staff_id']) ? urldecode($_GET['staff_id']) : null;
			$email = isset($_GET['email']) ? urldecode($_GET['email']) : null;
			$size = isset($_GET['size']) ? $_GET['size'] : 'medium';

			if (!$staff_id && !$email) {
				wp_die('Invalid request: staff_id or email required.');
			}

			// Build the query parameter
			$query_param = $staff_id 
				? 'staff_id=' . urlencode($staff_id) 
				: 'email=' . urlencode($email);

			// Call the REST API endpoint
			$response = wp_remote_get(home_url('/wp-json/ve/v1/staff-photo/?' . $query_param . '&size=' . $size));

			// Check for errors in the response
			if (is_wp_error($response)) {
				wp_die('Error fetching photo: ' . $response->get_error_message());
			}

			$body = wp_remote_retrieve_body($response);
			$data = json_decode($body, true);

			// Check if image_url is available in the response
			if (isset($data['image_url']) && !empty($data['image_url'])) {
				wp_redirect($data['image_url']);
				exit;
			}

			// Debugging information (remove in production)
			wp_die('Photo not found or invalid request. Debug Info: ' . print_r($data, true));
		}
	}
	
	// Location Hours Helpers
	public function format_pretty_time($time) {
		return strtoupper(str_replace(' ', '', $time)); // "9:00 am" => "9:00AM"
	}

	public function format_day_range($days) {
		$day_order = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
		$ordered = array_intersect($day_order, $days);

		$ranges = [];
		$start = $end = null;

		foreach ($ordered as $index => $day) {
			if ($start === null) {
				$start = $day;
				$end = $day;
			} elseif (array_search($day, $day_order) === array_search($end, $day_order) + 1) {
				$end = $day;
			} else {
				$ranges[] = ($start === $end) ? $start : "{$start}-{$end}";
				$start = $end = $day;
			}
		}

		if ($start) {
			$ranges[] = ($start === $end) ? $start : "{$start}-{$end}";
		}

		return implode(', ', $ranges);
	}
	
	private function extract_field_name($value) {
		if (is_object($value) && isset($value->name)) {
			return $value->name;
		}

		if (is_array($value)) {
			$first = reset($value); // In case it's an array of objects or IDs
			if (is_object($first) && isset($first->name)) {
				return $first->name;
			} elseif (is_numeric($first)) {
				$term = get_term($first);
				if ($term && !is_wp_error($term)) {
					return $term->name;
				}
			}
		}

		if (is_numeric($value)) {
			$term = get_term($value);
			if ($term && !is_wp_error($term)) {
				return $term->name;
			}
		}

		if (is_string($value)) {
			return $value;
		}

		return 'Not Set';
	}


}

new Ve_Staff_API();
?>
