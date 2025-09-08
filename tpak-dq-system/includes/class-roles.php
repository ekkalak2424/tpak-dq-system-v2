<?php
/**
 * Roles Class
 * 
 * Handles user role management
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Roles Class
 */
class TPAK_Roles {
    
    /**
     * Single instance
     * 
     * @var TPAK_Roles
     */
    private static $instance = null;
    
    /**
     * Get instance
     * 
     * @return TPAK_Roles
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
        add_action('init', array($this, 'init_role_capabilities'));
        add_filter('user_has_cap', array($this, 'filter_user_capabilities'), 10, 4);
    }
    
    /**
     * Initialize role capabilities
     */
    public function init_role_capabilities() {
        // Add capabilities to administrator role
        $this->add_admin_capabilities();
    }
    
    /**
     * Create custom roles
     */
    public function create_roles() {
        $this->create_interviewer_role();
        $this->create_supervisor_role();
        $this->create_examiner_role();
        $this->add_admin_capabilities();
    }
    
    /**
     * Create Interviewer (A) role
     */
    private function create_interviewer_role() {
        $capabilities = array(
            // Basic WordPress capabilities
            'read' => true,
            
            // TPAK specific capabilities
            'edit_tpak_survey_data' => true,
            'read_tpak_survey_data' => true,
            'edit_tpak_survey_datas' => true,
            'read_private_tpak_survey_datas' => true,
            
            // Workflow capabilities
            'tpak_review_pending_a' => true,
            'tpak_edit_pending_a' => true,
            'tpak_approve_to_supervisor' => true,
            'tpak_handle_rejected_by_b' => true,
            
            // Dashboard access
            'tpak_access_dashboard' => true,
            'tpak_view_assigned_data' => true,
        );
        
        add_role(
            'tpak_interviewer_a',
            __('TPAK Interviewer (A)', 'tpak-dq-system'),
            $capabilities
        );
    }
    
    /**
     * Create Supervisor (B) role
     */
    private function create_supervisor_role() {
        $capabilities = array(
            // Basic WordPress capabilities
            'read' => true,
            
            // TPAK specific capabilities
            'read_tpak_survey_data' => true,
            'read_tpak_survey_datas' => true,
            'read_private_tpak_survey_datas' => true,
            
            // Workflow capabilities
            'tpak_review_pending_b' => true,
            'tpak_approve_to_examiner' => true,
            'tpak_reject_to_interviewer' => true,
            'tpak_apply_sampling_gate' => true,
            'tpak_finalize_by_sampling' => true,
            
            // Dashboard access
            'tpak_access_dashboard' => true,
            'tpak_view_assigned_data' => true,
            'tpak_view_supervisor_stats' => true,
        );
        
        add_role(
            'tpak_supervisor_b',
            __('TPAK Supervisor (B)', 'tpak-dq-system'),
            $capabilities
        );
    }
    
    /**
     * Create Examiner (C) role
     */
    private function create_examiner_role() {
        $capabilities = array(
            // Basic WordPress capabilities
            'read' => true,
            
            // TPAK specific capabilities
            'read_tpak_survey_data' => true,
            'read_tpak_survey_datas' => true,
            'read_private_tpak_survey_datas' => true,
            
            // Workflow capabilities
            'tpak_review_pending_c' => true,
            'tpak_final_approval' => true,
            'tpak_reject_to_supervisor' => true,
            'tpak_finalize_data' => true,
            
            // Dashboard access
            'tpak_access_dashboard' => true,
            'tpak_view_assigned_data' => true,
            'tpak_view_examiner_stats' => true,
        );
        
        add_role(
            'tpak_examiner_c',
            __('TPAK Examiner (C)', 'tpak-dq-system'),
            $capabilities
        );
    }
    
