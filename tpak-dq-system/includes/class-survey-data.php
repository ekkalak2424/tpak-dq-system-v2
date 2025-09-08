<?php
/**
 * Survey Data Model Class
 * 
 * Represents a survey data record with workflow management
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Survey Data Class
 */
class TPAK_Survey_Data {
    
    /**
     * Post ID
     * 
     * @var int
     */
    private $id;
    
    /**
     * Survey ID from LimeSurvey
     * 
     * @var string
     */
    private $survey_id;
    
    /**
     * Response ID from LimeSurvey
     * 
     * @var string
     */
    private $response_id;
    
    /**
     * Survey response data (JSON)
     * 
     * @var array
     */
    private $data;
    
    /**
     * Current workflow status
     * 
     * @var string
     */
    private $status;
    
    /**
     * Assigned user ID
     * 
     * @var int
     */
    private $assigned_user;
    
    /**
     * Creation date
     * 
     * @var string
     */
    private $created_date;
    
    /**
     * Last modified date
     * 
     * @var string
     */
    private $last_modified;
    
    /**
     * Audit trail
     * 
     * @var array
     */
    private $audit_trail;
    
    /**
     * Constructor
     * 
     * @param int|WP_Post $post
     */
    public function __construct($post = null) {
        if ($post) {
            $this->load_from_post($post);
        }
    }
    
    /**
     * Load data from WordPress post
     * 
     * @param int|WP_Post $post
     * @return bool
     */
    public function load_from_post($post) {
        $post = get_post($post);
        
        if (!$post || $post->post_type !== 'tpak_survey_data') {
            return false;
        }
        
        $this->id = $post->ID;
        $this->created_date = $post->post_date;
        
        // Load meta data
        $this->survey_id = get_post_meta($post->ID, '_tpak_survey_id', true);
        $this->response_id = get_post_meta($post->ID, '_tpak_response_id', true);
        $this->status = get_post_meta($post->ID, '_tpak_workflow_status', true) ?: 'pending_a';
        $this->assigned_user = (int) get_post_meta($post->ID, '_tpak_assigned_user', true);
        $this->last_modified = get_post_meta($post->ID, '_tpak_last_modified', true);
        
        // Load JSON data
        $survey_data = get_post_meta($post->ID, '_tpak_survey_data', true);
        $this->data = !empty($survey_data) ? json_decode($survey_data, true) : array();
        
        $audit_trail = get_post_meta($post->ID, '_tpak_audit_trail', true);
        $this->audit_trail = !empty($audit_trail) ? json_decode($audit_trail, true) : array();
        
        return true;
    }
    
    /**
     * Save survey data to WordPress
     * 
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    public function save() {
        $post_data = array(
            'post_type'   => 'tpak_survey_data',
            'post_title'  => $this->generate_title(),
            'post_status' => 'publish',
            'meta_input'  => array(
                '_tpak_survey_id'       => $this->survey_id,
                '_tpak_response_id'     => $this->response_id,
                '_tpak_survey_data'     => wp_json_encode($this->data),
                '_tpak_workflow_status' => $this->status,
                '_tpak_assigned_user'   => $this->assigned_user,
                '_tpak_audit_trail'     => wp_json_encode($this->audit_trail),
                '_tpak_last_modified'   => current_time('mysql'),
            ),
        );
        
        if ($this->id) {
            $post_data['ID'] = $this->id;
            $result = wp_update_post($post_data, true);
        } else {
            $post_data['meta_input']['_tpak_import_date'] = current_time('mysql');
            $result = wp_insert_post($post_data, true);
            
            if (!is_wp_error($result)) {
                $this->id = $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Generate post title from survey data
     * 
     * @return string
     */
    private function generate_title() {
        $title = sprintf(
            __('Survey %s - Response %s', 'tpak-dq-system'),
            $this->survey_id,
            $this->response_id
        );
        
        // Add timestamp if no response ID
        if (empty($this->response_id)) {
            $title .= ' - ' . current_time('Y-m-d H:i:s');
        }
        
        return $title;
    }
    
    /**
     * Add audit trail entry
     * 
     * @param string $action
     * @param mixed  $old_value
     * @param mixed  $new_value
     * @param string $notes
     */
    public function add_audit_entry($action, $old_value = null, $new_value = null, $notes = '') {
        $entry = array(
            'timestamp' => current_time('mysql'),
            'user_id'   => get_current_user_id(),
            'action'    => $action,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'notes'     => $notes,
        );
        
        if (!is_array($this->audit_trail)) {
            $this->audit_trail = array();
        }
        
        $this->audit_trail[] = $entry;
    }
    
    /**
     * Get formatted audit trail
     * 
     * @return array
     */
    public function get_formatted_audit_trail() {
        if (!is_array($this->audit_trail)) {
            return array();
        }
        
        $formatted = array();
        
        foreach ($this->audit_trail as $entry) {
            $user = get_user_by('id', $entry['user_id']);
            $user_name = $user ? $user->display_name : __('Unknown User', 'tpak-dq-system');
            
            $formatted[] = array(
                'timestamp'  => $entry['timestamp'],
                'user_name'  => $user_name,
                'action'     => $entry['action'],
                'old_value'  => $entry['old_value'],
                'new_value'  => $entry['new_value'],
                'notes'      => $entry['notes'],
            );
        }
        
        return $formatted;
    }
    
