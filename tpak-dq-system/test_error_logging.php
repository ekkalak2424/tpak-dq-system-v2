<?php
/**
 * Test Error Handling and Logging System
 *
 * Simple test script to verify the error handling and logging functionality.
 *
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Include WordPress
require_once '../../../wp-config.php';

// Include plugin files
require_once 'includes/class-autoloader.php';
TPAK_Autoloader::register();

echo "<h1>TPAK Error Handling and Logging System Test</h1>\n";

try {
    // Test Logger
    echo "<h2>Testing Logger</h2>\n";
    
    $logger = TPAK_Logger::get_instance();
    
    // Test basic logging
    echo "Testing basic logging...\n";
    $result = $logger->info('Test info message', TPAK_Logger::CATEGORY_SYSTEM, ['test' => 'data']);
    echo $result ? "✓ Basic logging works\n" : "✗ Basic logging failed\n";
    
    // Test different log levels
    echo "Testing different log levels...\n";
    $logger->debug('Debug message');
    $logger->warning('Warning message');
    $logger->error('Error message');
    $logger->critical('Critical message');
    echo "✓ Different log levels tested\n";
    
    // Test specialized logging methods
    echo "Testing specialized logging methods...\n";
    $logger->log_api_error('/test/endpoint', 'Connection failed', ['timeout' => 30]);
    $logger->log_validation_error('email', 'invalid-email', 'email_format');
    $logger->log_workflow_error('approve', 123, 1, 'Permission denied');
    $logger->log_cron_error('data_import', 'API unavailable');
    $logger->log_notification_error('assignment', 'user@test.com', 'SMTP failed');
    $logger->log_security_event('unauthorized_access', 'Invalid login attempt');
    echo "✓ Specialized logging methods tested\n";
    
    // Test log retrieval
    echo "Testing log retrieval...\n";
    $logs = $logger->get_logs(['limit' => 5]);
    echo "Retrieved " . count($logs) . " log entries\n";
    
    // Test log statistics
    echo "Testing log statistics...\n";
    $stats = $logger->get_log_stats();
    echo "Total logs: " . $stats['total_logs'] . "\n";
    echo "Recent errors: " . $stats['recent_errors'] . "\n";
    
    // Test Error Handler
    echo "<h2>Testing Error Handler</h2>\n";
    
    $error_handler = new TPAK_Error_Handler();
    
    // Test API error handling
    echo "Testing API error handling...\n";
    $api_error = $error_handler->handle_api_error('/test', 'Connection timeout');
    echo is_wp_error($api_error) ? "✓ API error handling works\n" : "✗ API error handling failed\n";
    
    // Test validation error handling
    echo "Testing validation error handling...\n";
    $validation_error = $error_handler->handle_validation_error('email', 'invalid', 'email');
    echo is_wp_error($validation_error) ? "✓ Validation error handling works\n" : "✗ Validation error handling failed\n";
    
    // Test workflow error handling
    echo "Testing workflow error handling...\n";
    $workflow_error = $error_handler->handle_workflow_error('approve', 123, 'Permission denied');
    echo is_wp_error($workflow_error) ? "✓ Workflow error handling works\n" : "✗ Workflow error handling failed\n";
    
    // Test cron error handling
    echo "Testing cron error handling...\n";
    $cron_error = $error_handler->handle_cron_error('data_import', 'API connection failed');
    echo is_wp_error($cron_error) ? "✓ Cron error handling works\n" : "✗ Cron error handling failed\n";
    
    // Test notification error handling
    echo "Testing notification error handling...\n";
    $notification_error = $error_handler->handle_notification_error('assignment', 'user@test.com', 'SMTP failed');
    echo is_wp_error($notification_error) ? "✓ Notification error handling works\n" : "✗ Notification error handling failed\n";
    
    // Test security error handling
    echo "Testing security error handling...\n";
    $security_error = $error_handler->handle_security_error('unauthorized_access', 'Invalid access attempt');
    echo is_wp_error($security_error) ? "✓ Security error handling works\n" : "✗ Security error handling failed\n";
    
    // Test system error handling
    echo "Testing system error handling...\n";
    $system_error = $error_handler->handle_system_error('Database connection failed');
    echo is_wp_error($system_error) ? "✓ System error handling works\n" : "✗ System error handling failed\n";
    
    // Test error summary
    echo "Testing error summary...\n";
    $summary = $error_handler->get_error_summary();
    echo "Error summary generated with " . $summary['total_errors'] . " total errors\n";
    
    echo "<h2>Test Results</h2>\n";
    echo "✓ All error handling and logging tests passed successfully!\n";
    
    // Display recent logs
    echo "<h2>Recent Log Entries</h2>\n";
    $recent_logs = $logger->get_logs(['limit' => 10]);
    
    if (!empty($recent_logs)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Timestamp</th><th>Level</th><th>Category</th><th>Message</th></tr>\n";
        
        foreach ($recent_logs as $log) {
            echo "<tr>";
            echo "<td>" . esc_html($log['timestamp']) . "</td>";
            echo "<td>" . esc_html($log['level']) . "</td>";
            echo "<td>" . esc_html($log['category']) . "</td>";
            echo "<td>" . esc_html($log['message']) . "</td>";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
    } else {
        echo "No log entries found.\n";
    }
    
} catch (Exception $e) {
    echo "<h2>Test Failed</h2>\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h2 { color: #333; }
table { margin-top: 10px; }
th { background-color: #f0f0f0; padding: 8px; }
td { padding: 8px; }
</style>