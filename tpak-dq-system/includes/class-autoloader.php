<?php
/**
 * Autoloader Class
 * 
 * Handles automatic loading of plugin classes
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Autoloader Class
 */
class TPAK_Autoloader {
    
    /**
     * Class name mappings
     * 
     * @var array
     */
    private static $class_map = array(
        // Core classes
        'TPAK_Post_Types'     => 'includes/class-post-types.php',
        'TPAK_Roles'          => 'includes/class-roles.php',
        'TPAK_API_Handler'    => 'includes/class-api-handler.php',
        'TPAK_Cron'           => 'includes/class-cron.php',
        'TPAK_Workflow'       => 'includes/class-workflow.php',
        'TPAK_Notifications'  => 'includes/class-notifications.php',
        'TPAK_Validator'      => 'includes/class-validator.php',
        
        // Admin classes
        'TPAK_Admin_Menu'     => 'admin/class-admin-menu.php',
        'TPAK_Meta_Boxes'     => 'admin/class-meta-boxes.php',
        'TPAK_Admin_Columns'  => 'admin/class-admin-columns.php',
        'TPAK_Admin_Settings' => 'admin/class-admin-settings.php',
        'TPAK_Admin_Data'     => 'admin/class-admin-data.php',
        
        // Utility classes
        'TPAK_Logger'         => 'includes/class-logger.php',
        'TPAK_Survey_Data'    => 'includes/class-survey-data.php',
        'TPAK_Audit_Entry'    => 'includes/class-audit-entry.php',
        'TPAK_Role_Manager'   => 'includes/class-role-manager.php',
        
        // Interfaces
        'TPAK_API_Interface'  => 'includes/interface-api.php',
        
        // Workflow utilities
        'TPAK_Workflow_Diagram' => 'includes/class-workflow-diagram.php',
        
        // Cron utilities
        'TPAK_Cron_Monitor'   => 'includes/class-cron-monitor.php',
    );
    
    /**
     * Register autoloader
     */
    public static function register() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }
    
    /**
     * Autoload classes
     * 
     * @param string $class_name
     */
    public static function autoload($class_name) {
        // Check if class starts with TPAK_
        if (strpos($class_name, 'TPAK_') !== 0) {
            return;
        }
        
        // Check if class is in our map
        if (isset(self::$class_map[$class_name])) {
            $file_path = TPAK_DQ_PLUGIN_DIR . self::$class_map[$class_name];
            
            if (file_exists($file_path)) {
                require_once $file_path;
                return;
            }
        }
        
        // Fallback: convert class name to file path
        $file_path = self::class_name_to_file_path($class_name);
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
    
    /**
     * Convert class name to file path
     * 
     * @param string $class_name
     * @return string
     */
    private static function class_name_to_file_path($class_name) {
        // Remove TPAK_ prefix
        $class_name = str_replace('TPAK_', '', $class_name);
        
        // Convert to lowercase and replace underscores with hyphens
        $file_name = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
        
        // Determine directory based on class name
        $directory = 'includes/';
        if (strpos($class_name, 'Admin') === 0) {
            $directory = 'admin/';
        }
        
        return TPAK_DQ_PLUGIN_DIR . $directory . $file_name;
    }
    
    /**
     * Add class to autoloader map
     * 
     * @param string $class_name
     * @param string $file_path
     */
    public static function add_class($class_name, $file_path) {
        self::$class_map[$class_name] = $file_path;
    }
    
    /**
     * Get all registered classes
     * 
     * @return array
     */
    public static function get_registered_classes() {
        return self::$class_map;
    }
}