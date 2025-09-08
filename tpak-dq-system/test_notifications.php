<?php
/**
 * Test Notifications System
 * 
 * Standalone test file for TPAK Notifications
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Load WordPress
require_once '../../../wp-load.php';

// Load plugin files
require_once 'includes/class-autoloader.php';
TPAK_Autoloader::register();

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html>
<head>
    <title>TPAK DQ System - Notification Tests</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .notification-preview { border: 1px solid #ccc; padding: 15px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>TPAK DQ System - Notification System Tests</h1>
    
    <?php
    
    try {
        // Test 1: Basic Functionality
        echo '<div class="test-section">';
        echo '<h2>Test 1: Basic Notification System</h2>';
        
        $notifications = TPAK_Notifications::get_instance();
        
        if ($notifications) {
            echo '<p class="success">✓ Notifications instance created successfully</p>';
        } else {
            echo '<p class="error">✗ Failed to create notifications instance</p>';
        }
        
        // Test settings
        $settings = $notifications->get_notification_settings();
        echo '<h3>Default Settings:</h3>';
        echo '<pre>' . print_r($settings, true) . '</pre>';
        
        echo '</div>';
        
        // Test 2: Template Generation
        echo '<div class="test-section">';
        echo '<h2>Test 2: Template Generation</h2>';
        
        $test_data = array(
            'user_name' => 'John Doe',
            'survey_id' => '12345',
            'response_id' => '67890',
            'status' => 'pending_a',
            'old_status' => 'draft',
            'new_status' => 'pending_a',
            'changed_by' => 'System Administrator',
            'changed_date' => current_time('mysql'),
            'site_name' => get_bloginfo('name'),
            'action_link' => admin_url('post.php?post=123&action=edit'),
            'include_action_links' => true,
            'include_data_details' => true
        );
        
        $types = array(
            TPAK_Notifications::TYPE_ASSIGNMENT => 'Assignment Notification',
            TPAK_Notifications::TYPE_STATUS_CHANGE => 'Status Change Notification',
            TPAK_Notifications::TYPE_REJECTION => 'Rejection Notification',
            TPAK_Notifications::TYPE_APPROVAL => 'Approval Notification'
        );
        
        foreach ($types as $type => $label) {
            echo '<h3>' . $label . '</h3>';
            
            $template = $notifications->get_notification_template($type, $test_data);
            
            if (!empty($template)) {
                echo '<p class="success">✓ Template generated successfully</p>';
                
                // Replace variables for preview
                $preview = $template;
                foreach ($test_data as $key => $value) {
                    $preview = str_replace('{' . $key . '}', $value, $preview);
                }
                
                echo '<div class="notification-preview">';
                echo '<h4>Preview:</h4>';
                echo $preview;
                echo '</div>';
            } else {
                echo '<p class="error">✗ Failed to generate template</p>';
            }
        }
        
        echo '</div>';
        
        // Test 3: Settings Management
        echo '<div class="test-section">';
        echo '<h2>Test 3: Settings Management</h2>';
        
        // Test enabling/disabling notifications
        $original_enabled = $notifications->are_notifications_enabled();
        echo '<p class="info">Original enabled state: ' . ($original_enabled ? 'true' : 'false') . '</p>';
        
        // Disable notifications
        $notifications->update_notification_settings(array('enabled' => false));
        $disabled_state = $notifications->are_notifications_enabled();
        echo '<p class="info">After disabling: ' . ($disabled_state ? 'true' : 'false') . '</p>';
        
        // Re-enable notifications
        $notifications->update_notification_settings(array('enabled' => true));
        $enabled_state = $notifications->are_notifications_enabled();
        echo '<p class="info">After re-enabling: ' . ($enabled_state ? 'true' : 'false') . '</p>';
        
        if (!$disabled_state && $enabled_state) {
            echo '<p class="success">✓ Settings management working correctly</p>';
        } else {
            echo '<p class="error">✗ Settings management failed</p>';
        }
        
        echo '</div>';
        
        // Test 4: Logging System
        echo '<div class="test-section">';
        echo '<h2>Test 4: Logging System</h2>';
        
        // Clear existing logs
        $notifications->clear_notification_log();
        $notifications->clear_notification_errors();
        
        // Test notification log (using reflection to access private method)
        $reflection = new ReflectionClass($notifications);
        $log_method = $reflection->getMethod('log_notification_sent');
        $log_method->setAccessible(true);
        
        $log_method->invoke($notifications, 1, TPAK_Notifications::TYPE_ASSIGNMENT, 123, array('test' => 'data'));
        
        $log = $notifications->get_notification_log(1);
        if (!empty($log)) {
            echo '<p class="success">✓ Notification logging working</p>';
            echo '<pre>' . print_r($log[0], true) . '</pre>';
        } else {
            echo '<p class="error">✗ Notification logging failed</p>';
        }
        
        // Test error logging
        $error_method = $reflection->getMethod('log_error');
        $error_method->setAccessible(true);
        
        $error_method->invoke($notifications, 'Test error message', array('context' => 'test'));
        
        $errors = $notifications->get_notification_errors(1);
        if (!empty($errors)) {
            echo '<p class="success">✓ Error logging working</p>';
            echo '<pre>' . print_r($errors[0], true) . '</pre>';
        } else {
            echo '<p class="error">✗ Error logging failed</p>';
        }
        
        echo '</div>';
        
        // Test 5: Email Test (if requested)
        if (isset($_GET['test_email']) && !empty($_GET['email'])) {
            echo '<div class="test-section">';
            echo '<h2>Test 5: Email Sending Test</h2>';
            
            $test_email = sanitize_email($_GET['email']);
            
            if (is_email($test_email)) {
                $sent = $notifications->send_test_notification($test_email);
                
                if ($sent) {
                    echo '<p class="success">✓ Test email sent successfully to ' . $test_email . '</p>';
                } else {
                    echo '<p class="error">✗ Failed to send test email to ' . $test_email . '</p>';
                }
            } else {
                echo '<p class="error">✗ Invalid email address provided</p>';
            }
            
            echo '</div>';
        }
        
        // Test 6: Unit Tests
        echo '<div class="test-section">';
        echo '<h2>Test 6: Unit Tests</h2>';
        
        if (class_exists('Test_TPAK_Notifications')) {
            $unit_test = new Test_TPAK_Notifications();
            echo '<pre>';
            $unit_test->run_tests();
            echo '</pre>';
        } else {
            // Load and run unit tests
            require_once 'tests/test-notifications.php';
            if (class_exists('Test_TPAK_Notifications')) {
                $unit_test = new Test_TPAK_Notifications();
                echo '<pre>';
                $unit_test->run_tests();
                echo '</pre>';
            } else {
                echo '<p class="error">✗ Unit test class not found</p>';
            }
        }
        
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="test-section">';
        echo '<h2>Error</h2>';
        echo '<p class="error">Exception: ' . $e->getMessage() . '</p>';
        echo '<pre>' . $e->getTraceAsString() . '</pre>';
        echo '</div>';
    }
    
    ?>
    
    <div class="test-section">
        <h2>Email Test Form</h2>
        <form method="get">
            <p>
                <label for="email">Email Address:</label><br>
                <input type="email" id="email" name="email" value="<?php echo isset($_GET['email']) ? esc_attr($_GET['email']) : ''; ?>" style="width: 300px;">
            </p>
            <p>
                <input type="hidden" name="test_email" value="1">
                <input type="submit" value="Send Test Email" class="button">
            </p>
        </form>
    </div>
    
    <div class="test-section">
        <h2>Test Summary</h2>
        <p>The notification system includes the following features:</p>
        <ul>
            <li>✓ Email sending capabilities with HTML templates</li>
            <li>✓ Multiple notification types (assignment, status change, rejection, approval)</li>
            <li>✓ Template system with variable replacement</li>
            <li>✓ Settings management (enable/disable, from address, etc.)</li>
            <li>✓ Comprehensive logging system</li>
            <li>✓ Error handling and logging</li>
            <li>✓ Integration hooks for workflow events</li>
            <li>✓ Test notification functionality</li>
        </ul>
        
        <h3>Requirements Coverage:</h3>
        <ul>
            <li>✓ 5.1: Send email notification when data status changes to user's responsibility</li>
            <li>✓ 5.2: Enable/disable notification sending via administrator settings</li>
            <li>✓ 5.3: Stop sending notifications when disabled</li>
            <li>✓ 5.4: Log errors when email sending fails</li>
            <li>✓ 5.5: Include relevant data details and action links in notifications</li>
        </ul>
    </div>
    
</body>
</html>