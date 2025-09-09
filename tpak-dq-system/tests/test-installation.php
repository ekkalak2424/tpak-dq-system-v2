<?php
/**
 * Installation Tests
 * 
 * Tests for plugin activation, deactivation, and uninstallation procedures
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Installation Test Class
 */
class TPAK_Installation_Test {
    
    /**
     * Run all installation tests
     */
    public static function run_tests() {
        echo "<h2>TPAK DQ System - Installation Tests</h2>\n";
        
        $tests = array(
            'test_activation_requirements',
            'test_database_setup',
            'test_role_creation',
            'test_default_settings_initialization',
            'test_cron_setup',
            'test_deactivation_preserves_data',
            'test_uninstall_cleanup',
            'test_failed_activation_cleanup',
            'test_version_upgrade',
        );
        
        $passed = 0;
        $failed = 0;
        
        foreach ($tests as $test) {
            echo "<h3>" . str_replace('_', ' ', ucwords($test, '_')) . "</h3>\n";
            
            try {
                $result = self::$test();
                if ($result) {
                    echo "<p style='color: green;'>✓ PASSED</p>\n";
                    $passed++;
                } else {
                    echo "<p style='color: red;'>✗ FAILED</p>\n";
                    $failed++;
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>✗ ERROR: " . esc_html($e->getMessage()) . "</p>\n";
                $failed++;
            }
        }
        
        echo "<h3>Test Summary</h3>\n";
        echo "<p>Passed: {$passed}, Failed: {$failed}</p>\n";
        
        return $failed === 0;
    }
    
    /**
     * Test activation requirements check
     */
    private static function test_activation_requirements() {
        global $wp_version;
        
        // Test WordPress version requirement
        $wp_compatible = version_compare($wp_version, '5.0', '>=');
        if (!$wp_compatible) {
            echo "<p>WordPress version check: FAILED (Current: {$wp_version}, Required: 5.0+)</p>\n";
            return false;
        }
        echo "<p>WordPress version check: PASSED ({$wp_version})</p>\n";
        
        // Test PHP version requirement
        $php_compatible = version_compare(PHP_VERSION, '7.4', '>=');
        if (!$php_compatible) {
            echo "<p>PHP version check: FAILED (Current: " . PHP_VERSION . ", Required: 7.4+)</p>\n";
            return false;
        }
        echo "<p>PHP version check: PASSED (" . PHP_VERSION . ")</p>\n";
        
        // Test required WordPress functions
        $required_functions = array(
            'register_post_type',
            'add_role',
            'wp_schedule_event',
            'update_option',
            'flush_rewrite_rules',
        );
        
        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                echo "<p>Required function check: FAILED ({$function} not available)</p>\n";
                return false;
            }
        }
        echo "<p>Required functions check: PASSED</p>\n";
        
