<?php
if (!defined('ABSPATH')) {
    exit;
}

// Helper Functions
function apf_get_presets() {
    $presets = get_option('apf_filter_presets', array());
    return is_array($presets) ? $presets : array();
}

function apf_get_preset($preset_id) {
    $presets = apf_get_presets();
    return isset($presets[$preset_id]) ? $presets[$preset_id] : null;
}

function apf_get_available_taxonomies() {
    $taxonomies = array();
    
    // Add Product Categories
    if (taxonomy_exists('product_cat')) {
        $taxonomies['product_cat'] = array(
            'name' => 'product_cat',
            'label' => __('Product Categories', 'ajax-product-filter'),
            'hierarchical' => true
        );
    }
    
    // Add Product Tags
    if (taxonomy_exists('product_tag')) {
        $taxonomies['product_tag'] = array(
            'name' => 'product_tag',
            'label' => __('Product Tags', 'ajax-product-filter'),
            'hierarchical' => false
        );
    }
    
    // Add WooCommerce Product Attributes
    if (function_exists('wc_get_attribute_taxonomies')) {
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        foreach ($attribute_taxonomies as $tax) {
            $taxonomy_name = wc_attribute_taxonomy_name($tax->attribute_name);
            $taxonomies[$taxonomy_name] = array(
                'name' => $taxonomy_name,
                'label' => $tax->attribute_label,
                'hierarchical' => false
            );
        }
    }
    
    return apply_filters('apf_available_taxonomies', $taxonomies);
}

function apf_get_taxonomy_label($taxonomy) {
    $taxonomies = apf_get_available_taxonomies();
    return isset($taxonomies[$taxonomy]) ? $taxonomies[$taxonomy]['label'] : $taxonomy;
}

// Handle form submissions
function apf_handle_preset_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle Save/Update
    if (isset($_POST['action']) && $_POST['action'] === 'save_preset') {
        if (!check_admin_referer('save_preset', 'preset_nonce')) {
            wp_die('Security check failed');
        }

        $taxonomy = sanitize_key($_POST['taxonomy']);
        $available_taxonomies = apf_get_available_taxonomies();
        
        if (!isset($available_taxonomies[$taxonomy])) {
            wp_die('Invalid taxonomy selected');
        }

        $preset_data = array(
            'name' => sanitize_text_field($_POST['preset_name']),
            'taxonomy' => $taxonomy,
            'allow_multiple' => isset($_POST['allow_multiple']),
            'show_hierarchy' => isset($_POST['show_hierarchy']) && $available_taxonomies[$taxonomy]['hierarchical'],
            'modified_at' => current_time('mysql')
        );

        $presets = apf_get_presets();
        $preset_id = isset($_POST['preset_id']) ? intval($_POST['preset_id']) : null;
        
        if ($preset_id !== null) {
            if (isset($presets[$preset_id])) {
                $preset_data['created_at'] = $presets[$preset_id]['created_at'];
                $presets[$preset_id] = $preset_data;
                $status = 'updated';
            }
        } else {
            $preset_data['created_at'] = current_time('mysql');
            $presets[] = $preset_data;
            $status = 'created';
        }

        update_option('apf_filter_presets', $presets);
        
        wp_redirect(add_query_arg(array(
            'page' => 'apf-filter-presets',
            'message' => $status
        ), admin_url('admin.php')));
        exit;
    }

    // Handle Delete
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['preset'])) {
        if (!check_admin_referer('delete_preset_' . $_GET['preset'])) {
            wp_die('Security check failed');
        }

        $preset_id = intval($_GET['preset']);
        $presets = apf_get_presets();
        
        if (isset($presets[$preset_id])) {
            unset($presets[$preset_id]);
            update_option('apf_filter_presets', $presets);
            
            wp_redirect(add_query_arg(array(
                'page' => 'apf-filter-presets',
                'message' => 'deleted'
            ), admin_url('admin.php')));
            exit;
        }
    }
}
add_action('admin_init', 'apf_handle_preset_actions');

