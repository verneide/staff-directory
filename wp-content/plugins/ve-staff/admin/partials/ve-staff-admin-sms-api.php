<?php 
// Staff SMS API Class
if ( class_exists( 'Ve_Staff_SMS' ) ) :

class Ve_Staff_SMS_API extends Ve_Staff_SMS {
	
	public function __construct( ) {
		// Staff SMS API Authentication
		//add_action( 'rest_api_init', array( $this, 'staff_rest_api_authentication' ) );
		
		// Staff SMS Webhook
		add_action( 'rest_api_init', array( $this, 'sms_incoming_webhook_endpoint' ) );
		add_action( 'rest_api_init', array( $this, 'sms_incoming_webhook_vendor_endpoints' ) );
		
	}
	
	// Staff SMS Authentication // DOESNT WORK YET AND NOT ENABLED UNCOMMENT ABOVE TO ENABLE
	public function staff_rest_api_authentication() {
		// Get the current rest route
		$rest_route = $GLOBALS['wp']->query_vars['rest_route'];
		// Check if the route starts with 've'
		if ( strpos( $rest_route, '/ve' ) !== 0 ) {
			return;
		}

		// If it is, add the authentication filter
		add_filter( 'rest_authentication_errors', function( $result ) {
			return 'test';
			// If a previous authentication check was applied, do nothing.
			if ( ! empty( $result ) ) {
				return $result;
			}

			// Decode the authorization header
			$auth_header = base64_decode( $_SERVER['HTTP_AUTHORIZATION'] );
			
			// Extract username and password from the authorization header
			list( $username, $password ) = explode( ':', $auth_header );
			

			// If either the username or password is empty, return an error response.
			if ( empty( $username ) || empty( $password ) ) {
				return new WP_Error( 'rest_authentication_error', 'Authentication failed: username or password not provided.', array( 'status' => 401 ) );
			}

			// Validate the authorization header
			if ( ! validate_rest_api_authorization_header( $username, $password ) ) {
				return new WP_Error( 'rest_authentication_error', 'Authentication failed: invalid username, password or application password.', array( 'status' => 401 ) );
			}

			// Check if the user exists and the password is correct
			$user = wp_authenticate( $username, $password );
			if ( is_wp_error( $user ) ) {
				// Try authenticating with an application password
				$result = wp_authenticate_application_password( 'WP_User', $username, $password );
				if ( is_wp_error( $result ) ) {
					return new WP_Error( 'rest_authentication_error', 'Authentication failed: invalid username, password or application password.', array( 'status' => 401 ) );
				}
				$user = $result['user'];
			}
			return $user;
		});
	}

	// Staff SMS Rest Endpoint
	public function sms_incoming_webhook_endpoint() {
		register_rest_route( 've/v1', '/sms/incoming', array(
			'methods' => 'POST',
			'callback' => array($this, 'sms_incoming_webhook'),
		) );
	}
	
	public function sms_incoming_webhook_vendor_endpoints() {
		register_rest_route( 've/v1', '/sms/incoming/udrive', array(
			'methods' => 'POST',
			'callback' => array($this, 'sms_incoming_udrive'),
		) );
	}
	
	public function get_requesting_ip_address() {
		$ip_address = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			// Check for the IP address from shared internet connection
			$ip_address = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Check for the IP address from a proxy server
			$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			// Get the remote IP address
			$ip_address = $_SERVER['REMOTE_ADDR'];
		}

