<?php
// price-slider.php

if (!defined('ABSPATH')) {
    exit;
}

class APF_Price_Slider {
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_apf_update_price_filter', array($this, 'handle_price_filter'));
        add_action('wp_ajax_nopriv_apf_update_price_filter', array($this, 'handle_price_filter'));
    }

    public function enqueue_assets() {
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }

        wp_enqueue_style(
            'apf-price-slider',
            plugins_url('assets/css/price-slider.css', __FILE__),
            array(),
            APF_VERSION
        );

        wp_enqueue_script(
            'apf-price-slider',
            plugins_url('assets/js/price-slider.js', __FILE__),
            array('jquery', 'jquery-ui-slider'),
            APF_VERSION,
            true
        );

        // Get price range for current products query
        $price_range = $this->get_filtered_price_range();

        wp_localize_script('apf-price-slider', 'apfPriceData', array(
            'minPrice' => $price_range->min_price,
            'maxPrice' => $price_range->max_price,
            'currencySymbol' => get_woocommerce_currency_symbol(),
            'priceFormat' => get_woocommerce_price_format(),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('apf_price_filter')
        ));
    }

    private function get_filtered_price_range() {
        global $wpdb;

        // Start with base query for visible products
        $sql = "SELECT MIN(price_meta.meta_value) as min_price, 
                       MAX(price_meta.meta_value) as max_price
                FROM {$wpdb->posts} AS posts
                INNER JOIN {$wpdb->postmeta} AS price_meta ON posts.ID = price_meta.post_id
                WHERE posts.post_type = 'product'
                AND posts.post_status = 'publish'
                AND price_meta.meta_key IN ('_price', '_regular_price')";

        // Apply category/tag filters if present
        if (is_product_category() || is_product_tag()) {
            $term = get_queried_object();
            if ($term) {
                $sql .= $wpdb->prepare("
                    AND posts.ID IN (
                        SELECT object_id
                        FROM {$wpdb->term_relationships}
                        WHERE term_taxonomy_id = %d
                    )", $term->term_taxonomy_id
                );
            }
        }

        // Apply search query if present
        if (isset($_GET['s'])) {
            $search = '%' . $wpdb->esc_like($_GET['s']) . '%';
            $sql .= $wpdb->prepare("
                AND (
                    posts.post_title LIKE %s
                    OR posts.post_content LIKE %s
                )", $search, $search
            );
        }

        $price_range = $wpdb->get_row($sql);

        // Ensure we have valid prices
        $price_range->min_price = floatval($price_range->min_price);
        $price_range->max_price = floatval($price_range->max_price);

        if ($price_range->min_price === $price_range->max_price) {
            $price_range->min_price = floor($price_range->min_price);
            $price_range->max_price = ceil($price_range->max_price + 1);
        }

        return $price_range;
    }

    public function handle_price_filter() {
        check_ajax_referer('apf_price_filter', 'nonce');

        $min_price = isset($_POST['min_price']) ? floatval($_POST['min_price']) : 0;
        $max_price = isset($_POST['max_price']) ? floatval($_POST['max_price']) : PHP_FLOAT_MAX;

        // Get filtered products
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_price',
                    'value' => array($min_price, $max_price),
                    'type' => 'NUMERIC',
                    'compare' => 'BETWEEN'
                )
            )
        );

        // Add category/tag filter if on taxonomy page
        if (is_product_category() || is_product_tag()) {
            $term = get_queried_object();
            if ($term) {
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => $term->taxonomy,
                        'field' => 'term_id',
                        'terms' => $term->term_id
                    )
                );
            }
        }

        $query = new WP_Query($args);
        
        ob_start();
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                wc_get_template_part('content', 'product');
            }
        } else {
            echo '<p class="woocommerce-info">' . 
                 esc_html__('No products found in this price range.', 'ajax-product-filter') . 
                 '</p>';
        }
        wp_reset_postdata();

        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'productCount' => $query->found_posts
        ));
    }

    public function render_slider($args = array()) {
        $defaults = array(
            'wrapper_class' => '',
            'slider_class' => '',
            'show_labels' => true
        );
        $args = wp_parse_args($args, $defaults);
        
        $price_range = $this->get_filtered_price_range();
        ?>
        <div class="apf-price-filter <?php echo esc_attr($args['wrapper_class']); ?>">
            <?php if ($args['show_labels']): ?>
                <div class="price-labels">
                    <span class="min-price">
                        <?php echo wc_price($price_range->min_price); ?>
                    </span>
                    <span class="max-price">
                        <?php echo wc_price($price_range->max_price); ?>
                    </span>
                </div>
            <?php endif; ?>
            
            <div class="price-slider-wrapper">
                <div class="price-slider <?php echo esc_attr($args['slider_class']); ?>"
                     data-min="<?php echo esc_attr($price_range->min_price); ?>"
                     data-max="<?php echo esc_attr($price_range->max_price); ?>">
                </div>
            </div>
            
            <div class="price-inputs">
                <input type="hidden" class="min-price-input" 
                       name="min_price" 
                       value="<?php echo esc_attr($price_range->min_price); ?>">
                <input type="hidden" class="max-price-input" 
                       name="max_price" 
                       value="<?php echo esc_attr($price_range->max_price); ?>">
            </div>
        </div>
        <?php
    }
}

// Initialize the price slider
function apf_init_price_slider() {
    $price_slider = APF_Price_Slider::get_instance();
    $price_slider->init();
}
add_action('init', 'apf_init_price_slider');