<?php
declare( strict_types=1 );
/**
 * Front-end pricing: cart overrides, displayed prices and the clearance badge.
 *
 * @package Clearance_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clearance_Price_Price_Manager {

	/**
	 * Shared option helpers.
	 *
	 * @var Clearance_Price_Options
	 */
	private $options;

	public function __construct( Clearance_Price_Options $options ) {
		$this->options = $options;

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_totals' ), 20 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'price_html' ), 200, 2 );
		add_filter( 'woocommerce_sale_flash', array( $this, 'replace_sale_text' ), 10, 3 );
		add_filter( 'woocommerce_product_is_on_sale', array( $this, 'mark_on_sale' ), 10, 2 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_countdown' ), 25 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 3 );
	}

	/**
	 * Whether the clearance window is active for a product, using the
	 * parent's schedule for variations.
	 *
	 * @param WC_Product $product Product object.
	 */
	private function window_active_for( WC_Product $product ): bool {
		return $this->options->is_clearance_window_active_for( $this->options->get_schedule_id( $product ) );
	}

	/**
	 * Use clearance price instead of default price on product or variation
	 * in the cart. It is used only if it exists.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function calculate_totals( $cart ): void {
		if ( ! $cart instanceof WC_Cart || ! $this->options->is_enabled() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			// product_id is the parent product, which carries the schedule.
			if ( ! $this->options->is_clearance_window_active_for( (int) $cart_item['product_id'] ) ) {
				continue;
			}

			$clearance_price = $this->get_cart_item_clearance_price( $cart_item );
			if ( '' !== $clearance_price ) {
				$product->set_price( $clearance_price );
			}
		}
	}

	/**
	 * Clearance price applying to a cart item, or '' when none.
	 *
	 * @param array $cart_item Cart item data.
	 */
	private function get_cart_item_clearance_price( array $cart_item ): string {
		if ( ! empty( $cart_item['variation_id'] ) ) {
			return $this->options->variable_enabled()
				? $this->options->get_clearance_price( (int) $cart_item['variation_id'] )
				: '';
		}
		return $this->options->simple_enabled()
			? $this->options->get_clearance_price( (int) $cart_item['product_id'] )
			: '';
	}

	/**
	 * Change front-end price with clearance price.
	 *
	 * Simple products and single variations show the clearance price;
	 * variable products show a price range that accounts for the
	 * clearance prices of their variations.
	 *
	 * @param string     $price   Price HTML.
	 * @param WC_Product $product Product object.
	 */
	public function price_html( $price, $product ) {
		if ( ! $product instanceof WC_Product || ! $this->options->is_enabled() || ! $this->window_active_for( $product ) ) {
			return $price;
		}

		if ( ( $product->is_type( 'simple' ) && $this->options->simple_enabled() )
			|| ( $product->is_type( 'variation' ) && $this->options->variable_enabled() ) ) {
			$clearance_price = $this->options->get_clearance_price( $product->get_id() );
			if ( '' !== $clearance_price ) {
				$clearance_display = (float) wc_get_price_to_display( $product, array( 'price' => (float) $clearance_price ) );

				if ( $this->options->show_original_price() && '' !== $product->get_regular_price() ) {
					$regular_display = (float) wc_get_price_to_display( $product, array( 'price' => (float) $product->get_regular_price() ) );
					if ( $regular_display > $clearance_display ) {
						return wc_format_sale_price( $regular_display, $clearance_display ) . $product->get_price_suffix();
					}
				}

				return wc_price( $clearance_display ) . $product->get_price_suffix();
			}
		}

		if ( $product->is_type( 'variable' ) && $this->options->variable_enabled() ) {
			$range = $this->get_variable_clearance_range( $product );
			if ( null !== $range ) {
				$price_html = $range[0] === $range[1] ? wc_price( $range[0] ) : wc_format_price_range( $range[0], $range[1] );
				return $price_html . $product->get_price_suffix();
			}
		}

		return $price;
	}

	/**
	 * Min/max display price of a variable product with clearance prices
	 * applied, or null when no variation has a clearance price.
	 *
	 * @param WC_Product $product Variable product.
	 * @return array{0: float, 1: float}|null
	 */
	private function get_variable_clearance_range( WC_Product $product ): ?array {
		$has_clearance = false;
		$prices        = array();

		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $child instanceof WC_Product ) {
				continue;
			}
			$clearance_price = $this->options->get_clearance_price( (int) $child_id );
			if ( '' !== $clearance_price ) {
				$has_clearance = true;
				$prices[]      = (float) wc_get_price_to_display( $child, array( 'price' => (float) $clearance_price ) );
			} elseif ( '' !== $child->get_price() ) {
				$prices[] = (float) wc_get_price_to_display( $child );
			}
		}

		if ( ! $has_clearance || empty( $prices ) ) {
			return null;
		}

		return array( min( $prices ), max( $prices ) );
	}

	/**
	 * Change sale badge with a clearance one if a clearance price applies.
	 *
	 * @param string     $html    Badge HTML.
	 * @param WP_Post    $post    Post object.
	 * @param WC_Product $product Product object.
	 */
	public function replace_sale_text( $html, $post = null, $product = null ) {
		if ( ! $product instanceof WC_Product || ! $this->window_active_for( $product ) ) {
			return $html;
		}
		if ( ! $this->options->product_has_clearance( $product ) ) {
			return $html;
		}

		$style      = '';
		$background = $this->options->get_badge_background();
		$color      = $this->options->get_badge_color();
		if ( '' !== $background ) {
			$style .= 'background-color:' . $background . ';';
		}
		if ( '' !== $color ) {
			$style .= 'color:' . $color . ';';
		}

		$badge = '<span class="onsale clearance-badge"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>'
			. esc_html( $this->options->get_badge_text() )
			. '</span>';

		/**
		 * Filter the clearance badge markup.
		 *
		 * @param string     $badge   Badge HTML.
		 * @param WC_Product $product Product the badge is shown for.
		 */
		return apply_filters( 'clearance_price_badge_html', $badge, $product );
	}

	/**
	 * Treat products with an active clearance price as on sale, so the
	 * (clearance) badge shows even when no WooCommerce sale price is set.
	 *
	 * @param bool       $on_sale Whether WooCommerce considers the product on sale.
	 * @param WC_Product $product Product object.
	 */
	public function mark_on_sale( $on_sale, $product ) {
		if ( $on_sale || ! $product instanceof WC_Product ) {
			return $on_sale;
		}
		if ( ! $this->options->is_enabled() || ! $this->window_active_for( $product ) ) {
			return $on_sale;
		}
		return $this->options->product_has_clearance( $product );
	}

	/**
	 * Show "Clearance ends in ..." on the product page when the applicable
	 * window has an end date.
	 */
	public function render_countdown(): void {
		if ( ! $this->options->countdown_enabled() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( ! $this->window_active_for( $product ) || ! $this->options->product_has_clearance( $product ) ) {
			return;
		}

		$end = $this->options->get_window_end_for( $this->options->get_schedule_id( $product ) );
		if ( null === $end ) {
			return;
		}
		$now = current_datetime();
		if ( $end <= $now ) {
			return;
		}

		echo '<p class="clearance-countdown">'
			. esc_html( sprintf( /* translators: %s: human-readable time difference, e.g. "2 days". */ __( 'Clearance ends in %s', 'clearance-price' ), human_time_diff( $now->getTimestamp(), $end->getTimestamp() ) ) )
			. '</p>';
	}

	/**
	 * Record the applied clearance price on the order line item, so
	 * clearance revenue can be identified later.
	 *
	 * @param WC_Order_Item_Product $item          Order line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item data.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values ): void {
		if ( ! $this->options->is_enabled() || ! is_array( $values ) || ! $item instanceof WC_Order_Item_Product ) {
			return;
		}
		$parent_id = isset( $values['product_id'] ) ? (int) $values['product_id'] : 0;
		if ( 0 === $parent_id || ! $this->options->is_clearance_window_active_for( $parent_id ) ) {
			return;
		}

		$clearance_price = $this->get_cart_item_clearance_price( $values );
		if ( '' !== $clearance_price ) {
			$item->add_meta_data( '_clearance_price_applied', $clearance_price, true );
		}
	}
}
