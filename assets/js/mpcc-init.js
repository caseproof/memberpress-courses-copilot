/**
 * MemberPress Courses Copilot - Initialization Module
 * 
 * This module handles all initialization logic in a robust way to prevent
 * breaking when changes are made to individual components.
 */
(function($) {
    'use strict';
    
    // Configuration object - single source of truth
    const MPCC = {
        initialized: false,
        debug: true,
        selectors: {
            chatInput: '#mpcc-chat-input',
            sendButton: '#mpcc-send-message',
            sessionManagerBtn: '#mpcc-session-manager-btn',
            newConversationBtn: '#mpcc-new-conversation-btn',
            sessionList: '#mpcc-session-list',
            chatMessages: '.mpcc-chat-messages',
            chatInterface: '#mpcc-ai-chat-interface',
            quickStartBtns: '.mpcc-quick-start',
            inputContainer: '#mpcc-chat-input-container'
        },
        ajax: {
            url: null,
            nonce: null
        }
    };
    
    // Debug logger
    function log(...args) {
        if (MPCC.debug && console && console.log) {
            console.log('[MPCC]', ...args);
        }
    }
    
    // Get AJAX configuration with fallbacks
    function getAjaxConfig() {
        if (!MPCC.ajax.url) {
            MPCC.ajax.url = window.mpccAISettings?.ajaxUrl || 
                           window.ajaxurl || 
                           '/wp-admin/admin-ajax.php';
        }
        if (!MPCC.ajax.nonce) {
            MPCC.ajax.nonce = window.mpccAISettings?.nonce || 
                             $('#mpcc-ajax-nonce').val() || 
                             '';
        }
        return MPCC.ajax;
    }
    
    // Safe element check
    function elementExists(selector) {
        try {
            return $(selector).length > 0;
        } catch (e) {
            log('Error checking element:', selector, e);
            return false;
        }
    }
    
    // Initialize event handlers with delegation
    function initializeEventHandlers() {
        log('Initializing event handlers...');
        
        // Session management buttons - use delegation for dynamic content
        $(document).off('click.mpcc').on('click.mpcc', MPCC.selectors.sessionManagerBtn, function(e) {
            e.preventDefault();
            log('Session manager clicked');
            $(MPCC.selectors.sessionList).toggle();
            if ($(MPCC.selectors.sessionList).is(':visible') && window.showSessionManager) {
                window.showSessionManager();
            }
        });
        
        $(document).on('click.mpcc', MPCC.selectors.newConversationBtn, function(e) {
            e.preventDefault();
            log('New conversation clicked');
            
            // Check if there are unsaved changes
            if (window.isDirty && !confirm('You have unsaved changes. Create a new conversation anyway?')) {
                return;
            }
            
            // Clear session data
            sessionStorage.removeItem('mpcc_current_session_id');
            window.mpccConversationHistory = [];
            window.mpccConversationState = { current_step: 'initial', collected_data: {} };
            window.mpccCurrentCourse = null;
            
            // Clear the preview pane if it exists
            const $previewContent = $('#mpcc-preview-content');
            if ($previewContent.length > 0) {
                $previewContent.html('<p style="color: #666; text-align: center; padding: 40px;">Course preview will appear here as you build it...</p>');
            }
            
            // Call createNewConversation if available
            if (window.createNewConversation) {
                window.createNewConversation();
            } else {
                // Fallback: just reload the page
                location.reload();
            }
        });
        
        // Quick start buttons
        $(document).on('click.mpcc', MPCC.selectors.quickStartBtns, function(e) {
            e.preventDefault();
            const message = $(this).data('message') || $(this).data('prompt');
            log('Quick start clicked:', message);
            if (message) {
                $(MPCC.selectors.chatInput).val(message);
                $(MPCC.selectors.sendButton).trigger('click');
                
                // Clear input after a short delay
                setTimeout(function() {
                    $(MPCC.selectors.chatInput).val('').focus();
                }, 100);
            }
        });
        
        // Note: Send button and Enter key are handled by simple-ai-chat.js
        
        log('Event handlers initialized');
    }
    
    // Check if all required elements are present
    function checkRequiredElements() {
        const required = [
            MPCC.selectors.chatInput,
            MPCC.selectors.sendButton
        ];
        
        const optional = [
            MPCC.selectors.sessionManagerBtn,
            MPCC.selectors.newConversationBtn
        ];
        
        let allRequired = true;
        required.forEach(selector => {
            if (!elementExists(selector)) {
                log('Missing required element:', selector);
                allRequired = false;
            }
        });
        
        optional.forEach(selector => {
            if (!elementExists(selector)) {
                log('Optional element not found:', selector);
            }
        });
        
        return allRequired;
    }
    
    // Main initialization function
    function initialize() {
        if (MPCC.initialized) {
            log('Already initialized, skipping...');
            return;
        }
        
        log('Starting initialization...');
        
        // Set up AJAX config
        getAjaxConfig();
        log('AJAX config:', MPCC.ajax);
        
        // Check if interface is loaded
        if (!checkRequiredElements()) {
            log('Required elements not found, retrying in 500ms...');
            setTimeout(initialize, 500);
            return;
        }
        
        // Initialize event handlers
        initializeEventHandlers();
        
        // Mark as initialized
        MPCC.initialized = true;
        log('Initialization complete');
        
        // Trigger custom event
        $(document).trigger('mpcc:initialized', [MPCC]);
    }
    
    // Start initialization when DOM is ready
    $(document).ready(function() {
        log('Document ready, starting initialization process...');
        
        // Try to initialize immediately
        initialize();
        
        // Also listen for custom events that might indicate content has loaded
        $(document).on('mpcc:interface-loaded', initialize);
        
        // Fallback: try again after a delay
        setTimeout(function() {
            if (!MPCC.initialized) {
                log('Fallback initialization attempt...');
                initialize();
            }
        }, 2000);
    });
    
    // Expose for debugging
    window.MPCC = MPCC;
    
})(jQuery);