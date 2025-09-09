<?php
/**
 * Test Data Management Interface
 * 
 * Simple test script to verify data management functionality
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Check if user is admin
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to run this test.');
}

// Load plugin
if (!class_exists('TPAK_DQ_System')) {
    require_once dirname(__FILE__) . '/tpak-dq-system.php';
}

echo "<h1>TPAK Data Management Interface Test</h1>\n";

// Test 1: Check if classes are loaded
echo "<h2>1. Class Loading Test</h2>\n";
$classes_to_check = array(
    'TPAK_Admin_Data',
    'TPAK_Post_Types',
    'TPAK_Roles',
    'TPAK_Survey_Data'
);

foreach ($classes_to_check as $class) {
    if (class_exists($class)) {
        echo "✓ {$class} loaded successfully<br>\n";
    } else {
        echo "✗ {$class} failed to load<br>\n";
    }
}

// Test 2: Initialize admin data instance
echo "<h2>2. Admin Data Instance Test</h2>\n";
try {
    $admin_data = TPAK_Admin_Data::get_instance();
    if ($admin_data instanceof TPAK_Admin_Data) {
        echo "✓ TPAK_Admin_Data instance created successfully<br>\n";
    } else {
        echo "✗ Failed to create TPAK_Admin_Data instance<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Exception creating TPAK_Admin_Data: " . $e->getMessage() . "<br>\n";
}

// Test 3: Check post type registration
echo "<h2>3. Post Type Registration Test</h2>\n";
$post_types = TPAK_Post_Types::get_instance();
if (post_type_exists('tpak_survey_data')) {
    echo "✓ tpak_survey_data post type registered<br>\n";
    
    // Check meta fields
    $meta_fields = $post_types->get_meta_fields();
    echo "✓ " . count($meta_fields) . " meta fields defined<br>\n";
} else {
    echo "✗ tpak_survey_data post type not registered<br>\n";
}

// Test 4: Check roles and capabilities
echo "<h2>4. Roles and Capabilities Test</h2>\n";
$roles = TPAK_Roles::get_instance();
$tpak_roles = $roles->get_tpak_roles();

foreach ($tpak_roles as $role_name => $role_label) {
    $role = get_role($role_name);
    if ($role) {
        echo "✓ Role '{$role_label}' exists<br>\n";
    } else {
        echo "✗ Role '{$role_label}' not found<br>\n";
    }
}

// Test 5: Check available statuses
echo "<h2>5. Available Statuses Test</h2>\n";
try {
    $available_statuses = $admin_data->get_available_statuses();
    echo "✓ Available statuses for current user: " . count($available_statuses) . "<br>\n";
    foreach ($available_statuses as $status => $label) {
        echo "&nbsp;&nbsp;- {$status}: {$label}<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Exception getting available statuses: " . $e->getMessage() . "<br>\n";
}

// Test 6: Check bulk actions
echo "<h2>6. Bulk Actions Test</h2>\n";
try {
    $bulk_actions = $admin_data->get_bulk_actions();
    echo "✓ Available bulk actions for current user: " . count($bulk_actions) . "<br>\n";
    foreach ($bulk_actions as $action => $label) {
        echo "&nbsp;&nbsp;- {$action}: {$label}<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Exception getting bulk actions: " . $e->getMessage() . "<br>\n";
}

// Test 7: Test data list retrieval
echo "<h2>7. Data List Retrieval Test</h2>\n";
try {
    $data_list = $admin_data->get_data_list(array('per_page' => 5));
    
    if (is_array($data_list)) {
        echo "✓ Data list retrieved successfully<br>\n";
        echo "&nbsp;&nbsp;- Total items: " . $data_list['total'] . "<br>\n";
        echo "&nbsp;&nbsp;- Current page: " . $data_list['current_page'] . "<br>\n";
        echo "&nbsp;&nbsp;- Total pages: " . $data_list['total_pages'] . "<br>\n";
        echo "&nbsp;&nbsp;- Items on this page: " . count($data_list['data']) . "<br>\n";
        
        if (!empty($data_list['data'])) {
            $first_item = $data_list['data'][0];
            echo "&nbsp;&nbsp;- Sample item fields: " . implode(', ', array_keys($first_item)) . "<br>\n";
        }
    } else {
        echo "✗ Data list retrieval failed<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Exception retrieving data list: " . $e->getMessage() . "<br>\n";
}

// Test 8: Test user statistics
echo "<h2>8. User Statistics Test</h2>\n";
try {
    $stats = $admin_data->get_user_statistics();
    
    if (is_array($stats)) {
        echo "✓ User statistics retrieved successfully<br>\n";
        foreach ($stats as $status => $stat) {
            echo "&nbsp;&nbsp;- {$stat['label']}: {$stat['count']}<br>\n";
        }
    } else {
        echo "✗ User statistics retrieval failed<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Exception retrieving user statistics: " . $e->getMessage() . "<br>\n";
}

// Test 9: Create sample data for testing
echo "<h2>9. Sample Data Creation Test</h2>\n";
try {
    $sample_data = new TPAK_Survey_Data();
    $sample_data->set_survey_id('TEST_SURVEY_123');
    $sample_data->set_response_id('TEST_RESPONSE_' . time());
    $sample_data->set_status('pending_a');
    $sample_data->set_data(array(
        'question_1' => 'Test answer 1',
        'question_2' => 'Test answer 2',
        'timestamp' => current_time('mysql')
    ));
    
    $result = $sample_data->save();
    
    if (!is_wp_error($result)) {
        echo "✓ Sample survey data created successfully (ID: {$result})<br>\n";
        
        // Test data retrieval
        $retrieved_data = new TPAK_Survey_Data($result);
        if ($retrieved_data->get_id()) {
            echo "✓ Sample data retrieved successfully<br>\n";
            echo "&nbsp;&nbsp;- Survey ID: " . $retrieved_data->get_survey_id() . "<br>\n";
            echo "&nbsp;&nbsp;- Response ID: " . $retrieved_data->get_response_id() . "<br>\n";
            echo "&nbsp;&nbsp;- Status: " . $retrieved_data->get_status() . "<br>\n";
            
            // Clean up
            $retrieved_data->delete(true);
            echo "✓ Sample data cleaned up<br>\n";
        } else {
            echo "✗ Failed to retrieve sample data<br>\n";
        }
    } else {
        echo "✗ Failed to create sample data: " . $result->get_error_message() . "<br>\n";
    }
} catch (Exception $e) {
    echo "✗ Exception creating sample data: " . $e->getMessage() . "<br>\n";
}

// Test 10: Check AJAX hooks
echo "<h2>10. AJAX Hooks Test</h2>\n";
$ajax_actions = array(
    'tpak_load_data_table',
    'tpak_get_data_details',
    'tpak_perform_workflow_action',
    'tpak_bulk_action',
    'tpak_update_data'
);

foreach ($ajax_actions as $action) {
    if (has_action("wp_ajax_{$action}")) {
        echo "✓ AJAX action '{$action}' registered<br>\n";
    } else {
        echo "✗ AJAX action '{$action}' not registered<br>\n";
    }
}

echo "<h2>Test Summary</h2>\n";
echo "<p>Data management interface test completed. Check the results above for any issues.</p>\n";

// Display current user info
$current_user = wp_get_current_user();
echo "<h3>Current User Info</h3>\n";
echo "User: {$current_user->display_name} (ID: {$current_user->ID})<br>\n";
echo "Roles: " . implode(', ', $current_user->roles) . "<br>\n";

// Check TPAK capabilities
$tpak_caps = array(
    'tpak_access_dashboard',
    'tpak_view_assigned_data',
    'tpak_manage_settings',
    'tpak_view_all_data'
);

echo "TPAK Capabilities:<br>\n";
foreach ($tpak_caps as $cap) {
    $has_cap = current_user_can($cap) ? '✓' : '✗';
    echo "&nbsp;&nbsp;{$has_cap} {$cap}<br>\n";
}

echo "<hr>\n";
echo "<p><strong>Note:</strong> To fully test the data management interface, visit the WordPress admin area and navigate to TPAK DQ → Data Management.</p>\n";
?>