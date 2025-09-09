<?php
/**
 * Security Middleware Class
 *
 * Provides middleware functionality for securing admin actions,
 * AJAX requests, and form submissions.
 *
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Security_Middleware {
    
    /**
     * Secure admin page callback
     *
     * @param string $capability Required capability
     * @param callable $callback Page callback function
     * @param array $nonce_actions Required nonce actions
     */
    public static function secure_admin_page($capability, $callback, $nonce_actions = array()) {
        // Check basic admin access
        if (!TPAK_Security::can_access_admin()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        // Check specific capability
        if (!TPAK_Security::current_user_can($capability)) {
            TPAK_Security::log_security_event('unauthorized_page_access', "Attempted access to page requiring: {$capability}");
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        // Check session validity for sensitive pages
        if (!TPAK_Security::is_session_valid()) {
            TPAK_Security::log_security_event('invalid_session_page_access', 'Invalid session on admin page');
            wp_die(__('Your session has expired. Please log in again.', 'tpak-dq-system'));
        }
        
        // Execute the callback
        if (is_callable($callback)) {
            call_user_func($callback);
        }
    }
    
    /**
     * Secure AJAX action callback
     *
     * @param string $capability Required capability
     * @param callable $callback AJAX callback function
     * @param bool $require_nonce Whether to require nonce verification
     */
    public static function secure_ajax_action($capability, $callback, $require_nonce = true) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            TPAK_Security::log_security_event('unauthorized_ajax', 'AJAX request from non-logged-in user');
            wp_send_json_error(array('message' => __('You must be logged in to perform this action.', 'tpak-dq-system')), 401);
        }
        
        // Verify nonce if required
        if ($require_nonce && !TPAK_Security::verify_request_nonce(TPAK_Security::NONCE_AJAX, 'nonce')) {
            TPAK_Security::log_security_event('invalid_nonce_ajax', 'Invalid nonce in AJAX request');
            wp_send_json_error(array('message' => __('Security check failed. Please refresh the page and try again.', 'tpak-dq-system')), 403);
        }
        
        // Check capability
        if (!TPAK_Security::current_user_can($capability)) {
            TPAK_Security::log_security_event('insufficient_permissions_ajax', "AJAX request lacking capability: {$capability}");
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'tpak-dq-system')), 403);
        }
        
        // Check session validity
        if (!TPAK_Security::is_session_valid()) {
            TPAK_Security::log_security_event('invalid_session_ajax', 'Invalid session in AJAX request');
            wp_send_json_error(array('message' => __('Your session has expired. Please refresh the page and log in again.', 'tpak-dq-system')), 401);
        }
        
        // Execute the callback
        if (is_callable($callback)) {
            call_user_func($callback);
        }
    }
    
    /**
     * Secure form submission handler
     *
     * @param string $nonce_action Nonce action to verify
     * @param string $capability Required capability
     * @param callable $callback Form processing callback
     * @param array $sanitization_rules Field sanitization rules
     */
    public static function secure_form_submission($nonce_action, $capability, $callback, $sanitization_rules = array()) {
        // Check if this is a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            TPAK_Security::log_security_event('invalid_form_method', 'Non-POST request to form handler');
            wp_die(__('Invalid request method.', 'tpak-dq-system'));
        }
        
        // Verify nonce
        if (!TPAK_Security::verify_request_nonce($nonce_action)) {
            TPAK_Security::log_security_event('invalid_form_nonce', "Invalid nonce for action: {$nonce_action}");
            wp_die(__('Security check failed. Please go back and try again.', 'tpak-dq-system'));
        }
        
        // Check capability
        if (!TPAK_Security::current_user_can($capability)) {
            TPAK_Security::log_security_event('insufficient_permissions_form', "Form submission lacking capability: {$capability}");
            wp_die(__('You do not have permission to perform this action.', 'tpak-dq-system'));
        }
        
        // Check session validity
        if (!TPAK_Security::is_session_valid()) {
            TPAK_Security::log_security_event('invalid_session_form', 'Invalid session in form submission');
            wp_die(__('Your session has expired. Please log in again.', 'tpak-dq-system'));
        }
        
        // Sanitize form data
        $sanitized_data = self::sanitize_form_data($_POST, $sanitization_rules);
        
        // Execute the callback with sanitized data
        if (is_callable($callback)) {
            call_user_func($callback, $sanitized_data);
        }
    }
    
    /**
     * Sanitize form data based on rules
     *
     * @param array $data Raw form data
     * @param array $rules Sanitization rules
     * @return array Sanitized data
     */
    private static function sanitize_form_data($data, $rules) {
        $sanitized = array();
        
        foreach ($data as $key => $value) {
            if (isset($rules[$key])) {
                $rule = $rules[$key];
                
                switch ($rule['type']) {
                    case 'text':
                        $max_length = isset($rule['max_length']) ? $rule['max_length'] : null;
                        $sanitized[$key] = TPAK_Security::sanitize_text($value, $max_length);
                        break;
                        
                    case 'textarea':
                        $max_length = isset($rule['max_length']) ? $rule['max_length'] : null;
                        $sanitized[$key] = TPAK_Security::sanitize_textarea($value, $max_length);
                        break;
                        
                    case 'url':
                        $sanitized[$key] = TPAK_Security::sanitize_url($value);
                        break;
                        
                    case 'email':
                        $sanitized[$key] = TPAK_Security::sanitize_email($value);
                        break;
                        
                    case 'int':
                        $min = isset($rule['min']) ? $rule['min'] : null;
                        $max = isset($rule['max']) ? $rule['max'] : null;
                        $sanitized[$key] = TPAK_Security::sanitize_int($value, $min, $max);
                        break;
                        
                    case 'json':
                        $max_size = isset($rule['max_size']) ? $rule['max_size'] : 65535;
                        $sanitized[$key] = TPAK_Security::sanitize_json($value, $max_size);
                        break;
                        
                    case 'array':
                        if (is_array($value)) {
                            $sanitized[$key] = array_map('sanitize_text_field', $value);
                        } else {
                            $sanitized[$key] = array();
                        }
                        break;
                        
                    default:
                        $sanitized[$key] = sanitize_text_field($value);
                        break;
                }
            } else {
                // Default sanitization for fields without rules
                if (is_array($value)) {
                    $sanitized[$key] = array_map('sanitize_text_field', $value);
                } else {
                    $sanitized[$key] = sanitize_text_field($value);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Secure data access middleware
     *
     * @param int $post_id Post ID to access
     * @param string $action Action being performed
     * @param callable $callback Callback to execute if authorized
     */
    public static function secure_data_access($post_id, $action, $callback) {
        // Validate post ID
        $post_id = TPAK_Security::sanitize_int($post_id);
        if (!$post_id) {
            TPAK_Security::log_security_event('invalid_post_id', "Invalid post ID: {$post_id}");
            wp_die(__('Invalid data ID.', 'tpak-dq-system'));
        }
        
        // Check if post exists and is correct type
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'tpak_survey_data') {
            TPAK_Security::log_security_event('invalid_post_type', "Attempted access to non-survey data: {$post_id}");
            wp_die(__('Invalid data type.', 'tpak-dq-system'));
        }
        
        // Check if user can edit this specific data
        if (!TPAK_Security::can_edit_data($post_id)) {
            TPAK_Security::log_security_event('unauthorized_data_access', "Unauthorized access to data: {$post_id}, action: {$action}");
            wp_die(__('You do not have permission to access this data.', 'tpak-dq-system'));
        }
        
        // Rate limiting for sensitive actions
        if (in_array($action, array('approve', 'reject', 'delete'))) {
            if (!TPAK_Security::check_rate_limit("data_{$action}", 10, 300)) {
                wp_die(__('Too many requests. Please wait before trying again.', 'tpak-dq-system'));
            }
        }
        
        // Execute callback
        if (is_callable($callback)) {
            call_user_func($callback, $post_id, $post);
        }
    }
    
    /**
     * Add security headers to admin pages
     */
    public static function add_security_headers() {
        if (is_admin()) {
            // Prevent clickjacking
            header('X-Frame-Options: SAMEORIGIN');
            
            // Prevent MIME type sniffing
            header('X-Content-Type-Options: nosniff');
            
            // Enable XSS protection
            header('X-XSS-Protection: 1; mode=block');
            
            // Referrer policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Content Security Policy for admin pages
            $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self';";
            header("Content-Security-Policy: {$csp}");
        }
    }
    
    /**
     * Initialize security middleware
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'add_security_headers'));
        add_action('wp_ajax_nopriv_tpak_unauthorized', array(__CLASS__, 'handle_unauthorized_ajax'));
    }
    
    /**
     * Handle unauthorized AJAX requests
     */
    public static function handle_unauthorized_ajax() {
        TPAK_Security::log_security_event('unauthorized_ajax_attempt', 'Unauthorized AJAX request attempt');
        wp_send_json_error(array('message' => __('Unauthorized access.', 'tpak-dq-system')), 401);
    }
}