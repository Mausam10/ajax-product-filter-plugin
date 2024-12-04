<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('apf_filter_presets');
delete_option('apf_version');

// Clean up any other plugin-specific options and meta data
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'apf_%'");