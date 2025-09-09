<?php
/**
 * Security and Access Control Class
 *
 * Handles nonce verification, input sanitization, output escaping,
 * permission checking, and session management.
 *
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Security {
    
    /**
     * Nonce action names
     */
    const NONCE_SETTINGS = 'tpak_settings_nonce';
    const NONCE_DATA_ACTION = 'tpak_data_action_nonce';
    const NONCE_AJAX = 'tpak_ajax_nonce';
    const NONCE_IMPORT = 'tpak_import_nonce';
    const NONCE_WORKFLOW = 'tpak_workflow_nonce';
    
    /**
     * Session timeout in seconds (30 minutes)
     */
    const SESSION_TIMEOUT = 1800;
    
    /**
     * Initialize security hooks
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'start_session'));
        add_action('wp_logout', array(__CLASS__, 'destroy_session'));
        add_action('wp_ajax_tpak_check_session', array(__CLASS__, 'ajax_check_session'));
        add_action('wp_ajax_nopriv_tpak_check_session', array(__CLASS__, 'ajax_check_session_denied'));
        add_action('wp_ajax_tpak_refresh_nonce', array(__CLASS__, 'ajax_refresh_nonce'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_security_scripts'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_security_scripts'));
    }
    
    /**
     * Generate nonce for specific action
     *
     * @param string $action Nonce action name
     * @return string Generated nonce
     */
    public static function create_nonce($action) {
        return wp_create_nonce($action);
    }
    
    /**
     * Verify nonce for specific action
     *
     * @param string $nonce Nonce to verify
     * @param string $action Nonce action name
     * @return bool True if valid, false otherwise
     */
    public static function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action) !== false;
    }
    
    /**
     * Verify nonce from request (POST or GET)
     *
     * @param string $action Nonce action name
     * @param string $field Nonce field name (default: '_wpnonce')
     * @return bool True if valid, false otherwise
     */
    public static function verify_request_nonce($action, $field = '_wpnonce') {
        $nonce = '';
        
        if (isset($_POST[$field])) {
            $nonce = sanitize_text_field($_POST[$field]);
        } elseif (isset($_GET[$field])) {
            $nonce = sanitize_text_field($_GET[$field]);
        }
        
        return self::verify_nonce($nonce, $action);
    }
    
    /**
     * Check if current user has required capability
     *
     * @param string $capability Required capability
     * @param int $object_id Optional object ID for meta capabilities
     * @return bool True if user has capability, false otherwise
     */
    public static function current_user_can($capability, $object_id = null) {
        if ($object_id !== null) {
            return current_user_can($capability, $object_id);
        }
        return current_user_can($capability);
    }
    
    /**
     * Check if current user can access TPAK admin
     *
     * @return bool True if user can access, false otherwise
     */
    public static function can_access_admin() {
        return self::current_user_can('manage_options') || 
               self::current_user_can('edit_tpak_data') ||
               self::current_user_can('review_tpak_data');
    }
    
    /**
     * Check if current user can manage settings
     *
     * @return bool True if user can manage settings, false otherwise
     */
    public static function can_manage_settings() {
        return self::current_user_can('manage_options') ||
               self::current_user_can('manage_tpak_settings');
    }
    
    /**
     * Check if current user can edit specific data
     *
     * @param int $post_id Post ID to check
     * @return bool True if user can edit, false otherwise
     */
    public static function can_edit_data($post_id) {
        if (!self::current_user_can('edit_tpak_data')) {
            return false;
        }
        
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'tpak_survey_data') {
            return false;
        }
        
        $status = get_post_meta($post_id, '_tpak_status', true);
        $user_roles = wp_get_current_user()->roles;
        
        // Check role-based access to data status
        if (in_array('tpak_interviewer_a', $user_roles)) {
            return in_array($status, ['pending_a', 'rejected_by_b']);
        } elseif (in_array('tpak_supervisor_b', $user_roles)) {
            return $status === 'pending_b';
        } elseif (in_array('tpak_examiner_c', $user_roles)) {
            return $status === 'pending_c';
        } elseif (in_array('administrator', $user_roles)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Sanitize text input
     *
     * @param string $input Input to sanitize
     * @param int $max_length Maximum allowed length
     * @return string Sanitized text
     */
    public static function sanitize_text($input, $max_length = null) {
        $sanitized = sanitize_text_field($input);
        
        if ($max_length && strlen($sanitized) > $max_length) {
            $sanitized = substr($sanitized, 0, $max_length);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize textarea input
     *
     * @param string $input Input to sanitize
     * @param int $max_length Maximum allowed length
     * @return string Sanitized textarea content
     */
    public static function sanitize_textarea($input, $max_length = null) {
        $sanitized = sanitize_textarea_field($input);
        
        if ($max_length && strlen($sanitized) > $max_length) {
            $sanitized = substr($sanitized, 0, $max_length);
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize URL input
     *
     * @param string $input URL to sanitize
     * @return string Sanitized URL
     */
    public static function sanitize_url($input) {
        return esc_url_raw($input);
    }
    
    /**
     * Sanitize email input
     *
     * @param string $input Email to sanitize
     * @return string Sanitized email
     */
    public static function sanitize_email($input) {
        return sanitize_email($input);
    }
    
    /**
     * Sanitize integer input
     *
     * @param mixed $input Input to sanitize
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @return int Sanitized integer
     */
    public static function sanitize_int($input, $min = null, $max = null) {
        $sanitized = intval($input);
        
        if ($min !== null && $sanitized < $min) {
            $sanitized = $min;
        }
        
        if ($max !== null && $sanitized > $max) {
            $sanitized = $max;
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize JSON input
     *
     * @param string $input JSON string to sanitize
     * @param int $max_size Maximum JSON size in bytes
     * @return string|false Sanitized JSON or false if invalid
     */
    public static function sanitize_json($input, $max_size = 65535) {
        if (strlen($input) > $max_size) {
            return false;
        }
        
        $decoded = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return wp_json_encode($decoded);
    }
    
    /**
     * Escape output for HTML context
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function escape_html($text) {
        return esc_html($text);
    }
    
    /**
     * Escape output for HTML attribute context
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function escape_attr($text) {
        return esc_attr($text);
    }
    
    /**
     * Escape output for URL context
     *
     * @param string $url URL to escape
     * @return string Escaped URL
     */
    public static function escape_url($url) {
        return esc_url($url);
    }
    
    /**
     * Escape output for JavaScript context
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    public static function escape_js($text) {
        return esc_js($text);
    }
    
    /**
     * Start secure session
     */
    public static function start_session() {
        if (!session_id() && self::can_access_admin()) {
            session_start();
            $_SESSION['tpak_session_start'] = time();
            $_SESSION['tpak_user_id'] = get_current_user_id();
        }
    }
    
    /**
     * Check if session is valid and not expired
     *
     * @return bool True if session is valid, false otherwise
     */
    public static function is_session_valid() {
        if (!session_id()) {
            return false;
        }
        
        if (!isset($_SESSION['tpak_session_start']) || !isset($_SESSION['tpak_user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (time() - $_SESSION['tpak_session_start'] > self::SESSION_TIMEOUT) {
            self::destroy_session();
            return false;
        }
        
        // Check if user is still the same
        if ($_SESSION['tpak_user_id'] !== get_current_user_id()) {
            self::destroy_session();
            return false;
        }
        
        // Refresh session timestamp
        $_SESSION['tpak_session_start'] = time();
        
        return true;
    }
    
    /**
     * Destroy session
     */
    public static function destroy_session() {
        if (session_id()) {
            session_unset();
            session_destroy();
        }
    }
    
    /**
     * AJAX handler to check session status
     */
    public static function ajax_check_session() {
        if (!self::verify_request_nonce(self::NONCE_AJAX, 'nonce')) {
            wp_die('Invalid nonce', 'Security Error', array('response' => 403));
        }
        
        $valid = self::is_session_valid();
        
        wp_send_json(array(
            'valid' => $valid,
            'timeout' => self::SESSION_TIMEOUT,
            'remaining' => $valid ? (self::SESSION_TIMEOUT - (time() - $_SESSION['tpak_session_start'])) : 0
        ));
    }
    
    /**
     * AJAX handler for non-logged-in users
     */
    public static function ajax_check_session_denied() {
        wp_die('Access denied', 'Security Error', array('response' => 403));
    }
    
    /**
     * Log security event
     *
     * @param string $event Event type
     * @param string $message Event message
     * @param array $context Additional context
     */
    public static function log_security_event($event, $message, $context = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'user_ip' => self::get_client_ip(),
            'event' => $event,
            'message' => $message,
            'context' => $context,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        );
        
        error_log('TPAK Security Event: ' . wp_json_encode($log_entry));
        
        // Store in database for admin review
        $security_logs = get_option('tpak_security_logs', array());
        $security_logs[] = $log_entry;
        
        // Keep only last 100 entries
        if (count($security_logs) > 100) {
            $security_logs = array_slice($security_logs, -100);
        }
        
        update_option('tpak_security_logs', $security_logs);
    }
    
    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Middleware to check permissions for admin actions
     *
     * @param string $required_capability Required capability
     * @param callable $callback Callback to execute if authorized
     * @param array $args Arguments to pass to callback
     * @return mixed Callback result or false if unauthorized
     */
    public static function admin_action_middleware($required_capability, $callback, $args = array()) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            self::log_security_event('unauthorized_access', 'User not logged in');
            return false;
        }
        
        // Check session validity
        if (!self::is_session_valid()) {
            self::log_security_event('session_expired', 'Session expired or invalid');
            return false;
        }
        
        // Check user capability
        if (!self::current_user_can($required_capability)) {
            self::log_security_event('insufficient_permissions', "User lacks capability: {$required_capability}");
            return false;
        }
        
        // Execute callback if all checks pass
        if (is_callable($callback)) {
            return call_user_func_array($callback, $args);
        }
        
        return false;
    }
    
    /**
     * AJAX handler to refresh nonce
     */
    public static function ajax_refresh_nonce() {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized', 'Security Error', array('response' => 401));
        }
        
        $new_nonce = self::create_nonce(self::NONCE_AJAX);
        
        wp_send_json_success(array(
            'nonce' => $new_nonce
        ));
    }
    
    /**
     * Enqueue security scripts and styles
     */
    public static function enqueue_security_scripts() {
        if (is_admin() && self::can_access_admin()) {
            wp_enqueue_script(
                'tpak-security',
                TPAK_DQ_PLUGIN_URL . 'assets/js/security.js',
                array('jquery'),
                TPAK_DQ_VERSION,
                true
            );
            
            wp_localize_script('tpak-security', 'tpakAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => self::create_nonce(self::NONCE_AJAX)
            ));
            
            wp_localize_script('tpak-security', 'tpakSecurity', array(
                'messages' => array(
                    'sessionWarning' => __('Your session will expire in %d minutes. Please save your work.', 'tpak-dq-system'),
                    'sessionExpired' => __('Your session has expired.', 'tpak-dq-system'),
                    'sessionExpiredDesc' => __('For security reasons, you need to refresh the page and log in again.', 'tpak-dq-system'),
                    'reloadPage' => __('Reload Page', 'tpak-dq-system'),
                    'rateLimitExceeded' => __('Too many requests. Please wait before trying again.', 'tpak-dq-system'),
                    'requestTimeout' => __('Request timed out. Please try again.', 'tpak-dq-system')
                )
            ));
        }
    }
    
    /**
     * Rate limiting for sensitive operations
     *
     * @param string $action Action identifier
     * @param int $limit Maximum attempts
     * @param int $window Time window in seconds
     * @return bool True if within limits, false if rate limited
     */
    public static function check_rate_limit($action, $limit = 5, $window = 300) {
        $user_id = get_current_user_id();
        $key = "tpak_rate_limit_{$action}_{$user_id}";
        
        $attempts = get_transient($key);
        if ($attempts === false) {
            $attempts = 0;
        }
        
        if ($attempts >= $limit) {
            self::log_security_event('rate_limit_exceeded', "Rate limit exceeded for action: {$action}");
            return false;
        }
        
        set_transient($key, $attempts + 1, $window);
        return true;
    }
}