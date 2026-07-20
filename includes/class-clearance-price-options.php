<?php
declare( strict_types=1 );
/**
 * Option, clearance-window and clearance-price helpers shared across components.
 *
 * @package Clearance_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clearance_Price_Options {

	/**
	 * Whether the master switch is on.
	 */
	public function is_enabled(): bool {
		return (bool) get_option( 'clearance_price_cb' );
	}

	/**
	 * Whether clearance pricing applies to simple products.
	 */
	public function simple_enabled(): bool {
		return $this->is_enabled() && (bool) get_option( 'clearance_price_simple_cb' );
	}

	/**
	 * Whether clearance pricing applies to variable products.
	 */
	public function variable_enabled(): bool {
		return $this->is_enabled() && (bool) get_option( 'clearance_price_variable_cb' );
	}

	/**
	 * Whether the original price should be shown struck through
	 * next to the clearance price.
	 */
	public function show_original_price(): bool {
		return $this->is_enabled() && (bool) get_option( 'clearance_price_show_original_cb' );
	}

	/**
	 * Whether the clearance window is currently active.
	 *
	 * Dates are compared in the site timezone. An empty "from" date means
	 * active since forever, an empty "to" date means no end; the "to" day
	 * itself is fully included.
	 */
	public function is_clearance_window_active(): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$timezone = wp_timezone();
		$now      = current_datetime();

		$from = (string) get_option( 'clearance_price_from_date_cb', '' );
		if ( '' !== $from ) {
			$from_date = date_create_immutable( $from . ' 00:00:00', $timezone );
			if ( $from_date instanceof DateTimeImmutable && $now < $from_date ) {
				return false;
			}
		}

		$to = (string) get_option( 'clearance_price_to_date_cb', '' );
		if ( '' !== $to ) {
			$to_date = date_create_immutable( $to . ' 23:59:59', $timezone );
			if ( $to_date instanceof DateTimeImmutable && $now > $to_date ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the clearance price stored for a product or variation.
	 *
	 * @param int $object_id Product or variation ID.
	 * @return string Numeric price string, or '' when none is set.
	 */
	public function get_clearance_price( int $object_id ): string {
		$price = get_post_meta( $object_id, '_clearance_price', true );
		if ( ! is_string( $price ) && ! is_numeric( $price ) ) {
			return '';
		}
		$price = (string) $price;
		if ( '' === $price || ! is_numeric( $price ) || (float) $price <= 0 ) {
			return '';
		}
		return $price;
	}

	/**
	 * Whether a product (or one of its variations) has a clearance price.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function product_has_clearance( WC_Product $product ): bool {
		if ( $product->is_type( 'simple' ) && $this->simple_enabled() ) {
			return '' !== $this->get_clearance_price( $product->get_id() );
		}
		if ( $product->is_type( 'variable' ) && $this->variable_enabled() ) {
			foreach ( $product->get_children() as $child_id ) {
				if ( '' !== $this->get_clearance_price( (int) $child_id ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
