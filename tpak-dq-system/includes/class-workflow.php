<?php
/**
 * Workflow Class
 * 
 * Handles workflow engine and state management
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Workflow Class
 */
class TPAK_Workflow {
    
    /**
     * Single instance
     * 
     * @var TPAK_Workflow
     */
    private static $instance = null;
    
    /**
     * Get instance
     * 
     * @return TPAK_Workflow
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
        add_action('wp_ajax_tpak_workflow_action', array($this, 'handle_ajax_workflow_action'));
        add_action('tpak_dq_status_changed', array($this, 'handle_status_change'), 10, 4);
        add_filter('tpak_dq_workflow_transitions', array($this, 'get_workflow_transitions'));
    }
    
    /**
     * Get workflow state machine definition
     * 
     * @return array
     */
    public function get_workflow_states() {
        return array(
            'pending_a' => array(
                'label' => __('Pending Interviewer Review', 'tpak-dq-system'),
                'description' => __('Waiting for Interviewer (A) to review and edit data', 'tpak-dq-system'),
                'color' => '#f56565',
                'allowed_roles' => array('tpak_interviewer_a', 'administrator'),
                'actions' => array('edit', 'approve_to_supervisor'),
                'next_states' => array('pending_b')
            ),
            'pending_b' => array(
                'label' => __('Pending Supervisor Review', 'tpak-dq-system'),
                'description' => __('Waiting for Supervisor (B) to review and approve/reject', 'tpak-dq-system'),
                'color' => '#ed8936',
                'allowed_roles' => array('tpak_supervisor_b', 'administrator'),
                'actions' => array('approve_to_examiner', 'reject_to_interviewer', 'apply_sampling'),
                'next_states' => array('pending_c', 'rejected_by_b', 'finalized_by_sampling')
            ),
            'pending_c' => array(
                'label' => __('Pending Examiner Review', 'tpak-dq-system'),
                'description' => __('Waiting for Examiner (C) to perform final approval', 'tpak-dq-system'),
                'color' => '#38b2ac',
                'allowed_roles' => array('tpak_examiner_c', 'administrator'),
                'actions' => array('final_approval', 'reject_to_supervisor'),
                'next_states' => array('finalized', 'rejected_by_c')
            ),
            'rejected_by_b' => array(
                'label' => __('Rejected by Supervisor', 'tpak-dq-system'),
                'description' => __('Returned to Interviewer (A) for corrections', 'tpak-dq-system'),
                'color' => '#e53e3e',
                'allowed_roles' => array('tpak_interviewer_a', 'administrator'),
                'actions' => array('edit', 'resubmit_to_supervisor'),
                'next_states' => array('pending_b')
            ),
            'rejected_by_c' => array(
                'label' => __('Rejected by Examiner', 'tpak-dq-system'),
                'description' => __('Returned to Supervisor (B) for review', 'tpak-dq-system'),
                'color' => '#e53e3e',
                'allowed_roles' => array('tpak_supervisor_b', 'administrator'),
                'actions' => array('approve_to_examiner', 'reject_to_interviewer', 'apply_sampling'),
                'next_states' => array('pending_c', 'rejected_by_b', 'finalized_by_sampling')
            ),
            'finalized' => array(
                'label' => __('Finalized', 'tpak-dq-system'),
                'description' => __('Data review process completed successfully', 'tpak-dq-system'),
                'color' => '#38a169',
                'allowed_roles' => array(),
                'actions' => array(),
                'next_states' => array(),
                'is_final' => true
            ),
            'finalized_by_sampling' => array(
                'label' => __('Finalized by Sampling', 'tpak-dq-system'),
                'description' => __('Data finalized through sampling gate process', 'tpak-dq-system'),
                'color' => '#319795',
                'allowed_roles' => array(),
                'actions' => array(),
                'next_states' => array(),
                'is_final' => true
            )
        );
    }
    
