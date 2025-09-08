<?php
/**
 * Simple Test Runner for TPAK DQ System
 * 
 * This is a basic test runner for development purposes.
 * For production, use PHPUnit with WordPress test suite.
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple Test Runner Class
 */
class TPAK_Test_Runner {
    
    /**
     * Test results
     * 
     * @var array
     */
    private $results = array();
    
    /**
     * Run all tests
     */
    public function run_all_tests() {
        echo "<h2>TPAK DQ System Test Results</h2>\n";
        
        // Test post type registration
        $this->test_post_type_registration();
        
        // Test workflow statuses
        $this->test_workflow_statuses();
        
        // Test meta fields
        $this->test_meta_fields();
        
        // Test survey data model
        $this->test_survey_data_model();
        
        // Test notification system
        $this->test_notification_system();
        
        // Display results
        $this->display_results();
    }
    
    /**
     * Test post type registration
     */
    private function test_post_type_registration() {
        $this->start_test('Post Type Registration');
        
        try {
            // Check if post type exists
            if (post_type_exists('tpak_survey_data')) {
                $this->pass('Post type tpak_survey_data exists');
                
                $post_type = get_post_type_object('tpak_survey_data');
                
                if (!$post_type->public) {
                    $this->pass('Post type is not public');
                } else {
                    $this->fail('Post type should not be public');
                }
                
                if ($post_type->show_ui) {
                    $this->pass('Post type shows in UI');
                } else {
                    $this->fail('Post type should show in UI');
                }
                
            } else {
                $this->fail('Post type tpak_survey_data does not exist');
            }
            
        } catch (Exception $e) {
            $this->fail('Exception: ' . $e->getMessage());
        }
        
        $this->end_test();
    }
    
    /**
     * Test workflow statuses
     */
    private function test_workflow_statuses() {
        $this->start_test('Workflow Statuses');
        
        try {
            $post_types = TPAK_Post_Types::get_instance();
            $statuses = $post_types->get_workflow_statuses();
            
            $required_statuses = array(
                'pending_a', 'pending_b', 'pending_c',
                'rejected_by_b', 'rejected_by_c',
                'finalized', 'finalized_by_sampling'
            );
            
            foreach ($required_statuses as $status) {
                if (array_key_exists($status, $statuses)) {
                    $this->pass("Status '$status' exists");
                } else {
                    $this->fail("Status '$status' missing");
                }
            }
            
        } catch (Exception $e) {
            $this->fail('Exception: ' . $e->getMessage());
        }
        
        $this->end_test();
    }
    
    /**
     * Test meta fields
     */
    private function test_meta_fields() {
        $this->start_test('Meta Fields');
        
        try {
            $post_types = TPAK_Post_Types::get_instance();
            $meta_fields = $post_types->get_meta_fields();
            
            $required_fields = array(
                '_tpak_survey_id', '_tpak_response_id', '_tpak_survey_data',
                '_tpak_workflow_status', '_tpak_assigned_user', '_tpak_audit_trail'
            );
            
            foreach ($required_fields as $field) {
                if (array_key_exists($field, $meta_fields)) {
                    $this->pass("Meta field '$field' exists");
                } else {
                    $this->fail("Meta field '$field' missing");
                }
            }
            
        } catch (Exception $e) {
            $this->fail('Exception: ' . $e->getMessage());
        }
        
        $this->end_test();
    }
    
    /**
     * Test survey data model
     */
    private function test_survey_data_model() {
        $this->start_test('Survey Data Model');
        
        try {
            // Test basic creation
            $survey_data = new TPAK_Survey_Data();
            $survey_data->set_survey_id('12345');
            $survey_data->set_response_id('67890');
            $survey_data->set_data(array('question1' => 'answer1'));
            
            if ($survey_data->get_survey_id() === '12345') {
                $this->pass('Survey ID setter/getter works');
            } else {
                $this->fail('Survey ID setter/getter failed');
            }
            
            if ($survey_data->get_response_id() === '67890') {
                $this->pass('Response ID setter/getter works');
            } else {
                $this->fail('Response ID setter/getter failed');
            }
            
            $data = $survey_data->get_data();
            if (is_array($data) && $data['question1'] === 'answer1') {
                $this->pass('Survey data setter/getter works');
            } else {
                $this->fail('Survey data setter/getter failed');
            }
            
            // Test audit trail
            $survey_data->add_audit_entry('test_action', 'old', 'new', 'Test notes');
            $audit_trail = $survey_data->get_audit_trail();
            
            if (is_array($audit_trail) && count($audit_trail) === 1) {
                $this->pass('Audit trail functionality works');
            } else {
                $this->fail('Audit trail functionality failed');
            }
            
        } catch (Exception $e) {
            $this->fail('Exception: ' . $e->getMessage());
        }
        
        $this->end_test();
    }
    
