<?php
/**
 * Validate Meta Boxes Implementation
 * 
 * Simple validation script to check meta boxes functionality
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(__FILE__))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die('WordPress not found. Please run this from the WordPress directory.');
}

// Check if plugin is active
if (!class_exists('TPAK_Meta_Boxes')) {
    die('TPAK DQ System plugin is not active.');
}

echo "<h1>TPAK Meta Boxes Validation</h1>\n";

// Validation 1: Class exists and can be instantiated
echo "<h2>1. Class Instantiation</h2>\n";
try {
    $meta_boxes = TPAK_Meta_Boxes::get_instance();
    if ($meta_boxes instanceof TPAK_Meta_Boxes) {
        echo "<span style='color: green;'>✓</span> TPAK_Meta_Boxes class instantiated successfully<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Failed to instantiate TPAK_Meta_Boxes<br>\n";
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>✗</span> Exception: " . $e->getMessage() . "<br>\n";
}

// Validation 2: Required methods exist
echo "<h2>2. Required Methods</h2>\n";
$required_methods = array(
    'add_meta_boxes',
    'render_survey_data_meta_box',
    'render_workflow_meta_box',
    'render_audit_trail_meta_box',
    'save_meta_boxes',
    'enqueue_scripts'
);

foreach ($required_methods as $method) {
    if (method_exists($meta_boxes, $method)) {
        echo "<span style='color: green;'>✓</span> Method '{$method}' exists<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Method '{$method}' missing<br>\n";
    }
}

// Validation 3: Asset files exist
echo "<h2>3. Asset Files</h2>\n";
$plugin_dir = plugin_dir_path(__FILE__);

$js_file = $plugin_dir . 'assets/js/meta-boxes.js';
if (file_exists($js_file)) {
    echo "<span style='color: green;'>✓</span> JavaScript file exists<br>\n";
    
    // Check file size
    $js_size = filesize($js_file);
    if ($js_size > 1000) {
        echo "<span style='color: green;'>✓</span> JavaScript file has content ({$js_size} bytes)<br>\n";
    } else {
        echo "<span style='color: orange;'>⚠</span> JavaScript file seems small ({$js_size} bytes)<br>\n";
    }
} else {
    echo "<span style='color: red;'>✗</span> JavaScript file missing<br>\n";
}

$css_file = $plugin_dir . 'assets/css/meta-boxes.css';
if (file_exists($css_file)) {
    echo "<span style='color: green;'>✓</span> CSS file exists<br>\n";
    
    // Check file size
    $css_size = filesize($css_file);
    if ($css_size > 1000) {
        echo "<span style='color: green;'>✓</span> CSS file has content ({$css_size} bytes)<br>\n";
    } else {
        echo "<span style='color: orange;'>⚠</span> CSS file seems small ({$css_size} bytes)<br>\n";
    }
} else {
    echo "<span style='color: red;'>✗</span> CSS file missing<br>\n";
}

// Validation 4: WordPress hooks
echo "<h2>4. WordPress Hooks</h2>\n";
$hooks = array(
    'add_meta_boxes' => 'add_meta_boxes',
    'save_post' => 'save_meta_boxes',
    'admin_enqueue_scripts' => 'enqueue_scripts'
);

foreach ($hooks as $hook => $callback) {
    if (has_action($hook)) {
        echo "<span style='color: green;'>✓</span> Hook '{$hook}' is registered<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Hook '{$hook}' is not registered<br>\n";
    }
}

// Validation 5: Dependencies
echo "<h2>5. Dependencies</h2>\n";
$dependencies = array(
    'TPAK_Survey_Data' => 'Survey Data Model',
    'TPAK_Workflow' => 'Workflow Engine',
    'TPAK_Validator' => 'Validator',
    'TPAK_Post_Types' => 'Post Types'
);

foreach ($dependencies as $class => $name) {
    if (class_exists($class)) {
        echo "<span style='color: green;'>✓</span> {$name} ({$class}) is available<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> {$name} ({$class}) is missing<br>\n";
    }
}

// Validation 6: Test rendering (basic)
echo "<h2>6. Basic Rendering Test</h2>\n";
try {
    // Create a minimal test post
    $test_post = (object) array(
        'ID' => 999999,
        'post_type' => 'tpak_survey_data',
        'post_title' => 'Test Post'
    );
    
    // Mock some meta data
    add_filter('get_post_metadata', function($value, $object_id, $meta_key, $single) {
        if ($object_id == 999999) {
            switch ($meta_key) {
                case '_tpak_survey_id':
                    return $single ? 'TEST123' : array('TEST123');
                case '_tpak_response_id':
                    return $single ? 'RESP456' : array('RESP456');
                case '_tpak_survey_data':
                    return $single ? '{"test": "data"}' : array('{"test": "data"}');
                case '_tpak_workflow_status':
                    return $single ? 'pending_a' : array('pending_a');
                case '_tpak_assigned_user':
                    return $single ? 1 : array(1);
                case '_tpak_audit_trail':
                    return $single ? '[]' : array('[]');
                case '_tpak_import_date':
                    return $single ? current_time('mysql') : array(current_time('mysql'));
            }
        }
        return $value;
    }, 10, 4);
    
    // Test survey data meta box
    ob_start();
    $meta_boxes->render_survey_data_meta_box($test_post);
    $survey_output = ob_get_clean();
    
    if (!empty($survey_output) && strpos($survey_output, 'tpak_survey_id') !== false) {
        echo "<span style='color: green;'>✓</span> Survey data meta box renders<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Survey data meta box rendering failed<br>\n";
    }
    
    // Test workflow meta box
    ob_start();
    $meta_boxes->render_workflow_meta_box($test_post);
    $workflow_output = ob_get_clean();
    
    if (!empty($workflow_output) && strpos($workflow_output, 'Current Status') !== false) {
        echo "<span style='color: green;'>✓</span> Workflow meta box renders<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Workflow meta box rendering failed<br>\n";
    }
    
    // Test audit trail meta box
    ob_start();
    $meta_boxes->render_audit_trail_meta_box($test_post);
    $audit_output = ob_get_clean();
    
    if (!empty($audit_output)) {
        echo "<span style='color: green;'>✓</span> Audit trail meta box renders<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Audit trail meta box rendering failed<br>\n";
    }
    
} catch (Exception $e) {
    echo "<span style='color: red;'>✗</span> Rendering test failed: " . $e->getMessage() . "<br>\n";
}

echo "<h2>Validation Complete</h2>\n";
echo "<p>Meta boxes implementation has been validated. Check the results above for any issues.</p>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>Test the meta boxes in the WordPress admin by editing a survey data post</li>\n";
echo "<li>Verify workflow actions work correctly</li>\n";
echo "<li>Test data validation and saving</li>\n";
echo "<li>Check JavaScript functionality in the browser</li>\n";
echo "</ul>\n";
?>