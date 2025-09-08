<?php
/**
 * Admin Menu Class
 * 
 * Handles admin menu registration and management
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Admin Menu Class
 */
class TPAK_Admin_Menu {
    
    /**
     * Single instance
     * 
     * @var TPAK_Admin_Menu
     */
    private static $instance = null;
    
    /**
     * Menu pages
     * 
     * @var array
     */
    private $menu_pages = array();
    
    /**
     * Current page
     * 
     * @var string
     */
    private $current_page = '';
    
    /**
     * Get instance
     * 
     * @return TPAK_Admin_Menu
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Initialize admin
     */
    public function admin_init() {
        // Set current page
        $this->current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        
        // Verify user access for TPAK pages
        if (strpos($this->current_page, 'tpak-') === 0) {
            $this->verify_page_access();
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Check if user has any TPAK access
        if (!$this->user_has_tpak_access()) {
            return;
        }
        
        // Main menu page - Dashboard
        $dashboard_hook = add_menu_page(
            __('TPAK DQ System', 'tpak-dq-system'),
            __('TPAK DQ', 'tpak-dq-system'),
            'tpak_access_dashboard',
            'tpak-dashboard',
            array($this, 'render_dashboard_page'),
            'dashicons-analytics',
            30
        );
        
        $this->menu_pages['dashboard'] = $dashboard_hook;
        
        // Data Management - visible to all TPAK roles
        if (current_user_can('tpak_view_assigned_data')) {
            $data_hook = add_submenu_page(
                'tpak-dashboard',
                __('Data Management', 'tpak-dq-system'),
                __('Data Management', 'tpak-dq-system'),
                'tpak_view_assigned_data',
                'tpak-data',
                array($this, 'render_data_page')
            );
            
            $this->menu_pages['data'] = $data_hook;
        }
        
        // Settings - only for administrators
        if (current_user_can('tpak_manage_settings')) {
            $settings_hook = add_submenu_page(
                'tpak-dashboard',
                __('Settings', 'tpak-dq-system'),
                __('Settings', 'tpak-dq-system'),
                'tpak_manage_settings',
                'tpak-settings',
                array($this, 'render_settings_page')
            );
            
            $this->menu_pages['settings'] = $settings_hook;
        }
        
        // Import/Export - only for administrators
        if (current_user_can('tpak_import_data')) {
            $import_hook = add_submenu_page(
                'tpak-dashboard',
                __('Import/Export', 'tpak-dq-system'),
                __('Import/Export', 'tpak-dq-system'),
                'tpak_import_data',
                'tpak-import-export',
                array($this, 'render_import_export_page')
            );
            
            $this->menu_pages['import_export'] = $import_hook;
        }
        
        // User Management - only for administrators
        if (current_user_can('tpak_manage_users')) {
            $users_hook = add_submenu_page(
                'tpak-dashboard',
                __('User Management', 'tpak-dq-system'),
                __('User Management', 'tpak-dq-system'),
                'tpak_manage_users',
                'tpak-users',
                array($this, 'render_users_page')
            );
            
            $this->menu_pages['users'] = $users_hook;
        }
        
        // System Status - only for administrators
        if (current_user_can('tpak_manage_settings')) {
            $status_hook = add_submenu_page(
                'tpak-dashboard',
                __('System Status', 'tpak-dq-system'),
                __('System Status', 'tpak-dq-system'),
                'tpak_manage_settings',
                'tpak-status',
                array($this, 'render_status_page')
            );
            
            $this->menu_pages['status'] = $status_hook;
        }
    }
    
    /**
     * Check if user has any TPAK access
     * 
     * @return bool
     */
    private function user_has_tpak_access() {
        return current_user_can('tpak_access_dashboard') || 
               current_user_can('tpak_manage_settings') ||
               current_user_can('administrator');
    }
    
    /**
     * Verify page access
     */
    private function verify_page_access() {
        // Security check - verify nonce for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'tpak_admin_action')) {
                wp_die(__('Security check failed. Please try again.', 'tpak-dq-system'));
            }
        }
        
        // Check page-specific permissions
        $page_permissions = array(
            'tpak-dashboard'      => 'tpak_access_dashboard',
            'tpak-data'          => 'tpak_view_assigned_data',
            'tpak-settings'      => 'tpak_manage_settings',
            'tpak-import-export' => 'tpak_import_data',
            'tpak-users'         => 'tpak_manage_users',
            'tpak-status'        => 'tpak_manage_settings',
        );
        
