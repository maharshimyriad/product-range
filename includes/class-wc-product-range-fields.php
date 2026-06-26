<?php
/**
 * Main plugin class.
 *
 * @package WC_Product_Range_Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Product_Range_Fields' ) ) {
	/**
	 * Boots the plugin.
	 */
	class WC_Product_Range_Fields {
		/**
		 * Plugin version.
		 *
		 * @var string
		 */
		const VERSION = '1.0.0';

		/**
		 * Product meta key.
		 *
		 * @var string
		 */
		const META_ENABLED = '_range_enabled';

		/**
		 * Product meta key.
		 *
		 * @var string
		 */
		const META_MIN = '_min_range';

		/**
		 * Product meta key.
		 *
		 * @var string
		 */
		const META_MAX = '_max_range';

		/**
		 * Product meta key for repeater rows.
		 *
		 * @var string
		 */
		const META_RANGES = '_product_ranges';

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
		 * Register hooks.
		 *
		 * @return void
		 */
		public function run() {
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Initialize plugin integrations.
		 *
		 * @return void
		 */
		public function init() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
				return;
			}

			require_once plugin_dir_path( $this->plugin_file ) . 'includes/class-wc-product-range-fields-admin.php';

			$admin = new WC_Product_Range_Fields_Admin( $this->plugin_file );
			$admin->hooks();

			if ( class_exists( 'FrameWpf' ) ) {
				require_once plugin_dir_path( $this->plugin_file ) . 'includes/class-wc-product-range-fields-filter.php';

				$filter = new WC_Product_Range_Fields_Filter( $this->plugin_file );
				$filter->hooks();

				$debug_file = plugin_dir_path( $this->plugin_file ) . 'includes/class-wc-product-range-fields-debug.php';
				if ( file_exists( $debug_file ) ) {
					require_once $debug_file;
					( new WC_Product_Range_Fields_Debug() )->hooks();
				}
			}
		}

		/**
		 * Show missing WooCommerce notice.
		 *
		 * @return void
		 */
		public function woocommerce_missing_notice() {
			echo '<div class="notice notice-error"><p>WC Product Range Fields requires WooCommerce to be active.</p></div>';
		}

		/**
		 * Supported range types.
		 *
		 * @return array
		 */
		public static function get_range_types() {
			return array(
				'pipe_diameter' => __( 'Pipe Diameter', 'wc-product-range-fields' ),
				'product_length' => __( 'Product Length', 'wc-product-range-fields' ),
			);
		}
	}
}
