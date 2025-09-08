<?php
/**
 * Post Types Class
 * 
 * Handles registration of custom post types
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Post Types Class
 */
class TPAK_Post_Types {
    
    /**
     * Single instance
     * 
     * @var TPAK_Post_Types
     */
    private static $instance = null;
    
    /**
     * Get instance
     * 
     * @return TPAK_Post_Types
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
        add_action('init', array($this, 'register_post_types'));
        add_action('init', array($this, 'register_meta_fields'));
    }
    
    /**
     * Register custom post types
     */
    public function register_post_types() {
        $this->register_survey_data_post_type();
    }
    
    /**
     * Register survey data post type
     */
    private function register_survey_data_post_type() {
        $labels = array(
            'name'                  => _x('Survey Data', 'Post type general name', 'tpak-dq-system'),
            'singular_name'         => _x('Survey Data', 'Post type singular name', 'tpak-dq-system'),
            'menu_name'             => _x('Survey Data', 'Admin Menu text', 'tpak-dq-system'),
            'name_admin_bar'        => _x('Survey Data', 'Add New on Toolbar', 'tpak-dq-system'),
            'add_new'               => __('Add New', 'tpak-dq-system'),
            'add_new_item'          => __('Add New Survey Data', 'tpak-dq-system'),
            'new_item'              => __('New Survey Data', 'tpak-dq-system'),
            'edit_item'             => __('Edit Survey Data', 'tpak-dq-system'),
            'view_item'             => __('View Survey Data', 'tpak-dq-system'),
            'all_items'             => __('All Survey Data', 'tpak-dq-system'),
            'search_items'          => __('Search Survey Data', 'tpak-dq-system'),
            'parent_item_colon'     => __('Parent Survey Data:', 'tpak-dq-system'),
            'not_found'             => __('No survey data found.', 'tpak-dq-system'),
            'not_found_in_trash'    => __('No survey data found in Trash.', 'tpak-dq-system'),
            'featured_image'        => _x('Survey Data Featured Image', 'Overrides the "Featured Image" phrase', 'tpak-dq-system'),
            'set_featured_image'    => _x('Set featured image', 'Overrides the "Set featured image" phrase', 'tpak-dq-system'),
            'remove_featured_image' => _x('Remove featured image', 'Overrides the "Remove featured image" phrase', 'tpak-dq-system'),
            'use_featured_image'    => _x('Use as featured image', 'Overrides the "Use as featured image" phrase', 'tpak-dq-system'),
            'archives'              => _x('Survey Data archives', 'The post type archive label', 'tpak-dq-system'),
            'insert_into_item'      => _x('Insert into survey data', 'Overrides the "Insert into post" phrase', 'tpak-dq-system'),
            'uploaded_to_this_item' => _x('Uploaded to this survey data', 'Overrides the "Uploaded to this post" phrase', 'tpak-dq-system'),
            'filter_items_list'     => _x('Filter survey data list', 'Screen reader text for the filter links', 'tpak-dq-system'),
            'items_list_navigation' => _x('Survey data list navigation', 'Screen reader text for the pagination', 'tpak-dq-system'),
            'items_list'            => _x('Survey data list', 'Screen reader text for the items list', 'tpak-dq-system'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'show_in_menu'       => false, // We'll add it to our custom menu
            'show_in_nav_menus'  => false,
            'show_in_admin_bar'  => false,
            'query_var'          => true,
            'rewrite'            => false,
            'capability_type'    => array('tpak_survey_data', 'tpak_survey_data'),
            'map_meta_cap'       => true,
            'has_archive'        => false,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-analytics',
            'supports'           => array('title', 'custom-fields'),
            'show_in_rest'       => false,
        );

        register_post_type('tpak_survey_data', $args);
    }
    
    /**
     * Get workflow statuses
     * 
     * @return array
     */
    public function get_workflow_statuses() {
        return array(
            'pending_a'              => __('Pending Interviewer Review', 'tpak-dq-system'),
            'pending_b'              => __('Pending Supervisor Review', 'tpak-dq-system'),
            'pending_c'              => __('Pending Examiner Review', 'tpak-dq-system'),
            'rejected_by_b'          => __('Rejected by Supervisor', 'tpak-dq-system'),
            'rejected_by_c'          => __('Rejected by Examiner', 'tpak-dq-system'),
            'finalized'              => __('Finalized', 'tpak-dq-system'),
            'finalized_by_sampling'  => __('Finalized by Sampling', 'tpak-dq-system'),
        );
    }
    
    /**
     * Get meta field definitions
     * 
     * @return array
     */
    public function get_meta_fields() {
        return array(
            '_tpak_survey_id'        => array(
                'type'        => 'string',
                'description' => 'LimeSurvey Survey ID',
                'single'      => true,
                'default'     => '',
            ),
            '_tpak_response_id'      => array(
                'type'        => 'string',
                'description' => 'LimeSurvey Response ID',
                'single'      => true,
                'default'     => '',
            ),
            '_tpak_survey_data'      => array(
                'type'        => 'string',
                'description' => 'JSON encoded survey response data',
                'single'      => true,
                'default'     => '{}',
            ),
            '_tpak_workflow_status'  => array(
                'type'        => 'string',
                'description' => 'Current workflow status',
                'single'      => true,
                'default'     => 'pending_a',
            ),
            '_tpak_assigned_user'    => array(
                'type'        => 'integer',
                'description' => 'Currently assigned user ID',
                'single'      => true,
                'default'     => 0,
            ),
            '_tpak_audit_trail'      => array(
                'type'        => 'string',
                'description' => 'JSON encoded audit trail',
                'single'      => true,
                'default'     => '[]',
            ),
            '_tpak_import_date'      => array(
                'type'        => 'string',
                'description' => 'Date when data was imported',
                'single'      => true,
                'default'     => '',
            ),
            '_tpak_last_modified'    => array(
                'type'        => 'string',
                'description' => 'Last modification timestamp',
                'single'      => true,
                'default'     => '',
            ),
            '_tpak_completion_date'  => array(
                'type'        => 'string',
                'description' => 'Date when workflow was completed',
                'single'      => true,
                'default'     => '',
            ),
        );
    }
    
    /**
     * Register meta fields
     */
    public function register_meta_fields() {
        $meta_fields = $this->get_meta_fields();
        
        foreach ($meta_fields as $meta_key => $args) {
            register_post_meta('tpak_survey_data', $meta_key, array(
                'type'         => $args['type'],
                'description'  => $args['description'],
                'single'       => $args['single'],
                'default'      => $args['default'],
                'show_in_rest' => false,
                'auth_callback' => array($this, 'meta_auth_callback'),
                'sanitize_callback' => array($this, 'sanitize_meta_callback'),
            ));
        }
    }
    
    /**
     * Meta field authorization callback
     * 
     * @param bool   $allowed
     * @param string $meta_key
     * @param int    $post_id
     * @param int    $user_id
     * @param string $cap
     * @param array  $caps
     * @return bool
     */
    public function meta_auth_callback($allowed, $meta_key, $post_id, $user_id, $cap, $caps) {
        // Check if user can edit this post type
        if (!current_user_can('edit_tpak_survey_data', $post_id)) {
            return false;
        }
        
        // Additional checks based on workflow status and user role
        $workflow_status = get_post_meta($post_id, '_tpak_workflow_status', true);
        $user = wp_get_current_user();
        
        // Read-only fields for certain roles
        $readonly_fields = array('_tpak_survey_id', '_tpak_response_id', '_tpak_import_date');
        if (in_array($meta_key, $readonly_fields) && !current_user_can('manage_options')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Sanitize meta field callback
     * 
     * @param mixed  $meta_value
     * @param string $meta_key
     * @param string $object_type
     * @return mixed
     */
    public function sanitize_meta_callback($meta_value, $meta_key, $object_type) {
        switch ($meta_key) {
            case '_tpak_survey_data':
            case '_tpak_audit_trail':
                // Validate JSON format
                if (!empty($meta_value)) {
                    $decoded = json_decode($meta_value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return '{}'; // Return empty object for invalid JSON
                    }
                    return wp_json_encode($decoded); // Re-encode to ensure proper format
                }
                return '{}';
                
            case '_tpak_survey_id':
            case '_tpak_response_id':
                return sanitize_text_field($meta_value);
                
            case '_tpak_workflow_status':
                $valid_statuses = array_keys($this->get_workflow_statuses());
                return in_array($meta_value, $valid_statuses) ? $meta_value : 'pending_a';
                
            case '_tpak_assigned_user':
                return absint($meta_value);
                
            case '_tpak_import_date':
            case '_tpak_last_modified':
            case '_tpak_completion_date':
                return sanitize_text_field($meta_value);
                
            default:
                return sanitize_text_field($meta_value);
        }
    }
}