    /**
     * Add capabilities to administrator role
     */
    private function add_admin_capabilities() {
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            $admin_capabilities = array(
                // All TPAK survey data capabilities
                'edit_tpak_survey_data',
                'read_tpak_survey_data',
                'delete_tpak_survey_data',
                'edit_tpak_survey_datas',
                'edit_others_tpak_survey_datas',
                'publish_tpak_survey_datas',
                'read_private_tpak_survey_datas',
                'delete_tpak_survey_datas',
                'delete_private_tpak_survey_datas',
                'delete_published_tpak_survey_datas',
                'delete_others_tpak_survey_datas',
                'edit_private_tpak_survey_datas',
                'edit_published_tpak_survey_datas',
                
                // All workflow capabilities
                'tpak_review_pending_a',
                'tpak_edit_pending_a',
                'tpak_approve_to_supervisor',
                'tpak_handle_rejected_by_b',
                'tpak_review_pending_b',
                'tpak_approve_to_examiner',
                'tpak_reject_to_interviewer',
                'tpak_apply_sampling_gate',
                'tpak_finalize_by_sampling',
                'tpak_review_pending_c',
                'tpak_final_approval',
                'tpak_reject_to_supervisor',
                'tpak_finalize_data',
                
                // Admin specific capabilities
                'tpak_manage_settings',
                'tpak_import_data',
                'tpak_export_data',
                'tpak_manage_users',
                'tpak_view_all_data',
                'tpak_access_dashboard',
                'tpak_view_assigned_data',
                'tpak_view_supervisor_stats',
                'tpak_view_examiner_stats',
                'tpak_view_admin_stats',
                'tpak_manage_api_settings',
                'tpak_manage_cron_settings',
                'tpak_manage_notifications',
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->add_cap($cap);
            }
        }
    }
    
    /**
     * Remove custom roles
     */
    public function remove_roles() {
        remove_role('tpak_interviewer_a');
        remove_role('tpak_supervisor_b');
        remove_role('tpak_examiner_c');
        
        // Remove capabilities from administrator
        $this->remove_admin_capabilities();
    }
    
    /**
     * Remove capabilities from administrator role
     */
    private function remove_admin_capabilities() {
        $admin_role = get_role('administrator');
        
        if ($admin_role) {
            $admin_capabilities = array(
                'edit_tpak_survey_data',
                'read_tpak_survey_data',
                'delete_tpak_survey_data',
                'edit_tpak_survey_datas',
                'edit_others_tpak_survey_datas',
                'publish_tpak_survey_datas',
                'read_private_tpak_survey_datas',
                'delete_tpak_survey_datas',
                'delete_private_tpak_survey_datas',
                'delete_published_tpak_survey_datas',
                'delete_others_tpak_survey_datas',
                'edit_private_tpak_survey_datas',
                'edit_published_tpak_survey_datas',
                'tpak_review_pending_a',
                'tpak_edit_pending_a',
                'tpak_approve_to_supervisor',
                'tpak_handle_rejected_by_b',
                'tpak_review_pending_b',
                'tpak_approve_to_examiner',
                'tpak_reject_to_interviewer',
                'tpak_apply_sampling_gate',
                'tpak_finalize_by_sampling',
                'tpak_review_pending_c',
                'tpak_final_approval',
                'tpak_reject_to_supervisor',
                'tpak_finalize_data',
                'tpak_manage_settings',
                'tpak_import_data',
                'tpak_export_data',
                'tpak_manage_users',
                'tpak_view_all_data',
                'tpak_access_dashboard',
                'tpak_view_assigned_data',
                'tpak_view_supervisor_stats',
                'tpak_view_examiner_stats',
                'tpak_view_admin_stats',
                'tpak_manage_api_settings',
                'tpak_manage_cron_settings',
                'tpak_manage_notifications',
            );
            
            foreach ($admin_capabilities as $cap) {
                $admin_role->remove_cap($cap);
            }
        }
    }
    
    /**
     * Get all TPAK roles
     * 
     * @return array
     */
    public function get_tpak_roles() {
        return array(
            'tpak_interviewer_a' => __('TPAK Interviewer (A)', 'tpak-dq-system'),
            'tpak_supervisor_b'  => __('TPAK Supervisor (B)', 'tpak-dq-system'),
            'tpak_examiner_c'    => __('TPAK Examiner (C)', 'tpak-dq-system'),
        );
    }
    
    /**
     * Get role capabilities
     * 
     * @param string $role_name
     * @return array
     */
    public function get_role_capabilities($role_name) {
        $role = get_role($role_name);
        return $role ? $role->capabilities : array();
    }
    
    /**
     * Check if user has TPAK role
     * 
     * @param int    $user_id
     * @param string $role_name
     * @return bool
     */
    public function user_has_tpak_role($user_id, $role_name = null) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        $tpak_roles = array_keys($this->get_tpak_roles());
        
        if ($role_name) {
            return in_array($role_name, $user->roles);
        }
        
        // Check if user has any TPAK role
        return !empty(array_intersect($user->roles, $tpak_roles));
    }
    
    /**
     * Get user's TPAK role
     * 
     * @param int $user_id
     * @return string|null
     */
    public function get_user_tpak_role($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return null;
        }
        
        $tpak_roles = array_keys($this->get_tpak_roles());
        $user_tpak_roles = array_intersect($user->roles, $tpak_roles);
        
        return !empty($user_tpak_roles) ? reset($user_tpak_roles) : null;
    }
    
    /**
     * Check if user can access data based on status
     * 
     * @param int    $user_id
     * @param string $status
     * @return bool
     */
    public function can_user_access_status($user_id, $status) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Administrators can access everything
        if (in_array('administrator', $user->roles)) {
            return true;
        }
        
        // Check role-based access
        switch ($status) {
            case 'pending_a':
            case 'rejected_by_b':
                return in_array('tpak_interviewer_a', $user->roles);
                
            case 'pending_b':
                return in_array('tpak_supervisor_b', $user->roles);
                
            case 'pending_c':
            case 'rejected_by_c':
                return in_array('tpak_examiner_c', $user->roles);
                
            case 'finalized':
            case 'finalized_by_sampling':
                // Finalized data is read-only for all roles
                return $this->user_has_tpak_role($user_id);
                
            default:
                return false;
        }
    }
    
    /**
     * Check if user can edit data based on status
     * 
     * @param int    $user_id
     * @param string $status
     * @return bool
     */
    public function can_user_edit_status($user_id, $status) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        // Administrators can edit non-finalized data
        if (in_array('administrator', $user->roles)) {
            return !in_array($status, array('finalized', 'finalized_by_sampling'));
        }
        
        // Only Interviewer A can edit data
        switch ($status) {
            case 'pending_a':
            case 'rejected_by_b':
                return in_array('tpak_interviewer_a', $user->roles);
                
            default:
                return false; // Other roles can only review, not edit
        }
    }
    
    /**
     * Get users by TPAK role
     * 
     * @param string $role_name
     * @return array
     */
    public function get_users_by_role($role_name) {
        $users = get_users(array(
            'role' => $role_name,
            'fields' => array('ID', 'display_name', 'user_email'),
        ));
        
        return $users;
    }
    
    /**
     * Assign TPAK role to user
     * 
     * @param int    $user_id
     * @param string $role_name
     * @return bool
     */
    public function assign_role_to_user($user_id, $role_name) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        $tpak_roles = array_keys($this->get_tpak_roles());
        
        if (!in_array($role_name, $tpak_roles)) {
            return false;
        }
        
        // Remove existing TPAK roles
        foreach ($tpak_roles as $existing_role) {
            $user->remove_role($existing_role);
        }
        
        // Add new role
        $user->add_role($role_name);
        
        return true;
    }
    
    /**
     * Remove TPAK role from user
     * 
     * @param int    $user_id
     * @param string $role_name
     * @return bool
     */
    public function remove_role_from_user($user_id, $role_name = null) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return false;
        }
        
        if ($role_name) {
            $user->remove_role($role_name);
        } else {
            // Remove all TPAK roles
            $tpak_roles = array_keys($this->get_tpak_roles());
            foreach ($tpak_roles as $role) {
                $user->remove_role($role);
            }
        }
        
        return true;
    }
    
    /**
     * Filter user capabilities based on workflow context
     * 
     * @param array $allcaps
     * @param array $caps
     * @param array $args
     * @param WP_User $user
     * @return array
     */
    public function filter_user_capabilities($allcaps, $caps, $args, $user) {
        // Only filter for TPAK survey data
        if (isset($args[0]) && strpos($args[0], 'tpak_survey_data') !== false) {
            $post_id = isset($args[2]) ? $args[2] : 0;
            
            if ($post_id) {
                $post = get_post($post_id);
                
                if ($post && $post->post_type === 'tpak_survey_data') {
                    $status = get_post_meta($post_id, '_tpak_workflow_status', true);
                    
                    // Check if user can access this status
                    if (!$this->can_user_access_status($user->ID, $status)) {
                        // Remove capabilities for this specific post
                        foreach ($caps as $cap) {
                            $allcaps[$cap] = false;
                        }
                    }
                }
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Get role display name
     * 
     * @param string $role_name
     * @return string
     */
    public function get_role_display_name($role_name) {
        $roles = $this->get_tpak_roles();
        return isset($roles[$role_name]) ? $roles[$role_name] : $role_name;
    }
    
    /**
     * Get role description
     * 
     * @param string $role_name
     * @return string
     */
    public function get_role_description($role_name) {
        $descriptions = array(
            'tpak_interviewer_a' => __('Reviews and edits survey data in the first stage of verification. Can handle data with "pending_a" or "rejected_by_b" status.', 'tpak-dq-system'),
            'tpak_supervisor_b'  => __('Reviews data in the second stage. Can approve data for finalization or send to Examiner. Applies sampling gate (70% finalized, 30% to Examiner).', 'tpak-dq-system'),
            'tpak_examiner_c'    => __('Performs final review and approval. Can finalize data or reject it back to Supervisor for further review.', 'tpak-dq-system'),
        );
        
        return isset($descriptions[$role_name]) ? $descriptions[$role_name] : '';
    }
}