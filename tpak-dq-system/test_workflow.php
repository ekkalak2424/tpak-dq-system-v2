<?php
/**
 * Workflow Test Utility
 * 
 * Simple script to test workflow functionality during development
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Only run if WordPress is loaded and user is admin
if (!defined('ABSPATH') || !current_user_can('manage_options')) {
    exit('Access denied');
}

// Get workflow instance
$workflow = TPAK_Workflow::get_instance();

echo "<h1>TPAK DQ System - Workflow Test</h1>\n";
echo "<style>
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: #666; }
    .workflow-state { display: inline-block; padding: 5px 10px; margin: 2px; border-radius: 4px; color: white; font-size: 12px; }
    .transition-item { background: #fff; padding: 10px; margin: 5px 0; border-left: 4px solid #0073aa; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>\n";

// Test workflow states
echo "<div class='test-section'>\n";
echo "<h2>Workflow States</h2>\n";

$states = $workflow->get_workflow_states();
echo "<p>Total states defined: <strong>" . count($states) . "</strong></p>\n";

foreach ($states as $state_key => $state) {
    $final_badge = isset($state['is_final']) && $state['is_final'] ? ' (FINAL)' : '';
    echo "<div class='workflow-state' style='background-color: {$state['color']};'>";
    echo esc_html($state['label']) . $final_badge;
    echo "</div>\n";
}

echo "<h3>State Details</h3>\n";
echo "<table>\n";
echo "<tr><th>State</th><th>Label</th><th>Allowed Roles</th><th>Available Actions</th><th>Next States</th></tr>\n";

foreach ($states as $state_key => $state) {
    echo "<tr>\n";
    echo "<td><code>" . esc_html($state_key) . "</code></td>\n";
    echo "<td>" . esc_html($state['label']) . "</td>\n";
    echo "<td>" . esc_html(implode(', ', $state['allowed_roles'])) . "</td>\n";
    echo "<td>" . esc_html(implode(', ', $state['actions'])) . "</td>\n";
    echo "<td>" . esc_html(implode(', ', $state['next_states'])) . "</td>\n";
    echo "</tr>\n";
}

echo "</table>\n";
echo "</div>\n";

// Test workflow transitions
echo "<div class='test-section'>\n";
echo "<h2>Workflow Transitions</h2>\n";

$transitions = $workflow->get_workflow_transitions();
echo "<p>Total transitions defined: <strong>" . count($transitions) . "</strong></p>\n";

foreach ($transitions as $action_key => $transition) {
    echo "<div class='transition-item'>\n";
    echo "<strong>" . esc_html($transition['label']) . "</strong> (<code>" . esc_html($action_key) . "</code>)<br>\n";
    echo "<small>\n";
    echo "<strong>From:</strong> " . esc_html(implode(', ', $transition['from'])) . " → ";
    
    if (is_array($transition['to'])) {
        echo "<strong>To:</strong> Sampling Gate (" . esc_html(implode(' or ', $transition['to'])) . ")<br>\n";
    } else {
        echo "<strong>To:</strong> " . esc_html($transition['to']) . "<br>\n";
    }
    
    echo "<strong>Required Role:</strong> " . esc_html($transition['required_role']) . "<br>\n";
    
    if (isset($transition['requires_note']) && $transition['requires_note']) {
        echo "<em>Requires note/reason</em><br>\n";
    }
    
    if (isset($transition['is_sampling']) && $transition['is_sampling']) {
        echo "<em>Sampling transition (70/30 split)</em><br>\n";
    }
    
    echo "</small>\n";
    echo "</div>\n";
}

echo "</div>\n";

// Test workflow statistics
echo "<div class='test-section'>\n";
echo "<h2>Workflow Statistics</h2>\n";

$statistics = $workflow->get_workflow_statistics();

if (!empty($statistics)) {
    echo "<table>\n";
    echo "<tr><th>Status</th><th>Label</th><th>Count</th><th>Color</th></tr>\n";
    
    $total = 0;
    foreach ($statistics as $status => $data) {
        $total += $data['count'];
        echo "<tr>\n";
        echo "<td><code>" . esc_html($status) . "</code></td>\n";
        echo "<td>" . esc_html($data['label']) . "</td>\n";
        echo "<td><strong>" . esc_html($data['count']) . "</strong></td>\n";
        echo "<td><div style='width: 20px; height: 20px; background-color: " . esc_attr($data['color']) . "; display: inline-block; border: 1px solid #ccc;'></div></td>\n";
        echo "</tr>\n";
    }
    
    echo "<tr style='background-color: #f0f0f0; font-weight: bold;'>\n";
    echo "<td colspan='2'>Total</td>\n";
    echo "<td>" . esc_html($total) . "</td>\n";
    echo "<td></td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    
    // Display statistics chart
    echo TPAK_Workflow_Diagram::generate_statistics_chart($statistics);
} else {
    echo "<p class='info'>No survey data found for statistics.</p>\n";
}

echo "</div>\n";

// Test performance metrics
echo "<div class='test-section'>\n";
echo "<h2>Performance Metrics</h2>\n";

$metrics = $workflow->get_performance_metrics();

echo "<table>\n";
echo "<tr><th>Metric</th><th>Value</th></tr>\n";
echo "<tr><td>Average Processing Time</td><td>" . esc_html($metrics['average_processing_time']) . " hours</td></tr>\n";
echo "<tr><td>Completion Rate</td><td>" . esc_html($metrics['completion_rate']) . "%</td></tr>\n";
echo "<tr><td>Total Processed</td><td>" . esc_html($metrics['total_processed']) . "</td></tr>\n";
echo "<tr><td>Total Completed</td><td>" . esc_html($metrics['total_completed']) . "</td></tr>\n";
echo "</table>\n";

echo "<h3>Sampling Statistics</h3>\n";
echo "<table>\n";
echo "<tr><th>Metric</th><th>Value</th></tr>\n";
echo "<tr><td>Total Sampled</td><td>" . esc_html($metrics['sampling_statistics']['total_sampled']) . "</td></tr>\n";
echo "<tr><td>Finalized by Sampling</td><td>" . esc_html($metrics['sampling_statistics']['finalized_by_sampling']) . "</td></tr>\n";
echo "<tr><td>Sent to Examiner</td><td>" . esc_html($metrics['sampling_statistics']['sent_to_examiner']) . "</td></tr>\n";
echo "</table>\n";

echo "</div>\n";

// Test workflow logs
echo "<div class='test-section'>\n";
echo "<h2>Recent Workflow Logs</h2>\n";

$logs = $workflow->get_workflow_logs(array(), 10);

if (!empty($logs)) {
    echo "<table>\n";
    echo "<tr><th>Timestamp</th><th>Post ID</th><th>Action</th><th>User ID</th><th>Notes</th></tr>\n";
    
    foreach ($logs as $log) {
        echo "<tr>\n";
        echo "<td>" . esc_html($log['timestamp']) . "</td>\n";
        echo "<td>" . esc_html($log['post_id']) . "</td>\n";
        echo "<td><code>" . esc_html($log['action']) . "</code></td>\n";
        echo "<td>" . esc_html($log['user_id']) . "</td>\n";
        echo "<td>" . esc_html($log['notes']) . "</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
} else {
    echo "<p class='info'>No workflow logs found.</p>\n";
}

echo "</div>\n";

// Create test data if none exists
$test_posts = get_posts(array(
    'post_type' => 'tpak_survey_data',
    'numberposts' => 1,
    'post_status' => 'publish'
));

if (empty($test_posts)) {
    echo "<div class='test-section'>\n";
    echo "<h2>Create Test Data</h2>\n";
    echo "<p class='info'>No survey data found. You can create test data to see the workflow in action.</p>\n";
    
    if (isset($_POST['create_test_data']) && wp_verify_nonce($_POST['test_nonce'], 'create_test_data')) {
        // Create test posts with different statuses
        $test_statuses = array('pending_a', 'pending_b', 'pending_c', 'rejected_by_b', 'finalized');
        $created_posts = array();
        
        foreach ($test_statuses as $i => $status) {
            $post_id = wp_insert_post(array(
                'post_type' => 'tpak_survey_data',
                'post_title' => "Test Survey Data - $status",
                'post_status' => 'publish',
                'post_date' => date('Y-m-d H:i:s', strtotime("-$i hours"))
            ));
            
            if ($post_id) {
                update_post_meta($post_id, '_tpak_workflow_status', $status);
                update_post_meta($post_id, '_tpak_survey_id', '12345');
                update_post_meta($post_id, '_tpak_response_id', "test_" . str_pad($i + 1, 3, '0', STR_PAD_LEFT));
                update_post_meta($post_id, '_tpak_survey_data', json_encode(array(
                    'Q1' => "Test answer $i",
                    'Q2' => "Another test answer $i"
                )));
                
                if ($status === 'finalized') {
                    update_post_meta($post_id, '_tpak_completion_date', current_time('mysql'));
                }
                
                $created_posts[] = $post_id;
            }
        }
        
        echo "<div class='success'>✓ Created " . count($created_posts) . " test posts with different workflow statuses.</div>\n";
        echo "<p><a href='" . esc_url($_SERVER['REQUEST_URI']) . "'>Refresh page</a> to see updated statistics.</p>\n";
    } else {
        echo "<form method='post'>\n";
        wp_nonce_field('create_test_data', 'test_nonce');
        echo "<input type='submit' name='create_test_data' value='Create Test Data' class='button button-primary'>\n";
        echo "</form>\n";
    }
    
    echo "</div>\n";
}

// Display workflow diagram
echo "<div class='test-section'>\n";
echo "<h2>Workflow Diagram</h2>\n";
echo TPAK_Workflow_Diagram::generate_html_diagram();
echo "</div>\n";

// Test transition validation
if (!empty($test_posts)) {
    echo "<div class='test-section'>\n";
    echo "<h2>Test Transition Validation</h2>\n";
    
    $test_post = $test_posts[0];
    $current_status = get_post_meta($test_post->ID, '_tpak_workflow_status', true);
    
    echo "<p>Testing with Post ID: <strong>" . esc_html($test_post->ID) . "</strong> (Status: <code>" . esc_html($current_status) . "</code>)</p>\n";
    
    // Get current user's available actions
    $available_actions = $workflow->get_available_actions($test_post->ID);
    
    if (!empty($available_actions)) {
        echo "<h3>Available Actions for Current User</h3>\n";
        foreach ($available_actions as $action => $transition) {
            echo "<div class='transition-item'>\n";
            echo "<strong>" . esc_html($transition['label']) . "</strong> (<code>" . esc_html($action) . "</code>)<br>\n";
            echo "<small>To: " . esc_html($transition['to']) . "</small>\n";
            echo "</div>\n";
        }
    } else {
        echo "<p class='info'>No actions available for current user on this post.</p>\n";
    }
    
    // Test all possible transitions
    echo "<h3>Transition Validation Tests</h3>\n";
    $all_transitions = $workflow->get_workflow_transitions();
    
    foreach ($all_transitions as $action => $transition) {
        $validation = $workflow->validate_transition($test_post->ID, $action, get_current_user_id());
        $status_class = is_wp_error($validation) ? 'error' : 'success';
        $status_icon = is_wp_error($validation) ? '✗' : '✓';
        $message = is_wp_error($validation) ? $validation->get_error_message() : 'Valid';
        
        echo "<div class='$status_class'>$status_icon " . esc_html($transition['label']) . " - " . esc_html($message) . "</div>\n";
    }
    
    echo "</div>\n";
}

echo "<h2>Workflow Test Complete</h2>\n";
echo "<p>Review the results above to ensure the workflow engine is functioning correctly.</p>\n";