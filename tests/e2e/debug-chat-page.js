const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({ 
        headless: false,
        defaultViewport: null
    });
    const page = await browser.newPage();

    try {
        // Login
        await page.goto('http://localhost:10044/wp-admin');
        await page.type('#user_login', 'admin');
        await page.type('#user_pass', 'password');
        await page.click('#wp-submit');
        await page.waitForNavigation();
        console.log('✓ Logged in');

        // Go to course editor
        await page.goto('http://localhost:10044/wp-admin/admin.php?page=mpcc-course-editor', {
            waitUntil: 'networkidle2'
        });
        
        console.log('✓ Navigated to course editor');
        
        // Wait a bit for page to load
        await new Promise(resolve => setTimeout(resolve, 3000));

        // Get all relevant selectors
        const selectors = await page.evaluate(() => {
            const results = {};
            
            // Check for various chat-related elements
            const possibleSelectors = [
                '#mpcc-chat-interface',
                '#mpcc-chat-messages',
                '#mpcc-chat-input',
                '#mpcc-send-message',
                '#mpcc-course-send-message',
                '.mpcc-chat-container',
                '.mpcc-chat-messages',
                '#mpcc-course-chat-messages',
                '#mpcc-course-chat-input',
                '#mpcc-course-chat-interface'
            ];
            
            possibleSelectors.forEach(selector => {
                const element = document.querySelector(selector);
                results[selector] = element ? 'EXISTS' : 'NOT FOUND';
            });
            
            // Also get any IDs that contain 'chat'
            const allIds = Array.from(document.querySelectorAll('[id*="chat"]')).map(el => '#' + el.id);
            results['IDs containing "chat"'] = allIds;
            
            return results;
        });

        console.log('\nFound selectors:');
        console.log(JSON.stringify(selectors, null, 2));

        // Take screenshot
        await page.screenshot({ path: 'tests/e2e/screenshots/debug-page.png' });
        console.log('\nScreenshot saved to tests/e2e/screenshots/debug-page.png');

    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        await browser.close();
    }
})();