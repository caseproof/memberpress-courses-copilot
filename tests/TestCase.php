<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base Test Case for MemberPress Courses Copilot
 * 
 * Provides common functionality for tests including:
 * - WordPress database mock setup
 * - User permission mocking
 * - Nonce verification helpers
 * 
 * @package MemberPressCoursesCopilot\Tests
 */
abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Mock wpdb instance
     */
    protected static $wpdb;
    
    /**
     * Test database tables created
     */
    protected static $tablesCreated = false;
    
    /**
     * Current user capabilities
     */
    protected array $currentUserCaps = [];
    
    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize wpdb mock if not exists
        if (!self::$wpdb) {
            $this->initializeWpdb();
        }
        
        // Reset user capabilities
        $this->currentUserCaps = ['read' => true];
        
        // Clear any previous test data
        $this->clearTestData();
    }
    
    /**
     * Initialize wpdb mock
     */
    protected function initializeWpdb(): void
    {
        global $wpdb;
        
        // Create a real SQLite database for testing
        if (!class_exists('SQLite3')) {
            $this->markTestSkipped('SQLite3 not available for database tests');
        }
        
        // Create wpdb mock class if not exists
        if (!class_exists('wpdb')) {
            require_once __DIR__ . '/Mocks/wpdb.php';
        }
        
        $wpdb = new \wpdb();
        self::$wpdb = $wpdb;
    }
    
    /**
     * Clear test data
     */
    protected function clearTestData(): void
    {
        // Clear transients
        global $test_transients;
        $test_transients = [];
    }
    
    /**
     * Set current user capabilities
     */
    protected function setCurrentUserCaps(array $caps): void
    {
        $this->currentUserCaps = $caps;
        // Also update global for current_user_can function
        global $test_user_caps;
        $test_user_caps = $caps;
    }
    
    /**
     * Add current user capability
     */
    protected function addCap(string $cap): void
    {
        $this->currentUserCaps[$cap] = true;
    }
    
    /**
     * Mock current_user_can function
     */
    protected function mockCurrentUserCan(string $cap): bool
    {
        return isset($this->currentUserCaps[$cap]) && $this->currentUserCaps[$cap];
    }
    
    /**
     * Create a valid nonce for testing
     */
    protected function createTestNonce(string $action): string
    {
        return wp_create_nonce($action);
    }
    
    /**
     * Verify a test nonce
     */
    protected function verifyTestNonce(string $nonce, string $action): bool
    {
        // Simple verification for tests
        return $nonce === wp_create_nonce($action);
    }
    
    /**
     * Set $_POST data for testing
     */
    protected function setPostData(array $data): void
    {
        $_POST = $data;
        $_REQUEST = array_merge($_GET, $data); // WordPress often checks $_REQUEST
    }
    
    /**
     * Set $_GET data for testing  
     */
    protected function setGetData(array $data): void
    {
        $_GET = $data;
    }
    
    /**
     * Clear request data
     */
    protected function clearRequestData(): void
    {
        $_POST = [];
        $_GET = [];
        $_REQUEST = [];
    }
    
    /**
     * Capture output from a function that uses wp_send_json
     */
    protected function captureJsonOutput(callable $callback): array
    {
        ob_start();
        
        try {
            $callback();
        } catch (\Exception $e) {
            $output = ob_get_contents();
            ob_end_clean();
            
            if ($e->getMessage() === 'wp_send_json_exit') {
                // Expected exit from wp_send_json
                
                // If output is empty, return empty response
                if (empty($output)) {
                    return ['error' => 'No output captured', 'raw' => ''];
                }
                
                // Find the first complete JSON object
                $jsonStart = strpos($output, '{');
                if ($jsonStart !== false) {
                    $braceCount = 0;
                    $inString = false;
                    $escape = false;
                    
                    for ($i = $jsonStart; $i < strlen($output); $i++) {
                        $char = $output[$i];
                        
                        if (!$escape) {
                            if ($char === '"' && !$inString) {
                                $inString = true;
                            } elseif ($char === '"' && $inString) {
                                $inString = false;
                            } elseif (!$inString) {
                                if ($char === '{') {
                                    $braceCount++;
                                } elseif ($char === '}') {
                                    $braceCount--;
                                    if ($braceCount === 0) {
                                        // Found complete JSON object
                                        $jsonPart = substr($output, $jsonStart, $i - $jsonStart + 1);
                                        $decoded = json_decode($jsonPart, true);
                                        if (json_last_error() === JSON_ERROR_NONE) {
                                            return $decoded;
                                        }
                                        break;
                                    }
                                }
                            }
                            
                            if ($char === '\\') {
                                $escape = true;
                            }
                        } else {
                            $escape = false;
                        }
                    }
                }
                
                // Try direct decode if no pattern match
                $decoded = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
                
                // If json decode fails, return raw output info
                return ['error' => 'Failed to decode JSON', 'raw' => $output, 'json_error' => json_last_error_msg()];
            }
            
            throw $e;
        }
        
        $output = ob_get_clean();
        $decoded = json_decode($output, true);
        return $decoded ?: ['error' => 'No JSON output captured', 'raw' => $output];
    }
    
    /**
     * Assert that an array has expected structure
     */
    protected function assertArrayStructure(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actual);
            
            if (is_array($value)) {
                $this->assertIsArray($actual[$key]);
                if (!empty($value)) {
                    $this->assertArrayStructure($value, $actual[$key]);
                }
            }
        }
    }
    
    /**
     * Polyfill for assertStringContains (for older PHPUnit versions)
     */
    public function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (method_exists($this, 'assertStringContainsString')) {
            $this->assertStringContainsString($needle, $haystack, $message);
        } else {
            $this->assertContains($needle, $haystack, $message);
        }
    }
    
    /**
     * Polyfill for assertStringNotContains (for older PHPUnit versions)
     */
    public function assertStringNotContains(string $needle, string $haystack, string $message = ''): void
    {
        if (method_exists($this, 'assertStringNotContainsString')) {
            $this->assertStringNotContainsString($needle, $haystack, $message);
        } else {
            $this->assertNotContains($needle, $haystack, $message);
        }
    }
    
    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->clearRequestData();
        $this->clearTestData();
    }
}