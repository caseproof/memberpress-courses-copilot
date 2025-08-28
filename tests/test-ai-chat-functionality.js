/**
 * AI Chat Functionality Test Script
 * Tests the course editor AI chat on http://localhost:10044/wp-admin/admin.php?page=mpcc-course-editor&action=new
 * 
 * Run this script using:
 * node test-ai-chat-functionality.js
 */

const puppeteer = require('puppeteer');

// Test configuration
const testConfig = {
  baseURL: 'http://localhost:10044',
  username: 'admin',
  password: 'admin',
  courseEditorURL: '/wp-admin/admin.php?page=mpcc-course-editor&action=new',
  timeout: 30000,
  screenshotsDir: 'tests/screenshots'
};

// Test results
const testResults = {
  timestamp: new Date().toISOString(),
  tests: [],
  errors: [],
  networkRequests: [],
  consoleMessages: []
};

async function loginToWordPress(page) {
  console.log('🔐 Logging into WordPress...');
  await page.goto(`${testConfig.baseURL}/wp-login.php`, { waitUntil: 'networkidle2' });
  await page.type('#user_login', testConfig.username);
  await page.type('#user_pass', testConfig.password);
  await page.click('#wp-submit');
  await page.waitForNavigation({ waitUntil: 'networkidle2' });
  console.log('✅ Login successful');
}

async function testAIChatInterface(page) {
  console.log('\n📝 Testing AI Chat Interface...\n');
  
  const test = {
    name: 'AI Chat Interface Test',
    steps: [],
    status: 'running'
  };

  try {
    // Navigate to course editor
    console.log('1️⃣ Navigating to course editor page...');
    await page.goto(`${testConfig.baseURL}${testConfig.courseEditorURL}`, { waitUntil: 'networkidle2' });
    await page.waitForTimeout(2000);
    
    // Take screenshot of initial page
    await page.screenshot({ path: `${testConfig.screenshotsDir}/ai-chat-initial.png`, fullPage: true });
    test.steps.push({ step: 'Navigate to page', status: 'success' });

    // Check if chat interface exists
    console.log('2️⃣ Checking for chat interface elements...');
    const chatContainer = await page.$('#mpcc-chat-container');
    const chatInput = await page.$('#mpcc-chat-input');
    const sendButton = await page.$('#mpcc-send-message');
    
    if (!chatContainer || !chatInput || !sendButton) {
      throw new Error('Chat interface elements not found');
    }
    
    test.steps.push({ step: 'Find chat elements', status: 'success' });
    console.log('✅ Chat interface elements found');

    // Check quick starter buttons
    console.log('3️⃣ Checking quick starter suggestions...');
    const quickStarters = await page.$$('.mpcc-quick-starter-btn');
    test.steps.push({ 
      step: 'Quick starter buttons', 
      status: 'success',
      details: `Found ${quickStarters.length} quick starter buttons`
    });

    // Test message sending
    console.log('4️⃣ Testing message send functionality...');
    const testMessage = 'Create a JavaScript course for beginners that covers variables, functions, and DOM manipulation';
    
    // Monitor network requests
    const ajaxRequests = [];
    page.on('request', request => {
      if (request.url().includes('admin-ajax.php')) {
        ajaxRequests.push({
          url: request.url(),
          method: request.method(),
          postData: request.postData(),
          timestamp: new Date().toISOString()
        });
      }
    });

    page.on('response', response => {
      if (response.url().includes('admin-ajax.php')) {
        response.text().then(body => {
          testResults.networkRequests.push({
            url: response.url(),
            status: response.status(),
            statusText: response.statusText(),
            body: body.substring(0, 1000), // First 1000 chars
            timestamp: new Date().toISOString()
          });
        }).catch(() => {});
      }
    });

    // Type message
    await page.click('#mpcc-chat-input');
    await page.type('#mpcc-chat-input', testMessage);
    await page.screenshot({ path: `${testConfig.screenshotsDir}/ai-chat-typed-message.png`, fullPage: true });
    
    // Send message
    console.log('5️⃣ Sending test message...');
    await page.click('#mpcc-send-message');
    
    // Wait for response
    console.log('⏳ Waiting for AI response...');
    try {
      // Wait for typing indicator or response
      await page.waitForSelector('.typing-indicator, .mpcc-chat-message.assistant', { 
        timeout: 10000 
      });
      
      // Wait a bit longer for full response
      await page.waitForTimeout(5000);
      
      // Check if we got a response
      const assistantMessages = await page.$$('.mpcc-chat-message.assistant');
      if (assistantMessages.length > 1) { // Should be more than the welcome message
        test.steps.push({ 
          step: 'Send and receive AI response', 
          status: 'success',
          details: `Received ${assistantMessages.length - 1} AI responses`
        });
        console.log('✅ AI response received');
      } else {
        throw new Error('No AI response received');
      }
      
      await page.screenshot({ path: `${testConfig.screenshotsDir}/ai-chat-response.png`, fullPage: true });
      
    } catch (error) {
      test.steps.push({ 
        step: 'Send and receive AI response', 
        status: 'failed',
        error: error.message
      });
      console.log('❌ Failed to receive AI response:', error.message);
    }

    // Check if course preview updated
    console.log('6️⃣ Checking course preview update...');
    const courseStructure = await page.$('#mpcc-course-structure');
    const courseContent = await page.evaluate(el => el?.innerHTML, courseStructure);
    
    if (courseContent && !courseContent.includes('mpcc-empty-state')) {
      test.steps.push({ 
        step: 'Course preview update', 
        status: 'success',
        details: 'Course structure displayed'
      });
      console.log('✅ Course preview updated');
    } else {
      test.steps.push({ 
        step: 'Course preview update', 
        status: 'failed',
        details: 'Course structure not displayed'
      });
      console.log('❌ Course preview not updated');
    }

    // Test quick starter button
    console.log('7️⃣ Testing quick starter button...');
    try {
      // Clear chat and reload
      await page.reload({ waitUntil: 'networkidle2' });
      await page.waitForTimeout(2000);
      
      const quickStarterBtn = await page.$('.mpcc-quick-starter-btn');
      if (quickStarterBtn) {
        await quickStarterBtn.click();
        await page.waitForTimeout(1000);
        
        // Check if message was populated
        const inputValue = await page.$eval('#mpcc-chat-input', el => el.value);
        if (inputValue) {
          test.steps.push({ 
            step: 'Quick starter button', 
            status: 'success',
            details: `Populated: "${inputValue}"`
          });
          console.log('✅ Quick starter button works');
          
          // Send the quick starter message
          await page.click('#mpcc-send-message');
          await page.waitForTimeout(5000);
          await page.screenshot({ path: `${testConfig.screenshotsDir}/ai-chat-quick-starter-result.png`, fullPage: true });
        }
      }
    } catch (error) {
      test.steps.push({ 
        step: 'Quick starter button', 
        status: 'failed',
        error: error.message
      });
    }

    test.status = 'completed';
    
  } catch (error) {
    test.status = 'failed';
    test.error = error.message;
    testResults.errors.push({
      test: test.name,
      error: error.message,
      stack: error.stack
    });
  }

  testResults.tests.push(test);
}

