<?php
/**
 * Plugin Name: Page Tabs Organizer
 * Description: Organize WordPress pages with customizable tabs to improve admin panel management.
 * Version: 1.0.8
 * Author: Jorge ML
 * License: GPL v2 or later
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DTPT_PLUGIN_URL')) {
    define('DTPT_PLUGIN_URL', plugin_dir_url(__FILE__)); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- DTPT is this module's unique prefix.
}
if (!defined('DTPT_PLUGIN_PATH')) {
    define('DTPT_PLUGIN_PATH', plugin_dir_path(__FILE__)); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- DTPT is this module's unique prefix.
}
if (!defined('DTPT_VERSION')) {
    define('DTPT_VERSION', '1.0.8'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- DTPT is this module's unique prefix.
}

if (!class_exists('DevToolsPageTabs')) :

class DevToolsPageTabs {

    const DB_VERSION        = '1.1.0';
    const DB_VERSION_OPTION = 'dtpt_db_version';

    /**
     * Per-request cache of tabs keyed by post type.
     *
     * @var array<string, array>
     */
    private $tabs_cache = array();

    /**
     * Per-request cache of page_id => tab_id.
     *
     * @var array<int, int>|null
     */
    private $relations_cache = null;

    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    public function init() {
        // Admin hooks.
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_dtpt_save_tab', array($this, 'ajax_save_tab'));
        add_action('wp_ajax_dtpt_delete_tab', array($this, 'ajax_delete_tab'));
        add_action('wp_ajax_dtpt_assign_page_to_tab', array($this, 'ajax_assign_page_to_tab'));
        add_action('wp_ajax_dtpt_remove_page_from_tab', array($this, 'ajax_remove_page_from_tab'));

        // Post list screens.
        add_action('restrict_manage_posts', array($this, 'add_tab_filter'));
        add_filter('request', array($this, 'filter_posts_by_tab'));

        // "Create New Tab" button + modal.
        add_action('manage_posts_extra_tablenav', array($this, 'add_create_tab_button'));
        add_action('admin_footer-edit.php', array($this, 'add_tab_creation_modal'));
        add_action('wp_ajax_dtpt_create_tab_quick', array($this, 'ajax_create_tab_quick'));
        add_action('wp_ajax_dtpt_update_page_tab', array($this, 'ajax_update_page_tab'));

        // Register hooks for every supported post type.
        add_action('admin_init', array($this, 'register_post_type_hooks'));
    }

    /* --------------------------------------------------------------------- */
    /* Lifecycle — invoked by the DevTools controller                          */
    /* --------------------------------------------------------------------- */

    public static function activate(): void {
        self::maybe_install_schema();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Drop all data on uninstall. Destructive — only called from uninstall.php
     * when the user opted in.
     */
    public static function uninstall(): void {
        global $wpdb;
        $relations = $wpdb->prefix . 'page_tab_relations';
        $tabs      = $wpdb->prefix . 'page_tabs';
        $wpdb->query("DROP TABLE IF EXISTS {$relations}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $wpdb->query("DROP TABLE IF EXISTS {$tabs}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        delete_option(self::DB_VERSION_OPTION);
    }

    /**
     * Create/upgrade the schema only when the stored version is behind.
     */
    public static function maybe_install_schema(): void {
        if (version_compare((string) get_option(self::DB_VERSION_OPTION, '0'), self::DB_VERSION, '>=')) {
            return;
        }

        self::create_tables();
        self::migrate_relations_to_one_to_one();

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    private static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_tabs = $wpdb->prefix . 'page_tabs';
        $sql_tabs   = "CREATE TABLE {$table_tabs} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            color varchar(7) DEFAULT '#0073aa',
            position int(11) DEFAULT 0,
            post_type varchar(20) DEFAULT 'page',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_type (post_type)
        ) {$charset_collate};";

        // 1:1 model: page_id is unique, so $wpdb->replace() reassigns cleanly.
        $table_relations = $wpdb->prefix . 'page_tab_relations';
        $sql_relations   = "CREATE TABLE {$table_relations} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            tab_id mediumint(9) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY page_id (page_id),
            KEY tab_id (tab_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_tabs);
        dbDelta($sql_relations);
    }

    /**
     * Enforce the 1:1 relation index on legacy tables. dbDelta cannot drop the
     * old composite UNIQUE, so do it explicitly and idempotently.
     */
    private static function migrate_relations_to_one_to_one(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'page_tab_relations';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
            return;
        }

        // Deduplicate by page_id, keeping the most recent row.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $wpdb->query(
            "DELETE r1 FROM {$table} r1
             INNER JOIN {$table} r2
             ON r1.page_id = r2.page_id AND r1.id < r2.id"
        );

        // Drop the legacy composite unique key if present.
        if ($wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'page_tab'")) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
            $wpdb->query("ALTER TABLE {$table} DROP INDEX page_tab"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        }

        // Ensure a UNIQUE key on page_id (replacing any non-unique one).
        $page_id_index = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'page_id'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $is_unique     = $page_id_index && (int) $page_id_index[0]->Non_unique === 0;

        if (!$is_unique) {
            if ($page_id_index) {
                $wpdb->query("ALTER TABLE {$table} DROP INDEX page_id"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
            }
            $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY page_id (page_id)"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        }
    }

    /* --------------------------------------------------------------------- */
    /* Supported post types                                                   */
    /* --------------------------------------------------------------------- */

    private function get_supported_post_types() {
        $post_types = get_post_types(array(
            'public'  => true,
            'show_ui' => true,
        ), 'objects');

        $excluded  = array('attachment', 'revision', 'nav_menu_item');
        $supported = array();

        foreach ($post_types as $post_type) {
            if (!in_array($post_type->name, $excluded, true)) {
                $supported[$post_type->name] = $post_type->label;
            }
        }

        return $supported;
    }

    public function register_post_type_hooks() {
        foreach ($this->get_supported_post_types() as $post_type => $label) {
            add_filter("views_edit-{$post_type}", array($this, 'add_tab_views'));
            add_filter("manage_{$post_type}_posts_columns", array($this, 'add_tab_column'));
            add_action("manage_{$post_type}_posts_custom_column", array($this, 'display_tab_column'), 10, 2);
        }
    }

    /* --------------------------------------------------------------------- */
    /* Admin page & assets                                                    */
    /* --------------------------------------------------------------------- */

    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=page',
            __('Manage Tabs', 'suite-devtools'),
            __('Tabs', 'suite-devtools'),
            'manage_options',
            'page-tabs-organizer',
            array($this, 'admin_page')
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'page_page_page-tabs-organizer' && $hook !== 'edit.php') {
            return;
        }

        $screen    = get_current_screen();
        $post_type = ($screen && strpos($screen->id, 'edit-') === 0)
            ? str_replace('edit-', '', $screen->id)
            : '';

        wp_enqueue_script('dtpt-admin', DTPT_PLUGIN_URL . 'assets/admin.js', array('jquery'), DTPT_VERSION, true);
        wp_enqueue_style('dtpt-admin', DTPT_PLUGIN_URL . 'assets/admin.css', array(), DTPT_VERSION);

        wp_localize_script('dtpt-admin', 'dtpt_ajax', array(
            'ajax_url'          => admin_url('admin-ajax.php'),
            'nonce'             => wp_create_nonce('dtpt_nonce'),
            'current_post_type' => $post_type,
            'strings'           => array(
                'confirm_delete'        => __('Are you sure you want to delete this tab?', 'suite-devtools'),
                'confirm_remove'        => __('Are you sure you want to remove this page from the tab?', 'suite-devtools'),
                'name_required'         => __('The tab name is required.', 'suite-devtools'),
                'select_tab'            => __('Please select a tab first.', 'suite-devtools'),
                'connection_error'      => __('Connection error', 'suite-devtools'),
                'unknown_error'         => __('Unknown error', 'suite-devtools'),
                'error'                 => __('Operation error', 'suite-devtools'),
                'success'               => __('Operation completed successfully', 'suite-devtools'),
                'create_tab'            => __('Create Tab', 'suite-devtools'),
                'update_tab'            => __('Update Tab', 'suite-devtools'),
            ),
        ));
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'suite-devtools'));
        }
        include DTPT_PLUGIN_PATH . 'includes/admin-page.php';
    }

    /* --------------------------------------------------------------------- */
    /* Post list: views, filter, column                                       */
    /* --------------------------------------------------------------------- */

    public function add_tab_views($views) {
        global $wpdb, $typenow;

        $current_post_type = $typenow ?: 'page';
        $original_views    = $views;
        $views             = array();

        if (isset($original_views['all'])) {
            $views['all'] = $original_views['all'];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $tabs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}page_tabs WHERE post_type = %s ORDER BY position ASC, name ASC",
            $current_post_type
        ));

        if ($tabs) {
            // Single aggregate query instead of one COUNT per tab (no N+1).
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
            $counts = $wpdb->get_results($wpdb->prepare(
                "SELECT ptr.tab_id, COUNT(*) AS c
                 FROM {$wpdb->prefix}page_tab_relations ptr
                 JOIN {$wpdb->prefix}posts p ON ptr.page_id = p.ID
                 WHERE p.post_status != 'trash' AND p.post_type = %s
                 GROUP BY ptr.tab_id",
                $current_post_type
            ), OBJECT_K);

            $active_filter = isset($_GET['tab_filter']) ? absint(wp_unslash($_GET['tab_filter'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter; no state change.

            foreach ($tabs as $index => $tab) {
                $count = isset($counts[$tab->id]) ? (int) $counts[$tab->id]->c : 0;

                $class = 'dtpt-tab-link';
                if ($active_filter === (int) $tab->id) {
                    $class .= ' current';
                }

                $tab_label = !empty($tab->name) ? $tab->name : 'Tab ' . ($index + 1);

                $views['tab_' . $tab->id] = sprintf(
                    '<a href="%s" class="%s" data-tab-id="%d" title="%s">%s <span class="count">(%d)</span></a>',
                    esc_url(admin_url('edit.php?post_type=' . $current_post_type . '&tab_filter=' . $tab->id)),
                    esc_attr($class),
                    (int) $tab->id,
                    esc_attr($tab->description ? $tab->description : $tab_label),
                    esc_html($tab_label),
                    $count
                );
            }
        }

        foreach ($original_views as $key => $view) {
            if ($key !== 'all') {
                $views[$key] = $view;
            }
        }

        return $views;
    }

    public function add_tab_filter() {
        global $typenow, $wpdb;

        if (!array_key_exists($typenow, $this->get_supported_post_types())) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $tabs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}page_tabs WHERE post_type = %s ORDER BY position ASC, name ASC",
            $typenow
        ));

        if ($tabs) {
            $selected = isset($_GET['tab_filter']) ? absint(wp_unslash($_GET['tab_filter'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter; no state change.

            echo '<select name="tab_filter">';
            echo '<option value="">' . esc_html__('All tabs', 'suite-devtools') . '</option>';

            foreach ($tabs as $tab) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr($tab->id),
                    selected($selected, $tab->id, false),
                    esc_html($tab->name)
                );
            }

            echo '</select>';
        }
    }

    public function filter_posts_by_tab($vars) {
        global $typenow, $wpdb;

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter; no state change.
        if (!array_key_exists($typenow, $this->get_supported_post_types())
            || !isset($_GET['tab_filter']) || '' === $_GET['tab_filter']) {
            return $vars;
        }

        $tab_id   = absint(wp_unslash($_GET['tab_filter'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter; no state change.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $post_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ptr.page_id FROM {$wpdb->prefix}page_tab_relations ptr
             JOIN {$wpdb->prefix}posts p ON ptr.page_id = p.ID
             WHERE ptr.tab_id = %d AND p.post_type = %s",
            $tab_id,
            $typenow
        ));

        $vars['post__in'] = !empty($post_ids) ? $post_ids : array(0);

        return $vars;
    }

    public function add_create_tab_button($which) {
        global $typenow;

        if (!array_key_exists($typenow, $this->get_supported_post_types()) || $which !== 'top') {
            return;
        }

        echo '<div class="alignright" style="margin-top: 1px;">';
        echo '<button type="button" id="dtpt-create-new-tab" class="button button-primary" data-post-type="' . esc_attr($typenow) . '">';
        echo esc_html__('Create New Tab', 'suite-devtools');
        echo '</button>';
        echo '</div>';
    }

    public function add_tab_creation_modal() {
        global $current_screen, $typenow;

        if ($current_screen && strpos($current_screen->id, 'edit-') === 0
            && array_key_exists($typenow, $this->get_supported_post_types())) {
            include DTPT_PLUGIN_PATH . 'includes/tab-modal.php';
        }
    }

    public function add_tab_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['page_tab'] = __('Tab', 'suite-devtools');
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }

    public function display_tab_column($column, $post_id) {
        if ($column !== 'page_tab') {
            return;
        }

        $post_type = get_post_type($post_id);
        $tabs      = $this->get_tabs_for_post_type($post_type);

        if (empty($tabs)) {
            echo '<em>' . esc_html__('No tabs available', 'suite-devtools') . '</em>';
            return;
        }

        $relations       = $this->get_relations_map();
        $assigned_tab_id = isset($relations[$post_id]) ? $relations[$post_id] : 0;

        echo '<select class="dtpt-page-tab-selector" data-page-id="' . esc_attr($post_id) . '">';
        echo '<option value="0">' . esc_html__('No tab', 'suite-devtools') . '</option>';

        foreach ($tabs as $tab) {
            printf(
                '<option value="%d" %s data-color="%s">%s</option>',
                (int) $tab->id,
                selected($assigned_tab_id, $tab->id, false),
                esc_attr($tab->color),
                esc_html($tab->name)
            );
        }

        echo '</select>';
    }

    /**
     * Tabs for a post type, cached for the duration of the request.
     */
    private function get_tabs_for_post_type($post_type) {
        if (!isset($this->tabs_cache[$post_type])) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
            $this->tabs_cache[$post_type] = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, color FROM {$wpdb->prefix}page_tabs WHERE post_type = %s ORDER BY position ASC, name ASC",
                $post_type
            ));
        }
        return $this->tabs_cache[$post_type];
    }

    /**
     * Full page_id => tab_id map, loaded once per request.
     */
    private function get_relations_map() {
        if (null === $this->relations_cache) {
            global $wpdb;
            $this->relations_cache = array();
            $rows = $wpdb->get_results("SELECT page_id, tab_id FROM {$wpdb->prefix}page_tab_relations"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
            foreach ((array) $rows as $row) {
                $this->relations_cache[(int) $row->page_id] = (int) $row->tab_id;
            }
        }
        return $this->relations_cache;
    }

    /* --------------------------------------------------------------------- */
    /* AJAX                                                                    */
    /* --------------------------------------------------------------------- */

    public function ajax_create_tab_quick() {
        check_ajax_referer('dtpt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'suite-devtools'), 403);
        }

        $name      = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $color     = isset($_POST['color']) ? sanitize_hex_color(wp_unslash($_POST['color'])) : '';
        $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : 'page';

        if ('' === $post_type || !array_key_exists($post_type, $this->get_supported_post_types())) {
            $post_type = 'page';
        }

        if (empty($name)) {
            wp_send_json_error(__('The tab name is required.', 'suite-devtools'), 400);
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $next_position = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(position) + 1 FROM {$wpdb->prefix}page_tabs WHERE post_type = %s",
            $post_type
        )) ?: 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
        $result = $wpdb->insert(
            $wpdb->prefix . 'page_tabs',
            array(
                'name'        => $name,
                'description' => '',
                'color'       => $color ?: '#0073aa',
                'position'    => $next_position,
                'post_type'   => $post_type,
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );

        if (false !== $result) {
            $tab_id = $wpdb->insert_id;
            wp_send_json_success(array(
                'tab_id'       => $tab_id,
                'message'      => __('Tab created successfully.', 'suite-devtools'),
                'redirect_url' => admin_url('edit.php?post_type=' . $post_type . '&tab_filter=' . $tab_id),
            ));
        }

        wp_send_json_error(__('Error creating the tab.', 'suite-devtools'), 500);
    }

    public function ajax_save_tab() {
        check_ajax_referer('dtpt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'suite-devtools'), 403);
        }

        $tab_id      = isset($_POST['tab_id']) ? intval(wp_unslash($_POST['tab_id'])) : 0;
        $name        = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
        $color       = isset($_POST['color']) ? sanitize_hex_color(wp_unslash($_POST['color'])) : '';
        $position    = isset($_POST['position']) ? intval(wp_unslash($_POST['position'])) : 0;

        if (empty($name)) {
            wp_send_json_error(__('The tab name is required.', 'suite-devtools'), 400);
        }

        global $wpdb;

        $data = array(
            'name'        => $name,
            'description' => $description,
            'color'       => $color ?: '#0073aa',
            'position'    => $position,
        );

        if ($tab_id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
            $result = $wpdb->update(
                $wpdb->prefix . 'page_tabs',
                $data,
                array('id' => $tab_id),
                array('%s', '%s', '%s', '%d'),
                array('%d')
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
            $result = $wpdb->insert(
                $wpdb->prefix . 'page_tabs',
                $data,
                array('%s', '%s', '%s', '%d')
            );
            $tab_id = $wpdb->insert_id;
        }

        if (false !== $result) {
            wp_send_json_success(array(
                'tab_id'  => $tab_id,
                'message' => __('Tab saved successfully.', 'suite-devtools'),
            ));
        }

        wp_send_json_error(__('Error saving the tab.', 'suite-devtools'), 500);
    }

    public function ajax_delete_tab() {
        check_ajax_referer('dtpt_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'suite-devtools'), 403);
        }

        $tab_id = isset($_POST['tab_id']) ? intval(wp_unslash($_POST['tab_id'])) : 0;
        if (!$tab_id) {
            wp_send_json_error(__('Invalid tab.', 'suite-devtools'), 400);
        }

        global $wpdb;

        $wpdb->delete($wpdb->prefix . 'page_tab_relations', array('tab_id' => $tab_id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
        $result = $wpdb->delete($wpdb->prefix . 'page_tabs', array('id' => $tab_id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.

        if (false !== $result) {
            wp_send_json_success(__('Tab deleted successfully.', 'suite-devtools'));
        }

        wp_send_json_error(__('Error deleting the tab.', 'suite-devtools'), 500);
    }

    public function ajax_update_page_tab() {
        check_ajax_referer('dtpt_nonce', 'nonce');

        $page_id = isset($_POST['page_id']) ? absint(wp_unslash($_POST['page_id'])) : 0;
        $tab_id  = isset($_POST['tab_id']) ? absint(wp_unslash($_POST['tab_id'])) : 0;

        // Per-object capability check closes the IDOR: a generic edit_pages cap
        // is not enough — the user must be able to edit THIS page.
        if (!$page_id || !current_user_can('edit_post', $page_id)) {
            wp_send_json_error(__('You do not have permission to edit this page.', 'suite-devtools'), 403);
        }

        global $wpdb;

        if ($tab_id === 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
            $result = $wpdb->delete(
                $wpdb->prefix . 'page_tab_relations',
                array('page_id' => $page_id),
                array('%d')
            );
        } else {
            if (!$this->tab_exists($tab_id)) {
                wp_send_json_error(__('Invalid tab.', 'suite-devtools'), 400);
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
            $result = $wpdb->replace(
                $wpdb->prefix . 'page_tab_relations',
                array('page_id' => $page_id, 'tab_id' => $tab_id),
                array('%d', '%d')
            );
        }

        if (false !== $result) {
            $tab_name = '';
            if ($tab_id > 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
                $tab_name = $wpdb->get_var($wpdb->prepare(
                    "SELECT name FROM {$wpdb->prefix}page_tabs WHERE id = %d",
                    $tab_id
                ));
            }

            wp_send_json_success(array(
                'message'  => $tab_id === 0
                    ? __('Page removed from all tabs.', 'suite-devtools')
                    : sprintf(/* translators: %s: tab name. */ __('Page assigned to "%s".', 'suite-devtools'), $tab_name),
                'tab_id'   => $tab_id,
                'tab_name' => $tab_name,
            ));
        }

        wp_send_json_error(__('Error updating the tab assignment.', 'suite-devtools'), 500);
    }

    public function ajax_assign_page_to_tab() {
        check_ajax_referer('dtpt_nonce', 'nonce');

        $page_id = isset($_POST['page_id']) ? absint(wp_unslash($_POST['page_id'])) : 0;
        $tab_id  = isset($_POST['tab_id']) ? absint(wp_unslash($_POST['tab_id'])) : 0;

        if (!$page_id || !current_user_can('edit_post', $page_id)) {
            wp_send_json_error(__('You do not have permission to edit this page.', 'suite-devtools'), 403);
        }

        if (!$tab_id || !$this->tab_exists($tab_id)) {
            wp_send_json_error(__('Invalid tab.', 'suite-devtools'), 400);
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
        $result = $wpdb->replace(
            $wpdb->prefix . 'page_tab_relations',
            array('page_id' => $page_id, 'tab_id' => $tab_id),
            array('%d', '%d')
        );

        if (false !== $result) {
            wp_send_json_success(__('Page assigned to the tab successfully.', 'suite-devtools'));
        }

        wp_send_json_error(__('Error assigning the page to the tab.', 'suite-devtools'), 500);
    }

    public function ajax_remove_page_from_tab() {
        check_ajax_referer('dtpt_nonce', 'nonce');

        $page_id = isset($_POST['page_id']) ? absint(wp_unslash($_POST['page_id'])) : 0;
        $tab_id  = isset($_POST['tab_id']) ? absint(wp_unslash($_POST['tab_id'])) : 0;

        if (!$page_id || !current_user_can('edit_post', $page_id)) {
            wp_send_json_error(__('You do not have permission to edit this page.', 'suite-devtools'), 403);
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
        $result = $wpdb->delete(
            $wpdb->prefix . 'page_tab_relations',
            array('page_id' => $page_id, 'tab_id' => $tab_id),
            array('%d', '%d')
        );

        if (false !== $result) {
            wp_send_json_success(__('Page removed from the tab successfully.', 'suite-devtools'));
        }

        wp_send_json_error(__('Error removing the page from the tab.', 'suite-devtools'), 500);
    }

    /**
     * Whether a tab id exists. Used to keep relations referentially sound.
     */
    private function tab_exists(int $tab_id): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}page_tabs WHERE id = %d",
            $tab_id
        ));
    }
}

endif;

if (!defined('DEVTOOLS_LIFECYCLE_RUN')) {
    new DevToolsPageTabs();
}
