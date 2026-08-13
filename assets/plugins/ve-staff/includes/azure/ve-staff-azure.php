<?php

const VE_STAFF_AZURE_CRON_HOOK = 've_staff_azure_directory_poll';
const VE_STAFF_AZURE_LOG_TABLE = 've_staff_azure_log';

/** @return array<string, mixed> */
function ve_staff_azure_settings(): array {
	$defaults = array(
		'enabled' => false, 'mock_mode' => true, 'tenant_id' => '', 'client_id' => '', 'client_secret' => '',
		'webhook_client_state' => '', 'poll_minutes' => 15,
		'mappings' => array(
			'givenName' => array( 'target' => 'first_name', 'direction' => 'both', 'rules' => array( array( 'type' => 'trim' ) ) ),
			'surname' => array( 'target' => 'last_name', 'direction' => 'both', 'rules' => array( array( 'type' => 'trim' ) ) ),
			'mail' => array( 'target' => 'office_contact_info.office_email', 'direction' => 'both', 'rules' => array( array( 'type' => 'lowercase' ) ) ),
			'mobilePhone' => array( 'target' => 'office_contact_info.office_cell_phone', 'direction' => 'both', 'rules' => array( array( 'type' => 'phone_digits' ) ) ),
			'officeLocation' => array( 'target' => 'taxonomy:location', 'direction' => 'azure_to_wp', 'rules' => array() ),
			'photo' => array( 'target' => 'photo', 'direction' => 'both', 'rules' => array() ),
		),
	);
	$value = get_option( 've_staff_azure_settings', array() );
	return array_replace_recursive( $defaults, is_array( $value ) ? $value : array() );
}

function ve_staff_azure_activate(): void {
	global $wpdb;
	$table   = $wpdb->prefix . VE_STAFF_AZURE_LOG_TABLE;
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( "CREATE TABLE {$table} (id bigint unsigned NOT NULL AUTO_INCREMENT, created_at datetime NOT NULL, level varchar(20) NOT NULL, event varchar(100) NOT NULL, post_id bigint unsigned NULL, context longtext NOT NULL, PRIMARY KEY (id), KEY created_at (created_at), KEY post_id (post_id)) {$charset};" );
	ve_staff_azure_reschedule();
}

function ve_staff_azure_deactivate(): void {
	wp_clear_scheduled_hook( VE_STAFF_AZURE_CRON_HOOK );
	wp_clear_scheduled_hook( 've_staff_azure_prune_logs' );
}

function ve_staff_azure_reschedule(): void {
	wp_clear_scheduled_hook( VE_STAFF_AZURE_CRON_HOOK );
	$settings = ve_staff_azure_settings();
	$minutes  = max( 5, (int) $settings['poll_minutes'] );
	wp_schedule_event( time() + 60, 've_staff_azure_interval', VE_STAFF_AZURE_CRON_HOOK );
	if ( ! wp_next_scheduled( 've_staff_azure_prune_logs' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 've_staff_azure_prune_logs' );
	}
	update_option( 've_staff_azure_poll_interval_seconds', $minutes * MINUTE_IN_SECONDS, false );
}

/** @param array<string, mixed> $context */
function ve_staff_azure_log( string $level, string $event, array $context ): void {
	global $wpdb;
	$post_id = isset( $context['post_id'] ) ? (int) $context['post_id'] : null;
	$wpdb->insert( $wpdb->prefix . VE_STAFF_AZURE_LOG_TABLE, array( 'created_at' => current_time( 'mysql', true ), 'level' => $level, 'event' => $event, 'post_id' => $post_id, 'context' => wp_json_encode( $context ) ), array( '%s', '%s', '%s', '%d', '%s' ) );
}

