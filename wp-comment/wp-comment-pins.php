<?php
/**
 * Plugin Name: WP Comment Pins
 * Description: Visual comment pins system for WordPress (React front end).
 * Version: 2.1.0
 * Author: Jorge ML
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WPCommentPins')) :

class WPCommentPins {

    const VERSION             = '2.1.0';
    const DB_VERSION          = '2.0.0';
    const DB_VERSION_OPTION   = 'wpcp_db_version';
    const NONCE_ACTION        = 'comment_pins_nonce';
    const MAX_COMMENT_LENGTH  = 2000;
    const MAX_SELECTOR_LENGTH = 1000;

    /**
     * Fully-qualified table name.
     *
     * @var string
     */
    private $table_name;

    public function __construct() {
        $this->table_name = self::table_name();

        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_save_comment_pin', array($this, 'save_comment_pin'));
        add_action('wp_ajax_get_comment_pins', array($this, 'get_comment_pins'));
        add_action('wp_ajax_delete_comment_pin', array($this, 'delete_comment_pin'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_button'), 100);
    }

    /**
     * Fully-qualified table name. Static so lifecycle methods need no instance.
     */
    private static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'comment_pins';
    }

    /**
     * Capability required to create, read or delete comment pins. Filterable.
     */
    private function get_required_capability(): string {
        return apply_filters('wp_comment_pins_capability', 'edit_posts');
    }

    /* --------------------------------------------------------------------- */
    /* Lifecycle — invoked by the SuiteWP controller                          */
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
        $table = self::table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB
        delete_option(self::DB_VERSION_OPTION);
    }

    /**
     * Create/upgrade the schema only when the stored version is behind.
     *
     * 2.0.0 replaces the old absolute-pixel position model (x_position,
     * y_position) with a DOM-anchored model (anchor_selector + offset_x/y).
     * The previous schema holds no production data, so the table is recreated
     * cleanly on upgrade.
     */
    public static function maybe_install_schema(): void {
        $installed = (string) get_option(self::DB_VERSION_OPTION, '0');
        if (version_compare($installed, self::DB_VERSION, '>=')) {
            return;
        }

        global $wpdb;
        $table = self::table_name();

        // Drop the legacy table when migrating from the pre-anchor schema.
        if ('0' !== $installed && version_compare($installed, '2.0.0', '<')) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_url varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            anchor_selector text NOT NULL,
            offset_x decimal(7,3) NOT NULL DEFAULT 0,
            offset_y decimal(7,3) NOT NULL DEFAULT 0,
            comment_text text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY post_url (post_url),
            KEY user_id (user_id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

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
            'wp-comment-pins',
            $build_url . 'index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style('dashicons');

        if (file_exists($build_path . 'style-index.css')) {
            wp_enqueue_style(
                'wp-comment-pins',
                $build_url . 'style-index.css',
                array('dashicons'),
                $asset['version']
            );
        }

        wp_localize_script('wp-comment-pins', 'wpCommentPins', array(
            'ajax_url'    => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce(self::NONCE_ACTION),
            'current_url' => self::current_url(),
            'i18n'        => array(
                'placeholder'    => __('Write your comment here...', 'suitewp'),
                'cancel'         => __('Cancel', 'suitewp'),
                'save'           => __('Save', 'suitewp'),
                'delete'         => __('Delete', 'suitewp'),
                'close'          => __('Close', 'suitewp'),
                'you'            => __('You', 'suitewp'),
                'user'           => __('User', 'suitewp'),
                'save_error'     => __('Error saving comment', 'suitewp'),
                'delete_error'   => __('Error deleting comment', 'suitewp'),
                'connect_error'  => __('Connection error', 'suitewp'),
                'confirm_delete' => __('Delete this comment?', 'suitewp'),
            ),
        ));
    }

    public function add_admin_bar_button($wp_admin_bar) {
        if (is_admin() || !current_user_can($this->get_required_capability())) {
            return;
        }

        $wp_admin_bar->add_node(array(
            'id'    => 'comment-pins-toggle',
            'title' => '<span class="ab-icon dashicons dashicons-admin-comments"></span>' . esc_html__('Comment', 'suitewp'),
            'href'  => '#',
            'meta'  => array(
                'class' => 'comment-pins-admin-bar-btn',
                'title' => __('Toggle comment mode', 'suitewp'),
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
            wp_send_json_error(array('message' => __('Security check failed.', 'suitewp')), 403);
        }

        if (!current_user_can($this->get_required_capability())) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'suitewp')), 403);
        }
    }

    public function save_comment_pin() {
        $this->verify_request();

        $post_url     = isset($_POST['post_url']) ? esc_url_raw(wp_unslash($_POST['post_url'])) : '';
        $anchor       = isset($_POST['anchor_selector']) ? sanitize_text_field(wp_unslash($_POST['anchor_selector'])) : '';
        $comment_text = isset($_POST['comment_text']) ? sanitize_textarea_field(wp_unslash($_POST['comment_text'])) : '';
        $offset_x     = isset($_POST['offset_x']) ? (float) wp_unslash($_POST['offset_x']) : -1;
        $offset_y     = isset($_POST['offset_y']) ? (float) wp_unslash($_POST['offset_y']) : -1;

        $post_url = $this->normalize_post_url($post_url);

        if ('' === $post_url) {
            wp_send_json_error(array('message' => __('Invalid page URL.', 'suitewp')), 400);
        }
        if (strlen($post_url) > 255) {
            wp_send_json_error(array('message' => __('URL is too long.', 'suitewp')), 400);
        }
        if ('' === $anchor || strlen($anchor) > self::MAX_SELECTOR_LENGTH) {
            wp_send_json_error(array('message' => __('Invalid anchor.', 'suitewp')), 400);
        }
        if ('' === $comment_text) {
            wp_send_json_error(array('message' => __('Comment cannot be empty.', 'suitewp')), 400);
        }
        if (mb_strlen($comment_text) > self::MAX_COMMENT_LENGTH) {
            wp_send_json_error(array('message' => __('Comment is too long.', 'suitewp')), 400);
        }
        if ($offset_x < 0 || $offset_x > 100 || $offset_y < 0 || $offset_y > 100) {
            wp_send_json_error(array('message' => __('Invalid pin position.', 'suitewp')), 400);
        }

        global $wpdb;
        $created_at = current_time('mysql');

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'post_url'        => $post_url,
                'user_id'         => get_current_user_id(),
                'anchor_selector' => $anchor,
                'offset_x'        => $offset_x,
                'offset_y'        => $offset_y,
                'comment_text'    => $comment_text,
                'created_at'      => $created_at,
            ),
            array('%s', '%d', '%s', '%f', '%f', '%s', '%s')
        );

        if (false === $result) {
            wp_send_json_error(array('message' => __('Error saving comment.', 'suitewp')), 500);
        }

        wp_send_json_success(array(
            'id'         => (int) $wpdb->insert_id,
            'created_at' => $created_at,
            'message'    => __('Comment saved successfully.', 'suitewp'),
        ));
    }

    public function get_comment_pins() {
        $this->verify_request();

        $post_url = isset($_POST['post_url']) ? esc_url_raw(wp_unslash($_POST['post_url'])) : '';
        $post_url = $this->normalize_post_url($post_url);

        if ('' === $post_url) {
            wp_send_json_success(array());
        }

        global $wpdb;
        $current_user    = get_current_user_id();
        $can_edit_others = current_user_can('edit_others_posts');

        $pins = $wpdb->get_results($wpdb->prepare(
            "SELECT cp.id, cp.anchor_selector, cp.offset_x, cp.offset_y, cp.comment_text, cp.created_at, cp.user_id, u.display_name
             FROM {$this->table_name} cp
             LEFT JOIN {$wpdb->users} u ON cp.user_id = u.ID
             WHERE cp.post_url = %s
             ORDER BY cp.created_at ASC",
            $post_url
        ));

        $out = array();
        foreach ((array) $pins as $pin) {
            $out[] = array(
                'id'              => (int) $pin->id,
                'anchor_selector' => $pin->anchor_selector,
                'offset_x'        => (float) $pin->offset_x,
                'offset_y'        => (float) $pin->offset_y,
                'comment_text'    => $pin->comment_text,
                'created_at'      => $pin->created_at,
                'display_name'    => $pin->display_name ? $pin->display_name : __('User', 'suitewp'),
                // Boolean only — never expose the raw author id to the client.
                'can_delete'      => ((int) $pin->user_id === $current_user) || $can_edit_others,
            );
        }

        wp_send_json_success($out);
    }

    public function delete_comment_pin() {
        $this->verify_request();

        $pin_id = isset($_POST['pin_id']) ? absint(wp_unslash($_POST['pin_id'])) : 0;
        if (!$pin_id) {
            wp_send_json_error(array('message' => __('Invalid pin.', 'suitewp')), 400);
        }

        global $wpdb;
        $owner = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$this->table_name} WHERE id = %d",
            $pin_id
        ));

        if (null === $owner) {
            wp_send_json_error(array('message' => __('Pin not found.', 'suitewp')), 404);
        }

        // Owner can delete their own pins; deleting others' requires elevated capability.
        if ((int) $owner !== get_current_user_id() && !current_user_can('edit_others_posts')) {
            wp_send_json_error(array('message' => __('You cannot delete this pin.', 'suitewp')), 403);
        }

        $deleted = $wpdb->delete($this->table_name, array('id' => $pin_id), array('%d'));
        if (false === $deleted) {
            wp_send_json_error(array('message' => __('Error deleting comment.', 'suitewp')), 500);
        }

        wp_send_json_success(array('id' => $pin_id));
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
        $path = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
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
if (!defined('SUITEWP_LIFECYCLE_RUN')) {
    new WPCommentPins();
}
