<?php
declare(strict_types=1);
/*
 * Plugin Name: Clearance Price for Products
 * Description: Add a custom price for products to be calculated in a different way.
 * Author: Botez Costin
 * Version: 1.1
 * Author URI: https://nomad-developer.co.uk/
 * Requires PHP: 7.4
 */


if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

register_activation_hook( __FILE__, 'clearance_price_require_php_version' );

function clearance_price_require_php_version(): void {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( sprintf( esc_html__( 'Clearance Price for Products requires PHP %s or higher.', 'clearance-price' ), '7.4' ) );
    }
}
// only if WooCommerce is active
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

       define( 'CLEARANCE_PRICE_PLUGIN_PATH', plugin_basename(__FILE__));
       define( 'CLEARANCE_PRICE_PLUGIN_DIR_ASSETS_URL', plugin_dir_url(__FILE__) . 'assets/');

       if ( ! class_exists( 'WC_Clearance_Price' ) ) {
               class WC_Clearance_Price {
                public function __construct() {
			// Load textdomain
			add_action( 'plugins_loaded', array($this, 'clearance_price_load_textdomain' ));
			// Enqueue front-end scripts if needed
			add_action('wp_enqueue_scripts', array($this, 'clearance_price_style'));
			// Enqueue back-end scripts if needed
		    add_action('admin_enqueue_scripts', array($this, 'clearance_price_admin_style' ));
		    // Add settings page
			add_action('admin_menu', array($this, 'clearance_price_menu_item'));
			// Add settings field
			add_action('admin_init', array($this, 'clearance_price_settings'));
			// Add "Settings" in plugins page
		    add_filter('plugin_action_links_' . CLEARANCE_PRICE_PLUGIN_PATH, array($this, 'add_action_links' ));
		    // Overwrite regular price with clearance one if needed
		    add_action( 'woocommerce_before_calculate_totals', array($this, 'clearance_price_calculate_totals' ));
		    // Display Clearance Field in Admin (Simple Product)
			add_action( 'woocommerce_product_options_general_product_data', array($this, 'woo_clearance_price_field' ));
			// Save Clearance Field in Admin (Simple product)
			add_action( 'woocommerce_process_product_meta', array($this, 'woo_clearance_price_save' ));
			// Display Clearance Field in Admin (Variable Product)
			add_action( 'woocommerce_product_after_variable_attributes', array($this, 'woo_clearance_price_field_variation' ), 10, 3 );
			// Save Clearance Field in Admin (Variable product)
			add_action( 'woocommerce_save_product_variation', array($this, 'woo_clearance_price_save_variation' ), 10, 2 );
			// Remove Variable Product Prices Everywhere
			add_filter( 'woocommerce_variable_sale_price_html', array($this, 'clearance_price_remove_variation_price'), 10, 2 );
			add_filter( 'woocommerce_variable_price_html', array($this, 'clearance_price_remove_variation_price'), 10, 2 );
			// Change price on product
			add_filter( 'woocommerce_get_price_html', array($this, 'clearance_price_html'), 200, 2 );
			// Add Admin Column in product listing
			add_filter( 'manage_product_posts_columns', array($this, 'clearance_product_admin_column'),15 );
			// Get Clearance price column
			add_action( 'manage_product_posts_custom_column' , array($this, 'get_clearance_price_column'), 10, 2 );
			// Change sale text with clearance if exists
			add_filter( 'woocommerce_sale_flash', array($this, 'clearance_price_replace_sale_text' ));
			// Remove option when uninstall is triggered
		    register_uninstall_hook(__FILE__, array($this, 'clearance_price_plugin_uninstall'));
		}

		/**
		 * Load plugin's textdomain
		 */
                public function clearance_price_load_textdomain(): void {
			load_plugin_textdomain( 'clearance-price', false, dirname( CLEARANCE_PRICE_PLUGIN_PATH ) . '/languages/' );
		}

                public function clearance_price_style(): void {
			wp_register_style('clearance-price-style', CLEARANCE_PRICE_PLUGIN_DIR_ASSETS_URL . 'css/style.css');
	    	wp_enqueue_style('clearance-price-style');
		}

                public function clearance_price_admin_style(): void {
	    	wp_enqueue_script( 'clearance-price-handle', CLEARANCE_PRICE_PLUGIN_DIR_ASSETS_URL . 'js/scripts.js', array(), false, true );
		}

		/**
		 * Create the settings page
		 */
                public function clearance_price_menu_item(): void {
			add_options_page('Clearance Price for WooCommerce', 'Clearance Price for WooCommerce', 'manage_options', 'clearance_price', array($this, 'clearance_price_page'));
		}

		/**
		 * Callback for settings page
		 */
                public function clearance_price_page(): void { ?>
			<div class="wrap">
	      		<h1><?php _e('Clearance Price Options', 'clearance-price'); ?></h1>
	      		<form method="post" action="options.php">
			        <?php
			          settings_fields('clearance_price_settings_all');
			          do_settings_sections('clearance_price');
			          submit_button();
			        ?>
	      		</form>
	    	</div>
			<?php
		}

		/**
		 * Add the option on settings page
		 */
                public function clearance_price_settings(): void {
			// Sections
			add_settings_section('clearance_price_general_section', __('General Options', 'clearance-price'), null, 'clearance_price');

			// General section
			add_settings_field('clearance_price_cb', 	__('Use clearance price?', 'clearance-price'), 	array($this, 'clearance_price_cb_checkbox'), 'clearance_price', 'clearance_price_general_section');
			if(get_option('clearance_price_cb') == 1) {
				add_settings_section('clearance_price_product_type_section', __('Product type', 'clearance-price'), null, 'clearance_price');
				add_settings_section('clearance_price_date_section', __('Date Interval', 'clearance-price'), null, 'clearance_price');
				// Product type section
				add_settings_field('clearance_price_simple_cb', __('Overwrite simple product price?', 'clearance-price'), array($this, 'clearance_price_simple_cb_checkbox'), 'clearance_price', 'clearance_price_product_type_section');
				add_settings_field('clearance_price_variable_cb', __('Overwrite variable product price?', 'clearance-price'), array($this, 'clearance_price_variable_cb_checkbox'), 'clearance_price', 'clearance_price_product_type_section');
				add_settings_field( 'clearance_price_from_date_cb', __('From date', 'clearance-price'), array($this, 'clearance_price_from_date_cb_date'), 'clearance_price', 'clearance_price_date_section' );
				add_settings_field( 'clearance_price_to_date_cb', __('To date', 'clearance-price'), array($this, 'clearance_price_to_date_cb_date'), 'clearance_price', 'clearance_price_date_section' );

				register_setting('clearance_price_settings_all', 'clearance_price_simple_cb', 'intval');
				register_setting('clearance_price_settings_all', 'clearance_price_variable_cb', 'intval');
				register_setting('clearance_price_settings_all', 'clearance_price_from_date_cb', 'strval');
				register_setting('clearance_price_settings_all', 'clearance_price_to_date_cb', 'strval');
			} else {
				update_option('clearance_price_simple_cb', 0);
				update_option('clearance_price_variable_cb', 0);
				update_option('clearance_price_from_date_cb', "");
	    		update_option('clearance_price_to_date_cb', "");
			}
			// Sava sections options
			register_setting('clearance_price_settings_all', 'clearance_price_cb', 'intval');

		}

		/**
		 * Callback for input on settings page
		 */
                public function clearance_price_cb_checkbox(): void { ?>
	    	<input type="checkbox" name="clearance_price_cb" value="1" <?php checked(1, get_option('clearance_price_cb'), true); ?> /> <?php _e('Check for Yes', 'clearance-price'); ?>
	 		<?php
		}

		/**
		 * Callback for input on settings page
		 */
                public function clearance_price_simple_cb_checkbox(): void { ?>
	    	<input type="checkbox" name="clearance_price_simple_cb" value="1" <?php checked(1, get_option('clearance_price_simple_cb'), true); ?> /> <?php _e('Check for Yes', 'clearance-price'); ?>
	 		<?php
		}

		/**
		 * Callback for input on settings page
		 */
                public function clearance_price_variable_cb_checkbox(): void { ?>
	    	<input type="checkbox" name="clearance_price_variable_cb" value="1" <?php checked(1, get_option('clearance_price_variable_cb'), true); ?> /> <?php _e('Check for Yes', 'clearance-price'); ?>
	 		<?php
		}

                public function clearance_price_from_date_cb_date(): void{ ?>
     		<input type="date" name="clearance_price_from_date_cb" value="<?php echo get_option('clearance_price_from_date_cb'); ?>" />
     		<?php
		}

                public function clearance_price_to_date_cb_date(): void{ ?>
     		<input type="date" name="clearance_price_to_date_cb" value="<?php echo get_option('clearance_price_to_date_cb'); ?>" />
     		<?php
		}

		/**
		 * Add Settings link in plugins page
		 */
                public function add_action_links( array $links ): array {
		    $mylinks = array(
		      '<a href="' . admin_url( 'options-general.php?page=clearance_price' ) . '">' . __('Settings', 'clearance_price') .  '</a>',
		    );
		    return array_merge( $links, $mylinks );
	  	}

	  	/**
	  	 * 	Use clearance price instead of default price on product or variation product
	  	 *	It is used only if it exists
	  	 */
               public function clearance_price_calculate_totals( WC_Cart $cart ): void {
                       $server_time = current_time( 'timestamp' );
                       $from_date   = strtotime( get_option( 'clearance_price_from_date_cb' ) );
                       $to_date     = strtotime( get_option( 'clearance_price_to_date_cb' ) );

                       if ( $from_date <= $server_time && $server_time <= $to_date && get_option( 'clearance_price_cb' ) ) {
                               foreach ( $cart->get_cart() as $cart_item ) {
                                       $product                = $cart_item['data'];
                                       $product_clearance_price = $product->get_price();

                                       if ( get_option( 'clearance_price_simple_cb' ) ) {
                                               $meta = get_post_meta( $cart_item['product_id'], '_clearance_price', true );
                                               if ( '' !== $meta && 0 != $meta ) {
                                                       $product_clearance_price = $meta;
                                               }
                                       }

                                       if ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] && get_option( 'clearance_price_variable_cb' ) ) {
                                               $meta = get_post_meta( $cart_item['variation_id'], '_clearance_price', true );
                                               if ( '' !== $meta ) {
                                                       $product_clearance_price = $meta;
                                               }
                                       }

                                       $product->set_price( $product_clearance_price );
                               }
                       }
               }

		/**
		 * Display clearance price HTML input in product admin (Simple Product)
		 */
                public function woo_clearance_price_field(): void {
	  		global $woocommerce, $post;
	  		// Show clearance price on product
	  		if(get_option('clearance_price_cb') == 1) {
		  		echo '<div class="options_group show_if_simple">';
				woocommerce_wp_text_input(
					array(
						'id'                => '_clearance_price',
						'label'             => __( 'Clearance Price', 'clearance-price' ),
						'placeholder'       => '',
						'desc_tip'   	 	=> 'true',
						'description'       => __( 'Enter the custom price here. Leave empty to user regular price', 'clearance-price' ),
					)
				);
		  		echo '</div>';
		  	}
		}

		/**
		 * Save clearance price in product meta (Simple Product)
		 */
                public function woo_clearance_price_save( int $post_id ): void{
			if(get_option('clearance_price_cb') == 1) {
				$woocommerce_clearance_price = $_POST['_clearance_price'];
				// if( !empty( $woocommerce_clearance_price ) )
				update_post_meta( $post_id, '_clearance_price', esc_attr( $woocommerce_clearance_price ) );
			}
		}

		/**
		 * Display Clearance Field in Admin (Variable Product)
		 */
                public function woo_clearance_price_field_variation( int $loop, array $variation_data, $variation ): void {
			if(get_option('clearance_price_cb') == 1) {
				woocommerce_wp_text_input(
					array(
						'id'          => '_clearance_price[' . $variation->ID . ']',
						'label'       => __( 'Clearance Price', 'clearance-price' ),
						'placeholder' => '',
						'desc_tip'    => 'true',
						'description' => __( 'Enter the custom price here. Leave empty to user regular price', 'clearance-price' ),
						'value'       => get_post_meta( $variation->ID, '_clearance_price', true )
					)
				);
			}
		}

		/**
		 * Save Clearance Field in Admin (Variable product)
		 */
                public function woo_clearance_price_save_variation( int $post_id ): void {
			if(get_option('clearance_price_cb') == 1) {
				$woocommerce_clearance_price = $_POST['_clearance_price'][ $post_id ];
				// if( ! empty( $woocommerce_clearance_price ) )
				update_post_meta( $post_id, '_clearance_price', esc_attr( $woocommerce_clearance_price ) );
			}
		}

		/**
		 * Remove Variable product range
		 */
                public function clearance_price_remove_variation_price( $price, $product ) {
			$server_time = strtotime(current_time( 'Y-m-d' ));
			$from_date = strtotime(get_option('clearance_price_from_date_cb'));
			$to_date = strtotime(get_option('clearance_price_to_date_cb'));

			if($from_date <= $server_time && $server_time <= $to_date) {
				$price = '';
			}
			return $price;
		}

		/**
		 * Change front-end price with clearance price
		 */
               public function clearance_price_html( $price, $product ){
                       $server_time = current_time( 'timestamp' );
                       $from_date   = strtotime( get_option( 'clearance_price_from_date_cb' ) );
                       $to_date     = strtotime( get_option( 'clearance_price_to_date_cb' ) );

                       if ( $from_date <= $server_time && $server_time <= $to_date && get_option( 'clearance_price_cb' ) ) {
                               if ( $product->is_type( 'simple' ) ) {
                                       $clearance_price = get_post_meta( $product->get_id(), '_clearance_price', true );
                                       if ( ! empty( $clearance_price ) ) {
                                               return wc_price( $clearance_price );
                                       }
                               }
                       }

                       return $price;
               }

		/**
		 * Add Product Clearance price column in products listing
		 */
                public function clearance_product_admin_column($columns){
			$new = array();

			foreach ($columns as $key => $value) {
				$new[$key] = $value;
				if($key == 'price' && get_option('clearance_price_cb') == 1) {
					$new['clearance_price'] = __( 'Clearance Price', 'clearance-price');
				}
			}
		   	return $new;
		}


		/**
		 * Get Clearance price column
		 */
                public function get_clearance_price_column($column, $post_id): void {
			if(get_option('clearance_price_cb') == 1) {
				if($column == 'clearance_price') {
					$clearance_price = get_post_meta( $post_id , '_clearance_price' , true);
		            if (!empty( $clearance_price ))
		                echo $clearance_price;
		            else
		                _e( 'Not filled', 'clearance-price' );
				}
			}
		}

		/**
		 * Delete option from database when uninstall
		 */
                public function clearance_price_plugin_uninstall(): void {
	    	delete_option('clearance_price_cb');
	    	delete_option('clearance_price_simple_cb');
	    	delete_option('clearance_price_variable_cb');
	    	delete_option('clearance_price_from_date_cb');
	    	delete_option('clearance_price_to_date_cb');
		}

		/**
		 * Change sale text with clearance if exists
		 */
                public function clearance_price_replace_sale_text( string $html ) {
			global $product;
			if(get_option('clearance_price_cb') == 1) {
                                if ( $product instanceof WC_Product_Simple && get_option( 'clearance_price_simple_cb' ) == 1 ) {
                                        $product_clearance_price = get_post_meta( $product->get_id(), '_clearance_price', true );
			    	if(!(empty($product_clearance_price)) && $product_clearance_price != 0) {
			    		return str_replace( __( 'Sale!', 'woocommerce' ), __( 'Clearance!', 'woocommerce' ), $html );
			    	}
			    }
                            if ( $product instanceof WC_Product_Variable && get_option( 'clearance_price_variable_cb' ) == 1 ) {
			    	$show_sale_text = 1;
			    	foreach ($product->get_available_variations() as $key => $_product) {
			    		$variation_clearance_price = get_post_meta($_product['variation_id'], '_clearance_price', true);
			    		if(!(empty($variation_clearance_price)) && $variation_clearance_price != 0) {
			    			$show_sale_text = 0;
			    		}
			    	}
			    	if($show_sale_text == 0) {
			    		return str_replace( __( 'Sale!', 'woocommerce' ), __( 'Clearance!', 'woocommerce' ), $html );
			    	}
			    }
		    }
		    return $html;
               }
               }
       }

       new WC_Clearance_Price();
}

?>
