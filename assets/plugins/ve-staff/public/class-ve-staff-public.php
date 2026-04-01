<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.sabers-design.com
 * @since      1.0.0
 *
 * @package    Ve_Staff
 * @subpackage Ve_Staff/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Ve_Staff
 * @subpackage Ve_Staff/public
 * @author     Chase Sabers <csabers@verneide.com>
 */
class Ve_Staff_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		// Enqueue & Dequeue Scripts & Styles
		add_action('wp_enqueue_scripts', array( $this, 've_staff_dequeue_scripts' ), 100);
		add_action('wp_footer', array( $this, 'log_enqueued_assets_for_admins' ),999);
		
		// Plugin Public Partials
		$this->publicPartials();

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Ve_Staff_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Ve_Staff_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/ve-staff-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Ve_Staff_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Ve_Staff_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/ve-staff-public.js', array( 'jquery' ), $this->version, false );

		if(!is_user_logged_in()){
			wp_enqueue_script( $this->plugin_name.'-security', plugin_dir_url( __FILE__ ) . 'js/ve-security.js', array(), $this->version, false );
		}

	}
	
	/**
	 * Register public partial files.
	 *
	 * @since    1.0.0
	 */
	public function publicPartials() {

        // Require each PHP file in the partials folder

		$public_partials = array(
			'/ve-staff-public-functions.php',
			'/ve-staff-public-api.php'
		);

		foreach ( $public_partials as $file ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/partials/' . $file;
		}

	}
	
	/**
	 * Dequeue Scripts & Styles for certain post types
	 *
	 * @since    1.0.0
	 */
	public function ve_staff_dequeue_scripts(){
		if (is_singular('display')) {
			if($_GET['debug'] == true){
				console_log('Dequeueing Scripts & Styles');
			}
			// Dequeue scripts
			wp_dequeue_script('wp-bootstrap-starter-themejs');
			wp_dequeue_script('wp-bootstrap-starter-skip-link-focus-fix');
			wp_dequeue_script('wpsms-intel-tel-input');
			wp_dequeue_script('wpsms-intel-script');
			wp_dequeue_script('wp-sms-blocks-script');

			// Dequeue styles
			wp_dequeue_style('global-styles');
			wp_dequeue_style('wp-emoji-styles');
			wp_dequeue_style('wp-block-library');
			wp_dequeue_style('classic-theme-styles');
			wp_dequeue_style('wpsms-front');
			wp_dequeue_style('wpsms-intel-tel-input');
			wp_dequeue_style('core-block-supports-duotone');
			wp_dequeue_style('wp-bootstrap-starter-style');
			wp_dequeue_style('wp-bootstrap-starter-montserrat-opensans-font');
			wp_dequeue_style('wp-bootstrap-starter-montserrat-opensans');
		}
	}
	
	public function log_enqueued_assets_for_admins() {
		// Check if user is logged in and is an administrator
		if ( is_user_logged_in() && current_user_can('administrator') && $_GET['debug'] == true) {
			global $wp_scripts, $wp_styles;

			echo "<script>\n";
			echo "console.log('Enqueued Scripts:');\n";
			foreach ( $wp_scripts->queue as $handle ) {
				echo "console.log('" . $handle . "');\n";
			}

			echo "console.log('Enqueued Styles:');\n";
			foreach ( $wp_styles->queue as $handle ) {
				echo "console.log('" . $handle . "');\n";
			}
			echo "</script>\n";
		}
	}
	
}

function phoneFormat($number) {
	if(ctype_digit($number) && strlen($number) == 10) {
  	$number = substr($number, 0, 3) .'-'. substr($number, 3, 3) .'-'. substr($number, 6);
	} else {
		if(ctype_digit($number) && strlen($number) == 7) {
			$number = substr($number, 0, 3) .'-'. substr($number, 3, 4);
		}
	}
	return $number;
}
