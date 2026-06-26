<?php
/**
 * Temporary debug logger for the range filter.
 *
 * DELETE THIS FILE when debugging is complete.
 * Loaded conditionally from class-wc-product-range-fields.php only when it exists.
 *
 * Logs to: wp-content/debug-range-filter.log
 *
 * @package WC_Product_Range_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Range_Fields_Debug' ) ) {
	/**
	 * Hooks into the filter render pipeline and logs every decision point.
	 */
	class WC_Product_Range_Fields_Debug {

		/**
		 * Log file path (inside wp-content so it's web-accessible for quick viewing).
		 *
		 * @var string
		 */
		private $log_file;

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->log_file = WP_CONTENT_DIR . '/debug-range-filter.log';
		}

		/**
		 * Register debug hooks — fires early so we catch everything.
		 *
		 * @return void
		 */
		public function hooks() {
			// Intercept the same hooks the filter class uses.
			add_filter( 'wpf_addHtmlAfterFilter',     array( $this, 'debug_render' ),         5, 3 );
			add_filter( 'wpf_addCustomTaxQueryPro',   array( $this, 'debug_tax_query' ),       5, 3 );
			add_filter( 'wpf_addCustomFieldsQueryPro', array( $this, 'debug_fields_query' ),   5, 3 );
			add_action( 'wp',                          array( $this, 'debug_wp_query' ) );
		}

		/**
		 * Log context when render hook fires.
		 *
		 * @param string $html      Current HTML.
		 * @param array  $settings  WBW settings.
		 * @param int    $filter_id WBW filter ID.
		 * @return string Unchanged HTML.
		 */
		public function debug_render( $html, $settings, $filter_id ) {
			global $wpdb, $wp_query;

			$is_admin   = is_admin() ? 'yes' : 'no';
			$doing_ajax = wp_doing_ajax() ? 'yes' : 'no';

			// What filters does WBW have saved?
			$saved_filters = array();
			if ( ! empty( $settings['filters']['order'] ) ) {
				$decoded = json_decode( $settings['filters']['order'], true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $f ) {
						$saved_filters[] = ( $f['id'] ?? '?' ) . ' enabled=' . ( ! empty( $f['settings']['f_enable'] ) ? '1' : '0' );
					}
				}
			}

			// Postmeta check — does any product have _product_ranges?
			$meta_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
					'_product_ranges'
				)
			);

			$legacy_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN (%s,%s)",
					'_min_range',
					'_max_range'
				)
			);

			// Current wp_query post type and IDs.
			$query_post_type = 'n/a';
			$query_ids       = 'n/a';
			$query_is_tax    = 'n/a';
			if ( isset( $wp_query ) && $wp_query instanceof WP_Query ) {
				$query_post_type = wp_json_encode( $wp_query->get( 'post_type' ) );
				$query_is_tax    = $wp_query->is_tax() ? 'yes' : 'no';
				$query_ids       = wp_json_encode(
					array_slice(
						array_map( 'intval', wp_list_pluck( (array) $wp_query->posts, 'ID' ) ),
						0,
						20
					)
				);
			}

			// GET params.
			$get_params = wp_json_encode( $_GET );

			$this->log( '=== wpf_addHtmlAfterFilter fired ===' );
			$this->log( 'filter_id      : ' . $filter_id );
			$this->log( 'is_admin       : ' . $is_admin );
			$this->log( 'doing_ajax     : ' . $doing_ajax );
			$this->log( 'saved_filters  : ' . implode( ' | ', $saved_filters ) );
			$this->log( '_product_ranges rows in DB : ' . $meta_count );
			$this->log( 'legacy _min/_max rows in DB: ' . $legacy_count );
			$this->log( 'wp_query post_type : ' . $query_post_type );
			$this->log( 'wp_query is_tax    : ' . $query_is_tax );
			$this->log( 'wp_query post IDs  : ' . $query_ids );
			$this->log( '$_GET              : ' . $get_params );
			$this->log( '---' );

			return $html;
		}

		/**
		 * Log when the tax query hook fires.
		 *
		 * @param array  $tax_query Tax query.
		 * @param array  $data      WBW data.
		 * @param string $mode      Mode.
		 * @return array
		 */
		public function debug_tax_query( $tax_query, $data, $mode ) {
			$this->log( '=== wpf_addCustomTaxQueryPro fired ===' );
			$this->log( 'mode      : ' . $mode );
			$this->log( 'data keys : ' . implode( ', ', array_keys( (array) $data ) ) );
			$this->log( 'wpf_range_value in data: ' . ( isset( $data['wpf_range_value'] ) ? wp_json_encode( $data['wpf_range_value'] ) : 'NOT SET' ) );
			$this->log( '---' );
			return $tax_query;
		}

		/**
		 * Log when the fields query hook fires.
		 *
		 * @param array  $fields Fields.
		 * @param array  $data   WBW data.
		 * @param string $mode   Mode.
		 * @return array
		 */
		public function debug_fields_query( $fields, $data, $mode ) {
			$this->log( '=== wpf_addCustomFieldsQueryPro fired ===' );
			$this->log( 'mode      : ' . $mode );
			$this->log( 'data keys : ' . implode( ', ', array_keys( (array) $data ) ) );
			$this->log( 'wpf_range_value in data: ' . ( isset( $data['wpf_range_value'] ) ? wp_json_encode( $data['wpf_range_value'] ) : 'NOT SET' ) );
			$this->log( '---' );
			return $fields;
		}

		/**
		 * Log the main WP_Query state once WordPress has set up the query.
		 *
		 * @return void
		 */
		public function debug_wp_query() {
			global $wp_query;

			if ( ! isset( $wp_query ) ) {
				$this->log( 'wp action: $wp_query not set' );
				return;
			}

			$this->log( '=== wp action (main query ready) ===' );
			$this->log( 'is_admin    : ' . ( is_admin() ? 'yes' : 'no' ) );
			$this->log( 'doing_ajax  : ' . ( wp_doing_ajax() ? 'yes' : 'no' ) );
			$this->log( 'post_type   : ' . wp_json_encode( $wp_query->get( 'post_type' ) ) );
			$this->log( 'is_tax      : ' . ( $wp_query->is_tax() ? 'yes' : 'no' ) );
			$this->log( 'is_archive  : ' . ( $wp_query->is_post_type_archive( 'product' ) ? 'yes' : 'no' ) );
			$this->log( 'found_posts : ' . $wp_query->found_posts );
			$this->log( 'post count  : ' . count( (array) $wp_query->posts ) );
			$this->log( '---' );
		}

		/**
		 * Write a line to the log file.
		 *
		 * @param string $message Message to log.
		 * @return void
		 */
		private function log( $message ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents(
				$this->log_file,
				'[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $message . PHP_EOL,
				FILE_APPEND | LOCK_EX
			);
		}
	}
}
