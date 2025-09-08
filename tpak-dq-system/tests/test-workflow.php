<?php
/**
 * Unit Tests for Workflow Engine
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

/**
 * Test TPAK Workflow Class
 */
class Test_TPAK_Workflow extends WP_UnitTestCase {
    
    /**
     * Workflow instance
     * 
     * @var TPAK_Workflow
     */
    private $workflow;
    
    /**
     * Test post ID
     * 
     * @var int
     */
    private $test_post_id;
    
    /**
     * Test users
     * 
     * @var array
     */
    private $test_users = array();
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->workflow = TPAK_Workflow::get_instance();
        
        // Create test users with roles
        $roles = TPAK_Roles::get_instance();
        $roles->create_roles();
        
        $this->test_users['interviewer'] = $this->factory->user->create();
        $this->test_users['supervisor'] = $this->factory->user->create();
        $this->test_users['examiner'] = $this->factory->user->create();
        $this->test_users['admin'] = $this->factory->user->create(array('role' => 'administrator'));
        
        // Assign roles
        $roles->assign_role_to_user($this->test_users['interviewer'], 'tpak_interviewer_a');
        $roles->assign_role_to_user($this->test_users['supervisor'], 'tpak_supervisor_b');
        $roles->assign_role_to_user($this->test_users['examiner'], 'tpak_examiner_c');
        
        // Create test post
        $this->test_post_id = wp_insert_post(array(
            'post_type' => 'tpak_survey_data',
            'post_title' => 'Test Survey Data',
            'post_status' => 'publish'
        ));
        
