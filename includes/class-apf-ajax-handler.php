<?php
if (!defined('ABSPATH')) {
    exit;
}

if(!class_exists('APF_Ajax_Handler')){
class APF_Ajax_Handler {
    
    public function __construct() {
        add_action('wp_ajax_apf_filter_products', array($this, 'filter_products'));
        add_action('wp_ajax_nopriv_apf_filter_products', array($this, 'filter_products'));
        
        // Add filter for product query
        add_action('woocommerce_product_query', array($this, 'modify_product_query'));
    }

    /**
     * Handle AJAX filter request
     */
    public function filter_products() {
        check_ajax_referer('apf_price_filter_nonce', 'nonce');

        $min_price = isset($_POST['min_price']) ? floatval($_POST['min_price']) : 0;
        $max_price = isset($_POST['max_price']) ? floatval($_POST['max_price']) : PHP_FLOAT_MAX;

        // Store price filter in session for non-AJAX page loads
        WC()->session->set('apf_min_price', $min_price);
        WC()->session->set('apf_max_price', $max_price);

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => apply_filters('loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page()),
            'meta_query' => array(
                array(
                    'key' => '_price',
                    'value' => array($min_price, $max_price),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                )
            )
        );

        // Maintain current category if on category page
        if (is_product_category()) {
            $current_cat = get_queried_object();
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'id',
                    'terms' => $current_cat->term_id
                )
            );
        }

        $query = new WP_Query($args);

        ob_start();

        if ($query->have_posts()) {
            woocommerce_product_loop_start();

            while ($query->have_posts()) {
                $query->the_post();
                wc_get_template_part('content', 'product');
            }

            woocommerce_product_loop_end();

            $pagination = wc_get_template_html('loop/pagination.php');
        } else {
            wc_no_products_found();
        }

        $response = array(
            'success' => true,
            'data' => array(
                'html' => ob_get_clean(),
                'count' => $query->found_posts,
                'pagination' => isset($pagination) ? $pagination : ''
            )
        );

        wp_reset_postdata();
        wp_send_json($response);
    }

    /**
     * Modify main product query for non-AJAX price filtering
     */
    public function modify_product_query($q) {
        if (!is_admin() && $q->is_main_query() && (is_shop() || is_product_category())) {
            // Get price filter from URL or session
            $min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : WC()->session->get('apf_min_price', null);
            $max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : WC()->session->get('apf_max_price', null);

            if ($min_price !== null || $max_price !== null) {
                $meta_query = $q->get('meta_query') ? $q->get('meta_query') : array();
                
                $meta_query[] = array(
                    'key' => '_price',
                    'value' => array($min_price, $max_price),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                );

                $q->set('meta_query', $meta_query);
            }
        }
    }
}
}

// Initialize AJAX handler
new APF_Ajax_Handler();