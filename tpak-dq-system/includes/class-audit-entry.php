<?php
/**
 * Audit Entry Class
 * 
 * Represents an audit trail entry
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Audit Entry Class
 */
class TPAK_Audit_Entry {
    
    /**
     * Timestamp
     * 
     * @var string
     */
    private $timestamp;
    
    /**
     * User ID
     * 
     * @var int
     */
    private $user_id;
    
    /**
     * Action performed
     * 
     * @var string
     */
    private $action;
    
    /**
     * Old value
     * 
     * @var mixed
     */
    private $old_value;
    
    /**
     * New value
     * 
     * @var mixed
     */
    private $new_value;
    
    /**
     * Additional notes
     * 
     * @var string
     */
    private $notes;
    
    /**
     * Constructor
     * 
     * @param array $data
     */
    public function __construct($data = array()) {
        $this->timestamp = isset($data['timestamp']) ? $data['timestamp'] : current_time('mysql');
        $this->user_id = isset($data['user_id']) ? (int) $data['user_id'] : get_current_user_id();
        $this->action = isset($data['action']) ? sanitize_text_field($data['action']) : '';
        $this->old_value = isset($data['old_value']) ? $data['old_value'] : null;
        $this->new_value = isset($data['new_value']) ? $data['new_value'] : null;
        $this->notes = isset($data['notes']) ? sanitize_textarea_field($data['notes']) : '';
    }
    
    /**
     * Convert to array
     * 
     * @return array
     */
    public function to_array() {
        return array(
            'timestamp'  => $this->timestamp,
            'user_id'    => $this->user_id,
            'action'     => $this->action,
            'old_value'  => $this->old_value,
            'new_value'  => $this->new_value,
            'notes'      => $this->notes,
        );
    }
    
    /**
     * Get formatted display
     * 
     * @return array
     */
    public function get_formatted_display() {
        $user = get_user_by('id', $this->user_id);
        $user_name = $user ? $user->display_name : __('Unknown User', 'tpak-dq-system');
        
        return array(
            'timestamp'    => $this->get_formatted_timestamp(),
            'user_name'    => $user_name,
            'action'       => $this->get_formatted_action(),
            'description'  => $this->get_action_description(),
            'notes'        => $this->notes,
        );
    }
    
    /**
     * Get formatted timestamp
     * 
     * @return string
     */
    public function get_formatted_timestamp() {
        return mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $this->timestamp);
    }
    
    /**
     * Get formatted action
     * 
     * @return string
     */
    public function get_formatted_action() {
        $actions = array(
            'imported'        => __('Data Imported', 'tpak-dq-system'),
            'status_change'   => __('Status Changed', 'tpak-dq-system'),
            'data_edit'       => __('Data Edited', 'tpak-dq-system'),
            'user_assignment' => __('User Assigned', 'tpak-dq-system'),
            'approved'        => __('Approved', 'tpak-dq-system'),
            'rejected'        => __('Rejected', 'tpak-dq-system'),
            'sampling'        => __('Sampling Applied', 'tpak-dq-system'),
            'finalized'       => __('Finalized', 'tpak-dq-system'),
        );
        
        return isset($actions[$this->action]) ? $actions[$this->action] : ucfirst($this->action);
    }
    
    /**
     * Get action description
     * 
     * @return string
     */
    public function get_action_description() {
        switch ($this->action) {
            case 'status_change':
                $old_status = $this->get_status_label($this->old_value);
                $new_status = $this->get_status_label($this->new_value);
                return sprintf(
                    __('Status changed from "%s" to "%s"', 'tpak-dq-system'),
                    $old_status,
                    $new_status
                );
                
            case 'user_assignment':
                $old_user = $this->old_value ? get_user_by('id', $this->old_value) : null;
                $new_user = $this->new_value ? get_user_by('id', $this->new_value) : null;
                
                $old_name = $old_user ? $old_user->display_name : __('Unassigned', 'tpak-dq-system');
                $new_name = $new_user ? $new_user->display_name : __('Unassigned', 'tpak-dq-system');
                
                return sprintf(
                    __('Assignment changed from "%s" to "%s"', 'tpak-dq-system'),
                    $old_name,
                    $new_name
                );
                
            case 'data_edit':
                return __('Survey data was modified', 'tpak-dq-system');
                
            case 'imported':
                return __('Data imported from LimeSurvey', 'tpak-dq-system');
                
            case 'approved':
                return __('Data approved and moved to next stage', 'tpak-dq-system');
                
            case 'rejected':
                return __('Data rejected and returned for revision', 'tpak-dq-system');
                
            case 'sampling':
                return __('Sampling gate applied', 'tpak-dq-system');
                
            case 'finalized':
                return __('Data finalized and completed', 'tpak-dq-system');
                
            default:
                return $this->notes ?: __('Action performed', 'tpak-dq-system');
        }
    }
    
    /**
     * Get status label
     * 
     * @param string $status
     * @return string
     */
    private function get_status_label($status) {
        $statuses = TPAK_Post_Types::get_instance()->get_workflow_statuses();
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
    
    // Getters
    
    /**
     * Get timestamp
     * 
     * @return string
     */
    public function get_timestamp() {
        return $this->timestamp;
    }
    
    /**
     * Get user ID
     * 
     * @return int
     */
    public function get_user_id() {
        return $this->user_id;
    }
    
    /**
     * Get action
     * 
     * @return string
     */
    public function get_action() {
        return $this->action;
    }
    
    /**
     * Get old value
     * 
     * @return mixed
     */
    public function get_old_value() {
        return $this->old_value;
    }
    
    /**
     * Get new value
     * 
     * @return mixed
     */
    public function get_new_value() {
        return $this->new_value;
    }
    
    /**
     * Get notes
     * 
     * @return string
     */
    public function get_notes() {
        return $this->notes;
    }
    
    /**
     * Create audit entry for status change
     * 
     * @param string $old_status
     * @param string $new_status
     * @param string $notes
     * @return TPAK_Audit_Entry
     */
    public static function create_status_change($old_status, $new_status, $notes = '') {
        return new self(array(
            'action'    => 'status_change',
            'old_value' => $old_status,
            'new_value' => $new_status,
            'notes'     => $notes,
        ));
    }
    
    /**
     * Create audit entry for data edit
     * 
     * @param string $field
     * @param mixed  $old_value
     * @param mixed  $new_value
     * @param string $notes
     * @return TPAK_Audit_Entry
     */
    public static function create_data_edit($field, $old_value, $new_value, $notes = '') {
        return new self(array(
            'action'    => 'data_edit',
            'old_value' => array('field' => $field, 'value' => $old_value),
            'new_value' => array('field' => $field, 'value' => $new_value),
            'notes'     => $notes,
        ));
    }
    
    /**
     * Create audit entry for user assignment
     * 
     * @param int    $old_user_id
     * @param int    $new_user_id
     * @param string $notes
     * @return TPAK_Audit_Entry
     */
    public static function create_user_assignment($old_user_id, $new_user_id, $notes = '') {
        return new self(array(
            'action'    => 'user_assignment',
            'old_value' => $old_user_id,
            'new_value' => $new_user_id,
            'notes'     => $notes,
        ));
    }
}