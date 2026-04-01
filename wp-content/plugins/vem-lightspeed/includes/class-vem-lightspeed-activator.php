<?php

/**
 * Fired during plugin activation
 *
 * @link       https://www.sabers-design.com
 * @since      1.0.0
 *
 * @package    Vem_Lightspeed
 * @subpackage Vem_Lightspeed/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Vem_Lightspeed
 * @subpackage Vem_Lightspeed/includes
 * @author     Chase Sabers <marketing@verneide.com>
 */
class Vem_Lightspeed_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		require_once plugin_dir_path(__FILE__) . '../admin/partials/vem-lightspeed-parts.php';

		if (class_exists('Vem_Lightspeed_Parts')) {
			$parts = new Vem_Lightspeed_Parts();
			$parts->create_table();
		}
		
		if (!wp_next_scheduled('vem_lightspeed_cron_update_recent_parts_9am')) {
			wp_schedule_event(strtotime('09:00:00'), 'daily', 'vem_lightspeed_cron_update_recent_parts_9am');
		}
		if (!wp_next_scheduled('vem_lightspeed_cron_update_recent_parts_1pm')) {
			wp_schedule_event(strtotime('13:00:00'), 'daily', 'vem_lightspeed_cron_update_recent_parts_1pm');
		}
		if (!wp_next_scheduled('vem_lightspeed_cron_update_recent_parts_7pm')) {
			wp_schedule_event(strtotime('19:00:00'), 'daily', 'vem_lightspeed_cron_update_recent_parts_7pm');
		}
	}

}
