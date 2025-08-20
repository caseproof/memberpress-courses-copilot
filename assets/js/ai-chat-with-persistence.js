/**
 * Enhanced AI Chat Interface with Conversation Persistence
 * 
 * This module provides complete conversation state management including:
 * - Auto-save functionality
 * - Session recovery on page load
 * - Multi-session management
 * - Unsaved changes warnings
 */
jQuery(document).ready(function($) {
    // Configuration
    const AUTO_SAVE_INTERVAL = 30000; // 30 seconds
    const SESSION_STORAGE_KEY = 'mpcc_current_session_id';
    const DEBOUNCE_DELAY = 1000;
    
    // State management
    let currentSessionId = null;
    let autoSaveTimer = null;
    let lastSaveTime = 0;
    let isDirty = false;
    let saveDebounceTimer = null;
    
    // Initialize conversation state
    window.mpccConversationHistory = window.mpccConversationHistory || [];
    window.mpccConversationState = window.mpccConversationState || { 
        current_step: 'initial', 
        collected_data: {} 
    };
    
    /**
     * Initialize chat interface with persistence
     */
    function initializeChat() {
        // Check for existing session
        const storedSessionId = sessionStorage.getItem(SESSION_STORAGE_KEY);
        
        if (storedSessionId) {
            loadConversation(storedSessionId);
        } else {
            createNewConversation();
        }
        
        // Set up auto-save
        startAutoSave();
        
        // Set up beforeunload warning
        $(window).on('beforeunload', function() {
            if (isDirty) {
                saveConversation(true); // Synchronous save
                return 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        // Initialize UI components
        initializeUIComponents();
    }
    
    /**
     * Create new conversation session
     */
    function createNewConversation() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mpcc_create_conversation',
                nonce: $('#mpcc-ajax-nonce').val() || mpccAISettings?.nonce || '',
                context: 'course_creation',
                title: 'Course Creation - ' + new Date().toLocaleString()
            },
            success: function(response) {
                if (response.success) {
                    currentSessionId = response.data.session_id;
                    sessionStorage.setItem(SESSION_STORAGE_KEY, currentSessionId);
                    console.log('Created new conversation session:', currentSessionId);
                    showWelcomeMessage();
                } else {
                    console.error('Failed to create conversation:', response.data);
                    showError('Failed to create conversation session');
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to create conversation session:', error);
                showError('Failed to connect to server');
            }
        });
    }
    
    /**
     * Load existing conversation
     */
    function loadConversation(sessionId) {
        // Show loading indicator
        $('#mpcc-chat-messages').html(`
            <div class="mpcc-loading" style="text-align: center; padding: 20px;">
                <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                <p>Loading conversation...</p>
            </div>
        `);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mpcc_load_conversation',
                nonce: $('#mpcc-ajax-nonce').val() || mpccAISettings?.nonce || '',
                session_id: sessionId
            },
            success: function(response) {
                if (response.success) {
                    currentSessionId = response.data.session_id;
                    window.mpccConversationHistory = response.data.conversation_history || [];
                    window.mpccConversationState = response.data.conversation_state || { 
                        current_step: 'initial', 
                        collected_data: {} 
                    };
                    
                    // Rebuild chat UI
                    rebuildChatInterface();
                    
                    // Update last save time
                    lastSaveTime = Date.now();
                    isDirty = false;
                    updateSaveIndicator('saved');
                    
                    console.log('Loaded conversation session:', currentSessionId);
                } else {
                    // Session not found, create new one
                    console.warn('Session not found, creating new one');
                    sessionStorage.removeItem(SESSION_STORAGE_KEY);
                    createNewConversation();
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load conversation:', error);
                showError('Failed to load conversation');
                createNewConversation();
            }
        });
    }
    
    /**
     * Save conversation to database
     */
    function saveConversation(synchronous = false) {
        if (!currentSessionId) {
            console.warn('No session ID, cannot save');
            return;
        }
        
        if (!isDirty && !synchronous) {
            console.log('No changes to save');
            return;
        }
        
        // Clear any existing debounce timer
        if (saveDebounceTimer) {
            clearTimeout(saveDebounceTimer);
        }
        
        updateSaveIndicator('saving');
        
        const saveData = {
            action: 'mpcc_save_conversation',
            nonce: $('#mpcc-ajax-nonce').val() || mpccAISettings?.nonce || '',
            session_id: currentSessionId,
            conversation_history: window.mpccConversationHistory,
            conversation_state: window.mpccConversationState
        };
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: saveData,
            async: !synchronous,
            success: function(response) {
                if (response.success) {
                    lastSaveTime = Date.now();
                    isDirty = false;
                    updateSaveIndicator('saved');
                    console.log('Conversation saved successfully');
                } else {
                    updateSaveIndicator('error');
                    console.error('Failed to save conversation:', response.data);
                }
            },
            error: function(xhr, status, error) {
                updateSaveIndicator('error');
                console.error('Failed to save conversation:', error);
            }
        });
    }
    
    /**
     * Debounced save function
     */
    function debouncedSave() {
        if (saveDebounceTimer) {
            clearTimeout(saveDebounceTimer);
        }
        
        saveDebounceTimer = setTimeout(function() {
            saveConversation();
        }, DEBOUNCE_DELAY);
    }
    
    /**
     * Start auto-save timer
     */
    function startAutoSave() {
        autoSaveTimer = setInterval(function() {
            if (isDirty) {
                saveConversation();
            }
        }, AUTO_SAVE_INTERVAL);
    }
    
    /**
     * Mark conversation as dirty (needs saving)
     */
    function markDirty() {
        isDirty = true;
        updateSaveIndicator('unsaved');
        debouncedSave();
    }
    
    /**
     * Update save indicator
     */
    function updateSaveIndicator(status) {
        let indicator = $('#mpcc-save-indicator');
        
        if (!indicator.length) {
            indicator = $('<div id="mpcc-save-indicator" style="position: absolute; top: 5px; right: 5px; font-size: 12px; color: #666;"></div>');
            $('#mpcc-ai-chat-interface').css('position', 'relative').append(indicator);
        }
        
        switch (status) {
            case 'saved':
                indicator.html('<span style="color: #46b450;">✓ Saved</span>');
                setTimeout(() => indicator.fadeOut(), 3000);
                break;
            case 'unsaved':
                indicator.html('<span style="color: #f0ad4e;">● Unsaved changes</span>').fadeIn();
                break;
            case 'saving':
                indicator.html('<span style="color: #0073aa;">⟳ Saving...</span>').fadeIn();
                break;
            case 'error':
                indicator.html('<span style="color: #d63638;">✗ Save failed</span>').fadeIn();
                break;
        }
    }
    
    /**
     * Rebuild chat interface from history
     */
    function rebuildChatInterface() {
        $('#mpcc-chat-messages').empty();
        
        // Add messages from history
        window.mpccConversationHistory.forEach(function(message) {
            if (message.role === 'user') {
                addUserMessage(message.content, false);
            } else if (message.role === 'assistant') {
                addAssistantMessage(message.content, false);
            }
        });
        
        // Scroll to bottom
        scrollToBottom();
    }
    
    /**
     * Show welcome message
     */
    function showWelcomeMessage() {
        $('#mpcc-chat-messages').html(`
            <div class="mpcc-welcome-message" style="padding: 20px; text-align: center; color: #666;">
                <div style="font-size: 48px; margin-bottom: 15px;">🤖</div>
                <h3 style="margin: 0 0 10px 0;">AI Course Assistant</h3>
                <p style="margin: 0;">
                    Hi! I'm here to help you create an amazing course. What kind of course would you like to build?
                </p>
            </div>
        `);
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        const errorHtml = `
            <div class="mpcc-error-message" style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px;">
                <strong>Error:</strong> ${message}
            </div>
        `;
        $('#mpcc-chat-messages').append(errorHtml);
    }
    
    /**
     * Add user message to chat
     */
    function addUserMessage(message, save = true) {
        const userHtml = `
            <div class="mpcc-message mpcc-message--user" style="margin-bottom: 15px; text-align: right;">
                <div style="display: inline-block; background: #0073aa; color: white; padding: 10px 15px; border-radius: 18px; max-width: 70%;">
                    ${$('<div>').text(message).html()}
                </div>
                <div class="mpcc-message-time" style="font-size: 11px; color: #999; margin-top: 5px;">
                    ${new Date().toLocaleTimeString()}
                </div>
            </div>
        `;
        $('#mpcc-chat-messages').append(userHtml);
        
        if (save) {
            markDirty();
        }
    }
    
    /**
     * Add assistant message to chat
     */
    function addAssistantMessage(message, save = true) {
        const aiHtml = `
            <div class="mpcc-message mpcc-message--assistant" style="margin-bottom: 15px;">
                <div style="display: inline-block; background: #f0f0f0; padding: 10px 15px; border-radius: 18px; max-width: 70%;">
                    ${message}
                </div>
                <div class="mpcc-message-time" style="font-size: 11px; color: #999; margin-top: 5px;">
                    ${new Date().toLocaleTimeString()}
                </div>
            </div>
        `;
        $('#mpcc-chat-messages').append(aiHtml);
        
        if (save) {
            markDirty();
        }
    }
    
    /**
     * Scroll chat to bottom
     */
    function scrollToBottom() {
        const chatMessages = $('#mpcc-chat-messages');
        if (chatMessages.length) {
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
        }
    }
    
    /**
     * Initialize UI components
     */
    function initializeUIComponents() {
        // Add session management button
        if (!$('#mpcc-session-manager-btn').length) {
            const sessionControls = `
                <div class="mpcc-session-controls" style="margin-bottom: 10px;">
                    <button id="mpcc-session-manager-btn" class="button button-small">
                        <span class="dashicons dashicons-list-view"></span> Previous Conversations
                    </button>
                    <button id="mpcc-new-conversation-btn" class="button button-small">
                        <span class="dashicons dashicons-plus"></span> New Conversation
                    </button>
                </div>
                <div id="mpcc-session-list" style="display: none;"></div>
            `;
            $('#mpcc-chat-input-container').prepend(sessionControls);
            
            // Bind events
            $('#mpcc-session-manager-btn').on('click', function() {
                $('#mpcc-session-list').toggle();
                if ($('#mpcc-session-list').is(':visible')) {
                    showSessionManager();
                }
            });
            
            $('#mpcc-new-conversation-btn').on('click', function() {
                if (isDirty) {
                    if (!confirm('You have unsaved changes. Create a new conversation anyway?')) {
                        return;
                    }
                }
                sessionStorage.removeItem(SESSION_STORAGE_KEY);
                window.mpccConversationHistory = [];
                window.mpccConversationState = { current_step: 'initial', collected_data: {} };
                createNewConversation();
            });
        }
    }
    
    /**
     * Show session management UI
     */
    function showSessionManager() {
        $('#mpcc-session-list').html('<div class="spinner is-active" style="float: none;"></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mpcc_list_conversations',
                nonce: $('#mpcc-ajax-nonce').val() || mpccAISettings?.nonce || ''
            },
            success: function(response) {
                if (response.success && response.data.sessions.length > 0) {
                    let sessionListHtml = '<div style="padding: 15px; background: #f5f5f5; border-radius: 4px;"><h4>Recent Conversations</h4><ul style="list-style: none; padding: 0;">';
                    
                    response.data.sessions.forEach(function(session) {
                        const date = new Date(session.created_at * 1000).toLocaleDateString();
                        const isActive = session.session_id === currentSessionId;
                        
                        sessionListHtml += `
                            <li style="margin-bottom: 10px; padding: 10px; background: white; border-radius: 4px; ${isActive ? 'border: 2px solid #0073aa;' : 'border: 1px solid #ddd;'}">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>${$('<div>').text(session.title).html()}</strong><br>
                                        <small>Created: ${date} | Progress: ${session.progress}%</small>
                                    </div>
                                    ${!isActive ? `<button class="button button-small mpcc-load-session" data-session-id="${session.session_id}">Load</button>` : '<span style="color: #0073aa; font-weight: bold;">Active</span>'}
                                </div>
                            </li>
                        `;
                    });
                    
                    sessionListHtml += '</ul></div>';
                    $('#mpcc-session-list').html(sessionListHtml);
                    
                    // Handle load button clicks
                    $('.mpcc-load-session').on('click', function() {
                        if (isDirty) {
                            if (!confirm('You have unsaved changes. Load this conversation anyway?')) {
                                return;
                            }
                        }
                        const sessionId = $(this).data('session-id');
                        sessionStorage.setItem(SESSION_STORAGE_KEY, sessionId);
                        loadConversation(sessionId);
                        $('#mpcc-session-list').hide();
                    });
                } else {
                    $('#mpcc-session-list').html('<div style="padding: 15px; text-align: center; color: #666;">No previous conversations found</div>');
                }
            },
            error: function() {
                $('#mpcc-session-list').html('<div style="padding: 15px; color: #d63638;">Failed to load conversations</div>');
            }
        });
    }
    
    // Initialize on load
    initializeChat();
    
    // Enable/disable send button based on input
    $('#mpcc-chat-input').on('input keyup', function() {
        const hasText = $(this).val().trim().length > 0;
        $('#mpcc-send-message').prop('disabled', !hasText);
    });
    
    // Handle Enter key
    $('#mpcc-chat-input').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $('#mpcc-send-message').click();
        }
    });
    
    // Handle send button click
    $('#mpcc-send-message').on('click', function() {
        const message = $('#mpcc-chat-input').val().trim();
        if (!message) return;
        
        // Disable button to prevent double-click
        $(this).prop('disabled', true);
        
        // Add user message to conversation history
        window.mpccConversationHistory.push({ 
            role: 'user', 
            content: message,
            timestamp: Date.now()
        });
        
        // Add user message to chat
        addUserMessage(message);
        scrollToBottom();
        
        // Clear input
        $('#mpcc-chat-input').val('').focus();
        
        // Show typing indicator
        const typingHtml = `
            <div id="mpcc-typing" style="margin-bottom: 15px;">
                <div style="display: inline-block; background: #f0f0f0; padding: 10px 15px; border-radius: 18px;">
                    <span class="mpcc-typing-dots">
                        <span>.</span><span>.</span><span>.</span>
                    </span>
                    <span style="margin-left: 5px;">AI is thinking...</span>
                </div>
            </div>
        `;
        $('#mpcc-chat-messages').append(typingHtml);
        scrollToBottom();
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mpcc_ai_chat',
                nonce: $('#mpcc-ajax-nonce').val() || mpccAISettings?.nonce || '',
                message: message,
                context: 'course_creation',
                conversation_history: window.mpccConversationHistory,
                conversation_state: window.mpccConversationState,
                session_id: currentSessionId
            },
            success: function(response) {
                $('#mpcc-typing').remove();
                
                if (response.success) {
                    // Add AI response to conversation history
                    window.mpccConversationHistory.push({ 
                        role: 'assistant', 
                        content: response.data.message,
                        timestamp: Date.now()
                    });
                    
                    // Update conversation state
                    if (response.data.conversation_state) {
                        window.mpccConversationState = response.data.conversation_state;
                    }
                    
                    // Add AI response
                    addAssistantMessage(response.data.message);
                    
                    // Show action buttons if available
                    if (response.data.actions && response.data.actions.length > 0) {
                        const actionsHtml = `
                            <div class="mpcc-actions" style="margin: 15px 0; text-align: center;">
                                ${response.data.actions.map(action => 
                                    `<button class="button ${action.type === 'primary' ? 'button-primary' : ''}" 
                                        onclick="mpccHandleAction('${action.action}')" style="margin: 0 5px;">
                                        ${action.label}
                                    </button>`
                                ).join('')}
                            </div>
                        `;
                        $('#mpcc-chat-messages').append(actionsHtml);
                    }
                    
                    // Update course preview if data available
                    if (response.data.course_data) {
                        window.mpccCurrentCourse = response.data.course_data;
                        if (typeof window.mpccUpdatePreview === 'function') {
                            window.mpccUpdatePreview(response.data.course_data);
                        }
                    }
                    
                    scrollToBottom();
                } else {
                    showError(response.data || 'Something went wrong');
                }
                
                // Re-enable send button
                $('#mpcc-send-message').prop('disabled', false);
            },
            error: function(xhr, status, error) {
                $('#mpcc-typing').remove();
                showError('Failed to connect to AI service. Please try again.');
                
                // Re-enable send button
                $('#mpcc-send-message').prop('disabled', false);
            }
        });
    });
});

// Keep existing global functions for compatibility
window.mpccHandleAction = window.mpccHandleAction || function(action) {
    console.log('Action:', action);
    // Handle action based on type
    switch(action) {
        case 'create_course':
            if (window.mpccCurrentCourse) {
                mpccCreateCourse(window.mpccCurrentCourse);
            }
            break;
        default:
            console.warn('Unknown action:', action);
    }
};

window.mpccUpdatePreview = window.mpccUpdatePreview || function(courseData) {
    console.log('Update preview:', courseData);
    // Implementation depends on preview pane structure
};

// Add CSS for typing animation
if (!document.getElementById('mpcc-typing-animation')) {
    const style = document.createElement('style');
    style.id = 'mpcc-typing-animation';
    style.textContent = `
        .mpcc-typing-dots span {
            animation: mpcc-typing 1.4s infinite;
            animation-fill-mode: both;
        }
        .mpcc-typing-dots span:nth-child(2) {
            animation-delay: .2s;
        }
        .mpcc-typing-dots span:nth-child(3) {
            animation-delay: .4s;
        }
        @keyframes mpcc-typing {
            0%, 60%, 100% {
                opacity: 0.3;
            }
            30% {
                opacity: 1;
            }
        }
    `;
    document.head.appendChild(style);
}