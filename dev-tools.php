<?php
/*
Plugin Name: DevTools
Description: Controller plugin that manages and allows individual activation/deactivation of WordPress mini-plugins.
Version: 2.0.0
Author: JorgeML
*/

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main DevTools Plugin Controller.
 */
final class DevTools {
    private const VERSION       = '2.0.0';
    private const OPTION_KEY     = 'dev_tools_active_plugins';
    private const OPTION_DELETE_DATA = 'dev_tools_delete_data_on_uninstall';
    private const MENU_SLUG   = 'dev-tools';
    private const CAPABILITY  = 'manage_options';
    private const NONCE_FIELD = 'nonce';
    private const NONCE_ACTION = 'dev_tools_toggle_plugin';

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
        add_action('wp_ajax_dev_tools_toggle_plugin', array($this, 'ajax_toggle_plugin'));
        add_action('wp_ajax_dev_tools_set_uninstall_pref', array($this, 'ajax_set_uninstall_pref'));
    }

    /**
     * Handle tasks that should run once WordPress has loaded plugins.
     */
    public function on_plugins_loaded(): void {
        $this->load_text_domain();
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
                'name'        => __('Page State Management', 'dev-tools'),
                'description' => __('Complete page state management system with status tracking, notes, and responsive design checkboxes.', 'dev-tools'),
                'file'        => $base_path . 'page-state/page-state.php',
                'class'       => 'DevToolsPageState',
                'version'     => '2.0.0',
                'icon'        => 'dashicons-edit-page',
            ),
            'page-tabs'    => array(
                'name'        => __('Page Tabs Organizer', 'dev-tools'),
                'description' => __('Organize WordPress pages with customizable tabs to improve admin panel management.', 'dev-tools'),
                'file'        => $base_path . 'tabs/page-tabs-organizer.php',
                'class'       => 'DevToolsPageTabs',
                'version'     => '1.0.8',
                'icon'        => 'dashicons-category',
            ),
            'comment-pins' => array(
                'name'        => __('Comment Pins', 'dev-tools'),
                'description' => __('Visual comment pins system for WordPress. Add visual comments anywhere on a page.', 'dev-tools'),
                'file'        => $base_path . 'comment-pins/comment-pins.php',
                'class'       => 'DevToolsCommentPins',
                'version'     => '2.2.0',
                'icon'        => 'dashicons-admin-comments',
            ),
            'debug-tools'  => array(
                'name'        => __('Debug & Logs', 'dev-tools'),
                'description' => __('Toggle WordPress debugging and read the debug log from the admin, without FTP or server access.', 'dev-tools'),
                'file'        => $base_path . 'debug-tools/debug-tools.php',
                'class'       => 'DevToolsDebug',
                'version'     => '1.0.1',
                'icon'        => 'dashicons-code',
            ),
        );

        $cache = apply_filters('dev_tools_mini_plugins', $plugins);

        return $cache;
    }

    /**
     * Load translations for the controller.
     */
    private function load_text_domain(): void {
        load_plugin_textdomain('dev-tools', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Add the DevTools menu entry.
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('DevTools', 'dev-tools'),
            __('DevTools', 'dev-tools'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array($this, 'render_admin_page'),
            'dashicons-admin-plugins',
            30
        );
    }

    /**
     * Enqueue controller assets within the admin area.
     */
    public function enqueue_admin_assets(string $hook): void {
        if ('toplevel_page_' . self::MENU_SLUG !== $hook) {
            return;
        }

        wp_enqueue_script(
            'devtools-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            array('jquery'),
            self::VERSION,
            true
        );

        wp_enqueue_style(
            'devtools-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            self::VERSION
        );

        wp_localize_script(
            'devtools-admin',
            'dev_tools_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce(self::NONCE_ACTION),
                'strings'  => array(
                    'activating'        => __('Activating...', 'dev-tools'),
                    'deactivating'      => __('Deactivating...', 'dev-tools'),
                    'error'             => __('Operation error', 'dev-tools'),
                    'generic_error'     => __('An unexpected error occurred. Please try again.', 'dev-tools'),
                    'status_active'     => __('Active', 'dev-tools'),
                    'status_inactive'   => __('Inactive', 'dev-tools'),
                    'toggle_hint'       => __('Click the switch to activate or deactivate %s.', 'dev-tools'),
                ),
            )
        );
    }

    /**
     * Render the DevTools admin page.
     */
    public function render_admin_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p class="description"><?php esc_html_e('Manage the mini-plugins included in DevTools. You can activate or deactivate each one according to your needs.', 'dev-tools'); ?></p>

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
        ?>
        <div class="devtools-plugin-card <?php echo esc_attr($state_class); ?>" data-plugin="<?php echo esc_attr($plugin_key); ?>">
            <div class="devtools-plugin-header">
                <div class="devtools-plugin-icon">
                    <span class="dashicons <?php echo esc_attr($plugin_data['icon']); ?>"></span>
                </div>
                <div class="devtools-plugin-title">
                    <h3><?php echo esc_html($plugin_data['name']); ?></h3>
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
                <p><?php echo esc_html($plugin_data['description']); ?></p>
            </div>
            <div class="devtools-plugin-status">
                <span class="devtools-status-indicator <?php echo esc_attr($state_class); ?>">
                    <?php echo $is_active ? esc_html__('Active', 'dev-tools') : esc_html__('Inactive', 'dev-tools'); ?>
                </span>
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
            <h2><?php esc_html_e('Information', 'dev-tools'); ?></h2>
            <div class="devtools-info-grid">
                <div class="devtools-info-card">
                    <h4><?php esc_html_e('How does it work?', 'dev-tools'); ?></h4>
                    <p><?php esc_html_e('DevTools acts as a controller that allows you to activate or deactivate mini-plugins individually. Each mini-plugin is loaded only when active, optimizing performance.', 'dev-tools'); ?></p>
                </div>
                <div class="devtools-info-card">
                    <h4><?php esc_html_e('Included mini-plugins', 'dev-tools'); ?></h4>
                    <ul>
                        <li>
                            <strong><?php esc_html_e('Page State Management:', 'dev-tools'); ?></strong>
                            <?php esc_html_e('Page state management', 'dev-tools'); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Page Tabs Organizer:', 'dev-tools'); ?></strong>
                            <?php esc_html_e('Tab organization', 'dev-tools'); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e('Comment Pins:', 'dev-tools'); ?></strong>
                            <?php esc_html_e('Visual comment system', 'dev-tools'); ?>
                        </li>
                    </ul>
                </div>
                <div class="devtools-info-card">
                    <h4><?php esc_html_e('Data on uninstall', 'dev-tools'); ?></h4>
                    <p><?php esc_html_e('By default your data (tabs, pins and page notes) is kept if you delete DevTools. Enable this to remove all plugin data — including database tables — when the plugin is uninstalled.', 'dev-tools'); ?></p>
                    <label class="devtools-uninstall-pref">
                        <input type="checkbox" id="devtools-delete-data" <?php checked((bool) get_option(self::OPTION_DELETE_DATA, false)); ?> />
                        <?php esc_html_e('Delete all data on uninstall', 'dev-tools'); ?>
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
                'message' => __('You do not have permission to perform this action.', 'dev-tools'),
            ));
        }

        $plugin_key = isset($_POST['plugin']) ? sanitize_key(wp_unslash($_POST['plugin'])) : '';

        if ('' === $plugin_key || !isset($this->mini_plugins[$plugin_key])) {
            wp_send_json_error(array(
                'message' => __('Plugin not found.', 'dev-tools'),
            ));
        }

        if (!isset($_POST['should_activate'])) {
            wp_send_json_error(array(
                'message' => __('Invalid request.', 'dev-tools'),
            ));
        }

        $should_activate = wp_validate_boolean(wp_unslash($_POST['should_activate']));

        if ($should_activate) {
            $this->activate_mini_plugin($plugin_key);

            wp_send_json_success(array(
                'message'     => sprintf(
                    /* translators: %s: mini-plugin name. */
                    __('%s activated successfully.', 'dev-tools'),
                    $this->mini_plugins[$plugin_key]['name']
                ),
                'status'      => 'active',
                'statusLabel' => __('Active', 'dev-tools'),
            ));
        }

        $this->deactivate_mini_plugin($plugin_key);

        wp_send_json_success(array(
            'message'     => sprintf(
                /* translators: %s: mini-plugin name. */
                __('%s deactivated successfully.', 'dev-tools'),
                $this->mini_plugins[$plugin_key]['name']
            ),
            'status'      => 'inactive',
            'statusLabel' => __('Inactive', 'dev-tools'),
        ));
    }

    /**
     * AJAX handler to persist the "delete data on uninstall" preference.
     */
    public function ajax_set_uninstall_pref(): void {
        check_ajax_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to perform this action.', 'dev-tools'),
            ));
        }

        $enabled = isset($_POST['enabled']) ? wp_validate_boolean(wp_unslash($_POST['enabled'])) : false;
        update_option(self::OPTION_DELETE_DATA, $enabled ? '1' : '');

        wp_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled
                ? __('Plugin data will be deleted on uninstall.', 'dev-tools')
                : __('Plugin data will be kept on uninstall.', 'dev-tools'),
        ));
    }
}

register_activation_hook(__FILE__, array('DevTools', 'activate'));
register_deactivation_hook(__FILE__, array('DevTools', 'deactivate'));

// The guard lets uninstall.php include this file to reach the static
// definition/lifecycle helpers without booting the admin runtime.
if (!defined('DEVTOOLS_LIFECYCLE_RUN')) {
    DevTools::get_instance();
}
