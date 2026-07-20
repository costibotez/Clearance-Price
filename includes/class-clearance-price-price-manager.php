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
	}

	/**
	 * Use clearance price instead of default price on product or variation
	 * in the cart. It is used only if it exists.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function calculate_totals( $cart ): void {
		if ( ! $cart instanceof WC_Cart || ! $this->options->is_clearance_window_active() ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$clearance_price = '';
			if ( ! empty( $cart_item['variation_id'] ) ) {
				if ( $this->options->variable_enabled() ) {
					$clearance_price = $this->options->get_clearance_price( (int) $cart_item['variation_id'] );
				}
			} elseif ( $this->options->simple_enabled() ) {
				$clearance_price = $this->options->get_clearance_price( (int) $cart_item['product_id'] );
			}

			if ( '' !== $clearance_price ) {
				$product->set_price( $clearance_price );
			}
		}
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
		if ( ! $product instanceof WC_Product || ! $this->options->is_clearance_window_active() ) {
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
		if ( ! $product instanceof WC_Product || ! $this->options->is_clearance_window_active() ) {
			return $html;
		}
		if ( $this->options->product_has_clearance( $product ) ) {
			return '<span class="onsale clearance-badge">' . esc_html__( 'Clearance!', 'clearance-price' ) . '</span>';
		}
		return $html;
	}
}
