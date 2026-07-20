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

// Product meta (_clearance_price) is intentionally kept so prices survive a
// reinstall. Remove it manually if a full cleanup is wanted.
