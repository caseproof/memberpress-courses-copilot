const puppeteer = require('puppeteer');
const fs = require('fs').promises;
const path = require('path');

// Test configuration
const CONFIG = {
  url: 'http://localhost:10044/wp-admin/post.php?post=1798&action=edit',
  loginUrl: 'http://localhost:10044/wp-login.php',
  username: process.env.WP_USERNAME || 'admin',
  password: process.env.WP_PASSWORD || 'password',
  screenshotDir: path.join(__dirname, 'screenshots'),
  timeout: 60000,
  headless: false // Set to true for CI/CD
};

// Console message types to track
const CONSOLE_TYPES = {
  error: [],
  warning: [],
  log: [],
  info: []
};

// Network issues tracker
const NETWORK_ISSUES = {
  failed: [],
  aborted: [],
  notFound: []
};

// UI elements to check
const UI_ELEMENTS = {
  'AI Create Button (Classic)': '#mpcc-editor-ai-button',
  'AI Create Button (Block)': '#mpcc-editor-ai-button-block',
  'AI Modal Overlay': '#mpcc-editor-ai-modal-overlay',
  'AI Modal': '.mpcc-modal',
  'AI Messages Container': '#mpcc-editor-ai-messages',
  'AI Input Field': '#mpcc-editor-ai-input',
  'AI Send Button': '#mpcc-editor-ai-send',
  'Quick Start Section': '.mpcc-quick-start-section',
  'Quick Start Buttons': '.mpcc-quick-start-btn'
};

// Critical JavaScript files to check
const CRITICAL_JS_FILES = [
  'editor-ai-button.js',
  'editor-ai-modal.js',
  'shared-utilities.js',
  'toast.js',
  'ai-copilot.css',
  'editor-ai-modal.css'
];

async function ensureScreenshotDir() {
  try {
    await fs.access(CONFIG.screenshotDir);
  } catch {
    await fs.mkdir(CONFIG.screenshotDir, { recursive: true });
  }
}

async function loginToWordPress(page) {
  console.log('🔐 Logging into WordPress...');
  
  await page.goto(CONFIG.loginUrl, { waitUntil: 'networkidle2' });
  
  // Check if already logged in
  if (page.url().includes('wp-admin')) {
    console.log('✅ Already logged in');
    return true;
  }
  
  // Fill in login form
  await page.type('#user_login', CONFIG.username);
  await page.type('#user_pass', CONFIG.password);
  await page.click('#wp-submit');
  
  // Wait for redirect
  await page.waitForNavigation({ waitUntil: 'networkidle2' });
  
  if (page.url().includes('wp-admin')) {
    console.log('✅ Login successful');
    return true;
  } else {
    console.error('❌ Login failed');
    return false;
  }
}

async function captureConsoleMessages(page) {
  page.on('console', msg => {
    const type = msg.type();
    const text = msg.text();
    
    if (CONSOLE_TYPES[type]) {
      CONSOLE_TYPES[type].push({
        text,
        location: msg.location(),
        args: msg.args()
      });
    }
  });
  
  // Capture page errors
  page.on('pageerror', error => {
    CONSOLE_TYPES.error.push({
      text: error.message,
      stack: error.stack
    });
  });
}

async function monitorNetworkRequests(page) {
  page.on('requestfailed', request => {
    NETWORK_ISSUES.failed.push({
      url: request.url(),
      method: request.method(),
      failure: request.failure()
    });
  });
  
  page.on('response', response => {
    const status = response.status();
    const url = response.url();
    
    if (status === 404) {
      NETWORK_ISSUES.notFound.push({
        url,
        status,
        statusText: response.statusText()
      });
    } else if (status >= 400) {
      NETWORK_ISSUES.failed.push({
        url,
        status,
        statusText: response.statusText()
      });
    }
  });
}

