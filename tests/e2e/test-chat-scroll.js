const puppeteer = require('puppeteer');

/**
 * E2E test for chat interface scroll-to-bottom functionality
 * Tests fix for issue #108: https://github.com/sethshoultes/memberpress-courses-copilot/issues/108
 */
(async () => {
    const browser = await puppeteer.launch({ 
        headless: false,
        defaultViewport: null
    });
    const page = await browser.newPage();

    console.log('Testing chat scroll-to-bottom functionality...\n');

    try {
        // Login to WordPress
        console.log('1. Logging into WordPress...');
        await page.goto('http://localhost:10044/wp-admin');
        await page.type('#user_login', 'admin');
        await page.type('#user_pass', 'password');
        await page.click('#wp-submit');
        await page.waitForNavigation();
        console.log('   ✓ Logged in successfully');

        // Navigate to course editor with existing session
        console.log('\n2. Navigating to course editor...');
        await page.goto('http://localhost:10044/wp-admin/admin.php?page=mpcc-course-editor&session=mpcc_session_1756384040059_1tfpbe3ok', {
            waitUntil: 'networkidle2'
        });
        
        // Wait for chat container to be fully loaded
        await page.waitForSelector('#mpcc-chat-container', { visible: true, timeout: 10000 });
        await page.waitForSelector('#mpcc-chat-messages', { visible: true, timeout: 10000 });
        
        // Wait for any existing messages to load
        await new Promise(resolve => setTimeout(resolve, 3000));
        console.log('   ✓ Course editor loaded');

        // Check if chat is scrolled to bottom
        console.log('\n3. Checking initial scroll position...');
        const isScrolledToBottom = await page.evaluate(() => {
            const container = document.querySelector('#mpcc-chat-messages');
            if (!container) return { error: 'Container not found' };
            
            const scrollTop = container.scrollTop;
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;
            const scrollBottom = scrollTop + clientHeight;
            
            // Allow 5px tolerance for rounding
            const isAtBottom = scrollHeight - scrollBottom < 5;
            
            return {
                isAtBottom,
                scrollTop,
                scrollHeight,
                clientHeight,
                scrollBottom,
                difference: scrollHeight - scrollBottom
            };
        });

        console.log('   Container height:', isScrolledToBottom.clientHeight + 'px');
        console.log('   Total scroll height:', isScrolledToBottom.scrollHeight + 'px');
        console.log('   Current scroll position:', isScrolledToBottom.scrollTop + 'px');
        console.log('   Distance from bottom:', isScrolledToBottom.difference + 'px');
        console.log('   ✓ Is scrolled to bottom:', isScrolledToBottom.isAtBottom ? 'YES' : 'NO');

        if (!isScrolledToBottom.isAtBottom) {
            console.log('   ⚠️  WARNING: Chat should be scrolled to bottom on load!');
        }

        // Test sending a message
        console.log('\n4. Testing scroll after sending message...');
        await page.type('#mpcc-chat-input', 'Test message to check scroll behavior');
        
        // Click the send button
        await page.click('#mpcc-send-message');
        console.log('   ✓ Message sent');
        
        // Wait for response
        await new Promise(resolve => setTimeout(resolve, 3000));

        // Check scroll again after new message
        const afterMessageScroll = await page.evaluate(() => {
            const container = document.querySelector('#mpcc-chat-messages');
            if (!container) return { error: 'Container not found' };
            
            const scrollTop = container.scrollTop;
            const scrollHeight = container.scrollHeight;
            const clientHeight = container.clientHeight;
            const scrollBottom = scrollTop + clientHeight;
            const isAtBottom = scrollHeight - scrollBottom < 5;
            
            return {
                isAtBottom,
                difference: scrollHeight - scrollBottom,
                scrollHeight,
                scrollTop
            };
        });

        console.log('   New scroll height:', afterMessageScroll.scrollHeight + 'px');
        console.log('   New scroll position:', afterMessageScroll.scrollTop + 'px');
        console.log('   Distance from bottom:', afterMessageScroll.difference + 'px');
        console.log('   ✓ Is scrolled to bottom:', afterMessageScroll.isAtBottom ? 'YES' : 'NO');

        if (!afterMessageScroll.isAtBottom) {
            console.log('   ⚠️  WARNING: Chat should auto-scroll after new message!');
        }

        // Take screenshot
        const screenshotPath = 'tests/e2e/screenshots/chat-scroll-test.png';
        await page.screenshot({ path: screenshotPath });
        console.log('\n5. Screenshot saved:', screenshotPath);

        // Overall test result
        console.log('\n========================================');
        console.log('TEST RESULT:', (isScrolledToBottom.isAtBottom && afterMessageScroll.isAtBottom) ? '✅ PASSED' : '❌ FAILED');
        console.log('========================================');
        
        if (!isScrolledToBottom.isAtBottom || !afterMessageScroll.isAtBottom) {
            console.log('\nThe chat interface is not properly scrolling to bottom.');
            console.log('This issue is tracked at: https://github.com/sethshoultes/memberpress-courses-copilot/issues/108');
            process.exit(1);
        }

    } catch (error) {
        console.error('\n❌ Test failed with error:', error.message);
        process.exit(1);
    } finally {
        await browser.close();
    }
})();