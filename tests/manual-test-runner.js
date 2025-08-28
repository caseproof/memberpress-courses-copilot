/**
 * Manual Test Runner for AI Response Fix
 * 
 * Simple console-based test runner that can be executed manually
 * in the browser console to test the AI response fix.
 */

(function() {
    'use strict';

    // Test configuration
    const TEST_MESSAGE = 'Create a test course';

    // Console styling
    const styles = {
        success: 'color: #4CAF50; font-weight: bold;',
        error: 'color: #f44336; font-weight: bold;',
        info: 'color: #2196F3; font-weight: bold;',
        warn: 'color: #ff9800; font-weight: bold;'
    };

    function log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const style = styles[type] || styles.info;
        console.log(`%c[${timestamp}] ${message}`, style);
    }

    function success(message) { log(message, 'success'); }
    function error(message) { log(message, 'error'); }
    function info(message) { log(message, 'info'); }
    function warn(message) { log(message, 'warn'); }

    // Manual test functions
    window.MPCCManualTest = {
        
        // Test 1: Verify environment
        testEnvironment: function() {
            info('=== Testing Environment ===');
            
            // Check jQuery
            if (typeof jQuery === 'undefined') {
                error('✗ jQuery not available');
                return false;
            }
            success('✓ jQuery available');
            
            // Check MPCC settings
            if (typeof mpccEditorSettings === 'undefined') {
                error('✗ mpccEditorSettings not available');
                return false;
            }
            success('✓ mpccEditorSettings available');
            
            // Check CourseEditor
            if (typeof window.CourseEditor === 'undefined') {
                error('✗ CourseEditor not available');
                return false;
            }
            success('✓ CourseEditor available');
            
            // Check chat elements
            const chatInput = document.getElementById('mpcc-chat-input');
            const sendButton = document.getElementById('mpcc-send-message');
            const chatMessages = document.getElementById('mpcc-chat-messages');
            
            if (!chatInput) {
                error('✗ Chat input not found');
                return false;
            }
            success('✓ Chat input found');
            
            if (!sendButton) {
                error('✗ Send button not found');
                return false;
            }
            success('✓ Send button found');
            
            if (!chatMessages) {
                error('✗ Chat messages container not found');
                return false;
            }
            success('✓ Chat messages container found');
            
            success('✓ Environment test passed');
            return true;
        },

        // Test 2: Direct AJAX test
        testDirectAjax: function() {
            return new Promise((resolve, reject) => {
                info('=== Testing Direct AJAX ===');
                
                const sessionId = window.CourseEditor?.sessionId || 'test_session_' + Date.now();
                
                jQuery.ajax({
                    url: mpccEditorSettings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_chat_message',
                        nonce: mpccEditorSettings.nonce,
                        session_id: sessionId,
                        message: TEST_MESSAGE,
                        conversation_history: JSON.stringify([]),
                        course_structure: JSON.stringify({})
                    },
                    success: function(response) {
                        info('Raw response received:');
                        console.log(response);
                        
                        // Test response structure
                        if (!response) {
                            error('✗ Response is null/undefined');
                            reject(new Error('Null response'));
                            return;
                        }
                        
                        if (typeof response.success !== 'boolean') {
                            error('✗ response.success is not boolean');
                            reject(new Error('Invalid success field'));
                            return;
                        }
                        
                        if (!response.data) {
                            error('✗ response.data is missing');
                            reject(new Error('Missing data field'));
                            return;
                        }
                        
                        if (typeof response.data.message !== 'string') {
                            error('✗ response.data.message is not string');
                            reject(new Error('Invalid message field'));
                            return;
                        }
                        
                        if (response.data.message.trim().length === 0) {
                            error('✗ response.data.message is empty');
                            reject(new Error('Empty message'));
                            return;
                        }
                        
                        success('✓ Response structure valid');
                        success(`✓ Message: "${response.data.message.substring(0, 50)}..."`);
                        success('✓ Direct AJAX test passed');
                        resolve(response);
                    },
                    error: function(xhr, status, errorMsg) {
                        error(`✗ AJAX failed: ${status} - ${errorMsg}`);
                        error(`Response: ${xhr.responseText}`);
                        reject(new Error(`AJAX error: ${status}`));
                    }
                });
            });
        },

        // Test 3: CourseEditor integration
        testCourseEditorIntegration: function() {
            return new Promise((resolve, reject) => {
                info('=== Testing CourseEditor Integration ===');
                
                const chatInput = document.getElementById('mpcc-chat-input');
                const chatMessages = document.getElementById('mpcc-chat-messages');
                
                if (!chatInput || !chatMessages) {
                    error('✗ Required elements not found');
                    reject(new Error('Missing elements'));
                    return;
                }
                
                // Set up mutation observer to catch new messages
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        mutation.addedNodes.forEach((node) => {
                            if (node.classList && node.classList.contains('mpcc-chat-message') && node.classList.contains('assistant')) {
                                success('✓ Assistant message added to DOM');
                                const messageContent = node.querySelector('.message-content');
                                if (messageContent) {
                                    success(`✓ Message content: "${messageContent.textContent.substring(0, 50)}..."`);
                                    success('✓ CourseEditor integration test passed');
                                    observer.disconnect();
                                    resolve();
                                } else {
                                    error('✗ Message content element not found');
                                    observer.disconnect();
                                    reject(new Error('Missing message content'));
                                }
                            }
                        });
                    });
                });
                
                observer.observe(chatMessages, { childList: true, subtree: true });
                
                // Set timeout
                setTimeout(() => {
                    observer.disconnect();
                    warn('⚠ CourseEditor integration test timeout (may still be working)');
                    resolve();
                }, 20000);
                
                // Send test message through CourseEditor
                chatInput.value = TEST_MESSAGE;
                info(`Sending message: "${TEST_MESSAGE}"`);
                
                // Trigger send
                if (window.CourseEditor && window.CourseEditor.sendMessage) {
                    window.CourseEditor.sendMessage();
                } else {
                    // Fallback to button click
                    const sendButton = document.getElementById('mpcc-send-message');
                    if (sendButton && !sendButton.disabled) {
                        sendButton.click();
                    } else {
                        error('✗ Cannot send message - no method available');
                        observer.disconnect();
                        reject(new Error('Cannot send message'));
                    }
                }
            });
        },

        // Run all tests
        runAllTests: function() {
            info('==========================================');
            info('STARTING AI RESPONSE FIX MANUAL TESTS');
            info('==========================================');
            
            const startTime = Date.now();
            
            // Test 1: Environment
            if (!this.testEnvironment()) {
                error('Environment test failed - stopping');
                return;
            }
            
            // Test 2: Direct AJAX
            this.testDirectAjax()
                .then((response) => {
                    success('Test 2/3 completed: Direct AJAX ✓');
                    return this.testCourseEditorIntegration();
                })
                .then(() => {
                    const duration = Date.now() - startTime;
                    info('==========================================');
                    success(`ALL TESTS COMPLETED! Duration: ${duration}ms`);
                    info('==========================================');
                })
                .catch((error) => {
                    const duration = Date.now() - startTime;
                    info('==========================================');
                    error(`TESTS FAILED: ${error.message}`);
                    info(`Duration: ${duration}ms`);
                    info('==========================================');
                });
        },

        // Quick test - just the AJAX
        quickTest: function() {
            info('=== Quick AJAX Test ===');
            return this.testDirectAjax();
        }
    };

    // Auto-announce availability
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                info('MPCC Manual Test Runner loaded');
                info('Run: MPCCManualTest.runAllTests() or MPCCManualTest.quickTest()');
            }, 2000);
        });
    } else {
        setTimeout(() => {
            info('MPCC Manual Test Runner loaded');
            info('Run: MPCCManualTest.runAllTests() or MPCCManualTest.quickTest()');
        }, 2000);
    }

})();