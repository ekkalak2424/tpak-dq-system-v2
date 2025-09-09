<?php
/**
 * Installation Test Runner
 * 
 * Simple test runner for installation procedures
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Load WordPress
require_once '../../../wp-load.php';

// Check if user has permission
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to run tests.');
}

// Load plugin files
require_once __DIR__ . '/includes/class-autoloader.php';
TPAK_Autoloader::register();

// Load test class
require_once __DIR__ . '/tests/test-installation.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>TPAK DQ System - Installation Tests</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; border-bottom: 2px solid #0073aa; }
        h3 { color: #666; margin-top: 20px; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .passed { background-color: #d4edda; border: 1px solid #c3e6cb; }
        .failed { background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .summary { background-color: #e2e3e5; border: 1px solid #d6d8db; padding: 15px; margin-top: 20px; }
        pre { background-color: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>TPAK DQ System - Installation Tests</h1>
    
    <div class="test-info">
        <h3>Test Environment</h3>
        <ul>
            <li><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></li>
            <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
            <li><strong>Plugin Version:</strong> <?php echo defined('TPAK_DQ_VERSION') ? TPAK_DQ_VERSION : 'Not loaded'; ?></li>
            <li><strong>Test Time:</strong> <?php echo current_time('mysql'); ?></li>
        </ul>
    </div>
    
    <?php
    // Run the tests
    ob_start();
    $all_passed = TPAK_Installation_Test::run_tests();
    $test_output = ob_get_clean();
    
    echo $test_output;
    ?>
    
    <div class="summary <?php echo $all_passed ? 'passed' : 'failed'; ?>">
        <h3>Overall Result</h3>
        <p><strong><?php echo $all_passed ? 'ALL TESTS PASSED' : 'SOME TESTS FAILED'; ?></strong></p>
        <?php if (!$all_passed): ?>
        <p>Please review the failed tests above and fix any issues before using the plugin in production.</p>
        <?php endif; ?>
    </div>
    
    <div class="test-actions">
        <h3>Test Actions</h3>
        <p>
            <a href="?run_installation_tests=1" class="button">Run Tests Again</a>
            <a href="<?php echo admin_url('admin.php?page=tpak-dq-dashboard'); ?>" class="button">Go to Dashboard</a>
        </p>
    </div>
    
    <div class="test-details">
        <h3>Test Details</h3>
        <p>These tests verify that the TPAK DQ System plugin:</p>
        <ul>
            <li>Meets system requirements (WordPress 5.0+, PHP 7.4+)</li>
            <li>Properly sets up database structures and post types</li>
            <li>Creates user roles and capabilities correctly</li>
            <li>Initializes default settings with proper values</li>
            <li>Sets up cron jobs without auto-scheduling</li>
            <li>Preserves data during deactivation</li>
            <li>Cleans up properly during uninstallation</li>
            <li>Handles failed activation gracefully</li>
            <li>Manages version upgrades correctly</li>
        </ul>
    </div>
</body>
</html>