        update_post_meta($this->test_post_id, '_tpak_workflow_status', 'pending_a');
        update_post_meta($this->test_post_id, '_tpak_survey_id', '12345');
        update_post_meta($this->test_post_id, '_tpak_response_id', 'test_001');
    }
    
    /**
     * Test workflow states definition
     */
    public function test_workflow_states() {
        $states = $this->workflow->get_workflow_states();
        
        $this->assertIsArray($states);
        
        // Check required states exist
        $required_states = array(
            'pending_a', 'pending_b', 'pending_c',
            'rejected_by_b', 'rejected_by_c',
            'finalized', 'finalized_by_sampling'
        );
        
        foreach ($required_states as $state) {
            $this->assertArrayHasKey($state, $states);
            $this->assertArrayHasKey('label', $states[$state]);
            $this->assertArrayHasKey('description', $states[$state]);
            $this->assertArrayHasKey('color', $states[$state]);
            $this->assertArrayHasKey('allowed_roles', $states[$state]);
            $this->assertArrayHasKey('actions', $states[$state]);
            $this->assertArrayHasKey('next_states', $states[$state]);
        }
        
        // Check final states
        $this->assertTrue($states['finalized']['is_final'] ?? false);
        $this->assertTrue($states['finalized_by_sampling']['is_final'] ?? false);
    }
    
    /**
     * Test workflow transitions definition
     */
    public function test_workflow_transitions() {
        $transitions = $this->workflow->get_workflow_transitions();
        
        $this->assertIsArray($transitions);
        
        // Check required transitions exist
        $required_transitions = array(
            'approve_to_supervisor', 'approve_to_examiner',
            'reject_to_interviewer', 'reject_to_supervisor',
            'apply_sampling', 'final_approval', 'resubmit_to_supervisor'
        );
        
        foreach ($required_transitions as $transition) {
            $this->assertArrayHasKey($transition, $transitions);
            $this->assertArrayHasKey('from', $transitions[$transition]);
            $this->assertArrayHasKey('to', $transitions[$transition]);
            $this->assertArrayHasKey('label', $transitions[$transition]);
            $this->assertArrayHasKey('required_role', $transitions[$transition]);
        }
        
        // Check sampling transition
        $this->assertTrue($transitions['apply_sampling']['is_sampling'] ?? false);
        $this->assertIsArray($transitions['apply_sampling']['to']);
    }
    
    /**
     * Test transition validation
     */
    public function test_transition_validation() {
        // Valid transition
        $result = $this->workflow->validate_transition(
            $this->test_post_id, 
            'approve_to_supervisor', 
            $this->test_users['interviewer']
        );
        $this->assertTrue($result);
        
        // Invalid post
        $result = $this->workflow->validate_transition(99999, 'approve_to_supervisor', $this->test_users['interviewer']);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_post', $result->get_error_code());
        
        // Invalid action
        $result = $this->workflow->validate_transition($this->test_post_id, 'invalid_action', $this->test_users['interviewer']);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_action', $result->get_error_code());
        
        // Invalid transition from current status
        $result = $this->workflow->validate_transition($this->test_post_id, 'final_approval', $this->test_users['interviewer']);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_transition', $result->get_error_code());
        
        // Insufficient permissions
        $result = $this->workflow->validate_transition($this->test_post_id, 'approve_to_supervisor', $this->test_users['supervisor']);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('insufficient_permissions', $result->get_error_code());
    }
    
    /**
     * Test status transition
     */
    public function test_status_transition() {
        // Test approve to supervisor
        $result = $this->workflow->transition_status(
            $this->test_post_id, 
            'approve_to_supervisor', 
            $this->test_users['interviewer'],
            'Test approval'
        );
        
        $this->assertTrue($result);
        
        // Check status was updated
        $new_status = get_post_meta($this->test_post_id, '_tpak_workflow_status', true);
        $this->assertEquals('pending_b', $new_status);
        
        // Check last modified was updated
        $last_modified = get_post_meta($this->test_post_id, '_tpak_last_modified', true);
        $this->assertNotEmpty($last_modified);
        
        // Check assigned user was updated
        $assigned_user = get_post_meta($this->test_post_id, '_tpak_assigned_user', true);
        $this->assertEquals($this->test_users['supervisor'], $assigned_user);
    }
    
    /**
     * Test sampling gate
     */
    public function test_sampling_gate() {
        // Set status to pending_b first
        update_post_meta($this->test_post_id, '_tpak_workflow_status', 'pending_b');
        
        // Test sampling gate
        $result = $this->workflow->apply_sampling_gate(
            $this->test_post_id, 
            $this->test_users['supervisor'],
            'Sampling test'
        );
        
        $this->assertTrue($result);
        
        // Check status was updated to either finalized_by_sampling or pending_c
        $new_status = get_post_meta($this->test_post_id, '_tpak_workflow_status', true);
        $this->assertContains($new_status, array('finalized_by_sampling', 'pending_c'));
        
        // Check audit trail was updated
        $survey_data = new TPAK_Survey_Data($this->test_post_id);
        $audit_trail = $survey_data->get_audit_trail();
        $this->assertNotEmpty($audit_trail);
        
        // Find sampling entry
        $sampling_entry = null;
        foreach ($audit_trail as $entry) {
            if ($entry['action'] === 'sampling') {
                $sampling_entry = $entry;
                break;
            }
        }
        $this->assertNotNull($sampling_entry);
    }
    
    /**
     * Test available actions
     */
    public function test_available_actions() {
        // Test for interviewer on pending_a
        $actions = $this->workflow->get_available_actions($this->test_post_id, $this->test_users['interviewer']);
        $this->assertArrayHasKey('approve_to_supervisor', $actions);
        $this->assertArrayNotHasKey('approve_to_examiner', $actions);
        
        // Change status to pending_b
        update_post_meta($this->test_post_id, '_tpak_workflow_status', 'pending_b');
        
        // Test for supervisor on pending_b
        $actions = $this->workflow->get_available_actions($this->test_post_id, $this->test_users['supervisor']);
        $this->assertArrayHasKey('approve_to_examiner', $actions);
        $this->assertArrayHasKey('reject_to_interviewer', $actions);
        $this->assertArrayHasKey('apply_sampling', $actions);
        
        // Test for interviewer on pending_b (should have no actions)
        $actions = $this->workflow->get_available_actions($this->test_post_id, $this->test_users['interviewer']);
        $this->assertEmpty($actions);
        
        // Test for admin (should have all available actions)
        $actions = $this->workflow->get_available_actions($this->test_post_id, $this->test_users['admin']);
        $this->assertNotEmpty($actions);
    }
    
    /**
     * Test workflow statistics
     */
    public function test_workflow_statistics() {
        // Create additional test posts with different statuses
        $post_ids = array();
        $statuses = array('pending_a', 'pending_b', 'pending_c', 'finalized');
        
        foreach ($statuses as $status) {
            $post_id = wp_insert_post(array(
                'post_type' => 'tpak_survey_data',
                'post_title' => "Test Post - $status",
                'post_status' => 'publish'
            ));
            update_post_meta($post_id, '_tpak_workflow_status', $status);
            $post_ids[] = $post_id;
        }
        
        $statistics = $this->workflow->get_workflow_statistics();
        
        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('pending_a', $statistics);
        $this->assertArrayHasKey('pending_b', $statistics);
        $this->assertArrayHasKey('pending_c', $statistics);
        $this->assertArrayHasKey('finalized', $statistics);
        
        // Check structure
        foreach ($statistics as $status => $data) {
            $this->assertArrayHasKey('label', $data);
            $this->assertArrayHasKey('count', $data);
            $this->assertArrayHasKey('color', $data);
        }
        
        // Check counts (should be at least 1 for each status we created)
        $this->assertGreaterThanOrEqual(1, $statistics['pending_a']['count']);
        $this->assertGreaterThanOrEqual(1, $statistics['pending_b']['count']);
        $this->assertGreaterThanOrEqual(1, $statistics['pending_c']['count']);
        $this->assertGreaterThanOrEqual(1, $statistics['finalized']['count']);
    }
    
    /**
     * Test performance metrics
     */
    public function test_performance_metrics() {
        // Create completed posts
        $completed_post = wp_insert_post(array(
            'post_type' => 'tpak_survey_data',
            'post_title' => 'Completed Post',
            'post_status' => 'publish',
            'post_date' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ));
        
        update_post_meta($completed_post, '_tpak_workflow_status', 'finalized');
        update_post_meta($completed_post, '_tpak_completion_date', current_time('mysql'));
        
        $metrics = $this->workflow->get_performance_metrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('average_processing_time', $metrics);
        $this->assertArrayHasKey('completion_rate', $metrics);
        $this->assertArrayHasKey('total_processed', $metrics);
        $this->assertArrayHasKey('total_completed', $metrics);
        $this->assertArrayHasKey('sampling_statistics', $metrics);
        
        // Check sampling statistics structure
        $this->assertArrayHasKey('total_sampled', $metrics['sampling_statistics']);
        $this->assertArrayHasKey('finalized_by_sampling', $metrics['sampling_statistics']);
        $this->assertArrayHasKey('sent_to_examiner', $metrics['sampling_statistics']);
    }
    
    /**
     * Test workflow logging
     */
    public function test_workflow_logging() {
        // Clear existing logs
        delete_option('tpak_dq_workflow_logs');
        
        // Log an action
        $this->workflow->log_action($this->test_post_id, 'approve_to_supervisor', $this->test_users['interviewer'], 'Test log');
        
        // Get logs
        $logs = $this->workflow->get_workflow_logs();
        
        $this->assertIsArray($logs);
        $this->assertCount(1, $logs);
        
        $log = $logs[0];
        $this->assertEquals($this->test_post_id, $log['post_id']);
        $this->assertEquals('approve_to_supervisor', $log['action']);
        $this->assertEquals($this->test_users['interviewer'], $log['user_id']);
        $this->assertEquals('Test log', $log['notes']);
        $this->assertArrayHasKey('timestamp', $log);
    }
    
    /**
     * Test workflow logs filtering
     */
    public function test_workflow_logs_filtering() {
        // Clear existing logs
        delete_option('tpak_dq_workflow_logs');
        
        // Create multiple log entries
        $this->workflow->log_action($this->test_post_id, 'approve_to_supervisor', $this->test_users['interviewer']);
        $this->workflow->log_action($this->test_post_id, 'approve_to_examiner', $this->test_users['supervisor']);
        
        // Create another post for different filtering
        $other_post = wp_insert_post(array(
            'post_type' => 'tpak_survey_data',
            'post_title' => 'Other Post',
            'post_status' => 'publish'
        ));
        $this->workflow->log_action($other_post, 'final_approval', $this->test_users['examiner']);
        
        // Test filtering by post_id
        $logs = $this->workflow->get_workflow_logs(array('post_id' => $this->test_post_id));
        $this->assertCount(2, $logs);
        
        // Test filtering by user_id
        $logs = $this->workflow->get_workflow_logs(array('user_id' => $this->test_users['interviewer']));
        $this->assertCount(1, $logs);
        $this->assertEquals('approve_to_supervisor', $logs[0]['action']);
        
        // Test filtering by action
        $logs = $this->workflow->get_workflow_logs(array('action' => 'final_approval'));
        $this->assertCount(1, $logs);
        $this->assertEquals($other_post, $logs[0]['post_id']);
        
        // Test limit
        $logs = $this->workflow->get_workflow_logs(array(), 2);
        $this->assertCount(2, $logs);
    }
    
    /**
     * Test complete workflow process
     */
    public function test_complete_workflow_process() {
        // Start with pending_a
        $this->assertEquals('pending_a', get_post_meta($this->test_post_id, '_tpak_workflow_status', true));
        
        // Interviewer approves to supervisor
        $result = $this->workflow->transition_status($this->test_post_id, 'approve_to_supervisor', $this->test_users['interviewer']);
        $this->assertTrue($result);
        $this->assertEquals('pending_b', get_post_meta($this->test_post_id, '_tpak_workflow_status', true));
        
        // Supervisor approves to examiner
        $result = $this->workflow->transition_status($this->test_post_id, 'approve_to_examiner', $this->test_users['supervisor']);
        $this->assertTrue($result);
        $this->assertEquals('pending_c', get_post_meta($this->test_post_id, '_tpak_workflow_status', true));
        
        // Examiner gives final approval
        $result = $this->workflow->transition_status($this->test_post_id, 'final_approval', $this->test_users['examiner']);
        $this->assertTrue($result);
        $this->assertEquals('finalized', get_post_meta($this->test_post_id, '_tpak_workflow_status', true));
        
        // Check completion date was set
        $completion_date = get_post_meta($this->test_post_id, '_tpak_completion_date', true);
        $this->assertNotEmpty($completion_date);
    }
    
    /**
     * Test rejection workflow
     */
    public function test_rejection_workflow() {
        // Move to pending_b
        update_post_meta($this->test_post_id, '_tpak_workflow_status', 'pending_b');
        
        // Supervisor rejects to interviewer
        $result = $this->workflow->transition_status($this->test_post_id, 'reject_to_interviewer', $this->test_users['supervisor'], 'Needs correction');
        $this->assertTrue($result);
        $this->assertEquals('rejected_by_b', get_post_meta($this->test_post_id, '_tpak_workflow_status', true));
        
        // Interviewer resubmits
        $result = $this->workflow->transition_status($this->test_post_id, 'resubmit_to_supervisor', $this->test_users['interviewer']);
        $this->assertTrue($result);
        $this->assertEquals('pending_b', get_post_meta($this->test_post_id, '_tpak_workflow_status', true));
    }
    
    /**
     * Test user assignment
     */
    public function test_user_assignment() {
        // Test assignment for pending_a
        $reflection = new ReflectionClass($this->workflow);
        $method = $reflection->getMethod('assign_user_for_status');
        $method->setAccessible(true);
        
        $method->invoke($this->workflow, $this->test_post_id, 'pending_a');
        $assigned_user = get_post_meta($this->test_post_id, '_tpak_assigned_user', true);
        $this->assertEquals($this->test_users['interviewer'], $assigned_user);
        
        // Test assignment for pending_b
        $method->invoke($this->workflow, $this->test_post_id, 'pending_b');
        $assigned_user = get_post_meta($this->test_post_id, '_tpak_assigned_user', true);
        $this->assertEquals($this->test_users['supervisor'], $assigned_user);
        
        // Test assignment for pending_c
        $method->invoke($this->workflow, $this->test_post_id, 'pending_c');
        $assigned_user = get_post_meta($this->test_post_id, '_tpak_assigned_user', true);
        $this->assertEquals($this->test_users['examiner'], $assigned_user);
    }
}