async function checkUIElements(page) {
  const results = {};
  
  for (const [name, selector] of Object.entries(UI_ELEMENTS)) {
    try {
      const element = await page.$(selector);
      const isVisible = element ? await element.isIntersectingViewport() : false;
      
      results[name] = {
        exists: !!element,
        visible: isVisible,
        selector
      };
      
      if (element && isVisible) {
        // Get element details
        const boundingBox = await element.boundingBox();
        results[name].position = boundingBox;
      }
    } catch (error) {
      results[name] = {
        exists: false,
        visible: false,
        selector,
        error: error.message
      };
    }
  }
  
  return results;
}

async function checkJavaScriptFiles(page) {
  const results = {};
  
  // Get all script tags
  const scripts = await page.$$eval('script[src]', elements => 
    elements.map(el => ({
      src: el.src,
      async: el.async,
      defer: el.defer
    }))
  );
  
  // Check for critical files
  for (const file of CRITICAL_JS_FILES) {
    const found = scripts.find(script => script.src.includes(file));
    results[file] = {
      loaded: !!found,
      details: found || null
    };
  }
  
  return results;
}

async function checkAjaxRequests(page) {
  const ajaxRequests = [];
  
  // Intercept AJAX requests
  await page.evaluateOnNewDocument(() => {
    const originalOpen = XMLHttpRequest.prototype.open;
    const originalSend = XMLHttpRequest.prototype.send;
    
    window.__ajaxRequests = [];
    
    XMLHttpRequest.prototype.open = function(method, url, ...args) {
      this.__requestInfo = { method, url, timestamp: Date.now() };
      return originalOpen.call(this, method, url, ...args);
    };
    
    XMLHttpRequest.prototype.send = function(data) {
      const requestInfo = this.__requestInfo;
      
      this.addEventListener('load', function() {
        window.__ajaxRequests.push({
          ...requestInfo,
          status: this.status,
          statusText: this.statusText,
          responseTime: Date.now() - requestInfo.timestamp,
          success: this.status >= 200 && this.status < 300
        });
      });
      
      this.addEventListener('error', function() {
        window.__ajaxRequests.push({
          ...requestInfo,
          status: 0,
          statusText: 'Network Error',
          error: true
        });
      });
      
      return originalSend.call(this, data);
    };
  });
  
  // Wait a bit for AJAX requests to complete
  await new Promise(resolve => setTimeout(resolve, 5000));
  
  // Collect AJAX data
  const requests = await page.evaluate(() => window.__ajaxRequests || []);
  
  return requests;
}

async function takeScreenshots(page, prefix) {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  
  // Full page screenshot
  const fullPagePath = path.join(CONFIG.screenshotDir, `${prefix}-full-${timestamp}.png`);
  await page.screenshot({ 
    path: fullPagePath, 
    fullPage: true 
  });
  console.log(`📸 Full page screenshot saved: ${fullPagePath}`);
  
  // Viewport screenshot
  const viewportPath = path.join(CONFIG.screenshotDir, `${prefix}-viewport-${timestamp}.png`);
  await page.screenshot({ 
    path: viewportPath 
  });
  console.log(`📸 Viewport screenshot saved: ${viewportPath}`);
  
  return { fullPagePath, viewportPath };
}

async function generateReport(results) {
  const timestamp = new Date().toISOString();
  const reportPath = path.join(CONFIG.screenshotDir, `diagnostic-report-${timestamp.replace(/[:.]/g, '-')}.json`);
  
  const report = {
    timestamp,
    url: CONFIG.url,
    ...results
  };
  
  await fs.writeFile(reportPath, JSON.stringify(report, null, 2));
  console.log(`📄 Full report saved: ${reportPath}`);
  
  return report;
}

