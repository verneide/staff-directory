<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://www.sabers-design.com
 * @since      1.0.0
 *
 * @package    Vem_Woocommerce
 * @subpackage Vem_Woocommerce/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Vem_Woocommerce
 * @subpackage Vem_Woocommerce/admin
 * @author     Chase Sabers <chase@sabers-design.com>
 */
class Vem_Woocommerce_Admin {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add hooks for simple and variable product support
        add_action( 'woocommerce_product_options_inventory_product_data', [ $this, 'add_secondary_sku_field' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_secondary_sku_field' ] );

        // Hooks for variable product variations
        add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'add_secondary_sku_to_variations' ], 10, 3 );
        add_action( 'woocommerce_save_product_variation', [ $this, 'save_secondary_sku_for_variations' ], 10, 2 );

        // Display on frontend
        add_action( 'woocommerce_single_product_summary', [ $this, 'display_secondary_sku_on_frontend' ], 25 );
		
		// Hooks for WP All Import for WooCommerce Products
		add_action('pmxi_saved_post', [$this, 'handleProductImageGallery'], 10, 3);
		
		// Hook into WordPress Menu Actions & Filters
        add_action('wp_nav_menu_item_custom_fields', [$this, 'add_custom_menu_field'], 10, 4);
        add_action('wp_update_nav_menu_item', [$this, 'save_custom_menu_field'], 10, 3);
        add_filter('walker_nav_menu_start_el', [$this, 'add_custom_menu_attribute'], 10, 4);
		
		// Trigger sync when menus are saved
        add_action('wp_update_nav_menu', [$this, 'sync_menu_with_child_categories']);

