<?php
/**
 * Role Manager Utility Class
 * 
 * Provides utility functions for role management in admin interface
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Role Manager Class
 */
class TPAK_Role_Manager {
    
    /**
     * Single instance
     * 
     * @var TPAK_Role_Manager
     */
    private static $instance = null;
    
    /**
     * Get instance
     * 
     * @return TPAK_Role_Manager
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
        add_action('admin_init', array($this, 'handle_role_actions'));
    }
    
    /**
     * Handle role management actions
     */
    public function handle_role_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_POST['tpak_assign_role']) && wp_verify_nonce($_POST['tpak_role_nonce'], 'tpak_assign_role')) {
            $this->handle_role_assignment();
        }
        
        if (isset($_POST['tpak_remove_role']) && wp_verify_nonce($_POST['tpak_role_nonce'], 'tpak_remove_role')) {
            $this->handle_role_removal();
        }
    }
    
    /**
     * Handle role assignment
     */
    private function handle_role_assignment() {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $role_name = isset($_POST['role_name']) ? sanitize_text_field($_POST['role_name']) : '';
        
        if (!$user_id || !$role_name) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Invalid user or role specified.', 'tpak-dq-system') . '</p></div>';
            });
            return;
        }
        
        $roles = TPAK_Roles::get_instance();
        $result = $roles->assign_role_to_user($user_id, $role_name);
        
        if ($result) {
            add_action('admin_notices', function() use ($role_name) {
                $display_name = TPAK_Roles::get_instance()->get_role_display_name($role_name);
                echo '<div class="notice notice-success"><p>' . 
                     sprintf(esc_html__('Role "%s" assigned successfully.', 'tpak-dq-system'), esc_html($display_name)) . 
                     '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Failed to assign role.', 'tpak-dq-system') . '</p></div>';
            });
        }
    }
    
    /**
     * Handle role removal
     */
    private function handle_role_removal() {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $role_name = isset($_POST['role_name']) ? sanitize_text_field($_POST['role_name']) : '';
        
        if (!$user_id) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Invalid user specified.', 'tpak-dq-system') . '</p></div>';
            });
            return;
        }
        
        $roles = TPAK_Roles::get_instance();
        $result = $roles->remove_role_from_user($user_id, $role_name);
        
        if ($result) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . esc_html__('Role removed successfully.', 'tpak-dq-system') . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . esc_html__('Failed to remove role.', 'tpak-dq-system') . '</p></div>';
            });
        }
    }
    
    /**
     * Get role assignment form HTML
     * 
     * @param int $user_id
     * @return string
     */
    public function get_role_assignment_form($user_id) {
        $roles = TPAK_Roles::get_instance();
        $tpak_roles = $roles->get_tpak_roles();
        $current_role = $roles->get_user_tpak_role($user_id);
        
        ob_start();
        ?>
        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field('tpak_assign_role', 'tpak_role_nonce'); ?>
            <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
            
            <select name="role_name">
                <option value=""><?php esc_html_e('Select Role', 'tpak-dq-system'); ?></option>
                <?php foreach ($tpak_roles as $role_name => $display_name): ?>
                    <option value="<?php echo esc_attr($role_name); ?>" <?php selected($current_role, $role_name); ?>>
                        <?php echo esc_html($display_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="submit" name="tpak_assign_role" class="button button-secondary" 
                   value="<?php esc_attr_e('Assign Role', 'tpak-dq-system'); ?>">
            
            <?php if ($current_role): ?>
                <input type="submit" name="tpak_remove_role" class="button button-secondary" 
                       value="<?php esc_attr_e('Remove Role', 'tpak-dq-system'); ?>"
                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to remove this role?', 'tpak-dq-system'); ?>');">
            <?php endif; ?>
        </form>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get users table with role information
     * 
     * @return string
     */
    public function get_users_table() {
        $roles = TPAK_Roles::get_instance();
        $users = get_users(array('fields' => array('ID', 'display_name', 'user_email')));
        
        ob_start();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('User', 'tpak-dq-system'); ?></th>
                    <th><?php esc_html_e('Email', 'tpak-dq-system'); ?></th>
                    <th><?php esc_html_e('Current TPAK Role', 'tpak-dq-system'); ?></th>
                    <th><?php esc_html_e('Actions', 'tpak-dq-system'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <?php 
                    $current_role = $roles->get_user_tpak_role($user->ID);
                    $role_display = $current_role ? $roles->get_role_display_name($current_role) : __('None', 'tpak-dq-system');
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td>
                            <?php echo esc_html($role_display); ?>
                            <?php if ($current_role): ?>
                                <br><small style="color: #666;">
                                    <?php echo esc_html($roles->get_role_description($current_role)); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $this->get_role_assignment_form($user->ID); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get role statistics
     * 
     * @return array
     */
    public function get_role_statistics() {
        $roles = TPAK_Roles::get_instance();
        $tpak_roles = $roles->get_tpak_roles();
        $stats = array();
        
        foreach ($tpak_roles as $role_name => $display_name) {
            $users = $roles->get_users_by_role($role_name);
            $stats[$role_name] = array(
                'display_name' => $display_name,
                'user_count' => count($users),
                'users' => $users,
            );
        }
        
        return $stats;
    }
    
    /**
     * Get role statistics widget HTML
     * 
     * @return string
     */
    public function get_role_statistics_widget() {
        $stats = $this->get_role_statistics();
        
        ob_start();
        ?>
        <div class="tpak-role-stats">
            <h3><?php esc_html_e('Role Statistics', 'tpak-dq-system'); ?></h3>
            
            <?php foreach ($stats as $role_name => $data): ?>
                <div class="tpak-role-stat-item">
                    <h4><?php echo esc_html($data['display_name']); ?></h4>
                    <p>
                        <strong><?php echo esc_html($data['user_count']); ?></strong> 
                        <?php echo esc_html(_n('user', 'users', $data['user_count'], 'tpak-dq-system')); ?>
                    </p>
                    
                    <?php if (!empty($data['users'])): ?>
                        <ul style="margin-left: 20px; font-size: 12px;">
                            <?php foreach ($data['users'] as $user): ?>
                                <li><?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <style>
        .tpak-role-stats {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 15px;
            margin: 15px 0;
        }
        .tpak-role-stat-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .tpak-role-stat-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .tpak-role-stat-item h4 {
            margin: 0 0 5px 0;
            color: #23282d;
        }
        .tpak-role-stat-item p {
            margin: 0 0 10px 0;
        }
        .tpak-role-stat-item ul {
            margin: 0;
            color: #666;
        }
        </style>
        <?php
        return ob_get_clean();
    }
}