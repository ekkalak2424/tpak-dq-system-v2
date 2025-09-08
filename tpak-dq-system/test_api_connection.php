<?php
/**
 * API Connection Test Utility
 * 
 * Simple script to test LimeSurvey API connection during development
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Only run if WordPress is loaded and user is admin
if (!defined('ABSPATH') || !current_user_can('manage_options')) {
    exit('Access denied');
}

// Get API handler
$api_handler = TPAK_API_Handler::get_instance();

echo "<h1>TPAK DQ System - API Connection Test</h1>\n";
echo "<style>
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .info { color: #666; }
    .settings { background: #fff; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa; }
    pre { background: #f1f1f1; padding: 10px; overflow-x: auto; }
</style>\n";

// Get current settings
$settings = $api_handler->get_settings();
$status = $api_handler->get_connection_status();

echo "<div class='test-section'>\n";
echo "<h2>Current API Settings</h2>\n";
echo "<div class='settings'>\n";
echo "<strong>LimeSurvey URL:</strong> " . esc_html($settings['limesurvey_url']) . "<br>\n";
echo "<strong>Username:</strong> " . esc_html($settings['username']) . "<br>\n";
echo "<strong>Password:</strong> " . (empty($settings['password']) ? 'Not set' : '***hidden***') . "<br>\n";
echo "<strong>Survey ID:</strong> " . esc_html($settings['survey_id']) . "<br>\n";
echo "<strong>Timeout:</strong> " . esc_html($settings['timeout']) . " seconds<br>\n";
echo "<strong>SSL Verify:</strong> " . ($settings['ssl_verify'] ? 'Yes' : 'No') . "<br>\n";
echo "</div>\n";

echo "<div class='info'>\n";
echo "<strong>Configuration Status:</strong> " . ($status['is_configured'] ? 'Configured' : 'Not Configured') . "<br>\n";
if ($status['last_connection']) {
    echo "<strong>Last Connection:</strong> " . esc_html($status['last_connection']) . "<br>\n";
}
if ($status['last_import']) {
    echo "<strong>Last Import:</strong> " . esc_html($status['last_import']) . "<br>\n";
}
echo "</div>\n";
echo "</div>\n";

// Test connection if configured
if ($status['is_configured']) {
    echo "<div class='test-section'>\n";
    echo "<h2>Testing API Connection</h2>\n";
    
    // Clear any previous errors
    $api_handler->clear_error();
    
    // Test connection
    echo "<p>Attempting to connect to LimeSurvey API...</p>\n";
    $connection_result = $api_handler->connect();
    
    if (is_wp_error($connection_result)) {
        echo "<div class='error'>Connection Failed: " . esc_html($connection_result->get_error_message()) . "</div>\n";
    } else {
        echo "<div class='success'>✓ Connection Successful!</div>\n";
        
        // Test survey access
        echo "<p>Testing survey access...</p>\n";
        $survey_data = $api_handler->get_survey_data($settings['survey_id']);
        
        if (is_wp_error($survey_data)) {
            echo "<div class='error'>Survey Access Failed: " . esc_html($survey_data->get_error_message()) . "</div>\n";
        } else {
            echo "<div class='success'>✓ Survey Access Successful!</div>\n";
            
            // Display survey information
            echo "<h3>Survey Information</h3>\n";
            if (!empty($survey_data['survey_info'])) {
                echo "<div class='settings'>\n";
                foreach ($survey_data['survey_info'] as $key => $value) {
                    if (is_string($value) || is_numeric($value)) {
                        echo "<strong>" . esc_html(ucfirst($key)) . ":</strong> " . esc_html($value) . "<br>\n";
                    }
                }
                echo "</div>\n";
            }
            
            // Display questions
            if (!empty($survey_data['questions'])) {
                echo "<h3>Survey Questions (" . count($survey_data['questions']) . ")</h3>\n";
                echo "<div class='settings'>\n";
                $question_count = 0;
                foreach ($survey_data['questions'] as $question) {
                    $question_count++;
                    if ($question_count > 5) {
                        echo "<em>... and " . (count($survey_data['questions']) - 5) . " more questions</em><br>\n";
                        break;
                    }
                    $qid = isset($question['qid']) ? $question['qid'] : 'N/A';
                    $title = isset($question['title']) ? $question['title'] : (isset($question['question']) ? $question['question'] : 'No title');
                    echo "<strong>Q{$qid}:</strong> " . esc_html($title) . "<br>\n";
                }
                echo "</div>\n";
            }
            
            // Display response count
            echo "<h3>Survey Responses</h3>\n";
            echo "<div class='settings'>\n";
            echo "<strong>Total Responses:</strong> " . esc_html($survey_data['total_responses']) . "<br>\n";
            
            if ($survey_data['total_responses'] > 0) {
                echo "<strong>Sample Response Data:</strong><br>\n";
                $sample_responses = array_slice($survey_data['responses'], 0, 2, true);
                foreach ($sample_responses as $response_id => $response_data) {
                    echo "<em>Response ID: " . esc_html($response_id) . "</em><br>\n";
                    $field_count = 0;
                    foreach ($response_data as $field => $value) {
                        $field_count++;
                        if ($field_count > 3) {
                            echo "&nbsp;&nbsp;<em>... and " . (count($response_data) - 3) . " more fields</em><br>\n";
                            break;
                        }
                        echo "&nbsp;&nbsp;<strong>" . esc_html($field) . ":</strong> " . esc_html(substr($value, 0, 50)) . 
                             (strlen($value) > 50 ? '...' : '') . "<br>\n";
                    }
                    echo "<br>\n";
                }
            }
            echo "</div>\n";
        }
        
        // Disconnect
        echo "<p>Disconnecting from API...</p>\n";
        $disconnect_result = $api_handler->disconnect();
        if ($disconnect_result) {
            echo "<div class='success'>✓ Disconnected Successfully</div>\n";
        } else {
            echo "<div class='error'>Disconnect Warning: " . esc_html($api_handler->get_last_error()) . "</div>\n";
        }
    }
    
    echo "</div>\n";
    
    // Test import functionality
    echo "<div class='test-section'>\n";
    echo "<h2>Testing Data Import</h2>\n";
    
    if (!is_wp_error($connection_result)) {
        echo "<p>Testing import functionality...</p>\n";
        
        // Get last import date for incremental import test
        $last_import = get_option('tpak_dq_last_import');
        if ($last_import) {
            echo "<div class='info'>Last import: " . esc_html($last_import) . "</div>\n";
        }
        
        // Test import (this will actually import data)
        $import_result = $api_handler->import_survey_data($settings['survey_id'], $last_import);
        
        if (is_wp_error($import_result)) {
            echo "<div class='error'>Import Failed: " . esc_html($import_result->get_error_message()) . "</div>\n";
        } else {
            echo "<div class='success'>✓ Import Test Successful!</div>\n";
            echo "<div class='settings'>\n";
            echo "<strong>Imported:</strong> " . esc_html($import_result['imported']) . " responses<br>\n";
            echo "<strong>Skipped:</strong> " . esc_html($import_result['skipped']) . " responses (duplicates)<br>\n";
            echo "<strong>Errors:</strong> " . esc_html($import_result['errors']) . " responses<br>\n";
            echo "<strong>Total Processed:</strong> " . esc_html($import_result['total_responses']) . " responses<br>\n";
            
            if (!empty($import_result['error_messages'])) {
                echo "<strong>Error Messages:</strong><br>\n";
                foreach ($import_result['error_messages'] as $error_msg) {
                    echo "&nbsp;&nbsp;• " . esc_html($error_msg) . "<br>\n";
                }
            }
            echo "</div>\n";
        }
    } else {
        echo "<div class='error'>Cannot test import - connection failed</div>\n";
    }
    
    echo "</div>\n";
    
} else {
    echo "<div class='test-section'>\n";
    echo "<h2>Configuration Required</h2>\n";
    echo "<div class='error'>API is not configured. Please set up your LimeSurvey API settings first.</div>\n";
    echo "<p>Required settings:</p>\n";
    echo "<ul>\n";
    echo "<li>LimeSurvey URL (must end with /admin/remotecontrol)</li>\n";
    echo "<li>Username</li>\n";
    echo "<li>Password</li>\n";
    echo "<li>Survey ID</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
}

// Test validation
echo "<div class='test-section'>\n";
echo "<h2>Testing API Validation</h2>\n";

$validator = TPAK_Validator::get_instance();

// Test URL validation
$test_urls = array(
    'https://survey.example.com/admin/remotecontrol' => true,
    'http://localhost/limesurvey/index.php/admin/remotecontrol' => true,
    'https://example.com' => false,
    'not-a-url' => false,
);

echo "<h3>URL Validation Tests</h3>\n";
foreach ($test_urls as $url => $expected) {
    $validator->clear_errors();
    $result = $validator->validate_limesurvey_url($url);
    $status = ($result === $expected) ? 'success' : 'error';
    $icon = $result ? '✓' : '✗';
    
    echo "<div class='$status'>$icon " . esc_html($url) . " - " . 
         ($result ? 'Valid' : 'Invalid') . "</div>\n";
    
    if ($validator->has_errors()) {
        echo "<div class='info'>&nbsp;&nbsp;Error: " . esc_html($validator->get_error_messages()) . "</div>\n";
    }
}

echo "</div>\n";

echo "<h2>API Connection Test Complete</h2>\n";
echo "<p>Review the results above to ensure your LimeSurvey API integration is working correctly.</p>\n";