<?php
/**
 * Test Error Handler Class
 *
 * Unit tests for the TPAK_Error_Handler class.
 *
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

class Test_TPAK_Error_Handler extends WP_UnitTestCase {
    
    private $error_handler;
    private $logger;
    
    public function setUp(): void {
        parent::setUp();
        $this->error_handler = new TPAK_Error_Handler();
        $this->logger = TPAK_Logger::get_instance();
        
        // Clean up any existing logs and notices
        $this->logger->clear_logs();
        $this->error_handler->clear_admin_notices();
    }
    
    public function tearDown(): void {
        // Clean up after each test
        $this->logger->clear_logs();
        $this->error_handler->clear_admin_notices();
        parent::tearDown();
    }
    
    /**
     * Test API error handling
     */
    public function test_handle_api_error() {
        $endpoint = '/api/surveys';
        $error_message = 'Connection timeout';
        $context = ['timeout' => 30];
        
        $result = $this->error_handler->handle_api_error($endpoint, $error_message, $context);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('api_error', $result->get_error_code());
        $this->assertNotEmpty($result->get_error_message());
        
        // Verify error was logged
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_API]);
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
    }
    
    /**
     * Test API error handling with Exception object
     */
    public function test_handle_api_error_with_exception() {
        $endpoint = '/api/surveys';
        $exception = new Exception('Connection failed', 500);
        
        $result = $this->error_handler->handle_api_error($endpoint, $exception);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals(500, $result->get_error_code());
        $this->assertNotEmpty($result->get_error_message());
        
        // Verify error was logged
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_API]);
        $this->assertCount(1, $logs);
    }
    
    /**
     * Test validation error handling
     */
    public function test_handle_validation_error() {
        $field = 'email';
        $value = 'invalid-email';
        $rule = 'email_format';
        $custom_message = 'Please enter a valid email address';
        
        $result = $this->error_handler->handle_validation_error($field, $value, $rule, $custom_message);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('validation_error', $result->get_error_code());
        $this->assertEquals($custom_message, $result->get_error_message());
        
        // Verify error was logged
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_VALIDATION]);
        $this->assertCount(1, $logs);
        $this->assertEquals('warning', $logs[0]['level']);
    }
    
    /**
     * Test validation error with auto-generated message
     */
    public function test_handle_validation_error_auto_message() {
        $field = 'api_url';
        $value = 'not-a-url';
        $rule = 'url';
        
        $result = $this->error_handler->handle_validation_error($field, $value, $rule);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('validation_error', $result->get_error_code());
        $this->assertStringContains('API URL', $result->get_error_message());
        $this->assertStringContains('valid URL', $result->get_error_message());
    }
    
    /**
     * Test workflow error handling
     */
    public function test_handle_workflow_error() {
        $user_id = $this->factory->user->create();
        wp_set_current_user($user_id);
        
        $action = 'approve';
        $data_id = 123;
        $error_message = 'Insufficient permissions';
        
        $result = $this->error_handler->handle_workflow_error($action, $data_id, $error_message);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('workflow_error', $result->get_error_code());
        $this->assertNotEmpty($result->get_error_message());
        
        // Verify error was logged
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_WORKFLOW]);
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
        $this->assertEquals($user_id, $logs[0]['user_id']);
    }
    
    /**
     * Test cron error handling
     */
    public function test_handle_cron_error() {
        $job_name = 'data_import';
        $error_message = 'API connection failed';
        $context = ['retry_count' => 3];
        
        $result = $this->error_handler->handle_cron_error($job_name, $error_message, $context);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('cron_error', $result->get_error_code());
        $this->assertNotEmpty($result->get_error_message());
        
        // Verify error was logged
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_CRON]);
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
    }
    
    /**
     * Test notification error handling
     */
    public function test_handle_notification_error() {
        $type = 'assignment';
        $recipient = 'user@example.com';
        $error_message = 'SMTP connection failed';
        
        $result = $this->error_handler->handle_notification_error($type, $recipient, $error_message);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('notification_error', $result->get_error_code());
        $this->assertNotEmpty($result->get_error_message());
        
        // Verify error was logged
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_NOTIFICATION]);
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
    }
    
    /**
     * Test security error handling
     */
    public function test_handle_security_error() {
        $event = 'unauthorized_access';
        $description = 'User attempted to access restricted data';
        $context = ['user_id' => 123, 'data_id' => 456];
        
        $result = $this->error_handler->handle_security_error($event, $description, $context);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('security_error', $result->get_error_code());
        $this->assertNotEmpty($result->get_error_message());
        
        // Verify error was logged
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_SECURITY]);
        $this->assertCount(1, $logs);
        $this->assertEquals('warning', $logs[0]['level']);
    }
    
    /**
     * Test system error handling
     */
    public function test_handle_system_error() {
        $error_message = 'Database connection failed';
        $context = ['database' => 'main', 'host' => 'localhost'];
        
        $result = $this->error_handler->handle_system_error($error_message, $context);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('system_error', $result->get_error_code());
        $this->assertNotEmpty($result->get_error_message());
        
        // Verify error was logged
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_SYSTEM]);
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
    }
    
    /**
     * Test system error handling with Exception
     */
    public function test_handle_system_error_with_exception() {
        $exception = new Exception('Memory limit exceeded', 1001);
        
        $result = $this->error_handler->handle_system_error($exception);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals(1001, $result->get_error_code());
        $this->assertNotEmpty($result->get_error_message());
    }
    
    /**
     * Test admin notice functionality
     */
    public function test_add_admin_notice() {
        $message = 'Test admin notice';
        $type = 'warning';
        
        $this->error_handler->add_admin_notice($message, $type);
        
        // Capture output
        ob_start();
        $this->error_handler->display_admin_notices();
        $output = ob_get_clean();
        
        $this->assertStringContains($message, $output);
        $this->assertStringContains('notice-warning', $output);
    }
    
    /**
     * Test persistent admin notice
     */
    public function test_persistent_admin_notice() {
        $message = 'Persistent test notice';
        $type = 'error';
        
        $this->error_handler->add_admin_notice($message, $type, true);
        
        // Verify notice is stored in database
        $notices = get_option('tpak_dq_admin_notices', []);
        $this->assertCount(1, $notices);
        $this->assertEquals($message, $notices[0]['message']);
        $this->assertEquals($type, $notices[0]['type']);
        $this->assertTrue($notices[0]['persistent']);
    }
    
    /**
     * Test error summary generation
     */
    public function test_get_error_summary() {
        // Create some test errors
        $this->logger->error('Test error 1');
        $this->logger->critical('Test critical error');
        $this->logger->warning('Test warning');
        $this->logger->info('Test info');
        
        $summary = $this->error_handler->get_error_summary();
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('total_errors', $summary);
        $this->assertArrayHasKey('by_category', $summary);
        $this->assertEquals(2, $summary['total_errors']); // error + critical
    }
    
    /**
     * Test user-friendly message generation for different validation rules
     */
    public function test_validation_message_generation() {
        $test_cases = [
            ['field' => 'email', 'rule' => 'email', 'expected_contains' => 'Email Address'],
            ['field' => 'api_url', 'rule' => 'url', 'expected_contains' => 'API URL'],
            ['field' => 'password', 'rule' => 'required', 'expected_contains' => 'Password'],
            ['field' => 'sampling_percentage', 'rule' => 'range', 'expected_contains' => 'Sampling Percentage']
        ];
        
        foreach ($test_cases as $case) {
            $result = $this->error_handler->handle_validation_error(
                $case['field'],
                'test_value',
                $case['rule']
            );
            
            $this->assertStringContains($case['expected_contains'], $result->get_error_message());
        }
    }
    
    /**
     * Test workflow message generation for different actions
     */
    public function test_workflow_message_generation() {
        $actions = ['approve', 'reject', 'assign', 'transition'];
        
        foreach ($actions as $action) {
            $result = $this->error_handler->handle_workflow_error($action, 123, 'Test error');
            
            $message = $result->get_error_message();
            $this->assertNotEmpty($message);
            $this->assertStringContains('Unable to', $message);
        }
    }
    
    /**
     * Test API error message generation for different error codes
     */
    public function test_api_error_message_generation() {
        $error_codes = [
            'connection_failed',
            'authentication_failed',
            'invalid_survey_id',
            'rate_limit_exceeded',
            'server_error'
        ];
        
        foreach ($error_codes as $code) {
            $exception = new Exception('Test error', $code);
            $result = $this->error_handler->handle_api_error('/test', $exception);
            
            $message = $result->get_error_message();
            $this->assertNotEmpty($message);
            $this->assertIsString($message);
        }
    }
    
    /**
     * Test admin notice display with administrator permissions
     */
    public function test_admin_notice_with_permissions() {
        // Create admin user
        $admin_id = $this->factory->user->create(['role' => 'administrator']);
        wp_set_current_user($admin_id);
        
        $this->error_handler->handle_api_error('/test', 'Test error');
        
        // Capture output
        ob_start();
        $this->error_handler->display_admin_notices();
        $output = ob_get_clean();
        
        $this->assertStringContains('API Error', $output);
    }
    
    /**
     * Test that non-admin users don't see admin notices
     */
    public function test_admin_notice_without_permissions() {
        // Create regular user
        $user_id = $this->factory->user->create(['role' => 'subscriber']);
        wp_set_current_user($user_id);
        
        $this->error_handler->handle_api_error('/test', 'Test error');
        
        // Capture output
        ob_start();
        $this->error_handler->display_admin_notices();
        $output = ob_get_clean();
        
        $this->assertEmpty($output);
    }
    
    /**
     * Test error context preservation
     */
    public function test_error_context_preservation() {
        $context = [
            'endpoint' => '/api/test',
            'method' => 'POST',
            'data' => ['key' => 'value']
        ];
        
        $result = $this->error_handler->handle_api_error('/test', 'Test error', $context);
        
        $error_data = $result->get_error_data();
        $this->assertEquals($context, $error_data['context']);
        $this->assertEquals('/test', $error_data['endpoint']);
    }
}