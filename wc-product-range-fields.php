<?php

/**
 * Plugin Name: WC Product Range Fields
 * Description: Adds min range, max range, and a visibility toggle to simple products and each product variation.
 * Version: 1.0.0
 * Author: Myriadsolutionz
 * Requires Plugins: woocommerce
 * Text Domain: wc-product-range-fields
 *
 * @package WC_Product_Range_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-product-range-fields.php';

$wc_product_range_fields = new WC_Product_Range_Fields( __FILE__ );
$wc_product_range_fields->run();
