<?php
/**
 * Unit Tests for Cron System
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

/**
 * Test TPAK Cron Class
 */
class Test_TPAK_Cron extends WP_UnitTestCase {
    
    /**
     * Cron instance
     * 
     * @var TPAK_Cron
     */
    private $cron;
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->cron = TPAK_Cron::get_instance();
        
        // Clear any existing cron jobs
        wp_clear_scheduled_hook(TPAK_Cron::CRON_HOOK);
        
        // Clear logs and stats
        delete_option('tpak_dq_cron_logs');
        delete_option('tpak_dq_import_stats');
        delete_option('tpak_dq_import_failures');
    }
    
    /**
     * Clean up after test
     */
    public function tearDown(): void {
        // Clear cron jobs
        wp_clear_scheduled_hook(TPAK_Cron::CRON_HOOK);
        
        // Clear test options
        delete_option('tpak_dq_cron_logs');
        delete_option('tpak_dq_import_stats');
        delete_option('tpak_dq_import_failures');
        
        parent::tearDown();
    }
    
    /**
     * Test custom cron schedules
     */
    public function test_custom_cron_schedules() {
        $schedules = wp_get_schedules();
        
        // Check if custom schedules are added
        $this->assertArrayHasKey('tpak_twicedaily', $schedules);
        $this->assertArrayHasKey('tpak_every_six_hours', $schedules);
        $this->assertArrayHasKey('tpak_every_three_hours', $schedules);
        
        // Check intervals
        $this->assertEquals(12 * HOUR_IN_SECONDS, $schedules['tpak_twicedaily']['interval']);
        $this->assertEquals(6 * HOUR_IN_SECONDS, $schedules['tpak_every_six_hours']['interval']);
        $this->assertEquals(3 * HOUR_IN_SECONDS, $schedules['tpak_every_three_hours']['interval']);
    }
    
    /**
     * Test cron scheduling
     */
    public function test_cron_scheduling() {
        // Test successful scheduling
        $result = $this->cron->schedule_import('daily', '12345');
        $this->assertTrue($result);
        
        // Check if event is scheduled
        $next_scheduled = wp_next_scheduled(TPAK_Cron::CRON_HOOK);
        $this->assertNotFalse($next_scheduled);
        
        // Check settings were updated
        $settings = get_option('tpak_dq_settings', array());
        $this->assertEquals('daily', $settings['cron']['interval']);
        $this->assertEquals('12345', $settings['cron']['survey_id']);
        $this->assertNotEmpty($settings['cron']['last_scheduled']);
    }
    
    /**
     * Test cron scheduling with invalid interval
     */
    public function test_cron_scheduling_invalid_interval() {
        $result = $this->cron->schedule_import('invalid_interval', '12345');
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('invalid_interval', $result->get_error_code());
    }
    
    /**
     * Test cron scheduling without survey ID
     */
    public function test_cron_scheduling_no_survey_id() {
        $result = $this->cron->schedule_import('daily', '');
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('no_survey_id', $result->get_error_code());
    }
    
    /**
     * Test cron unscheduling
     */
    public function test_cron_unscheduling() {
        // First schedule a job
        $this->cron->schedule_import('daily', '12345');
        $this->assertNotFalse(wp_next_scheduled(TPAK_Cron::CRON_HOOK));
        
        // Then unschedule it
        $result = $this->cron->unschedule_import();
        $this->assertTrue($result);
        
        // Check if event is unscheduled
        $this->assertFalse(wp_next_scheduled(TPAK_Cron::CRON_HOOK));
    }
    
    /**
     * Test cron status
     */
    public function test_cron_status() {
        // Test when not scheduled
        $status = $this->cron->get_cron_status();
        $this->assertFalse($status['is_scheduled']);
        $this->assertNull($status['next_run']);
        
        // Schedule a job
        $this->cron->schedule_import('daily', '12345');
        
        // Test when scheduled
        $status = $this->cron->get_cron_status();
        $this->assertTrue($status['is_scheduled']);
        $this->assertNotNull($status['next_run']);
        $this->assertEquals('daily', $status['interval']);
        $this->assertEquals('12345', $status['survey_id']);
    }
    
    /**
     * Test import statistics update
     */
    public function test_import_statistics_update() {
        $result = array(
            'imported' => 5,
            'skipped' => 2,
            'errors' => 1,
            'total_responses' => 8
        );
        
        $execution_time = 2.5;
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->cron);
        $method = $reflection->getMethod('update_import_statistics');
        $method->setAccessible(true);
        
        $method->invoke($this->cron, $result, $execution_time);
        
        // Check statistics were updated
        $stats = get_option('tpak_dq_import_stats');
        $this->assertEquals(1, $stats['total_runs']);
        $this->assertEquals(5, $stats['total_imported']);
        $this->assertEquals(2, $stats['total_skipped']);
        $this->assertEquals(1, $stats['total_errors']);
        $this->assertEquals(2.5, $stats['total_execution_time']);
        $this->assertEquals(2.5, $stats['average_execution_time']);
        $this->assertNotNull($stats['last_run']);
        
        // Test second run
        $method->invoke($this->cron, $result, $execution_time);
        
        $stats = get_option('tpak_dq_import_stats');
        $this->assertEquals(2, $stats['total_runs']);
        $this->assertEquals(10, $stats['total_imported']);
        $this->assertEquals(2.5, $stats['average_execution_time']);
    }
    
    /**
     * Test cron logging
     */
    public function test_cron_logging() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->cron);
        $method = $reflection->getMethod('log_cron_event');
        $method->setAccessible(true);
        
        // Log an event
        $method->invoke($this->cron, 'test_event', array('key' => 'value'));
        
        // Get logs
        $logs = $this->cron->get_cron_logs();
        
        $this->assertCount(1, $logs);
        $this->assertEquals('test_event', $logs[0]['event']);
        $this->assertEquals('value', $logs[0]['data']['key']);
        $this->assertArrayHasKey('timestamp', $logs[0]);
    }
    
    /**
     * Test cron logs filtering
     */
    public function test_cron_logs_filtering() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->cron);
        $method = $reflection->getMethod('log_cron_event');
        $method->setAccessible(true);
        
        // Log multiple events
        $method->invoke($this->cron, 'import_success', array('imported' => 5));
        $method->invoke($this->cron, 'import_failed', array('error' => 'Test error'));
        $method->invoke($this->cron, 'scheduled', array('interval' => 'daily'));
        
        // Test filtering by event
        $logs = $this->cron->get_cron_logs(array('event' => 'import_success'));
        $this->assertCount(1, $logs);
        $this->assertEquals('import_success', $logs[0]['event']);
        
        // Test limit
        $logs = $this->cron->get_cron_logs(array(), 2);
        $this->assertCount(2, $logs);
        
        // Test no filters (should return all)
        $logs = $this->cron->get_cron_logs();
        $this->assertCount(3, $logs);
    }
    
    /**
     * Test available intervals
     */
    public function test_available_intervals() {
        $intervals = $this->cron->get_available_intervals();
        
        $this->assertIsArray($intervals);
        
        // Check required intervals exist
        $required_intervals = array('hourly', 'daily', 'weekly');
        foreach ($required_intervals as $interval) {
            $this->assertArrayHasKey($interval, $intervals);
            $this->assertArrayHasKey('label', $intervals[$interval]);
            $this->assertArrayHasKey('interval', $intervals[$interval]);
            $this->assertArrayHasKey('display', $intervals[$interval]);
        }
        
        // Check custom intervals
        $this->assertArrayHasKey('tpak_twicedaily', $intervals);
        $this->assertArrayHasKey('tpak_every_six_hours', $intervals);
        $this->assertArrayHasKey('tpak_every_three_hours', $intervals);
    }
    
    /**
     * Test cron functionality test
     */
    public function test_cron_functionality_test() {
        $test_result = $this->cron->test_cron_functionality();
        
        $this->assertIsArray($test_result);
        
        // Check required test results
        $required_tests = array(
            'wp_cron_status',
            'cron_scheduled',
            'custom_schedules',
            'api_configuration'
        );
        
        foreach ($required_tests as $test) {
            $this->assertArrayHasKey($test, $test_result);
            $this->assertArrayHasKey('status', $test_result[$test]);
            $this->assertArrayHasKey('message', $test_result[$test]);
        }
        
        // Custom schedules should be success
        $this->assertEquals('success', $test_result['custom_schedules']['status']);
    }
    
    /**
     * Test import failure handling
     */
    public function test_import_failure_handling() {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->cron);
        $method = $reflection->getMethod('handle_import_failure');
        $method->setAccessible(true);
        
        // Test first failure
        $method->invoke($this->cron, 'Test error', '12345');
        
        $failure_count = get_option('tpak_dq_import_failures', 0);
        $this->assertEquals(1, $failure_count);
        
        // Test multiple failures (should disable cron after 5)
        for ($i = 2; $i <= 5; $i++) {
            $method->invoke($this->cron, 'Test error', '12345');
        }
        
        $failure_count = get_option('tpak_dq_import_failures', 0);
        $this->assertEquals(5, $failure_count);
        
        // Check if cron was disabled
        $settings = get_option('tpak_dq_settings', array());
        $this->assertFalse($settings['cron']['enabled']);
        $this->assertNotEmpty($settings['cron']['disabled_reason']);
    }
    
    /**
     * Test settings update triggers cron reschedule
     */
    public function test_settings_update_reschedule() {
        // Set initial settings
        $old_settings = array(
            'cron' => array(
                'interval' => 'daily',
                'survey_id' => '12345'
            )
        );
        
        $new_settings = array(
            'cron' => array(
                'interval' => 'hourly',
                'survey_id' => '67890'
            )
        );
        
        update_option('tpak_dq_settings', $old_settings);
        
        // Schedule with old settings
        $this->cron->schedule_import('daily', '12345');
        $this->assertNotFalse(wp_next_scheduled(TPAK_Cron::CRON_HOOK));
        
        // Update settings (should trigger reschedule)
        $this->cron->update_cron_schedule($old_settings, $new_settings);
        
        // Check if still scheduled (should be rescheduled with new interval)
        $this->assertNotFalse(wp_next_scheduled(TPAK_Cron::CRON_HOOK));
    }
    
    /**
     * Test clear cron logs
     */
    public function test_clear_cron_logs() {
        // Add some logs
        $reflection = new ReflectionClass($this->cron);
        $method = $reflection->getMethod('log_cron_event');
        $method->setAccessible(true);
        
        $method->invoke($this->cron, 'test_event', array());
        
        // Verify logs exist
        $logs = $this->cron->get_cron_logs();
        $this->assertNotEmpty($logs);
        
        // Clear logs
        $result = $this->cron->clear_cron_logs();
        $this->assertTrue($result);
        
        // Verify logs are cleared
        $logs = $this->cron->get_cron_logs();
        $this->assertEmpty($logs);
    }
    
    /**
     * Test AJAX security
     */
    public function test_ajax_security() {
        // Test manual import without permissions
        $user_id = $this->factory->user->create(array('role' => 'subscriber'));
        wp_set_current_user($user_id);
        
        $this->expectException('WPDieException');
        $this->cron->handle_manual_import();
    }
    
    /**
     * Test execute import method structure
     */
    public function test_execute_import_structure() {
        // Test that execute_import method exists and handles missing survey ID
        $this->assertTrue(method_exists($this->cron, 'execute_import'));
        
        // Test with empty survey ID (should log failure)
        $this->cron->execute_import('');
        
        // Check if failure was logged
        $logs = $this->cron->get_cron_logs(array('event' => 'import_failed'));
        $this->assertNotEmpty($logs);
    }
}