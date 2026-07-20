<?php
declare( strict_types=1 );
/**
 * Plugin Name: Clearance Price for Products
 * Description: Add a custom clearance price for WooCommerce products, applied during a configurable date range.
 * Author: Botez Costin
 * Author URI: https://nomad-developer.co.uk/
 * Version: 1.2
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clearance-price
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 9.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'CLEARANCE_PRICE_VERSION', '1.2' );
define( 'CLEARANCE_PRICE_PLUGIN_PATH', plugin_basename( __FILE__ ) );
define( 'CLEARANCE_PRICE_PLUGIN_DIR_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'assets/' );

register_activation_hook( __FILE__, 'clearance_price_require_php_version' );

function clearance_price_require_php_version(): void {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( sprintf( esc_html__( 'Clearance Price for Products requires PHP %s or higher.', 'clearance-price' ), '7.4' ) );
	}
}

add_action( 'before_woocommerce_init', 'clearance_price_declare_hpos_compatibility' );

function clearance_price_declare_hpos_compatibility(): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

add_action( 'plugins_loaded', 'clearance_price_bootstrap' );

function clearance_price_bootstrap(): void {
	// Covers normal and multisite/network activations, unlike checking the active_plugins option.
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	new WC_Clearance_Price();
}

if ( ! class_exists( 'WC_Clearance_Price' ) ) {
	class WC_Clearance_Price {

		/**
		 * Hook suffix of the settings page, used to scope admin assets.
		 *
		 * @var string
		 */
		private $settings_hook = '';

		public function __construct() {
			// Load textdomain.
			add_action( 'init', array( $this, 'clearance_price_load_textdomain' ) );
			// Enqueue back-end scripts on the settings page only.
			add_action( 'admin_enqueue_scripts', array( $this, 'clearance_price_admin_style' ) );
			// Add settings page.
			add_action( 'admin_menu', array( $this, 'clearance_price_menu_item' ) );
			// Add settings fields.
			add_action( 'admin_init', array( $this, 'clearance_price_settings' ) );
			// Add "Settings" in plugins page.
			add_filter( 'plugin_action_links_' . CLEARANCE_PRICE_PLUGIN_PATH, array( $this, 'add_action_links' ) );
			// Overwrite regular price with clearance one if needed.
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'clearance_price_calculate_totals' ), 20 );
			// Display Clearance Field in Admin (Simple Product).
			add_action( 'woocommerce_product_options_general_product_data', array( $this, 'woo_clearance_price_field' ) );
			// Save Clearance Field in Admin (Simple product).
			add_action( 'woocommerce_process_product_meta', array( $this, 'woo_clearance_price_save' ) );
			// Display Clearance Field in Admin (Variable Product).
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'woo_clearance_price_field_variation' ), 10, 3 );
			// Save Clearance Field in Admin (Variable product).
			add_action( 'woocommerce_save_product_variation', array( $this, 'woo_clearance_price_save_variation' ), 10, 2 );
			// Change displayed price on simple, variable and variation products.
			add_filter( 'woocommerce_get_price_html', array( $this, 'clearance_price_html' ), 200, 2 );
			// Add Admin Column in product listing.
			add_filter( 'manage_product_posts_columns', array( $this, 'clearance_product_admin_column' ), 15 );
			// Get Clearance price column.
			add_action( 'manage_product_posts_custom_column', array( $this, 'get_clearance_price_column' ), 10, 2 );
			// Change sale badge to clearance if a clearance price applies.
			add_filter( 'woocommerce_sale_flash', array( $this, 'clearance_price_replace_sale_text' ), 10, 3 );
		}

		/**
		 * Load plugin's textdomain.
		 */
		public function clearance_price_load_textdomain(): void {
			load_plugin_textdomain( 'clearance-price', false, dirname( CLEARANCE_PRICE_PLUGIN_PATH ) . '/languages' );
		}

		/**
		 * Enqueue the settings-page script only where it is needed.
		 *
		 * @param string $hook Current admin page hook suffix.
		 */
		public function clearance_price_admin_style( $hook ): void {
			if ( $hook !== $this->settings_hook ) {
				return;
			}
			wp_enqueue_script( 'clearance-price-settings', CLEARANCE_PRICE_PLUGIN_DIR_ASSETS_URL . 'js/scripts.js', array( 'jquery' ), CLEARANCE_PRICE_VERSION, true );
		}

		/**
		 * Create the settings page.
		 */
		public function clearance_price_menu_item(): void {
			$this->settings_hook = (string) add_options_page(
				__( 'Clearance Price for WooCommerce', 'clearance-price' ),
				__( 'Clearance Price for WooCommerce', 'clearance-price' ),
				'manage_options',
				'clearance_price',
				array( $this, 'clearance_price_page' )
			);
		}

		/**
		 * Callback for settings page.
		 */
		public function clearance_price_page(): void { ?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Clearance Price Options', 'clearance-price' ); ?></h1>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'clearance_price_settings_all' );
					do_settings_sections( 'clearance_price' );
					submit_button();
					?>
				</form>
			</div>
			<?php
		}

		/**
		 * Register settings, sections and fields.
		 *
		 * All fields are always registered; visibility of the dependent
		 * sections is handled client-side by assets/js/scripts.js.
		 */
		public function clearance_price_settings(): void {
			add_settings_section( 'clearance_price_general_section', __( 'General Options', 'clearance-price' ), null, 'clearance_price' );
			add_settings_section( 'clearance_price_product_type_section', __( 'Product type', 'clearance-price' ), null, 'clearance_price' );
			add_settings_section( 'clearance_price_date_section', __( 'Date Interval', 'clearance-price' ), null, 'clearance_price' );

			add_settings_field( 'clearance_price_cb', __( 'Use clearance price?', 'clearance-price' ), array( $this, 'clearance_price_cb_checkbox' ), 'clearance_price', 'clearance_price_general_section' );
			add_settings_field( 'clearance_price_simple_cb', __( 'Overwrite simple product price?', 'clearance-price' ), array( $this, 'clearance_price_simple_cb_checkbox' ), 'clearance_price', 'clearance_price_product_type_section' );
			add_settings_field( 'clearance_price_variable_cb', __( 'Overwrite variable product price?', 'clearance-price' ), array( $this, 'clearance_price_variable_cb_checkbox' ), 'clearance_price', 'clearance_price_product_type_section' );
			add_settings_field( 'clearance_price_from_date_cb', __( 'From date', 'clearance-price' ), array( $this, 'clearance_price_from_date_cb_date' ), 'clearance_price', 'clearance_price_date_section' );
			add_settings_field( 'clearance_price_to_date_cb', __( 'To date', 'clearance-price' ), array( $this, 'clearance_price_to_date_cb_date' ), 'clearance_price', 'clearance_price_date_section' );

			register_setting( 'clearance_price_settings_all', 'clearance_price_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
			register_setting( 'clearance_price_settings_all', 'clearance_price_simple_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
			register_setting( 'clearance_price_settings_all', 'clearance_price_variable_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
			register_setting( 'clearance_price_settings_all', 'clearance_price_from_date_cb', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_date' ), 'default' => '' ) );
			register_setting( 'clearance_price_settings_all', 'clearance_price_to_date_cb', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_date' ), 'default' => '' ) );
		}

		/**
		 * Normalise a checkbox value to 0/1.
		 *
		 * @param mixed $value Raw submitted value.
		 */
		public function sanitize_checkbox( $value ): int {
			return empty( $value ) ? 0 : 1;
		}

		/**
		 * Accept only an empty string or a valid Y-m-d date.
		 *
		 * @param mixed $value Raw submitted value.
		 */
		public function sanitize_date( $value ): string {
			$value = is_string( $value ) ? trim( $value ) : '';
			if ( '' === $value ) {
				return '';
			}
			$date = DateTimeImmutable::createFromFormat( 'Y-m-d', $value );
			return ( $date instanceof DateTimeImmutable && $date->format( 'Y-m-d' ) === $value ) ? $value : '';
		}

		/**
		 * Callback for input on settings page.
		 */
		public function clearance_price_cb_checkbox(): void { ?>
			<input type="checkbox" name="clearance_price_cb" value="1" <?php checked( 1, (int) get_option( 'clearance_price_cb' ) ); ?> /> <?php esc_html_e( 'Check for Yes', 'clearance-price' ); ?>
			<?php
		}

		/**
		 * Callback for input on settings page.
		 */
		public function clearance_price_simple_cb_checkbox(): void { ?>
			<input type="checkbox" name="clearance_price_simple_cb" value="1" <?php checked( 1, (int) get_option( 'clearance_price_simple_cb' ) ); ?> /> <?php esc_html_e( 'Check for Yes', 'clearance-price' ); ?>
			<?php
		}

		/**
		 * Callback for input on settings page.
		 */
		public function clearance_price_variable_cb_checkbox(): void { ?>
			<input type="checkbox" name="clearance_price_variable_cb" value="1" <?php checked( 1, (int) get_option( 'clearance_price_variable_cb' ) ); ?> /> <?php esc_html_e( 'Check for Yes', 'clearance-price' ); ?>
			<?php
		}

		public function clearance_price_from_date_cb_date(): void { ?>
			<input type="date" name="clearance_price_from_date_cb" value="<?php echo esc_attr( (string) get_option( 'clearance_price_from_date_cb', '' ) ); ?>" />
			<?php
		}

		public function clearance_price_to_date_cb_date(): void { ?>
			<input type="date" name="clearance_price_to_date_cb" value="<?php echo esc_attr( (string) get_option( 'clearance_price_to_date_cb', '' ) ); ?>" />
			<?php
		}

		/**
		 * Add Settings link in plugins page.
		 */
		public function add_action_links( $links ): array {
			$links   = is_array( $links ) ? $links : array();
			$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=clearance_price' ) ) . '">' . esc_html__( 'Settings', 'clearance-price' ) . '</a>';
			return $links;
		}

		/**
		 * Whether the master switch is on.
		 */
		private function is_enabled(): bool {
			return (bool) get_option( 'clearance_price_cb' );
		}

		/**
		 * Whether clearance pricing applies to simple products.
		 */
		private function simple_enabled(): bool {
			return $this->is_enabled() && (bool) get_option( 'clearance_price_simple_cb' );
		}

		/**
		 * Whether clearance pricing applies to variable products.
		 */
		private function variable_enabled(): bool {
			return $this->is_enabled() && (bool) get_option( 'clearance_price_variable_cb' );
		}

		/**
		 * Whether the clearance window is currently active.
		 *
		 * Dates are compared in the site timezone. An empty "from" date means
		 * active since forever, an empty "to" date means no end; the "to" day
		 * itself is fully included.
		 */
		private function is_clearance_window_active(): bool {
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
		private function get_clearance_price( int $object_id ): string {
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
		 */
		private function product_has_clearance( WC_Product $product ): bool {
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

		/**
		 * Use clearance price instead of default price on product or variation
		 * in the cart. It is used only if it exists.
		 *
		 * @param WC_Cart $cart Cart object.
		 */
		public function clearance_price_calculate_totals( $cart ): void {
			if ( ! $cart instanceof WC_Cart || ! $this->is_clearance_window_active() ) {
				return;
			}

			foreach ( $cart->get_cart() as $cart_item ) {
				$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$clearance_price = '';
				if ( ! empty( $cart_item['variation_id'] ) ) {
					if ( $this->variable_enabled() ) {
						$clearance_price = $this->get_clearance_price( (int) $cart_item['variation_id'] );
					}
				} elseif ( $this->simple_enabled() ) {
					$clearance_price = $this->get_clearance_price( (int) $cart_item['product_id'] );
				}

				if ( '' !== $clearance_price ) {
					$product->set_price( $clearance_price );
				}
			}
		}

		/**
		 * Display clearance price HTML input in product admin (Simple Product).
		 */
		public function woo_clearance_price_field(): void {
			if ( ! $this->is_enabled() ) {
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
		 * Save clearance price in product meta (Simple Product).
		 *
		 * Nonce and intent are verified upstream by WooCommerce before the
		 * woocommerce_process_product_meta action fires.
		 *
		 * @param int $post_id Product ID.
		 */
		public function woo_clearance_price_save( $post_id ): void {
			$post_id = (int) $post_id;
			// For variable products the field is submitted as an array keyed
			// by variation ID and handled by woo_clearance_price_save_variation().
			if ( ! isset( $_POST['_clearance_price'] ) || is_array( $_POST['_clearance_price'] ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_product', $post_id ) ) {
				return;
			}
			$this->update_clearance_price_meta( $post_id, wp_unslash( $_POST['_clearance_price'] ) );
		}

		/**
		 * Display Clearance Field in Admin (Variable Product).
		 *
		 * @param int     $loop           Position in the loop.
		 * @param array   $variation_data Variation data.
		 * @param WP_Post $variation      Variation post object.
		 */
		public function woo_clearance_price_field_variation( $loop, $variation_data, $variation ): void {
			if ( ! $this->is_enabled() || ! isset( $variation->ID ) ) {
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
		public function woo_clearance_price_save_variation( $variation_id, $i = 0 ): void {
			$variation_id = (int) $variation_id;
			if ( ! isset( $_POST['_clearance_price'] ) || ! is_array( $_POST['_clearance_price'] ) || ! isset( $_POST['_clearance_price'][ $variation_id ] ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_product', $variation_id ) ) {
				return;
			}
			$this->update_clearance_price_meta( $variation_id, wp_unslash( $_POST['_clearance_price'][ $variation_id ] ) );
		}

		/**
		 * Sanitise a submitted clearance price and persist it.
		 *
		 * @param int   $object_id Product or variation ID.
		 * @param mixed $raw_price Raw submitted value.
		 */
		private function update_clearance_price_meta( int $object_id, $raw_price ): void {
			$price = is_scalar( $raw_price ) ? wc_format_decimal( (string) $raw_price ) : '';
			if ( '' === $price || ! is_numeric( $price ) || (float) $price < 0 ) {
				delete_post_meta( $object_id, '_clearance_price' );
				return;
			}
			update_post_meta( $object_id, '_clearance_price', $price );
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
		public function clearance_price_html( $price, $product ) {
			if ( ! $product instanceof WC_Product || ! $this->is_clearance_window_active() ) {
				return $price;
			}

			if ( ( $product->is_type( 'simple' ) && $this->simple_enabled() )
				|| ( $product->is_type( 'variation' ) && $this->variable_enabled() ) ) {
				$clearance_price = $this->get_clearance_price( $product->get_id() );
				if ( '' !== $clearance_price ) {
					return wc_price( wc_get_price_to_display( $product, array( 'price' => (float) $clearance_price ) ) ) . $product->get_price_suffix();
				}
			}

			if ( $product->is_type( 'variable' ) && $this->variable_enabled() ) {
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
				$clearance_price = $this->get_clearance_price( (int) $child_id );
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
		 * Add Product Clearance price column in products listing.
		 */
		public function clearance_product_admin_column( $columns ): array {
			$columns = is_array( $columns ) ? $columns : array();
			if ( ! $this->is_enabled() ) {
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
		 * Get Clearance price column.
		 *
		 * @param string $column  Column key.
		 * @param int    $post_id Product ID.
		 */
		public function get_clearance_price_column( $column, $post_id ): void {
			if ( 'clearance_price' !== $column || ! $this->is_enabled() ) {
				return;
			}
			$clearance_price = $this->get_clearance_price( (int) $post_id );
			if ( '' !== $clearance_price ) {
				echo wp_kses_post( wc_price( (float) $clearance_price ) );
			} else {
				esc_html_e( 'Not filled', 'clearance-price' );
			}
		}

		/**
		 * Change sale badge with a clearance one if a clearance price applies.
		 *
		 * @param string     $html    Badge HTML.
		 * @param WP_Post    $post    Post object.
		 * @param WC_Product $product Product object.
		 */
		public function clearance_price_replace_sale_text( $html, $post = null, $product = null ) {
			if ( ! $product instanceof WC_Product || ! $this->is_clearance_window_active() ) {
				return $html;
			}
			if ( $this->product_has_clearance( $product ) ) {
				return '<span class="onsale clearance-badge">' . esc_html__( 'Clearance!', 'clearance-price' ) . '</span>';
			}
			return $html;
		}
	}
}