    /**
     * Get workflow transitions
     * 
     * @return array
     */
    public function get_workflow_transitions() {
        return array(
            'approve_to_supervisor' => array(
                'from' => array('pending_a', 'rejected_by_b'),
                'to' => 'pending_b',
                'label' => __('Approve to Supervisor', 'tpak-dq-system'),
                'required_role' => 'tpak_interviewer_a',
                'requires_note' => false
            ),
            'approve_to_examiner' => array(
                'from' => array('pending_b', 'rejected_by_c'),
                'to' => 'pending_c',
                'label' => __('Approve to Examiner', 'tpak-dq-system'),
                'required_role' => 'tpak_supervisor_b',
                'requires_note' => false
            ),
            'reject_to_interviewer' => array(
                'from' => array('pending_b', 'rejected_by_c'),
                'to' => 'rejected_by_b',
                'label' => __('Reject to Interviewer', 'tpak-dq-system'),
                'required_role' => 'tpak_supervisor_b',
                'requires_note' => true
            ),
            'reject_to_supervisor' => array(
                'from' => array('pending_c'),
                'to' => 'rejected_by_c',
                'label' => __('Reject to Supervisor', 'tpak-dq-system'),
                'required_role' => 'tpak_examiner_c',
                'requires_note' => true
            ),
            'apply_sampling' => array(
                'from' => array('pending_b', 'rejected_by_c'),
                'to' => array('finalized_by_sampling', 'pending_c'), // 70/30 split
                'label' => __('Apply Sampling Gate', 'tpak-dq-system'),
                'required_role' => 'tpak_supervisor_b',
                'requires_note' => false,
                'is_sampling' => true
            ),
            'final_approval' => array(
                'from' => array('pending_c'),
                'to' => 'finalized',
                'label' => __('Final Approval', 'tpak-dq-system'),
                'required_role' => 'tpak_examiner_c',
                'requires_note' => false
            ),
            'resubmit_to_supervisor' => array(
                'from' => array('rejected_by_b'),
                'to' => 'pending_b',
                'label' => __('Resubmit to Supervisor', 'tpak-dq-system'),
                'required_role' => 'tpak_interviewer_a',
                'requires_note' => false
            )
        );
    }
    
    /**
     * Transition survey data status
     * 
     * @param int    $post_id
     * @param string $action
     * @param int    $user_id
     * @param string $notes
     * @return bool|WP_Error
     */
    public function transition_status($post_id, $action, $user_id = null, $notes = '') {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Validate the transition
        $validation = $this->validate_transition($post_id, $action, $user_id);
        if (is_wp_error($validation)) {
            return $validation;
        }
        
        // Get current status
        $current_status = get_post_meta($post_id, '_tpak_workflow_status', true);
        $transitions = $this->get_workflow_transitions();
        $transition = $transitions[$action];
        
        // Handle sampling gate
        if (isset($transition['is_sampling']) && $transition['is_sampling']) {
            return $this->apply_sampling_gate($post_id, $user_id, $notes);
        }
        
        // Perform regular transition
        $new_status = $transition['to'];
        
        // Update status
        $result = $this->update_status($post_id, $new_status, $user_id, $action, $notes);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Fire action hook
        do_action('tpak_dq_status_changed', $post_id, $current_status, $new_status, $user_id);
        
        return true;
    }
    
    /**
     * Apply sampling gate (70% finalized, 30% to examiner)
     * 
     * @param int    $post_id
     * @param int    $user_id
     * @param string $notes
     * @return bool|WP_Error
     */
    public function apply_sampling_gate($post_id, $user_id, $notes = '') {
        // Get sampling percentage from settings
        $settings = get_option('tpak_dq_settings', array());
        $sampling_percentage = isset($settings['notifications']['sampling_percentage']) 
            ? (int) $settings['notifications']['sampling_percentage'] 
            : 70;
        
        // Generate random number (1-100)
        $random = wp_rand(1, 100);
        
        // Determine outcome
        $new_status = ($random <= $sampling_percentage) ? 'finalized_by_sampling' : 'pending_c';
        
        // Get current status
        $current_status = get_post_meta($post_id, '_tpak_workflow_status', true);
        
        // Update status
        $result = $this->update_status($post_id, $new_status, $user_id, 'apply_sampling', $notes);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Log sampling decision
        $survey_data = new TPAK_Survey_Data($post_id);
        $survey_data->add_audit_entry(
            'sampling',
            $current_status,
            $new_status,
            sprintf(
                __('Sampling gate applied: %d%% chance, rolled %d, result: %s', 'tpak-dq-system'),
                $sampling_percentage,
                $random,
                $new_status === 'finalized_by_sampling' ? __('Finalized', 'tpak-dq-system') : __('To Examiner', 'tpak-dq-system')
            )
        );
        $survey_data->save();
        
        // Fire action hook
        do_action('tpak_dq_status_changed', $post_id, $current_status, $new_status, $user_id);
        
        return true;
    }
    
