<?php
/*
Plugin Name: Modulforge
Description: Controller that manages and lets you toggle bundled developer mini-tools individually.
Version: 1.0.0
Requires at least: 6.0
Requires PHP: 7.4
Author: JorgeML
Author URI: https://suitedevtools.com
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: modulforge
Domain Path: /languages
*/

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Modulforge Plugin Controller.
 */
final class Modulforge_Controller {
    private const VERSION       = '1.0.0';
    private const OPTION_KEY     = 'modulforge_active_plugins';
    private const OPTION_DELETE_DATA = 'modulforge_delete_data_on_uninstall';
    private const MENU_SLUG   = 'modulforge';
    private const CAPABILITY  = 'manage_options';
    private const NONCE_FIELD = 'nonce';
    private const NONCE_ACTION = 'modulforge_toggle_plugin';

    /**
     * Single plugin instance.
     */
    private static ?self $instance = null;

    /**
     * Registered mini-plugins definition map.
     *
     * @var array<string, array<string, string>>
     */
    private array $mini_plugins = array();

    /**
     * Cache for active mini-plugins.
     *
     * @var string[]|null
     */
    private ?array $active_plugins_cache = null;

    /**
     * Private constructor to enforce singleton.
     */
    private function __construct() {
        $this->mini_plugins = self::get_mini_plugin_definitions();
        $this->register_hooks();
    }

