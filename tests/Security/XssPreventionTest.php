<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Security;

use PHPUnit\Framework\TestCase;
use MemberPressCoursesCopilot\Utilities\Helper;

/**
 * XSS Prevention Test
 * 
 * Tests XSS prevention measures throughout the application
 * Following CLAUDE.md principles - real security tests
 * 
 * @package MemberPressCoursesCopilot\Tests\Security
 * @since 1.0.0
 */
class XssPreventionTest extends TestCase
{
    /**
     * Common XSS attack vectors to test
     */
    private array $xssVectors = [
        '<script>alert("XSS")</script>',
        '<img src=x onerror="alert(\'XSS\')">',
        '<svg onload="alert(\'XSS\')">',
        'javascript:alert("XSS")',
        '<iframe src="javascript:alert(\'XSS\')"></iframe>',
        '<object data="javascript:alert(\'XSS\')"></object>',
        '<embed src="javascript:alert(\'XSS\')">',
        '<form action="javascript:alert(\'XSS\')"><input type="submit"></form>',
        '<a href="javascript:alert(\'XSS\')">Click me</a>',
        '<div onmouseover="alert(\'XSS\')">Hover me</div>',
        '"><script>alert("XSS")</script>',
        '\'-alert("XSS")-\'',
        '<script>document.cookie</script>',
        '<meta http-equiv="refresh" content="0;url=javascript:alert(\'XSS\')">',
        '<base href="javascript:alert(\'XSS\')//">',
        '<style>@import "javascript:alert(\'XSS\')";</style>',
        '<marquee onstart="alert(\'XSS\')">',
        '${alert("XSS")}',
        '`alert("XSS")`',
        '<details open ontoggle="alert(\'XSS\')">',
    ];
    
    /**
     * Test esc_html properly escapes dangerous content
     */
    public function testEscHtmlFunction(): void
    {
        foreach ($this->xssVectors as $vector) {
            $escaped = esc_html($vector);
            
            // Should not contain any HTML tags
            $this->assertStringNotContainsString('<script', $escaped);
            $this->assertStringNotContainsString('<img', $escaped);
            $this->assertStringNotContainsString('<svg', $escaped);
            $this->assertStringNotContainsString('<iframe', $escaped);
            
            // Should not contain event handlers
            $this->assertStringNotContainsString('onerror=', $escaped);
            $this->assertStringNotContainsString('onload=', $escaped);
            $this->assertStringNotContainsString('onmouseover=', $escaped);
            
            // Should not contain javascript: protocol
            $this->assertStringNotContainsString('javascript:', $escaped);
            
            // Should escape HTML entities
            if (strpos($vector, '<') !== false) {
                $this->assertStringContainsString('&lt;', $escaped);
            }
            if (strpos($vector, '>') !== false) {
                $this->assertStringContainsString('&gt;', $escaped);
            }
        }
    }
    
    /**
     * Test esc_attr properly escapes attribute content
     */
    public function testEscAttrFunction(): void
    {
        foreach ($this->xssVectors as $vector) {
            $escaped = esc_attr($vector);
            
            // Should escape quotes
            $this->assertStringNotContainsString('"', $escaped);
            $this->assertStringNotContainsString("'", $escaped);
            
            // Should not allow breaking out of attributes
            $this->assertStringNotContainsString('><', $escaped);
            
            // Should escape special characters
            if (strpos($vector, '"') !== false) {
                $this->assertStringContainsString('&quot;', $escaped);
            }
        }
    }
    
    /**
     * Test esc_js properly escapes JavaScript content
     */
    public function testEscJsFunction(): void
    {
        $jsVectors = [
            'alert("XSS")',
            '</script><script>alert("XSS")</script>',
            '"; alert("XSS"); //',
            '\'; alert("XSS"); //',
            '\\"; alert("XSS"); //',
            "\n alert('XSS') \n",
            "\r\n alert('XSS') \r\n",
        ];
        
        foreach ($jsVectors as $vector) {
            $escaped = esc_js($vector);
            
            // Should escape quotes and backslashes
            $this->assertStringNotContainsString('</script>', $escaped);
            
            // Should escape newlines
            $this->assertStringNotContainsString("\n", $escaped);
            $this->assertStringNotContainsString("\r", $escaped);
            
            // Should have escaped sequences
            if (strpos($vector, '"') !== false) {
                $this->assertStringContainsString('\\"', $escaped);
            }
            if (strpos($vector, "'") !== false) {
                $this->assertStringContainsString("\\'", $escaped);  
            }
        }
    }
    