        // Trigger sync when a product category is added, updated, or deleted
        add_action('edited_product_cat', [$this, 'sync_menu_with_child_categories_on_taxonomy_change']);
        add_action('create_product_cat', [$this, 'sync_menu_with_child_categories_on_taxonomy_change']);
        add_action('delete_product_cat', [$this, 'sync_menu_with_child_categories_on_taxonomy_change']);
    }

    /**
     * Add a Secondary SKU field under the Inventory Tab for simple products.
     */
    public function add_secondary_sku_field() {
        echo '<div class="options_group">';
        
        woocommerce_wp_text_input( array(
            'id'          => 'secondary_sku',
            'label'       => __( 'Manufacturer SKU', 'woocommerce' ),
            'desc_tip'    => 'true',
            'description' => __( 'Enter the manufacturer SKU for this product.', 'woocommerce' ),
        ) );

        echo '</div>';
    }

    /**
     * Save the Secondary SKU field value for simple products.
     *
     * @param int $post_id The ID of the product being saved.
     */
    public function save_secondary_sku_field( $post_id ) {
        $secondary_sku = isset( $_POST['secondary_sku'] ) ? sanitize_text_field( $_POST['secondary_sku'] ) : '';
        update_post_meta( $post_id, 'secondary_sku', $secondary_sku );
    }

    /**
     * Add a Secondary SKU field to each variation under the GTIN/UPC field.
     *
     * @param int $loop The loop index for variations.
     * @param array $variation_data The variation data array.
     * @param WP_Post $variation The variation post object.
     */
    public function add_secondary_sku_to_variations( $loop, $variation_data, $variation ) {
        woocommerce_wp_text_input( array(
            'id'          => "variable_secondary_sku[{$loop}]",
            'label'       => __( 'Manufacturer SKU', 'woocommerce' ),
            'desc_tip'    => 'true',
            'description' => __( 'Enter the manufacturer SKU for this variation.', 'woocommerce' ),
            'value'       => get_post_meta( $variation->ID, 'variable_secondary_sku', true ),
            'wrapper_class' => 'form-row form-field variable_secondary_sku_field',
        ) );
    }

    /**
     * Save the Secondary SKU field value for each variation.
     *
     * @param int $variation_id The ID of the variation being saved.
     * @param int $i The loop index for variations.
     */
    public function save_secondary_sku_for_variations( $variation_id, $i ) {
        if ( isset( $_POST['variable_secondary_sku'][ $i ] ) ) {
            $secondary_sku = sanitize_text_field( $_POST['variable_secondary_sku'][ $i ] );
            update_post_meta( $variation_id, 'variable_secondary_sku', $secondary_sku );
        }
    }

    /**
     * Display the Secondary SKU on the product page frontend.
     */
    public function display_secondary_sku_on_frontend() {
        global $post;

        $secondary_sku = get_post_meta( $post->ID, 'secondary_sku', true );
        if ( ! empty( $secondary_sku ) ) {
            echo '<p class="secondary-sku">' . esc_html__( 'Manufacturer SKU: ', 'woocommerce' ) . esc_html( $secondary_sku ) . '</p>';
        }

        if ( is_product() && $post->post_type === 'product' ) {
            global $product;

            if ( $product->is_type( 'variable' ) ) {
                foreach ( $product->get_available_variations() as $variation ) {
                    $variation_secondary_sku = get_post_meta( $variation['variation_id'], 'variable_secondary_sku', true );
                    if ( ! empty( $variation_secondary_sku ) ) {
                        echo '<p class="variation-secondary-sku">' . esc_html__( 'Variation Manufacturer SKU: ', 'woocommerce' ) . esc_html( $variation_secondary_sku ) . '</p>';
                    }
                }
            }
        }
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/vem-woocommerce-admin.css', array(), $this->version, 'all' );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/vem-woocommerce-admin.js', array( 'jquery' ), $this->version, false );
    }
	
	/** WP All Import Functions **/
	/**
     * Public function to handle WooCommerce product image gallery updates
     *
     * @param int $post_id The ID of the saved post.
     * @param array $xml The XML data of the imported post.
     * @param int $import_id The ID of the import process.
     */
    public function handleProductImageGallery($post_id, $xml, $import_id) {
        // Check if this is a WooCommerce product import
        if (get_post_type($post_id) === 'product') {
            $image_ids = get_post_meta($post_id, '_product_image_gallery_temp', true);

            if (!empty($image_ids)) {
                // Update the _product_image_gallery meta field with the image IDs
                update_post_meta($post_id, '_product_image_gallery', $image_ids);

                // Remove the temporary meta field
                delete_post_meta($post_id, '_product_image_gallery_temp');
            }
        }
    }
	
	/** Navigation Menu Functions **/
	/**
     * Add a custom field to the menu editor.
     */
    /**
     * Add custom fields for data-category and excluded child category IDs.
     */
    public function add_custom_menu_field($item_id, $item, $depth, $args) {
        // Add field for the "data-category" attribute
        $data_category = get_post_meta($item_id, '_menu_item_data_category', true);

        // Add field for excluding child categories by ID
        $exclude_child_categories = get_post_meta($item_id, '_menu_item_exclude_child_categories', true);
        ?>
        <p class="field-custom description description-wide">
            <label for="edit-menu-item-data-category-<?php echo $item_id; ?>">
                Data Category (Woo Product Cat ID Child Categories will be Auto-Added)<br>
                <input type="text" id="edit-menu-item-data-category-<?php echo $item_id; ?>" class="widefat code edit-menu-item-custom" name="menu-item-data-category[<?php echo $item_id; ?>]" value="<?php echo esc_attr($data_category); ?>" />
            </label>
        </p>
        <p class="field-custom description description-wide">
            <label for="edit-menu-item-exclude-child-categories-<?php echo $item_id; ?>">
                Exclude Auto-Adding Child Categories (IDs, Comma-Separated)<br>
                <input type="text" id="edit-menu-item-exclude-child-categories-<?php echo $item_id; ?>" class="widefat code edit-menu-item-custom" name="menu-item-exclude-child-categories[<?php echo $item_id; ?>]" value="<?php echo esc_attr($exclude_child_categories); ?>" />
            </label>
        </p>
        <?php
    }

    /**
     * Save the custom field value when the menu is updated.
     */
    public function save_custom_menu_field($menu_id, $menu_item_db_id, $args) {
        // Save data-category field
        if (isset($_POST['menu-item-data-category'][$menu_item_db_id])) {
            $data_category = sanitize_text_field($_POST['menu-item-data-category'][$menu_item_db_id]);

            if (empty($data_category)) {
                delete_post_meta($menu_item_db_id, '_menu_item_data_category');
            } elseif (term_exists((int)$data_category, 'product_cat')) {
                update_post_meta($menu_item_db_id, '_menu_item_data_category', $data_category);
            } else {
                add_action('admin_notices', function () {
                    echo '<div class="error"><p>Invalid Product Category ID entered. Please check and try again.</p></div>';
                });
                delete_post_meta($menu_item_db_id, '_menu_item_data_category');
            }
        } else {
            delete_post_meta($menu_item_db_id, '_menu_item_data_category');
        }

        // Save exclude-child-categories field
        if (isset($_POST['menu-item-exclude-child-categories'][$menu_item_db_id])) {
            $exclude_child_categories = sanitize_text_field($_POST['menu-item-exclude-child-categories'][$menu_item_db_id]);
            update_post_meta($menu_item_db_id, '_menu_item_exclude_child_categories', $exclude_child_categories);
        } else {
            delete_post_meta($menu_item_db_id, '_menu_item_exclude_child_categories');
        }
    }

    /**
     * Add the custom attribute to the frontend menu output.
     */
    public function add_custom_menu_attribute($item_output, $item, $depth, $args) {
        $data_category = get_post_meta($item->ID, '_menu_item_data_category', true);
        if ($data_category) {
            $item_output = str_replace('<a ', '<a data-category="' . esc_attr($data_category) . '" ', $item_output);
        }
        return $item_output;
    }

	/**
     * Sync menus with child categories on menu save or taxonomy change.
     */
    public function sync_menu_with_child_categories() {
        $menus = wp_get_nav_menus();

        foreach ($menus as $menu) {
            $menu_items = wp_get_nav_menu_items($menu->term_id);

            foreach ($menu_items as $item) {
                $data_category = get_post_meta($item->ID, '_menu_item_data_category', true);
                $excluded_ids = get_post_meta($item->ID, '_menu_item_exclude_child_categories', true);

                if ($data_category) {
                    $excluded_ids = $this->parse_excluded_ids($excluded_ids);
                    $this->update_child_menu_items($menu->term_id, $item, $data_category, $excluded_ids);
                }
            }
        }
    }
	
	/**
     * Trigger menu sync for specific taxonomy changes.
     */
    public function sync_menu_with_child_categories_on_taxonomy_change($term_id) {
        // Check if the term has a parent category and sync menus
        $term = get_term($term_id, 'product_cat');
        if ($term && $term->parent) {
            $this->sync_menu_with_child_categories();
        }
    }

    /**
     * Parse excluded category IDs from a comma-separated string.
     */
    private function parse_excluded_ids($excluded_ids) {
        return $excluded_ids ? array_map('intval', explode(',', $excluded_ids)) : [];
    }

    /**
     * Update child menu items under a parent menu item.
     */
    private function update_child_menu_items($menu_id, $parent_item, $parent_category_id, $excluded_ids) {
        // The logic remains the same as in the previous implementation
        $child_categories = get_terms([
            'taxonomy' => 'product_cat',
            'parent'   => (int)$parent_category_id,
            'hide_empty' => true,
            'exclude' => $excluded_ids,
        ]);

        $existing_child_items = [];
        foreach (wp_get_nav_menu_items($menu_id) as $item) {
            if ((int)$item->menu_item_parent === $parent_item->ID) {
                $existing_child_items[$item->object_id] = $item;
            }
        }

        // Sort categories alphabetically (use custom label if exists)
        usort($child_categories, function ($a, $b) use ($existing_child_items) {
            $a_label = isset($existing_child_items[$a->term_id]) ? $existing_child_items[$a->term_id]->title : $a->name;
            $b_label = isset($existing_child_items[$b->term_id]) ? $existing_child_items[$b->term_id]->title : $b->name;
            return strcasecmp($a_label, $b_label);
        });

        foreach ($child_categories as $child_category) {
            if (!isset($existing_child_items[$child_category->term_id])) {
                wp_update_nav_menu_item($menu_id, 0, [
                    'menu-item-title'     => $child_category->name,
                    'menu-item-url'       => get_term_link($child_category),
                    'menu-item-parent-id' => $parent_item->ID,
                    'menu-item-type'      => 'taxonomy',
                    'menu-item-object'    => 'product_cat',
                    'menu-item-object-id' => $child_category->term_id,
                    'menu-item-status'    => 'publish',
                ]);
            }
        }

        foreach ($existing_child_items as $menu_item_id => $menu_item) {
            if (!in_array($menu_item->object_id, wp_list_pluck($child_categories, 'term_id'))) {
                wp_delete_post($menu_item_id, true);
            }
        }
    }

}
