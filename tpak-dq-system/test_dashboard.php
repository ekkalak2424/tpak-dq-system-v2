<?php
/**
 * Dashboard Test Runner
 * 
 * Simple test to verify dashboard statistics functionality
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Include WordPress
require_once '../../../wp-config.php';

// Ensure we're in admin context
if (!is_admin()) {
    define('WP_ADMIN', true);
}

echo "<h1>TPAK Dashboard Statistics Test</h1>\n";

try {
    // Initialize required classes
    if (!class_exists('TPAK_Post_Types')) {
        require_once 'includes/class-post-types.php';
    }
    
    if (!class_exists('TPAK_Roles')) {
        require_once 'includes/class-roles.php';
    }
    
    if (!class_exists('TPAK_Dashboard_Stats')) {
        require_once 'includes/class-dashboard-stats.php';
    }
    
    // Initialize components
    TPAK_Post_Types::get_instance();
    TPAK_Roles::get_instance();
    $stats = TPAK_Dashboard_Stats::get_instance();
    
    echo "<h2>✓ Classes loaded successfully</h2>\n";
    
    // Test 1: Create test user
    $test_user_id = wp_create_user('test_interviewer', 'password123', 'test@example.com');
    if (is_wp_error($test_user_id)) {
        throw new Exception('Failed to create test user: ' . $test_user_id->get_error_message());
    }
    
    $user = new WP_User($test_user_id);
    $user->set_role('tpak_interviewer_a');
    
    echo "<h2>✓ Test user created with Interviewer A role</h2>\n";
    
    // Test 2: Create test survey data
    $test_posts = array();
    for ($i = 0; $i < 5; $i++) {
        $post_id = wp_insert_post(array(
            'post_type' => 'tpak_survey_data',
            'post_status' => 'publish',
            'post_title' => "Test Survey Data {$i}",
        ));
        
        if (is_wp_error($post_id)) {
            throw new Exception('Failed to create test post: ' . $post_id->get_error_message());
        }
        
        update_post_meta($post_id, '_tpak_workflow_status', 'pending_a');
        update_post_meta($post_id, '_tpak_assigned_user', $test_user_id);
        
        $test_posts[] = $post_id;
    }
    
    echo "<h2>✓ Created 5 test survey data records</h2>\n";
    
    // Test 3: Get user statistics
    $user_stats = $stats->get_user_statistics($test_user_id, 'tpak_interviewer_a');
    
    echo "<h2>✓ User Statistics Retrieved</h2>\n";
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Statistic</th><th>Value</th></tr>\n";
    foreach ($user_stats as $key => $value) {
        echo "<tr><td>{$key}</td><td>{$value}</td></tr>\n";
    }
    echo "</table>\n";
    
    // Test 4: Get status counts
    $status_counts = $stats->get_status_counts();
    
    echo "<h2>✓ Status Counts Retrieved</h2>\n";
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Status</th><th>Count</th></tr>\n";
    foreach ($status_counts as $status => $count) {
        echo "<tr><td>{$status}</td><td>{$count}</td></tr>\n";
    }
    echo "</table>\n";
    
    // Test 5: Get chart data
    $chart_data = $stats->get_workflow_chart_data();
    
    echo "<h2>✓ Chart Data Retrieved</h2>\n";
    echo "<p>Labels: " . implode(', ', $chart_data['labels']) . "</p>\n";
    echo "<p>Data: " . implode(', ', $chart_data['data']) . "</p>\n";
    
    // Test 6: Get activity data
    $activity_data = $stats->get_daily_activity_stats();
    
    echo "<h2>✓ Activity Data Retrieved</h2>\n";
    echo "<p>Activity data for last 30 days: " . count($activity_data) . " days</p>\n";
    echo "<p>Today's activity: " . ($activity_data[date('Y-m-d')] ?? 0) . " records</p>\n";
    
    // Test 7: Test caching
    $start_time = microtime(true);
    $cached_stats = $stats->get_user_statistics($test_user_id, 'tpak_interviewer_a');
    $cache_time = microtime(true) - $start_time;
    
    echo "<h2>✓ Cache Test</h2>\n";
    echo "<p>Cached statistics retrieved in: " . round($cache_time * 1000, 2) . " ms</p>\n";
    echo "<p>Statistics match: " . ($user_stats === $cached_stats ? 'Yes' : 'No') . "</p>\n";
    
    // Test 8: Test admin statistics
    $admin_user_id = 1; // Assume user ID 1 is admin
    wp_set_current_user($admin_user_id);
    
    $admin_stats = $stats->get_user_statistics($admin_user_id, null);
    
    echo "<h2>✓ Admin Statistics Retrieved</h2>\n";
    echo "<table border='1' cellpadding='5'>\n";
    echo "<tr><th>Statistic</th><th>Value</th></tr>\n";
    foreach ($admin_stats as $key => $value) {
        echo "<tr><td>{$key}</td><td>{$value}</td></tr>\n";
    }
    echo "</table>\n";
    
    // Cleanup
    foreach ($test_posts as $post_id) {
        wp_delete_post($post_id, true);
    }
    wp_delete_user($test_user_id);
    
    echo "<h2>✓ Cleanup completed</h2>\n";
    echo "<h1 style='color: green;'>All Dashboard Tests Passed!</h1>\n";
    
} catch (Exception $e) {
    echo "<h1 style='color: red;'>Test Failed!</h1>\n";
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
    
    // Cleanup on error
    if (isset($test_posts)) {
        foreach ($test_posts as $post_id) {
            wp_delete_post($post_id, true);
        }
    }
    if (isset($test_user_id) && !is_wp_error($test_user_id)) {
        wp_delete_user($test_user_id);
    }
}

echo "<hr>\n";
echo "<p><strong>Test completed at:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>