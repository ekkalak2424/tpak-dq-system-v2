<?php
/**
 * Cron Class
 * 
 * Handles cron job management for automated imports
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Cron Class
 */
class TPAK_Cron {
    
    /**
     * Single instance
     * 
     * @var TPAK_Cron
     */
    private static $instance = null;
    
    /**
     * Get instance
     * 
     * @return TPAK_Cron
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cron hook name
     */
    const CRON_HOOK = 'tpak_dq_import_data';
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action(self::CRON_HOOK, array($this, 'execute_import'));
        add_action('wp_ajax_tpak_manual_import', array($this, 'handle_manual_import'));
        add_action('wp_ajax_tpak_test_cron', array($this, 'handle_test_cron'));
        add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));
        
        // Hook into settings save to update cron schedule
        add_action('update_option_tpak_dq_settings', array($this, 'update_cron_schedule'), 10, 2);
    }
    
    /**
     * Add custom cron schedules
     * 
     * @param array $schedules
     * @return array
     */
    public function add_custom_cron_schedules($schedules) {
        $schedules['tpak_twicedaily'] = array(
            'interval' => 12 * HOUR_IN_SECONDS,
            'display'  => __('Twice Daily', 'tpak-dq-system')
        );
        
        $schedules['tpak_every_six_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __('Every 6 Hours', 'tpak-dq-system')
        );
        
        $schedules['tpak_every_three_hours'] = array(
            'interval' => 3 * HOUR_IN_SECONDS,
            'display'  => __('Every 3 Hours', 'tpak-dq-system')
        );
        
        return $schedules;
    }
    
    /**
     * Schedule import job
     * 
     * @param string $interval
     * @param string $survey_id
     * @return bool
     */
    public function schedule_import($interval = 'daily', $survey_id = null) {
        // Clear existing scheduled event
        $this->unschedule_import();
        
        // Validate interval
        $valid_intervals = array('hourly', 'tpak_every_three_hours', 'tpak_every_six_hours', 'tpak_twicedaily', 'daily', 'weekly');
        if (!in_array($interval, $valid_intervals)) {
            return new WP_Error('invalid_interval', __('Invalid cron interval specified.', 'tpak-dq-system'));
        }
        
        // Get survey ID from settings if not provided
        if (!$survey_id) {
            $settings = get_option('tpak_dq_settings', array());
            $survey_id = isset($settings['cron']['survey_id']) ? $settings['cron']['survey_id'] : '';
        }
        
        if (empty($survey_id)) {
            return new WP_Error('no_survey_id', __('Survey ID is required for scheduling imports.', 'tpak-dq-system'));
        }
        
        // Schedule the event
        $result = wp_schedule_event(time(), $interval, self::CRON_HOOK, array($survey_id));
        
        if ($result === false) {
            return new WP_Error('schedule_failed', __('Failed to schedule import job.', 'tpak-dq-system'));
        }
        
        // Update settings
        $settings = get_option('tpak_dq_settings', array());
        $settings['cron']['interval'] = $interval;
        $settings['cron']['survey_id'] = $survey_id;
        $settings['cron']['last_scheduled'] = current_time('mysql');
        update_option('tpak_dq_settings', $settings);
        
        // Log the scheduling
        $this->log_cron_event('scheduled', array(
            'interval' => $interval,
            'survey_id' => $survey_id,
            'next_run' => wp_next_scheduled(self::CRON_HOOK)
        ));
        
        return true;
    }
    
    /**
     * Unschedule import job
     * 
     * @return bool
     */
    public function unschedule_import() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
            
            // Log the unscheduling
            $this->log_cron_event('unscheduled', array(
                'timestamp' => $timestamp
            ));
        }
        
        return true;
    }
    
    /**
     * Execute import job
     * 
     * @param string $survey_id
     */
    public function execute_import($survey_id = null) {
        $start_time = microtime(true);
        
        try {
            // Get survey ID from settings if not provided
            if (!$survey_id) {
                $settings = get_option('tpak_dq_settings', array());
                $survey_id = isset($settings['cron']['survey_id']) ? $settings['cron']['survey_id'] : '';
            }
            
            if (empty($survey_id)) {
                throw new Exception(__('Survey ID not configured for cron import.', 'tpak-dq-system'));
            }
            
            // Get last import date for incremental import
            $last_import = get_option('tpak_dq_last_import');
            
            // Execute import
            $api_handler = TPAK_API_Handler::get_instance();
            $result = $api_handler->import_survey_data($survey_id, $last_import);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $execution_time = round(microtime(true) - $start_time, 2);
            
            // Log successful import
            $this->log_cron_event('import_success', array(
                'survey_id' => $survey_id,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'total_responses' => $result['total_responses'],
                'execution_time' => $execution_time,
                'last_import' => $last_import
            ));
            
            // Update statistics
            $this->update_import_statistics($result, $execution_time);
            
            // Send notification if there were imports or errors
            if ($result['imported'] > 0 || $result['errors'] > 0) {
                $this->send_import_notification($result);
            }
            
        } catch (Exception $e) {
            $execution_time = round(microtime(true) - $start_time, 2);
            
            // Log failed import
            $this->log_cron_event('import_failed', array(
                'survey_id' => $survey_id,
                'error' => $e->getMessage(),
                'execution_time' => $execution_time
            ));
            
            // Handle import failure
            $this->handle_import_failure($e->getMessage(), $survey_id);
        }
    }
    
    /**
     * Handle import failure
     * 
     * @param string $error_message
     * @param string $survey_id
     */
    private function handle_import_failure($error_message, $survey_id) {
        // Increment failure count
        $failure_count = get_option('tpak_dq_import_failures', 0) + 1;
        update_option('tpak_dq_import_failures', $failure_count);
        
        // If too many failures, disable cron
        $max_failures = apply_filters('tpak_dq_max_import_failures', 5);
        if ($failure_count >= $max_failures) {
            $this->unschedule_import();
            
            // Update settings to reflect disabled state
            $settings = get_option('tpak_dq_settings', array());
            $settings['cron']['enabled'] = false;
            $settings['cron']['disabled_reason'] = sprintf(
                __('Disabled after %d consecutive failures. Last error: %s', 'tpak-dq-system'),
                $failure_count,
                $error_message
            );
            update_option('tpak_dq_settings', $settings);
            
            // Send failure notification
            $this->send_failure_notification($error_message, $failure_count);
        }
        
        // Log error
        error_log(sprintf(
            'TPAK DQ System: Import failed for survey %s. Error: %s (Failure count: %d)',
            $survey_id,
            $error_message,
            $failure_count
        ));
    }
    
    /**
     * Update import statistics
     * 
     * @param array $result
     * @param float $execution_time
     */
    private function update_import_statistics($result, $execution_time) {
        $stats = get_option('tpak_dq_import_stats', array(
            'total_runs' => 0,
            'total_imported' => 0,
            'total_skipped' => 0,
            'total_errors' => 0,
            'total_execution_time' => 0,
            'last_run' => null,
            'average_execution_time' => 0
        ));
        
        $stats['total_runs']++;
        $stats['total_imported'] += $result['imported'];
        $stats['total_skipped'] += $result['skipped'];
        $stats['total_errors'] += $result['errors'];
        $stats['total_execution_time'] += $execution_time;
        $stats['last_run'] = current_time('mysql');
        $stats['average_execution_time'] = round($stats['total_execution_time'] / $stats['total_runs'], 2);
        
        update_option('tpak_dq_import_stats', $stats);
        
        // Reset failure count on successful import
        if ($result['imported'] > 0 || $result['errors'] == 0) {
            delete_option('tpak_dq_import_failures');
        }
    }
    
    /**
     * Send import notification
     * 
     * @param array $result
     */
    private function send_import_notification($result) {
        $settings = get_option('tpak_dq_settings', array());
        $notifications_enabled = isset($settings['notifications']['email_enabled']) 
            ? $settings['notifications']['email_enabled'] 
            : false;
        
        if (!$notifications_enabled) {
            return;
        }
        
        $notifications = TPAK_Notifications::get_instance();
        $notifications->send_import_notification($result);
    }
    
    /**
     * Send failure notification
     * 
     * @param string $error_message
     * @param int    $failure_count
     */
    private function send_failure_notification($error_message, $failure_count) {
        $settings = get_option('tpak_dq_settings', array());
        $notifications_enabled = isset($settings['notifications']['email_enabled']) 
            ? $settings['notifications']['email_enabled'] 
            : false;
        
        if (!$notifications_enabled) {
            return;
        }
        
        $notifications = TPAK_Notifications::get_instance();
        $notifications->send_import_failure_notification($error_message, $failure_count);
    }
    
    /**
     * Handle manual import via AJAX
     */
    public function handle_manual_import() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'tpak-dq-system'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_manual_import')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        // Get survey ID
        $survey_id = sanitize_text_field($_POST['survey_id'] ?? '');
        if (empty($survey_id)) {
            wp_send_json_error(array(
                'message' => __('Survey ID is required.', 'tpak-dq-system')
            ));
        }
        
        // Execute import
        $this->execute_import($survey_id);
        
        // Get the latest log entry for results
        $logs = $this->get_cron_logs(array('event' => 'import_success'), 1);
        if (!empty($logs)) {
            $log = $logs[0];
            wp_send_json_success(array(
                'message' => __('Import completed successfully.', 'tpak-dq-system'),
                'imported' => $log['data']['imported'] ?? 0,
                'skipped' => $log['data']['skipped'] ?? 0,
                'errors' => $log['data']['errors'] ?? 0,
                'execution_time' => $log['data']['execution_time'] ?? 0
            ));
        } else {
            // Check for failure
            $logs = $this->get_cron_logs(array('event' => 'import_failed'), 1);
            if (!empty($logs)) {
                $log = $logs[0];
                wp_send_json_error(array(
                    'message' => $log['data']['error'] ?? __('Import failed.', 'tpak-dq-system')
                ));
            } else {
                wp_send_json_error(array(
                    'message' => __('Import status unknown.', 'tpak-dq-system')
                ));
            }
        }
    }
    
    /**
     * Handle test cron via AJAX
     */
    public function handle_test_cron() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'tpak-dq-system'));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'tpak_test_cron')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        // Test cron functionality
        $test_result = $this->test_cron_functionality();
        
        if (is_wp_error($test_result)) {
            wp_send_json_error(array(
                'message' => $test_result->get_error_message()
            ));
        } else {
            wp_send_json_success(array(
                'message' => __('Cron test completed successfully.', 'tpak-dq-system'),
                'details' => $test_result
            ));
        }
    }
    
    /**
     * Test cron functionality
     * 
     * @return array|WP_Error
     */
    public function test_cron_functionality() {
        $results = array();
        
        // Test 1: Check if WP-Cron is enabled
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $results['wp_cron_status'] = array(
                'status' => 'warning',
                'message' => __('WP-Cron is disabled. You may need to set up server cron jobs.', 'tpak-dq-system')
            );
        } else {
            $results['wp_cron_status'] = array(
                'status' => 'success',
                'message' => __('WP-Cron is enabled.', 'tpak-dq-system')
            );
        }
        
        // Test 2: Check if our cron hook is scheduled
        $next_scheduled = wp_next_scheduled(self::CRON_HOOK);
        if ($next_scheduled) {
            $results['cron_scheduled'] = array(
                'status' => 'success',
                'message' => sprintf(
                    __('Import job is scheduled. Next run: %s', 'tpak-dq-system'),
                    date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled)
                )
            );
        } else {
            $results['cron_scheduled'] = array(
                'status' => 'warning',
                'message' => __('No import job is currently scheduled.', 'tpak-dq-system')
            );
        }
        
        // Test 3: Check cron schedules
        $schedules = wp_get_schedules();
        $custom_schedules = array('tpak_twicedaily', 'tpak_every_six_hours', 'tpak_every_three_hours');
        $missing_schedules = array();
        
        foreach ($custom_schedules as $schedule) {
            if (!isset($schedules[$schedule])) {
                $missing_schedules[] = $schedule;
            }
        }
        
        if (empty($missing_schedules)) {
            $results['custom_schedules'] = array(
                'status' => 'success',
                'message' => __('All custom cron schedules are registered.', 'tpak-dq-system')
            );
        } else {
            $results['custom_schedules'] = array(
                'status' => 'error',
                'message' => sprintf(
                    __('Missing custom schedules: %s', 'tpak-dq-system'),
                    implode(', ', $missing_schedules)
                )
            );
        }
        
        // Test 4: Check API configuration
        $api_handler = TPAK_API_Handler::get_instance();
        $api_status = $api_handler->get_connection_status();
        
        if ($api_status['is_configured']) {
            $results['api_configuration'] = array(
                'status' => 'success',
                'message' => __('API is configured for imports.', 'tpak-dq-system')
            );
        } else {
            $results['api_configuration'] = array(
                'status' => 'error',
                'message' => __('API is not configured. Imports will fail.', 'tpak-dq-system')
            );
        }
        
        // Test 5: Test actual cron execution (if API is configured)
        if ($api_status['is_configured']) {
            try {
                // Get survey ID from settings
                $settings = get_option('tpak_dq_settings', array());
                $survey_id = isset($settings['cron']['survey_id']) ? $settings['cron']['survey_id'] : '';
                
                if (!empty($survey_id)) {
                    // Test API connection
                    $connection_test = $api_handler->validate_connection();
                    
                    if (is_wp_error($connection_test)) {
                        $results['cron_execution'] = array(
                            'status' => 'error',
                            'message' => sprintf(
                                __('API connection test failed: %s', 'tpak-dq-system'),
                                $connection_test->get_error_message()
                            )
                        );
                    } else {
                        $results['cron_execution'] = array(
                            'status' => 'success',
                            'message' => __('Cron execution test passed. API connection is working.', 'tpak-dq-system')
                        );
                    }
                } else {
                    $results['cron_execution'] = array(
                        'status' => 'warning',
                        'message' => __('Survey ID not configured for cron imports.', 'tpak-dq-system')
                    );
                }
            } catch (Exception $e) {
                $results['cron_execution'] = array(
                    'status' => 'error',
                    'message' => sprintf(
                        __('Cron execution test failed: %s', 'tpak-dq-system'),
                        $e->getMessage()
                    )
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Update cron schedule when settings change
     * 
     * @param mixed $old_value
     * @param mixed $new_value
     */
    public function update_cron_schedule($old_value, $new_value) {
        // Check if cron settings changed
        $old_cron = isset($old_value['cron']) ? $old_value['cron'] : array();
        $new_cron = isset($new_value['cron']) ? $new_value['cron'] : array();
        
        if ($old_cron !== $new_cron) {
            // Reschedule if interval or survey ID changed
            if (isset($new_cron['interval']) && isset($new_cron['survey_id'])) {
                $this->schedule_import($new_cron['interval'], $new_cron['survey_id']);
            } else {
                // Unschedule if settings are incomplete
                $this->unschedule_import();
            }
        }
    }
    
    /**
     * Get cron status
     * 
     * @return array
     */
    public function get_cron_status() {
        $next_scheduled = wp_next_scheduled(self::CRON_HOOK);
        $settings = get_option('tpak_dq_settings', array());
        $cron_settings = isset($settings['cron']) ? $settings['cron'] : array();
        $stats = get_option('tpak_dq_import_stats', array());
        $failure_count = get_option('tpak_dq_import_failures', 0);
        
        return array(
            'is_scheduled' => (bool) $next_scheduled,
            'next_run' => $next_scheduled ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled) : null,
            'next_run_timestamp' => $next_scheduled,
            'interval' => $cron_settings['interval'] ?? null,
            'survey_id' => $cron_settings['survey_id'] ?? null,
            'last_scheduled' => $cron_settings['last_scheduled'] ?? null,
            'enabled' => $cron_settings['enabled'] ?? true,
            'disabled_reason' => $cron_settings['disabled_reason'] ?? null,
            'statistics' => $stats,
            'failure_count' => $failure_count,
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON
        );
    }
    
    /**
     * Log cron event
     * 
     * @param string $event
     * @param array  $data
     */
    private function log_cron_event($event, $data = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'event' => $event,
            'data' => $data
        );
        
        // Store in WordPress options (could be moved to custom table for better performance)
        $logs = get_option('tpak_dq_cron_logs', array());
        $logs[] = $log_entry;
        
        // Keep only last 100 entries
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option('tpak_dq_cron_logs', $logs);
    }
    
    /**
     * Get cron logs
     * 
     * @param array $filters
     * @param int   $limit
     * @return array
     */
    public function get_cron_logs($filters = array(), $limit = 20) {
        $logs = get_option('tpak_dq_cron_logs', array());
        
        // Apply filters
        if (!empty($filters)) {
            $logs = array_filter($logs, function($log) use ($filters) {
                if (!empty($filters['event']) && $log['event'] !== $filters['event']) {
                    return false;
                }
                
                if (!empty($filters['date_from']) && $log['timestamp'] < $filters['date_from']) {
                    return false;
                }
                
                if (!empty($filters['date_to']) && $log['timestamp'] > $filters['date_to']) {
                    return false;
                }
                
                return true;
            });
        }
        
        // Sort by timestamp (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        // Apply limit
        if ($limit > 0) {
            $logs = array_slice($logs, 0, $limit);
        }
        
        return $logs;
    }
    
    /**
     * Clear cron logs
     * 
     * @return bool
     */
    public function clear_cron_logs() {
        return delete_option('tpak_dq_cron_logs');
    }
    
    /**
     * Get available cron intervals
     * 
     * @return array
     */
    public function get_available_intervals() {
        $schedules = wp_get_schedules();
        $intervals = array();
        
        $allowed_intervals = array(
            'hourly' => __('Hourly', 'tpak-dq-system'),
            'tpak_every_three_hours' => __('Every 3 Hours', 'tpak-dq-system'),
            'tpak_every_six_hours' => __('Every 6 Hours', 'tpak-dq-system'),
            'tpak_twicedaily' => __('Twice Daily', 'tpak-dq-system'),
            'daily' => __('Daily', 'tpak-dq-system'),
            'weekly' => __('Weekly', 'tpak-dq-system')
        );
        
        foreach ($allowed_intervals as $interval => $label) {
            if (isset($schedules[$interval])) {
                $intervals[$interval] = array(
                    'label' => $label,
                    'interval' => $schedules[$interval]['interval'],
                    'display' => $schedules[$interval]['display']
                );
            }
        }
        
        return $intervals;
    }
}