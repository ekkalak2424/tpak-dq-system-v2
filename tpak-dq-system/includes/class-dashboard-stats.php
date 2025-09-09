<?php
/**
 * Dashboard Statistics Class
 * 
 * Handles statistics calculation and reporting for the dashboard
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Dashboard Statistics Class
 */
class TPAK_Dashboard_Stats {
    
    /**
     * Single instance
     * 
     * @var TPAK_Dashboard_Stats
     */
    private static $instance = null;
    
    /**
     * Cache duration in seconds (5 minutes)
     * 
     * @var int
     */
    private $cache_duration = 300;
    
    /**
     * Get instance
     * 
     * @return TPAK_Dashboard_Stats
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
        add_action('wp_ajax_tpak_refresh_stats', array($this, 'ajax_refresh_stats'));
        add_action('tpak_workflow_status_changed', array($this, 'clear_stats_cache'));
        add_action('tpak_data_imported', array($this, 'clear_stats_cache'));
    }
    
    /**
     * Get user statistics based on role
     * 
     * @param int    $user_id
     * @param string $user_role
     * @return array
     */
    public function get_user_statistics($user_id, $user_role) {
        $cache_key = "tpak_user_stats_{$user_id}_{$user_role}";
        $cached_stats = get_transient($cache_key);
        
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        $stats = array();
        
        switch ($user_role) {
            case 'tpak_interviewer_a':
                $stats = $this->get_interviewer_stats($user_id);
                break;
                
            case 'tpak_supervisor_b':
                $stats = $this->get_supervisor_stats($user_id);
                break;
                
            case 'tpak_examiner_c':
                $stats = $this->get_examiner_stats($user_id);
                break;
                
            default:
                // Administrator or other roles
                if (current_user_can('tpak_manage_settings')) {
                    $stats = $this->get_admin_stats();
                } else {
                    $stats = $this->get_basic_stats($user_id);
                }
                break;
        }
        
        // Cache the results
        set_transient($cache_key, $stats, $this->cache_duration);
        
        return $stats;
    }
    
    /**
     * Get statistics for Interviewer A role
     * 
     * @param int $user_id
     * @return array
     */
    private function get_interviewer_stats($user_id) {
        global $wpdb;
        
        $stats = array();
        
        // Pending review (assigned to this user or unassigned pending_a)
        $pending_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_assigned_user'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value = 'pending_a'
            AND (pm2.meta_value = %d OR pm2.meta_value = '0' OR pm2.meta_value IS NULL)
        ", $user_id);
        
        $stats['Pending Review'] = $wpdb->get_var($pending_query);
        
        // Rejected items (rejected_by_b assigned to this user)
        $rejected_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_assigned_user'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value = 'rejected_by_b'
            AND pm2.meta_value = %d
        ", $user_id);
        
        $stats['Rejected Items'] = $wpdb->get_var($rejected_query);
        
        // Completed today (approved to supervisor today)
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $completed_today_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_audit_trail'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value IN ('pending_b', 'finalized', 'finalized_by_sampling', 'pending_c')
            AND pm2.meta_value LIKE %s
            AND p.post_modified >= %s
            AND p.post_modified <= %s
        ", '%"user_id":' . $user_id . '%"action":"approve_to_supervisor"%', $today_start, $today_end);
        
        $stats['Completed Today'] = $wpdb->get_var($completed_today_query);
        
        // Total assigned
        $total_assigned_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_assigned_user'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value IN ('pending_a', 'rejected_by_b')
            AND pm2.meta_value = %d
        ", $user_id);
        
        $stats['Total Assigned'] = $wpdb->get_var($total_assigned_query);
        
        return $stats;
    }
    
    /**
     * Get statistics for Supervisor B role
     * 
     * @param int $user_id
     * @return array
     */
    private function get_supervisor_stats($user_id) {
        global $wpdb;
        
        $stats = array();
        
        // Pending approval (pending_b status)
        $pending_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_assigned_user'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value = 'pending_b'
            AND (pm2.meta_value = %d OR pm2.meta_value = '0' OR pm2.meta_value IS NULL)
        ", $user_id);
        
        $stats['Pending Approval'] = $wpdb->get_var($pending_query);
        
        // Approved today (finalized_by_sampling today)
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $approved_today_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_audit_trail'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value = 'finalized_by_sampling'
            AND pm2.meta_value LIKE %s
            AND p.post_modified >= %s
            AND p.post_modified <= %s
        ", '%"user_id":' . $user_id . '%"action":"finalize_by_sampling"%', $today_start, $today_end);
        
        $stats['Approved Today'] = $wpdb->get_var($approved_today_query);
        
        // Sent to examiner today
        $sent_examiner_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_audit_trail'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value = 'pending_c'
            AND pm2.meta_value LIKE %s
            AND p.post_modified >= %s
            AND p.post_modified <= %s
        ", '%"user_id":' . $user_id . '%"action":"send_to_examiner"%', $today_start, $today_end);
        
        $stats['Sent to Examiner'] = $wpdb->get_var($sent_examiner_query);
        
        // Rejected items
        $rejected_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_audit_trail'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value = 'rejected_by_b'
            AND pm2.meta_value LIKE %s
        ", '%"user_id":' . $user_id . '%"action":"reject_to_interviewer"%');
        
        $stats['Rejected Items'] = $wpdb->get_var($rejected_query);
        
        return $stats;
    }
    
