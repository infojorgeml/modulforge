<?php
/**
 * Plugin Name: Suite DevTools Convert to WebP
 * Description: Convert JPEG/PNG media to WebP — bulk-convert the existing library and auto-convert new uploads. Replaces and removes the originals.
 * Version: 1.0.0
 * Author: Jorge ML
 * License: GPL2
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package DevToolsWebP
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DevToolsWebP')) :

/**
 * Convert JPEG/PNG attachments to WebP, replacing the originals.
 */
class DevToolsWebP {

    const VERSION      = '1.0.0';
    const OPTION_KEY   = 'dev_tools_webp_settings';
    const NONCE_ACTION = 'dev_tools_webp';
    const MENU_SLUG    = 'devtools-webp';
    const CAPABILITY   = 'manage_options';

    /** Guard against re-entry when we regenerate metadata mid-conversion. */
    private static $is_converting = false;

    /** Hook suffix of our admin page (set when the menu is registered). */
    private $page_hook = '';

    public function __construct() {
        // Priority 11 so the DevTools top-level menu (priority 10) exists first.
        add_action('admin_menu', array($this, 'add_admin_menu'), 11);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_dev_tools_webp_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_dev_tools_webp_scan', array($this, 'ajax_scan'));
        add_action('wp_ajax_dev_tools_webp_convert', array($this, 'ajax_convert'));

        // Auto-convert new uploads once their metadata (and sub-sizes) exist.
        add_filter('wp_generate_attachment_metadata', array($this, 'maybe_convert_on_upload'), 99, 2);
    }

    /* --------------------------------------------------------------------- */
    /* Settings                                                               */
    /* --------------------------------------------------------------------- */

    private static function default_settings(): array {
        return array(
            'auto_upload' => true,
            'quality'     => 82,
        );
    }

    private static function get_settings(): array {
        $saved = get_option(self::OPTION_KEY, array());
        $s     = wp_parse_args(is_array($saved) ? $saved : array(), self::default_settings());

        $s['auto_upload'] = (bool) $s['auto_upload'];
        $s['quality']     = max(1, min(100, (int) $s['quality']));

        return $s;
    }

    /** MIME types we know how to convert. */
    private static function convertible_mimes(): array {
        return array('image/jpeg', 'image/png');
    }

    private static function server_supports_webp(): bool {
        return (bool) wp_image_editor_supports(array('mime_type' => 'image/webp'));
    }

    /* --------------------------------------------------------------------- */
    /* Lifecycle — invoked by the DevTools controller                         */
    /* --------------------------------------------------------------------- */

    public static function activate(): void {
        if (false === get_option(self::OPTION_KEY, false)) {
            add_option(self::OPTION_KEY, self::default_settings());
        }
    }

    public static function deactivate(): void {
        // Nothing to revert: files that were already converted stay converted.
    }

    public static function uninstall(): void {
        delete_option(self::OPTION_KEY);
    }

    /* --------------------------------------------------------------------- */
    /* Admin page & assets                                                    */
    /* --------------------------------------------------------------------- */