/** @param mixed $value @param array<int, array<string, mixed>> $rules @return mixed */
function ve_staff_azure_apply_rules( $value, array $rules ) {
	$result = $value;
	foreach ( $rules as $rule ) {
		$type = isset( $rule['type'] ) ? (string) $rule['type'] : '';
		if ( 'trim' === $type && is_string( $result ) ) { $result = trim( $result ); }
		elseif ( 'lowercase' === $type && is_string( $result ) ) { $result = strtolower( $result ); }
		elseif ( 'uppercase' === $type && is_string( $result ) ) { $result = strtoupper( $result ); }
		elseif ( 'phone_digits' === $type && is_string( $result ) ) { $result = preg_replace( '/[^0-9+]/', '', $result ); }
		elseif ( 'replace' === $type && is_string( $result ) ) { $result = str_replace( (string) ( $rule['search'] ?? '' ), (string) ( $rule['replacement'] ?? '' ), $result ); }
		elseif ( 'regex' === $type && is_string( $result ) ) { $result = preg_replace( (string) ( $rule['pattern'] ?? '' ), (string) ( $rule['replacement'] ?? '' ), $result ); }
		elseif ( 'value_map' === $type ) { $map = is_array( $rule['map'] ?? null ) ? $rule['map'] : array(); $result = $map[ (string) $result ] ?? $result; }
	}
	return $result;
}

function ve_staff_azure_connector(): Ve_Staff_Azure_Graph_Connector {
	$s = ve_staff_azure_settings();
	if ( '' === $s['tenant_id'] || '' === $s['client_id'] || '' === $s['client_secret'] ) { throw new RuntimeException( 'Azure tenant ID, client ID, and client secret are required.' ); }
	return new Ve_Staff_Azure_Graph_Connector( (string) $s['tenant_id'], (string) $s['client_id'], (string) $s['client_secret'] );
}

/** @param array<string, mixed> $user */
function ve_staff_azure_import_user( array $user, string $source ): void {
	$azure_id = sanitize_text_field( (string) ( $user['id'] ?? '' ) );
	if ( '' === $azure_id ) { throw new InvalidArgumentException( 'Azure user event is missing id.' ); }
	$posts = get_posts( array( 'post_type' => 'staff', 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => '_ve_staff_azure_id', 'meta_value' => $azure_id ) );
	$post_id = $posts ? (int) $posts[0]->ID : 0;
	if ( 0 === $post_id && ! empty( $user['mail'] ) ) {
		$posts = get_posts( array( 'post_type' => 'staff', 'post_status' => 'any', 'numberposts' => 1, 'meta_key' => 'office_contact_info_office_email', 'meta_value' => sanitize_email( (string) $user['mail'] ) ) );
		$post_id = $posts ? (int) $posts[0]->ID : 0;
	}
	if ( 0 === $post_id ) { throw new RuntimeException( 'No staff post matches Azure user ' . $azure_id . '.' ); }
	$settings = ve_staff_azure_settings();
	$changes  = array();
	foreach ( $settings['mappings'] as $azure_field => $mapping ) {
		if ( ! array_key_exists( $azure_field, $user ) || ! in_array( $mapping['direction'], array( 'both', 'azure_to_wp' ), true ) ) { continue; }
		$value = ve_staff_azure_apply_rules( $user[ $azure_field ], is_array( $mapping['rules'] ?? null ) ? $mapping['rules'] : array() );
		$changes[] = array( 'azure_field' => $azure_field, 'target' => $mapping['target'], 'value' => $value );
		if ( ! $settings['mock_mode'] ) { ve_staff_azure_write_target( $post_id, (string) $mapping['target'], $value ); }
	}
	if ( ! $settings['mock_mode'] ) { update_post_meta( $post_id, '_ve_staff_azure_id', $azure_id ); update_post_meta( $post_id, '_ve_staff_azure_importing', time() ); }
	ve_staff_azure_log( 'info', $settings['mock_mode'] ? 'mock_import' : 'import', array( 'post_id' => $post_id, 'azure_id' => $azure_id, 'source' => $source, 'changes' => $changes ) );
}