		return $ip_address;
	}
	
	public function return_request_http_info() {
	
		$http_info = array();

		// Get the request method
		$http_info['request_method'] = $_SERVER['REQUEST_METHOD'];
		// Get the request URI
		$http_info['request_uri'] = $_SERVER['REQUEST_URI'];
		// Get the request origin
		$http_info['request_origin'] = $_SERVER['HTTP_ORIGIN'];
		// Get the protocol version
		$http_info['protocol_version'] = $_SERVER['SERVER_PROTOCOL'];
		// Get the user agent
		$http_info['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
		// Get the accept header
		$http_info['accept'] = $_SERVER['HTTP_ACCEPT'];
		// Get the content type
		$http_info['content_type'] = $_SERVER['CONTENT_TYPE'];
		// Get the referring page
		$http_info['referrer'] = $_SERVER['HTTP_REFERER'];
		// Get the requesting IP address
		$http_info['ip_address'] = $this->get_requesting_ip_address();
		
		return $http_info;
	}
	
	public function api_request_access($request){
		// Define the whitelist of originating domains
		$whitelist = array(
			'udrivetech.com',
			'mytowntext.com'
		);

		if (in_array($_SERVER['HTTP_ORIGIN'], $whitelist)) {
			return true;
		} else {
			return $_SERVER['HTTP_ORIGIN'];
		}
	}
	
	public function clean_phone_number($phone_number) {
	  // Remove all non-numeric characters
	  $cleaned_number = preg_replace('/\D/', '', $phone_number);

	  // Remove leading "1" if present
	  if (strlen($cleaned_number) == 11 && substr($cleaned_number, 0, 1) == '1') {
		$cleaned_number = substr($cleaned_number, 1);
	  }

	  // Return the last 10 digits of the cleaned phone number
	  return substr($cleaned_number, -10);
	}
	
	function sms_incoming_udrive( WP_REST_Request $request ) {
	  //$raw_request = file_get_contents("php://input");
	  $vendor = 'UDrive';
		
	  //return $this->return_request_http_info();
		
	  // Checks request URL in whitelist	
	  //$apiaccess = $this->api_request_access($request);
	  //if($apiaccess == false){
		  //return 'API ACCESS DENIED';
	  //}
	  //return $apiaccess;
	  

	  // Get the body if it exists in the request
	  if ($request->get_body()) {
	    $request_body = $request->get_body();
		// Check if the data is URL-encoded
	    if (strpos($request_body, '%') !== false) {
		   // Decode the URL-encoded data
		   $request_body = urldecode($request_body);
	    }
	    // Check if the request is prefixed with "content="
	    if (strpos($request_body, "content=") === 0) {
	      // Remove the "content=" prefix
	      $request_body = substr($request_body, strpos($request_body, "=") + 1);
	    }
	    $request_data = json_decode($request_body, true); // Decode the JSON data into an associative array
	  } else {
		$request_body = file_get_contents('php://input');
		// Check if the data is URL-encoded
	    if (strpos($request_body, '%') !== false) {
		   // Decode the URL-encoded data
		   $request_body = urldecode($request_body);
	    }
	    // Check if the request is prefixed with "content="
	    if (strpos($request_body, "content=") === 0) {
	      // Remove the "content=" prefix
	      $request_body = substr($request_body, strpos($request_body, "=") + 1);
	    }
	    $request_data = json_decode($request_body, true); // Decode the JSON data into an associative array
	  }
		
	  // Generate a unique log filename
	  $logfilename = 'log-incoming-sms-' . date("Ymd_His") . '.txt';

	  // Save the data to a file
	  file_put_contents(VE_STAFF_PLUGIN_DIR."logs/{$logfilename}", $request_data);
	  
	  $msgID = $request_data['ReceivedMessage']['MessageID'];
	  $from_number = $request_data['ReceivedMessage']['FromNumber'];
	  $from_number = $this->clean_phone_number($from_number);
	  $to_number = $request_data['ReceivedMessage']['ToNumber'];
	  $to_number = $this->clean_phone_number($to_number);
	  $message = $request_data['ReceivedMessage']['Message'];
		
	  $optOutKeyword = get_field('ve_staff_sms_optout_keyword', 'option');	
	  $stopKeyword = 'STOP '.$optOutKeyword;
	  $startKeyword = 'START '.$optOutKeyword;

	  // Check if the message contains the STOP keyword
	  if (strpos(strtoupper($message), $stopKeyword) !== false) {
		// Search for the staff post that matches the FromNumber value
		$staff_query = new WP_Query(array(
		  'post_type' => 'staff',
		  'meta_query' => array(
			array(
			  'key' => 'office_contact_info_office_cell_phone',
			  'value' => $from_number,
			  'compare' => '='
			)
		  )
		));

		// If a staff post is found, update the office_sms_status custom field to FALSE
		if ($staff_query->have_posts()) {
		  while ($staff_query->have_posts()) {
			$staff_query->the_post();
			$staffID = get_the_ID();
			update_post_meta(get_the_ID(), 'office_contact_info_office_sms_status', false);
			$action = "SMS Unsubscribed";
			$actiontype = "Keyword";
			$actionref = $stopKeyword;
			$actionmessage = "Profile unsubscribed from SMS Communication";
			
			// Update Revision History
			$sms_history_log_exisiting = get_field( 'sms_history_log' );
			if ( ! is_array($sms_history_log_exisiting) ) $sms_history_log_exisiting = [];
			  
			// Set the default timezone to the WordPress timezone
			date_default_timezone_set(get_option('timezone_string'));

			$sms_history_log_add = [
				[
					'action' => $action,
					'source' => 'Incoming SMS Event - '.$vendor,
					'ref_notes' => 'An unsubscribe keyword was found in the message "'.$message.'".',
					'timestamp' => date('m/d/Y h:i:s a', time())
				]
			];

			$sms_history_log_updated = array_merge($sms_history_log_exisiting, $sms_history_log_add);
			update_field( 'sms_history_log', $sms_history_log_updated );
			// End Revision History
			
			// Send unsubscribe msg  
			$this->sms_send_unsubscribe_msg($from_number);
		  }
		  wp_reset_postdata();
		  
		  return new WP_REST_Response(array(
				'success' => true,
				'msgID' 	=> $msgID,
				'staffID' 	=> $staffID,
				'vendor' 	=> $vendor,
				'action'	=> $action,
				'actiontype'=> $actiontype,
				'actionref' => $actionref,
				'message'	=> $actionmessage
		   )); // Return a success response
		}else{
		// NO PROFILE FOUND USING THE FROM NUMBER
			return new WP_REST_Response(array(
				'success' => false,
				'msgID' 	=> $msgID,
				'staffID' 	=> '',
				'vendor' 	=> $vendor,
				'action'	=> '',
				'actiontype'=> '',
				'actionref' => '',
				'message'	=> "We're unable to find your number in our system, and only registered numbers can UNSUBSCRIBE to this service. If you believe this is a mistake or if you wish to be added, please reach out to the service administrator for assistance."
			)); // Return a unsucessfull response
		}
	  } // End Stop Keyword
		
	// Check if the message contains the START keyword
	  if (strpos(strtoupper($message), $startKeyword) !== false) {
		// Search for the staff post that matches the FromNumber value
		$staff_query = new WP_Query(array(
		  'post_type' => 'staff',
		  'meta_query' => array(
			array(
			  'key' => 'office_contact_info_office_cell_phone',
			  'value' => $from_number,
			  'compare' => '='
			)
		  )
		));

		// If a staff post is found, update the office_sms_status custom field to FALSE
		if ($staff_query->have_posts()) {
		  while ($staff_query->have_posts()) {
			$staff_query->the_post();
			$staffID = get_the_ID();
			update_post_meta(get_the_ID(), 'office_contact_info_office_sms_status', true);
			$action = "SMS Subscribed";
			$actiontype = "Keyword";
			$actionref = $startKeyword;
			$actionmessage = "Profile subscribed to SMS Communication";
			
			// Update Revision History
			$sms_history_log_exisiting = get_field( 'sms_history_log' );
			if ( ! is_array($sms_history_log_exisiting) ) $sms_history_log_exisiting = [];

			// Set the default timezone to the WordPress timezone
			date_default_timezone_set(get_option('timezone_string'));  
			  
			$sms_history_log_add = [
				[
					'action' => $action,
					'source' => 'Incoming SMS Event - '.$vendor,
					'ref_notes' => 'An subscribe keyword was found in the message "'.$message.'".',
					'timestamp' => date('m/d/Y h:i:s a', time())
				]
			];

			$sms_history_log_updated = array_merge($sms_history_log_exisiting, $sms_history_log_add);
			update_field( 'sms_history_log', $sms_history_log_updated );
			// End Revision History
			
			// Send subscribed msg  
			$this->sms_send_subscribed_msg($from_number);
			
		  }
		  wp_reset_postdata();
		  
		  return new WP_REST_Response(array(
				'success' => true,
				'msgID' 	=> $msgID,
				'staffID' 	=> $staffID,
				'vendor' 	=> $vendor,
				'action'	=> $action,
				'actiontype'=> $actiontype,
				'actionref' => $actionref,
				'message'	=> $actionmessage
		   )); // Return a success response
		}else{
		// NO PROFILE FOUND USING THE FROM NUMBER
			return new WP_REST_Response(array(
				'success' => false,
				'msgID' 	=> $msgID,
				'staffID' 	=> '',
				'vendor' 	=> $vendor,
				'action'	=> '',
				'actiontype'=> '',
				'actionref' => '',
				'message'	=> "We're unable to find your number in our system, and only registered numbers can SUBSCRIBE to this service. If you believe this is a mistake or if you wish to be added, please reach out to the service administrator for assistance."
			)); // Return a unsucessfull response
		}
	  } // End Start Keyword

	}
	
	public function sms_incoming_webhook($request) {
		// Define the whitelist of originating domains
		$whitelist = array(
			'udrivetech.com',
			'mytowntext.com'
		);

		// Check if the request is from an allowed domain
		if (in_array($_SERVER['HTTP_ORIGIN'], $whitelist)) {
			// Check if the request is for the "incoming-sms" endpoint
			if (strpos($_SERVER['REQUEST_URI'], '/ve/incoming-sms') !== false) {
				// Check if the request is a POST request
				if ($_SERVER['REQUEST_METHOD'] == 'POST') {
					// Get the data from the webhook
					$data = $_POST['data'];

					// Check for the specific keyword in the message
					if (strpos($data['message'], 'STOP EA') !== false) {
						$this->sms_unsubscribe_msg($data);
					}
				} else {
					return "ERROR";
				}
			}
		} else {
			return "Domain: ".$_SERVER['HTTP_ORIGIN']." Not Allowed";
		}
	}
	
}

$smsapi = new Ve_Staff_SMS_API();


endif; // class_exists check