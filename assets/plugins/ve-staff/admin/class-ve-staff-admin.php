<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.sabers-design.com
 * @since      1.0.0
 *
 * @package    Ve_Staff
 * @subpackage Ve_Staff/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for admin-only functionality.
 *
 * @package    Ve_Staff
 * @subpackage Ve_Staff/admin
 * @author     Chase Sabers <csabers@verneide.com>
 */

class Ve_Staff_Admin {

	/**
     * @var Ve_Staff_SMS
     */
    public $SMS; // ✅ fixes "Creation of dynamic property" deprecation

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string
	 */
	private $version;
	private $did_clear_related_transients = false;

	/**
	 * Initialize the class and set its properties, hooks, and schedules.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;

		// Plugin Partials
		$this->adminPartials();

		// ─────────────────────────────────────────────────────────────────────────────
		// Core Hooks / Actions
		// ─────────────────────────────────────────────────────────────────────────────
		add_action( 've_staff_tag_cron', array( $this, 've_staff_tag_staff_cron' ) );
		add_action( 'init', array( $this, 'wpb_remove_schedule_delete' ) );

		// Post Actions
		add_action( 'acf/save_post', array( $this, 've_staff_tag_staff_save' ), 20 );
        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
	  	add_action( 'acf/save_post', array( $this, 'staff_post_title_updater' ), 20 );
		add_action( 'acf/save_post', array( $this, 'staff_post_name_updater' ), 20 );
		add_action( 'acf/save_post', array( $this, 'update_anniversary_fields_on_save' ), 25 );
		add_action( 'save_post', array( $this, 'delete_transients_on_post_update' ), 20 );
		add_action( 'wp_insert_post', array( $this, 'delete_transients_on_post_add' ), 10, 3 );
		add_action( 'wp_trash_post', array( $this, 'delete_transients_on_post_update' ), 10, 1 );
		add_action( 'save_post_staff', array( $this, 'check_anniversary_tag_on_save' ), 30, 3 );

		// ACF Mods
		add_action( 'acf/input/admin_footer', array( $this, 'make_acf_fields_read_only' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_acf_readonly_script' ) );
        add_action( 'acf/render_field_settings', array( $this, 'add_acf_field_permissions_settings' ) );
        add_filter( 'acf/load_field', array( $this, 'apply_acf_field_role_permissions' ) );

		// Options Page Actions
		add_action( 'acf/save_post', array( $this, 've_staff_referrer_settings' ), 20 );
		add_action( 'acf/save_post', array( $this, 'handle_anniversary_options_save' ), 20 );

		// Options Menus
		add_action( 'admin_menu', array( $this, 'register_custom_staff_batch_update_submenu' ) );
		add_action( 'admin_menu', array( $this, 'register_ve_logs_submenu' ) );
		add_action( 'admin_menu', array( $this, 'staff_register_security_submenu' ) );

		// Batch Processes (AJAX)
		add_action( 'wp_ajax_process_staff_batch_update', array( $this, 'process_staff_batch_update' ) );
		add_action( 'wp_ajax_process_bulk_staff_meta_data_update', array( $this, 'process_bulk_staff_meta_data_update' ) );
		add_action( 'wp_ajax_run_anniversary_tag_cleanup', array( $this, 'ajax_run_anniversary_tag_cleanup' ) );
		add_action( 'wp_ajax_run_backfill_anniversary_date_last', array( $this, 'ajax_run_backfill_anniversary_date_last' ) );

		// Batch Processes Cron + Deactivation cleanup
        add_action( 'daily_staff_batch_update_event', array( $this, 'run_daily_staff_batch_update' ) );
        register_deactivation_hook( __FILE__, array( $this, 'clear_staff_batch_update_event' ) );

		// Logging (auto-prune / cleanup)
        add_action( 've_clear_old_log_entries', array( $this, 've_clear_old_log_entries' ) );
        $this->schedule_ve_clear_old_log_entries();
		add_action( 've_prune_staff_debug_log', array( $this, 'prune_staff_debug_log' ) );
		$this->schedule_prune_staff_debug_log();

		// POST Filters
		add_filter( 'acf/update_value/name=photo', array( $this, 'acf_set_featured_image' ), 10, 3 );

		// Initiate SMS Class
		$this->SMS = new Ve_Staff_SMS( $plugin_name, $version );

		// STAFF GROUPED TAGS
		add_action( 'save_post', array( $this, 'update_and_schedule_staff_posts_tags_on_save' ), 20, 3 );
        add_action( 'remove_staff_posts_tags_on_expiration', array( $this, 'remove_staff_posts_tags_on_expiration' ) );
        $this->schedule_remove_staff_posts_tags_on_expiration();
        add_action( 'wp_trash_post', array( $this, 'remove_staff_tags_on_post_trash' ) );
        add_action( 'before_delete_post', array( $this, 'remove_staff_tags_on_post_trash' ) );
        add_action( 'save_post', array( $this, 'delete_staff_transients_on_staff_tagged_post_change' ), 20 );
        add_action( 'before_delete_post', array( $this, 'delete_staff_transients_on_staff_tagged_post_change' ), 20, 1 );

		// Third Party Action Hooks
		add_action( 'siteground_optimizer_flush_cache', array( $this, 'delete_transients_on_demand' ) );

		// Security Key reset (optional)
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'reset_key' ) {
			add_action( 'admin_init', array( $this, 'staff_reset_secure_csv_key' ) );
		}

		// CSV Export Hooks
		add_action( 'wp_ajax_generate_staff_csv', array( $this, 'ajax_generate_staff_csv_files' ) );
		add_action( 'template_redirect', array( $this, 'staff_handle_csv_request' ) );
		add_action( 'save_post', array( $this, 'handle_staff_post_update' ), 10, 3 );
		add_action( 'wp', array( $this, 'schedule_staff_csv_updates' ) );
		add_action( 'staff_generate_daily_updates_csv', array( $this, 'generate_daily_updates_csv' ) );
		add_action( 've_staff_generate_csv_async', array( $this, 'process_scheduled_staff_csv_generation' ) );
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// ADMIN ASSETS
	// ─────────────────────────────────────────────────────────────────────────────

	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url(__FILE__) . 'css/ve-staff-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	// Add this new method within the class
	public function enqueue_post_editor_styles() {
		$screen = get_current_screen();
		$target_post_types = ['staff'];

		if ( $screen && in_array( $screen->post_type, $target_post_types, true ) && $screen->base === 'post' ) {
			wp_enqueue_style(
				've-staff-admin-post-editor',
				plugin_dir_url(__FILE__) . 'css/ve-staff-admin-post-editor.css',
				array(),
				$this->version,
				'all'
			);
		}
	}

	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url(__FILE__) . 'js/ve-staff-admin.js',
			array( 'jquery' ),
			$this->version,
			false
		);
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// INTERNAL LOGGING
	// ─────────────────────────────────────────────────────────────────────────────

	/**
	 * VE Logging
	 */
	private function ve_log($msg) {
		try {
			$upload_dir = wp_upload_dir();
			$dir = rtrim($upload_dir['basedir'], '/').'/staff/logs';
			if ( ! file_exists($dir) ) {
				wp_mkdir_p($dir);
			}
			$file = $dir.'/debug.log';
			$line = '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL;
			// message_type=3 writes directly to the file path
			error_log($line, 3, $file);
		} catch (Throwable $e) {
			// last resort: try normal error_log
			error_log('ve_log failed: '.$e->getMessage());
		}
	}
	
	// ─────────────────────────────────────────────────────────────────────────────
	// HELPERS (shared across methods)
	// ─────────────────────────────────────────────────────────────────────────────

	/**
	 * Return all defined anniversary tag mappings.
	 */
	public function get_anniversary_tag_map(): array {
		return [
			1 => '1-year', 3 => '3-year', 5 => '5-year', 10 => '10-year',
			15 => '15-year', 20 => '20-year', 25 => '25-year', 30 => '30-year',
			35 => '35-year', 40 => '40-year', 45 => '45-year', 50 => '50-year', 55 => '55-year',
		];
	}
	
	public function get_anniversary_tag_terms(): array {
		$tag_map = $this->get_anniversary_tag_map();
		$tag_terms = [];

		foreach ( $tag_map as $years => $slug ) {
			$term = get_term_by( 'slug', $slug, 'post_tag' );
			if ( $term && ! is_wp_error( $term ) ) {
				$tag_terms[ $years ] = $term; // store full term object
			}
		}

		return $tag_terms;
	}

