<?php
/**
 * Modulforge — Debug & Logs module.
 *
 * Bundled component loaded by the Modulforge controller; not a standalone plugin.
 *
 * Captures PHP errors to a private log file inside the uploads folder and lets
 * you read, filter, download and clear it from the admin. It does NOT edit
 * wp-config.php or any core file: logging is enabled at runtime with ini_set(),
 * and all writes happen inside wp_upload_dir()/modulforge via WP_Filesystem.
 *
 * @package Modulforge
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Modulforge_Debug')) :

class Modulforge_Debug {

    const VERSION        = '1.1.0';
    const OPTION_KEY     = 'modulforge_debug_settings';
    const NONCE_ACTION   = 'modulforge_debug';
    const MENU_SLUG      = 'modulforge-debug';
    const CAPABILITY     = 'manage_options';
    const LOG_TAIL_BYTES = 131072;        // 128 KB tail shown in the viewer
    const LOG_DIR_NAME   = 'modulforge';  // sub-folder of wp_upload_dir()

    /** Hook suffix of our admin page (set when the menu is registered). */
    private $page_hook = '';

    public function __construct() {
        // Apply the logging configuration as early as the module can (it is
        // loaded on plugins_loaded by the controller), on BOTH front and admin
        // requests, so errors are captured everywhere while logging is enabled.
        $this->maybe_enable_logging();

        add_action('admin_menu', array($this, 'add_admin_menu'), 11);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_modulforge_debug_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_modulforge_debug_get_log', array($this, 'ajax_get_log'));
        add_action('wp_ajax_modulforge_debug_clear_log', array($this, 'ajax_clear_log'));
        add_action('wp_ajax_modulforge_debug_download_log', array($this, 'ajax_download_log'));
    }

    /* --------------------------------------------------------------------- */
    /* Settings                                                               */
    /* --------------------------------------------------------------------- */

    private static function default_settings(): array {
        return array(
            'enabled'        => false,  // capture PHP errors to our log file
            'display_errors' => false,  // also print errors on screen (unsafe live)
            'log_token'      => '',      // random component of the log filename
        );
    }

    private static function get_settings(): array {
        $saved = get_option(self::OPTION_KEY, array());
        $s     = wp_parse_args(is_array($saved) ? $saved : array(), self::default_settings());

        $s['enabled']        = (bool) $s['enabled'];
        $s['display_errors'] = (bool) $s['display_errors'];
        $s['log_token']      = is_string($s['log_token']) ? preg_replace('/[^A-Za-z0-9]/', '', $s['log_token']) : '';

        return $s;
    }

    /** Persist settings, minting the random log-file token on first enable. */
    private static function store_settings(array $settings): array {
        if ($settings['enabled'] && '' === $settings['log_token']) {
            $settings['log_token'] = wp_generate_password(20, false, false); // alnum, unguessable
        }
        update_option(self::OPTION_KEY, $settings);
        return $settings;
    }

    /* --------------------------------------------------------------------- */
    /* Log location — always inside uploads, never the plugin or core dirs    */
    /* --------------------------------------------------------------------- */

    private static function log_dir(): string {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['basedir']) . self::LOG_DIR_NAME;
    }

    /** Randomised, hard-to-guess filename (defence on servers ignoring .htaccess). */
    private static function log_path(): string {
        $s     = self::get_settings();
        $token = '' !== $s['log_token'] ? $s['log_token'] : 'log';
        return self::log_dir() . '/debug-' . $token . '.log';
    }

    /* --------------------------------------------------------------------- */
    /* Runtime logging — no wp-config edits, just ini_set for this request    */
    /* --------------------------------------------------------------------- */

    private function maybe_enable_logging(): void {
        $s = self::get_settings();
        if (!$s['enabled'] || '' === $s['log_token']) {
            return;
        }

        $dir = self::log_dir();
        if (!is_dir($dir)) {
            wp_mkdir_p($dir); // native, cheap, idempotent; hardening added on save
        }

        // Route PHP's error log to our private file and raise reporting. ini_set
        // may be disabled on some hosts — fail silently if so.
        @ini_set('log_errors', '1');                                   // phpcs:ignore WordPress.PHP.IniSet.Risky -- Opt-in debugging toggle, scoped to this request.
        @ini_set('error_log', self::log_path());                       // phpcs:ignore WordPress.PHP.IniSet.Risky -- Send errors to our own log under uploads, not wp-config.
        @ini_set('display_errors', $s['display_errors'] ? '1' : '0');  // phpcs:ignore WordPress.PHP.IniSet.display_errors,WordPress.PHP.IniSet.Risky -- User-controlled debug toggle.
        error_reporting(E_ALL);                                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_reporting_error_reporting -- Capture all errors while debugging is enabled.
    }

    /**
     * Create + harden the uploads/modulforge directory. .htaccess covers Apache;
     * the randomised filename covers servers that ignore .htaccess. Writes go
     * through WP_Filesystem. Safe to call repeatedly.
     */
    private static function ensure_log_dir(): void {
        $dir = self::log_dir();
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $fs = self::fs();
        if (!$fs) {
            return;
        }
        if (!$fs->exists($dir . '/.htaccess')) {
            $fs->put_contents($dir . '/.htaccess', "Require all denied\n");
        }
        if (!$fs->exists($dir . '/index.html')) {
            $fs->put_contents($dir . '/index.html', '');
        }
    }

    /** Lazily initialise WP_Filesystem (direct method on typical uploads). */
    private static function fs() {
        global $wp_filesystem;
        if (!empty($wp_filesystem)) {
            return $wp_filesystem;
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if (WP_Filesystem()) {
            return $wp_filesystem;
        }
        return null;
    }

    /* --------------------------------------------------------------------- */
    /* Lifecycle — invoked by the Modulforge controller                       */
    /* --------------------------------------------------------------------- */

    public static function activate(): void {
        if (false === get_option(self::OPTION_KEY, false)) {
            add_option(self::OPTION_KEY, self::default_settings());
        }
    }

    public static function deactivate(): void {
        // Logging stops on its own once this module is no longer loaded (the
        // ini_set runs only while active). Nothing in wp-config to revert.
    }

    public static function uninstall(): void {
        delete_option(self::OPTION_KEY);

        // Remove our private log directory (and its contents) from uploads.
        $dir = self::log_dir();
        $fs  = self::fs();
        if ($fs && $fs->is_dir($dir)) {
            $fs->delete($dir, true); // recursive
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
                'saved'         => __('Settings saved. Reload your site to start capturing errors.', 'modulforge'),
                'save_error'    => __('Could not save settings.', 'modulforge'),
                'connect_error' => __('Connection error.', 'modulforge'),
                'confirm_clear' => __('Clear the log? This cannot be undone.', 'modulforge'),
                'cleared'       => __('Log cleared.', 'modulforge'),
                'empty_log'     => __('The log is empty.', 'modulforge'),
                'no_match'      => __('No entries match the current filter.', 'modulforge'),
                'copied'        => __('Copied to clipboard.', 'modulforge'),
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

        $raw      = isset($_POST['settings']) && is_array($_POST['settings']) ? array_map('sanitize_text_field', wp_unslash($_POST['settings'])) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify().
        $current  = self::get_settings();
        $settings = array(
            'enabled'        => isset($raw['enabled']) ? wp_validate_boolean($raw['enabled']) : false,
            'display_errors' => isset($raw['display_errors']) ? wp_validate_boolean($raw['display_errors']) : false,
            'log_token'      => $current['log_token'],
        );
        // "Show errors on screen" only makes sense while logging is enabled.
        if (!$settings['enabled']) {
            $settings['display_errors'] = false;
        }

        $settings = self::store_settings($settings); // mints the token on first enable

        if ($settings['enabled']) {
            self::ensure_log_dir(); // create + harden the directory now
        }

        wp_send_json_success(array(
            'message' => __('Settings saved. Reload your site to start capturing errors.', 'modulforge'),
            'state'   => self::current_state(),
        ));
    }

    public function ajax_get_log() {
        $this->verify();

        $path = self::log_path();
        if (!file_exists($path) || !is_readable($path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_readable -- Read-only check on our own log under uploads.
            wp_send_json_success(array('raw' => '', 'exists' => false, 'size' => 0, 'mtime' => 0, 'truncated' => false));
        }

        $size = (int) filesize($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Metadata of our own log.
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
        $fs   = self::fs();
        if ($fs && $fs->exists($path)) {
            $fs->put_contents($path, ''); // truncate via WP_Filesystem
        }
        wp_send_json_success(array('message' => __('Log cleared.', 'modulforge')));
    }

    public function ajax_download_log() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false) || !current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Access denied.', 'modulforge'), '', array('response' => 403));
        }

        $path = self::log_path();
        if (!file_exists($path) || !is_readable($path)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_readable -- Read-only check on our own log under uploads.
            wp_die(esc_html__('No log file.', 'modulforge'), '', array('response' => 404));
        }

        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="modulforge-debug.log"');
        header('Content-Length: ' . filesize($path)); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Metadata of our own log.
        readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming our own log file to the browser for download.
        exit;
    }

    /* --------------------------------------------------------------------- */
    /* Log helpers                                                            */
    /* --------------------------------------------------------------------- */

    /** Read up to $bytes from the end of our log, discarding the first partial line. */
    private static function tail(string $path, int $bytes): string {
        $size = (int) filesize($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Metadata of our own log.
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
        $s      = self::get_settings();
        $path   = self::log_path();
        $exists = file_exists($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_exists -- Read-only check on our own log.

        return array(
            'settings'       => array(
                'enabled'        => $s['enabled'],
                'display_errors' => $s['display_errors'],
            ),
            // Whether our ini_set actually took effect this request.
            'logging_active' => $s['enabled'] && '' !== $s['log_token'] && (string) @ini_get('error_log') === $path,
            'dir_writable'   => wp_is_writable(self::log_dir()) || wp_is_writable(dirname(self::log_dir())),
            'log_exists'     => $exists,
            'log_size'       => $exists ? (int) filesize($path) : 0, // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- Metadata of our own log.
            'log_mtime'      => $exists ? (int) filemtime($path) : 0,
            // Informational only — the real WP_DEBUG constants live in wp-config.
            'wp_debug'       => defined('WP_DEBUG') && WP_DEBUG,
        );
    }
}

endif;

if (!defined('MODULFORGE_LIFECYCLE_RUN')) {
    new Modulforge_Debug();
}
