<?php
/**
 * Debug & Logs admin page.
 *
 * @var array $settings  Current saved settings (enabled, display_errors, log_token).
 * @var array $state     Runtime state from current_state().
 *
 * @package Modulforge
 */

if (!defined('ABSPATH')) {
    exit;
}

$levels = array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
    'fatal'      => __('Fatal', 'modulforge'),
    'error'      => __('Error', 'modulforge'),
    'warning'    => __('Warning', 'modulforge'),
    'notice'     => __('Notice', 'modulforge'),
    'deprecated' => __('Deprecated', 'modulforge'),
    'other'      => __('Other', 'modulforge'),
);

// Static block users can paste into wp-config.php themselves for full core
// WP_DEBUG (deprecation/doing_it_wrong notices). Shown for reference only —
// the plugin never writes to wp-config.php.
$wp_config_block = "define( 'WP_DEBUG', true );\n" .
    "define( 'WP_DEBUG_LOG', true );\n" .
    "define( 'WP_DEBUG_DISPLAY', false );\n" .
    "@ini_set( 'display_errors', 0 );"; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
?>
<div class="wrap devtools-debug">
    <h1><?php esc_html_e('Debug &amp; Logs', 'modulforge'); ?></h1>
    <p class="description">
        <?php esc_html_e('Capture PHP errors to a private log file in your uploads folder, then read, filter, download or clear it here. This does not modify wp-config.php.', 'modulforge'); ?>
    </p>

    <?php if (!$state['dir_writable']) : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('The uploads folder is not writable, so the log file cannot be created. Check your file permissions.', 'modulforge'); ?></p>
        </div>
    <?php endif; ?>

    <div id="devtools-debug-notice" class="notice" style="display:none;"><p></p></div>

    <div class="devtools-debug-grid">
        <div class="devtools-debug-card">
            <h2><?php esc_html_e('Error logging', 'modulforge'); ?></h2>

            <form id="devtools-debug-form">
                <label class="devtools-debug-toggle is-master">
                    <input type="checkbox" name="enabled" <?php checked($settings['enabled']); ?> />
                    <span><?php esc_html_e('Enable error logging', 'modulforge'); ?> — <?php esc_html_e('record PHP errors to a private log file', 'modulforge'); ?></span>
                </label>

                <div class="devtools-debug-sub">
                    <label class="devtools-debug-toggle">
                        <input type="checkbox" name="display_errors" <?php checked($settings['display_errors']); ?> />
                        <span><?php esc_html_e('Show errors on screen', 'modulforge'); ?> — <?php esc_html_e('avoid on live sites; visitors would see the errors', 'modulforge'); ?></span>
                    </label>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save settings', 'modulforge'); ?></button>
                </p>
            </form>

            <p class="devtools-debug-runtime" id="devtools-debug-runtime"
               data-active="<?php echo $state['logging_active'] ? '1' : '0'; ?>">
                <?php
                if ($state['logging_active']) {
                    esc_html_e('Logging is ACTIVE — errors are being written to the log.', 'modulforge');
                } elseif ($settings['enabled']) {
                    esc_html_e('Logging is enabled but not active yet. Reload your site, or your host may disable ini_set().', 'modulforge');
                } else {
                    esc_html_e('Logging is OFF.', 'modulforge');
                }
                ?>
            </p>

            <details class="devtools-debug-manual">
                <summary><?php esc_html_e('Need full WordPress core debugging (deprecation notices)?', 'modulforge'); ?></summary>
                <p><?php esc_html_e('That requires constants in wp-config.php, which a plugin must not edit. Paste this just above the "stop editing" line yourself:', 'modulforge'); ?></p>
                <textarea id="devtools-debug-config" readonly rows="4"><?php echo esc_textarea($wp_config_block); ?></textarea>
                <button type="button" class="button" id="devtools-debug-copy"><?php esc_html_e('Copy', 'modulforge'); ?></button>
            </details>
        </div>

        <div class="devtools-debug-card devtools-debug-viewer">
            <h2><?php esc_html_e('Log', 'modulforge'); ?></h2>

            <div class="devtools-debug-toolbar">
                <div class="devtools-debug-filters">
                    <?php foreach ($levels as $key => $label) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope. ?>
                        <label class="devtools-debug-level lvl-<?php echo esc_attr($key); ?>">
                            <input type="checkbox" value="<?php echo esc_attr($key); ?>" checked />
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="devtools-debug-tools">
                    <input type="search" id="devtools-debug-search" placeholder="<?php esc_attr_e('Search…', 'modulforge'); ?>" />
                    <label class="devtools-debug-auto">
                        <input type="checkbox" id="devtools-debug-autorefresh" /> <?php esc_html_e('Auto', 'modulforge'); ?>
                    </label>
                    <button type="button" class="button" id="devtools-debug-refresh"><?php esc_html_e('Refresh', 'modulforge'); ?></button>
                    <button type="button" class="button" id="devtools-debug-download"><?php esc_html_e('Download', 'modulforge'); ?></button>
                    <button type="button" class="button button-link-delete" id="devtools-debug-clear"><?php esc_html_e('Clear', 'modulforge'); ?></button>
                </div>
            </div>

            <div class="devtools-debug-metabar" id="devtools-debug-meta"></div>
            <div class="devtools-debug-log" id="devtools-debug-log" aria-live="polite"></div>
        </div>
    </div>
</div>
