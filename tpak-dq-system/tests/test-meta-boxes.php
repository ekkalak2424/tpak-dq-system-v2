<?php
/**
 * Meta Boxes Tests
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Meta Boxes Test Class
 */
class TPAK_Meta_Boxes_Test {
    
    /**
     * Test post ID
     * 
     * @var int
     */
    private $test_post_id;
    
    /**
     * Test user IDs
     * 
     * @var array
     */
    private $test_users = array();
    
    /**
     * Run all tests
     */
    public function run_tests() {
        echo "<h2>TPAK Meta Boxes Tests</h2>\n";
        
        $this->setup_test_data();
        
        $tests = array(
            'test_meta_box_registration',
            'test_survey_data_meta_box_rendering',
            'test_workflow_meta_box_rendering',
            'test_audit_trail_meta_box_rendering',
            'test_meta_box_saving',
            'test_json_validation',
            'test_workflow_action_handling',
            'test_permission_checks',
            'test_script_enqueuing',
        );
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            try {
                $result = $this->$test();
                if ($result) {
                    echo "<span style='color: green;'>✓</span> {$test}<br>\n";
                    $passed++;
                } else {
                    echo "<span style='color: red;'>✗</span> {$test}<br>\n";
                }
            } catch (Exception $e) {
                echo "<span style='color: red;'>✗</span> {$test} - Exception: " . $e->getMessage() . "<br>\n";
            }
        }
        
        echo "<br><strong>Results: {$passed}/{$total} tests passed</strong><br>\n";
        
        $this->cleanup_test_data();
        
