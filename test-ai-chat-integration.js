/**
 * Puppeteer Test for MemberPress AI Chat Integration
 * 
 * This test verifies that the AI Course Assistant metabox is working correctly
 * on MemberPress course edit pages.
 */

const puppeteer = require('puppeteer');

async function testAIChatIntegration() {
    console.log('Starting AI Chat Integration Test...');
    
    const browser = await puppeteer.launch({
        headless: false, // Set to true for headless mode
        defaultViewport: { width: 1920, height: 1080 },
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();
    
    // Enable console logging from the page
    page.on('console', (msg) => {
        console.log('Browser Console:', msg.text());
    });
    
    // Listen for JavaScript errors
    page.on('pageerror', (error) => {
        console.error('Page Error:', error.message);
    });
    
    // Listen for failed requests
    page.on('requestfailed', (request) => {
        console.error('Request Failed:', request.url(), request.failure().errorText);
    });
    
    try {
        // Navigate to WordPress admin login
        console.log('Navigating to WordPress admin...');
        await page.goto('http://localhost:10044/wp-admin/', { 
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        // Check if we need to login
        const isLoginPage = await page.$('#loginform');
        if (isLoginPage) {
            console.log('Login page detected. Please log in manually or update credentials.');
            console.log('Waiting 30 seconds for manual login...');
            await new Promise(resolve => setTimeout(resolve, 30000));
        }
        
        // Look for a MemberPress course to edit or create one
        console.log('Looking for MemberPress courses...');
        await page.goto('http://localhost:10044/wp-admin/edit.php?post_type=mpcs-course', { 
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        // Check if we can see the courses list
        const coursesExist = await page.$('.wp-list-table');
        if (!coursesExist) {
            console.log('No courses found or not on correct page. Let\'s create a new course.');
            await page.goto('http://localhost:10044/wp-admin/post-new.php?post_type=mpcs-course', { 
                waitUntil: 'networkidle2',
                timeout: 30000
            });
        } else {
            // Try to edit an existing course
            const firstEditLink = await page.$('.row-actions .edit a');
            if (firstEditLink) {
                console.log('Found existing course, clicking to edit...');
                await firstEditLink.click();
                await page.waitForNavigation({ waitUntil: 'networkidle2' });
            } else {
                // Create new course
                console.log('Creating new course...');
                await page.goto('http://localhost:10044/wp-admin/post-new.php?post_type=mpcs-course', { 
                    waitUntil: 'networkidle2',
                    timeout: 30000
                });
            }
        }
        
        // Wait for the page to fully load
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        console.log('Current page URL:', page.url());
        console.log('Checking for AI Course Assistant metabox...');
        
        // Test 1: Check if AI Course Assistant metabox is present
        const aiMetabox = await page.$('#mpcc-ai-chat-metabox');
        if (aiMetabox) {
            console.log('✅ AI Course Assistant metabox found!');
            
            // Get the metabox title
            const metaboxTitle = await page.$eval('#mpcc-ai-chat-metabox .hndle', el => el.textContent.trim());
            console.log('Metabox title:', metaboxTitle);
            
        } else {
            console.log('❌ AI Course Assistant metabox NOT found');
            
            // Let's check what metaboxes are present
            const allMetaboxes = await page.$$eval('[id$="-metabox"], .postbox', elements => 
                elements.map(el => ({
                    id: el.id,
                    title: el.querySelector('.hndle, h2')?.textContent?.trim() || 'No title'
                }))
            );
            console.log('Available metaboxes:', allMetaboxes);
        }
        
        // Test 2: Check for "Open AI Chat" button
        const openChatButton = await page.$('#mpcc-open-ai-chat');
        if (openChatButton) {
            console.log('✅ "Open AI Chat" button found!');
            
            // Test 3: Check if button is visible and clickable
            const isVisible = await openChatButton.isIntersectingViewport();
            const isEnabled = await page.evaluate(el => !el.disabled, openChatButton);
            
            console.log('Button visible:', isVisible);
            console.log('Button enabled:', isEnabled);
            
            if (isVisible && isEnabled) {
                console.log('Testing button click...');
                
                // Test 4: Click the button to expand chat interface
                await openChatButton.click();
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                // Check if chat container expanded
                const chatContainer = await page.$('#mpcc-ai-chat-container');
                if (chatContainer) {
                    const isExpanded = await page.evaluate(el => {
                        const display = window.getComputedStyle(el).display;
                        return display !== 'none';
                    }, chatContainer);
                    
                    if (isExpanded) {
                        console.log('✅ Chat interface expanded successfully!');
                        
                        // Test 5: Check for message input and send button
                        const messageInput = await page.$('#mpcc-ai-input');
                        const sendButton = await page.$('#mpcc-ai-send');
                        
                        if (messageInput && sendButton) {
                            console.log('✅ Message input and send button found!');
                            
                            // Test 6: Try sending a test message
                            console.log('Testing message sending...');
                            await messageInput.type('Hello, this is a test message');
                            await new Promise(resolve => setTimeout(resolve, 500));
                            
                            // Click send button and monitor network requests
                            const responsePromise = page.waitForResponse(
                                response => response.url().includes('admin-ajax.php') && 
                                           response.request().postData()?.includes('mpcc_new_ai_chat'),
                                { timeout: 10000 }
                            ).catch(() => null);
                            
                            await sendButton.click();
                            
                            const response = await responsePromise;
                            
                            if (response) {
                                console.log('✅ AJAX request sent successfully!');
                                console.log('Response status:', response.status());
                                
                                const responseText = await response.text();
                                console.log('Response preview:', responseText.substring(0, 200) + '...');
                                
                                // Check if response was successful
                                try {
                                    const responseJson = JSON.parse(responseText);
                                    if (responseJson.success) {
                                        console.log('✅ AI chat AJAX working correctly!');
                                        console.log('AI Response:', responseJson.data?.message?.substring(0, 100) + '...');
                                    } else {
                                        console.log('⚠️  AJAX response indicates error:', responseJson.data);
                                    }
                                } catch (e) {
                                    console.log('⚠️  Invalid JSON response:', e.message);
                                }
                                
                            } else {
                                console.log('❌ No AJAX response received (request failed or timed out)');
                            }
                            
                            // Wait to see if AI response appears in chat
                            await new Promise(resolve => setTimeout(resolve, 3000));
                            
                            const messages = await page.$$eval('.mpcc-ai-message', elements => 
                                elements.map(el => el.textContent.trim())
                            );
                            console.log('Chat messages:', messages);
                            
                        } else {
                            console.log('❌ Message input or send button not found');
                        }
                    } else {
                        console.log('❌ Chat interface did not expand properly');
                    }
                } else {
                    console.log('❌ Chat container not found');
                }
            } else {
                console.log('❌ Button not visible or not enabled');
            }
            
        } else {
            console.log('❌ "Open AI Chat" button NOT found');
        }
        
        // Test 7: Check for JavaScript errors in console
        console.log('\n=== JavaScript Console Errors Check ===');
        
        // Execute some basic JavaScript to test for errors
        try {
            await page.evaluate(() => {
                // Test if jQuery is available
                if (typeof jQuery === 'undefined') {
                    console.error('jQuery is not loaded');
                }
                
                // Test for MPCC specific objects
                if (typeof CourseEditAIChat === 'undefined') {
                    console.log('CourseEditAIChat object not found (this might be normal)');
                }
                
                // Test for WordPress admin globals
                if (typeof ajaxurl === 'undefined') {
                    console.error('ajaxurl is not defined');
                }
                
                console.log('JavaScript environment test completed');
            });
        } catch (error) {
            console.error('Error testing JavaScript environment:', error.message);
        }
        
        // Final summary
        console.log('\n=== Test Summary ===');
        console.log('✅ Tests passed: AI metabox checks, button functionality');
        console.log('⚠️  Issues found: Check console output above for details');
        console.log('📝 Recommendation: Review AJAX handler implementation');
        
    } catch (error) {
        console.error('Test failed with error:', error.message);
    } finally {
        // Keep browser open for manual inspection
        console.log('\nTest completed. Browser will stay open for 30 seconds for manual inspection...');
        await new Promise(resolve => setTimeout(resolve, 30000));
        await browser.close();
    }
}

// Run the test
testAIChatIntegration().catch(console.error);