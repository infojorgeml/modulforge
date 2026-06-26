<?php
/**
 * Modulforge — Debug & Logs module.
 *
 * Bundled component loaded by the Modulforge controller; not a standalone plugin.
 * Toggle WordPress debugging from the admin and view the debug log without FTP.
 *
 * @package Modulforge
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Modulforge_Debug')) :

class Modulforge_Debug {

    const VERSION         = '1.0.1';
    const OPTION_KEY      = 'modulforge_debug_settings';
    const NONCE_ACTION    = 'modulforge_debug';
    const MENU_SLUG       = 'modulforge-debug';
    const CAPABILITY      = 'manage_options';
    const BLOCK_BEGIN     = '/* BEGIN Modulforge Debug */';
    const BLOCK_END       = '/* END Modulforge Debug */';
    const DISABLED_PREFIX = '// Modulforge-disabled: ';
    const LOG_TAIL_BYTES  = 131072; // 128 KB tail

    /** Hook suffix of our admin page (set when the menu is registered). */
    private $page_hook = '';

    public function __construct() {
        // Priority 11 so the Modulforge top-level menu (priority 10) exists first.
        add_action('admin_menu', array($this, 'add_admin_menu'), 11);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_modulforge_debug_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_modulforge_debug_get_log', array($this, 'ajax_get_log'));
        add_action('wp_ajax_modulforge_debug_clear_log', array($this, 'ajax_clear_log'));
        add_action('wp_ajax_modulforge_debug_download_log', array($this, 'ajax_download_log'));
        add_action('wp_ajax_modulforge_debug_restore_backup', array($this, 'ajax_restore_backup'));
    }

    /** Constants managed inside our wp-config block. */
    private static function managed_constants(): array {
        return array('WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'SCRIPT_DEBUG', 'SAVEQUERIES');
    }

    private static function default_settings(): array {
        return array(
            'wp_debug'         => false,
            'wp_debug_log'     => false,
            'wp_debug_display' => false,
            'script_debug'     => false,
            'savequeries'      => false,
        );
    }

    private static function get_settings(): array {
        $saved = get_option(self::OPTION_KEY, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), self::default_settings());
    }

    /* --------------------------------------------------------------------- */
    /* Lifecycle — invoked by the Modulforge controller                          */
    /* --------------------------------------------------------------------- */

    public static function activate(): void {
        if (false === get_option(self::OPTION_KEY, false)) {
            add_option(self::OPTION_KEY, self::default_settings());
        }
    }

    /** Never leave the site in debug mode once the tool is gone. */
    public static function deactivate(): void {
        self::revert_wp_config();
    }

    public static function uninstall(): void {
        self::revert_wp_config();
        delete_option(self::OPTION_KEY);

        // Remove our backup directory.
        $dir = self::backup_dir();
        foreach (array('wp-config-original.bak', '.htaccess', 'index.html') as $f) {
            if (file_exists($dir . '/' . $f)) {
                wp_delete_file($dir . '/' . $f);
            }
        }
        if (is_dir($dir)) {
            @rmdir($dir); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing our own empty backup directory on uninstall.
        }
    }

    /* --------------------------------------------------------------------- */
    /* Admin page & assets                                                    */
    /* --------------------------------------------------------------------- */

    public function add_admin_menu() {
        // Register under the Modulforge top-level menu so it's easy to find.
        $this->page_hook = add_submenu_page(
            'modulforge',
            __('Debug & Logs', 'modulforge'),
            __('Debug & Logs', 'modulforge'),
            self::CAPABILITY,
            self::MENU_SLUG,
            array($this, 'render_admin_page')
        );
    }

    public function render_admin_page() {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to access this page.', 'modulforge'));
        }
        $settings = self::get_settings();
        $state    = self::current_state();
        include __DIR__ . '/includes/admin-page.php';
    }

    public function enqueue_assets($hook) {
        if ('' === $this->page_hook || $hook !== $this->page_hook) {
            return;
        }

        wp_enqueue_script(
            'modulforge-debug',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            array(),
            self::VERSION,
            true
        );
        wp_enqueue_style(
            'modulforge-debug',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            array(),
            self::VERSION
        );

        wp_localize_script('modulforge-debug', 'modulforgeDebug', array(
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE_ACTION),
            'download_url' => add_query_arg(
                array('action' => 'modulforge_debug_download_log', 'nonce' => wp_create_nonce(self::NONCE_ACTION)),
                admin_url('admin-ajax.php')
            ),
            'state'        => self::current_state(),
            'i18n'         => array(
                'saved'          => __('Settings saved. Reload pages to apply.', 'modulforge'),
                'save_error'     => __('Could not save settings.', 'modulforge'),
                'connect_error'  => __('Connection error.', 'modulforge'),
                'confirm_clear'  => __('Clear the debug log? This cannot be undone.', 'modulforge'),
                'confirm_restore'=> __('Restore wp-config.php from the original backup?', 'modulforge'),
                'cleared'        => __('Log cleared.', 'modulforge'),
                'empty_log'      => __('The debug log is empty.', 'modulforge'),
                'no_match'       => __('No entries match the current filter.', 'modulforge'),
                'restored'       => __('wp-config.php restored from backup.', 'modulforge'),
            ),
        ));
    }

    /* --------------------------------------------------------------------- */
    /* AJAX                                                                    */
    /* --------------------------------------------------------------------- */

    private function verify(): void {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'modulforge')), 403);
        }
        if (!current_user_can(self::CAPABILITY)) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'modulforge')), 403);
        }
    }

    public function ajax_save_settings() {
        $this->verify();

        $raw      = isset($_POST['settings']) && is_array($_POST['settings']) ? array_map('sanitize_text_field', wp_unslash($_POST['settings'])) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in verify().
        $settings = array();
        foreach (self::default_settings() as $key => $unused) {
            $settings[$key] = isset($raw[$key]) ? wp_validate_boolean($raw[$key]) : false;
        }
        // WP_DEBUG is the master switch.
        if (!$settings['wp_debug']) {
            $settings['wp_debug_log']     = false;
            $settings['wp_debug_display'] = false;
            $settings['script_debug']     = false;
            $settings['savequeries']      = false;
        }

        update_option(self::OPTION_KEY, $settings);

        $result = self::apply_wp_config($settings);
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message'      => $result->get_error_message(),
                'manual_block' => $settings['wp_debug'] ? self::build_block($settings) : '',
                'state'        => self::current_state(),
            ));
        }

        wp_send_json_success(array(
            'message' => __('Settings saved.', 'modulforge'),
            'state'   => self::current_state(),
        ));
    }

    public function ajax_get_log() {
        $this->verify();

        $path = self::log_path();
        if (!file_exists($path) || !is_readable($path)) {
            wp_send_json_success(array('raw' => '', 'exists' => false, 'size' => 0, 'mtime' => 0, 'truncated' => false));
        }

        $size = (int) filesize($path);
        wp_send_json_success(array(
            'raw'       => self::tail($path, self::LOG_TAIL_BYTES),
            'exists'    => true,
            'size'      => $size,
            'mtime'     => (int) filemtime($path),
            'truncated' => $size > self::LOG_TAIL_BYTES,
        ));
    }

    public function ajax_clear_log() {
        $this->verify();

        $path = self::log_path();
        if (file_exists($path)) {
            file_put_contents($path, '', LOCK_EX);
        }
        wp_send_json_success(array('message' => __('Log cleared.', 'modulforge')));
    }

    public function ajax_restore_backup() {
        $this->verify();

        $path   = self::locate_wp_config();
        $backup = self::backup_dir() . '/wp-config-original.bak';

        if ('' === $path || !is_writable($path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- wp-config.php is outside WP_Filesystem's scope.
            wp_send_json_error(array('message' => __('wp-config.php is not writable.', 'modulforge')), 400);
        }
        if (!file_exists($backup)) {
            wp_send_json_error(array('message' => __('No backup found.', 'modulforge')), 404);
        }

        $contents = file_get_contents($backup);
        if (false === $contents || false === file_put_contents($path, $contents, LOCK_EX)) {
            wp_send_json_error(array('message' => __('Could not restore wp-config.php.', 'modulforge')), 500);
        }

        update_option(self::OPTION_KEY, self::default_settings());
        wp_send_json_success(array('message' => __('Restored.', 'modulforge'), 'state' => self::current_state()));
    }

    public function ajax_download_log() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false) || !current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Access denied.', 'modulforge'), '', array('response' => 403));
        }

        $path = self::log_path();
        if (!file_exists($path) || !is_readable($path)) {
            wp_die(esc_html__('No log file.', 'modulforge'), '', array('response' => 404));
        }

        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="debug.log"');
        header('Content-Length: ' . filesize($path));
        readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming the debug log file to the browser for download.
        exit;
    }

    /* --------------------------------------------------------------------- */
    /* wp-config.php editing                                                  */
    /* --------------------------------------------------------------------- */

    /** Locate wp-config.php the way wp-load.php does. */
    private static function locate_wp_config(): string {
        if (file_exists(ABSPATH . 'wp-config.php')) {
            return ABSPATH . 'wp-config.php';
        }
        if (file_exists(dirname(ABSPATH) . '/wp-config.php') && !file_exists(dirname(ABSPATH) . '/wp-settings.php')) {
            return dirname(ABSPATH) . '/wp-config.php';
        }
        return '';
    }

    /**
     * Apply settings to wp-config.php.
     *
     * @return true|WP_Error
     */
    private static function apply_wp_config(array $settings) {
        $path = self::locate_wp_config();
        if ('' === $path) {
            return new WP_Error('not_found', __('wp-config.php could not be located.', 'modulforge'));
        }
        if (!is_writable($path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- wp-config.php is outside WP_Filesystem's scope.
            return new WP_Error('not_writable', __('wp-config.php is not writable. Add the block below manually.', 'modulforge'));
        }

        $contents = file_get_contents($path);
        if (false === $contents) {
            return new WP_Error('read_failed', __('Could not read wp-config.php.', 'modulforge'));
        }

        self::backup($contents);

        // Always start from a clean slate.
        $contents = self::strip_block($contents);
        $contents = self::restore_commented_defines($contents);

        // When debug is on, comment the originals and insert our block.
        if (!empty($settings['wp_debug'])) {
            $contents = self::comment_existing_defines($contents);
            $contents = self::insert_block($contents, self::build_block($settings));
        }

        if (false === file_put_contents($path, $contents, LOCK_EX)) {
            return new WP_Error('write_failed', __('Could not write wp-config.php.', 'modulforge'));
        }
        return true;
    }

    /** Remove our block and un-comment the originals. Used on disable/deactivate/uninstall. */
    private static function revert_wp_config(): void {
        $path = self::locate_wp_config();
        if ('' === $path || !is_writable($path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- wp-config.php is outside WP_Filesystem's scope.
            return;
        }
        $contents = file_get_contents($path);
        if (false === $contents) {
            return;
        }
        $new = self::restore_commented_defines(self::strip_block($contents));
        if ($new !== $contents) {
            file_put_contents($path, $new, LOCK_EX);
        }
    }

    private static function build_block(array $settings): string {
        $lines   = array(self::BLOCK_BEGIN);
        $lines[] = "define( 'WP_DEBUG', true );";
        if (!empty($settings['wp_debug_log'])) {
            $lines[] = "define( 'WP_DEBUG_LOG', true );";
        }
        $lines[] = "define( 'WP_DEBUG_DISPLAY', " . (!empty($settings['wp_debug_display']) ? 'true' : 'false') . " );";
        if (empty($settings['wp_debug_display'])) {
            $lines[] = "@ini_set( 'display_errors', '0' );";
        }
        if (!empty($settings['script_debug'])) {
            $lines[] = "define( 'SCRIPT_DEBUG', true );";
        }
        if (!empty($settings['savequeries'])) {
            $lines[] = "define( 'SAVEQUERIES', true );";
        }
        $lines[] = self::BLOCK_END;
        return implode("\n", $lines);
    }

    private static function strip_block(string $contents): string {
        $pattern = '/\n?[ \t]*' . preg_quote(self::BLOCK_BEGIN, '/') . '.*?' . preg_quote(self::BLOCK_END, '/') . '[ \t]*\n?/s';
        return preg_replace($pattern, "\n", $contents);
    }

    private static function comment_existing_defines(string $contents): string {
        foreach (self::managed_constants() as $const) {
            $pattern = '/^([ \t]*)(define\(\s*[\'"]' . $const . '[\'"]\s*,.*?\)\s*;.*)$/m';
            $contents = preg_replace($pattern, '$1' . self::DISABLED_PREFIX . '$2', $contents);
        }
        return $contents;
    }

    private static function restore_commented_defines(string $contents): string {
        $pattern = '/^([ \t]*)' . preg_quote(self::DISABLED_PREFIX, '/') . '(define\(.*)$/m';
        return preg_replace($pattern, '$1$2', $contents);
    }

    private static function insert_block(string $contents, string $block): string {
        $anchor = self::find_insert_offset($contents);
        return substr($contents, 0, $anchor) . $block . "\n\n" . substr($contents, $anchor);
    }

    /** Offset of the start of the line containing "stop editing" (or the wp-settings require). */
    private static function find_insert_offset(string $contents): int {
        $pos = strpos($contents, 'stop editing');
        if (false === $pos) {
            $pos = strpos($contents, "wp-settings.php");
        }
        if (false === $pos) {
            return strlen($contents); // append as a last resort
        }
        $nl = strrpos(substr($contents, 0, $pos), "\n");
        return (false === $nl) ? 0 : $nl + 1;
    }

    /* --------------------------------------------------------------------- */
    /* Backups                                                                */
    /* --------------------------------------------------------------------- */

    private static function backup_dir(): string {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['basedir']) . 'modulforge-debug';
    }

    /** Keep a single pristine backup (the state before Modulforge ever touched it). */
    private static function backup(string $contents): void {
        $dir = self::backup_dir();
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
            @file_put_contents($dir . '/index.html', '');
        }
        $orig = $dir . '/wp-config-original.bak';
        if (!file_exists($orig)) {
            @file_put_contents($orig, $contents, LOCK_EX);
        }
    }

    /* --------------------------------------------------------------------- */
    /* Log helpers                                                            */
    /* --------------------------------------------------------------------- */

    private static function log_path(): string {
        return WP_CONTENT_DIR . '/debug.log';
    }

    /** Read up to $bytes from the end of a file, discarding the first partial line. */
    private static function tail(string $path, int $bytes): string {
        $size = (int) filesize($path);
        $fp   = fopen($path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Seeking the tail of a potentially large log; WP_Filesystem offers no seek.
        if (!$fp) {
            return '';
        }
        if ($size > $bytes) {
            fseek($fp, -$bytes, SEEK_END);
            fgets($fp); // drop partial first line
        }
        $data = stream_get_contents($fp);
        fclose($fp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the handle opened above.
        return (false === $data) ? '' : $data;
    }

    private static function current_state(): array {
        $path = self::log_path();
        $cfg  = self::locate_wp_config();
        return array(
            'settings'        => self::get_settings(),
            'runtime'         => array(
                'wp_debug'         => defined('WP_DEBUG') && WP_DEBUG,
                'wp_debug_log'     => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
                'wp_debug_display' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY,
                'script_debug'     => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
                'savequeries'      => defined('SAVEQUERIES') && SAVEQUERIES,
            ),
            'config_writable' => '' !== $cfg && is_writable($cfg), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- wp-config.php is outside WP_Filesystem's scope.
            'has_backup'      => file_exists(self::backup_dir() . '/wp-config-original.bak'),
            'log_exists'      => file_exists($path),
            'log_size'        => file_exists($path) ? (int) filesize($path) : 0,
            'log_mtime'       => file_exists($path) ? (int) filemtime($path) : 0,
        );
    }
}

endif;

if (!defined('MODULFORGE_LIFECYCLE_RUN')) {
    new Modulforge_Debug();
}
