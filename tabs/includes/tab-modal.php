<?php
// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Create New Tab modal -->
<div id="pto-create-tab-modal" class="pto-modal" style="display: none;">
    <div class="pto-modal-backdrop"></div>
    <div class="pto-modal-wrap">
        <div class="pto-modal-content">
            <div class="pto-modal-header">
                <h2><?php esc_html_e('Create New Tab', 'suitewp'); ?></h2>
                <button type="button" class="pto-modal-close">
                    <span class="screen-reader-text"><?php esc_html_e('Close modal', 'suitewp'); ?></span>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="pto-modal-body">
                <form id="pto-quick-tab-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="quick-tab-name"><?php esc_html_e('Tab Name', 'suitewp'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="quick-tab-name" name="name" class="regular-text" required
                                       placeholder="<?php esc_attr_e('E.g. Legal Pages, Services, Blog...', 'suitewp'); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="quick-tab-color"><?php esc_html_e('Color', 'suitewp'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="quick-tab-color" name="color" value="#0073aa">
                                <p class="description"><?php esc_html_e('Pick a color to visually identify this tab', 'suitewp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>

            <div class="pto-modal-footer">
                <button type="button" class="button" id="pto-modal-cancel">
                    <?php esc_html_e('Cancel', 'suitewp'); ?>
                </button>
                <button type="submit" form="pto-quick-tab-form" class="button button-primary" id="pto-modal-create">
                    <span class="button-text"><?php esc_html_e('Create Tab', 'suitewp'); ?></span>
                    <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Manage Tab modal (edit/delete) -->
<div id="pto-manage-tab-modal" class="pto-modal" style="display: none;">
    <div class="pto-modal-backdrop"></div>
    <div class="pto-modal-wrap">
        <div class="pto-modal-content">
            <div class="pto-modal-header">
                <h2 id="pto-manage-tab-title"><?php esc_html_e('Manage Tab', 'suitewp'); ?></h2>
                <button type="button" class="pto-modal-close">
                    <span class="screen-reader-text"><?php esc_html_e('Close modal', 'suitewp'); ?></span>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="pto-modal-body">
                <form id="pto-manage-tab-form">
                    <input type="hidden" id="manage-tab-id" name="tab_id" value="">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="manage-tab-name"><?php esc_html_e('Tab Name', 'suitewp'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="manage-tab-name" name="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="manage-tab-description"><?php esc_html_e('Description', 'suitewp'); ?></label>
                            </th>
                            <td>
                                <textarea id="manage-tab-description" name="description" class="regular-text" rows="3"
                                         placeholder="<?php esc_attr_e('Optional tab description', 'suitewp'); ?>"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="manage-tab-color"><?php esc_html_e('Color', 'suitewp'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="manage-tab-color" name="color" value="#0073aa">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="manage-tab-position"><?php esc_html_e('Position', 'suitewp'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="manage-tab-position" name="position" value="0" min="0">
                                <p class="description"><?php esc_html_e('Display order (0 = first position)', 'suitewp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>

            <div class="pto-modal-footer">
                <button type="button" class="button button-link-delete" id="pto-modal-delete">
                    <?php esc_html_e('Delete Tab', 'suitewp'); ?>
                </button>
                <div class="pto-modal-actions-right">
                    <button type="button" class="button" id="pto-modal-cancel-manage">
                        <?php esc_html_e('Cancel', 'suitewp'); ?>
                    </button>
                    <button type="submit" form="pto-manage-tab-form" class="button button-primary" id="pto-modal-update">
                        <span class="button-text"><?php esc_html_e('Update Tab', 'suitewp'); ?></span>
                        <span class="spinner"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
