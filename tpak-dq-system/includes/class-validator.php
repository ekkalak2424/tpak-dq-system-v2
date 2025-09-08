<?php
/**
 * Validator Class
 * 
 * Handles comprehensive data validation
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Validator Class
 */
class TPAK_Validator {
    
    /**
     * Single instance
     * 
     * @var TPAK_Validator
     */
    private static $instance = null;
    
    /**
     * Get instance
     * 
     * @return TPAK_Validator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Validation errors
     * 
     * @var array
     */
    private $errors = array();
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook into WordPress validation points
        add_filter('pre_update_option_tpak_dq_settings', array($this, 'validate_settings'), 10, 2);
        add_action('save_post_tpak_survey_data', array($this, 'validate_survey_data_post'), 10, 2);
    }
    
    /**
     * Validate email address
     * 
     * @param string $email
     * @return bool
     */
    public function validate_email($email) {
        if (empty($email)) {
            $this->add_error('email', __('Email address is required.', 'tpak-dq-system'));
            return false;
        }
        
        if (!is_email($email)) {
            $this->add_error('email', __('Please enter a valid email address.', 'tpak-dq-system'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url
     * @param array  $allowed_schemes
     * @return bool
     */
    public function validate_url($url, $allowed_schemes = array('http', 'https')) {
        if (empty($url)) {
            $this->add_error('url', __('URL is required.', 'tpak-dq-system'));
            return false;
        }
        
        $validated_url = wp_http_validate_url($url);
        if (!$validated_url) {
            $this->add_error('url', __('Please enter a valid URL.', 'tpak-dq-system'));
            return false;
        }
        
        $parsed_url = wp_parse_url($url);
        if (!in_array($parsed_url['scheme'], $allowed_schemes)) {
            $this->add_error('url', sprintf(
                __('URL must use one of these schemes: %s', 'tpak-dq-system'),
                implode(', ', $allowed_schemes)
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate LimeSurvey API URL
     * 
     * @param string $url
     * @return bool
     */
    public function validate_limesurvey_url($url) {
        if (!$this->validate_url($url)) {
            return false;
        }
        
        // Check if URL ends with proper LimeSurvey API endpoint
        $expected_endpoints = array(
            '/admin/remotecontrol',
            '/index.php/admin/remotecontrol',
            '/index.php?r=admin/remotecontrol'
        );
        
        $has_valid_endpoint = false;
        foreach ($expected_endpoints as $endpoint) {
            if (strpos($url, $endpoint) !== false) {
                $has_valid_endpoint = true;
                break;
            }
        }
        
        if (!$has_valid_endpoint) {
            $this->add_error('limesurvey_url', __('URL must point to LimeSurvey RemoteControl API endpoint.', 'tpak-dq-system'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate numeric ID
     * 
     * @param mixed  $id
     * @param string $field_name
     * @param bool   $allow_zero
     * @return bool
     */
    public function validate_numeric_id($id, $field_name = 'ID', $allow_zero = false) {
        if (empty($id) && !$allow_zero) {
            $this->add_error($field_name, sprintf(__('%s is required.', 'tpak-dq-system'), $field_name));
            return false;
        }
        
        if (!is_numeric($id)) {
            $this->add_error($field_name, sprintf(__('%s must be a number.', 'tpak-dq-system'), $field_name));
            return false;
        }
        
        $numeric_id = (int) $id;
        if ($numeric_id < 0 || (!$allow_zero && $numeric_id === 0)) {
            $this->add_error($field_name, sprintf(__('%s must be a positive number.', 'tpak-dq-system'), $field_name));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate percentage
     * 
     * @param mixed  $percentage
     * @param string $field_name
     * @return bool
     */
    public function validate_percentage($percentage, $field_name = 'Percentage') {
        if (empty($percentage) && $percentage !== '0') {
            $this->add_error($field_name, sprintf(__('%s is required.', 'tpak-dq-system'), $field_name));
            return false;
        }
        
        if (!is_numeric($percentage)) {
            $this->add_error($field_name, sprintf(__('%s must be a number.', 'tpak-dq-system'), $field_name));
            return false;
        }
        
        $numeric_percentage = (float) $percentage;
        if ($numeric_percentage < 1 || $numeric_percentage > 100) {
            $this->add_error($field_name, sprintf(__('%s must be between 1 and 100.', 'tpak-dq-system'), $field_name));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate text field
     * 
     * @param string $text
     * @param string $field_name
     * @param int    $min_length
     * @param int    $max_length
     * @param bool   $required
     * @return bool
     */
    public function validate_text($text, $field_name = 'Text', $min_length = 0, $max_length = 255, $required = true) {
        if (empty($text)) {
            if ($required) {
                $this->add_error($field_name, sprintf(__('%s is required.', 'tpak-dq-system'), $field_name));
                return false;
            }
            return true;
        }
        
        $text_length = strlen($text);
        
        if ($min_length > 0 && $text_length < $min_length) {
            $this->add_error($field_name, sprintf(
                __('%s must be at least %d characters long.', 'tpak-dq-system'),
                $field_name,
                $min_length
            ));
            return false;
        }
        
        if ($max_length > 0 && $text_length > $max_length) {
            $this->add_error($field_name, sprintf(
                __('%s must not exceed %d characters.', 'tpak-dq-system'),
                $field_name,
                $max_length
            ));
            return false;
        }
        
        // Check for potentially dangerous content
        if ($this->contains_malicious_content($text)) {
            $this->add_error($field_name, sprintf(__('%s contains invalid content.', 'tpak-dq-system'), $field_name));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate JSON string
     * 
     * @param string $json
     * @param string $field_name
     * @param bool   $required
     * @return bool
     */
    public function validate_json($json, $field_name = 'JSON', $required = true) {
        if (empty($json)) {
            if ($required) {
                $this->add_error($field_name, sprintf(__('%s is required.', 'tpak-dq-system'), $field_name));
                return false;
            }
            return true;
        }
        
        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->add_error($field_name, sprintf(
                __('%s is not valid JSON: %s', 'tpak-dq-system'),
                $field_name,
                json_last_error_msg()
            ));
            return false;
        }
        
        // Check JSON size (prevent extremely large JSON)
        if (strlen($json) > 1048576) { // 1MB limit
            $this->add_error($field_name, sprintf(__('%s is too large (maximum 1MB).', 'tpak-dq-system'), $field_name));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate date string
     * 
     * @param string $date
     * @param string $format
     * @param string $field_name
     * @param bool   $required
     * @return bool
     */
    public function validate_date($date, $format = 'Y-m-d H:i:s', $field_name = 'Date', $required = true) {
        if (empty($date)) {
            if ($required) {
                $this->add_error($field_name, sprintf(__('%s is required.', 'tpak-dq-system'), $field_name));
                return false;
            }
            return true;
        }
        
        $datetime = DateTime::createFromFormat($format, $date);
        if (!$datetime || $datetime->format($format) !== $date) {
            $this->add_error($field_name, sprintf(
                __('%s is not a valid date. Expected format: %s', 'tpak-dq-system'),
                $field_name,
                $format
            ));
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate API settings
     * 
     * @param array $settings
     * @return bool
     */
    public function validate_api_settings($settings) {
        $this->clear_errors();
        $is_valid = true;
        
        // Validate LimeSurvey URL
        if (!$this->validate_limesurvey_url($settings['limesurvey_url'] ?? '')) {
            $is_valid = false;
        }
        
        // Validate username
        if (!$this->validate_text($settings['username'] ?? '', 'Username', 1, 100)) {
            $is_valid = false;
        }
        
        // Validate password
        if (!$this->validate_text($settings['password'] ?? '', 'Password', 1, 255)) {
            $is_valid = false;
        }
        
        // Validate survey ID
        if (!$this->validate_numeric_id($settings['survey_id'] ?? '', 'Survey ID')) {
            $is_valid = false;
        }
        
        return $is_valid;
    }
    
    /**
     * Validate survey data structure
     * 
     * @param array $data
     * @return bool
     */
    public function validate_survey_data($data) {
        $this->clear_errors();
        $is_valid = true;
        
        if (!is_array($data)) {
            $this->add_error('survey_data', __('Survey data must be an array.', 'tpak-dq-system'));
            return false;
        }
        
        // Validate required fields
        $required_fields = array('survey_id', 'response_id');
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->add_error($field, sprintf(__('%s is required in survey data.', 'tpak-dq-system'), ucfirst(str_replace('_', ' ', $field))));
                $is_valid = false;
            }
        }
        
        // Validate survey ID
        if (isset($data['survey_id']) && !$this->validate_numeric_id($data['survey_id'], 'Survey ID')) {
            $is_valid = false;
        }
        
        // Validate response ID
        if (isset($data['response_id']) && !$this->validate_text($data['response_id'], 'Response ID', 1, 100)) {
            $is_valid = false;
        }
        
        // Validate response data if present
        if (isset($data['responses']) && !empty($data['responses'])) {
            if (!is_array($data['responses'])) {
                $this->add_error('responses', __('Response data must be an array.', 'tpak-dq-system'));
                $is_valid = false;
            } else {
                // Validate individual responses
                foreach ($data['responses'] as $question_id => $response) {
                    if (!$this->validate_survey_response($question_id, $response)) {
                        $is_valid = false;
                    }
                }
            }
        }
        
        return $is_valid;
    }
    
    /**
     * Validate individual survey response
     * 
     * @param string $question_id
     * @param mixed  $response
     * @return bool
     */
    public function validate_survey_response($question_id, $response) {
        // Validate question ID
        if (!$this->validate_text($question_id, 'Question ID', 1, 50)) {
            return false;
        }
        
        // Response can be string, number, or array (for multiple choice)
        if (is_array($response)) {
            foreach ($response as $value) {
                if (!$this->validate_text($value, 'Response Value', 0, 1000, false)) {
                    return false;
                }
            }
        } else {
            if (!$this->validate_text($response, 'Response', 0, 1000, false)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Validate workflow action
     * 
     * @param string $action
     * @param int    $post_id
     * @param int    $user_id
     * @return bool
     */
    public function validate_workflow_action($action, $post_id, $user_id = null) {
        $this->clear_errors();
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        // Validate action
        $valid_actions = array(
            'approve_to_supervisor',
            'reject_to_interviewer',
            'approve_to_examiner',
            'apply_sampling',
            'final_approval',
            'reject_to_supervisor'
        );
        
        if (!in_array($action, $valid_actions)) {
            $this->add_error('action', __('Invalid workflow action.', 'tpak-dq-system'));
            return false;
        }
        
        // Validate post exists and is correct type
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'tpak_survey_data') {
            $this->add_error('post', __('Invalid survey data post.', 'tpak-dq-system'));
            return false;
        }
        
        // Validate user permissions
        $current_status = get_post_meta($post_id, '_tpak_workflow_status', true);
        $roles = TPAK_Roles::get_instance();
        
        if (!$roles->can_user_access_status($user_id, $current_status)) {
            $this->add_error('permission', __('You do not have permission to perform this action.', 'tpak-dq-system'));
            return false;
        }
        
        // Validate action is appropriate for current status
        if (!$this->is_action_valid_for_status($action, $current_status)) {
            $this->add_error('action_status', __('This action is not valid for the current data status.', 'tpak-dq-system'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if action is valid for current status
     * 
     * @param string $action
     * @param string $status
     * @return bool
     */
    private function is_action_valid_for_status($action, $status) {
        $valid_transitions = array(
            'pending_a' => array('approve_to_supervisor'),
            'pending_b' => array('reject_to_interviewer', 'approve_to_examiner', 'apply_sampling'),
            'pending_c' => array('final_approval', 'reject_to_supervisor'),
            'rejected_by_b' => array('approve_to_supervisor'),
            'rejected_by_c' => array('approve_to_examiner', 'apply_sampling'),
        );
        
        return isset($valid_transitions[$status]) && in_array($action, $valid_transitions[$status]);
    }
    
    /**
     * Validate cron settings
     * 
     * @param array $settings
     * @return bool
     */
    public function validate_cron_settings($settings) {
        $this->clear_errors();
        $is_valid = true;
        
        // Validate import interval
        $valid_intervals = array('hourly', 'twicedaily', 'daily', 'weekly');
        if (!isset($settings['import_interval']) || !in_array($settings['import_interval'], $valid_intervals)) {
            $this->add_error('import_interval', __('Please select a valid import interval.', 'tpak-dq-system'));
            $is_valid = false;
        }
        
        // Validate survey ID
        if (!$this->validate_numeric_id($settings['survey_id'] ?? '', 'Survey ID')) {
            $is_valid = false;
        }
        
        return $is_valid;
    }
    
    /**
     * Validate notification settings
     * 
     * @param array $settings
     * @return bool
     */
    public function validate_notification_settings($settings) {
        $this->clear_errors();
        $is_valid = true;
        
        // Validate email notifications enabled flag
        if (isset($settings['email_enabled']) && !is_bool($settings['email_enabled'])) {
            $settings['email_enabled'] = (bool) $settings['email_enabled'];
        }
        
        // Validate sampling percentage
        if (!$this->validate_percentage($settings['sampling_percentage'] ?? '', 'Sampling Percentage')) {
            $is_valid = false;
        }
        
        // Validate notification email addresses if provided
        if (!empty($settings['notification_emails'])) {
            $emails = is_array($settings['notification_emails']) 
                ? $settings['notification_emails'] 
                : explode(',', $settings['notification_emails']);
            
            foreach ($emails as $email) {
                $email = trim($email);
                if (!empty($email) && !$this->validate_email($email)) {
                    $is_valid = false;
                }
            }
        }
        
        return $is_valid;
    }
    
    /**
     * Check for malicious content
     * 
     * @param string $content
     * @return bool
     */
    private function contains_malicious_content($content) {
        // Check for common malicious patterns
        $malicious_patterns = array(
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/<iframe\b/i',
            '/<object\b/i',
            '/<embed\b/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
        );
        
        foreach ($malicious_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add validation error
     * 
     * @param string $field
     * @param string $message
     */
    private function add_error($field, $message) {
        $this->errors[$field] = $message;
    }
    
    /**
     * Get validation errors
     * 
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Check if there are validation errors
     * 
     * @return bool
     */
    public function has_errors() {
        return !empty($this->errors);
    }
    
    /**
     * Clear validation errors
     */
    public function clear_errors() {
        $this->errors = array();
    }
    
    /**
     * Get formatted error messages
     * 
     * @return string
     */
    public function get_error_messages() {
        if (empty($this->errors)) {
            return '';
        }
        
        $messages = array();
        foreach ($this->errors as $field => $message) {
            $messages[] = $message;
        }
        
        return implode('<br>', $messages);
    }
    
    /**
     * Validate settings on save
     * 
     * @param mixed $new_value
     * @param mixed $old_value
     * @return mixed
     */
    public function validate_settings($new_value, $old_value) {
        if (!is_array($new_value)) {
            return $old_value;
        }
        
        // Validate API settings if present
        if (isset($new_value['api'])) {
            if (!$this->validate_api_settings($new_value['api'])) {
                add_settings_error(
                    'tpak_dq_settings',
                    'api_validation',
                    $this->get_error_messages(),
                    'error'
                );
                return $old_value;
            }
        }
        
        // Validate cron settings if present
        if (isset($new_value['cron'])) {
            if (!$this->validate_cron_settings($new_value['cron'])) {
                add_settings_error(
                    'tpak_dq_settings',
                    'cron_validation',
                    $this->get_error_messages(),
                    'error'
                );
                return $old_value;
            }
        }
        
        // Validate notification settings if present
        if (isset($new_value['notifications'])) {
            if (!$this->validate_notification_settings($new_value['notifications'])) {
                add_settings_error(
                    'tpak_dq_settings',
                    'notification_validation',
                    $this->get_error_messages(),
                    'error'
                );
                return $old_value;
            }
        }
        
        return $new_value;
    }
    
    /**
     * Validate survey data post on save
     * 
     * @param int     $post_id
     * @param WP_Post $post
     */
    public function validate_survey_data_post($post_id, $post) {
        // Skip validation for autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Validate survey data meta
        $survey_data = get_post_meta($post_id, '_tpak_survey_data', true);
        if (!empty($survey_data)) {
            if (!$this->validate_json($survey_data, 'Survey Data', false)) {
                // Log error but don't prevent save
                error_log('TPAK DQ System: Invalid survey data JSON for post ' . $post_id);
            }
        }
        
        // Validate workflow status
        $status = get_post_meta($post_id, '_tpak_workflow_status', true);
        if (!empty($status)) {
            $valid_statuses = array_keys(TPAK_Post_Types::get_instance()->get_workflow_statuses());
            if (!in_array($status, $valid_statuses)) {
                update_post_meta($post_id, '_tpak_workflow_status', 'pending_a');
            }
        }
    }
    
    /**
     * Sanitize and validate input data
     * 
     * @param mixed  $data
     * @param string $type
     * @return mixed
     */
    public function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($data);
                
            case 'url':
                return esc_url_raw($data);
                
            case 'text':
                return sanitize_text_field($data);
                
            case 'textarea':
                return sanitize_textarea_field($data);
                
            case 'html':
                return wp_kses_post($data);
                
            case 'int':
                return absint($data);
                
            case 'float':
                return (float) $data;
                
            case 'bool':
                return (bool) $data;
                
            case 'json':
                if (is_string($data)) {
                    $decoded = json_decode($data, true);
                    return json_last_error() === JSON_ERROR_NONE ? wp_json_encode($decoded) : '{}';
                }
                return wp_json_encode($data);
                
            default:
                return sanitize_text_field($data);
        }
    }
}