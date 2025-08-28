const puppeteer = require('puppeteer');

/**
 * E2E test for Create with AI button spacing
 * Tests fix for issue #94: https://github.com/sethshoultes/memberpress-courses-copilot/issues/94
 */
(async () => {
    const browser = await puppeteer.launch({ 
        headless: false,
        defaultViewport: null
    });
    const page = await browser.newPage();

    console.log('Testing Create with AI button spacing...\n');

    try {
        // Login to WordPress
        console.log('1. Logging into WordPress...');
        await page.goto('http://localhost:10044/wp-admin');
        await page.type('#user_login', 'admin');
        await page.type('#user_pass', 'password');
        await page.click('#wp-submit');
        await page.waitForNavigation();
        console.log('   ✓ Logged in successfully');

        // Navigate to courses listing page
        console.log('\n2. Navigating to courses listing...');
        await page.goto('http://localhost:10044/wp-admin/edit.php?post_type=mpcs-course', {
            waitUntil: 'networkidle2'
        });
        
        // Wait for the page to load
        await new Promise(resolve => setTimeout(resolve, 2000));
        console.log('   ✓ Courses listing loaded');

        // Check Create with AI button spacing
        console.log('\n3. Checking Create with AI button spacing...');
        const buttonSpacing = await page.evaluate(() => {
            const button = document.querySelector('#mpcc-create-with-ai');
            const addNewButton = document.querySelector('.page-title-action');
            
            if (!button) {
                return { error: 'Create with AI button not found' };
            }
            
            const buttonStyles = window.getComputedStyle(button);
            const buttonRect = button.getBoundingClientRect();
            
            // Find the Add New button (first page-title-action)
            let addNewRect = null;
            if (addNewButton && addNewButton.id !== 'mpcc-create-with-ai') {
                addNewRect = addNewButton.getBoundingClientRect();
            }
            
            return {
                found: true,
                marginLeft: buttonStyles.marginLeft,
                marginRight: buttonStyles.marginRight,
                paddingLeft: buttonStyles.paddingLeft,
                paddingRight: buttonStyles.paddingRight,
                buttonLeft: buttonRect.left,
                buttonRight: buttonRect.right,
                addNewRight: addNewRect ? addNewRect.right : null,
                spacing: addNewRect ? (buttonRect.left - addNewRect.right) : null
            };
        });

        if (buttonSpacing.found) {
            console.log('   Button margin-left:', buttonSpacing.marginLeft);
            console.log('   Button margin-right:', buttonSpacing.marginRight);
            if (buttonSpacing.spacing !== null) {
                console.log('   Spacing from Add New button:', buttonSpacing.spacing + 'px');
            }
            console.log('   ✓ Create with AI button spacing verified');
        } else {
            console.log('   ❌ Could not find Create with AI button');
        }

        // Take screenshot
        await page.screenshot({ path: 'tests/e2e/screenshots/create-ai-button-spacing.png' });
        console.log('\n4. Screenshot saved');

        // Test result
        console.log('\n========================================');
        const minSpacing = 8; // Minimum acceptable spacing in pixels
        const hasProperSpacing = buttonSpacing.found && 
                                (buttonSpacing.marginLeft === '10px' || 
                                 (buttonSpacing.spacing !== null && buttonSpacing.spacing >= minSpacing));
        
        if (hasProperSpacing) {
            console.log('TEST RESULT: ✅ PASSED');
            console.log('Create with AI button has proper spacing!');
        } else {
            console.log('TEST RESULT: ❌ FAILED');
            console.log('Button spacing issue not resolved');
        }
        console.log('========================================');

    } catch (error) {
        console.error('\n❌ Test failed with error:', error.message);
        process.exit(1);
    } finally {
        await browser.close();
    }
})();