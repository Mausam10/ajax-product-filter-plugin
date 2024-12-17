<?php
if (!defined('ABSPATH')) {
    exit;
}

class APF_Price_Slider_Widget extends WP_Widget {
    
    public function __construct() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        parent::__construct(
            'apf_price_slider',
            __('Product Price Filter (AJAX)', 'ajax-product-filter'),
            array(
                'description' => __('Filter WooCommerce products by price range', 'ajax-product-filter'),
                'classname' => 'widget_apf_price_filter',
            )
        );

        // Add AJAX handlers
        add_action('wp_ajax_filter_by_price', array($this, 'filter_by_price'));
        add_action('wp_ajax_nopriv_filter_by_price', array($this, 'filter_by_price'));
        
        // Add scripts
        if (is_active_widget(false, false, $this->id_base, true)) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        }
    }

    public function enqueue_scripts() {
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }

        // Enqueue jQuery UI
        wp_enqueue_script('jquery-ui-slider');
        
        // Enqueue jQuery UI styles
        wp_enqueue_style(
            'jquery-ui-style',
            'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
            array(),
            '1.13.2'
        );

        // Enqueue our custom styles
        wp_enqueue_style(
            'price-slider-style',
            plugins_url('assets/css/price-slider.css', dirname(__FILE__)),
            array('jquery-ui-style'),
            '1.0.0'
        );

        // Enqueue custom script
        wp_enqueue_script(
            'price-slider-script',
            plugins_url('assets/js/price-slider.js', dirname(__FILE__)),
            array('jquery', 'jquery-ui-slider'),
            '1.0.0',
            true
        );

        // Get price range
        $prices = $this->get_filtered_price_range();

        // Pass data to script
        wp_localize_script('price-slider-script', 'priceSliderData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'min_price' => $prices['min'],
            'max_price' => $prices['max'],
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'currency_pos' => get_option('woocommerce_currency_pos'),
            'price_format' => get_woocommerce_price_format(),
            'action' => 'filter_by_price',
            'nonce' => wp_create_nonce('price_slider_nonce'),
            'text' => array(
                'min_price' => __('Minimum price', 'ajax-product-filter'),
                'max_price' => __('Maximum price', 'ajax-product-filter')
            )
        ));
    }

    private function get_filtered_price_range() {
        global $wpdb;

        $args = array(
            'status' => 'publish',
            'visibility' => 'catalog'
        );

        if (is_product_category()) {
            $term = get_queried_object();
            $args['category'] = array($term->slug);
        }

        $query = new WC_Product_Query($args);
        $products = $query->get_products();

        $prices = array();
        foreach ($products as $product) {
            $price = $product->get_price();
            if ($price !== '') {
                $prices[] = floatval($price);
            }
        }

        if (empty($prices)) {
            return array('min' => 0, 'max' => 1000);
        }

        return array(
            'min' => floor(min($prices)),
            'max' => ceil(max($prices))
        );
    }

    public function widget($args, $instance) {
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }

        // Get settings
        $title = !empty($instance['title']) ? $instance['title'] : __('Filter by price', 'ajax-product-filter');
        $prices = $this->get_filtered_price_range();
        
        // Start widget
        echo $args['before_widget'];
        
        if (!empty($title)) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }
        ?>
        <div class="price-filter-wrapper">
            <form class="price-filter-form">
                <?php wp_nonce_field('price_slider_nonce', 'price_slider_nonce'); ?>
                
                <div class="price-slider-amount">
                    <div class="price-label">
                        <span class="from-text"><?php esc_html_e('From:', 'ajax-product-filter'); ?></span>
                        <span class="price-from"><?php echo wc_price($prices['min']); ?></span>
                        <span class="to-text"><?php esc_html_e('to', 'ajax-product-filter'); ?></span>
                        <span class="price-to"><?php echo wc_price($prices['max']); ?></span>
                    </div>
                </div>

                <div id="price-slider" 
                     class="price-slider"
                     data-min="<?php echo esc_attr($prices['min']); ?>"
                     data-max="<?php echo esc_attr($prices['max']); ?>"
                     data-step="<?php echo esc_attr(apply_filters('apf_price_filter_step', 1)); ?>"
                     role="slider"
                     aria-valuemin="<?php echo esc_attr($prices['min']); ?>"
                     aria-valuemax="<?php echo esc_attr($prices['max']); ?>"
                     aria-valuenow="<?php echo esc_attr($prices['min']); ?>,<?php echo esc_attr($prices['max']); ?>"
                     aria-label="<?php esc_attr_e('Price range slider', 'ajax-product-filter'); ?>">
                </div>

                <input type="hidden" 
                       name="min_price" 
                       value="<?php echo esc_attr($prices['min']); ?>"
                       class="min-price-input">
                <input type="hidden" 
                       name="max_price" 
                       value="<?php echo esc_attr($prices['max']); ?>"
                       class="max-price-input">

                <!-- <button type="submit" class="button">
                    <?php //esc_html_e('Filter', 'ajax-product-filter'); ?>
                </button> -->
            </form>
        </div>
        <?php
        echo $args['after_widget'];
    }

    public function filter_by_price() {
        check_ajax_referer('price_slider_nonce', 'nonce');

        $min_price = isset($_POST['min_price']) ? floatval($_POST['min_price']) : 0;
        $max_price = isset($_POST['max_price']) ? floatval($_POST['max_price']) : PHP_FLOAT_MAX;

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => apply_filters('apf_price_filter_posts_per_page', -1),
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_price',
                    'value' => array($min_price, $max_price),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                )
            )
        );

        // Add category filter if on category page
        if (is_product_category()) {
            $current_cat = get_queried_object();
            if ($current_cat) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $current_cat->term_id
                );
            }
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
        } else {
            wc_no_products_found();
        }

        wp_reset_postdata();

        $response = array(
            'html' => ob_get_clean(),
            'count' => $query->found_posts,
            'min_price' => $min_price,
            'max_price' => $max_price
        );

        wp_send_json_success($response);
    }

    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : __('Filter by price', 'ajax-product-filter');
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'ajax-product-filter'); ?>
            </label>
            <input class="widefat" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        return $instance;
    }
}

function register_price_slider_widget() {
    register_widget('APF_Price_Slider_Widget');
}
add_action('widgets_init', 'register_price_slider_widget');