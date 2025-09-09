<?php
/**
 * Installation Validation Script
 * 
 * Validates that installation procedures work correctly
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Load WordPress
require_once '../../../wp-load.php';

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to run validation.');
}

// Load plugin
require_once __DIR__ . '/includes/class-autoloader.php';
TPAK_Autoloader::register();

/**
 * Installation Validator Class
 */
class TPAK_Installation_Validator {
    
    /**
     * Run validation
     */
    public static function validate() {
        echo "<h2>TPAK DQ System - Installation Validation</h2>\n";
        
        $validations = array(
            'validate_plugin_activated' => 'Plugin Activation Status',
            'validate_post_types' => 'Custom Post Types',
            'validate_user_roles' => 'User Roles and Capabilities',
            'validate_default_settings' => 'Default Settings',
            'validate_cron_intervals' => 'Cron Intervals',
            'validate_database_structure' => 'Database Structure',
            'validate_file_structure' => 'File Structure',
            'validate_dependencies' => 'Dependencies',
        );
        
        $results = array();
        
        foreach ($validations as $method => $name) {
            echo "<h3>{$name}</h3>\n";
            $result = self::$method();
            $results[$method] = $result;
            
            if ($result['status']) {
                echo "<p style='color: green;'>✓ VALID</p>\n";
            } else {
                echo "<p style='color: red;'>✗ INVALID</p>\n";
            }
            
            if (!empty($result['details'])) {
                echo "<ul>\n";
                foreach ($result['details'] as $detail) {
                    echo "<li>" . esc_html($detail) . "</li>\n";
                }
                echo "</ul>\n";
            }
        }
        
        // Summary
        $passed = count(array_filter($results, function($r) { return $r['status']; }));
        $total = count($results);
        
        echo "<h3>Validation Summary</h3>\n";
        echo "<p>Passed: {$passed}/{$total}</p>\n";
        
        if ($passed === $total) {
            echo "<p style='color: green; font-weight: bold;'>✓ ALL VALIDATIONS PASSED</p>\n";
        } else {
            echo "<p style='color: red; font-weight: bold;'>✗ SOME VALIDATIONS FAILED</p>\n";
        }
        
        return $passed === $total;
    }
    
    /**
     * Validate plugin activation
     */
    private static function validate_plugin_activated() {
        $details = array();
        
        // Check activation flag
        $activated = get_option('tpak_dq_activated');
        if (!$activated) {
            $details[] = 'Plugin activation flag not set';
        } else {
            $details[] = 'Plugin activation flag: OK';
        }
        
        // Check version
        $version = get_option('tpak_dq_version');
        if (!$version) {
            $details[] = 'Plugin version not stored';
        } else {
            $details[] = "Plugin version: {$version}";
        }
        
        // Check activation date
        $activation_date = get_option('tpak_dq_activation_date');
        if (!$activation_date) {
            $details[] = 'Activation date not recorded';
        } else {
            $details[] = "Activation date: {$activation_date}";
        }
        
        return array(
            'status' => $activated && $version,
            'details' => $details
        );
    }
    
    /**
     * Validate post types
     */
    private static function validate_post_types() {
        $details = array();
        
        // Check if post type is registered
        $post_types = get_post_types();
        if (!isset($post_types['tpak_survey_data'])) {
            $details[] = 'Custom post type not registered';
            return array('status' => false, 'details' => $details);
        }
        
        $details[] = 'Custom post type registered: OK';
        
        // Check post type object
        $post_type_obj = get_post_type_object('tpak_survey_data');
        if (!$post_type_obj) {
            $details[] = 'Post type object not found';
            return array('status' => false, 'details' => $details);
        }
        
        // Check capabilities
        if ($post_type_obj->capability_type !== 'tpak_survey_data') {
            $details[] = 'Custom capability type not set';
        } else {
            $details[] = 'Custom capability type: OK';
        }
        
        // Check meta fields
        $meta_fields = TPAK_Post_Types::get_instance()->get_meta_fields();
        $details[] = "Meta fields defined: " . count($meta_fields);
        
        return array(
            'status' => true,
            'details' => $details
        );
    }
    
    /**
     * Validate user roles
     */
    private static function validate_user_roles() {
        $details = array();
        
        $expected_roles = array(
            'tpak_interviewer_a' => 'TPAK Interviewer (A)',
            'tpak_supervisor_b' => 'TPAK Supervisor (B)',
            'tpak_examiner_c' => 'TPAK Examiner (C)'
        );
        
        $all_valid = true;
        
        foreach ($expected_roles as $role_name => $display_name) {
            $role = get_role($role_name);
            if (!$role) {
                $details[] = "Role missing: {$role_name}";
                $all_valid = false;
            } else {
                $cap_count = count($role->capabilities);
                $details[] = "Role {$role_name}: OK ({$cap_count} capabilities)";
            }
        }
        
        // Check administrator capabilities
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $tpak_caps = array_filter($admin_role->capabilities, function($cap, $key) {
                return strpos($key, 'tpak_') === 0;
            }, ARRAY_FILTER_USE_BOTH);
            
            $details[] = "Administrator TPAK capabilities: " . count($tpak_caps);
        }
        
