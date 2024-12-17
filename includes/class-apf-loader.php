<?php
if (!defined('ABSPATH')) {
    exit;
}

class APF_Loader {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    private function includes() {
        require_once APF_PLUGIN_DIR . 'includes/class-apf-price-slider-widget.php';
        require_once APF_PLUGIN_DIR . 'includes/class-apf-ajax-handler.php';
    }

    private function init_hooks() {
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
        add_action('widgets_init', array($this, 'register_widgets'));
    }

    public function register_scripts() {
        // Register styles
        wp_register_style(
            'apf-styles',
            APF_PLUGIN_URL . 'assets/css/price-slider.css',
            array(),
            APF_VERSION
        );

        // Register scripts
        wp_register_script(
            'apf-scripts',
            APF_PLUGIN_URL . 'assets/js/price-slider.js',
            array('jquery', 'jquery-ui-slider'),
            APF_VERSION,
            true
        );

        // Enqueue on shop pages
        if (is_shop() || is_product_category() || is_product_tag()) {
            wp_enqueue_style('apf-styles');
            wp_enqueue_script('apf-scripts');
        }
    }

    public function register_widgets() {
        register_widget('APF_Price_Slider_Widget');
    }
}