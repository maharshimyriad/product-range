<?php
/**
 * Admin functionality.
 *
 * @package WC_Product_Range_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Range_Fields_Admin' ) ) {
	/**
	 * Handles WooCommerce admin fields.
	 */
	class WC_Product_Range_Fields_Admin {
		/**
		 * Plugin file path.
		 *
		 * @var string
		 */
		private $plugin_file;

		/**
		 * Constructor.
		 *
		 * @param string $plugin_file Main plugin file path.
		 */
		public function __construct( $plugin_file ) {
			$this->plugin_file = $plugin_file;
		}

		/**
		 * Register admin hooks.
		 *
		 * @return void
		 */
		public function hooks() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_simple_fields' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_simple_fields' ) );
			add_action( 'woocommerce_variation_options', array( $this, 'render_variation_fields' ), 5, 3 );
			add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
		}

		/**
		 * Load admin CSS/JS on product edit screens.
		 *
		 * @param string $hook_suffix Current admin page.
		 * @return void
		 */
		public function enqueue_assets( $hook_suffix ) {
			if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( ! $screen || 'product' !== $screen->id ) {
				return;
			}

			wp_enqueue_style(
				'wc-product-range-fields-admin',
				plugin_dir_url( $this->plugin_file ) . 'assets/css/admin.css',
				array(),
				WC_Product_Range_Fields::VERSION
			);

			wp_enqueue_script(
				'wc-product-range-fields-admin',
				plugin_dir_url( $this->plugin_file ) . 'assets/js/admin.js',
				array( 'jquery' ),
				WC_Product_Range_Fields::VERSION,
				true
			);

			wp_localize_script(
				'wc-product-range-fields-admin',
				'wcProductRangeFields',
				array(
					'enabledPrefix' => WC_Product_Range_Fields::META_ENABLED,
				)
			);
		}

		/**
		 * Render simple product fields.
		 *
		 * @return void
		 */
		public function render_simple_fields() {
			echo '<div class="options_group wc-product-range-fields show_if_simple">';

			woocommerce_wp_checkbox(
				array(
					'id'            => WC_Product_Range_Fields::META_ENABLED,
					'label'         => __( 'Enable range fields', 'wc-product-range-fields' ),
					'description'   => __( 'Show min and max range fields for this simple product.', 'wc-product-range-fields' ),
					'desc_tip'      => true,
					'value'         => get_post_meta( get_the_ID(), WC_Product_Range_Fields::META_ENABLED, true ),
					'wrapper_class' => 'range-toggle-wrapper',
				)
			);

			echo '<div class="range-fields-group">';

			woocommerce_wp_text_input(
				array(
					'id'                => WC_Product_Range_Fields::META_MIN,
					'label'             => __( 'Min range', 'wc-product-range-fields' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
					),
					'value'             => get_post_meta( get_the_ID(), WC_Product_Range_Fields::META_MIN, true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => WC_Product_Range_Fields::META_MAX,
					'label'             => __( 'Max range', 'wc-product-range-fields' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
					),
					'value'             => get_post_meta( get_the_ID(), WC_Product_Range_Fields::META_MAX, true ),
				)
			);

			echo '</div>';
			echo '</div>';
		}

		/**
		 * Save simple product fields.
		 *
		 * @param int $product_id Product ID.
		 * @return void
		 */
		public function save_simple_fields( $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->is_type( 'simple' ) ) {
				return;
			}

			$enabled = isset( $_POST[ WC_Product_Range_Fields::META_ENABLED ] ) ? 'yes' : 'no';

			update_post_meta( $product_id, WC_Product_Range_Fields::META_ENABLED, $enabled );
			update_post_meta( $product_id, WC_Product_Range_Fields::META_MIN, $this->sanitize_range_value( $_POST[ WC_Product_Range_Fields::META_MIN ] ?? '' ) );
			update_post_meta( $product_id, WC_Product_Range_Fields::META_MAX, $this->sanitize_range_value( $_POST[ WC_Product_Range_Fields::META_MAX ] ?? '' ) );
		}

		/**
		 * Render variation fields.
		 *
		 * @param int     $loop Variation loop index.
		 * @param array   $variation_data Variation data.
		 * @param WP_Post $variation Variation post object.
		 * @return void
		 */
		public function render_variation_fields( $loop, $variation_data, $variation ) {
			$enabled = get_post_meta( $variation->ID, WC_Product_Range_Fields::META_ENABLED, true );
			$min     = get_post_meta( $variation->ID, WC_Product_Range_Fields::META_MIN, true );
			$max     = get_post_meta( $variation->ID, WC_Product_Range_Fields::META_MAX, true );

			echo '<div class="form-row form-row-full wc-product-range-fields range-toggle-wrapper">';

			woocommerce_wp_checkbox(
				array(
					'id'            => WC_Product_Range_Fields::META_ENABLED . '[' . $loop . ']',
					'name'          => WC_Product_Range_Fields::META_ENABLED . '[' . $loop . ']',
					'label'         => __( 'Enable range fields', 'wc-product-range-fields' ),
					'description'   => __( 'Show min and max range fields for this variation.', 'wc-product-range-fields' ),
					'desc_tip'      => true,
					'value'         => $enabled,
					'cbvalue'       => 'yes',
					'wrapper_class' => 'form-row form-row-full',
				)
			);

			echo '</div>';
			echo '<div class="range-fields-group wc-product-range-fields">';

			woocommerce_wp_text_input(
				array(
					'id'                => WC_Product_Range_Fields::META_MIN . '[' . $loop . ']',
					'name'              => WC_Product_Range_Fields::META_MIN . '[' . $loop . ']',
					'label'             => __( 'Min range', 'wc-product-range-fields' ),
					'type'              => 'number',
					'value'             => $min,
					'wrapper_class'     => 'form-row form-row-first',
					'custom_attributes' => array(
						'step' => 'any',
					),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => WC_Product_Range_Fields::META_MAX . '[' . $loop . ']',
					'name'              => WC_Product_Range_Fields::META_MAX . '[' . $loop . ']',
					'label'             => __( 'Max range', 'wc-product-range-fields' ),
					'type'              => 'number',
					'value'             => $max,
					'wrapper_class'     => 'form-row form-row-last',
					'custom_attributes' => array(
						'step' => 'any',
					),
				)
			);

			echo '<div class="clear"></div>';
			echo '</div>';
		}

		/**
		 * Save variation fields.
		 *
		 * @param int $variation_id Variation ID.
		 * @param int $loop Variation loop index.
		 * @return void
		 */
		public function save_variation_fields( $variation_id, $loop ) {
			$enabled_values = $_POST[ WC_Product_Range_Fields::META_ENABLED ] ?? array();
			$min_values     = $_POST[ WC_Product_Range_Fields::META_MIN ] ?? array();
			$max_values     = $_POST[ WC_Product_Range_Fields::META_MAX ] ?? array();

			$enabled = isset( $enabled_values[ $loop ] ) ? 'yes' : 'no';
			$min     = $min_values[ $loop ] ?? '';
			$max     = $max_values[ $loop ] ?? '';

			update_post_meta( $variation_id, WC_Product_Range_Fields::META_ENABLED, $enabled );
			update_post_meta( $variation_id, WC_Product_Range_Fields::META_MIN, $this->sanitize_range_value( $min ) );
			update_post_meta( $variation_id, WC_Product_Range_Fields::META_MAX, $this->sanitize_range_value( $max ) );
		}

		/**
		 * Sanitize decimal input.
		 *
		 * @param string $value Input value.
		 * @return string
		 */
		private function sanitize_range_value( $value ) {
			if ( '' === $value ) {
				return '';
			}

			return wc_format_decimal( wp_unslash( $value ) );
		}
	}
}
