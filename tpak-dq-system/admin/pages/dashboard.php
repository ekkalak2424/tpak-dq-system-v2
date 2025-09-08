<?php
/**
 * Dashboard Page Template
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current user info
$current_user = wp_get_current_user();
$roles_instance = TPAK_Roles::get_instance();
$user_role = $roles_instance->get_user_tpak_role($current_user->ID);
?>

<div class="tpak-dashboard">
    <div class="tpak-dashboard-widgets">
        
        <!-- Welcome Widget -->
        <div class="tpak-widget tpak-welcome-widget">
            <h3><?php _e('Welcome to TPAK DQ System', 'tpak-dq-system'); ?></h3>
            <p><?php printf(__('Hello %s! You are logged in as: %s', 'tpak-dq-system'), 
                esc_html($current_user->display_name), 
                esc_html($roles_instance->get_role_display_name($user_role))
            ); ?></p>
            
            <?php if ($user_role): ?>
                <p><em><?php echo esc_html($roles_instance->get_role_description($user_role)); ?></em></p>
            <?php endif; ?>
        </div>
        
        <!-- Statistics Widget -->
        <div class="tpak-widget tpak-stats-widget">
            <h3><?php _e('Your Statistics', 'tpak-dq-system'); ?></h3>
            <div class="tpak-stats-grid">
                <?php
                // Role-based statistics (placeholder - will be implemented in dashboard task)
                $stats = $this->get_user_statistics($current_user->ID, $user_role);
                
                foreach ($stats as $stat_key => $stat_value) {
                    printf(
                        '<div class="tpak-stat-item">
                            <div class="tpak-stat-number">%s</div>
                            <div class="tpak-stat-label">%s</div>
                        </div>',
                        esc_html($stat_value),
                        esc_html($stat_key)
                    );
                }
                ?>
            </div>
        </div>
        
        <!-- Quick Actions Widget -->
        <div class="tpak-widget tpak-actions-widget">
            <h3><?php _e('Quick Actions', 'tpak-dq-system'); ?></h3>
            <div class="tpak-actions-list">
                <?php if (current_user_can('tpak_view_assigned_data')): ?>
                    <a href="<?php echo admin_url('admin.php?page=tpak-data'); ?>" class="button button-primary">
                        <?php _e('View My Data', 'tpak-dq-system'); ?>
                    </a>
                <?php endif; ?>
                
                <?php if (current_user_can('tpak_manage_settings')): ?>
                    <a href="<?php echo admin_url('admin.php?page=tpak-settings'); ?>" class="button">
                        <?php _e('Settings', 'tpak-dq-system'); ?>
                    </a>
                <?php endif; ?>
                
                <?php if (current_user_can('tpak_import_data')): ?>
                    <a href="<?php echo admin_url('admin.php?page=tpak-import-export'); ?>" class="button">
                        <?php _e('Import/Export', 'tpak-dq-system'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- System Status Widget (Admin only) -->
        <?php if (current_user_can('tpak_manage_settings')): ?>
        <div class="tpak-widget tpak-status-widget">
            <h3><?php _e('System Status', 'tpak-dq-system'); ?></h3>
            <div class="tpak-status-items">
                <?php
                // System status checks (placeholder)
                $status_items = array(
                    'API Connection' => 'unknown',
                    'Cron Jobs' => 'unknown',
                    'Database' => 'ok',
                    'Notifications' => 'unknown',
                );
                
                foreach ($status_items as $item => $status) {
                    $status_class = 'tpak-status-' . $status;
                    $status_text = ucfirst($status);
                    
                    printf(
                        '<div class="tpak-status-item %s">
                            <span class="tpak-status-label">%s:</span>
                            <span class="tpak-status-value">%s</span>
                        </div>',
                        esc_attr($status_class),
                        esc_html($item),
                        esc_html($status_text)
                    );
                }
                ?>
            </div>
            <p>
                <a href="<?php echo admin_url('admin.php?page=tpak-status'); ?>">
                    <?php _e('View detailed status', 'tpak-dq-system'); ?>
                </a>
            </p>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<?php
/**
 * Get user statistics (placeholder function)
 * This will be properly implemented in the dashboard task
 */
function get_user_statistics($user_id, $user_role) {
    // Placeholder statistics based on role
    switch ($user_role) {
        case 'tpak_interviewer_a':
            return array(
                'Pending Review' => '0',
                'Completed Today' => '0',
                'Rejected Items' => '0',
            );
            
        case 'tpak_supervisor_b':
            return array(
                'Pending Approval' => '0',
                'Approved Today' => '0',
                'Sent to Examiner' => '0',
            );
            
        case 'tpak_examiner_c':
            return array(
                'Final Review' => '0',
                'Finalized Today' => '0',
                'Rejected Items' => '0',
            );
            
        default:
            return array(
                'Total Records' => '0',
                'Active Users' => '0',
                'System Health' => 'OK',
            );
    }
}
?>