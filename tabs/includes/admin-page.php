<?php
// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to access this page.', 'dev-tools'));
}

global $wpdb;

// All tabs.
$tabs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}page_tabs ORDER BY position ASC, name ASC");

// All pages.
$pages = get_pages(array(
    'post_status' => array('publish', 'draft', 'private'),
    'number'      => 0,
));

// Page-tab relations + per-tab counts (computed in PHP — no per-tab COUNT query).
$relations      = array();
$counts         = array();
$relations_data = $wpdb->get_results("SELECT page_id, tab_id FROM {$wpdb->prefix}page_tab_relations");
foreach ($relations_data as $relation) {
    $relations[$relation->page_id] = $relation->tab_id;
    $counts[$relation->tab_id]     = isset($counts[$relation->tab_id]) ? $counts[$relation->tab_id] + 1 : 1;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Manage Page Tabs', 'dev-tools'); ?></h1>

    <div class="dtpt-admin-container">
        <!-- Tabs section -->
        <div class="dtpt-tabs-section">
            <h2><?php esc_html_e('Tabs', 'dev-tools'); ?></h2>

            <div class="dtpt-add-tab-form">
                <h3><?php esc_html_e('Create New Tab', 'dev-tools'); ?></h3>
                <form id="dtpt-tab-form">
                    <input type="hidden" id="tab-id" name="tab_id" value="0">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="tab-name"><?php esc_html_e('Name', 'dev-tools'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="tab-name" name="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tab-description"><?php esc_html_e('Description', 'dev-tools'); ?></label>
                            </th>
                            <td>
                                <textarea id="tab-description" name="description" class="regular-text" rows="3"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tab-color"><?php esc_html_e('Color', 'dev-tools'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="tab-color" name="color" value="#0073aa">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tab-position"><?php esc_html_e('Position', 'dev-tools'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="tab-position" name="position" value="0" min="0">
                                <p class="description"><?php esc_html_e('Display order (0 = first position)', 'dev-tools'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <span class="button-text"><?php esc_html_e('Create Tab', 'dev-tools'); ?></span>
                            <span class="spinner"></span>
                        </button>
                        <button type="button" id="cancel-edit" class="button" style="display: none;">
                            <?php esc_html_e('Cancel', 'dev-tools'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <!-- Existing tabs list -->
            <div class="dtpt-tabs-list">
                <h3><?php esc_html_e('Existing Tabs', 'dev-tools'); ?></h3>

                <?php if (empty($tabs)) : ?>
                    <p><?php esc_html_e('No tabs created yet.', 'dev-tools'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'dev-tools'); ?></th>
                                <th><?php esc_html_e('Description', 'dev-tools'); ?></th>
                                <th><?php esc_html_e('Color', 'dev-tools'); ?></th>
                                <th><?php esc_html_e('Pages', 'dev-tools'); ?></th>
                                <th><?php esc_html_e('Position', 'dev-tools'); ?></th>
                                <th><?php esc_html_e('Actions', 'dev-tools'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tabs as $tab) : ?>
                                <?php $page_count = isset($counts[$tab->id]) ? $counts[$tab->id] : 0; ?>
                                <tr data-tab-id="<?php echo esc_attr($tab->id); ?>">
                                    <td>
                                        <strong style="color: <?php echo esc_attr($tab->color); ?>;">
                                            <?php echo esc_html($tab->name); ?>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html($tab->description); ?></td>
                                    <td>
                                        <div class="color-preview" style="background-color: <?php echo esc_attr($tab->color); ?>; width: 20px; height: 20px; border-radius: 3px; display: inline-block;"></div>
                                        <?php echo esc_html($tab->color); ?>
                                    </td>
                                    <td><?php echo intval($page_count); ?></td>
                                    <td><?php echo intval($tab->position); ?></td>
                                    <td>
                                        <button class="button button-small edit-tab"
                                                data-tab-id="<?php echo esc_attr($tab->id); ?>"
                                                data-name="<?php echo esc_attr($tab->name); ?>"
                                                data-description="<?php echo esc_attr($tab->description); ?>"
                                                data-color="<?php echo esc_attr($tab->color); ?>"
                                                data-position="<?php echo esc_attr($tab->position); ?>">
                                            <?php esc_html_e('Edit', 'dev-tools'); ?>
                                        </button>
                                        <button class="button button-small button-link-delete delete-tab"
                                                data-tab-id="<?php echo esc_attr($tab->id); ?>">
                                            <?php esc_html_e('Delete', 'dev-tools'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Page assignment section -->
        <div class="dtpt-pages-section">
            <h2><?php esc_html_e('Assign Pages to Tabs', 'dev-tools'); ?></h2>

            <?php if (empty($tabs)) : ?>
                <p><?php esc_html_e('You must create at least one tab before you can assign pages.', 'dev-tools'); ?></p>
            <?php elseif (empty($pages)) : ?>
                <p><?php esc_html_e('No pages available to assign.', 'dev-tools'); ?></p>
            <?php else : ?>
                <div class="dtpt-pages-assignment">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Page', 'dev-tools'); ?></th>
                                <th><?php esc_html_e('Status', 'dev-tools'); ?></th>
                                <th><?php esc_html_e('Assigned Tab', 'dev-tools'); ?></th>
                                <th><?php esc_html_e('Actions', 'dev-tools'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pages as $page) : ?>
                                <tr data-page-id="<?php echo esc_attr($page->ID); ?>">
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url(get_edit_post_link($page->ID)); ?>" target="_blank">
                                                <?php echo esc_html($page->post_title); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="view">
                                                <a href="<?php echo esc_url(get_permalink($page->ID)); ?>" target="_blank">
                                                    <?php esc_html_e('View', 'dev-tools'); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="post-state">
                                            <?php echo esc_html(ucfirst($page->post_status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $assigned_tab_id    = isset($relations[$page->ID]) ? $relations[$page->ID] : 0;
                                        $assigned_tab_name  = '';
                                        $assigned_tab_color = '#666';

                                        if ($assigned_tab_id > 0) {
                                            foreach ($tabs as $tab) {
                                                if ($tab->id == $assigned_tab_id) {
                                                    $assigned_tab_name  = $tab->name;
                                                    $assigned_tab_color = $tab->color;
                                                    break;
                                                }
                                            }
                                        }
                                        ?>

                                        <select class="page-tab-select" data-page-id="<?php echo esc_attr($page->ID); ?>">
                                            <option value="0"><?php esc_html_e('No tab', 'dev-tools'); ?></option>
                                            <?php foreach ($tabs as $tab) : ?>
                                                <option value="<?php echo esc_attr($tab->id); ?>"
                                                        <?php selected($assigned_tab_id, $tab->id); ?>
                                                        data-color="<?php echo esc_attr($tab->color); ?>">
                                                    <?php echo esc_html($tab->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <?php if ($assigned_tab_name) : ?>
                                            <div class="current-tab" style="color: <?php echo esc_attr($assigned_tab_color); ?>; font-weight: bold; margin-top: 5px;">
                                                <?php echo esc_html($assigned_tab_name); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="button button-small assign-page"
                                                data-page-id="<?php echo esc_attr($page->ID); ?>">
                                            <?php esc_html_e('Assign', 'dev-tools'); ?>
                                        </button>

                                        <?php if ($assigned_tab_id > 0) : ?>
                                            <button class="button button-small button-link-delete remove-page"
                                                    data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                    data-tab-id="<?php echo esc_attr($assigned_tab_id); ?>">
                                                <?php esc_html_e('Remove', 'dev-tools'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Notification messages -->
<div id="dtpt-messages" class="notice" style="display: none;">
    <p></p>
</div>
