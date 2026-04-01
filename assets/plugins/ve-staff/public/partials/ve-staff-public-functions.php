<?php
// Public Query Functions used in Posts etc.

function ve_staff_check_page_access() {
	if(is_user_logged_in()){
		return true;
	}
    $host = ve_staff_clean_url($_SERVER['HTTP_HOST'] ?? '');
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $requested_url = $_SERVER['REQUEST_URI'] ?? '';
    $source = '';

    // Determine the source of the request
    if (!empty($referrer)) {
        $referrer_host = ve_staff_clean_url($referrer);

        if ($referrer_host == $host) {
            $source = 'direct';
        } else {
            $source = 'iframe';
        }
    } elseif (strpos($requested_url, 'type=script') !== false) {
        $source = 'script';
    } else {
        $source = 'direct';
    }

    // Get sources to check list against. 
    $check_sources = get_field('ve_allowed_referrer_logic', 'option');

    // Check if the source is in the logic if not return true.
    if (!in_array($source, $check_sources)) {
        return true;
    }

    // Load JSON files
    $theme_dir = get_template_directory();
    $url_list = json_decode(file_get_contents($theme_dir . '/inc/ve-staff/settings/allowed_url_list.json'), true);
    $ip_list = json_decode(file_get_contents($theme_dir . '/inc/ve-staff/settings/allowed_ip_list.json'), true);

    // If referrer host is set, check against URL list
    if (!empty($referrer_host)) {
        if (in_array($referrer_host, $url_list)) {
            return true; // Access granted
        }
    } else {
        // Referrer host not set, check user IP against IP list
        $user_ip = $_SERVER['REMOTE_ADDR'];
        if (in_array($user_ip, $ip_list)) {
            return true; // Access granted
        }
    }

    // Return false if the source is not authorized.
    ve_staff_log_unauthorized_access();
    return false;
}

function ve_staff_clean_url($url) {
    $parsed_url = parse_url($url);
    $host = $parsed_url['host'] ?? '';

    // Remove 'www.' if present
    $host = preg_replace('/^www\./', '', $host);

    return $host;
}

// STAFF VCARD FUNCTIONS //
function staff_clean_up_old_vcards() {
    // Define the directory where vCards are stored
    $upload_dir = wp_upload_dir();
    $vcard_dir = $upload_dir['basedir'] . '/staff/vcards';

    // Check if the directory exists
    if (!file_exists($vcard_dir)) {
        return; // No vCard directory, so nothing to clean up
    }

    // Get all vCard files in the directory
    $vcard_files = glob($vcard_dir . '/*.vcf');

    // Check each file's age
    foreach ($vcard_files as $file) {
        // Get the file's last modified time
        $file_modified_time = filemtime($file);

        // Calculate the file's age in seconds (60 days = 60 * 60 * 24 * 60)
        $file_age = time() - $file_modified_time;

        // If the file is older than 60 days, delete it
        if ($file_age > 60 * 24 * 60 * 60) {
            @unlink($file); // Suppress errors in case of permissions issues
        }
    }
}

// Schedule the cleanup function to run daily
if (!wp_next_scheduled('staff_daily_vcard_cleanup')) {
    wp_schedule_event(time(), 'daily', 'staff_daily_vcard_cleanup');
}

// Hook the cleanup function to the scheduled event
add_action('staff_daily_vcard_cleanup', 'staff_clean_up_old_vcards');

function ve_staff_log_unauthorized_access() {
    $timestamp = date('Y-m-d H:i:s');
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'No Referrer';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Agent';
    
    // Construct the full URL of the request
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'Unknown Host';
    $uri = $_SERVER['REQUEST_URI'] ?? 'Unknown URI';
    $full_url = $scheme . '://' . $host . $uri;

    // Log message
    $log_message = "Timestamp: $timestamp, IP: $user_ip, Referrer: $referrer, User Agent: $user_agent, Attempted URL: $full_url" . PHP_EOL;

    // Define the log file path in the theme directory
    $log_file = get_template_directory() . '/inc/ve-staff/logs/unauthorized_requests.txt';

    // Check if the directory exists, if not create it
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true); // Recursive creation
    }

    // Append the log message to the file
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

function get_staff_list_page_token($post_id) {
    // Use a transient key unique to the list page's post ID
    $transient_key = 'staff_list_page_token_' . $post_id;

    // Check if the token exists in the transient
    $token = get_transient($transient_key);

    // Generate a new token if it doesn't exist
    if (!$token) {
        $token = wp_generate_password(32, false); // Generate a secure random token
        set_transient($transient_key, $token, 12 * HOUR_IN_SECONDS); // Store in transient
    }

    return $token;
}

