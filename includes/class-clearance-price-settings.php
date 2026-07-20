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
			<?php
			/**
			 * Fires after the settings form, inside the wrap. Used by the
			 * bulk-apply tool to render its own form.
			 */
			do_action( 'clearance_price_settings_after_form' );
			?>
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
		add_settings_field( 'clearance_price_countdown_cb', __( 'Show "clearance ends in" notice on product pages?', 'clearance-price' ), array( $this, 'render_countdown_checkbox' ), 'clearance_price', 'clearance_price_display_section' );
		add_settings_field( 'clearance_price_badge_text', __( 'Badge text', 'clearance-price' ), array( $this, 'render_badge_text' ), 'clearance_price', 'clearance_price_display_section' );
		add_settings_field( 'clearance_price_badge_bg', __( 'Badge background colour', 'clearance-price' ), array( $this, 'render_badge_bg' ), 'clearance_price', 'clearance_price_display_section' );
		add_settings_field( 'clearance_price_badge_color', __( 'Badge text colour', 'clearance-price' ), array( $this, 'render_badge_color' ), 'clearance_price', 'clearance_price_display_section' );

		register_setting( 'clearance_price_settings_all', 'clearance_price_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_simple_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_variable_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_show_original_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_countdown_cb', array( 'type' => 'boolean', 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => 0 ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_badge_text', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_badge_bg', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_color' ), 'default' => '' ) );
		register_setting( 'clearance_price_settings_all', 'clearance_price_badge_color', array( 'type' => 'string', 'sanitize_callback' => array( $this, 'sanitize_color' ), 'default' => '' ) );
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
		return Clearance_Price_Options::sanitize_date_value( $value );
	}

	/**
	 * Accept only an empty string or a #rgb/#rrggbb hex colour.
	 *
	 * @param mixed $value Raw submitted value.
	 */
	public function sanitize_color( $value ): string {
		return Clearance_Price_Options::sanitize_color_value( $value );
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

	/**
	 * Callback for input on settings page.
	 */
	public function render_countdown_checkbox(): void { ?>
		<input type="checkbox" name="clearance_price_countdown_cb" value="1" <?php checked( 1, (int) get_option( 'clearance_price_countdown_cb' ) ); ?> /> <?php esc_html_e( 'Check for Yes', 'clearance-price' ); ?>
		<?php
	}

	/**
	 * Callback for input on settings page.
	 */
	public function render_badge_text(): void { ?>
		<input type="text" class="regular-text" name="clearance_price_badge_text" value="<?php echo esc_attr( (string) get_option( 'clearance_price_badge_text', '' ) ); ?>" placeholder="<?php esc_attr_e( 'Clearance!', 'clearance-price' ); ?>" />
		<?php
	}

	/**
	 * Callback for input on settings page.
	 */
	public function render_badge_bg(): void { ?>
		<input type="text" class="small-text" name="clearance_price_badge_bg" value="<?php echo esc_attr( (string) get_option( 'clearance_price_badge_bg', '' ) ); ?>" placeholder="#rrggbb" />
		<p class="description"><?php esc_html_e( 'Leave empty for the theme default.', 'clearance-price' ); ?></p>
		<?php
	}

	/**
	 * Callback for input on settings page.
	 */
	public function render_badge_color(): void { ?>
		<input type="text" class="small-text" name="clearance_price_badge_color" value="<?php echo esc_attr( (string) get_option( 'clearance_price_badge_color', '' ) ); ?>" placeholder="#rrggbb" />
		<p class="description"><?php esc_html_e( 'Leave empty for the theme default.', 'clearance-price' ); ?></p>
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