    /**
     * Update survey data status
     * 
     * @param int    $post_id
     * @param string $new_status
     * @param int    $user_id
     * @param string $action
     * @param string $notes
     * @return bool|WP_Error
     */
    private function update_status($post_id, $new_status, $user_id, $action, $notes) {
        $current_status = get_post_meta($post_id, '_tpak_workflow_status', true);
        
        // Update post meta
        update_post_meta($post_id, '_tpak_workflow_status', $new_status);
        update_post_meta($post_id, '_tpak_last_modified', current_time('mysql'));
        
        // Update assigned user based on new status
        $this->assign_user_for_status($post_id, $new_status);
        
        // Add audit trail entry
        $survey_data = new TPAK_Survey_Data($post_id);
        $survey_data->add_audit_entry($action, $current_status, $new_status, $notes);
        
        // Set completion date for final states
        $workflow_states = $this->get_workflow_states();
        if (isset($workflow_states[$new_status]['is_final']) && $workflow_states[$new_status]['is_final']) {
            update_post_meta($post_id, '_tpak_completion_date', current_time('mysql'));
        }
        
        return $survey_data->save();
    }
    
    /**
     * Assign user for status
     * 
     * @param int    $post_id
     * @param string $status
     */
    private function assign_user_for_status($post_id, $status) {
        $roles = TPAK_Roles::get_instance();
        $assigned_user = 0;
        
        // Get users for the appropriate role
        switch ($status) {
            case 'pending_a':
            case 'rejected_by_b':
                $users = $roles->get_users_by_role('tpak_interviewer_a');
                break;
                
            case 'pending_b':
            case 'rejected_by_c':
                $users = $roles->get_users_by_role('tpak_supervisor_b');
                break;
                
            case 'pending_c':
                $users = $roles->get_users_by_role('tpak_examiner_c');
                break;
                
            default:
                $users = array();
        }
        
        // Assign to first available user (could be enhanced with load balancing)
        if (!empty($users)) {
            $assigned_user = $users[0]->ID;
        }
        
        update_post_meta($post_id, '_tpak_assigned_user', $assigned_user);
    }
    
    /**
     * Validate workflow transition
     * 
     * @param int    $post_id
     * @param string $action
     * @param int    $user_id
     * @return bool|WP_Error
     */
    public function validate_transition($post_id, $action, $user_id) {
        // Validate post exists
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'tpak_survey_data') {
            return new WP_Error('invalid_post', __('Invalid survey data post.', 'tpak-dq-system'));
        }
        
        // Get current status
        $current_status = get_post_meta($post_id, '_tpak_workflow_status', true);
        if (empty($current_status)) {
            $current_status = 'pending_a';
        }
        
        // Validate action exists
        $transitions = $this->get_workflow_transitions();
        if (!isset($transitions[$action])) {
            return new WP_Error('invalid_action', __('Invalid workflow action.', 'tpak-dq-system'));
        }
        
        $transition = $transitions[$action];
        
        // Validate current status allows this transition
        if (!in_array($current_status, $transition['from'])) {
            return new WP_Error('invalid_transition', sprintf(
                __('Cannot perform action "%s" from status "%s".', 'tpak-dq-system'),
                $transition['label'],
                $current_status
            ));
        }
        
