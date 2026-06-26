<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 8.6.0
 */

defined('ABSPATH') || exit;

get_header('shop'); 

do_action('woocommerce_before_main_content');
do_action('woocommerce_shop_loop_header');

// ==========================================
// 1. Build Excluded SKUs Array (Optimized)
// ==========================================
$excluded_skus = [];
$extras_query = new WP_Query([
    'post_type'      => 'product',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'meta_query'     => [
        [
            'key'     => 'optional_extras',
            'value'   => '',
            'compare' => '!=',
        ],
    ],
    'fields'         => 'ids',
    'no_found_rows'  => true, // Optimization: Skip SQL_CALC_FOUND_ROWS
]);

if ($extras_query->posts) {
    foreach ($extras_query->posts as $product_id) {
        $val = get_field('optional_extras', $product_id);
        if (!empty($val)) {
            // Clean and filter SKUs efficiently in one go
            $skus = array_filter(array_map('trim', explode(',', $val)));
            foreach ($skus as $sku) {
                $excluded_skus[$sku] = true;
            }
        }
    }
}
wp_reset_postdata();

// ==========================================
// 2. Calculate Visible Total for Loop Props
// ==========================================
global $wp_query;
$visible_total = 0;

if ($wp_query->posts) {
    foreach ($wp_query->posts as $post) {
        $product = wc_get_product($post->ID);
        if ($product) {
            $sku = $product->get_sku();
            if (!$sku || !isset($excluded_skus[$sku])) {
                $visible_total++;
            }
        }
    }
}
wc_set_loop_prop('total', $visible_total);

// ==========================================
// 3. HTML Output (Broken out of PHP for performance)
// ==========================================
?>

<div class="shop-archive-container single-custom-container">
    
    <div class="shop-toolbar">
        <?php do_action('woocommerce_before_shop_loop'); ?>
    </div>

    <div class="shop-layout">
        
        <div class="shop-filters">
		<?php echo do_shortcode('[wpf-filters id=12]'); ?>
        </div>

        <?php if (woocommerce_product_loop()) : ?>
            <div class="products-grid">
                <?php
                woocommerce_product_loop_start();

                if (wc_get_loop_prop('total')) {
                    while (have_posts()) {
                        the_post();

                        $product_id  = get_the_ID();
                        $product     = wc_get_product($product_id);
                        $product_sku = $product ? $product->get_sku() : '';

                        // Skip excluded SKUs
                        if ($product_sku && isset($excluded_skus[$product_sku])) {
                            continue;
                        }

                        do_action('woocommerce_shop_loop');
                        wc_get_template_part('content', 'product');
                    }
                }
                ?>
                
                <li class="product-category product pipe">
                    <a href="https://fhs.com.au/pipe-fittings-and-sheet/">
                        <img src="https://fhs.com.au/wp-content/uploads/2026/03/Pipe-Fittings-Sheet.png" alt="Pipe Fittings & Sheet">
                        <div class="custom-category-heading-container">
                            <div class="custom-category-heading">
                                <span>Pipe Fittings & Sheet</span>
                            </div>
                            <button class="custom-category-button">View More</button>
                        </div>
                    </a>
                </li>

                <?php woocommerce_product_loop_end(); ?>
            </div>
        <?php else : ?>
            <div class="products-grid">
                <?php do_action('woocommerce_no_products_found'); ?>
            </div>
        <?php endif; ?>

    </div> <!-- .shop-layout -->
</div> <!-- .shop-archive-container -->

<?php
if (woocommerce_product_loop()) {
    do_action('woocommerce_after_shop_loop');
}

do_action('woocommerce_after_main_content');
do_action('woocommerce_sidebar');

get_footer('shop');