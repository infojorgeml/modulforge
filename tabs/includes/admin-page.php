<?php
// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission to access this page.', 'modulforge'));
}

global $wpdb;

// All tabs.
$tabs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}modulforge_page_tabs ORDER BY position ASC, name ASC"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct query on the plugin's own custom table; not cacheable. Table name comes from $wpdb->prefix; no value params.

// All pages.
$pages = get_pages(array(
    'post_status' => array('publish', 'draft', 'private'),
    'number'      => 0,
));

// Page-tab relations + per-tab counts (computed in PHP — no per-tab COUNT query).
$relations      = array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
$counts         = array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
$relations_data = $wpdb->get_results("SELECT page_id, tab_id FROM {$wpdb->prefix}modulforge_page_tab_relations"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Direct query on the plugin's own custom table; not cacheable. Table name comes from $wpdb->prefix; no value params. Local variable inside an included template; not global scope.
foreach ($relations_data as $relation) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
    $relations[$relation->page_id] = $relation->tab_id; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
    $counts[$relation->tab_id]     = isset($counts[$relation->tab_id]) ? $counts[$relation->tab_id] + 1 : 1; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Manage Page Tabs', 'modulforge'); ?></h1>

    <div class="dtpt-admin-container">
        <!-- Tabs section -->
        <div class="dtpt-tabs-section">
            <h2><?php esc_html_e('Tabs', 'modulforge'); ?></h2>

            <div class="dtpt-add-tab-form">
                <h3><?php esc_html_e('Create New Tab', 'modulforge'); ?></h3>
                <form id="dtpt-tab-form">
                    <input type="hidden" id="tab-id" name="tab_id" value="0">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="tab-name"><?php esc_html_e('Name', 'modulforge'); ?> *</label>
                            </th>
                            <td>
                                <input type="text" id="tab-name" name="name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tab-description"><?php esc_html_e('Description', 'modulforge'); ?></label>
                            </th>
                            <td>
                                <textarea id="tab-description" name="description" class="regular-text" rows="3"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tab-color"><?php esc_html_e('Color', 'modulforge'); ?></label>
                            </th>
                            <td>
                                <input type="color" id="tab-color" name="color" value="#0073aa">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tab-position"><?php esc_html_e('Position', 'modulforge'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="tab-position" name="position" value="0" min="0">
                                <p class="description"><?php esc_html_e('Display order (0 = first position)', 'modulforge'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <span class="button-text"><?php esc_html_e('Create Tab', 'modulforge'); ?></span>
                            <span class="spinner"></span>
                        </button>
                        <button type="button" id="cancel-edit" class="button" style="display: none;">
                            <?php esc_html_e('Cancel', 'modulforge'); ?>
                        </button>
                    </p>
                </form>
            </div>

            <!-- Existing tabs list -->
            <div class="dtpt-tabs-list">
                <h3><?php esc_html_e('Existing Tabs', 'modulforge'); ?></h3>

                <?php if (empty($tabs)) : ?>
                    <p><?php esc_html_e('No tabs created yet.', 'modulforge'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'modulforge'); ?></th>
                                <th><?php esc_html_e('Description', 'modulforge'); ?></th>
                                <th><?php esc_html_e('Color', 'modulforge'); ?></th>
                                <th><?php esc_html_e('Pages', 'modulforge'); ?></th>
                                <th><?php esc_html_e('Position', 'modulforge'); ?></th>
                                <th><?php esc_html_e('Actions', 'modulforge'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tabs as $tab) : ?>
                                <?php $page_count = isset($counts[$tab->id]) ? $counts[$tab->id] : 0; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope. ?>
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
                                            <?php esc_html_e('Edit', 'modulforge'); ?>
                                        </button>
                                        <button class="button button-small button-link-delete delete-tab"
                                                data-tab-id="<?php echo esc_attr($tab->id); ?>">
                                            <?php esc_html_e('Delete', 'modulforge'); ?>
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
            <h2><?php esc_html_e('Assign Pages to Tabs', 'modulforge'); ?></h2>

            <?php if (empty($tabs)) : ?>
                <p><?php esc_html_e('You must create at least one tab before you can assign pages.', 'modulforge'); ?></p>
            <?php elseif (empty($pages)) : ?>
                <p><?php esc_html_e('No pages available to assign.', 'modulforge'); ?></p>
            <?php else : ?>
                <div class="dtpt-pages-assignment">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Page', 'modulforge'); ?></th>
                                <th><?php esc_html_e('Status', 'modulforge'); ?></th>
                                <th><?php esc_html_e('Assigned Tab', 'modulforge'); ?></th>
                                <th><?php esc_html_e('Actions', 'modulforge'); ?></th>
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
                                                    <?php esc_html_e('View', 'modulforge'); ?>
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
                                        $assigned_tab_id    = isset($relations[$page->ID]) ? $relations[$page->ID] : 0; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
                                        $assigned_tab_name  = ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
                                        $assigned_tab_color = '#666'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.

                                        if ($assigned_tab_id > 0) {
                                            foreach ($tabs as $tab) {
                                                if ($tab->id == $assigned_tab_id) {
                                                    $assigned_tab_name  = $tab->name; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
                                                    $assigned_tab_color = $tab->color; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variable inside an included template; not global scope.
                                                    break;
                                                }
                                            }
                                        }
                                        ?>

                                        <select class="page-tab-select" data-page-id="<?php echo esc_attr($page->ID); ?>">
                                            <option value="0"><?php esc_html_e('No tab', 'modulforge'); ?></option>
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
                                            <?php esc_html_e('Assign', 'modulforge'); ?>
                                        </button>

                                        <?php if ($assigned_tab_id > 0) : ?>
                                            <button class="button button-small button-link-delete remove-page"
                                                    data-page-id="<?php echo esc_attr($page->ID); ?>"
                                                    data-tab-id="<?php echo esc_attr($assigned_tab_id); ?>">
                                                <?php esc_html_e('Remove', 'modulforge'); ?>
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
