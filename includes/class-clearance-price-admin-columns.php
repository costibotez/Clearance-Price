<?php
declare( strict_types=1 );
/**
 * "Clearance Price" column in the admin product list.
 *
 * @package Clearance_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clearance_Price_Admin_Columns {

	/**
	 * Shared option helpers.
	 *
	 * @var Clearance_Price_Options
	 */
	private $options;

	public function __construct( Clearance_Price_Options $options ) {
		$this->options = $options;

		add_filter( 'manage_product_posts_columns', array( $this, 'add_column' ), 15 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Add Product Clearance price column in products listing.
	 *
	 * @param array $columns Existing columns.
	 */
	public function add_column( $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();
		if ( ! $this->options->is_enabled() ) {
			return $columns;
		}

		$new = array();
		foreach ( $columns as $key => $value ) {
			$new[ $key ] = $value;
			if ( 'price' === $key ) {
				$new['clearance_price'] = __( 'Clearance Price', 'clearance-price' );
			}
		}
		return $new;
	}

	/**
	 * Render the Clearance price column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product ID.
	 */
	public function render_column( $column, $post_id ): void {
		if ( 'clearance_price' !== $column || ! $this->options->is_enabled() ) {
			return;
		}
		$clearance_price = $this->options->get_clearance_price( (int) $post_id );
		if ( '' !== $clearance_price ) {
			echo wp_kses_post( wc_price( (float) $clearance_price ) );
		} else {
			esc_html_e( 'Not filled', 'clearance-price' );
		}
	}
}
