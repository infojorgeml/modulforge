<?php
/**
 * Admin page for the Convert to WebP module.
 *
 * @var array $settings  Current settings (auto_upload, quality).
 * @var bool  $supported Whether the server can generate WebP.
 *
 * @package DevToolsWebP
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap devtools-webp">
    <h1><?php esc_html_e('Convert to WebP', 'modulforge'); ?></h1>
    <p class="description">
        <?php esc_html_e('Convert JPEG and PNG images to WebP to reduce their file size. New uploads can be converted automatically, and you can bulk-convert everything already in the media library.', 'modulforge'); ?>
    </p>

    <?php if (!$supported) : ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('WebP is not available on this server.', 'modulforge'); ?></strong>
                <?php esc_html_e('Neither GD (with WebP support) nor Imagick is available, so images cannot be converted. Ask your host to enable WebP support in PHP.', 'modulforge'); ?>
            </p>
        </div>
    <?php else : ?>

        <h2 class="title"><?php esc_html_e('Settings', 'modulforge'); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><?php esc_html_e('Automatic conversion', 'modulforge'); ?></th>
                    <td>
                        <label for="dtw-auto-upload">
                            <input type="checkbox" id="dtw-auto-upload" <?php checked(!empty($settings['auto_upload'])); ?> />
                            <?php esc_html_e('Convert new JPEG/PNG uploads to WebP automatically', 'modulforge'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="dtw-quality"><?php esc_html_e('WebP quality', 'modulforge'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="dtw-quality" class="small-text" min="1" max="100" step="1" value="<?php echo esc_attr($settings['quality']); ?>" />
                        <span>/ 100</span>
                        <p class="description"><?php esc_html_e('Higher means better quality and larger files. 80–85 is a good balance.', 'modulforge'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        <p>
            <button type="button" class="button button-primary" id="dtw-save"><?php esc_html_e('Save settings', 'modulforge'); ?></button>
            <span id="dtw-settings-msg" class="devtools-webp-msg" role="status" aria-live="polite"></span>
        </p>

        <hr />

        <h2 class="title"><?php esc_html_e('Bulk convert the media library', 'modulforge'); ?></h2>
        <p id="dtw-pending" class="devtools-webp-pending"><?php esc_html_e('Scanning the media library…', 'modulforge'); ?></p>

        <div class="notice notice-warning devtools-webp-warning inline">
            <p>
                <strong><?php esc_html_e('This cannot be undone.', 'modulforge'); ?></strong>
                <?php esc_html_e('Each image is replaced by a WebP version and the original JPEG/PNG files (including every generated size) are permanently deleted. There is no backup. Make sure you have your own backup before continuing.', 'modulforge'); ?>
            </p>
        </div>

        <p>
            <label for="dtw-confirm">
                <input type="checkbox" id="dtw-confirm" />
                <?php esc_html_e('I understand this permanently deletes the original images and cannot be undone.', 'modulforge'); ?>
            </label>
        </p>

        <p>
            <button type="button" class="button button-primary" id="dtw-convert-all" disabled>
                <?php esc_html_e('Convert all to WebP', 'modulforge'); ?>
            </button>
        </p>

        <div id="dtw-progress" class="devtools-webp-progress" hidden>
            <div class="devtools-webp-progress-track">
                <div id="dtw-progress-bar" class="devtools-webp-progress-bar"></div>
            </div>
            <p id="dtw-progress-text" class="devtools-webp-progress-text" role="status" aria-live="polite"></p>
        </div>

        <ul id="dtw-log" class="devtools-webp-log" hidden></ul>

    <?php endif; ?>
</div>
