<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://www.sabers-design.com
 * @since      1.0.0
 *
 * @package    Vem_Lightspeed
 * @subpackage Vem_Lightspeed/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Vem_Lightspeed
 * @subpackage Vem_Lightspeed/includes
 * @author     Chase Sabers <marketing@verneide.com>
 */
class Vem_Lightspeed_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook('vem_lightspeed_cron_update_recent_parts_9am');
		wp_clear_scheduled_hook('vem_lightspeed_cron_update_recent_parts_1pm');
		wp_clear_scheduled_hook('vem_lightspeed_cron_update_recent_parts_7pm');
	}

}
