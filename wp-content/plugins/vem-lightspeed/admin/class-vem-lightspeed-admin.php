<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.sabers-design.com
 * @since      1.0.0
 *
 * @package    Vem_Lightspeed
 * @subpackage Vem_Lightspeed/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Vem_Lightspeed
 * @subpackage Vem_Lightspeed/admin
 * @author     Chase Sabers <marketing@verneide.com>
 */
class Vem_Lightspeed_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	
	private $parts_manager;
	
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		/* Parts 3PA Integration */
		
		require_once plugin_dir_path(__FILE__) . 'partials/vem-lightspeed-parts.php';
		$this->lightspeed_parts = new Vem_Lightspeed_Parts();

		add_action('vem_lightspeed_cron_update_all_parts', [$this, 'run_full_import_cron']);
		
		/* ADMIN PAGE STYLES */
		$this->maybe_add_admin_styles();
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Vem_Lightspeed_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Vem_Lightspeed_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/vem-lightspeed-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Vem_Lightspeed_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Vem_Lightspeed_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/vem-lightspeed-admin.js', array( 'jquery' ), $this->version, false );

	}
	
	/***
	 ** ADMIN PAGE STYLES **
	 */
	private function maybe_add_admin_styles() {
		add_action('admin_head', [$this, 'render_admin_styles']);
	}

	public function render_admin_styles() {
		if (isset($_GET['page']) && $_GET['page'] === 'vem_lightspeed_logs') {
			echo '<style>
				.badge {
					display: inline-block;
					padding: 0.25em 0.5em;
					font-size: 12px;
					font-weight: 600;
					line-height: 1;
					text-align: center;
					white-space: nowrap;
					vertical-align: baseline;
					border-radius: 0.25rem;
					color: #fff;
				}
				.badge-success   { background-color: #28a745; }
				.badge-danger    { background-color: #dc3545; }
				.badge-secondary { background-color: #6c757d; }
				.badge-light     { background-color: #d6d8db; color: #000; }

				.wp-list-table.logs .column-status {
					width: 100px;
					white-space: nowrap;
				}
				.wp-list-table.logs .column-records_updated,
				.wp-list-table.logs .column-records_added {
					width: 65px;
					text-align: right;
					white-space: nowrap;
				}
				.wp-list-table.logs .column-message {
					width: 45%;
				}
				.wp-list-table.logs td.column-message pre {
					max-width: 100%;
					white-space: pre-wrap;
					word-break: break-word;
					margin: 2px 0;
				}
			</style>';
		}
	}

}


