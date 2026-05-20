<?php
/**
 * Woo Product Filter integration.
 *
 * @package WC_Product_Range_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Range_Fields_Filter' ) ) {
	/**
	 * Adds a WBW custom range-value filter.
	 */
	class WC_Product_Range_Fields_Filter {
		/**
		 * Query-string key used by the custom filter.
		 *
		 * @var string
		 */
		const FILTER_PARAM = 'wpf_range_value';

		/**
		 * Plugin file path.
		 *
		 * @var string
		 */
		private $plugin_file;

		/**
		 * Current request value captured from WBW filter processing.
		 *
		 * @var string|null
		 */
		private static $current_filter_value = null;

		/**
		 * Constructor.
		 *
		 * @param string $plugin_file Main plugin file path.
		 */
		public function __construct( $plugin_file ) {
			$this->plugin_file = $plugin_file;
		}

		/**
		 * Register hooks.
		 *
		 * @return void
		 */
		public function hooks() {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_filter( 'wpf_addHtmlAfterFilter', array( $this, 'append_range_filter_html' ), 10, 3 );
			add_filter( 'wpf_addCustomTaxQueryPro', array( $this, 'capture_range_filter_value' ), 10, 3 );
			add_filter( 'posts_where', array( $this, 'filter_posts_where' ), 20, 2 );
		}

		/**
		 * Load frontend assets for the WBW integration.
		 *
		 * @return void
		 */
		public function enqueue_assets() {
			if ( is_admin() ) {
				return;
			}

			wp_enqueue_style(
				'wc-product-range-fields-frontend',
				plugin_dir_url( $this->plugin_file ) . 'assets/css/frontend.css',
				array(),
				WC_Product_Range_Fields::VERSION
			);

			wp_enqueue_script(
				'wc-product-range-fields-frontend',
				plugin_dir_url( $this->plugin_file ) . 'assets/js/frontend.js',
				array( 'jquery', 'frontend.filters' ),
				WC_Product_Range_Fields::VERSION,
				true
			);
		}

		/**
		 * Append the custom numeric filter to WBW filter output.
		 *
		 * @param string $html Existing filter HTML.
		 * @param array  $settings Current filter settings.
		 * @param int    $filter_id WBW filter ID.
		 * @return string
		 */
		public function append_range_filter_html( $html, $settings, $filter_id ) {
			$filters = $this->get_saved_range_filters( $settings );
			if ( empty( $filters ) ) {
				return $html;
			}

			$current_value = $this->get_current_filter_value();
			$is_active     = '' !== $current_value;

			foreach ( $filters as $range_filter ) {
				$uniq_id     = empty( $range_filter['uniqId'] ) ? 'wpf-range-value-' . absint( $filter_id ) : $range_filter['uniqId'];
				$title       = ! empty( $range_filter['settings']['f_title'] ) ? $range_filter['settings']['f_title'] : __( 'Range value', 'wc-product-range-fields' );
				$description = ! empty( $range_filter['settings']['f_description'] ) ? $range_filter['settings']['f_description'] : '';

				$html .=
					'<div class="wpfFilterWrapper wc-product-range-filter' . ( $is_active ? '' : ' wpfNotActive' ) . '"' .
						' data-filter-type="wpfSearchNumber"' .
						' data-display-type="text"' .
						' data-get-attribute="' . esc_attr( self::FILTER_PARAM ) . '"' .
						' data-query-logic="and"' .
						' data-uniq-id="' . esc_attr( $uniq_id ) . '"' .
						'>' .
						'<div class="wpfFilterTitle">' . esc_html( $title ) . '</div>';

				if ( '' !== $description ) {
					$html .= '<div class="wfpDescription">' . esc_html( $description ) . '</div>';
				}

				$html .=
						'<div class="wpfFilterContent">' .
							'<input type="number" step="any" inputmode="decimal" class="wc-product-range-filter__input"' .
								' value="' . esc_attr( $current_value ) . '"' .
								' placeholder="' . esc_attr__( 'Enter a value', 'wc-product-range-fields' ) . '"' .
								' aria-label="' . esc_attr( $title ) . '"' .
							'>' .
						'</div>' .
					'</div>';
			}

			return $html;
		}

		/**
		 * Capture the numeric value sent by WBW for the custom range filter.
		 *
		 * @param array  $tax_query Existing tax query.
		 * @param array  $data Filter data.
		 * @param string $mode Request mode.
		 * @return array
		 */
		public function capture_range_filter_value( $tax_query, $data, $mode ) {
			unset( $mode );

			if ( ! empty( $data[ self::FILTER_PARAM ] ) ) {
				self::$current_filter_value = $this->sanitize_filter_value( $data[ self::FILTER_PARAM ] );
			}

			return $tax_query;
		}

		/**
		 * Restrict product queries to products whose range contains the requested value.
		 *
		 * @param string   $where SQL WHERE clause.
		 * @param WP_Query $query Query object.
		 * @return string
		 */
		public function filter_posts_where( $where, $query ) {
			$value = $this->get_current_filter_value();
			if ( '' === $value || ! $this->query_targets_products( $query ) ) {
				return $where;
			}

			global $wpdb;

			$numeric = (float) $value;

			$where .= $wpdb->prepare(
				"
				AND (
					EXISTS (
						SELECT 1
						FROM {$wpdb->postmeta} range_enabled
						INNER JOIN {$wpdb->postmeta} range_min
							ON range_min.post_id = range_enabled.post_id
							AND range_min.meta_key = %s
						INNER JOIN {$wpdb->postmeta} range_max
							ON range_max.post_id = range_enabled.post_id
							AND range_max.meta_key = %s
						WHERE range_enabled.post_id = {$wpdb->posts}.ID
							AND range_enabled.meta_key = %s
							AND range_enabled.meta_value = 'yes'
							AND CAST(range_min.meta_value AS DECIMAL(20,6)) <= %f
							AND CAST(range_max.meta_value AS DECIMAL(20,6)) >= %f
					)
					OR EXISTS (
						SELECT 1
						FROM {$wpdb->posts} variations
						INNER JOIN {$wpdb->postmeta} variation_enabled
							ON variation_enabled.post_id = variations.ID
							AND variation_enabled.meta_key = %s
							AND variation_enabled.meta_value = 'yes'
						INNER JOIN {$wpdb->postmeta} variation_min
							ON variation_min.post_id = variations.ID
							AND variation_min.meta_key = %s
						INNER JOIN {$wpdb->postmeta} variation_max
							ON variation_max.post_id = variations.ID
							AND variation_max.meta_key = %s
						WHERE variations.post_parent = {$wpdb->posts}.ID
							AND variations.post_type = 'product_variation'
							AND variations.post_status = 'publish'
							AND CAST(variation_min.meta_value AS DECIMAL(20,6)) <= %f
							AND CAST(variation_max.meta_value AS DECIMAL(20,6)) >= %f
					)
				)
				",
				WC_Product_Range_Fields::META_MIN,
				WC_Product_Range_Fields::META_MAX,
				WC_Product_Range_Fields::META_ENABLED,
				$numeric,
				$numeric,
				WC_Product_Range_Fields::META_ENABLED,
				WC_Product_Range_Fields::META_MIN,
				WC_Product_Range_Fields::META_MAX,
				$numeric,
				$numeric
			);

			return $where;
		}

		/**
		 * Resolve the current numeric filter value.
		 *
		 * @return string
		 */
		private function get_current_filter_value() {
			if ( null !== self::$current_filter_value ) {
				return self::$current_filter_value;
			}

			if ( isset( $_GET[ self::FILTER_PARAM ] ) ) {
				self::$current_filter_value = $this->sanitize_filter_value( wp_unslash( $_GET[ self::FILTER_PARAM ] ) );
				return self::$current_filter_value;
			}

			return '';
		}

		/**
		 * Check whether the current query is for products.
		 *
		 * @param WP_Query $query Query object.
		 * @return bool
		 */
		private function query_targets_products( $query ) {
			$post_type = $query->get( 'post_type' );

			if ( empty( $post_type ) ) {
				return true;
			}

			if ( is_array( $post_type ) ) {
				return in_array( 'product', $post_type, true );
			}

			return 'product' === $post_type;
		}

		/**
		 * Sanitize numeric input.
		 *
		 * @param mixed $value Raw input.
		 * @return string
		 */
		private function sanitize_filter_value( $value ) {
			if ( is_array( $value ) ) {
				$value = reset( $value );
			}

			$value = wc_format_decimal( (string) $value );

			return '' === $value || ! is_numeric( $value ) ? '' : $value;
		}

		/**
		 * Extract saved range-filter blocks from WBW settings.
		 *
		 * @param array $settings WBW settings array.
		 * @return array
		 */
		private function get_saved_range_filters( $settings ) {
			if ( empty( $settings['filters']['order'] ) ) {
				return array();
			}

			$filters = json_decode( $settings['filters']['order'], true );
			if ( ! is_array( $filters ) ) {
				return array();
			}

			return array_values(
				array_filter(
					$filters,
					static function( $filter ) {
						return isset( $filter['id'], $filter['settings']['f_range_value_filter'] )
							&& 'wpfRangeValue' === $filter['id']
							&& ! empty( $filter['settings']['f_enable'] );
					}
				)
			);
		}
	}
}
