<?php
/**
 * Debug & Logs admin page.
 *
 * @var array $settings  Current saved settings.
 * @var array $state     Runtime/config state from current_state().
 */

if (!defined('ABSPATH')) {
    exit;
}

$runtime  = $state['runtime']; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
$writable = $state['config_writable']; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.

$levels = array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
    'fatal'      => __('Fatal', 'dev-tools'),
    'error'      => __('Error', 'dev-tools'),
    'warning'    => __('Warning', 'dev-tools'),
    'notice'     => __('Notice', 'dev-tools'),
    'deprecated' => __('Deprecated', 'dev-tools'),
    'other'      => __('Other', 'dev-tools'),
);
?>
<div class="wrap devtools-debug">
    <h1><?php esc_html_e('Debug &amp; Logs', 'dev-tools'); ?></h1>

    <?php if (!$writable) : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('wp-config.php is not writable. Saving will show a block you can paste manually.', 'dev-tools'); ?></p>
        </div>
    <?php endif; ?>

    <div id="devtools-debug-notice" class="notice" style="display:none;"><p></p></div>

    <div class="devtools-debug-grid">
        <div class="devtools-debug-card">
            <h2><?php esc_html_e('Debug settings', 'dev-tools'); ?></h2>

            <form id="devtools-debug-form">
                <label class="devtools-debug-toggle is-master">
                    <input type="checkbox" name="wp_debug" <?php checked($settings['wp_debug']); ?> />
                    <span><code>WP_DEBUG</code> — <?php esc_html_e('master switch', 'dev-tools'); ?></span>
                </label>

                <div class="devtools-debug-sub">
                    <label class="devtools-debug-toggle">
                        <input type="checkbox" name="wp_debug_log" <?php checked($settings['wp_debug_log']); ?> />
                        <span><code>WP_DEBUG_LOG</code> — <?php esc_html_e('write errors to debug.log', 'dev-tools'); ?></span>
                    </label>
                    <label class="devtools-debug-toggle">
                        <input type="checkbox" name="wp_debug_display" <?php checked($settings['wp_debug_display']); ?> />
                        <span><code>WP_DEBUG_DISPLAY</code> — <?php esc_html_e('show errors on screen (avoid on live sites)', 'dev-tools'); ?></span>
                    </label>
                    <label class="devtools-debug-toggle">
                        <input type="checkbox" name="script_debug" <?php checked($settings['script_debug']); ?> />
                        <span><code>SCRIPT_DEBUG</code> — <?php esc_html_e('load unminified core assets', 'dev-tools'); ?></span>
                    </label>
                    <label class="devtools-debug-toggle">
                        <input type="checkbox" name="savequeries" <?php checked($settings['savequeries']); ?> />
                        <span><code>SAVEQUERIES</code> — <?php esc_html_e('record DB queries (uses memory)', 'dev-tools'); ?></span>
                    </label>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save settings', 'dev-tools'); ?></button>
                    <button type="button" id="devtools-debug-restore" class="button"<?php echo $state['has_backup'] ? '' : ' style="display:none;"'; ?>>
                        <?php esc_html_e('Restore wp-config backup', 'dev-tools'); ?>
                    </button>
                </p>
            </form>

            <div id="devtools-debug-manual" class="devtools-debug-manual" style="display:none;">
                <p><?php esc_html_e('Paste this into wp-config.php, just above the "stop editing" line:', 'dev-tools'); ?></p>
                <textarea readonly rows="7"></textarea>
            </div>

            <p class="devtools-debug-runtime" id="devtools-debug-runtime"
               data-active="<?php echo $runtime['wp_debug'] ? '1' : '0'; ?>">
                <?php
                echo $runtime['wp_debug']
                    ? esc_html__('Debug is currently ACTIVE on the site.', 'dev-tools')
                    : esc_html__('Debug is currently OFF on the site.', 'dev-tools');
                ?>
            </p>
        </div>

        <div class="devtools-debug-card devtools-debug-viewer">
            <h2><?php esc_html_e('Debug log', 'dev-tools'); ?></h2>

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
                    <input type="search" id="devtools-debug-search" placeholder="<?php esc_attr_e('Search…', 'dev-tools'); ?>" />
                    <label class="devtools-debug-auto">
                        <input type="checkbox" id="devtools-debug-autorefresh" /> <?php esc_html_e('Auto', 'dev-tools'); ?>
                    </label>
                    <button type="button" class="button" id="devtools-debug-refresh"><?php esc_html_e('Refresh', 'dev-tools'); ?></button>
                    <button type="button" class="button" id="devtools-debug-download"><?php esc_html_e('Download', 'dev-tools'); ?></button>
                    <button type="button" class="button button-link-delete" id="devtools-debug-clear"><?php esc_html_e('Clear', 'dev-tools'); ?></button>
                </div>
            </div>

            <div class="devtools-debug-metabar" id="devtools-debug-meta"></div>
            <div class="devtools-debug-log" id="devtools-debug-log" aria-live="polite"></div>
        </div>
    </div>
</div>
