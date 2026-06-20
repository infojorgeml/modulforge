<?php
// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Create New Tab modal -->
<div id="dtpt-create-tab-modal" class="dtpt-modal" style="display: none;">
    <div class="dtpt-modal-backdrop"></div>
    <div class="dtpt-modal-wrap">
        <div class="dtpt-modal-content">
            <div class="dtpt-modal-header">
                <h2><?php esc_html_e('Create New Tab', 'dev-tools'); ?></h2>
                <button type="button" class="dtpt-modal-close">
                    <span class="screen-reader-text"><?php esc_html_e('Close modal', 'dev-tools'); ?></span>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="dtpt-modal-body">
                <form id="dtpt-quick-tab-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="quick-tab-name"><?php esc_html_e('Tab Name', 'dev-tools'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="quick-tab-name" name="name" class="regular-text" required
                                       placeholder="<?php esc_attr_e('E.g. Legal Pages, Services, Blog...', 'dev-tools'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="quick-tab-color"><?php esc_html_e('Color', 'dev-tools'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="quick-tab-color" name="color" value="#0073aa">
                                <p class="description"><?php esc_html_e('Pick a color to visually identify this tab', 'dev-tools'); ?></p>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>

            <div class="dtpt-modal-footer">
                <button type="button" class="button" id="dtpt-modal-cancel">
                    <?php esc_html_e('Cancel', 'dev-tools'); ?>
                </button>
                <button type="submit" form="dtpt-quick-tab-form" class="button button-primary" id="dtpt-modal-create">
                    <span class="button-text"><?php esc_html_e('Create Tab', 'dev-tools'); ?></span>
                    <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Tab modal (edit/delete) -->
<div id="dtpt-manage-tab-modal" class="dtpt-modal" style="display: none;">
    <div class="dtpt-modal-backdrop"></div>
    <div class="dtpt-modal-wrap">
        <div class="dtpt-modal-content">
            <div class="dtpt-modal-header">
                <h2 id="dtpt-manage-tab-title"><?php esc_html_e('Manage Tab', 'dev-tools'); ?></h2>
                <button type="button" class="dtpt-modal-close">
                    <span class="screen-reader-text"><?php esc_html_e('Close modal', 'dev-tools'); ?></span>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="dtpt-modal-body">
                <form id="dtpt-manage-tab-form">
                    <input type="hidden" id="manage-tab-id" name="tab_id" value="">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="manage-tab-name"><?php esc_html_e('Tab Name', 'dev-tools'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="manage-tab-name" name="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="manage-tab-description"><?php esc_html_e('Description', 'dev-tools'); ?></label>
                            </th>
                            <td>
                                <textarea id="manage-tab-description" name="description" class="regular-text" rows="3"
                                         placeholder="<?php esc_attr_e('Optional tab description', 'dev-tools'); ?>"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="manage-tab-color"><?php esc_html_e('Color', 'dev-tools'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="manage-tab-color" name="color" value="#0073aa">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="manage-tab-position"><?php esc_html_e('Position', 'dev-tools'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="manage-tab-position" name="position" value="0" min="0">
                                <p class="description"><?php esc_html_e('Display order (0 = first position)', 'dev-tools'); ?></p>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>

            <div class="dtpt-modal-footer">
                <button type="button" class="button button-link-delete" id="dtpt-modal-delete">
                    <?php esc_html_e('Delete Tab', 'dev-tools'); ?>
                </button>
                <div class="dtpt-modal-actions-right">
                    <button type="button" class="button" id="dtpt-modal-cancel-manage">
                        <?php esc_html_e('Cancel', 'dev-tools'); ?>
                    </button>
                    <button type="submit" form="dtpt-manage-tab-form" class="button button-primary" id="dtpt-modal-update">
                        <span class="button-text"><?php esc_html_e('Update Tab', 'dev-tools'); ?></span>
                        <span class="spinner"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
