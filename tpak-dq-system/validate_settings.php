<?php
/**
 * Validate Settings Implementation
 * 
 * This script validates that all required components for the settings
 * functionality are properly implemented.
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

echo "TPAK DQ System - Settings Implementation Validation\n";
echo str_repeat("=", 60) . "\n\n";

$validation_results = array();

// Check if admin settings class exists
$admin_settings_file = 'admin/class-admin-settings.php';
if (file_exists($admin_settings_file)) {
    $content = file_get_contents($admin_settings_file);
    
    // Check for required methods
    $required_methods = array(
        'get_instance',
        'get_all_settings',
        'get_settings',
        'update_settings',
        'handle_settings_save',
        'render_api_settings_tab',
        'render_cron_settings_tab',
        'render_notifications_settings_tab',
        'render_workflow_settings_tab',
        'ajax_test_api_connection',
        'ajax_manual_import',
        'sanitize_settings'
    );
    
    $validation_results['admin_settings_class'] = true;
    $validation_results['required_methods'] = array();
    
    foreach ($required_methods as $method) {
        if (strpos($content, "function $method") !== false) {
            $validation_results['required_methods'][$method] = true;
        } else {
            $validation_results['required_methods'][$method] = false;
        }
    }
    
    // Check for default settings structure
    if (strpos($content, 'init_default_settings') !== false) {
        $validation_results['default_settings'] = true;
    } else {
        $validation_results['default_settings'] = false;
    }
    
} else {
    $validation_results['admin_settings_class'] = false;
}

// Check if settings page template exists
$settings_page_file = 'admin/pages/settings.php';
if (file_exists($settings_page_file)) {
    $content = file_get_contents($settings_page_file);
    
    // Check for required elements
    $required_elements = array(
        'nav-tab-wrapper',
        'tpak-settings-content',
        'test-api-connection',
        'trigger-manual-import',
        'tpak_settings_nonce'
    );
    
    $validation_results['settings_page'] = true;
    $validation_results['page_elements'] = array();
    
    foreach ($required_elements as $element) {
        if (strpos($content, $element) !== false) {
            $validation_results['page_elements'][$element] = true;
        } else {
            $validation_results['page_elements'][$element] = false;
        }
    }
    
} else {
    $validation_results['settings_page'] = false;
}

// Check if test file exists
$test_file = 'tests/test-admin-settings.php';
if (file_exists($test_file)) {
    $content = file_get_contents($test_file);
    
    // Check for test methods
    $test_methods = array(
        'test_settings_instance',
        'test_default_settings',
        'test_get_settings_section',
        'test_update_settings_section',
        'test_settings_sanitization',
        'test_api_settings_validation',
        'test_cron_settings_validation',
        'test_notification_settings_validation',
        'test_workflow_settings_validation'
    );
    
    $validation_results['test_file'] = true;
    $validation_results['test_methods'] = array();
    
    foreach ($test_methods as $method) {
        if (strpos($content, "function $method") !== false) {
            $validation_results['test_methods'][$method] = true;
        } else {
            $validation_results['test_methods'][$method] = false;
        }
    }
    
} else {
    $validation_results['test_file'] = false;
}

// Check CSS enhancements
$css_file = 'assets/css/admin.css';
if (file_exists($css_file)) {
    $content = file_get_contents($css_file);
    
    // Check for settings-specific styles
    $css_classes = array(
        'tpak-settings-page',
        'tpak-settings-section',
        'tpak-api-test-section',
        'tpak-cron-status-section',
        'tpak-workflow-info-section',
        'tpak-status-active',
        'tpak-status-inactive'
    );
    
    $validation_results['css_file'] = true;
    $validation_results['css_classes'] = array();
    
    foreach ($css_classes as $class) {
        if (strpos($content, ".$class") !== false) {
            $validation_results['css_classes'][$class] = true;
        } else {
            $validation_results['css_classes'][$class] = false;
        }
    }
    
} else {
    $validation_results['css_file'] = false;
}

// Check JavaScript enhancements
$js_file = 'assets/js/admin.js';
if (file_exists($js_file)) {
    $content = file_get_contents($js_file);
    
    // Check for settings-specific functions
    $js_functions = array(
        'initSettings',
        'initApiTesting',
        'initManualImport',
        'initSettingsValidation',
        'initDependentFields',
        'updateWorkflowDiagram'
    );
    
    $validation_results['js_file'] = true;
    $validation_results['js_functions'] = array();
    
    foreach ($js_functions as $function) {
        if (strpos($content, $function) !== false) {
            $validation_results['js_functions'][$function] = true;
        } else {
            $validation_results['js_functions'][$function] = false;
        }
    }
    
} else {
    $validation_results['js_file'] = false;
}

// Display results
echo "VALIDATION RESULTS:\n\n";

// Admin Settings Class
echo "1. Admin Settings Class: ";
if ($validation_results['admin_settings_class']) {
    echo "✓ FOUND\n";
    
    echo "   Required Methods:\n";
    foreach ($validation_results['required_methods'] as $method => $found) {
        echo "   - $method: " . ($found ? "✓" : "✗") . "\n";
    }
    
    echo "   Default Settings: " . ($validation_results['default_settings'] ? "✓" : "✗") . "\n";
} else {
    echo "✗ NOT FOUND\n";
}

echo "\n";

// Settings Page Template
echo "2. Settings Page Template: ";
if ($validation_results['settings_page']) {
    echo "✓ FOUND\n";
    
    echo "   Required Elements:\n";
    foreach ($validation_results['page_elements'] as $element => $found) {
        echo "   - $element: " . ($found ? "✓" : "✗") . "\n";
    }
} else {
    echo "✗ NOT FOUND\n";
}

echo "\n";

// Test File
echo "3. Test File: ";
if ($validation_results['test_file']) {
    echo "✓ FOUND\n";
    
    echo "   Test Methods:\n";
    foreach ($validation_results['test_methods'] as $method => $found) {
        echo "   - $method: " . ($found ? "✓" : "✗") . "\n";
    }
} else {
    echo "✗ NOT FOUND\n";
}

echo "\n";

// CSS File
echo "4. CSS Enhancements: ";
if ($validation_results['css_file']) {
    echo "✓ FOUND\n";
    
    echo "   Settings Styles:\n";
    foreach ($validation_results['css_classes'] as $class => $found) {
        echo "   - $class: " . ($found ? "✓" : "✗") . "\n";
    }
} else {
    echo "✗ NOT FOUND\n";
}

echo "\n";

// JavaScript File
echo "5. JavaScript Enhancements: ";
if ($validation_results['js_file']) {
    echo "✓ FOUND\n";
    
    echo "   Settings Functions:\n";
    foreach ($validation_results['js_functions'] as $function => $found) {
        echo "   - $function: " . ($found ? "✓" : "✗") . "\n";
    }
} else {
    echo "✗ NOT FOUND\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

// Calculate overall completion
$total_checks = 0;
$passed_checks = 0;

foreach ($validation_results as $category => $result) {
    if (is_array($result)) {
        foreach ($result as $item => $status) {
            $total_checks++;
            if ($status) $passed_checks++;
        }
    } else {
        $total_checks++;
        if ($result) $passed_checks++;
    }
}

$completion_percentage = round(($passed_checks / $total_checks) * 100, 1);

echo "OVERALL COMPLETION: $completion_percentage% ($passed_checks/$total_checks)\n";

if ($completion_percentage >= 90) {
    echo "STATUS: ✓ EXCELLENT - Settings implementation is comprehensive\n";
} elseif ($completion_percentage >= 75) {
    echo "STATUS: ✓ GOOD - Settings implementation is mostly complete\n";
} elseif ($completion_percentage >= 50) {
    echo "STATUS: ⚠ FAIR - Settings implementation needs improvement\n";
} else {
    echo "STATUS: ✗ POOR - Settings implementation is incomplete\n";
}

echo str_repeat("=", 60) . "\n";
?>