        return $passed === $total;
    }
    
    /**
     * Setup test data
     */
    private function setup_test_data() {
        // Create test users
        $this->test_users['admin'] = wp_create_user('test_admin_meta', 'password', 'admin@test.com');
        $this->test_users['interviewer'] = wp_create_user('test_interviewer_meta', 'password', 'interviewer@test.com');
        $this->test_users['supervisor'] = wp_create_user('test_supervisor_meta', 'password', 'supervisor@test.com');
        $this->test_users['examiner'] = wp_create_user('test_examiner_meta', 'password', 'examiner@test.com');
        
        // Assign roles
        $admin_user = get_user_by('id', $this->test_users['admin']);
        $admin_user->add_role('administrator');
        
        $interviewer_user = get_user_by('id', $this->test_users['interviewer']);
        $interviewer_user->add_role('tpak_interviewer_a');
        
        $supervisor_user = get_user_by('id', $this->test_users['supervisor']);
        $supervisor_user->add_role('tpak_supervisor_b');
        
        $examiner_user = get_user_by('id', $this->test_users['examiner']);
        $examiner_user->add_role('tpak_examiner_c');
        
        // Create test survey data post
        $this->test_post_id = wp_insert_post(array(
            'post_type' => 'tpak_survey_data',
            'post_title' => 'Test Survey Data for Meta Boxes',
            'post_status' => 'publish',
            'meta_input' => array(
                '_tpak_survey_id' => 'TEST123',
                '_tpak_response_id' => 'RESP456',
                '_tpak_survey_data' => wp_json_encode(array(
                    'question1' => 'Answer 1',
                    'question2' => 'Answer 2',
                    'question3' => array('option1', 'option2')
                )),
                '_tpak_workflow_status' => 'pending_a',
                '_tpak_assigned_user' => $this->test_users['interviewer'],
                '_tpak_audit_trail' => wp_json_encode(array(
                    array(
                        'timestamp' => current_time('mysql'),
                        'user_id' => $this->test_users['admin'],
                        'action' => 'imported',
                        'old_value' => null,
                        'new_value' => null,
                        'notes' => 'Test data imported'
                    )
                )),
                '_tpak_import_date' => current_time('mysql'),
                '_tpak_last_modified' => current_time('mysql')
            )
        ));
    }
    
    /**
     * Cleanup test data
     */
    private function cleanup_test_data() {
        // Delete test post
        if ($this->test_post_id) {
            wp_delete_post($this->test_post_id, true);
        }
        
        // Delete test users
        foreach ($this->test_users as $user_id) {
            wp_delete_user($user_id);
        }
    }
    
    /**
     * Test meta box registration
     */
    private function test_meta_box_registration() {
        global $wp_meta_boxes;
        
        // Initialize meta boxes
        $meta_boxes = TPAK_Meta_Boxes::get_instance();
        
        // Simulate add_meta_boxes action
        do_action('add_meta_boxes');
        
        // Check if meta boxes are registered
        $registered_boxes = $wp_meta_boxes['tpak_survey_data'] ?? array();
        
        $expected_boxes = array(
            'tpak_survey_data_details',
            'tpak_workflow_status',
            'tpak_audit_trail'
        );
        
        foreach ($expected_boxes as $box_id) {
            $found = false;
            foreach ($registered_boxes as $context => $priorities) {
                foreach ($priorities as $priority => $boxes) {
                    if (isset($boxes[$box_id])) {
                        $found = true;
                        break 2;
                    }
                }
            }
            
            if (!$found) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test survey data meta box rendering
     */
    private function test_survey_data_meta_box_rendering() {
        $meta_boxes = TPAK_Meta_Boxes::get_instance();
        $post = get_post($this->test_post_id);
        
        // Set current user as interviewer (can edit)
        wp_set_current_user($this->test_users['interviewer']);
        
        // Capture output
        ob_start();
        $meta_boxes->render_survey_data_meta_box($post);
        $output = ob_get_clean();
        
        // Check if required elements are present
        $required_elements = array(
            'tpak_survey_id',
            'tpak_response_id',
            'tpak_import_date',
            'tpak_survey_data',
            'TEST123',
            'RESP456'
        );
        
        foreach ($required_elements as $element) {
            if (strpos($output, $element) === false) {
                return false;
            }
        }
        
        // Check if textarea is editable for interviewer
        if (strpos($output, '<textarea') === false) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Test workflow meta box rendering
     */
    private function test_workflow_meta_box_rendering() {
        $meta_boxes = TPAK_Meta_Boxes::get_instance();
        $post = get_post($this->test_post_id);
        
        // Set current user as interviewer
        wp_set_current_user($this->test_users['interviewer']);
        
        // Capture output
        ob_start();
        $meta_boxes->render_workflow_meta_box($post);
        $output = ob_get_clean();
        
        // Check if required elements are present
        $required_elements = array(
            'Current Status',
            'Assigned User',
            'Available Actions',
            'pending_a',
            'tpak-status-badge',
            'tpak-workflow-action-btn'
        );
        
        foreach ($required_elements as $element) {
            if (strpos($output, $element) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test audit trail meta box rendering
     */
    private function test_audit_trail_meta_box_rendering() {
        $meta_boxes = TPAK_Meta_Boxes::get_instance();
        $post = get_post($this->test_post_id);
        
        // Capture output
        ob_start();
        $meta_boxes->render_audit_trail_meta_box($post);
        $output = ob_get_clean();
        
        // Check if required elements are present
        $required_elements = array(
            'tpak-audit-entry',
            'tpak-audit-action',
            'imported',
            'Test data imported'
        );
        
        foreach ($required_elements as $element) {
            if (strpos($output, $element) === false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Test meta box saving
     */
    private function test_meta_box_saving() {
        $meta_boxes = TPAK_Meta_Boxes::get_instance();
        
        // Set current user as interviewer (can edit)
        wp_set_current_user($this->test_users['interviewer']);
        
        // Simulate form submission
        $_POST['tpak_meta_boxes_nonce'] = wp_create_nonce('tpak_meta_boxes');
        $_POST['tpak_survey_data'] = wp_json_encode(array(
            'question1' => 'Updated Answer 1',
            'question2' => 'Updated Answer 2',
            'new_question' => 'New Answer'
        ));
        
        // Get post object
        $post = get_post($this->test_post_id);
        
        // Call save method
        $meta_boxes->save_meta_boxes($this->test_post_id, $post);
        
        // Check if data was saved
        $saved_data = get_post_meta($this->test_post_id, '_tpak_survey_data', true);
        $decoded_data = json_decode($saved_data, true);
        
        if (!isset($decoded_data['new_question']) || $decoded_data['new_question'] !== 'New Answer') {
            return false;
        }
        
        if (!isset($decoded_data['question1']) || $decoded_data['question1'] !== 'Updated Answer 1') {
            return false;
        }
        
        // Clean up
        unset($_POST['tpak_meta_boxes_nonce']);
        unset($_POST['tpak_survey_data']);
        
        return true;
    }
    
    /**
     * Test JSON validation
     */
    private function test_json_validation() {
        $meta_boxes = TPAK_Meta_Boxes::get_instance();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($meta_boxes);
        $method = $reflection->getMethod('validate_survey_data');
        $method->setAccessible(true);
        
        // Test valid JSON
        $valid_json = '{"question1": "answer1", "question2": ["option1", "option2"]}';
        $result = $method->invoke($meta_boxes, $valid_json);
        if (is_wp_error($result)) {
            return false;
        }
        
        // Test invalid JSON
        $invalid_json = '{"question1": "answer1", "question2": [}';
        $result = $method->invoke($meta_boxes, $invalid_json);
        if (!is_wp_error($result)) {
            return false;
        }
        
        // Test empty data
        $empty_json = '';
        $result = $method->invoke($meta_boxes, $empty_json);
        if (!is_wp_error($result)) {
            return false;
        }
        
        // Test non-array data
        $non_array_json = '"just a string"';
        $result = $method->invoke($meta_boxes, $non_array_json);
        if (!is_wp_error($result)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Test workflow action handling
     */
    private function test_workflow_action_handling() {
        // Set current user as interviewer
        wp_set_current_user($this->test_users['interviewer']);
        
        // Simulate AJAX request
        $_POST['nonce'] = wp_create_nonce('tpak_workflow_action');
        $_POST['post_id'] = $this->test_post_id;
        $_POST['action'] = 'approve_to_supervisor';
        $_POST['notes'] = 'Test approval notes';
        
        // Capture output
        ob_start();
        
        try {
            // This would normally be called via AJAX
            $workflow = TPAK_Workflow::get_instance();
            $result = $workflow->transition_status($this->test_post_id, 'approve_to_supervisor', $this->test_users['interviewer'], 'Test approval notes');
            
            if (is_wp_error($result)) {
                ob_end_clean();
                return false;
            }
            
            // Check if status was updated
            $new_status = get_post_meta($this->test_post_id, '_tpak_workflow_status', true);
            if ($new_status !== 'pending_b') {
                ob_end_clean();
                return false;
            }
            
        } catch (Exception $e) {
            ob_end_clean();
            return false;
        }
        
        ob_end_clean();
        
        // Clean up
        unset($_POST['nonce']);
        unset($_POST['post_id']);
        unset($_POST['action']);
        unset($_POST['notes']);
        
        return true;
    }
    
    /**
     * Test permission checks
     */
    private function test_permission_checks() {
        // Test with examiner trying to edit pending_a data
        wp_set_current_user($this->test_users['examiner']);
        
        $survey_data = new TPAK_Survey_Data($this->test_post_id);
        
        // Examiner should not be able to edit pending_a data
        if ($survey_data->can_user_edit($this->test_users['examiner'])) {
            return false;
        }
        
        // Test with interviewer (should be able to edit pending_a)
        if (!$survey_data->can_user_edit($this->test_users['interviewer'])) {
            return false;
        }
        
        // Test with admin (should always be able to edit)
        if (!$survey_data->can_user_edit($this->test_users['admin'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Test script enqueuing
     */
    private function test_script_enqueuing() {
        global $wp_scripts, $wp_styles;
        
        // Set up global post
        global $post;
        $post = get_post($this->test_post_id);
        
        $meta_boxes = TPAK_Meta_Boxes::get_instance();
        
        // Simulate admin page
        set_current_screen('post');
        
        // Call enqueue method
        $meta_boxes->enqueue_scripts('post.php');
        
        // Check if scripts are enqueued
        if (!wp_script_is('tpak-meta-boxes', 'enqueued') && !wp_script_is('tpak-meta-boxes', 'registered')) {
            return false;
        }
        
        // Check if styles are enqueued
        if (!wp_style_is('tpak-meta-boxes', 'enqueued') && !wp_style_is('tpak-meta-boxes', 'registered')) {
            return false;
        }
        
        return true;
    }
}

// Run tests if accessed directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    // Load WordPress
    $wp_load_path = dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    }
    
    $test = new TPAK_Meta_Boxes_Test();
    $test->run_tests();
}