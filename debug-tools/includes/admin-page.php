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

$runtime  = $state['runtime'];
$writable = $state['config_writable'];

$levels = array(
    'fatal'      => __('Fatal', 'suitewp'),
    'error'      => __('Error', 'suitewp'),
    'warning'    => __('Warning', 'suitewp'),
    'notice'     => __('Notice', 'suitewp'),
    'deprecated' => __('Deprecated', 'suitewp'),
    'other'      => __('Other', 'suitewp'),
);
?>
<div class="wrap suitewp-debug">
    <h1><?php esc_html_e('Debug &amp; Logs', 'suitewp'); ?></h1>

    <?php if (!$writable) : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('wp-config.php is not writable. Saving will show a block you can paste manually.', 'suitewp'); ?></p>
        </div>
    <?php endif; ?>

    <div id="suitewp-debug-notice" class="notice" style="display:none;"><p></p></div>

    <div class="suitewp-debug-grid">
        <div class="suitewp-debug-card">
            <h2><?php esc_html_e('Debug settings', 'suitewp'); ?></h2>

            <form id="suitewp-debug-form">
                <label class="suitewp-debug-toggle is-master">
                    <input type="checkbox" name="wp_debug" <?php checked($settings['wp_debug']); ?> />
                    <span><code>WP_DEBUG</code> — <?php esc_html_e('master switch', 'suitewp'); ?></span>
                </label>

                <div class="suitewp-debug-sub">
                    <label class="suitewp-debug-toggle">
                        <input type="checkbox" name="wp_debug_log" <?php checked($settings['wp_debug_log']); ?> />
                        <span><code>WP_DEBUG_LOG</code> — <?php esc_html_e('write errors to debug.log', 'suitewp'); ?></span>
                    </label>
                    <label class="suitewp-debug-toggle">
                        <input type="checkbox" name="wp_debug_display" <?php checked($settings['wp_debug_display']); ?> />
                        <span><code>WP_DEBUG_DISPLAY</code> — <?php esc_html_e('show errors on screen (avoid on live sites)', 'suitewp'); ?></span>
                    </label>
                    <label class="suitewp-debug-toggle">
                        <input type="checkbox" name="script_debug" <?php checked($settings['script_debug']); ?> />
                        <span><code>SCRIPT_DEBUG</code> — <?php esc_html_e('load unminified core assets', 'suitewp'); ?></span>
                    </label>
                    <label class="suitewp-debug-toggle">
                        <input type="checkbox" name="savequeries" <?php checked($settings['savequeries']); ?> />
                        <span><code>SAVEQUERIES</code> — <?php esc_html_e('record DB queries (uses memory)', 'suitewp'); ?></span>
                    </label>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save settings', 'suitewp'); ?></button>
                    <button type="button" id="suitewp-debug-restore" class="button"<?php echo $state['has_backup'] ? '' : ' style="display:none;"'; ?>>
                        <?php esc_html_e('Restore wp-config backup', 'suitewp'); ?>
                    </button>
                </p>
            </form>

            <div id="suitewp-debug-manual" class="suitewp-debug-manual" style="display:none;">
                <p><?php esc_html_e('Paste this into wp-config.php, just above the "stop editing" line:', 'suitewp'); ?></p>
                <textarea readonly rows="7"></textarea>
            </div>

            <p class="suitewp-debug-runtime" id="suitewp-debug-runtime"
               data-active="<?php echo $runtime['wp_debug'] ? '1' : '0'; ?>">
                <?php
                echo $runtime['wp_debug']
                    ? esc_html__('Debug is currently ACTIVE on the site.', 'suitewp')
                    : esc_html__('Debug is currently OFF on the site.', 'suitewp');
                ?>
            </p>
        </div>

        <div class="suitewp-debug-card suitewp-debug-viewer">
            <h2><?php esc_html_e('Debug log', 'suitewp'); ?></h2>

            <div class="suitewp-debug-toolbar">
                <div class="suitewp-debug-filters">
                    <?php foreach ($levels as $key => $label) : ?>
                        <label class="suitewp-debug-level lvl-<?php echo esc_attr($key); ?>">
                            <input type="checkbox" value="<?php echo esc_attr($key); ?>" checked />
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="suitewp-debug-tools">
                    <input type="search" id="suitewp-debug-search" placeholder="<?php esc_attr_e('Search…', 'suitewp'); ?>" />
                    <label class="suitewp-debug-auto">
                        <input type="checkbox" id="suitewp-debug-autorefresh" /> <?php esc_html_e('Auto', 'suitewp'); ?>
                    </label>
                    <button type="button" class="button" id="suitewp-debug-refresh"><?php esc_html_e('Refresh', 'suitewp'); ?></button>
                    <button type="button" class="button" id="suitewp-debug-download"><?php esc_html_e('Download', 'suitewp'); ?></button>
                    <button type="button" class="button button-link-delete" id="suitewp-debug-clear"><?php esc_html_e('Clear', 'suitewp'); ?></button>
                </div>
            </div>

            <div class="suitewp-debug-metabar" id="suitewp-debug-meta"></div>
            <div class="suitewp-debug-log" id="suitewp-debug-log" aria-live="polite"></div>
        </div>
    </div>
</div>