/** @param mixed $value */
function ve_staff_azure_write_target( int $post_id, string $target, $value ): void {
	if ( 0 === strpos( $target, 'taxonomy:' ) ) {
		$taxonomy = substr( $target, 9 ); $term = get_term_by( 'slug', sanitize_title( (string) $value ), $taxonomy );
		if ( ! $term ) { $term = get_term_by( 'name', (string) $value, $taxonomy ); }
		if ( ! $term ) { throw new RuntimeException( sprintf( 'Mapped taxonomy term not found: taxonomy=%s value=%s', $taxonomy, (string) $value ) ); }
		wp_set_object_terms( $post_id, array( (int) $term->term_id ), $taxonomy, false ); update_field( 'primary_location', (int) $term->term_id, $post_id ); return;
	}
	$parts = explode( '.', $target );
	if ( 2 === count( $parts ) ) { $group = get_field( $parts[0], $post_id ); $group = is_array( $group ) ? $group : array(); $group[ $parts[1] ] = $value; update_field( $parts[0], $group, $post_id ); return; }
	update_field( $target, $value, $post_id );
}

function ve_staff_azure_poll(): void {
	$s = ve_staff_azure_settings(); if ( ! $s['enabled'] ) { return; }
	$url = (string) get_option( 've_staff_azure_delta_link', 'https://graph.microsoft.com/v1.0/users/delta?$select=id,givenName,surname,mail,mobilePhone,officeLocation' );
	do { $page = ve_staff_azure_connector()->get_json( $url ); foreach ( (array) ( $page['value'] ?? array() ) as $user ) { if ( is_array( $user ) && empty( $user['@removed'] ) ) { ve_staff_azure_import_user( $user, 'poll' ); } } $url = (string) ( $page['@odata.nextLink'] ?? '' ); } while ( '' !== $url );
	if ( ! empty( $page['@odata.deltaLink'] ) ) { update_option( 've_staff_azure_delta_link', esc_url_raw( (string) $page['@odata.deltaLink'] ), false ); }
}

function ve_staff_azure_webhook( WP_REST_Request $request ): WP_REST_Response {
	$validation = $request->get_param( 'validationToken' ); if ( is_string( $validation ) ) { return new WP_REST_Response( $validation, 200, array( 'Content-Type' => 'text/plain' ) ); }
	$body = $request->get_json_params(); $s = ve_staff_azure_settings();
	foreach ( (array) ( $body['value'] ?? array() ) as $event ) {
		if ( ! hash_equals( (string) $s['webhook_client_state'], (string) ( $event['clientState'] ?? '' ) ) ) { throw new RuntimeException( 'Azure webhook clientState validation failed.' ); }
		$id = basename( (string) ( $event['resourceData']['id'] ?? $event['resource'] ?? '' ) ); $user = ve_staff_azure_connector()->get_json( 'https://graph.microsoft.com/v1.0/users/' . rawurlencode( $id ) . '?$select=id,givenName,surname,mail,mobilePhone,officeLocation' ); ve_staff_azure_import_user( $user, 'webhook' );
	}
	return new WP_REST_Response( null, 202 );
}

function ve_staff_azure_export_post( int $post_id, WP_Post $post, bool $update ): void {
	if ( 'staff' !== $post->post_type || wp_is_post_revision( $post_id ) || 'publish' !== $post->post_status ) { return; }
	$s = ve_staff_azure_settings(); $azure_id = (string) get_post_meta( $post_id, '_ve_staff_azure_id', true );
	if ( ! $s['enabled'] || '' === $azure_id || (int) get_post_meta( $post_id, '_ve_staff_azure_importing', true ) >= time() - 30 ) { return; }
	$payload = array();
	foreach ( $s['mappings'] as $azure_field => $mapping ) { if ( in_array( $mapping['direction'], array( 'both', 'wp_to_azure' ), true ) && 'photo' !== $azure_field ) { $payload[ $azure_field ] = ve_staff_azure_apply_rules( ve_staff_azure_read_target( $post_id, (string) $mapping['target'] ), (array) ( $mapping['rules'] ?? array() ) ); } }
	if ( ! $s['mock_mode'] ) {
		$connector = ve_staff_azure_connector();
		$connector->patch_json( 'https://graph.microsoft.com/v1.0/users/' . rawurlencode( $azure_id ), $payload );
		$photo     = get_field( 'photo', $post_id );
		$photo_id  = is_array( $photo ) ? (int) ( $photo['ID'] ?? 0 ) : (int) $photo;
		$path     = $photo_id > 0 ? get_attached_file( $photo_id ) : '';
		if ( is_string( $path ) && is_readable( $path ) ) {
			$mime = (string) get_post_mime_type( $photo_id );
			$connector->put_binary( 'https://graph.microsoft.com/v1.0/users/' . rawurlencode( $azure_id ) . '/photo/$value', $mime, (string) file_get_contents( $path ) );
		}
	}
	ve_staff_azure_log( 'info', $s['mock_mode'] ? 'mock_export' : 'export', array( 'post_id' => $post_id, 'azure_id' => $azure_id, 'payload' => $payload ) );
}

