<?php
/**
 * Modulforge — Comment Pins module.
 *
 * Bundled component loaded by the Modulforge controller; not a standalone plugin.
 * Visual comment pins system for WordPress (React front end).
 *
 * @package Modulforge
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DevToolsCommentPins')) :

class DevToolsCommentPins {

    const VERSION             = '2.2.0';
    const DB_VERSION          = '2.1.0';
    const DB_VERSION_OPTION   = 'dtcp_db_version';
    const NONCE_ACTION        = 'comment_pins_nonce';
    const MAX_COMMENT_LENGTH  = 2000;
    const MAX_SELECTOR_LENGTH = 1000;

    /**
     * Fully-qualified table names.
     *
     * @var string
     */
    private $table_name;
    private $replies_table;

    public function __construct() {
        $this->table_name    = self::table_name();
        $this->replies_table = self::replies_table_name();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_save_comment_pin', array($this, 'save_comment_pin'));
        add_action('wp_ajax_get_comment_pins', array($this, 'get_comment_pins'));
        add_action('wp_ajax_delete_comment_pin', array($this, 'delete_comment_pin'));
        add_action('wp_ajax_resolve_comment_pin', array($this, 'resolve_comment_pin'));
        add_action('wp_ajax_get_comment_replies', array($this, 'get_comment_replies'));
        add_action('wp_ajax_add_comment_reply', array($this, 'add_comment_reply'));
        add_action('wp_ajax_delete_comment_reply', array($this, 'delete_comment_reply'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_button'), 100);
    }

    /** Pins table. Static so lifecycle methods need no instance. */
    private static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'comment_pins';
    }

    /** Replies table. */
    private static function replies_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'comment_pin_replies';
    }

    /**
     * Capability required to create, read, resolve or reply to comment pins.
     * Filterable. Deleting others' content additionally requires edit_others_posts.
     */
    private function get_required_capability(): string {
        return apply_filters('dev_tools_comment_pins_capability', 'edit_posts');
    }

    /* --------------------------------------------------------------------- */
    /* Lifecycle — invoked by the DevTools controller                          */
    /* --------------------------------------------------------------------- */

    public static function activate(): void {
        self::maybe_install_schema();
    }

    /**
     * Remove all data on uninstall. Destructive — only called from
     * uninstall.php when the user opted in.
     */
    public static function uninstall(): void {
        global $wpdb;
        $pins    = self::table_name();
        $replies = self::replies_table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$replies}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; no user input.
        $wpdb->query("DROP TABLE IF EXISTS {$pins}");    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; no user input.
        delete_option(self::DB_VERSION_OPTION);
    }

    /**
     * Create/upgrade the schema only when the stored version is behind.
     *
     * 2.0.0 introduced the DOM-anchored model. 2.1.0 adds resolve state
     * (status/resolved_at/resolved_by) to pins and a replies table. The upgrade
     * is additive (dbDelta adds the columns and the new table); the legacy drop
     * only applies to pre-2.0.0 installs.
     */
    public static function maybe_install_schema(): void {
        $installed = (string) get_option(self::DB_VERSION_OPTION, '0');
        if (version_compare($installed, self::DB_VERSION, '>=')) {
            return;
        }

        global $wpdb;
        $table   = self::table_name();
        $replies = self::replies_table_name();

        // Drop the legacy table only when migrating from the pre-anchor schema.
        if ('0' !== $installed && version_compare($installed, '2.0.0', '<')) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; no user input.
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql_pins = "CREATE TABLE {$table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_url varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            anchor_selector text NOT NULL,
            offset_x decimal(7,3) NOT NULL DEFAULT 0,
            offset_y decimal(7,3) NOT NULL DEFAULT 0,
            comment_text text NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            resolved_at datetime NULL,
            resolved_by bigint(20) NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_url (post_url),
            KEY user_id (user_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql_replies = "CREATE TABLE {$replies} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            pin_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            comment_text text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY pin_id (pin_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_pins);
        dbDelta($sql_replies);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /* --------------------------------------------------------------------- */
    /* Assets & admin bar                                                     */
    /* --------------------------------------------------------------------- */

    public function enqueue_scripts() {
        if (!current_user_can($this->get_required_capability())) {
            return;
        }

        $build_path = plugin_dir_path(__FILE__) . 'build/';
        $build_url  = plugin_dir_url(__FILE__) . 'build/';
        $asset_file = $build_path . 'index.asset.php';

        // The React bundle must be built (npm run build). Bail quietly if not.
        if (!file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

        wp_enqueue_script(
            'dev-tools-comment-pins',
            $build_url . 'index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style('dashicons');

        if (file_exists($build_path . 'style-index.css')) {
            wp_enqueue_style(
                'dev-tools-comment-pins',
                $build_url . 'style-index.css',
                array('dashicons'),
                $asset['version']
            );
        }

        wp_localize_script('dev-tools-comment-pins', 'devToolsCommentPins', array(
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce(self::NONCE_ACTION),
            'current_url' => self::current_url(),
            'i18n'        => array(
                'placeholder'          => __('Write your comment here...', 'modulforge'),
                'cancel'               => __('Cancel', 'modulforge'),
                'save'                 => __('Save', 'modulforge'),
                'delete'               => __('Delete', 'modulforge'),
                'close'                => __('Close', 'modulforge'),
                'you'                  => __('You', 'modulforge'),
                'user'                 => __('User', 'modulforge'),
                'save_error'           => __('Error saving comment', 'modulforge'),
                'delete_error'         => __('Error deleting comment', 'modulforge'),
                'connect_error'        => __('Connection error', 'modulforge'),
                'confirm_delete'       => __('Delete this comment?', 'modulforge'),
                'confirm_delete_reply' => __('Delete this reply?', 'modulforge'),
                'reply'                => __('Reply', 'modulforge'),
                'reply_placeholder'    => __('Write a reply...', 'modulforge'),
                'resolve'              => __('Resolve', 'modulforge'),
                'reopen'               => __('Reopen', 'modulforge'),
                'resolved'             => __('Resolved', 'modulforge'),
                'open'                 => __('Open', 'modulforge'),
                'comments'             => __('Comments', 'modulforge'),
                'filter_all'           => __('All', 'modulforge'),
                'filter_open'          => __('Open', 'modulforge'),
                'filter_resolved'      => __('Resolved', 'modulforge'),
                'filter_mine'          => __('Mine', 'modulforge'),
                'show_resolved'        => __('Show resolved on page', 'modulforge'),
                'no_comments'          => __('No comments on this page yet.', 'modulforge'),
                'no_match'             => __('Nothing matches this filter.', 'modulforge'),
                'loading'              => __('Loading…', 'modulforge'),
                'panel_open'           => __('Open comments panel', 'modulforge'),
                'panel_close'          => __('Close panel', 'modulforge'),
                /* translators: %s: user name. */
                'resolved_by'          => __('Resolved by %s', 'modulforge'),
                'one_reply'            => __('1 reply', 'modulforge'),
                /* translators: %d: number of replies. */
                'many_replies'         => __('%d replies', 'modulforge'),
            ),
        ));
    }

    public function add_admin_bar_button($wp_admin_bar) {
        if (is_admin() || !current_user_can($this->get_required_capability())) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id'    => 'comment-pins-toggle',
            'title' => '<span class="ab-icon dashicons dashicons-admin-comments"></span>' . esc_html__('Comment', 'modulforge'),
            'href'  => '#',
            'meta'  => array(
                'class' => 'comment-pins-admin-bar-btn',
                'title' => __('Toggle comment mode', 'modulforge'),
            ),
        ));
    }

    /* --------------------------------------------------------------------- */
    /* AJAX                                                                    */
    /* --------------------------------------------------------------------- */

    /**
     * Shared gate for every AJAX endpoint: valid nonce + capability.
     * Always answers with JSON and an HTTP status; never wp_die()s raw text.
     */
    private function verify_request(): void {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'modulforge')), 403);
        }

        if (!current_user_can($this->get_required_capability())) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'modulforge')), 403);
        }
    }

    public function save_comment_pin() {
        $this->verify_request();

        $post_url     = isset($_POST['post_url']) ? esc_url_raw(wp_unslash($_POST['post_url'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
        $anchor       = isset($_POST['anchor_selector']) ? sanitize_text_field(wp_unslash($_POST['anchor_selector'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
        $comment_text = isset($_POST['comment_text']) ? sanitize_textarea_field(wp_unslash($_POST['comment_text'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
        $offset_x     = isset($_POST['offset_x']) ? (float) wp_unslash($_POST['offset_x']) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify_request(); cast to number.
        $offset_y     = isset($_POST['offset_y']) ? (float) wp_unslash($_POST['offset_y']) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify_request(); cast to number.

        $post_url = $this->normalize_post_url($post_url);

        if ('' === $post_url) {
            wp_send_json_error(array('message' => __('Invalid page URL.', 'modulforge')), 400);
        }
        if (strlen($post_url) > 255) {
            wp_send_json_error(array('message' => __('URL is too long.', 'modulforge')), 400);
        }
        if ('' === $anchor || strlen($anchor) > self::MAX_SELECTOR_LENGTH) {
            wp_send_json_error(array('message' => __('Invalid anchor.', 'modulforge')), 400);
        }
        if ('' === $comment_text) {
            wp_send_json_error(array('message' => __('Comment cannot be empty.', 'modulforge')), 400);
        }
        if (mb_strlen($comment_text) > self::MAX_COMMENT_LENGTH) {
            wp_send_json_error(array('message' => __('Comment is too long.', 'modulforge')), 400);
        }
        if ($offset_x < 0 || $offset_x > 100 || $offset_y < 0 || $offset_y > 100) {
            wp_send_json_error(array('message' => __('Invalid pin position.', 'modulforge')), 400);
        }

        global $wpdb;
        $created_at = current_time('mysql');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'post_url'        => $post_url,
                'user_id'         => get_current_user_id(),
                'anchor_selector' => $anchor,
                'offset_x'        => $offset_x,
                'offset_y'        => $offset_y,
                'comment_text'    => $comment_text,
                'status'          => 'open',
                'created_at'      => $created_at,
            ),
            array('%s', '%d', '%s', '%f', '%f', '%s', '%s', '%s')
        );

        if (false === $result) {
            wp_send_json_error(array('message' => __('Error saving comment.', 'modulforge')), 500);
        }

        wp_send_json_success(array(
            'id'         => (int) $wpdb->insert_id,
            'created_at' => $created_at,
            'message'    => __('Comment saved successfully.', 'modulforge'),
        ));
    }

    public function get_comment_pins() {
        $this->verify_request();

        $post_url = isset($_POST['post_url']) ? esc_url_raw(wp_unslash($_POST['post_url'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
        $post_url = $this->normalize_post_url($post_url);

        if ('' === $post_url) {
            wp_send_json_success(array());
        }

        global $wpdb;
        $current_user    = get_current_user_id();
        $can_edit_others = current_user_can('edit_others_posts');

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $pins = $wpdb->get_results($wpdb->prepare(
            "SELECT cp.id, cp.anchor_selector, cp.offset_x, cp.offset_y, cp.comment_text, cp.created_at,
                    cp.user_id, cp.status, cp.resolved_at, u.display_name, ru.display_name AS resolved_by_name
             FROM {$this->table_name} cp
             LEFT JOIN {$wpdb->users} u ON cp.user_id = u.ID
             LEFT JOIN {$wpdb->users} ru ON cp.resolved_by = ru.ID
             WHERE cp.post_url = %s
             ORDER BY cp.created_at ASC",
            $post_url
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        // Reply counts in a single aggregate query (no N+1).
        $counts = array();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $count_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.pin_id AS pin_id, COUNT(*) AS c
             FROM {$this->replies_table} r
             JOIN {$this->table_name} cp ON r.pin_id = cp.id
             WHERE cp.post_url = %s
             GROUP BY r.pin_id",
            $post_url
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        foreach ((array) $count_rows as $row) {
            $counts[(int) $row->pin_id] = (int) $row->c;
        }

        $out = array();
        foreach ((array) $pins as $pin) {
            $id = (int) $pin->id;
            $out[] = array(
                'id'               => $id,
                'anchor_selector'  => $pin->anchor_selector,
                'offset_x'         => (float) $pin->offset_x,
                'offset_y'         => (float) $pin->offset_y,
                'comment_text'     => $pin->comment_text,
                'created_at'       => $pin->created_at,
                'display_name'     => $pin->display_name ? $pin->display_name : __('User', 'modulforge'),
                'status'           => $pin->status ? $pin->status : 'open',
                'resolved_at'      => $pin->resolved_at,
                'resolved_by_name' => $pin->resolved_by_name,
                'reply_count'      => isset($counts[$id]) ? $counts[$id] : 0,
                'is_mine'          => ((int) $pin->user_id === $current_user),
                // Boolean only — never expose the raw author id to the client.
                'can_delete'       => ((int) $pin->user_id === $current_user) || $can_edit_others,
            );
        }

        wp_send_json_success($out);
    }

    public function delete_comment_pin() {
        $this->verify_request();

        $pin_id = isset($_POST['pin_id']) ? absint(wp_unslash($_POST['pin_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
        if (!$pin_id) {
            wp_send_json_error(array('message' => __('Invalid pin.', 'modulforge')), 400);
        }

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->table_name} WHERE id = %d",
            $pin_id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (null === $owner) {
            wp_send_json_error(array('message' => __('Pin not found.', 'modulforge')), 404);
        }

        // Owner can delete their own pins; deleting others' requires elevated capability.
        if ((int) $owner !== get_current_user_id() && !current_user_can('edit_others_posts')) {
            wp_send_json_error(array('message' => __('You cannot delete this pin.', 'modulforge')), 403);
        }

        $deleted = $wpdb->delete($this->table_name, array('id' => $pin_id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
        if (false === $deleted) {
            wp_send_json_error(array('message' => __('Error deleting comment.', 'modulforge')), 500);
        }

        // Cascade: remove the pin's replies.
        $wpdb->delete($this->replies_table, array('pin_id' => $pin_id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.

        wp_send_json_success(array('id' => $pin_id));
    }

    /**
     * Resolve or reopen a pin. Collaborative: any user with the capability may
     * do it; we record who and when.
     */
    public function resolve_comment_pin() {
        $this->verify_request();

        $pin_id   = isset($_POST['pin_id']) ? absint(wp_unslash($_POST['pin_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
        $resolved = isset($_POST['resolved']) ? wp_validate_boolean(wp_unslash($_POST['resolved'])) : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify_request(); validated with wp_validate_boolean().
        if (!$pin_id) {
            wp_send_json_error(array('message' => __('Invalid pin.', 'modulforge')), 400);
        }

        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE id = %d", $pin_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        if (null === $exists) {
            wp_send_json_error(array('message' => __('Pin not found.', 'modulforge')), 404);
        }

        $current = get_current_user_id();
        $name    = '';

        if ($resolved) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name} SET status = 'resolved', resolved_at = %s, resolved_by = %d WHERE id = %d",
                current_time('mysql'),
                $current,
                $pin_id
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM {$wpdb->users} WHERE ID = %d", $current)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query; not cacheable.
        } else {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_name} SET status = 'open', resolved_at = NULL, resolved_by = NULL WHERE id = %d",
                $pin_id
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }

        if (false === $result) {
            wp_send_json_error(array('message' => __('Error updating comment.', 'modulforge')), 500);
        }

        wp_send_json_success(array(
            'id'               => $pin_id,
            'status'           => $resolved ? 'resolved' : 'open',
            'resolved_by_name' => $name ? $name : null,
        ));
    }

    public function get_comment_replies() {
        $this->verify_request();

        $pin_id = isset($_POST['pin_id']) ? absint(wp_unslash($_POST['pin_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
        if (!$pin_id) {
            wp_send_json_error(array('message' => __('Invalid pin.', 'modulforge')), 400);
        }

        global $wpdb;
        $current_user    = get_current_user_id();
        $can_edit_others = current_user_can('edit_others_posts');

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $replies = $wpdb->get_results($wpdb->prepare(
            "SELECT r.id, r.comment_text, r.created_at, r.user_id, u.display_name
             FROM {$this->replies_table} r
             LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
             WHERE r.pin_id = %d
             ORDER BY r.created_at ASC",
            $pin_id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $out = array();
        foreach ((array) $replies as $reply) {
            $out[] = array(
                'id'           => (int) $reply->id,
                'comment_text' => $reply->comment_text,
                'created_at'   => $reply->created_at,
                'display_name' => $reply->display_name ? $reply->display_name : __('User', 'modulforge'),
                'can_delete'   => ((int) $reply->user_id === $current_user) || $can_edit_others,
            );
        }

        wp_send_json_success($out);
    }

    public function add_comment_reply() {
        $this->verify_request();

        $pin_id = isset($_POST['pin_id']) ? absint(wp_unslash($_POST['pin_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
        $text   = isset($_POST['comment_text']) ? sanitize_textarea_field(wp_unslash($_POST['comment_text'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().

        if (!$pin_id) {
            wp_send_json_error(array('message' => __('Invalid pin.', 'modulforge')), 400);
        }
        if ('' === $text) {
            wp_send_json_error(array('message' => __('Comment cannot be empty.', 'modulforge')), 400);
        }
        if (mb_strlen($text) > self::MAX_COMMENT_LENGTH) {
            wp_send_json_error(array('message' => __('Comment is too long.', 'modulforge')), 400);
        }

        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE id = %d", $pin_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        if (null === $exists) {
            wp_send_json_error(array('message' => __('Pin not found.', 'modulforge')), 404);
        }

        $current    = get_current_user_id();
        $created_at = current_time('mysql');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
        $result = $wpdb->insert(
            $this->replies_table,
            array(
                'pin_id'       => $pin_id,
                'user_id'      => $current,
                'comment_text' => $text,
                'created_at'   => $created_at,
            ),
            array('%d', '%d', '%s', '%s')
        );

        if (false === $result) {
            wp_send_json_error(array('message' => __('Error saving comment.', 'modulforge')), 500);
        }

        $name = $wpdb->get_var($wpdb->prepare("SELECT display_name FROM {$wpdb->users} WHERE ID = %d", $current)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query; not cacheable.

        wp_send_json_success(array(
            'id'           => (int) $wpdb->insert_id,
            'pin_id'       => $pin_id,
            'comment_text' => $text,
            'created_at'   => $created_at,
            'display_name' => $name ? $name : __('User', 'modulforge'),
            'can_delete'   => true,
        ));
    }

    public function delete_comment_reply() {
        $this->verify_request();

        $reply_id = isset($_POST['reply_id']) ? absint(wp_unslash($_POST['reply_id'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_request().
        if (!$reply_id) {
            wp_send_json_error(array('message' => __('Invalid reply.', 'modulforge')), 400);
        }

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name derived from $wpdb->prefix; values are passed through $wpdb->prepare().
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->replies_table} WHERE id = %d",
            $reply_id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (null === $owner) {
            wp_send_json_error(array('message' => __('Reply not found.', 'modulforge')), 404);
        }

        if ((int) $owner !== get_current_user_id() && !current_user_can('edit_others_posts')) {
            wp_send_json_error(array('message' => __('You cannot delete this reply.', 'modulforge')), 403);
        }

        $deleted = $wpdb->delete($this->replies_table, array('id' => $reply_id), array('%d')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query on the plugin's own custom table; not cacheable.
        if (false === $deleted) {
            wp_send_json_error(array('message' => __('Error deleting comment.', 'modulforge')), 500);
        }

        wp_send_json_success(array('id' => $reply_id));
    }

    /* --------------------------------------------------------------------- */
    /* Helpers                                                                */
    /* --------------------------------------------------------------------- */

    /**
     * A stable, always-present URL for the current front-end view.
     * get_permalink() returns false on non-singular views, so resolve from
     * the request URI under home_url() instead.
     */
    private static function current_url(): string {
        $path = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        return self::normalize_post_url_static(home_url($path));
    }

    private function normalize_post_url(string $url): string {
        return self::normalize_post_url_static($url);
    }

    /**
     * Constrain a URL to this site and strip the volatile query string and
     * fragment so pins group by a stable key. Returns '' if off-site or empty.
     */
    private static function normalize_post_url_static(string $url): string {
        if ('' === $url) {
            return '';
        }

        // Reject anything that is not under this site's home URL.
        if (strpos($url, home_url()) !== 0) {
            return '';
        }

        // Drop the fragment, then the query string.
        $hash = strpos($url, '#');
        if (false !== $hash) {
            $url = substr($url, 0, $hash);
        }
        $query = strpos($url, '?');
        if (false !== $query) {
            $url = substr($url, 0, $query);
        }

        return esc_url_raw(untrailingslashit($url));
    }
}

endif;

// Instantiate for normal runtime. The guard lets uninstall.php include this
// file purely to reach the static uninstall() method without booting hooks.
if (!defined('DEVTOOLS_LIFECYCLE_RUN')) {
    new DevToolsCommentPins();
}