    /**
     * Get statistics for Examiner C role
     * 
     * @param int $user_id
     * @return array
     */
    private function get_examiner_stats($user_id) {
        global $wpdb;
        
        $stats = array();
        
        // Final review (pending_c status)
        $pending_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_assigned_user'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value = 'pending_c'
            AND (pm2.meta_value = %d OR pm2.meta_value = '0' OR pm2.meta_value IS NULL)
        ", $user_id);
        
        $stats['Final Review'] = $wpdb->get_var($pending_query);
        
        // Finalized today
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        
        $finalized_today_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_audit_trail'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value = 'finalized'
            AND pm2.meta_value LIKE %s
            AND p.post_modified >= %s
            AND p.post_modified <= %s
        ", '%"user_id":' . $user_id . '%"action":"final_approval"%', $today_start, $today_end);
        
        $stats['Finalized Today'] = $wpdb->get_var($finalized_today_query);
        
        // Rejected items
        $rejected_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tpak_audit_trail'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value = 'rejected_by_c'
            AND pm2.meta_value LIKE %s
        ", '%"user_id":' . $user_id . '%"action":"reject_to_supervisor"%');
        
        $stats['Rejected Items'] = $wpdb->get_var($rejected_query);
        
        // Total processed
        $total_processed_query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_audit_trail'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND pm1.meta_value LIKE %s
        ", '%"user_id":' . $user_id . '%"action":"final_approval"%');
        
        $stats['Total Processed'] = $wpdb->get_var($total_processed_query);
        
        return $stats;
    }
    
    /**
     * Get administrator statistics
     * 
     * @return array
     */
    private function get_admin_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total records
        $total_query = "
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'tpak_survey_data' 
            AND post_status = 'publish'
        ";
        
        $stats['Total Records'] = $wpdb->get_var($total_query);
        
        // Active users (users with TPAK roles)
        $roles_instance = TPAK_Roles::get_instance();
        $tpak_roles = array_keys($roles_instance->get_tpak_roles());
        $active_users = 0;
        
        foreach ($tpak_roles as $role) {
            $users = get_users(array('role' => $role, 'fields' => 'ID'));
            $active_users += count($users);
        }
        
        $stats['Active Users'] = $active_users;
        
        // Pending items by status
        $status_counts = $this->get_status_counts();
        $stats['Pending A'] = $status_counts['pending_a'] ?? 0;
        $stats['Pending B'] = $status_counts['pending_b'] ?? 0;
        $stats['Pending C'] = $status_counts['pending_c'] ?? 0;
        $stats['Finalized'] = ($status_counts['finalized'] ?? 0) + ($status_counts['finalized_by_sampling'] ?? 0);
        
        // System health
        $stats['System Health'] = $this->get_system_health_status();
        
