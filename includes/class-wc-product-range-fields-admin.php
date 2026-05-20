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
			add_action( 'admin_notices', array( $this, 'render_filter_admin_notice' ) );
			add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_simple_fields' ) );
			add_action( 'woocommerce_process_product_meta', array( $this, 'save_simple_fields' ) );
			add_action( 'woocommerce_variation_options', array( $this, 'render_variation_fields' ), 5, 3 );
			add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
			add_action( 'admin_footer', array( $this, 'render_attribute_picker_dialog' ) );
		}

		/**
		 * Load admin CSS/JS on product edit screens.
		 *
		 * @param string $hook_suffix Current admin page.
		 * @return void
		 */
		public function enqueue_assets( $hook_suffix ) {
			if ( ! $this->should_enqueue_assets( $hook_suffix ) ) {
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
				array( 'jquery', 'jquery-ui-dialog' ),
				WC_Product_Range_Fields::VERSION,
				true
			);

			wp_localize_script(
				'wc-product-range-fields-admin',
				'wcProductRangeFields',
				array(
					'rangeTypes'    => WC_Product_Range_Fields::get_range_types(),
					'strings'       => array(
						'selectType' => __( 'Select type', 'wc-product-range-fields' ),
						'addMore'    => __( 'Add more', 'wc-product-range-fields' ),
						'remove'     => __( 'Remove', 'wc-product-range-fields' ),
					),
				)
			);
		}

		/**
		 * Show Woo Product Filter setup instructions for the custom meta fields.
		 *
		 * @return void
		 */
		public function render_filter_admin_notice() {
			if ( ! $this->is_filter_admin_screen() ) {
				return;
			}

			?>
			<div class="notice notice-info">
				<p>
					<?php esc_html_e( 'Range fields now use repeater rows saved in product meta, with legacy single-range values still supported as frontend fallback.', 'wc-product-range-fields' ); ?>
					<?php esc_html_e( 'Use the custom "Range value" filter in Woo Product Filter to render the typed range inputs.', 'wc-product-range-fields' ); ?>
				</p>
			</div>
			<?php
		}

		/**
		 * Check whether assets should load on the current admin screen.
		 *
		 * @param string $hook_suffix Current admin page.
		 * @return bool
		 */
		private function should_enqueue_assets( $hook_suffix ) {
			if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

				return $screen && 'product' === $screen->id;
			}

			return $this->is_filter_admin_screen();
		}

		/**
		 * Check whether the user is editing a Woo Product Filter form.
		 *
		 * @return bool
		 */
		private function is_filter_admin_screen() {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

			return 'wpf-filters' === $page && false !== strpos( $tab, 'woofilters' );
		}

		/**
		 * Render the moved bulk attribute picker on the filter admin page.
		 *
		 * @return void
		 */
		public function render_attribute_picker_dialog() {
			if ( ! $this->is_filter_admin_screen() || ! class_exists( 'FrameWpf' ) ) {
				return;
			}

			$module = FrameWpf::_()->getModule( 'woofilters' );
			if ( ! $module ) {
				return;
			}

			list( $attr_display ) = $module->getAttributesDisplay();
			?>
			<div class="wpf-product-range-attribute-picker">
				<button style="margin-left:25px;" id="wpfAddAllAttributesButton" type="button" class="button button-small">
					<span><?php esc_html_e( 'Choose attributes', 'woo-product-filter' ); ?></span>
				</button>
				<div id="wpfAttributesPickerDialog" title="<?php echo esc_attr__( 'Choose attributes', 'woo-product-filter' ); ?>" style="display:none;">
					<div class="wpfAttributesPickerActions">
						<button type="button" class="button button-small" data-action="select-all"><?php esc_html_e( 'Select all', 'woo-product-filter' ); ?></button>
						<button type="button" class="button button-small" data-action="clear-all"><?php esc_html_e( 'Clear', 'woo-product-filter' ); ?></button>
					</div>
					<div class="wpfAttributesPickerList">
						<?php foreach ( $attr_display as $attr_value => $attr_label ) : ?>
							<?php if ( empty( $attr_value ) || '0' === (string) $attr_value ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<div class="wpfAttributesPickerItem">
								<label>
									<input type="checkbox" value="<?php echo esc_attr( $attr_value ); ?>">
									<span><?php echo esc_html( $attr_label ); ?></span>
								</label>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Render simple product fields.
		 *
		 * @return void
		 */
		public function render_simple_fields() {
			echo '<div class="options_group wc-product-range-fields show_if_simple hide_if_variable hide_if_grouped hide_if_external">';
			echo '<p class="form-field"><label>' . esc_html__( 'Range fields', 'wc-product-range-fields' ) . '</label>';
			echo '<span class="description">' . esc_html__( 'Add one or more typed ranges for this product.', 'wc-product-range-fields' ) . '</span></p>';
			$this->render_repeater(
				WC_Product_Range_Fields::META_RANGES,
				$this->get_admin_range_rows( get_the_ID() ),
				'show_if_simple hide_if_variable hide_if_grouped hide_if_external'
			);
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

			$this->save_range_rows( $product_id, $_POST[ WC_Product_Range_Fields::META_RANGES ] ?? array() );
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
			echo '<div class="form-row form-row-full wc-product-range-fields wc-product-range-fields__section">';
			echo '<strong class="wc-product-range-fields__heading">' . esc_html__( 'Range fields', 'wc-product-range-fields' ) . '</strong>';
			echo '</div>';
			$this->render_repeater(
				WC_Product_Range_Fields::META_RANGES . '[' . $loop . ']',
				$this->get_admin_range_rows( $variation->ID ),
				'form-row form-row-full wc-product-range-fields'
			);
		}

		/**
		 * Save variation fields.
		 *
		 * @param int $variation_id Variation ID.
		 * @param int $loop Variation loop index.
		 * @return void
		 */
		public function save_variation_fields( $variation_id, $loop ) {
			$range_values = $_POST[ WC_Product_Range_Fields::META_RANGES ] ?? array();
			$this->save_range_rows( $variation_id, $range_values[ $loop ] ?? array() );
		}

		/**
		 * Render the repeater fields.
		 *
		 * @param string $field_name Field name prefix.
		 * @param array  $rows Existing rows.
		 * @param string $wrapper_class Wrapper classes.
		 * @return void
		 */
		private function render_repeater( $field_name, $rows, $wrapper_class ) {
			$types = WC_Product_Range_Fields::get_range_types();
			$rows  = empty( $rows ) ? array( $this->get_empty_range_row() ) : array_values( $rows );

			echo '<div class="wc-product-range-repeater ' . esc_attr( $wrapper_class ) . '" data-field-name="' . esc_attr( $field_name ) . '">';
			echo '<div class="wc-product-range-repeater__rows">';

			foreach ( $rows as $index => $row ) {
				$this->render_repeater_row( $field_name, $index, $row, $types );
			}

			echo '</div>';
			echo '<p><button type="button" class="button wc-product-range-repeater__add">' . esc_html__( 'Add more', 'wc-product-range-fields' ) . '</button></p>';
			echo '</div>';
		}

		/**
		 * Render one repeater row.
		 *
		 * @param string $field_name Field name prefix.
		 * @param int    $index Row index.
		 * @param array  $row Row values.
		 * @param array  $types Supported types.
		 * @return void
		 */
		private function render_repeater_row( $field_name, $index, $row, $types ) {
			$type_name = $field_name . '[' . $index . '][type]';
			$min_name  = $field_name . '[' . $index . '][min]';
			$max_name  = $field_name . '[' . $index . '][max]';

			echo '<div class="wc-product-range-repeater__row">';
			echo '<div class="wc-product-range-repeater__field wc-product-range-repeater__field--type">';
			echo '<label>' . esc_html__( 'Product type', 'wc-product-range-fields' ) . '</label>';
			echo '<select name="' . esc_attr( $type_name ) . '" class="wc-product-range-repeater__type">';
			echo '<option value="">' . esc_html__( 'Select type', 'wc-product-range-fields' ) . '</option>';

			foreach ( $types as $type_key => $type_label ) {
				echo '<option value="' . esc_attr( $type_key ) . '"' . selected( $row['type'], $type_key, false ) . '>' . esc_html( $type_label ) . '</option>';
			}

			echo '</select>';
			echo '</div>';

			echo '<div class="wc-product-range-repeater__field">';
			echo '<label>' . esc_html__( 'Min range', 'wc-product-range-fields' ) . '</label>';
			echo '<input type="number" step="any" name="' . esc_attr( $min_name ) . '" value="' . esc_attr( $row['min'] ) . '">';
			echo '</div>';

			echo '<div class="wc-product-range-repeater__field">';
			echo '<label>' . esc_html__( 'Max range', 'wc-product-range-fields' ) . '</label>';
			echo '<input type="number" step="any" name="' . esc_attr( $max_name ) . '" value="' . esc_attr( $row['max'] ) . '">';
			echo '</div>';

			echo '<div class="wc-product-range-repeater__actions">';
			echo '<button type="button" class="button-link-delete wc-product-range-repeater__remove">' . esc_html__( 'Remove', 'wc-product-range-fields' ) . '</button>';
			echo '</div>';
			echo '</div>';
		}

		/**
		 * Resolve rows for admin editing.
		 *
		 * @param int $post_id Product or variation ID.
		 * @return array
		 */
		private function get_admin_range_rows( $post_id ) {
			$rows = get_post_meta( $post_id, WC_Product_Range_Fields::META_RANGES, true );
			if ( is_array( $rows ) && ! empty( $rows ) ) {
				return array_map( array( $this, 'normalize_range_row' ), $rows );
			}

			$legacy_min = get_post_meta( $post_id, WC_Product_Range_Fields::META_MIN, true );
			$legacy_max = get_post_meta( $post_id, WC_Product_Range_Fields::META_MAX, true );
			if ( '' !== $legacy_min || '' !== $legacy_max ) {
				return array(
					array(
						'type' => '',
						'min'  => $legacy_min,
						'max'  => $legacy_max,
					),
				);
			}

			return array();
		}

		/**
		 * Save repeater rows.
		 *
		 * @param int   $post_id Product or variation ID.
		 * @param mixed $raw_rows Posted rows.
		 * @return void
		 */
		private function save_range_rows( $post_id, $raw_rows ) {
			$rows = $this->sanitize_range_rows( $raw_rows );

			if ( empty( $rows ) ) {
				delete_post_meta( $post_id, WC_Product_Range_Fields::META_RANGES );
				return;
			}

			update_post_meta( $post_id, WC_Product_Range_Fields::META_RANGES, $rows );
			delete_post_meta( $post_id, WC_Product_Range_Fields::META_ENABLED );
			delete_post_meta( $post_id, WC_Product_Range_Fields::META_MIN );
			delete_post_meta( $post_id, WC_Product_Range_Fields::META_MAX );
		}

		/**
		 * Sanitize repeater rows.
		 *
		 * @param mixed $raw_rows Raw posted rows.
		 * @return array
		 */
		private function sanitize_range_rows( $raw_rows ) {
			if ( ! is_array( $raw_rows ) ) {
				return array();
			}

			$types      = WC_Product_Range_Fields::get_range_types();
			$seen_types = array();
			$rows       = array();

			foreach ( $raw_rows as $row ) {
				$row = $this->normalize_range_row( $row );

				if ( empty( $row['type'] ) || ! isset( $types[ $row['type'] ] ) ) {
					continue;
				}

				if ( isset( $seen_types[ $row['type'] ] ) ) {
					continue;
				}

				if ( '' === $row['min'] && '' === $row['max'] ) {
					continue;
				}

				$seen_types[ $row['type'] ] = true;
				$rows[]                     = $row;
			}

			return $rows;
		}

		/**
		 * Normalize one row.
		 *
		 * @param mixed $row Raw row data.
		 * @return array
		 */
		private function normalize_range_row( $row ) {
			$row = is_array( $row ) ? $row : array();

			return array(
				'type' => isset( $row['type'] ) ? sanitize_key( wp_unslash( $row['type'] ) ) : '',
				'min'  => $this->sanitize_range_value( $row['min'] ?? '' ),
				'max'  => $this->sanitize_range_value( $row['max'] ?? '' ),
			);
		}

		/**
		 * Empty row shape.
		 *
		 * @return array
		 */
		private function get_empty_range_row() {
			return array(
				'type' => '',
				'min'  => '',
				'max'  => '',
			);
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
