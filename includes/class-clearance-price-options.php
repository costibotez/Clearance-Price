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
	 * Whether the "clearance ends in ..." notice should be shown on
	 * product pages.
	 */
	public function countdown_enabled(): bool {
		return $this->is_enabled() && (bool) get_option( 'clearance_price_countdown_cb' );
	}

	/**
	 * Badge text, falling back to the default label.
	 */
	public function get_badge_text(): string {
		$text = get_option( 'clearance_price_badge_text', '' );
		$text = is_string( $text ) ? trim( $text ) : '';
		return '' !== $text ? $text : __( 'Clearance!', 'clearance-price' );
	}

	/**
	 * Badge background colour (hex) or '' for the theme default.
	 */
	public function get_badge_background(): string {
		return self::sanitize_color_value( get_option( 'clearance_price_badge_bg', '' ) );
	}

	/**
	 * Badge text colour (hex) or '' for the theme default.
	 */
	public function get_badge_color(): string {
		return self::sanitize_color_value( get_option( 'clearance_price_badge_color', '' ) );
	}

	/**
	 * Accept only an empty string or a valid Y-m-d date.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_date_value( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d', $value );
		return ( $date instanceof DateTimeImmutable && $date->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	/**
	 * Accept only an empty string or a #rgb/#rrggbb hex colour.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_color_value( $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';
		return preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value ) ? $value : '';
	}

	/**
	 * Whether the global clearance window is currently active.
	 */
	public function is_clearance_window_active(): bool {
		return $this->is_enabled() && $this->is_window_active(
			(string) get_option( 'clearance_price_from_date_cb', '' ),
			(string) get_option( 'clearance_price_to_date_cb', '' )
		);
	}

	/**
	 * Whether the clearance window is active for a specific product.
	 *
	 * A product can override the global date range with its own
	 * _clearance_price_from / _clearance_price_to meta; when either is set,
	 * the product's own range is used instead of the global one. Variations
	 * follow their parent's schedule — pass the parent product ID.
	 *
	 * @param int $product_id Parent product ID.
	 */
	public function is_clearance_window_active_for( int $product_id ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$from = self::sanitize_date_value( get_post_meta( $product_id, '_clearance_price_from', true ) );
		$to   = self::sanitize_date_value( get_post_meta( $product_id, '_clearance_price_to', true ) );

		$active = ( '' === $from && '' === $to )
			? $this->is_clearance_window_active()
			: $this->is_window_active( $from, $to );

		/**
		 * Filter whether the clearance window is active for a product.
		 *
		 * @param bool $active     Whether the window is active.
		 * @param int  $product_id Parent product ID.
		 */
		return (bool) apply_filters( 'clearance_price_window_active', $active, $product_id );
	}

	/**
	 * End of the clearance window that applies to a product, or null when
	 * the window is open-ended.
	 *
	 * @param int $product_id Parent product ID.
	 */
	public function get_window_end_for( int $product_id ): ?DateTimeImmutable {
		$from = self::sanitize_date_value( get_post_meta( $product_id, '_clearance_price_from', true ) );
		$to   = self::sanitize_date_value( get_post_meta( $product_id, '_clearance_price_to', true ) );

		if ( '' === $from && '' === $to ) {
			$to = (string) get_option( 'clearance_price_to_date_cb', '' );
		}
		if ( '' === $to ) {
			return null;
		}

		$end = date_create_immutable( $to . ' 23:59:59', wp_timezone() );
		return $end instanceof DateTimeImmutable ? $end : null;
	}

	/**
	 * Whether a from/to date pair contains the current moment.
	 *
	 * Dates are compared in the site timezone. An empty "from" date means
	 * active since forever, an empty "to" date means no end; the "to" day
	 * itself is fully included.
	 */
	private function is_window_active( string $from, string $to ): bool {
		$timezone = wp_timezone();
		$now      = current_datetime();

		if ( '' !== $from ) {
			$from_date = date_create_immutable( $from . ' 00:00:00', $timezone );
			if ( $from_date instanceof DateTimeImmutable && $now < $from_date ) {
				return false;
			}
		}

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
		if ( $product->is_type( 'variation' ) && $this->variable_enabled() ) {
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

	/**
	 * The product ID that carries the clearance schedule for a product:
	 * the parent for variations, the product itself otherwise.
	 *
	 * @param WC_Product $product Product object.
	 */
	public function get_schedule_id( WC_Product $product ): int {
		if ( $product->is_type( 'variation' ) ) {
			$parent_id = (int) $product->get_parent_id();
			if ( $parent_id > 0 ) {
				return $parent_id;
			}
		}
		return (int) $product->get_id();
	}
}
