<?php
/**
 * Public v2 assets. V1 assets and templates intentionally remain unchanged.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Adds explicit CORS headers only for configured embed origins.
 */
function ve_staff_v2_cors_headers() {
	if (!isset($_GET['embed_version']) || '2' !== sanitize_text_field(wp_unslash($_GET['embed_version']))) {
		return;
	}

	$origin = isset($_SERVER['HTTP_ORIGIN']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_ORIGIN'])) : '';
	$allowed_origins = apply_filters('ve_staff_v2_allowed_origins', array());
	if ($origin && in_array($origin, $allowed_origins, true)) {
		header('Access-Control-Allow-Origin: ' . $origin);
		header('Vary: Origin');
	}
	header('Cache-Control: no-store, max-age=0');
}
add_action('send_headers', 've_staff_v2_cors_headers');
