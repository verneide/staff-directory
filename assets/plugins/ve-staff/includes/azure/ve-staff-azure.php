<?php

const VE_STAFF_AZURE_CRON_HOOK = 've_staff_azure_directory_poll';
const VE_STAFF_AZURE_LOG_TABLE = 've_staff_azure_log';
const VE_STAFF_AZURE_PERMISSIONS_OPTION = 've_staff_azure_permissions';
const VE_STAFF_AZURE_PERMISSION_MAX_AGE = DAY_IN_SECONDS;

/** @return array<int, string> */
function ve_staff_azure_common_user_fields(): array {
	return array( 'displayName', 'givenName', 'surname', 'mail', 'userPrincipalName', 'jobTitle', 'department', 'companyName', 'employeeId', 'employeeType', 'mobilePhone', 'businessPhones', 'officeLocation', 'city', 'state', 'postalCode', 'streetAddress', 'country', 'preferredLanguage', 'onPremisesSamAccountName', 'onPremisesDistinguishedName', 'onPremisesExtensionAttributes' );
}

/** @param array<string, mixed> $user @return array<string, mixed> */
function ve_staff_azure_add_derived_fields( array $user ): array {
	$distinguished_name = (string) ( $user['onPremisesDistinguishedName'] ?? '' );
	if ( '' !== $distinguished_name && preg_match_all( '/(?:^|,)OU=((?:\\\\.|[^,])*)/i', $distinguished_name, $matches ) ) {
		$user['organizationalUnit'] = implode( ' / ', array_map( static fn( string $value ): string => str_replace( '\\,', ',', $value ), $matches[1] ) );
	}
	return $user;
}

/** @param array<string, mixed> $values @return mixed */
function ve_staff_azure_read_source( array $values, string $path ) {
	$value = $values;
	foreach ( explode( '.', $path ) as $part ) {
		if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) { return null; }
		$value = $value[ $part ];
	}
	return $value;
}

/** @param array<string, mixed> $values @return array<string, string> */
function ve_staff_azure_discovered_fields( array $values ): array {
	$fields = array();
	foreach ( $values as $name => $value ) {
		if ( 'id' === $name || 0 === strpos( (string) $name, '@' ) ) { continue; }
		if ( is_array( $value ) && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			foreach ( $value as $child_name => $child_value ) {
				if ( null !== $child_value && '' !== $child_value ) { $fields[ $name . '.' . $child_name ] = is_scalar( $child_value ) ? (string) $child_value : (string) wp_json_encode( $child_value ); }
			}
		} elseif ( null !== $value && '' !== $value ) {
			$fields[ (string) $name ] = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
		}
	}
	ksort( $fields );
	return $fields;
}

/** @param array<string, mixed> $mappings */
function ve_staff_azure_user_select( array $mappings ): string {
	$fields = array( 'id', 'mail', 'onPremisesDistinguishedName' );
	foreach ( array_keys( $mappings ) as $field ) {
		$root = explode( '.', (string) $field )[0];
		if ( ! in_array( $root, array( 'photo', 'organizationalUnit' ), true ) ) { $fields[] = $root; }
	}
	return implode( ',', array_unique( $fields ) );
}

