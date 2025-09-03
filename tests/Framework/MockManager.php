<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Framework;

/**
 * Mock Manager for handling WordPress function mocks in tests
 * 
 * This class provides a way to register and manage mocks for WordPress functions
 * on a per-test basis, preventing interference between tests.
 * 
 * @package MemberPressCoursesCopilot\Tests\Framework
 */
class MockManager
{
    /**
     * Registered mocks for current test
     */
    private array $mocks = [];
    
    /**
     * Global state backup
     */
    private array $globalBackup = [];
    
    /**
     * Function overrides
     */
    private array $functionOverrides = [];
    
    /**
     * Singleton instance
     */
    private static ?MockManager $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor
     */
    private function __construct()
    {
        // Initialize
    }
    
    /**
     * Register a mock for a WordPress function
     * 
     * @param string $functionName The function name to mock
     * @param callable|mixed $returnValue The value to return or callable to execute
     * @param int $callLimit Maximum number of calls allowed (-1 for unlimited)
     */
    public function registerMock(string $functionName, $returnValue, int $callLimit = -1): void
    {
        $this->mocks[$functionName] = [
            'returnValue' => $returnValue,
            'callCount' => 0,
            'callLimit' => $callLimit,
            'calls' => []
        ];
        
        // Override the function in the global namespace
        $this->overrideFunction($functionName);
    }
    
    /**
     * Register multiple mocks at once
     * 
     * @param array $mocks Array of function => return value pairs
     */
    public function registerMocks(array $mocks): void
    {
        foreach ($mocks as $function => $returnValue) {
            $this->registerMock($function, $returnValue);
        }
    }
    
    /**
     * Call a mocked function
     * 
     * @param string $functionName
     * @param array $args
     * @return mixed
     */
    public function callMock(string $functionName, array $args)
    {
        if (!isset($this->mocks[$functionName])) {
            throw new \RuntimeException("No mock registered for function: $functionName");
        }
        
        $mock = &$this->mocks[$functionName];
        
        // Check call limit
        if ($mock['callLimit'] !== -1 && $mock['callCount'] >= $mock['callLimit']) {
            throw new \RuntimeException("Mock function '$functionName' exceeded call limit of {$mock['callLimit']}");
        }
        
        // Track the call
        $mock['callCount']++;
        $mock['calls'][] = $args;
        
        // Return the mocked value
        if (is_callable($mock['returnValue'])) {
            return call_user_func_array($mock['returnValue'], $args);
        }
        
        return $mock['returnValue'];
    }
    
    /**
     * Get call count for a mocked function
     * 
     * @param string $functionName
     * @return int
     */
    public function getCallCount(string $functionName): int
    {
        return $this->mocks[$functionName]['callCount'] ?? 0;
    }
    
    /**
     * Get call arguments for a mocked function
     * 
     * @param string $functionName
     * @param int $callIndex
     * @return array|null
     */
    public function getCallArgs(string $functionName, int $callIndex = 0): ?array
    {
        if (!isset($this->mocks[$functionName]['calls'][$callIndex])) {
            return null;
        }
        
        return $this->mocks[$functionName]['calls'][$callIndex];
    }
    
    /**
     * Check if a function was called with specific arguments
     * 
     * @param string $functionName
     * @param array $expectedArgs
     * @return bool
     */
    public function wasCalledWith(string $functionName, array $expectedArgs): bool
    {
        if (!isset($this->mocks[$functionName]['calls'])) {
            return false;
        }
        
        foreach ($this->mocks[$functionName]['calls'] as $call) {
            if ($call === $expectedArgs) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Back up global variables
     * 
     * @param array $globalVars Variable names to backup
     */
    public function backupGlobals(array $globalVars): void
    {
        foreach ($globalVars as $var) {
            if (isset($GLOBALS[$var])) {
                $this->globalBackup[$var] = $GLOBALS[$var];
            } else {
                $this->globalBackup[$var] = null;
            }
        }
    }
    
    /**
     * Restore global variables
     */
    public function restoreGlobals(): void
    {
        foreach ($this->globalBackup as $var => $value) {
            if ($value === null) {
                unset($GLOBALS[$var]);
            } else {
                $GLOBALS[$var] = $value;
            }
        }
        $this->globalBackup = [];
    }
    
    /**
     * Set global variable value
     * 
     * @param string $name
     * @param mixed $value
     */
    public function setGlobal(string $name, $value): void
    {
        $GLOBALS[$name] = $value;
    }
    
    /**
     * Clear all mocks and restore state
     */
    public function reset(): void
    {
        $this->mocks = [];
        $this->functionOverrides = [];
        $this->restoreGlobals();
    }
    
    /**
     * Override a function in the global namespace
     * 
     * @param string $functionName
     */
    private function overrideFunction(string $functionName): void
    {
        // Store the override for reference
        $this->functionOverrides[$functionName] = true;
        
        // Note: In a real implementation, this would use runkit or similar
        // For our test framework, we'll rely on the bootstrap.php to check
        // for mocks before executing the default implementation
    }
    
    /**
     * Check if a function is mocked
     * 
     * @param string $functionName
     * @return bool
     */
    public function isMocked(string $functionName): bool
    {
        return isset($this->mocks[$functionName]);
    }
    
    /**
     * Create a spy that tracks calls but passes through to original function
     * 
     * @param string $functionName
     * @param callable $originalFunction
     */
    public function createSpy(string $functionName, callable $originalFunction): void
    {
        $this->registerMock($functionName, function(...$args) use ($originalFunction) {
            return $originalFunction(...$args);
        });
    }
    
    /**
     * Assert a function was called a specific number of times
     * 
     * @param string $functionName
     * @param int $expectedCount
     * @throws \AssertionError
     */
    public function assertCallCount(string $functionName, int $expectedCount): void
    {
        $actualCount = $this->getCallCount($functionName);
        if ($actualCount !== $expectedCount) {
            throw new \AssertionError(
                "Expected $functionName to be called $expectedCount times, but was called $actualCount times"
            );
        }
    }
    
    /**
     * Assert a function was never called
     * 
     * @param string $functionName
     * @throws \AssertionError
     */
    public function assertNotCalled(string $functionName): void
    {
        $this->assertCallCount($functionName, 0);
    }
    
    /**
     * Assert a function was called at least once
     * 
     * @param string $functionName
     * @throws \AssertionError
     */
    public function assertCalled(string $functionName): void
    {
        $count = $this->getCallCount($functionName);
        if ($count === 0) {
            throw new \AssertionError("Expected $functionName to be called at least once, but was never called");
        }
    }
}