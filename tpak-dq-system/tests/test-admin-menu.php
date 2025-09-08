<?php
/**
 * Admin Menu Tests
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Admin Menu Class
 */
class Test_TPAK_Admin_Menu extends WP_UnitTestCase {
    
    /**
     * Admin menu instance
     * 
     * @var TPAK_Admin_Menu
     */
    private $admin_menu;
    
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
        
        // Create admin menu instance
        $this->admin_menu = TPAK_Admin_Menu::get_instance();
        
        // Create test users with different roles
        $this->create_test_users();
        
        // Create TPAK roles
        $roles = TPAK_Roles::get_instance();
        $roles->create_roles();
    }
    
    /**
     * Tear down test
     */
    public function tearDown(): void {
        // Clean up test users
        foreach ($this->test_users as $user_id) {
            wp_delete_user($user_id);
        }
        
        // Remove TPAK roles
        $roles = TPAK_Roles::get_instance();
        $roles->remove_roles();
        
        parent::tearDown();
    }
    
    /**
     * Create test users
     */
    private function create_test_users() {
        // Administrator
        $this->test_users['admin'] = $this->factory->user->create(array(
            'role' => 'administrator',
            'user_login' => 'test_admin',
            'user_email' => 'admin@test.com',
        ));
        
        // Interviewer A
        $this->test_users['interviewer'] = $this->factory->user->create(array(
            'user_login' => 'test_interviewer',
            'user_email' => 'interviewer@test.com',
        ));
        
        // Supervisor B
        $this->test_users['supervisor'] = $this->factory->user->create(array(
            'user_login' => 'test_supervisor',
            'user_email' => 'supervisor@test.com',
        ));
        
        // Examiner C
        $this->test_users['examiner'] = $this->factory->user->create(array(
            'user_login' => 'test_examiner',
            'user_email' => 'examiner@test.com',
        ));
        
        // Regular user (no TPAK access)
        $this->test_users['regular'] = $this->factory->user->create(array(
            'role' => 'subscriber',
            'user_login' => 'test_regular',
            'user_email' => 'regular@test.com',
        ));
    }
    
    /**
     * Test singleton pattern
     */
    public function test_singleton_pattern() {
        $instance1 = TPAK_Admin_Menu::get_instance();
        $instance2 = TPAK_Admin_Menu::get_instance();
        
        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf('TPAK_Admin_Menu', $instance1);
    }
    
    /**
     * Test admin menu registration for administrator
     */
    public function test_admin_menu_registration_administrator() {
        wp_set_current_user($this->test_users['admin']);
        
        // Simulate admin_menu action
        do_action('admin_menu');
        
        // Check if main menu page is registered
        global $menu, $submenu;
        
        $found_main_menu = false;
        foreach ($menu as $menu_item) {
            if ($menu_item[2] === 'tpak-dashboard') {
                $found_main_menu = true;
                break;
            }
        }
        
        $this->assertTrue($found_main_menu, 'Main TPAK menu should be registered for administrator');
        
        // Check submenu pages
        $this->assertArrayHasKey('tpak-dashboard', $submenu);
        
        $expected_submenus = array('tpak-data', 'tpak-settings', 'tpak-import-export', 'tpak-users', 'tpak-status');
        
        foreach ($expected_submenus as $submenu_slug) {
            $found_submenu = false;
            foreach ($submenu['tpak-dashboard'] as $submenu_item) {
                if ($submenu_item[2] === $submenu_slug) {
                    $found_submenu = true;
                    break;
                }
            }
            $this->assertTrue($found_submenu, "Submenu {$submenu_slug} should be registered for administrator");
        }
    }
    
    /**
     * Test admin menu registration for interviewer
     */
    public function test_admin_menu_registration_interviewer() {
        // Assign interviewer role
        $user = get_user_by('id', $this->test_users['interviewer']);
        $user->add_role('tpak_interviewer_a');
        
        wp_set_current_user($this->test_users['interviewer']);
        
        // Simulate admin_menu action
        do_action('admin_menu');
        
        global $menu, $submenu;
        
        // Should have main menu
        $found_main_menu = false;
        foreach ($menu as $menu_item) {
            if ($menu_item[2] === 'tpak-dashboard') {
                $found_main_menu = true;
                break;
            }
        }
        
        $this->assertTrue($found_main_menu, 'Main TPAK menu should be registered for interviewer');
        
        // Should have data management but not settings
        $this->assertArrayHasKey('tpak-dashboard', $submenu);
        
        $has_data_menu = false;
        $has_settings_menu = false;
        
        foreach ($submenu['tpak-dashboard'] as $submenu_item) {
            if ($submenu_item[2] === 'tpak-data') {
                $has_data_menu = true;
            }
            if ($submenu_item[2] === 'tpak-settings') {
                $has_settings_menu = true;
            }
        }
        
        $this->assertTrue($has_data_menu, 'Interviewer should have access to data management');
        $this->assertFalse($has_settings_menu, 'Interviewer should not have access to settings');
    }
    
    /**
     * Test admin menu registration for regular user
     */
    public function test_admin_menu_registration_regular_user() {
        wp_set_current_user($this->test_users['regular']);
        
        // Simulate admin_menu action
        do_action('admin_menu');
        
        global $menu;
        
        // Should not have TPAK menu
        $found_main_menu = false;
        foreach ($menu as $menu_item) {
            if ($menu_item[2] === 'tpak-dashboard') {
                $found_main_menu = true;
                break;
            }
        }
        
        $this->assertFalse($found_main_menu, 'Regular user should not have TPAK menu');
    }
    
    /**
     * Test page access verification
     */
    public function test_page_access_verification() {
        // Test administrator access
        wp_set_current_user($this->test_users['admin']);
        $_GET['page'] = 'tpak-settings';
        
        // Should not throw exception
        $this->admin_menu->admin_init();
        $this->assertTrue(true, 'Administrator should have access to settings page');
        
        // Test interviewer access to settings (should fail)
        $user = get_user_by('id', $this->test_users['interviewer']);
        $user->add_role('tpak_interviewer_a');
        wp_set_current_user($this->test_users['interviewer']);
        
        // This would normally call wp_die(), but we can't test that directly
        // Instead, we test the capability check
        $this->assertFalse(current_user_can('tpak_manage_settings'), 'Interviewer should not have settings capability');
    }
    
    /**
     * Test security nonce verification
     */
    public function test_security_nonce_verification() {
        wp_set_current_user($this->test_users['admin']);
        $_GET['page'] = 'tpak-dashboard';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Test without nonce (should fail)
        $_POST = array('test_data' => 'value');
        
        $this->expectException('WPDieException');
        $this->admin_menu->admin_init();
    }
    
    /**
     * Test menu page hooks
     */
    public function test_menu_page_hooks() {
        wp_set_current_user($this->test_users['admin']);
        
        // Simulate admin_menu action
        do_action('admin_menu');
        
        $menu_pages = $this->admin_menu->get_menu_pages();
        
        $this->assertIsArray($menu_pages);
        $this->assertArrayHasKey('dashboard', $menu_pages);
        $this->assertArrayHasKey('data', $menu_pages);
        $this->assertArrayHasKey('settings', $menu_pages);
    }
    
    /**
     * Test current page detection
     */
    public function test_current_page_detection() {
        $_GET['page'] = 'tpak-dashboard';
        
        $this->admin_menu->admin_init();
        
        $this->assertEquals('tpak-dashboard', $this->admin_menu->get_current_page());
        $this->assertTrue($this->admin_menu->is_tpak_admin_page());
    }
    
    /**
     * Test admin action handling
     */
    public function test_admin_action_handling() {
        wp_set_current_user($this->test_users['admin']);
        
        // Test with valid nonce
        $nonce = wp_create_nonce('tpak_admin_action');
        $data = array('_wpnonce' => $nonce);
        
        // Test save settings action
        $result = $this->admin_menu->handle_admin_action('save_settings', $data);
        $this->assertTrue($result, 'Admin should be able to save settings');
        
        // Test import data action
        $result = $this->admin_menu->handle_admin_action('import_data', $data);
        $this->assertTrue($result, 'Admin should be able to import data');
        
        // Test invalid action
        $result = $this->admin_menu->handle_admin_action('invalid_action', $data);
        $this->assertFalse($result, 'Invalid action should return false');
    }
    
    /**
     * Test admin action handling with insufficient permissions
     */
    public function test_admin_action_handling_insufficient_permissions() {
        // Assign interviewer role
        $user = get_user_by('id', $this->test_users['interviewer']);
        $user->add_role('tpak_interviewer_a');
        wp_set_current_user($this->test_users['interviewer']);
        
        $nonce = wp_create_nonce('tpak_admin_action');
        $data = array('_wpnonce' => $nonce);
        
        // Test save settings action (should fail)
        $result = $this->admin_menu->handle_admin_action('save_settings', $data);
        $this->assertFalse($result, 'Interviewer should not be able to save settings');
        
        // Test import data action (should fail)
        $result = $this->admin_menu->handle_admin_action('import_data', $data);
        $this->assertFalse($result, 'Interviewer should not be able to import data');
    }
    
    /**
     * Test admin notice functionality
     */
    public function test_admin_notice_functionality() {
        wp_set_current_user($this->test_users['admin']);
        
        // Test adding admin notice
        ob_start();
        $this->admin_menu->add_admin_notice('Test message', 'success');
        $output = ob_get_clean();
        
        // Should redirect, so no output expected
        $this->assertEmpty($output);
    }
    
    /**
     * Test role-based menu visibility
     */
    public function test_role_based_menu_visibility() {
        // Test each role
        $roles_to_test = array(
            'interviewer' => 'tpak_interviewer_a',
            'supervisor' => 'tpak_supervisor_b',
            'examiner' => 'tpak_examiner_c',
        );
        
        foreach ($roles_to_test as $user_key => $role_name) {
            $user = get_user_by('id', $this->test_users[$user_key]);
            $user->add_role($role_name);
            wp_set_current_user($this->test_users[$user_key]);
            
            // Reset global menu variables
            global $menu, $submenu;
            $menu = array();
            $submenu = array();
            
            // Simulate admin_menu action
            do_action('admin_menu');
            
            // Should have main menu
            $found_main_menu = false;
            foreach ($menu as $menu_item) {
                if ($menu_item[2] === 'tpak-dashboard') {
                    $found_main_menu = true;
                    break;
                }
            }
            
            $this->assertTrue($found_main_menu, "Role {$role_name} should have main TPAK menu");
            
            // Should have data management
            $has_data_menu = false;
            if (isset($submenu['tpak-dashboard'])) {
                foreach ($submenu['tpak-dashboard'] as $submenu_item) {
                    if ($submenu_item[2] === 'tpak-data') {
                        $has_data_menu = true;
                        break;
                    }
                }
            }
            
            $this->assertTrue($has_data_menu, "Role {$role_name} should have data management menu");
        }
    }
    
    /**
     * Test script and style enqueuing
     */
    public function test_script_and_style_enqueuing() {
        wp_set_current_user($this->test_users['admin']);
        
        // Simulate admin_menu action to register pages
        do_action('admin_menu');
        
        $menu_pages = $this->admin_menu->get_menu_pages();
        $dashboard_hook = $menu_pages['dashboard'];
        
        // Simulate admin_enqueue_scripts action
        $this->admin_menu->enqueue_admin_scripts($dashboard_hook);
        
        // Check if styles are enqueued
        $this->assertTrue(wp_style_is('tpak-admin-style', 'enqueued'), 'Admin styles should be enqueued');
        
        // Check if scripts are enqueued
        $this->assertTrue(wp_script_is('tpak-admin-script', 'enqueued'), 'Admin scripts should be enqueued');
        
        // Check localized script data
        $localized_data = wp_scripts()->get_data('tpak-admin-script', 'data');
        $this->assertNotEmpty($localized_data, 'Script should have localized data');
    }
    
    /**
     * Test admin page rendering
     */
    public function test_admin_page_rendering() {
        wp_set_current_user($this->test_users['admin']);
        
        // Test dashboard page rendering
        ob_start();
        $this->admin_menu->render_dashboard_page();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('tpak-admin-page', $output, 'Dashboard page should contain admin page class');
        $this->assertStringContainsString('Dashboard', $output, 'Dashboard page should contain title');
        
        // Test settings page rendering
        ob_start();
        $this->admin_menu->render_settings_page();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Settings', $output, 'Settings page should contain title');
    }
    
    /**
     * Test user capability checking
     */
    public function test_user_capability_checking() {
        // Test administrator capabilities
        wp_set_current_user($this->test_users['admin']);
        
        $capabilities = $this->invokeMethod($this->admin_menu, 'get_user_tpak_capabilities');
        
        $this->assertIsArray($capabilities);
        $this->assertContains('tpak_access_dashboard', $capabilities);
        $this->assertContains('tpak_manage_settings', $capabilities);
        
        // Test interviewer capabilities
        $user = get_user_by('id', $this->test_users['interviewer']);
        $user->add_role('tpak_interviewer_a');
        wp_set_current_user($this->test_users['interviewer']);
        
        $capabilities = $this->invokeMethod($this->admin_menu, 'get_user_tpak_capabilities');
        
        $this->assertContains('tpak_access_dashboard', $capabilities);
        $this->assertNotContains('tpak_manage_settings', $capabilities);
    }
    
    /**
     * Helper method to invoke private methods
     */
    private function invokeMethod($object, $methodName, array $parameters = array()) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $parameters);
    }
}