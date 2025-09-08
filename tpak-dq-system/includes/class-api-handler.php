<?php
/**
 * API Handler Class
 * 
 * Handles LimeSurvey API communication
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK API Handler Class
 */
class TPAK_API_Handler implements TPAK_API_Interface {
    
    /**
     * Single instance
     * 
     * @var TPAK_API_Handler
     */
    private static $instance = null;
    
    /**
     * Get instance
     * 
     * @return TPAK_API_Handler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * API session key
     * 
     * @var string
     */
    private $session_key = null;
    
    /**
     * API settings
     * 
     * @var array
     */
    private $settings = array();
    
    /**
     * Last error message
     * 
     * @var string
     */
    private $last_error = '';
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_settings();
        add_action('wp_ajax_tpak_test_api_connection', array($this, 'ajax_test_connection'));
    }
    
    /**
     * Load API settings
     */
    private function load_settings() {
        $settings = get_option('tpak_dq_settings', array());
        $this->settings = isset($settings['api']) ? $settings['api'] : array();
        
        // Set defaults
        $this->settings = wp_parse_args($this->settings, array(
            'limesurvey_url' => '',
            'username' => '',
            'password' => '',
            'survey_id' => '',
            'timeout' => 30,
            'ssl_verify' => true,
        ));
    }
    
    /**
     * Connect to LimeSurvey API
     * 
     * @param string $url
     * @param string $username
     * @param string $password
     * @return bool|WP_Error
     */
    public function connect($url = null, $username = null, $password = null) {
        // Use provided parameters or fall back to settings
        $api_url = $url ?: $this->settings['limesurvey_url'];
        $api_username = $username ?: $this->settings['username'];
        $api_password = $password ?: $this->settings['password'];
        
        // Validate required parameters
        if (empty($api_url) || empty($api_username) || empty($api_password)) {
            $this->last_error = __('API URL, username, and password are required.', 'tpak-dq-system');
            return new WP_Error('missing_credentials', $this->last_error);
        }
        
        // Prepare API request
        $request_data = array(
            'method' => 'get_session_key',
            'params' => array($api_username, $api_password),
            'id' => 1
        );
        
        $response = $this->make_api_request($api_url, $request_data);
        
        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return $response;
        }
        
        // Check for API errors
        if (isset($response['error'])) {
            $this->last_error = isset($response['error']['message']) 
                ? $response['error']['message'] 
                : __('Unknown API error', 'tpak-dq-system');
            return new WP_Error('api_error', $this->last_error);
        }
        
        // Check for session key
        if (!isset($response['result']) || empty($response['result'])) {
            $this->last_error = __('Failed to obtain session key from LimeSurvey API.', 'tpak-dq-system');
            return new WP_Error('no_session_key', $this->last_error);
        }
        
        $this->session_key = $response['result'];
        
        // Store connection timestamp
        update_option('tpak_dq_last_api_connection', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Disconnect from LimeSurvey API
     * 
     * @return bool
     */
    public function disconnect() {
        if (!$this->session_key) {
            return true;
        }
        
        $request_data = array(
            'method' => 'release_session_key',
            'params' => array($this->session_key),
            'id' => 1
        );
        
        $response = $this->make_api_request($this->settings['limesurvey_url'], $request_data);
        
        $this->session_key = null;
        
        return !is_wp_error($response);
    }
    
    /**
     * Validate API connection
     * 
     * @return bool|WP_Error
     */
    public function validate_connection() {
        $connection = $this->connect();
        
        if (is_wp_error($connection)) {
            return $connection;
        }
        
        // Test a simple API call
        $survey_info = $this->get_survey_properties($this->settings['survey_id']);
        
        $this->disconnect();
        
        if (is_wp_error($survey_info)) {
            return $survey_info;
        }
        
        return true;
    }
    
    /**
     * Get survey data
     * 
     * @param string $survey_id
     * @param string $last_import_date
     * @return array|WP_Error
     */
    public function get_survey_data($survey_id = null, $last_import_date = null) {
        $survey_id = $survey_id ?: $this->settings['survey_id'];
        
        if (empty($survey_id)) {
            return new WP_Error('no_survey_id', __('Survey ID is required.', 'tpak-dq-system'));
        }
        
        // Connect to API
        $connection = $this->connect();
        if (is_wp_error($connection)) {
            return $connection;
        }
        
        try {
            // Get survey responses
            $responses = $this->get_survey_responses($survey_id, $last_import_date);
            
            if (is_wp_error($responses)) {
                $this->disconnect();
                return $responses;
            }
            
            // Get survey structure for context
            $survey_info = $this->get_survey_properties($survey_id);
            $questions = $this->get_survey_questions($survey_id);
            
            $result = array(
                'survey_id' => $survey_id,
                'survey_info' => is_wp_error($survey_info) ? array() : $survey_info,
                'questions' => is_wp_error($questions) ? array() : $questions,
                'responses' => $responses,
                'import_date' => current_time('mysql'),
                'total_responses' => count($responses)
            );
            
            $this->disconnect();
            
            return $result;
            
        } catch (Exception $e) {
            $this->disconnect();
            $this->last_error = $e->getMessage();
            return new WP_Error('api_exception', $this->last_error);
        }
    }
    
    /**
     * Get survey responses
     * 
     * @param string $survey_id
     * @param string $last_import_date
     * @return array|WP_Error
     */
    private function get_survey_responses($survey_id, $last_import_date = null) {
        if (!$this->session_key) {
            return new WP_Error('no_session', __('Not connected to API.', 'tpak-dq-system'));
        }
        
        // Prepare parameters
        $params = array($this->session_key, $survey_id);
        
        // Add date filter if provided
        if ($last_import_date) {
            $params[] = 'all'; // Document type
            $params[] = 'en'; // Language
            $params[] = 'complete'; // Completion status
            $params[] = 'full'; // Heading type
            $params[] = 'code'; // Response type
            $params[] = $last_import_date; // From date
        }
        
        $request_data = array(
            'method' => 'export_responses',
            'params' => $params,
            'id' => 1
        );
        
        $response = $this->make_api_request($this->settings['limesurvey_url'], $request_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['error'])) {
            return new WP_Error('api_error', $response['error']['message'] ?? __('Unknown error', 'tpak-dq-system'));
        }
        
        if (!isset($response['result'])) {
            return new WP_Error('no_result', __('No response data received.', 'tpak-dq-system'));
        }
        
        // Decode base64 response data
        $csv_data = base64_decode($response['result']);
        
        if ($csv_data === false) {
            return new WP_Error('decode_error', __('Failed to decode response data.', 'tpak-dq-system'));
        }
        
        // Parse CSV data
        return $this->parse_csv_responses($csv_data);
    }
    
    /**
     * Get survey properties
     * 
     * @param string $survey_id
     * @return array|WP_Error
     */
    private function get_survey_properties($survey_id) {
        if (!$this->session_key) {
            return new WP_Error('no_session', __('Not connected to API.', 'tpak-dq-system'));
        }
        
        $request_data = array(
            'method' => 'get_survey_properties',
            'params' => array($this->session_key, $survey_id),
            'id' => 1
        );
        
        $response = $this->make_api_request($this->settings['limesurvey_url'], $request_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['error'])) {
            return new WP_Error('api_error', $response['error']['message'] ?? __('Unknown error', 'tpak-dq-system'));
        }
        
        return $response['result'] ?? array();
    }
    
    /**
     * Get survey questions
     * 
     * @param string $survey_id
     * @return array|WP_Error
     */
    private function get_survey_questions($survey_id) {
        if (!$this->session_key) {
            return new WP_Error('no_session', __('Not connected to API.', 'tpak-dq-system'));
        }
        
        $request_data = array(
            'method' => 'list_questions',
            'params' => array($this->session_key, $survey_id),
            'id' => 1
        );
        
        $response = $this->make_api_request($this->settings['limesurvey_url'], $request_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        if (isset($response['error'])) {
            return new WP_Error('api_error', $response['error']['message'] ?? __('Unknown error', 'tpak-dq-system'));
        }
        
        return $response['result'] ?? array();
    }
    
    /**
     * Parse CSV response data
     * 
     * @param string $csv_data
     * @return array
     */
    private function parse_csv_responses($csv_data) {
        $lines = str_getcsv($csv_data, "\n");
        
        if (empty($lines)) {
            return array();
        }
        
        // First line contains headers
        $headers = str_getcsv(array_shift($lines));
        $responses = array();
        
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $values = str_getcsv($line);
            
            // Skip if not enough values
            if (count($values) < count($headers)) {
                continue;
            }
            
            $response = array();
            foreach ($headers as $index => $header) {
                $response[$header] = isset($values[$index]) ? $values[$index] : '';
            }
            
            // Use response ID as key if available
            $response_id = $response['id'] ?? $response['Response ID'] ?? uniqid();
            $responses[$response_id] = $response;
        }
        
        return $responses;
    }
    
    /**
     * Transform API data to WordPress format
     * 
     * @param array $api_data
     * @return array
     */
    public function transform_data($api_data) {
        if (!is_array($api_data) || !isset($api_data['responses'])) {
            return array();
        }
        
        $transformed = array();
        
        foreach ($api_data['responses'] as $response_id => $response_data) {
            $survey_data = new TPAK_Survey_Data();
            $survey_data->set_survey_id($api_data['survey_id']);
            $survey_data->set_response_id($response_id);
            $survey_data->set_data($response_data);
            $survey_data->set_status('pending_a');
            
            $transformed[] = $survey_data;
        }
        
        return $transformed;
    }
    
    /**
     * Make API request
     * 
     * @param string $url
     * @param array  $data
     * @return array|WP_Error
     */
    private function make_api_request($url, $data) {
        $request_body = wp_json_encode($data);
        
        $args = array(
            'method' => 'POST',
            'timeout' => $this->settings['timeout'],
            'headers' => array(
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($request_body),
            ),
            'body' => $request_body,
            'sslverify' => $this->settings['ssl_verify'],
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $this->last_error = sprintf(
                __('HTTP Error %d: %s', 'tpak-dq-system'),
                $response_code,
                wp_remote_retrieve_response_message($response)
            );
            return new WP_Error('http_error', $this->last_error);
        }
        
        $decoded_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->last_error = __('Invalid JSON response from API.', 'tpak-dq-system');
            return new WP_Error('json_error', $this->last_error);
        }
        
        return $decoded_response;
    }
    
    /**
     * Get last error message
     * 
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Clear last error
     */
    public function clear_error() {
        $this->last_error = '';
    }
    
    /**
     * Check if connected
     * 
     * @return bool
     */
    public function is_connected() {
        return !empty($this->session_key);
    }
    
    /**
     * Get API settings
     * 
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Update API settings
     * 
     * @param array $settings
     */
    public function update_settings($settings) {
        $this->settings = wp_parse_args($settings, $this->settings);
        
        // Save to WordPress options
        $all_settings = get_option('tpak_dq_settings', array());
        $all_settings['api'] = $this->settings;
        update_option('tpak_dq_settings', $all_settings);
    }
    
    /**
     * Test API connection via AJAX
     */
    public function ajax_test_connection() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'tpak-dq-system'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_test_api')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        // Get test parameters
        $url = sanitize_url($_POST['url'] ?? '');
        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = sanitize_text_field($_POST['password'] ?? '');
        $survey_id = sanitize_text_field($_POST['survey_id'] ?? '');
        
        // Validate inputs
        $validator = TPAK_Validator::get_instance();
        
        if (!$validator->validate_limesurvey_url($url) ||
            !$validator->validate_text($username, 'Username') ||
            !$validator->validate_text($password, 'Password') ||
            !$validator->validate_numeric_id($survey_id, 'Survey ID')) {
            
            wp_send_json_error(array(
                'message' => $validator->get_error_messages()
            ));
        }
        
        // Test connection
        $connection = $this->connect($url, $username, $password);
        
        if (is_wp_error($connection)) {
            wp_send_json_error(array(
                'message' => $connection->get_error_message()
            ));
        }
        
        // Test survey access
        $survey_info = $this->get_survey_properties($survey_id);
        
        $this->disconnect();
        
        if (is_wp_error($survey_info)) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Connection successful, but cannot access survey %s: %s', 'tpak-dq-system'),
                    $survey_id,
                    $survey_info->get_error_message()
                )
            ));
        }
        
        wp_send_json_success(array(
            'message' => __('Connection successful!', 'tpak-dq-system'),
            'survey_info' => $survey_info
        ));
    }
    
    /**
     * Import survey data and prevent duplicates
     * 
     * @param string $survey_id
     * @param string $last_import_date
     * @return array|WP_Error
     */
    public function import_survey_data($survey_id = null, $last_import_date = null) {
        // Get survey data from API
        $api_data = $this->get_survey_data($survey_id, $last_import_date);
        
        if (is_wp_error($api_data)) {
            return $api_data;
        }
        
        $imported_count = 0;
        $skipped_count = 0;
        $error_count = 0;
        $errors = array();
        
        foreach ($api_data['responses'] as $response_id => $response_data) {
            // Check if response already exists
            $existing = TPAK_Survey_Data::find_by_response_id($api_data['survey_id'], $response_id);
            
            if ($existing) {
                $skipped_count++;
                continue;
            }
            
            // Create new survey data
            $survey_data = TPAK_Survey_Data::create_from_limesurvey(
                $api_data['survey_id'],
                $response_id,
                $response_data
            );
            
            if (is_wp_error($survey_data)) {
                $error_count++;
                $errors[] = sprintf(
                    __('Failed to import response %s: %s', 'tpak-dq-system'),
                    $response_id,
                    $survey_data->get_error_message()
                );
            } else {
                $imported_count++;
            }
        }
        
        // Update last import timestamp
        update_option('tpak_dq_last_import', current_time('mysql'));
        
        return array(
            'imported' => $imported_count,
            'skipped' => $skipped_count,
            'errors' => $error_count,
            'error_messages' => $errors,
            'total_responses' => count($api_data['responses'])
        );
    }
    
    /**
     * Get connection status
     * 
     * @return array
     */
    public function get_connection_status() {
        $last_connection = get_option('tpak_dq_last_api_connection');
        $last_import = get_option('tpak_dq_last_import');
        
        return array(
            'is_configured' => !empty($this->settings['limesurvey_url']) && 
                              !empty($this->settings['username']) && 
                              !empty($this->settings['password']),
            'last_connection' => $last_connection,
            'last_import' => $last_import,
            'settings' => $this->settings
        );
    }
}