/** @return mixed */
function ve_staff_azure_read_target( int $post_id, string $target ) { $parts = explode( '.', $target ); if ( 2 === count( $parts ) ) { $group = get_field( $parts[0], $post_id ); return is_array( $group ) ? ( $group[ $parts[1] ] ?? '' ) : ''; } return get_field( $target, $post_id ); }

function ve_staff_azure_prune_logs(): void { global $wpdb; $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . $wpdb->prefix . VE_STAFF_AZURE_LOG_TABLE . ' WHERE created_at < %s', gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS ) ) ); }

function ve_staff_azure_register_meta_box(): void { add_meta_box( 've-staff-azure-history', 'Azure sync history', 've_staff_azure_render_meta_box', 'staff', 'normal', 'default' ); }
function ve_staff_azure_render_meta_box( WP_Post $post ): void { global $wpdb; $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT created_at,event,context FROM ' . $wpdb->prefix . VE_STAFF_AZURE_LOG_TABLE . ' WHERE post_id=%d ORDER BY id DESC LIMIT 25', $post->ID ), ARRAY_A ); echo '<table class="widefat"><thead><tr><th>UTC time</th><th>Event</th><th>Details</th></tr></thead><tbody>'; foreach ( $rows as $row ) { echo '<tr><td>' . esc_html( $row['created_at'] ) . '</td><td>' . esc_html( $row['event'] ) . '</td><td><code>' . esc_html( $row['context'] ) . '</code></td></tr>'; } echo '</tbody></table>'; }

add_filter( 'cron_schedules', static function ( array $schedules ): array { $seconds = max( 300, (int) get_option( 've_staff_azure_poll_interval_seconds', 900 ) ); $schedules['ve_staff_azure_interval'] = array( 'interval' => $seconds, 'display' => 'Azure staff sync' ); return $schedules; } );
add_action( VE_STAFF_AZURE_CRON_HOOK, 've_staff_azure_poll' );
add_action( 've_staff_azure_prune_logs', 've_staff_azure_prune_logs' );
add_action( 'rest_api_init', static function (): void { register_rest_route( 've-staff/v1', '/azure/webhook', array( 'methods' => 'POST', 'callback' => 've_staff_azure_webhook', 'permission_callback' => '__return_true' ) ); } );
add_action( 'add_meta_boxes_staff', 've_staff_azure_register_meta_box' );

function ve_staff_azure_export_after_acf( $post_id ): void {
	if ( ! is_numeric( $post_id ) ) { return; }
	$post = get_post( (int) $post_id );
	if ( $post instanceof WP_Post ) { ve_staff_azure_export_post( (int) $post_id, $post, true ); }
}
add_action( 'acf/save_post', 've_staff_azure_export_after_acf', 100 );

function ve_staff_azure_admin_menu(): void { add_submenu_page( 'edit.php?post_type=staff', 'Azure sync', 'Azure sync', 'manage_options', 've-staff-azure', 've_staff_azure_settings_page' ); }
function ve_staff_azure_register_settings(): void { register_setting( 've_staff_azure', 've_staff_azure_settings', array( 'type' => 'array', 'sanitize_callback' => 've_staff_azure_sanitize_settings' ) ); }
/** @param mixed $input @return array<string, mixed> */
function ve_staff_azure_sanitize_settings( $input ): array {
	if ( ! is_array( $input ) ) { throw new InvalidArgumentException( 'Azure settings must be an array.' ); }
	$mappings = json_decode( (string) ( $input['mappings_json'] ?? '{}' ), true );
	if ( ! is_array( $mappings ) ) { throw new InvalidArgumentException( 'Azure field mappings must be valid JSON.' ); }
	$result = array( 'enabled' => ! empty( $input['enabled'] ), 'mock_mode' => ! empty( $input['mock_mode'] ), 'tenant_id' => sanitize_text_field( (string) ( $input['tenant_id'] ?? '' ) ), 'client_id' => sanitize_text_field( (string) ( $input['client_id'] ?? '' ) ), 'client_secret' => sanitize_text_field( (string) ( $input['client_secret'] ?? '' ) ), 'webhook_client_state' => sanitize_text_field( (string) ( $input['webhook_client_state'] ?? '' ) ), 'poll_minutes' => max( 5, (int) ( $input['poll_minutes'] ?? 15 ) ), 'mappings' => $mappings );
	delete_transient( 've_staff_azure_access_token' ); return $result;
}
function ve_staff_azure_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You cannot manage Azure sync.', 've-staff' ) ); }
	$s = ve_staff_azure_settings(); $fields = array( 'tenant_id' => 'Tenant ID', 'client_id' => 'Client ID', 'client_secret' => 'Client secret', 'webhook_client_state' => 'Webhook client state', 'poll_minutes' => 'Polling interval (minutes)' );
	echo '<div class="wrap"><h1>Microsoft Azure staff sync</h1><p>Use webhook URL <code>' . esc_html( rest_url( 've-staff/v1/azure/webhook' ) ) . '</code>. Microsoft Graph application permissions require <code>User.Read.All</code>, <code>User.ReadWrite.All</code>, and <code>ProfilePhoto.ReadWrite.All</code>.</p><form method="post" action="options.php">'; settings_fields( 've_staff_azure' ); echo '<table class="form-table">';
	foreach ( $fields as $key => $label ) { $type = 'client_secret' === $key ? 'password' : ( 'poll_minutes' === $key ? 'number' : 'text' ); echo '<tr><th><label for="azure-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="azure-' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" name="ve_staff_azure_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( (string) $s[ $key ] ) . '"></td></tr>'; }
	echo '<tr><th>Operation</th><td><label><input type="checkbox" name="ve_staff_azure_settings[enabled]" value="1" ' . checked( true, (bool) $s['enabled'], false ) . '> Enable synchronization</label><br><label><input type="checkbox" name="ve_staff_azure_settings[mock_mode]" value="1" ' . checked( true, (bool) $s['mock_mode'], false ) . '> Mock mode (log only; do not mutate either system)</label></td></tr><tr><th><label for="azure-mappings">Mappings and rules (JSON)</label></th><td><textarea class="large-text code" rows="20" id="azure-mappings" name="ve_staff_azure_settings[mappings_json]">' . esc_textarea( (string) wp_json_encode( $s['mappings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) . '</textarea><p class="description">Targets may be ACF fields, group.subfield, or taxonomy:name. Rules run in order and support trim, lowercase, uppercase, phone_digits, replace, regex, and value_map. Use value_map for unique scenarios such as mapping “Vern Eide Honda” to a WordPress location slug.</p></td></tr></table>'; submit_button(); echo '</form></div>';
}
add_action( 'admin_menu', 've_staff_azure_admin_menu' );
add_action( 'admin_init', 've_staff_azure_register_settings' );
add_action( 'update_option_ve_staff_azure_settings', static function (): void { ve_staff_azure_reschedule(); } );
