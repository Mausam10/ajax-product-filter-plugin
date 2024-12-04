<?php
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submissions and actions
function apf_handle_preset_actions() {
    // Handle preset saving
    if (isset($_POST['apf_action']) && $_POST['apf_action'] === 'save_preset') {
        if (!check_admin_referer('apf_save_preset', 'apf_preset_nonce')) {
            wp_die(__('Security check failed.', 'ajax-product-filter'));
        }

        $preset_data = array(
            'name' => sanitize_text_field($_POST['preset_name']),
            'taxonomy' => sanitize_key($_POST['taxonomy']),
            'allow_multiple' => isset($_POST['allow_multiple']),
            'show_hierarchy' => isset($_POST['show_hierarchy']),
            'modified_at' => current_time('mysql'),
            'modified_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        );

        $presets = get_option('apf_filter_presets', array());
        
        if (isset($_POST['preset_id'])) {
            $preset_id = intval($_POST['preset_id']);
            if (isset($presets[$preset_id])) {
                // Preserve creation date for existing presets
                $preset_data['created_at'] = $presets[$preset_id]['created_at'] ?? current_time('mysql');
                $preset_data['created_by'] = $presets[$preset_id]['created_by'] ?? get_current_user_id();
                $presets[$preset_id] = $preset_data;
                $status = 'updated';
            }
        } else {
            $presets[] = $preset_data;
            $status = 'created';
        }

        update_option('apf_filter_presets', $presets);
        
        wp_redirect(add_query_arg(array(
            'page' => 'apf-filter-presets',
            'status' => $status
        ), admin_url('admin.php')));
        exit;
    }

    // Handle preset deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['preset'])) {
        if (!check_admin_referer('delete_preset_' . $_GET['preset'])) {
            wp_die(__('Security check failed.', 'ajax-product-filter'));
        }

        $preset_id = intval($_GET['preset']);
        $presets = get_option('apf_filter_presets', array());
        
        if (isset($presets[$preset_id])) {
            unset($presets[$preset_id]);
            update_option('apf_filter_presets', $presets);
        }

        wp_redirect(add_query_arg(array(
            'page' => 'apf-filter-presets',
            'status' => 'deleted'
        ), admin_url('admin.php')));
        exit;
    }
}
add_action('admin_init', 'apf_handle_preset_actions');

// Display status messages
if (isset($_GET['status'])) {
    $status = sanitize_key($_GET['status']);
    $messages = array(
        'created' => __('Preset created successfully.', 'ajax-product-filter'),
        'updated' => __('Preset updated successfully.', 'ajax-product-filter'),
        'deleted' => __('Preset deleted successfully.', 'ajax-product-filter')
    );
    
    if (isset($messages[$status])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$status]) . '</p></div>';
    }
}

