<?php
/**
 * Test Meta Boxes Functionality
 * 
 * Simple test to verify meta boxes are working correctly
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Load WordPress
require_once dirname(dirname(dirname(__FILE__))) . '/wp-load.php';

// Check if plugin is active
if (!class_exists('TPAK_Meta_Boxes')) {
    die('TPAK DQ System plugin is not active.');
}

echo "<h1>TPAK Meta Boxes Test</h1>\n";

// Test 1: Meta Boxes Instance
echo "<h2>Test 1: Meta Boxes Instance</h2>\n";
$meta_boxes = TPAK_Meta_Boxes::get_instance();
if ($meta_boxes instanceof TPAK_Meta_Boxes) {
    echo "<span style='color: green;'>✓</span> Meta boxes instance created successfully<br>\n";
} else {
    echo "<span style='color: red;'>✗</span> Failed to create meta boxes instance<br>\n";
}

// Test 2: Check if hooks are registered
echo "<h2>Test 2: Hook Registration</h2>\n";
$hooks_to_check = array(
    'add_meta_boxes' => 'add_meta_boxes',
    'save_post' => 'save_meta_boxes',
    'admin_enqueue_scripts' => 'enqueue_scripts'
);

foreach ($hooks_to_check as $hook => $method) {
    if (has_action($hook)) {
        echo "<span style='color: green;'>✓</span> Hook '{$hook}' is registered<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Hook '{$hook}' is not registered<br>\n";
    }
}

// Test 3: Create test survey data
echo "<h2>Test 3: Test Survey Data Creation</h2>\n";
$test_post_id = wp_insert_post(array(
    'post_type' => 'tpak_survey_data',
    'post_title' => 'Test Survey Data for Meta Boxes',
    'post_status' => 'publish',
    'meta_input' => array(
        '_tpak_survey_id' => 'TEST123',
        '_tpak_response_id' => 'RESP456',
        '_tpak_survey_data' => wp_json_encode(array(
            'question1' => 'Test Answer 1',
            'question2' => 'Test Answer 2'
        )),
        '_tpak_workflow_status' => 'pending_a',
        '_tpak_assigned_user' => 1,
        '_tpak_audit_trail' => wp_json_encode(array(
            array(
                'timestamp' => current_time('mysql'),
                'user_id' => 1,
                'action' => 'imported',
                'old_value' => null,
                'new_value' => null,
                'notes' => 'Test data created'
            )
        )),
        '_tpak_import_date' => current_time('mysql')
    )
));

if ($test_post_id && !is_wp_error($test_post_id)) {
    echo "<span style='color: green;'>✓</span> Test survey data created (ID: {$test_post_id})<br>\n";
    
    // Test 4: Meta box rendering
    echo "<h2>Test 4: Meta Box Rendering</h2>\n";
    
    $post = get_post($test_post_id);
    
    // Test survey data meta box
    ob_start();
    $meta_boxes->render_survey_data_meta_box($post);
    $survey_output = ob_get_clean();
    
    if (strpos($survey_output, 'tpak_survey_id') !== false && 
        strpos($survey_output, 'TEST123') !== false) {
        echo "<span style='color: green;'>✓</span> Survey data meta box renders correctly<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Survey data meta box rendering failed<br>\n";
    }
    
    // Test workflow meta box
    ob_start();
    $meta_boxes->render_workflow_meta_box($post);
    $workflow_output = ob_get_clean();
    
    if (strpos($workflow_output, 'tpak-status-badge') !== false && 
        strpos($workflow_output, 'pending_a') !== false) {
        echo "<span style='color: green;'>✓</span> Workflow meta box renders correctly<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Workflow meta box rendering failed<br>\n";
    }
    
    // Test audit trail meta box
    ob_start();
    $meta_boxes->render_audit_trail_meta_box($post);
    $audit_output = ob_get_clean();
    
    if (strpos($audit_output, 'tpak-audit-entry') !== false && 
        strpos($audit_output, 'imported') !== false) {
        echo "<span style='color: green;'>✓</span> Audit trail meta box renders correctly<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Audit trail meta box rendering failed<br>\n";
    }
    
    // Test 5: Survey Data Model Integration
    echo "<h2>Test 5: Survey Data Model Integration</h2>\n";
    
    $survey_data = new TPAK_Survey_Data($test_post_id);
    
    if ($survey_data->get_survey_id() === 'TEST123') {
        echo "<span style='color: green;'>✓</span> Survey data model loads correctly<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Survey data model loading failed<br>\n";
    }
    
    if ($survey_data->get_status() === 'pending_a') {
        echo "<span style='color: green;'>✓</span> Workflow status loads correctly<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Workflow status loading failed<br>\n";
    }
    
    $audit_trail = $survey_data->get_formatted_audit_trail();
    if (!empty($audit_trail) && $audit_trail[0]['action'] === 'imported') {
        echo "<span style='color: green;'>✓</span> Audit trail loads correctly<br>\n";
    } else {
        echo "<span style='color: red;'>✗</span> Audit trail loading failed<br>\n";
    }
    
    // Clean up
    wp_delete_post($test_post_id, true);
    echo "<br><em>Test data cleaned up</em><br>\n";
    
} else {
    echo "<span style='color: red;'>✗</span> Failed to create test survey data<br>\n";
}

// Test 6: Asset Files
echo "<h2>Test 6: Asset Files</h2>\n";

$js_file = plugin_dir_path(__FILE__) . 'assets/js/meta-boxes.js';
if (file_exists($js_file)) {
    echo "<span style='color: green;'>✓</span> JavaScript file exists<br>\n";
} else {
    echo "<span style='color: red;'>✗</span> JavaScript file missing<br>\n";
}

$css_file = plugin_dir_path(__FILE__) . 'assets/css/meta-boxes.css';
if (file_exists($css_file)) {
    echo "<span style='color: green;'>✓</span> CSS file exists<br>\n";
} else {
    echo "<span style='color: red;'>✗</span> CSS file missing<br>\n";
}

echo "<h2>Test Complete</h2>\n";
echo "<p>Meta boxes functionality has been tested. Check the results above for any issues.</p>\n";
echo "<p><strong>Note:</strong> To fully test the workflow actions, you need to access the WordPress admin interface and edit a survey data post.</p>\n";
?>