/** @return array<string, mixed> */
function ve_staff_azure_settings(): array {
	$defaults = array(
		'enabled' => false, 'mock_mode' => true, 'tenant_id' => '', 'client_id' => '', 'client_secret' => '', 'client_secret_expires' => '',
		'webhook_client_state' => '', 'poll_minutes' => 15,
		'mappings' => array(
			'givenName' => array( 'target' => 'first_name', 'direction' => 'azure_to_wp', 'rules' => array( array( 'type' => 'trim' ) ) ),
			'surname' => array( 'target' => 'last_name', 'direction' => 'azure_to_wp', 'rules' => array( array( 'type' => 'trim' ) ) ),
			'mail' => array( 'target' => 'office_contact_info.office_email', 'direction' => 'azure_to_wp', 'rules' => array( array( 'type' => 'lowercase' ) ) ),
			'mobilePhone' => array( 'target' => 'office_contact_info.office_cell_phone', 'direction' => 'wp_to_azure', 'rules' => array( array( 'type' => 'phone_digits' ) ) ),
			'officeLocation' => array( 'target' => 'taxonomy:location', 'direction' => 'azure_to_wp', 'rules' => array() ),
			'photo' => array( 'target' => 'photo', 'direction' => 'wp_to_azure', 'rules' => array() ),
		),
	);
	$value = get_option( 've_staff_azure_settings', array() );
	$settings = array_replace_recursive( $defaults, is_array( $value ) ? $value : array() );
	foreach ( $settings['mappings'] as $field => $mapping ) {
		if ( 'both' === ( $mapping['direction'] ?? '' ) ) {
			$settings['mappings'][ $field ]['direction'] = in_array( $field, array( 'mobilePhone', 'photo' ), true ) ? 'wp_to_azure' : 'azure_to_wp';
		}
	}
	return $settings;
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

/** @return array<string, bool> */
function ve_staff_azure_permission_capabilities( array $permissions ): array {
	$can_write_users = in_array( 'User.ReadWrite.All', $permissions, true );
	return array(
		'read_users'   => $can_write_users || in_array( 'User.Read.All', $permissions, true ),
		'write_users'  => $can_write_users,
		'write_photos' => in_array( 'ProfilePhoto.ReadWrite.All', $permissions, true ),
	);
}

function ve_staff_azure_credentials_fingerprint( string $tenant_id, string $client_id, string $client_secret ): string {
	return hash( 'sha256', $tenant_id . "\0" . $client_id . "\0" . $client_secret );
}

/** @return array<string, mixed> */
function ve_staff_azure_store_permissions( array $permissions, string $tenant_id, string $client_id, string $client_secret ): array {
	$state = array(
		'permissions' => array_values( array_map( 'strval', $permissions ) ),
		'capabilities' => ve_staff_azure_permission_capabilities( $permissions ),
		'checked_at' => time(),
		'credentials_fingerprint' => ve_staff_azure_credentials_fingerprint( $tenant_id, $client_id, $client_secret ),
	);
	update_option( VE_STAFF_AZURE_PERMISSIONS_OPTION, $state, false );
	return $state;
}

/** @return array<string, mixed> */
function ve_staff_azure_permission_state(): array {
	$settings = ve_staff_azure_settings();
	$state = get_option( VE_STAFF_AZURE_PERMISSIONS_OPTION, array() );
	$fingerprint = ve_staff_azure_credentials_fingerprint( (string) $settings['tenant_id'], (string) $settings['client_id'], (string) $settings['client_secret'] );
	if ( ! is_array( $state ) || ! hash_equals( $fingerprint, (string) ( $state['credentials_fingerprint'] ?? '' ) ) ) {
		return array( 'permissions' => array(), 'capabilities' => ve_staff_azure_permission_capabilities( array() ), 'checked_at' => 0 );
	}
	return $state;
}

/** @return array<string, mixed> */
function ve_staff_azure_recheck_permissions(): array {
	$settings = ve_staff_azure_settings();
	$permissions = ve_staff_azure_connector()->test_connection();
	return ve_staff_azure_store_permissions( $permissions, (string) $settings['tenant_id'], (string) $settings['client_id'], (string) $settings['client_secret'] );
}

/** @param array<string, mixed> $user */
function ve_staff_azure_import_user( array $user, string $source ): void {
	$user = ve_staff_azure_add_derived_fields( $user );
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
		if ( ! in_array( $mapping['direction'], array( 'both', 'azure_to_wp' ), true ) ) { continue; }
		$value = ve_staff_azure_read_source( $user, (string) $azure_field );
		if ( null === $value ) { continue; }
		$value = ve_staff_azure_apply_rules( $value, is_array( $mapping['rules'] ?? null ) ? $mapping['rules'] : array() );
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
	$permission_state = ve_staff_azure_permission_state();
	if ( time() - (int) $permission_state['checked_at'] >= VE_STAFF_AZURE_PERMISSION_MAX_AGE ) { $permission_state = ve_staff_azure_recheck_permissions(); }
	if ( empty( $permission_state['capabilities']['read_users'] ) ) { ve_staff_azure_log( 'warning', 'poll_skipped_missing_permission', array( 'required_permission' => 'User.Read.All' ) ); return; }
	$url = (string) get_option( 've_staff_azure_delta_link', 'https://graph.microsoft.com/v1.0/users/delta?$select=' . rawurlencode( ve_staff_azure_user_select( $s['mappings'] ) ) );
	do { $page = ve_staff_azure_connector()->get_json( $url ); foreach ( (array) ( $page['value'] ?? array() ) as $user ) { if ( is_array( $user ) && empty( $user['@removed'] ) ) { ve_staff_azure_import_user( $user, 'poll' ); } } $url = (string) ( $page['@odata.nextLink'] ?? '' ); } while ( '' !== $url );
	if ( ! empty( $page['@odata.deltaLink'] ) ) { update_option( 've_staff_azure_delta_link', esc_url_raw( (string) $page['@odata.deltaLink'] ), false ); }
}

function ve_staff_azure_webhook( WP_REST_Request $request ): WP_REST_Response {
	$validation = $request->get_param( 'validationToken' ); if ( is_string( $validation ) ) { return new WP_REST_Response( $validation, 200, array( 'Content-Type' => 'text/plain' ) ); }
	$body = $request->get_json_params(); $s = ve_staff_azure_settings();
	foreach ( (array) ( $body['value'] ?? array() ) as $event ) {
		if ( ! hash_equals( (string) $s['webhook_client_state'], (string) ( $event['clientState'] ?? '' ) ) ) { throw new RuntimeException( 'Azure webhook clientState validation failed.' ); }
		if ( empty( ve_staff_azure_permission_state()['capabilities']['read_users'] ) ) { ve_staff_azure_log( 'warning', 'webhook_skipped_missing_permission', array( 'required_permission' => 'User.Read.All' ) ); continue; }
		$id = basename( (string) ( $event['resourceData']['id'] ?? $event['resource'] ?? '' ) ); $user = ve_staff_azure_connector()->get_json( 'https://graph.microsoft.com/v1.0/users/' . rawurlencode( $id ) . '?$select=' . rawurlencode( ve_staff_azure_user_select( $s['mappings'] ) ) ); ve_staff_azure_import_user( $user, 'webhook' );
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
		$capabilities = ve_staff_azure_permission_state()['capabilities'];
		if ( array() !== $payload && ! empty( $capabilities['write_users'] ) ) { $connector->patch_json( 'https://graph.microsoft.com/v1.0/users/' . rawurlencode( $azure_id ), $payload ); }
		elseif ( array() !== $payload ) { ve_staff_azure_log( 'warning', 'user_export_skipped_missing_permission', array( 'post_id' => $post_id, 'azure_id' => $azure_id, 'required_permission' => 'User.ReadWrite.All' ) ); }
		$photo_mapping = $s['mappings']['photo'] ?? array();
		$photo         = 'wp_to_azure' === ( $photo_mapping['direction'] ?? '' ) ? get_field( 'photo', $post_id ) : null;
		$photo_id  = is_array( $photo ) ? (int) ( $photo['ID'] ?? 0 ) : (int) $photo;
		$path     = $photo_id > 0 ? get_attached_file( $photo_id ) : '';
		if ( is_string( $path ) && is_readable( $path ) && ! empty( $capabilities['write_photos'] ) ) {
			$mime = (string) get_post_mime_type( $photo_id );
			$connector->put_binary( 'https://graph.microsoft.com/v1.0/users/' . rawurlencode( $azure_id ) . '/photo/$value', $mime, (string) file_get_contents( $path ) );
		}
		elseif ( is_string( $path ) && is_readable( $path ) ) { ve_staff_azure_log( 'warning', 'photo_export_skipped_missing_permission', array( 'post_id' => $post_id, 'azure_id' => $azure_id, 'required_permission' => 'ProfilePhoto.ReadWrite.All' ) ); }
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

/** @param mixed $rules @return array<int, array<string, mixed>> */
function ve_staff_azure_sanitize_rules( $rules, string $azure_field ): array {
	$decoded = is_string( $rules ) ? json_decode( $rules, true ) : $rules;
	if ( ! is_array( $decoded ) || array_values( $decoded ) !== $decoded ) { throw new InvalidArgumentException( 'Rules for ' . $azure_field . ' must be a JSON array.' ); }
	$allowed = array( 'trim', 'lowercase', 'uppercase', 'phone_digits', 'replace', 'regex', 'value_map' );
	foreach ( $decoded as $rule ) {
		if ( ! is_array( $rule ) || ! in_array( (string) ( $rule['type'] ?? '' ), $allowed, true ) ) { throw new InvalidArgumentException( 'Rules for ' . $azure_field . ' contain an unsupported rule type.' ); }
		if ( 'regex' === $rule['type'] && false === @preg_match( (string) ( $rule['pattern'] ?? '' ), '' ) ) { throw new InvalidArgumentException( 'Rules for ' . $azure_field . ' contain an invalid regular expression.' ); }
	}
	return $decoded;
}

/** @param mixed $input @return array<string, mixed> */
function ve_staff_azure_validate_settings( $input ): array {
	if ( ! is_array( $input ) ) { throw new InvalidArgumentException( 'Azure settings must be an array.' ); }
	$existing = ve_staff_azure_settings();
	$submitted = $input['mappings'] ?? array();
	if ( ! is_array( $submitted ) ) { throw new InvalidArgumentException( 'Azure field mappings must be an array.' ); }
	$mappings = array();
	foreach ( $submitted as $mapping ) {
		if ( ! is_array( $mapping ) ) { throw new InvalidArgumentException( 'Every Azure mapping must be an object.' ); }
		$field = sanitize_text_field( (string) ( $mapping['azure_field'] ?? '' ) );
		$target = sanitize_text_field( (string) ( $mapping['target'] ?? '' ) );
		$direction = sanitize_key( (string) ( $mapping['direction'] ?? '' ) );
		if ( '' === $field || '' === $target ) { throw new InvalidArgumentException( 'Every mapping requires an Azure field and a WordPress target.' ); }
		if ( ! in_array( $direction, array( 'azure_to_wp', 'wp_to_azure', 'disabled' ), true ) ) { throw new InvalidArgumentException( 'Mapping for ' . $field . ' has an invalid source of truth.' ); }
		if ( isset( $mappings[ $field ] ) ) { throw new InvalidArgumentException( 'Azure field ' . $field . ' is mapped more than once.' ); }
		$mappings[ $field ] = array( 'target' => $target, 'direction' => $direction, 'rules' => ve_staff_azure_sanitize_rules( $mapping['rules'] ?? '[]', $field ) );
	}
	$secret_action = sanitize_key( (string) ( $input['client_secret_action'] ?? 'keep' ) );
	$secret = sanitize_text_field( (string) ( $input['client_secret'] ?? '' ) );
	if ( ! in_array( $secret_action, array( 'keep', 'replace', 'remove' ), true ) ) { throw new InvalidArgumentException( 'Choose a valid client secret action.' ); }
	if ( 'replace' === $secret_action && '' === $secret ) { throw new InvalidArgumentException( 'Enter the new client secret value before saving.' ); }
	$stored_secret = 'replace' === $secret_action ? $secret : (string) $existing['client_secret'];
	if ( 'remove' === $secret_action ) { $stored_secret = ''; }
	$secret_expires = sanitize_text_field( (string) ( $input['client_secret_expires'] ?? $existing['client_secret_expires'] ) );
	$expiration_parts = explode( '-', $secret_expires );
	if ( '' !== $secret_expires && ( 3 !== count( $expiration_parts ) || ! checkdate( (int) $expiration_parts[1], (int) $expiration_parts[2], (int) $expiration_parts[0] ) ) ) { throw new InvalidArgumentException( 'Client secret expiration must be a valid date.' ); }
	$result = array(
		'enabled' => ! empty( $input['enabled'] ), 'mock_mode' => ! empty( $input['mock_mode'] ),
		'tenant_id' => sanitize_text_field( (string) ( $input['tenant_id'] ?? $existing['tenant_id'] ) ), 'client_id' => sanitize_text_field( (string) ( $input['client_id'] ?? $existing['client_id'] ) ),
		'client_secret' => $stored_secret,
		'client_secret_expires' => 'remove' === $secret_action ? '' : $secret_expires,
		'webhook_client_state' => sanitize_text_field( (string) ( $input['webhook_client_state'] ?? $existing['webhook_client_state'] ) ),
		'poll_minutes' => max( 5, (int) ( $input['poll_minutes'] ?? $existing['poll_minutes'] ) ), 'mappings' => $mappings,
	);
	delete_transient( 've_staff_azure_access_token' );
	return $result;
}

/** @param mixed $input @return array<string, mixed> */
function ve_staff_azure_sanitize_settings( $input ): array {
	try {
		return ve_staff_azure_validate_settings( $input );
	} catch ( InvalidArgumentException $error ) {
		add_settings_error( 've_staff_azure_settings', 've_staff_azure_settings_invalid', $error->getMessage(), 'error' );
		return ve_staff_azure_settings();
	}
}

/** @return array<string, string> */
function ve_staff_azure_field_help(): array {
	return array(
		'tenant_id' => 'Directory (tenant) ID from Microsoft Entra ID → Overview.',
		'client_id' => 'Application (client) ID from the app registration Overview page.',
		'client_secret' => 'Secret value (not its ID) from Certificates & secrets. Choose Replace saved secret before entering a new value.',
		'client_secret_expires' => 'Expiration date shown for the secret in Microsoft Entra. This date is stored for renewal reminders; Azure does not expose the secret value or its expiration through this connection.',
		'webhook_client_state' => 'A random private value used to verify Graph change notifications. It must match the subscription clientState.',
		'poll_minutes' => 'How often the delta poll runs. Five minutes is the minimum.',
	);
}

/** @param array<string, mixed> $state */
function ve_staff_azure_permission_summary( array $state ): string {
	$capabilities = is_array( $state['capabilities'] ?? null ) ? $state['capabilities'] : array();
	$parts = array(
		! empty( $capabilities['read_users'] ) ? 'Azure-to-WordPress user sync is available' : 'Azure-to-WordPress user sync is unavailable (grant User.Read.All)',
		! empty( $capabilities['write_users'] ) ? 'WordPress-to-Azure user fields are available' : 'WordPress-to-Azure user fields are unavailable (grant User.ReadWrite.All)',
		! empty( $capabilities['write_photos'] ) ? 'WordPress-to-Azure profile photos are available' : 'WordPress-to-Azure profile photos are unavailable (grant ProfilePhoto.ReadWrite.All)',
	);
	$checked_at = (int) ( $state['checked_at'] ?? 0 );
	return implode( '; ', $parts ) . ( $checked_at > 0 ? '. Last checked ' . gmdate( 'Y-m-d H:i:s', $checked_at ) . ' UTC.' : '. Run the connection test to check granted permissions.' );
}

function ve_staff_azure_ajax_connection_test(): void {
	check_ajax_referer( 've_staff_azure_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'You cannot test Azure sync.' ), 403 ); }
	$settings = ve_staff_azure_settings();
	$tenant = sanitize_text_field( (string) ( $_POST['tenant_id'] ?? '' ) );
	$client = sanitize_text_field( (string) ( $_POST['client_id'] ?? '' ) );
	$secret = sanitize_text_field( (string) ( $_POST['client_secret'] ?? '' ) );
	if ( '' === $secret ) { $secret = (string) $settings['client_secret']; }
	try {
		delete_transient( 've_staff_azure_access_token' );
		$connector = new Ve_Staff_Azure_Graph_Connector( $tenant, $client, $secret );
		$permissions = $connector->test_connection();
		$state = ve_staff_azure_store_permissions( $permissions, $tenant, $client, $secret );
		$discovered_fields = array();
		if ( ! empty( $state['capabilities']['read_users'] ) ) {
			$sample = $connector->get_json( 'https://graph.microsoft.com/v1.0/users?$top=1&$select=' . rawurlencode( implode( ',', array_merge( array( 'id' ), ve_staff_azure_common_user_fields() ) ) ) );
			$users = is_array( $sample['value'] ?? null ) ? $sample['value'] : array();
			if ( isset( $users[0] ) && is_array( $users[0] ) ) { $discovered_fields = ve_staff_azure_discovered_fields( ve_staff_azure_add_derived_fields( $users[0] ) ); }
		}
		wp_send_json_success( array( 'message' => ve_staff_azure_permission_summary( $state ), 'capabilities' => $state['capabilities'], 'discovered_fields' => $discovered_fields ) );
	} catch ( Throwable $error ) { wp_send_json_error( array( 'message' => $error->getMessage() ), 400 ); }
}

function ve_staff_azure_ajax_save_field(): void {
	check_ajax_referer( 've_staff_azure_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'You cannot update Azure sync settings.' ), 403 ); }
	$field = sanitize_key( (string) ( $_POST['field'] ?? '' ) );
	$value = sanitize_text_field( wp_unslash( (string) ( $_POST['value'] ?? '' ) ) );
	$allowed_fields = array( 'tenant_id', 'client_id', 'client_secret', 'client_secret_expires', 'webhook_client_state', 'poll_minutes' );
	if ( ! in_array( $field, $allowed_fields, true ) ) { wp_send_json_error( array( 'message' => 'This Azure setting cannot be saved individually.' ), 400 ); }
	if ( 'client_secret' === $field && '' === $value ) { wp_send_json_error( array( 'message' => 'Enter a client secret value before saving.' ), 400 ); }
	if ( 'poll_minutes' === $field ) {
		if ( ! ctype_digit( $value ) || (int) $value < 5 ) { wp_send_json_error( array( 'message' => 'Polling interval must be at least five minutes.' ), 400 ); }
		$value = (string) (int) $value;
	}
	if ( 'client_secret_expires' === $field && '' !== $value ) {
		$parts = explode( '-', $value );
		if ( 3 !== count( $parts ) || ! checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] ) ) { wp_send_json_error( array( 'message' => 'Client secret expiration must be a valid date.' ), 400 ); }
	}
	$stored = get_option( 've_staff_azure_settings', array() );
	$stored = is_array( $stored ) ? $stored : array();
	$stored[ $field ] = 'poll_minutes' === $field ? (int) $value : $value;
	if ( ! update_option( 've_staff_azure_settings', $stored ) && get_option( 've_staff_azure_settings' ) !== $stored ) {
		wp_send_json_error( array( 'message' => 'WordPress could not save this Azure setting.' ), 500 );
	}
	if ( in_array( $field, array( 'tenant_id', 'client_id', 'client_secret' ), true ) ) { delete_transient( 've_staff_azure_access_token' ); delete_option( VE_STAFF_AZURE_PERMISSIONS_OPTION ); }
	wp_send_json_success( array( 'message' => 'Saved.' ) );
}

/** @return array<string, mixed> */
function ve_staff_azure_load_test_user( int $post_id, array $mappings ): array {
	$azure_id = (string) get_post_meta( $post_id, '_ve_staff_azure_id', true );
	$select = ve_staff_azure_user_select( $mappings );
	if ( '' !== $azure_id ) { return ve_staff_azure_add_derived_fields( ve_staff_azure_connector()->get_json( 'https://graph.microsoft.com/v1.0/users/' . rawurlencode( $azure_id ) . '?$select=' . rawurlencode( $select ) ) ); }
	$email = (string) ve_staff_azure_read_target( $post_id, 'office_contact_info.office_email' );
	if ( '' === $email ) { throw new RuntimeException( 'The selected staff post has neither an Azure ID nor an office email address.' ); }
	$filter = rawurlencode( "mail eq '" . str_replace( "'", "''", $email ) . "'" );
	$result = ve_staff_azure_connector()->get_json( 'https://graph.microsoft.com/v1.0/users?$filter=' . $filter . '&$select=' . rawurlencode( $select ) );
	$users = $result['value'] ?? array();
	if ( ! is_array( $users ) || 1 !== count( $users ) || ! is_array( $users[0] ) ) { throw new RuntimeException( 'Expected exactly one Azure user matching staff email ' . $email . '.' ); }
	return ve_staff_azure_add_derived_fields( $users[0] );
}

function ve_staff_azure_ajax_sync_preview(): void {
	check_ajax_referer( 've_staff_azure_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'You cannot preview Azure sync.' ), 403 ); }
	$post_id = absint( $_POST['post_id'] ?? 0 );
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || 'staff' !== $post->post_type ) { wp_send_json_error( array( 'message' => 'Choose a valid staff post.' ), 400 ); }
	try {
		$settings = ve_staff_azure_settings();
		$user = ve_staff_azure_load_test_user( $post_id, $settings['mappings'] );
		$rows = array();
		foreach ( $settings['mappings'] as $field => $mapping ) {
			if ( 'disabled' === $mapping['direction'] || 'photo' === $field ) { continue; }
			$azure = ve_staff_azure_apply_rules( ve_staff_azure_read_source( $user, (string) $field ), (array) $mapping['rules'] );
			$wordpress = ve_staff_azure_apply_rules( ve_staff_azure_read_target( $post_id, (string) $mapping['target'] ), (array) $mapping['rules'] );
			$from = 'azure_to_wp' === $mapping['direction'] ? $azure : $wordpress;
			$to = 'azure_to_wp' === $mapping['direction'] ? $wordpress : $azure;
			$rows[] = array( 'field' => $field, 'target' => $mapping['target'], 'source' => 'azure_to_wp' === $mapping['direction'] ? 'Azure' : 'WordPress', 'source_value' => $from, 'destination_value' => $to, 'action' => $from === $to ? 'No change' : 'Would update' );
		}
		wp_send_json_success( array( 'message' => 'Preview only: no data was changed.', 'rows' => $rows ) );
	} catch ( Throwable $error ) { wp_send_json_error( array( 'message' => $error->getMessage() ), 400 ); }
}

function ve_staff_azure_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'You cannot manage Azure sync.', 've-staff' ) ); }
	$s = ve_staff_azure_settings(); $help = ve_staff_azure_field_help(); $permission_state = ve_staff_azure_permission_state();
	$posts = get_posts( array( 'post_type' => 'staff', 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	wp_enqueue_style( 've-staff-azure-admin', VE_STAFF_PLUGIN_URL . 'admin/css/ve-staff-azure-admin.css', array(), VE_STAFF_VERSION );
	wp_enqueue_script( 've-staff-azure-admin', VE_STAFF_PLUGIN_URL . 'admin/js/ve-staff-azure-admin.js', array(), VE_STAFF_VERSION, true );
	wp_localize_script( 've-staff-azure-admin', 'veStaffAzure', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 've_staff_azure_admin' ) ) );
	echo '<div class="wrap ve-azure"><h1>Microsoft Azure staff sync</h1>';
	settings_errors( 've_staff_azure_settings' );
	echo '<div class="notice notice-info inline"><h2>Microsoft Entra app registration</h2><ol><li>Create a single-tenant app registration in <strong>Microsoft Entra ID → App registrations</strong>.</li><li>No Redirect URI is required. This integration uses the server-to-server OAuth client credentials flow and never signs in a browser user.</li><li>Under <strong>API permissions</strong>, grant only the Microsoft Graph <strong>Application</strong> permissions needed: <code>User.Read.All</code> for Azure-to-WordPress user sync, <code>User.ReadWrite.All</code> for WordPress-to-Azure user fields, and <code>ProfilePhoto.ReadWrite.All</code> for WordPress-to-Azure photos.</li><li>Create a client secret under <strong>Certificates &amp; secrets</strong>, copy its value before leaving the page, and record its expiration date below.</li><li>Optional webhooks use notification URL <code>' . esc_html( rest_url( 've-staff/v1/azure/webhook' ) ) . '</code> and the client state below. Polling works without a webhook subscription.</li></ol></div><form id="ve-azure-settings" method="post" action="options.php">';
	settings_fields( 've_staff_azure' ); echo '<table class="form-table">';
	foreach ( array( 'tenant_id' => 'Tenant ID', 'client_id' => 'Client ID' ) as $key => $label ) {
		$type = 'text'; $value = (string) $s[ $key ];
		echo '<tr><th><label for="azure-' . esc_attr( $key ) . '">' . esc_html( $label ) . ' <span class="dashicons dashicons-editor-help ve-azure-tip" tabindex="0" data-tip="' . esc_attr( $help[ $key ] ) . '" aria-label="' . esc_attr( $help[ $key ] ) . '"></span></label></th><td><input class="regular-text ve-azure-autosave" data-setting="' . esc_attr( $key ) . '" id="azure-' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" name="ve_staff_azure_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"><span class="ve-azure-save-status" role="status"></span><p class="description">' . esc_html( $help[ $key ] ) . '</p></td></tr>';
	}
	$has_secret = '' !== (string) $s['client_secret'];
	echo '<tr><th><label for="azure-client_secret_action">Client secret</label></th><td><p><strong>' . ( $has_secret ? '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> A client secret is saved.' : '<span class="dashicons dashicons-warning" aria-hidden="true"></span> No client secret is saved.' ) . '</strong></p><select id="azure-client_secret_action" name="ve_staff_azure_settings[client_secret_action]">';
	if ( $has_secret ) { echo '<option value="keep">Keep saved secret</option>'; }
	echo '<option value="replace">' . ( $has_secret ? 'Replace saved secret' : 'Save a new secret' ) . '</option>';
	if ( $has_secret ) { echo '<option value="remove">Remove saved secret</option>'; }
	echo '</select><p id="ve-azure-secret-entry"' . ( $has_secret ? ' hidden' : '' ) . '><label for="azure-client_secret">New client secret value</label><br><input class="regular-text ve-azure-autosave" data-setting="client_secret" id="azure-client_secret" type="password" autocomplete="new-password" name="ve_staff_azure_settings[client_secret]" value=""><span class="ve-azure-save-status" role="status"></span></p><p class="description">' . esc_html( $help['client_secret'] ) . '</p></td></tr>';
	$expiration = (string) $s['client_secret_expires']; $expiration_note = '';
	if ( '' !== $expiration ) {
		$days_until_expiration = (int) floor( ( strtotime( $expiration . ' 23:59:59 UTC' ) - time() ) / DAY_IN_SECONDS );
		if ( $days_until_expiration < 0 ) { $expiration_note = ' This saved secret has expired and must be replaced.'; }
		elseif ( $days_until_expiration <= 30 ) { $expiration_note = ' This saved secret expires in ' . $days_until_expiration . ' days; replace it soon.'; }
		else { $expiration_note = ' Saved expiration: ' . $expiration . '.'; }
	}
	echo '<tr><th><label for="azure-client_secret_expires">Client secret expiration</label></th><td><input class="ve-azure-autosave" data-setting="client_secret_expires" id="azure-client_secret_expires" type="date" name="ve_staff_azure_settings[client_secret_expires]" value="' . esc_attr( $expiration ) . '"><span class="ve-azure-save-status" role="status"></span><p class="description">' . esc_html( $help['client_secret_expires'] . $expiration_note ) . '</p></td></tr>';
	foreach ( array( 'webhook_client_state' => 'Webhook client state', 'poll_minutes' => 'Polling interval (minutes)' ) as $key => $label ) {
		$type = 'poll_minutes' === $key ? 'number' : 'text'; $value = (string) $s[ $key ];
		echo '<tr><th><label for="azure-' . esc_attr( $key ) . '">' . esc_html( $label ) . ' <span class="dashicons dashicons-editor-help ve-azure-tip" tabindex="0" data-tip="' . esc_attr( $help[ $key ] ) . '" aria-label="' . esc_attr( $help[ $key ] ) . '"></span></label></th><td><input class="regular-text ve-azure-autosave" data-setting="' . esc_attr( $key ) . '" id="azure-' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" name="ve_staff_azure_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"' . ( 'poll_minutes' === $key ? ' min="5"' : '' ) . '><span class="ve-azure-save-status" role="status"></span><p class="description">' . esc_html( $help[ $key ] ) . '</p></td></tr>';
	}
	echo '<tr><th>Operation <span class="dashicons dashicons-editor-help ve-azure-tip" tabindex="0" data-tip="Mock mode is the safe default and records activity without changing either system."></span></th><td><label><input type="checkbox" name="ve_staff_azure_settings[enabled]" value="1" ' . checked( true, (bool) $s['enabled'], false ) . '> Enable synchronization</label><br><label><input type="checkbox" name="ve_staff_azure_settings[mock_mode]" value="1" ' . checked( true, (bool) $s['mock_mode'], false ) . '> Mock mode (log only; do not mutate either system)</label><p><button type="button" class="button" id="ve-azure-test-connection">Test connection &amp; permissions</button></p><p id="ve-azure-connection-result" role="status">' . esc_html( ve_staff_azure_permission_summary( $permission_state ) ) . '</p><p class="description">Granted permissions are stored after each test and rechecked automatically at least once per day while synchronization is enabled.</p></td></tr></table>';
	echo '<h2>Field mappings and source of truth</h2><p>Each row has exactly one source of truth. <strong>Azure → WordPress</strong> can change the site; <strong>WordPress → Azure</strong> can change Azure. Disabled rows never sync. Rules transform both values before comparison.</p><p>Common Azure fields include <code>displayName</code>, <code>jobTitle</code>, <code>department</code>, <code>companyName</code>, <code>employeeId</code>, <code>businessPhones</code>, and <code>officeLocation</code>. Synced directories can also expose <code>organizationalUnit</code> (derived from <code>onPremisesDistinguishedName</code>) and <code>onPremisesExtensionAttributes.extensionAttribute1</code> through <code>extensionAttribute15</code>. Run the connection test to inspect fields populated on one directory user.</p><div id="ve-azure-discovered-fields" aria-live="polite"></div><datalist id="ve-azure-field-options">';
	foreach ( ve_staff_azure_common_user_fields() as $field ) { echo '<option value="' . esc_attr( $field ) . '"></option>'; }
	for ( $attribute = 1; $attribute <= 15; $attribute++ ) { echo '<option value="onPremisesExtensionAttributes.extensionAttribute' . $attribute . '"></option>'; }
	echo '<option value="organizationalUnit"></option></datalist><table class="widefat striped" id="ve-azure-mappings"><thead><tr><th>Azure field</th><th>WordPress target</th><th>Source of truth</th><th>Rules (JSON array)</th><th></th></tr></thead><tbody>';
	$index = 0; foreach ( $s['mappings'] as $field => $mapping ) { echo ve_staff_azure_mapping_row( $index, (string) $field, $mapping ); $index++; }
	echo '</tbody></table><p><button type="button" class="button" id="ve-azure-add-mapping">Add mapping</button></p><details><summary>Rules reference and examples</summary><p>Available types: <code>trim</code>, <code>lowercase</code>, <code>uppercase</code>, <code>phone_digits</code>, <code>replace</code>, <code>regex</code>, and <code>value_map</code>.</p><pre>[{"type":"phone_digits"}]\n[{"type":"value_map","map":{"Vern Eide Honda":"vern-eide-honda"}}]</pre></details>';
	submit_button(); echo '</form><hr><h2>Dry-run a staff member</h2><p>This fetches the matching Azure user, applies the <em>saved</em> mappings and rules, and shows the destination changes. It never writes data.</p><select id="ve-azure-test-post"><option value="">Choose staff…</option>'; foreach ( $posts as $post ) { echo '<option value="' . (int) $post->ID . '">' . esc_html( $post->post_title ) . '</option>'; } echo '</select> <button type="button" class="button button-secondary" id="ve-azure-run-preview">Run dry-run</button><div id="ve-azure-preview" aria-live="polite"></div></div>';
}

