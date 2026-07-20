<?php
declare( strict_types=1 );
/**
 * Plugin Name: Clearance Price for Products
 * Description: Add a custom clearance price for WooCommerce products, applied during a configurable date range.
 * Author: Botez Costin
 * Author URI: https://nomad-developer.co.uk/
 * Version: 1.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clearance-price
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 9.5
 *
 * @package Clearance_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'CLEARANCE_PRICE_VERSION', '1.4' );
define( 'CLEARANCE_PRICE_PLUGIN_FILE', __FILE__ );
define( 'CLEARANCE_PRICE_PLUGIN_PATH', plugin_basename( __FILE__ ) );
define( 'CLEARANCE_PRICE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLEARANCE_PRICE_PLUGIN_DIR_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'assets/' );

register_activation_hook( __FILE__, 'clearance_price_require_php_version' );

function clearance_price_require_php_version(): void {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( sprintf( esc_html__( 'Clearance Price for Products requires PHP %s or higher.', 'clearance-price' ), '7.4' ) );
	}
}

add_action( 'before_woocommerce_init', 'clearance_price_declare_hpos_compatibility' );

function clearance_price_declare_hpos_compatibility(): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

add_action( 'plugins_loaded', 'clearance_price_bootstrap' );

function clearance_price_bootstrap(): void {
	// Covers normal and multisite/network activations, unlike checking the active_plugins option.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once CLEARANCE_PRICE_PLUGIN_DIR . 'includes/class-clearance-price-options.php';
	require_once CLEARANCE_PRICE_PLUGIN_DIR . 'includes/class-clearance-price-settings.php';
	require_once CLEARANCE_PRICE_PLUGIN_DIR . 'includes/class-clearance-price-product-meta.php';
	require_once CLEARANCE_PRICE_PLUGIN_DIR . 'includes/class-clearance-price-price-manager.php';
	require_once CLEARANCE_PRICE_PLUGIN_DIR . 'includes/class-clearance-price-admin-columns.php';
	require_once CLEARANCE_PRICE_PLUGIN_DIR . 'includes/class-clearance-price-bulk.php';
	require_once CLEARANCE_PRICE_PLUGIN_DIR . 'includes/class-clearance-price-shortcode.php';
	require_once CLEARANCE_PRICE_PLUGIN_DIR . 'includes/class-clearance-price-plugin.php';

	Clearance_Price_Plugin::instance();
}
