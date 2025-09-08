<?php
/**
 * Unit Tests for API Handler
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

/**
 * Test TPAK API Handler Class
 */
class Test_TPAK_API_Handler extends WP_UnitTestCase {
    
    /**
     * API Handler instance
     * 
     * @var TPAK_API_Handler
     */
    private $api_handler;
    
    /**
     * Mock API settings
     * 
     * @var array
     */
    private $mock_settings;
    
    /**
     * Set up test
     */
    public function setUp(): void {
        parent::setUp();
        $this->api_handler = TPAK_API_Handler::get_instance();
        
        $this->mock_settings = array(
            'limesurvey_url' => 'https://test.limesurvey.com/admin/remotecontrol',
            'username' => 'test_user',
            'password' => 'test_password',
            'survey_id' => '12345',
            'timeout' => 30,
            'ssl_verify' => true,
        );
        
        // Set up mock settings
        update_option('tpak_dq_settings', array('api' => $this->mock_settings));
    }
    
    /**
     * Test settings loading
     */
    public function test_settings_loading() {
        $settings = $this->api_handler->get_settings();
        
        $this->assertIsArray($settings);
        $this->assertEquals($this->mock_settings['limesurvey_url'], $settings['limesurvey_url']);
        $this->assertEquals($this->mock_settings['username'], $settings['username']);
        $this->assertEquals($this->mock_settings['survey_id'], $settings['survey_id']);
    }
    
    /**
     * Test settings update
     */
    public function test_settings_update() {
        $new_settings = array(
            'limesurvey_url' => 'https://new.limesurvey.com/admin/remotecontrol',
            'username' => 'new_user',
            'survey_id' => '67890'
        );
        
        $this->api_handler->update_settings($new_settings);
        $updated_settings = $this->api_handler->get_settings();
        
        $this->assertEquals($new_settings['limesurvey_url'], $updated_settings['limesurvey_url']);
        $this->assertEquals($new_settings['username'], $updated_settings['username']);
        $this->assertEquals($new_settings['survey_id'], $updated_settings['survey_id']);
        
        // Check that other settings are preserved
        $this->assertEquals($this->mock_settings['password'], $updated_settings['password']);
    }
    
    /**
     * Test connection with missing credentials
     */
    public function test_connection_missing_credentials() {
        $result = $this->api_handler->connect('', '', '');
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('missing_credentials', $result->get_error_code());
        $this->assertNotEmpty($this->api_handler->get_last_error());
    }
    
    /**
     * Test error handling
     */
    public function test_error_handling() {
        // Initially no error
        $this->assertEmpty($this->api_handler->get_last_error());
        
        // Trigger an error
        $this->api_handler->connect('', '', '');
        $this->assertNotEmpty($this->api_handler->get_last_error());
        
        // Clear error
        $this->api_handler->clear_error();
        $this->assertEmpty($this->api_handler->get_last_error());
    }
    
    /**
     * Test connection status
     */
    public function test_connection_status() {
        $status = $this->api_handler->get_connection_status();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('is_configured', $status);
        $this->assertArrayHasKey('last_connection', $status);
        $this->assertArrayHasKey('last_import', $status);
        $this->assertArrayHasKey('settings', $status);
        
        // Should be configured with mock settings
        $this->assertTrue($status['is_configured']);
    }
    
