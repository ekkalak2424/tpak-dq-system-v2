<?php
/**
 * Unit Tests for Roles Management
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

/**
 * Test TPAK Roles Class
 */
class Test_TPAK_Roles extends WP_UnitTestCase {
    
    /**
     * Roles instance
     * 
     * @var TPAK_Roles
     */
    private $roles;
    
    /**
     * Test user IDs
     * 
     * @var array
     */
    private $test_users = array();
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->roles = TPAK_Roles::get_instance();
        
        // Create test users
        $this->test_users['interviewer'] = $this->factory->user->create(array(
            'role' => 'subscriber'
        ));
        $this->test_users['supervisor'] = $this->factory->user->create(array(
            'role' => 'subscriber'
        ));
        $this->test_users['examiner'] = $this->factory->user->create(array(
            'role' => 'subscriber'
        ));
        $this->test_users['admin'] = $this->factory->user->create(array(
            'role' => 'administrator'
        ));
    }
    
    /**
     * Clean up after test
     */
    public function tearDown(): void {
        // Remove custom roles
        $this->roles->remove_roles();
        parent::tearDown();
    }
    
    /**
     * Test role creation
     */
    public function test_role_creation() {
        // Create roles
        $this->roles->create_roles();
        
        // Check if roles exist
        $this->assertNotNull(get_role('tpak_interviewer_a'));
        $this->assertNotNull(get_role('tpak_supervisor_b'));
        $this->assertNotNull(get_role('tpak_examiner_c'));
        
        // Check role display names
        $interviewer_role = get_role('tpak_interviewer_a');
        $this->assertNotEmpty($interviewer_role->capabilities);
        
        // Check specific capabilities
        $this->assertTrue($interviewer_role->has_cap('tpak_edit_pending_a'));
        $this->assertTrue($interviewer_role->has_cap('tpak_review_pending_a'));
        $this->assertFalse($interviewer_role->has_cap('tpak_review_pending_b'));
    }
    
    /**
     * Test role capabilities
     */
    public function test_role_capabilities() {
        $this->roles->create_roles();
        
        // Test Interviewer A capabilities
        $interviewer_role = get_role('tpak_interviewer_a');
        $this->assertTrue($interviewer_role->has_cap('edit_tpak_survey_data'));
        $this->assertTrue($interviewer_role->has_cap('tpak_edit_pending_a'));
        $this->assertTrue($interviewer_role->has_cap('tpak_approve_to_supervisor'));
        $this->assertFalse($interviewer_role->has_cap('tpak_review_pending_b'));
        
        // Test Supervisor B capabilities
        $supervisor_role = get_role('tpak_supervisor_b');
        $this->assertTrue($supervisor_role->has_cap('tpak_review_pending_b'));
        $this->assertTrue($supervisor_role->has_cap('tpak_apply_sampling_gate'));
        $this->assertFalse($supervisor_role->has_cap('edit_tpak_survey_data'));
        
        // Test Examiner C capabilities
        $examiner_role = get_role('tpak_examiner_c');
        $this->assertTrue($examiner_role->has_cap('tpak_review_pending_c'));
        $this->assertTrue($examiner_role->has_cap('tpak_final_approval'));
        $this->assertFalse($examiner_role->has_cap('edit_tpak_survey_data'));
    }
    
    /**
     * Test administrator capabilities
     */
    public function test_administrator_capabilities() {
        $this->roles->create_roles();
        
        $admin_role = get_role('administrator');
        
        // Check admin has all TPAK capabilities
        $this->assertTrue($admin_role->has_cap('edit_tpak_survey_data'));
        $this->assertTrue($admin_role->has_cap('tpak_manage_settings'));
        $this->assertTrue($admin_role->has_cap('tpak_review_pending_a'));
        $this->assertTrue($admin_role->has_cap('tpak_review_pending_b'));
        $this->assertTrue($admin_role->has_cap('tpak_review_pending_c'));
        $this->assertTrue($admin_role->has_cap('tpak_import_data'));
    }
    
    /**
     * Test role assignment
     */
    public function test_role_assignment() {
        $this->roles->create_roles();
        
        $user_id = $this->test_users['interviewer'];
        
        // Assign role
        $result = $this->roles->assign_role_to_user($user_id, 'tpak_interviewer_a');
        $this->assertTrue($result);
        
        // Check if user has role
        $this->assertTrue($this->roles->user_has_tpak_role($user_id, 'tpak_interviewer_a'));
        $this->assertEquals('tpak_interviewer_a', $this->roles->get_user_tpak_role($user_id));
        
        // Assign different role (should remove previous)
        $result = $this->roles->assign_role_to_user($user_id, 'tpak_supervisor_b');
        $this->assertTrue($result);
        
        $this->assertFalse($this->roles->user_has_tpak_role($user_id, 'tpak_interviewer_a'));
        $this->assertTrue($this->roles->user_has_tpak_role($user_id, 'tpak_supervisor_b'));
    }
    
    /**
     * Test role removal
     */
    public function test_role_removal() {
        $this->roles->create_roles();
        
        $user_id = $this->test_users['supervisor'];
        
        // Assign and then remove role
        $this->roles->assign_role_to_user($user_id, 'tpak_supervisor_b');
        $this->assertTrue($this->roles->user_has_tpak_role($user_id, 'tpak_supervisor_b'));
        
        $result = $this->roles->remove_role_from_user($user_id, 'tpak_supervisor_b');
        $this->assertTrue($result);
        $this->assertFalse($this->roles->user_has_tpak_role($user_id, 'tpak_supervisor_b'));
    }
    
    /**
     * Test status access permissions
     */
    public function test_status_access_permissions() {
        $this->roles->create_roles();
        
        // Assign roles to test users
        $this->roles->assign_role_to_user($this->test_users['interviewer'], 'tpak_interviewer_a');
        $this->roles->assign_role_to_user($this->test_users['supervisor'], 'tpak_supervisor_b');
        $this->roles->assign_role_to_user($this->test_users['examiner'], 'tpak_examiner_c');
        
        // Test Interviewer A access
        $this->assertTrue($this->roles->can_user_access_status($this->test_users['interviewer'], 'pending_a'));
        $this->assertTrue($this->roles->can_user_access_status($this->test_users['interviewer'], 'rejected_by_b'));
        $this->assertFalse($this->roles->can_user_access_status($this->test_users['interviewer'], 'pending_b'));
        
        // Test Supervisor B access
        $this->assertTrue($this->roles->can_user_access_status($this->test_users['supervisor'], 'pending_b'));
        $this->assertFalse($this->roles->can_user_access_status($this->test_users['supervisor'], 'pending_a'));
        $this->assertFalse($this->roles->can_user_access_status($this->test_users['supervisor'], 'pending_c'));
        
        // Test Examiner C access
        $this->assertTrue($this->roles->can_user_access_status($this->test_users['examiner'], 'pending_c'));
        $this->assertTrue($this->roles->can_user_access_status($this->test_users['examiner'], 'rejected_by_c'));
        $this->assertFalse($this->roles->can_user_access_status($this->test_users['examiner'], 'pending_a'));
        
        // Test Administrator access (should access everything)
        $this->assertTrue($this->roles->can_user_access_status($this->test_users['admin'], 'pending_a'));
        $this->assertTrue($this->roles->can_user_access_status($this->test_users['admin'], 'pending_b'));
        $this->assertTrue($this->roles->can_user_access_status($this->test_users['admin'], 'pending_c'));
        $this->assertTrue($this->roles->can_user_access_status($this->test_users['admin'], 'finalized'));
    }
    
    /**
     * Test edit permissions
     */
    public function test_edit_permissions() {
        $this->roles->create_roles();
        
        // Assign roles to test users
        $this->roles->assign_role_to_user($this->test_users['interviewer'], 'tpak_interviewer_a');
        $this->roles->assign_role_to_user($this->test_users['supervisor'], 'tpak_supervisor_b');
        $this->roles->assign_role_to_user($this->test_users['examiner'], 'tpak_examiner_c');
        
        // Only Interviewer A can edit data
        $this->assertTrue($this->roles->can_user_edit_status($this->test_users['interviewer'], 'pending_a'));
        $this->assertTrue($this->roles->can_user_edit_status($this->test_users['interviewer'], 'rejected_by_b'));
        $this->assertFalse($this->roles->can_user_edit_status($this->test_users['interviewer'], 'pending_b'));
        
        // Supervisor and Examiner cannot edit (only review)
        $this->assertFalse($this->roles->can_user_edit_status($this->test_users['supervisor'], 'pending_b'));
        $this->assertFalse($this->roles->can_user_edit_status($this->test_users['examiner'], 'pending_c'));
        
        // Administrator can edit non-finalized data
        $this->assertTrue($this->roles->can_user_edit_status($this->test_users['admin'], 'pending_a'));
        $this->assertTrue($this->roles->can_user_edit_status($this->test_users['admin'], 'pending_b'));
        $this->assertFalse($this->roles->can_user_edit_status($this->test_users['admin'], 'finalized'));
    }
    
    /**
     * Test get users by role
     */
    public function test_get_users_by_role() {
        $this->roles->create_roles();
        
        // Assign roles
        $this->roles->assign_role_to_user($this->test_users['interviewer'], 'tpak_interviewer_a');
        $this->roles->assign_role_to_user($this->test_users['supervisor'], 'tpak_supervisor_b');
        
        // Get users by role
        $interviewers = $this->roles->get_users_by_role('tpak_interviewer_a');
        $supervisors = $this->roles->get_users_by_role('tpak_supervisor_b');
        $examiners = $this->roles->get_users_by_role('tpak_examiner_c');
        
        $this->assertCount(1, $interviewers);
        $this->assertCount(1, $supervisors);
        $this->assertCount(0, $examiners);
        
        $this->assertEquals($this->test_users['interviewer'], $interviewers[0]->ID);
        $this->assertEquals($this->test_users['supervisor'], $supervisors[0]->ID);
    }
    
    /**
     * Test role display names and descriptions
     */
    public function test_role_display_names_and_descriptions() {
        $roles = $this->roles->get_tpak_roles();
        
        $this->assertArrayHasKey('tpak_interviewer_a', $roles);
        $this->assertArrayHasKey('tpak_supervisor_b', $roles);
        $this->assertArrayHasKey('tpak_examiner_c', $roles);
        
        // Test display names
        $this->assertNotEmpty($this->roles->get_role_display_name('tpak_interviewer_a'));
        $this->assertNotEmpty($this->roles->get_role_display_name('tpak_supervisor_b'));
        $this->assertNotEmpty($this->roles->get_role_display_name('tpak_examiner_c'));
        
        // Test descriptions
        $this->assertNotEmpty($this->roles->get_role_description('tpak_interviewer_a'));
        $this->assertNotEmpty($this->roles->get_role_description('tpak_supervisor_b'));
        $this->assertNotEmpty($this->roles->get_role_description('tpak_examiner_c'));
    }
    
    /**
     * Test invalid role operations
     */
    public function test_invalid_role_operations() {
        $this->roles->create_roles();
        
        // Test invalid user ID
        $this->assertFalse($this->roles->assign_role_to_user(99999, 'tpak_interviewer_a'));
        $this->assertFalse($this->roles->user_has_tpak_role(99999));
        $this->assertNull($this->roles->get_user_tpak_role(99999));
        
        // Test invalid role name
        $this->assertFalse($this->roles->assign_role_to_user($this->test_users['interviewer'], 'invalid_role'));
        
        // Test access with invalid status
        $this->assertFalse($this->roles->can_user_access_status($this->test_users['interviewer'], 'invalid_status'));
    }
}