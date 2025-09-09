<?php
/**
 * Admin Data Management Class
 * 
 * Handles data listing, filtering, and management interface
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Admin Data Class
 */
class TPAK_Admin_Data {
    
    /**
     * Single instance
     * 
     * @var TPAK_Admin_Data
     */
    private static $instance = null;
    
    /**
     * Items per page
     * 
     * @var int
     */
    private $per_page = 20;
    
    /**
     * Get instance
     * 
     * @return TPAK_Admin_Data
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
        add_action('wp_ajax_tpak_load_data_table', array($this, 'ajax_load_data_table'));
        add_action('wp_ajax_tpak_get_data_details', array($this, 'ajax_get_data_details'));
        add_action('wp_ajax_tpak_perform_workflow_action', array($this, 'ajax_perform_workflow_action'));
        add_action('wp_ajax_tpak_bulk_action', array($this, 'ajax_bulk_action'));
        add_action('wp_ajax_tpak_update_data', array($this, 'ajax_update_data'));
        add_action('admin_post_tpak_data_action', array($this, 'handle_data_action_secure'));
    }
    
    /**
     * Get data list with role-based filtering
     * 
     * @param array $args
     * @return array
     */
    public function get_data_list($args = array()) {
        $defaults = array(
            'page'     => 1,
            'per_page' => $this->per_page,
            'status'   => '',
            'search'   => '',
            'orderby'  => 'date',
            'order'    => 'DESC',
            'user_id'  => get_current_user_id(),
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build query arguments
        $query_args = array(
            'post_type'      => 'tpak_survey_data',
            'post_status'    => 'publish',
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['page'],
            'orderby'        => $this->get_orderby_field($args['orderby']),
            'order'          => $args['order'],
            'meta_query'     => array(),
        );
        
        // Apply role-based filtering
        $this->apply_role_based_filtering($query_args, $args['user_id']);
        
        // Apply status filter
        if (!empty($args['status'])) {
            $query_args['meta_query'][] = array(
                'key'   => '_tpak_workflow_status',
                'value' => sanitize_text_field($args['status']),
            );
        }
        
        // Apply search filter
        if (!empty($args['search'])) {
            $query_args['s'] = sanitize_text_field($args['search']);
        }
        
        // Execute query
        $query = new WP_Query($query_args);
        
        // Format results
        $data = array();
        foreach ($query->posts as $post) {
            $survey_data = new TPAK_Survey_Data($post);
            $data[] = $this->format_data_item($survey_data);
        }
        
        return array(
            'data'        => $data,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
            'current_page' => $args['page'],
        );
    }
    
    /**
     * Apply role-based filtering to query
     * 
     * @param array $query_args
     * @param int   $user_id
     */
    private function apply_role_based_filtering(&$query_args, $user_id) {
        $roles_instance = TPAK_Roles::get_instance();
        $user_role = $roles_instance->get_user_tpak_role($user_id);
        
        // Administrators can see all data
        if (current_user_can('tpak_view_all_data')) {
            return;
        }
        
        // Apply role-based status filtering
        $allowed_statuses = array();
        
        switch ($user_role) {
            case 'tpak_interviewer_a':
                $allowed_statuses = array('pending_a', 'rejected_by_b');
                break;
                
            case 'tpak_supervisor_b':
                $allowed_statuses = array('pending_b');
                break;
                
            case 'tpak_examiner_c':
                $allowed_statuses = array('pending_c', 'rejected_by_c');
                break;
        }
        
        if (!empty($allowed_statuses)) {
            $query_args['meta_query'][] = array(
                'key'     => '_tpak_workflow_status',
                'value'   => $allowed_statuses,
                'compare' => 'IN',
            );
        } else {
            // No access - return empty results
            $query_args['post__in'] = array(0);
        }
    }
    
    /**
     * Get orderby field for query
     * 
     * @param string $orderby
     * @return string
     */
    private function get_orderby_field($orderby) {
        switch ($orderby) {
            case 'title':
                return 'title';
            case 'status':
                return 'meta_value';
            case 'assigned_user':
                return 'meta_value_num';
            case 'modified':
                return 'modified';
            default:
                return 'date';
        }
    }
    
    /**
     * Format data item for display
     * 
     * @param TPAK_Survey_Data $survey_data
     * @return array
     */
    private function format_data_item($survey_data) {
        $assigned_user = get_user_by('id', $survey_data->get_assigned_user());
        $post_types = TPAK_Post_Types::get_instance();
        $statuses = $post_types->get_workflow_statuses();
        
        return array(
            'id'            => $survey_data->get_id(),
            'survey_id'     => $survey_data->get_survey_id(),
            'response_id'   => $survey_data->get_response_id(),
            'status'        => $survey_data->get_status(),
            'status_label'  => isset($statuses[$survey_data->get_status()]) ? $statuses[$survey_data->get_status()] : $survey_data->get_status(),
            'assigned_user' => $assigned_user ? $assigned_user->display_name : __('Unassigned', 'tpak-dq-system'),
            'created_date'  => $survey_data->get_created_date(),
            'last_modified' => $survey_data->get_last_modified(),
            'can_edit'      => $survey_data->can_user_edit(),
            'actions'       => $this->get_available_actions($survey_data),
        );
    }
    
    /**
     * Get available actions for data item
     * 
     * @param TPAK_Survey_Data $survey_data
     * @return array
     */
    private function get_available_actions($survey_data) {
        $actions = array();
        $status = $survey_data->get_status();
        $user_id = get_current_user_id();
        $roles_instance = TPAK_Roles::get_instance();
        
        // View action - always available if user can access
        if ($roles_instance->can_user_access_status($user_id, $status)) {
            $actions['view'] = array(
                'label' => __('View', 'tpak-dq-system'),
                'class' => 'button-secondary',
            );
        }
        
        // Edit action - only for editable statuses
        if ($survey_data->can_user_edit()) {
            $actions['edit'] = array(
                'label' => __('Edit', 'tpak-dq-system'),
                'class' => 'button-primary',
            );
        }
        
        // Workflow actions based on status and role
        switch ($status) {
            case 'pending_a':
                if (current_user_can('tpak_approve_to_supervisor')) {
                    $actions['approve_to_b'] = array(
                        'label' => __('Approve to Supervisor', 'tpak-dq-system'),
                        'class' => 'button-primary',
                    );
                }
                break;
                
            case 'pending_b':
                if (current_user_can('tpak_approve_to_examiner')) {
                    $actions['approve_to_c'] = array(
                        'label' => __('Send to Examiner', 'tpak-dq-system'),
                        'class' => 'button-primary',
                    );
                    $actions['finalize_sampling'] = array(
                        'label' => __('Apply Sampling Gate', 'tpak-dq-system'),
                        'class' => 'button-secondary',
                    );
                }
                if (current_user_can('tpak_reject_to_interviewer')) {
                    $actions['reject_to_a'] = array(
                        'label' => __('Reject to Interviewer', 'tpak-dq-system'),
                        'class' => 'button-link-delete',
                    );
                }
                break;
                
            case 'pending_c':
                if (current_user_can('tpak_final_approval')) {
                    $actions['finalize'] = array(
                        'label' => __('Finalize', 'tpak-dq-system'),
                        'class' => 'button-primary',
                    );
                }
                if (current_user_can('tpak_reject_to_supervisor')) {
                    $actions['reject_to_b'] = array(
                        'label' => __('Reject to Supervisor', 'tpak-dq-system'),
                        'class' => 'button-link-delete',
                    );
                }
                break;
                
            case 'rejected_by_b':
                if (current_user_can('tpak_approve_to_supervisor')) {
                    $actions['resubmit_to_b'] = array(
                        'label' => __('Resubmit to Supervisor', 'tpak-dq-system'),
                        'class' => 'button-primary',
                    );
                }
                break;
                
            case 'rejected_by_c':
                if (current_user_can('tpak_approve_to_examiner')) {
                    $actions['resubmit_to_c'] = array(
                        'label' => __('Resubmit to Examiner', 'tpak-dq-system'),
                        'class' => 'button-primary',
                    );
                }
                break;
        }
        
        return $actions;
    }
    
    /**
     * Get available statuses for current user
     * 
     * @return array
     */
    public function get_available_statuses() {
        $post_types = TPAK_Post_Types::get_instance();
        $all_statuses = $post_types->get_workflow_statuses();
        $user_id = get_current_user_id();
        $roles_instance = TPAK_Roles::get_instance();
        
        // Administrators can see all statuses
        if (current_user_can('tpak_view_all_data')) {
            return $all_statuses;
        }
        
        // Filter based on user role
        $user_role = $roles_instance->get_user_tpak_role($user_id);
        $available_statuses = array();
        
        foreach ($all_statuses as $status => $label) {
            if ($roles_instance->can_user_access_status($user_id, $status)) {
                $available_statuses[$status] = $label;
            }
        }
        
        return $available_statuses;
    }
    
    /**
     * Get bulk actions for current user
     * 
     * @return array
     */
    public function get_bulk_actions() {
        $actions = array();
        
        // Export action - available to all users
        $actions['export'] = __('Export Selected', 'tpak-dq-system');
        
        // Admin-only actions
        if (current_user_can('tpak_manage_settings')) {
            $actions['delete'] = __('Delete Selected', 'tpak-dq-system');
            $actions['reassign'] = __('Reassign Selected', 'tpak-dq-system');
        }
        
        // Role-specific bulk actions
        if (current_user_can('tpak_approve_to_supervisor')) {
            $actions['bulk_approve_to_b'] = __('Bulk Approve to Supervisor', 'tpak-dq-system');
        }
        
        if (current_user_can('tpak_approve_to_examiner')) {
            $actions['bulk_approve_to_c'] = __('Bulk Send to Examiner', 'tpak-dq-system');
            $actions['bulk_apply_sampling'] = __('Bulk Apply Sampling Gate', 'tpak-dq-system');
        }
        
        if (current_user_can('tpak_final_approval')) {
            $actions['bulk_finalize'] = __('Bulk Finalize', 'tpak-dq-system');
        }
        
        return $actions;
    }
    
    /**
     * AJAX handler for loading data table
     */
    public function ajax_load_data_table() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_admin_ajax')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        // Check permissions
        if (!current_user_can('tpak_view_assigned_data')) {
            wp_die(__('Insufficient permissions.', 'tpak-dq-system'));
        }
        
        $args = array(
            'page'    => absint($_POST['page'] ?? 1),
            'status'  => sanitize_text_field($_POST['status'] ?? ''),
            'search'  => sanitize_text_field($_POST['search'] ?? ''),
            'orderby' => sanitize_text_field($_POST['orderby'] ?? 'date'),
            'order'   => sanitize_text_field($_POST['order'] ?? 'DESC'),
        );
        
        $result = $this->get_data_list($args);
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX handler for getting data details
     */
    public function ajax_get_data_details() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_admin_ajax')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        $data_id = absint($_POST['data_id'] ?? 0);
        
        if (!$data_id) {
            wp_send_json_error(__('Invalid data ID.', 'tpak-dq-system'));
        }
        
        $survey_data = new TPAK_Survey_Data($data_id);
        
        if (!$survey_data->get_id()) {
            wp_send_json_error(__('Data not found.', 'tpak-dq-system'));
        }
        
        // Check permissions
        $roles_instance = TPAK_Roles::get_instance();
        if (!$roles_instance->can_user_access_status(get_current_user_id(), $survey_data->get_status())) {
            wp_send_json_error(__('Insufficient permissions.', 'tpak-dq-system'));
        }
        
        $result = array(
            'id'            => $survey_data->get_id(),
            'survey_id'     => $survey_data->get_survey_id(),
            'response_id'   => $survey_data->get_response_id(),
            'status'        => $survey_data->get_status(),
            'data'          => $survey_data->get_data(),
            'audit_trail'   => $survey_data->get_formatted_audit_trail(),
            'can_edit'      => $survey_data->can_user_edit(),
            'actions'       => $this->get_available_actions($survey_data),
        );
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX handler for performing workflow actions
     */
    public function ajax_perform_workflow_action() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_admin_ajax')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        $data_id = absint($_POST['data_id'] ?? 0);
        $action = sanitize_text_field($_POST['action'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        if (!$data_id || !$action) {
            wp_send_json_error(__('Invalid parameters.', 'tpak-dq-system'));
        }
        
        $survey_data = new TPAK_Survey_Data($data_id);
        
        if (!$survey_data->get_id()) {
            wp_send_json_error(__('Data not found.', 'tpak-dq-system'));
        }
        
        // Perform workflow action using workflow engine
        $workflow = TPAK_Workflow::get_instance();
        $result = $workflow->perform_action($survey_data, $action, $notes);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Action performed successfully.', 'tpak-dq-system'),
            'new_status' => $survey_data->get_status(),
        ));
    }
    
