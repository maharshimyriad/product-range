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
			add_filter( 'wpf_addHtmlBeforeFilter', array( $this, 'append_range_filter_html' ), 10, 3 );
			add_filter( 'wpf_addCustomTaxQueryPro', array( $this, 'capture_range_filter_value' ), 10, 3 );
			add_filter( 'wpf_addCustomFieldsQueryPro', array( $this, 'add_range_filter_fields_query' ), 10, 3 );
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
		 * Prepend the custom numeric filter inside the WBW wrapper, before the
		 * filter list and button bar, so JS can reposition it correctly.
		 *
		 * Uses wpf_addHtmlBeforeFilter (fires right after <div class="wpfMainWrapper">
		 * opens) instead of wpf_addHtmlAfterFilter (which fires after the button bar).
		 *
		 * @param string $html     Existing HTML accumulated so far.
		 * @param array  $settings Current filter settings.
		 * @param string $view_id  WBW view identifier string.
		 * @return string
		 */
		public function append_range_filter_html( $html, $settings, $view_id ) {
			$filters   = $this->get_saved_range_filters( $settings );
			$order_map = $this->get_filter_order_map( $settings );
			if ( empty( $filters ) ) {
				return $html;
			}

			$current_values = $this->get_current_filter_values();
			$filter_types   = $this->get_catalog_filter_types();

			// Fall back to all supported types so the filter always renders in the
			// admin preview, even before any products have range meta saved.
			if ( empty( $filter_types ) ) {
				$filter_types = WC_Product_Range_Fields::get_range_types();
			}

			if ( empty( $filter_types ) ) {
				return $html;
			}

			$is_active = ! empty( $current_values );

			foreach ( $filters as $range_filter ) {
				if ( ! is_array( $range_filter ) ) {
					continue;
				}

				$range_settings = isset( $range_filter['settings'] ) && is_array( $range_filter['settings'] ) ? $range_filter['settings'] : array();
				$uniq_id     = empty( $range_filter['uniqId'] ) ? 'wpf-range-value-' . sanitize_html_class( $view_id ) : $range_filter['uniqId'];
				$title       = ! empty( $range_settings['f_title'] ) ? $range_settings['f_title'] : __( 'Range value', 'wc-product-range-fields' );
				$description = ! empty( $range_settings['f_description'] ) ? $range_settings['f_description'] : '';
				$show_title  = ! empty( $range_settings['f_enable_title'] ) ? $range_settings['f_enable_title'] : 'yes_open';
				$show_mobile = ! empty( $range_settings['f_enable_title_mobile'] ) ? $range_settings['f_enable_title_mobile'] : $show_title;
				$title_data  = ' data-show-on-mobile="' . esc_attr( $show_mobile ) . '" data-show-on-desctop="' . esc_attr( $show_title ) . '"';
				$content_css = 'yes_close' === $show_title ? ' wpfBlockAnimated wpfHide' : '';
				$order_meta  = isset( $order_map[ $uniq_id ] ) ? $order_map[ $uniq_id ] : array();
				$order_index = isset( $order_meta['index'] ) ? (string) $order_meta['index'] : '';
				$prev_uniq   = isset( $order_meta['prev'] ) ? $order_meta['prev'] : '';
				$next_uniq   = isset( $order_meta['next'] ) ? $order_meta['next'] : '';

				$block  = '<div class="wpfFilterWrapper wc-product-range-filter' . ( $is_active ? '' : ' wpfNotActive' ) . '"';
				$block .= ' data-filter-type="wpfSearchNumber"';
				$block .= ' data-display-type="text"';
				$block .= ' data-get-attribute="' . esc_attr( self::FILTER_PARAM ) . '"';
				$block .= ' data-query-logic="and"';
				$block .= ' data-uniq-id="' . esc_attr( $uniq_id ) . '"';
				$block .= ' data-range-order-index="' . esc_attr( $order_index ) . '"';
				$block .= ' data-range-prev-uniq-id="' . esc_attr( $prev_uniq ) . '"';
				$block .= ' data-range-next-uniq-id="' . esc_attr( $next_uniq ) . '"';
				$block .= '>';

				if ( 'no' !== $show_title || 'no' !== $show_mobile ) {
					$icon_class = 'yes_close' === $show_title ? 'fa-plus' : 'fa-minus';
					$block     .= '<div class="wpfFilterTitle"' . $title_data . '><div class="wfpTitle wfpClickable">' . esc_html( $title ) . '</div><i class="fa ' . esc_attr( $icon_class ) . ' wpfTitleToggle"></i></div>';
				}

				if ( '' !== $description ) {
					$block .= '<div class="wfpDescription">' . esc_html( $description ) . '</div>';
				}

				$block .= '<div class="wpfFilterContent' . esc_attr( $content_css ) . '">';
				$block .= $this->get_range_inputs_html( $filter_types, $current_values, $title );
				$block .= '</div>';
				$block .= '</div>';

				// Append our block to $html so it sits inside the wrapper.
				$html .= $block;
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
				self::$current_filter_values = $this->sanitize_filter_values( $data[ self::FILTER_PARAM ] );
			}

			return $tax_query;
		}

		/**
		 * Add matching product IDs to WBW fields query args.
		 *
		 * @param array  $fields Existing WBW query fields.
		 * @param array  $data Filter data.
		 * @param string $mode Request mode.
		 * @return array
		 */
		public function add_range_filter_fields_query( $fields, $data, $mode ) {
			unset( $mode );

			if ( ! empty( $data[ self::FILTER_PARAM ] ) ) {
				self::$current_filter_values = $this->sanitize_filter_values( $data[ self::FILTER_PARAM ] );
			}

			$values = $this->get_current_filter_values();
			if ( empty( $values ) ) {
				return $fields;
			}

			$matching_ids = $this->get_matching_product_ids( $values );
			if ( empty( $matching_ids ) ) {
				$fields['post__in'] = array( 0 );
				return $fields;
			}

			if ( ! empty( $fields['post__in'] ) && is_array( $fields['post__in'] ) ) {
				$matching_ids = array_values( array_intersect( array_map( 'intval', $fields['post__in'] ), $matching_ids ) );
			}

			$fields['post__in'] = empty( $matching_ids ) ? array( 0 ) : $matching_ids;

			return $fields;
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
			$doing_ajax = function_exists( 'wp_doing_ajax' ) ? wp_doing_ajax() : ( defined( 'DOING_AJAX' ) && DOING_AJAX );
			if ( empty( $values ) || ! $this->query_targets_products( $query ) || ( is_admin() && ! $doing_ajax ) ) {
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
				$html .= '<input type="number" step="any" inputmode="decimal" class="wc-product-range-filter__input" name="' . esc_attr( self::FILTER_PARAM . '[' . $type . ']' ) . '" data-range-type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr__( 'Enter a value', 'wc-product-range-fields' ) . '" aria-label="' . esc_attr( $title . ' ' . $label ) . '">';
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

			if ( empty( $found_types ) ) {
				$legacy_exists = (bool) $wpdb->get_var(
					$wpdb->prepare(
						"
						SELECT COUNT(1)
						FROM {$wpdb->postmeta}
						WHERE meta_key IN (%s, %s)
						",
						WC_Product_Range_Fields::META_MIN,
						WC_Product_Range_Fields::META_MAX
					)
				);

				if ( $legacy_exists ) {
					$found_types = $supported_types;
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
			if ( ! $this->is_entity_enabled( $entity ) ) {
				return false;
			}

			foreach ( $values as $type => $value ) {
				$numeric = (float) $value;
				$bounds  = $this->get_entity_bounds_for_type( $entity, $type );

				if ( empty( $bounds ) || ! $this->is_value_within_bounds( $numeric, $bounds['min'], $bounds['max'] ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Resolve bounds for a specific type, with legacy fallback for older data.
		 *
		 * @param array  $entity Product or variation range data.
		 * @param string $type Requested range type.
		 * @return array
		 */
		private function get_entity_bounds_for_type( $entity, $type ) {
			if ( ! empty( $entity['ranges'][ $type ] ) ) {
				return $entity['ranges'][ $type ];
			}

			if ( empty( $entity['ranges'] ) && ( '' !== $entity['legacy_min'] || '' !== $entity['legacy_max'] ) ) {
				return array(
					'min' => $entity['legacy_min'],
					'max' => $entity['legacy_max'],
				);
			}

			return array();
		}

		/**
		 * Determine whether an entity should participate in range matching.
		 *
		 * Older products may not have the explicit enabled flag saved yet.
		 *
		 * @param array $entity Product or variation range data.
		 * @return bool
		 */
		private function is_entity_enabled( $entity ) {
			if ( 'yes' === $entity['enabled'] ) {
				return true;
			}

			if ( ! empty( $entity['ranges'] ) ) {
				return true;
			}

			return '' !== $entity['legacy_min'] || '' !== $entity['legacy_max'];
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
						return is_array( $filter )
							&& isset( $filter['id'] )
							&& isset( $filter['settings'] )
							&& is_array( $filter['settings'] )
							&& isset( $filter['settings']['f_range_value_filter'] )
							&& 'wpfRangeValue' === $filter['id']
							&& ! empty( $filter['settings']['f_enable'] );
					}
				)
			);
		}

		/**
		 * Build an order map keyed by WBW uniq ID.
		 *
		 * @param array $settings WBW settings array.
		 * @return array
		 */
		private function get_filter_order_map( $settings ) {
			$enabled_filters = $this->get_all_enabled_filters( $settings );
			if ( empty( $enabled_filters ) ) {
				return array();
			}

			$order_map = array();
			$max_index = count( $enabled_filters ) - 1;

			foreach ( $enabled_filters as $index => $filter ) {
				if ( ! is_array( $filter ) || empty( $filter['uniqId'] ) ) {
					continue;
				}

				$uniq_id = (string) $filter['uniqId'];

				$order_map[ $uniq_id ] = array(
					'index' => $index,
					'prev'  => $index > 0 ? (string) $enabled_filters[ $index - 1 ]['uniqId'] : '',
					'next'  => $index < $max_index ? (string) $enabled_filters[ $index + 1 ]['uniqId'] : '',
					'id'    => isset( $filter['id'] ) ? (string) $filter['id'] : '',
				);
			}

			return $order_map;
		}

		/**
		 * Return all enabled WBW filters in saved order.
		 *
		 * @param array $settings WBW settings array.
		 * @return array
		 */
		private function get_all_enabled_filters( $settings ) {
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
						return is_array( $filter )
							&& ! empty( $filter['uniqId'] )
							&& isset( $filter['settings'] )
							&& is_array( $filter['settings'] )
							&& ! empty( $filter['settings']['f_enable'] );
					}
				)
			);
		}

	}
}
