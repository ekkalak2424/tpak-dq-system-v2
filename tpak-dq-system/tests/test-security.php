<?php
/**
 * Security Tests
 *
 * Tests for security features including nonce verification,
 * input sanitization, access control, and XSS/CSRF prevention.
 *
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

class TPAK_Security_Test extends WP_UnitTestCase {
    
    private $admin_user;
    private $interviewer_user;
    private $supervisor_user;
    private $examiner_user;
    private $regular_user;
    private $test_post_id;
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        
        // Create test users with different roles
        $this->admin_user = $this->factory->user->create(array(
            'role' => 'administrator'
        ));
        
        $this->interviewer_user = $this->factory->user->create();
        $interviewer = new WP_User($this->interviewer_user);
        $interviewer->add_role('tpak_interviewer_a');
        
        $this->supervisor_user = $this->factory->user->create();
        $supervisor = new WP_User($this->supervisor_user);
        $supervisor->add_role('tpak_supervisor_b');
        
        $this->examiner_user = $this->factory->user->create();
        $examiner = new WP_User($this->examiner_user);
        $examiner->add_role('tpak_examiner_c');
        
        $this->regular_user = $this->factory->user->create(array(
            'role' => 'subscriber'
        ));
        
        // Create test post
        $this->test_post_id = $this->factory->post->create(array(
            'post_type' => 'tpak_survey_data',
            'post_status' => 'publish'
        ));
        
        // Set initial status
        update_post_meta($this->test_post_id, '_tpak_status', 'pending_a');
        
        // Initialize security
        TPAK_Security::init();
        TPAK_Security_Middleware::init();
    }
    
    /**
     * Test nonce creation and verification
     */
    public function test_nonce_creation_and_verification() {
        $action = TPAK_Security::NONCE_SETTINGS;
        
        // Test nonce creation
        $nonce = TPAK_Security::create_nonce($action);
        $this->assertNotEmpty($nonce);
        $this->assertTrue(is_string($nonce));
        
        // Test nonce verification
        $this->assertTrue(TPAK_Security::verify_nonce($nonce, $action));
        
        // Test invalid nonce
        $this->assertFalse(TPAK_Security::verify_nonce('invalid_nonce', $action));
        
        // Test wrong action
        $this->assertFalse(TPAK_Security::verify_nonce($nonce, 'wrong_action'));
    }
    
    /**
     * Test request nonce verification
     */
    public function test_request_nonce_verification() {
        $action = TPAK_Security::NONCE_AJAX;
        $nonce = TPAK_Security::create_nonce($action);
        
        // Test POST nonce
        $_POST['_wpnonce'] = $nonce;
        $this->assertTrue(TPAK_Security::verify_request_nonce($action));
        
        // Test GET nonce
        unset($_POST['_wpnonce']);
        $_GET['_wpnonce'] = $nonce;
        $this->assertTrue(TPAK_Security::verify_request_nonce($action));
        
        // Test custom field name
        $_POST['custom_nonce'] = $nonce;
        $this->assertTrue(TPAK_Security::verify_request_nonce($action, 'custom_nonce'));
        
        // Clean up
        unset($_POST['_wpnonce'], $_GET['_wpnonce'], $_POST['custom_nonce']);
    }
    
    /**
     * Test capability checking
     */
    public function test_capability_checking() {
        // Test admin user
        wp_set_current_user($this->admin_user);
        $this->assertTrue(TPAK_Security::current_user_can('manage_options'));
        $this->assertTrue(TPAK_Security::can_access_admin());
        $this->assertTrue(TPAK_Security::can_manage_settings());
        
        // Test interviewer user
        wp_set_current_user($this->interviewer_user);
        $this->assertFalse(TPAK_Security::current_user_can('manage_options'));
        $this->assertTrue(TPAK_Security::can_access_admin());
        $this->assertFalse(TPAK_Security::can_manage_settings());
        
        // Test regular user
        wp_set_current_user($this->regular_user);
        $this->assertFalse(TPAK_Security::can_access_admin());
        $this->assertFalse(TPAK_Security::can_manage_settings());
    }
    
    /**
     * Test data access permissions
     */
    public function test_data_access_permissions() {
        // Test interviewer access to pending_a data
        wp_set_current_user($this->interviewer_user);
        update_post_meta($this->test_post_id, '_tpak_status', 'pending_a');
        $this->assertTrue(TPAK_Security::can_edit_data($this->test_post_id));
        
        // Test interviewer cannot access pending_b data
        update_post_meta($this->test_post_id, '_tpak_status', 'pending_b');
        $this->assertFalse(TPAK_Security::can_edit_data($this->test_post_id));
        
        // Test supervisor access to pending_b data
        wp_set_current_user($this->supervisor_user);
        $this->assertTrue(TPAK_Security::can_edit_data($this->test_post_id));
        
        // Test examiner access to pending_c data
        wp_set_current_user($this->examiner_user);
        update_post_meta($this->test_post_id, '_tpak_status', 'pending_c');
        $this->assertTrue(TPAK_Security::can_edit_data($this->test_post_id));
        
        // Test admin access to all data
        wp_set_current_user($this->admin_user);
        $this->assertTrue(TPAK_Security::can_edit_data($this->test_post_id));
    }
    
    /**
     * Test input sanitization
     */
    public function test_input_sanitization() {
        // Test text sanitization
        $dirty_text = '<script>alert("xss")</script>Hello World';
        $clean_text = TPAK_Security::sanitize_text($dirty_text);
        $this->assertEquals('Hello World', $clean_text);
        
        // Test text with length limit
        $long_text = str_repeat('a', 100);
        $limited_text = TPAK_Security::sanitize_text($long_text, 50);
        $this->assertEquals(50, strlen($limited_text));
        
        // Test textarea sanitization
        $dirty_textarea = "<script>alert('xss')</script>\nLine 1\nLine 2";
        $clean_textarea = TPAK_Security::sanitize_textarea($dirty_textarea);
        $this->assertStringContainsString("Line 1\nLine 2", $clean_textarea);
        $this->assertStringNotContainsString('<script>', $clean_textarea);
        
        // Test URL sanitization
        $dirty_url = 'javascript:alert("xss")';
        $clean_url = TPAK_Security::sanitize_url($dirty_url);
        $this->assertEquals('', $clean_url);
        
        $valid_url = 'https://example.com/path?param=value';
        $clean_valid_url = TPAK_Security::sanitize_url($valid_url);
        $this->assertEquals($valid_url, $clean_valid_url);
        
        // Test email sanitization
        $dirty_email = 'test@example.com<script>';
        $clean_email = TPAK_Security::sanitize_email($dirty_email);
        $this->assertEquals('test@example.com', $clean_email);
        
        // Test integer sanitization
        $this->assertEquals(42, TPAK_Security::sanitize_int('42'));
        $this->assertEquals(0, TPAK_Security::sanitize_int('not_a_number'));
        $this->assertEquals(10, TPAK_Security::sanitize_int(5, 10, 20)); // Min constraint
        $this->assertEquals(20, TPAK_Security::sanitize_int(25, 10, 20)); // Max constraint
    }
    
    /**
     * Test JSON sanitization
     */
    public function test_json_sanitization() {
        // Test valid JSON
        $valid_json = '{"key": "value", "number": 42}';
        $sanitized = TPAK_Security::sanitize_json($valid_json);
        $this->assertNotFalse($sanitized);
        $this->assertJson($sanitized);
        
        // Test invalid JSON
        $invalid_json = '{"key": "value"'; // Missing closing brace
        $this->assertFalse(TPAK_Security::sanitize_json($invalid_json));
        
        // Test size limit
        $large_json = '{"data": "' . str_repeat('a', 70000) . '"}';
        $this->assertFalse(TPAK_Security::sanitize_json($large_json, 65535));
    }
    
    /**
     * Test output escaping
     */
    public function test_output_escaping() {
        $dangerous_text = '<script>alert("xss")</script>Hello & "World"';
        
        // Test HTML escaping
        $escaped_html = TPAK_Security::escape_html($dangerous_text);
        $this->assertStringNotContainsString('<script>', $escaped_html);
        $this->assertStringContainsString('&lt;script&gt;', $escaped_html);
        
        // Test attribute escaping
        $escaped_attr = TPAK_Security::escape_attr($dangerous_text);
        $this->assertStringNotContainsString('"', $escaped_attr);
        $this->assertStringContainsString('&quot;', $escaped_attr);
        
        // Test URL escaping
        $dangerous_url = 'http://example.com/path?param=<script>';
        $escaped_url = TPAK_Security::escape_url($dangerous_url);
        $this->assertStringNotContainsString('<script>', $escaped_url);
        
        // Test JavaScript escaping
        $dangerous_js = 'alert("xss"); //';
        $escaped_js = TPAK_Security::escape_js($dangerous_js);
        $this->assertStringNotContainsString('alert(', $escaped_js);
    }
    
    /**
     * Test session management
     */
    public function test_session_management() {
        wp_set_current_user($this->admin_user);
        
        // Start session
        TPAK_Security::start_session();
        $this->assertTrue(session_id() !== '');
        
        // Check session validity
        $this->assertTrue(TPAK_Security::is_session_valid());
        
        // Test session timeout
        $_SESSION['tpak_session_start'] = time() - (TPAK_Security::SESSION_TIMEOUT + 100);
        $this->assertFalse(TPAK_Security::is_session_valid());
        
        // Test user mismatch
        $_SESSION['tpak_session_start'] = time();
        $_SESSION['tpak_user_id'] = 999; // Different user
        $this->assertFalse(TPAK_Security::is_session_valid());
        
        // Destroy session
        TPAK_Security::destroy_session();
        $this->assertFalse(session_id());
    }
    
    /**
     * Test rate limiting
     */
    public function test_rate_limiting() {
        wp_set_current_user($this->admin_user);
        
        $action = 'test_action';
        $limit = 3;
        $window = 300;
        
        // Test within limits
        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue(TPAK_Security::check_rate_limit($action, $limit, $window));
        }
        
        // Test exceeding limit
        $this->assertFalse(TPAK_Security::check_rate_limit($action, $limit, $window));
    }
    
    /**
     * Test security logging
     */
    public function test_security_logging() {
        wp_set_current_user($this->admin_user);
        
        $event = 'test_event';
        $message = 'Test security event';
        $context = array('test' => 'data');
        
        // Clear existing logs
        delete_option('tpak_security_logs');
        
        // Log event
        TPAK_Security::log_security_event($event, $message, $context);
        
        // Check log was created
        $logs = get_option('tpak_security_logs', array());
        $this->assertNotEmpty($logs);
        $this->assertEquals($event, $logs[0]['event']);
        $this->assertEquals($message, $logs[0]['message']);
        $this->assertEquals($context, $logs[0]['context']);
    }
    
    /**
     * Test middleware security
     */
    public function test_middleware_security() {
        wp_set_current_user($this->admin_user);
        
        $callback_executed = false;
        $callback = function() use (&$callback_executed) {
            $callback_executed = true;
            return 'success';
        };
        
        // Test successful middleware execution
        $result = TPAK_Security::admin_action_middleware('manage_options', $callback);
        $this->assertTrue($callback_executed);
        $this->assertEquals('success', $result);
        
        // Test unauthorized access
        wp_set_current_user($this->regular_user);
        $callback_executed = false;
        
        $result = TPAK_Security::admin_action_middleware('manage_options', $callback);
        $this->assertFalse($callback_executed);
        $this->assertFalse($result);
    }
    
    /**
     * Test XSS prevention
     */
    public function test_xss_prevention() {
        $xss_payloads = array(
            '<script>alert("xss")</script>',
            'javascript:alert("xss")',
            '<img src="x" onerror="alert(\'xss\')">',
            '<svg onload="alert(\'xss\')">',
            '"><script>alert("xss")</script>',
            '\';alert(\'xss\');//'
        );
        
        foreach ($xss_payloads as $payload) {
            // Test text sanitization
            $sanitized = TPAK_Security::sanitize_text($payload);
            $this->assertStringNotContainsString('<script>', $sanitized);
            $this->assertStringNotContainsString('javascript:', $sanitized);
            $this->assertStringNotContainsString('onerror=', $sanitized);
            $this->assertStringNotContainsString('onload=', $sanitized);
            
            // Test HTML escaping
            $escaped = TPAK_Security::escape_html($payload);
            $this->assertStringNotContainsString('<script>', $escaped);
            $this->assertStringContainsString('&lt;', $escaped);
        }
    }
    
    /**
     * Test CSRF prevention
     */
    public function test_csrf_prevention() {
        wp_set_current_user($this->admin_user);
        
        $action = TPAK_Security::NONCE_SETTINGS;
        $nonce = TPAK_Security::create_nonce($action);
        
        // Test valid nonce
        $_POST['_wpnonce'] = $nonce;
        $this->assertTrue(TPAK_Security::verify_request_nonce($action));
        
        // Test CSRF attack (no nonce)
        unset($_POST['_wpnonce']);
        $this->assertFalse(TPAK_Security::verify_request_nonce($action));
        
        // Test CSRF attack (wrong nonce)
        $_POST['_wpnonce'] = 'malicious_nonce';
        $this->assertFalse(TPAK_Security::verify_request_nonce($action));
        
        // Clean up
        unset($_POST['_wpnonce']);
    }
    
    /**
     * Test SQL injection prevention
     */
    public function test_sql_injection_prevention() {
        global $wpdb;
        
        $malicious_inputs = array(
            "'; DROP TABLE wp_posts; --",
            "1' OR '1'='1",
            "1; DELETE FROM wp_posts WHERE 1=1; --",
            "1' UNION SELECT * FROM wp_users --"
        );
        
        foreach ($malicious_inputs as $input) {
            // Test integer sanitization
            $sanitized_int = TPAK_Security::sanitize_int($input);
            $this->assertTrue(is_int($sanitized_int));
            $this->assertStringNotContainsString('DROP', (string)$sanitized_int);
            $this->assertStringNotContainsString('DELETE', (string)$sanitized_int);
            $this->assertStringNotContainsString('UNION', (string)$sanitized_int);
            
            // Test text sanitization
            $sanitized_text = TPAK_Security::sanitize_text($input);
            $this->assertStringNotContainsString('--', $sanitized_text);
            $this->assertStringNotContainsString(';', $sanitized_text);
        }
    }
    
    /**
     * Test secure data access middleware
     */
    public function test_secure_data_access_middleware() {
        wp_set_current_user($this->interviewer_user);
        update_post_meta($this->test_post_id, '_tpak_status', 'pending_a');
        
        $callback_executed = false;
        $callback = function($post_id, $post) use (&$callback_executed) {
            $callback_executed = true;
            $this->assertEquals($this->test_post_id, $post_id);
            $this->assertEquals('tpak_survey_data', $post->post_type);
        };
        
        // Test authorized access
        ob_start();
        TPAK_Security_Middleware::secure_data_access($this->test_post_id, 'edit', $callback);
        ob_end_clean();
        $this->assertTrue($callback_executed);
        
        // Test unauthorized access (wrong status)
        update_post_meta($this->test_post_id, '_tpak_status', 'pending_b');
        $callback_executed = false;
        
        $this->expectException('WPDieException');
        TPAK_Security_Middleware::secure_data_access($this->test_post_id, 'edit', $callback);
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        // Clean up session
        if (session_id()) {
            session_destroy();
        }
        
        // Clean up globals
        unset($_POST, $_GET);
        $_POST = array();
        $_GET = array();
        
        // Clean up options
        delete_option('tpak_security_logs');
        
        parent::tearDown();
    }
}