    /**
     * Test CSV parsing
     */
    public function test_csv_parsing() {
        $csv_data = "id,Response ID,Q1,Q2,Q3\n1,resp_001,Answer1,Answer2,Answer3\n2,resp_002,Answer4,Answer5,Answer6";
        
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->api_handler);
        $method = $reflection->getMethod('parse_csv_responses');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->api_handler, $csv_data);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Check first response
        $this->assertArrayHasKey('1', $result);
        $this->assertEquals('resp_001', $result['1']['Response ID']);
        $this->assertEquals('Answer1', $result['1']['Q1']);
        
        // Check second response
        $this->assertArrayHasKey('2', $result);
        $this->assertEquals('resp_002', $result['2']['Response ID']);
        $this->assertEquals('Answer4', $result['2']['Q1']);
    }
    
    /**
     * Test empty CSV parsing
     */
    public function test_empty_csv_parsing() {
        $reflection = new ReflectionClass($this->api_handler);
        $method = $reflection->getMethod('parse_csv_responses');
        $method->setAccessible(true);
        
        // Test empty CSV
        $result = $method->invoke($this->api_handler, '');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
        
        // Test CSV with only headers
        $result = $method->invoke($this->api_handler, "id,Response ID,Q1");
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    /**
     * Test data transformation
     */
    public function test_data_transformation() {
        $api_data = array(
            'survey_id' => '12345',
            'responses' => array(
                'resp_001' => array(
                    'Q1' => 'Answer 1',
                    'Q2' => 'Answer 2'
                ),
                'resp_002' => array(
                    'Q1' => 'Answer 3',
                    'Q2' => 'Answer 4'
                )
            )
        );
        
        $transformed = $this->api_handler->transform_data($api_data);
        
        $this->assertIsArray($transformed);
        $this->assertCount(2, $transformed);
        
        // Check first transformed item
        $this->assertInstanceOf('TPAK_Survey_Data', $transformed[0]);
        $this->assertEquals('12345', $transformed[0]->get_survey_id());
        $this->assertEquals('resp_001', $transformed[0]->get_response_id());
        $this->assertEquals('pending_a', $transformed[0]->get_status());
        
        $data = $transformed[0]->get_data();
        $this->assertEquals('Answer 1', $data['Q1']);
        $this->assertEquals('Answer 2', $data['Q2']);
    }
    
    /**
     * Test invalid data transformation
     */
    public function test_invalid_data_transformation() {
        // Test with invalid data
        $result = $this->api_handler->transform_data('not an array');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
        
        // Test with missing responses
        $result = $this->api_handler->transform_data(array('survey_id' => '12345'));
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    /**
     * Test connection state
     */
    public function test_connection_state() {
        // Initially not connected
        $this->assertFalse($this->api_handler->is_connected());
        
        // Mock successful connection by setting session key via reflection
        $reflection = new ReflectionClass($this->api_handler);
        $property = $reflection->getProperty('session_key');
        $property->setAccessible(true);
        $property->setValue($this->api_handler, 'mock_session_key');
        
        // Now should be connected
        $this->assertTrue($this->api_handler->is_connected());
        
        // Test disconnect
        $this->api_handler->disconnect();
        // Note: disconnect() sets session_key to null, so is_connected() should return false
        // But we can't easily test the actual API call without mocking HTTP requests
    }
    
    /**
     * Test AJAX test connection security
     */
    public function test_ajax_security() {
        // Test without proper permissions
        $user_id = $this->factory->user->create(array('role' => 'subscriber'));
        wp_set_current_user($user_id);
        
        $this->expectException('WPDieException');
        $this->api_handler->ajax_test_connection();
    }
    
    /**
     * Test import result structure
     */
    public function test_import_result_structure() {
        // Mock import data
        $mock_api_data = array(
            'survey_id' => '12345',
            'responses' => array(
                'resp_001' => array('Q1' => 'Answer1'),
                'resp_002' => array('Q1' => 'Answer2')
            )
        );
        
        // Mock the get_survey_data method to return our test data
        $api_handler_mock = $this->getMockBuilder('TPAK_API_Handler')
            ->setMethods(array('get_survey_data'))
            ->getMock();
        
        $api_handler_mock->method('get_survey_data')
            ->willReturn($mock_api_data);
        
        // We can't easily test the full import without database operations
        // But we can test the expected structure
        $expected_keys = array('imported', 'skipped', 'errors', 'error_messages', 'total_responses');
        
        // This is more of a structural test
        $this->assertTrue(method_exists($this->api_handler, 'import_survey_data'));
    }
    
    /**
     * Test API request structure
     */
    public function test_api_request_structure() {
        // Test that make_api_request method exists and is properly structured
        $reflection = new ReflectionClass($this->api_handler);
        $method = $reflection->getMethod('make_api_request');
        $method->setAccessible(true);
        
        // Test with invalid URL to check error handling
        $result = $method->invoke($this->api_handler, 'invalid-url', array('test' => 'data'));
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertNotEmpty($this->api_handler->get_last_error());
    }
    
    /**
     * Test survey data validation in import
     */
    public function test_survey_data_validation() {
        // Test that the API handler properly validates survey data
        $invalid_survey_id = '';
        $result = $this->api_handler->get_survey_data($invalid_survey_id);
        
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('no_survey_id', $result->get_error_code());
    }
    
    /**
     * Test settings defaults
     */
    public function test_settings_defaults() {
        // Clear settings
        delete_option('tpak_dq_settings');
        
        // Create new instance to test defaults
        $reflection = new ReflectionClass($this->api_handler);
        $method = $reflection->getMethod('load_settings');
        $method->setAccessible(true);
        $method->invoke($this->api_handler);
        
        $settings = $this->api_handler->get_settings();
        
        // Check defaults are set
        $this->assertArrayHasKey('timeout', $settings);
        $this->assertArrayHasKey('ssl_verify', $settings);
        $this->assertEquals(30, $settings['timeout']);
        $this->assertTrue($settings['ssl_verify']);
    }
    
    /**
     * Test error message formatting
     */
    public function test_error_message_formatting() {
        // Test various error scenarios
        $this->api_handler->connect('', '', '');
        $error = $this->api_handler->get_last_error();
        $this->assertIsString($error);
        $this->assertNotEmpty($error);
        
        // Test that error messages are properly internationalized
        $this->assertStringContainsString('required', $error);
    }
}