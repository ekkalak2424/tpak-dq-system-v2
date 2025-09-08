<?php
/**
 * Unit Tests for Validator
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

/**
 * Test TPAK Validator Class
 */
class Test_TPAK_Validator extends WP_UnitTestCase {
    
    /**
     * Validator instance
     * 
     * @var TPAK_Validator
     */
    private $validator;
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->validator = TPAK_Validator::get_instance();
    }
    
    /**
     * Test email validation
     */
    public function test_email_validation() {
        // Valid emails
        $this->assertTrue($this->validator->validate_email('test@example.com'));
        $this->assertTrue($this->validator->validate_email('user.name+tag@domain.co.uk'));
        
        // Invalid emails
        $this->assertFalse($this->validator->validate_email(''));
        $this->assertFalse($this->validator->validate_email('invalid-email'));
        $this->assertFalse($this->validator->validate_email('test@'));
        $this->assertFalse($this->validator->validate_email('@example.com'));
        
        // Check error messages
        $this->validator->clear_errors();
        $this->validator->validate_email('');
        $this->assertTrue($this->validator->has_errors());
        $this->assertArrayHasKey('email', $this->validator->get_errors());
    }
    
    /**
     * Test URL validation
     */
    public function test_url_validation() {
        // Valid URLs
        $this->assertTrue($this->validator->validate_url('https://example.com'));
        $this->assertTrue($this->validator->validate_url('http://subdomain.example.com/path'));
        
        // Invalid URLs
        $this->assertFalse($this->validator->validate_url(''));
        $this->assertFalse($this->validator->validate_url('not-a-url'));
        $this->assertFalse($this->validator->validate_url('ftp://example.com')); // Wrong scheme
        
        // Test custom schemes
        $this->assertTrue($this->validator->validate_url('ftp://example.com', array('ftp')));
        
        // Check error handling
        $this->validator->clear_errors();
        $this->validator->validate_url('invalid');
        $this->assertTrue($this->validator->has_errors());
    }
    
    /**
     * Test LimeSurvey URL validation
     */
    public function test_limesurvey_url_validation() {
        // Valid LimeSurvey URLs
        $this->assertTrue($this->validator->validate_limesurvey_url('https://survey.example.com/admin/remotecontrol'));
        $this->assertTrue($this->validator->validate_limesurvey_url('http://localhost/limesurvey/index.php/admin/remotecontrol'));
        $this->assertTrue($this->validator->validate_limesurvey_url('https://example.com/index.php?r=admin/remotecontrol'));
        
        // Invalid LimeSurvey URLs
        $this->assertFalse($this->validator->validate_limesurvey_url('https://example.com')); // No endpoint
        $this->assertFalse($this->validator->validate_limesurvey_url('https://example.com/wrong/endpoint'));
        
        // Check error messages
        $this->validator->clear_errors();
        $this->validator->validate_limesurvey_url('https://example.com');
        $this->assertTrue($this->validator->has_errors());
        $this->assertArrayHasKey('limesurvey_url', $this->validator->get_errors());
    }
    
    /**
     * Test numeric ID validation
     */
    public function test_numeric_id_validation() {
        // Valid IDs
        $this->assertTrue($this->validator->validate_numeric_id('123'));
        $this->assertTrue($this->validator->validate_numeric_id(456));
        $this->assertTrue($this->validator->validate_numeric_id('0', 'ID', true)); // Allow zero
        
        // Invalid IDs
        $this->assertFalse($this->validator->validate_numeric_id(''));
        $this->assertFalse($this->validator->validate_numeric_id('abc'));
        $this->assertFalse($this->validator->validate_numeric_id('-1'));
        $this->assertFalse($this->validator->validate_numeric_id('0')); // Zero not allowed by default
        
        // Check error handling
        $this->validator->clear_errors();
        $this->validator->validate_numeric_id('abc', 'Survey ID');
        $this->assertTrue($this->validator->has_errors());
        $errors = $this->validator->get_errors();
        $this->assertStringContainsString('Survey ID', $errors['Survey ID']);
    }
    
    /**
     * Test percentage validation
     */
    public function test_percentage_validation() {
        // Valid percentages
        $this->assertTrue($this->validator->validate_percentage('50'));
        $this->assertTrue($this->validator->validate_percentage(75.5));
        $this->assertTrue($this->validator->validate_percentage('1'));
        $this->assertTrue($this->validator->validate_percentage('100'));
        
        // Invalid percentages
        $this->assertFalse($this->validator->validate_percentage(''));
        $this->assertFalse($this->validator->validate_percentage('0'));
        $this->assertFalse($this->validator->validate_percentage('101'));
        $this->assertFalse($this->validator->validate_percentage('-5'));
        $this->assertFalse($this->validator->validate_percentage('abc'));
        
        // Check error messages
        $this->validator->clear_errors();
        $this->validator->validate_percentage('150', 'Sampling Rate');
        $this->assertTrue($this->validator->has_errors());
        $errors = $this->validator->get_errors();
        $this->assertStringContainsString('Sampling Rate', $errors['Sampling Rate']);
    }
    
    /**
     * Test text validation
     */
    public function test_text_validation() {
        // Valid text
        $this->assertTrue($this->validator->validate_text('Valid text'));
        $this->assertTrue($this->validator->validate_text('', 'Optional', 0, 255, false)); // Not required
        
        // Invalid text - too short
        $this->assertFalse($this->validator->validate_text('Hi', 'Name', 5));
        
        // Invalid text - too long
        $this->assertFalse($this->validator->validate_text(str_repeat('a', 300), 'Description', 0, 255));
        
        // Invalid text - required but empty
        $this->assertFalse($this->validator->validate_text('', 'Required Field'));
        
        // Test malicious content detection
        $this->assertFalse($this->validator->validate_text('<script>alert("xss")</script>', 'Content'));
        $this->assertFalse($this->validator->validate_text('javascript:alert(1)', 'URL'));
        
        // Check error handling
        $this->validator->clear_errors();
        $this->validator->validate_text('', 'Username');
        $this->assertTrue($this->validator->has_errors());
    }
    
    /**
     * Test JSON validation
     */
    public function test_json_validation() {
        // Valid JSON
        $this->assertTrue($this->validator->validate_json('{"key": "value"}'));
        $this->assertTrue($this->validator->validate_json('[]'));
        $this->assertTrue($this->validator->validate_json('null'));
        $this->assertTrue($this->validator->validate_json('', 'Optional', false)); // Not required
        
        // Invalid JSON
        $this->assertFalse($this->validator->validate_json('{"invalid": json}'));
        $this->assertFalse($this->validator->validate_json('{key: "value"}')); // Unquoted key
        $this->assertFalse($this->validator->validate_json('', 'Required')); // Required but empty
        
        // Test size limit
        $large_json = '{"data": "' . str_repeat('a', 1048577) . '"}'; // > 1MB
        $this->assertFalse($this->validator->validate_json($large_json));
        
        // Check error messages
        $this->validator->clear_errors();
        $this->validator->validate_json('invalid json', 'Survey Data');
        $this->assertTrue($this->validator->has_errors());
        $errors = $this->validator->get_errors();
        $this->assertStringContainsString('Survey Data', $errors['Survey Data']);
    }
    
    /**
     * Test date validation
     */
    public function test_date_validation() {
        // Valid dates
        $this->assertTrue($this->validator->validate_date('2023-12-25 15:30:00'));
        $this->assertTrue($this->validator->validate_date('2023-12-25', 'Y-m-d'));
        $this->assertTrue($this->validator->validate_date('', 'Y-m-d H:i:s', 'Optional', false)); // Not required
        
        // Invalid dates
        $this->assertFalse($this->validator->validate_date('2023-13-25 15:30:00')); // Invalid month
        $this->assertFalse($this->validator->validate_date('not-a-date'));
        $this->assertFalse($this->validator->validate_date('2023/12/25', 'Y-m-d')); // Wrong format
        $this->assertFalse($this->validator->validate_date('', 'Y-m-d H:i:s', 'Required')); // Required but empty
        
        // Check error handling
        $this->validator->clear_errors();
        $this->validator->validate_date('invalid', 'Y-m-d', 'Import Date');
        $this->assertTrue($this->validator->has_errors());
    }
    
    /**
     * Test API settings validation
     */
    public function test_api_settings_validation() {
        // Valid settings
        $valid_settings = array(
            'limesurvey_url' => 'https://survey.example.com/admin/remotecontrol',
            'username' => 'admin',
            'password' => 'password123',
            'survey_id' => '12345'
        );
        $this->assertTrue($this->validator->validate_api_settings($valid_settings));
        
        // Invalid settings - missing URL
        $invalid_settings = array(
            'username' => 'admin',
            'password' => 'password123',
            'survey_id' => '12345'
        );
        $this->assertFalse($this->validator->validate_api_settings($invalid_settings));
        
        // Invalid settings - invalid survey ID
        $invalid_settings = array(
            'limesurvey_url' => 'https://survey.example.com/admin/remotecontrol',
            'username' => 'admin',
            'password' => 'password123',
            'survey_id' => 'abc'
        );
        $this->assertFalse($this->validator->validate_api_settings($invalid_settings));
        
        // Check multiple errors
        $this->validator->clear_errors();
        $this->validator->validate_api_settings(array());
        $this->assertTrue($this->validator->has_errors());
        $errors = $this->validator->get_errors();
        $this->assertGreaterThan(1, count($errors));
    }
    
    /**
     * Test survey data validation
     */
    public function test_survey_data_validation() {
        // Valid survey data
        $valid_data = array(
            'survey_id' => '12345',
            'response_id' => 'resp_001',
            'responses' => array(
                'Q1' => 'Answer 1',
                'Q2' => array('Option A', 'Option B'),
                'Q3' => '42'
            )
        );
        $this->assertTrue($this->validator->validate_survey_data($valid_data));
        
        // Invalid data - not array
        $this->assertFalse($this->validator->validate_survey_data('not an array'));
        
        // Invalid data - missing required fields
        $invalid_data = array(
            'responses' => array('Q1' => 'Answer')
        );
        $this->assertFalse($this->validator->validate_survey_data($invalid_data));
        
        // Invalid data - invalid survey ID
        $invalid_data = array(
            'survey_id' => 'abc',
            'response_id' => 'resp_001'
        );
        $this->assertFalse($this->validator->validate_survey_data($invalid_data));
        
        // Check error handling
        $this->validator->clear_errors();
        $this->validator->validate_survey_data(array());
        $this->assertTrue($this->validator->has_errors());
    }
    
    /**
     * Test workflow action validation
     */
    public function test_workflow_action_validation() {
        // Create test post
        $post_id = wp_insert_post(array(
            'post_type' => 'tpak_survey_data',
            'post_title' => 'Test Survey Data',
            'post_status' => 'publish'
        ));
        
        update_post_meta($post_id, '_tpak_workflow_status', 'pending_a');
        
        // Create test user with appropriate role
        $user_id = $this->factory->user->create();
        wp_set_current_user($user_id);
        
        // Mock the roles class for testing
        $roles_mock = $this->createMock(TPAK_Roles::class);
        $roles_mock->method('can_user_access_status')->willReturn(true);
        
        // Valid action
        $this->assertTrue($this->validator->validate_workflow_action('approve_to_supervisor', $post_id, $user_id));
        
        // Invalid action
        $this->assertFalse($this->validator->validate_workflow_action('invalid_action', $post_id, $user_id));
        
        // Invalid post ID
        $this->assertFalse($this->validator->validate_workflow_action('approve_to_supervisor', 99999, $user_id));
        
        // Check error handling
        $this->validator->clear_errors();
        $this->validator->validate_workflow_action('invalid_action', $post_id, $user_id);
        $this->assertTrue($this->validator->has_errors());
    }
    
    /**
     * Test input sanitization
     */
    public function test_input_sanitization() {
        // Test text sanitization
        $this->assertEquals('Clean text', $this->validator->sanitize_input('Clean text', 'text'));
        $this->assertEquals('Cleaned', $this->validator->sanitize_input('<script>Cleaned</script>', 'text'));
        
        // Test email sanitization
        $this->assertEquals('test@example.com', $this->validator->sanitize_input('test@example.com', 'email'));
        
        // Test URL sanitization
        $this->assertEquals('https://example.com', $this->validator->sanitize_input('https://example.com', 'url'));
        
        // Test integer sanitization
        $this->assertEquals(123, $this->validator->sanitize_input('123', 'int'));
        $this->assertEquals(0, $this->validator->sanitize_input('-123', 'int')); // absint removes negative
        
        // Test float sanitization
        $this->assertEquals(123.45, $this->validator->sanitize_input('123.45', 'float'));
        
        // Test boolean sanitization
        $this->assertTrue($this->validator->sanitize_input('1', 'bool'));
        $this->assertFalse($this->validator->sanitize_input('0', 'bool'));
        
        // Test JSON sanitization
        $json_input = array('key' => 'value');
        $result = $this->validator->sanitize_input($json_input, 'json');
        $this->assertEquals('{"key":"value"}', $result);
        
        // Test invalid JSON
        $result = $this->validator->sanitize_input('invalid json', 'json');
        $this->assertEquals('{}', $result);
    }
    
    /**
     * Test error handling methods
     */
    public function test_error_handling() {
        // Clear errors
        $this->validator->clear_errors();
        $this->assertFalse($this->validator->has_errors());
        $this->assertEmpty($this->validator->get_errors());
        
        // Add some errors
        $this->validator->validate_email(''); // Will add error
        $this->validator->validate_numeric_id('abc'); // Will add error
        
        // Check errors exist
        $this->assertTrue($this->validator->has_errors());
        $errors = $this->validator->get_errors();
        $this->assertCount(2, $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('ID', $errors);
        
        // Test error messages formatting
        $error_messages = $this->validator->get_error_messages();
        $this->assertNotEmpty($error_messages);
        $this->assertStringContainsString('<br>', $error_messages);
        
        // Clear errors again
        $this->validator->clear_errors();
        $this->assertFalse($this->validator->has_errors());
    }
    
    /**
     * Test malicious content detection
     */
    public function test_malicious_content_detection() {
        // Test various malicious patterns
        $malicious_inputs = array(
            '<script>alert("xss")</script>',
            'javascript:alert(1)',
            'vbscript:msgbox("xss")',
            '<img onload="alert(1)">',
            '<div onclick="alert(1)">',
            '<iframe src="evil.com"></iframe>',
            '<object data="evil.swf"></object>',
            '<embed src="evil.swf">',
            'eval("alert(1)")',
            'expression(alert(1))'
        );
        
        foreach ($malicious_inputs as $input) {
            $this->assertFalse($this->validator->validate_text($input, 'Content'), 
                "Failed to detect malicious content: " . $input);
        }
        
        // Test safe content
        $safe_inputs = array(
            'Normal text content',
            'Email: user@example.com',
            'URL: https://example.com',
            'Numbers: 123.45',
            'Special chars: !@#$%^&*()'
        );
        
        foreach ($safe_inputs as $input) {
            $this->assertTrue($this->validator->validate_text($input, 'Content'), 
                "Incorrectly flagged safe content: " . $input);
        }
    }
}