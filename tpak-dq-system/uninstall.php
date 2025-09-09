<?php
/**
 * Uninstall Script
 * 
 * Handles complete cleanup when plugin is uninstalled
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load autoloader to access plugin classes
if (file_exists(__DIR__ . '/includes/class-autoloader.php')) {
    require_once __DIR__ . '/includes/class-autoloader.php';
    TPAK_Autoloader::register();
}

/**
 * TPAK DQ System Uninstaller
 */
class TPAK_DQ_Uninstaller {
    
    /**
     * Run uninstall procedures
     */
    public static function uninstall() {
        // Remove custom post types and data
        self::remove_post_types();
        
        // Remove custom user roles
        self::remove_user_roles();
        
        // Remove plugin options
        self::remove_options();
        
        // Remove scheduled cron jobs
        self::remove_cron_jobs();
        
        // Remove custom database tables (if any)
        self::remove_custom_tables();
        
        // Clear any cached data
        self::clear_cache();
    }
    
    /**
     * Remove custom post types and all associated data
     */
    private static function remove_post_types() {
        global $wpdb;
        
        // Get all posts of our custom post type
        $posts = get_posts(array(
            'post_type' => 'tpak_survey_data',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        // Delete each post and its meta data
        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
        
        // Remove any orphaned meta data
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->postmeta} 
            WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})
        "));
    }
    
    /**
     * Remove custom user roles
     */
    private static function remove_user_roles() {
        // Use the roles class to properly remove roles and capabilities
        if (class_exists('TPAK_Roles')) {
            TPAK_Roles::get_instance()->remove_roles();
        } else {
            // Fallback manual removal
            $roles = array(
                'tpak_interviewer_a',
                'tpak_supervisor_b',
                'tpak_examiner_c'
            );
            
            foreach ($roles as $role) {
                remove_role($role);
            }
            
            // Remove capabilities from administrator
            $admin_role = get_role('administrator');
            if ($admin_role) {
                $admin_capabilities = array(
                    'edit_tpak_survey_data',
                    'read_tpak_survey_data',
                    'delete_tpak_survey_data',
                    'tpak_manage_settings',
                    'tpak_import_data',
                    'tpak_export_data',
                    'tpak_manage_users',
                    'tpak_view_all_data',
                    'tpak_access_dashboard',
                );
                
                foreach ($admin_capabilities as $cap) {
                    $admin_role->remove_cap($cap);
                }
            }
        }
    }
    
    /**
     * Remove plugin options
     */
    private static function remove_options() {
        $options = array(
            // Legacy options
            'tpak_dq_settings',
            'tpak_dq_sampling_percentage',
            
            // Current options
            'tpak_dq_activated',
            'tpak_dq_version',
            'tpak_dq_activation_date',
            'tpak_dq_api_settings',
            'tpak_dq_cron_settings',
            'tpak_dq_notification_settings',
            'tpak_dq_workflow_settings',
            'tpak_dq_general_settings',
            
            // Runtime options
            'tpak_dq_last_import_date',
            'tpak_dq_import_status',
            'tpak_dq_error_count',
            'tpak_dq_last_error',
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Remove any options with our prefix that might have been added dynamically
        global $wpdb;
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s
        ", 'tpak_dq_%'));
    }
    
    /**
     * Remove scheduled cron jobs
     */
    private static function remove_cron_jobs() {
        wp_clear_scheduled_hook('tpak_dq_import_data');
        wp_clear_scheduled_hook('tpak_dq_cleanup_old_data');
    }
    
    /**
     * Remove custom database tables
     */
    private static function remove_custom_tables() {
        global $wpdb;
        
        // Add any custom table removal here if needed in future versions
        // Example:
        // $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tpak_audit_log");
    }
    
    /**
     * Clear any cached data
     */
    private static function clear_cache() {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear transients
        $transients = array(
            'tpak_dq_api_status',
            'tpak_dq_dashboard_stats',
            'tpak_dq_user_stats',
            'tpak_dq_workflow_stats',
            'tpak_dq_system_status',
        );
        
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
        
        // Clear any site transients
        foreach ($transients as $transient) {
            delete_site_transient($transient);
        }
        
        // Remove any cached data with our prefix
        global $wpdb;
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s OR option_name LIKE %s
        ", '_transient_tpak_dq_%', '_transient_timeout_tpak_dq_%'));
        
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s OR option_name LIKE %s
        ", '_site_transient_tpak_dq_%', '_site_transient_timeout_tpak_dq_%'));
    }
}

// Run uninstall
TPAK_DQ_Uninstaller::uninstall();