async function testCourseEditMetabox(page) {
  console.log('\n📦 Testing Course Edit Page AI Assistant Metabox...\n');
  
  const test = {
    name: 'Course Edit Metabox Test',
    steps: [],
    status: 'running'
  };

  try {
    // Create a test course first
    console.log('1️⃣ Creating a test course...');
    await page.goto(`${testConfig.baseURL}/wp-admin/post-new.php?post_type=mpcs-course`, { waitUntil: 'networkidle2' });
    
    // Fill in course title
    await page.type('#title', 'Test Course for AI Assistant');
    
    // Save draft
    await page.click('#save-post');
    await page.waitForSelector('.notice-success', { timeout: 10000 });
    
    test.steps.push({ step: 'Create test course', status: 'success' });

    // Check for AI Assistant metabox
    console.log('2️⃣ Looking for AI Assistant metabox...');
    await page.waitForTimeout(2000);
    
    const metabox = await page.$('#mpcc_ai_assistant_metabox');
    if (metabox) {
      test.steps.push({ step: 'Find AI Assistant metabox', status: 'success' });
      console.log('✅ AI Assistant metabox found');
      
      // Take screenshot
      await page.screenshot({ path: `${testConfig.screenshotsDir}/course-edit-metabox.png`, fullPage: true });
      
      // Check for chat interface inside metabox
      const metaboxChatInput = await page.$('#mpcc_ai_assistant_metabox #mpcc-course-chat-input');
      const metaboxSendBtn = await page.$('#mpcc_ai_assistant_metabox #mpcc-course-send-message');
      
      if (metaboxChatInput && metaboxSendBtn) {
        test.steps.push({ step: 'Metabox chat interface', status: 'success' });
        
        // Test sending a message
        console.log('3️⃣ Testing metabox chat...');
        await metaboxChatInput.type('Help me improve this course description');
        await metaboxSendBtn.click();
        
        await page.waitForTimeout(5000);
        await page.screenshot({ path: `${testConfig.screenshotsDir}/course-edit-metabox-response.png`, fullPage: true });
        
        test.steps.push({ step: 'Send metabox message', status: 'success' });
      } else {
        test.steps.push({ 
          step: 'Metabox chat interface', 
          status: 'failed',
          details: 'Chat interface not found in metabox'
        });
      }
    } else {
      test.steps.push({ 
        step: 'Find AI Assistant metabox', 
        status: 'failed',
        details: 'Metabox not found on page'
      });
      console.log('❌ AI Assistant metabox not found');
    }

    test.status = 'completed';
    
  } catch (error) {
    test.status = 'failed';
    test.error = error.message;
    testResults.errors.push({
      test: test.name,
      error: error.message,
      stack: error.stack
    });
  }

  testResults.tests.push(test);
}

