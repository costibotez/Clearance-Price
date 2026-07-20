<?php
declare( strict_types=1 );
/**
 * Bulk tool: apply a percentage-off clearance price to a product category,
 * or clear clearance prices for a category.
 *
 * @package Clearance_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clearance_Price_Bulk {

	/**
	 * Shared option helpers.
	 *
	 * @var Clearance_Price_Options
	 */
	private $options;

	public function __construct( Clearance_Price_Options $options ) {
		$this->options = $options;

		add_action( 'clearance_price_settings_after_form', array( $this, 'render_form' ) );
		add_action( 'admin_post_clearance_price_bulk', array( $this, 'handle' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Render the bulk-apply form on the settings page.
	 */
	public function render_form(): void {
		if ( ! $this->options->is_enabled() || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<hr />
		<h2><?php esc_html_e( 'Bulk apply', 'clearance-price' ); ?></h2>
		<p><?php esc_html_e( 'Set the clearance price of every product in a category to a percentage off its regular price (variations included). Existing clearance prices in the category are overwritten.', 'clearance-price' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="clearance_price_bulk" />
			<?php wp_nonce_field( 'clearance_price_bulk' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Category', 'clearance-price' ); ?></th>
					<td>
						<?php
						wp_dropdown_categories(
							array(
								'taxonomy'         => 'product_cat',
								'name'             => 'clearance_price_bulk_category',
								'hide_empty'       => false,
								'hierarchical'     => true,
								'show_option_none' => __( 'Select a category', 'clearance-price' ),
								'option_none_value' => '0',
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Percentage off', 'clearance-price' ); ?></th>
					<td><input type="number" name="clearance_price_bulk_percent" min="1" max="99" step="0.1" class="small-text" /> %</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="clearance_price_bulk_mode" value="apply" class="button button-primary"><?php esc_html_e( 'Apply percentage', 'clearance-price' ); ?></button>
				<button type="submit" name="clearance_price_bulk_mode" value="clear" class="button"><?php esc_html_e( 'Clear clearance prices', 'clearance-price' ); ?></button>
			</p>
		</form>
		<?php
	}

	/**
	 * Handle the bulk-apply form submission.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'clearance-price' ) );
		}
		check_admin_referer( 'clearance_price_bulk' );

		$term_id = isset( $_POST['clearance_price_bulk_category'] ) ? absint( $_POST['clearance_price_bulk_category'] ) : 0;
		$mode    = isset( $_POST['clearance_price_bulk_mode'] ) ? sanitize_key( wp_unslash( $_POST['clearance_price_bulk_mode'] ) ) : '';
		$percent = isset( $_POST['clearance_price_bulk_percent'] ) ? (float) wp_unslash( $_POST['clearance_price_bulk_percent'] ) : 0.0;

		$updated = -1; // -1 signals invalid input.
		if ( $term_id > 0 && 'clear' === $mode ) {
			$updated = $this->process_category( $term_id, null );
		} elseif ( $term_id > 0 && 'apply' === $mode && $percent >= 1 && $percent <= 99 ) {
			$updated = $this->process_category( $term_id, $percent );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                => 'clearance_price',
					'clearance_bulk_done' => (string) $updated,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Apply a percentage-off clearance price (or clear clearance prices)
	 * for all products and variations in a category.
	 *
	 * @param int        $term_id Product category term ID.
	 * @param float|null $percent Percentage off the regular price, or null to clear.
	 * @return int Number of products/variations updated.
	 */
	private function process_category( int $term_id, ?float $percent ): int {
		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term instanceof WP_Term ) {
			return 0;
		}

		$products = wc_get_products(
			array(
				'limit'    => -1,
				'status'   => 'publish',
				'category' => array( $term->slug ),
			)
		);

		$updated = 0;
		foreach ( $products as $product ) {
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( $child instanceof WC_Product ) {
						$updated += $this->process_single( $child, $percent );
					}
				}
			} else {
				$updated += $this->process_single( $product, $percent );
			}
		}

		return $updated;
	}

	/**
	 * Set or clear the clearance price of one product/variation.
	 *
	 * @param WC_Product $product Product or variation.
	 * @param float|null $percent Percentage off, or null to clear.
	 * @return int 1 when updated, 0 otherwise.
	 */
	private function process_single( WC_Product $product, ?float $percent ): int {
		if ( null === $percent ) {
			delete_post_meta( $product->get_id(), '_clearance_price' );
			return 1;
		}

		$regular = $product->get_regular_price();
		if ( '' === $regular || ! is_numeric( $regular ) || (float) $regular <= 0 ) {
			return 0;
		}

		$price = wc_format_decimal( (float) $regular * ( 1 - $percent / 100 ), wc_get_price_decimals() );
		if ( '' === $price || (float) $price <= 0 ) {
			return 0;
		}

		update_post_meta( $product->get_id(), '_clearance_price', $price );
		return 1;
	}

	/**
	 * Show the result notice after a bulk run.
	 */
	public function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		if ( ! isset( $_GET['page'], $_GET['clearance_bulk_done'] ) || 'clearance_price' !== $_GET['page'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a redirect result.
		$done = (int) $_GET['clearance_bulk_done'];

		if ( $done < 0 ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Bulk update failed: choose a category and a percentage between 1 and 99.', 'clearance-price' ) . '</p></div>';
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>'
			/* translators: %d: number of products/variations updated. */
			. esc_html( sprintf( _n( '%d product updated.', '%d products updated.', $done, 'clearance-price' ), $done ) )
			. '</p></div>';
	}
}
