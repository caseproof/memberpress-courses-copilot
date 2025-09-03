/**
 * Jest configuration for MemberPress Courses Copilot JavaScript tests
 * 
 * @package MemberPressCoursesCopilot\Tests\JavaScript
 */

module.exports = {
    // Root directory
    rootDir: '../../',
    
    // Test environment
    testEnvironment: 'jsdom',
    
    // Test file patterns
    testMatch: [
        '<rootDir>/tests/JavaScript/**/*.test.js',
        '<rootDir>/tests/JavaScript/**/*.spec.js'
    ],
    
    // Setup files
    setupFilesAfterEnv: [
        '<rootDir>/tests/JavaScript/setup.js'
    ],
    
    // Module paths
    moduleDirectories: [
        'node_modules',
        '<rootDir>/assets/js',
        '<rootDir>/tests/JavaScript'
    ],
    
    // Coverage configuration
    collectCoverage: true,
    collectCoverageFrom: [
        'assets/js/**/*.js',
        '!assets/js/**/*.min.js',
        '!node_modules/**'
    ],
    coverageDirectory: 'tests/coverage',
    coverageReporters: ['text', 'lcov', 'html'],
    
    // Transform files
    transform: {
        '^.+\\.js$': 'babel-jest'
    },
    
    // Ignore patterns
    testPathIgnorePatterns: [
        '/node_modules/',
        '/vendor/'
    ],
    
    // Mock CSS and other assets
    moduleNameMapper: {
        '\\.(css|less|scss|sass)$': 'identity-obj-proxy'
    },
    
    // Verbose output
    verbose: true,
    
    // Test timeout
    testTimeout: 10000
};