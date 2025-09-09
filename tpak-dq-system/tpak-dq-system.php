<?php
/**
 * Plugin Name: TPAK DQ System
 * Plugin URI: https://tpak.org
 * Description: ระบบจัดการข้อมูลคุณภาพสำหรับ TPAK Survey System - เชื่อมต่อกับ LimeSurvey API และจัดการกระบวนการตรวจสอบ 3 ขั้นตอน
 * Version: 1.0.0
 * Author: TPAK Development Team
 * Author URI: https://tpak.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tpak-dq-system
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TPAK_DQ_VERSION', '1.0.0');
define('TPAK_DQ_PLUGIN_FILE', __FILE__);
define('TPAK_DQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPAK_DQ_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPAK_DQ_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main TPAK DQ System Class
 * 
 * @since 1.0.0
 */
final class TPAK_DQ_System {
    
    /**
     * Single instance of the class
     * 
     * @var TPAK_DQ_System
     */
    private static $instance = null;
    
    /**
     * Get single instance of the class
     * 
     * @return TPAK_DQ_System
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        register_activation_hook(TPAK_DQ_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(TPAK_DQ_PLUGIN_FILE, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Autoloader
        require_once TPAK_DQ_PLUGIN_DIR . 'includes/class-autoloader.php';
        TPAK_Autoloader::register();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check WordPress version compatibility
        if (!$this->is_compatible()) {
            return;
        }
        
        // Initialize components
        $this->init_components();
        
        // Hook into WordPress
        do_action('tpak_dq_init');
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize logging and error handling first
        TPAK_Logger::get_instance();
        new TPAK_Error_Handler();
        
        // Security components (initialize first)
        TPAK_Security::init();
        TPAK_Security_Middleware::init();
        
        // Core components
        TPAK_Post_Types::get_instance();
        TPAK_Roles::get_instance();
        TPAK_Validator::get_instance();
        
        // Admin components (only in admin)
        if (is_admin()) {
            TPAK_Admin_Menu::get_instance();
            TPAK_Admin_Settings::get_instance();
            TPAK_Admin_Data::get_instance();
            TPAK_Meta_Boxes::get_instance();
            TPAK_Dashboard_Stats::get_instance();
        }
        
        // Background components
        TPAK_API_Handler::get_instance();
        TPAK_Workflow::get_instance();
        TPAK_Cron::get_instance();
        TPAK_Notifications::get_instance();
    }
    
    /**
     * Check WordPress compatibility
     * 
     * @return bool
     */
    private function is_compatible() {
        global $wp_version;
        
        if (version_compare($wp_version, '5.0', '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * WordPress version notice
     */
    public function wp_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('TPAK DQ System requires WordPress 5.0 or higher.', 'tpak-dq-system');
        echo '</p></div>';
    }
    
    /**
     * PHP version notice
     */
    public function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('TPAK DQ System requires PHP 7.4 or higher.', 'tpak-dq-system');
        echo '</p></div>';
    }
    
    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'tpak-dq-system',
            false,
            dirname(TPAK_DQ_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check system requirements
        if (!$this->is_compatible()) {
            wp_die(esc_html__('TPAK DQ System requires WordPress 5.0+ and PHP 7.4+', 'tpak-dq-system'));
        }
        
        // Load dependencies for activation
        $this->load_dependencies();
        
        try {
            // Run activation procedures in order
            $this->setup_database();
            $this->create_user_roles();
            $this->initialize_default_settings();
            $this->setup_cron_jobs();
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Set activation flags
            update_option('tpak_dq_activated', true);
            update_option('tpak_dq_version', TPAK_DQ_VERSION);
            update_option('tpak_dq_activation_date', current_time('mysql'));
            
            // Log successful activation
            if (class_exists('TPAK_Logger')) {
                TPAK_Logger::get_instance()->log('info', 'TPAK DQ System activated successfully', array(
                    'version' => TPAK_DQ_VERSION,
                    'timestamp' => current_time('mysql')
                ));
            }
            
            do_action('tpak_dq_activated');
            
        } catch (Exception $e) {
            // Log activation error
            error_log('TPAK DQ System activation failed: ' . $e->getMessage());
            
            // Clean up partial activation
            $this->cleanup_failed_activation();
            
            wp_die(
                sprintf(
                    esc_html__('TPAK DQ System activation failed: %s', 'tpak-dq-system'),
                    esc_html($e->getMessage())
                )
            );
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove scheduled cron jobs
        wp_clear_scheduled_hook('tpak_dq_import_data');
        wp_clear_scheduled_hook('tpak_dq_cleanup_old_data');
        
        // Log deactivation
        if (class_exists('TPAK_Logger')) {
            TPAK_Logger::get_instance()->log('info', 'TPAK DQ System deactivated', array(
                'version' => TPAK_DQ_VERSION,
                'timestamp' => current_time('mysql')
            ));
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('tpak_dq_deactivated');
    }
    
    /**
     * Setup database tables and post types
     */
    private function setup_database() {
        // Register post types and meta fields
        TPAK_Post_Types::get_instance()->register_post_types();
        
        // Create any custom database tables if needed in future
        $this->create_custom_tables();
    }
    
    /**
     * Create custom database tables
     */
    private function create_custom_tables() {
        global $wpdb;
        
        // Currently using WordPress post system, but this method
        // is available for future custom table requirements
        
        // Example for future audit log table:
        /*
        $table_name = $wpdb->prefix . 'tpak_audit_log';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            old_value text,
            new_value text,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        */
    }
    
    /**
     * Create user roles and capabilities
     */
    private function create_user_roles() {
        TPAK_Roles::get_instance()->create_roles();
    }
    
    /**
     * Initialize default settings
     */
    private function initialize_default_settings() {
        // API Settings
        $default_api_settings = array(
            'url' => '',
            'username' => '',
            'password' => '',
            'survey_id' => '',
            'connection_timeout' => 30,
            'read_timeout' => 60,
            'ssl_verify' => true,
            'last_test_date' => '',
            'last_test_result' => '',
        );
        
        if (!get_option('tpak_dq_api_settings')) {
            update_option('tpak_dq_api_settings', $default_api_settings);
        }
        
        // Cron Settings
        $default_cron_settings = array(
            'enabled' => false,
            'interval' => 'daily',
            'survey_id' => '',
            'last_run' => '',
            'next_run' => '',
            'max_records_per_run' => 100,
            'retry_attempts' => 3,
            'retry_delay' => 300, // 5 minutes
        );
        
        if (!get_option('tpak_dq_cron_settings')) {
            update_option('tpak_dq_cron_settings', $default_cron_settings);
        }
        
        // Notification Settings
        $default_notification_settings = array(
            'enabled' => true,
            'email_from_name' => get_bloginfo('name'),
            'email_from_address' => get_option('admin_email'),
            'notify_on_assignment' => true,
            'notify_on_status_change' => true,
            'notify_on_rejection' => true,
            'notify_on_completion' => false,
            'email_template_assignment' => $this->get_default_email_template('assignment'),
            'email_template_status_change' => $this->get_default_email_template('status_change'),
            'email_template_rejection' => $this->get_default_email_template('rejection'),
        );
        
        if (!get_option('tpak_dq_notification_settings')) {
            update_option('tpak_dq_notification_settings', $default_notification_settings);
        }
        
        // Workflow Settings
        $default_workflow_settings = array(
            'sampling_percentage' => 30,
            'auto_assign_users' => false,
            'require_notes_on_rejection' => true,
            'max_rejection_cycles' => 3,
            'enable_audit_trail' => true,
        );
        
        if (!get_option('tpak_dq_workflow_settings')) {
            update_option('tpak_dq_workflow_settings', $default_workflow_settings);
        }
        
        // General Settings
        $default_general_settings = array(
            'data_retention_days' => 365,
            'enable_debug_logging' => false,
            'max_log_entries' => 1000,
            'timezone' => get_option('timezone_string', 'UTC'),
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
        );
        
        if (!get_option('tpak_dq_general_settings')) {
            update_option('tpak_dq_general_settings', $default_general_settings);
        }
    }
    
    /**
     * Get default email template
     * 
     * @param string $type
     * @return string
     */
    private function get_default_email_template($type) {
        switch ($type) {
            case 'assignment':
                return __('Hello {user_name},

A new survey data item has been assigned to you for review.

Survey ID: {survey_id}
Response ID: {response_id}
Status: {status}
Assigned Date: {assigned_date}

Please log in to the system to review this item:
{review_url}

Best regards,
TPAK DQ System', 'tpak-dq-system');
                
            case 'status_change':
                return __('Hello {user_name},

The status of a survey data item has been updated.

Survey ID: {survey_id}
Response ID: {response_id}
Previous Status: {old_status}
New Status: {new_status}
Updated By: {updated_by}
Updated Date: {updated_date}

View details:
{review_url}

Best regards,
TPAK DQ System', 'tpak-dq-system');
                
            case 'rejection':
                return __('Hello {user_name},

A survey data item has been rejected and requires your attention.

Survey ID: {survey_id}
Response ID: {response_id}
Status: {status}
Rejected By: {rejected_by}
Rejection Date: {rejection_date}
Rejection Notes: {rejection_notes}

Please review and address the issues:
{review_url}

Best regards,
TPAK DQ System', 'tpak-dq-system');
                
            default:
                return '';
        }
    }
    
    /**
     * Setup cron jobs
     */
    private function setup_cron_jobs() {
        // Don't schedule cron jobs during activation - let user configure first
        // This prevents automatic imports before API is configured
        
        // Add custom cron intervals
        add_filter('cron_schedules', array($this, 'add_custom_cron_intervals'));
    }
    
    /**
     * Add custom cron intervals
     * 
     * @param array $schedules
     * @return array
     */
    public function add_custom_cron_intervals($schedules) {
        $schedules['twice_daily'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __('Twice Daily', 'tpak-dq-system')
        );
        
        $schedules['weekly'] = array(
            'interval' => 7 * DAY_IN_SECONDS,
            'display'  => __('Weekly', 'tpak-dq-system')
        );
        
        return $schedules;
    }
    
    /**
     * Cleanup failed activation
     */
    private function cleanup_failed_activation() {
        // Remove any partially created data
        delete_option('tpak_dq_activated');
        delete_option('tpak_dq_version');
        delete_option('tpak_dq_activation_date');
        
        // Remove roles if they were created
        if (class_exists('TPAK_Roles')) {
            TPAK_Roles::get_instance()->remove_roles();
        }
        
        // Clear any scheduled cron jobs
        wp_clear_scheduled_hook('tpak_dq_import_data');
        wp_clear_scheduled_hook('tpak_dq_cleanup_old_data');
    }
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function get_version() {
        return TPAK_DQ_VERSION;
    }
    
    /**
     * Get plugin directory path
     * 
     * @return string
     */
    public function get_plugin_dir() {
        return TPAK_DQ_PLUGIN_DIR;
    }
    
    /**
     * Get plugin URL
     * 
     * @return string
     */
    public function get_plugin_url() {
        return TPAK_DQ_PLUGIN_URL;
    }
}

/**
 * Initialize the plugin
 */
function tpak_dq_system() {
    return TPAK_DQ_System::get_instance();
}

// Start the plugin
tpak_dq_system();