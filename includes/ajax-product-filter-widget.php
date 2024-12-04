<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APF Product Filter Widget
 */
class APF_Product_Filter_Widget extends WP_Widget {
    /**
     * Widget constructor
     */
    public function __construct() {
        parent::__construct(
            'apf_product_filter', // Base ID
            'Product Filter (AJAX)', // Widget name in admin
            array(
                'description' => __('Filter WooCommerce products with AJAX', 'ajax-product-filter'),
                'classname' => 'widget_apf_filter', // Widget CSS class
            )
        );
    }

    /**
     * Front-end display of widget
     */
    public function widget($args, $instance) {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Get widget settings
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);
        $preset_id = !empty($instance['preset_id']) ? absint($instance['preset_id']) : 0;

        // Get presets
        $presets = get_option('apf_filter_presets', array());
        
        // Check if selected preset exists
        if (empty($preset_id) || empty($presets[$preset_id])) {
            return;
        }

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        $this->render_filter_form($preset_id, $presets[$preset_id]);

        echo $args['after_widget'];
    }

    /**
     * Render the filter form
     */
    private function render_filter_form($preset_id, $preset) {
        if (empty($preset['taxonomy']) || !taxonomy_exists($preset['taxonomy'])) {
            return;
        }

        $terms = get_terms(array(
            'taxonomy' => $preset['taxonomy'],
            'hide_empty' => true,
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return;
        }
        ?>
        <div class="apf-filter-wrapper" data-preset="<?php echo esc_attr($preset_id); ?>">
            <form class="apf-filter-form">
                <?php wp_nonce_field('apf_filter_nonce', 'apf_nonce'); ?>
                
                <ul class="apf-filter-list">
                    <?php foreach ($terms as $term): ?>
                        <li class="apf-filter-item">
                            <label>
                                <input type="checkbox" 
                                       name="filter[<?php echo esc_attr($preset['taxonomy']); ?>][]" 
                                       value="<?php echo esc_attr($term->slug); ?>"
                                       class="apf-filter-checkbox">
                                <?php echo esc_html($term->name); ?>
                                <span class="count">(<?php echo esc_html($term->count); ?>)</span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <button type="button" class="apf-reset-filter button">
                    <?php esc_html_e('Reset', 'ajax-product-filter'); ?>
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Back-end widget form
     */
    public function form($instance) {
        $title = isset($instance['title']) ? $instance['title'] : '';
        $preset_id = isset($instance['preset_id']) ? $instance['preset_id'] : '';
        $presets = get_option('apf_filter_presets', array());
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
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('preset_id')); ?>">
                <?php esc_html_e('Filter Preset:', 'ajax-product-filter'); ?>
            </label>
            <select class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('preset_id')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('preset_id')); ?>">
                <option value=""><?php esc_html_e('Select a preset', 'ajax-product-filter'); ?></option>
                <?php foreach ($presets as $key => $preset): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($preset_id, $key); ?>>
                        <?php echo esc_html($preset['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <?php if (empty($presets)): ?>
            <p class="description">
                <?php 
                printf(
                    __('No presets found. <a href="%s">Create a preset</a> first.', 'ajax-product-filter'),
                    esc_url(admin_url('admin.php?page=apf-filter-presets'))
                );
                ?>
            </p>
        <?php endif;
    }

    /**
     * Sanitize widget form values as they are saved
     */
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['preset_id'] = (!empty($new_instance['preset_id'])) ? absint($new_instance['preset_id']) : '';
        return $instance;
    }
}

// Register the widget
function register_apf_widget() {
    register_widget('APF_Product_Filter_Widget');
}
add_action('widgets_init', 'register_apf_widget');