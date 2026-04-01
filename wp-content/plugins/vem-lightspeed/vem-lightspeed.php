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
 * @package           Vem_Lightspeed
 *
 * @wordpress-plugin
 * Plugin Name:       VEM - Lightspeed Evo
 * Plugin URI:        https://www.verneidemarketing.com
 * Description:       Custom plugin to integrate CDK Lightspeed Evo Api with WordPress for various uses including inventory, WooCommerce and more.
 * Version:           1.0.0
 * Author:            Chase Sabers
 * Author URI:        https://www.sabers-design.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       vem-lightspeed
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
define( 'VEM_LIGHTSPEED_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-vem-lightspeed-activator.php
 */
function activate_vem_lightspeed() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-vem-lightspeed-activator.php';
	Vem_Lightspeed_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-vem-lightspeed-deactivator.php
 */
function deactivate_vem_lightspeed() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-vem-lightspeed-deactivator.php';
	Vem_Lightspeed_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_vem_lightspeed' );
register_deactivation_hook( __FILE__, 'deactivate_vem_lightspeed' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-vem-lightspeed.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_vem_lightspeed() {

	$plugin = new Vem_Lightspeed();
	$plugin->run();

}
run_vem_lightspeed();


/*** CRON JOBS FOR IMPORTS ****/
add_action('vem_lightspeed_cron_update_recent_parts', 'vem_lightspeed_run_cron_update_recent_parts');

add_action('vem_lightspeed_cron_update_recent_parts_9am', 'vem_lightspeed_run_cron_update_recent_parts');
add_action('vem_lightspeed_cron_update_recent_parts_1pm', 'vem_lightspeed_run_cron_update_recent_parts');
add_action('vem_lightspeed_cron_update_recent_parts_7pm', 'vem_lightspeed_run_cron_update_recent_parts');

function vem_lightspeed_run_cron_update_recent_parts() {
	if (class_exists('Vem_Lightspeed_Parts')) {
		$instance = new Vem_Lightspeed_Parts();
		$instance->update_recent_parts();
	}
}
