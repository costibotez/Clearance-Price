<?php
declare( strict_types=1 );
/**
 * Main plugin class: wires the components together.
 *
 * @package Clearance_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Clearance_Price_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Shared option helpers.
	 *
	 * @var Clearance_Price_Options
	 */
	public $options;

	/**
	 * Settings page component.
	 *
	 * @var Clearance_Price_Settings
	 */
	public $settings;

	/**
	 * Product edit screen component.
	 *
	 * @var Clearance_Price_Product_Meta
	 */
	public $product_meta;

	/**
	 * Front-end pricing component.
	 *
	 * @var Clearance_Price_Price_Manager
	 */
	public $price_manager;

	/**
	 * Admin product list component.
	 *
	 * @var Clearance_Price_Admin_Columns
	 */
	public $admin_columns;

	/**
	 * Bulk-apply tool component.
	 *
	 * @var Clearance_Price_Bulk
	 */
	public $bulk;

	/**
	 * [clearance_products] shortcode component.
	 *
	 * @var Clearance_Price_Shortcode
	 */
	public $shortcode;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->options       = new Clearance_Price_Options();
		$this->settings      = new Clearance_Price_Settings();
		$this->product_meta  = new Clearance_Price_Product_Meta( $this->options );
		$this->price_manager = new Clearance_Price_Price_Manager( $this->options );
		$this->admin_columns = new Clearance_Price_Admin_Columns( $this->options );
		$this->bulk          = new Clearance_Price_Bulk( $this->options );
		$this->shortcode     = new Clearance_Price_Shortcode( $this->options );

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin's textdomain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'clearance-price', false, dirname( CLEARANCE_PRICE_PLUGIN_PATH ) . '/languages' );
	}
}
