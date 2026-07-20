<?php
declare( strict_types=1 );
/**
 * [clearance_products] shortcode: lists products with an active clearance price.
 *
 * @package Clearance_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clearance_Price_Shortcode {

	/**
	 * How many candidate products/variations to inspect at most.
	 */
	private const CANDIDATE_LIMIT = 200;

	/**
	 * Shared option helpers.
	 *
	 * @var Clearance_Price_Options
	 */
	private $options;

	public function __construct( Clearance_Price_Options $options ) {
		$this->options = $options;

		add_shortcode( 'clearance_products', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode via WooCommerce's [products] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function render( $atts ): string {
		if ( ! $this->options->is_enabled() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'limit'   => '12',
				'columns' => '4',
				'orderby' => 'title',
				'order'   => 'ASC',
			),
			is_array( $atts ) ? $atts : array(),
			'clearance_products'
		);

		$ids = $this->get_clearance_product_ids();
		if ( empty( $ids ) ) {
			return '';
		}

		$order = strtoupper( (string) $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		return do_shortcode(
			sprintf(
				'[products ids="%s" limit="%d" columns="%d" orderby="%s" order="%s"]',
				implode( ',', array_map( 'absint', $ids ) ),
				absint( $atts['limit'] ),
				absint( $atts['columns'] ),
				sanitize_key( (string) $atts['orderby'] ),
				$order
			)
		);
	}

	/**
	 * IDs of products whose clearance price currently applies.
	 *
	 * @return int[]
	 */
	private function get_clearance_product_ids(): array {
		$query = new WP_Query(
			array(
				'post_type'      => array( 'product', 'product_variation' ),
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => self::CANDIDATE_LIMIT,
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded candidate query for the shortcode.
					array(
						'key'     => '_clearance_price',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		$parent_ids = array();
		foreach ( $query->posts as $post_id ) {
			$post_id   = (int) $post_id;
			$parent_id = (int) wp_get_post_parent_id( $post_id );
			$parent_ids[ $parent_id > 0 ? $parent_id : $post_id ] = true;
		}

		$ids = array();
		foreach ( array_keys( $parent_ids ) as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( ! $this->options->is_clearance_window_active_for( (int) $product_id ) ) {
				continue;
			}
			if ( $this->options->product_has_clearance( $product ) ) {
				$ids[] = (int) $product_id;
			}
		}

		return $ids;
	}
}
