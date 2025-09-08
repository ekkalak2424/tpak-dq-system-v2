<?php
/**
 * API Interface
 * 
 * Defines the contract for API handlers
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK API Interface
 */
interface TPAK_API_Interface {
    
    /**
     * Connect to the API
     * 
     * @param string $url
     * @param string $username
     * @param string $password
     * @return bool|WP_Error
     */
    public function connect($url = null, $username = null, $password = null);
    
    /**
     * Disconnect from the API
     * 
     * @return bool
     */
    public function disconnect();
    
    /**
     * Validate API connection
     * 
     * @return bool|WP_Error
     */
    public function validate_connection();
    
    /**
     * Get survey data from the API
     * 
     * @param string $survey_id
     * @param string $last_import_date
     * @return array|WP_Error
     */
    public function get_survey_data($survey_id = null, $last_import_date = null);
    
    /**
     * Transform API data to WordPress format
     * 
     * @param array $api_data
     * @return array
     */
    public function transform_data($api_data);
    
    /**
     * Get last error message
     * 
     * @return string
     */
    public function get_last_error();
    
    /**
     * Check if connected to API
     * 
     * @return bool
     */
    public function is_connected();
}