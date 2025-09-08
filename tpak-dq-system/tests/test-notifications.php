<?php
/**
 * Test Notifications Class
 * 
 * Unit tests for TPAK_Notifications
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test TPAK Notifications Class
 */
class Test_TPAK_Notifications {
    
    /**
     * Notifications instance
     * 
     * @var TPAK_Notifications
     */
    private $notifications;
    
    /**
     * Test user ID
     * 
     * @var int
     */
    private $test_user_id;
    
    /**
     * Test post ID
     * 
     * @var int
     */
    private $test_post_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->notifications = TPAK_Notifications::get_instance();
        $this->setup_test_data();
    }
    
    /**
     * Setup test data
     */
    private function setup_test_data() {
        // Create test user
        $this->test_user_id = wp_create_user('testuser', 'testpass', 'test@example.com');
        
        // Create test post
        $this->test_post_id = wp_insert_post(array(
            'post_title' => 'Test Survey Data',
            'post_type' => 'tpak_survey_data',
            'post_status' => 'pending_a',
            'meta_input' => array(
                '_tpak_survey_data' => array(
                    'survey_id' => '12345',
                    'response_id' => '67890',
                    'data' => array('question1' => 'answer1')
                )
            )
        ));
    }
    
    /**
     * Run all tests
     */
    public function run_tests() {
        echo "<h2>Testing TPAK Notifications</h2>\n";
        
        $tests = array(
            'test_singleton_instance',
            'test_notification_settings',
            'test_notification_enabled_check',
            'test_template_generation',
            'test_template_variable_replacement',
            'test_notification_subject_generation',
            'test_notification_headers',
            'test_status_label_conversion',
            'test_notification_type_from_status',
            'test_template_data_preparation',
            'test_notification_logging',
            'test_error_logging',
            'test_settings_update',
            'test_html_content_type',
            'test_notification_templates'
        );
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            try {
                $result = $this->$test();
                if ($result) {
                    echo "✓ {$test} - PASSED\n";
                    $passed++;
                } else {
                    echo "✗ {$test} - FAILED\n";
                    $failed++;
                }
            } catch (Exception $e) {
                echo "✗ {$test} - ERROR: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
        
        echo "\n<strong>Results: {$passed} passed, {$failed} failed</strong>\n";
        
        $this->cleanup_test_data();
        
        return $failed === 0;
    }
    
    /**
     * Test singleton instance
     */
    public function test_singleton_instance() {
        $instance1 = TPAK_Notifications::get_instance();
        $instance2 = TPAK_Notifications::get_instance();
        
        return $instance1 === $instance2;
    }
    
    /**
     * Test notification settings
     */
    public function test_notification_settings() {
        // Test default settings
        $default_settings = $this->notifications->get_notification_settings();
        
        if (!is_array($default_settings)) {
            return false;
        }
        
        $required_keys = array('enabled', 'from_name', 'from_email', 'include_data_details', 'include_action_links');
        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $default_settings)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test notification enabled check
     */
    public function test_notification_enabled_check() {
        // Test default enabled state
        $enabled = $this->notifications->are_notifications_enabled();
        
        // Should be enabled by default
        if (!$enabled) {
            return false;
        }
        
        // Test disabling notifications
        $this->notifications->update_notification_settings(array('enabled' => false));
        $disabled = $this->notifications->are_notifications_enabled();
        
        // Should be disabled now
        if ($disabled) {
            return false;
        }
        
        // Re-enable for other tests
        $this->notifications->update_notification_settings(array('enabled' => true));
        
        return true;
    }
    
    /**
     * Test template generation
     */
    public function test_template_generation() {
        $test_data = array(
            'user_name' => 'Test User',
            'survey_id' => '12345',
            'response_id' => '67890',
            'status' => 'pending_a',
            'include_action_links' => true
        );
        
        $types = array(
            TPAK_Notifications::TYPE_ASSIGNMENT,
            TPAK_Notifications::TYPE_STATUS_CHANGE,
            TPAK_Notifications::TYPE_REJECTION,
            TPAK_Notifications::TYPE_APPROVAL
        );
        
        foreach ($types as $type) {
            $template = $this->notifications->get_notification_template($type, $test_data);
            
            if (empty($template)) {
                return false;
            }
            
            // Check if template contains expected elements
            if (strpos($template, 'font-family: Arial') === false) {
                return false;
            }
            
            if (strpos($template, '{user_name}') === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test template variable replacement
     */
    public function test_template_variable_replacement() {
        $template = 'Hello {user_name}, your survey {survey_id} is {status}.';
        $data = array(
            'user_name' => 'John Doe',
            'survey_id' => '12345',
            'status' => 'approved'
        );
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->notifications);
        $method = $reflection->getMethod('replace_template_variables');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->notifications, $template, $data);
        $expected = 'Hello John Doe, your survey 12345 is approved.';
        
        return $result === $expected;
    }
    
    /**
     * Test notification subject generation
     */
    public function test_notification_subject_generation() {
        $test_data = array(
            'site_name' => 'Test Site',
            'survey_id' => '12345'
        );
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->notifications);
        $method = $reflection->getMethod('get_notification_subject');
        $method->setAccessible(true);
        
        $subject = $method->invoke($this->notifications, TPAK_Notifications::TYPE_ASSIGNMENT, $test_data);
        
        if (strpos($subject, 'Test Site') === false) {
            return false;
        }
        
        if (strpos($subject, '12345') === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Test notification headers
     */
    public function test_notification_headers() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->notifications);
        $method = $reflection->getMethod('get_notification_headers');
        $method->setAccessible(true);
        
        $headers = $method->invoke($this->notifications);
        
        if (!is_array($headers)) {
            return false;
        }
        
        // Check for HTML content type
        $has_html_header = false;
        foreach ($headers as $header) {
            if (strpos($header, 'text/html') !== false) {
                $has_html_header = true;
                break;
            }
        }
        
        return $has_html_header;
    }
    
    /**
     * Test status label conversion
     */
    public function test_status_label_conversion() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->notifications);
        $method = $reflection->getMethod('get_status_label');
        $method->setAccessible(true);
        
        $test_cases = array(
            'pending_a' => 'Pending Interviewer Review',
            'pending_b' => 'Pending Supervisor Review',
            'rejected_by_b' => 'Rejected by Supervisor',
            'finalized' => 'Finalized'
        );
        
        foreach ($test_cases as $status => $expected_label) {
            $actual_label = $method->invoke($this->notifications, $status);
            if ($actual_label !== $expected_label) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test notification type from status
     */
    public function test_notification_type_from_status() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->notifications);
        $method = $reflection->getMethod('get_notification_type_from_status');
        $method->setAccessible(true);
        
        $test_cases = array(
            'rejected_by_b' => TPAK_Notifications::TYPE_REJECTION,
            'rejected_by_c' => TPAK_Notifications::TYPE_REJECTION,
            'finalized' => TPAK_Notifications::TYPE_APPROVAL,
            'finalized_by_sampling' => TPAK_Notifications::TYPE_APPROVAL,
            'pending_a' => TPAK_Notifications::TYPE_STATUS_CHANGE
        );
        
        foreach ($test_cases as $status => $expected_type) {
            $actual_type = $method->invoke($this->notifications, $status);
            if ($actual_type !== $expected_type) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test template data preparation
     */
    public function test_template_data_preparation() {
        $user = get_user_by('id', $this->test_user_id);
        $post = get_post($this->test_post_id);
        
        if (!$user || !$post) {
            return false;
        }
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->notifications);
        $method = $reflection->getMethod('prepare_template_data');
        $method->setAccessible(true);
        
        $context = array('old_status' => 'pending_a', 'new_status' => 'pending_b');
        $data = $method->invoke($this->notifications, $user, $post, TPAK_Notifications::TYPE_STATUS_CHANGE, $context);
        
        if (!is_array($data)) {
            return false;
        }
        
        $required_keys = array('user_name', 'user_email', 'survey_id', 'response_id', 'status', 'post_id');
        foreach ($required_keys as $key) {
            if (!array_key_exists($key, $data)) {
                return false;
            }
        }
        
        return $data['user_name'] === $user->display_name && $data['post_id'] === $post->ID;
    }
    
    /**
     * Test notification logging
     */
    public function test_notification_logging() {
        // Clear existing log
        $this->notifications->clear_notification_log();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->notifications);
        $method = $reflection->getMethod('log_notification_sent');
        $method->setAccessible(true);
        
        $method->invoke($this->notifications, $this->test_user_id, TPAK_Notifications::TYPE_ASSIGNMENT, $this->test_post_id, array());
        
        $log = $this->notifications->get_notification_log(1);
        
        if (empty($log)) {
            return false;
        }
        
        $entry = $log[0];
        return $entry['user_id'] == $this->test_user_id && $entry['type'] === TPAK_Notifications::TYPE_ASSIGNMENT;
    }
    
    /**
     * Test error logging
     */
    public function test_error_logging() {
        // Clear existing errors
        $this->notifications->clear_notification_errors();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->notifications);
        $method = $reflection->getMethod('log_error');
        $method->setAccessible(true);
        
        $method->invoke($this->notifications, 'Test error message', array('test' => 'context'));
        
        $errors = $this->notifications->get_notification_errors(1);
        
        if (empty($errors)) {
            return false;
        }
        
        $error = $errors[0];
        return $error['message'] === 'Test error message' && $error['level'] === 'error';
    }
    
    /**
     * Test settings update
     */
    public function test_settings_update() {
        $new_settings = array(
            'enabled' => false,
            'from_name' => 'Test System',
            'from_email' => 'test@example.com'
        );
        
        $result = $this->notifications->update_notification_settings($new_settings);
        
        if (!$result) {
            return false;
        }
        
        $updated_settings = $this->notifications->get_notification_settings();
        
        return $updated_settings['enabled'] === false && 
               $updated_settings['from_name'] === 'Test System' &&
               $updated_settings['from_email'] === 'test@example.com';
    }
    
    /**
     * Test HTML content type
     */
    public function test_html_content_type() {
        $content_type = $this->notifications->set_html_content_type();
        return $content_type === 'text/html';
    }
    
    /**
     * Test notification templates
     */
    public function test_notification_templates() {
        $test_data = array(
            'user_name' => 'Test User',
            'survey_id' => '12345',
            'response_id' => '67890',
            'status' => 'pending_a',
            'include_action_links' => true,
            'include_data_details' => true
        );
        
        // Test assignment template
        $assignment_template = $this->notifications->get_notification_template(TPAK_Notifications::TYPE_ASSIGNMENT, $test_data);
        if (strpos($assignment_template, 'New Task Assignment') === false) {
            return false;
        }
        
        // Test status change template
        $status_template = $this->notifications->get_notification_template(TPAK_Notifications::TYPE_STATUS_CHANGE, $test_data);
        if (strpos($status_template, 'Status Update Notification') === false) {
            return false;
        }
        
        // Test rejection template
        $rejection_template = $this->notifications->get_notification_template(TPAK_Notifications::TYPE_REJECTION, $test_data);
        if (strpos($rejection_template, 'Data Rejected') === false) {
            return false;
        }
        
        // Test approval template
        $approval_template = $this->notifications->get_notification_template(TPAK_Notifications::TYPE_APPROVAL, $test_data);
        if (strpos($approval_template, 'Data Approved') === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Cleanup test data
     */
    private function cleanup_test_data() {
        // Delete test user
        if ($this->test_user_id) {
            wp_delete_user($this->test_user_id);
        }
        
        // Delete test post
        if ($this->test_post_id) {
            wp_delete_post($this->test_post_id, true);
        }
        
        // Clear notification settings
        delete_option('tpak_dq_notification_settings');
        delete_option('tpak_dq_notification_log');
        delete_option('tpak_dq_notification_errors');
    }
}

// Run tests if accessed directly
if (defined('WP_CLI') || (defined('DOING_AJAX') && DOING_AJAX) || 
    (isset($_GET['run_tests']) && $_GET['run_tests'] === 'notifications')) {
    
    // Ensure WordPress is loaded
    if (!function_exists('wp_create_user')) {
        die('WordPress not loaded');
    }
    
    // Ensure our classes are loaded
    if (!class_exists('TPAK_Notifications')) {
        die('TPAK_Notifications class not found');
    }
    
    $test = new Test_TPAK_Notifications();
    $test->run_tests();
}