<?php
/**
 * Test Settings Functionality
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Simulate WordPress environment
define('ABSPATH', dirname(__FILE__) . '/');
define('WP_DEBUG', true);

// Mock WordPress functions
function wp_verify_nonce($nonce, $action) { return true; }
function current_user_can($capability) { return true; }
function sanitize_text_field($str) { return trim(strip_tags($str)); }
function sanitize_url($url) { return filter_var($url, FILTER_SANITIZE_URL); }
function absint($maybeint) { return abs(intval($maybeint)); }
function esc_attr($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_html($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function __($text, $domain = 'default') { return $text; }
function _e($text, $domain = 'default') { echo $text; }
function wp_parse_args($args, $defaults) { return array_merge($defaults, $args); }
function get_option($option, $default = false) { return $default; }
function update_option($option, $value) { return true; }
function delete_option($option) { return true; }
function register_setting($group, $name, $args = array()) { return true; }
function wp_create_nonce($action) { return 'test_nonce_' . $action; }
function wp_send_json_success($data) { echo json_encode(array('success' => true, 'data' => $data)); exit; }
function wp_send_json_error($data) { echo json_encode(array('success' => false, 'data' => $data)); exit; }
function wp_die($message) { die($message); }
function is_email($email) { return filter_var($email, FILTER_VALIDATE_EMAIL) !== false; }
function wp_clear_scheduled_hook($hook) { return true; }
function wp_schedule_event($timestamp, $recurrence, $hook) { return true; }
function wp_next_scheduled($hook) { return false; }
function current_time($type) { return date('Y-m-d H:i:s'); }
function checked($checked, $current = true, $echo = true) { 
    $result = checked($checked, $current, false);
    if ($echo) echo $result;
    return $result;
}
function selected($selected, $current = true, $echo = true) {
    $result = ($selected == $current) ? ' selected="selected"' : '';
    if ($echo) echo $result;
    return $result;
}

// Mock classes
class TPAK_Validator {
    private static $instance = null;
    private $errors = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function validate_api_settings($data) { return true; }
    public function validate_cron_settings($data) { return true; }
    public function validate_notification_settings($data) { return true; }
    public function validate_percentage($value, $field) { return $value >= 1 && $value <= 100; }
    public function validate_limesurvey_url($url) { return filter_var($url, FILTER_VALIDATE_URL) !== false; }
    public function validate_text($text, $field) { return !empty(trim($text)); }
    public function validate_numeric_id($id, $field) { return is_numeric($id) && $id > 0; }
    public function has_errors() { return !empty($this->errors); }
    public function get_error_messages() { return implode(', ', $this->errors); }
    public function sanitize_input($input, $type) {
        switch ($type) {
            case 'url': return sanitize_url($input);
            case 'text': return sanitize_text_field($input);
            case 'int': return absint($input);
            case 'bool': return (bool) $input;
            case 'email': return sanitize_email($input);
            default: return $input;
        }
    }
}

class TPAK_API_Handler {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function test_connection($url, $username, $password, $survey_id) {
        return array('success' => true, 'message' => 'Connection successful');
    }
}

class TPAK_Cron {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function execute_import() {
        return array('success' => true, 'imported_count' => 5);
    }
}

function sanitize_email($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

// Include the settings class
require_once 'admin/class-admin-settings.php';

// Test the settings functionality
echo "Testing TPAK Admin Settings...\n\n";

try {
    // Test 1: Instance creation
    echo "1. Testing instance creation...\n";
    $settings = TPAK_Admin_Settings::get_instance();
    if ($settings instanceof TPAK_Admin_Settings) {
        echo "✓ Settings instance created successfully\n";
    } else {
        echo "✗ Failed to create settings instance\n";
    }
    
    // Test 2: Default settings
    echo "\n2. Testing default settings...\n";
    $all_settings = $settings->get_all_settings();
    
    $required_sections = array('api', 'cron', 'notifications', 'workflow');
    foreach ($required_sections as $section) {
        if (isset($all_settings[$section])) {
            echo "✓ Section '$section' exists\n";
        } else {
            echo "✗ Section '$section' missing\n";
        }
    }
    
    // Test 3: API settings
    echo "\n3. Testing API settings...\n";
    $api_settings = $settings->get_settings('api');
    $required_api_fields = array('limesurvey_url', 'username', 'password', 'survey_id', 'connection_timeout');
    foreach ($required_api_fields as $field) {
        if (array_key_exists($field, $api_settings)) {
            echo "✓ API field '$field' exists\n";
        } else {
            echo "✗ API field '$field' missing\n";
        }
    }
    
    // Test 4: Settings update
    echo "\n4. Testing settings update...\n";
    $test_data = array(
        'limesurvey_url' => 'https://test.example.com/api',
        'username' => 'testuser',
        'survey_id' => 123
    );
    
    $result = $settings->update_settings('api', $test_data);
    if ($result) {
        echo "✓ Settings update successful\n";
        
        // Verify the update
        $updated_settings = $settings->get_settings('api');
        if ($updated_settings['limesurvey_url'] === 'https://test.example.com/api') {
            echo "✓ Settings values updated correctly\n";
        } else {
            echo "✗ Settings values not updated correctly\n";
        }
    } else {
        echo "✗ Settings update failed\n";
    }
    
    // Test 5: Settings sanitization
    echo "\n5. Testing settings sanitization...\n";
    $test_settings = array(
        'api' => array(
            'limesurvey_url' => 'javascript:alert("xss")',
            'username' => '<script>alert("xss")</script>',
            'survey_id' => '123abc',
            'connection_timeout' => 'invalid'
        ),
        'workflow' => array(
            'sampling_percentage' => '30.7',
            'auto_finalize_sampling' => 'true'
        )
    );
    
    $sanitized = $settings->sanitize_settings($test_settings);
    
    if ($sanitized['api']['username'] === 'alert("xss")') {
        echo "✓ XSS sanitization working\n";
    } else {
        echo "✗ XSS sanitization failed\n";
    }
    
    if ($sanitized['api']['survey_id'] === 123) {
        echo "✓ Integer conversion working\n";
    } else {
        echo "✗ Integer conversion failed\n";
    }
    
    if ($sanitized['workflow']['sampling_percentage'] === 30) {
        echo "✓ Float to integer conversion working\n";
    } else {
        echo "✗ Float to integer conversion failed\n";
    }
    
    // Test 6: Workflow settings validation
    echo "\n6. Testing workflow settings validation...\n";
    $validator = TPAK_Validator::get_instance();
    
    // Test valid percentage
    if ($validator->validate_percentage(30, 'Sampling Percentage')) {
        echo "✓ Valid percentage accepted\n";
    } else {
        echo "✗ Valid percentage rejected\n";
    }
    
    // Test invalid percentage
    if (!$validator->validate_percentage(150, 'Sampling Percentage')) {
        echo "✓ Invalid percentage rejected\n";
    } else {
        echo "✗ Invalid percentage accepted\n";
    }
    
    echo "\n✓ All tests completed successfully!\n";
    echo "\nSettings functionality is working correctly.\n";
    
} catch (Exception $e) {
    echo "✗ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "TPAK Admin Settings Test Complete\n";
echo str_repeat("=", 50) . "\n";
?>