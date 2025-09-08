<?php
/**
 * Cron Monitor Class
 * 
 * Advanced monitoring and alerting for cron jobs
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Cron Monitor Class
 */
class TPAK_Cron_Monitor {
    
    /**
     * Single instance
     * 
     * @var TPAK_Cron_Monitor
     */
    private static $instance = null;
    
    /**
     * Get instance
     * 
     * @return TPAK_Cron_Monitor
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
        add_action('tpak_dq_cron_monitor', array($this, 'run_monitoring_checks'));
        add_action('init', array($this, 'schedule_monitoring'));
    }
    
    /**
     * Schedule monitoring checks
     */
    public function schedule_monitoring() {
        if (!wp_next_scheduled('tpak_dq_cron_monitor')) {
            wp_schedule_event(time(), 'hourly', 'tpak_dq_cron_monitor');
        }
    }
    
    /**
     * Run monitoring checks
     */
    public function run_monitoring_checks() {
        $this->check_missed_runs();
        $this->check_failure_rate();
        $this->check_execution_time();
        $this->cleanup_old_logs();
    }
    
    /**
     * Check for missed cron runs
     */
    private function check_missed_runs() {
        $next_scheduled = wp_next_scheduled(TPAK_Cron::CRON_HOOK);
        
        if (!$next_scheduled) {
            return; // No job scheduled
        }
        
        $current_time = time();
        $grace_period = 15 * MINUTE_IN_SECONDS; // 15 minutes grace period
        
        // Check if the scheduled time has passed beyond grace period
        if ($next_scheduled + $grace_period < $current_time) {
            $this->alert_missed_run($next_scheduled, $current_time);
        }
    }
    
    /**
     * Check failure rate
     */
    private function check_failure_rate() {
        $cron = TPAK_Cron::get_instance();
        $recent_logs = $cron->get_cron_logs(array(), 10); // Last 10 runs
        
        if (count($recent_logs) < 5) {
            return; // Not enough data
        }
        
        $failures = 0;
        foreach ($recent_logs as $log) {
            if ($log['event'] === 'import_failed') {
                $failures++;
            }
        }
        
        $failure_rate = ($failures / count($recent_logs)) * 100;
        
        // Alert if failure rate is above 50%
        if ($failure_rate > 50) {
            $this->alert_high_failure_rate($failure_rate, $failures, count($recent_logs));
        }
    }
    
    /**
     * Check execution time
     */
    private function check_execution_time() {
        $stats = get_option('tpak_dq_import_stats', array());
        
        if (empty($stats['average_execution_time'])) {
            return;
        }
        
        $cron = TPAK_Cron::get_instance();
        $recent_logs = $cron->get_cron_logs(array('event' => 'import_success'), 5);
        
        if (empty($recent_logs)) {
            return;
        }
        
        // Check if recent runs are significantly slower
        $recent_times = array();
        foreach ($recent_logs as $log) {
            if (isset($log['data']['execution_time'])) {
                $recent_times[] = $log['data']['execution_time'];
            }
        }
        
        if (empty($recent_times)) {
            return;
        }
        
        $recent_average = array_sum($recent_times) / count($recent_times);
        $threshold = $stats['average_execution_time'] * 2; // 200% of average
        
        if ($recent_average > $threshold) {
            $this->alert_slow_execution($recent_average, $stats['average_execution_time']);
        }
    }
    
    /**
     * Cleanup old logs
     */
    private function cleanup_old_logs() {
        $logs = get_option('tpak_dq_cron_logs', array());
        
        if (count($logs) > 200) {
            // Keep only the most recent 100 logs
            $logs = array_slice($logs, -100);
            update_option('tpak_dq_cron_logs', $logs);
        }
        
        // Also cleanup old workflow logs
        $workflow_logs = get_option('tpak_dq_workflow_logs', array());
        
        if (count($workflow_logs) > 2000) {
            // Keep only the most recent 1000 logs
            $workflow_logs = array_slice($workflow_logs, -1000);
            update_option('tpak_dq_workflow_logs', $workflow_logs);
        }
    }
    