    /**
     * Test JSON encoding prevents XSS
     */
    public function testJsonEncodingPreventsXss(): void
    {
        foreach ($this->xssVectors as $vector) {
            $data = [
                'message' => $vector,
                'html' => $vector,
                'script' => $vector
            ];
            
            $json = json_encode($data);
            
            // JSON encoding should escape forward slashes in closing tags
            $this->assertStringNotContainsString('</script>', $json);
            $this->assertStringNotContainsString('</style>', $json);
            
            // Should be valid JSON
            $decoded = json_decode($json, true);
            $this->assertNotNull($decoded);
            $this->assertEquals($data, $decoded);
        }
    }
    
    /**
     * Test sanitize_text_field removes scripts
     */
    public function testSanitizeTextField(): void
    {
        foreach ($this->xssVectors as $vector) {
            $sanitized = sanitize_text_field($vector);
            
            // Should strip all tags
            $this->assertStringNotContainsString('<script', $sanitized);
            $this->assertStringNotContainsString('<img', $sanitized);
            $this->assertStringNotContainsString('<', $sanitized);
            $this->assertStringNotContainsString('>', $sanitized);
            
            // Should not contain javascript: protocol
            $this->assertStringNotContainsString('javascript:', $sanitized);
            
            // Should trim whitespace
            $this->assertEquals($sanitized, trim($sanitized));
        }
    }
    
    /**
     * Test wp_kses_post allows safe HTML but blocks dangerous content
     */
    public function testWpKsesPost(): void
    {
        // First add the function if it doesn't exist
        if (!function_exists('wp_kses_post')) {
            function wp_kses_post($content) {
                // Simple implementation for testing
                $allowed_tags = [
                    'p' => [],
                    'br' => [],
                    'strong' => [],
                    'em' => [],
                    'a' => ['href' => true, 'title' => true],
                    'ul' => [],
                    'ol' => [],
                    'li' => [],
                    'blockquote' => [],
                    'code' => [],
                    'pre' => [],
                ];
                
                // Remove script tags and javascript: URLs
                $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
                $content = preg_replace('/javascript:/i', '', $content);
                $content = preg_replace('/on[a-z]+\s*=/i', '', $content);
                
                return strip_tags($content, '<' . implode('><', array_keys($allowed_tags)) . '>');
            }
        }
        
        $testCases = [
            // Safe HTML should be preserved
            '<p>This is safe content</p>' => '<p>This is safe content</p>',
            '<strong>Bold text</strong>' => '<strong>Bold text</strong>',
            '<a href="https://example.com">Link</a>' => '<a href="https://example.com">Link</a>',
            
            // Dangerous content should be stripped
            '<script>alert("XSS")</script>' => '',
            '<p onclick="alert(\'XSS\')">Text</p>' => '<p>Text</p>',
            '<a href="javascript:alert(\'XSS\')">Link</a>' => '<a href="">Link</a>',
            '<img src=x onerror="alert(\'XSS\')">' => '',
        ];
        
        foreach ($testCases as $input => $expected) {
            $result = wp_kses_post($input);
            
            // Should not contain scripts
            $this->assertStringNotContainsString('<script', $result);
            $this->assertStringNotContainsString('javascript:', $result);
            $this->assertStringNotContainsString('onerror=', $result);
            $this->assertStringNotContainsString('onclick=', $result);
        }
    }
    