    /**
     * AJAX handler for bulk actions
     */
    public function ajax_bulk_action() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_admin_ajax')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        $action = sanitize_text_field($_POST['action'] ?? '');
        $data_ids = array_map('absint', $_POST['data_ids'] ?? array());
        
        if (!$action || empty($data_ids)) {
            wp_send_json_error(__('Invalid parameters.', 'tpak-dq-system'));
        }
        
        $results = array(
            'success' => 0,
            'failed'  => 0,
            'messages' => array(),
        );
        
        foreach ($data_ids as $data_id) {
            $survey_data = new TPAK_Survey_Data($data_id);
            
            if (!$survey_data->get_id()) {
                $results['failed']++;
                continue;
            }
            
            $result = $this->perform_bulk_action($survey_data, $action);
            
            if (is_wp_error($result)) {
                $results['failed']++;
                $results['messages'][] = sprintf(
                    __('ID %d: %s', 'tpak-dq-system'),
                    $data_id,
                    $result->get_error_message()
                );
            } else {
                $results['success']++;
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Perform bulk action on survey data
     * 
     * @param TPAK_Survey_Data $survey_data
     * @param string           $action
     * @return bool|WP_Error
     */
    private function perform_bulk_action($survey_data, $action) {
        switch ($action) {
            case 'delete':
                if (!current_user_can('tpak_manage_settings')) {
                    return new WP_Error('permission_denied', __('Insufficient permissions.', 'tpak-dq-system'));
                }
                return $survey_data->delete();
                
            case 'export':
                // Export will be handled separately
                return true;
                
            case 'bulk_approve_to_b':
            case 'bulk_approve_to_c':
            case 'bulk_apply_sampling':
            case 'bulk_finalize':
                $workflow = TPAK_Workflow::get_instance();
                return $workflow->perform_bulk_action($survey_data, $action);
                
            default:
                return new WP_Error('invalid_action', __('Invalid action.', 'tpak-dq-system'));
        }
    }
    
    /**
     * AJAX handler for updating data
     */
    public function ajax_update_data() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_admin_ajax')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        $data_id = absint($_POST['data_id'] ?? 0);
        $survey_data_json = wp_unslash($_POST['survey_data'] ?? '');
        
        if (!$data_id) {
            wp_send_json_error(__('Invalid data ID.', 'tpak-dq-system'));
        }
        
        $survey_data = new TPAK_Survey_Data($data_id);
        
        if (!$survey_data->get_id()) {
            wp_send_json_error(__('Data not found.', 'tpak-dq-system'));
        }
        
        // Check permissions
        if (!$survey_data->can_user_edit()) {
            wp_send_json_error(__('Insufficient permissions to edit this data.', 'tpak-dq-system'));
        }
        
        // Validate and update data
        $validator = TPAK_Validator::get_instance();
        $validation_result = $validator->validate_survey_data($survey_data_json);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        $new_data = json_decode($survey_data_json, true);
        $old_data = $survey_data->get_data();
        
        $survey_data->set_data($new_data);
        $survey_data->add_audit_entry('data_edit', $old_data, $new_data, 'Data updated via admin interface');
        
        $result = $survey_data->save();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(array(
            'message' => __('Data updated successfully.', 'tpak-dq-system'),
        ));
    }
    
    /**
     * Get statistics for current user
     * 
     * @return array
     */
    public function get_user_statistics() {
        $user_id = get_current_user_id();
        $roles_instance = TPAK_Roles::get_instance();
        $user_role = $roles_instance->get_user_tpak_role($user_id);
        
        $stats = array();
        
        // Get counts for each status the user can access
        $available_statuses = $this->get_available_statuses();
        
        foreach ($available_statuses as $status => $label) {
            $query_args = array(
                'post_type'      => 'tpak_survey_data',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_tpak_workflow_status',
                        'value' => $status,
                    ),
                ),
            );
            
            // Apply role-based filtering
            $this->apply_role_based_filtering($query_args, $user_id);
            
            $query = new WP_Query($query_args);
            $stats[$status] = array(
                'label' => $label,
                'count' => $query->found_posts,
            );
        }
        