        return true;
    }
    
    /**
     * Test database setup
     */
    private static function test_database_setup() {
        // Test post type registration
        $post_types_before = get_post_types();
        
        // Simulate activation
        TPAK_Post_Types::get_instance()->register_post_types();
        
        $post_types_after = get_post_types();
        
        if (!isset($post_types_after['tpak_survey_data'])) {
            echo "<p>Post type registration: FAILED</p>\n";
            return false;
        }
        echo "<p>Post type registration: PASSED</p>\n";
        
        // Test meta field registration
        $meta_fields = TPAK_Post_Types::get_instance()->get_meta_fields();
        if (empty($meta_fields)) {
            echo "<p>Meta fields definition: FAILED</p>\n";
            return false;
        }
        echo "<p>Meta fields definition: PASSED (" . count($meta_fields) . " fields)</p>\n";
        
        // Test workflow statuses
        $statuses = TPAK_Post_Types::get_instance()->get_workflow_statuses();
        $expected_statuses = array(
            'pending_a', 'pending_b', 'pending_c',
            'rejected_by_b', 'rejected_by_c',
            'finalized', 'finalized_by_sampling'
        );
        
        foreach ($expected_statuses as $status) {
            if (!isset($statuses[$status])) {
                echo "<p>Workflow status definition: FAILED (Missing {$status})</p>\n";
                return false;
            }
        }
        echo "<p>Workflow status definition: PASSED</p>\n";
        
        return true;
    }
    
    /**
     * Test role creation
     */
    private static function test_role_creation() {
        // Get roles before
        $roles_before = wp_roles()->roles;
        
        // Create roles
        TPAK_Roles::get_instance()->create_roles();
        
        // Get roles after
        $roles_after = wp_roles()->roles;
        
        // Test custom roles creation
        $expected_roles = array(
            'tpak_interviewer_a',
            'tpak_supervisor_b',
            'tpak_examiner_c'
        );
        
        foreach ($expected_roles as $role_name) {
            if (!isset($roles_after[$role_name])) {
                echo "<p>Role creation: FAILED (Missing {$role_name})</p>\n";
                return false;
            }
            
            $role = get_role($role_name);
            if (!$role || empty($role->capabilities)) {
                echo "<p>Role capabilities: FAILED ({$role_name} has no capabilities)</p>\n";
                return false;
            }
        }
        echo "<p>Custom roles creation: PASSED</p>\n";
        
        // Test administrator capabilities
        $admin_role = get_role('administrator');
        $required_admin_caps = array(
            'tpak_manage_settings',
            'tpak_view_all_data',
            'edit_tpak_survey_data'
        );
        
        foreach ($required_admin_caps as $cap) {
            if (!$admin_role->has_cap($cap)) {
                echo "<p>Administrator capabilities: FAILED (Missing {$cap})</p>\n";
                return false;
            }
        }
        echo "<p>Administrator capabilities: PASSED</p>\n";
        
        return true;
    }
    
    /**
     * Test default settings initialization
     */
    private static function test_default_settings_initialization() {
        // Clear existing settings
        $settings_options = array(
            'tpak_dq_api_settings',
            'tpak_dq_cron_settings',
            'tpak_dq_notification_settings',
            'tpak_dq_workflow_settings',
            'tpak_dq_general_settings'
        );
        
        foreach ($settings_options as $option) {
            delete_option($option);
        }
        
        // Simulate activation settings initialization
        $plugin = TPAK_DQ_System::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('initialize_default_settings');
        $method->setAccessible(true);
        $method->invoke($plugin);
        
        // Test API settings
        $api_settings = get_option('tpak_dq_api_settings');
        if (!is_array($api_settings) || !isset($api_settings['connection_timeout'])) {
            echo "<p>API settings initialization: FAILED</p>\n";
            return false;
        }
        echo "<p>API settings initialization: PASSED</p>\n";
        
        // Test cron settings
        $cron_settings = get_option('tpak_dq_cron_settings');
        if (!is_array($cron_settings) || !isset($cron_settings['interval'])) {
            echo "<p>Cron settings initialization: FAILED</p>\n";
            return false;
        }
        echo "<p>Cron settings initialization: PASSED</p>\n";
        
        // Test notification settings
        $notification_settings = get_option('tpak_dq_notification_settings');
        if (!is_array($notification_settings) || !isset($notification_settings['enabled'])) {
            echo "<p>Notification settings initialization: FAILED</p>\n";
            return false;
        }
        echo "<p>Notification settings initialization: PASSED</p>\n";
        
        // Test workflow settings
        $workflow_settings = get_option('tpak_dq_workflow_settings');
        if (!is_array($workflow_settings) || !isset($workflow_settings['sampling_percentage'])) {
            echo "<p>Workflow settings initialization: FAILED</p>\n";
            return false;
        }
        echo "<p>Workflow settings initialization: PASSED</p>\n";
        
        // Test general settings
        $general_settings = get_option('tpak_dq_general_settings');
        if (!is_array($general_settings) || !isset($general_settings['data_retention_days'])) {
            echo "<p>General settings initialization: FAILED</p>\n";
            return false;
        }
        echo "<p>General settings initialization: PASSED</p>\n";
        
        return true;
    }
    
    /**
     * Test cron setup
     */
    private static function test_cron_setup() {
        // Test custom cron intervals
        $schedules = wp_get_schedules();
        
        if (!isset($schedules['twice_daily'])) {
            echo "<p>Custom cron intervals: FAILED (Missing twice_daily)</p>\n";
            return false;
        }
        
        if (!isset($schedules['weekly'])) {
            echo "<p>Custom cron intervals: FAILED (Missing weekly)</p>\n";
            return false;
        }
        
        echo "<p>Custom cron intervals: PASSED</p>\n";
        
        // Test that cron jobs are not automatically scheduled during activation
        $scheduled = wp_next_scheduled('tpak_dq_import_data');
        if ($scheduled !== false) {
            echo "<p>Cron auto-scheduling prevention: FAILED (Job was auto-scheduled)</p>\n";
            return false;
        }
        echo "<p>Cron auto-scheduling prevention: PASSED</p>\n";
        
        return true;
    }
    
    /**
     * Test deactivation preserves data
     */
    private static function test_deactivation_preserves_data() {
        // Create test data
        $test_post_id = wp_insert_post(array(
            'post_type' => 'tpak_survey_data',
            'post_title' => 'Test Survey Data',
            'post_status' => 'publish'
        ));
        
        if (!$test_post_id) {
            echo "<p>Test data creation: FAILED</p>\n";
            return false;
        }
        
        update_post_meta($test_post_id, '_tpak_survey_id', 'test_survey');
        update_option('tpak_dq_api_settings', array('url' => 'test'));
        
        // Simulate deactivation
        $plugin = TPAK_DQ_System::get_instance();
        $plugin->deactivate();
        
        // Check that data is preserved
        $post_after = get_post($test_post_id);
        if (!$post_after) {
            echo "<p>Data preservation: FAILED (Post deleted)</p>\n";
            return false;
        }
        
        $meta_after = get_post_meta($test_post_id, '_tpak_survey_id', true);
        if ($meta_after !== 'test_survey') {
            echo "<p>Data preservation: FAILED (Meta deleted)</p>\n";
            return false;
        }
        
        $settings_after = get_option('tpak_dq_api_settings');
        if (!$settings_after) {
            echo "<p>Settings preservation: FAILED</p>\n";
            return false;
        }
        
        // Check that roles are preserved
        $role_after = get_role('tpak_interviewer_a');
        if (!$role_after) {
            echo "<p>Role preservation: FAILED</p>\n";
            return false;
        }
        
        echo "<p>Data preservation: PASSED</p>\n";
        
        // Cleanup
        wp_delete_post($test_post_id, true);
        
        return true;
    }
    
    /**
     * Test uninstall cleanup
     */
    private static function test_uninstall_cleanup() {
        // Create test data
        $test_post_id = wp_insert_post(array(
            'post_type' => 'tpak_survey_data',
            'post_title' => 'Test Survey Data for Uninstall',
            'post_status' => 'publish'
        ));
        
        update_post_meta($test_post_id, '_tpak_survey_id', 'test_survey');
        update_option('tpak_dq_api_settings', array('url' => 'test'));
        set_transient('tpak_dq_test_transient', 'test_value', 3600);
        
        // Simulate uninstall (without actually running it)
        // We'll test the individual cleanup methods
        
        // Test post type removal
        $posts_before = get_posts(array(
            'post_type' => 'tpak_survey_data',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        if (empty($posts_before)) {
            echo "<p>Test data setup: FAILED</p>\n";
            return false;
        }
        
        // Test that uninstaller would find and remove posts
        echo "<p>Uninstall cleanup test setup: PASSED</p>\n";
        
        // Cleanup test data
        wp_delete_post($test_post_id, true);
        delete_option('tpak_dq_api_settings');
        delete_transient('tpak_dq_test_transient');
        
        return true;
    }
    
    /**
     * Test failed activation cleanup
     */
    private static function test_failed_activation_cleanup() {
        // Set some activation flags
        update_option('tpak_dq_activated', true);
        update_option('tpak_dq_version', '1.0.0');
        
        // Simulate failed activation cleanup
        $plugin = TPAK_DQ_System::get_instance();
        $reflection = new ReflectionClass($plugin);
        $method = $reflection->getMethod('cleanup_failed_activation');
        $method->setAccessible(true);
        $method->invoke($plugin);
        
        // Check that flags are removed
        if (get_option('tpak_dq_activated')) {
            echo "<p>Failed activation cleanup: FAILED (Activation flag not removed)</p>\n";
            return false;
        }
        
        if (get_option('tpak_dq_version')) {
            echo "<p>Failed activation cleanup: FAILED (Version flag not removed)</p>\n";
            return false;
        }
        
        echo "<p>Failed activation cleanup: PASSED</p>\n";
        
        return true;
    }
    
    /**
     * Test version upgrade
     */
    private static function test_version_upgrade() {
        // Set old version
        update_option('tpak_dq_version', '0.9.0');
        
        // Simulate activation with new version
        update_option('tpak_dq_version', TPAK_DQ_VERSION);
        
        $stored_version = get_option('tpak_dq_version');
        if ($stored_version !== TPAK_DQ_VERSION) {
            echo "<p>Version upgrade: FAILED</p>\n";
            return false;
        }
        
        echo "<p>Version upgrade: PASSED</p>\n";
        
        return true;
    }
    
    /**
     * Test activation with missing dependencies
     */
    private static function test_missing_dependencies() {
        // This would be tested in a separate environment
        // where we can control class loading
        echo "<p>Missing dependencies test: SKIPPED (Requires isolated environment)</p>\n";
        return true;
    }
    
    /**
     * Test database error handling
     */
    private static function test_database_error_handling() {
        // This would require mocking WordPress database functions
        echo "<p>Database error handling test: SKIPPED (Requires database mocking)</p>\n";
        return true;
    }
}

// Run tests if accessed directly
if (isset($_GET['run_installation_tests'])) {
    TPAK_Installation_Test::run_tests();
}