    // Getters and Setters
    
    /**
     * Get ID
     * 
     * @return int
     */
    public function get_id() {
        return $this->id;
    }
    
    /**
     * Get survey ID
     * 
     * @return string
     */
    public function get_survey_id() {
        return $this->survey_id;
    }
    
    /**
     * Set survey ID
     * 
     * @param string $survey_id
     */
    public function set_survey_id($survey_id) {
        $this->survey_id = sanitize_text_field($survey_id);
    }
    
    /**
     * Get response ID
     * 
     * @return string
     */
    public function get_response_id() {
        return $this->response_id;
    }
    
    /**
     * Set response ID
     * 
     * @param string $response_id
     */
    public function set_response_id($response_id) {
        $this->response_id = sanitize_text_field($response_id);
    }
    
    /**
     * Get survey data
     * 
     * @return array
     */
    public function get_data() {
        return $this->data;
    }
    
    /**
     * Set survey data
     * 
     * @param array $data
     */
    public function set_data($data) {
        $this->data = is_array($data) ? $data : array();
    }
    
    /**
     * Get workflow status
     * 
     * @return string
     */
    public function get_status() {
        return $this->status;
    }
    
    /**
     * Set workflow status
     * 
     * @param string $status
     */
    public function set_status($status) {
        $valid_statuses = array_keys(TPAK_Post_Types::get_instance()->get_workflow_statuses());
        
        if (in_array($status, $valid_statuses)) {
            $old_status = $this->status;
            $this->status = $status;
            
            // Add audit trail entry
            $this->add_audit_entry('status_change', $old_status, $status);
            
            // Update completion date if finalized
            if (in_array($status, array('finalized', 'finalized_by_sampling'))) {
                update_post_meta($this->id, '_tpak_completion_date', current_time('mysql'));
            }
        }
    }
    
    /**
     * Get assigned user
     * 
     * @return int
     */
    public function get_assigned_user() {
        return $this->assigned_user;
    }
    
    /**
     * Set assigned user
     * 
     * @param int $user_id
     */
    public function set_assigned_user($user_id) {
        $old_user = $this->assigned_user;
        $this->assigned_user = absint($user_id);
        
        // Add audit trail entry
        $this->add_audit_entry('user_assignment', $old_user, $user_id);
    }
    
    /**
     * Get created date
     * 
     * @return string
     */
    public function get_created_date() {
        return $this->created_date;
    }
    
    /**
     * Get last modified date
     * 
     * @return string
     */
    public function get_last_modified() {
        return $this->last_modified;
    }
    
    /**
     * Get audit trail
     * 
     * @return array
     */
    public function get_audit_trail() {
        return $this->audit_trail;
    }
    
    /**
     * Check if user can edit this data
     * 
     * @param int $user_id
     * @return bool
     */
    public function can_user_edit($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Administrators can always edit
        if (user_can($user, 'manage_options')) {
            return true;
        }
        
        // Check role-based permissions based on status
        switch ($this->status) {
            case 'pending_a':
            case 'rejected_by_b':
                return in_array('tpak_interviewer_a', $user->roles);
                
            case 'pending_b':
                return in_array('tpak_supervisor_b', $user->roles);
                
            case 'pending_c':
            case 'rejected_by_c':
                return in_array('tpak_examiner_c', $user->roles);
                
            default:
                return false; // Finalized data cannot be edited
        }
    }
    
    /**
     * Delete survey data
     * 
     * @param bool $force_delete
     * @return bool
     */
    public function delete($force_delete = false) {
        if (!$this->id) {
            return false;
        }
        
        $result = wp_delete_post($this->id, $force_delete);
        
        if ($result) {
            $this->id = null;
        }
        
        return (bool) $result;
    }
    
    /**
     * Create survey data from LimeSurvey response
     * 
     * @param string $survey_id
     * @param string $response_id
     * @param array  $response_data
     * @return TPAK_Survey_Data|WP_Error
     */
    public static function create_from_limesurvey($survey_id, $response_id, $response_data) {
        $survey_data = new self();
        $survey_data->set_survey_id($survey_id);
        $survey_data->set_response_id($response_id);
        $survey_data->set_data($response_data);
        $survey_data->set_status('pending_a');
        
        // Add initial audit entry
        $survey_data->add_audit_entry('imported', null, null, 'Data imported from LimeSurvey');
        
        $result = $survey_data->save();
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return $survey_data;
    }
    
    /**
     * Find existing survey data by response ID
     * 
     * @param string $survey_id
     * @param string $response_id
     * @return TPAK_Survey_Data|null
     */
    public static function find_by_response_id($survey_id, $response_id) {
        $posts = get_posts(array(
            'post_type'  => 'tpak_survey_data',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'   => '_tpak_survey_id',
                    'value' => $survey_id,
                ),
                array(
                    'key'   => '_tpak_response_id',
                    'value' => $response_id,
                ),
            ),
            'numberposts' => 1,
        ));
        
        if (empty($posts)) {
            return null;
        }
        
        return new self($posts[0]);
    }
}