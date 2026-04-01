<?php
// Custom Functions for Theme to Tweak WooCommerce

function remove_uncategorized_on_save($post_id) {
    // Check if this is a product post type and not a revision.
    if (get_post_type($post_id) === 'product' && !wp_is_post_revision($post_id)) {
        
        // Get the current product categories.
        $terms = wp_get_post_terms($post_id, 'product_cat', ['fields' => 'ids']);

        // Find the "Uncategorized" category ID.
        $uncategorized_id = get_term_by('slug', 'uncategorized', 'product_cat')->term_id;

        // If there's more than one category and "Uncategorized" is among them, remove it.
        if (count($terms) > 1 && in_array($uncategorized_id, $terms)) {
            $new_terms = array_diff($terms, [$uncategorized_id]);
            wp_set_post_terms($post_id, $new_terms, 'product_cat');
        }
    }
}
add_action('save_post', 'remove_uncategorized_on_save');


/////////////////////////
// NO SHIPPING OPTIONS //
/////////////////////////

// Display message on product page for "No Shipping Allowed" products
function display_local_pickup_message_for_no_shipping_class() {
    global $product;
    if (has_term('no-shipping-allowed', 'product_shipping_class', $product->get_id())) {
        echo '<p class="no-shipping-message" style="color: red; font-weight: bold;">This product is not available for shipping. Local pickup only.</p>';
    }
}
add_action('woocommerce_single_product_summary', 'display_local_pickup_message_for_no_shipping_class', 20);

// Restrict shipping methods to pickup locations if "No Shipping Allowed" products are in the cart
function restrict_shipping_methods_if_no_shipping_allowed($available_shipping_methods, $package) {
    $pickup_required = false;
    $debug_log = [];

    // Check if the cart contains a "No Shipping Allowed" product
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (has_term('no-shipping-allowed', 'product_shipping_class', $cart_item['product_id'])) {
            $pickup_required = true;
            $debug_log[] = "Local pickup required for product: " . $cart_item['data']->get_name();
            break;
        }
    }

    // If a pickup-only item is found, retain only pickup location methods
    if ($pickup_required) {
        foreach ($available_shipping_methods as $key => $method) {
            if (strpos($method->id, 'pickup_location') === false) {
                unset($available_shipping_methods[$key]);
                $debug_log[] = "Removed shipping method: {$method->id}";
            } else {
                $debug_log[] = "Retained shipping method: {$method->id} - {$method->label}";
            }
        }
    } else {
        $debug_log[] = "No pickup-only items found; all shipping methods available.";
    }

    // Output debug info to the JavaScript console (for troubleshooting, if needed)
    echo "<script>console.log(" . json_encode($debug_log) . ");</script>";

    return $available_shipping_methods;
}
add_filter('woocommerce_package_rates', 'restrict_shipping_methods_if_no_shipping_allowed', 10, 2);

// Enqueue JavaScript for Checkout Page
function enqueue_checkout_restrictions_script() {
    if (is_checkout()) {
        wp_enqueue_script('checkout-restrictions', get_stylesheet_directory_uri() . '/inc/js/checkout-restrictions.js', array('jquery'), null, true);

        // Pass pickupRequired flag for JavaScript to use
        $pickup_required = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (has_term('no-shipping-allowed', 'product_shipping_class', $cart_item['product_id'])) {
                $pickup_required = true;
                break;
            }
        }
        wp_localize_script('checkout-restrictions', 'pickupOnlySettings', array(
            'pickupRequired' => $pickup_required,
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_checkout_restrictions_script');