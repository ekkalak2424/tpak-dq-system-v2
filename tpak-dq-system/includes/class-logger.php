<?php
/**
 * TPAK Logger Class
 *
 * Handles comprehensive logging with different levels and categories
 * for the TPAK DQ System plugin.
 *
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPAK_Logger {
    
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    /**
     * Log categories
     */
    const CATEGORY_API = 'api';
    const CATEGORY_VALIDATION = 'validation';
    const CATEGORY_WORKFLOW = 'workflow';
    const CATEGORY_CRON = 'cron';
    const CATEGORY_NOTIFICATION = 'notification';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_SYSTEM = 'system';
    
    /**
     * Maximum log entries to keep
     */
    const MAX_LOG_ENTRIES = 1000;
    
    /**
     * Log retention period in days
     */
    const LOG_RETENTION_DAYS = 30;
    
    /**
     * Instance of the logger
     *
     * @var TPAK_Logger
     */
    private static $instance = null;
    
    /**
     * Current log level threshold
     *
     * @var string
     */
    private $log_level;
    
    /**
     * Log level hierarchy for filtering
     *
     * @var array
     */
    private $level_hierarchy = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3,
        self::LEVEL_CRITICAL => 4
    ];
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->log_level = get_option('tpak_dq_log_level', self::LEVEL_WARNING);
        $this->init_database();
        $this->schedule_cleanup();
    }
    
    /**
     * Get singleton instance
     *
     * @return TPAK_Logger
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize database table for logs
     */
    private function init_database() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            level varchar(20) NOT NULL,
            category varchar(50) NOT NULL,
            message text NOT NULL,
            context longtext,
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY level (level),
            KEY category (category),
            KEY timestamp (timestamp),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Schedule log cleanup
     */
    private function schedule_cleanup() {
        if (!wp_next_scheduled('tpak_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'tpak_cleanup_logs');
        }
        
        add_action('tpak_cleanup_logs', [$this, 'cleanup_old_logs']);
    }
    
    /**
     * Log a message
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param string $category Log category
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function log($level, $message, $category = self::CATEGORY_SYSTEM, $context = []) {
        // Check if log level meets threshold
        if (!$this->should_log($level)) {
            return false;
        }
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_logs';
        
        // Prepare context data
        $context_json = !empty($context) ? wp_json_encode($context) : null;
        
        // Get current user and request info
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
        
        // Insert log entry
        $result = $wpdb->insert(
            $table_name,
            [
                'level' => $level,
                'category' => $category,
                'message' => $message,
                'context' => $context_json,
                'user_id' => $user_id ?: null,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent
            ],
            [
                '%s', // level
                '%s', // category
                '%s', // message
                '%s', // context
                '%d', // user_id
                '%s', // ip_address
                '%s'  // user_agent
            ]
        );
        
        // Also log to WordPress debug log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $debug_message = sprintf(
                '[TPAK-%s-%s] %s',
                strtoupper($level),
                strtoupper($category),
                $message
            );
            
            if (!empty($context)) {
                $debug_message .= ' Context: ' . wp_json_encode($context);
            }
            
            error_log($debug_message);
        }
        
        return $result !== false;
    }
    
    /**
     * Log debug message
     *
     * @param string $message
     * @param string $category
     * @param array $context
     * @return bool
     */
    public function debug($message, $category = self::CATEGORY_SYSTEM, $context = []) {
        return $this->log(self::LEVEL_DEBUG, $message, $category, $context);
    }
    
    /**
     * Log info message
     *
     * @param string $message
     * @param string $category
     * @param array $context
     * @return bool
     */
    public function info($message, $category = self::CATEGORY_SYSTEM, $context = []) {
        return $this->log(self::LEVEL_INFO, $message, $category, $context);
    }
    
    /**
     * Log warning message
     *
     * @param string $message
     * @param string $category
     * @param array $context
     * @return bool
     */
    public function warning($message, $category = self::CATEGORY_SYSTEM, $context = []) {
        return $this->log(self::LEVEL_WARNING, $message, $category, $context);
    }
    
    /**
     * Log error message
     *
     * @param string $message
     * @param string $category
     * @param array $context
     * @return bool
     */
    public function error($message, $category = self::CATEGORY_SYSTEM, $context = []) {
        return $this->log(self::LEVEL_ERROR, $message, $category, $context);
    }
    
    /**
     * Log critical message
     *
     * @param string $message
     * @param string $category
     * @param array $context
     * @return bool
     */
    public function critical($message, $category = self::CATEGORY_SYSTEM, $context = []) {
        return $this->log(self::LEVEL_CRITICAL, $message, $category, $context);
    }   
 
    /**
     * Log API error
     *
     * @param string $endpoint API endpoint
     * @param string $error Error message
     * @param array $request_data Request data
     * @return bool
     */
    public function log_api_error($endpoint, $error, $request_data = []) {
        $context = [
            'endpoint' => $endpoint,
            'request_data' => $request_data,
            'error_details' => $error
        ];
        
        return $this->error("API Error at endpoint: {$endpoint} - {$error}", self::CATEGORY_API, $context);
    }
    
    /**
     * Log validation error
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     * @param string $error_message Error message
     * @return bool
     */
    public function log_validation_error($field, $value, $rule, $error_message = '') {
        $context = [
            'field' => $field,
            'value' => is_string($value) ? $value : wp_json_encode($value),
            'validation_rule' => $rule,
            'error_message' => $error_message
        ];
        
        $message = "Validation failed for field '{$field}' with rule '{$rule}'";
        if ($error_message) {
            $message .= ": {$error_message}";
        }
        
        return $this->warning($message, self::CATEGORY_VALIDATION, $context);
    }
    
    /**
     * Log workflow error
     *
     * @param string $action Workflow action
     * @param int $data_id Data ID
     * @param int $user_id User ID
     * @param string $error Error message
     * @return bool
     */
    public function log_workflow_error($action, $data_id, $user_id, $error) {
        $context = [
            'action' => $action,
            'data_id' => $data_id,
            'user_id' => $user_id,
            'error_details' => $error
        ];
        
        return $this->error("Workflow error during '{$action}' for data ID {$data_id}: {$error}", self::CATEGORY_WORKFLOW, $context);
    }
    
    /**
     * Log cron error
     *
     * @param string $job_name Cron job name
     * @param string $error Error message
     * @param array $context Additional context
     * @return bool
     */
    public function log_cron_error($job_name, $error, $context = []) {
        $context['job_name'] = $job_name;
        $context['error_details'] = $error;
        
        return $this->error("Cron job '{$job_name}' failed: {$error}", self::CATEGORY_CRON, $context);
    }
    
    /**
     * Log notification error
     *
     * @param string $type Notification type
     * @param string $recipient Recipient
     * @param string $error Error message
     * @return bool
     */
    public function log_notification_error($type, $recipient, $error) {
        $context = [
            'notification_type' => $type,
            'recipient' => $recipient,
            'error_details' => $error
        ];
        
        return $this->error("Notification '{$type}' to '{$recipient}' failed: {$error}", self::CATEGORY_NOTIFICATION, $context);
    }
    
    /**
     * Log security event
     *
     * @param string $event Security event type
     * @param string $description Event description
     * @param array $context Additional context
     * @return bool
     */
    public function log_security_event($event, $description, $context = []) {
        $context['event_type'] = $event;
        $context['description'] = $description;
        
        return $this->warning("Security event '{$event}': {$description}", self::CATEGORY_SECURITY, $context);
    }
    
    /**
     * Get logs with filtering options
     *
     * @param array $args Query arguments
     * @return array Log entries
     */
    public function get_logs($args = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_logs';
        
        // Default arguments
        $defaults = [
            'level' => '',
            'category' => '',
            'limit' => 50,
            'offset' => 0,
            'order' => 'DESC',
            'date_from' => '',
            'date_to' => '',
            'user_id' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = ['1=1'];
        $where_values = [];
        
        if (!empty($args['level'])) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $args['level'];
        }
        
        if (!empty($args['category'])) {
            $where_conditions[] = 'category = %s';
            $where_values[] = $args['category'];
        }
        
        if (!empty($args['user_id'])) {
            $where_conditions[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = 'timestamp >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = 'timestamp <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Build ORDER BY clause
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Build query
        $query = "SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY timestamp {$order}";
        
        // Add LIMIT
        if ($args['limit'] > 0) {
            $query .= $wpdb->prepare(' LIMIT %d', $args['limit']);
            
            if ($args['offset'] > 0) {
                $query .= $wpdb->prepare(' OFFSET %d', $args['offset']);
            }
        }
        
        // Prepare and execute query
        if (!empty($where_values)) {
            $prepared_query = $wpdb->prepare($query, $where_values);
        } else {
            $prepared_query = $query;
        }
        
        return $wpdb->get_results($prepared_query, ARRAY_A);
    }
    
    /**
     * Get log statistics
     *
     * @return array Statistics
     */
    public function get_log_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_logs';
        
        // Get counts by level
        $level_stats = $wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM {$table_name} GROUP BY level",
            ARRAY_A
        );
        
        // Get counts by category
        $category_stats = $wpdb->get_results(
            "SELECT category, COUNT(*) as count FROM {$table_name} GROUP BY category",
            ARRAY_A
        );
        
        // Get recent error count (last 24 hours)
        $recent_errors = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE level IN ('error', 'critical') AND timestamp >= %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            )
        );
        
        // Get total log count
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        return [
            'total_logs' => (int) $total_logs,
            'recent_errors' => (int) $recent_errors,
            'by_level' => $level_stats,
            'by_category' => $category_stats
        ];
    }
    
    /**
     * Clear logs
     *
     * @param array $args Clear arguments
     * @return bool Success status
     */
    public function clear_logs($args = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_logs';
        
        // Default arguments
        $defaults = [
            'level' => '',
            'category' => '',
            'older_than_days' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Build WHERE clause
        $where_conditions = [];
        $where_values = [];
        
        if (!empty($args['level'])) {
            $where_conditions[] = 'level = %s';
            $where_values[] = $args['level'];
        }
        
        if (!empty($args['category'])) {
            $where_conditions[] = 'category = %s';
            $where_values[] = $args['category'];
        }
        
        if ($args['older_than_days'] > 0) {
            $where_conditions[] = 'timestamp < %s';
            $where_values[] = date('Y-m-d H:i:s', strtotime("-{$args['older_than_days']} days"));
        }
        
        if (empty($where_conditions)) {
            // Clear all logs
            $result = $wpdb->query("TRUNCATE TABLE {$table_name}");
        } else {
            $where_clause = implode(' AND ', $where_conditions);
            $query = "DELETE FROM {$table_name} WHERE {$where_clause}";
            $result = $wpdb->query($wpdb->prepare($query, $where_values));
        }
        
        return $result !== false;
    }
    
    /**
     * Cleanup old logs (called by cron)
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tpak_logs';
        
        // Remove logs older than retention period
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE timestamp < %s",
                date('Y-m-d H:i:s', strtotime('-' . self::LOG_RETENTION_DAYS . ' days'))
            )
        );
        
        // Keep only the most recent entries if we exceed the limit
        $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        if ($total_logs > self::MAX_LOG_ENTRIES) {
            $excess = $total_logs - self::MAX_LOG_ENTRIES;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$table_name} ORDER BY timestamp ASC LIMIT %d",
                    $excess
                )
            );
        }
        
        // Optimize table
        $wpdb->query("OPTIMIZE TABLE {$table_name}");
    }
    
    /**
     * Check if message should be logged based on level threshold
     *
     * @param string $level Log level
     * @return bool
     */
    private function should_log($level) {
        if (!isset($this->level_hierarchy[$level]) || !isset($this->level_hierarchy[$this->log_level])) {
            return false;
        }
        
        return $this->level_hierarchy[$level] >= $this->level_hierarchy[$this->log_level];
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Set log level threshold
     *
     * @param string $level
     */
    public function set_log_level($level) {
        if (isset($this->level_hierarchy[$level])) {
            $this->log_level = $level;
            update_option('tpak_dq_log_level', $level);
        }
    }
    
    /**
     * Get current log level
     *
     * @return string
     */
    public function get_log_level() {
        return $this->log_level;
    }
    
    /**
     * Get available log levels
     *
     * @return array
     */
    public function get_log_levels() {
        return array_keys($this->level_hierarchy);
    }
    
    /**
     * Get available log categories
     *
     * @return array
     */
    public function get_log_categories() {
        return [
            self::CATEGORY_API,
            self::CATEGORY_VALIDATION,
            self::CATEGORY_WORKFLOW,
            self::CATEGORY_CRON,
            self::CATEGORY_NOTIFICATION,
            self::CATEGORY_SECURITY,
            self::CATEGORY_SYSTEM
        ];
    }
}