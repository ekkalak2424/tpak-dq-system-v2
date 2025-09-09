<?php
/**
 * Data Management Page Template
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get data management instance
$data_manager = TPAK_Admin_Data::get_instance();
$available_statuses = $data_manager->get_available_statuses();
$bulk_actions = $data_manager->get_bulk_actions();
$user_stats = $data_manager->get_user_statistics();
$current_user = wp_get_current_user();
$roles_instance = TPAK_Roles::get_instance();
$user_role = $roles_instance->get_user_tpak_role($current_user->ID);
?>

<div class="tpak-data-management">
    
    <!-- Statistics Cards -->
    <div class="tpak-stats-cards">
        <h2><?php _e('Your Data Overview', 'tpak-dq-system'); ?></h2>
        <div class="tpak-stats-grid">
            <?php foreach ($user_stats as $status => $stat): ?>
                <div class="tpak-stat-card" data-status="<?php echo esc_attr($status); ?>">
                    <div class="stat-number"><?php echo esc_html($stat['count']); ?></div>
                    <div class="stat-label"><?php echo esc_html($stat['label']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Filters and Search -->
    <div class="tpak-data-filters">
        <div class="tpak-filter-row">
            <div class="tpak-filter-group">
                <label for="status-filter"><?php _e('Status:', 'tpak-dq-system'); ?></label>
                <select id="status-filter" name="status">
                    <option value=""><?php _e('All Statuses', 'tpak-dq-system'); ?></option>
                    <?php foreach ($available_statuses as $status => $label): ?>
                        <option value="<?php echo esc_attr($status); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="tpak-filter-group">
                <label for="search-input"><?php _e('Search:', 'tpak-dq-system'); ?></label>
                <input type="text" id="search-input" name="search" placeholder="<?php esc_attr_e('Search by Survey ID, Response ID...', 'tpak-dq-system'); ?>">
            </div>
            
            <div class="tpak-filter-group">
                <button type="button" id="filter-button" class="button button-secondary">
                    <?php _e('Filter', 'tpak-dq-system'); ?>
                </button>
                <button type="button" id="reset-filters" class="button">
                    <?php _e('Reset', 'tpak-dq-system'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Bulk Actions -->
    <div class="tpak-bulk-actions">
        <div class="alignleft actions">
            <select id="bulk-action-selector" name="action">
                <option value=""><?php _e('Bulk Actions', 'tpak-dq-system'); ?></option>
                <?php foreach ($bulk_actions as $action => $label): ?>
                    <option value="<?php echo esc_attr($action); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="bulk-action-button" class="button" disabled>
                <?php _e('Apply', 'tpak-dq-system'); ?>
            </button>
        </div>
        
        <div class="alignright">
            <span class="displaying-num" id="items-count">0 <?php _e('items', 'tpak-dq-system'); ?></span>
        </div>
        <div class="clear"></div>
    </div>
    
    <!-- Data Table -->
    <div class="tpak-data-table-container">
        <div id="loading-indicator" class="tpak-loading" style="display: none;">
            <div class="spinner is-active"></div>
            <p><?php _e('Loading data...', 'tpak-dq-system'); ?></p>
        </div>
        
        <table class="wp-list-table widefat fixed striped" id="data-table">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="select-all">
                    </td>
                    <th class="manage-column column-survey-id sortable" data-orderby="survey_id">
                        <a href="#" class="sort-link">
                            <span><?php _e('Survey ID', 'tpak-dq-system'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th class="manage-column column-response-id sortable" data-orderby="response_id">
                        <a href="#" class="sort-link">
                            <span><?php _e('Response ID', 'tpak-dq-system'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th class="manage-column column-status sortable" data-orderby="status">
                        <a href="#" class="sort-link">
                            <span><?php _e('Status', 'tpak-dq-system'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th class="manage-column column-assigned-user sortable" data-orderby="assigned_user">
                        <a href="#" class="sort-link">
                            <span><?php _e('Assigned User', 'tpak-dq-system'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th class="manage-column column-date sortable desc" data-orderby="date">
                        <a href="#" class="sort-link">
                            <span><?php _e('Created', 'tpak-dq-system'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th class="manage-column column-modified sortable" data-orderby="modified">
                        <a href="#" class="sort-link">
                            <span><?php _e('Modified', 'tpak-dq-system'); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <th class="manage-column column-actions">
                        <?php _e('Actions', 'tpak-dq-system'); ?>
                    </th>
                </tr>
            </thead>
            <tbody id="data-table-body">
                <!-- Data will be loaded via AJAX -->
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <div class="alignleft actions">
                <!-- Bulk actions repeated for bottom -->
            </div>
            <div class="tablenav-pages" id="pagination-container">
                <!-- Pagination will be loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- Data Detail Modal -->
<div id="data-detail-modal" class="tpak-modal" style="display: none;">
    <div class="tpak-modal-content">
        <div class="tpak-modal-header">
            <h2 id="modal-title"><?php _e('Survey Data Details', 'tpak-dq-system'); ?></h2>
            <button type="button" class="tpak-modal-close" aria-label="<?php esc_attr_e('Close', 'tpak-dq-system'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        
        <div class="tpak-modal-body">
            <div class="tpak-modal-loading" style="display: none;">
                <div class="spinner is-active"></div>
                <p><?php _e('Loading details...', 'tpak-dq-system'); ?></p>
            </div>
            
            <div id="modal-content" style="display: none;">
                <!-- Tabs -->
                <div class="tpak-tabs">
                    <ul class="tpak-tab-list">
                        <li><a href="#tab-overview" class="tpak-tab-link active"><?php _e('Overview', 'tpak-dq-system'); ?></a></li>
                        <li><a href="#tab-data" class="tpak-tab-link"><?php _e('Survey Data', 'tpak-dq-system'); ?></a></li>
                        <li><a href="#tab-audit" class="tpak-tab-link"><?php _e('Audit Trail', 'tpak-dq-system'); ?></a></li>
                    </ul>
                    
                    <!-- Overview Tab -->
                    <div id="tab-overview" class="tpak-tab-content active">
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Survey ID:', 'tpak-dq-system'); ?></th>
                                <td id="detail-survey-id"></td>
                            </tr>
                            <tr>
                                <th><?php _e('Response ID:', 'tpak-dq-system'); ?></th>
                                <td id="detail-response-id"></td>
                            </tr>
                            <tr>
                                <th><?php _e('Status:', 'tpak-dq-system'); ?></th>
                                <td id="detail-status"></td>
                            </tr>
                            <tr>
                                <th><?php _e('Assigned User:', 'tpak-dq-system'); ?></th>
                                <td id="detail-assigned-user"></td>
                            </tr>
                        </table>
                        
                        <!-- Workflow Actions -->
                        <div class="tpak-workflow-actions">
                            <h3><?php _e('Available Actions', 'tpak-dq-system'); ?></h3>
                            <div id="workflow-actions-container">
                                <!-- Actions will be populated via JavaScript -->
                            </div>
                            
                            <div id="action-notes" style="display: none;">
                                <label for="action-notes-input"><?php _e('Notes (optional):', 'tpak-dq-system'); ?></label>
                                <textarea id="action-notes-input" rows="3" cols="50"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Survey Data Tab -->
                    <div id="tab-data" class="tpak-tab-content">
                        <div class="tpak-data-editor">
                            <div class="editor-toolbar">
                                <button type="button" id="edit-data-button" class="button button-secondary" style="display: none;">
                                    <?php _e('Edit Data', 'tpak-dq-system'); ?>
                                </button>
                                <button type="button" id="save-data-button" class="button button-primary" style="display: none;">
                                    <?php _e('Save Changes', 'tpak-dq-system'); ?>
                                </button>
                                <button type="button" id="cancel-edit-button" class="button" style="display: none;">
                                    <?php _e('Cancel', 'tpak-dq-system'); ?>
                                </button>
                            </div>
                            
                            <div id="data-display" class="data-readonly">
                                <!-- Survey data will be displayed here -->
                            </div>
                            
                            <div id="data-editor" class="data-editable" style="display: none;">
                                <textarea id="data-editor-textarea" rows="20" cols="80"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Audit Trail Tab -->
                    <div id="tab-audit" class="tpak-tab-content">
                        <div id="audit-trail-container">
                            <!-- Audit trail will be populated via JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="tpak-modal-footer">
            <button type="button" class="button button-secondary tpak-modal-close">
                <?php _e('Close', 'tpak-dq-system'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Workflow Action Confirmation Modal -->
<div id="workflow-action-modal" class="tpak-modal" style="display: none;">
    <div class="tpak-modal-content">
        <div class="tpak-modal-header">
            <h2 id="workflow-modal-title"><?php _e('Confirm Action', 'tpak-dq-system'); ?></h2>
            <button type="button" class="tpak-modal-close" aria-label="<?php esc_attr_e('Close', 'tpak-dq-system'); ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        
        <div class="tpak-modal-body">
            <p id="workflow-confirmation-message"></p>
            
            <div class="workflow-notes">
                <label for="workflow-notes-input"><?php _e('Notes (optional):', 'tpak-dq-system'); ?></label>
                <textarea id="workflow-notes-input" rows="3" cols="50"></textarea>
            </div>
        </div>
        
        <div class="tpak-modal-footer">
            <button type="button" class="button button-secondary tpak-modal-close">
                <?php _e('Cancel', 'tpak-dq-system'); ?>
            </button>
            <button type="button" id="confirm-workflow-action" class="button button-primary">
                <?php _e('Confirm', 'tpak-dq-system'); ?>
            </button>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize data management interface
    window.TPAKDataManager = new TPAKDataManagement();
});
</script>