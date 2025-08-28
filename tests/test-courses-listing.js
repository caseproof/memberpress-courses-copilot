const puppeteer = require('puppeteer');
const path = require('path');

async function testCoursesListingPage() {
  console.log('Starting MemberPress Courses listing page test...');
  
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

    // Take initial screenshot
    await page.screenshot({
      path: path.join(__dirname, 'screenshots', 'courses-listing-initial.png'),
      fullPage: true
    });
    console.log('Initial screenshot saved');

    // Check for JavaScript errors
    const jsErrors = await page.evaluate(() => {
      return window.jsErrors || [];
    });
    if (jsErrors.length > 0) {
      console.log('JavaScript errors found:', jsErrors);
    }

    // Look for "Create with AI" button using multiple strategies
    console.log('Looking for "Create with AI" button...');
    
    // Find button with text content (excluding menu items)
    const createWithAIButton = await page.evaluateHandle(() => {
      // Look specifically in the page header/action area, not in menus
      const wrap = document.querySelector('.wrap');
      if (!wrap) return null;
      
      // Check buttons with "Create with AI" text in the main content area
      const buttons = Array.from(wrap.querySelectorAll('button, a.page-title-action, a.button'));
      const aiButton = buttons.find(btn => 
        btn.textContent.includes('Create with AI') || 
        btn.textContent.includes('Create Course with AI') ||
        btn.textContent.includes('AI Create')
      );
      if (aiButton) return aiButton;
      
      // Check by class/id in the content area
      return wrap.querySelector('.ai-create-button') ||
             wrap.querySelector('#ai-create-course-btn') ||
             wrap.querySelector('[data-action="ai-create"]') ||
             wrap.querySelector('a[href*="ai-create"]') ||
             wrap.querySelector('button[data-copilot]') ||
             wrap.querySelector('.mp-copilot-button') ||
             wrap.querySelector('.mp-ai-create');
    });
    
    const hasButton = await createWithAIButton.evaluate(el => el !== null);
    
    if (hasButton) {
      console.log('"Create with AI" button found!');
      
      // Get button details
      const buttonDetails = await page.evaluate(el => {
        return {
          text: el.textContent,
          id: el.id,
          className: el.className,
          tagName: el.tagName,
          href: el.href || null,
          dataAttributes: Object.keys(el.dataset).reduce((acc, key) => {
            acc[key] = el.dataset[key];
            return acc;
          }, {})
        };
      }, createWithAIButton);
      console.log('Button details:', buttonDetails);
      
      // Try clicking the button
      console.log('Attempting to click "Create with AI" button...');
      await createWithAIButton.click();
      
      // Wait a bit for modal or response
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      // Check if modal opened
      const modal = await page.$('.ai-modal, .modal, [role="dialog"], .mp-copilot-modal, .mp-ai-modal');
      if (modal) {
        console.log('Modal opened successfully!');
        await page.screenshot({
          path: path.join(__dirname, 'screenshots', 'courses-listing-modal.png'),
          fullPage: true
        });
        console.log('Modal screenshot saved');
        
        // Get modal details
        const modalDetails = await page.evaluate(el => {
          return {
            className: el.className,
            id: el.id,
            isVisible: el.offsetParent !== null,
            content: el.textContent.substring(0, 200) + '...'
          };
        }, modal);
        console.log('Modal details:', modalDetails);
      } else {
        console.log('No modal detected after clicking button');
      }
    } else {
      console.log('"Create with AI" button NOT found on the page');
      
      // Look for any buttons in the page header area
      const allButtons = await page.$$eval('.wrap .page-title-action, .wrap button, .wrap a.button', buttons => {
        return buttons.map(btn => ({
          text: btn.textContent.trim(),
          id: btn.id,
          className: btn.className,
          href: btn.href || null
        }));
      });
      console.log('All buttons found in header area:', allButtons);
      
      // Also check for buttons with AI-related text
      const aiRelatedElements = await page.evaluate(() => {
        const elements = Array.from(document.querySelectorAll('button, a'));
        return elements
          .filter(el => el.textContent.toLowerCase().includes('ai'))
          .map(el => ({
            text: el.textContent.trim(),
            tagName: el.tagName,
            id: el.id,
            className: el.className
          }));
      });
      if (aiRelatedElements.length > 0) {
        console.log('Found AI-related elements:', aiRelatedElements);
      }
    }

    // Check for AI-related scripts
    const aiScripts = await page.evaluate(() => {
      const scripts = Array.from(document.querySelectorAll('script[src]'));
      return scripts
        .map(s => s.src)
        .filter(src => src.includes('ai') || src.includes('copilot') || src.includes('mp-copilot'));
    });
    console.log('AI-related scripts loaded:', aiScripts.length > 0 ? aiScripts : 'None found');

    // Check for AI-related styles
    const aiStyles = await page.evaluate(() => {
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
      return links
        .map(l => l.href)
        .filter(href => href.includes('ai') || href.includes('copilot') || href.includes('mp-copilot'));
    });
    console.log('AI-related stylesheets loaded:', aiStyles.length > 0 ? aiStyles : 'None found');

    // Check page structure
    const pageStructure = await page.evaluate(() => {
      const wrap = document.querySelector('.wrap');
      if (!wrap) return { error: 'No .wrap element found' };
      
      return {
        title: wrap.querySelector('h1')?.textContent || 'No title found',
        buttonCount: wrap.querySelectorAll('a.page-title-action, button').length,
        hasTable: !!wrap.querySelector('table.wp-list-table'),
        formPresent: !!wrap.querySelector('form')
      };
    });
    console.log('Page structure:', pageStructure);

    // Final screenshot
    await page.screenshot({
      path: path.join(__dirname, 'screenshots', 'courses-listing-final.png'),
      fullPage: true
    });
    console.log('Final screenshot saved');

  } catch (error) {
    console.error('Test failed:', error);
    throw error;
  } finally {
    console.log('Closing browser...');
    await browser.close();
  }
}

// Run the test
testCoursesListingPage()
  .then(() => {
    console.log('Test completed successfully');
    process.exit(0);
  })
  .catch(error => {
    console.error('Test failed:', error);
    process.exit(1);
  });