	/**
	 * Remove all cached staff/listing-related transients.
	 */
	private function clear_related_transients(): void {
		global $wpdb;

		if ( $this->did_clear_related_transients ) {
			return;
		}

		$patterns = [
			'_transient_simplelist_%',
			'_transient_list_%',
			'_transient_display_%',              // catches most display-related caches
			'_transient_display_%_output_%',     // NEW: ensures HTML, script, and JSON display caches are cleared
			'_transient_staff_query_%',
			'_transient_staff_list_page_token_%',
			'_transient_staff_list_page_token__%',
			'_transient_staff_anniversary_%',
		];

		$this->ve_log('🧹 Starting transient cleanup...');

		$where = implode(' OR ', array_map(fn($p) => "option_name LIKE '$p' OR option_name LIKE '_transient_timeout_" . substr($p, strlen('_transient_')) . "'", $patterns));
		$sql = "DELETE FROM {$wpdb->options} WHERE {$where}";
		$deleted_rows = (int) $wpdb->query($sql);

		$this->did_clear_related_transients = true;
		$this->ve_log($deleted_rows > 0
			? "✅ Transient cleanup complete — {$deleted_rows} option rows deleted."
			: 'ℹ️ No matching transients found for cleanup.'
		);
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// PARTIALS / META BOXES
	// ─────────────────────────────────────────────────────────────────────────────

	/**
	 * Register admin partial files.
	 *
	 * @since    1.0.0
	 */
	public function adminPartials() {
		$admin_partials = array(
			'/ve-staff-admin-functions.php', // Initialize Admin Theme Functions
			'/ve-staff-admin-sms.php',
			'/ve-staff-admin-sms-api.php',
		);

		foreach ( $admin_partials as $file ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/' . $file;
		}
	}

    /**
    * Register admin meta boxes.
    */
    public function register_meta_boxes() {
        // Display Meta Box on the following Post Types
        $post_types = array('listing', 'simple-listing', 'display');
        add_meta_box( 'vestaffscript-1', __( 'VE Staff Embed Code', 'vestaffembed' ), array( $this, 'display_script_code_callback' ), $post_types );
    }

    /**
    * Meta box display callback.
    *
    * @param WP_Post $post Current post object.
    */
    public function display_script_code_callback( $post ) {
		$included_post_types = ['listing', 'simple-listing', 'display'];
		$current_post_type = get_post_type($post);

		if (!in_array($current_post_type, $included_post_types)) {
			return;
		}

		$postid     = $post->ID;
		$scripturl  = get_permalink($postid).'?type=script';
		$scriptcode = '<h2 class="loading-section">Loading... Please Wait!</h2><script type="text/javascript" src="'.$scripturl.'"></script><noscript><div>This page could not be loaded properly. Please make sure scripts are enabled and reload this page. If using Internet Explorer please try a different browser</div></noscript>';
		$scriptcode = htmlentities($scriptcode, ENT_QUOTES);

		echo '<h4>Copy the code below and paste into the target website to embed this listing:</h4>';
		echo '<pre><code>' . $scriptcode . '</code></pre>';
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// STAFF TAGGING (CRON + SAVE)
	// ─────────────────────────────────────────────────────────────────────────────

	/**
	 * Tag Employees Cron & Action
	 *
	 * @since    1.0.0
	 */
	public function ve_staff_tag_staff_cron() {
		$args = array(
			'post_type'   		=> array( 'staff' ),
			'post_status' 		=> array( 'publish' ),
			'posts_per_page'    => '-1',
		);
		$staff_posts = new WP_Query( $args );

		if ( $staff_posts->have_posts() ) :
			while( $staff_posts->have_posts() ) : $staff_posts->the_post();
				// Args
				$post_id       = get_the_ID();
				$published_date = date(get_the_date( 'Y-m-d' ));
				$start_date    = get_field('start_date');
				$newmonthsprior = '-1';
				$tags          = array();

				if (empty($start_date)) { $start_date = $published_date; }

				// Date to compare
				$comparedate = date('Y-m-d', strtotime("$newmonthsprior months"));

				// CHECK IF START DATE OR PROMOTION DATE IS BETWEEN FILTERS
				if ( $start_date >= $comparedate && $start_date <= date('Y-m-d') ) {
					if ( !has_tag( 'New' ) ) {
						$tags[] = 'New';
					}
				} else {
					if ( has_tag( 'New' ) ) {
						wp_remove_object_terms( $post_id, 'New', 'post_tag' );
					}
				}

				// Add tags to post
				if ( $tags ){
					wp_set_object_terms( $post_id, $tags, 'post_tag', true);
				}
			endwhile;
		endif;
	}

	// Add tags to staff on save
	public function ve_staff_tag_staff_save( $post_id ) {
		if ( get_post_type() !== 'staff' ) {
			return;
		}

		$published_date = date(get_the_date( 'Y-m-d' ));
		$start_date     = get_field('start_date');
		$newmonthsprior = '-1';
		$tags           = array();

		if ( empty($start_date) ) { $start_date = $published_date; }

		$comparedate = date('Y-m-d', strtotime("$newmonthsprior months"));

		if ( $start_date >= $comparedate && $start_date <= date('Y-m-d') ) {
			if ( !has_tag( 'New' ) ) {
				$tags[] = 'New';
			}
		} else {
			if ( has_tag( 'New' ) ) {
				wp_remove_object_terms( $post_id, 'New', 'post_tag' );
			}
		}

		if ( $tags ){
			wp_set_object_terms( $post_id, $tags, 'post_tag', true );
		}
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// STAFF TITLE & SLUG AUTO-UPDATER + BULK
	// ─────────────────────────────────────────────────────────────────────────────

	/**
   	* Set Staff Post Title from Meta Fields.
	*/
	public function staff_post_title_updater($post_id) {
		if (get_post_type($post_id) !== 'staff') {
			return;
		}

		$first_name = get_field('first_name', $post_id) ?: '';
		$last_name  = get_field('last_name', $post_id) ?: '';

		if (empty($first_name) || empty($last_name)) {
			$this->ve_log("⚠️ staff_post_title_updater skipped: Missing name fields for Post ID $post_id");
			return;
		}

		$title_field = get_field('title', $post_id);
		$title = '';

		if (is_array($title_field)) {
			$title = isset($title_field[0]['name']) ? $title_field[0]['name'] : '';
		} elseif (is_object($title_field) && isset($title_field->name)) {
			$title = $title_field->name;
		} elseif (is_string($title_field)) {
			$title = $title_field;
		}

		$new_title = trim("{$first_name} {$last_name}" . ($title ? " | {$title}" : ''));

		$current_title = get_post_field( 'post_title', $post_id );
		if ( $current_title === $new_title ) {
			return;
		}

		wp_update_post([
			'ID'         => $post_id,
			'post_title' => $new_title
		]);

		$this->ve_log("✅ staff_post_title_updater: Updated title for Post ID $post_id to '{$new_title}'");
	}

	//Auto add and update Staff Post Name field:
	public function staff_post_name_updater($post_id) {
		if (get_post_type($post_id) !== 'staff') {
			return;
		}

		$first_name = get_field('first_name', $post_id) ?: '';
		$last_name  = get_field('last_name', $post_id) ?: '';

		if (empty($first_name) || empty($last_name)) {
			$this->ve_log("⚠️ staff_post_name_updater skipped: Missing name fields for Post ID $post_id");
			return;
		}

		$slug = sanitize_title("{$first_name} {$last_name}");

		$current_slug = get_post_field( 'post_name', $post_id );
		if ( $current_slug === $slug ) {
			return;
		}

		wp_update_post([
			'ID'        => $post_id,
			'post_name' => $slug
		]);

		$this->ve_log("✅ staff_post_name_updater: Updated slug for Post ID $post_id to '{$slug}'");
	}

	/** BULK UPDATE ALL STAFF POSTS **/
	public function bulk_update_staff_posts() {
		$staff_query = new WP_Query([
			'post_type'      => 'staff',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]);

		foreach ($staff_query->posts as $post_id) {
			$this->staff_post_name_updater($post_id);
			$this->staff_post_title_updater($post_id);
		}
	}

 	/**
   	* Set ACF 'photo' as featured image on update.
	*/
	public function acf_set_featured_image( $value, $post_id, $field  ){
		if ($value != '') {
			update_post_meta($post_id, '_thumbnail_id', $value);
		}
		return $value;
	}

 	/**
   	* Remove the scheduled delete (keeps trash indefinitely).
	*/
	public function wpb_remove_schedule_delete() {
		remove_action( 'wp_scheduled_delete', 'wp_scheduled_delete' );
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// ACF MODS (Read-only + Role-based permissions)
	// ─────────────────────────────────────────────────────────────────────────────

	public function make_acf_fields_read_only() { ?>
		<script type="text/javascript">
			(function($) {
				$(document).ready(function(){
					$('[data-name="anniversary_date"] input').attr('readonly', 'readonly').css('pointer-events', 'none');
					$('[data-name="anniversary_date_last"] input').attr('readonly', 'readonly').css('pointer-events', 'none');
					$('[data-name="years_service_current"] input').attr('readonly', 'readonly').css('pointer-events', 'none');
					$('[data-name="years_service_on_anniversary"] input').attr('readonly', 'readonly').css('pointer-events', 'none');
				});
			})(jQuery);
		</script>
	<?php }

	public function enqueue_acf_readonly_script() {
		if (function_exists('acf') && is_admin()) {
			wp_enqueue_script('acf-readonly-script', plugin_dir_url(__FILE__) . 'js/acf-readonly.js', array('jquery'), null, true);
		}
	}

    // Add custom role permissions config to each ACF field (except structural)
    public function add_acf_field_permissions_settings($field) {
		$excluded_field_types = ['acfe_column', 'tab', 'accordion'];
		if (in_array($field['type'], $excluded_field_types, true)) {
			return;
		}

		global $wp_roles;
		$roles = $wp_roles->roles;
		$role_choices = [];

		foreach ($roles as $role_key => $role) {
			if ($role_key !== 'administrator') {
				$role_choices[$role_key] = $role['name'];
			}
		}

		acf_render_field_setting($field, array(
			'label' => 'Custom Role Permissions',
			'name' => 'custom_role_permissions',
			'type' => 'checkbox',
			'choices' => $role_choices,
			'ui' => 0,
			'default_value' => ['editor'],
			'instructions' => 'This is a custom permissions setting by Vern Eide to control which roles can edit this field. If a role is not allowed to edit, they will still see the field data as Read-Only. To hide the field completely, use advanced permissions under Presentation.',
			'message' => 'Select the roles that can edit this field.',
		));
	}

	// Apply role-based ACF restrictions
    public function apply_acf_field_role_permissions($field) {
		if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'acf-tools') {
			return $field;
		}
		$current_user = wp_get_current_user();
		$user_roles = $current_user->roles;

		if (in_array('administrator', $user_roles, true)) {
			return $field;
		}

		$excluded_field_types = ['acfe_column', 'tab', 'accordion'];
		if (in_array($field['type'], $excluded_field_types, true)) {
			return $field;
		}

		$allowed_roles = $field['custom_role_permissions'] ?? [];

		if (!empty($allowed_roles)) {
			foreach ($user_roles as $role) {
				if (in_array($role, $allowed_roles, true)) {
					return $field;
				}
			}
		}

		$field['wrapper']['class'] .= ' acf-readonly-field';
		$field['readonly'] = 1;

		return $field;
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// TRANSIENT MANAGEMENT
	// ─────────────────────────────────────────────────────────────────────────────

	public function delete_transients_on_demand() {
		$this->clear_related_transients();
	}

	public function delete_transients_on_post_update($post_id) {
		if ( wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) ) {
			return;
		}

		$post = get_post($post_id);
		if (!$post) return;

		if (in_array($post->post_type, ['staff', 'listing', 'simple-listing', 'display'])) {
			$this->clear_related_transients();
		}
	}

	public function delete_transients_on_post_add($post_id, $post, $update) {
		if ( $update || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id) ) {
			return;
		}

		if (in_array($post->post_type, ['staff', 'listing', 'simple-listing', 'display'])) {
			$this->clear_related_transients();
		}
	}

	public function delete_staff_transients_on_staff_tagged_post_change($post_id) {
		$post = get_post($post_id);
		if (!$post) return;

		if ($post->post_type === 'staff-tagged') {
			$this->clear_related_transients();
		}
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// STAFF-TAGGED GROUP POSTS (scheduling + expiration + trash)
	// ─────────────────────────────────────────────────────────────────────────────

	public function update_and_schedule_staff_posts_tags_on_save($post_id, $post, $update) {
		if ($post->post_type !== 'staff-tagged') return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (get_post_status($post_id) === 'trash') return;

		$start_date = get_field('staff_tag_start_date', $post_id);

		// If start date is in future, schedule the post and bail
		if ($start_date && strtotime($start_date) > current_time('timestamp')) {
			$timestamp = strtotime($start_date);

			if (get_post_status($post_id) !== 'future') {
				wp_update_post(array(
					'ID'            => $post_id,
					'post_status'   => 'future',
					'post_date'     => date('Y-m-d H:i:s', $timestamp),
					'post_date_gmt' => get_gmt_from_date(date('Y-m-d H:i:s', $timestamp)),
				));
			}
			return;
		}

		$selected_tag_id = get_field('staff_tag', $post_id);
		$selected_posts  = get_field('staff_tagged', $post_id);

		if ($selected_tag_id && $selected_posts) {
			if (is_array($selected_posts)) {
				foreach ($selected_posts as $staff_post_id) {
					if (!has_term($selected_tag_id, 'post_tag', $staff_post_id)) {
						wp_set_post_tags($staff_post_id, array($selected_tag_id), true);
					}
				}
			} else {
				if (!has_term($selected_tag_id, 'post_tag', $selected_posts)) {
					wp_set_post_tags($selected_posts, array($selected_tag_id), true);
				}
			}
		}
	}

	public function schedule_remove_staff_posts_tags_on_expiration() {
        if (!wp_next_scheduled('remove_staff_posts_tags_on_expiration')) {
            wp_schedule_event(time(), 'hourly', 'remove_staff_posts_tags_on_expiration');
        }
    }

	public function remove_staff_posts_tags_on_expiration() {
		$args = array(
			'post_type'      => 'staff-tagged',
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => 'staff_tag_expiration_date',
					'value'   => current_time('Y-m-d H:i:s'),
					'compare' => '<=',
					'type'    => 'DATETIME',
				),
			),
		);

		$query = new WP_Query($args);

		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();

				$selected_tag_id = get_field('staff_tag', get_the_ID());
				$selected_posts  = get_field('staff_tagged', get_the_ID());

				if ($selected_tag_id && $selected_posts) {
					if (is_array($selected_posts)) {
						foreach ($selected_posts as $staff_post_id) {
							wp_remove_object_terms($staff_post_id, $selected_tag_id, 'post_tag');
						}
					} else {
						wp_remove_object_terms($selected_posts, $selected_tag_id, 'post_tag');
					}
				}

				wp_trash_post(get_the_ID());
			}
			wp_reset_postdata();
		}
	}

	public function remove_staff_tags_on_post_trash($post_id) {
		if (get_post_type($post_id) !== 'staff-tagged') return;

		$selected_tag_id = get_field('staff_tag', $post_id);
		$selected_posts  = get_field('staff_tagged', $post_id);

		if ($selected_tag_id && $selected_posts) {
			if (is_array($selected_posts)) {
				foreach ($selected_posts as $staff_post_id) {
					wp_remove_object_terms($staff_post_id, $selected_tag_id, 'post_tag');
				}
			} else {
				wp_remove_object_terms($selected_posts, $selected_tag_id, 'post_tag');
			}
		}
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// OPTIONS PAGES (Referrer list + Anniversary Settings + Logs)
	// ─────────────────────────────────────────────────────────────────────────────

	public function ve_staff_referrer_settings( $post_id ) {
		// Only run on the specific options page
		if (!isset($_GET['page']) || $_GET['page'] !== 've-staff-settings') {
			return;
		}

		$referrers = get_field('ve_allowed_referrers', 'option');
		$url_list = [];
		$ip_list  = [];

		foreach ($referrers as $referrer) {
			if ($referrer['match_type'] === 'url') {
				$url = $referrer['url'];
				if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
					$url = "http://" . $url;
				}
				$parsed_url  = parse_url($url);
				$root_domain = $parsed_url['host'] ?? '';
				$root_domain = str_replace('www.', '', $root_domain);
				$url_list[]  = $root_domain;
			} elseif ($referrer['match_type'] === 'ip') {
				$ips = explode(',', $referrer['ip_address']);
				foreach ($ips as $ip) {
					$ip_list[] = trim($ip);
				}
			}
		}

		$settings_folder = get_template_directory() . '/inc/ve-staff/settings/';
		if (!file_exists($settings_folder)) {
			mkdir($settings_folder, 0755, true);
		}

		file_put_contents($settings_folder . 'allowed_url_list.json', json_encode($url_list));
		file_put_contents($settings_folder . 'allowed_ip_list.json', json_encode($ip_list));
	}

	public function handle_anniversary_options_save($post_id) {
		if ($post_id !== 'options') {
			return;
		}
		$this->ve_log("⚙️ Anniversary settings updated — refreshing all expiration dates...");
		$this->update_all_anniversary_expirations();
	}

	public function register_ve_logs_submenu() {
		add_submenu_page(
			've-staff-settings',
			'View Logs',
			'Logs Viewer',
			'manage_options',
			've_staff_logs',
			array( $this, 'render_logs_page' )
		);
	}

	public function render_logs_page() {
		$upload_dir = wp_upload_dir();
		$log_file = rtrim($upload_dir['basedir'], '/') . '/staff/logs/debug.log';

		echo '<div class="wrap"><h1>VE Staff Logs Viewer</h1>';

		if ( ! file_exists($log_file) ) {
			echo '<p><em>No log file found at:</em> <code>' . esc_html($log_file) . '</code></p>';
			echo '</div>';
			return;
		}

		$lines_to_show = 500;
		$lines = $this->tail_file($log_file, $lines_to_show);

		echo '<p><strong>Showing the most recent ' . intval($lines_to_show) . ' entries from:</strong> <code>' . esc_html($log_file) . '</code></p>';
		echo '<textarea readonly style="width:100%;height:600px;font-family:monospace;background:#111;color:#0f0;white-space:pre;overflow:auto;">';
		echo esc_textarea(implode("\n", array_reverse($lines)));
		echo '</textarea>';

		if ( current_user_can('manage_options') ) {
			$clear_url = add_query_arg(['page' => 've_staff_logs', 'clear_log' => '1']);
			echo '<p><a href="' . esc_url($clear_url) . '" class="button button-secondary" onclick="return confirm(\'Clear the log file?\')">Clear Log</a></p>';

			if ( isset($_GET['clear_log']) && $_GET['clear_log'] === '1' ) {
				file_put_contents($log_file, '');
				echo '<div class="updated notice"><p>Log file cleared successfully.</p></div>';
			}
		}

		echo '</div>';
	}

	private function tail_file($file, $lines = 100) {
		$f = fopen($file, "r");
		$buffer = '';
		$chunk_size = 4096;
		$pos = -1;
		$line_count = 0;
		$data = [];

		fseek($f, 0, SEEK_END);
		$pos = ftell($f);

		while ($pos > 0 && $line_count < $lines) {
			$read_size = ($pos - $chunk_size > 0) ? $chunk_size : $pos;
			$pos -= $read_size;
			fseek($f, $pos);
			$chunk = fread($f, $read_size);
			$buffer = $chunk . $buffer;
			$line_count = substr_count($buffer, "\n");
		}

		fclose($f);
		$all_lines = explode("\n", trim($buffer));
		return array_slice($all_lines, -$lines);
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// ANNIVERSARY LOGIC (fields, reconciliation, cleanup, backfill, settings)
	// ─────────────────────────────────────────────────────────────────────────────

	public function update_anniversary_fields_on_save( $post_id ) {
		if ( get_post_type($post_id) !== 'staff' || wp_is_post_revision($post_id)) {
			return;
		}
		$this->ve_log("Calling update_anniversary_fields_staff_posts for post_id: $post_id");
		$this->update_anniversary_fields_staff_posts( $post_id );
	}

	/**
	 * Safety net — ensure anniversary tag is correct on any staff save/update.
	 */
	public function check_anniversary_tag_on_save($post_id, $post, $update) {
		if (wp_is_post_revision($post_id)) return;
		if (get_post_type($post_id) !== 'staff') return;

		$this->ve_log("🔄 check_anniversary_tag_on_save triggered for post {$post_id}");

		$last_anniversary_date = get_field('anniversary_anniversary_date_last', $post_id);
		$next_anniversary_date = get_field('anniversary_anniversary_date', $post_id);
		$years_service_current = (int) get_field('anniversary_years_service_current', $post_id);

		if (!$last_anniversary_date && !$next_anniversary_date) {
			$this->ve_log("⚠️ No anniversary dates found for post {$post_id}, skipping tag check.");
			return;
		}

		$this->recompute_and_reconcile_anniversary_tags($post_id);
		$this->ve_log("✅ Finished tag reconciliation for post {$post_id}");
	}

	/**
	 * Recompute and reconcile anniversary tags so only the correct milestone tag (if any) remains.
	 * Uses both last & next dates and the global days_before/months_after window.
	 */
	public function recompute_and_reconcile_anniversary_tags($post_id) {
		if (get_post_type($post_id) !== 'staff') return;

		$today = new DateTime('today');
		$settings = $this->get_anniversary_settings();
		$days_before  = (int) ($settings['days_before'] ?? 0);
		$months_after = (int) ($settings['months_after'] ?? 6);

		$last_str = get_field('anniversary_anniversary_date_last', $post_id) ?: '';
		$next_str = get_field('anniversary_anniversary_date', $post_id) ?: '';
		$years_current = (int) get_field('anniversary_years_service_current', $post_id);

		$tag_map = $this->get_anniversary_tag_map();
		$valid_slugs = array_values($tag_map);

		$current_slugs = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'slugs']) ?: [];

		$last = null; $next = null;
		try { if ($last_str && preg_match('/^\d{4}-\d{2}-\d{2}$/', $last_str)) $last = new DateTime($last_str); } catch (Exception $e) {}
		try { if ($next_str && preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_str)) $next = new DateTime($next_str); } catch (Exception $e) {}

		$in_window = false;
		$expected_milestone = null;
		$window_end_for_meta = null;

		if ($last || $next) {
			$anchor = null;
			if ($days_before > 0 && $next && $today <= $next) {
				$anchor = clone $next;
			} elseif ($last) {
				$anchor = clone $last;
			}

			if ($anchor) {
				$window_start = (clone $anchor)->modify("-{$days_before} days");
				$window_end   = (clone $anchor)->modify("+{$months_after} months");
				$in_window    = ($today >= $window_start && $today <= $window_end);
				$window_end_for_meta = $window_end;

				if ($in_window) {
					$expected_milestone = ($today < $anchor) ? ($years_current + 1) : $years_current;
				}
			}
		}

		$expected_slug = ($expected_milestone && isset($tag_map[$expected_milestone])) ? $tag_map[$expected_milestone] : null;

		$to_remove = array_intersect($current_slugs, $valid_slugs);
		if ($expected_slug) {
			$to_remove = array_diff($to_remove, [$expected_slug]);
		}

		foreach ($to_remove as $slug) {
			$term = get_term_by('slug', $slug, 'post_tag');
			if ($term) {
				wp_remove_object_terms($post_id, (int)$term->term_id, 'post_tag');
				$this->ve_log("🧹 Reconciler: removed stale anniversary tag '{$slug}' from post {$post_id}");
			}
		}

		if ($expected_slug) {
			$term = get_term_by('slug', $expected_slug, 'post_tag');
			if (!$term) {
				$name = sprintf('%d Year Anniversary', (int)$expected_milestone);
				$ins  = wp_insert_term($name, 'post_tag', ['slug' => $expected_slug]);
				if (is_wp_error($ins)) {
					$this->ve_log("❌ Reconciler: failed to create tag '{$expected_slug}' for post {$post_id}: ".$ins->get_error_message());
					return;
				}
				$term_id = (int)$ins['term_id'];
			} else {
				$term_id = (int)$term->term_id;
			}

			if (!in_array($expected_slug, $current_slugs, true)) {
				wp_set_post_terms($post_id, [$term_id], 'post_tag', true);
				$this->ve_log("✅ Reconciler: added expected tag '{$expected_slug}' to post {$post_id}");
			}

			if ($window_end_for_meta) {
				update_post_meta($post_id, 'anniversary_tag_expiration_date', $window_end_for_meta->format('Y-m-d'));
			}
		} else {
			delete_post_meta($post_id, 'anniversary_tag_expiration_date');
		}
	}

	public function update_anniversary_fields_staff_posts($post_id) {
		$this->ve_log("Running update_anniversary_fields_staff_posts for post_id: $post_id");

		// Collect all possible hire/rehire dates
		$raw_dates = [
			get_field('start_date', $post_id),
			get_field('rehire_date_1', $post_id),
			get_field('rehire_date_2', $post_id),
			get_field('rehire_date_3', $post_id),
			get_field('rehire_date_4', $post_id),
		];

		// Filter valid YYYY-MM-DD formatted dates
		$dates = array_filter($raw_dates, function($date) {
			return $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
		});

		$this->ve_log("Filtered dates: " . print_r($dates, true));

		// Parse all valid dates into DateTime objects
		foreach ($dates as &$date) {
			try {
				$date = new DateTime($date);
				$this->ve_log("Parsed date: " . $date->format('Y-m-d'));
			} catch (Exception $e) {
				$this->ve_log("Error parsing date '$date': " . $e->getMessage());
				return;
			}
		}
		unset($date);

		// Add additional years of service (if any)
		$add_yrs_service = (int) get_field('anniversary_add_yrs_service', $post_id) ?: 0;
		$this->ve_log("add_yrs_service: $add_yrs_service");

		$today = new DateTime();
		$this->ve_log("Today's date: " . $today->format('Y-m-d'));

		// Determine most recent hire/rehire date
		if (empty($dates)) {
			try {
				$most_recent_date = new DateTime(get_field('start_date', $post_id));
				$this->ve_log("Only start_date available, set most_recent_date: " . $most_recent_date->format('Y-m-d'));
			} catch (Exception $e) {
				$this->ve_log("Error parsing start_date: " . $e->getMessage());
				return;
			}
		} else {
			rsort($dates);
			$most_recent_date = $dates[0];
			$this->ve_log("Most recent date: " . $most_recent_date->format('Y-m-d'));
		}

		// ───────────────────────────────────────────────
		// Calculate Anniversary Dates
		// ───────────────────────────────────────────────

		// Base anniversary this year
		$anniv_this_year = new DateTime();
		$anniv_this_year->setDate($today->format('Y'), $most_recent_date->format('m'), $most_recent_date->format('d'));

		$last_anniversary = clone $anniv_this_year;
		if ($last_anniversary > $today) {
			$last_anniversary->modify('-1 year');
		}
		$next_anniversary = (clone $last_anniversary)->modify('+1 year');

		// ✅ Adjustment: Ensure last anniversary never predates the most recent hire/rehire date
		if ($last_anniversary < $most_recent_date) {
			$this->ve_log("🩹 Adjusting last anniversary forward to rehire date for post {$post_id}");
			$last_anniversary = clone $most_recent_date;
			$next_anniversary = (clone $most_recent_date)->modify('+1 year');
		}

		// Safety: ensure next > last
		if ($next_anniversary <= $last_anniversary) {
			$next_anniversary = (clone $last_anniversary)->modify('+1 year');
		}

		$this->ve_log("Last anniversary: " . $last_anniversary->format('Y-m-d'));
		$this->ve_log("Next anniversary: " . $next_anniversary->format('Y-m-d'));

		// Calculate years of service
		$years_service_current = $today->diff($most_recent_date)->y + $add_yrs_service;
		$years_service_on_anniversary = $next_anniversary->diff($most_recent_date)->y + $add_yrs_service;

		$this->ve_log("years_service_current: $years_service_current, years_service_on_anniversary: $years_service_on_anniversary");

		// Update ACF fields
		update_field('anniversary_anniversary_date_last', $last_anniversary->format('Y-m-d'), $post_id);
		update_field('anniversary_anniversary_date', $next_anniversary->format('Y-m-d'), $post_id);
		update_field('anniversary_years_service_current', $years_service_current, $post_id);
		update_field('anniversary_years_service_on_anniversary', $years_service_on_anniversary, $post_id);

		$this->ve_log("✅ Updated ACF anniversary fields for post_id: $post_id");

		// Reconcile tags
		$this->recompute_and_reconcile_anniversary_tags($post_id);
		$this->ve_log("🔁 Reconciled anniversary tags after field update for post {$post_id}");
	}

	public function get_anniversary_settings() {
		return [
			'days_before'  => (int) get_field('anniversary_days_before', 'option') ?: 0,
			'months_after' => (int) get_field('anniversary_months_after', 'option') ?: 6,
		];
	}

	/* ────────────────────────────────────────────────────────────────────────────
	 * LEGACY (Deprecated): add_anniversary_tag()
	 * Kept for reference — replaced by recompute_and_reconcile_anniversary_tags()
	 *
	 * The original function is left commented out to preserve history.
	 * ────────────────────────────────────────────────────────────────────────────
	 *
	 *  ... (unchanged commented legacy function kept as in your source) ...
	 */

	public function update_all_anniversary_expirations() {
		$settings = $this->get_anniversary_settings();
		$months_after = $settings['months_after'];
		$today = new DateTime('today');

		$staff_query = new WP_Query([
			'post_type'      => 'staff',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids'
		]);

		foreach ($staff_query->posts as $post_id) {
			$anniversary_date = get_field('anniversary_anniversary_date', $post_id);
			if (!$anniversary_date) continue;

			try {
				$anniversary_date = new DateTime($anniversary_date);
				$window_end = (clone $anniversary_date)->modify("+{$months_after} months");
				update_post_meta($post_id, 'anniversary_tag_expiration_date', $window_end->format('Y-m-d'));
				$this->ve_log("🔄 Updated expiration date for post {$post_id} → {$window_end->format('Y-m-d')}");
			} catch (Exception $e) {
				$this->ve_log("⚠️ Skipped post {$post_id}: Invalid anniversary date format");
			}
			$this->recompute_and_reconcile_anniversary_tags($post_id);
		}
	}

	/**
	 * Manually run anniversary tag cleanup for all staff posts.
	 * Removes expired or missing anniversary tags immediately.
	 */
	public function run_anniversary_tag_cleanup() {
		$this->ve_log("🧹 Manual anniversary tag cleanup started...");

		$staff_query = new WP_Query([
			'post_type'      => 'staff',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids'
		]);

		$count_removed = 0;
		$today = new DateTime('today');

		foreach ($staff_query->posts as $post_id) {
			$expiration_date = get_post_meta($post_id, 'anniversary_tag_expiration_date', true);
			$expired_or_missing = false;

			$last_anniv = get_field('anniversary_anniversary_date_last', $post_id);
			$next_anniv = get_field('anniversary_anniversary_date', $post_id);

			try {
				if (empty($expiration_date)) {
					$has_valid_window = false;

					if ($next_anniv && preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_anniv)) {
						$next = new DateTime($next_anniv);
						$window_start = (clone $next)->modify('-7 days');
						$window_end   = (clone $next)->modify('+6 months');
						if ($today >= $window_start && $today <= $window_end) {
							$has_valid_window = true;
						}
					}

					if (!$has_valid_window) {
						$expired_or_missing = true;
						$this->ve_log("⚠️ Post {$post_id}: missing expiration metadata and no active anniversary window — will remove tag(s).");
					} else {
						$this->ve_log("ℹ️ Post {$post_id}: within active anniversary window — keeping tags.");
					}
				} else {
					$expiration_date_obj = new DateTime($expiration_date);
					if ($today > $expiration_date_obj) {
						$expired_or_missing = true;
						$this->ve_log("🧾 Tag expired on {$expiration_date_obj->format('Y-m-d')}");
					}
				}
			} catch (Exception $e) {
				$expired_or_missing = true;
				$this->ve_log("⚠️ Invalid expiration format for post {$post_id}, removing tag(s).");
			}

			if ($expired_or_missing) {
				$tag_map = $this->get_anniversary_tag_map();
				$valid_slugs = array_values($tag_map);
				$current_tags = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'slugs']) ?: [];
				$tags_to_remove = array_intersect($current_tags, $valid_slugs);

				foreach ($tags_to_remove as $slug) {
					$term = get_term_by('slug', $slug, 'post_tag');
					if ($term) {
						wp_remove_object_terms($post_id, (int) $term->term_id, 'post_tag');
						$this->ve_log("🧹 Removed expired anniversary tag '{$slug}'");
						$count_removed++;
					}
				}

				delete_post_meta($post_id, 'anniversary_tag_expiration_date');
			}
			$this->recompute_and_reconcile_anniversary_tags($post_id);
		}

		$this->ve_log("✅ Manual anniversary tag cleanup completed. Removed tags from {$count_removed} staff posts.");
		return $count_removed;
	}

	public function ajax_run_backfill_anniversary_date_last() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized'], 403);
		}
		$this->backfill_anniversary_date_last_safe();
		wp_send_json_success(['message' => 'Safe backfill completed. Check debug log for details.']);
	}

	/**
	 * One-time maintenance: safely populate anniversary_date_last for all staff.
	 * - Only fills missing or invalid values.
	 * - Safe to rerun multiple times.
	 */
	public function backfill_anniversary_date_last_safe() {
		$this->ve_log("⚙️ Running safe backfill for anniversary_date_last...");

		$staff_query = new WP_Query([
			'post_type'      => 'staff',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids'
		]);

		$total_updated = 0;
		$today = new DateTime();

		foreach ($staff_query->posts as $post_id) {
			// Skip if already has a valid 'last' anniversary field
			$existing_last = get_field('anniversary_anniversary_date_last', $post_id);
			if ($existing_last && preg_match('/^\d{4}-\d{2}-\d{2}$/', $existing_last)) {
				continue;
			}

			// Gather all possible date fields (start + rehires)
			$raw_dates = [
				get_field('start_date', $post_id),
				get_field('rehire_date_1', $post_id),
				get_field('rehire_date_2', $post_id),
				get_field('rehire_date_3', $post_id),
				get_field('rehire_date_4', $post_id),
			];

			$dates = array_filter($raw_dates, function($date) {
				return $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
			});

			if (empty($dates)) {
				$this->ve_log("⚠️ Skipping post {$post_id} — no valid hire/rehire dates found.");
				continue;
			}

			rsort($dates);
			try {
				$most_recent_date = new DateTime($dates[0]);
			} catch (Exception $e) {
				$this->ve_log("⚠️ Invalid hire date format for post {$post_id}: " . $e->getMessage());
				continue;
			}

			// Pull next anniversary for comparison
			$next_anniversary_date = get_field('anniversary_anniversary_date', $post_id);
			if (!$next_anniversary_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_anniversary_date)) {
				$this->ve_log("⚠️ Skipping post {$post_id} — no valid 'next' anniversary date.");
				continue;
			}

			try {
				$next_anniversary = new DateTime($next_anniversary_date);
			} catch (Exception $e) {
				$this->ve_log("⚠️ Invalid next anniversary format for post {$post_id}: " . $e->getMessage());
				continue;
			}

			// Calculate 'last' anniversary (previous year)
			$last_anniversary = (clone $next_anniversary)->modify('-1 year');

			// ✅ Safeguard: Ensure last anniversary never predates the most recent hire/rehire date
			if ($last_anniversary < $most_recent_date) {
				$this->ve_log("🩹 Adjusting last anniversary forward to rehire date for post {$post_id}");
				$last_anniversary = clone $most_recent_date;
			}

			// Save updated value
			update_field('anniversary_anniversary_date_last', $last_anniversary->format('Y-m-d'), $post_id);
			$total_updated++;
			$this->ve_log("✅ Backfilled last anniversary for post {$post_id} → {$last_anniversary->format('Y-m-d')}");
		}

		$this->ve_log("✅ Backfill complete. Updated {$total_updated} staff records.");
		return $total_updated;
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// BATCH UPDATES (AJAX + Cron) + SCHEDULING
	// ─────────────────────────────────────────────────────────────────────────────

	// Deactivation cleanup
	public function clear_staff_batch_update_event() {
		$ts = wp_next_scheduled('daily_staff_batch_update_event');
		if ($ts) wp_unschedule_event($ts, 'daily_staff_batch_update_event');

		$ts2 = wp_next_scheduled('ve_clear_old_log_entries');
		if ($ts2) wp_unschedule_event($ts2, 've_clear_old_log_entries');

		$ts3 = wp_next_scheduled('staff_generate_daily_updates_csv');
		if ($ts3) wp_unschedule_event($ts3, 'staff_generate_daily_updates_csv');
	}

	public function run_staff_batch_update(int $offset = 0, int $batch_size = 50) {
		$staff_query = new WP_Query([
			'post_type' => 'staff',
			'posts_per_page' => $batch_size,
			'offset' => $offset,
			'fields' => 'ids'
		]);

		$updated_count = 0;
		foreach ($staff_query->posts as $post_id) {
			$this->update_anniversary_fields_staff_posts($post_id);
			$this->recompute_and_reconcile_anniversary_tags($post_id);
			$updated_count++;
		}

		$has_more = $staff_query->found_posts > ($offset + $batch_size);

		return [
			'count' => $updated_count,
			'has_more' => $has_more
		];
	}

	public function run_bulk_staff_meta_data_update_batch(int $offset = 0, int $batch_size = 50) {
		$staff_query = new WP_Query([
			'post_type'      => 'staff',
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'fields'         => 'ids',
		]);

		$updated_count = 0;

		foreach ($staff_query->posts as $post_id) {
			$this->staff_post_title_updater($post_id);
			$this->staff_post_name_updater($post_id);
			$updated_count++;
		}

		$has_more = $staff_query->found_posts > ($offset + $batch_size);

		return [
			'count'     => $updated_count,
			'has_more'  => $has_more,
		];
	}

	// AJAX: Trigger manual anniversary tag cleanup
	public function ajax_run_anniversary_tag_cleanup() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized access.'], 403);
		}

		try {
			$count_removed = $this->run_anniversary_tag_cleanup();
			wp_send_json_success(['message' => "Removed tags from {$count_removed} staff posts."]);
		} catch (Throwable $e) {
			wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
		}
	}

	public function process_staff_batch_update() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized', 403);
		}

		$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
		$batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;
		$total_updated_count = isset($_POST['total_updated_count']) ? intval($_POST['total_updated_count']) : 0;

		$result = $this->run_staff_batch_update($offset, $batch_size);
		$total_updated_count += $result['count'];

		if ($result['has_more']) {
			wp_send_json_success([
				'count' => $result['count'],
				'has_more' => true,
				'next_offset' => $offset + $batch_size,
				'total_updated_count' => $total_updated_count
			]);
		} else {
			update_option('staff_batch_update_last_run', current_time('mysql'));
			update_option('staff_batch_update_last_run_results', $total_updated_count);

			wp_send_json_success([
				'count' => $result['count'],
				'has_more' => false,
				'total_updated_count' => $total_updated_count
			]);
		}
	}

	public function process_bulk_staff_meta_data_update() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized', 403);
		}

		$offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
		$batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 50;

		$result = $this->run_bulk_staff_meta_data_update_batch($offset, $batch_size);

		$total_updated_count = $offset + $result['count'];

		if ($result['has_more']) {
			wp_send_json_success([
				'count' => $result['count'],
				'has_more' => true,
				'next_offset' => $offset + $batch_size,
				'total_updated_count' => $total_updated_count,
			]);
		} else {
			update_option('staff_batch_meta_update_last_run', current_time('mysql'));
			update_option('staff_batch_meta_update_last_run_results', $total_updated_count);

			wp_send_json_success([
				'count' => $result['count'],
				'has_more' => false,
				'total_updated_count' => $total_updated_count,
			]);
		}
	}

	// Admin screen for batch updates (as-is, just organized placement)
	public function register_custom_staff_batch_update_submenu() {
		add_submenu_page(
			've-staff-settings',
			'Staff Batch Update',
			'Staff Batch Update',
			'manage_options',
			'staff-batch-update',
			array( $this, 'render_staff_batch_update_page' )
		);
	}

	public function render_staff_batch_update_page() {
		$last_run = get_option('staff_batch_update_last_run');
		$last_run_results = get_option('staff_batch_update_last_run_results', 0);
		$last_batch_meta_run = get_option('staff_batch_meta_update_last_run');
		$last__batch_meta_run_results = get_option('staff_batch_meta_update_last_run_results', 0);

		?>
		<div class="wrap">
			<h1>Staff Batch Update Actions</h1>
			<p>The following actions can be manually run to batch process all the staff profiles.</p>
		</div>
		<div class="wrap">
			<h3>Update Staff Anniversary Fields</h3>
			<p>Click the button below to begin updating anniversary fields for all staff posts in batches.<br>
			<small><i>Note: This batch process is also scheduled to run daily at 3AM.</i></small></p>
			<div style="margin-bottom: 20px;">
				<strong>Last Batch Update Run:</strong> <?php echo $last_run ? esc_html($last_run) : 'Never'; ?><br>
				<strong>Last Batch Update Results:</strong> <?php echo intval($last_run_results); ?> posts processed
			</div>

			<button id="start-update" class="button button-primary">Start Anniversary Update</button>
			<div style="margin-top:10px;" id="update-status"></div>
		</div>
		<div class="wrap">
			<h3>Update Staff Titles & Slugs</h3>
			<p>This action will update the <code>post_title</code> and <code>post_name</code> for all staff posts using the First and Last Name fields.</p>
			<div style="margin-bottom: 20px;">
				<strong>Last Batch Update Run:</strong> <?php echo $last_batch_meta_run ? esc_html($last_batch_meta_run) : 'Never'; ?><br>
				<strong>Last Batch Update Results:</strong> <?php echo intval($last__batch_meta_run_results); ?> posts processed
			</div>
			<button id="start-meta-data-update" class="button button-primary">Start Title + Slug Update</button>
			<div style="margin-top:10px;" id="meta-data-update-status"></div>
		</div>

		<script type="text/javascript">
		(function($) {
			function runBatchUpdate(actionName, statusSelector, buttonSelector, resultKey = 'count') {
				$(buttonSelector).on('click', function () {
					let offset = 0;
					let totalUpdated = 0;
					let batchSize = 50;
					const $status = $(statusSelector);

					function processBatch() {
						$status.html(`<em>Processing offset ${offset}...</em>`);

						$.post(ajaxurl, {
							action: actionName,
							offset: offset,
							batch_size: batchSize,
							total_updated_count: totalUpdated
						})
						.done(function (response) {
							if (response.success) {
								const batchCount = response.data[resultKey] || 0;
								totalUpdated = response.data.total_updated_count || (totalUpdated + batchCount);

								$status.html(`<strong>Updated ${totalUpdated} staff posts...</strong>`);

								if (response.data.has_more) {
									offset = response.data.next_offset;
									setTimeout(processBatch, 250);
								} else {
									$status.html(`<strong style="color:green;">All done! ${totalUpdated} staff posts updated.</strong>`);
								}
							} else {
								const errorMsg = typeof response.data === 'string' ? response.data : 'Unexpected response structure.';
								$status.html(`<span style="color:red;">Error: ${errorMsg}</span>`);
							}
						})
						.fail(function (jqXHR, textStatus, errorThrown) {
							$status.html(`<span style="color:red;">Request failed: ${textStatus} – ${errorThrown}</span>`);
						});
					}

					processBatch();
				});
			}

			runBatchUpdate('process_staff_batch_update', '#update-status', '#start-update', 'total_updated_count');
			runBatchUpdate('process_bulk_staff_meta_data_update', '#meta-data-update-status', '#start-meta-data-update', 'updated_total');
		})(jQuery);
		</script>

		<div class="wrap">
			<h3>Manual Anniversary Tag Cleanup</h3>
			<p>Removes expired or missing anniversary tags from staff posts immediately (instead of waiting for cron).</p>
			<button id="run-anniversary-cleanup" class="button button-secondary">Run Anniversary Tag Cleanup</button>
			<div style="margin-top:10px;" id="anniversary-cleanup-status"></div>
		</div>

		<script type="text/javascript">
		(function($) {
			$('#run-anniversary-cleanup').on('click', function() {
				const $status = $('#anniversary-cleanup-status');
				$status.html('<em>Running cleanup...</em>');
				$.post(ajaxurl, { action: 'run_anniversary_tag_cleanup' })
				 .done(function(response) {
					 if (response.success) {
						 $status.html('<strong style="color:green;">' + response.data.message + '</strong>');
					 } else {
						 $status.html('<strong style="color:red;">' + response.data.message + '</strong>');
					 }
				 })
				 .fail(function() {
					 $status.html('<strong style="color:red;">AJAX request failed.</strong>');
				 });
			});
		})(jQuery);
		</script>

		<div class="wrap">
			<h3>Backfill Anniversary “Last” Dates</h3>
			<p>Run this one-time process to populate the new <code>anniversary_date_last</code> field for all staff.</p>
			<button id="run-backfill-anniversary" class="button button-secondary">Run Backfill</button>
			<div style="margin-top:10px;" id="backfill-status"></div>
		</div>

		<script type="text/javascript">
		(function($) {
			$('#run-backfill-anniversary').on('click', function() {
				const $status = $('#backfill-status');
				$status.html('<em>Running backfill, please wait...</em>');
				$.post(ajaxurl, { action: 'run_backfill_anniversary_date_last' })
					.done(response => {
						if (response.success) {
							$status.html('<strong style="color:green;">' + response.data.message + '</strong>');
						} else {
							$status.html('<strong style="color:red;">Error: ' + response.data.message + '</strong>');
						}
					})
					.fail((xhr, textStatus, errorThrown) => {
						$status.html('<strong style="color:red;">Failed: ' + textStatus + '</strong>');
					});
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * Schedule or reschedule the daily staff batch update event (DST-safe and self-adjusting).
	 * Function is called in the main ve-staff class.
	 */
	public function schedule_staff_batch_update_event() {
		$event_hook = 'daily_staff_batch_update_event';
		$desired_time_local = '3:00'; // 3:00 AM local time

		$tz = wp_timezone();
		$now = new DateTime('now', $tz);
		$run = new DateTime("today {$desired_time_local}", $tz);

		if ($run <= $now) {
			$run->modify('+1 day');
		}

		$utc = new DateTimeZone('UTC');
		$run_utc = clone $run;
		$run_utc->setTimezone($utc);
		$first_run_utc_ts = (int) $run_utc->getTimestamp();

		$existing_ts = wp_next_scheduled($event_hook);

		if (!$existing_ts) {
			wp_schedule_event($first_run_utc_ts, 'daily', $event_hook);
			$this->ve_log("🕒 Scheduled {$event_hook} for {$run->format('Y-m-d H:i:s T')} ({$run_utc->format('Y-m-d H:i:s T')} UTC)");
		} elseif (abs($existing_ts - $first_run_utc_ts) > 300) {
			wp_unschedule_event($existing_ts, $event_hook);
			wp_schedule_event($first_run_utc_ts, 'daily', $event_hook);
			$this->ve_log("🔄 Rescheduled {$event_hook} for {$run->format('Y-m-d H:i:s T')} ({$run_utc->format('Y-m-d H:i:s T')} UTC)");
		}
	}

	public function run_daily_staff_batch_update() {
		$batch_size = 50;
		$offset = 0;
		$total_updated_count = 0;

		do {
			$result = $this->run_staff_batch_update($offset, $batch_size);
			$total_updated_count += $result['count'];
			$offset += $batch_size;
		} while ($result['has_more']);

		update_option('staff_batch_update_last_run', current_time('mysql'));
		update_option('staff_batch_update_last_run_results', $total_updated_count);
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// LOG FILE MAINTENANCE (scheduled)
	// ─────────────────────────────────────────────────────────────────────────────

	public function schedule_ve_clear_old_log_entries() {
        if (!wp_next_scheduled('ve_clear_old_log_entries')) {
            wp_schedule_event(time(), 'daily', 've_clear_old_log_entries');
        }
    }

	public function ve_clear_old_log_entries() {
		$days_old = 90;
		$log_file = get_template_directory() . '/inc/ve-staff/logs/unauthorized_requests.txt';
		$debug_log = WP_CONTENT_DIR . '/debug.log';

		if (!file_exists($log_file) && !file_exists($debug_log)) {
			return;
		}

		$current_time = time();
		$cutoff = $days_old * 24 * 60 * 60;

		if (file_exists($log_file)) {
			$log_contents = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			$new_contents = array_filter($log_contents, function($line) use ($current_time, $cutoff) {
				if (preg_match('/^Timestamp: (\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $matches)) {
					$timestamp = strtotime($matches[1]);
					return ($current_time - $timestamp) < $cutoff;
				}
				return false;
			});
			file_put_contents($log_file, implode(PHP_EOL, $new_contents) . PHP_EOL);
		}

		if (file_exists($debug_log)) {
			$debug_contents = file($debug_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			$filtered_debug_contents = array_filter($debug_contents, function($line) use ($current_time, $cutoff) {
				if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
					$timestamp = strtotime($matches[1]);
					return ($current_time - $timestamp) < $cutoff;
				}
				return true;
			});
			file_put_contents($debug_log, implode(PHP_EOL, $filtered_debug_contents) . PHP_EOL);
		}
	}

	/**
	 * Trim staff debug log to keep only recent entries (default 30 days).
	 */
	public function prune_staff_debug_log($days_to_keep = 30) {
		try {
			$upload_dir = wp_upload_dir();
			$log_file = rtrim($upload_dir['basedir'], '/').'/staff/logs/debug.log';
			if (!file_exists($log_file)) {
				return;
			}

			$cutoff = strtotime("-{$days_to_keep} days");
			$lines  = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if (!$lines) return;

			$new_lines = array_filter($lines, function($line) use ($cutoff) {
				if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
					$timestamp = strtotime($matches[1]);
					return $timestamp >= $cutoff;
				}
				return true;
			});

			file_put_contents($log_file, implode(PHP_EOL, $new_lines) . PHP_EOL);
		} catch (Throwable $e) {
			error_log('Failed to prune staff debug log: '.$e->getMessage());
		}
	}

	public function schedule_prune_staff_debug_log() {
		if (!wp_next_scheduled('ve_prune_staff_debug_log')) {
			wp_schedule_event(time(), 'daily', 've_prune_staff_debug_log');
		}
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// SECURITY PAGE + KEY MGMT
	// ─────────────────────────────────────────────────────────────────────────────

	public function staff_get_secure_csv_key() {
		$stored_key = get_option('staff_csv_secure_key');
		if (!$stored_key) {
			$new_key = wp_generate_password(32, false);
			update_option('staff_csv_secure_key', $new_key);
			return $new_key;
		}
		return $stored_key;
	}

	public function staff_register_security_submenu() {
		add_submenu_page(
			've-staff-settings',
			'Staff Security Settings',
			'Security Settings',
			'manage_options',
			've-staff-security',
			array( $this, 'staff_render_security_page' )
		);
	}

	public function staff_render_security_page() {
		$secure_key = $this->staff_get_secure_csv_key();

		?>
		<div class="wrap">
			<h1>Staff Security Settings</h1>
			<p>The secure key below is used to access the CSV files. Ensure it is kept private.</p>
			<div style="margin: 20px 0; padding: 15px; border: 1px solid #ccc; background: #f9f9f9;">
				<strong>Secure Key:</strong>
				<p><code><?php echo esc_html($secure_key); ?></code></p>
			</div>

			<p>Use the following URLs with the secure key:</p>
			<ul>
				<li>
					<strong>All Staff CSV:</strong>
					<code><?php echo esc_url(site_url('?csv=staff_directory&key=' . $secure_key)); ?></code>
				</li>
				<li>
					<strong>Updated Staff CSV:</strong>
					<code><?php echo esc_url(site_url('?csv=staff_directory_updates&key=' . $secure_key)); ?></code>
				</li>
			</ul>

			<div id="csv-generation-status"></div>

			<h2>On-Demand CSV Update</h2>
			<p>Click below to regenerate the CSV files immediately.</p>
			<button id="generate-csv-files" class="button button-primary">Regenerate CSV Files</button>
			<h2>Reset Secure Key</h2>
			<p>
				<a href="<?php echo esc_url(admin_url('admin.php?page=ve-staff-security&action=reset_key')); ?>"
				   class="button button-secondary"
				   onclick="return confirm('Are you sure you want to reset the secure key? This will invalidate all current URLs.');">
					Reset Secure Key
				</a>
			</p>
		</div>

		<script type="text/javascript">
			(function($) {
				$('#generate-csv-files').on('click', function() {
					const $status = $('#csv-generation-status');
					$status.html('<p><strong>Processing:</strong> Generating CSV files...</p>');
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						dataType: 'json',
						data: {
							action: 'generate_staff_csv',
							_ajax_nonce: '<?php echo wp_create_nonce('generate_staff_csv'); ?>',
						},
						success: function(response) {
							if (response.success) {
								$status.html('<p style="color: green;"><strong>Success:</strong> ' + response.data.message + '</p>');
							} else {
								$status.html('<p style="color: red;"><strong>Error:</strong> ' + response.data.message + '</p>');
							}
						},
						error: function(jqXHR, textStatus, errorThrown) {
							$status.html('<p style="color: red;"><strong>Error:</strong> An unexpected error occurred.</p>');
						},
					});
				});
			})(jQuery);
		</script>
		<?php
	}

	public function staff_reset_secure_csv_key() {
		if (!current_user_can('manage_options')) {
			wp_die('Unauthorized', 'Error', ['response' => 403]);
		}

		$new_key = wp_generate_password(32, false);
		update_option('staff_csv_secure_key', $new_key);

		wp_redirect(admin_url('admin.php?page=ve-staff-security&reset=success'));
		exit;
	}

	// ─────────────────────────────────────────────────────────────────────────────
	// STAFF EXPORT CSV GENERATION + DAILY RUN
	// ─────────────────────────────────────────────────────────────────────────────

	public function staff_get_location_data($location_id) {
		$location_data = [];
		$address = get_field('location_address', 'location_' . $location_id);

		$location_data['street_address'] = $address['street_address'] ?? '';
		$location_data['city'] = $address['city'] ?? '';
		$location_data['state'] = $address['state'] ?? '';
		$location_data['postal_code'] = $address['postal_code'] ?? '';
		$location_data['country'] = $address['country'] ?? '';

		$location_data['website'] = get_field('location_website', 'location_' . $location_id) ?? '';

		return $location_data;
	}

	public function staff_generate_csv($type = 'full') {
		$upload_dir = wp_upload_dir();
		$csv_dir = $upload_dir['basedir'] . '/staff/csv';
		$filename = $type === 'updates' ? 'staff_directory_updates.csv' : 'staff_directory.csv';
		$csv_file_path = $csv_dir . '/' . $filename;

		if (!file_exists($csv_dir) && !wp_mkdir_p($csv_dir)) {
			$this->ve_log('Failed to create CSV directory: ' . $csv_dir);
			return false;
		}

		$file = fopen($csv_file_path, 'w');
		if (!$file) {
			$this->ve_log('Failed to open CSV file for writing: ' . $csv_file_path);
			return false;
		}

		$header = [
			'First Name', 'Last Name', 'Work Email', 'Mobile Phone', 'Work Phone',
			'Organization', 'Department', 'Title', 'Street Address', 'City', 'State',
			'Postal Code', 'Country', 'Website', 'Large Photo URL', 'Medium Photo URL'
		];
		fputcsv($file, $header);

		$args = [
			'post_type' => 'staff',
			'post_status' => 'publish',
			'posts_per_page' => -1,
		];

		if ($type === 'updates') {
			$args['date_query'] = [
				[
					'column' => 'post_modified',
					'after' => '1 day ago',
				],
			];
		}

		$staff_query = new WP_Query($args);

		if (!$staff_query->have_posts()) {
			$this->ve_log('No staff posts found for CSV generation. Query Args: ' . print_r($args, true));
			fclose($file);
			return false;
		}

		$this->ve_log('Found ' . $staff_query->found_posts . ' posts for CSV generation.');

		$row_count = 0;
		while ($staff_query->have_posts()) {
			$staff_query->the_post();
			$post_id = get_the_ID();

			$first_name = get_field('first_name', $post_id) ?: 'N/A';
			$last_name = get_field('last_name', $post_id) ?: 'N/A';

			$titleterm = get_field('title', $post_id);
			$title = 'N/A';
			if ($titleterm && !is_wp_error($titleterm)) {
				if ($titleterm instanceof WP_Term) {
					$title = $titleterm->name;
				} elseif (is_array($titleterm) && isset($titleterm[0]['name'])) {
					$title = $titleterm[0]['name'];
				} elseif (is_numeric($titleterm)) {
					$t = get_term($titleterm);
					if ($t && !is_wp_error($t)) $title = $t->name;
				} elseif (is_string($titleterm)) {
					$title = $titleterm;
				}
			}

			$office_contact_info = get_field('office_contact_info', $post_id) ?: [];
			$office_email = $office_contact_info['office_email'] ?? '';
			$office_cell_phone = $office_contact_info['office_cell_phone'] ?? '';
			$mobile_phone = $this->format_phone_number($office_cell_phone);
			$office_phone_prefix = $office_contact_info['office_phone_prefix'] ?? '';
			$office_extension = $office_contact_info['office_extension'] ?? '';
			$office_other_direct = $office_contact_info['office_other_direct'] ?? '';
			$work_phone = $office_other_direct ? $this->format_phone_number($office_other_direct) : $this->format_phone_number($office_phone_prefix . $office_extension);

			$location = get_field('primary_location', $post_id);
			if ($location instanceof WP_Term) {
				$location_name = $location->name;
				$location_id = $location->term_id;
			} elseif (is_numeric($location)) {
				$term = get_term($location);
				$location_name = $term && !is_wp_error($term) ? $term->name : 'N/A';
				$location_id = $term ? $term->term_id : null;
			} else {
				$location_name = 'N/A';
				$location_id = null;
			}

			$location_data = $location_id ? $this->staff_get_location_data($location_id) : [];
			$street_address = $location_data['street_address'] ?? '';
			$city = $location_data['city'] ?? '';
			$state = $location_data['state'] ?? '';
			$postal_code = $location_data['postal_code'] ?? '';
			$country = $location_data['country'] ?? '';
			$website = $location_data['website'] ?? '';

			$department = get_field('department', $post_id);
			if ($department instanceof WP_Term) {
				$department_name = $department->name;
			} elseif (is_numeric($department)) {
				$term = get_term($department);
				$department_name = $term && !is_wp_error($term) ? $term->name : 'N/A';
			} else {
				$department_name = 'N/A';
			}

			$photo = get_field('photo', $post_id);
			$large_photo_url = $photo['sizes']['large'] ?? '';
			$medium_photo_url = $photo['sizes']['medium'] ?? '';

			$row = [
				$first_name,
				$last_name,
				$office_email,
				$mobile_phone,
				$work_phone,
				$location_name,
				$department_name,
				$title,
				$street_address,
				$city,
				$state,
				$postal_code,
				$country,
				$website,
				$large_photo_url,
				$medium_photo_url,
			];
			fputcsv($file, $row);
			$row_count++;
		}

		$this->ve_log('Total rows written to CSV: ' . $row_count);
		fclose($file);
		wp_reset_postdata();

		$this->ve_log('CSV file generated successfully at: ' . $csv_file_path);
		return $csv_file_path;
	}

	// Helper: clean and format phone numbers
	public function format_phone_number($phone) {
		$cleaned = preg_replace('/\D/', '', $phone);
		return (strlen($cleaned) >= 10) ? '+1' . $cleaned : '';
	}

	public function ajax_generate_staff_csv_files() {
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => 'Unauthorized access.'], 403);
		}

		if (!isset($_POST['_ajax_nonce']) || !wp_verify_nonce($_POST['_ajax_nonce'], 'generate_staff_csv')) {
			wp_send_json_error(['message' => 'Invalid nonce.'], 403);
		}

		try {
			$full_csv_path    = $this->staff_generate_csv('full');
			$updates_csv_path = $this->staff_generate_csv('updates');

			$this->ve_log('Full CSV Path: ' . $full_csv_path);
			$this->ve_log('Updates CSV Path: ' . $updates_csv_path);

			wp_send_json_success([
				'message' => 'CSV files generated successfully.',
				'full_csv' => $full_csv_path,
				'updates_csv' => $updates_csv_path,
			]);
		} catch (Throwable $e) {
			$this->ve_log('AJAX CSV Generation Error: ' . $e->getMessage());
			wp_send_json_error(['message' => 'Error generating CSV: ' . $e->getMessage()]);
		}
	}

	public function handle_staff_post_update($post_id, $post, $update) {
		if ($post->post_type === 'staff' && $update && !wp_is_post_autosave($post_id) && !wp_is_post_revision($post_id)) {
			if (!wp_next_scheduled('ve_staff_generate_csv_async')) {
				wp_schedule_single_event(time() + 30, 've_staff_generate_csv_async');
			}
		}
	}

	public function process_scheduled_staff_csv_generation() {
		$this->staff_generate_csv('full');
		$this->staff_generate_csv('updates');
	}

	public function staff_handle_csv_request() {
		if (isset($_GET['csv'])) {
			$secure_key = $_GET['key'] ?? '';
			$csv_type = sanitize_text_field($_GET['csv']);

			if ($secure_key !== $this->staff_get_secure_csv_key()) {
				wp_die('Unauthorized access. Invalid secure key.', 'Error', ['response' => 403]);
			}

			$upload_dir = wp_upload_dir();
			$csv_dir = $upload_dir['basedir'] . '/staff/csv';
			$filename = $csv_type === 'staff_directory_updates' ? 'staff_directory_updates.csv' : 'staff_directory.csv';
			$csv_file_path = $csv_dir . '/' . $filename;

			if (!file_exists($csv_file_path)) {
				wp_die('The requested file does not exist.', 'Error', ['response' => 404]);
			}

			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			readfile($csv_file_path);
			exit;
		}
	}

	public function schedule_staff_csv_updates() {
		if (!wp_next_scheduled('staff_generate_daily_updates_csv')) {
			wp_schedule_event(time(), 'daily', 'staff_generate_daily_updates_csv');
		}
	}

	public function generate_daily_updates_csv() {
		$this->staff_generate_csv('updates');
	}

} // END ADMIN CLASS


/**
 * WRITE TO DEBUG LOG FUNCTION
 */
if ( ! function_exists('write_log')) {
   function write_log ( $log )  {
	  if ( is_array( $log ) || is_object( $log ) ) {
		 error_log( print_r( $log, true ) );
	  } else {
		 error_log( $log );
	  }
   }
}
