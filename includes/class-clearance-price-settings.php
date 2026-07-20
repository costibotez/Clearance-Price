<?php
declare( strict_types=1 );
/**
 * Settings page, settings registration and admin assets.
 *
 * @package Clearance_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clearance_Price_Settings {

	/**
	 * Hook suffix of the settings page, used to scope admin assets.
	 *
	 * @var string
	 */
	private $settings_hook = '';

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_item' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . CLEARANCE_PRICE_PLUGIN_PATH, array( $this, 'add_action_links' ) );
	}

	/**
	 * Enqueue the settings-page script only where it is needed.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ): void {
		if ( $hook !== $this->settings_hook ) {
			return;
		}
		wp_enqueue_script( 'clearance-price-settings', CLEARANCE_PRICE_PLUGIN_DIR_ASSETS_URL . 'js/scripts.js', array( 'jquery' ), CLEARANCE_PRICE_VERSION, true );
	}

	/**
	 * Create the settings page.
	 */
	public function add_menu_item(): void {
		$this->settings_hook = (string) add_options_page(
			__( 'Clearance Price for WooCommerce', 'clearance-price' ),
			__( 'Clearance Price for WooCommerce', 'clearance-price' ),
			'manage_options',
			'clearance_price',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Callback for settings page.
	 */
	public function render_page(): void { ?>
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
	public function register_settings(): void {
		add_settings_section( 'clearance_price_general_section', __( 'General Options', 'clearance-price' ), null, 'clearance_price' );
		add_settings_section( 'clearance_price_product_type_section', __( 'Product type', 'clearance-price' ), null, 'clearance_price' );
		add_settings_section( 'clearance_price_date_section', __( 'Date Interval', 'clearance-price' ), null, 'clearance_price' );
		add_settings_section( 'clearance_price_display_section', __( 'Display', 'clearance-price' ), null, 'clearance_price' );

		add_settings_field( 'clearance_price_cb', __( 'Use clearance price?', 'clearance-price' ), array( $this, 'render_master_checkbox' ), 'clearance_price', 'clearance_price_general_section' );
		add_settings_field( 'clearance_price_simple_cb', __( 'Overwrite simple product price?', 'clearance-price' ), array( $this, 'render_simple_checkbox' ), 'clearance_price', 'clearance_price_product_type_section' );
		add_settings_field( 'clearance_price_variable_cb', __( 'Overwrite variable product price?', 'clearance-price' ), array( $this, 'render_variable_checkbox' ), 'clearance_price', 'clearance_price_product_type_section' );
		add_settings_field( 'clearance_price_from_date_cb', __( 'From date', 'clearance-price' ), array( $this, 'render_from_date' ), 'clearance_price', 'clearance_price_date_section' );
		add_settings_field( 'clearance_price_to_date_cb', __( 'To date', 'clearance-price' ), array( $this, 'render_to_date' ), 'clearance_price', 'clearance_price_date_section' );
		add_settings_field( 'clearance_price_show_original_cb', __( 'Show original price struck through?', 'clearance-price' ), array( $this, 'render_show_original_checkbox' ), 'clearance_price', 'clearance_price_display_section' );

		register_setting( 'clearance_price_settings_all', 'clearance_price_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_simple_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_variable_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_show_original_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
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
	public function render_master_checkbox(): void { ?>
		<input type="checkbox" name="clearance_price_cb" value="1" <?php checked( 1, (int) get_option( 'clearance_price_cb' ) ); ?> /> <?php esc_html_e( 'Check for Yes', 'clearance-price' ); ?>
		<?php
	}

	/**
	 * Callback for input on settings page.
	 */
	public function render_simple_checkbox(): void { ?>
		<input type="checkbox" name="clearance_price_simple_cb" value="1" <?php checked( 1, (int) get_option( 'clearance_price_simple_cb' ) ); ?> /> <?php esc_html_e( 'Check for Yes', 'clearance-price' ); ?>
		<?php
	}

	/**
	 * Callback for input on settings page.
	 */
	public function render_variable_checkbox(): void { ?>
		<input type="checkbox" name="clearance_price_variable_cb" value="1" <?php checked( 1, (int) get_option( 'clearance_price_variable_cb' ) ); ?> /> <?php esc_html_e( 'Check for Yes', 'clearance-price' ); ?>
		<?php
	}

	/**
	 * Callback for input on settings page.
	 */
	public function render_show_original_checkbox(): void { ?>
		<input type="checkbox" name="clearance_price_show_original_cb" value="1" <?php checked( 1, (int) get_option( 'clearance_price_show_original_cb' ) ); ?> /> <?php esc_html_e( 'Check for Yes', 'clearance-price' ); ?>
		<?php
	}

	public function render_from_date(): void { ?>
		<input type="date" name="clearance_price_from_date_cb" value="<?php echo esc_attr( (string) get_option( 'clearance_price_from_date_cb', '' ) ); ?>" />
		<?php
	}

	public function render_to_date(): void { ?>
		<input type="date" name="clearance_price_to_date_cb" value="<?php echo esc_attr( (string) get_option( 'clearance_price_to_date_cb', '' ) ); ?>" />
		<?php
	}

	/**
	 * Add Settings link in plugins page.
	 *
	 * @param array $links Existing action links.
	 */
	public function add_action_links( $links ): array {
		$links   = is_array( $links ) ? $links : array();
		$links[] = '<a href="' . esc_url( admin_url( 'options-general.php?page=clearance_price' ) ) . '">' . esc_html__( 'Settings', 'clearance-price' ) . '</a>';
		return $links;
	}
}
