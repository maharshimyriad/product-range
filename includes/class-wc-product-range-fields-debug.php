<?php
/**
 * Debug logger — DELETE THIS FILE when done.
 * Log: wp-content/debug-range-filter.log
 *
 * @package WC_Product_Range_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Range_Fields_Debug' ) ) {
	class WC_Product_Range_Fields_Debug {

		private $log;

		public function __construct() {
			$this->log = WP_CONTENT_DIR . '/debug-range-filter.log';
		}

		public function hooks() {
			add_filter( 'wpf_addHtmlAfterFilter', array( $this, 'log_render' ), 5, 3 );
			add_action( 'wp',                     array( $this, 'log_wp_query' ) );
		}

		public function log_wp_query() {
			global $wp_query;
			$ids = $wp_query ? array_slice( array_map( 'intval', wp_list_pluck( (array) $wp_query->posts, 'ID' ) ), 0, 10 ) : [];
			$this->w( 'WP_QUERY post_type=' . wp_json_encode( $wp_query ? $wp_query->get( 'post_type' ) : null )
				. ' is_tax=' . ( $wp_query && $wp_query->is_tax() ? 'yes' : 'no' )
				. ' found=' . ( $wp_query ? $wp_query->found_posts : 0 )
				. ' ids=' . wp_json_encode( $ids ) );
		}

		public function log_render( $html, $settings, $filter_id ) {
			global $wpdb, $wp_query;

			// How many _product_ranges rows exist total?
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key=%s", '_product_ranges' ) );

			// IDs currently in wp_query
			$ids = $wp_query ? array_slice( array_map( 'intval', wp_list_pluck( (array) $wp_query->posts, 'ID' ) ), 0, 20 ) : [];

			// How many of those IDs have _product_ranges?
			$scoped = 0;
			if ( ! empty( $ids ) ) {
				$ph     = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$scoped = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key=%s AND post_id IN ($ph)", array_merge( ['_product_ranges'], $ids ) ) );
			}

			// Saved wpfRangeValue blocks in settings
			$blocks = 0;
			if ( ! empty( $settings['filters']['order'] ) ) {
				$f = json_decode( $settings['filters']['order'], true );
				if ( is_array( $f ) ) {
					foreach ( $f as $item ) {
						if ( isset( $item['id'] ) && 'wpfRangeValue' === $item['id'] && ! empty( $item['settings']['f_enable'] ) ) {
							$blocks++;
						}
					}
				}
			}

			$this->w( 'RENDER filter_id=' . $filter_id
				. ' is_admin=' . ( is_admin() ? 'y' : 'n' )
				. ' doing_ajax=' . ( wp_doing_ajax() ? 'y' : 'n' )
				. ' _product_ranges_total=' . $total
				. ' wp_query_ids=' . count( $ids )
				. ' scoped_with_ranges=' . $scoped
				. ' wpfRangeValue_blocks_in_settings=' . $blocks );

			return $html;
		}

		private function w( $msg ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $this->log, '[' . gmdate( 'Y-m-d H:i:s' ) . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX );
		}
	}
}
