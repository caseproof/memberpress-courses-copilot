const puppeteer = require('puppeteer');
const path = require('path');

async function testButtonClick() {
  console.log('Testing "Create with AI" button click...');
  
  const browser = await puppeteer.launch({
    headless: false,
    devtools: false,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  try {
    const page = await browser.newPage();
    
    // Enable console logging
    page.on('console', msg => {
      console.log(`Browser console [${msg.type()}]:`, msg.text());
    });
    
    // Login
    await page.goto('http://localhost:10044/wp-login.php', { waitUntil: 'networkidle2' });
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForNavigation({ waitUntil: 'networkidle2' });
    console.log('✓ Logged in successfully');
    
    // Go to courses page
    await page.goto('http://localhost:10044/wp-admin/edit.php?post_type=mpcs-course', { 
      waitUntil: 'networkidle2' 
    });
    console.log('✓ Navigated to courses listing page');
    
    // Wait for button to appear
    await page.waitForSelector('#mpcc-create-with-ai', { timeout: 5000 });
    console.log('✓ "Create with AI" button found');
    
    // Take screenshot before click
    await page.screenshot({
      path: path.join(__dirname, 'screenshots', 'before-click.png'),
      fullPage: false
    });
    
    // Get button details
    const buttonDetails = await page.evaluate(() => {
      const button = document.querySelector('#mpcc-create-with-ai');
      return {
        href: button.href,
        onclick: button.onclick ? 'has onclick' : 'no onclick',
        text: button.textContent.trim()
      };
    });
    console.log('Button details:', buttonDetails);
    
    // Click the button
    console.log('Clicking "Create with AI" button...');
    await page.click('#mpcc-create-with-ai');
    
    // Wait for navigation or modal
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Check current URL
    const currentUrl = page.url();
    console.log('Current URL after click:', currentUrl);
    
    // Check if we navigated to the AI editor
    if (currentUrl.includes('page=mpcc-course-editor')) {
      console.log('✓ Successfully navigated to AI Course Editor!');
      
      // Take screenshot of the editor page
      await page.screenshot({
        path: path.join(__dirname, 'screenshots', 'ai-editor-page.png'),
        fullPage: false
      });
      
      // Check for AI interface elements
      const editorElements = await page.evaluate(() => {
        return {
          hasInterface: !!document.querySelector('.mpcc-ai-interface'),
          hasChat: !!document.querySelector('.mpcc-chat-container'),
          pageTitle: document.querySelector('h1')?.textContent || null,
          errors: Array.from(document.querySelectorAll('.error, .notice-error')).map(el => el.textContent)
        };
      });
      console.log('Editor page elements:', editorElements);
    } else {
      console.log('⚠️  Did not navigate to AI editor. Still on:', currentUrl);
      
      // Check if a modal opened instead
      const modalCheck = await page.evaluate(() => {
        const modals = document.querySelectorAll('.modal, [role="dialog"], .mp-copilot-modal');
        return {
          modalCount: modals.length,
          modalVisible: Array.from(modals).some(m => m.offsetParent !== null)
        };
      });
      console.log('Modal check:', modalCheck);
    }
    
  } catch (error) {
    console.error('Test failed:', error);
    
    // Take error screenshot
    await page.screenshot({
      path: path.join(__dirname, 'screenshots', 'error-state.png'),
      fullPage: true
    });
  } finally {
    console.log('\nTest complete. Browser will close in 5 seconds...');
    await new Promise(resolve => setTimeout(resolve, 5000));
    await browser.close();
  }
}

testButtonClick();