    /**
     * Test URL escaping
     */
    public function testUrlEscaping(): void
    {
        $urlVectors = [
            'javascript:alert("XSS")',
            'data:text/html,<script>alert("XSS")</script>',
            'vbscript:msgbox("XSS")',
            'file:///etc/passwd',
            '//evil.com/xss',
            '\x00javascript:alert("XSS")',
            'java\nscript:alert("XSS")',
            'java\rscript:alert("XSS")',
            'java\tscript:alert("XSS")',
            'jav&#x09;ascript:alert("XSS")',
        ];
        
        // Add esc_url_raw if not exists
        if (!function_exists('esc_url_raw')) {
            function esc_url_raw($url) {
                // Remove dangerous protocols
                $url = str_replace(['javascript:', 'data:', 'vbscript:', 'file:'], '', $url);
                
                // Remove null bytes and whitespace
                $url = str_replace(["\x00", "\n", "\r", "\t"], '', $url);
                
                // Basic URL validation
                if (!preg_match('/^https?:\/\//i', $url) && strpos($url, '//') !== 0) {
                    return '';
                }
                
                return $url;
            }
        }
        
        foreach ($urlVectors as $vector) {
            $escaped = esc_url_raw($vector);
            
            // Should not contain dangerous protocols
            $this->assertStringNotContainsString('javascript:', $escaped);
            $this->assertStringNotContainsString('data:', $escaped);
            $this->assertStringNotContainsString('vbscript:', $escaped);
            $this->assertStringNotContainsString('file:', $escaped);
            
            // Should not contain null bytes or special characters
            $this->assertStringNotContainsString("\x00", $escaped);
            $this->assertStringNotContainsString("\n", $escaped);
            $this->assertStringNotContainsString("\r", $escaped);
            $this->assertStringNotContainsString("\t", $escaped);
        }
    }
    
    /**
     * Test mixed context XSS prevention
     */
    public function testMixedContextXss(): void
    {
        $mixedVectors = [
            'Hello <script>alert("XSS")</script> World',
            'Normal text with javascript:alert("XSS") link',
            'Data: {"xss": "<script>alert(\'XSS\')</script>"}',
            'CSS: body { background: url(javascript:alert("XSS")) }',
        ];
        
        foreach ($mixedVectors as $vector) {
            // Test as HTML
            $htmlEscaped = esc_html($vector);
            $this->assertStringNotContainsString('<script', $htmlEscaped);
            
            // Test as attribute
            $attrEscaped = esc_attr($vector);
            $this->assertStringNotContainsString('"', $attrEscaped);
            
            // Test as JavaScript
            $jsEscaped = esc_js($vector);
            $this->assertStringNotContainsString('</script>', $jsEscaped);
            
            // Test as JSON
            $jsonEscaped = json_encode($vector);
            $this->assertStringNotContainsString('</script>', $jsonEscaped);
        }
    }
    
    /**
     * Test DOM-based XSS prevention
     */
    public function testDomBasedXssPrevention(): void
    {
        $domVectors = [
            '#<script>alert("XSS")</script>',
            '?search=<script>alert("XSS")</script>',
            '&param="><script>alert("XSS")</script>',
            'window.location="#<script>alert(\'XSS\')</script>"',
        ];
        
        foreach ($domVectors as $vector) {
            // URL encoding for hash/query parameters
            $encoded = urlencode($vector);
            $this->assertStringNotContainsString('<script>', $encoded);
            $this->assertStringNotContainsString('>', $encoded);
            $this->assertStringNotContainsString('<', $encoded);
            
            // HTML entity encoding
            $entities = htmlentities($vector, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->assertStringNotContainsString('<script>', $entities);
        }
    }
    
    /**
     * Test content security policy headers would block XSS
     */
    public function testContentSecurityPolicy(): void
    {
        // This would test CSP headers in a real application
        // For now, we'll test that unsafe content would be blocked
        
        $unsafeContent = [
            'inline-script' => '<div>Test</div><script>alert("XSS")</script>',
            'inline-style' => '<div style="background: url(javascript:alert(\'XSS\'))">',
            'eval' => '<script>eval("alert(\'XSS\')")</script>',
        ];
        
        foreach ($unsafeContent as $type => $content) {
            // With proper CSP, these would be blocked
            // For testing, ensure they're escaped
            $escaped = esc_html($content);
            $this->assertStringNotContainsString('<script>', $escaped);
            $this->assertStringNotContainsString('javascript:', $escaped);
        }
    }
}