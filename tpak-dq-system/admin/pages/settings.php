<?php
/**
 * Settings Page Template
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security check
if (!current_user_can('tpak_manage_settings')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
}

// Get settings handler
$settings_handler = TPAK_Admin_Settings::get_instance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tpak_settings_nonce'])) {
    $settings_handler->handle_settings_save();
}

// Get current settings
$current_settings = $settings_handler->get_all_settings();
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'api';

?>

<div class="tpak-settings-page">
    <div class="tpak-settings-header">
        <h1><?php _e('TPAK DQ System Settings', 'tpak-dq-system'); ?></h1>
        <p class="description">
            <?php _e('Configure your TPAK Data Quality System settings including API connections, automation, and notifications.', 'tpak-dq-system'); ?>
        </p>
    </div>

    <!-- Settings Navigation Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="<?php echo esc_url(admin_url('admin.php?page=tpak-settings&tab=api')); ?>" 
           class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-plugins"></span>
            <?php _e('LimeSurvey API', 'tpak-dq-system'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=tpak-settings&tab=cron')); ?>" 
           class="nav-tab <?php echo $active_tab === 'cron' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-clock"></span>
            <?php _e('Automation', 'tpak-dq-system'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=tpak-settings&tab=notifications')); ?>" 
           class="nav-tab <?php echo $active_tab === 'notifications' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-email-alt"></span>
            <?php _e('Notifications', 'tpak-dq-system'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=tpak-settings&tab=workflow')); ?>" 
           class="nav-tab <?php echo $active_tab === 'workflow' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-networking"></span>
            <?php _e('Workflow', 'tpak-dq-system'); ?>
        </a>
    </nav>

    <!-- Settings Content -->
    <div class="tpak-settings-content">
        <?php
        switch ($active_tab) {
            case 'api':
                $settings_handler->render_api_settings_tab($current_settings);
                break;
            case 'cron':
                $settings_handler->render_cron_settings_tab($current_settings);
                break;
            case 'notifications':
                $settings_handler->render_notifications_settings_tab($current_settings);
                break;
            case 'workflow':
                $settings_handler->render_workflow_settings_tab($current_settings);
                break;
            default:
                $settings_handler->render_api_settings_tab($current_settings);
        }
        ?>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Test API connection
    $('#test-api-connection').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var originalText = button.text();
        
        button.text('<?php _e('Testing...', 'tpak-dq-system'); ?>').prop('disabled', true);
        
        var data = {
            action: 'tpak_test_api_connection',
            nonce: tpak_admin.nonce,
            url: $('#limesurvey_url').val(),
            username: $('#api_username').val(),
            password: $('#api_password').val(),
            survey_id: $('#survey_id').val()
        };
        
        $.post(tpak_admin.ajax_url, data, function(response) {
            if (response.success) {
                $('#api-test-result').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
            } else {
                $('#api-test-result').html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
            }
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    });
    
    // Manual import trigger
    $('#trigger-manual-import').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('<?php _e('Are you sure you want to trigger a manual import? This may take some time.', 'tpak-dq-system'); ?>')) {
            return;
        }
        
        var button = $(this);
        var originalText = button.text();
        
        button.text('<?php _e('Importing...', 'tpak-dq-system'); ?>').prop('disabled', true);
        
        var data = {
            action: 'tpak_manual_import',
            nonce: tpak_admin.nonce
        };
        
        $.post(tpak_admin.ajax_url, data, function(response) {
            if (response.success) {
                $('#manual-import-result').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
            } else {
                $('#manual-import-result').html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
            }
        }).always(function() {
            button.text(originalText).prop('disabled', false);
        });
    });
});
</script>