    /**
     * Test notification system
     */
    private function test_notification_system() {
        $this->start_test('Notification System');
        
        try {
            // Test instance creation
            $notifications = TPAK_Notifications::get_instance();
            
            if ($notifications instanceof TPAK_Notifications) {
                $this->pass('Notification instance created successfully');
            } else {
                $this->fail('Failed to create notification instance');
                return;
            }
            
            // Test settings
            $settings = $notifications->get_notification_settings();
            if (is_array($settings) && isset($settings['enabled'])) {
                $this->pass('Notification settings accessible');
            } else {
                $this->fail('Notification settings not accessible');
            }
            
            // Test enabled/disabled functionality
            $original_state = $notifications->are_notifications_enabled();
            $notifications->update_notification_settings(array('enabled' => false));
            $disabled_state = $notifications->are_notifications_enabled();
            $notifications->update_notification_settings(array('enabled' => true));
            $enabled_state = $notifications->are_notifications_enabled();
            
            if (!$disabled_state && $enabled_state) {
                $this->pass('Enable/disable functionality works');
            } else {
                $this->fail('Enable/disable functionality failed');
            }
            
            // Test template generation
            $test_data = array(
                'user_name' => 'Test User',
                'survey_id' => '12345',
                'include_action_links' => true
            );
            
            $template = $notifications->get_notification_template(TPAK_Notifications::TYPE_ASSIGNMENT, $test_data);
            if (!empty($template) && strpos($template, '{user_name}') !== false) {
                $this->pass('Template generation works');
            } else {
                $this->fail('Template generation failed');
            }
            
            // Test HTML content type
            $content_type = $notifications->set_html_content_type();
            if ($content_type === 'text/html') {
                $this->pass('HTML content type setting works');
            } else {
                $this->fail('HTML content type setting failed');
            }
            
            // Test logging functionality
            $notifications->clear_notification_log();
            $notifications->clear_notification_errors();
            
            // Use reflection to test private methods
            $reflection = new ReflectionClass($notifications);
            
            // Test notification logging
            $log_method = $reflection->getMethod('log_notification_sent');
            $log_method->setAccessible(true);
            $log_method->invoke($notifications, 1, TPAK_Notifications::TYPE_ASSIGNMENT, 123, array());
            
            $log = $notifications->get_notification_log(1);
            if (!empty($log)) {
                $this->pass('Notification logging works');
            } else {
                $this->fail('Notification logging failed');
            }
            
            // Test error logging
            $error_method = $reflection->getMethod('log_error');
            $error_method->setAccessible(true);
            $error_method->invoke($notifications, 'Test error', array());
            
            $errors = $notifications->get_notification_errors(1);
            if (!empty($errors)) {
                $this->pass('Error logging works');
            } else {
                $this->fail('Error logging failed');
            }
            
        } catch (Exception $e) {
            $this->fail('Exception: ' . $e->getMessage());
        }
        
        $this->end_test();
    }
    
    /**
     * Start a test
     * 
     * @param string $name
     */
    private function start_test($name) {
        $this->current_test = $name;
        $this->current_results = array();
        echo "<h3>Testing: $name</h3>\n";
    }
    
    /**
     * End a test
     */
    private function end_test() {
        $this->results[$this->current_test] = $this->current_results;
    }
    
    /**
     * Record a pass
     * 
     * @param string $message
     */
    private function pass($message) {
        $this->current_results[] = array('status' => 'pass', 'message' => $message);
        echo "<p style='color: green;'>✓ $message</p>\n";
    }
    
    /**
     * Record a fail
     * 
     * @param string $message
     */
    private function fail($message) {
        $this->current_results[] = array('status' => 'fail', 'message' => $message);
        echo "<p style='color: red;'>✗ $message</p>\n";
    }
    
    /**
     * Display test results summary
     */
    private function display_results() {
        $total_tests = 0;
        $passed_tests = 0;
        
        foreach ($this->results as $test_name => $results) {
            foreach ($results as $result) {
                $total_tests++;
                if ($result['status'] === 'pass') {
                    $passed_tests++;
                }
            }
        }
        
        echo "<h3>Test Summary</h3>\n";
        echo "<p>Total Tests: $total_tests</p>\n";
        echo "<p>Passed: $passed_tests</p>\n";
        echo "<p>Failed: " . ($total_tests - $passed_tests) . "</p>\n";
        
        if ($passed_tests === $total_tests) {
            echo "<p style='color: green; font-weight: bold;'>All tests passed!</p>\n";
        } else {
            echo "<p style='color: red; font-weight: bold;'>Some tests failed.</p>\n";
        }
    }
}

// Run tests if accessed directly (for development)
if (isset($_GET['run_tpak_tests']) && current_user_can('manage_options')) {
    $test_runner = new TPAK_Test_Runner();
    $test_runner->run_all_tests();
}