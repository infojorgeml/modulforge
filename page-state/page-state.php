<?php
/**
 * Suite DevTools — Page State Management module.
 *
 * Bundled component loaded by the Suite DevTools controller; not a standalone plugin.
 * Page management with status tracking, notes, and responsive design
 * checkboxes for the WordPress pages list.
 *
 * @package SuiteDevTools
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DevToolsPageState')) :

/**
 * Page state management: status, notes and responsive checkboxes on pages.
 */
class DevToolsPageState {

    const VERSION = '2.0.0';

    public function __construct() {
        add_action('init', [$this, 'register_meta_fields']);

        // Admin columns.
        add_filter('manage_pages_columns', [$this, 'add_custom_columns']);
        add_action('manage_pages_custom_column', [$this, 'render_custom_columns'], 10, 2);

        // AJAX handlers for live saving.
        add_action('wp_ajax_save_page_status', [$this, 'ajax_save_page_status']);
        add_action('wp_ajax_save_page_notes', [$this, 'ajax_save_page_notes']);
        add_action('wp_ajax_save_page_responsive', [$this, 'ajax_save_page_responsive']);

        // Admin assets.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    /**
     * Remove all page-state meta on uninstall. Destructive — only called from
     * uninstall.php when the user opted in.
     */
    public static function uninstall(): void {
        $keys = ['page_status', 'page_notes', 'responsive_desktop', 'responsive_tablet', 'responsive_mobile'];
        foreach ($keys as $key) {
            delete_post_meta_by_key($key);
        }
    }

    /**
     * Register custom meta fields for page management.
     */
    public function register_meta_fields() {
        register_post_meta('page', 'page_status', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => [$this, 'sanitize_status'],
            'auth_callback'     => function () { return current_user_can('edit_posts'); },
        ]);

        register_post_meta('page', 'page_notes', [
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'auth_callback'     => function () { return current_user_can('edit_posts'); },
        ]);

        foreach (['responsive_desktop', 'responsive_tablet', 'responsive_mobile'] as $meta_key) {
            register_post_meta('page', $meta_key, [
                'type'              => 'boolean',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => 'rest_sanitize_boolean',
                'auth_callback'     => function () { return current_user_can('edit_posts'); },
            ]);
        }
    }

    /**
     * Sanitize and validate status field values.
     */
    public function sanitize_status($value) {
        $allowed_statuses = ['draft', 'revision', 'process', 'done'];
        $value            = sanitize_text_field($value);
        return in_array($value, $allowed_statuses, true) ? $value : '';
    }