        return array(
            'status' => $all_valid,
            'details' => $details
        );
    }
    
    /**
     * Validate default settings
     */
    private static function validate_default_settings() {
        $details = array();
        
        $settings_options = array(
            'tpak_dq_api_settings' => 'API Settings',
            'tpak_dq_cron_settings' => 'Cron Settings',
            'tpak_dq_notification_settings' => 'Notification Settings',
            'tpak_dq_workflow_settings' => 'Workflow Settings',
            'tpak_dq_general_settings' => 'General Settings'
        );
        
        $all_valid = true;
        
        foreach ($settings_options as $option_name => $display_name) {
            $settings = get_option($option_name);
            if (!$settings || !is_array($settings)) {
                $details[] = "{$display_name}: Missing or invalid";
                $all_valid = false;
            } else {
                $details[] = "{$display_name}: OK (" . count($settings) . " options)";
            }
        }
        
        return array(
            'status' => $all_valid,
            'details' => $details
        );
    }
    
    /**
     * Validate cron intervals
     */
    private static function validate_cron_intervals() {
        $details = array();
        
        $schedules = wp_get_schedules();
        
        $expected_intervals = array(
            'twice_daily' => 'Twice Daily',
            'weekly' => 'Weekly'
        );
        
        $all_valid = true;
        
        foreach ($expected_intervals as $interval => $display_name) {
            if (!isset($schedules[$interval])) {
                $details[] = "Custom interval missing: {$interval}";
                $all_valid = false;
            } else {
                $details[] = "Custom interval {$interval}: OK";
            }
        }
        
        return array(
            'status' => $all_valid,
            'details' => $details
        );
    }
    
    /**
     * Validate database structure
     */
    private static function validate_database_structure() {
        global $wpdb;
        
        $details = array();
        
        // Check if we can create test post
        $test_post_id = wp_insert_post(array(
            'post_type' => 'tpak_survey_data',
            'post_title' => 'Validation Test Post',
            'post_status' => 'draft'
        ), true);
        
        if (is_wp_error($test_post_id)) {
            $details[] = 'Cannot create test post: ' . $test_post_id->get_error_message();
            return array('status' => false, 'details' => $details);
        }
        
        $details[] = 'Test post creation: OK';
        
        // Test meta field operations
        $meta_fields = TPAK_Post_Types::get_instance()->get_meta_fields();
        foreach ($meta_fields as $meta_key => $args) {
            $test_value = ($args['type'] === 'string') ? 'test_value' : 123;
            $result = update_post_meta($test_post_id, $meta_key, $test_value);
            
            if (!$result) {
                $details[] = "Meta field update failed: {$meta_key}";
            }
        }
        
        $details[] = "Meta fields tested: " . count($meta_fields);
        
        // Cleanup
        wp_delete_post($test_post_id, true);
        $details[] = 'Test post cleanup: OK';
        
        return array(
            'status' => true,
            'details' => $details
        );
    }
    
    /**
     * Validate file structure
     */
    private static function validate_file_structure() {
        $details = array();
        
        $required_files = array(
            'tpak-dq-system.php' => 'Main plugin file',
            'uninstall.php' => 'Uninstall script',
            'includes/class-autoloader.php' => 'Autoloader',
            'includes/class-post-types.php' => 'Post types class',
            'includes/class-roles.php' => 'Roles class',
        );
        
        $all_valid = true;
        
        foreach ($required_files as $file => $description) {
            $file_path = __DIR__ . '/' . $file;
            if (!file_exists($file_path)) {
                $details[] = "Missing file: {$file} ({$description})";
                $all_valid = false;
            } else {
                $details[] = "File exists: {$file}";
            }
        }
        
        return array(
            'status' => $all_valid,
            'details' => $details
        );
    }
    
    /**
     * Validate dependencies
     */
    private static function validate_dependencies() {
        $details = array();
        
        $required_classes = array(
            'TPAK_DQ_System' => 'Main plugin class',
            'TPAK_Autoloader' => 'Autoloader class',
            'TPAK_Post_Types' => 'Post types class',
            'TPAK_Roles' => 'Roles class',
        );
        
        $all_valid = true;
        
        foreach ($required_classes as $class => $description) {
            if (!class_exists($class)) {
                $details[] = "Missing class: {$class} ({$description})";
                $all_valid = false;
            } else {
                $details[] = "Class loaded: {$class}";
            }
        }
        
        return array(
            'status' => $all_valid,
            'details' => $details
        );
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>TPAK DQ System - Installation Validation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; border-bottom: 2px solid #0073aa; }
        h3 { color: #666; margin-top: 20px; }
        ul { margin: 10px 0; }
        li { margin: 5px 0; }
        .summary { background-color: #e2e3e5; border: 1px solid #d6d8db; padding: 15px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>TPAK DQ System - Installation Validation</h1>
    
    <?php TPAK_Installation_Validator::validate(); ?>
    
    <div class="actions">
        <h3>Actions</h3>
        <p>
            <a href="test_installation.php">Run Installation Tests</a> |
            <a href="<?php echo admin_url('admin.php?page=tpak-dq-dashboard'); ?>">Go to Dashboard</a>
        </p>
    </div>
</body>
</html>