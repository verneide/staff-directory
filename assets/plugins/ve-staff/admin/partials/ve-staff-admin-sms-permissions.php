<?php
/** Staff SMS authorization and recipient selection. */
const VE_SMS_LOCATIONS = 've_staff_sms_locations';
const VE_SMS_DEPARTMENTS = 've_staff_sms_departments';
const VE_SMS_NUMBERS = 've_staff_sms_numbers_allowed';
const VE_SMS_RECIPIENTS = 've_staff_sms_recipient_ids';
const VE_SMS_NUMBER_TAX = 'sms-sending-number';
const VE_SMS_GROUP_TAX = 'sms-recipient-group';

function ve_sms_unrestricted( int $user_id ): bool {
	return user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'edit_others_posts' );
}
function ve_sms_allowed_terms( int $user_id, string $taxonomy ): array {
	$keys = array( 'location' => VE_SMS_LOCATIONS, 'department' => VE_SMS_DEPARTMENTS, VE_SMS_NUMBER_TAX => VE_SMS_NUMBERS );
	$ids = array_values( array_filter( array_map( 'absint', (array) get_user_meta( $user_id, $keys[ $taxonomy ], true ) ) ) );
	if ( $ids || ! ve_sms_unrestricted( $user_id ) ) return $ids;
	$all = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'fields' => 'ids' ) );
	return is_wp_error( $all ) ? array() : array_map( 'absint', $all );
}
function ve_sms_register_taxonomies(): void {
	register_taxonomy( VE_SMS_NUMBER_TAX, 'staff-sms', array( 'labels' => array( 'name' => 'Sending Numbers', 'singular_name' => 'Sending Number' ), 'public' => false, 'show_ui' => true, 'show_admin_column' => true, 'capabilities' => array( 'manage_terms' => 'manage_options', 'edit_terms' => 'manage_options', 'delete_terms' => 'manage_options', 'assign_terms' => 'edit_posts' ) ) );
	register_taxonomy( VE_SMS_GROUP_TAX, 'staff-sms', array( 'labels' => array( 'name' => 'Recipient Groups', 'singular_name' => 'Recipient Group' ), 'public' => false, 'show_ui' => true, 'show_admin_column' => true ) );
}
add_action( 'init', 've_sms_register_taxonomies', 8 );
function ve_sms_number( string $value ): string {
	$digits = preg_replace( '/\D+/', '', $value );
	if ( strlen( $digits ) === 10 ) $digits = '1' . $digits;
	return $digits ? '+' . $digits : '';
}
function ve_sms_migrate_numbers(): void {
	if ( get_option( 've_sms_number_terms_migrated' ) || ! function_exists( 'get_field' ) ) return;
	foreach ( (array) get_field( 've_staff_sms_numbers', 'option' ) as $row ) {
		$number = ve_sms_number( (string) ( $row['number'] ?? '' ) );
		if ( ! $number ) continue;
		$found = get_terms( array( 'taxonomy' => VE_SMS_NUMBER_TAX, 'hide_empty' => false, 'meta_key' => 'sms_number', 'meta_value' => $number ) );
		$id = $found && ! is_wp_error( $found ) ? (int) $found[0]->term_id : 0;
		if ( ! $id ) { $created = wp_insert_term( (string) ( $row['label'] ?: $number ), VE_SMS_NUMBER_TAX ); $id = is_wp_error( $created ) ? 0 : (int) $created['term_id']; }
		if ( ! $id ) continue;
		update_term_meta( $id, 'sms_number', $number ); update_term_meta( $id, 'sms_active', ! empty( $row['active'] ) ? 1 : 0 );
		foreach ( array( 'from_name_status', 'from_name', 'optout_keyword_status', 'optout_keyword' ) as $key ) update_term_meta( $id, 'sms_' . $key, $row[ $key ] ?? '' );
	}
	update_option( 've_sms_number_terms_migrated', 1, false );
}
add_action( 'admin_init', 've_sms_migrate_numbers' );
function ve_sms_user_fields( WP_User $user ): void {
	if ( ! current_user_can( 'edit_users' ) ) return;
	wp_nonce_field( 've_sms_permissions', 've_sms_permissions_nonce' );
	echo '<h2>SMS Permissions</h2><p>High-access roles with no selection have access to all values. Other roles with no selection have no recipient access.</p><table class="form-table">';
	foreach ( array( VE_SMS_LOCATIONS => array( 'Locations', 'location' ), VE_SMS_DEPARTMENTS => array( 'Departments', 'department' ), VE_SMS_NUMBERS => array( 'Sending Numbers', VE_SMS_NUMBER_TAX ) ) as $key => $definition ) {
		$selected = array_map( 'absint', (array) get_user_meta( $user->ID, $key, true ) ); $terms = get_terms( array( 'taxonomy' => $definition[1], 'hide_empty' => false ) );
		echo '<tr><th>' . esc_html( $definition[0] ) . '</th><td>';
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) echo '<label style="display:block"><input type="checkbox" name="' . esc_attr( $key ) . '[]" value="' . esc_attr( $term->term_id ) . '" ' . checked( in_array( (int) $term->term_id, $selected, true ), true, false ) . '> ' . esc_html( $term->name ) . '</label>';
		echo '</td></tr>';
	}
	echo '</table>';
}
add_action( 'show_user_profile', 've_sms_user_fields' ); add_action( 'edit_user_profile', 've_sms_user_fields' );
function ve_sms_save_user_fields( int $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['ve_sms_permissions_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ve_sms_permissions_nonce'] ) ), 've_sms_permissions' ) ) return;
	foreach ( array( VE_SMS_LOCATIONS, VE_SMS_DEPARTMENTS, VE_SMS_NUMBERS ) as $key ) update_user_meta( $user_id, $key, isset( $_POST[ $key ] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ $key ] ) ) ) ) : array() );
}
add_action( 'personal_options_update', 've_sms_save_user_fields' ); add_action( 'edit_user_profile_update', 've_sms_save_user_fields' );
function ve_sms_filter_field( array $field ): array {
	$taxonomy = ( $field['name'] ?? '' ) === 'sms_msg_location' ? 'location' : ( ( $field['name'] ?? '' ) === 'sms_msg_department' ? 'department' : '' );
	if ( ! $taxonomy ) return $field;
	$field['allow_terms'] = ve_sms_allowed_terms( get_current_user_id(), $taxonomy ) ?: array( 0 );
	if ( $taxonomy === 'department' && ! ve_sms_unrestricted( get_current_user_id() ) ) { $field['toggle'] = 0; $field['required'] = 1; }
	return $field;
}
add_filter( 'acf/load_field/name=sms_msg_location', 've_sms_filter_field', 20 ); add_filter( 'acf/load_field/name=sms_msg_department', 've_sms_filter_field', 20 );
function ve_sms_number_choices( array $field ): array {
	$field['choices'] = array();
	foreach ( ve_sms_allowed_terms( get_current_user_id(), VE_SMS_NUMBER_TAX ) as $id ) { $term = get_term( $id ); $number = ve_sms_number( (string) get_term_meta( $id, 'sms_number', true ) ); if ( $term && ! is_wp_error( $term ) && $number && get_term_meta( $id, 'sms_active', true ) ) $field['choices'][ $number ] = $term->name . ' (' . $number . ')'; }
	$field['required'] = 1; return $field;
}
add_filter( 'acf/load_field/name=sms_msg_from', 've_sms_number_choices', 30 );
function ve_sms_staff_allowed( int $staff_id, int $user_id ): bool {
	$locations = wp_get_object_terms( $staff_id, 'location', array( 'fields' => 'ids' ) ); $departments = wp_get_object_terms( $staff_id, 'department', array( 'fields' => 'ids' ) );
	return ! is_wp_error( $locations ) && ! is_wp_error( $departments ) && (bool) array_intersect( $locations, ve_sms_allowed_terms( $user_id, 'location' ) ) && (bool) array_intersect( $departments, ve_sms_allowed_terms( $user_id, 'department' ) );
}
function ve_sms_filtered_ids( array $locations, array $departments, int $user_id ): array {
	$locations = array_intersect( array_map( 'absint', $locations ), ve_sms_allowed_terms( $user_id, 'location' ) ); $departments = array_intersect( array_map( 'absint', $departments ), ve_sms_allowed_terms( $user_id, 'department' ) );
	if ( ! $locations || ! $departments ) return array();
	return array_map( 'absint', get_posts( array( 'post_type' => 'staff', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( 'relation' => 'AND', array( 'taxonomy' => 'location', 'field' => 'term_id', 'terms' => $locations ), array( 'taxonomy' => 'department', 'field' => 'term_id', 'terms' => $departments ) ), 'meta_query' => array( array( 'key' => 'office_contact_info_office_sms_status', 'value' => 1 ), array( 'key' => 'office_contact_info_office_cell_phone', 'value' => '', 'compare' => '!=' ) ) ) ) );
}
function ve_sms_effective_ids( int $post_id ): array {
	$user_id = (int) get_post_field( 'post_author', $post_id ); $ids = array_map( 'absint', (array) get_post_meta( $post_id, VE_SMS_RECIPIENTS, true ) );
	if ( ! $ids ) $ids = ve_sms_filtered_ids( (array) get_field( 'sms_msg_location', $post_id ), (array) get_field( 'sms_msg_department', $post_id ), $user_id );
	$groups = wp_get_object_terms( $post_id, VE_SMS_GROUP_TAX, array( 'fields' => 'ids' ) ); foreach ( is_wp_error( $groups ) ? array() : $groups as $group ) $ids = array_merge( $ids, (array) get_term_meta( $group, 'recipient_ids', true ) );
	return array_values( array_filter( array_unique( array_map( 'absint', $ids ) ), static fn( int $id ): bool => ve_sms_staff_allowed( $id, $user_id ) ) );
}
function ve_staff_sms_recipient_rows( int $post_id ): array {
	$rows = array(); foreach ( ve_sms_effective_ids( $post_id ) as $id ) { $mobile = ve_sms_number( (string) get_field( 'office_contact_info_office_cell_phone', $id ) ); if ( $mobile ) $rows[] = array( 'ID' => $id, 'post_title' => get_the_title( $id ), 'mobile' => $mobile ); } return $rows;
}
function ve_sms_recipients_box( WP_Post $post ): void {
	wp_nonce_field( 've_sms_recipients', 've_sms_recipients_nonce' ); echo '<button type="button" class="button" id="ve-sms-load-recipients">Load Recipients</button><p><select id="ve-sms-recipients" name="ve_staff_sms_recipient_ids[]" multiple style="width:100%">'; foreach ( ve_sms_effective_ids( $post->ID ) as $id ) echo '<option selected value="' . esc_attr( $id ) . '">' . esc_html( get_the_title( $id ) ) . '</option>'; echo '</select></p><p class="description">Load the permitted filters, then remove or search for individual staff.</p>';
}
add_action( 'add_meta_boxes', static function (): void { add_meta_box( 've-sms-recipients-box', 'Recipients', 've_sms_recipients_box', 'staff-sms', 'normal', 'high' ); } );
function ve_sms_save_recipients( int $post_id ): void {
	if ( ! isset( $_POST['ve_sms_recipients_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ve_sms_recipients_nonce'] ) ), 've_sms_recipients' ) || ! current_user_can( 'edit_post', $post_id ) ) return;
	$ids = isset( $_POST['ve_staff_sms_recipient_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ve_staff_sms_recipient_ids'] ) ) : array(); update_post_meta( $post_id, VE_SMS_RECIPIENTS, array_values( array_filter( $ids, static fn( int $id ): bool => ve_sms_staff_allowed( $id, get_current_user_id() ) ) ) );
}
add_action( 'save_post_staff-sms', 've_sms_save_recipients' );
function ve_sms_ajax_load(): void { check_ajax_referer( 'ajax-nonce', 'nonce' ); if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 ); $ids = ve_sms_filtered_ids( (array) ( $_POST['locations'] ?? array() ), (array) ( $_POST['departments'] ?? array() ), get_current_user_id() ); wp_send_json_success( array_map( static fn( int $id ): array => array( 'id' => $id, 'text' => get_the_title( $id ) ), $ids ) ); }
add_action( 'wp_ajax_ve_sms_load_recipients', 've_sms_ajax_load' );
function ve_sms_ajax_search(): void { check_ajax_referer( 'ajax-nonce', 'nonce' ); $posts = get_posts( array( 'post_type' => 'staff', 'post_status' => 'publish', 'posts_per_page' => 20, 's' => sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) ) ) ); $results = array(); foreach ( $posts as $post ) if ( ve_sms_staff_allowed( (int) $post->ID, get_current_user_id() ) ) $results[] = array( 'id' => $post->ID, 'text' => $post->post_title ); wp_send_json( array( 'results' => $results ) ); }
add_action( 'wp_ajax_ve_sms_search_recipients', 've_sms_ajax_search' );
function ve_sms_remove_trashed_recipient( int $post_id ): void { if ( get_post_type( $post_id ) !== 'staff' ) return; $groups = get_terms( array( 'taxonomy' => VE_SMS_GROUP_TAX, 'hide_empty' => false ) ); foreach ( is_wp_error( $groups ) ? array() : $groups as $group ) update_term_meta( $group->term_id, 'recipient_ids', array_values( array_diff( array_map( 'absint', (array) get_term_meta( $group->term_id, 'recipient_ids', true ) ), array( $post_id ) ) ) ); }
add_action( 'wp_trash_post', 've_sms_remove_trashed_recipient' ); add_action( 'before_delete_post', 've_sms_remove_trashed_recipient' );
function ve_staff_sms_validate_post_access( int $post_id ): bool {
	$user_id = (int) get_post_field( 'post_author', $post_id );
	$allowed = array_map( static fn( int $id ): string => ve_sms_number( (string) get_term_meta( $id, 'sms_number', true ) ), ve_sms_allowed_terms( $user_id, VE_SMS_NUMBER_TAX ) );
	$groups = wp_get_object_terms( $post_id, VE_SMS_GROUP_TAX, array( 'fields' => 'ids' ) );
	foreach ( is_wp_error( $groups ) ? array() : $groups as $group_id ) {
		foreach ( array_map( 'absint', (array) get_term_meta( $group_id, 'recipient_ids', true ) ) as $staff_id ) {
			if ( ! ve_sms_staff_allowed( $staff_id, $user_id ) ) return false;
		}
	}
	return in_array( ve_sms_number( (string) get_field( 'sms_msg_from', $post_id ) ), $allowed, true ) && (bool) ve_sms_effective_ids( $post_id );
}

