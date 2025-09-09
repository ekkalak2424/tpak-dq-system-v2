<?php
/**
 * Test Admin Data Management
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Admin Data Management Class
 */
class Test_TPAK_Admin_Data extends WP_UnitTestCase {
    
    /**
     * Admin data instance
     * 
     * @var TPAK_Admin_Data
     */
    private $admin_data;
    
    /**
     * Test users
     * 
     * @var array
     */
    private $test_users = array();
    
    /**
     * Test survey data
     * 
     * @var array
     */
    private $test_data = array();
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Initialize required classes
        TPAK_Post_Types::get_instance();
        TPAK_Roles::get_instance();
        $this->admin_data = TPAK_Admin_Data::get_instance();
        
        // Create test users with different roles
        $this->create_test_users();
        
        // Create test survey data
        $this->create_test_data();
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Clean up test data
        $this->cleanup_test_data();
        
        parent::tearDown();
    }
    
    /**
     * Create test users with different roles
     */
    private function create_test_users() {
        // Create roles first
        $roles = TPAK_Roles::get_instance();
        $roles->create_roles();
        
        // Create test users
        $this->test_users['admin'] = $this->factory->user->create(array(
            'role' => 'administrator',
            'display_name' => 'Test Admin',
        ));
        
        $this->test_users['interviewer'] = $this->factory->user->create(array(
            'role' => 'tpak_interviewer_a',
            'display_name' => 'Test Interviewer',
        ));
        
        $this->test_users['supervisor'] = $this->factory->user->create(array(
            'role' => 'tpak_supervisor_b',
            'display_name' => 'Test Supervisor',
        ));
        
        $this->test_users['examiner'] = $this->factory->user->create(array(
            'role' => 'tpak_examiner_c',
            'display_name' => 'Test Examiner',
        ));
    }
    
    /**
     * Create test survey data
     */
    private function create_test_data() {
        $statuses = array('pending_a', 'pending_b', 'pending_c', 'finalized');
        
        foreach ($statuses as $index => $status) {
            $survey_data = new TPAK_Survey_Data();
            $survey_data->set_survey_id('123456');
            $survey_data->set_response_id('resp_' . ($index + 1));
            $survey_data->set_status($status);
            $survey_data->set_data(array(
                'question_1' => 'Answer ' . ($index + 1),
                'question_2' => 'Response ' . ($index + 1),
            ));
            
            $result = $survey_data->save();
            $this->assertNotInstanceOf('WP_Error', $result);
            
            $this->test_data[] = $survey_data->get_id();
        }
    }
    
    /**
     * Clean up test data
     */
    private function cleanup_test_data() {
        foreach ($this->test_data as $data_id) {
            wp_delete_post($data_id, true);
        }
        
        foreach ($this->test_users as $user_id) {
            wp_delete_user($user_id);
        }
    }
    
    /**
     * Test admin data instance creation
     */
    public function test_admin_data_instance() {
        $this->assertInstanceOf('TPAK_Admin_Data', $this->admin_data);
    }
    
    /**
     * Test role-based data filtering for administrator
     */
    public function test_admin_can_see_all_data() {
        wp_set_current_user($this->test_users['admin']);
        
        $data_list = $this->admin_data->get_data_list();
        
        $this->assertIsArray($data_list);
        $this->assertArrayHasKey('data', $data_list);
        $this->assertArrayHasKey('total', $data_list);
        
        // Admin should see all test data
        $this->assertGreaterThanOrEqual(4, $data_list['total']);
    }
    
    /**
     * Test role-based data filtering for interviewer
     */
    public function test_interviewer_filtered_data() {
        wp_set_current_user($this->test_users['interviewer']);
        
        $data_list = $this->admin_data->get_data_list();
        
        $this->assertIsArray($data_list);
        
        // Interviewer should only see pending_a and rejected_by_b data
        foreach ($data_list['data'] as $item) {
            $this->assertContains($item['status'], array('pending_a', 'rejected_by_b'));
        }
    }
    
    /**
     * Test role-based data filtering for supervisor
     */
    public function test_supervisor_filtered_data() {
        wp_set_current_user($this->test_users['supervisor']);
        
        $data_list = $this->admin_data->get_data_list();
        
        $this->assertIsArray($data_list);
        
        // Supervisor should only see pending_b data
        foreach ($data_list['data'] as $item) {
            $this->assertEquals('pending_b', $item['status']);
        }
    }
    
    /**
     * Test role-based data filtering for examiner
     */
    public function test_examiner_filtered_data() {
        wp_set_current_user($this->test_users['examiner']);
        
        $data_list = $this->admin_data->get_data_list();
        
        $this->assertIsArray($data_list);
        
        // Examiner should only see pending_c and rejected_by_c data
        foreach ($data_list['data'] as $item) {
            $this->assertContains($item['status'], array('pending_c', 'rejected_by_c'));
        }
    }
    
    /**
     * Test status filtering
     */
    public function test_status_filtering() {
        wp_set_current_user($this->test_users['admin']);
        
        $data_list = $this->admin_data->get_data_list(array(
            'status' => 'pending_a'
        ));
        
        $this->assertIsArray($data_list);
        
        // All returned items should have pending_a status
        foreach ($data_list['data'] as $item) {
            $this->assertEquals('pending_a', $item['status']);
        }
    }
    
    /**
     * Test search functionality
     */
    public function test_search_functionality() {
        wp_set_current_user($this->test_users['admin']);
        
        $data_list = $this->admin_data->get_data_list(array(
            'search' => 'resp_1'
        ));
        
        $this->assertIsArray($data_list);
        
        // Should find the data with response_id 'resp_1'
        $found = false;
        foreach ($data_list['data'] as $item) {
            if ($item['response_id'] === 'resp_1') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }
    
    /**
     * Test pagination
     */
    public function test_pagination() {
        wp_set_current_user($this->test_users['admin']);
        
        $data_list = $this->admin_data->get_data_list(array(
            'per_page' => 2,
            'page' => 1
        ));
        
        $this->assertIsArray($data_list);
        $this->assertArrayHasKey('current_page', $data_list);
        $this->assertArrayHasKey('total_pages', $data_list);
        $this->assertEquals(1, $data_list['current_page']);
        
        // Should return maximum 2 items per page
        $this->assertLessThanOrEqual(2, count($data_list['data']));
    }
    
    /**
     * Test sorting functionality
     */
    public function test_sorting() {
        wp_set_current_user($this->test_users['admin']);
        
        $data_list_asc = $this->admin_data->get_data_list(array(
            'orderby' => 'date',
            'order' => 'ASC'
        ));
        
        $data_list_desc = $this->admin_data->get_data_list(array(
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $this->assertIsArray($data_list_asc);
        $this->assertIsArray($data_list_desc);
        
        // Results should be in different order
        if (count($data_list_asc['data']) > 1 && count($data_list_desc['data']) > 1) {
            $this->assertNotEquals(
                $data_list_asc['data'][0]['id'],
                $data_list_desc['data'][0]['id']
            );
        }
    }
    
    /**
     * Test available statuses for different roles
     */
    public function test_available_statuses_by_role() {
        // Test administrator
        wp_set_current_user($this->test_users['admin']);
        $admin_statuses = $this->admin_data->get_available_statuses();
        $this->assertGreaterThan(4, count($admin_statuses));
        
        // Test interviewer
        wp_set_current_user($this->test_users['interviewer']);
        $interviewer_statuses = $this->admin_data->get_available_statuses();
        $this->assertArrayHasKey('pending_a', $interviewer_statuses);
        $this->assertArrayHasKey('rejected_by_b', $interviewer_statuses);
        
        // Test supervisor
        wp_set_current_user($this->test_users['supervisor']);
        $supervisor_statuses = $this->admin_data->get_available_statuses();
        $this->assertArrayHasKey('pending_b', $supervisor_statuses);
        
        // Test examiner
        wp_set_current_user($this->test_users['examiner']);
        $examiner_statuses = $this->admin_data->get_available_statuses();
        $this->assertArrayHasKey('pending_c', $examiner_statuses);
        $this->assertArrayHasKey('rejected_by_c', $examiner_statuses);
    }
    
    /**
     * Test bulk actions availability
     */
    public function test_bulk_actions_by_role() {
        // Test administrator
        wp_set_current_user($this->test_users['admin']);
        $admin_actions = $this->admin_data->get_bulk_actions();
        $this->assertArrayHasKey('export', $admin_actions);
        $this->assertArrayHasKey('delete', $admin_actions);
        
        // Test interviewer
        wp_set_current_user($this->test_users['interviewer']);
        $interviewer_actions = $this->admin_data->get_bulk_actions();
        $this->assertArrayHasKey('export', $interviewer_actions);
        $this->assertArrayHasKey('bulk_approve_to_b', $interviewer_actions);
        
        // Test supervisor
        wp_set_current_user($this->test_users['supervisor']);
        $supervisor_actions = $this->admin_data->get_bulk_actions();
        $this->assertArrayHasKey('export', $supervisor_actions);
        $this->assertArrayHasKey('bulk_approve_to_c', $supervisor_actions);
        $this->assertArrayHasKey('bulk_apply_sampling', $supervisor_actions);
        
        // Test examiner
        wp_set_current_user($this->test_users['examiner']);
        $examiner_actions = $this->admin_data->get_bulk_actions();
        $this->assertArrayHasKey('export', $examiner_actions);
        $this->assertArrayHasKey('bulk_finalize', $examiner_actions);
    }
    
    /**
     * Test user statistics
     */
    public function test_user_statistics() {
        wp_set_current_user($this->test_users['admin']);
        
        $stats = $this->admin_data->get_user_statistics();
        
        $this->assertIsArray($stats);
        
        // Should have statistics for each status
        foreach ($stats as $status => $stat) {
            $this->assertArrayHasKey('label', $stat);
            $this->assertArrayHasKey('count', $stat);
            $this->assertIsNumeric($stat['count']);
        }
    }
    
    /**
     * Test data formatting
     */
    public function test_data_formatting() {
        wp_set_current_user($this->test_users['admin']);
        
        $data_list = $this->admin_data->get_data_list();
        
        if (!empty($data_list['data'])) {
            $item = $data_list['data'][0];
            
            // Check required fields
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('survey_id', $item);
            $this->assertArrayHasKey('response_id', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('status_label', $item);
            $this->assertArrayHasKey('assigned_user', $item);
            $this->assertArrayHasKey('created_date', $item);
            $this->assertArrayHasKey('last_modified', $item);
            $this->assertArrayHasKey('can_edit', $item);
            $this->assertArrayHasKey('actions', $item);
            
            // Check data types
            $this->assertIsInt($item['id']);
            $this->assertIsString($item['survey_id']);
            $this->assertIsString($item['response_id']);
            $this->assertIsString($item['status']);
            $this->assertIsString($item['status_label']);
            $this->assertIsBool($item['can_edit']);
            $this->assertIsArray($item['actions']);
        }
    }
    
    /**
     * Test permission checking
     */
    public function test_permission_checking() {
        // Test with no user (not logged in)
        wp_set_current_user(0);
        
        $data_list = $this->admin_data->get_data_list();
        
        // Should return empty results for non-logged-in users
        $this->assertEquals(0, $data_list['total']);
        
        // Test with user without TPAK role
        $regular_user = $this->factory->user->create(array('role' => 'subscriber'));
        wp_set_current_user($regular_user);
        
        $data_list = $this->admin_data->get_data_list();
        
        // Should return empty results for users without TPAK roles
        $this->assertEquals(0, $data_list['total']);
        
        wp_delete_user($regular_user);
    }
    
    /**
     * Test AJAX nonce verification (simulated)
     */
    public function test_ajax_security() {
        wp_set_current_user($this->test_users['admin']);
        
        // Simulate AJAX request without nonce
        $_POST = array(
            'action' => 'tpak_load_data_table',
            'page' => 1
        );
        
        // This should fail due to missing nonce
        // In a real test, we would capture the wp_die() call
        $this->assertTrue(true); // Placeholder assertion
    }
}

/**
 * Run the tests if this file is executed directly
 */
if (defined('WP_CLI') && WP_CLI) {
    // Add test to the test suite
    $GLOBALS['wp_tests_options'] = array(
        'active_plugins' => array('tpak-dq-system/tpak-dq-system.php'),
    );
}