<?php
/**
 * Uninstall handler for DevTools.
 *
 * Runs only when the plugin is deleted from the WordPress admin. Data is
 * preserved unless the "Delete all data on uninstall" option was enabled on
 * the DevTools screen.
 *
 * @package DevTools
 */

// Exit if not called by WordPress during uninstall.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Respect the opt-in. Default: keep everything.
if (!get_option('dev_tools_delete_data_on_uninstall', false)) {
    return;
}

// Prevent the controller and mini-plugin files from booting their runtime
// hooks when we include them purely to reach the static uninstall() methods.
if (!defined('DEVTOOLS_LIFECYCLE_RUN')) {
    define('DEVTOOLS_LIFECYCLE_RUN', true);
}

require_once __DIR__ . '/dev-tools.php';

if (class_exists('DevTools')) {
    // Clean every module — including inactive ones — so nothing is left behind.
    foreach (DevTools::get_mini_plugin_definitions() as $plugin_key => $data) {
        DevTools::run_lifecycle($plugin_key, 'uninstall');
    }
}

// Remove the controller's own options.
delete_option('dev_tools_active_plugins');
delete_option('dev_tools_delete_data_on_uninstall');

/*
 * Note: this performs single-site cleanup. On a multisite network, wrap the
 * module loop and delete_option() calls in a get_sites() / switch_to_blog()
 * loop to purge per-site tables, options and postmeta.
 */
