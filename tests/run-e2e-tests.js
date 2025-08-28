#!/usr/bin/env node

const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

console.log('MemberPress Courses Copilot - E2E Test Runner');
console.log('============================================\n');

// Change to plugin directory
const pluginDir = path.join(__dirname, '..');
process.chdir(pluginDir);

// Check if puppeteer is installed
try {
    require.resolve('puppeteer');
} catch (e) {
    console.error('❌ Puppeteer not installed. Installing now...');
    execSync('npm install puppeteer', { stdio: 'inherit' });
}

// Find all e2e test files
const e2eDir = path.join(__dirname, 'e2e');
const testFiles = fs.readdirSync(e2eDir)
    .filter(file => file.startsWith('test-') && file.endsWith('.js'))
    .map(file => path.join(e2eDir, file));

console.log(`Found ${testFiles.length} E2E test(s) to run:\n`);

let passed = 0;
let failed = 0;

// Run each test
testFiles.forEach((testFile, index) => {
    const testName = path.basename(testFile);
    console.log(`\nRunning test ${index + 1}/${testFiles.length}: ${testName}`);
    console.log('----------------------------------------');
    
    try {
        execSync(`node "${testFile}"`, { stdio: 'inherit' });
        passed++;
        console.log(`✅ ${testName} PASSED`);
    } catch (error) {
        failed++;
        console.log(`❌ ${testName} FAILED`);
    }
});

// Summary
console.log('\n\nTest Summary');
console.log('============');
console.log(`Total tests: ${testFiles.length}`);
console.log(`Passed: ${passed}`);
console.log(`Failed: ${failed}`);

if (failed > 0) {
    console.log('\n❌ Some tests failed!');
    process.exit(1);
} else {
    console.log('\n✅ All tests passed!');
    process.exit(0);
}