        if (isset($page_permissions[$this->current_page])) {
            if (!current_user_can($page_permissions[$this->current_page])) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
            }
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on TPAK pages
        if (!in_array($hook, $this->menu_pages)) {
            return;
        }
        
        // Enqueue WordPress admin styles
        wp_enqueue_style('wp-admin');
        wp_enqueue_style('common');
        wp_enqueue_style('forms');
        
        // Enqueue custom admin styles (will be created in later tasks)
        wp_enqueue_style(
            'tpak-admin-style',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css',
            array(),
            '1.0.0'
        );
        
        // Enqueue admin scripts
        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-util');
        
        // Enqueue custom admin scripts (will be created in later tasks)
        wp_enqueue_script(
            'tpak-admin-script',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            '1.0.0',
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('tpak-admin-script', 'tpak_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tpak_admin_ajax'),
            'strings'  => array(
                'loading'        => __('Loading...', 'tpak-dq-system'),
                'error'          => __('An error occurred. Please try again.', 'tpak-dq-system'),
                'confirm_delete' => __('Are you sure you want to delete this item?', 'tpak-dq-system'),
                'success'        => __('Operation completed successfully.', 'tpak-dq-system'),
            ),
        ));
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        $this->render_admin_page('dashboard', __('Dashboard', 'tpak-dq-system'));
    }
    
    /**
     * Render data management page
     */
    public function render_data_page() {
        $this->render_admin_page('data', __('Data Management', 'tpak-dq-system'));
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $this->render_admin_page('settings', __('Settings', 'tpak-dq-system'));
    }
    
    /**
     * Render import/export page
     */
    public function render_import_export_page() {
        $this->render_admin_page('import-export', __('Import/Export', 'tpak-dq-system'));
    }
    
    /**
     * Render users page
     */
    public function render_users_page() {
        $this->render_admin_page('users', __('User Management', 'tpak-dq-system'));
    }
    
    /**
     * Render status page
     */
    public function render_status_page() {
        $this->render_admin_page('status', __('System Status', 'tpak-dq-system'));
    }
    
    /**
     * Render admin page template
     * 
     * @param string $page_slug
     * @param string $page_title
     */
    private function render_admin_page($page_slug, $page_title) {
        // Security check
        if (!current_user_can('tpak_access_dashboard')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'tpak-dq-system'));
        }
        
        // Get current user info for role-based content
        $current_user = wp_get_current_user();
        $user_role = $this->get_user_tpak_role($current_user->ID);
        
        ?>
        <div class="wrap tpak-admin-page">
            <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
            
            <?php $this->render_admin_notices(); ?>
            
            <div class="tpak-admin-content">
                <?php
                // Include page-specific content
                $template_file = dirname(__FILE__) . "/pages/{$page_slug}.php";
                
                if (file_exists($template_file)) {
                    include $template_file;
                } else {
                    $this->render_placeholder_content($page_slug, $user_role);
                }
                ?>
            </div>
            
            <?php $this->render_admin_footer(); ?>
        </div>
        <?php
    }
    
    /**
     * Render admin notices
     */
    private function render_admin_notices() {
        // Check for success/error messages
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'success';
            
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        }
        
        // System status notices
        $this->render_system_notices();
    }
    
    /**
     * Render system notices
     */
    private function render_system_notices() {
        if (!current_user_can('tpak_manage_settings')) {
            return;
        }
        
        $notices = array();
        
        // Check API configuration
        $api_settings = get_option('tpak_dq_settings', array());
        if (empty($api_settings['api_url']) || empty($api_settings['api_username'])) {
            $notices[] = array(
                'type'    => 'warning',
                'message' => sprintf(
                    __('LimeSurvey API is not configured. <a href="%s">Configure it now</a>.', 'tpak-dq-system'),
                    admin_url('admin.php?page=tpak-settings')
                ),
            );
        }
        
        // Check cron status
        if (!wp_next_scheduled('tpak_import_survey_data')) {
            $notices[] = array(
                'type'    => 'warning',
                'message' => __('Automated data import is not scheduled. Check your cron settings.', 'tpak-dq-system'),
            );
        }
        
        // Display notices
        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%s"><p>%s</p></div>',
                esc_attr($notice['type']),
                wp_kses_post($notice['message'])
            );
        }
    }
    
    /**
     * Render placeholder content for pages not yet implemented
     * 
     * @param string $page_slug
     * @param string $user_role
     */
    private function render_placeholder_content($page_slug, $user_role) {
        ?>
        <div class="tpak-placeholder-content">
            <div class="card">
                <h2><?php printf(__('%s Page', 'tpak-dq-system'), ucwords(str_replace('-', ' ', $page_slug))); ?></h2>
                <p><?php _e('This page is under development and will be implemented in upcoming tasks.', 'tpak-dq-system'); ?></p>
                
                <?php if ($user_role): ?>
                    <p><strong><?php _e('Your Role:', 'tpak-dq-system'); ?></strong> 
                    <?php echo esc_html($this->get_role_display_name($user_role)); ?></p>
                <?php endif; ?>
                
                <p><strong><?php _e('Available Capabilities:', 'tpak-dq-system'); ?></strong></p>
                <ul>
                    <?php
                    $capabilities = $this->get_user_tpak_capabilities();
                    foreach ($capabilities as $cap) {
                        echo '<li>' . esc_html($cap) . '</li>';
                    }
                    ?>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render admin footer
     */
    private function render_admin_footer() {
        ?>
        <div class="tpak-admin-footer">
            <p><?php printf(__('TPAK DQ System v%s', 'tpak-dq-system'), '1.0.0'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Get user's TPAK role
     * 
     * @param int $user_id
     * @return string|null
     */
    private function get_user_tpak_role($user_id) {
        $roles_instance = TPAK_Roles::get_instance();
        return $roles_instance->get_user_tpak_role($user_id);
    }
    
    /**
     * Get role display name
     * 
     * @param string $role_name
     * @return string
     */
    private function get_role_display_name($role_name) {
        $roles_instance = TPAK_Roles::get_instance();
        return $roles_instance->get_role_display_name($role_name);
    }
    
    /**
     * Get user's TPAK capabilities
     * 
     * @return array
     */
    private function get_user_tpak_capabilities() {
        $current_user = wp_get_current_user();
        $capabilities = array();
        
        $tpak_caps = array(
            'tpak_access_dashboard',
            'tpak_view_assigned_data',
            'tpak_manage_settings',
            'tpak_import_data',
            'tpak_export_data',
            'tpak_manage_users',
            'tpak_review_pending_a',
            'tpak_review_pending_b',
            'tpak_review_pending_c',
            'tpak_edit_pending_a',
            'tpak_approve_to_supervisor',
            'tpak_approve_to_examiner',
            'tpak_final_approval',
        );
        
        foreach ($tpak_caps as $cap) {
            if (current_user_can($cap)) {
                $capabilities[] = $cap;
            }
        }
        
        return $capabilities;
    }
    
    /**
     * Get menu pages
     * 
     * @return array
     */
    public function get_menu_pages() {
        return $this->menu_pages;
    }
    
    /**
     * Get current page
     * 
     * @return string
     */
    public function get_current_page() {
        return $this->current_page;
    }
    
    /**
     * Check if current page is TPAK admin page
     * 
     * @return bool
     */
    public function is_tpak_admin_page() {
        return strpos($this->current_page, 'tpak-') === 0;
    }
    
    /**
     * Add admin notice
     * 
     * @param string $message
     * @param string $type
     */
    public function add_admin_notice($message, $type = 'success') {
        $redirect_url = add_query_arg(array(
            'message' => urlencode($message),
            'type'    => $type,
        ));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Handle admin actions
     * 
     * @param string $action
     * @param array  $data
     * @return bool
     */
    public function handle_admin_action($action, $data = array()) {
        // Verify nonce
        if (!wp_verify_nonce($data['_wpnonce'] ?? '', 'tpak_admin_action')) {
            return false;
        }
        
        // Handle different actions
        switch ($action) {
            case 'save_settings':
                return $this->handle_save_settings($data);
                
            case 'import_data':
                return $this->handle_import_data($data);
                
            case 'export_data':
                return $this->handle_export_data($data);
                
            default:
                return false;
        }
    }
    
    /**
     * Handle save settings action
     * 
     * @param array $data
     * @return bool
     */
    private function handle_save_settings($data) {
        if (!current_user_can('tpak_manage_settings')) {
            return false;
        }
        
        // This will be implemented in the settings task
        return true;
    }
    
    /**
     * Handle import data action
     * 
     * @param array $data
     * @return bool
     */
    private function handle_import_data($data) {
        if (!current_user_can('tpak_import_data')) {
            return false;
        }
        
        // This will be implemented in the import/export task
        return true;
    }
    
    /**
     * Handle export data action
     * 
     * @param array $data
     * @return bool
     */
    private function handle_export_data($data) {
        if (!current_user_can('tpak_export_data')) {
            return false;
        }
        
        // This will be implemented in the import/export task
        return true;
    }
}