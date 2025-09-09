<?php
/**
 * Dashboard Statistics Tests
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test Dashboard Statistics Class
 */
class Test_TPAK_Dashboard_Stats extends WP_UnitTestCase {
    
    /**
     * Dashboard stats instance
     * 
     * @var TPAK_Dashboard_Stats
     */
    private $stats;
    
    /**
     * Test user IDs
     * 
     * @var array
     */
    private $test_users = array();
    
    /**
     * Test post IDs
     * 
     * @var array
     */
    private $test_posts = array();
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Initialize components
        TPAK_Post_Types::get_instance();
        TPAK_Roles::get_instance()->create_roles();
        $this->stats = TPAK_Dashboard_Stats::get_instance();
        
        // Create test users
        $this->create_test_users();
        
        // Create test data
        $this->create_test_data();
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Clean up test data
        foreach ($this->test_posts as $post_id) {
            wp_delete_post($post_id, true);
        }
        
        foreach ($this->test_users as $user_id) {
            wp_delete_user($user_id);
        }
        
        // Clear cache
        $this->stats->clear_stats_cache();
        
        parent::tearDown();
    }
    
    /**
     * Create test users with different roles
     */
    private function create_test_users() {
        // Create Interviewer A
        $this->test_users['interviewer_a'] = $this->factory->user->create(array(
            'role' => 'tpak_interviewer_a',
            'display_name' => 'Test Interviewer A',
        ));
        
        // Create Supervisor B
        $this->test_users['supervisor_b'] = $this->factory->user->create(array(
            'role' => 'tpak_supervisor_b',
            'display_name' => 'Test Supervisor B',
        ));
        
        // Create Examiner C
        $this->test_users['examiner_c'] = $this->factory->user->create(array(
            'role' => 'tpak_examiner_c',
            'display_name' => 'Test Examiner C',
        ));
        
        // Create Administrator
        $this->test_users['administrator'] = $this->factory->user->create(array(
            'role' => 'administrator',
            'display_name' => 'Test Administrator',
        ));
    }
    
    /**
     * Create test survey data
     */
    private function create_test_data() {
        $statuses = array('pending_a', 'pending_b', 'pending_c', 'finalized', 'rejected_by_b');
        
        foreach ($statuses as $index => $status) {
            for ($i = 0; $i < 3; $i++) {
                $post_id = $this->factory->post->create(array(
                    'post_type' => 'tpak_survey_data',
                    'post_status' => 'publish',
                    'post_title' => "Test Survey Data {$status} {$i}",
                ));
                
                // Set workflow status
                update_post_meta($post_id, '_tpak_workflow_status', $status);
                
                // Assign to appropriate user based on status
                $assigned_user = 0;
                switch ($status) {
                    case 'pending_a':
                    case 'rejected_by_b':
                        $assigned_user = $this->test_users['interviewer_a'];
                        break;
                    case 'pending_b':
                        $assigned_user = $this->test_users['supervisor_b'];
                        break;
                    case 'pending_c':
                        $assigned_user = $this->test_users['examiner_c'];
                        break;
                }
                
                if ($assigned_user) {
                    update_post_meta($post_id, '_tpak_assigned_user', $assigned_user);
                }
                
                // Add audit trail
                $audit_trail = array(
                    array(
                        'timestamp' => current_time('mysql'),
                        'user_id' => $assigned_user,
                        'action' => 'status_change',
                        'old_value' => '',
                        'new_value' => $status,
                        'notes' => 'Test data creation',
                    ),
                );
                update_post_meta($post_id, '_tpak_audit_trail', wp_json_encode($audit_trail));
                
                $this->test_posts[] = $post_id;
            }
        }
    }
    
    /**
     * Test get_user_statistics for Interviewer A
     */
    public function test_get_interviewer_stats() {
        $user_id = $this->test_users['interviewer_a'];
        $stats = $this->stats->get_user_statistics($user_id, 'tpak_interviewer_a');
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('Pending Review', $stats);
        $this->assertArrayHasKey('Rejected Items', $stats);
        $this->assertArrayHasKey('Completed Today', $stats);
        $this->assertArrayHasKey('Total Assigned', $stats);
        
        // Check that pending review count is correct (3 pending_a items)
        $this->assertEquals(3, $stats['Pending Review']);
        
        // Check that rejected items count is correct (3 rejected_by_b items)
        $this->assertEquals(3, $stats['Rejected Items']);
        
        // Check that total assigned is correct (6 items: 3 pending_a + 3 rejected_by_b)
        $this->assertEquals(6, $stats['Total Assigned']);
    }
    
    /**
     * Test get_user_statistics for Supervisor B
     */
    public function test_get_supervisor_stats() {
        $user_id = $this->test_users['supervisor_b'];
        $stats = $this->stats->get_user_statistics($user_id, 'tpak_supervisor_b');
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('Pending Approval', $stats);
        $this->assertArrayHasKey('Approved Today', $stats);
        $this->assertArrayHasKey('Sent to Examiner', $stats);
        $this->assertArrayHasKey('Rejected Items', $stats);
        
        // Check that pending approval count is correct (3 pending_b items)
        $this->assertEquals(3, $stats['Pending Approval']);
    }
    
    /**
     * Test get_user_statistics for Examiner C
     */
    public function test_get_examiner_stats() {
        $user_id = $this->test_users['examiner_c'];
        $stats = $this->stats->get_user_statistics($user_id, 'tpak_examiner_c');
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('Final Review', $stats);
        $this->assertArrayHasKey('Finalized Today', $stats);
        $this->assertArrayHasKey('Rejected Items', $stats);
        $this->assertArrayHasKey('Total Processed', $stats);
        
        // Check that final review count is correct (3 pending_c items)
        $this->assertEquals(3, $stats['Final Review']);
    }
    
    /**
     * Test get_user_statistics for Administrator
     */
    public function test_get_admin_stats() {
        $user_id = $this->test_users['administrator'];
        wp_set_current_user($user_id);
        
        $stats = $this->stats->get_user_statistics($user_id, null);
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('Total Records', $stats);
        $this->assertArrayHasKey('Active Users', $stats);
        $this->assertArrayHasKey('Pending A', $stats);
        $this->assertArrayHasKey('Pending B', $stats);
        $this->assertArrayHasKey('Pending C', $stats);
        $this->assertArrayHasKey('Finalized', $stats);
        $this->assertArrayHasKey('System Health', $stats);
        
        // Check total records (15 total: 3 each of 5 statuses)
        $this->assertEquals(15, $stats['Total Records']);
        
        // Check active users (3 TPAK role users)
        $this->assertEquals(3, $stats['Active Users']);
        
        // Check status counts
        $this->assertEquals(3, $stats['Pending A']);
        $this->assertEquals(3, $stats['Pending B']);
        $this->assertEquals(3, $stats['Pending C']);
        $this->assertEquals(3, $stats['Finalized']); // Only finalized status
    }
    
    /**
     * Test get_status_counts
     */
    public function test_get_status_counts() {
        $counts = $this->stats->get_status_counts();
        
        $this->assertIsArray($counts);
        $this->assertEquals(3, $counts['pending_a']);
        $this->assertEquals(3, $counts['pending_b']);
        $this->assertEquals(3, $counts['pending_c']);
        $this->assertEquals(3, $counts['finalized']);
        $this->assertEquals(3, $counts['rejected_by_b']);
    }
    
    /**
     * Test get_workflow_chart_data
     */
    public function test_get_workflow_chart_data() {
        $chart_data = $this->stats->get_workflow_chart_data();
        
        $this->assertIsArray($chart_data);
        $this->assertArrayHasKey('labels', $chart_data);
        $this->assertArrayHasKey('data', $chart_data);
        $this->assertArrayHasKey('colors', $chart_data);
        
        $this->assertCount(5, $chart_data['labels']); // 5 different statuses
        $this->assertCount(5, $chart_data['data']);
        $this->assertCount(5, $chart_data['colors']);
        
        // Check that all data values are 3 (we created 3 items per status)
        foreach ($chart_data['data'] as $value) {
            $this->assertEquals(3, $value);
        }
    }
    
    /**
     * Test get_daily_activity_stats
     */
    public function test_get_daily_activity_stats() {
        $activity_data = $this->stats->get_daily_activity_stats();
        
        $this->assertIsArray($activity_data);
        $this->assertCount(31, $activity_data); // 31 days (30 days ago + today)
        
        // Check that today has data (our test posts were created today)
        $today = date('Y-m-d');
        $this->assertArrayHasKey($today, $activity_data);
        $this->assertEquals(15, $activity_data[$today]); // 15 test posts created today
    }
    
    /**
     * Test get_performance_metrics
     */
    public function test_get_performance_metrics() {
        $metrics = $this->stats->get_performance_metrics();
        
        $this->assertIsArray($metrics);
        
        if (isset($metrics['throughput'])) {
            $this->assertArrayHasKey('today', $metrics['throughput']);
            $this->assertArrayHasKey('yesterday', $metrics['throughput']);
            $this->assertArrayHasKey('change', $metrics['throughput']);
        }
        
        if (isset($metrics['avg_processing_time'])) {
            $this->assertIsArray($metrics['avg_processing_time']);
        }
    }
    
    /**
     * Test statistics caching
     */
    public function test_statistics_caching() {
        $user_id = $this->test_users['interviewer_a'];
        
        // First call should set cache
        $stats1 = $this->stats->get_user_statistics($user_id, 'tpak_interviewer_a');
        
        // Second call should use cache (same results)
        $stats2 = $this->stats->get_user_statistics($user_id, 'tpak_interviewer_a');
        
        $this->assertEquals($stats1, $stats2);
        
        // Clear cache and verify it's cleared
        $this->stats->clear_stats_cache();
        
        // After clearing cache, should get fresh data
        $stats3 = $this->stats->get_user_statistics($user_id, 'tpak_interviewer_a');
        $this->assertEquals($stats1, $stats3); // Should still be same since data hasn't changed
    }
    
    /**
     * Test role-based filtering
     */
    public function test_role_based_filtering() {
        // Test that each role only sees their relevant statistics
        $interviewer_stats = $this->stats->get_user_statistics(
            $this->test_users['interviewer_a'], 
            'tpak_interviewer_a'
        );
        
        $supervisor_stats = $this->stats->get_user_statistics(
            $this->test_users['supervisor_b'], 
            'tpak_supervisor_b'
        );
        
        $examiner_stats = $this->stats->get_user_statistics(
            $this->test_users['examiner_c'], 
            'tpak_examiner_c'
        );
        
        // Each role should have different stat keys
        $this->assertNotEquals(
            array_keys($interviewer_stats), 
            array_keys($supervisor_stats)
        );
        
        $this->assertNotEquals(
            array_keys($supervisor_stats), 
            array_keys($examiner_stats)
        );
        
        $this->assertNotEquals(
            array_keys($interviewer_stats), 
            array_keys($examiner_stats)
        );
    }
    
    /**
     * Test AJAX refresh functionality
     */
    public function test_ajax_refresh_stats() {
        // Set up AJAX request
        wp_set_current_user($this->test_users['administrator']);
        
        $_POST['nonce'] = wp_create_nonce('tpak_admin_ajax');
        $_POST['action'] = 'tpak_refresh_stats';
        
        // Capture output
        ob_start();
        
        try {
            $this->stats->ajax_refresh_stats();
        } catch (WPAjaxDieContinueException $e) {
            // Expected for successful AJAX response
        }
        
        $output = ob_get_clean();
        
        // Should return JSON response
        $this->assertJson($output);
        
        $response = json_decode($output, true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('stats', $response['data']);
        $this->assertArrayHasKey('chart_data', $response['data']);
        $this->assertArrayHasKey('activity_data', $response['data']);
    }
    
    /**
     * Test statistics with no data
     */
    public function test_stats_with_no_data() {
        // Clear all test data
        foreach ($this->test_posts as $post_id) {
            wp_delete_post($post_id, true);
        }
        $this->test_posts = array();
        
        // Clear cache
        $this->stats->clear_stats_cache();
        
        // Get stats for interviewer
        $stats = $this->stats->get_user_statistics(
            $this->test_users['interviewer_a'], 
            'tpak_interviewer_a'
        );
        
        // All counts should be 0
        foreach ($stats as $key => $value) {
            if (is_numeric($value)) {
                $this->assertEquals(0, $value, "Stat '{$key}' should be 0 when no data exists");
            }
        }
    }
    
    /**
     * Test system health status calculation
     */
    public function test_system_health_status() {
        // Test with no configuration (should be critical)
        delete_option('tpak_dq_settings');
        delete_option('tpak_last_import_date');
        wp_clear_scheduled_hook('tpak_import_survey_data');
        
        $this->stats->clear_stats_cache();
        
        wp_set_current_user($this->test_users['administrator']);
        $stats = $this->stats->get_user_statistics($this->test_users['administrator'], null);
        
        $this->assertEquals('Critical', $stats['System Health']);
        
        // Test with partial configuration (should be warning)
        update_option('tpak_dq_settings', array(
            'api_url' => 'http://example.com',
            'api_username' => 'test',
        ));
        
        $this->stats->clear_stats_cache();
        $stats = $this->stats->get_user_statistics($this->test_users['administrator'], null);
        
        $this->assertContains($stats['System Health'], array('Warning', 'Critical'));
    }
    
    /**
     * Test performance with large dataset
     */
    public function test_performance_with_large_dataset() {
        // Create a larger dataset
        $start_time = microtime(true);
        
        for ($i = 0; $i < 100; $i++) {
            $post_id = $this->factory->post->create(array(
                'post_type' => 'tpak_survey_data',
                'post_status' => 'publish',
            ));
            
            update_post_meta($post_id, '_tpak_workflow_status', 'pending_a');
            update_post_meta($post_id, '_tpak_assigned_user', $this->test_users['interviewer_a']);
            
            $this->test_posts[] = $post_id;
        }
        
        // Test statistics calculation performance
        $stats_start = microtime(true);
        $stats = $this->stats->get_user_statistics(
            $this->test_users['interviewer_a'], 
            'tpak_interviewer_a'
        );
        $stats_time = microtime(true) - $stats_start;
        
        // Should complete within reasonable time (1 second)
        $this->assertLessThan(1.0, $stats_time, 'Statistics calculation should complete within 1 second');
        
        // Verify correct count
        $this->assertEquals(103, $stats['Pending Review']); // 100 new + 3 original
    }
}

/**
 * Run the tests
 */
function run_dashboard_stats_tests() {
    echo "Running Dashboard Statistics Tests...\n";
    
    $test = new Test_TPAK_Dashboard_Stats();
    $methods = get_class_methods($test);
    
    $passed = 0;
    $failed = 0;
    
    foreach ($methods as $method) {
        if (strpos($method, 'test_') === 0) {
            echo "Running {$method}... ";
            
            try {
                $test->setUp();
                $test->$method();
                $test->tearDown();
                echo "PASSED\n";
                $passed++;
            } catch (Exception $e) {
                echo "FAILED: " . $e->getMessage() . "\n";
                $failed++;
            }
        }
    }
    
    echo "\nResults: {$passed} passed, {$failed} failed\n";
    
    if ($failed === 0) {
        echo "All dashboard statistics tests passed!\n";
    }
}

// Run tests if called directly
if (defined('WP_CLI') && WP_CLI) {
    run_dashboard_stats_tests();
}