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
 * @package           Vem_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       VEM - WooCommerce Mods
 * Plugin URI:        https://www.verneide.com
 * Description:       Custom WooCommerce Modifications and Add-Ons for Vern Eide e-commerce sites.
 * Version:           1.0.0
 * Author:            Chase Sabers
 * Author URI:        https://www.sabers-design.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       vem-woocommerce
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
define( 'VEM_WOOCOMMERCE_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-vem-woocommerce-activator.php
 */
function activate_vem_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-vem-woocommerce-activator.php';
	Vem_Woocommerce_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-vem-woocommerce-deactivator.php
 */
function deactivate_vem_woocommerce() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-vem-woocommerce-deactivator.php';
	Vem_Woocommerce_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_vem_woocommerce' );
register_deactivation_hook( __FILE__, 'deactivate_vem_woocommerce' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-vem-woocommerce.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_vem_woocommerce() {

	$plugin = new Vem_Woocommerce();
	$plugin->run();

}
run_vem_woocommerce();