// Render Admin Page
function apf_render_presets_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $editing = false;
    $edit_preset = null;
    $edit_id = null;

    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['preset'])) {
        $edit_id = intval($_GET['preset']);
        $edit_preset = apf_get_preset($edit_id);
        if ($edit_preset) {
            $editing = true;
        }
    }

    // Display Messages
    if (isset($_GET['message'])) {
        $messages = array(
            'created' => 'Preset created successfully.',
            'updated' => 'Preset updated successfully.',
            'deleted' => 'Preset deleted successfully.'
        );
        
        if (isset($messages[$_GET['message']])) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html($messages[$_GET['message']])
            );
        }
    }

    $available_taxonomies = apf_get_available_taxonomies();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">
            <?php echo $editing ? 'Edit Filter Preset' : 'AJAX Filter Presets'; ?>
        </h1>
        
        <?php if (!$editing): ?>
            <a href="<?php echo esc_url(add_query_arg('action', 'new')); ?>" class="page-title-action">Add New</a>
        <?php endif; ?>
        
        <hr class="wp-header-end">

        <form method="post" action="" class="apf-preset-form">
            <?php wp_nonce_field('save_preset', 'preset_nonce'); ?>
            <input type="hidden" name="action" value="save_preset">
            <?php if ($editing): ?>
                <input type="hidden" name="preset_id" value="<?php echo esc_attr($edit_id); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="preset_name">Preset Name</label></th>
                    <td>
                        <input type="text" 
                               id="preset_name" 
                               name="preset_name" 
                               class="regular-text" 
                               value="<?php echo $editing ? esc_attr($edit_preset['name']) : ''; ?>" 
                               required>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="taxonomy">Filter Type</label></th>
                    <td>
                        <select id="taxonomy" name="taxonomy" required>
                            <option value=""><?php esc_html_e('Select Filter Type', 'ajax-product-filter'); ?></option>
                            <?php foreach ($available_taxonomies as $tax_name => $tax_data): ?>
                                <option value="<?php echo esc_attr($tax_name); ?>" 
                                        data-hierarchical="<?php echo esc_attr($tax_data['hierarchical']); ?>"
                                        <?php selected($editing && $edit_preset['taxonomy'] === $tax_name); ?>>
                                    <?php echo esc_html($tax_data['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Options</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" 
                                       name="allow_multiple" 
                                       value="1" 
                                       <?php checked($editing && !empty($edit_preset['allow_multiple'])); ?>>
                                Allow multiple selections
                            </label>
                            <br>
                            <label class="hierarchy-option" style="display: none;">
                                <input type="checkbox" 
                                       name="show_hierarchy" 
                                       value="1" 
                                       <?php checked($editing && !empty($edit_preset['show_hierarchy'])); ?>>
                                Show hierarchy
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>

            <?php submit_button($editing ? 'Update Preset' : 'Add Preset'); ?>
        </form>

        <?php if (!$editing): ?>
            <h2>Existing Presets</h2>
            <?php
            $presets = apf_get_presets();
            if (!empty($presets)):
            ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Filter Type</th>
                            <th>Last Modified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presets as $key => $preset): ?>
                            <tr>
                                <td><?php echo esc_html($preset['name']); ?></td>
                                <td><?php echo esc_html(apf_get_taxonomy_label($preset['taxonomy'])); ?></td>
                                <td>
                                    <?php 
                                    $modified_date = isset($preset['modified_at']) ? $preset['modified_at'] : $preset['created_at'];
                                    echo esc_html(get_date_from_gmt($modified_date, get_option('date_format') . ' ' . get_option('time_format')));
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'preset' => $key))); ?>" 
                                       class="button button-small">Edit</a>
                                    <a href="<?php echo esc_url(wp_nonce_url(
                                        add_query_arg(array('action' => 'delete', 'preset' => $key)),
                                        'delete_preset_' . $key
                                    )); ?>" 
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('Are you sure you want to delete this preset?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No presets found. Create your first preset using the form above.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <style type="text/css">
        .apf-preset-form .form-table td fieldset label {
            margin-bottom: 8px;
            display: block;
        }
        .button-link-delete {
            color: #a00;
            margin-left: 8px;
        }
        .button-link-delete:hover {
            color: #dc3232;
        }
    </style>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function updateHierarchyOption() {
            var selectedOption = $('#taxonomy option:selected');
            var isHierarchical = selectedOption.data('hierarchical');
            var hierarchyOption = $('.hierarchy-option');
            
            if (isHierarchical) {
                hierarchyOption.show();
            } else {
                hierarchyOption.hide();
                hierarchyOption.find('input').prop('checked', false);
            }
        }

        $('#taxonomy').on('change', updateHierarchyOption);
        updateHierarchyOption(); // Run on page load
    });
    </script>
    <?php
}