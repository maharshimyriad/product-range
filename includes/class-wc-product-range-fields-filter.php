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
		private static $current_filter_values = null;

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
			add_action( 'pre_get_posts', array( $this, 'apply_range_filter_to_query' ) );
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
			$range_filter = $this->get_current_range_filter_settings( $settings, $filter_id );
			if ( empty( $range_filter ) ) {
				return $html;
			}

			$current_values = $this->get_current_filter_values();
			$filter_types   = $this->get_catalog_filter_types();
			if ( empty( $filter_types ) ) {
				return $html;
			}

			$is_active = ! empty( $current_values );
			$uniq_id     = empty( $range_filter['uniqId'] ) ? 'wpf-range-value-' . absint( $filter_id ) : $range_filter['uniqId'];
			$title       = ! empty( $range_filter['f_title'] ) ? $range_filter['f_title'] : __( 'Range value', 'wc-product-range-fields' );
			$description = ! empty( $range_filter['f_description'] ) ? $range_filter['f_description'] : '';
			$show_title  = ! empty( $range_filter['f_enable_title'] ) ? $range_filter['f_enable_title'] : 'yes_open';
			$show_mobile = ! empty( $range_filter['f_enable_title_mobile'] ) ? $range_filter['f_enable_title_mobile'] : $show_title;
			$title_data  = ' data-show-on-mobile="' . esc_attr( $show_mobile ) . '" data-show-on-desctop="' . esc_attr( $show_title ) . '"';
			$content_css = 'yes_close' === $show_title ? ' wpfBlockAnimated wpfHide' : '';

			$html  = '<div class="wpfFilterWrapper wc-product-range-filter' . ( $is_active ? '' : ' wpfNotActive' ) . '"';
			$html .= ' data-filter-type="wpfSearchNumber"';
			$html .= ' data-display-type="text"';
			$html .= ' data-get-attribute="' . esc_attr( self::FILTER_PARAM ) . '"';
			$html .= ' data-query-logic="and"';
			$html .= ' data-uniq-id="' . esc_attr( $uniq_id ) . '"';
			$html .= '>';

			if ( 'no' !== $show_title || 'no' !== $show_mobile ) {
				$icon_class = 'yes_close' === $show_title ? 'fa-plus' : 'fa-minus';
				$html      .= '<div class="wpfFilterTitle"' . $title_data . '><div class="wfpTitle wfpClickable">' . esc_html( $title ) . '</div><i class="fa ' . esc_attr( $icon_class ) . ' wpfTitleToggle"></i></div>';
			}

			if ( '' !== $description ) {
				$html .= '<div class="wfpDescription">' . esc_html( $description ) . '</div>';
			}

			$html .= '<div class="wpfFilterContent' . esc_attr( $content_css ) . '">';
			$html .= $this->get_range_inputs_html( $filter_types, $current_values, $title );
			$html .= '</div></div>';

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
				self::$current_filter_values = $this->sanitize_filter_values( $data[ self::FILTER_PARAM ] );
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
		public function apply_range_filter_to_query( $query ) {
			$values = $this->get_current_filter_values();
			if ( empty( $values ) || ! $this->query_targets_products( $query ) || is_admin() ) {
				return;
			}

			$matching_ids = $this->get_matching_product_ids( $values );
			if ( empty( $matching_ids ) ) {
				$query->set( 'post__in', array( 0 ) );
				return;
			}

			$existing_post__in = $query->get( 'post__in' );
			if ( ! empty( $existing_post__in ) && is_array( $existing_post__in ) ) {
				$matching_ids = array_values( array_intersect( array_map( 'intval', $existing_post__in ), $matching_ids ) );
			}

			$query->set( 'post__in', empty( $matching_ids ) ? array( 0 ) : $matching_ids );
		}

		/**
		 * Resolve the current numeric filter values.
		 *
		 * @return array
		 */
		private function get_current_filter_values() {
			if ( null !== self::$current_filter_values ) {
				return self::$current_filter_values;
			}

			if ( isset( $_GET[ self::FILTER_PARAM ] ) ) {
				self::$current_filter_values = $this->sanitize_filter_values( wp_unslash( $_GET[ self::FILTER_PARAM ] ) );
				return self::$current_filter_values;
			}

			return array();
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
		private function sanitize_filter_values( $values ) {
			if ( ! is_array( $values ) ) {
				return array();
			}

			$allowed = $this->get_supported_filter_types();
			$output  = array();

			foreach ( $values as $type => $value ) {
				$type = sanitize_key( (string) $type );
				if ( ! isset( $allowed[ $type ] ) ) {
					continue;
				}

				$value = wc_format_decimal( (string) $value );
				if ( '' === $value || ! is_numeric( $value ) ) {
					continue;
				}

				$output[ $type ] = $value;
			}

			return $output;
		}

		/**
		 * Render the typed inputs inside the filter block.
		 *
		 * @param array  $filter_types Discovered filter types.
		 * @param array  $current_values Current request values.
		 * @param string $title Filter title.
		 * @return string
		 */
		private function get_range_inputs_html( $filter_types, $current_values, $title ) {
			$html = '';

			foreach ( $filter_types as $type => $label ) {
				$value = isset( $current_values[ $type ] ) ? $current_values[ $type ] : '';
				$html .= '<label class="wc-product-range-filter__field">';
				$html .= '<span class="wc-product-range-filter__label">' . esc_html( $label ) . '</span>';
				$html .= '<input type="number" step="any" inputmode="decimal" class="wc-product-range-filter__input" data-range-type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr__( 'Enter a value', 'wc-product-range-fields' ) . '" aria-label="' . esc_attr( $title . ' ' . $label ) . '">';
				$html .= '</label>';
			}

			return $html;
		}

		/**
		 * Discover filter types that exist in the catalog.
		 *
		 * @return array
		 */
		private function get_catalog_filter_types() {
			global $wpdb;

			$supported_types = WC_Product_Range_Fields::get_range_types();
			$found_types     = array();
			$meta_key        = WC_Product_Range_Fields::META_RANGES;

			$matches = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
					$meta_key
				)
			);

			foreach ( $matches as $meta_value ) {
				$rows = maybe_unserialize( $meta_value );
				if ( ! is_array( $rows ) ) {
					continue;
				}

				foreach ( $rows as $row ) {
					if ( empty( $row['type'] ) ) {
						continue;
					}

					$type = sanitize_key( $row['type'] );
					if ( isset( $supported_types[ $type ] ) ) {
						$found_types[ $type ] = $supported_types[ $type ];
					}
				}
			}

			return $found_types;
		}

		/**
		 * Supported filter types, including legacy fallback.
		 *
		 * @return array
		 */
		private function get_supported_filter_types() {
			return WC_Product_Range_Fields::get_range_types();
		}

		/**
		 * Get matching product IDs for the current typed filters.
		 *
		 * @param array $values Typed numeric filters.
		 * @return array
		 */
		private function get_matching_product_ids( $values ) {
			global $wpdb;

			$product_rows = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT p.ID, p.post_parent, p.post_type, pm.meta_key, pm.meta_value
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
					WHERE p.post_type IN ('product', 'product_variation')
						AND p.post_status = 'publish'
						AND pm.meta_key IN (%s, %s, %s)
					",
					WC_Product_Range_Fields::META_RANGES,
					WC_Product_Range_Fields::META_MIN,
					WC_Product_Range_Fields::META_MAX
				),
				ARRAY_A
			);

			$entities = array();

			foreach ( $product_rows as $row ) {
				$post_id = (int) $row['ID'];

				if ( ! isset( $entities[ $post_id ] ) ) {
					$entities[ $post_id ] = array(
						'post_type'    => $row['post_type'],
						'post_parent'  => (int) $row['post_parent'],
						'ranges'       => array(),
						'legacy_min'   => '',
						'legacy_max'   => '',
						'has_repeater' => false,
						'enabled'      => 'no',
					);
				}

				if ( WC_Product_Range_Fields::META_RANGES === $row['meta_key'] ) {
					$entities[ $post_id ]['ranges']       = $this->normalize_saved_rows( maybe_unserialize( $row['meta_value'] ) );
					$entities[ $post_id ]['has_repeater'] = ! empty( $entities[ $post_id ]['ranges'] );
				} elseif ( WC_Product_Range_Fields::META_MIN === $row['meta_key'] ) {
					$entities[ $post_id ]['legacy_min'] = wc_format_decimal( (string) $row['meta_value'] );
				} elseif ( WC_Product_Range_Fields::META_MAX === $row['meta_key'] ) {
					$entities[ $post_id ]['legacy_max'] = wc_format_decimal( (string) $row['meta_value'] );
				}
			}

			$enabled_rows = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT post_id, meta_value
					FROM {$wpdb->postmeta}
					WHERE meta_key = %s
					",
					WC_Product_Range_Fields::META_ENABLED
				),
				ARRAY_A
			);

			foreach ( $enabled_rows as $enabled_row ) {
				$post_id = (int) $enabled_row['post_id'];
				if ( isset( $entities[ $post_id ] ) ) {
					$entities[ $post_id ]['enabled'] = 'yes' === $enabled_row['meta_value'] ? 'yes' : 'no';
				}
			}

			$matched_products = array();

			foreach ( $entities as $post_id => $entity ) {
				if ( ! $this->entity_matches_filters( $entity, $values ) ) {
					continue;
				}

				$matched_products[] = 'product_variation' === $entity['post_type'] ? $entity['post_parent'] : $post_id;
			}

			$matched_products = array_values( array_unique( array_filter( array_map( 'intval', $matched_products ) ) ) );
			sort( $matched_products );

			return $matched_products;
		}

		/**
		 * Normalize stored repeater rows.
		 *
		 * @param mixed $rows Raw rows from meta.
		 * @return array
		 */
		private function normalize_saved_rows( $rows ) {
			if ( ! is_array( $rows ) ) {
				return array();
			}

			$supported = WC_Product_Range_Fields::get_range_types();
			$normalized = array();

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || empty( $row['type'] ) ) {
					continue;
				}

				$type = sanitize_key( (string) $row['type'] );
				if ( ! isset( $supported[ $type ] ) ) {
					continue;
				}

				$normalized[ $type ] = array(
					'min' => isset( $row['min'] ) ? wc_format_decimal( (string) $row['min'] ) : '',
					'max' => isset( $row['max'] ) ? wc_format_decimal( (string) $row['max'] ) : '',
				);
			}

			return $normalized;
		}

		/**
		 * Check whether one product or variation matches all typed filters.
		 *
		 * @param array $entity Product or variation range data.
		 * @param array $values Submitted filter values.
		 * @return bool
		 */
		private function entity_matches_filters( $entity, $values ) {
			foreach ( $values as $type => $value ) {
				$numeric = (float) $value;

				if ( 'yes' !== $entity['enabled'] ) {
					return false;
				}

				if ( empty( $entity['ranges'][ $type ] ) ) {
					return false;
				}

				if ( ! $this->is_value_within_bounds( $numeric, $entity['ranges'][ $type ]['min'], $entity['ranges'][ $type ]['max'] ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Check whether a numeric value fits the stored bounds.
		 *
		 * @param float  $numeric Input value.
		 * @param string $min Minimum bound.
		 * @param string $max Maximum bound.
		 * @return bool
		 */
		private function is_value_within_bounds( $numeric, $min, $max ) {
			if ( '' === $min && '' === $max ) {
				return false;
			}

			if ( '' !== $min && $numeric < (float) $min ) {
				return false;
			}

			if ( '' !== $max && $numeric > (float) $max ) {
				return false;
			}

			return true;
		}

		/**
		 * Extract saved range-filter blocks from WBW settings.
		 *
		 * @param array $settings WBW settings array.
		 * @return array
		 */
		private function get_current_range_filter_settings( $settings, $filter_id ) {
			unset( $filter_id );

			if ( ! is_array( $settings ) ) {
				return array();
			}

			if ( ! empty( $settings['settings'] ) && is_array( $settings['settings'] ) ) {
				$settings = array_merge( $settings, $settings['settings'] );
			}

			if ( empty( $settings['f_range_value_filter'] ) || empty( $settings['f_enable'] ) ) {
				return array();
			}

			return $settings;
		}
	}
}