// Get preset for editing if applicable
$editing = false;
$edit_preset = array();
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['preset'])) {
    $preset_id = intval($_GET['preset']);
    $presets = get_option('apf_filter_presets', array());
    if (isset($presets[$preset_id])) {
        $editing = true;
        $edit_preset = $presets[$preset_id];
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $editing ? esc_html__('Edit Filter Preset', 'ajax-product-filter') : esc_html__('AJAX Filter Presets', 'ajax-product-filter'); ?>
    </h1>
    
    <?php if (!$editing): ?>
        <a href="<?php echo esc_url(add_query_arg('action', 'new')); ?>" class="page-title-action">
            <?php esc_html_e('Add New', 'ajax-product-filter'); ?>
        </a>
    <?php endif; ?>
    
    <hr class="wp-header-end">

    <form method="post" action="">
        <?php wp_nonce_field('apf_save_preset', 'apf_preset_nonce'); ?>
        <input type="hidden" name="apf_action" value="save_preset">
        <?php if ($editing): ?>
            <input type="hidden" name="preset_id" value="<?php echo esc_attr($preset_id); ?>">
        <?php endif; ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="preset_name"><?php esc_html_e('Preset Name', 'ajax-product-filter'); ?></label>
                </th>
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
                <th scope="row">
                    <label for="taxonomy"><?php esc_html_e('Filter Type', 'ajax-product-filter'); ?></label>
                </th>
                <td>
                    <select id="taxonomy" name="taxonomy" required>
                        <option value=""><?php esc_html_e('Select Filter Type', 'ajax-product-filter'); ?></option>
                        <option value="product_cat" <?php selected($editing && $edit_preset['taxonomy'] === 'product_cat'); ?>>
                            <?php esc_html_e('Product Categories', 'ajax-product-filter'); ?>
                        </option>
                        <option value="product_tag" <?php selected($editing && $edit_preset['taxonomy'] === 'product_tag'); ?>>
                            <?php esc_html_e('Product Tags', 'ajax-product-filter'); ?>
                        </option>
                        <?php 
                        if (function_exists('wc_get_attribute_taxonomies')) {
                            $attributes = wc_get_attribute_taxonomies();
                            if (!empty($attributes)) {
                                echo '<optgroup label="' . esc_attr__('Product Attributes', 'ajax-product-filter') . '">';
                                foreach ($attributes as $attribute) {
                                    $taxonomy = 'pa_' . $attribute->attribute_name;
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($taxonomy),
                                        selected($editing && $edit_preset['taxonomy'] === $taxonomy, true, false),
                                        esc_html($attribute->attribute_label)
                                    );
                                }
                                echo '</optgroup>';
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Options', 'ajax-product-filter'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="checkbox" 
                                   name="allow_multiple" 
                                   value="1" 
                                   <?php checked($editing && !empty($edit_preset['allow_multiple'])); ?>>
                            <?php esc_html_e('Allow multiple selections', 'ajax-product-filter'); ?>
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" 
                                   name="show_hierarchy" 
                                   value="1" 
                                   <?php checked($editing && !empty($edit_preset['show_hierarchy'])); ?>>
                            <?php esc_html_e('Show hierarchy (for hierarchical taxonomies)', 'ajax-product-filter'); ?>
                        </label>
                    </fieldset>
                </td>
            </tr>
        </table>

        <?php submit_button($editing ? __('Update Preset', 'ajax-product-filter') : __('Add Preset', 'ajax-product-filter')); ?>
    </form>

    <?php if (!$editing): ?>
        <h2><?php esc_html_e('Existing Presets', 'ajax-product-filter'); ?></h2>
        
        <?php
        $presets = get_option('apf_filter_presets', array());
        if (!empty($presets)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Name', 'ajax-product-filter'); ?></th>
                        <th scope="col"><?php esc_html_e('Filter Type', 'ajax-product-filter'); ?></th>
                        <th scope="col"><?php esc_html_e('Last Modified', 'ajax-product-filter'); ?></th>
                        <th scope="col"><?php esc_html_e('Actions', 'ajax-product-filter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presets as $key => $preset): ?>
                        <tr>
                            <td><?php echo esc_html($preset['name'] ?? ''); ?></td>
                            <td><?php echo esc_html($preset['taxonomy'] ?? ''); ?></td>
                            <td>
                                <?php 
                                $modified_date = $preset['modified_at'] ?? $preset['created_at'] ?? null;
                                if ($modified_date) {
                                    echo esc_html(get_date_from_gmt($modified_date, get_option('date_format') . ' ' . get_option('time_format'))); 
                                } else {
                                    esc_html_e('N/A', 'ajax-product-filter');
                                }
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg(array('action' => 'edit', 'preset' => $key))); ?>" 
                                   class="button button-small">
                                    <?php esc_html_e('Edit', 'ajax-product-filter'); ?>
                                </a>
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'preset' => $key)), 'delete_preset_' . $key)); ?>" 
                                   class="button button-small button-link-delete" 
                                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this preset?', 'ajax-product-filter'); ?>');">
                                    <?php esc_html_e('Delete', 'ajax-product-filter'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php esc_html_e('No presets found. Create your first filter preset using the form above.', 'ajax-product-filter'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>