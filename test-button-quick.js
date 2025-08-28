const puppeteer = require('puppeteer');
const path = require('path');

async function quickButtonTest() {
  console.log('Quick button test...');
  
  const browser = await puppeteer.launch({
    headless: false,
    devtools: false,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  try {
    const page = await browser.newPage();
    
    // Login
    await page.goto('http://localhost:10044/wp-login.php', { waitUntil: 'networkidle2' });
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await page.click('#wp-submit');
    await page.waitForNavigation({ waitUntil: 'networkidle2' });
    
    // Go to courses page
    await page.goto('http://localhost:10044/wp-admin/edit.php?post_type=mpcs-course', { 
      waitUntil: 'networkidle2' 
    });
    
    // Wait a moment for scripts to load
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Check for button and scripts
    const result = await page.evaluate(() => {
      return {
        buttonExists: !!document.querySelector('#mpcc-create-with-ai'),
        buttonHTML: document.querySelector('#mpcc-create-with-ai')?.outerHTML || null,
        mpccCreateButton: typeof mpccCreateButton !== 'undefined' ? mpccCreateButton : null,
        copilotScripts: Array.from(document.querySelectorAll('script[src]'))
          .map(s => s.src)
          .filter(src => src.includes('copilot') || src.includes('course-integration'))
      };
    });
    
    console.log('Result:', JSON.stringify(result, null, 2));
    
    // Take screenshot
    await page.screenshot({
      path: path.join(__dirname, 'screenshots', 'quick-button-test.png'),
      fullPage: false
    });
    
  } catch (error) {
    console.error('Test failed:', error);
  } finally {
    await browser.close();
  }
}

quickButtonTest();