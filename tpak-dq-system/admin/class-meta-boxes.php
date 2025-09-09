<?php
/**
 * Meta Boxes Class
 * 
 * Handles meta boxes for survey data editing
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Meta Boxes Class
 */
class TPAK_Meta_Boxes {
    
    /**
     * Single instance
     * 
     * @var TPAK_Meta_Boxes
     */
    private static $instance = null;
    
    /**
     * Get instance
     * 
     * @return TPAK_Meta_Boxes
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'tpak_survey_data_details',
            __('Survey Data Details', 'tpak-dq-system'),
            array($this, 'render_survey_data_meta_box'),
            'tpak_survey_data',
            'normal',
            'high'
        );
        
        add_meta_box(
            'tpak_workflow_status',
            __('Workflow Status & Actions', 'tpak-dq-system'),
            array($this, 'render_workflow_meta_box'),
            'tpak_survey_data',
            'side',
            'high'
        );
        
        add_meta_box(
            'tpak_audit_trail',
            __('Audit Trail', 'tpak-dq-system'),
            array($this, 'render_audit_trail_meta_box'),
            'tpak_survey_data',
            'normal',
            'low'
        );
    }
    
    /**
     * Render survey data meta box
     * 
     * @param WP_Post $post
     */
    public function render_survey_data_meta_box($post) {
        // Add nonce field
        wp_nonce_field('tpak_meta_boxes', 'tpak_meta_boxes_nonce');
        
        // Load survey data
        $survey_data = new TPAK_Survey_Data($post);
        $data = $survey_data->get_data();
        $survey_id = $survey_data->get_survey_id();
        $response_id = $survey_data->get_response_id();
        $import_date = get_post_meta($post->ID, '_tpak_import_date', true);
        
        // Check if user can edit
        $can_edit = $survey_data->can_user_edit();
        
        ?>
        <div class="tpak-survey-data-meta-box">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="tpak_survey_id"><?php _e('Survey ID', 'tpak-dq-system'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="tpak_survey_id" 
                               name="tpak_survey_id" 
                               value="<?php echo esc_attr($survey_id); ?>" 
                               class="regular-text" 
                               readonly />
                        <p class="description"><?php _e('LimeSurvey Survey ID (read-only)', 'tpak-dq-system'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="tpak_response_id"><?php _e('Response ID', 'tpak-dq-system'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="tpak_response_id" 
                               name="tpak_response_id" 
                               value="<?php echo esc_attr($response_id); ?>" 
                               class="regular-text" 
                               readonly />
                        <p class="description"><?php _e('LimeSurvey Response ID (read-only)', 'tpak-dq-system'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="tpak_import_date"><?php _e('Import Date', 'tpak-dq-system'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="tpak_import_date" 
                               name="tpak_import_date" 
                               value="<?php echo esc_attr($import_date); ?>" 
                               class="regular-text" 
                               readonly />
                        <p class="description"><?php _e('Date when data was imported from LimeSurvey', 'tpak-dq-system'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="tpak_survey_data"><?php _e('Survey Response Data', 'tpak-dq-system'); ?></label>
                    </th>
                    <td>
                        <?php if ($can_edit): ?>
                            <textarea id="tpak_survey_data" 
                                      name="tpak_survey_data" 
                                      rows="15" 
                                      cols="50" 
                                      class="large-text code"><?php echo esc_textarea(wp_json_encode($data, JSON_PRETTY_PRINT)); ?></textarea>
                            <p class="description"><?php _e('Survey response data in JSON format. Edit carefully to maintain data integrity.', 'tpak-dq-system'); ?></p>
                        <?php else: ?>
                            <div class="tpak-readonly-data">
                                <pre class="tpak-json-display"><?php echo esc_html(wp_json_encode($data, JSON_PRETTY_PRINT)); ?></pre>
                            </div>
                            <p class="description"><?php _e('Survey response data (read-only for your role)', 'tpak-dq-system'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <style>
        .tpak-survey-data-meta-box .form-table th {
            width: 200px;
            vertical-align: top;
            padding-top: 15px;
        }
        
        .tpak-readonly-data {
            background: #f9f9f9;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 3px;
        }
        
        .tpak-json-display {
            margin: 0;
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
            line-height: 1.4;
            max-height: 300px;
            overflow-y: auto;
        }
        </style>
        <?php
    }
    
    /**
     * Render workflow meta box
     * 
     * @param WP_Post $post
     */
    public function render_workflow_meta_box($post) {
        $survey_data = new TPAK_Survey_Data($post);
        $workflow = TPAK_Workflow::get_instance();
        
        $current_status = $survey_data->get_status();
        $assigned_user_id = $survey_data->get_assigned_user();
        $workflow_states = $workflow->get_workflow_states();
        $available_actions = $workflow->get_available_actions($post->ID);
        
        // Get assigned user info
        $assigned_user = $assigned_user_id ? get_user_by('id', $assigned_user_id) : null;
        
        ?>
        <div class="tpak-workflow-meta-box">
            <div class="tpak-current-status">
                <h4><?php _e('Current Status', 'tpak-dq-system'); ?></h4>
                <div class="tpak-status-badge" style="background-color: <?php echo esc_attr($workflow_states[$current_status]['color'] ?? '#666'); ?>">
                    <?php echo esc_html($workflow_states[$current_status]['label'] ?? $current_status); ?>
                </div>
                <p class="description">
                    <?php echo esc_html($workflow_states[$current_status]['description'] ?? ''); ?>
                </p>
            </div>
            
            <div class="tpak-assigned-user">
                <h4><?php _e('Assigned User', 'tpak-dq-system'); ?></h4>
                <p>
                    <?php if ($assigned_user): ?>
                        <strong><?php echo esc_html($assigned_user->display_name); ?></strong><br>
                        <small><?php echo esc_html($assigned_user->user_email); ?></small>
                    <?php else: ?>
                        <em><?php _e('No user assigned', 'tpak-dq-system'); ?></em>
                    <?php endif; ?>
                </p>
            </div>
            
            <?php if (!empty($available_actions)): ?>
            <div class="tpak-workflow-actions">
                <h4><?php _e('Available Actions', 'tpak-dq-system'); ?></h4>
                
                <?php foreach ($available_actions as $action => $transition): ?>
                <div class="tpak-action-item">
                    <button type="button" 
                            class="button tpak-workflow-action-btn" 
                            data-action="<?php echo esc_attr($action); ?>"
                            data-post-id="<?php echo esc_attr($post->ID); ?>"
                            data-requires-note="<?php echo esc_attr($transition['requires_note'] ? '1' : '0'); ?>">
                        <?php echo esc_html($transition['label']); ?>
                    </button>
                </div>
                <?php endforeach; ?>
                
                <div id="tpak-action-notes" style="display: none; margin-top: 10px;">
                    <label for="tpak-workflow-notes"><?php _e('Notes (required)', 'tpak-dq-system'); ?></label>
                    <textarea id="tpak-workflow-notes" 
                              rows="3" 
                              cols="30" 
                              class="widefat"
                              placeholder="<?php esc_attr_e('Enter notes for this action...', 'tpak-dq-system'); ?>"></textarea>
                </div>
            </div>
            <?php else: ?>
            <div class="tpak-no-actions">
                <p><em><?php _e('No actions available for your role at this status.', 'tpak-dq-system'); ?></em></p>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .tpak-workflow-meta-box h4 {
            margin: 15px 0 8px 0;
            font-size: 13px;
            text-transform: uppercase;
            color: #666;
        }
        
        .tpak-workflow-meta-box h4:first-child {
            margin-top: 0;
        }
        
        .tpak-status-badge {
            display: inline-block;
            padding: 4px 8px;
            color: white;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .tpak-action-item {
            margin-bottom: 8px;
        }
        
        .tpak-workflow-action-btn {
            width: 100%;
            text-align: left;
        }
        
        .tpak-no-actions {
            color: #666;
            font-style: italic;
        }
        </style>
        <?php
    }
    
    /**
     * Render audit trail meta box
     * 
     * @param WP_Post $post
     */
    public function render_audit_trail_meta_box($post) {
        $survey_data = new TPAK_Survey_Data($post);
        $audit_trail = $survey_data->get_formatted_audit_trail();
        
        ?>
        <div class="tpak-audit-trail-meta-box">
            <?php if (!empty($audit_trail)): ?>
                <div class="tpak-audit-entries">
                    <?php foreach (array_reverse($audit_trail) as $entry): ?>
                    <div class="tpak-audit-entry">
                        <div class="tpak-audit-header">
                            <span class="tpak-audit-action"><?php echo esc_html($this->format_audit_action($entry['action'])); ?></span>
                            <span class="tpak-audit-user"><?php echo esc_html($entry['user_name']); ?></span>
                            <span class="tpak-audit-date"><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $entry['timestamp'])); ?></span>
                        </div>
                        
                        <?php if (!empty($entry['old_value']) || !empty($entry['new_value'])): ?>
                        <div class="tpak-audit-changes">
                            <?php if (!empty($entry['old_value'])): ?>
                                <span class="tpak-audit-old"><?php _e('From:', 'tpak-dq-system'); ?> <code><?php echo esc_html($entry['old_value']); ?></code></span>
                            <?php endif; ?>
                            <?php if (!empty($entry['new_value'])): ?>
                                <span class="tpak-audit-new"><?php _e('To:', 'tpak-dq-system'); ?> <code><?php echo esc_html($entry['new_value']); ?></code></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($entry['notes'])): ?>
                        <div class="tpak-audit-notes">
                            <strong><?php _e('Notes:', 'tpak-dq-system'); ?></strong> <?php echo esc_html($entry['notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="tpak-no-audit-entries">
                    <em><?php _e('No audit trail entries found.', 'tpak-dq-system'); ?></em>
                </p>
            <?php endif; ?>
        </div>
        
        <style>
        .tpak-audit-trail-meta-box {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .tpak-audit-entry {
            border-left: 3px solid #0073aa;
            padding: 10px 15px;
            margin-bottom: 15px;
            background: #f9f9f9;
            border-radius: 0 3px 3px 0;
        }
        
        .tpak-audit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .tpak-audit-action {
            color: #0073aa;
            text-transform: capitalize;
        }
        
        .tpak-audit-user {
            color: #666;
            font-size: 12px;
        }
        
        .tpak-audit-date {
            color: #999;
            font-size: 11px;
            font-weight: normal;
        }
        
        .tpak-audit-changes {
            margin: 5px 0;
            font-size: 12px;
        }
        
        .tpak-audit-old,
        .tpak-audit-new {
            display: block;
            margin: 2px 0;
        }
        
        .tpak-audit-old code {
            background: #ffebee;
            color: #c62828;
        }
        
        .tpak-audit-new code {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .tpak-audit-notes {
            margin-top: 8px;
            font-size: 12px;
            color: #555;
        }
        
        .tpak-no-audit-entries {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 20px;
        }
        </style>
        <?php
    }
    
    /**
     * Format audit action for display
     * 
     * @param string $action
     * @return string
     */
    private function format_audit_action($action) {
        $action_labels = array(
            'imported' => __('Data Imported', 'tpak-dq-system'),
            'status_change' => __('Status Changed', 'tpak-dq-system'),
            'user_assignment' => __('User Assigned', 'tpak-dq-system'),
            'data_edit' => __('Data Edited', 'tpak-dq-system'),
            'approve_to_supervisor' => __('Approved to Supervisor', 'tpak-dq-system'),
            'approve_to_examiner' => __('Approved to Examiner', 'tpak-dq-system'),
            'reject_to_interviewer' => __('Rejected to Interviewer', 'tpak-dq-system'),
            'reject_to_supervisor' => __('Rejected to Supervisor', 'tpak-dq-system'),
            'apply_sampling' => __('Sampling Applied', 'tpak-dq-system'),
            'final_approval' => __('Final Approval', 'tpak-dq-system'),
            'sampling' => __('Sampling Gate', 'tpak-dq-system'),
        );
        
        return $action_labels[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
    
    /**
     * Save meta boxes
     * 
     * @param int     $post_id
     * @param WP_Post $post
     */
    public function save_meta_boxes($post_id, $post) {
        // Check if this is the right post type
        if ($post->post_type !== 'tpak_survey_data') {
            return;
        }
        
        // Check if user can edit this post
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['tpak_meta_boxes_nonce']) || !wp_verify_nonce($_POST['tpak_meta_boxes_nonce'], 'tpak_meta_boxes')) {
            return;
        }
        
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Load survey data
        $survey_data = new TPAK_Survey_Data($post);
        
        // Check if user can edit this data based on workflow status
        if (!$survey_data->can_user_edit()) {
            return;
        }
        
        // Validate and save survey data
        if (isset($_POST['tpak_survey_data'])) {
            $json_data = stripslashes($_POST['tpak_survey_data']);
            
            // Validate JSON
            $validation_result = $this->validate_survey_data($json_data);
            if (is_wp_error($validation_result)) {
                // Store error for display
                set_transient('tpak_meta_box_error_' . $post_id, $validation_result->get_error_message(), 30);
                return;
            }
            
            // Decode and save
            $decoded_data = json_decode($json_data, true);
            if ($decoded_data !== null) {
                $old_data = $survey_data->get_data();
                $survey_data->set_data($decoded_data);
                
                // Add audit entry for data change
                if ($old_data !== $decoded_data) {
                    $survey_data->add_audit_entry('data_edit', 
                        wp_json_encode($old_data), 
                        wp_json_encode($decoded_data), 
                        __('Data edited via admin interface', 'tpak-dq-system')
                    );
                }
                
                // Save the survey data
                $result = $survey_data->save();
                
                if (is_wp_error($result)) {
                    set_transient('tpak_meta_box_error_' . $post_id, $result->get_error_message(), 30);
                } else {
                    set_transient('tpak_meta_box_success_' . $post_id, __('Survey data updated successfully.', 'tpak-dq-system'), 30);
                }
            }
        }
    }
    
    /**
     * Validate survey data JSON
     * 
     * @param string $json_data
     * @return bool|WP_Error
     */
    private function validate_survey_data($json_data) {
        // Check if empty
        if (empty($json_data)) {
            return new WP_Error('empty_data', __('Survey data cannot be empty.', 'tpak-dq-system'));
        }
        
        // Check JSON validity
        $decoded = json_decode($json_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', sprintf(
                __('Invalid JSON format: %s', 'tpak-dq-system'),
                json_last_error_msg()
            ));
        }
        
        // Check if it's an array/object
        if (!is_array($decoded)) {
            return new WP_Error('invalid_format', __('Survey data must be a JSON object or array.', 'tpak-dq-system'));
        }
        
        // Check size limit (1MB)
        if (strlen($json_data) > 1048576) {
            return new WP_Error('data_too_large', __('Survey data is too large. Maximum size is 1MB.', 'tpak-dq-system'));
        }
        
        // Additional validation using the validator class
        $validator = TPAK_Validator::get_instance();
        return $validator->validate_survey_data_structure($decoded);
    }
    

    
    /**
     * Enqueue scripts and styles
     * 
     * @param string $hook
     */
    public function enqueue_scripts($hook) {
        global $post;
        
        // Only load on survey data edit pages
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        if (!$post || $post->post_type !== 'tpak_survey_data') {
            return;
        }
        
        // Enqueue admin scripts and styles
        wp_enqueue_script(
            'tpak-meta-boxes',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/meta-boxes.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_enqueue_style(
            'tpak-meta-boxes',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/meta-boxes.css',
            array(),
            '1.0.0'
        );
        
        // Localize script
        wp_localize_script('tpak-meta-boxes', 'tpakMetaBoxes', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpak_workflow_action'),
            'strings' => array(
                'confirmAction' => __('Are you sure you want to perform this action?', 'tpak-dq-system'),
                'notesRequired' => __('Notes are required for this action.', 'tpak-dq-system'),
                'processing' => __('Processing...', 'tpak-dq-system'),
                'error' => __('An error occurred. Please try again.', 'tpak-dq-system'),
                'success' => __('Action completed successfully.', 'tpak-dq-system'),
            )
        ));
        
        // Display any stored messages
        $this->display_admin_notices();
    }
    
    /**
     * Display admin notices for meta box operations
     */
    private function display_admin_notices() {
        global $post;
        
        if (!$post || $post->post_type !== 'tpak_survey_data') {
            return;
        }
        
        // Check for error messages
        $error = get_transient('tpak_meta_box_error_' . $post->ID);
        if ($error) {
            delete_transient('tpak_meta_box_error_' . $post->ID);
            add_action('admin_notices', function() use ($error) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
            });
        }
        
        // Check for success messages
        $success = get_transient('tpak_meta_box_success_' . $post->ID);
        if ($success) {
            delete_transient('tpak_meta_box_success_' . $post->ID);
            add_action('admin_notices', function() use ($success) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success) . '</p></div>';
            });
        }
    }
}