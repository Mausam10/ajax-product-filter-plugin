<?php
/**
 * Plugin Name: AJAX Product Filter for WooCommerce Shop
 * Description: A plugin that adds AJAX-based product filtering to WooCommerce.
 * Version: 1.0
 * Author: Mausam1011
 * Text Domain: ajax-product-filter
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('APF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('APF_VERSION', '1.0.0');

// Load required files early
require_once APF_PLUGIN_DIR . 'includes/ajax-product-filter-widget.php';
require_once APF_PLUGIN_DIR . 'includes/ajax-product-filter-functions.php';
require_once APF_PLUGIN_DIR . 'includes/ajax-product-filter-ajax.php';
require_once APF_PLUGIN_DIR . 'includes/class-font-loader.php';
require_once APF_PLUGIN_DIR . 'includes/class-apf-mobile-panel.php';
require_once APF_PLUGIN_DIR . 'includes/class-apf-price-slider-widget.php';
require_once APF_PLUGIN_DIR . 'includes/class-apf-ajax-handler.php';
require_once APF_PLUGIN_DIR . 'includes/class-apf-loader.php';
// require_once APF_PLUGIN_DIR . 'includes/class-apf-active-filter-widget.php';
// require_once APF_PLUGIN_DIR . 'templates/price-slider.php';


class Ajax_Product_Filter {
    private static $instance = null;


    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_not_active_notice'));
            return;
        }

        $this->init_hooks();
    }

    private function init_hooks() {
        // Register widget
        add_action('widgets_init', array($this, 'register_widget'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // AJAX hooks
        add_action('wp_ajax_apf_filter_products', array($this, 'handle_ajax_filter'));
        add_action('wp_ajax_nopriv_apf_filter_products', array($this, 'handle_ajax_filter'));
    }

    public function woocommerce_not_active_notice() {
        ?>
        <div class="error">
            <p><?php _e('AJAX Product Filter requires WooCommerce to be installed and active.', 'ajax-product-filter'); ?></p>
        </div>
        <?php
    }

    public function enqueue_scripts() {
        if (!is_shop() && !is_product_category() && !is_product_tag() && 
            !is_active_widget(false, false, 'apf_product_filter_widget', true)) {
            return;
        }

        wp_enqueue_style(
            'apf-styles',
            APF_PLUGIN_URL . 'assets/css/styles.css',
            array(),
            APF_VERSION
        );

        wp_enqueue_script(
            'apf-filter',
            APF_PLUGIN_URL . 'assets/js/ajax-filter.js',
            array('jquery'),
            APF_VERSION,
            true
        );

        wp_localize_script('apf-filter', 'apfAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('apf_filter_nonce'),
            'shop_url' => get_permalink(wc_get_page_id('shop'))
        ));
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('AJAX Filter Presets', 'ajax-product-filter'),
            __('AJAX PRODUCT FILTER', 'ajax-product-filter'),
            'manage_options',
            'apf-filter-presets',
            'apf_render_presets_page'
        );
    }

    public function render_admin_page() {
        require_once APF_PLUGIN_DIR . 'includes/admin/presets-page.php';
    }

    public function handle_ajax_filter() {
        check_ajax_referer('apf_filter_nonce', 'nonce');

        if (class_exists('APF_Ajax_Handler')) {
            $ajax_handler = new APF_Ajax_Handler();
            $ajax_handler->filter_products();
        }
    }
}

// Initialize plugin
function init_ajax_product_filter() {
    return Ajax_Product_Filter::get_instance();
}

// Hook initialization to WordPress init to ensure all required functions are available
add_action('init', 'init_ajax_product_filter');

// Debug
add_action('admin_init', function() {
    error_log('Admin init triggered');
});

// Include the admin page
function apf_include_admin_files() {
    if (is_admin()) {
        require_once plugin_dir_path(__FILE__) . 'includes/admin/presets-page.php';
    }
}
add_action('plugins_loaded', 'apf_include_admin_files');