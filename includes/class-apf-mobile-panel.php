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

        // Localize script with translations
        wp_localize_script('apf-mobile-panel', 'apfMobilePanel', array(
            'filterText' => __('Filter Products', 'ajax-product-filter'),
            'closeText' => __('Close', 'ajax-product-filter')
        ));
    }

    public function add_panel_markup() {
        // Only add markup on shop/product archive pages
        if (!is_shop() && !is_product_category() && !is_product_tag()) {
            return;
        }
        ?>
          <div class="apf-filter-overlay"></div>
    <div class="apf-filter-panel">
        <div class="apf-panel-header">
            <h3 class="apf-panel-title"><?php esc_html_e('Filter Products', 'ajax-product-filter'); ?></h3>
            <button class="apf-panel-close" aria-label="<?php esc_attr_e('Close filter panel', 'ajax-product-filter'); ?>">&times;</button>
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