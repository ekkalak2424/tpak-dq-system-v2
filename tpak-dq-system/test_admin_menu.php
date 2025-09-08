<?php
/**
 * Test Admin Menu Functionality
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // Load WordPress test environment
    $wp_tests_dir = getenv('WP_TESTS_DIR');
    if (!$wp_tests_dir) {
        $wp_tests_dir = '/tmp/wordpress-tests-lib';
    }
    
    if (!file_exists($wp_tests_dir . '/includes/functions.php')) {
        echo "WordPress test environment not found. Please set up WordPress testing environment.\n";
        echo "See: https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/\n";
        exit(1);
    }
    
    require_once $wp_tests_dir . '/includes/functions.php';
    
    function _manually_load_plugin() {
        require dirname(__FILE__) . '/tpak-dq-system.php';
    }
    tests_add_filter('muplugins_loaded', '_manually_load_plugin');
    
    require $wp_tests_dir . '/includes/bootstrap.php';
}

// Include required classes
require_once dirname(__FILE__) . '/includes/class-autoloader.php';
require_once dirname(__FILE__) . '/includes/class-roles.php';
require_once dirname(__FILE__) . '/admin/class-admin-menu.php';

/**
 * Simple test runner for admin menu functionality
 */
class TPAK_Admin_Menu_Test_Runner {
    
    /**
     * Run tests
     */
    public function run_tests() {
        echo "=== TPAK Admin Menu Tests ===\n\n";
        
        $this->test_singleton_pattern();
        $this->test_menu_registration();
        $this->test_role_based_access();
        $this->test_page_routing();
        $this->test_security_checks();
        
        echo "\n=== All Tests Completed ===\n";
    }
    
    /**
     * Test singleton pattern
     */
    private function test_singleton_pattern() {
        echo "Testing singleton pattern... ";
        
        $instance1 = TPAK_Admin_Menu::get_instance();
        $instance2 = TPAK_Admin_Menu::get_instance();
        
        if ($instance1 === $instance2 && $instance1 instanceof TPAK_Admin_Menu) {
            echo "✓ PASSED\n";
        } else {
            echo "✗ FAILED\n";
        }
    }
    
    /**
     * Test menu registration
     */
    private function test_menu_registration() {
        echo "Testing menu registration... ";
        
        // Create admin user
        $admin_id = wp_create_user('test_admin', 'password', 'admin@test.com');
        $admin_user = get_user_by('id', $admin_id);
        $admin_user->add_role('administrator');
        wp_set_current_user($admin_id);
        
        // Create roles
        $roles = TPAK_Roles::get_instance();
        $roles->create_roles();
        
        // Get admin menu instance
        $admin_menu = TPAK_Admin_Menu::get_instance();
        
        // Simulate admin_menu action
        global $menu, $submenu;
        $menu = array();
        $submenu = array();
        
        do_action('admin_menu');
        
        // Check if main menu is registered
        $found_main_menu = false;
        foreach ($menu as $menu_item) {
            if ($menu_item[2] === 'tpak-dashboard') {
                $found_main_menu = true;
                break;
            }
        }
        
        if ($found_main_menu && isset($submenu['tpak-dashboard'])) {
            echo "✓ PASSED\n";
        } else {
            echo "✗ FAILED\n";
        }
        
        // Clean up
        wp_delete_user($admin_id);
        $roles->remove_roles();
    }
    
    /**
     * Test role-based access
     */
    private function test_role_based_access() {
        echo "Testing role-based access... ";
        
        // Create test users
        $admin_id = wp_create_user('test_admin2', 'password', 'admin2@test.com');
        $interviewer_id = wp_create_user('test_interviewer', 'password', 'interviewer@test.com');
        
        $admin_user = get_user_by('id', $admin_id);
        $admin_user->add_role('administrator');
        
        // Create roles
        $roles = TPAK_Roles::get_instance();
        $roles->create_roles();
        
        $interviewer_user = get_user_by('id', $interviewer_id);
        $interviewer_user->add_role('tpak_interviewer_a');
        
        // Test admin access
        wp_set_current_user($admin_id);
        $admin_has_settings = current_user_can('tpak_manage_settings');
        
        // Test interviewer access
        wp_set_current_user($interviewer_id);
        $interviewer_has_settings = current_user_can('tpak_manage_settings');
        $interviewer_has_dashboard = current_user_can('tpak_access_dashboard');
        
        if ($admin_has_settings && !$interviewer_has_settings && $interviewer_has_dashboard) {
            echo "✓ PASSED\n";
        } else {
            echo "✗ FAILED\n";
        }
        
        // Clean up
        wp_delete_user($admin_id);
        wp_delete_user($interviewer_id);
        $roles->remove_roles();
    }
    
    /**
     * Test page routing
     */
    private function test_page_routing() {
        echo "Testing page routing... ";
        
        $admin_menu = TPAK_Admin_Menu::get_instance();
        
        // Test current page detection
        $_GET['page'] = 'tpak-dashboard';
        $admin_menu->admin_init();
        
        $current_page = $admin_menu->get_current_page();
        $is_tpak_page = $admin_menu->is_tpak_admin_page();
        
        if ($current_page === 'tpak-dashboard' && $is_tpak_page) {
            echo "✓ PASSED\n";
        } else {
            echo "✗ FAILED\n";
        }
        
        // Clean up
        unset($_GET['page']);
    }
    
    /**
     * Test security checks
     */
    private function test_security_checks() {
        echo "Testing security checks... ";
        
        // Create admin user
        $admin_id = wp_create_user('test_admin3', 'password', 'admin3@test.com');
        $admin_user = get_user_by('id', $admin_id);
        $admin_user->add_role('administrator');
        wp_set_current_user($admin_id);
        
        // Create roles
        $roles = TPAK_Roles::get_instance();
        $roles->create_roles();
        
        $admin_menu = TPAK_Admin_Menu::get_instance();
        
        // Test action handling with valid nonce
        $nonce = wp_create_nonce('tpak_admin_action');
        $data = array('_wpnonce' => $nonce);
        
        $result = $admin_menu->handle_admin_action('save_settings', $data);
        
        // Test action handling with invalid nonce
        $invalid_data = array('_wpnonce' => 'invalid_nonce');
        $invalid_result = $admin_menu->handle_admin_action('save_settings', $invalid_data);
        
        if ($result === true && $invalid_result === false) {
            echo "✓ PASSED\n";
        } else {
            echo "✗ FAILED\n";
        }
        
        // Clean up
        wp_delete_user($admin_id);
        $roles->remove_roles();
    }
}

// Run tests if called directly
if (!defined('PHPUNIT_RUNNING')) {
    $test_runner = new TPAK_Admin_Menu_Test_Runner();
    $test_runner->run_tests();
}
?>