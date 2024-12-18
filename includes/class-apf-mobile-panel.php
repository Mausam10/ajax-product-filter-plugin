<?php
if (!defined('ABSPATH')) {
    exit;
}

class APF_Mobile_Panel {
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'add_panel_markup'));
    }

    public function enqueue_scripts() {
        // Only enqueue on shop/product archive pages
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }

        wp_enqueue_style(
            'apf-mobile-panel',
            plugins_url('assets/css/mobile-panel.css', dirname(__FILE__)),
            array(),
            APF_VERSION
        );

        wp_enqueue_script(
            'apf-mobile-panel',
            plugins_url('assets/js/mobile-panel.js', dirname(__FILE__)),
            array('jquery'),
            APF_VERSION,
            true
        );

        // Enqueue jQuery UI slider
        wp_enqueue_script('jquery-ui-slider');
        
        wp_enqueue_style(
            'jquery-ui-style',
            'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css',
            array(),
            '1.13.2'
        );

        // Get price range for localization
        $prices = $this->get_filtered_price_range();

        // Localize scripts
        wp_localize_script('apf-mobile-panel', 'apfMobilePanel', array(
            'filterText' => __('Filter Products', 'ajax-product-filter'),
            'closeText' => __('Close', 'ajax-product-filter'),
            'priceRange' => $prices,
            'currencySymbol' => get_woocommerce_currency_symbol(),
            'currencyPosition' => get_option('woocommerce_currency_pos'),
            'priceFormat' => get_woocommerce_price_format(),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('price_filter_nonce')
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

        if (!class_exists('WC_Product_Query')) {
            return array('min' => 0, 'max' => 1000);
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

    public function add_panel_markup() {
        // Only add markup on shop/product archive pages
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }

        $prices = $this->get_filtered_price_range();
        ?>
        <div class="apf-filter-overlay"></div>
        <div class="apf-filter-panel">
            <div class="apf-panel-header">
                <h3 class="apf-panel-title"><?php esc_html_e('Filter Products', 'ajax-product-filter'); ?></h3>
                <button class="apf-panel-close" aria-label="<?php esc_attr_e('Close filter panel', 'ajax-product-filter'); ?>">&times;</button>
            </div>
            <div class="apf-panel-content">
                <div class="apf-price-filter-section">
                    <h4><?php esc_html_e('Filter by Price', 'ajax-product-filter'); ?></h4>
                    <div class="price-filter-wrapper">
                        <form class="price-filter-form">
                            <?php wp_nonce_field('price_filter_nonce', 'price_filter_nonce'); ?>
                            
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
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <button class="apf-filter-toggle" aria-label="<?php esc_attr_e('Open filter panel', 'ajax-product-filter'); ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="21" x2="4" y2="14"></line>
                <line x1="4" y1="10" x2="4" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12" y2="3"></line>
                <line x1="20" y1="21" x2="20" y2="16"></line>
                <line x1="20" y1="12" x2="20" y2="3"></line>
                <line x1="1" y1="14" x2="7" y2="14"></line>
                <line x1="9" y1="8" x2="15" y2="8"></line>
                <line x1="17" y1="16" x2="23" y2="16"></line>
            </svg>
        </button>
        <?php
    }
}

// Initialize the class
new APF_Mobile_Panel();