    /**
     * Add custom columns to the pages list table after the title column.
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['page_status']     = __('Status', 'suite-devtools');
                $new_columns['page_notes']      = __('Notes', 'suite-devtools');
                $new_columns['page_responsive'] = __('Responsive', 'suite-devtools');
            }
        }
        return $new_columns;
    }

    public function render_custom_columns($column, $post_id) {
        if ($column === 'page_status') {
            $this->render_status_column($post_id);
        } elseif ($column === 'page_notes') {
            $this->render_notes_column($post_id);
        } elseif ($column === 'page_responsive') {
            $this->render_responsive_column($post_id);
        }
    }

    private function render_status_column($post_id) {
        $status = get_post_meta($post_id, 'page_status', true);
        ?>
        <select class="page-status-selector" data-post="<?php echo esc_attr($post_id); ?>">
            <option value="">— <?php esc_html_e('None', 'suite-devtools'); ?> —</option>
            <option value="draft" <?php selected($status, 'draft'); ?>>⚪ <?php esc_html_e('Draft', 'suite-devtools'); ?></option>
            <option value="revision" <?php selected($status, 'revision'); ?>>🟡 <?php esc_html_e('Revision', 'suite-devtools'); ?></option>
            <option value="process" <?php selected($status, 'process'); ?>>🔵 <?php esc_html_e('In Progress', 'suite-devtools'); ?></option>
            <option value="done" <?php selected($status, 'done'); ?>>🟢 <?php esc_html_e('Done', 'suite-devtools'); ?></option>
        </select>
        <div class="page-status-loading" style="display: none;">
            <span class="spinner" style="visibility: visible; float: none;"></span>
        </div>
        <?php
    }

    private function render_notes_column($post_id) {
        $notes = get_post_meta($post_id, 'page_notes', true);
        ?>
        <div class="page-notes-container">
            <textarea
                class="page-notes-textarea auto-resize"
                data-post="<?php echo esc_attr($post_id); ?>"
                rows="2"
                style="width: 100%; min-height: 60px; resize: vertical;"
                placeholder="<?php esc_attr_e('Add notes...', 'suite-devtools'); ?>"
            ><?php echo esc_textarea($notes); ?></textarea>
            <div class="page-notes-status" style="font-size: 11px; color: #666; margin-top: 3px;"></div>
        </div>
        <?php
    }

    private function render_responsive_column($post_id) {
        $desktop = get_post_meta($post_id, 'responsive_desktop', true);
        $tablet  = get_post_meta($post_id, 'responsive_tablet', true);
        $mobile  = get_post_meta($post_id, 'responsive_mobile', true);
        ?>
        <div class="page-responsive-container">
            <div class="responsive-checkboxes" data-post="<?php echo esc_attr($post_id); ?>">
                <label class="responsive-checkbox-label">
                    <input type="checkbox" class="responsive-checkbox" data-device="desktop" <?php checked($desktop, true); ?>>
                    <span class="device-icon">🖥️</span>
                    <span class="device-label"><?php esc_html_e('Desktop', 'suite-devtools'); ?></span>
                </label>
                <label class="responsive-checkbox-label">
                    <input type="checkbox" class="responsive-checkbox" data-device="tablet" <?php checked($tablet, true); ?>>
                    <span class="device-icon">📱</span>
                    <span class="device-label"><?php esc_html_e('Tablet', 'suite-devtools'); ?></span>
                </label>
                <label class="responsive-checkbox-label">
                    <input type="checkbox" class="responsive-checkbox" data-device="mobile" <?php checked($mobile, true); ?>>
                    <span class="device-icon">📲</span>
                    <span class="device-label"><?php esc_html_e('Mobile', 'suite-devtools'); ?></span>
                </label>
            </div>
            <div class="responsive-status" style="font-size: 11px; color: #666; margin-top: 3px;"></div>
        </div>
        <?php
    }

    /**
     * Load admin scripts and styles on the pages list screen only.
     */
    public function enqueue_admin_scripts($hook) {
        $post_type = isset($_GET['post_type']) ? sanitize_key(wp_unslash($_GET['post_type'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check to scope asset loading; no data is processed.
        if ($hook !== 'edit.php' || $post_type !== 'page') {
            return;
        }

        wp_enqueue_script(
            'page-state-plugin',
            plugin_dir_url(__FILE__) . 'page-state-plugin.js',
            ['jquery'],
            self::VERSION,
            true
        );

        wp_localize_script('page-state-plugin', 'devToolsPageState', [
            'ajaxurl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('dtps_nonce'),
            'messages' => [
                'saved'  => __('Saved', 'suite-devtools'),
                'saving' => __('Saving...', 'suite-devtools'),
                'error'  => __('Error saving', 'suite-devtools'),
            ],
        ]);

        wp_enqueue_style(
            'page-state-plugin-admin',
            plugin_dir_url(__FILE__) . 'page-state.css',
            [],
            self::VERSION
        );
    }

    public function ajax_save_page_status() {
        check_ajax_referer('dtps_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $status  = isset($_POST['status']) ? $this->sanitize_status(wp_unslash($_POST['status'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_status() whitelists the value.

        if (!$post_id || get_post_type($post_id) !== 'page') {
            wp_send_json_error(['message' => __('Invalid page ID', 'suite-devtools')]);
        }

        if (!current_user_can('edit_page', $post_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'suite-devtools')]);
        }

        update_post_meta($post_id, 'page_status', $status);
        wp_send_json_success(['message' => __('Status saved successfully', 'suite-devtools')]);
    }

    public function ajax_save_page_notes() {
        check_ajax_referer('dtps_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $notes   = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';

        if (!$post_id || get_post_type($post_id) !== 'page') {
            wp_send_json_error(['message' => __('Invalid page ID', 'suite-devtools')]);
        }

        if (!current_user_can('edit_page', $post_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'suite-devtools')]);
        }

        update_post_meta($post_id, 'page_notes', $notes);
        wp_send_json_success(['message' => __('Notes saved successfully', 'suite-devtools')]);
    }

    public function ajax_save_page_responsive() {
        check_ajax_referer('dtps_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $device  = isset($_POST['device']) ? sanitize_text_field(wp_unslash($_POST['device'])) : '';
        $checked = isset($_POST['checked']) ? rest_sanitize_boolean(wp_unslash($_POST['checked'])) : false;

        if (!$post_id || get_post_type($post_id) !== 'page') {
            wp_send_json_error(['message' => __('Invalid page ID', 'suite-devtools')]);
        }

        if (!current_user_can('edit_page', $post_id)) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'suite-devtools')]);
        }

        $allowed_devices = ['desktop', 'tablet', 'mobile'];
        if (!in_array($device, $allowed_devices, true)) {
            wp_send_json_error(['message' => __('Invalid device type', 'suite-devtools')]);
        }

        update_post_meta($post_id, 'responsive_' . $device, $checked);
        wp_send_json_success(['message' => __('Responsive status saved successfully', 'suite-devtools')]);
    }
}

endif;

if (!defined('DEVTOOLS_LIFECYCLE_RUN')) {
    new DevToolsPageState();
}
