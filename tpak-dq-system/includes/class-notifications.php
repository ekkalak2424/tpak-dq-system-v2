<?php
/**
 * Notifications Class
 * 
 * Handles email notifications and messaging
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Notifications Class
 */
class TPAK_Notifications {
    
    /**
     * Single instance
     * 
     * @var TPAK_Notifications
     */
    private static $instance = null;
    
    /**
     * Notification types
     */
    const TYPE_ASSIGNMENT = 'assignment';
    const TYPE_STATUS_CHANGE = 'status_change';
    const TYPE_REJECTION = 'rejection';
    const TYPE_APPROVAL = 'approval';
    
    /**
     * Get instance
     * 
     * @return TPAK_Notifications
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
        add_action('tpak_dq_status_changed', array($this, 'handle_status_change_notification'), 10, 4);
        add_action('tpak_dq_data_assigned', array($this, 'handle_assignment_notification'), 10, 3);
        add_filter('wp_mail_content_type', array($this, 'set_html_content_type'));
    }
    
    /**
     * Check if notifications are enabled
     * 
     * @return bool
     */
    public function are_notifications_enabled() {
        $settings = get_option('tpak_dq_notification_settings', array());
        return isset($settings['enabled']) ? (bool) $settings['enabled'] : true;
    }
    
    /**
     * Get notification settings
     * 
     * @return array
     */
    public function get_notification_settings() {
        return get_option('tpak_dq_notification_settings', array(
            'enabled' => true,
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
            'include_data_details' => true,
            'include_action_links' => true
        ));
    }
    
    /**
     * Update notification settings
     * 
     * @param array $settings
     * @return bool
     */
    public function update_notification_settings($settings) {
        $current_settings = $this->get_notification_settings();
        $updated_settings = wp_parse_args($settings, $current_settings);
        
        return update_option('tpak_dq_notification_settings', $updated_settings);
    }
    
    /**
     * Handle status change notification
     * 
     * @param int $post_id
     * @param string $old_status
     * @param string $new_status
     * @param int $user_id
     */
    public function handle_status_change_notification($post_id, $old_status, $new_status, $user_id) {
        if (!$this->are_notifications_enabled()) {
            return;
        }
        
        // Determine who should be notified based on new status
        $recipient_user_id = $this->get_status_recipient($new_status, $post_id);
        
        if (!$recipient_user_id) {
            return;
        }
        
        $notification_type = $this->get_notification_type_from_status($new_status);
        
        $this->send_notification(
            $recipient_user_id,
            $notification_type,
            $post_id,
            array(
                'old_status' => $old_status,
                'new_status' => $new_status,
                'changed_by' => $user_id
            )
        );
    }
    
    /**
     * Handle assignment notification
     * 
     * @param int $post_id
     * @param int $user_id
     * @param string $status
     */
    public function handle_assignment_notification($post_id, $user_id, $status) {
        if (!$this->are_notifications_enabled()) {
            return;
        }
        
        $this->send_notification(
            $user_id,
            self::TYPE_ASSIGNMENT,
            $post_id,
            array('status' => $status)
        );
    }
    
