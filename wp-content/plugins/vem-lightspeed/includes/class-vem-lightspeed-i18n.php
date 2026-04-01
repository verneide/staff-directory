<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://www.sabers-design.com
 * @since      1.0.0
 *
 * @package    Vem_Lightspeed
 * @subpackage Vem_Lightspeed/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Vem_Lightspeed
 * @subpackage Vem_Lightspeed/includes
 * @author     Chase Sabers <marketing@verneide.com>
 */
class Vem_Lightspeed_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'vem-lightspeed',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