function staff_handle_vcard_download() {
    if (isset($_GET['vcard']) && $_GET['vcard'] === 'download') {
        $post_id = intval($_GET['staff_id'] ?? 0);
        $list_id = intval($_GET['list_id'] ?? 0);
        $token = $_GET['token'] ?? '';
        $transient_key = 'staff_list_page_token_' . $list_id;

        $expected_token = get_transient($transient_key);

        if (!$expected_token) {
            wp_die('Token expired or not found. Please refresh the page and try again.', 'Error', ['response' => 403, 'back_link' => true]);
        }

        if ($token !== $expected_token) {
            wp_die('Invalid token. Permission denied.', 'Error', [
                'response' => 403,
                'back_link' => true,
            ]);
        }

        $upload_dir = wp_upload_dir();
        $vcf_dir = $upload_dir['basedir'] . '/staff/vcard';

        if (!file_exists($vcf_dir)) {
            wp_mkdir_p($vcf_dir);
        }

        $first_name = get_field('first_name', $post_id) ?: 'staff';
        $last_name = get_field('last_name', $post_id) ?: 'member';
        $file_name = sanitize_title("{$first_name}_{$last_name}") . '.vcf';
        $vcf_file = $vcf_dir . '/' . $file_name;

        function format_phone_number($phone) {
            $cleaned = preg_replace('/\D/', '', $phone);
            return (strlen($cleaned) >= 10) ? '+1' . $cleaned : '';
        }

        function encode_photo_to_base64($photo_url) {
            $photo_data = @file_get_contents($photo_url);
            if ($photo_data) {
                $mime_type = mime_content_type($photo_url);
                $base64_data = base64_encode($photo_data);
                return "PHOTO;ENCODING=b;TYPE=" . strtoupper(pathinfo($photo_url, PATHINFO_EXTENSION)) . ":$base64_data";
            }
            return '';
        }

        if (!file_exists($vcf_file)) {
            $title_term = get_field('title', $post_id);
			$title = $title_term && !is_wp_error($title_term) ? $title_term->name : '';
            $office_contact_info = get_field('office_contact_info', $post_id) ?: [];
            $office_phone_prefix = $office_contact_info['office_phone_prefix'] ?? '';
            $office_extension = $office_contact_info['office_extension'] ?? '';
            $office_other_direct = $office_contact_info['office_other_direct'] ?? '';
            $office_email = $office_contact_info['office_email'] ?? '';
            $office_cell_phone = $office_contact_info['office_cell_phone'] ?? '';
            $mobile_phone = format_phone_number($office_cell_phone);

            // Validate work phone length
            $office_phone_raw = $office_other_direct ?: ($office_phone_prefix . $office_extension);
            $office_phone = (strlen(preg_replace('/\D/', '', $office_phone_raw)) >= 10) ? format_phone_number($office_phone_raw) : '';

            $additional_phones = get_field('additional_phones', $post_id) ?: [];
            $formatted_additional_phones = [];
            foreach ($additional_phones as $phone) {
                $formatted_phone = format_phone_number($phone);
                if (!empty($formatted_phone)) {
                    $formatted_additional_phones[] = $formatted_phone;
                }
            }

            $photo = get_field('photo', $post_id);
            $photo_encoded = $photo && isset($photo['sizes']['medium']) ? encode_photo_to_base64($photo['sizes']['medium']) : '';
            $location = get_field('primary_location', $post_id);
            $location_id = $location->term_id ?? null;
            $location_name = $location->name ?? '';
            $location_data = $location_id ? staff_get_location_data($location_id) : [];
            $address_street = $location_data['street_address'] ?? '';
            $address_city = $location_data['city'] ?? '';
            $address_state = $location_data['state'] ?? '';
            $address_postal = $location_data['postal_code'] ?? '';
            $address_country = $location_data['country'] ?? '';
            $location_website = $location_data['website'] ?? '';

            $vcard = "BEGIN:VCARD\n";
            $vcard .= "VERSION:3.0\n";
            $vcard .= "FN:$first_name $last_name\n";
            $vcard .= "N:$last_name;$first_name;;;\n";
            $vcard .= "TITLE:$title\n";
            $vcard .= "ORG:$location_name\n";
            if ($address_street || $address_city || $address_state || $address_postal || $address_country) {
                $vcard .= "ADR;TYPE=WORK:;;$address_street;$address_city;$address_state;$address_postal;$address_country\n";
            }
            if (!empty($office_phone)) {
                $vcard .= "TEL;TYPE=WORK,VOICE:$office_phone\n";
            }
            if (!empty($mobile_phone)) {
                $vcard .= "TEL;TYPE=CELL:$mobile_phone\n";
            }
            foreach ($formatted_additional_phones as $additional_phone) {
                $vcard .= "TEL;TYPE=OTHER:$additional_phone\n";
            }
            if (!empty($office_email)) {
                $vcard .= "EMAIL;TYPE=WORK:$office_email\n";
            }
            if (!empty($location_website)) {
                $vcard .= "URL;TYPE=WORK:$location_website\n";
            }
            if (!empty($photo_encoded)) {
                $vcard .= "$photo_encoded\n";
            }
            $vcard .= "END:VCARD";

            file_put_contents($vcf_file, $vcard);
        }

        wp_redirect($upload_dir['baseurl'] . '/staff/vcard/' . $file_name);
        exit;
    }
}
add_action('template_redirect', 'staff_handle_vcard_download');

// Scheduled hook to delete old vCard files
function delete_temporary_vcards() {
    $upload_dir = wp_upload_dir();
    $vcf_dir = $upload_dir['basedir'] . '/staff/vcard';

    if (is_dir($vcf_dir)) {
        $files = glob($vcf_dir . '/*.vcf');
        foreach ($files as $file) {
            @unlink($file); // Delete the file
        }
    }
}
add_action('delete_temporary_vcards', 'delete_temporary_vcards');