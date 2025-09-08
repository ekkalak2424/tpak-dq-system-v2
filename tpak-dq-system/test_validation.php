<?php
/**
 * Validation Test Utility
 * 
 * Simple script to test validation functions during development
 * 
 * @package TPAK_DQ_System
 * @since 1.0.0
 */

// Only run if WordPress is loaded and user is admin
if (!defined('ABSPATH') || !current_user_can('manage_options')) {
    exit('Access denied');
}

// Load the validator
$validator = TPAK_Validator::get_instance();

echo "<h1>TPAK DQ System - Validation Tests</h1>\n";
echo "<style>
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
    .pass { color: green; }
    .fail { color: red; }
    .test-input { background: #f9f9f9; padding: 10px; margin: 5px 0; }
    .test-result { margin: 5px 0; }
</style>\n";

/**
 * Test a validation function
 */
function test_validation($test_name, $function, $inputs, $expected_results) {
    global $validator;
    
    echo "<div class='test-section'>\n";
    echo "<h3>Testing: $test_name</h3>\n";
    
    $passed = 0;
    $total = count($inputs);
    
    foreach ($inputs as $i => $input) {
        $validator->clear_errors();
        
        if (is_array($input)) {
            $result = call_user_func_array(array($validator, $function), $input);
            $input_display = json_encode($input);
        } else {
            $result = $validator->$function($input);
            $input_display = htmlspecialchars($input);
        }
        
        $expected = $expected_results[$i];
        $status = ($result === $expected) ? 'pass' : 'fail';
        $status_text = $status === 'pass' ? '✓' : '✗';
        
        if ($status === 'pass') {
            $passed++;
        }
        
        echo "<div class='test-input'>Input: $input_display</div>\n";
        echo "<div class='test-result $status'>$status_text Expected: " . 
             ($expected ? 'true' : 'false') . ", Got: " . ($result ? 'true' : 'false') . "</div>\n";
        
        if ($validator->has_errors()) {
            echo "<div style='color: #666; font-size: 12px;'>Errors: " . 
                 $validator->get_error_messages() . "</div>\n";
        }
        
        echo "<br>\n";
    }
    
    echo "<strong>Results: $passed/$total passed</strong>\n";
    echo "</div>\n";
}

// Test Email Validation
test_validation(
    'Email Validation',
    'validate_email',
    array(
        'test@example.com',
        'user.name+tag@domain.co.uk',
        '',
        'invalid-email',
        'test@',
        '@example.com'
    ),
    array(true, true, false, false, false, false)
);

// Test URL Validation
test_validation(
    'URL Validation',
    'validate_url',
    array(
        'https://example.com',
        'http://subdomain.example.com/path',
        '',
        'not-a-url',
        'ftp://example.com'
    ),
    array(true, true, false, false, false)
);

// Test LimeSurvey URL Validation
test_validation(
    'LimeSurvey URL Validation',
    'validate_limesurvey_url',
    array(
        'https://survey.example.com/admin/remotecontrol',
        'http://localhost/limesurvey/index.php/admin/remotecontrol',
        'https://example.com/index.php?r=admin/remotecontrol',
        'https://example.com',
        'https://example.com/wrong/endpoint'
    ),
    array(true, true, true, false, false)
);

// Test Numeric ID Validation
test_validation(
    'Numeric ID Validation',
    'validate_numeric_id',
    array(
        array('123', 'ID'),
        array(456, 'ID'),
        array('', 'ID'),
        array('abc', 'ID'),
        array('-1', 'ID'),
        array('0', 'ID', true) // Allow zero
    ),
    array(true, true, false, false, false, true)
);

// Test Percentage Validation
test_validation(
    'Percentage Validation',
    'validate_percentage',
    array(
        array('50', 'Percentage'),
        array(75.5, 'Percentage'),
        array('1', 'Percentage'),
        array('100', 'Percentage'),
        array('', 'Percentage'),
        array('0', 'Percentage'),
        array('101', 'Percentage'),
        array('-5', 'Percentage'),
        array('abc', 'Percentage')
    ),
    array(true, true, true, true, false, false, false, false, false)
);

// Test Text Validation
test_validation(
    'Text Validation',
    'validate_text',
    array(
        array('Valid text', 'Text'),
        array('', 'Optional', 0, 255, false),
        array('Hi', 'Name', 5),
        array(str_repeat('a', 300), 'Description', 0, 255),
        array('', 'Required Field'),
        array('<script>alert("xss")</script>', 'Content'),
        array('javascript:alert(1)', 'URL')
    ),
    array(true, true, false, false, false, false, false)
);

// Test JSON Validation
test_validation(
    'JSON Validation',
    'validate_json',
    array(
        array('{"key": "value"}', 'JSON'),
        array('[]', 'JSON'),
        array('null', 'JSON'),
        array('', 'Optional', false),
        array('{"invalid": json}', 'JSON'),
        array('{key: "value"}', 'JSON'),
        array('', 'Required')
    ),
    array(true, true, true, true, false, false, false)
);

// Test Date Validation
test_validation(
    'Date Validation',
    'validate_date',
    array(
        array('2023-12-25 15:30:00', 'Y-m-d H:i:s', 'Date'),
        array('2023-12-25', 'Y-m-d', 'Date'),
        array('', 'Y-m-d H:i:s', 'Optional', false),
        array('2023-13-25 15:30:00', 'Y-m-d H:i:s', 'Date'),
        array('not-a-date', 'Y-m-d H:i:s', 'Date'),
        array('2023/12/25', 'Y-m-d', 'Date'),
        array('', 'Y-m-d H:i:s', 'Required')
    ),
    array(true, true, true, false, false, false, false)
);

// Test API Settings Validation
echo "<div class='test-section'>\n";
echo "<h3>Testing: API Settings Validation</h3>\n";

$valid_settings = array(
    'limesurvey_url' => 'https://survey.example.com/admin/remotecontrol',
    'username' => 'admin',
    'password' => 'password123',
    'survey_id' => '12345'
);

$validator->clear_errors();
$result = $validator->validate_api_settings($valid_settings);
echo "<div class='test-input'>Valid Settings: " . json_encode($valid_settings) . "</div>\n";
echo "<div class='test-result " . ($result ? 'pass' : 'fail') . "'>" . 
     ($result ? '✓' : '✗') . " Result: " . ($result ? 'Valid' : 'Invalid') . "</div>\n";

$invalid_settings = array(
    'username' => 'admin',
    'password' => 'password123',
    'survey_id' => 'abc'
);

$validator->clear_errors();
$result = $validator->validate_api_settings($invalid_settings);
echo "<div class='test-input'>Invalid Settings: " . json_encode($invalid_settings) . "</div>\n";
echo "<div class='test-result " . ($result ? 'fail' : 'pass') . "'>" . 
     ($result ? '✗' : '✓') . " Result: " . ($result ? 'Valid' : 'Invalid') . "</div>\n";
if ($validator->has_errors()) {
    echo "<div style='color: #666; font-size: 12px;'>Errors: " . 
         $validator->get_error_messages() . "</div>\n";
}

echo "</div>\n";

// Test Survey Data Validation
echo "<div class='test-section'>\n";
echo "<h3>Testing: Survey Data Validation</h3>\n";

$valid_data = array(
    'survey_id' => '12345',
    'response_id' => 'resp_001',
    'responses' => array(
        'Q1' => 'Answer 1',
        'Q2' => array('Option A', 'Option B'),
        'Q3' => '42'
    )
);

$validator->clear_errors();
$result = $validator->validate_survey_data($valid_data);
echo "<div class='test-input'>Valid Data: " . json_encode($valid_data) . "</div>\n";
echo "<div class='test-result " . ($result ? 'pass' : 'fail') . "'>" . 
     ($result ? '✓' : '✗') . " Result: " . ($result ? 'Valid' : 'Invalid') . "</div>\n";

$invalid_data = array(
    'responses' => array('Q1' => 'Answer')
);

$validator->clear_errors();
$result = $validator->validate_survey_data($invalid_data);
echo "<div class='test-input'>Invalid Data: " . json_encode($invalid_data) . "</div>\n";
echo "<div class='test-result " . ($result ? 'fail' : 'pass') . "'>" . 
     ($result ? '✗' : '✓') . " Result: " . ($result ? 'Valid' : 'Invalid') . "</div>\n";
if ($validator->has_errors()) {
    echo "<div style='color: #666; font-size: 12px;'>Errors: " . 
         $validator->get_error_messages() . "</div>\n";
}

echo "</div>\n";

// Test Input Sanitization
echo "<div class='test-section'>\n";
echo "<h3>Testing: Input Sanitization</h3>\n";

$sanitization_tests = array(
    array('Clean text', 'text', 'Clean text'),
    array('<script>Cleaned</script>', 'text', 'Cleaned'),
    array('test@example.com', 'email', 'test@example.com'),
    array('https://example.com', 'url', 'https://example.com'),
    array('123', 'int', 123),
    array('-123', 'int', 0),
    array('123.45', 'float', 123.45),
    array('1', 'bool', true),
    array('0', 'bool', false),
    array(array('key' => 'value'), 'json', '{"key":"value"}'),
    array('invalid json', 'json', '{}')
);

foreach ($sanitization_tests as $test) {
    list($input, $type, $expected) = $test;
    $result = $validator->sanitize_input($input, $type);
    $status = ($result === $expected) ? 'pass' : 'fail';
    $status_text = $status === 'pass' ? '✓' : '✗';
    
    echo "<div class='test-input'>Input: " . json_encode($input) . " (Type: $type)</div>\n";
    echo "<div class='test-result $status'>$status_text Expected: " . 
         json_encode($expected) . ", Got: " . json_encode($result) . "</div>\n";
    echo "<br>\n";
}

echo "</div>\n";

echo "<h2>Validation Tests Complete</h2>\n";
echo "<p>Review the results above to ensure all validation functions are working correctly.</p>\n";