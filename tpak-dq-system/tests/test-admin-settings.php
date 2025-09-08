<?php
/**
 * Admin Settings Tests
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Admin Settings Class
 */
class Test_TPAK_Admin_Settings extends WP_UnitTestCase {
    
    /**
     * Settings instance
     * 
     * @var TPAK_Admin_Settings
     */
    private $settings;
    
    /**
     * Test user ID
     * 
     * @var int
     */
    private $admin_user_id;
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create admin user
        $this->admin_user_id = $this->factory->user->create(array(
            'role' => 'administrator'
        ));
        
        // Set current user
        wp_set_current_user($this->admin_user_id);
        
        // Initialize settings
        $this->settings = TPAK_Admin_Settings::get_instance();
        
        // Clear any existing settings
        delete_option('tpak_dq_settings');
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Clean up settings
        delete_option('tpak_dq_settings');
        
        parent::tearDown();
    }
    
    /**
     * Test settings instance creation
     */
    public function test_settings_instance() {
        $this->assertInstanceOf('TPAK_Admin_Settings', $this->settings);
        
        // Test singleton pattern
        $another_instance = TPAK_Admin_Settings::get_instance();
        $this->assertSame($this->settings, $another_instance);
    }
    
    /**
     * Test default settings initialization
     */
    public function test_default_settings() {
        $settings = $this->settings->get_all_settings();
        
        // Test API settings defaults
        $this->assertArrayHasKey('api', $settings);
        $this->assertArrayHasKey('limesurvey_url', $settings['api']);
        $this->assertArrayHasKey('username', $settings['api']);
        $this->assertArrayHasKey('password', $settings['api']);
        $this->assertArrayHasKey('survey_id', $settings['api']);
        $this->assertEquals(30, $settings['api']['connection_timeout']);
        
        // Test cron settings defaults
        $this->assertArrayHasKey('cron', $settings);
        $this->assertTrue($settings['cron']['import_enabled']);
        $this->assertEquals('daily', $settings['cron']['import_interval']);
        $this->assertEquals(100, $settings['cron']['import_limit']);
        $this->assertEquals(3, $settings['cron']['retry_attempts']);
        
        // Test notification settings defaults
        $this->assertArrayHasKey('notifications', $settings);
        $this->assertTrue($settings['notifications']['email_enabled']);
        $this->assertTrue($settings['notifications']['send_on_assignment']);
        $this->assertTrue($settings['notifications']['send_on_status_change']);
        $this->assertTrue($settings['notifications']['send_on_error']);
        $this->assertEquals('default', $settings['notifications']['email_template']);
        
        // Test workflow settings defaults
        $this->assertArrayHasKey('workflow', $settings);
        $this->assertEquals(30, $settings['workflow']['sampling_percentage']);
        $this->assertTrue($settings['workflow']['auto_finalize_sampling']);
        $this->assertFalse($settings['workflow']['require_comments']);
        $this->assertEquals(365, $settings['workflow']['audit_retention_days']);
    }
    
    /**
     * Test getting specific settings section
     */
    public function test_get_settings_section() {
        $api_settings = $this->settings->get_settings('api');
        $this->assertIsArray($api_settings);
        $this->assertArrayHasKey('limesurvey_url', $api_settings);
        
        $cron_settings = $this->settings->get_settings('cron');
        $this->assertIsArray($cron_settings);
        $this->assertArrayHasKey('import_enabled', $cron_settings);
        
        // Test non-existent section
        $invalid_settings = $this->settings->get_settings('invalid_section');
        $this->assertIsArray($invalid_settings);
        $this->assertEmpty($invalid_settings);
    }
    
    /**
     * Test updating settings section
     */
    public function test_update_settings_section() {
        $new_api_data = array(
            'limesurvey_url' => 'https://test.example.com/api',
            'username' => 'testuser',
            'survey_id' => 123
        );
        
        $result = $this->settings->update_settings('api', $new_api_data);
        $this->assertTrue($result);
        
        $updated_settings = $this->settings->get_settings('api');
        $this->assertEquals('https://test.example.com/api', $updated_settings['limesurvey_url']);
        $this->assertEquals('testuser', $updated_settings['username']);
        $this->assertEquals(123, $updated_settings['survey_id']);
        
        // Original values should be preserved
        $this->assertEquals(30, $updated_settings['connection_timeout']);
    }
    
    /**
     * Test settings sanitization
     */
    public function test_settings_sanitization() {
        $test_settings = array(
            'api' => array(
                'limesurvey_url' => 'javascript:alert("xss")',
                'username' => '<script>alert("xss")</script>',
                'password' => 'test<>password',
                'survey_id' => '123abc',
                'connection_timeout' => 'invalid'
            ),
            'cron' => array(
                'import_enabled' => 'yes',
                'import_interval' => '<script>daily</script>',
                'import_limit' => '100.5',
                'retry_attempts' => 'three'
            ),
            'workflow' => array(
                'sampling_percentage' => '30.7',
                'auto_finalize_sampling' => 'true',
                'require_comments' => 0,
                'audit_retention_days' => '365.5'
            )
        );
        
        $sanitized = $this->settings->sanitize_settings($test_settings);
        
        // Test API sanitization
        $this->assertEquals('', $sanitized['api']['limesurvey_url']); // Invalid URL should be empty
        $this->assertEquals('alert("xss")', $sanitized['api']['username']); // Script tags removed
        $this->assertEquals('test<>password', $sanitized['api']['password']); // Text preserved
        $this->assertEquals(123, $sanitized['api']['survey_id']); // Converted to int
        $this->assertEquals(0, $sanitized['api']['connection_timeout']); // Invalid converted to 0
        
        // Test cron sanitization
        $this->assertTrue($sanitized['cron']['import_enabled']); // 'yes' converted to true
        $this->assertEquals('daily', $sanitized['cron']['import_interval']); // Script tags removed
        $this->assertEquals(100, $sanitized['cron']['import_limit']); // Float converted to int
        $this->assertEquals(0, $sanitized['cron']['retry_attempts']); // Invalid converted to 0
        
        // Test workflow sanitization
        $this->assertEquals(30, $sanitized['workflow']['sampling_percentage']); // Float converted to int
        $this->assertTrue($sanitized['workflow']['auto_finalize_sampling']); // 'true' converted to bool
        $this->assertFalse($sanitized['workflow']['require_comments']); // 0 converted to false
        $this->assertEquals(365, $sanitized['workflow']['audit_retention_days']); // Float converted to int
    }
    
    /**
     * Test API settings validation and saving
     */
    public function test_api_settings_validation() {
        // Mock validator
        $validator = $this->createMock('TPAK_Validator');
        $validator->method('validate_api_settings')->willReturn(true);
        
        // Test valid API settings
        $post_data = array(
            'limesurvey_url' => 'https://survey.example.com/api',
            'api_username' => 'admin',
            'api_password' => 'password123',
            'survey_id' => '456',
            'connection_timeout' => '60'
        );
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->settings);
        $method = $reflection->getMethod('save_api_settings');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->settings, $post_data, $validator);
        $this->assertTrue($result);
        
        // Verify settings were saved
        $saved_settings = $this->settings->get_settings('api');
        $this->assertEquals('https://survey.example.com/api', $saved_settings['limesurvey_url']);
        $this->assertEquals('admin', $saved_settings['username']);
        $this->assertEquals('password123', $saved_settings['password']);
        $this->assertEquals(456, $saved_settings['survey_id']);
        $this->assertEquals(60, $saved_settings['connection_timeout']);
    }
    
    /**
     * Test cron settings validation and saving
     */
    public function test_cron_settings_validation() {
        // Mock validator
        $validator = $this->createMock('TPAK_Validator');
        $validator->method('validate_cron_settings')->willReturn(true);
        
        $post_data = array(
            'import_enabled' => '1',
            'import_interval' => 'hourly',
            'cron_survey_id' => '789',
            'import_limit' => '50',
            'retry_attempts' => '5'
        );
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->settings);
        $method = $reflection->getMethod('save_cron_settings');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->settings, $post_data, $validator);
        $this->assertTrue($result);
        
        // Verify settings were saved
        $saved_settings = $this->settings->get_settings('cron');
        $this->assertTrue($saved_settings['import_enabled']);
        $this->assertEquals('hourly', $saved_settings['import_interval']);
        $this->assertEquals(789, $saved_settings['survey_id']);
        $this->assertEquals(50, $saved_settings['import_limit']);
        $this->assertEquals(5, $saved_settings['retry_attempts']);
    }
    
    /**
     * Test notification settings validation and saving
     */
    public function test_notification_settings_validation() {
        // Mock validator
        $validator = $this->createMock('TPAK_Validator');
        $validator->method('validate_notification_settings')->willReturn(true);
        
        $post_data = array(
            'email_enabled' => '1',
            'notification_emails' => 'admin@example.com, user@example.com, invalid-email',
            'send_on_assignment' => '1',
            'send_on_status_change' => '1',
            'send_on_error' => '',
            'email_template' => 'detailed'
        );
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->settings);
        $method = $reflection->getMethod('save_notifications_settings');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->settings, $post_data, $validator);
        $this->assertTrue($result);
        
        // Verify settings were saved
        $saved_settings = $this->settings->get_settings('notifications');
        $this->assertTrue($saved_settings['email_enabled']);
        $this->assertCount(2, $saved_settings['notification_emails']); // Invalid email filtered out
        $this->assertContains('admin@example.com', $saved_settings['notification_emails']);
        $this->assertContains('user@example.com', $saved_settings['notification_emails']);
        $this->assertTrue($saved_settings['send_on_assignment']);
        $this->assertTrue($saved_settings['send_on_status_change']);
        $this->assertFalse($saved_settings['send_on_error']);
        $this->assertEquals('detailed', $saved_settings['email_template']);
    }
    
    /**
     * Test workflow settings validation and saving
     */
    public function test_workflow_settings_validation() {
        // Mock validator
        $validator = $this->createMock('TPAK_Validator');
        $validator->method('validate_percentage')->willReturn(true);
        
        $post_data = array(
            'sampling_percentage' => '25',
            'auto_finalize_sampling' => '1',
            'require_comments' => '1',
            'audit_retention_days' => '730'
        );
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->settings);
        $method = $reflection->getMethod('save_workflow_settings');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->settings, $post_data, $validator);
        $this->assertTrue($result);
        
        // Verify settings were saved
        $saved_settings = $this->settings->get_settings('workflow');
        $this->assertEquals(25, $saved_settings['sampling_percentage']);
        $this->assertTrue($saved_settings['auto_finalize_sampling']);
        $this->assertTrue($saved_settings['require_comments']);
        $this->assertEquals(730, $saved_settings['audit_retention_days']);
    }
    
    /**
     * Test settings registration
     */
    public function test_settings_registration() {
        // Trigger settings registration
        do_action('admin_init');
        
        // Check if setting is registered
        global $wp_settings_fields;
        $this->assertTrue(isset($wp_settings_fields['tpak_dq_settings_group']));
    }
    
    /**
     * Test cron schedule update
     */
    public function test_cron_schedule_update() {
        // Clear any existing schedules
        wp_clear_scheduled_hook('tpak_import_survey_data');
        
        $cron_data = array(
            'import_enabled' => true,
            'import_interval' => 'hourly'
        );
        
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->settings);
        $method = $reflection->getMethod('update_cron_schedule');
        $method->setAccessible(true);
        
        $method->invoke($this->settings, $cron_data);
        
        // Check if cron is scheduled
        $next_scheduled = wp_next_scheduled('tpak_import_survey_data');
        $this->assertNotFalse($next_scheduled);
        
        // Test disabling cron
        $cron_data['import_enabled'] = false;
        $method->invoke($this->settings, $cron_data);
        
        $next_scheduled = wp_next_scheduled('tpak_import_survey_data');
        $this->assertFalse($next_scheduled);
    }
    
    /**
     * Test AJAX API connection test
     */
    public function test_ajax_api_connection_test() {
        // Mock API handler
        $api_handler = $this->createMock('TPAK_API_Handler');
        $api_handler->method('test_connection')->willReturn(array(
            'success' => true,
            'message' => 'Connection successful'
        ));
        
        // Set up POST data
        $_POST = array(
            'nonce' => wp_create_nonce('tpak_admin_ajax'),
            'url' => 'https://survey.example.com/api',
            'username' => 'admin',
            'password' => 'password',
            'survey_id' => '123'
        );
        
        // Capture output
        ob_start();
        try {
            $this->settings->ajax_test_api_connection();
        } catch (WPDieException $e) {
            // Expected for wp_send_json_success
        }
        $output = ob_get_clean();
        
        // Verify response (would normally be JSON)
        $this->assertNotEmpty($output);
    }
    
    /**
     * Test AJAX manual import
     */
    public function test_ajax_manual_import() {
        // Mock cron handler
        $cron_handler = $this->createMock('TPAK_Cron');
        $cron_handler->method('execute_import')->willReturn(array(
            'success' => true,
            'imported_count' => 5
        ));
        
        // Set up POST data
        $_POST = array(
            'nonce' => wp_create_nonce('tpak_admin_ajax')
        );
        
        // Capture output
        ob_start();
        try {
            $this->settings->ajax_manual_import();
        } catch (WPDieException $e) {
            // Expected for wp_send_json_success
        }
        $output = ob_get_clean();
        
        // Verify response (would normally be JSON)
        $this->assertNotEmpty($output);
    }
}