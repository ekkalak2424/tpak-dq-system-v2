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
        // Core components
        TPAK_Post_Types::get_instance();
        TPAK_Roles::get_instance();
        TPAK_Validator::get_instance();
        
        // Admin components (only in admin)
        if (is_admin()) {
            TPAK_Admin_Menu::get_instance();
            TPAK_Meta_Boxes::get_instance();
            TPAK_Admin_Columns::get_instance();
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
        
        // Run activation procedures
        TPAK_Post_Types::get_instance()->register_post_types();
        TPAK_Roles::get_instance()->create_roles();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Set activation flag
        update_option('tpak_dq_activated', true);
        update_option('tpak_dq_version', TPAK_DQ_VERSION);
        
        do_action('tpak_dq_activated');
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Remove scheduled cron jobs
        wp_clear_scheduled_hook('tpak_dq_import_data');
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        do_action('tpak_dq_deactivated');
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