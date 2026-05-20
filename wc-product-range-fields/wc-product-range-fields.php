<?php
/**
 * Plugin Name: WC Product Range Fields
 * Description: Adds min range, max range, and a visibility toggle to simple products and each product variation.
 * Version: 1.0.0
 * Author: Codex
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Range_Fields' ) ) {
	class WC_Product_Range_Fields {
		const META_ENABLED = '_range_enabled';
		const META_MIN     = '_min_range';
		const META_MAX     = '_max_range';

		public function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		public function init() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
				return;
			}

			add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_simple_fields' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_simple_fields' ) );

			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_fields' ), 10, 3 );
			add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

			add_action( 'admin_footer', array( $this, 'print_admin_script' ) );
		}

		public function woocommerce_missing_notice() {
			echo '<div class="notice notice-error"><p>WC Product Range Fields requires WooCommerce to be active.</p></div>';
		}

		public function render_simple_fields() {
			echo '<div class="options_group">';

			woocommerce_wp_checkbox(
				array(
					'id'            => self::META_ENABLED,
					'label'         => __( 'Enable range fields', 'wc-product-range-fields' ),
					'description'   => __( 'Show min and max range fields for this simple product.', 'wc-product-range-fields' ),
					'desc_tip'      => true,
					'value'         => get_post_meta( get_the_ID(), self::META_ENABLED, true ),
					'wrapper_class' => 'range-toggle-wrapper',
				)
			);

			echo '<div class="range-fields-group">';

			woocommerce_wp_text_input(
				array(
					'id'                => self::META_MIN,
					'label'             => __( 'Min range', 'wc-product-range-fields' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
					),
					'value'             => get_post_meta( get_the_ID(), self::META_MIN, true ),
				)
			);

			woocommerce_wp_text_input(
				array(
					'id'                => self::META_MAX,
					'label'             => __( 'Max range', 'wc-product-range-fields' ),
					'type'              => 'number',
					'custom_attributes' => array(
						'step' => 'any',
					),
					'value'             => get_post_meta( get_the_ID(), self::META_MAX, true ),
				)
			);

			echo '</div>';
			echo '</div>';
		}

		public function save_simple_fields( $product_id ) {
			$enabled = isset( $_POST[ self::META_ENABLED ] ) ? 'yes' : 'no';

			update_post_meta( $product_id, self::META_ENABLED, $enabled );
			update_post_meta( $product_id, self::META_MIN, $this->sanitize_range_value( $_POST[ self::META_MIN ] ?? '' ) );
			update_post_meta( $product_id, self::META_MAX, $this->sanitize_range_value( $_POST[ self::META_MAX ] ?? '' ) );
		}

		public function render_variation_fields( $loop, $variation_data, $variation ) {
			$enabled = get_post_meta( $variation->ID, self::META_ENABLED, true );
			$min     = get_post_meta( $variation->ID, self::META_MIN, true );
			$max     = get_post_meta( $variation->ID, self::META_MAX, true );

			echo '<div class="form-row form-row-full range-toggle-wrapper">';

			woocommerce_wp_checkbox(
				array(
					'id'            => self::META_ENABLED . '[' . $loop . ']',
					'name'          => self::META_ENABLED . '[' . $loop . ']',
					'label'         => __( 'Enable range fields', 'wc-product-range-fields' ),
					'description'   => __( 'Show min and max range fields for this variation.', 'wc-product-range-fields' ),
					'desc_tip'      => true,
					'value'         => $enabled,
					'cbvalue'       => 'yes',
					'wrapper_class' => 'form-row form-row-full',
				)
			);

			echo '</div>';
			echo '<div class="range-fields-group">';

			woocommerce_wp_text_input(
				array(
					'id'                => self::META_MIN . '[' . $loop . ']',
					'name'              => self::META_MIN . '[' . $loop . ']',
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
					'id'                => self::META_MAX . '[' . $loop . ']',
					'name'              => self::META_MAX . '[' . $loop . ']',
					'label'             => __( 'Max range', 'wc-product-range-fields' ),
					'type'              => 'number',
					'value'             => $max,
					'wrapper_class'     => 'form-row form-row-last',
					'custom_attributes' => array(
						'step' => 'any',
					),
				)
			);

			echo '<div style="clear: both;"></div>';
			echo '</div>';
		}

		public function save_variation_fields( $variation_id, $loop ) {
			$enabled_values = $_POST[ self::META_ENABLED ] ?? array();
			$min_values     = $_POST[ self::META_MIN ] ?? array();
			$max_values     = $_POST[ self::META_MAX ] ?? array();

			$enabled = isset( $enabled_values[ $loop ] ) ? 'yes' : 'no';
			$min     = $min_values[ $loop ] ?? '';
			$max     = $max_values[ $loop ] ?? '';

			update_post_meta( $variation_id, self::META_ENABLED, $enabled );
			update_post_meta( $variation_id, self::META_MIN, $this->sanitize_range_value( $min ) );
			update_post_meta( $variation_id, self::META_MAX, $this->sanitize_range_value( $max ) );
		}

		private function sanitize_range_value( $value ) {
			if ( '' === $value ) {
				return '';
			}

			return wc_format_decimal( wp_unslash( $value ) );
		}

		public function print_admin_script() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( ! $screen || 'product' !== $screen->id ) {
				return;
			}
			?>
			<script type="text/javascript">
				jQuery(function($) {
					function toggleRangeFields($scope) {
						var $checkbox = $scope.find('input[type="checkbox"][id^="<?php echo esc_js( self::META_ENABLED ); ?>"]');
						var $fields = $scope.find('.range-fields-group');

						if (!$checkbox.length || !$fields.length) {
							return;
						}

						$fields.toggle($checkbox.is(':checked'));
					}

					function initRangeFields(context) {
						$(context).find('.options_group, .woocommerce_variation').each(function() {
							toggleRangeFields($(this));
						});
					}

					initRangeFields(document);

					$(document).on('change', 'input[type="checkbox"][id^="<?php echo esc_js( self::META_ENABLED ); ?>"]', function() {
						var $scope = $(this).closest('.options_group, .woocommerce_variation');
						toggleRangeFields($scope);
					});

					$(document).on('woocommerce_variations_loaded woocommerce_variations_added', function() {
						initRangeFields(document);
					});
				});
			</script>
			<?php
		}
	}

	new WC_Product_Range_Fields();
}
