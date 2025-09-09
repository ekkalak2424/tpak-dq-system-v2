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

// Get dashboard statistics
$stats_instance = TPAK_Dashboard_Stats::get_instance();
$user_stats = $stats_instance->get_user_statistics($current_user->ID, $user_role);
$chart_data = $stats_instance->get_workflow_chart_data();
$activity_data = $stats_instance->get_daily_activity_stats();
$performance_metrics = $stats_instance->get_performance_metrics();
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
            <h3>
                <?php _e('Your Statistics', 'tpak-dq-system'); ?>
                <button type="button" class="button button-small tpak-refresh-stats" title="<?php _e('Refresh Statistics', 'tpak-dq-system'); ?>">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </h3>
            <div class="tpak-stats-grid" id="tpak-stats-grid">
                <?php foreach ($user_stats as $stat_key => $stat_value): ?>
                    <div class="tpak-stat-item">
                        <div class="tpak-stat-number"><?php echo esc_html($stat_value); ?></div>
                        <div class="tpak-stat-label"><?php echo esc_html($stat_key); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="tpak-stats-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <?php _e('Refreshing statistics...', 'tpak-dq-system'); ?>
            </div>
        </div>
        
        <!-- Workflow Chart Widget -->
        <?php if (current_user_can('tpak_view_admin_stats') && !empty($chart_data['data'])): ?>
        <div class="tpak-widget tpak-chart-widget">
            <h3><?php _e('Workflow Status Distribution', 'tpak-dq-system'); ?></h3>
            <div class="tpak-chart-container">
                <canvas id="tpak-workflow-chart" width="400" height="200"></canvas>
            </div>
            <div class="tpak-chart-legend">
                <?php foreach ($chart_data['labels'] as $index => $label): ?>
                    <div class="tpak-legend-item">
                        <span class="tpak-legend-color" style="background-color: <?php echo esc_attr($chart_data['colors'][$index]); ?>"></span>
                        <span class="tpak-legend-label"><?php echo esc_html($label); ?></span>
                        <span class="tpak-legend-value"><?php echo esc_html($chart_data['data'][$index]); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Activity Chart Widget -->
        <?php if (current_user_can('tpak_view_admin_stats') && !empty($activity_data)): ?>
        <div class="tpak-widget tpak-activity-widget">
            <h3><?php _e('Daily Activity (Last 30 Days)', 'tpak-dq-system'); ?></h3>
            <div class="tpak-chart-container">
                <canvas id="tpak-activity-chart" width="600" height="200"></canvas>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Performance Metrics Widget -->
        <?php if (current_user_can('tpak_view_admin_stats') && !empty($performance_metrics)): ?>
        <div class="tpak-widget tpak-performance-widget">
            <h3><?php _e('Performance Metrics', 'tpak-dq-system'); ?></h3>
            <div class="tpak-performance-grid">
                <?php if (isset($performance_metrics['throughput'])): ?>
                    <div class="tpak-performance-item">
                        <div class="tpak-performance-label"><?php _e('Today\'s Throughput', 'tpak-dq-system'); ?></div>
                        <div class="tpak-performance-value">
                            <?php echo esc_html($performance_metrics['throughput']['today']); ?>
                            <?php if ($performance_metrics['throughput']['change'] != 0): ?>
                                <span class="tpak-performance-change <?php echo $performance_metrics['throughput']['change'] > 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo $performance_metrics['throughput']['change'] > 0 ? '+' : ''; ?>
                                    <?php echo esc_html($performance_metrics['throughput']['change']); ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($performance_metrics['avg_processing_time'])): ?>
                    <?php foreach ($performance_metrics['avg_processing_time'] as $status => $hours): ?>
                        <div class="tpak-performance-item">
                            <div class="tpak-performance-label">
                                <?php printf(__('Avg. %s Processing', 'tpak-dq-system'), esc_html(ucfirst(str_replace('_', ' ', $status)))); ?>
                            </div>
                            <div class="tpak-performance-value">
                                <?php echo esc_html($hours); ?> <?php _e('hours', 'tpak-dq-system'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
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
                // System status checks
                $api_settings = get_option('tpak_dq_settings', array());
                $api_status = (!empty($api_settings['api_url']) && !empty($api_settings['api_username'])) ? 'ok' : 'error';
                
                $cron_status = wp_next_scheduled('tpak_import_survey_data') ? 'ok' : 'warning';
                
                $last_import = get_option('tpak_last_import_date', '');
                $import_status = (!empty($last_import) && strtotime($last_import) > strtotime('-24 hours')) ? 'ok' : 'warning';
                
                $notification_settings = get_option('tpak_notification_settings', array());
                $notification_status = isset($notification_settings['enabled']) && $notification_settings['enabled'] ? 'ok' : 'warning';
                
                $status_items = array(
                    'API Connection' => $api_status,
                    'Cron Jobs' => $cron_status,
                    'Recent Import' => $import_status,
                    'Notifications' => $notification_status,
                );
                
                foreach ($status_items as $item => $status) {
                    $status_class = 'tpak-status-' . $status;
                    $status_text = ucfirst($status);
                    $status_icon = $status === 'ok' ? 'yes-alt' : ($status === 'warning' ? 'warning' : 'dismiss');
                    
                    printf(
                        '<div class="tpak-status-item %s">
                            <span class="dashicons dashicons-%s"></span>
                            <span class="tpak-status-label">%s:</span>
                            <span class="tpak-status-value">%s</span>
                        </div>',
                        esc_attr($status_class),
                        esc_attr($status_icon),
                        esc_html($item),
                        esc_html($status_text)
                    );
                }
                ?>
            </div>
            <p>
                <a href="<?php echo admin_url('admin.php?page=tpak-status'); ?>" class="button">
                    <?php _e('View Detailed Status', 'tpak-dq-system'); ?>
                </a>
            </p>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script type="text/javascript">
// Dashboard chart data
var tpakDashboardData = {
    workflowChart: <?php echo wp_json_encode($chart_data); ?>,
    activityData: <?php echo wp_json_encode($activity_data); ?>,
    performanceMetrics: <?php echo wp_json_encode($performance_metrics); ?>
};
</script>