    /**
     * Alert for missed run
     * 
     * @param int $scheduled_time
     * @param int $current_time
     */
    private function alert_missed_run($scheduled_time, $current_time) {
        $delay_minutes = round(($current_time - $scheduled_time) / MINUTE_IN_SECONDS);
        
        $this->log_alert('missed_run', array(
            'scheduled_time' => $scheduled_time,
            'current_time' => $current_time,
            'delay_minutes' => $delay_minutes
        ));
        
        // Send notification if enabled
        $this->send_alert_notification(
            __('TPAK DQ System: Missed Cron Run', 'tpak-dq-system'),
            sprintf(
                __('The scheduled import job was missed by %d minutes. Scheduled time: %s', 'tpak-dq-system'),
                $delay_minutes,
                date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $scheduled_time)
            )
        );
    }
    
    /**
     * Alert for high failure rate
     * 
     * @param float $failure_rate
     * @param int   $failures
     * @param int   $total_runs
     */
    private function alert_high_failure_rate($failure_rate, $failures, $total_runs) {
        $this->log_alert('high_failure_rate', array(
            'failure_rate' => $failure_rate,
            'failures' => $failures,
            'total_runs' => $total_runs
        ));
        
        $this->send_alert_notification(
            __('TPAK DQ System: High Import Failure Rate', 'tpak-dq-system'),
            sprintf(
                __('Import failure rate is %.1f%% (%d failures out of %d recent runs). Please check the system.', 'tpak-dq-system'),
                $failure_rate,
                $failures,
                $total_runs
            )
        );
    }
    
    /**
     * Alert for slow execution
     * 
     * @param float $recent_average
     * @param float $overall_average
     */
    private function alert_slow_execution($recent_average, $overall_average) {
        $this->log_alert('slow_execution', array(
            'recent_average' => $recent_average,
            'overall_average' => $overall_average,
            'slowdown_factor' => round($recent_average / $overall_average, 2)
        ));
        
        $this->send_alert_notification(
            __('TPAK DQ System: Slow Import Performance', 'tpak-dq-system'),
            sprintf(
                __('Recent imports are running slower than usual. Recent average: %.2f seconds, Overall average: %.2f seconds.', 'tpak-dq-system'),
                $recent_average,
                $overall_average
            )
        );
    }
    
    /**
     * Log alert
     * 
     * @param string $alert_type
     * @param array  $data
     */
    private function log_alert($alert_type, $data = array()) {
        $alert_entry = array(
            'timestamp' => current_time('mysql'),
            'alert_type' => $alert_type,
            'data' => $data
        );
        
        $alerts = get_option('tpak_dq_cron_alerts', array());
        $alerts[] = $alert_entry;
        
        // Keep only last 50 alerts
        if (count($alerts) > 50) {
            $alerts = array_slice($alerts, -50);
        }
        
        update_option('tpak_dq_cron_alerts', $alerts);
        
        // Also log to error log
        error_log(sprintf(
            'TPAK DQ System Alert [%s]: %s',
            $alert_type,
            json_encode($data)
        ));
    }
    
    /**
     * Send alert notification
     * 
     * @param string $subject
     * @param string $message
     */
    private function send_alert_notification($subject, $message) {
        $settings = get_option('tpak_dq_settings', array());
        $notifications_enabled = isset($settings['notifications']['email_enabled']) 
            ? $settings['notifications']['email_enabled'] 
            : false;
        
        if (!$notifications_enabled) {
            return;
        }
        
        // Get admin email
        $admin_email = get_option('admin_email');
        
        // Add additional notification emails if configured
        $notification_emails = array($admin_email);
        if (!empty($settings['notifications']['alert_emails'])) {
            $additional_emails = is_array($settings['notifications']['alert_emails']) 
                ? $settings['notifications']['alert_emails']
                : explode(',', $settings['notifications']['alert_emails']);
            
            foreach ($additional_emails as $email) {
                $email = trim($email);
                if (is_email($email) && !in_array($email, $notification_emails)) {
                    $notification_emails[] = $email;
                }
            }
        }
        
        // Send notification
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        $html_message = sprintf(
            '<h2>%s</h2><p>%s</p><p><strong>Time:</strong> %s</p><p><strong>Site:</strong> %s</p>',
            esc_html($subject),
            esc_html($message),
            current_time('mysql'),
            get_site_url()
        );
        
        foreach ($notification_emails as $email) {
            wp_mail($email, $subject, $html_message, $headers);
        }
    }
    
    /**
     * Get monitoring alerts
     * 
     * @param array $filters
     * @param int   $limit
     * @return array
     */
    public function get_alerts($filters = array(), $limit = 20) {
        $alerts = get_option('tpak_dq_cron_alerts', array());
        
        // Apply filters
        if (!empty($filters)) {
            $alerts = array_filter($alerts, function($alert) use ($filters) {
                if (!empty($filters['alert_type']) && $alert['alert_type'] !== $filters['alert_type']) {
                    return false;
                }
                
                if (!empty($filters['date_from']) && $alert['timestamp'] < $filters['date_from']) {
                    return false;
                }
                
                if (!empty($filters['date_to']) && $alert['timestamp'] > $filters['date_to']) {
                    return false;
                }
                
                return true;
            });
        }
        
        // Sort by timestamp (newest first)
        usort($alerts, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Apply limit
        if ($limit > 0) {
            $alerts = array_slice($alerts, 0, $limit);
        }
        
        return $alerts;
    }
    
    /**
     * Clear monitoring alerts
     * 
     * @return bool
     */
    public function clear_alerts() {
        return delete_option('tpak_dq_cron_alerts');
    }
    
    /**
     * Get monitoring statistics
     * 
     * @return array
     */
    public function get_monitoring_stats() {
        $alerts = get_option('tpak_dq_cron_alerts', array());
        $cron = TPAK_Cron::get_instance();
        $recent_logs = $cron->get_cron_logs(array(), 50);
        
        // Count alert types
        $alert_counts = array();
        foreach ($alerts as $alert) {
            $type = $alert['alert_type'];
            $alert_counts[$type] = isset($alert_counts[$type]) ? $alert_counts[$type] + 1 : 1;
        }
        
        // Calculate uptime (successful runs vs total runs)
        $successful_runs = 0;
        $total_runs = 0;
        
        foreach ($recent_logs as $log) {
            if (in_array($log['event'], array('import_success', 'import_failed'))) {
                $total_runs++;
                if ($log['event'] === 'import_success') {
                    $successful_runs++;
                }
            }
        }
        
        $uptime_percentage = $total_runs > 0 ? ($successful_runs / $total_runs) * 100 : 0;
        
        return array(
            'total_alerts' => count($alerts),
            'alert_counts' => $alert_counts,
            'uptime_percentage' => round($uptime_percentage, 2),
            'successful_runs' => $successful_runs,
            'total_runs' => $total_runs,
            'last_alert' => !empty($alerts) ? end($alerts)['timestamp'] : null
        );
    }
    
    /**
     * Disable monitoring
     */
    public function disable_monitoring() {
        wp_clear_scheduled_hook('tpak_dq_cron_monitor');
    }
    
    /**
     * Enable monitoring
     */
    public function enable_monitoring() {
        $this->schedule_monitoring();
    }
}