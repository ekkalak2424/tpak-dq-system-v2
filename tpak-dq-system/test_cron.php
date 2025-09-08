<?php
/**
 * Cron Test Utility
 * 
 * Simple script to test and manage cron functionality during development
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Only run if WordPress is loaded and user is admin
if (!defined('ABSPATH') || !current_user_can('manage_options')) {
    exit('Access denied');
}

// Get cron instance
$cron = TPAK_Cron::get_instance();

echo "<h1>TPAK DQ System - Cron Management</h1>\n";
echo "<style>
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: #666; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .button { display: inline-block; padding: 8px 12px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px; margin: 2px; }
    .button:hover { background: #005a87; }
    .button.secondary { background: #666; }
    .button.danger { background: #dc3232; }
    .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 5px; }
    .status-success { background-color: #46b450; }
    .status-warning { background-color: #ffb900; }
    .status-error { background-color: #dc3232; }
</style>\n";

// Handle actions
if (isset($_POST['action']) && wp_verify_nonce($_POST['cron_nonce'], 'cron_management')) {
    $action = sanitize_text_field($_POST['action']);
    
    switch ($action) {
        case 'schedule':
            $interval = sanitize_text_field($_POST['interval']);
            $survey_id = sanitize_text_field($_POST['survey_id']);
            $result = $cron->schedule_import($interval, $survey_id);
            
            if (is_wp_error($result)) {
                echo "<div class='error'>✗ Failed to schedule: " . esc_html($result->get_error_message()) . "</div>\n";
            } else {
                echo "<div class='success'>✓ Cron job scheduled successfully</div>\n";
            }
            break;
            
        case 'unschedule':
            $cron->unschedule_import();
            echo "<div class='success'>✓ Cron job unscheduled</div>\n";
            break;
            
        case 'manual_import':
            $survey_id = sanitize_text_field($_POST['survey_id']);
            echo "<div class='info'>Running manual import...</div>\n";
            $cron->execute_import($survey_id);
            echo "<div class='success'>✓ Manual import completed (check logs below for details)</div>\n";
            break;
            
        case 'clear_logs':
            $cron->clear_cron_logs();
            echo "<div class='success'>✓ Cron logs cleared</div>\n";
            break;
    }
}

// Get current status
$status = $cron->get_cron_status();
$available_intervals = $cron->get_available_intervals();

// Display current status
echo "<div class='test-section'>\n";
echo "<h2>Current Cron Status</h2>\n";

echo "<table>\n";
echo "<tr><th>Property</th><th>Value</th></tr>\n";

$scheduled_indicator = $status['is_scheduled'] ? 'status-success' : 'status-error';
echo "<tr><td>Scheduled</td><td><span class='status-indicator $scheduled_indicator'></span>" . 
     ($status['is_scheduled'] ? 'Yes' : 'No') . "</td></tr>\n";

if ($status['is_scheduled']) {
    echo "<tr><td>Next Run</td><td>" . esc_html($status['next_run']) . "</td></tr>\n";
    echo "<tr><td>Interval</td><td>" . esc_html($status['interval']) . "</td></tr>\n";
    echo "<tr><td>Survey ID</td><td>" . esc_html($status['survey_id']) . "</td></tr>\n";
}

if ($status['last_scheduled']) {
    echo "<tr><td>Last Scheduled</td><td>" . esc_html($status['last_scheduled']) . "</td></tr>\n";
}

$enabled_indicator = $status['enabled'] ? 'status-success' : 'status-error';
echo "<tr><td>Enabled</td><td><span class='status-indicator $enabled_indicator'></span>" . 
     ($status['enabled'] ? 'Yes' : 'No') . "</td></tr>\n";

if (!$status['enabled'] && $status['disabled_reason']) {
    echo "<tr><td>Disabled Reason</td><td class='error'>" . esc_html($status['disabled_reason']) . "</td></tr>\n";
}

if ($status['failure_count'] > 0) {
    echo "<tr><td>Consecutive Failures</td><td class='warning'>" . esc_html($status['failure_count']) . "</td></tr>\n";
}

if ($status['wp_cron_disabled']) {
    echo "<tr><td>WP-Cron Status</td><td class='warning'>Disabled (DISABLE_WP_CRON is true)</td></tr>\n";
} else {
    echo "<tr><td>WP-Cron Status</td><td class='success'>Enabled</td></tr>\n";
}

echo "</table>\n";
echo "</div>\n";

// Display import statistics
if (!empty($status['statistics'])) {
    echo "<div class='test-section'>\n";
    echo "<h2>Import Statistics</h2>\n";
    
    $stats = $status['statistics'];
    
    echo "<table>\n";
    echo "<tr><th>Metric</th><th>Value</th></tr>\n";
    echo "<tr><td>Total Runs</td><td>" . esc_html($stats['total_runs']) . "</td></tr>\n";
    echo "<tr><td>Total Imported</td><td>" . esc_html($stats['total_imported']) . "</td></tr>\n";
    echo "<tr><td>Total Skipped</td><td>" . esc_html($stats['total_skipped']) . "</td></tr>\n";
    echo "<tr><td>Total Errors</td><td>" . esc_html($stats['total_errors']) . "</td></tr>\n";
    echo "<tr><td>Average Execution Time</td><td>" . esc_html($stats['average_execution_time']) . " seconds</td></tr>\n";
    echo "<tr><td>Total Execution Time</td><td>" . esc_html($stats['total_execution_time']) . " seconds</td></tr>\n";
    if ($stats['last_run']) {
        echo "<tr><td>Last Run</td><td>" . esc_html($stats['last_run']) . "</td></tr>\n";
    }
    echo "</table>\n";
    
    echo "</div>\n";
}

// Cron management actions
echo "<div class='test-section'>\n";
echo "<h2>Cron Management</h2>\n";

echo "<form method='post' style='margin-bottom: 20px;'>\n";
wp_nonce_field('cron_management', 'cron_nonce');

echo "<h3>Schedule Import Job</h3>\n";
echo "<table>\n";
echo "<tr>\n";
echo "<td><label for='interval'>Interval:</label></td>\n";
echo "<td><select name='interval' id='interval'>\n";
foreach ($available_intervals as $interval => $data) {
    $selected = ($status['interval'] === $interval) ? 'selected' : '';
    echo "<option value='" . esc_attr($interval) . "' $selected>" . esc_html($data['label']) . "</option>\n";
}
echo "</select></td>\n";
echo "</tr>\n";

echo "<tr>\n";
echo "<td><label for='survey_id'>Survey ID:</label></td>\n";
echo "<td><input type='text' name='survey_id' id='survey_id' value='" . esc_attr($status['survey_id']) . "' required></td>\n";
echo "</tr>\n";
echo "</table>\n";

echo "<input type='hidden' name='action' value='schedule'>\n";
echo "<input type='submit' value='Schedule Import Job' class='button'>\n";

if ($status['is_scheduled']) {
    echo "<input type='hidden' name='action' value='unschedule'>\n";
    echo "<input type='submit' value='Unschedule Job' class='button danger' onclick='this.form.action.value=\"unschedule\"; return confirm(\"Are you sure you want to unschedule the import job?\");'>\n";
}

echo "</form>\n";

echo "<h3>Manual Actions</h3>\n";
echo "<form method='post' style='display: inline-block; margin-right: 10px;'>\n";
wp_nonce_field('cron_management', 'cron_nonce');
echo "<input type='hidden' name='action' value='manual_import'>\n";
echo "<input type='hidden' name='survey_id' value='" . esc_attr($status['survey_id']) . "'>\n";
echo "<input type='submit' value='Run Manual Import' class='button secondary' onclick='return confirm(\"This will run an import now. Continue?\");'>\n";
echo "</form>\n";

echo "<form method='post' style='display: inline-block;'>\n";
wp_nonce_field('cron_management', 'cron_nonce');
echo "<input type='hidden' name='action' value='clear_logs'>\n";
echo "<input type='submit' value='Clear Logs' class='button secondary' onclick='return confirm(\"This will clear all cron logs. Continue?\");'>\n";
echo "</form>\n";

echo "</div>\n";

// Test cron functionality
echo "<div class='test-section'>\n";
echo "<h2>Cron Functionality Test</h2>\n";

$test_results = $cron->test_cron_functionality();

foreach ($test_results as $test_name => $result) {
    $status_class = $result['status'];
    $icon = '';
    
    switch ($result['status']) {
        case 'success':
            $icon = '✓';
            break;
        case 'warning':
            $icon = '⚠';
            break;
        case 'error':
            $icon = '✗';
            break;
    }
    
    echo "<div class='$status_class'>$icon " . esc_html(ucwords(str_replace('_', ' ', $test_name))) . ": " . esc_html($result['message']) . "</div>\n";
}

echo "</div>\n";

// Display recent cron logs
echo "<div class='test-section'>\n";
echo "<h2>Recent Cron Logs</h2>\n";

$logs = $cron->get_cron_logs(array(), 20);

if (!empty($logs)) {
    echo "<table>\n";
    echo "<tr><th>Timestamp</th><th>Event</th><th>Details</th></tr>\n";
    
    foreach ($logs as $log) {
        echo "<tr>\n";
        echo "<td>" . esc_html($log['timestamp']) . "</td>\n";
        echo "<td><code>" . esc_html($log['event']) . "</code></td>\n";
        echo "<td>\n";
        
        if (!empty($log['data'])) {
            foreach ($log['data'] as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                echo "<strong>" . esc_html($key) . ":</strong> " . esc_html($value) . "<br>\n";
            }
        }
        
        echo "</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
} else {
    echo "<p class='info'>No cron logs found.</p>\n";
}

echo "</div>\n";

// Display available intervals
echo "<div class='test-section'>\n";
echo "<h2>Available Cron Intervals</h2>\n";

echo "<table>\n";
echo "<tr><th>Interval</th><th>Label</th><th>Frequency</th><th>Seconds</th></tr>\n";

foreach ($available_intervals as $interval => $data) {
    $hours = round($data['interval'] / HOUR_IN_SECONDS, 2);
    echo "<tr>\n";
    echo "<td><code>" . esc_html($interval) . "</code></td>\n";
    echo "<td>" . esc_html($data['label']) . "</td>\n";
    echo "<td>" . esc_html($data['display']) . "</td>\n";
    echo "<td>" . esc_html($data['interval']) . " (" . esc_html($hours) . " hours)</td>\n";
    echo "</tr>\n";
}

echo "</table>\n";
echo "</div>\n";

// Display WordPress cron events (for debugging)
echo "<div class='test-section'>\n";
echo "<h2>WordPress Cron Events</h2>\n";

$cron_events = _get_cron_array();
$our_events = array();

foreach ($cron_events as $timestamp => $events) {
    foreach ($events as $hook => $event_data) {
        if ($hook === TPAK_Cron::CRON_HOOK) {
            $our_events[] = array(
                'timestamp' => $timestamp,
                'hook' => $hook,
                'args' => $event_data
            );
        }
    }
}

if (!empty($our_events)) {
    echo "<table>\n";
    echo "<tr><th>Scheduled Time</th><th>Hook</th><th>Arguments</th></tr>\n";
    
    foreach ($our_events as $event) {
        echo "<tr>\n";
        echo "<td>" . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $event['timestamp'])) . "</td>\n";
        echo "<td><code>" . esc_html($event['hook']) . "</code></td>\n";
        echo "<td>" . esc_html(json_encode($event['args'])) . "</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
} else {
    echo "<p class='info'>No TPAK cron events currently scheduled.</p>\n";
}

echo "</div>\n";

echo "<h2>Cron Management Complete</h2>\n";
echo "<p>Use the tools above to manage and monitor your automated import jobs.</p>\n";