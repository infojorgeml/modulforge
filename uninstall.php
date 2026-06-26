<?php
/**
 * Uninstall handler for Modulforge.
 *
 * Runs only when the plugin is deleted from the WordPress admin. Data is
 * preserved unless the "Delete all data on uninstall" option was enabled on
 * the Modulforge screen.
 *
 * @package Modulforge
 */

// Exit if not called by WordPress during uninstall.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Respect the opt-in. Default: keep everything.
if (!get_option('modulforge_delete_data_on_uninstall', false)) {
    return;
}

// Prevent the controller and mini-plugin files from booting their runtime
// hooks when we include them purely to reach the static uninstall() methods.
if (!defined('MODULFORGE_LIFECYCLE_RUN')) {
    define('MODULFORGE_LIFECYCLE_RUN', true);
}

require_once __DIR__ . '/modulforge.php';

if (class_exists('Modulforge_Controller')) {
    // Clean every module — including inactive ones — so nothing is left behind.
    foreach (Modulforge_Controller::get_mini_plugin_definitions() as $plugin_key => $data) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
        Modulforge_Controller::run_lifecycle($plugin_key, 'uninstall');
    }
}

// Remove the controller's own options.
delete_option('modulforge_active_plugins');
delete_option('modulforge_delete_data_on_uninstall');

/*
 * Note: this performs single-site cleanup. On a multisite network, wrap the
 * module loop and delete_option() calls in a get_sites() / switch_to_blog()
 * loop to purge per-site tables, options and postmeta.
 */
