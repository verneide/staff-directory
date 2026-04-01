<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://www.sabers-design.com
 * @since      1.0.0
 *
 * @package    Vem_Woocommerce
 * @subpackage Vem_Woocommerce/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Vem_Woocommerce
 * @subpackage Vem_Woocommerce/public
 * @author     Chase Sabers <chase@sabers-design.com>
 */
class Vem_Woocommerce_Public {

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
		
		/** WooCommerce Products **/
		add_filter('woocommerce_product_tabs', [$this, 'add_custom_product_tabs']);

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
		 * defined in Vem_Woocommerce_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Vem_Woocommerce_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/vem-woocommerce-public.css', array(), $this->version, 'all' );

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
		 * defined in Vem_Woocommerce_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Vem_Woocommerce_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/vem-woocommerce-public.js', array( 'jquery' ), $this->version, false );

	}
	
	/** WooCommerce Custom Products Tabs **/
	
	public function add_custom_product_tabs($tabs) {
		// Add Overview & Specs Tab
		if ($content = get_field('product_overview_specs')) {
			$tabs['overview_specs'] = array(
				'title'    => __('Overview & Specs', 'your-textdomain'),
				'priority' => 11,
				'callback' => [$this, 'product_overview_specs_content'],
			);
		}

		// Add Sizing & Fit Tab
		if ($content = get_field('product_size_fit')) {
			$tabs['sizing_fit'] = array(
				'title'    => __('Sizing & Fit', 'your-textdomain'),
				'priority' => 15,
				'callback' => [$this, 'product_size_fit_content'],
			);
		}

		// Add Compatibility Tab
		if ($content = get_field('product_design_fit')) {
			$tabs['compatibility'] = array(
				'title'    => __('Compatibility', 'your-textdomain'),
				'priority' => 19,
				'callback' => [$this, 'product_design_fit_content'],
			);
		}

		return $tabs;
	}

	public function product_overview_specs_content() {
		$content = get_field('product_overview_specs');
		echo '<div class="woocommerce-tab-content">' . wp_kses_post($content) . '</div>';
	}

	public function product_size_fit_content() {
		$content = get_field('product_size_fit');
		echo '<div class="woocommerce-tab-content">' . wp_kses_post($content) . '</div>';
	}

	public function product_design_fit_content() {
		$content = get_field('product_design_fit');
		echo '<div class="woocommerce-tab-content">' . wp_kses_post($content) . '</div>';
	}

}