async function generateReport() {
  console.log('\n📊 Generating Test Report...\n');
  
  const report = {
    ...testResults,
    summary: {
      totalTests: testResults.tests.length,
      passed: testResults.tests.filter(t => t.status === 'completed').length,
      failed: testResults.tests.filter(t => t.status === 'failed').length,
      totalErrors: testResults.errors.length
    }
  };

  // Save report
  const fs = require('fs');
  const reportPath = `${testConfig.screenshotsDir}/ai-chat-test-report-${Date.now()}.json`;
  fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
  
  // Print summary
  console.log('='.repeat(50));
  console.log('TEST SUMMARY');
  console.log('='.repeat(50));
  console.log(`Total Tests: ${report.summary.totalTests}`);
  console.log(`Passed: ${report.summary.passed} ✅`);
  console.log(`Failed: ${report.summary.failed} ❌`);
  console.log(`Errors: ${report.summary.totalErrors} 🚨`);
  console.log('='.repeat(50));
  
  // Print detailed results
  report.tests.forEach(test => {
    console.log(`\n${test.name}: ${test.status === 'completed' ? '✅' : '❌'}`);
    test.steps.forEach(step => {
      const icon = step.status === 'success' ? '✓' : '✗';
      console.log(`  ${icon} ${step.step}${step.details ? ': ' + step.details : ''}`);
      if (step.error) {
        console.log(`    Error: ${step.error}`);
      }
    });
  });
  
  // Print network requests summary
  if (testResults.networkRequests.length > 0) {
    console.log('\n📡 AJAX Requests:');
    testResults.networkRequests.forEach(req => {
      console.log(`  ${req.status} ${req.url}`);
      if (req.body && req.body.includes('error')) {
        console.log(`    Response: ${req.body.substring(0, 200)}...`);
      }
    });
  }
  
  // Print errors
  if (testResults.errors.length > 0) {
    console.log('\n🚨 ERRORS:');
    testResults.errors.forEach(err => {
      console.log(`  ${err.test}: ${err.error}`);
    });
  }
  
  console.log(`\n📄 Full report saved to: ${reportPath}`);
}

async function runTests() {
  const browser = await puppeteer.launch({
    headless: false, // Set to true for CI
    slowMo: 50,
    devtools: true,
    args: ['--window-size=1920,1080']
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1920, height: 1080 });
    
    // Capture console messages
    page.on('console', msg => {
      if (msg.type() === 'error' || msg.text().includes('MPCC')) {
        testResults.consoleMessages.push({
          type: msg.type(),
          text: msg.text(),
          timestamp: new Date().toISOString()
        });
      }
    });

    // Login first
    await loginToWordPress(page);
    
    // Run tests
    await testAIChatInterface(page);
    await testCourseEditMetabox(page);
    
    // Generate report
    await generateReport();
    
  } catch (error) {
    console.error('🚨 Test execution failed:', error);
    testResults.errors.push({
      test: 'Test Runner',
      error: error.message,
      stack: error.stack
    });
  } finally {
    await browser.close();
  }
}

// Create screenshots directory
const fs = require('fs');
if (!fs.existsSync(testConfig.screenshotsDir)) {
  fs.mkdirSync(testConfig.screenshotsDir, { recursive: true });
}

// Run the tests
console.log('🚀 Starting AI Chat Functionality Tests...');
runTests().catch(console.error);