        return $stats;
    }
    
    /**
     * Get basic statistics for users without specific TPAK roles
     * 
     * @param int $user_id
     * @return array
     */
    private function get_basic_stats($user_id) {
        global $wpdb;
        
        $stats = array();
        
        // Total records
        $total_query = "
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'tpak_survey_data' 
            AND post_status = 'publish'
        ";
        
        $stats['Total Records'] = $wpdb->get_var($total_query);
        
        // System status
        $stats['System Status'] = 'Active';
        
        // Last import
        $last_import = get_option('tpak_last_import_date', '');
        $stats['Last Import'] = $last_import ? date('M j, Y', strtotime($last_import)) : 'Never';
        
        return $stats;
    }
    
    /**
     * Get counts by workflow status
     * 
     * @return array
     */
    public function get_status_counts() {
        global $wpdb;
        
        $cache_key = 'tpak_status_counts';
        $cached_counts = get_transient($cache_key);
        
        if ($cached_counts !== false) {
            return $cached_counts;
        }
        
        $query = "
            SELECT pm.meta_value as status, COUNT(*) as count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_tpak_workflow_status'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            GROUP BY pm.meta_value
        ";
        
        $results = $wpdb->get_results($query);
        $counts = array();
        
        foreach ($results as $result) {
            $counts[$result->status] = (int) $result->count;
        }
        
        // Cache the results
        set_transient($cache_key, $counts, $this->cache_duration);
        
        return $counts;
    }
    
    /**
     * Get workflow statistics for charts
     * 
     * @return array
     */
    public function get_workflow_chart_data() {
        $status_counts = $this->get_status_counts();
        $post_types = TPAK_Post_Types::get_instance();
        $status_labels = $post_types->get_workflow_statuses();
        
        $chart_data = array(
            'labels' => array(),
            'data' => array(),
            'colors' => array(),
        );
        
        $colors = array(
            'pending_a' => '#ff9800',
            'pending_b' => '#2196f3',
            'pending_c' => '#9c27b0',
            'rejected_by_b' => '#f44336',
            'rejected_by_c' => '#e91e63',
            'finalized' => '#4caf50',
            'finalized_by_sampling' => '#8bc34a',
        );
        
        foreach ($status_counts as $status => $count) {
            if ($count > 0) {
                $chart_data['labels'][] = $status_labels[$status] ?? $status;
                $chart_data['data'][] = $count;
                $chart_data['colors'][] = $colors[$status] ?? '#607d8b';
            }
        }
        
        return $chart_data;
    }
    
    /**
     * Get daily activity statistics for the last 30 days
     * 
     * @return array
     */
    public function get_daily_activity_stats() {
        global $wpdb;
        
        $cache_key = 'tpak_daily_activity_stats';
        $cached_stats = get_transient($cache_key);
        
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        $thirty_days_ago = date('Y-m-d', strtotime('-30 days'));
        
        $query = $wpdb->prepare("
            SELECT DATE(p.post_date) as date, COUNT(*) as count
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND p.post_date >= %s
            GROUP BY DATE(p.post_date)
            ORDER BY date ASC
        ", $thirty_days_ago);
        
        $results = $wpdb->get_results($query);
        $activity_data = array();
        
        // Fill in missing dates with 0 counts
        for ($i = 30; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $activity_data[$date] = 0;
        }
        
        foreach ($results as $result) {
            $activity_data[$result->date] = (int) $result->count;
        }
        
        // Cache the results
        set_transient($cache_key, $activity_data, $this->cache_duration);
        
        return $activity_data;
    }
    
    /**
     * Get system health status
     * 
     * @return string
     */
    private function get_system_health_status() {
        $issues = array();
        
        // Check API configuration
        $api_settings = get_option('tpak_dq_settings', array());
        if (empty($api_settings['api_url']) || empty($api_settings['api_username'])) {
            $issues[] = 'API not configured';
        }
        
        // Check cron status
        if (!wp_next_scheduled('tpak_import_survey_data')) {
            $issues[] = 'Cron not scheduled';
        }
        
        // Check recent imports
        $last_import = get_option('tpak_last_import_date', '');
        if (empty($last_import) || strtotime($last_import) < strtotime('-7 days')) {
            $issues[] = 'No recent imports';
        }
        
        if (empty($issues)) {
            return 'Healthy';
        } elseif (count($issues) <= 1) {
            return 'Warning';
        } else {
            return 'Critical';
        }
    }
    
    /**
     * Clear statistics cache
     */
    public function clear_stats_cache() {
        global $wpdb;
        
        // Delete all TPAK stats transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tpak_%_stats%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tpak_%_stats%'");
        
        // Delete specific cache keys
        delete_transient('tpak_status_counts');
        delete_transient('tpak_daily_activity_stats');
    }
    
    /**
     * AJAX handler for refreshing statistics
     */
    public function ajax_refresh_stats() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_admin_ajax')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('tpak_access_dashboard')) {
            wp_die('Insufficient permissions');
        }
        
        // Clear cache
        $this->clear_stats_cache();
        
        // Get current user info
        $current_user = wp_get_current_user();
        $roles_instance = TPAK_Roles::get_instance();
        $user_role = $roles_instance->get_user_tpak_role($current_user->ID);
        
        // Get fresh statistics
        $stats = $this->get_user_statistics($current_user->ID, $user_role);
        
        wp_send_json_success(array(
            'stats' => $stats,
            'chart_data' => $this->get_workflow_chart_data(),
            'activity_data' => $this->get_daily_activity_stats(),
        ));
    }
    
    /**
     * Get performance metrics
     * 
     * @return array
     */
    public function get_performance_metrics() {
        global $wpdb;
        
        $cache_key = 'tpak_performance_metrics';
        $cached_metrics = get_transient($cache_key);
        
        if ($cached_metrics !== false) {
            return $cached_metrics;
        }
        
        $metrics = array();
        
        // Average processing time by status
        $processing_times = $wpdb->get_results("
            SELECT 
                pm1.meta_value as status,
                AVG(TIMESTAMPDIFF(HOUR, p.post_date, p.post_modified)) as avg_hours
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_tpak_workflow_status'
            WHERE p.post_type = 'tpak_survey_data'
            AND p.post_status = 'publish'
            AND p.post_modified > p.post_date
            GROUP BY pm1.meta_value
        ");
        
        foreach ($processing_times as $time) {
            $metrics['avg_processing_time'][$time->status] = round($time->avg_hours, 2);
        }
        
        // Throughput (items processed per day)
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $today_processed = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'tpak_survey_data'
            AND post_status = 'publish'
            AND DATE(post_modified) = %s
        ", $today));
        
        $yesterday_processed = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} 
            WHERE post_type = 'tpak_survey_data'
            AND post_status = 'publish'
            AND DATE(post_modified) = %s
        ", $yesterday));
        
        $metrics['throughput'] = array(
            'today' => (int) $today_processed,
            'yesterday' => (int) $yesterday_processed,
            'change' => $yesterday_processed > 0 ? 
                round((($today_processed - $yesterday_processed) / $yesterday_processed) * 100, 1) : 0,
        );
        
        // Cache the results
        set_transient($cache_key, $metrics, $this->cache_duration);
        
        return $metrics;
    }
}