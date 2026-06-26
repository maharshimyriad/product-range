<?php
/**
 * Template Name: Product Filters Layout
 *
 * Uses WBW Product Filter Pro to drive the product query.
 * The filter shortcode [wpf-filters] controls what products are shown —
 * do NOT use a custom WP_Query here as it bypasses WBW's AJAX filtering.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<style>
.products-container {
	max-width: 1640px;
	margin: 0 auto;
	padding: 40px 20px;
	width: 92vw;
}

.products-grid {
	display: grid;
	grid-template-columns: 300px 1fr;
	gap: 40px;
	align-items: start;
}

.products-filters {
	background: #fff;
	border: 1px solid #eee;
	padding: 20px;
	border-radius: 10px;
	position: sticky;
	top: 45px;
}

.products-list {
	position: relative;
	min-height: 800px;
}

/* WooCommerce product grid inside the results area */
.products-list ul.products {
	display: grid;
	grid-template-columns: repeat( 3, 1fr );
	gap: 30px;
	padding: 0;
	margin: 0;
	list-style: none;
}

.products-list li.product {
	background: #fff;
	border: 1px solid #eee;
	border-radius: 10px;
	padding: 15px;
	transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.products-list li.product:hover {
	transform: translateY( -3px );
	box-shadow: 0 10px 25px rgba( 0, 0, 0, 0.08 );
}

.products-list li.product img {
	border-radius: 8px;
	width: 100%;
	height: auto;
}

/* WBW injects its own loader — hide the static one once JS runs */
.products-loader {
	position: absolute;
	inset: 0;
	background: rgba( 255, 255, 255, 0.85 );
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10;
}

.products-loader span {
	width: 40px;
	height: 40px;
	border: 3px solid #ddd;
	border-top: 3px solid #333;
	border-radius: 50%;
	animation: spin 1s linear infinite;
}

@keyframes spin {
	0%   { transform: rotate( 0deg ); }
	100% { transform: rotate( 360deg ); }
}

.products-list .woocommerce-info,
.products-list .woocommerce-no-products-found {
	min-height: 400px;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #666;
}

@media ( max-width: 1024px ) {
	.products-grid {
		grid-template-columns: 260px 1fr;
	}
}

@media ( max-width: 768px ) {
	.products-grid {
		grid-template-columns: 1fr;
	}

	.products-filters {
		position: relative;
		top: auto;
	}
}
</style>

<div class="products-container">
	<div class="products-grid">

		<aside class="products-filters">
			<?php
			/*
			 * WBW filter shortcode — drives the product query via AJAX.
			 * Change id=12 to your actual WBW filter form ID.
			 */
			echo do_shortcode( '[wpf-filters id=12]' );
			?>
		</aside>

		<main class="products-list">

			<div class="products-loader" id="productsLoader">
				<span></span>
			</div>

			<?php
			/*
			 * WBW needs a standard WooCommerce product loop here so it can
			 * replace its contents via AJAX when filters change.
			 *
			 * woocommerce_product_loop() renders the shop loop using the
			 * current main $wp_query. On this page template the main query
			 * is a page post, so we temporarily swap it with a product query
			 * so the initial render and WBW's AJAX both operate on products.
			 */
			global $wp_query, $woocommerce_loop;

			$paged = max( 1, get_query_var( 'paged' ) );

			$product_query = new WP_Query(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => 12,
					'paged'          => $paged,
				)
			);

			// Swap the main query so WBW and WooCommerce hooks see it correctly.
			$original_query = $wp_query;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_query = $product_query;

			wc_set_loop_prop( 'is_shortcode', false );

			if ( $product_query->have_posts() ) {

				woocommerce_product_loop_start();

				while ( $product_query->have_posts() ) {
					$product_query->the_post();
					wc_get_template_part( 'content', 'product' );
				}

				woocommerce_product_loop_end();

				// Pagination.
				woocommerce_pagination();

			} else {
				echo '<p class="woocommerce-info">' . esc_html__( 'No products found.', 'woocommerce' ) . '</p>';
			}

			// Restore the original main query.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_query = $original_query;
			wp_reset_postdata();
			?>

		</main>

	</div>
</div>

<script>
window.addEventListener( 'load', function () {
	var loader = document.getElementById( 'productsLoader' );
	if ( loader ) {
		loader.style.display = 'none';
	}
} );
</script>

<?php get_footer(); ?>