    /**
     * Send notification email
     * 
     * @param int $user_id
     * @param string $type
     * @param int $post_id
     * @param array $context
     * @return bool
     */
    public function send_notification($user_id, $type, $post_id, $context = array()) {
        try {
            if (!$this->are_notifications_enabled()) {
                return false;
            }
            
            $user = get_user_by('id', $user_id);
            if (!$user) {
                $this->log_error('Invalid user ID for notification', array('user_id' => $user_id));
                return false;
            }
            
            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'tpak_survey_data') {
                $this->log_error('Invalid post ID for notification', array('post_id' => $post_id));
                return false;
            }
            
            $template_data = $this->prepare_template_data($user, $post, $type, $context);
            $subject = $this->get_notification_subject($type, $template_data);
            $message = $this->get_notification_message($type, $template_data);
            $headers = $this->get_notification_headers();
            
            $sent = wp_mail($user->user_email, $subject, $message, $headers);
            
            if (!$sent) {
                $this->log_error('Failed to send notification email', array(
                    'user_id' => $user_id,
                    'type' => $type,
                    'post_id' => $post_id
                ));
                return false;
            }
            
            // Log successful notification
            $this->log_notification_sent($user_id, $type, $post_id, $context);
            
            return true;
            
        } catch (Exception $e) {
            $this->log_error('Exception in send_notification', array(
                'message' => $e->getMessage(),
                'user_id' => $user_id,
                'type' => $type,
                'post_id' => $post_id
            ));
            return false;
        }
    }
    
    /**
     * Get notification template
     * 
     * @param string $type
     * @param array $data
     * @return string
     */
    public function get_notification_template($type, $data) {
        $templates = array(
            self::TYPE_ASSIGNMENT => $this->get_assignment_template($data),
            self::TYPE_STATUS_CHANGE => $this->get_status_change_template($data),
            self::TYPE_REJECTION => $this->get_rejection_template($data),
            self::TYPE_APPROVAL => $this->get_approval_template($data)
        );
        
        return isset($templates[$type]) ? $templates[$type] : '';
    }
    
    /**
     * Get assignment notification template
     * 
     * @param array $data
     * @return string
     */
    private function get_assignment_template($data) {
        $template = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $template .= '<h2 style="color: #0073aa;">New Task Assignment</h2>';
        $template .= '<p>Hello {user_name},</p>';
        $template .= '<p>You have been assigned a new task in the TPAK DQ System.</p>';
        $template .= '<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">';
        $template .= '<h3>Survey Data Details:</h3>';
        $template .= '<p><strong>Survey ID:</strong> {survey_id}</p>';
        $template .= '<p><strong>Response ID:</strong> {response_id}</p>';
        $template .= '<p><strong>Status:</strong> {status}</p>';
        $template .= '<p><strong>Assigned Date:</strong> {assigned_date}</p>';
        $template .= '</div>';
        
        if ($data['include_action_links']) {
            $template .= '<p><a href="{action_link}" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">View Task</a></p>';
        }
        
        $template .= '<p>Please log in to the system to review and process this task.</p>';
        $template .= '<p>Best regards,<br>TPAK DQ System</p>';
        $template .= '</div>';
        
        return $template;
    }
    
    /**
     * Get status change notification template
     * 
     * @param array $data
     * @return string
     */
    private function get_status_change_template($data) {
        $template = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $template .= '<h2 style="color: #0073aa;">Status Update Notification</h2>';
        $template .= '<p>Hello {user_name},</p>';
        $template .= '<p>The status of survey data has been updated and requires your attention.</p>';
        $template .= '<div style="background: #f9f9f9; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">';
        $template .= '<h3>Survey Data Details:</h3>';
        $template .= '<p><strong>Survey ID:</strong> {survey_id}</p>';
        $template .= '<p><strong>Response ID:</strong> {response_id}</p>';
        $template .= '<p><strong>Previous Status:</strong> {old_status}</p>';
        $template .= '<p><strong>New Status:</strong> {new_status}</p>';
        $template .= '<p><strong>Changed By:</strong> {changed_by}</p>';
        $template .= '<p><strong>Changed Date:</strong> {changed_date}</p>';
        $template .= '</div>';
        
        if ($data['include_action_links']) {
            $template .= '<p><a href="{action_link}" style="background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">Review Data</a></p>';
        }
        
        $template .= '<p>Please log in to the system to review this update.</p>';
        $template .= '<p>Best regards,<br>TPAK DQ System</p>';
        $template .= '</div>';
        
        return $template;
    }
    
    /**
     * Get rejection notification template
     * 
     * @param array $data
     * @return string
     */
    private function get_rejection_template($data) {
        $template = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $template .= '<h2 style="color: #dc3232;">Data Rejected</h2>';
        $template .= '<p>Hello {user_name},</p>';
        $template .= '<p>Survey data has been rejected and returned for revision.</p>';
        $template .= '<div style="background: #fef7f7; padding: 15px; border-left: 4px solid #dc3232; margin: 20px 0;">';
        $template .= '<h3>Survey Data Details:</h3>';
        $template .= '<p><strong>Survey ID:</strong> {survey_id}</p>';
        $template .= '<p><strong>Response ID:</strong> {response_id}</p>';
        $template .= '<p><strong>Status:</strong> {status}</p>';
        $template .= '<p><strong>Rejected By:</strong> {rejected_by}</p>';
        $template .= '<p><strong>Rejection Date:</strong> {rejection_date}</p>';
        if (isset($data['rejection_reason'])) {
            $template .= '<p><strong>Reason:</strong> {rejection_reason}</p>';
        }
        $template .= '</div>';
        
        if ($data['include_action_links']) {
            $template .= '<p><a href="{action_link}" style="background: #dc3232; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;">Review and Revise</a></p>';
        }
        
        $template .= '<p>Please review the feedback and make necessary corrections.</p>';
        $template .= '<p>Best regards,<br>TPAK DQ System</p>';
        $template .= '</div>';
        
        return $template;
    }
    
    /**
     * Get approval notification template
     * 
     * @param array $data
     * @return string
     */
    private function get_approval_template($data) {
        $template = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $template .= '<h2 style="color: #46b450;">Data Approved</h2>';
        $template .= '<p>Hello {user_name},</p>';
        $template .= '<p>Survey data has been approved and processed successfully.</p>';
        $template .= '<div style="background: #f7fef7; padding: 15px; border-left: 4px solid #46b450; margin: 20px 0;">';
        $template .= '<h3>Survey Data Details:</h3>';
        $template .= '<p><strong>Survey ID:</strong> {survey_id}</p>';
        $template .= '<p><strong>Response ID:</strong> {response_id}</p>';
        $template .= '<p><strong>Status:</strong> {status}</p>';
        $template .= '<p><strong>Approved By:</strong> {approved_by}</p>';
        $template .= '<p><strong>Approval Date:</strong> {approval_date}</p>';
        $template .= '</div>';
        
        $template .= '<p>Thank you for your contribution to the data quality process.</p>';
        $template .= '<p>Best regards,<br>TPAK DQ System</p>';
        $template .= '</div>';
        
        return $template;
    }
    
    /**
     * Prepare template data
     * 
     * @param WP_User $user
     * @param WP_Post $post
     * @param string $type
     * @param array $context
     * @return array
     */
    private function prepare_template_data($user, $post, $type, $context) {
        $settings = $this->get_notification_settings();
        $survey_data = get_post_meta($post->ID, '_tpak_survey_data', true);
        
        $data = array(
            'user_name' => $user->display_name,
            'user_email' => $user->user_email,
            'survey_id' => isset($survey_data['survey_id']) ? $survey_data['survey_id'] : 'N/A',
            'response_id' => isset($survey_data['response_id']) ? $survey_data['response_id'] : 'N/A',
            'status' => $post->post_status,
            'post_id' => $post->ID,
            'site_name' => get_bloginfo('name'),
            'site_url' => get_site_url(),
            'assigned_date' => current_time('mysql'),
            'changed_date' => current_time('mysql'),
            'include_data_details' => $settings['include_data_details'],
            'include_action_links' => $settings['include_action_links']
        );
        
        // Add context-specific data
        if (isset($context['old_status'])) {
            $data['old_status'] = $this->get_status_label($context['old_status']);
        }
        if (isset($context['new_status'])) {
            $data['new_status'] = $this->get_status_label($context['new_status']);
        }
        if (isset($context['changed_by'])) {
            $changed_by_user = get_user_by('id', $context['changed_by']);
            $data['changed_by'] = $changed_by_user ? $changed_by_user->display_name : 'System';
        }
        
        // Add action link
        if ($settings['include_action_links']) {
            $data['action_link'] = admin_url('post.php?post=' . $post->ID . '&action=edit');
        }
        
        return $data;
    }
    
    /**
     * Get notification subject
     * 
     * @param string $type
     * @param array $data
     * @return string
     */
    private function get_notification_subject($type, $data) {
        $subjects = array(
            self::TYPE_ASSIGNMENT => '[{site_name}] New Task Assignment - Survey {survey_id}',
            self::TYPE_STATUS_CHANGE => '[{site_name}] Status Update - Survey {survey_id}',
            self::TYPE_REJECTION => '[{site_name}] Data Rejected - Survey {survey_id}',
            self::TYPE_APPROVAL => '[{site_name}] Data Approved - Survey {survey_id}'
        );
        
        $subject = isset($subjects[$type]) ? $subjects[$type] : '[{site_name}] TPAK DQ Notification';
        
        return $this->replace_template_variables($subject, $data);
    }
    
    /**
     * Get notification message
     * 
     * @param string $type
     * @param array $data
     * @return string
     */
    private function get_notification_message($type, $data) {
        $template = $this->get_notification_template($type, $data);
        return $this->replace_template_variables($template, $data);
    }
    
    /**
     * Get notification headers
     * 
     * @return array
     */
    private function get_notification_headers() {
        $settings = $this->get_notification_settings();
        
        $headers = array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        
        if (!empty($settings['from_name']) && !empty($settings['from_email'])) {
            $headers[] = 'From: ' . $settings['from_name'] . ' <' . $settings['from_email'] . '>';
        }
        
        return $headers;
    }
    
    /**
     * Replace template variables
     * 
     * @param string $template
     * @param array $data
     * @return string
     */
    private function replace_template_variables($template, $data) {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }
    
    /**
     * Get status recipient user ID
     * 
     * @param string $status
     * @param int $post_id
     * @return int|false
     */
    private function get_status_recipient($status, $post_id) {
        // This would integrate with the role management system
        // For now, return false - will be implemented when role system is ready
        return false;
    }
    
    /**
     * Get notification type from status
     * 
     * @param string $status
     * @return string
     */
    private function get_notification_type_from_status($status) {
        $rejection_statuses = array('rejected_by_b', 'rejected_by_c');
        $approval_statuses = array('finalized', 'finalized_by_sampling');
        
        if (in_array($status, $rejection_statuses)) {
            return self::TYPE_REJECTION;
        } elseif (in_array($status, $approval_statuses)) {
            return self::TYPE_APPROVAL;
        } else {
            return self::TYPE_STATUS_CHANGE;
        }
    }
    
    /**
     * Get status label
     * 
     * @param string $status
     * @return string
     */
    private function get_status_label($status) {
        $labels = array(
            'pending_a' => 'Pending Interviewer Review',
            'pending_b' => 'Pending Supervisor Review',
            'pending_c' => 'Pending Examiner Review',
            'rejected_by_b' => 'Rejected by Supervisor',
            'rejected_by_c' => 'Rejected by Examiner',
            'finalized' => 'Finalized',
            'finalized_by_sampling' => 'Finalized by Sampling'
        );
        
        return isset($labels[$status]) ? $labels[$status] : ucfirst(str_replace('_', ' ', $status));
    }
    
    /**
     * Set HTML content type for emails
     * 
     * @return string
     */
    public function set_html_content_type() {
        return 'text/html';
    }
    
    /**
     * Log notification sent
     * 
     * @param int $user_id
     * @param string $type
     * @param int $post_id
     * @param array $context
     */
    private function log_notification_sent($user_id, $type, $post_id, $context) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => $user_id,
            'type' => $type,
            'post_id' => $post_id,
            'context' => $context,
            'status' => 'sent'
        );
        
        $log = get_option('tpak_dq_notification_log', array());
        $log[] = $log_entry;
        
        // Keep only last 1000 entries
        if (count($log) > 1000) {
            $log = array_slice($log, -1000);
        }
        
        update_option('tpak_dq_notification_log', $log);
    }
    
    /**
     * Log notification error
     * 
     * @param string $message
     * @param array $context
     */
    private function log_error($message, $context = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'level' => 'error',
            'message' => $message,
            'context' => $context
        );
        
        $error_log = get_option('tpak_dq_notification_errors', array());
        $error_log[] = $log_entry;
        
        // Keep only last 500 error entries
        if (count($error_log) > 500) {
            $error_log = array_slice($error_log, -500);
        }
        
        update_option('tpak_dq_notification_errors', $error_log);
        
        // Also log to WordPress error log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TPAK DQ Notifications: ' . $message . ' - Context: ' . wp_json_encode($context));
        }
    }
    
    /**
     * Get notification log
     * 
     * @param int $limit
     * @return array
     */
    public function get_notification_log($limit = 100) {
        $log = get_option('tpak_dq_notification_log', array());
        return array_slice(array_reverse($log), 0, $limit);
    }
    
    /**
     * Get notification errors
     * 
     * @param int $limit
     * @return array
     */
    public function get_notification_errors($limit = 50) {
        $errors = get_option('tpak_dq_notification_errors', array());
        return array_slice(array_reverse($errors), 0, $limit);
    }
    
    /**
     * Clear notification log
     * 
     * @return bool
     */
    public function clear_notification_log() {
        return delete_option('tpak_dq_notification_log');
    }
    
    /**
     * Clear notification errors
     * 
     * @return bool
     */
    public function clear_notification_errors() {
        return delete_option('tpak_dq_notification_errors');
    }
    
    /**
     * Test notification sending
     * 
     * @param string $email
     * @return bool
     */
    public function send_test_notification($email) {
        if (!is_email($email)) {
            return false;
        }
        
        $subject = '[' . get_bloginfo('name') . '] TPAK DQ System Test Notification';
        $message = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $message .= '<h2 style="color: #0073aa;">Test Notification</h2>';
        $message .= '<p>This is a test notification from the TPAK DQ System.</p>';
        $message .= '<p>If you received this email, the notification system is working correctly.</p>';
        $message .= '<p>Sent at: ' . current_time('mysql') . '</p>';
        $message .= '<p>Best regards,<br>TPAK DQ System</p>';
        $message .= '</div>';
        
        $headers = $this->get_notification_headers();
        
        return wp_mail($email, $subject, $message, $headers);
    }
}