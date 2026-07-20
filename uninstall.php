<?php
/**
 * Uninstall handler for Clearance Price for Products.
 *
 * The previous register_uninstall_hook() call used an instance method, which
 * WordPress does not support, so options were never removed. This file is the
 * supported mechanism and runs when the plugin is deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'clearance_price_cb' );
delete_option( 'clearance_price_simple_cb' );
delete_option( 'clearance_price_variable_cb' );
delete_option( 'clearance_price_from_date_cb' );
delete_option( 'clearance_price_to_date_cb' );
delete_option( 'clearance_price_show_original_cb' );
delete_option( 'clearance_price_countdown_cb' );
delete_option( 'clearance_price_badge_text' );
delete_option( 'clearance_price_badge_bg' );
delete_option( 'clearance_price_badge_color' );

// Product meta (_clearance_price, _clearance_price_from, _clearance_price_to
// and order item meta _clearance_price_applied) is intentionally kept so data
// survives a reinstall. Remove it manually if a full cleanup is wanted.