async function runDiagnostic() {
  console.log('🚀 Starting Course Edit Page Diagnostic...');
  console.log(`📍 Target URL: ${CONFIG.url}`);
  
  await ensureScreenshotDir();
  
  const browser = await puppeteer.launch({
    headless: CONFIG.headless,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  
  const page = await browser.newPage();
  await page.setViewport({ width: 1920, height: 1080 });
  
  // Set up monitoring
  captureConsoleMessages(page);
  monitorNetworkRequests(page);
  
  try {
    // Login first
    const loginSuccess = await loginToWordPress(page);
    if (!loginSuccess) {
      throw new Error('Failed to login to WordPress');
    }
    
    // Navigate to course edit page
    console.log('📄 Navigating to course edit page...');
    await page.goto(CONFIG.url, { 
      waitUntil: 'networkidle2',
      timeout: CONFIG.timeout 
    });
    
    // Wait a bit for dynamic content
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Run diagnostics
    console.log('🔍 Checking UI elements...');
    const uiResults = await checkUIElements(page);
    
    console.log('📜 Checking JavaScript files...');
    const jsFiles = await checkJavaScriptFiles(page);
    
    console.log('🌐 Checking AJAX requests...');
    const ajaxRequests = await checkAjaxRequests(page);
    
    // Take screenshots
    console.log('📸 Taking screenshots...');
    const screenshots = await takeScreenshots(page, 'course-edit');
    
    // Generate summary
    const summary = {
      success: true,
      pageTitle: await page.title(),
      pageUrl: page.url(),
      console: CONSOLE_TYPES,
      network: NETWORK_ISSUES,
      ui: uiResults,
      javascript: jsFiles,
      ajax: ajaxRequests,
      screenshots
    };
    
    // Print summary
    console.log('\n📊 DIAGNOSTIC SUMMARY:');
    console.log('====================');
    
    console.log('\n🔴 JavaScript Errors:', CONSOLE_TYPES.error.length);
    if (CONSOLE_TYPES.error.length > 0) {
      CONSOLE_TYPES.error.forEach((err, i) => {
        console.log(`  ${i + 1}. ${err.text}`);
      });
    }
    
    console.log('\n⚠️  Console Warnings:', CONSOLE_TYPES.warning.length);
    
    console.log('\n🌐 Network Issues:');
    console.log(`  - Failed requests: ${NETWORK_ISSUES.failed.length}`);
    console.log(`  - 404 Not Found: ${NETWORK_ISSUES.notFound.length}`);
    
    if (NETWORK_ISSUES.notFound.length > 0) {
      console.log('  404 URLs:');
      NETWORK_ISSUES.notFound.forEach(issue => {
        console.log(`    - ${issue.url}`);
      });
    }
    
    console.log('\n🎨 UI Elements Status:');
    for (const [name, result] of Object.entries(uiResults)) {
      const status = result.exists ? (result.visible ? '✅' : '⚠️ ') : '❌';
      console.log(`  ${status} ${name}: ${result.exists ? 'Found' : 'Missing'} ${result.visible ? '(Visible)' : result.exists ? '(Hidden)' : ''}`);
    }
    
    console.log('\n📜 Critical JavaScript Files:');
    for (const [file, result] of Object.entries(jsFiles)) {
      console.log(`  ${result.loaded ? '✅' : '❌'} ${file}`);
    }
    
    console.log('\n🔄 AJAX Requests:', ajaxRequests.length);
    const failedAjax = ajaxRequests.filter(req => !req.success && !req.error);
    if (failedAjax.length > 0) {
      console.log(`  ⚠️  Failed AJAX requests: ${failedAjax.length}`);
      failedAjax.forEach(req => {
        console.log(`    - ${req.method} ${req.url} (${req.status})`);
      });
    }
    
    // Generate full report
    const report = await generateReport(summary);
    
    return report;
    
  } catch (error) {
    console.error('❌ Diagnostic failed:', error);
    
    // Take error screenshot
    try {
      await takeScreenshots(page, 'error');
    } catch (screenshotError) {
      console.error('Failed to take error screenshot:', screenshotError);
    }
    
    throw error;
    
  } finally {
    await browser.close();
  }
}

// Run the diagnostic
runDiagnostic()
  .then(report => {
    console.log('\n✅ Diagnostic completed successfully!');
    console.log(`📄 Check ${CONFIG.screenshotDir} for screenshots and full report`);
    process.exit(0);
  })
  .catch(error => {
    console.error('\n❌ Diagnostic failed:', error);
    process.exit(1);
  });