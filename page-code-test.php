<?php
/**
 * Template Name: Product Filters Layout
 *
 * Custom product listing page with a category dropdown.
 * Selecting a category reloads the page scoped to that category so WBW
 * filter counts and the range-value filter visibility are accurate.
 */

defined( 'ABSPATH' ) || exit;

// Resolve selected category from URL param.
$selected_cat = isset( $_GET['product_cat'] ) ? sanitize_title( wp_unslash( $_GET['product_cat'] ) ) : '';
$paged        = max( 1, get_query_var( 'paged', 1 ) );

// Build the product query args.
$query_args = array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 12,
	'paged'          => $paged,
);

if ( '' !== $selected_cat ) {
	$query_args['tax_query'] = array(
		array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $selected_cat,
		),
	);
}

$product_query = new WP_Query( $query_args );

// Swap the global $wp_query so WBW and WooCommerce hooks see products.
global $wp_query;
$original_query = $wp_query;
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
$wp_query = $product_query;

get_header();
?>

<style>
.pfl-container {
	max-width: 1640px;
	margin: 0 auto;
	padding: 30px 20px 60px;
	width: 92vw;
}

/* ── Category bar ── */
.pfl-category-bar {
	display: flex;
	align-items: center;
	gap: 12px;
	margin-bottom: 28px;
	flex-wrap: wrap;
}

.pfl-category-bar label {
	font-weight: 600;
	font-size: 14px;
	color: #1d2327;
	white-space: nowrap;
}

.pfl-category-bar select {
	min-width: 220px;
	padding: 8px 12px;
	border: 1px solid #dcdcde;
	border-radius: 6px;
	font-size: 14px;
	background: #fff;
	cursor: pointer;
}

.pfl-category-bar button {
	padding: 8px 18px;
	background: #1d2327;
	color: #fff;
	border: none;
	border-radius: 6px;
	font-size: 14px;
	cursor: pointer;
}

.pfl-category-bar button:hover {
	background: #2c3338;
}

/* ── Main grid ── */
.pfl-grid {
	display: grid;
	grid-template-columns: 300px 1fr;
	gap: 40px;
	align-items: start;
}

/* ── Filter sidebar ── */
.pfl-filters {
	background: #fff;
	border: 1px solid #eee;
	padding: 20px;
	border-radius: 10px;
	position: sticky;
	top: 45px;
}

/* ── Product area ── */
.pfl-products {
	position: relative;
	min-height: 600px;
}

.pfl-products ul.products {
	display: grid;
	grid-template-columns: repeat( 3, 1fr );
	gap: 30px;
	padding: 0;
	margin: 0;
	list-style: none;
}

.pfl-products li.product {
	background: #fff;
	border: 1px solid #eee;
	border-radius: 10px;
	padding: 15px;
	transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.pfl-products li.product:hover {
	transform: translateY( -3px );
	box-shadow: 0 10px 25px rgba( 0, 0, 0, 0.08 );
}

.pfl-products li.product img {
	border-radius: 8px;
	width: 100%;
	height: auto;
}

.pfl-loader {
	position: absolute;
	inset: 0;
	background: rgba( 255, 255, 255, 0.85 );
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10;
}

.pfl-loader span {
	width: 40px;
	height: 40px;
	border: 3px solid #ddd;
	border-top: 3px solid #333;
	border-radius: 50%;
	animation: pfl-spin 1s linear infinite;
}

@keyframes pfl-spin {
	to { transform: rotate( 360deg ); }
}

.pfl-products .woocommerce-info {
	padding: 60px 20px;
	text-align: center;
	color: #666;
}

@media ( max-width: 1024px ) {
	.pfl-grid { grid-template-columns: 260px 1fr; }
}

@media ( max-width: 768px ) {
	.pfl-grid { grid-template-columns: 1fr; }
	.pfl-filters { position: relative; top: auto; }
	.pfl-products ul.products { grid-template-columns: repeat( 2, 1fr ); }
}

@media ( max-width: 480px ) {
	.pfl-products ul.products { grid-template-columns: 1fr; }
}
</style>

<div class="pfl-container">

	<?php
	// ── Category dropdown ────────────────────────────────────────────────────
	$categories = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) :
		$current_url = strtok( home_url( add_query_arg( array() ) ), '?' );
	?>
	<div class="pfl-category-bar">
		<label for="pflCategorySelect"><?php esc_html_e( 'Category:', 'wc-product-range-fields' ); ?></label>
		<form method="get" action="<?php echo esc_url( $current_url ); ?>" id="pflCategoryForm">
			<select name="product_cat" id="pflCategorySelect">
				<option value=""><?php esc_html_e( 'All products', 'wc-product-range-fields' ); ?></option>
				<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat->slug ); ?>"
						<?php selected( $selected_cat, $cat->slug ); ?>>
						<?php echo esc_html( $cat->name ); ?>
						(<?php echo absint( $cat->count ); ?>)
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit"><?php esc_html_e( 'Go', 'wc-product-range-fields' ); ?></button>
		</form>
	</div>
	<?php endif; ?>

	<div class="pfl-grid">

		<aside class="pfl-filters">
			<?php
			/*
			 * WBW filter shortcode.
			 * Change id=12 to your actual WBW filter form ID.
			 */
			echo do_shortcode( '[wpf-filters id=12]' );
			?>
		</aside>

		<main class="pfl-products">

			<div class="pfl-loader" id="pflLoader"><span></span></div>

			<?php
			wc_set_loop_prop( 'is_shortcode', false );

			if ( $product_query->have_posts() ) {
				woocommerce_product_loop_start();
				while ( $product_query->have_posts() ) {
					$product_query->the_post();
					wc_get_template_part( 'content', 'product' );
				}
				woocommerce_product_loop_end();
				woocommerce_pagination();
			} else {
				echo '<p class="woocommerce-info">' . esc_html__( 'No products found.', 'woocommerce' ) . '</p>';
			}

			// Restore original query.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_query = $original_query;
			wp_reset_postdata();
			?>

		</main>

	</div><!-- .pfl-grid -->

</div><!-- .pfl-container -->

<script>
( function () {
	// Hide the static loader once the page is ready.
	window.addEventListener( 'load', function () {
		var loader = document.getElementById( 'pflLoader' );
		if ( loader ) loader.style.display = 'none';
	} );

	// Auto-submit the category form when the dropdown changes.
	var select = document.getElementById( 'pflCategorySelect' );
	if ( select ) {
		select.addEventListener( 'change', function () {
			document.getElementById( 'pflCategoryForm' ).submit();
		} );
	}
} )();
</script>

<?php get_footer(); ?>