function ve_sms_term_permission_fields( string $taxonomy ): void {
	$number_tax = $taxonomy === VE_SMS_NUMBER_TAX;
	if ( $number_tax ) {
		echo '<div class="form-field"><label for="sms_number">Phone number</label><input name="sms_number" id="sms_number" required></div><div class="form-field"><label><input type="checkbox" name="sms_active" value="1" checked> Active</label></div>';
		return;
	}
	echo '<div class="form-field"><label for="recipient_ids">Staff profile IDs</label><textarea name="recipient_ids" id="recipient_ids" rows="4"></textarea><p>Comma-separated IDs. Filters can be stored below and are dynamically resolved when the group is saved.</p></div>';
	foreach ( array( 'location' => 'Location IDs', 'department' => 'Department IDs' ) as $key => $label ) echo '<div class="form-field"><label for="group_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label><input name="group_' . esc_attr( $key ) . '" id="group_' . esc_attr( $key ) . '"></div>';
}
add_action( VE_SMS_NUMBER_TAX . '_add_form_fields', static function (): void { ve_sms_term_permission_fields( VE_SMS_NUMBER_TAX ); } );
add_action( VE_SMS_GROUP_TAX . '_add_form_fields', static function (): void { ve_sms_term_permission_fields( VE_SMS_GROUP_TAX ); } );
function ve_sms_edit_term_fields( WP_Term $term ): void {
	if ( $term->taxonomy === VE_SMS_NUMBER_TAX ) {
		echo '<tr class="form-field"><th><label for="sms_number">Phone number</label></th><td><input name="sms_number" id="sms_number" value="' . esc_attr( (string) get_term_meta( $term->term_id, 'sms_number', true ) ) . '" required><label><input type="checkbox" name="sms_active" value="1" ' . checked( get_term_meta( $term->term_id, 'sms_active', true ), 1, false ) . '> Active</label></td></tr>'; return;
	}
	$ids = implode( ', ', array_map( 'absint', (array) get_term_meta( $term->term_id, 'recipient_ids', true ) ) );
	echo '<tr class="form-field"><th><label for="recipient_ids">Staff profile IDs</label></th><td><textarea name="recipient_ids" id="recipient_ids">' . esc_textarea( $ids ) . '</textarea><p class="description">Comma-separated IDs. Add profiles manually or save filter IDs below.</p></td></tr>';
	foreach ( array( 'location' => 'Location IDs', 'department' => 'Department IDs' ) as $key => $label ) echo '<tr class="form-field"><th><label for="group_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input name="group_' . esc_attr( $key ) . '" value="' . esc_attr( implode( ', ', (array) get_term_meta( $term->term_id, $key . '_ids', true ) ) ) . '"></td></tr>';
}
add_action( VE_SMS_NUMBER_TAX . '_edit_form_fields', 've_sms_edit_term_fields' ); add_action( VE_SMS_GROUP_TAX . '_edit_form_fields', 've_sms_edit_term_fields' );
function ve_sms_save_term_fields( int $term_id, int $tt_id, string $taxonomy ): void {
	if ( $taxonomy === VE_SMS_NUMBER_TAX ) { update_term_meta( $term_id, 'sms_number', ve_sms_number( sanitize_text_field( wp_unslash( $_POST['sms_number'] ?? '' ) ) ) ); update_term_meta( $term_id, 'sms_active', isset( $_POST['sms_active'] ) ? 1 : 0 ); return; }
	if ( $taxonomy !== VE_SMS_GROUP_TAX ) return;
	$parse = static fn( string $value ): array => array_values( array_filter( array_map( 'absint', preg_split( '/[\s,]+/', $value ) ) ) );
	$locations = $parse( sanitize_text_field( wp_unslash( $_POST['group_location'] ?? '' ) ) ); $departments = $parse( sanitize_text_field( wp_unslash( $_POST['group_department'] ?? '' ) ) ); $manual = $parse( sanitize_textarea_field( wp_unslash( $_POST['recipient_ids'] ?? '' ) ) );
	$filtered = $locations && $departments ? ve_sms_filtered_ids( $locations, $departments, get_current_user_id() ) : array();
	$ids = array_values( array_filter( array_unique( array_merge( $manual, $filtered ) ), static fn( int $id ): bool => ve_sms_staff_allowed( $id, get_current_user_id() ) ) );
	update_term_meta( $term_id, 'location_ids', $locations ); update_term_meta( $term_id, 'department_ids', $departments ); update_term_meta( $term_id, 'recipient_ids', $ids );
}
add_action( 'created_term', 've_sms_save_term_fields', 10, 3 ); add_action( 'edited_term', 've_sms_save_term_fields', 10, 3 );
