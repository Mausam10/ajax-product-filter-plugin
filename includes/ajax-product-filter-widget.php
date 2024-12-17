<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * APF Product Filter Widget
 */
class APF_Product_Filter_Widget extends WP_Widget {
    
    private $defaults = array(
        'title' => '',
        'preset_id' => 0
    );

    public function __construct() {
        parent::__construct(
            'apf_product_filter',
            __('Product Filter (AJAX)', 'ajax-product-filter'),
            array(
                'description' => __('Filter WooCommerce products with AJAX', 'ajax-product-filter'),
                'classname' => 'widget_apf_filter',
            )
        );
    }

    public function widget($args, $instance) {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $instance = wp_parse_args($instance, $this->defaults);
        $title = apply_filters('widget_title', $instance['title'], $instance, $this->id_base);
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

    private function get_filter_terms($taxonomy) {
        // Ensure we have a valid taxonomy
        if (!taxonomy_exists($taxonomy)) {
            return array();
        }

        // Query args specific to the taxonomy type
        $args = array(
            'taxonomy' => $taxonomy,
            'hide_empty' => true,
            'fields' => 'all'
        );

        // Add specific arguments for product categories
        if ($taxonomy === 'product_cat') {
            $args['hierarchical'] = true;
            $args['orderby'] = 'name';
            $args['show_count'] = true;
            $args['pad_counts'] = true;
        }

        // Get terms specifically for this taxonomy
        $terms = get_terms($args);

        if (is_wp_error($terms)) {
            return array();
        }

        return $terms;
    }

    private function render_filter_form($preset_id, $preset) {
        if (empty($preset['taxonomy'])) {
            return;
        }

        $terms = $this->get_filter_terms($preset['taxonomy']);
        
        if (empty($terms)) {
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

                <div class="apf-filter-actions">
                    <!-- <button type="button" class="apf-reset-filter button">
                        <?php //esc_html_e('Reset', 'ajax-product-filter'); ?>
                    </button> -->
                    <!-- <button type="submit" class="apf-apply-filter button button-primary">
                        <?php //esc_html_e('Apply', 'ajax-product-filter'); ?>
                    </button> -->
                </div>
            </form>
        </div>
        <?php
    }

    public function form($instance) {
        $instance = wp_parse_args($instance, $this->defaults);
        $presets = get_option('apf_filter_presets', array());
    
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'ajax-product-filter'); ?>
            </label>
            <input class="widefat" 
                   type="text" 
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>" 
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" 
                   value="<?php echo esc_attr($instance['title']); ?>">
        </p>
        
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('preset_id')); ?>">
                <?php esc_html_e('Filter Preset:', 'ajax-product-filter'); ?>
            </label>
            <select class="widefat" 
                    id="<?php echo esc_attr($this->get_field_id('preset_id')); ?>" 
                    name="<?php echo esc_attr($this->get_field_name('preset_id')); ?>">
                <option value="0"><?php esc_html_e('Select a preset', 'ajax-product-filter'); ?></option>
                <?php foreach ($presets as $key => $preset): ?>
                    <?php
                    $preset_name = !empty($preset['name']) ? $preset['name'] : sprintf(__('Preset %d', 'ajax-product-filter'), $key);
                    $taxonomy_label = '';
                    if (!empty($preset['taxonomy'])) {
                        $taxonomy_obj = get_taxonomy($preset['taxonomy']);
                        if ($taxonomy_obj) {
                            $taxonomy_label = ' (' . $taxonomy_obj->labels->singular_name . ')';
                        }
                    }
                    ?>
                    <option value="<?php echo absint($key); ?>" <?php selected($instance['preset_id'], $key); ?>>
                        <?php echo esc_html($preset_name . $taxonomy_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="description">
                <?php esc_html_e('Select a filter preset to configure the display options.', 'ajax-product-filter'); ?>
            </span>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        return array(
            'title' => !empty($new_instance['title']) ? strip_tags($new_instance['title']) : '',
            'preset_id' => !empty($new_instance['preset_id']) ? absint($new_instance['preset_id']) : 0,
        );
    }
}

// Register the widget
add_action('widgets_init', function() {
    register_widget('APF_Product_Filter_Widget');
});