<?php
/**
 * Test Logger Class
 *
 * Unit tests for the TPAK_Logger class.
 *
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

class Test_TPAK_Logger extends WP_UnitTestCase {
    
    private $logger;
    
    public function setUp(): void {
        parent::setUp();
        $this->logger = TPAK_Logger::get_instance();
        
        // Clean up any existing logs
        $this->logger->clear_logs();
    }
    
    public function tearDown(): void {
        // Clean up logs after each test
        $this->logger->clear_logs();
        parent::tearDown();
    }
    
    /**
     * Test logger singleton instance
     */
    public function test_singleton_instance() {
        $logger1 = TPAK_Logger::get_instance();
        $logger2 = TPAK_Logger::get_instance();
        
        $this->assertSame($logger1, $logger2);
    }
    
    /**
     * Test basic logging functionality
     */
    public function test_basic_logging() {
        $result = $this->logger->log(
            TPAK_Logger::LEVEL_INFO,
            'Test message',
            TPAK_Logger::CATEGORY_SYSTEM,
            ['test' => 'data']
        );
        
        $this->assertTrue($result);
        
        // Verify log was stored
        $logs = $this->logger->get_logs(['limit' => 1]);
        $this->assertCount(1, $logs);
        $this->assertEquals('info', $logs[0]['level']);
        $this->assertEquals('system', $logs[0]['category']);
        $this->assertEquals('Test message', $logs[0]['message']);
    }
    
    /**
     * Test log level methods
     */
    public function test_log_level_methods() {
        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        $this->logger->critical('Critical message');
        
        $logs = $this->logger->get_logs(['limit' => 10]);
        $this->assertCount(5, $logs);
        
        $levels = array_column($logs, 'level');
        $this->assertContains('debug', $levels);
        $this->assertContains('info', $levels);
        $this->assertContains('warning', $levels);
        $this->assertContains('error', $levels);
        $this->assertContains('critical', $levels);
    }
    
    /**
     * Test log level filtering
     */
    public function test_log_level_filtering() {
        // Set log level to warning
        $this->logger->set_log_level(TPAK_Logger::LEVEL_WARNING);
        
        // Log messages at different levels
        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        
        // Only warning and error should be logged
        $logs = $this->logger->get_logs(['limit' => 10]);
        $this->assertCount(2, $logs);
        
        $levels = array_column($logs, 'level');
        $this->assertContains('warning', $levels);
        $this->assertContains('error', $levels);
        $this->assertNotContains('debug', $levels);
        $this->assertNotContains('info', $levels);
    }
    
    /**
     * Test API error logging
     */
    public function test_api_error_logging() {
        $result = $this->logger->log_api_error(
            '/api/surveys',
            'Connection timeout',
            ['timeout' => 30]
        );
        
        $this->assertTrue($result);
        
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_API]);
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
        $this->assertEquals('api', $logs[0]['category']);
        $this->assertStringContains('/api/surveys', $logs[0]['message']);
    }
    
    /**
     * Test validation error logging
     */
    public function test_validation_error_logging() {
        $result = $this->logger->log_validation_error(
            'email',
            'invalid-email',
            'email_format',
            'Invalid email format'
        );
        
        $this->assertTrue($result);
        
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_VALIDATION]);
        $this->assertCount(1, $logs);
        $this->assertEquals('warning', $logs[0]['level']);
        $this->assertEquals('validation', $logs[0]['category']);
    }
    
    /**
     * Test workflow error logging
     */
    public function test_workflow_error_logging() {
        $user_id = $this->factory->user->create();
        wp_set_current_user($user_id);
        
        $result = $this->logger->log_workflow_error(
            'approve',
            123,
            $user_id,
            'Insufficient permissions'
        );
        
        $this->assertTrue($result);
        
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_WORKFLOW]);
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
        $this->assertEquals('workflow', $logs[0]['category']);
        $this->assertEquals($user_id, $logs[0]['user_id']);
    }
    
    /**
     * Test cron error logging
     */
    public function test_cron_error_logging() {
        $result = $this->logger->log_cron_error(
            'data_import',
            'API connection failed',
            ['retry_count' => 3]
        );
        
        $this->assertTrue($result);
        
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_CRON]);
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
        $this->assertEquals('cron', $logs[0]['category']);
    }
    
    /**
     * Test notification error logging
     */
    public function test_notification_error_logging() {
        $result = $this->logger->log_notification_error(
            'assignment',
            'user@example.com',
            'SMTP connection failed'
        );
        
        $this->assertTrue($result);
        
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_NOTIFICATION]);
        $this->assertCount(1, $logs);
        $this->assertEquals('error', $logs[0]['level']);
        $this->assertEquals('notification', $logs[0]['category']);
    }
    
    /**
     * Test security event logging
     */
    public function test_security_event_logging() {
        $result = $this->logger->log_security_event(
            'unauthorized_access',
            'User attempted to access restricted data',
            ['user_id' => 123, 'data_id' => 456]
        );
        
        $this->assertTrue($result);
        
        $logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_SECURITY]);
        $this->assertCount(1, $logs);
        $this->assertEquals('warning', $logs[0]['level']);
        $this->assertEquals('security', $logs[0]['category']);
    }
    
    /**
     * Test log filtering by level
     */
    public function test_log_filtering_by_level() {
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        
        $error_logs = $this->logger->get_logs(['level' => 'error']);
        $this->assertCount(1, $error_logs);
        $this->assertEquals('error', $error_logs[0]['level']);
        
        $warning_logs = $this->logger->get_logs(['level' => 'warning']);
        $this->assertCount(1, $warning_logs);
        $this->assertEquals('warning', $warning_logs[0]['level']);
    }
    
    /**
     * Test log filtering by category
     */
    public function test_log_filtering_by_category() {
        $this->logger->log(TPAK_Logger::LEVEL_INFO, 'API message', TPAK_Logger::CATEGORY_API);
        $this->logger->log(TPAK_Logger::LEVEL_INFO, 'System message', TPAK_Logger::CATEGORY_SYSTEM);
        $this->logger->log(TPAK_Logger::LEVEL_INFO, 'Workflow message', TPAK_Logger::CATEGORY_WORKFLOW);
        
        $api_logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_API]);
        $this->assertCount(1, $api_logs);
        $this->assertEquals('api', $api_logs[0]['category']);
        
        $system_logs = $this->logger->get_logs(['category' => TPAK_Logger::CATEGORY_SYSTEM]);
        $this->assertCount(1, $system_logs);
        $this->assertEquals('system', $system_logs[0]['category']);
    }
    
    /**
     * Test log pagination
     */
    public function test_log_pagination() {
        // Create multiple log entries
        for ($i = 1; $i <= 10; $i++) {
            $this->logger->info("Message $i");
        }
        
        // Test limit
        $logs = $this->logger->get_logs(['limit' => 5]);
        $this->assertCount(5, $logs);
        
        // Test offset
        $logs_page2 = $this->logger->get_logs(['limit' => 5, 'offset' => 5]);
        $this->assertCount(5, $logs_page2);
        
        // Ensure different results
        $this->assertNotEquals($logs[0]['id'], $logs_page2[0]['id']);
    }
    
    /**
     * Test log statistics
     */
    public function test_log_statistics() {
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        $this->logger->log(TPAK_Logger::LEVEL_INFO, 'API message', TPAK_Logger::CATEGORY_API);
        
        $stats = $this->logger->get_log_stats();
        
        $this->assertEquals(4, $stats['total_logs']);
        $this->assertIsArray($stats['by_level']);
        $this->assertIsArray($stats['by_category']);
        
        // Check level counts
        $level_counts = array_column($stats['by_level'], 'count', 'level');
        $this->assertEquals(2, $level_counts['info']);
        $this->assertEquals(1, $level_counts['warning']);
        $this->assertEquals(1, $level_counts['error']);
    }
    
    /**
     * Test log clearing
     */
    public function test_log_clearing() {
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        
        // Clear all logs
        $result = $this->logger->clear_logs();
        $this->assertTrue($result);
        
        $logs = $this->logger->get_logs();
        $this->assertEmpty($logs);
    }
    
    /**
     * Test selective log clearing
     */
    public function test_selective_log_clearing() {
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        
        // Clear only error logs
        $result = $this->logger->clear_logs(['level' => 'error']);
        $this->assertTrue($result);
        
        $logs = $this->logger->get_logs();
        $this->assertCount(2, $logs);
        
        $levels = array_column($logs, 'level');
        $this->assertNotContains('error', $levels);
        $this->assertContains('info', $levels);
        $this->assertContains('warning', $levels);
    }
    
    /**
     * Test context data storage
     */
    public function test_context_data_storage() {
        $context = [
            'user_id' => 123,
            'action' => 'test_action',
            'data' => ['key' => 'value']
        ];
        
        $this->logger->info('Test message with context', TPAK_Logger::CATEGORY_SYSTEM, $context);
        
        $logs = $this->logger->get_logs(['limit' => 1]);
        $this->assertCount(1, $logs);
        
        $stored_context = json_decode($logs[0]['context'], true);
        $this->assertEquals($context, $stored_context);
    }
    
    /**
     * Test log level hierarchy
     */
    public function test_log_level_hierarchy() {
        // Set to error level
        $this->logger->set_log_level(TPAK_Logger::LEVEL_ERROR);
        
        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        $this->logger->critical('Critical message');
        
        $logs = $this->logger->get_logs();
        $this->assertCount(2, $logs); // Only error and critical should be logged
        
        $levels = array_column($logs, 'level');
        $this->assertContains('error', $levels);
        $this->assertContains('critical', $levels);
    }
    
    /**
     * Test user information capture
     */
    public function test_user_information_capture() {
        $user_id = $this->factory->user->create();
        wp_set_current_user($user_id);
        
        $this->logger->info('Test message');
        
        $logs = $this->logger->get_logs(['limit' => 1]);
        $this->assertCount(1, $logs);
        $this->assertEquals($user_id, $logs[0]['user_id']);
    }
}