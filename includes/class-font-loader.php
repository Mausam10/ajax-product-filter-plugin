<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class APF_Font_Loader {
    public function __construct() {
        add_action('wp_head', array($this, 'preload_woo_fonts'), 1);
    }

    public function preload_woo_fonts() {
        if (class_exists('WooCommerce')) {
            $woo_font_url = plugins_url('woocommerce/assets/fonts/WooCommerce.woff2');
            echo '<link rel="preload" href="' . esc_url($woo_font_url) . '" as="font" type="font/woff2" crossorigin>';
        }
    }
}

// Initialize the class
new APF_Font_Loader();