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
        $roles = array(
            'tpak_interviewer_a',
            'tpak_supervisor_b',
            'tpak_examiner_c'
        );
        
        foreach ($roles as $role) {
            remove_role($role);
        }
    }
    
    /**
     * Remove plugin options
     */
    private static function remove_options() {
        $options = array(
            'tpak_dq_settings',
            'tpak_dq_cron_settings',
            'tpak_dq_activated',
            'tpak_dq_version',
            'tpak_dq_api_settings',
            'tpak_dq_notification_settings',
            'tpak_dq_sampling_percentage'
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
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
        
        // Clear any transients
        delete_transient('tpak_dq_api_status');
        delete_transient('tpak_dq_dashboard_stats');
    }
}

// Run uninstall
TPAK_DQ_Uninstaller::uninstall();