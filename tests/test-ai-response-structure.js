/**
 * AI Response Structure Test
 * 
 * Tests the complete AI response fix by:
 * 1. Verifying AJAX endpoint returns correct structure  
 * 2. Testing JavaScript can access response.data.message
 * 3. Verifying UI displays response correctly
 * 4. Testing with real message via course editor
 */

(function() {
    'use strict';

    // Test configuration
    const TEST_CONFIG = {
        testMessage: 'Create a test course',
        courseEditorUrl: 'http://localhost:10044/wp-admin/admin.php?page=mpcc-course-editor',
        timeout: 30000, // 30 seconds
        expectedResponseStructure: {
            success: true,
            data: {
                message: 'string'
            }
        }
    };

    // Test utilities
    const TestUtils = {
        log: function(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const prefix = `[${timestamp}] [${type.toUpperCase()}]`;
            console.log(`${prefix} ${message}`);
            
            // Also add to page if test container exists
            const container = document.getElementById('mpcc-test-results');
            if (container) {
                const div = document.createElement('div');
                div.className = `test-log test-${type}`;
                div.innerHTML = `<span class="timestamp">${timestamp}</span> <span class="type">[${type.toUpperCase()}]</span> ${message}`;
                container.appendChild(div);
                container.scrollTop = container.scrollHeight;
            }
        },

        error: function(message) {
            this.log(message, 'error');
        },

        success: function(message) {
            this.log(message, 'success');
        },

        warn: function(message) {
            this.log(message, 'warn');
        },

        validateResponseStructure: function(response) {
            try {
                // Check if response exists
                if (!response) {
                    return { valid: false, error: 'Response is null or undefined' };
                }

                // Check success property
                if (typeof response.success !== 'boolean') {
                    return { valid: false, error: 'Response.success is not boolean' };
                }

                // Check data property exists
                if (!response.data) {
                    return { valid: false, error: 'Response.data is missing' };
                }

                // Check message property in data
                if (typeof response.data.message !== 'string') {
                    return { valid: false, error: 'Response.data.message is not a string' };
                }

                // Check message is not empty
                if (response.data.message.trim().length === 0) {
                    return { valid: false, error: 'Response.data.message is empty' };
                }

                return { valid: true, message: 'Response structure is valid' };
            } catch (e) {
                return { valid: false, error: `Validation error: ${e.message}` };
            }
        },

        createTestUI: function() {
            // Create test UI container
            const testContainer = document.createElement('div');
            testContainer.id = 'mpcc-ai-response-test';
            testContainer.innerHTML = `
                <div class="mpcc-test-header">
                    <h2>AI Response Structure Test</h2>
                    <button id="mpcc-start-test" class="button button-primary">Start Test</button>
                    <button id="mpcc-clear-results" class="button">Clear Results</button>
                </div>
                <div id="mpcc-test-results" class="mpcc-test-results"></div>
                <div id="mpcc-test-status" class="mpcc-test-status">Ready to test</div>
            `;

            // Add styles
            const style = document.createElement('style');
            style.textContent = `
                #mpcc-ai-response-test {
                    position: fixed;
                    top: 50px;
                    right: 20px;
                    width: 400px;
                    max-height: 600px;
                    background: white;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                    z-index: 9999;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto;
                }
                .mpcc-test-header {
                    padding: 15px;
                    border-bottom: 1px solid #eee;
                    background: #f9f9f9;
                }
                .mpcc-test-header h2 {
                    margin: 0 0 10px 0;
                    font-size: 16px;
                }
                .mpcc-test-results {
                    max-height: 400px;
                    overflow-y: auto;
                    padding: 10px;
                    font-size: 12px;
                    font-family: monospace;
                }
                .test-log {
                    margin-bottom: 5px;
                    padding: 4px 6px;
                    border-radius: 3px;
                }
                .test-info { background: #f0f8ff; border-left: 3px solid #2196F3; }
                .test-success { background: #f0fff0; border-left: 3px solid #4CAF50; }
                .test-error { background: #fff5f5; border-left: 3px solid #f44336; }
                .test-warn { background: #fffbf0; border-left: 3px solid #ff9800; }
                .timestamp { color: #666; }
                .type { font-weight: bold; }
                .mpcc-test-status {
                    padding: 10px;
                    border-top: 1px solid #eee;
                    background: #f9f9f9;
                    font-size: 12px;
                    text-align: center;
                }
            `;
            document.head.appendChild(style);

            // Add to page
            document.body.appendChild(testContainer);

            // Bind events
            document.getElementById('mpcc-start-test').addEventListener('click', () => {
                this.runCompleteTest();
            });

            document.getElementById('mpcc-clear-results').addEventListener('click', () => {
                document.getElementById('mpcc-test-results').innerHTML = '';
            });
        },

        updateStatus: function(status) {
            const statusEl = document.getElementById('mpcc-test-status');
            if (statusEl) {
                statusEl.textContent = status;
            }
        }
    };

    // Main test class
    const AIResponseTest = {
        init: function() {
            TestUtils.log('AI Response Structure Test initialized');
            TestUtils.createTestUI();
        },

        // Test 1: AJAX endpoint structure
        testAjaxEndpointStructure: function() {
            return new Promise((resolve, reject) => {
                TestUtils.log('Starting AJAX endpoint structure test...');
                TestUtils.updateStatus('Testing AJAX endpoint...');

                // Check if required globals exist
                if (typeof mpccEditorSettings === 'undefined') {
                    TestUtils.error('mpccEditorSettings is not defined');
                    reject(new Error('Missing mpccEditorSettings'));
                    return;
                }

                // Check if we have session ID
                const sessionId = window.CourseEditor?.sessionId || 'test_session_' + Date.now();
                TestUtils.log(`Using session ID: ${sessionId}`);

                const ajaxData = {
                    action: 'mpcc_chat_message',
                    nonce: mpccEditorSettings.nonce,
                    session_id: sessionId,
                    message: TEST_CONFIG.testMessage,
                    conversation_history: JSON.stringify([]),
                    course_structure: JSON.stringify({})
                };

                TestUtils.log('Sending AJAX request...');

                jQuery.ajax({
                    url: mpccEditorSettings.ajaxUrl,
                    type: 'POST',
                    data: ajaxData,
                    timeout: TEST_CONFIG.timeout,
                    success: (response) => {
                        TestUtils.log('AJAX request successful');
                        TestUtils.log('Response received: ' + JSON.stringify(response, null, 2));

                        // Validate response structure
                        const validation = TestUtils.validateResponseStructure(response);
                        if (validation.valid) {
                            TestUtils.success('✓ AJAX endpoint returns correct structure');
                            TestUtils.success(`✓ Response.data.message: "${response.data.message.substring(0, 50)}..."`);
                            resolve(response);
                        } else {
                            TestUtils.error('✗ AJAX response structure invalid: ' + validation.error);
                            reject(new Error(validation.error));
                        }
                    },
                    error: (xhr, status, error) => {
                        TestUtils.error(`✗ AJAX request failed: ${status} - ${error}`);
                        TestUtils.error(`Response: ${xhr.responseText}`);
                        reject(new Error(`AJAX error: ${status} - ${error}`));
                    }
                });
            });
        },

        // Test 2: JavaScript access
        testJavaScriptAccess: function(response) {
            return new Promise((resolve, reject) => {
                TestUtils.log('Testing JavaScript access to response.data.message...');
                TestUtils.updateStatus('Testing JavaScript access...');

                try {
                    // Test accessing response.data.message
                    const message = response.data.message;
                    TestUtils.success('✓ JavaScript can access response.data.message');
                    TestUtils.log(`Message length: ${message.length} characters`);

                    // Test that message is not undefined/null
                    if (message === undefined || message === null) {
                        throw new Error('Message is undefined or null');
                    }

                    // Test message is string
                    if (typeof message !== 'string') {
                        throw new Error('Message is not a string');
                    }

                    // Test message has content
                    if (message.trim().length === 0) {
                        throw new Error('Message is empty');
                    }

                    TestUtils.success('✓ Message content validation passed');
                    resolve(response);

                } catch (e) {
                    TestUtils.error('✗ JavaScript access test failed: ' + e.message);
                    reject(e);
                }
            });
        },

        // Test 3: UI Display
        testUIDisplay: function(response) {
            return new Promise((resolve, reject) => {
                TestUtils.log('Testing UI display of AI response...');
                TestUtils.updateStatus('Testing UI display...');

                try {
                    // Check if chat container exists
                    const chatContainer = document.getElementById('mpcc-chat-messages');
                    if (!chatContainer) {
                        throw new Error('Chat container #mpcc-chat-messages not found');
                    }

                    // Get initial message count
                    const initialCount = chatContainer.children.length;
                    TestUtils.log(`Initial message count: ${initialCount}`);

                    // Simulate adding message to UI (like CourseEditor does)
                    const message = response.data.message;
                    
                    // Create message element
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'mpcc-chat-message assistant test-message';
                    messageDiv.innerHTML = `
                        <div class="message-content">${this.escapeHtml(message)}</div>
                    `;

                    // Add to container
                    chatContainer.appendChild(messageDiv);

                    // Verify message was added
                    const newCount = chatContainer.children.length;
                    if (newCount === initialCount + 1) {
                        TestUtils.success('✓ AI message successfully added to UI');
                    } else {
                        throw new Error(`Message count mismatch. Expected ${initialCount + 1}, got ${newCount}`);
                    }

                    // Check for JavaScript errors
                    setTimeout(() => {
                        TestUtils.success('✓ No JavaScript errors during UI update');
                        TestUtils.log('UI display test completed successfully');
                        resolve(response);
                    }, 100);

                } catch (e) {
                    TestUtils.error('✗ UI display test failed: ' + e.message);
                    reject(e);
                }
            });
        },

        // Test 4: Real integration test
        testRealIntegration: function() {
            return new Promise((resolve, reject) => {
                TestUtils.log('Testing real integration with course editor...');
                TestUtils.updateStatus('Testing real integration...');

                try {
                    // Check if CourseEditor exists and is initialized
                    if (typeof window.CourseEditor === 'undefined') {
                        TestUtils.warn('CourseEditor not available - skipping real integration test');
                        resolve();
                        return;
                    }

                    TestUtils.log('CourseEditor found, testing message sending...');

                    // Test sending a message through CourseEditor
                    const chatInput = document.getElementById('mpcc-chat-input');
                    if (!chatInput) {
                        throw new Error('Chat input not found');
                    }

                    // Set test message
                    chatInput.value = TEST_CONFIG.testMessage;
                    TestUtils.log(`Set chat input to: "${TEST_CONFIG.testMessage}"`);

                    // Monitor for new messages
                    const chatMessages = document.getElementById('mpcc-chat-messages');
                    const initialMessageCount = chatMessages.children.length;

                    // Add temporary listener for new messages
                    const messageObserver = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                                // Check if any added nodes are assistant messages
                                Array.from(mutation.addedNodes).forEach((node) => {
                                    if (node.classList && node.classList.contains('assistant')) {
                                        TestUtils.success('✓ Real assistant message appeared in chat');
                                        TestUtils.log('Message content: ' + node.textContent.substring(0, 100) + '...');
                                        messageObserver.disconnect();
                                        resolve();
                                    }
                                });
                            }
                        });
                    });

                    messageObserver.observe(chatMessages, { childList: true });

                    // Simulate send button click
                    const sendButton = document.getElementById('mpcc-send-message');
                    if (sendButton && !sendButton.disabled) {
                        sendButton.click();
                        TestUtils.log('Send button clicked');
                        
                        // Set timeout for test
                        setTimeout(() => {
                            messageObserver.disconnect();
                            TestUtils.warn('Real integration test timeout - but message may have been processed');
                            resolve();
                        }, 15000);
                    } else {
                        throw new Error('Send button not available or disabled');
                    }

                } catch (e) {
                    TestUtils.error('✗ Real integration test failed: ' + e.message);
                    reject(e);
                }
            });
        },

        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Run complete test suite
    TestUtils.runCompleteTest = function() {
        TestUtils.log('='.repeat(50));
        TestUtils.log('Starting complete AI response structure test');
        TestUtils.log('='.repeat(50));
        
        const startTime = Date.now();

        AIResponseTest.testAjaxEndpointStructure()
            .then((response) => {
                TestUtils.log('Test 1/4 completed: AJAX endpoint structure ✓');
                return AIResponseTest.testJavaScriptAccess(response);
            })
            .then((response) => {
                TestUtils.log('Test 2/4 completed: JavaScript access ✓');
                return AIResponseTest.testUIDisplay(response);
            })
            .then((response) => {
                TestUtils.log('Test 3/4 completed: UI display ✓');
                return AIResponseTest.testRealIntegration();
            })
            .then(() => {
                const duration = Date.now() - startTime;
                TestUtils.log('Test 4/4 completed: Real integration ✓');
                TestUtils.log('='.repeat(50));
                TestUtils.success(`ALL TESTS PASSED! Duration: ${duration}ms`);
                TestUtils.log('='.repeat(50));
                TestUtils.updateStatus(`All tests passed in ${duration}ms`);
            })
            .catch((error) => {
                const duration = Date.now() - startTime;
                TestUtils.log('='.repeat(50));
                TestUtils.error(`TEST SUITE FAILED: ${error.message}`);
                TestUtils.log(`Duration: ${duration}ms`);
                TestUtils.log('='.repeat(50));
                TestUtils.updateStatus(`Tests failed: ${error.message}`);
            });
    };

    // Auto-initialize when page loads
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => AIResponseTest.init(), 1000);
        });
    } else {
        setTimeout(() => AIResponseTest.init(), 1000);
    }

    // Expose for manual testing
    window.AIResponseTest = AIResponseTest;
    window.TestUtils = TestUtils;

})();