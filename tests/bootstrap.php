<?php
declare( strict_types=1 );
/**
 * Unit-test bootstrap.
 *
 * These are lightweight unit tests for the plugin's pure logic (date window,
 * price validation, sanitizers). The handful of WordPress functions those
 * units touch are stubbed here with controllable globals — no WordPress or
 * WooCommerce install is needed.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CLEARANCE_PRICE_VERSION', 'test' );
define( 'CLEARANCE_PRICE_PLUGIN_PATH', 'clearance-price/clearance.php' );
define( 'CLEARANCE_PRICE_PLUGIN_DIR_ASSETS_URL', 'https://example.test/wp-content/plugins/clearance-price/assets/' );

$GLOBALS['cp_test_options']   = array();
$GLOBALS['cp_test_post_meta'] = array();
$GLOBALS['cp_test_timezone']  = 'UTC';
$GLOBALS['cp_test_now']       = null;

function cp_test_reset(): void {
	$GLOBALS['cp_test_options']   = array();
	$GLOBALS['cp_test_post_meta'] = array();
	$GLOBALS['cp_test_timezone']  = 'UTC';
	$GLOBALS['cp_test_now']       = null;
}

// --- WordPress function stubs -------------------------------------------

function add_action( ...$args ): bool {
	return true;
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function add_filter( ...$args ): bool {
	return true;
}

function get_option( $name, $default_value = false ) {
	return array_key_exists( $name, $GLOBALS['cp_test_options'] ) ? $GLOBALS['cp_test_options'][ $name ] : $default_value;
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	return $GLOBALS['cp_test_post_meta'][ $post_id ][ $key ] ?? '';
}

function wp_timezone(): DateTimeZone {
	return new DateTimeZone( $GLOBALS['cp_test_timezone'] );
}

function current_datetime(): DateTimeImmutable {
	return $GLOBALS['cp_test_now'] instanceof DateTimeImmutable
		? $GLOBALS['cp_test_now']
		: new DateTimeImmutable( 'now', wp_timezone() );
}

// ------------------------------------------------------------------------

require_once dirname( __DIR__ ) . '/includes/class-clearance-price-options.php';
require_once dirname( __DIR__ ) . '/includes/class-clearance-price-settings.php';