    /**
     * Get the single instance of the plugin controller.
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Ensure required option exists on activation and provision any
     * mini-plugin that is already flagged as active (e.g. on reactivation).
     */
    public static function activate(): void {
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, array());
        }

        $active      = get_option(self::OPTION_KEY, array());
        $definitions = self::get_mini_plugin_definitions();

        if (is_array($active)) {
            foreach ($active as $plugin_key) {
                $plugin_key = sanitize_key($plugin_key);
                if (isset($definitions[$plugin_key])) {
                    self::run_lifecycle($plugin_key, 'activate');
                }
            }
        }
    }

    /**
     * Placeholder for future clean-up tasks.
     */
    public static function deactivate(): void {
        // Intentionally left empty.
    }

    /**
     * Run a lifecycle method on a single mini-plugin.
     *
     * Loads the mini-plugin file if its class is not present yet, then calls
     * the requested static method when the mini-plugin implements it. This is
     * the single funnel for activate / deactivate / uninstall / self-heal.
     *
     * @param string $plugin_key Mini-plugin identifier.
     * @param string $method     Static lifecycle method to invoke.
     */
    public static function run_lifecycle(string $plugin_key, string $method): void {
        $definitions = self::get_mini_plugin_definitions();

        if (!isset($definitions[$plugin_key])) {
            return;
        }

        $class = $definitions[$plugin_key]['class'];
        $file  = $definitions[$plugin_key]['file'];

        if (!class_exists($class) && $file && is_readable($file)) {
            include_once $file;
        }

        if (class_exists($class) && method_exists($class, $method)) {
            call_user_func(array($class, $method));
        }
    }

    /**
     * Register WordPress hooks used by the controller.
     */
    private function register_hooks(): void {
        add_action('plugins_loaded', array($this, 'on_plugins_loaded'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_modulforge_toggle_plugin', array($this, 'ajax_toggle_plugin'));
        add_action('wp_ajax_modulforge_set_uninstall_pref', array($this, 'ajax_set_uninstall_pref'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
    }

    /**
     * Handle tasks that should run once WordPress has loaded plugins.
     */
    public function on_plugins_loaded(): void {
        $this->load_active_mini_plugins();
    }

    /**
     * Register bundled mini-plugins.
     *
     * Static so the controller, its activation hook and uninstall.php can all
     * share the same source of truth without instantiating the controller.
     * Result is memoised because the definition never changes within a request.
     *
     * @return array<string, array<string, string>>
     */
    public static function get_mini_plugin_definitions(): array {
        static $cache = null;

        if (null !== $cache) {
            return $cache;
        }

        $base_path = plugin_dir_path(__FILE__);

        $plugins = array(
            'page-state'   => array(
                'file'    => $base_path . 'page-state/page-state.php',
                'class'   => 'Modulforge_Page_State',
                'version' => '2.0.0',
            ),
            'page-tabs'    => array(
                'file'    => $base_path . 'tabs/page-tabs-organizer.php',
                'class'   => 'Modulforge_Page_Tabs',
                'version' => '1.0.8',
            ),
            'comment-pins' => array(
                'file'    => $base_path . 'comment-pins/comment-pins.php',
                'class'   => 'Modulforge_Comment_Pins',
                'version' => '2.2.0',
            ),
            'debug-tools'  => array(
                'file'    => $base_path . 'debug-tools/debug-tools.php',
                'class'   => 'Modulforge_Debug',
                'version' => '1.1.0',
            ),
            'convert-webp' => array(
                'file'    => $base_path . 'convert-webp/convert-webp.php',
                'class'   => 'Modulforge_WebP',
                'version' => '1.0.0',
            ),
        );

        $cache = apply_filters('modulforge_mini_plugins', $plugins);

        return $cache;
    }

    /**
     * Translatable labels (name + description) for each bundled mini-plugin.
     *
     * Kept separate from get_mini_plugin_definitions() so the controller can
     * boot and load modules without calling __() before the `init` hook —
     * WordPress 6.7+ warns about just-in-time textdomain loading otherwise.
     * Only invoked from admin output (render + AJAX), which runs after init.
     *
     * @return array<string, array<string, string>>
     */
    private static function get_mini_plugin_labels(): array {
        return array(
            'page-state'   => array(
                'name'        => __('Page State Management', 'modulforge'),
                'description' => __('Complete page state management system with status tracking, notes, and responsive design checkboxes.', 'modulforge'),
            ),
            'page-tabs'    => array(
                'name'        => __('Page Tabs Organizer', 'modulforge'),
                'description' => __('Organize WordPress pages with customizable tabs to improve admin panel management.', 'modulforge'),
            ),
            'comment-pins' => array(
                'name'        => __('Comment Pins', 'modulforge'),
                'description' => __('Visual comment pins system for WordPress. Add visual comments anywhere on a page.', 'modulforge'),
            ),
            'debug-tools'  => array(
                'name'        => __('Debug & Logs', 'modulforge'),
                'description' => __('Capture PHP errors to a private log file and read them from the admin, without FTP or editing wp-config.', 'modulforge'),
            ),
            'convert-webp' => array(
                'name'        => __('Convert to WebP', 'modulforge'),
                'description' => __('Convert JPEG and PNG images to WebP — bulk-convert the media library and auto-convert new uploads. Replaces and deletes the originals.', 'modulforge'),
            ),
        );
    }

    /**
     * Get the translated name + description for a single mini-plugin.
     *
     * Falls back to the raw key (and to any name/description supplied through
     * the `modulforge_mini_plugins` filter) so third-party modules keep working.
     *
     * @param array<string, string> $plugin_data Definition data for fallback.
     * @return array<string, string>
     */
    private static function get_mini_plugin_label(string $plugin_key, array $plugin_data = array()): array {
        $labels = self::get_mini_plugin_labels();
        if (isset($labels[$plugin_key])) {
            return $labels[$plugin_key];
        }
        return array(
            'name'        => isset($plugin_data['name']) ? $plugin_data['name'] : $plugin_key,
            'description' => isset($plugin_data['description']) ? $plugin_data['description'] : '',
        );
    }

    /**
     * Add the Modulforge menu entry.
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('Modulforge', 'modulforge'),
            __('Modulforge', 'modulforge'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array($this, 'render_admin_page'),
            'dashicons-admin-plugins',
            30
        );

        // Rename the auto-generated first submenu (a duplicate "Modulforge") to "Tools".
        add_submenu_page(
            self::MENU_SLUG,
            __('Tools', 'modulforge'),
            __('Tools', 'modulforge'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array($this, 'render_admin_page')
        );
    }

    /**
     * Add a "Settings" link (pointing to the Tools page) on the plugins list row.
     *
     * @param string[] $links Existing action links.
     * @return string[]
     */
    public function add_action_links(array $links): array {
        $settings = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)),
            esc_html__('Settings', 'modulforge')
        );
        array_unshift($links, $settings);
        return $links;
    }

    /**
     * Enqueue controller assets within the admin area.
     */
    public function enqueue_admin_assets(string $hook): void {
        if ('toplevel_page_' . self::MENU_SLUG !== $hook) {
            return;
        }

        wp_enqueue_script(
            'modulforge-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            array('jquery'),
            self::VERSION,
            true
        );

        wp_enqueue_style(
            'modulforge-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            self::VERSION
        );

        wp_localize_script(
            'modulforge-admin',
            'modulforge_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce(self::NONCE_ACTION),
                'strings'  => array(
                    'activating'        => __('Activating...', 'modulforge'),
                    'deactivating'      => __('Deactivating...', 'modulforge'),
                    'error'             => __('Operation error', 'modulforge'),
                    'generic_error'     => __('An unexpected error occurred. Please try again.', 'modulforge'),
                    /* translators: %s: mini-plugin name. */
                    'toggle_hint'       => __('Click the switch to activate or deactivate %s.', 'modulforge'),
                ),
            )
        );
    }

    /**
     * Render the Modulforge admin page.
     */
    public function render_admin_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php esc_html_e('Manage the mini-plugins included in Modulforge. You can activate or deactivate each one according to your needs.', 'modulforge'); ?></p>

            <div class="devtools-plugins-grid">
                <?php foreach ($this->mini_plugins as $plugin_key => $plugin_data) : ?>
                    <?php $this->render_plugin_card($plugin_key, $plugin_data, $this->is_mini_plugin_active($plugin_key)); ?>
                <?php endforeach; ?>
            </div>

            <?php $this->render_information_section(); ?>
        </div>
        <?php
    }

    /**
     * Output a plugin card within the admin page.
     *
     * @param string $plugin_key   Mini-plugin identifier.
     * @param array  $plugin_data  Mini-plugin metadata.
     * @param bool   $is_active    Whether the mini-plugin is active.
     */
    private function render_plugin_card(string $plugin_key, array $plugin_data, bool $is_active): void {
        $state_class = $is_active ? 'active' : 'inactive';
        $label       = self::get_mini_plugin_label($plugin_key, $plugin_data);
        ?>
        <div class="devtools-plugin-card <?php echo esc_attr($state_class); ?>" data-plugin="<?php echo esc_attr($plugin_key); ?>">
            <div class="devtools-plugin-header">
                <div class="devtools-plugin-title">
                    <h3><?php echo esc_html($label['name']); ?></h3>
                    <span class="devtools-plugin-version">v<?php echo esc_html($plugin_data['version']); ?></span>
                </div>
                <div class="devtools-plugin-toggle">
                    <label class="devtools-switch">
                        <input
                            type="checkbox"
                            class="devtools-plugin-checkbox"
                            data-plugin="<?php echo esc_attr($plugin_key); ?>"
                            <?php checked($is_active); ?>
                        />
                        <span class="devtools-slider"></span>
                    </label>
                </div>
            </div>
            <div class="devtools-plugin-description">
                <p><?php echo esc_html($label['description']); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Render static informational content for the admin page.
     */
    private function render_information_section(): void {
        ?>
        <div class="devtools-info-section">
            <h2><?php esc_html_e('Information', 'modulforge'); ?></h2>
            <div class="devtools-info-grid">
                <div class="devtools-info-card">
                    <h4><?php esc_html_e('How does it work?', 'modulforge'); ?></h4>
                    <p><?php esc_html_e('Modulforge acts as a controller that allows you to activate or deactivate mini-plugins individually. Each mini-plugin is loaded only when active, optimizing performance.', 'modulforge'); ?></p>
                </div>
                <div class="devtools-info-card">
                    <h4><?php esc_html_e('Included mini-plugins', 'modulforge'); ?></h4>
                    <ul>
                        <li>
                            <strong><?php esc_html_e('Page State Management:', 'modulforge'); ?></strong>
                            <?php esc_html_e('Page state management', 'modulforge'); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Page Tabs Organizer:', 'modulforge'); ?></strong>
                            <?php esc_html_e('Tab organization', 'modulforge'); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Comment Pins:', 'modulforge'); ?></strong>
                            <?php esc_html_e('Visual comment system', 'modulforge'); ?>
                        </li>
                    </ul>
                </div>
                <div class="devtools-info-card">
                    <h4><?php esc_html_e('Data on uninstall', 'modulforge'); ?></h4>
                    <p><?php esc_html_e('By default your data (tabs, pins and page notes) is kept if you delete Modulforge. Enable this to remove all plugin data — including database tables — when the plugin is uninstalled.', 'modulforge'); ?></p>
                    <label class="devtools-uninstall-pref">
                        <input type="checkbox" id="devtools-delete-data" <?php checked((bool) get_option(self::OPTION_DELETE_DATA, false)); ?> />
                        <?php esc_html_e('Delete all data on uninstall', 'modulforge'); ?>
                    </label>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Determine if the given mini-plugin is active.
     */
    private function is_mini_plugin_active(string $plugin_key): bool {
        return in_array($plugin_key, $this->get_active_plugins(), true);
    }

    /**
     * Retrieve active mini-plugins from cache or database.
     *
     * @return string[]
     */
    private function get_active_plugins(): array {
        if (null !== $this->active_plugins_cache) {
            return $this->active_plugins_cache;
        }

        $stored = get_option(self::OPTION_KEY, array());
        $stored = is_array($stored) ? $stored : array();

        $valid = array();
        foreach ($stored as $plugin_key) {
            $plugin_key = sanitize_key($plugin_key);
            if (isset($this->mini_plugins[$plugin_key])) {
                $valid[] = $plugin_key;
            }
        }

        $this->active_plugins_cache = array_values(array_unique($valid));

        return $this->active_plugins_cache;
    }

    /**
     * Persist the list of active mini-plugins and refresh cache.
     *
     * @param string[] $plugins List of plugin identifiers.
     */
    private function save_active_plugins(array $plugins): void {
        $sanitised = array();
        foreach ($plugins as $plugin_key) {
            $plugin_key = sanitize_key($plugin_key);
            if (isset($this->mini_plugins[$plugin_key])) {
                $sanitised[] = $plugin_key;
            }
        }

        $sanitised = array_values(array_unique($sanitised));
        $this->active_plugins_cache = $sanitised;

        update_option(self::OPTION_KEY, $sanitised);
    }

    /**
     * Load mini-plugins flagged as active.
     */
    public function load_active_mini_plugins(): void {
        foreach ($this->get_active_plugins() as $plugin_key) {
            $this->load_mini_plugin($plugin_key);
            // Version-gated self-heal: cheap no-op when the schema is current,
            // and a rescue for installs whose tables were never created.
            self::run_lifecycle($plugin_key, 'maybe_install_schema');
        }
    }

    /**
     * Include a mini-plugin file if available.
     */
    private function load_mini_plugin(string $plugin_key): void {
        if (!isset($this->mini_plugins[$plugin_key])) {
            return;
        }

        $plugin_file = $this->mini_plugins[$plugin_key]['file'];

        if ($plugin_file && is_readable($plugin_file)) {
            include_once $plugin_file;
        }
    }

    /**
     * Activate the specified mini-plugin.
     */
    private function activate_mini_plugin(string $plugin_key): void {
        $active = $this->get_active_plugins();

        if (!in_array($plugin_key, $active, true)) {
            $active[] = $plugin_key;
            $this->save_active_plugins($active);
        }

        // Loads the file, instantiates the module and runs its activation
        // routine (e.g. table creation). Idempotent, so re-activation is safe.
        self::run_lifecycle($plugin_key, 'activate');
    }

    /**
     * Deactivate the specified mini-plugin.
     */
    private function deactivate_mini_plugin(string $plugin_key): void {
        $active = array_diff($this->get_active_plugins(), array($plugin_key));
        $this->save_active_plugins($active);

        // Lets the module run light cleanup (e.g. flush_rewrite_rules).
        // Never deletes data — that only happens on uninstall.
        self::run_lifecycle($plugin_key, 'deactivate');
    }

    /**
     * AJAX handler to toggle mini-plugin state.
     */
    public function ajax_toggle_plugin(): void {
        check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'modulforge'),
            ));
        }

        $plugin_key = isset($_POST['plugin']) ? sanitize_key(wp_unslash($_POST['plugin'])) : '';

        if ('' === $plugin_key || !isset($this->mini_plugins[$plugin_key])) {
            wp_send_json_error(array(
                'message' => __('Plugin not found.', 'modulforge'),
            ));
        }

        if (!isset($_POST['should_activate'])) {
            wp_send_json_error(array(
                'message' => __('Invalid request.', 'modulforge'),
            ));
        }

        $should_activate = wp_validate_boolean(wp_unslash($_POST['should_activate'])); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_validate_boolean() casts to a strict boolean.

        if ($should_activate) {
            $this->activate_mini_plugin($plugin_key);

            wp_send_json_success(array(
                'message'     => sprintf(
                    /* translators: %s: mini-plugin name. */
                    __('%s activated successfully.', 'modulforge'),
                    self::get_mini_plugin_label($plugin_key)['name']
                ),
                'status'      => 'active',
            ));
        }

        $this->deactivate_mini_plugin($plugin_key);

        wp_send_json_success(array(
            'message'     => sprintf(
                /* translators: %s: mini-plugin name. */
                __('%s deactivated successfully.', 'modulforge'),
                self::get_mini_plugin_label($plugin_key)['name']
            ),
            'status'      => 'inactive',
        ));
    }

    /**
     * AJAX handler to persist the "delete data on uninstall" preference.
     */
    public function ajax_set_uninstall_pref(): void {
        check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'modulforge'),
            ));
        }

        $enabled = isset($_POST['enabled']) ? wp_validate_boolean(wp_unslash($_POST['enabled'])) : false; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_validate_boolean() casts to a strict boolean.
        update_option(self::OPTION_DELETE_DATA, $enabled ? '1' : '');

        wp_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled
                ? __('Plugin data will be deleted on uninstall.', 'modulforge')
                : __('Plugin data will be kept on uninstall.', 'modulforge'),
        ));
    }
}

register_activation_hook(__FILE__, array('Modulforge_Controller', 'activate'));
register_deactivation_hook(__FILE__, array('Modulforge_Controller', 'deactivate'));

// The guard lets uninstall.php include this file to reach the static
// definition/lifecycle helpers without booting the admin runtime.
if (!defined('MODULFORGE_LIFECYCLE_RUN')) {
    Modulforge_Controller::get_instance();
}
