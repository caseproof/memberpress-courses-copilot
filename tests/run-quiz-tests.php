<?php
/**
 * Quiz Test Runner
 * 
 * Simple script to run quiz-specific tests and display results
 * 
 * @package MemberPressCoursesCopilot\Tests
 */

// Ensure we're running from command line
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

// Get plugin root directory
$pluginRoot = dirname(__DIR__);

echo "===========================================\n";
echo "MemberPress Courses Copilot - Quiz Tests\n";
echo "===========================================\n\n";

// Check if PHPUnit is available
$phpunitPath = $pluginRoot . '/vendor/bin/phpunit';
if (!file_exists($phpunitPath)) {
    echo "❌ PHPUnit not found. Please run 'composer install' first.\n";
    exit(1);
}

// Check if autoloader exists
$autoloaderPath = $pluginRoot . '/vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    echo "❌ Autoloader not found. Please run 'composer install' first.\n";
    exit(1);
}

echo "🔍 Running Quiz-specific PHPUnit Tests...\n\n";

// Run specific quiz tests
$testFiles = [
    'tests/Unit/Controllers/MpccQuizAjaxControllerTest.php',
    'tests/Unit/Security/QuizSanitizationTest.php', 
    'tests/Unit/Security/QuizPermissionTest.php'
];

$allPassed = true;
$totalTests = 0;
$totalAssertions = 0;
$totalFailures = 0;

foreach ($testFiles as $testFile) {
    $fullPath = $pluginRoot . '/' . $testFile;
    
    if (!file_exists($fullPath)) {
        echo "❌ Test file not found: $testFile\n";
        $allPassed = false;
        continue;
    }
    
    echo "📋 Running: " . basename($testFile) . "\n";
    
    // Run the specific test file
    $command = escapeshellcmd($phpunitPath) . ' --configuration=' . escapeshellarg($pluginRoot . '/phpunit.xml') . ' ' . escapeshellarg($fullPath);
    $output = [];
    $returnCode = 0;
    
    exec($command . ' 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✅ Passed\n";
        
        // Extract test statistics from output
        $outputText = implode("\n", $output);
        if (preg_match('/Tests: (\d+), Assertions: (\d+)/', $outputText, $matches)) {
            $tests = (int)$matches[1];
            $assertions = (int)$matches[2];
            $totalTests += $tests;
            $totalAssertions += $assertions;
            echo "   Tests: $tests, Assertions: $assertions\n";
        }
    } else {
        echo "❌ Failed\n";
        $allPassed = false;
        
        // Extract failure information
        $outputText = implode("\n", $output);
        if (preg_match('/Tests: (\d+), Assertions: (\d+), Failures: (\d+)/', $outputText, $matches)) {
            $tests = (int)$matches[1];
            $assertions = (int)$matches[2];
            $failures = (int)$matches[3];
            $totalTests += $tests;
            $totalAssertions += $assertions;
            $totalFailures += $failures;
            echo "   Tests: $tests, Assertions: $assertions, Failures: $failures\n";
        }
        
        // Show first few lines of error output
        $errorLines = array_slice($output, 0, 10);
        foreach ($errorLines as $line) {
            if (trim($line)) {
                echo "   " . $line . "\n";
            }
        }
    }
    
    echo "\n";
}

echo "===========================================\n";
echo "Test Summary\n";
echo "===========================================\n";
echo "Total Tests: $totalTests\n";
echo "Total Assertions: $totalAssertions\n";

if ($allPassed && $totalFailures === 0) {
    echo "✅ All quiz tests passed!\n";
    echo "\n🎉 Quiz functionality is properly tested and secure.\n";
    exit(0);
} else {
    echo "❌ Some tests failed.\n";
    echo "Total Failures: $totalFailures\n";
    echo "\n🔧 Please review the failing tests and fix any issues.\n";
    exit(1);
}