        // Validate user permissions
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new WP_Error('invalid_user', __('Invalid user.', 'tpak-dq-system'));
        }
        
        // Check if user has required role or is administrator
        $required_role = $transition['required_role'];
        if (!in_array('administrator', $user->roles) && !in_array($required_role, $user->roles)) {
            return new WP_Error('insufficient_permissions', sprintf(
                __('You do not have permission to perform this action. Required role: %s', 'tpak-dq-system'),
                $required_role
            ));
        }
        
        return true;
    }
    
    /**
     * Get available actions for post
     * 
     * @param int $post_id
     * @param int $user_id
     * @return array
     */
    public function get_available_actions($post_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $current_status = get_post_meta($post_id, '_tpak_workflow_status', true);
        if (empty($current_status)) {
            $current_status = 'pending_a';
        }
        
        $transitions = $this->get_workflow_transitions();
        $available_actions = array();
        
        foreach ($transitions as $action => $transition) {
            // Check if action is valid for current status
            if (!in_array($current_status, $transition['from'])) {
                continue;
            }
            
            // Check user permissions
            $validation = $this->validate_transition($post_id, $action, $user_id);
            if (is_wp_error($validation)) {
                continue;
            }
            
            $available_actions[$action] = $transition;
        }
        
        return $available_actions;
    }
    
    /**
     * Handle AJAX workflow action
     */
    public function handle_ajax_workflow_action() {
        // Check permissions
        if (!current_user_can('read')) {
            wp_die(__('Insufficient permissions.', 'tpak-dq-system'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_workflow_action')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        // Get parameters
        $post_id = absint($_POST['post_id'] ?? 0);
        $action = sanitize_text_field($_POST['action'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        if (!$post_id || !$action) {
            wp_send_json_error(array(
                'message' => __('Missing required parameters.', 'tpak-dq-system')
            ));
        }
        
        // Perform transition
        $result = $this->transition_status($post_id, $action, get_current_user_id(), $notes);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        // Get new status for response
        $new_status = get_post_meta($post_id, '_tpak_workflow_status', true);
        $workflow_states = $this->get_workflow_states();
        
        wp_send_json_success(array(
            'message' => __('Status updated successfully.', 'tpak-dq-system'),
            'new_status' => $new_status,
            'status_label' => $workflow_states[$new_status]['label'] ?? $new_status,
            'post_id' => $post_id
        ));
    }
    
    /**
     * Handle status change event
     * 
     * @param int    $post_id
     * @param string $old_status
     * @param string $new_status
     * @param int    $user_id
     */
    public function handle_status_change($post_id, $old_status, $new_status, $user_id) {
        // Send notifications
        $notifications = TPAK_Notifications::get_instance();
        $notifications->send_status_change_notification($post_id, $old_status, $new_status, $user_id);
        
        // Log the change
        error_log(sprintf(
            'TPAK DQ System: Status changed for post %d from %s to %s by user %d',
            $post_id,
            $old_status,
            $new_status,
            $user_id
        ));
    }
    
    /**
     * Get workflow statistics
     * 
     * @param array $filters
     * @return array
     */
    public function get_workflow_statistics($filters = array()) {
        global $wpdb;
        
        $where_clauses = array("p.post_type = 'tpak_survey_data'", "p.post_status = 'publish'");
        $join_clauses = array();
        
        // Add date filter
        if (!empty($filters['date_from'])) {
            $where_clauses[] = $wpdb->prepare("p.post_date >= %s", $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = $wpdb->prepare("p.post_date <= %s", $filters['date_to']);
        }
        
        // Add user filter
        if (!empty($filters['assigned_user'])) {
            $join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pm_user ON p.ID = pm_user.post_id AND pm_user.meta_key = '_tpak_assigned_user'";
            $where_clauses[] = $wpdb->prepare("pm_user.meta_value = %d", $filters['assigned_user']);
        }
        
        // Build query
        $join_sql = implode(' ', $join_clauses);
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = "
            SELECT 
                pm_status.meta_value as status,
                COUNT(*) as count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_tpak_workflow_status'
            {$join_sql}
            WHERE {$where_sql}
            GROUP BY pm_status.meta_value
        ";
        
        $results = $wpdb->get_results($query);
        
        // Format results
        $statistics = array();
        $workflow_states = $this->get_workflow_states();
        
        foreach ($workflow_states as $status => $state_info) {
            $statistics[$status] = array(
                'label' => $state_info['label'],
                'count' => 0,
                'color' => $state_info['color']
            );
        }
        
        foreach ($results as $result) {
            $status = $result->status ?: 'pending_a';
            if (isset($statistics[$status])) {
                $statistics[$status]['count'] = (int) $result->count;
            }
        }
        
        return $statistics;
    }
    
    /**
     * Get workflow performance metrics
     * 
     * @param array $filters
     * @return array
     */
    public function get_performance_metrics($filters = array()) {
        global $wpdb;
        
        $where_clauses = array("p.post_type = 'tpak_survey_data'", "p.post_status = 'publish'");
        
        // Add date filter
        if (!empty($filters['date_from'])) {
            $where_clauses[] = $wpdb->prepare("p.post_date >= %s", $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = $wpdb->prepare("p.post_date <= %s", $filters['date_to']);
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Average processing time
        $avg_time_query = "
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, p.post_date, pm_completion.meta_value)) as avg_hours
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_completion ON p.ID = pm_completion.post_id AND pm_completion.meta_key = '_tpak_completion_date'
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_tpak_workflow_status'
            WHERE {$where_sql} 
            AND pm_completion.meta_value IS NOT NULL 
            AND pm_status.meta_value IN ('finalized', 'finalized_by_sampling')
        ";
        
        $avg_time = $wpdb->get_var($avg_time_query);
        
        // Completion rate
        $completion_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN pm_status.meta_value IN ('finalized', 'finalized_by_sampling') THEN 1 ELSE 0 END) as completed
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_tpak_workflow_status'
            WHERE {$where_sql}
        ";
        
        $completion_data = $wpdb->get_row($completion_query);
        $completion_rate = $completion_data->total > 0 ? ($completion_data->completed / $completion_data->total) * 100 : 0;
        
        // Sampling statistics
        $sampling_query = "
            SELECT 
                COUNT(*) as total_sampling,
                SUM(CASE WHEN pm_status.meta_value = 'finalized_by_sampling' THEN 1 ELSE 0 END) as finalized_by_sampling,
                SUM(CASE WHEN pm_status.meta_value = 'pending_c' OR pm_status.meta_value = 'finalized' THEN 1 ELSE 0 END) as to_examiner
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm_audit ON p.ID = pm_audit.post_id AND pm_audit.meta_key = '_tpak_audit_trail'
            WHERE {$where_sql} 
            AND pm_audit.meta_value LIKE '%sampling%'
        ";
        
        $sampling_data = $wpdb->get_row($sampling_query);
        
        return array(
            'average_processing_time' => round($avg_time, 2),
            'completion_rate' => round($completion_rate, 2),
            'total_processed' => (int) $completion_data->total,
            'total_completed' => (int) $completion_data->completed,
            'sampling_statistics' => array(
                'total_sampled' => (int) $sampling_data->total_sampling,
                'finalized_by_sampling' => (int) $sampling_data->finalized_by_sampling,
                'sent_to_examiner' => (int) $sampling_data->to_examiner
            )
        );
    }
    
    /**
     * Log workflow action
     * 
     * @param int    $post_id
     * @param string $action
     * @param int    $user_id
     * @param string $notes
     */
    public function log_action($post_id, $action, $user_id, $notes = '') {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'post_id' => $post_id,
            'action' => $action,
            'user_id' => $user_id,
            'notes' => $notes
        );
        
        // Store in WordPress options (could be moved to custom table for better performance)
        $logs = get_option('tpak_dq_workflow_logs', array());
        $logs[] = $log_entry;
        
        // Keep only last 1000 entries
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        update_option('tpak_dq_workflow_logs', $logs);
    }
    
    /**
     * Get workflow logs
     * 
     * @param array $filters
     * @param int   $limit
     * @return array
     */
    public function get_workflow_logs($filters = array(), $limit = 50) {
        $logs = get_option('tpak_dq_workflow_logs', array());
        
        // Apply filters
        if (!empty($filters)) {
            $logs = array_filter($logs, function($log) use ($filters) {
                if (!empty($filters['post_id']) && $log['post_id'] != $filters['post_id']) {
                    return false;
                }
                
                if (!empty($filters['user_id']) && $log['user_id'] != $filters['user_id']) {
                    return false;
                }
                
                if (!empty($filters['action']) && $log['action'] != $filters['action']) {
                    return false;
                }
                
                if (!empty($filters['date_from']) && $log['timestamp'] < $filters['date_from']) {
                    return false;
                }
                
                if (!empty($filters['date_to']) && $log['timestamp'] > $filters['date_to']) {
                    return false;
                }
                
                return true;
            });
        }
        
        // Sort by timestamp (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Apply limit
        if ($limit > 0) {
            $logs = array_slice($logs, 0, $limit);
        }
        
        return $logs;
    }
}