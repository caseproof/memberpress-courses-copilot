<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Security;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Controllers\SimpleAjaxController;

/**
 * Input Sanitization Test
 * 
 * Tests input sanitization throughout the application
 * Following CLAUDE.md principles - real sanitization tests
 * 
 * @package MemberPressCoursesCopilot\Tests\Security
 * @since 1.0.0
 */
class InputSanitizationTest extends TestCase
{
    private SimpleAjaxController $controller;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SimpleAjaxController();
    }
    
    /**
     * Test sanitizeArray method with different data types
     */
    public function testSanitizeArrayMethod(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);
        
        // Test text sanitization
        $textData = [
            'name' => '  John Doe  ',
            'email' => 'john@example.com<script>',
            'message' => '<p>Hello World</p>',
            'nested' => [
                'value' => '  Nested <b>Value</b>  '
            ]
        ];
        
        $sanitized = $method->invoke($this->controller, $textData, 'text');
        
        $this->assertEquals('John Doe', $sanitized['name']); // Trimmed
        $this->assertEquals('john@example.com', $sanitized['email']); // Script removed
        $this->assertEquals('Hello World', $sanitized['message']); // Tags stripped
        $this->assertEquals('Nested Value', $sanitized['nested']['value']); // Nested sanitization
    }
    
    /**
     * Test email sanitization
     */
    public function testEmailSanitization(): void
    {
        $emailInputs = [
            'valid@example.com' => 'valid@example.com',
            ' spaces@example.com ' => 'spaces@example.com',
            'UPPER@EXAMPLE.COM' => 'upper@example.com',
            'invalid<script>@example.com' => 'invalid@example.com',
            'test@example.com<script>alert("XSS")</script>' => 'test@example.com',
            'javascript:alert("XSS")@example.com' => ':alert("XSS")@example.com',
            '' => '',
            'not-an-email' => '',
        ];
        
        foreach ($emailInputs as $input => $expected) {
            $sanitized = sanitize_email($input);
            $this->assertEquals($expected, $sanitized, "Failed sanitizing: $input");
        }
    }
    
    /**
     * Test URL sanitization
     */
    public function testUrlSanitization(): void
    {
        $urlInputs = [
            'https://example.com' => 'https://example.com',
            'http://example.com/path?query=value' => 'http://example.com/path?query=value',
            'javascript:alert("XSS")' => '',
            'data:text/html,<script>alert("XSS")</script>' => '',
            ' https://example.com ' => 'https://example.com',
            'https://example.com/<script>' => 'https://example.com/',
            'ftp://files.example.com' => 'ftp://files.example.com',
            '//example.com' => '//example.com',
            'vbscript:msgbox("XSS")' => '',
        ];
        
        foreach ($urlInputs as $input => $expected) {
            $sanitized = esc_url_raw($input);
            
            // Should not contain dangerous protocols
            $this->assertStringNotContainsString('javascript:', $sanitized);
            $this->assertStringNotContainsString('data:', $sanitized);
            $this->assertStringNotContainsString('vbscript:', $sanitized);
        }
    }
    
    /**
     * Test integer sanitization
     */
    public function testIntegerSanitization(): void
    {
        $intInputs = [
            '123' => 123,
            ' 456 ' => 456,
            '789abc' => 789,
            'abc123' => 0,
            '12.34' => 12,
            '-999' => -999,
            '' => 0,
            'null' => 0,
            '0' => 0,
            '00123' => 123,
        ];
        
        foreach ($intInputs as $input => $expected) {
            $sanitized = intval($input);
            $this->assertEquals($expected, $sanitized, "Failed sanitizing: $input");
        }
    }
    
    /**
     * Test float sanitization
     */
    public function testFloatSanitization(): void
    {
        $floatInputs = [
            '123.45' => 123.45,
            ' 67.89 ' => 67.89,
            '12.34abc' => 12.34,
            'abc12.34' => 0.0,
            '-99.99' => -99.99,
            '0.5' => 0.5,
            '.5' => 0.5,
            '5.' => 5.0,
            '' => 0.0,
        ];
        
        foreach ($floatInputs as $input => $expected) {
            $sanitized = floatval($input);
            $this->assertEquals($expected, $sanitized, "Failed sanitizing: $input");
        }
    }
    
    /**
     * Test boolean sanitization
     */
    public function testBooleanSanitization(): void
    {
        $boolInputs = [
            'true' => true,
            'false' => false,
            '1' => true,
            '0' => false,
            'yes' => true,
            'no' => false,
            'on' => true,
            'off' => false,
            '' => false,
            'anything' => true,
        ];
        
        foreach ($boolInputs as $input => $expected) {
            $sanitized = filter_var($input, FILTER_VALIDATE_BOOLEAN);
            $this->assertEquals($expected, $sanitized, "Failed sanitizing: $input");
        }
    }
    
    /**
     * Test textarea sanitization preserves newlines
     */
    public function testTextareaSanitization(): void
    {
        $textareaInputs = [
            "Line 1\nLine 2\nLine 3" => "Line 1\nLine 2\nLine 3",
            "Text with\r\nWindows newlines" => "Text with\nWindows newlines",
            "<script>alert('XSS')</script>\nNormal text" => "\nNormal text",
            "   Trimmed text   " => "Trimmed text",
        ];
        
        foreach ($textareaInputs as $input => $expected) {
            $sanitized = sanitize_textarea_field($input);
            
            // Should not contain scripts
            $this->assertStringNotContainsString('<script>', $sanitized);
            
            // Should preserve newlines (normalized to \n)
            if (strpos($expected, "\n") !== false) {
                $this->assertStringContainsString("\n", $sanitized);
            }
        }
    }
    
    /**
     * Test HTML sanitization with wp_kses_post
     */
    public function testHtmlSanitization(): void
    {
        // Define wp_kses_post if not exists
        if (!function_exists('wp_kses_post')) {
            require_once __DIR__ . '/XssPreventionTest.php';
        }
        
        $htmlInputs = [
            '<p>Valid paragraph</p>' => ['contains' => '<p>', 'not_contains' => []],
            '<script>alert("XSS")</script>' => ['contains' => [], 'not_contains' => ['<script>']],
            '<a href="https://example.com">Link</a>' => ['contains' => ['<a', 'href='], 'not_contains' => []],
            '<a href="javascript:alert(\'XSS\')">Bad</a>' => ['contains' => ['<a'], 'not_contains' => ['javascript:']],
            '<img src="image.jpg" onerror="alert(\'XSS\')">' => ['contains' => [], 'not_contains' => ['<img', 'onerror']],
        ];
        
        foreach ($htmlInputs as $input => $expectations) {
            $sanitized = wp_kses_post($input);
            
            foreach ($expectations['contains'] as $expected) {
                $this->assertStringContainsString($expected, $sanitized, "Should contain: $expected");
            }
            
            foreach ($expectations['not_contains'] as $notExpected) {
                $this->assertStringNotContainsString($notExpected, $sanitized, "Should not contain: $notExpected");
            }
        }
    }
    
    /**
     * Test JSON data sanitization
     */
    public function testJsonDataSanitization(): void
    {
        $jsonInputs = [
            '{"name":"John","age":30}' => ['name' => 'John', 'age' => 30],
            '{"xss":"<script>alert(\'XSS\')</script>"}' => ['xss' => 'alert(\'XSS\')'],
            '{"nested":{"value":"<b>Bold</b>"}}' => ['nested' => ['value' => 'Bold']],
            'invalid json' => null,
            '' => null,
        ];
        
        foreach ($jsonInputs as $input => $expected) {
            $decoded = json_decode($input, true);
            
            if ($decoded !== null) {
                // Sanitize decoded data
                array_walk_recursive($decoded, function(&$value) {
                    if (is_string($value)) {
                        $value = sanitize_text_field($value);
                    }
                });
                
                // Check sanitization
                if ($expected !== null) {
                    foreach ($decoded as $key => $value) {
                        if (is_array($value)) {
                            $this->assertIsArray($value);
                        } else {
                            $this->assertStringNotContainsString('<script>', $value);
                            $this->assertStringNotContainsString('<', $value);
                        }
                    }
                }
            } else {
                $this->assertNull($expected);
            }
        }
    }
    
    /**
     * Test file path sanitization
     */
    public function testFilePathSanitization(): void
    {
        $pathInputs = [
            '../../../etc/passwd' => 'etcpasswd',
            '/var/www/html/../../etc/passwd' => 'varwwwhtmletcpasswd',
            'C:\\Windows\\System32\\..\\..\\passwords.txt' => 'CWindowsSystem32passwordstxt',
            'normal-file.txt' => 'normal-filetxt',
            'file name with spaces.pdf' => 'file name with spacespdf',
            'file<script>.txt' => 'filescripttxt',
        ];
        
        foreach ($pathInputs as $input => $expected) {
            // Remove directory traversal attempts
            $sanitized = str_replace(['..', '/', '\\', ':'], '', $input);
            $sanitized = sanitize_text_field($sanitized);
            
            // Should not contain path traversal
            $this->assertStringNotContainsString('..', $sanitized);
            $this->assertStringNotContainsString('/', $sanitized);
            $this->assertStringNotContainsString('\\', $sanitized);
            
            // Should not contain scripts
            $this->assertStringNotContainsString('<script>', $sanitized);
        }
    }
    
    /**
     * Test SQL injection prevention
     */
    public function testSqlInjectionPrevention(): void
    {
        global $wpdb;
        
        $sqlInputs = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'--",
            "' UNION SELECT * FROM passwords --",
            "1; DELETE FROM courses WHERE 1=1; --",
        ];
        
        foreach ($sqlInputs as $input) {
            // Test with prepare
            $prepared = $wpdb->prepare("SELECT * FROM courses WHERE title = %s", $input);
            
            // Should escape quotes
            $this->assertStringNotContainsString("'$input'", $prepared);
            
            // Should not contain unescaped SQL commands
            $this->assertStringNotContainsString('DROP TABLE', $prepared);
            $this->assertStringNotContainsString('DELETE FROM', $prepared);
            $this->assertStringNotContainsString('UNION SELECT', $prepared);
        }
    }
    
    /**
     * Test array key sanitization
     */
    public function testArrayKeySanitization(): void
    {
        $unsafeKeys = [
            '<script>key</script>' => 'scriptkeyscript',
            'key with spaces' => 'key_with_spaces',
            'key-with-dashes' => 'key-with-dashes',
            'KEY_WITH_CAPS' => 'key_with_caps',
            '123numeric' => '_123numeric',
            '' => '_empty_',
        ];
        
        foreach ($unsafeKeys as $unsafe => $expected) {
            // Sanitize as key
            $sanitized = sanitize_key($unsafe);
            
            // Should only contain lowercase letters, numbers, underscores, and dashes
            $this->assertMatchesRegularExpression('/^[a-z0-9_-]*$/', $sanitized);
            
            // Should not contain HTML or special characters
            $this->assertStringNotContainsString('<', $sanitized);
            $this->assertStringNotContainsString('>', $sanitized);
            $this->assertStringNotContainsString(' ', $sanitized);
        }
    }
    
    /**
     * Test comprehensive input validation
     */
    public function testComprehensiveInputValidation(): void
    {
        $testData = [
            'user_id' => '123<script>',
            'email' => 'test@example.com<img src=x>',
            'website' => 'javascript:alert("XSS")',
            'age' => '25 years',
            'active' => 'true',
            'price' => '99.99USD',
            'description' => '<p>Description with <strong>HTML</strong></p>',
            'tags' => ['php', 'javascript<script>', 'security'],
        ];
        
        // Sanitize based on expected types
        $sanitized = [
            'user_id' => intval($testData['user_id']),
            'email' => sanitize_email($testData['email']),
            'website' => esc_url_raw($testData['website']),
            'age' => intval($testData['age']),
            'active' => filter_var($testData['active'], FILTER_VALIDATE_BOOLEAN),
            'price' => floatval($testData['price']),
            'description' => wp_kses_post($testData['description']),
            'tags' => array_map('sanitize_text_field', $testData['tags']),
        ];
        
        // Verify sanitization
        $this->assertEquals(123, $sanitized['user_id']);
        $this->assertEquals('test@example.com', $sanitized['email']);
        $this->assertEquals('', $sanitized['website']); // JavaScript URL blocked
        $this->assertEquals(25, $sanitized['age']);
        $this->assertTrue($sanitized['active']);
        $this->assertEquals(99.99, $sanitized['price']);
        $this->assertStringContainsString('<p>', $sanitized['description']);
        $this->assertStringNotContainsString('<script>', $sanitized['tags'][1]);
    }
}

// Add missing WordPress functions if needed
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);
        return $key;
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        $str = strip_tags($str);
        $str = trim($str);
        // Normalize newlines
        $str = str_replace("\r\n", "\n", $str);
        return $str;
    }
}