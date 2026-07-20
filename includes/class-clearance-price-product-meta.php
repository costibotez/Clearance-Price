<?php
declare( strict_types=1 );
/**
 * Clearance price fields on the product edit screen and their persistence.
 *
 * @package Clearance_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clearance_Price_Product_Meta {

	/**
	 * Shared option helpers.
	 *
	 * @var Clearance_Price_Options
	 */
	private $options;

	public function __construct( Clearance_Price_Options $options ) {
		$this->options = $options;

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_simple_field' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_schedule_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_simple_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_schedule_fields' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_field' ), 10, 2 );
	}

	/**
	 * Display clearance price HTML input in product admin (Simple Product).
	 */
	public function render_simple_field(): void {
		if ( ! $this->options->is_enabled() ) {
			return;
		}
		echo '<div class="options_group show_if_simple">';
		woocommerce_wp_text_input(
			array(
				'id'          => '_clearance_price',
				'label'       => __( 'Clearance Price', 'clearance-price' ),
				'placeholder' => '',
				'desc_tip'    => true,
				'description' => __( 'Enter the custom price here. Leave empty to use the regular price.', 'clearance-price' ),
				'data_type'   => 'price',
			)
		);
		echo '</div>';
	}

	/**
	 * Display the per-product clearance schedule fields (simple and
	 * variable products; variations follow their parent's schedule).
	 */
	public function render_schedule_fields(): void {
		if ( ! $this->options->is_enabled() ) {
			return;
		}
		global $post;
		$post_id = isset( $post->ID ) ? (int) $post->ID : 0;

		echo '<div class="options_group show_if_simple show_if_variable">';
		woocommerce_wp_text_input(
			array(
				'id'          => '_clearance_price_from',
				'label'       => __( 'Clearance From date', 'clearance-price' ),
				'type'        => 'date',
				'desc_tip'    => true,
				'description' => __( 'Optional. Overrides the global clearance date range for this product.', 'clearance-price' ),
				'value'       => get_post_meta( $post_id, '_clearance_price_from', true ),
			)
		);
		woocommerce_wp_text_input(
			array(
				'id'          => '_clearance_price_to',
				'label'       => __( 'Clearance To date', 'clearance-price' ),
				'type'        => 'date',
				'desc_tip'    => true,
				'description' => __( 'Optional. Overrides the global clearance date range for this product.', 'clearance-price' ),
				'value'       => get_post_meta( $post_id, '_clearance_price_to', true ),
			)
		);
		echo '</div>';
	}

	/**
	 * Save the per-product clearance schedule fields.
	 *
	 * Nonce and intent are verified upstream by WooCommerce before the
	 * woocommerce_process_product_meta action fires.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_schedule_fields( $post_id ): void {
		$post_id = (int) $post_id;
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}
		foreach ( array( '_clearance_price_from', '_clearance_price_to' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream by WooCommerce.
			if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream by WooCommerce.
			$value = Clearance_Price_Options::sanitize_date_value( wp_unslash( $_POST[ $key ] ) );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Save clearance price in product meta (Simple Product).
	 *
	 * Nonce and intent are verified upstream by WooCommerce before the
	 * woocommerce_process_product_meta action fires.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_simple_field( $post_id ): void {
		$post_id = (int) $post_id;
		// For variable products the field is submitted as an array keyed by
		// variation ID and handled by save_variation_field().
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream by WooCommerce.
		if ( ! isset( $_POST['_clearance_price'] ) || is_array( $_POST['_clearance_price'] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified upstream by WooCommerce; sanitised in update_meta().
		$this->update_meta( $post_id, wp_unslash( $_POST['_clearance_price'] ) );
	}

	/**
	 * Display Clearance Field in Admin (Variable Product).
	 *
	 * @param int     $loop           Position in the loop.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation post object.
	 */
	public function render_variation_field( $loop, $variation_data, $variation ): void {
		if ( ! $this->options->is_enabled() || ! isset( $variation->ID ) ) {
			return;
		}
		woocommerce_wp_text_input(
			array(
				'id'          => '_clearance_price[' . (int) $variation->ID . ']',
				'label'       => __( 'Clearance Price', 'clearance-price' ),
				'placeholder' => '',
				'desc_tip'    => true,
				'description' => __( 'Enter the custom price here. Leave empty to use the regular price.', 'clearance-price' ),
				'value'       => get_post_meta( (int) $variation->ID, '_clearance_price', true ),
				'data_type'   => 'price',
			)
		);
	}

	/**
	 * Save Clearance Field in Admin (Variable product).
	 *
	 * Nonce and intent are verified upstream by WooCommerce before the
	 * woocommerce_save_product_variation action fires.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $i            Position in the loop.
	 */
	public function save_variation_field( $variation_id, $i = 0 ): void {
		$variation_id = (int) $variation_id;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified upstream by WooCommerce.
		if ( ! isset( $_POST['_clearance_price'] ) || ! is_array( $_POST['_clearance_price'] ) || ! isset( $_POST['_clearance_price'][ $variation_id ] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_product', $variation_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified upstream by WooCommerce; sanitised in update_meta().
		$this->update_meta( $variation_id, wp_unslash( $_POST['_clearance_price'][ $variation_id ] ) );
	}

	/**
	 * Sanitise a submitted clearance price and persist it.
	 *
	 * @param int   $object_id Product or variation ID.
	 * @param mixed $raw_price Raw submitted value.
	 */
	private function update_meta( int $object_id, $raw_price ): void {
		$price = is_scalar( $raw_price ) ? wc_format_decimal( (string) $raw_price ) : '';
		if ( '' === $price || ! is_numeric( $price ) || (float) $price < 0 ) {
			delete_post_meta( $object_id, '_clearance_price' );
			return;
		}
		update_post_meta( $object_id, '_clearance_price', $price );
	}
}