    public function add_admin_menu() {
        $this->page_hook = add_submenu_page(
            'suite-devtools',
            __('Convert to WebP', 'suite-devtools'),
            __('Convert to WebP', 'suite-devtools'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'suite-devtools'));
        }
        $settings  = self::get_settings();
        $supported = self::server_supports_webp();
        include __DIR__ . '/includes/admin-page.php';
    }

    public function enqueue_assets($hook) {
        if ('' === $this->page_hook || $hook !== $this->page_hook) {
            return;
        }

        wp_enqueue_script(
            'devtools-webp',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            array(),
            self::VERSION,
            true
        );
        wp_enqueue_style(
            'devtools-webp',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            self::VERSION
        );

        wp_localize_script('devtools-webp', 'devToolsWebP', array(
            'ajax_url'  => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce(self::NONCE_ACTION),
            'supported' => self::server_supports_webp(),
            'i18n'      => array(
                'saved'         => __('Settings saved.', 'suite-devtools'),
                'save_error'    => __('Could not save settings.', 'suite-devtools'),
                'connect_error' => __('Connection error. Please try again.', 'suite-devtools'),
                'scanning'      => __('Scanning the media library…', 'suite-devtools'),
                'none_pending'  => __('No JPEG or PNG images left to convert.', 'suite-devtools'),
                'converting'    => __('Converting…', 'suite-devtools'),
                'done'          => __('Finished.', 'suite-devtools'),
                'skipped'       => __('skipped', 'suite-devtools'),
                'failed'        => __('failed', 'suite-devtools'),
                /* translators: 1: number converted, 2: number skipped, 3: number failed, 4: human-readable size saved. */
                'summary'       => __('Done: %1$s converted, %2$s skipped, %3$s failed. Saved %4$s.', 'suite-devtools'),
                /* translators: %s: number of images pending conversion. */
                'pending'       => __('%s image(s) ready to convert.', 'suite-devtools'),
            ),
        ));
    }

    /* --------------------------------------------------------------------- */
    /* AJAX                                                                    */
    /* --------------------------------------------------------------------- */

    private function verify(): void {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'suite-devtools')), 403);
        }
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'suite-devtools')), 403);
        }
    }

    public function ajax_save_settings() {
        $this->verify();

        $raw      = isset($_POST['settings']) && is_array($_POST['settings']) ? array_map('sanitize_text_field', wp_unslash($_POST['settings'])) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify().
        $settings = array(
            'auto_upload' => isset($raw['auto_upload']) ? wp_validate_boolean($raw['auto_upload']) : false,
            'quality'     => isset($raw['quality']) ? max(1, min(100, (int) $raw['quality'])) : 82,
        );

        update_option(self::OPTION_KEY, $settings);
        wp_send_json_success(array(
            'message'  => __('Settings saved.', 'suite-devtools'),
            'settings' => $settings,
        ));
    }

    /** Return the IDs of every convertible (JPEG/PNG) attachment. */
    public function ajax_scan() {
        $this->verify();

        if (!self::server_supports_webp()) {
            wp_send_json_error(array('message' => __('This server cannot generate WebP images.', 'suite-devtools')), 400);
        }

        $ids = get_posts(array(
            'post_type'      => 'attachment',
            'post_mime_type' => self::convertible_mimes(),
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ));

        $ids = array_map('intval', $ids);
        wp_send_json_success(array('ids' => $ids, 'total' => count($ids)));
    }

    public function ajax_convert() {
        $this->verify();

        $id = isset($_POST['id']) ? (int) wp_unslash($_POST['id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in verify(); value cast to int.
        if ($id <= 0) {
            wp_send_json_error(array('message' => __('Invalid attachment ID.', 'suite-devtools')));
        }

        $result = self::convert_attachment($id, true);
        if (empty($result['success'])) {
            wp_send_json_error($result);
        }
        wp_send_json_success($result);
    }

    /* --------------------------------------------------------------------- */
    /* Auto-convert on upload                                                 */
    /* --------------------------------------------------------------------- */

    /**
     * Convert freshly uploaded JPEG/PNG attachments to WebP.
     *
     * Hooked late on wp_generate_attachment_metadata so the sub-sizes already
     * exist. Re-entrant calls (from our own regeneration) are skipped.
     *
     * @param array $metadata      Generated attachment metadata.
     * @param int   $attachment_id Attachment ID.
     * @return array Possibly-updated metadata.
     */
    public function maybe_convert_on_upload($metadata, $attachment_id) {
        if (self::$is_converting) {
            return $metadata;
        }

        $settings = self::get_settings();
        if (empty($settings['auto_upload'])) {
            return $metadata;
        }

        if (!self::server_supports_webp()) {
            return $metadata;
        }

        $mime = get_post_mime_type($attachment_id);
        if (!in_array($mime, self::convertible_mimes(), true)) {
            return $metadata;
        }

        // Persist the just-generated metadata so convert_attachment() can read
        // the original sizes, then convert. No content rewrite: a brand-new
        // upload is not referenced anywhere yet.
        wp_update_attachment_metadata($attachment_id, $metadata);

        $result = self::convert_attachment((int) $attachment_id, false);
        if (!empty($result['success']) && empty($result['skipped'])) {
            $new = wp_get_attachment_metadata($attachment_id);
            if (is_array($new)) {
                return $new;
            }
        }
        return $metadata;
    }

    /* --------------------------------------------------------------------- */
    /* Conversion core                                                        */
    /* --------------------------------------------------------------------- */

    /** Apply the configured WebP quality during (re)generation. */
    public static function filter_quality($quality, $mime_type = '') {
        if ('' === $mime_type || 'image/webp' === $mime_type) {
            $settings = self::get_settings();
            return $settings['quality'];
        }
        return $quality;
    }

    /**
     * Convert a single attachment to WebP, replacing and deleting the originals.
     *
     * @param int  $attachment_id   Attachment ID.
     * @param bool $rewrite_content Whether to rewrite the old URLs in post content.
     * @return array { success:bool, id:int, skipped:bool, message:string, saved_bytes:int }
     */
    public static function convert_attachment(int $attachment_id, bool $rewrite_content = true): array {
        $result = array(
            'success'     => false,
            'id'          => $attachment_id,
            'skipped'     => false,
            'message'     => '',
            'saved_bytes' => 0,
        );

        $mime = get_post_mime_type($attachment_id);

        if ('image/webp' === $mime) {
            $result['success'] = true;
            $result['skipped'] = true;
            $result['message'] = __('Already WebP.', 'suite-devtools');
            return $result;
        }
        if (!in_array($mime, self::convertible_mimes(), true)) {
            $result['success'] = true;
            $result['skipped'] = true;
            $result['message'] = __('Not a convertible image.', 'suite-devtools');
            return $result;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            $result['message'] = __('Source file not found.', 'suite-devtools');
            return $result;
        }

        // Capture the OLD locations before we change anything.
        $old_url      = wp_get_attachment_url($attachment_id);
        $base_url_dir = trailingslashit(dirname($old_url));
        $base_dir     = trailingslashit(dirname($file));
        $old_meta     = wp_get_attachment_metadata($attachment_id);

        $old_files = array();        // absolute path => true (to delete afterwards)
        $url_map   = array();        // old URL => new URL (to rewrite in content)
        $old_bytes = 0;
        $new_bytes = 0;

        $old_files[$file] = true;
        $old_bytes       += (int) @filesize($file);

        // 1) Convert the main file to WebP.
        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            $result['message'] = $editor->get_error_message();
            return $result;
        }
        $editor->set_quality(self::get_settings()['quality']);

        $new_path = self::webp_path($file);
        $saved    = $editor->save($new_path, 'image/webp');
        if (is_wp_error($saved)) {
            $result['message'] = $saved->get_error_message();
            return $result;
        }
        $new_path   = $saved['path'];
        $new_bytes += (int) @filesize($new_path);

        $url_map[$old_url] = $base_url_dir . wp_basename($new_path);

        // 2) Point the attachment at the new file and mark it as WebP.
        update_attached_file($attachment_id, $new_path);
        wp_update_post(array('ID' => $attachment_id, 'post_mime_type' => 'image/webp'));

        // 3) Regenerate the sub-sizes from the new WebP (guarded so the
        //    on-upload filter does not recurse).
        require_once ABSPATH . 'wp-admin/includes/image.php';
        self::$is_converting = true;
        add_filter('wp_editor_set_quality', array(__CLASS__, 'filter_quality'), 10, 2);
        $new_meta = wp_generate_attachment_metadata($attachment_id, $new_path);
        remove_filter('wp_editor_set_quality', array(__CLASS__, 'filter_quality'), 10);
        self::$is_converting = false;

        // 4) Collect the OLD sub-size files (and the unscaled original) and map
        //    their URLs to the freshly generated WebP equivalents.
        if (is_array($old_meta)) {
            if (!empty($old_meta['sizes'])) {
                foreach ($old_meta['sizes'] as $size => $info) {
                    if (empty($info['file'])) {
                        continue;
                    }
                    $old_size_path        = $base_dir . $info['file'];
                    $old_files[$old_size_path] = true;
                    $old_bytes           += (int) @filesize($old_size_path);

                    $new_size_file = isset($new_meta['sizes'][$size]['file']) ? $new_meta['sizes'][$size]['file'] : '';
                    if ('' !== $new_size_file) {
                        $url_map[$base_url_dir . $info['file']] = $base_url_dir . $new_size_file;
                        $new_bytes += (int) @filesize($base_dir . $new_size_file);
                    }
                }
            }
            if (!empty($old_meta['original_image'])) {
                $orig_path             = $base_dir . $old_meta['original_image'];
                $old_files[$orig_path] = true;
                $old_bytes            += (int) @filesize($orig_path);
            }
        }

        if (is_array($new_meta)) {
            wp_update_attachment_metadata($attachment_id, $new_meta);
        }

        // 5) Rewrite references in post content (bulk only).
        if ($rewrite_content) {
            self::rewrite_urls_in_content($url_map);
        }

        // 6) Delete the old JPEG/PNG files now that nothing points to them.
        foreach (array_keys($old_files) as $old_path) {
            if ($old_path !== $new_path && file_exists($old_path) && self::is_legacy_image($old_path)) {
                wp_delete_file($old_path);
            }
        }

        $result['success']     = true;
        $result['saved_bytes'] = max(0, $old_bytes - $new_bytes);
        /* translators: %s: human-readable size saved (e.g. "1.2 MB"). */
        $result['message']     = sprintf(__('Converted — saved %s.', 'suite-devtools'), size_format($result['saved_bytes']));
        return $result;
    }

    /** Build the .webp sibling path for a given file. */
    private static function webp_path(string $file): string {
        $dir  = dirname($file);
        $name = pathinfo($file, PATHINFO_FILENAME);
        return $dir . '/' . $name . '.webp';
    }

    /** True for the extensions we replace (never deletes a .webp). */
    private static function is_legacy_image(string $path): bool {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, array('jpg', 'jpeg', 'jpe', 'png'), true);
    }

    /**
     * Replace old image URLs with their WebP equivalents inside post content.
     * Limited to wp_posts.post_content (plain HTML) to avoid corrupting
     * serialized postmeta.
     */
    private static function rewrite_urls_in_content(array $url_map): void {
        if (empty($url_map)) {
            return;
        }
        global $wpdb;

        foreach ($url_map as $old => $new) {
            if ($old === $new || '' === $old || '' === $new) {
                continue;
            }
            $like = '%' . $wpdb->esc_like($old) . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk URL rewrite in post_content; not cacheable.
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
                    $old,
                    $new,
                    $like
                )
            );
        }
    }
}

endif;

if (!defined('DEVTOOLS_LIFECYCLE_RUN')) {
    new DevToolsWebP();
}
