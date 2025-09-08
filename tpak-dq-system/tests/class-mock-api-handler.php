<?php
/**
 * Mock API Handler for Testing
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

/**
 * Mock TPAK API Handler Class
 */
class TPAK_Mock_API_Handler implements TPAK_API_Interface {
    
    /**
     * Mock connection state
     * 
     * @var bool
     */
    private $is_connected = false;
    
    /**
     * Mock error message
     * 
     * @var string
     */
    private $last_error = '';
    
    /**
     * Mock survey data
     * 
     * @var array
     */
    private $mock_data = array();
    
    /**
     * Should simulate errors
     * 
     * @var bool
     */
    private $simulate_errors = false;
    
    /**
     * Constructor
     * 
     * @param array $mock_data
     * @param bool  $simulate_errors
     */
    public function __construct($mock_data = array(), $simulate_errors = false) {
        $this->mock_data = $mock_data;
        $this->simulate_errors = $simulate_errors;
    }
    
    /**
     * Connect to the API
     * 
     * @param string $url
     * @param string $username
     * @param string $password
     * @return bool|WP_Error
     */
    public function connect($url = null, $username = null, $password = null) {
        if ($this->simulate_errors) {
            $this->last_error = 'Mock connection error';
            return new WP_Error('mock_error', $this->last_error);
        }
        
        if (empty($url) || empty($username) || empty($password)) {
            $this->last_error = 'Missing credentials';
            return new WP_Error('missing_credentials', $this->last_error);
        }
        
        $this->is_connected = true;
        return true;
    }
    
    /**
     * Disconnect from the API
     * 
     * @return bool
     */
    public function disconnect() {
        $this->is_connected = false;
        return true;
    }
    
    /**
     * Validate API connection
     * 
     * @return bool|WP_Error
     */
    public function validate_connection() {
        if ($this->simulate_errors) {
            return new WP_Error('validation_error', 'Mock validation error');
        }
        
        return $this->connect('mock_url', 'mock_user', 'mock_pass');
    }
    
    /**
     * Get survey data from the API
     * 
     * @param string $survey_id
     * @param string $last_import_date
     * @return array|WP_Error
     */
    public function get_survey_data($survey_id = null, $last_import_date = null) {
        if ($this->simulate_errors) {
            return new WP_Error('api_error', 'Mock API error');
        }
        
        if (empty($survey_id)) {
            return new WP_Error('no_survey_id', 'Survey ID is required');
        }
        
        // Return mock data
        return array(
            'survey_id' => $survey_id,
            'survey_info' => array(
                'title' => 'Mock Survey',
                'description' => 'Mock survey for testing'
            ),
            'questions' => array(
                array('qid' => '1', 'question' => 'Mock Question 1'),
                array('qid' => '2', 'question' => 'Mock Question 2')
            ),
            'responses' => $this->mock_data,
            'import_date' => current_time('mysql'),
            'total_responses' => count($this->mock_data)
        );
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
     * Get last error message
     * 
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Check if connected to API
     * 
     * @return bool
     */
    public function is_connected() {
        return $this->is_connected;
    }
    
    /**
     * Set mock data
     * 
     * @param array $data
     */
    public function set_mock_data($data) {
        $this->mock_data = $data;
    }
    
    /**
     * Enable/disable error simulation
     * 
     * @param bool $simulate
     */
    public function set_simulate_errors($simulate) {
        $this->simulate_errors = $simulate;
    }
    
    /**
     * Clear error
     */
    public function clear_error() {
        $this->last_error = '';
    }
}