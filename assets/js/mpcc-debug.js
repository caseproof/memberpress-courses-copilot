/**
 * Debug helper for MemberPress Courses Copilot
 * Run these commands in the browser console to diagnose issues
 */
window.mpccDebug = {
    // Check all elements
    checkElements: function() {
        console.log('=== MPCC Element Check ===');
        const elements = {
            'Chat Input': '#mpcc-chat-input',
            'Send Button': '#mpcc-send-message', 
            'Session Manager': '#mpcc-session-manager-btn',
            'New Conversation': '#mpcc-new-conversation-btn',
            'Quick Start': '.mpcc-quick-start',
            'Chat Messages': '.mpcc-chat-messages'
        };
        
        for (let [name, selector] of Object.entries(elements)) {
            const count = jQuery(selector).length;
            const visible = jQuery(selector).is(':visible');
            console.log(`${name} (${selector}): ${count} found, visible: ${visible}`);
        }
    },
    
    // Check event handlers
    checkHandlers: function() {
        console.log('=== MPCC Event Handlers ===');
        
        // Check send button
        const sendBtn = jQuery('#mpcc-send-message')[0];
        if (sendBtn) {
            const events = jQuery._data(sendBtn, 'events');
            console.log('Send button events:', events);
        } else {
            console.log('Send button not found');
        }
        
        // Check document handlers
        const docEvents = jQuery._data(document, 'events');
        if (docEvents && docEvents.click) {
            console.log('Document click handlers:');
            docEvents.click.forEach((handler, i) => {
                console.log(`  ${i}: selector="${handler.selector}", namespace="${handler.namespace}"`);
            });
        }
    },
    
    // Test send functionality
    testSend: function(message = 'Test message') {
        console.log('=== Testing Send ===');
        jQuery('#mpcc-chat-input').val(message);
        console.log('Input value set to:', jQuery('#mpcc-chat-input').val());
        console.log('Triggering send button click...');
        jQuery('#mpcc-send-message').trigger('click');
    },
    
    // Check initialization status
    checkInit: function() {
        console.log('=== MPCC Initialization Status ===');
        console.log('MPCC object:', window.MPCC);
        console.log('Initialized:', window.MPCC?.initialized);
        console.log('Chat initialized:', window.mpccChatInitialized);
        console.log('Functions available:');
        console.log('  - initializeUIComponents:', typeof window.initializeUIComponents);
        console.log('  - createNewConversation:', typeof window.createNewConversation);
        console.log('  - showSessionManager:', typeof window.showSessionManager);
    },
    
    // Run all checks
    runAll: function() {
        this.checkElements();
        this.checkHandlers();
        this.checkInit();
    }
};

// Auto-run on load for debugging
jQuery(document).ready(function() {
    console.log('MPCC Debug loaded. Run mpccDebug.runAll() to check status.');
});