        return $stats;
    }
}    

    /**
     * Secure AJAX handler for loading data table
     */
    public function ajax_load_data_table() {
        TPAK_Security_Middleware::secure_ajax_action(
            'edit_tpak_data',
            array($this, 'load_data_table_handler')
        );
    }
    
    /**
     * Load data table handler
     */
    public function load_data_table_handler() {
        $page = TPAK_Security::sanitize_int($_POST['page'] ?? 1, 1);
        $status = TPAK_Security::sanitize_text($_POST['status'] ?? '');
        $search = TPAK_Security::sanitize_text($_POST['search'] ?? '');
        $orderby = TPAK_Security::sanitize_text($_POST['orderby'] ?? 'date');
        $order = TPAK_Security::sanitize_text($_POST['order'] ?? 'DESC');
        
        $args = array(
            'page' => $page,
            'status' => $status,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order
        );
        
        $data = $this->get_data_list($args);
        
        wp_send_json_success(array(
            'html' => $this->render_data_table($data['items']),
            'pagination' => $this->render_pagination($data['total'], $page),
            'total' => $data['total']
        ));
    }
    
    /**
     * Secure AJAX handler for getting data details
     */
    public function ajax_get_data_details() {
        TPAK_Security_Middleware::secure_ajax_action(
            'edit_tpak_data',
            array($this, 'get_data_details_handler')
        );
    }
    
    /**
     * Get data details handler
     */
    public function get_data_details_handler() {
        $post_id = TPAK_Security::sanitize_int($_POST['post_id'] ?? 0);
        
        TPAK_Security_Middleware::secure_data_access(
            $post_id,
            'view',
            array($this, 'render_data_details')
        );
    }
    
    /**
     * Render data details
     */
    public function render_data_details($post_id, $post) {
        $survey_data = new TPAK_Survey_Data($post_id);
        $audit_trail = $survey_data->get_audit_trail();
        
        $html = '<div class="tpak-data-details">';
        $html .= '<h3>' . TPAK_Security::escape_html($post->post_title) . '</h3>';
        $html .= '<div class="tpak-survey-responses">';
        
        $responses = $survey_data->get_responses();
        if ($responses) {
            foreach ($responses as $question => $answer) {
                $html .= '<div class="tpak-response-item">';
                $html .= '<strong>' . TPAK_Security::escape_html($question) . ':</strong> ';
                $html .= TPAK_Security::escape_html($answer);
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        $html .= '<div class="tpak-audit-trail">';
        $html .= '<h4>' . __('Audit Trail', 'tpak-dq-system') . '</h4>';
        
        if ($audit_trail) {
            foreach ($audit_trail as $entry) {
                $html .= '<div class="tpak-audit-entry">';
                $html .= '<span class="timestamp">' . TPAK_Security::escape_html($entry['timestamp']) . '</span> - ';
                $html .= '<span class="action">' . TPAK_Security::escape_html($entry['action']) . '</span>';
                if (!empty($entry['notes'])) {
                    $html .= '<div class="notes">' . TPAK_Security::escape_html($entry['notes']) . '</div>';
                }
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * Secure AJAX handler for workflow actions
     */
    public function ajax_perform_workflow_action() {
        TPAK_Security_Middleware::secure_ajax_action(
            'edit_tpak_data',
            array($this, 'perform_workflow_action_handler')
        );
    }
    
    /**
     * Perform workflow action handler
     */
    public function perform_workflow_action_handler() {
        $post_id = TPAK_Security::sanitize_int($_POST['post_id'] ?? 0);
        $action = TPAK_Security::sanitize_text($_POST['workflow_action'] ?? '');
        $notes = TPAK_Security::sanitize_textarea($_POST['notes'] ?? '', 1000);
        
        if (!in_array($action, array('approve', 'reject', 'return'))) {
            wp_send_json_error(array('message' => __('Invalid workflow action.', 'tpak-dq-system')));
        }
        
        TPAK_Security_Middleware::secure_data_access(
            $post_id,
            $action,
            array($this, 'execute_workflow_action')
        );
    }
    
    /**
     * Execute workflow action
     */
    public function execute_workflow_action($post_id, $post) {
        $action = TPAK_Security::sanitize_text($_POST['workflow_action'] ?? '');
        $notes = TPAK_Security::sanitize_textarea($_POST['notes'] ?? '', 1000);
        
        try {
            $workflow = TPAK_Workflow::get_instance();
            $result = $workflow->transition_status($post_id, $action, $notes);
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'new_status' => $result['new_status']
                ));
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }
        } catch (Exception $e) {
            TPAK_Security::log_security_event('workflow_action_error', $e->getMessage(), array(
                'post_id' => $post_id,
                'action' => $action
            ));
            wp_send_json_error(array('message' => __('Workflow action failed.', 'tpak-dq-system')));
        }
    }
    
    /**
     * Secure AJAX handler for bulk actions
     */
    public function ajax_bulk_action() {
        TPAK_Security_Middleware::secure_ajax_action(
            'edit_tpak_data',
            array($this, 'bulk_action_handler')
        );
    }
    
    /**
     * Bulk action handler
     */
    public function bulk_action_handler() {
        $action = TPAK_Security::sanitize_text($_POST['bulk_action'] ?? '');
        $post_ids = array_map('intval', $_POST['post_ids'] ?? array());
        
        if (empty($post_ids) || !in_array($action, array('approve', 'reject', 'delete'))) {
            wp_send_json_error(array('message' => __('Invalid bulk action or no items selected.', 'tpak-dq-system')));
        }
        
        // Rate limiting for bulk actions
        if (!TPAK_Security::check_rate_limit('bulk_action', 5, 300)) {
            wp_send_json_error(array('message' => __('Too many bulk actions. Please wait before trying again.', 'tpak-dq-system')));
        }
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($post_ids as $post_id) {
            if (TPAK_Security::can_edit_data($post_id)) {
                try {
                    $workflow = TPAK_Workflow::get_instance();
                    $result = $workflow->transition_status($post_id, $action, 'Bulk action');
                    
                    if ($result['success']) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } catch (Exception $e) {
                    $error_count++;
                    TPAK_Security::log_security_event('bulk_action_error', $e->getMessage(), array(
                        'post_id' => $post_id,
                        'action' => $action
                    ));
                }
            } else {
                $error_count++;
            }
        }
        
        $message = sprintf(
            __('%d items processed successfully, %d errors.', 'tpak-dq-system'),
            $success_count,
            $error_count
        );
        
        wp_send_json_success(array('message' => $message));
    }
    
    /**
     * Secure AJAX handler for updating data
     */
    public function ajax_update_data() {
        TPAK_Security_Middleware::secure_ajax_action(
            'edit_tpak_data',
            array($this, 'update_data_handler')
        );
    }
    
    /**
     * Update data handler
     */
    public function update_data_handler() {
        $post_id = TPAK_Security::sanitize_int($_POST['post_id'] ?? 0);
        $survey_data = TPAK_Security::sanitize_json($_POST['survey_data'] ?? '{}');
        
        if (!$survey_data) {
            wp_send_json_error(array('message' => __('Invalid survey data format.', 'tpak-dq-system')));
        }
        
        TPAK_Security_Middleware::secure_data_access(
            $post_id,
            'edit',
            array($this, 'save_data_updates')
        );
    }
    
    /**
     * Save data updates
     */
    public function save_data_updates($post_id, $post) {
        $survey_data_json = TPAK_Security::sanitize_json($_POST['survey_data'] ?? '{}');
        
        try {
            $survey_data = new TPAK_Survey_Data($post_id);
            $result = $survey_data->update_responses(json_decode($survey_data_json, true));
            
            if ($result) {
                wp_send_json_success(array('message' => __('Data updated successfully.', 'tpak-dq-system')));
            } else {
                wp_send_json_error(array('message' => __('Failed to update data.', 'tpak-dq-system')));
            }
        } catch (Exception $e) {
            TPAK_Security::log_security_event('data_update_error', $e->getMessage(), array(
                'post_id' => $post_id
            ));
            wp_send_json_error(array('message' => __('Data update failed.', 'tpak-dq-system')));
        }
    }
    
    /**
     * Handle secure data actions
     */
    public function handle_data_action_secure() {
        $sanitization_rules = array(
            'data_action' => array('type' => 'text', 'max_length' => 20),
            'post_id' => array('type' => 'int', 'min' => 1),
            'notes' => array('type' => 'textarea', 'max_length' => 1000),
            'redirect_to' => array('type' => 'url')
        );
        
        TPAK_Security_Middleware::secure_form_submission(
            TPAK_Security::NONCE_DATA_ACTION,
            'edit_tpak_data',
            array($this, 'process_data_action'),
            $sanitization_rules
        );
    }
    
    /**
     * Process data action
     */
    public function process_data_action($sanitized_data) {
        $action = $sanitized_data['data_action'];
        $post_id = $sanitized_data['post_id'];
        $notes = $sanitized_data['notes'] ?? '';
        $redirect_to = $sanitized_data['redirect_to'] ?? admin_url('admin.php?page=tpak-data');
        
        try {
            $workflow = TPAK_Workflow::get_instance();
            $result = $workflow->transition_status($post_id, $action, $notes);
            
            $message = $result['success'] ? $result['message'] : $result['message'];
            $type = $result['success'] ? 'success' : 'error';
            
            $redirect_url = add_query_arg(array(
                'message' => urlencode($message),
                'type' => $type
            ), $redirect_to);
            
            wp_redirect($redirect_url);
            exit;
        } catch (Exception $e) {
            TPAK_Security::log_security_event('data_action_error', $e->getMessage(), array(
                'post_id' => $post_id,
                'action' => $action
            ));
            
            $redirect_url = add_query_arg(array(
                'message' => urlencode(__('Action failed due to an error.', 'tpak-dq-system')),
                'type' => 'error'
            ), $redirect_to);
            
            wp_redirect($redirect_url);
            exit;
        }
    }