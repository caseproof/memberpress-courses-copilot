const puppeteer = require('puppeteer');
const path = require('path');

async function detailedCoursesListingTest() {
  console.log('Starting detailed MemberPress Courses listing page test...');
  
  const browser = await puppeteer.launch({
    headless: false,
    devtools: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  try {
    const page = await browser.newPage();
    
    // Enable console logging
    page.on('console', msg => {
      console.log(`Browser console [${msg.type()}]:`, msg.text());
    });
    
    // Capture network failures
    page.on('requestfailed', request => {
      console.log(`Failed request: ${request.url()} - ${request.failure().errorText}`);
    });

    // Capture page errors
    page.on('pageerror', error => {
      console.log('Page error:', error.message);
    });

    console.log('Navigating to WordPress login page...');
    await page.goto('http://localhost:10044/wp-login.php', {
      waitUntil: 'networkidle2'
    });

    // Login to WordPress
    console.log('Logging in to WordPress...');
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await page.click('#wp-submit');
    
    // Wait for login to complete
    await page.waitForNavigation({ waitUntil: 'networkidle2' });
    console.log('Login successful');

    // Navigate to courses listing page
    console.log('Navigating to MemberPress Courses listing page...');
    await page.goto('http://localhost:10044/wp-admin/edit.php?post_type=mpcs-course', {
      waitUntil: 'networkidle2',
      timeout: 30000
    });

    // Check for .wp-header-end element
    const wpHeaderEnd = await page.evaluate(() => {
      const element = document.querySelector('.wp-header-end');
      return {
        exists: !!element,
        html: element ? element.outerHTML : null,
        parent: element ? element.parentElement.className : null
      };
    });
    console.log('.wp-header-end element:', wpHeaderEnd);

    // Check if JavaScript variable mpccCreateButton exists
    const jsVariableExists = await page.evaluate(() => {
      return {
        mpccCreateButton: typeof mpccCreateButton !== 'undefined' ? mpccCreateButton : null,
        jQuery: typeof jQuery !== 'undefined',
        jQueryVersion: typeof jQuery !== 'undefined' ? jQuery.fn.jquery : null
      };
    });
    console.log('JavaScript variables:', jsVariableExists);

    // Check all scripts loaded
    const scripts = await page.evaluate(() => {
      return Array.from(document.querySelectorAll('script[src]'))
        .map(s => s.src)
        .filter(src => src.includes('mpcc') || src.includes('copilot') || src.includes('course-integration'));
    });
    console.log('Copilot scripts loaded:', scripts);

    // Check if the button was added dynamically
    const dynamicButton = await page.evaluate(() => {
      return document.querySelector('#mpcc-create-with-ai') ? {
        exists: true,
        html: document.querySelector('#mpcc-create-with-ai').outerHTML,
        parent: document.querySelector('#mpcc-create-with-ai').parentElement.className
      } : { exists: false };
    });
    console.log('Dynamic button check:', dynamicButton);

    // Try to manually run the button creation code
    console.log('Attempting to manually create button...');
    const manualButtonResult = await page.evaluate(() => {
      if (typeof jQuery === 'undefined') {
        return { error: 'jQuery not available' };
      }
      
      const $ = jQuery;
      const wpHeaderEnd = $('.wp-header-end');
      if (wpHeaderEnd.length === 0) {
        // Alternative: Try adding after h1
        const h1 = $('.wrap h1');
        if (h1.length > 0) {
          const button = $('<a href="#" id="mpcc-create-with-ai-manual" class="page-title-action" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; text-shadow: none; margin-left: 10px;">Create with AI (Manual)</a>');
          h1.append(button);
          return { success: true, method: 'after h1' };
        }
        return { error: 'No .wp-header-end or h1 found' };
      }
      
      const button = $('<a href="#" id="mpcc-create-with-ai-manual" class="page-title-action" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; text-shadow: none;">Create with AI (Manual)</a>');
      wpHeaderEnd.before(button);
      return { success: true, method: 'before wp-header-end' };
    });
    console.log('Manual button creation result:', manualButtonResult);

    // Take screenshot after manual attempt
    await page.screenshot({
      path: path.join(__dirname, 'screenshots', 'courses-listing-manual-button.png'),
      fullPage: true
    });
    console.log('Screenshot with manual button saved');

    // Check the DOM structure around the title
    const titleStructure = await page.evaluate(() => {
      const wrap = document.querySelector('.wrap');
      if (!wrap) return { error: 'No .wrap found' };
      
      const h1 = wrap.querySelector('h1');
      if (!h1) return { error: 'No h1 found' };
      
      return {
        h1Text: h1.textContent.trim(),
        h1HTML: h1.outerHTML,
        nextSibling: h1.nextElementSibling ? {
          tag: h1.nextElementSibling.tagName,
          className: h1.nextElementSibling.className
        } : null,
        parentHTML: h1.parentElement.outerHTML.substring(0, 500) + '...'
      };
    });
    console.log('Title structure:', titleStructure);

  } catch (error) {
    console.error('Test failed:', error);
    throw error;
  } finally {
    console.log('Test completed. Browser will remain open for inspection.');
    // Keep browser open for manual inspection
    await new Promise(resolve => setTimeout(resolve, 30000));
    await browser.close();
  }
}

// Run the test
detailedCoursesListingTest()
  .then(() => {
    console.log('Test completed successfully');
    process.exit(0);
  })
  .catch(error => {
    console.error('Test failed:', error);
    process.exit(1);
  });