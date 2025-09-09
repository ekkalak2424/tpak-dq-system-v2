<?php
/**
 * TPAK Error Handler Class
 *
 * Centralized error handling with user-friendly messages
 * and comprehensive error reporting for administrators.
 *
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Error_Handler {
    
    /**
     * Error types
     */
    const TYPE_API = 'api';
    const TYPE_VALIDATION = 'validation';
    const TYPE_WORKFLOW = 'workflow';
    const TYPE_CRON = 'cron';
    const TYPE_NOTIFICATION = 'notification';
    const TYPE_SECURITY = 'security';
    const TYPE_SYSTEM = 'system';
    
    /**
     * Logger instance
     *
     * @var TPAK_Logger
     */
    private $logger;
    
    /**
     * Error messages for users
     *
     * @var array
     */
    private $user_messages = [];
    
    /**
     * Admin error notices
     *
     * @var array
     */
    private $admin_notices = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = TPAK_Logger::get_instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_action('wp_ajax_tpak_dismiss_error', [$this, 'dismiss_error_notice']);
    }
    
    /**
     * Handle API errors
     *
     * @param string $endpoint API endpoint
     * @param Exception|string $error Error object or message
     * @param array $context Additional context
     * @return WP_Error
     */
    public function handle_api_error($endpoint, $error, $context = []) {
        $error_message = is_object($error) ? $error->getMessage() : $error;
        $error_code = is_object($error) && method_exists($error, 'getCode') ? $error->getCode() : 'api_error';
        
        // Log the error
        $this->logger->log_api_error($endpoint, $error_message, $context);
        
        // Create user-friendly message
        $user_message = $this->get_user_friendly_api_message($endpoint, $error_code);
        
        // Add admin notice for administrators
        if (current_user_can('manage_options')) {
            $this->add_admin_notice(
                sprintf(__('API Error: %s - %s', 'tpak-dq-system'), $endpoint, $error_message),
                'error'
            );
        }
        
        return new WP_Error($error_code, $user_message, [
            'endpoint' => $endpoint,
            'original_error' => $error_message,
            'context' => $context
        ]);
    }
    
    /**
     * Handle validation errors
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     * @param string $custom_message Custom error message
     * @return WP_Error
     */
    public function handle_validation_error($field, $value, $rule, $custom_message = '') {
        // Log the validation error
        $this->logger->log_validation_error($field, $value, $rule, $custom_message);
        
        // Create user-friendly message
        $user_message = $custom_message ?: $this->get_user_friendly_validation_message($field, $rule);
        
        return new WP_Error('validation_error', $user_message, [
            'field' => $field,
            'rule' => $rule,
            'value' => $value
        ]);
    }
    
    /**
     * Handle workflow errors
     *
     * @param string $action Workflow action
     * @param int $data_id Data ID
     * @param Exception|string $error Error object or message
     * @return WP_Error
     */
    public function handle_workflow_error($action, $data_id, $error) {
        $error_message = is_object($error) ? $error->getMessage() : $error;
        $user_id = get_current_user_id();
        
        // Log the workflow error
        $this->logger->log_workflow_error($action, $data_id, $user_id, $error_message);
        
        // Create user-friendly message
        $user_message = $this->get_user_friendly_workflow_message($action, $error_message);
        
        return new WP_Error('workflow_error', $user_message, [
            'action' => $action,
            'data_id' => $data_id,
            'original_error' => $error_message
        ]);
    }
    
    /**
     * Handle cron job errors
     *
     * @param string $job_name Cron job name
     * @param Exception|string $error Error object or message
     * @param array $context Additional context
     * @return WP_Error
     */
    public function handle_cron_error($job_name, $error, $context = []) {
        $error_message = is_object($error) ? $error->getMessage() : $error;
        
        // Log the cron error
        $this->logger->log_cron_error($job_name, $error_message, $context);
        
        // Add admin notice for cron failures
        $this->add_admin_notice(
            sprintf(__('Scheduled task "%s" failed: %s', 'tpak-dq-system'), $job_name, $error_message),
            'error',
            true // persistent
        );
        
        return new WP_Error('cron_error', __('Scheduled task failed. Administrator has been notified.', 'tpak-dq-system'), [
            'job_name' => $job_name,
            'original_error' => $error_message,
            'context' => $context
        ]);
    }
    
    /**
     * Handle notification errors
     *
     * @param string $type Notification type
     * @param string $recipient Recipient
     * @param Exception|string $error Error object or message
     * @return WP_Error
     */
    public function handle_notification_error($type, $recipient, $error) {
        $error_message = is_object($error) ? $error->getMessage() : $error;
        
        // Log the notification error
        $this->logger->log_notification_error($type, $recipient, $error_message);
        
        // Create user-friendly message
        $user_message = __('Failed to send notification. Please check your email settings.', 'tpak-dq-system');
        
        return new WP_Error('notification_error', $user_message, [
            'type' => $type,
            'recipient' => $recipient,
            'original_error' => $error_message
        ]);
    }
    
    /**
     * Handle security errors
     *
     * @param string $event Security event type
     * @param string $description Event description
     * @param array $context Additional context
     * @return WP_Error
     */
    public function handle_security_error($event, $description, $context = []) {
        // Log the security event
        $this->logger->log_security_event($event, $description, $context);
        
        // Add admin notice for security events
        if (current_user_can('manage_options')) {
            $this->add_admin_notice(
                sprintf(__('Security Event: %s - %s', 'tpak-dq-system'), $event, $description),
                'warning',
                true // persistent
            );
        }
        
        return new WP_Error('security_error', __('Access denied for security reasons.', 'tpak-dq-system'), [
            'event' => $event,
            'description' => $description,
            'context' => $context
        ]);
    }
    
    /**
     * Handle system errors
     *
     * @param Exception|string $error Error object or message
     * @param array $context Additional context
     * @return WP_Error
     */
    public function handle_system_error($error, $context = []) {
        $error_message = is_object($error) ? $error->getMessage() : $error;
        $error_code = is_object($error) && method_exists($error, 'getCode') ? $error->getCode() : 'system_error';
        
        // Log the system error
        $this->logger->error($error_message, TPAK_Logger::CATEGORY_SYSTEM, $context);
        
        // Add admin notice for system errors
        if (current_user_can('manage_options')) {
            $this->add_admin_notice(
                sprintf(__('System Error: %s', 'tpak-dq-system'), $error_message),
                'error'
            );
        }
        
        return new WP_Error($error_code, __('A system error occurred. Please try again or contact support.', 'tpak-dq-system'), [
            'original_error' => $error_message,
            'context' => $context
        ]);
    }
    
    /**
     * Get user-friendly API error message
     *
     * @param string $endpoint
     * @param string $error_code
     * @return string
     */
    private function get_user_friendly_api_message($endpoint, $error_code) {
        $messages = [
            'connection_failed' => __('Unable to connect to the survey system. Please check your connection settings.', 'tpak-dq-system'),
            'authentication_failed' => __('Authentication failed. Please check your username and password.', 'tpak-dq-system'),
            'invalid_survey_id' => __('Invalid survey ID. Please verify the survey exists and is accessible.', 'tpak-dq-system'),
            'rate_limit_exceeded' => __('Too many requests. Please wait a moment and try again.', 'tpak-dq-system'),
            'server_error' => __('The survey server is experiencing issues. Please try again later.', 'tpak-dq-system')
        ];
        
        return isset($messages[$error_code]) ? $messages[$error_code] : 
               __('An error occurred while communicating with the survey system.', 'tpak-dq-system');
    }
    
    /**
     * Get user-friendly validation error message
     *
     * @param string $field
     * @param string $rule
     * @return string
     */
    private function get_user_friendly_validation_message($field, $rule) {
        $field_labels = [
            'api_url' => __('API URL', 'tpak-dq-system'),
            'username' => __('Username', 'tpak-dq-system'),
            'password' => __('Password', 'tpak-dq-system'),
            'survey_id' => __('Survey ID', 'tpak-dq-system'),
            'email' => __('Email Address', 'tpak-dq-system'),
            'sampling_percentage' => __('Sampling Percentage', 'tpak-dq-system')
        ];
        
        $rule_messages = [
            'required' => __('%s is required.', 'tpak-dq-system'),
            'url' => __('%s must be a valid URL.', 'tpak-dq-system'),
            'email' => __('%s must be a valid email address.', 'tpak-dq-system'),
            'numeric' => __('%s must be a number.', 'tpak-dq-system'),
            'min_length' => __('%s is too short.', 'tpak-dq-system'),
            'max_length' => __('%s is too long.', 'tpak-dq-system'),
            'range' => __('%s must be within the allowed range.', 'tpak-dq-system')
        ];
        
        $field_label = isset($field_labels[$field]) ? $field_labels[$field] : ucfirst(str_replace('_', ' ', $field));
        $message_template = isset($rule_messages[$rule]) ? $rule_messages[$rule] : __('%s is invalid.', 'tpak-dq-system');
        
        return sprintf($message_template, $field_label);
    }
    
    /**
     * Get user-friendly workflow error message
     *
     * @param string $action
     * @param string $error_message
     * @return string
     */
    private function get_user_friendly_workflow_message($action, $error_message) {
        $action_messages = [
            'approve' => __('Unable to approve this item. Please check your permissions and try again.', 'tpak-dq-system'),
            'reject' => __('Unable to reject this item. Please check your permissions and try again.', 'tpak-dq-system'),
            'assign' => __('Unable to assign this item. Please check your permissions and try again.', 'tpak-dq-system'),
            'transition' => __('Unable to change the status of this item. Please check your permissions and try again.', 'tpak-dq-system')
        ];
        
        return isset($action_messages[$action]) ? $action_messages[$action] : 
               __('Unable to complete the requested action. Please try again.', 'tpak-dq-system');
    }
    
    /**
     * Add admin notice
     *
     * @param string $message
     * @param string $type (success, warning, error, info)
     * @param bool $persistent Whether notice should persist across page loads
     */
    public function add_admin_notice($message, $type = 'info', $persistent = false) {
        $notice = [
            'message' => $message,
            'type' => $type,
            'dismissible' => true,
            'persistent' => $persistent
        ];
        
        if ($persistent) {
            // Store persistent notices in database
            $notices = get_option('tpak_dq_admin_notices', []);
            $notices[] = $notice;
            update_option('tpak_dq_admin_notices', $notices);
        } else {
            // Store temporary notices in session/transient
            $this->admin_notices[] = $notice;
        }
    }
    
    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        // Display temporary notices
        foreach ($this->admin_notices as $notice) {
            $this->render_admin_notice($notice);
        }
        
        // Display persistent notices
        $persistent_notices = get_option('tpak_dq_admin_notices', []);
        foreach ($persistent_notices as $index => $notice) {
            $notice['data_index'] = $index;
            $this->render_admin_notice($notice);
        }
    }
    
    /**
     * Render admin notice HTML
     *
     * @param array $notice
     */
    private function render_admin_notice($notice) {
        $classes = ['notice', 'notice-' . $notice['type']];
        
        if ($notice['dismissible']) {
            $classes[] = 'is-dismissible';
        }
        
        $data_attrs = '';
        if (isset($notice['data_index'])) {
            $data_attrs = 'data-notice-index="' . esc_attr($notice['data_index']) . '"';
        }
        
        echo '<div class="' . esc_attr(implode(' ', $classes)) . '" ' . $data_attrs . '>';
        echo '<p>' . wp_kses_post($notice['message']) . '</p>';
        echo '</div>';
    }
    
    /**
     * Dismiss error notice (AJAX handler)
     */
    public function dismiss_error_notice() {
        check_ajax_referer('tpak_dismiss_error', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'tpak-dq-system'));
        }
        
        $notice_index = intval($_POST['notice_index']);
        
        $notices = get_option('tpak_dq_admin_notices', []);
        if (isset($notices[$notice_index])) {
            unset($notices[$notice_index]);
            $notices = array_values($notices); // Re-index array
            update_option('tpak_dq_admin_notices', $notices);
        }
        
        wp_send_json_success();
    }
    
    /**
     * Clear all admin notices
     */
    public function clear_admin_notices() {
        delete_option('tpak_dq_admin_notices');
        $this->admin_notices = [];
    }
    
    /**
     * Get error summary for dashboard
     *
     * @return array
     */
    public function get_error_summary() {
        $log_stats = $this->logger->get_log_stats();
        
        return [
            'recent_errors' => $log_stats['recent_errors'],
            'total_errors' => array_sum(array_column(
                array_filter($log_stats['by_level'], function($item) {
                    return in_array($item['level'], ['error', 'critical']);
                }),
                'count'
            )),
            'by_category' => $log_stats['by_category']
        ];
    }
}