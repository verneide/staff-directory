<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.sabers-design.com
 * @since             1.0.0
 * @package           Ve_Staff
 *
 * @wordpress-plugin
 * Plugin Name:       Vern Eide Staff
 * Plugin URI:        https://www.verneide.com
 * Description:       Custom plugin for Vern Eide to create a staff listing site.
 * Version:           1.0.0
 * Author:            Chase Sabers
 * Author URI:        https://www.sabers-design.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ve-staff
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'VE_STAFF_VERSION', '1.0.0' );

/**
 * Define Plugin Constants
 */
define( 'VE_STAFF_PLUGIN_URL', plugin_dir_url(__FILE__) );
define( 'VE_STAFF_PLUGIN_DIR', plugin_dir_path(__FILE__) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-ve-staff-activator.php
 */
function activate_ve_staff() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ve-staff-activator.php';
	Ve_Staff_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-ve-staff-deactivator.php
 */
function deactivate_ve_staff() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ve-staff-deactivator.php';
	Ve_Staff_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_ve_staff' );
register_deactivation_hook( __FILE__, 'deactivate_ve_staff' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-ve-staff.php';

/**
* Load background process library
*/ 
if ( ! class_exists( 'WP_Async_Request' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/wp-background-processing/wp-async-request.php';
}

if ( ! class_exists( 'WP_Background_Process' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/wp-background-processing/wp-background-process.php';
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_ve_staff() {

	$ve_staff = new Ve_Staff();
	$ve_staff->run();

}
run_ve_staff();

/**
 * ------------------------------------------------------------
 * Staff Batch Update Cron Scheduling
 * ------------------------------------------------------------
 * Ensures the daily staff update runs at 3:00 AM local time.
 * Runs only on plugin activation or when options are saved.
 */

// Schedule event on plugin activation
register_activation_hook(__FILE__, function() {
    $admin = new Ve_Staff_Admin('ve-staff', VE_STAFF_VERSION);
    $admin->schedule_staff_batch_update_event();
});

// Re-run scheduling when saving ACF options page
add_action('acf/save_post', function($post_id) {
    if ($post_id === 'options') {
        $admin = new Ve_Staff_Admin('ve-staff', VE_STAFF_VERSION);
        $admin->schedule_staff_batch_update_event();
    }
}, 20);

// Clean up on plugin deactivation
register_deactivation_hook(__FILE__, function() {
    $timestamp = wp_next_scheduled('daily_staff_batch_update_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'daily_staff_batch_update_event');
    }
});
