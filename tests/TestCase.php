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
            ob_end_clean();
            
            if ($e->getMessage() === 'wp_send_json_exit') {
                // Expected exit from wp_send_json
                $output = ob_get_contents();
                ob_end_clean();
                
                $decoded = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
            
            throw $e;
        }
        
        $output = ob_get_clean();
        return json_decode($output, true) ?: [];
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
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->clearRequestData();
        $this->clearTestData();
    }
}