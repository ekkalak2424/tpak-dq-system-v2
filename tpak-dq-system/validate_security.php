<?php
/**
 * Security Validation Script
 * 
 * Tests security features including nonce verification,
 * input sanitization, and access control.
 */

// Include WordPress
require_once '../../../wp-config.php';

// Include plugin files
require_once 'includes/class-autoloader.php';
TPAK_Autoloader::register();

echo "<h1>TPAK Security Validation</h1>\n";

// Test 1: Nonce Creation and Verification
echo "<h2>Test 1: Nonce Verification</h2>\n";
$nonce = TPAK_Security::create_nonce(TPAK_Security::NONCE_SETTINGS);
echo "Created nonce: " . esc_html($nonce) . "<br>\n";

$valid = TPAK_Security::verify_nonce($nonce, TPAK_Security::NONCE_SETTINGS);
echo "Nonce verification: " . ($valid ? "✓ PASS" : "✗ FAIL") . "<br>\n";

$invalid = TPAK_Security::verify_nonce('invalid_nonce', TPAK_Security::NONCE_SETTINGS);
echo "Invalid nonce rejection: " . (!$invalid ? "✓ PASS" : "✗ FAIL") . "<br>\n";

// Test 2: Input Sanitization
echo "<h2>Test 2: Input Sanitization</h2>\n";

$xss_input = '<script>alert("xss")</script>Hello World';
$sanitized = TPAK_Security::sanitize_text($xss_input);
echo "XSS input: " . esc_html($xss_input) . "<br>\n";
echo "Sanitized: " . esc_html($sanitized) . "<br>\n";
echo "XSS prevention: " . (strpos($sanitized, '<script>') === false ? "✓ PASS" : "✗ FAIL") . "<br>\n";

// Test URL sanitization
$malicious_url = 'javascript:alert("xss")';
$clean_url = TPAK_Security::sanitize_url($malicious_url);
echo "Malicious URL blocked: " . (empty($clean_url) ? "✓ PASS" : "✗ FAIL") . "<br>\n";

// Test 3: Output Escaping
echo "<h2>Test 3: Output Escaping</h2>\n";

$dangerous_output = '<script>alert("xss")</script>Test & "quotes"';
$escaped_html = TPAK_Security::escape_html($dangerous_output);
$escaped_attr = TPAK_Security::escape_attr($dangerous_output);

echo "Original: " . esc_html($dangerous_output) . "<br>\n";
echo "HTML escaped: " . $escaped_html . "<br>\n";
echo "Attribute escaped: " . esc_html($escaped_attr) . "<br>\n";
echo "HTML escaping works: " . (strpos($escaped_html, '&lt;script&gt;') !== false ? "✓ PASS" : "✗ FAIL") . "<br>\n";

// Test 4: JSON Sanitization
echo "<h2>Test 4: JSON Sanitization</h2>\n";

$valid_json = '{"key": "value", "number": 42}';
$invalid_json = '{"key": "value"'; // Missing closing brace

$sanitized_valid = TPAK_Security::sanitize_json($valid_json);
$sanitized_invalid = TPAK_Security::sanitize_json($invalid_json);

echo "Valid JSON sanitization: " . ($sanitized_valid !== false ? "✓ PASS" : "✗ FAIL") . "<br>\n";
echo "Invalid JSON rejection: " . ($sanitized_invalid === false ? "✓ PASS" : "✗ FAIL") . "<br>\n";

// Test 5: Integer Sanitization
echo "<h2>Test 5: Integer Sanitization</h2>\n";

$int_tests = array(
    '42' => 42,
    'not_a_number' => 0,
    '5' => 10, // With min constraint of 10
    '25' => 20  // With max constraint of 20
);

foreach ($int_tests as $input => $expected) {
    if ($input === '5') {
        $result = TPAK_Security::sanitize_int($input, 10, 20);
    } elseif ($input === '25') {
        $result = TPAK_Security::sanitize_int($input, 10, 20);
    } else {
        $result = TPAK_Security::sanitize_int($input);
    }
    
    echo "Input: '{$input}' → {$result} (expected: {$expected}) " . 
         ($result === $expected ? "✓ PASS" : "✗ FAIL") . "<br>\n";
}

// Test 6: Capability Checking (requires user context)
echo "<h2>Test 6: Capability Checking</h2>\n";

if (is_user_logged_in()) {
    $can_access = TPAK_Security::can_access_admin();
    $can_manage = TPAK_Security::can_manage_settings();
    
    echo "Can access admin: " . ($can_access ? "Yes" : "No") . "<br>\n";
    echo "Can manage settings: " . ($can_manage ? "Yes" : "No") . "<br>\n";
} else {
    echo "Not logged in - capability tests skipped<br>\n";
}

// Test 7: Rate Limiting
echo "<h2>Test 7: Rate Limiting</h2>\n";

if (is_user_logged_in()) {
    $action = 'test_action';
    $limit = 3;
    
    $results = array();
    for ($i = 0; $i < 5; $i++) {
        $results[] = TPAK_Security::check_rate_limit($action, $limit, 300);
    }
    
    $passed_count = array_sum($results);
    echo "Rate limit test (limit: {$limit}): {$passed_count}/5 requests allowed<br>\n";
    echo "Rate limiting works: " . ($passed_count === $limit ? "✓ PASS" : "✗ FAIL") . "<br>\n";
} else {
    echo "Not logged in - rate limiting tests skipped<br>\n";
}

echo "<h2>Security Validation Complete</h2>\n";
echo "<p>All security features have been tested. Check the results above for any failures.</p>\n";
?>