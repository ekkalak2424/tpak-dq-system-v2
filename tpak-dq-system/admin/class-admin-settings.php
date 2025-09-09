<?php
/**
 * Admin Settings Class
 * 
 * Handles settings page functionality and configuration management
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TPAK Admin Settings Class
 */
class TPAK_Admin_Settings {
    
    /**
     * Single instance
     * 
     * @var TPAK_Admin_Settings
     */
    private static $instance = null;
    
    /**
     * Settings option name
     * 
     * @var string
     */
    private $option_name = 'tpak_dq_settings';
    
    /**
     * Default settings
     * 
     * @var array
     */
    private $default_settings = array();
    
    /**
     * Get instance
     * 
     * @return TPAK_Admin_Settings
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
        $this->init_default_settings();
        add_action('wp_ajax_tpak_test_api_connection', array($this, 'ajax_test_api_connection'));
        add_action('wp_ajax_tpak_manual_import', array($this, 'ajax_manual_import'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_tpak_save_settings', array($this, 'handle_settings_save_secure'));
    }
    
    /**
     * Initialize default settings
     */
    private function init_default_settings() {
        $this->default_settings = array(
            'api' => array(
                'limesurvey_url' => '',
                'username' => '',
                'password' => '',
                'survey_id' => '',
                'connection_timeout' => 30,
                'last_test_result' => '',
                'last_test_date' => '',
            ),
            'cron' => array(
                'import_enabled' => true,
                'import_interval' => 'daily',
                'survey_id' => '',
                'last_import_date' => '',
                'import_limit' => 100,
                'retry_attempts' => 3,
            ),
            'notifications' => array(
                'email_enabled' => true,
                'notification_emails' => array(),
                'send_on_assignment' => true,
                'send_on_status_change' => true,
                'send_on_error' => true,
                'email_template' => 'default',
            ),
            'workflow' => array(
                'sampling_percentage' => 30,
                'auto_finalize_sampling' => true,
                'require_comments' => false,
                'audit_retention_days' => 365,
            ),
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'tpak_dq_settings_group',
            $this->option_name,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->default_settings,
            )
        );
    }
    
    /**
     * Get all settings
     * 
     * @return array
     */
    public function get_all_settings() {
        $settings = get_option($this->option_name, $this->default_settings);
        return wp_parse_args($settings, $this->default_settings);
    }
    
    /**
     * Get specific setting section
     * 
     * @param string $section
     * @return array
     */
    public function get_settings($section) {
        $all_settings = $this->get_all_settings();
        return isset($all_settings[$section]) ? $all_settings[$section] : array();
    }
    
    /**
     * Update settings section
     * 
     * @param string $section
     * @param array  $data
     * @return bool
     */
    public function update_settings($section, $data) {
        $all_settings = $this->get_all_settings();
        $all_settings[$section] = array_merge($all_settings[$section], $data);
        return update_option($this->option_name, $all_settings);
    }
    
    /**
     * Handle settings save with security middleware
     */
    public function handle_settings_save_secure() {
        $sanitization_rules = array(
            'active_tab' => array('type' => 'text', 'max_length' => 20),
            'limesurvey_url' => array('type' => 'url'),
            'api_username' => array('type' => 'text', 'max_length' => 100),
            'api_password' => array('type' => 'text', 'max_length' => 255),
            'survey_id' => array('type' => 'int', 'min' => 1),
            'connection_timeout' => array('type' => 'int', 'min' => 5, 'max' => 300),
            'import_interval' => array('type' => 'text', 'max_length' => 20),
            'cron_survey_id' => array('type' => 'int', 'min' => 1),
            'import_limit' => array('type' => 'int', 'min' => 1, 'max' => 1000),
            'retry_attempts' => array('type' => 'int', 'min' => 0, 'max' => 10),
            'notification_emails' => array('type' => 'textarea', 'max_length' => 1000),
            'email_template' => array('type' => 'text', 'max_length' => 50),
            'sampling_percentage' => array('type' => 'int', 'min' => 1, 'max' => 100),
            'audit_retention_days' => array('type' => 'int', 'min' => 1, 'max' => 3650)
        );
        
        TPAK_Security_Middleware::secure_form_submission(
            TPAK_Security::NONCE_SETTINGS,
            'manage_tpak_settings',
            array($this, 'handle_settings_save'),
            $sanitization_rules
        );
    }

    /**
     * Handle settings save (called by middleware)
     */
    public function handle_settings_save($sanitized_data) {
        
        $tab = $sanitized_data['active_tab'];
        $validator = TPAK_Validator::get_instance();
        $success = false;
        $message = '';
        
        switch ($tab) {
            case 'api':
                $success = $this->save_api_settings($sanitized_data, $validator);
                break;
            case 'cron':
                $success = $this->save_cron_settings($sanitized_data, $validator);
                break;
            case 'notifications':
                $success = $this->save_notifications_settings($sanitized_data, $validator);
                break;
            case 'workflow':
                $success = $this->save_workflow_settings($sanitized_data, $validator);
                break;
        }
        
        if ($success) {
            $message = __('Settings saved successfully.', 'tpak-dq-system');
            $type = 'success';
        } else {
            $message = $validator->has_errors() ? $validator->get_error_messages() : __('Failed to save settings.', 'tpak-dq-system');
            $type = 'error';
        }
        
        // Redirect with message
        $redirect_url = add_query_arg(array(
            'page' => 'tpak-settings',
            'tab' => $tab,
            'message' => urlencode($message),
            'type' => $type,
        ), admin_url('admin.php'));
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Save API settings
     * 
     * @param array           $post_data
     * @param TPAK_Validator $validator
     * @return bool
     */
    private function save_api_settings($post_data, $validator) {
        $api_data = array(
            'limesurvey_url' => sanitize_url($post_data['limesurvey_url'] ?? ''),
            'username' => sanitize_text_field($post_data['api_username'] ?? ''),
            'password' => sanitize_text_field($post_data['api_password'] ?? ''),
            'survey_id' => absint($post_data['survey_id'] ?? 0),
            'connection_timeout' => absint($post_data['connection_timeout'] ?? 30),
        );
        
        // Validate API settings
        if (!$validator->validate_api_settings($api_data)) {
            return false;
        }
        
        return $this->update_settings('api', $api_data);
    }
    
    /**
     * Save cron settings
     * 
     * @param array           $post_data
     * @param TPAK_Validator $validator
     * @return bool
     */
    private function save_cron_settings($post_data, $validator) {
        $cron_data = array(
            'import_enabled' => isset($post_data['import_enabled']),
            'import_interval' => sanitize_text_field($post_data['import_interval'] ?? 'daily'),
            'survey_id' => absint($post_data['cron_survey_id'] ?? 0),
            'import_limit' => absint($post_data['import_limit'] ?? 100),
            'retry_attempts' => absint($post_data['retry_attempts'] ?? 3),
        );
        
        // Validate cron settings
        if (!$validator->validate_cron_settings($cron_data)) {
            return false;
        }
        
        // Update cron schedule if needed
        $current_settings = $this->get_settings('cron');
        if ($current_settings['import_interval'] !== $cron_data['import_interval'] || 
            $current_settings['import_enabled'] !== $cron_data['import_enabled']) {
            $this->update_cron_schedule($cron_data);
        }
        
        return $this->update_settings('cron', $cron_data);
    }
    
    /**
     * Save notifications settings
     * 
     * @param array           $post_data
     * @param TPAK_Validator $validator
     * @return bool
     */
    private function save_notifications_settings($post_data, $validator) {
        $notification_emails = array();
        if (!empty($post_data['notification_emails'])) {
            $emails = explode(',', $post_data['notification_emails']);
            foreach ($emails as $email) {
                $email = trim($email);
                if (!empty($email) && is_email($email)) {
                    $notification_emails[] = $email;
                }
            }
        }
        
        $notifications_data = array(
            'email_enabled' => isset($post_data['email_enabled']),
            'notification_emails' => $notification_emails,
            'send_on_assignment' => isset($post_data['send_on_assignment']),
            'send_on_status_change' => isset($post_data['send_on_status_change']),
            'send_on_error' => isset($post_data['send_on_error']),
            'email_template' => sanitize_text_field($post_data['email_template'] ?? 'default'),
        );
        
        // Validate notification settings
        if (!$validator->validate_notification_settings($notifications_data)) {
            return false;
        }
        
        return $this->update_settings('notifications', $notifications_data);
    }
    
    /**
     * Save workflow settings
     * 
     * @param array           $post_data
     * @param TPAK_Validator $validator
     * @return bool
     */
    private function save_workflow_settings($post_data, $validator) {
        $workflow_data = array(
            'sampling_percentage' => absint($post_data['sampling_percentage'] ?? 30),
            'auto_finalize_sampling' => isset($post_data['auto_finalize_sampling']),
            'require_comments' => isset($post_data['require_comments']),
            'audit_retention_days' => absint($post_data['audit_retention_days'] ?? 365),
        );
        
        // Validate sampling percentage
        if (!$validator->validate_percentage($workflow_data['sampling_percentage'], 'Sampling Percentage')) {
            return false;
        }
        
        return $this->update_settings('workflow', $workflow_data);
    }
    
    /**
     * Update cron schedule
     * 
     * @param array $cron_data
     */
    private function update_cron_schedule($cron_data) {
        // Clear existing schedule
        wp_clear_scheduled_hook('tpak_import_survey_data');
        
        // Schedule new cron if enabled
        if ($cron_data['import_enabled']) {
            wp_schedule_event(time(), $cron_data['import_interval'], 'tpak_import_survey_data');
        }
    }
    
    /**
     * Sanitize settings
     * 
     * @param array $settings
     * @return array
     */
    public function sanitize_settings($settings) {
        $validator = TPAK_Validator::get_instance();
        $sanitized = array();
        
        foreach ($settings as $section => $data) {
            if (!is_array($data)) {
                continue;
            }
            
            switch ($section) {
                case 'api':
                    $sanitized[$section] = array(
                        'limesurvey_url' => $validator->sanitize_input($data['limesurvey_url'] ?? '', 'url'),
                        'username' => $validator->sanitize_input($data['username'] ?? '', 'text'),
                        'password' => $validator->sanitize_input($data['password'] ?? '', 'text'),
                        'survey_id' => $validator->sanitize_input($data['survey_id'] ?? 0, 'int'),
                        'connection_timeout' => $validator->sanitize_input($data['connection_timeout'] ?? 30, 'int'),
                        'last_test_result' => $validator->sanitize_input($data['last_test_result'] ?? '', 'text'),
                        'last_test_date' => $validator->sanitize_input($data['last_test_date'] ?? '', 'text'),
                    );
                    break;
                    
                case 'cron':
                    $sanitized[$section] = array(
                        'import_enabled' => $validator->sanitize_input($data['import_enabled'] ?? true, 'bool'),
                        'import_interval' => $validator->sanitize_input($data['import_interval'] ?? 'daily', 'text'),
                        'survey_id' => $validator->sanitize_input($data['survey_id'] ?? 0, 'int'),
                        'last_import_date' => $validator->sanitize_input($data['last_import_date'] ?? '', 'text'),
                        'import_limit' => $validator->sanitize_input($data['import_limit'] ?? 100, 'int'),
                        'retry_attempts' => $validator->sanitize_input($data['retry_attempts'] ?? 3, 'int'),
                    );
                    break;
                    
                case 'notifications':
                    $sanitized[$section] = array(
                        'email_enabled' => $validator->sanitize_input($data['email_enabled'] ?? true, 'bool'),
                        'notification_emails' => is_array($data['notification_emails'] ?? array()) ? 
                            array_map(function($email) use ($validator) {
                                return $validator->sanitize_input($email, 'email');
                            }, $data['notification_emails']) : array(),
                        'send_on_assignment' => $validator->sanitize_input($data['send_on_assignment'] ?? true, 'bool'),
                        'send_on_status_change' => $validator->sanitize_input($data['send_on_status_change'] ?? true, 'bool'),
                        'send_on_error' => $validator->sanitize_input($data['send_on_error'] ?? true, 'bool'),
                        'email_template' => $validator->sanitize_input($data['email_template'] ?? 'default', 'text'),
                    );
                    break;
                    
                case 'workflow':
                    $sanitized[$section] = array(
                        'sampling_percentage' => $validator->sanitize_input($data['sampling_percentage'] ?? 30, 'int'),
                        'auto_finalize_sampling' => $validator->sanitize_input($data['auto_finalize_sampling'] ?? true, 'bool'),
                        'require_comments' => $validator->sanitize_input($data['require_comments'] ?? false, 'bool'),
                        'audit_retention_days' => $validator->sanitize_input($data['audit_retention_days'] ?? 365, 'int'),
                    );
                    break;
                    
                default:
                    $sanitized[$section] = $data;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_api_connection() {
        TPAK_Security_Middleware::secure_ajax_action(
            'manage_tpak_settings',
            array($this, 'test_api_connection_handler')
        );
    }
    
    /**
     * Test API connection handler
     */
    public function test_api_connection_handler() {
        $url = TPAK_Security::sanitize_url($_POST['url'] ?? '');
        $username = TPAK_Security::sanitize_text($_POST['username'] ?? '');
        $password = TPAK_Security::sanitize_text($_POST['password'] ?? '');
        $survey_id = TPAK_Security::sanitize_int($_POST['survey_id'] ?? 0);
        
        if (empty($url) || empty($username) || empty($password) || !$survey_id) {
            wp_send_json_error(array('message' => __('All fields are required for testing.', 'tpak-dq-system')));
        }
        
        // Rate limiting for API tests
        if (!TPAK_Security::check_rate_limit('api_test', 5, 300)) {
            wp_send_json_error(array('message' => __('Too many test attempts. Please wait before trying again.', 'tpak-dq-system')));
        }
        
        try {
            $api_handler = new TPAK_API_Handler();
            $result = $api_handler->test_connection($url, $username, $password, $survey_id);
            
            // Update test results
            $api_settings = $this->get_settings('api');
            $api_settings['last_test_result'] = $result['success'] ? 'Success' : $result['message'];
            $api_settings['last_test_date'] = current_time('mysql');
            $this->update_settings('api', $api_settings);
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => __('API connection successful!', 'tpak-dq-system'),
                    'details' => $result['details'] ?? ''
                ));
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }
        } catch (Exception $e) {
            TPAK_Security::log_security_event('api_test_error', $e->getMessage());
            wp_send_json_error(array('message' => __('Connection test failed: ', 'tpak-dq-system') . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for manual import
     */
    public function ajax_manual_import() {
        TPAK_Security_Middleware::secure_ajax_action(
            'manage_tpak_settings',
            array($this, 'manual_import_handler')
        );
    }
    
    /**
     * Manual import handler
     */
    public function manual_import_handler() {
        // Rate limiting for manual imports
        if (!TPAK_Security::check_rate_limit('manual_import', 3, 600)) {
            wp_send_json_error(array('message' => __('Too many import attempts. Please wait before trying again.', 'tpak-dq-system')));
        }
        
        try {
            $cron = TPAK_Cron::get_instance();
            $result = $cron->execute_import();
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => sprintf(__('Import completed successfully. %d records processed.', 'tpak-dq-system'), $result['count']),
                    'details' => $result
                ));
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }
        } catch (Exception $e) {
            TPAK_Security::log_security_event('manual_import_error', $e->getMessage());
            wp_send_json_error(array('message' => __('Import failed: ', 'tpak-dq-system') . $e->getMessage()));
        }
    }

    /**
     * Render API settings tab
     * 
     * @param array $settings
     */
    public function render_api_settings_tab($settings) {
        $api_settings = $settings['api'];
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field(TPAK_Security::NONCE_SETTINGS, '_wpnonce'); ?>
            <input type="hidden" name="action" value="tpak_save_settings">
            <input type="hidden" name="active_tab" value="api">
            
            <div class="tpak-settings-section">
                <h2><?php _e('LimeSurvey API Configuration', 'tpak-dq-system'); ?></h2>
                <p class="description">
                    <?php _e('Configure the connection to your LimeSurvey RemoteControl 2 API.', 'tpak-dq-system'); ?>
                </p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="limesurvey_url"><?php _e('LimeSurvey API URL', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="limesurvey_url" name="limesurvey_url" 
                                   value="<?php echo esc_attr($api_settings['limesurvey_url']); ?>" 
                                   class="regular-text" required>
                            <p class="description">
                                <?php _e('Full URL to your LimeSurvey RemoteControl API endpoint (e.g., https://survey.example.com/index.php/admin/remotecontrol)', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="api_username"><?php _e('Username', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="api_username" name="api_username" 
                                   value="<?php echo esc_attr($api_settings['username']); ?>" 
                                   class="regular-text" required>
                            <p class="description">
                                <?php _e('LimeSurvey administrator username', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="api_password"><?php _e('Password', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="api_password" name="api_password" 
                                   value="<?php echo esc_attr($api_settings['password']); ?>" 
                                   class="regular-text" required>
                            <p class="description">
                                <?php _e('LimeSurvey administrator password', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="survey_id"><?php _e('Survey ID', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="survey_id" name="survey_id" 
                                   value="<?php echo esc_attr($api_settings['survey_id']); ?>" 
                                   class="small-text" min="1" required>
                            <p class="description">
                                <?php _e('The ID of the survey to import data from', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="connection_timeout"><?php _e('Connection Timeout', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="connection_timeout" name="connection_timeout" 
                                   value="<?php echo esc_attr($api_settings['connection_timeout']); ?>" 
                                   class="small-text" min="5" max="300">
                            <span><?php _e('seconds', 'tpak-dq-system'); ?></span>
                            <p class="description">
                                <?php _e('Maximum time to wait for API responses (5-300 seconds)', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="tpak-api-test-section">
                    <h3><?php _e('Connection Test', 'tpak-dq-system'); ?></h3>
                    <p>
                        <button type="button" id="test-api-connection" class="button button-secondary">
                            <?php _e('Test API Connection', 'tpak-dq-system'); ?>
                        </button>
                    </p>
                    <div id="api-test-result"></div>
                    
                    <?php if (!empty($api_settings['last_test_date'])): ?>
                        <p class="description">
                            <?php printf(
                                __('Last test: %s - %s', 'tpak-dq-system'),
                                esc_html($api_settings['last_test_date']),
                                esc_html($api_settings['last_test_result'])
                            ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php submit_button(__('Save API Settings', 'tpak-dq-system')); ?>
        </form>
        <?php
    }
    
    /**
     * Render cron settings tab
     * 
     * @param array $settings
     */
    public function render_cron_settings_tab($settings) {
        $cron_settings = $settings['cron'];
        $api_settings = $settings['api'];
        
        // Get cron status
        $next_scheduled = wp_next_scheduled('tpak_import_survey_data');
        $cron_status = $next_scheduled ? 'scheduled' : 'not_scheduled';
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('tpak_save_settings', 'tpak_settings_nonce'); ?>
            <input type="hidden" name="active_tab" value="cron">
            
            <div class="tpak-settings-section">
                <h2><?php _e('Automated Data Import', 'tpak-dq-system'); ?></h2>
                <p class="description">
                    <?php _e('Configure automatic import of survey data from LimeSurvey.', 'tpak-dq-system'); ?>
                </p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <?php _e('Enable Automated Import', 'tpak-dq-system'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="import_enabled" value="1" 
                                           <?php checked($cron_settings['import_enabled']); ?>>
                                    <?php _e('Enable automatic data import', 'tpak-dq-system'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, the system will automatically import new survey responses at the specified interval.', 'tpak-dq-system'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="import_interval"><?php _e('Import Interval', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <select id="import_interval" name="import_interval">
                                <option value="hourly" <?php selected($cron_settings['import_interval'], 'hourly'); ?>>
                                    <?php _e('Every Hour', 'tpak-dq-system'); ?>
                                </option>
                                <option value="twicedaily" <?php selected($cron_settings['import_interval'], 'twicedaily'); ?>>
                                    <?php _e('Twice Daily', 'tpak-dq-system'); ?>
                                </option>
                                <option value="daily" <?php selected($cron_settings['import_interval'], 'daily'); ?>>
                                    <?php _e('Daily', 'tpak-dq-system'); ?>
                                </option>
                                <option value="weekly" <?php selected($cron_settings['import_interval'], 'weekly'); ?>>
                                    <?php _e('Weekly', 'tpak-dq-system'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('How often to check for new survey responses', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cron_survey_id"><?php _e('Survey ID', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cron_survey_id" name="cron_survey_id" 
                                   value="<?php echo esc_attr($cron_settings['survey_id'] ?: $api_settings['survey_id']); ?>" 
                                   class="small-text" min="1">
                            <p class="description">
                                <?php _e('Survey ID to import data from (defaults to API settings survey ID)', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="import_limit"><?php _e('Import Limit', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="import_limit" name="import_limit" 
                                   value="<?php echo esc_attr($cron_settings['import_limit']); ?>" 
                                   class="small-text" min="1" max="1000">
                            <p class="description">
                                <?php _e('Maximum number of responses to import per run (1-1000)', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="retry_attempts"><?php _e('Retry Attempts', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="retry_attempts" name="retry_attempts" 
                                   value="<?php echo esc_attr($cron_settings['retry_attempts']); ?>" 
                                   class="small-text" min="0" max="10">
                            <p class="description">
                                <?php _e('Number of times to retry failed imports (0-10)', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="tpak-cron-status-section">
                    <h3><?php _e('Import Status', 'tpak-dq-system'); ?></h3>
                    
                    <div class="tpak-status-info">
                        <p>
                            <strong><?php _e('Current Status:', 'tpak-dq-system'); ?></strong>
                            <?php if ($cron_status === 'scheduled'): ?>
                                <span class="tpak-status-active"><?php _e('Scheduled', 'tpak-dq-system'); ?></span>
                                <br>
                                <span class="description">
                                    <?php printf(
                                        __('Next import: %s', 'tpak-dq-system'),
                                        date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $next_scheduled)
                                    ); ?>
                                </span>
                            <?php else: ?>
                                <span class="tpak-status-inactive"><?php _e('Not Scheduled', 'tpak-dq-system'); ?></span>
                            <?php endif; ?>
                        </p>
                        
                        <?php if (!empty($cron_settings['last_import_date'])): ?>
                            <p>
                                <strong><?php _e('Last Import:', 'tpak-dq-system'); ?></strong>
                                <?php echo esc_html($cron_settings['last_import_date']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <p>
                        <button type="button" id="trigger-manual-import" class="button button-secondary">
                            <?php _e('Trigger Manual Import', 'tpak-dq-system'); ?>
                        </button>
                    </p>
                    <div id="manual-import-result"></div>
                </div>
            </div>
            
            <?php submit_button(__('Save Automation Settings', 'tpak-dq-system')); ?>
        </form>
        <?php
    }
    
    /**
     * Render notifications settings tab
     * 
     * @param array $settings
     */
    public function render_notifications_settings_tab($settings) {
        $notification_settings = $settings['notifications'];
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('tpak_save_settings', 'tpak_settings_nonce'); ?>
            <input type="hidden" name="active_tab" value="notifications">
            
            <div class="tpak-settings-section">
                <h2><?php _e('Email Notifications', 'tpak-dq-system'); ?></h2>
                <p class="description">
                    <?php _e('Configure email notifications for workflow events and system alerts.', 'tpak-dq-system'); ?>
                </p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <?php _e('Enable Email Notifications', 'tpak-dq-system'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="email_enabled" value="1" 
                                           <?php checked($notification_settings['email_enabled']); ?>>
                                    <?php _e('Send email notifications', 'tpak-dq-system'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, the system will send email notifications for workflow events.', 'tpak-dq-system'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="notification_emails"><?php _e('Notification Recipients', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <textarea id="notification_emails" name="notification_emails" 
                                      class="large-text" rows="3"><?php 
                                echo esc_textarea(implode(', ', $notification_settings['notification_emails'])); 
                            ?></textarea>
                            <p class="description">
                                <?php _e('Comma-separated list of email addresses to receive system notifications (in addition to assigned users)', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php _e('Notification Events', 'tpak-dq-system'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="send_on_assignment" value="1" 
                                           <?php checked($notification_settings['send_on_assignment']); ?>>
                                    <?php _e('Send notifications when data is assigned to users', 'tpak-dq-system'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="send_on_status_change" value="1" 
                                           <?php checked($notification_settings['send_on_status_change']); ?>>
                                    <?php _e('Send notifications when data status changes', 'tpak-dq-system'); ?>
                                </label><br>
                                
                                <label>
                                    <input type="checkbox" name="send_on_error" value="1" 
                                           <?php checked($notification_settings['send_on_error']); ?>>
                                    <?php _e('Send notifications for system errors and import failures', 'tpak-dq-system'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="email_template"><?php _e('Email Template', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <select id="email_template" name="email_template">
                                <option value="default" <?php selected($notification_settings['email_template'], 'default'); ?>>
                                    <?php _e('Default Template', 'tpak-dq-system'); ?>
                                </option>
                                <option value="minimal" <?php selected($notification_settings['email_template'], 'minimal'); ?>>
                                    <?php _e('Minimal Template', 'tpak-dq-system'); ?>
                                </option>
                                <option value="detailed" <?php selected($notification_settings['email_template'], 'detailed'); ?>>
                                    <?php _e('Detailed Template', 'tpak-dq-system'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Choose the email template style for notifications', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php submit_button(__('Save Notification Settings', 'tpak-dq-system')); ?>
        </form>
        <?php
    }
    
    /**
     * Render workflow settings tab
     * 
     * @param array $settings
     */
    public function render_workflow_settings_tab($settings) {
        $workflow_settings = $settings['workflow'];
        ?>
        <form method="post" action="">
            <?php wp_nonce_field('tpak_save_settings', 'tpak_settings_nonce'); ?>
            <input type="hidden" name="active_tab" value="workflow">
            
            <div class="tpak-settings-section">
                <h2><?php _e('Workflow Configuration', 'tpak-dq-system'); ?></h2>
                <p class="description">
                    <?php _e('Configure workflow behavior including sampling rates and audit settings.', 'tpak-dq-system'); ?>
                </p>
                
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="sampling_percentage"><?php _e('Sampling Percentage', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="sampling_percentage" name="sampling_percentage" 
                                   value="<?php echo esc_attr($workflow_settings['sampling_percentage']); ?>" 
                                   class="small-text" min="1" max="100" required>
                            <span>%</span>
                            <p class="description">
                                <?php _e('Percentage of data sent to Examiner (C) after Supervisor (B) approval. Remaining data is automatically finalized.', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php _e('Sampling Behavior', 'tpak-dq-system'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="auto_finalize_sampling" value="1" 
                                           <?php checked($workflow_settings['auto_finalize_sampling']); ?>>
                                    <?php _e('Automatically finalize data not sent to Examiner', 'tpak-dq-system'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, data not selected for examination will be automatically marked as finalized.', 'tpak-dq-system'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php _e('Comments Requirement', 'tpak-dq-system'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="require_comments" value="1" 
                                           <?php checked($workflow_settings['require_comments']); ?>>
                                    <?php _e('Require comments for workflow actions', 'tpak-dq-system'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, users must provide comments when approving or rejecting data.', 'tpak-dq-system'); ?>
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="audit_retention_days"><?php _e('Audit Trail Retention', 'tpak-dq-system'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="audit_retention_days" name="audit_retention_days" 
                                   value="<?php echo esc_attr($workflow_settings['audit_retention_days']); ?>" 
                                   class="small-text" min="30" max="3650">
                            <span><?php _e('days', 'tpak-dq-system'); ?></span>
                            <p class="description">
                                <?php _e('Number of days to retain audit trail records (30-3650 days)', 'tpak-dq-system'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <div class="tpak-workflow-info-section">
                    <h3><?php _e('Workflow Overview', 'tpak-dq-system'); ?></h3>
                    <div class="tpak-workflow-diagram">
                        <p><?php _e('Current workflow configuration:', 'tpak-dq-system'); ?></p>
                        <ul>
                            <li><?php _e('1. New data → Interviewer (A) for initial review', 'tpak-dq-system'); ?></li>
                            <li><?php _e('2. Approved data → Supervisor (B) for secondary review', 'tpak-dq-system'); ?></li>
                            <li><?php printf(
                                __('3. Supervisor approval → %d%% to Examiner (C), %d%% automatically finalized', 'tpak-dq-system'),
                                $workflow_settings['sampling_percentage'],
                                100 - $workflow_settings['sampling_percentage']
                            ); ?></li>
                            <li><?php _e('4. Examiner review → Final approval or rejection', 'tpak-dq-system'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <?php submit_button(__('Save Workflow Settings', 'tpak-dq-system')); ?>
        </form>
        <?php
    }
    
    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_api_connection() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_admin_ajax')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        // Check permissions
        if (!current_user_can('tpak_manage_settings')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'tpak-dq-system')));
        }
        
        $url = sanitize_url($_POST['url']);
        $username = sanitize_text_field($_POST['username']);
        $password = sanitize_text_field($_POST['password']);
        $survey_id = absint($_POST['survey_id']);
        
        // Validate inputs
        $validator = TPAK_Validator::get_instance();
        if (!$validator->validate_limesurvey_url($url) || 
            !$validator->validate_text($username, 'Username') ||
            !$validator->validate_text($password, 'Password') ||
            !$validator->validate_numeric_id($survey_id, 'Survey ID')) {
            wp_send_json_error(array('message' => $validator->get_error_messages()));
        }
        
        // Test API connection
        try {
            $api_handler = TPAK_API_Handler::get_instance();
            $result = $api_handler->test_connection($url, $username, $password, $survey_id);
            
            // Update test results in settings
            $api_settings = $this->get_settings('api');
            $api_settings['last_test_result'] = $result['success'] ? 'Success' : $result['message'];
            $api_settings['last_test_date'] = current_time('mysql');
            $this->update_settings('api', $api_settings);
            
            if ($result['success']) {
                wp_send_json_success(array('message' => __('API connection successful!', 'tpak-dq-system')));
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler for manual import
     */
    public function ajax_manual_import() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'tpak_admin_ajax')) {
            wp_die(__('Security check failed.', 'tpak-dq-system'));
        }
        
        // Check permissions
        if (!current_user_can('tpak_manage_settings')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'tpak-dq-system')));
        }
        
        try {
            // Trigger manual import
            $cron_handler = TPAK_Cron::get_instance();
            $result = $cron_handler->execute_import();
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => sprintf(
                        __('Import completed successfully. %d records imported.', 'tpak-dq-system'),
                        $result['imported_count']
                    )
                ));
            } else {
                wp_send_json_error(array('message' => $result['message']));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }
}