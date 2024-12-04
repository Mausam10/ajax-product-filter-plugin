<?php
if (!defined('ABSPATH')) {
    exit;
}

function apf_save_preset($data) {
    if (empty($data) || !is_array($data)) {
        error_log('APF: Invalid preset data provided');
        return false;
    }

    if (empty($data['name']) || empty($data['taxonomy'])) {
        error_log('APF: Missing required preset fields');
        return false;
    }

    $presets = get_option('apf_filter_presets', array());

    $preset_data = array(
        'name' => sanitize_text_field($data['name']),
        'taxonomy' => sanitize_key($data['taxonomy']),
        'allow_multiple' => !empty($data['allow_multiple']),
        'show_hierarchy' => !empty($data['show_hierarchy']),
        'created_at' => current_time('mysql'),
        'created_by' => get_current_user_id()
    );

    if (!empty($data['preset_id'])) {
        $preset_id = absint($data['preset_id']);
        if (isset($presets[$preset_id])) {
            $preset_data['modified_at'] = current_time('mysql');
            $preset_data['modified_by'] = get_current_user_id();
            $presets[$preset_id] = array_merge($presets[$preset_id], $preset_data);
        } else {
            error_log('APF: Invalid preset ID for update');
            return false;
        }
    } else {
        $presets[] = $preset_data;
    }

    return update_option('apf_filter_presets', $presets);
}

function apf_get_filter_presets() {
    return get_option('apf_filter_presets', array());
}

function apf_delete_preset($preset_id) {
    if (empty($preset_id)) {
        return false;
    }

    $presets = get_option('apf_filter_presets', array());
    $preset_id = absint($preset_id);

    if (!isset($presets[$preset_id])) {
        return false;
    }

    unset($presets[$preset_id]);
    return update_option('apf_filter_presets', $presets);
}

function apf_get_available_taxonomies() {
    $taxonomies = array(
        'product_cat' => __('Product Categories', 'ajax-product-filter'),
        'product_tag' => __('Product Tags', 'ajax-product-filter')
    );

    if (function_exists('wc_get_attribute_taxonomies')) {
        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $attribute) {
            $taxonomy = wc_attribute_taxonomy_name($attribute->attribute_name);
            $taxonomies[$taxonomy] = $attribute->attribute_label;
        }
    }

    return apply_filters('apf_available_taxonomies', $taxonomies);
}

function apf_is_valid_taxonomy($taxonomy) {
    if (empty($taxonomy)) {
        return false;
    }

    $valid_taxonomies = array_keys(apf_get_available_taxonomies());
    return in_array($taxonomy, $valid_taxonomies, true);
}

function apf_get_taxonomy_terms($taxonomy, $args = array()) {
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('invalid_taxonomy', __('Invalid taxonomy', 'ajax-product-filter'));
    }

    $defaults = array(
        'hide_empty' => true,
        'hierarchical' => is_taxonomy_hierarchical($taxonomy)
    );

    $args = wp_parse_args($args, $defaults);
    
    try {
        $terms = get_terms(array_merge(array('taxonomy' => $taxonomy), $args));
        if (is_wp_error($terms)) {
            error_log('APF Error getting terms: ' . $terms->get_error_message());
            return array();
        }
        return $terms;
    } catch (Exception $e) {
        error_log('APF Exception getting terms: ' . $e->getMessage());
        return array();
    }
}

function apf_format_preset_data($preset) {
    if (!is_array($preset)) {
        return array();
    }

    $taxonomies = apf_get_available_taxonomies();
    
    return array(
        'name' => isset($preset['name']) ? esc_html($preset['name']) : '',
        'taxonomy' => isset($preset['taxonomy']) ? esc_html($taxonomies[$preset['taxonomy']] ?? $preset['taxonomy']) : '',
        'allow_multiple' => !empty($preset['allow_multiple']),
        'show_hierarchy' => !empty($preset['show_hierarchy']),
        'created_at' => isset($preset['created_at']) ? get_date_from_gmt($preset['created_at'], get_option('date_format')) : '',
        'modified_at' => isset($preset['modified_at']) ? get_date_from_gmt($preset['modified_at'], get_option('date_format')) : ''
    );
}

if (!function_exists('apf_log')) {
    function apf_log($message, $level = 'debug') {
        if (WP_DEBUG === true) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }
}