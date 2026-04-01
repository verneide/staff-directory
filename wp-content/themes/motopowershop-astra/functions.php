<?php
/**
 * MotoPowerShop Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package MotoPowerShop
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_MOTOPOWERSHOP_VERSION', '1.0.0' );

/**
 * Enqueue styles
 */
function child_enqueue_styles() {

	wp_enqueue_style( 'motopowershop-theme-css', get_stylesheet_directory_uri() . '/style.css', array('astra-theme-css'), CHILD_THEME_MOTOPOWERSHOP_VERSION, 'all' );

}

add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );

// Include custom function files
require_once get_stylesheet_directory() . '/inc/functions/login-functions.php';
require_once get_stylesheet_directory() . '/inc/functions/custom-woocomm-functions.php';