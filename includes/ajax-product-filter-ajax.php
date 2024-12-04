<?php
if (!defined('ABSPATH')) {
    exit;
}

class APF_Ajax_Handler {
    public function filter_products() {
        try {
            check_ajax_referer('apf_filter_nonce', 'nonce');

            $filters = isset($_POST['filters']) ? (array)$_POST['filters'] : array();
            $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;

            // Build WP_Query arguments
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => get_option('posts_per_page'),
                'paged' => $paged,
            );

            // Add taxonomy queries if filters are set
            if (!empty($filters)) {
                $tax_query = array('relation' => 'AND');
                
                foreach ($filters as $taxonomy => $terms) {
                    if (!empty($terms)) {
                        $tax_query[] = array(
                            'taxonomy' => sanitize_key($taxonomy),
                            'field' => 'slug',
                            'terms' => array_map('sanitize_title', (array)$terms),
                            'operator' => 'IN',
                        );
                    }
                }
                
                $args['tax_query'] = $tax_query;
            }

            // Add WooCommerce specific queries
            $args['meta_query'] = WC()->query->get_meta_query();
            $args['tax_query'][] = WC()->query->get_tax_query();

            // Perform the query
            $query = new WP_Query($args);
            
            ob_start();

            if ($query->have_posts()) {
                woocommerce_product_loop_start();
                
                while ($query->have_posts()) {
                    $query->the_post();
                    wc_get_template_part('content', 'product');
                }
                
                woocommerce_product_loop_end();
            } else {
                do_action('woocommerce_no_products_found');
            }

            $products_html = ob_get_clean();
            wp_reset_postdata();

            wp_send_json_success(array(
                'html' => $products_html,
                'found_posts' => $query->found_posts,
                'max_pages' => $query->max_num_pages,
                'current_page' => $paged
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
}