/** @param array<string, mixed> $mapping */
function ve_staff_azure_mapping_row( int $index, string $field, array $mapping ): string {
	$name = 've_staff_azure_settings[mappings][' . $index . ']'; $rules = wp_json_encode( $mapping['rules'] ?? array(), JSON_UNESCAPED_SLASHES );
	$options = array( 'azure_to_wp' => 'Azure → WordPress', 'wp_to_azure' => 'WordPress → Azure', 'disabled' => 'Disabled' ); $select = '';
	foreach ( $options as $value => $label ) { $select .= '<option value="' . esc_attr( $value ) . '" ' . selected( $value, (string) ( $mapping['direction'] ?? 'disabled' ), false ) . '>' . esc_html( $label ) . '</option>'; }
	return '<tr><td><input required list="ve-azure-field-options" name="' . esc_attr( $name ) . '[azure_field]" value="' . esc_attr( $field ) . '" placeholder="mobilePhone"></td><td><input required name="' . esc_attr( $name ) . '[target]" value="' . esc_attr( (string) ( $mapping['target'] ?? '' ) ) . '" placeholder="group.field"></td><td><select name="' . esc_attr( $name ) . '[direction]">' . $select . '</select></td><td><textarea required class="large-text code ve-azure-rules" rows="2" name="' . esc_attr( $name ) . '[rules]">' . esc_textarea( (string) $rules ) . '</textarea><span class="ve-azure-json-status"></span></td><td><button type="button" class="button-link-delete ve-azure-remove">Remove</button></td></tr>';
}

add_action( 'admin_menu', 've_staff_azure_admin_menu' );
add_action( 'admin_init', 've_staff_azure_register_settings' );
add_action( 'wp_ajax_ve_staff_azure_connection_test', 've_staff_azure_ajax_connection_test' );
add_action( 'wp_ajax_ve_staff_azure_save_field', 've_staff_azure_ajax_save_field' );
add_action( 'wp_ajax_ve_staff_azure_sync_preview', 've_staff_azure_ajax_sync_preview' );
add_action( 'update_option_ve_staff_azure_settings', static function (): void { ve_staff_azure_reschedule(); } );
