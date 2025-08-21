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
    // Prevent multiple initializations
    if (window.mpccChatInitialized) {
        console.log('Chat already initialized, skipping...');
        return;
    }
    window.mpccChatInitialized = true;
    
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
    let isProcessingMessage = false;
    
    // Initialize conversation state only if not already initialized
    if (!window.mpccConversationHistory) {
        window.mpccConversationHistory = [];
    }
    if (!window.mpccConversationState) {
        window.mpccConversationState = { 
            current_step: 'initial', 
            collected_data: {} 
        };
    }
    
    /**
     * Show non-blocking notification
     */
    window.showNotification = function(message, type = 'info') {
        // Remove any existing notifications
        jQuery('.mpcc-notification').remove();
        
        // Create notification element
        const notification = jQuery('<div class="mpcc-notification mpcc-notification-' + type + '">' +
            '<div class="mpcc-notification-content">' +
                '<span class="mpcc-notification-icon dashicons dashicons-' + 
                (type === 'success' ? 'yes-alt' : type === 'error' ? 'dismiss' : 'info-outline') + 
                '"></span>' +
                '<span class="mpcc-notification-text">' + message + '</span>' +
            '</div>' +
        '</div>');
        
        // Add to page
        jQuery('body').append(notification);
        
        // Animate in
        setTimeout(function() {
            notification.addClass('mpcc-notification-show');
        }, 10);
        
        // Auto-hide after 5 seconds (except for info during processing)
        if (type !== 'info' || !message.includes('Creating')) {
            setTimeout(function() {
                notification.removeClass('mpcc-notification-show');
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 5000);
        }
    }
    
    /**
     * Initialize chat interface with persistence
     */
    function initializeChat() {
        // Check for existing session in sessionStorage
        const existingSessionId = sessionStorage.getItem(SESSION_STORAGE_KEY);
        
        if (existingSessionId) {
            console.log('Found existing session:', existingSessionId);
            // Try to load the existing conversation
            loadConversation(existingSessionId);
        } else {
            console.log('No existing session found, creating new conversation');
            // Create a new conversation if no session exists
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
        // Clear any existing course data and preview
        window.mpccCurrentCourse = null;
        const $previewContent = jQuery('#mpcc-preview-content');
        if ($previewContent.length > 0) {
            $previewContent.html('<p style="color: #666; text-align: center; padding: 40px;">Course preview will appear here as you build it...</p>');
        }
        
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
                    
                    // Check if we have course data in the conversation state and rebuild preview
                    console.log('Checking for course data in loaded conversation:', window.mpccConversationState);
                    console.log('Collected data keys:', window.mpccConversationState.collected_data ? Object.keys(window.mpccConversationState.collected_data) : 'No collected data');
                    
                    if (window.mpccConversationState && window.mpccConversationState.collected_data) {
                        // Check for course_structure key first (new format)
                        if (window.mpccConversationState.collected_data.course_structure) {
                            console.log('Found course data (new format), updating preview:', window.mpccConversationState.collected_data.course_structure);
                            window.mpccCurrentCourse = window.mpccConversationState.collected_data.course_structure;
                            if (typeof window.mpccUpdatePreview === 'function') {
                                window.mpccUpdatePreview(window.mpccCurrentCourse);
                            }
                        } 
                        // Fallback: Check if collected_data itself contains course data (old format)
                        else if (window.mpccConversationState.collected_data.title && window.mpccConversationState.collected_data.sections) {
                            console.log('Found course data (old format), updating preview:', window.mpccConversationState.collected_data);
                            window.mpccCurrentCourse = window.mpccConversationState.collected_data;
                            if (typeof window.mpccUpdatePreview === 'function') {
                                window.mpccUpdatePreview(window.mpccCurrentCourse);
                            }
                        } else {
                            console.log('No course data found in conversation state');
                            console.log('Full conversation state:', JSON.stringify(window.mpccConversationState, null, 2));
                        }
                    } else {
                        console.log('No collected_data in conversation state');
                    }
                    
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
        // Only clear and rebuild if we have messages to display
        if (window.mpccConversationHistory && window.mpccConversationHistory.length > 0) {
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
        } else {
            // If no messages, ensure welcome message is visible
            showWelcomeMessage();
        }
    }
    
    /**
     * Show welcome message
     */
    function showWelcomeMessage() {
        $('#mpcc-chat-messages').html(`
            <div class="mpcc-welcome-message" style="text-align: center; padding: 20px; color: #666;">
                <div style="font-size: 32px; margin-bottom: 15px;">🤖</div>
                <h3 style="margin: 0 0 10px 0; color: #1a73e8;">AI Course Assistant</h3>
                <p style="margin: 0; line-height: 1.5;">
                    Hi! I'm here to help you create an amazing course. What kind of course would you like to build today?
                </p>
                
                <div style="margin-top: 20px;">
                    <p style="font-size: 14px; color: #888; margin-bottom: 10px;">Quick starters:</p>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;">
                        <button type="button" class="button button-small mpcc-quick-start" data-message="Help me create a programming course for beginners">
                            Programming Course
                        </button>
                        <button type="button" class="button button-small mpcc-quick-start" data-message="I want to create a business skills course">
                            Business Skills
                        </button>
                        <button type="button" class="button button-small mpcc-quick-start" data-message="Help me design a creative arts course">
                            Creative Arts
                        </button>
                    </div>
                </div>
            </div>
        `);
        
        // Re-bind quick start button handlers
        $('.mpcc-quick-start').off('click').on('click', function(e) {
            e.preventDefault();
            var message = $(this).data('message');
            if (message) {
                $('#mpcc-chat-input').val(message);
                $('#mpcc-send-message').trigger('click');
            }
        });
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
            <div class="mpcc-message mpcc-message-user">
                <div class="mpcc-message-avatar">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="mpcc-message-content">
                    ${$('<div>').text(message).html()}
                </div>
                <div class="mpcc-message-meta">
                    <span class="mpcc-message-time">${new Date().toLocaleTimeString()}</span>
                </div>
            </div>
        `;
        $('#mpcc-chat-messages').append(userHtml);
        
        if (save) {
            markDirty();
        }
    }
    
    /**
     * Format text message to HTML with proper structure
     */
    function formatMessageToHTML(message) {
        // First, handle escaped characters from the server
        let formatted = message
            .replace(/\\'/g, "'")      // Replace escaped single quotes
            .replace(/\\"/g, '"')      // Replace escaped double quotes
            .replace(/\\\\/g, '\\')    // Replace escaped backslashes
            .replace(/\\n/g, '\n')     // Replace escaped newlines with actual newlines
            .replace(/\\r/g, '\r')     // Replace escaped carriage returns
            .replace(/\\t/g, '\t');    // Replace escaped tabs
        
        // Escape HTML to prevent XSS
        formatted = $('<div>').text(formatted).html();
        
        // Convert numbered lists (e.g., "1. Item" or "1) Item")
        formatted = formatted.replace(/^(\d+)[\.\)]\s+(.+)$/gm, '<li value="$1">$2</li>');
        
        // Wrap consecutive list items in <ol> tags
        formatted = formatted.replace(/(<li.*?<\/li>\n?)+/g, function(match) {
            return '<ol>' + match + '</ol>';
        });
        
        // Convert bullet points (-, *, •) to unordered lists
        formatted = formatted.replace(/^\s*[-*•]\s+(.+)$/gm, '<li>$1</li>');
        
        // Handle indented lines that should be list items (2+ spaces at start)
        formatted = formatted.replace(/^\s{2,}(?!<li>)(.+)$/gm, '<li>$1</li>');
        
        // Wrap consecutive unordered list items
        formatted = formatted.replace(/(<li>.*?<\/li>\n?)+/g, function(match) {
            // Only wrap if not already in an ordered list
            if (!match.includes('value=')) {
                return '<ul>' + match + '</ul>';
            }
            return match;
        });
        
        // Convert double line breaks to paragraphs
        const paragraphs = formatted.split(/\n\n+/);
        formatted = paragraphs.map(para => {
            para = para.trim();
            // Don't wrap lists or empty strings in paragraphs
            if (para && !para.startsWith('<ul>') && !para.startsWith('<ol>')) {
                return '<p>' + para + '</p>';
            }
            return para;
        }).join('');
        
        // Convert single line breaks to <br> within paragraphs
        formatted = formatted.replace(/\n/g, '<br>');
        
        // Clean up any double-wrapped lists
        formatted = formatted.replace(/<p>(<[uo]l>.*?<\/[uo]l>)<\/p>/g, '$1');
        
        return formatted;
    }
    
    /**
     * Add assistant message to chat
     */
    function addAssistantMessage(message, save = true) {
        // Remove welcome message if it exists
        $('.mpcc-welcome-message').remove();
        
        // Format the message for better readability
        const formattedMessage = formatMessageToHTML(message);
        
        const aiHtml = `
            <div class="mpcc-message mpcc-message-assistant">
                <div class="mpcc-message-avatar">
                    <span class="dashicons dashicons-format-chat"></span>
                </div>
                <div class="mpcc-message-content">
                    ${formattedMessage}
                </div>
                <div class="mpcc-message-meta">
                    <span class="mpcc-message-time">${new Date().toLocaleTimeString()}</span>
                </div>
            </div>
        `;
        $('#mpcc-chat-messages').append(aiHtml);
        scrollToBottom();
        
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
                <div class="mpcc-session-controls">
                    <button id="mpcc-session-manager-btn" class="button button-small">
                        <span class="dashicons dashicons-list-view"></span> Previous Conversations
                    </button>
                    <button id="mpcc-new-conversation-btn" class="button button-small">
                        <span class="dashicons dashicons-plus"></span> New Conversation
                    </button>
                </div>
                <div id="mpcc-session-list" style="display: none;"></div>
            `;
            $('#mpcc-chat-input-container').after(sessionControls);
            
            // Add class to indicate session controls are present
            $('#mpcc-ai-interface-container').addClass('has-session-controls');
            
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
                
                // Clear the current course data
                window.mpccCurrentCourse = null;
                
                // Clear the preview pane
                const $previewContent = jQuery('#mpcc-preview-content');
                if ($previewContent.length > 0) {
                    $previewContent.html('<p style="color: #666; text-align: center; padding: 40px;">Course preview will appear here as you build it...</p>');
                }
                
                createNewConversation();
            });
        }
    }
    
    /**
     * Show session management UI
     */
    function showSessionManager() {
        console.log('Showing session manager...');
        $('#mpcc-session-list').html('<div class="spinner is-active" style="float: none;"></div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mpcc_list_conversations',
                nonce: $('#mpcc-ajax-nonce').val() || mpccAISettings?.nonce || ''
            },
            success: function(response) {
                console.log('Session list response:', response);
                
                if (response.success && response.data && response.data.sessions && response.data.sessions.length > 0) {
                    let sessionListHtml = '<div style="padding: 15px; background: #f5f5f5; border-radius: 4px;"><h4>Recent Conversations</h4><ul style="list-style: none; padding: 0;">';
                    
                    response.data.sessions.forEach(function(session) {
                        const date = new Date(session.created_at * 1000).toLocaleDateString();
                        const isActive = session.session_id === currentSessionId;
                        
                        sessionListHtml += `
                            <li style="margin-bottom: 10px; padding: 10px; background: white; border-radius: 4px; ${isActive ? 'border: 2px solid #0073aa;' : 'border: 1px solid #ddd;'}">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong>${$('<div>').text(session.title).html()}</strong><br>
                                        <small>Created: ${date} | Progress: ${session.progress || 0}%</small>
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
                    console.log('No sessions found or invalid response structure');
                    $('#mpcc-session-list').html('<div style="padding: 15px; text-align: center; color: #666;">No previous conversations found</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load conversations:', error, xhr.responseText);
                $('#mpcc-session-list').html('<div style="padding: 15px; color: #d63638;">Failed to load conversations. Please check the console for details.</div>');
            }
        });
    }
    
    // Initialize on load
    initializeChat();
    
    // REMOVED: Duplicate handler - handled in line 828 below
    
    // Handle Save Draft button click
    $(document).on('click', '#mpcc-save-draft:not(.mpcc-handled)', function(e) {
        e.preventDefault();
        $(this).addClass('mpcc-handled');
        if (window.mpccCurrentCourse) {
            // For now, just save the conversation
            saveConversation();
            alert('Course draft saved to your conversation history.');
        } else {
            alert('No course data to save.');
        }
    });
    
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
    $('#mpcc-send-message').off('click').on('click', function() {
        const message = $('#mpcc-chat-input').val().trim();
        if (!message) return;
        
        // Prevent duplicate processing
        if (isProcessingMessage) {
            console.log('Already processing a message, ignoring duplicate');
            return;
        }
        isProcessingMessage = true;
        
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
                // Remove typing indicator
                $('#mpcc-typing').remove();
                console.log('Typing indicator removed');
                
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
                    console.log('AI Response received:', response.data.message);
                    
                    // Force add the message directly to ensure it's visible
                    const $container = $('#mpcc-chat-messages');
                    console.log('Container found:', $container.length, 'Container ID:', $container.attr('id'));
                    console.log('Container visible:', $container.is(':visible'), 'Height:', $container.height());
                    
                    // Ensure container is visible
                    if (!$container.is(':visible')) {
                        console.warn('Chat container is hidden! Making it visible...');
                        $container.show();
                    }
                    
                    // Remove welcome message
                    $('.mpcc-welcome-message').remove();
                    
                    // Add the assistant message HTML directly
                    const aiHtml = `
                        <div class="mpcc-message mpcc-message-assistant">
                            <div class="mpcc-message-avatar">
                                <span class="dashicons dashicons-format-chat"></span>
                            </div>
                            <div class="mpcc-message-content">
                                ${response.data.message}
                            </div>
                            <div class="mpcc-message-meta">
                                <span class="mpcc-message-time">${new Date().toLocaleTimeString()}</span>
                            </div>
                        </div>
                    `;
                    $container.append(aiHtml);
                    console.log('Message HTML added directly');
                    
                    // Force scroll
                    $container.scrollTop($container[0].scrollHeight);
                    
                    // Mark as dirty for auto-save
                    markDirty();
                    
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
                        
                        // Store course data in conversation state for persistence
                        if (!window.mpccConversationState.collected_data) {
                            window.mpccConversationState.collected_data = {};
                        }
                        window.mpccConversationState.collected_data.course_structure = response.data.course_data;
                        
                        if (typeof window.mpccUpdatePreview === 'function') {
                            window.mpccUpdatePreview(response.data.course_data);
                        }
                    }
                    
                    scrollToBottom();
                } else {
                    showError(response.data || 'Something went wrong');
                }
                
                // Re-enable send button and reset processing flag
                $('#mpcc-send-message').prop('disabled', false);
                isProcessingMessage = false;
            },
            error: function(xhr, status, error) {
                $('#mpcc-typing').remove();
                showError('Failed to connect to AI service. Please try again.');
                
                // Re-enable send button and reset processing flag
                $('#mpcc-send-message').prop('disabled', false);
                isProcessingMessage = false;
            }
        });
    });
    
    // REMOVED: This handler is managed by courses-integration.js to avoid duplicates
    
    // Handle Save Draft button click
    jQuery(document).on('click', '#mpcc-save-draft', function(e) {
        e.preventDefault();
        console.log('Save draft button clicked');
        // Trigger save conversation
        if (typeof window.saveConversation === 'function') {
            window.saveConversation();
            alert('Draft saved successfully!');
        }
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

window.mpccCreateCourse = window.mpccCreateCourse || function(courseData) {
    console.log('Creating course:', courseData);
    
    // Prevent multiple submissions
    const $createButton = jQuery('#mpcc-create-course');
    if ($createButton.prop('disabled')) {
        console.log('Course creation already in progress');
        return;
    }
    
    // Show loading state
    const originalText = $createButton.text();
    $createButton.prop('disabled', true).text('Creating...');
    
    // Show non-blocking notification - use the function if available, otherwise fallback
    if (typeof showNotification === 'function') {
        showNotification('Creating your course...', 'info');
    }
    
    // Send AJAX request to create the course
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'mpcc_create_course_with_ai',
            nonce: jQuery('#mpcc-ajax-nonce').val() || mpccAISettings?.nonce || '',
            course_data: courseData
        },
        success: function(response) {
            if (response.success) {
                console.log('Course creation successful, response:', response);
                
                // Show success message
                if (typeof window.showNotification === 'function') {
                    window.showNotification('Course created successfully! Redirecting...', 'success');
                }
                
                // Redirect to the edit page with a small delay
                if (response.data && response.data.edit_url) {
                    console.log('Redirecting to:', response.data.edit_url);
                    setTimeout(function() {
                        window.location.href = response.data.edit_url;
                    }, 1500);
                } else {
                    console.error('No edit_url in response:', response);
                    alert('Course created but redirect URL not found. Please check the courses list.');
                }
            } else {
                // Show error message
                if (typeof window.showNotification === 'function') {
                    window.showNotification('Failed to create course: ' + (response.data.message || response.data || 'Unknown error'), 'error');
                } else {
                    alert('Failed to create course: ' + (response.data.message || response.data || 'Unknown error'));
                }
                jQuery('#mpcc-create-course').prop('disabled', false).text(originalText);
            }
        },
        error: function(xhr, status, error) {
            if (typeof window.showNotification === 'function') {
                window.showNotification('Failed to create course: ' + error, 'error');
            } else {
                alert('Failed to create course: ' + error);
            }
            $createButton.prop('disabled', false).text(originalText);
        }
    });
};

window.mpccUpdatePreview = window.mpccUpdatePreview || function(courseData) {
    console.log('Update preview:', courseData);
    
    // Check if we're in the modal with preview pane
    const $previewPane = jQuery('#mpcc-preview-pane');
    const $previewContent = jQuery('#mpcc-preview-content');
    
    if ($previewContent.length === 0) {
        console.warn('Preview content container not found');
        return;
    }
    
    // Make sure preview pane is visible
    if ($previewPane.length > 0) {
        $previewPane.addClass('active').show();
    }
    
    // Clear placeholder if it exists
    $previewContent.find('p[style*="text-align: center"]').remove();
    
    // Build the preview HTML
    let previewHtml = '<div class="mpcc-course-preview">';
    
    // Course title and description
    if (courseData.title) {
        previewHtml += '<h3 class="mpcc-course-title" style="margin: 0 0 10px 0; color: #1a73e8;">' + 
                       jQuery('<div>').text(courseData.title).html() + '</h3>';
    }
    
    if (courseData.description) {
        previewHtml += '<p class="mpcc-course-description" style="color: #666; margin-bottom: 20px;">' + 
                       jQuery('<div>').text(courseData.description).html() + '</p>';
    }
    
    // Course sections and lessons
    if (courseData.sections && courseData.sections.length > 0) {
        previewHtml += '<div class="mpcc-sections" style="margin-top: 20px;">';
        previewHtml += '<h4 style="margin: 0 0 15px 0; color: #333;">Course Structure</h4>';
        
        courseData.sections.forEach(function(section, sectionIndex) {
            previewHtml += '<div class="mpcc-section" style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #e0e0e0; border-radius: 4px;">';
            previewHtml += '<h5 style="margin: 0 0 10px 0; color: #1a73e8; font-size: 16px;">' + 
                          'Section ' + (sectionIndex + 1) + ': ' + jQuery('<div>').text(section.title).html() + '</h5>';
            
            if (section.description) {
                previewHtml += '<p style="color: #666; margin: 0 0 10px 0; font-size: 14px;">' + 
                              jQuery('<div>').text(section.description).html() + '</p>';
            }
            
            if (section.lessons && section.lessons.length > 0) {
                previewHtml += '<ul style="margin: 10px 0 0 0; padding-left: 20px;">';
                section.lessons.forEach(function(lesson, lessonIndex) {
                    previewHtml += '<li style="margin-bottom: 8px; color: #333;">';
                    previewHtml += '<strong>Lesson ' + (sectionIndex + 1) + '.' + (lessonIndex + 1) + ':</strong> ';
                    previewHtml += jQuery('<div>').text(lesson.title).html();
                    if (lesson.duration) {
                        previewHtml += ' <span style="color: #666; font-size: 12px;">(' + lesson.duration + ' min)</span>';
                    }
                    previewHtml += '</li>';
                });
                previewHtml += '</ul>';
            }
            
            previewHtml += '</div>';
        });
        
        previewHtml += '</div>';
    }
    
    // Course settings/metadata
    if (courseData.categories || courseData.tags) {
        previewHtml += '<div class="mpcc-metadata" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">';
        
        if (courseData.categories && courseData.categories.length > 0) {
            previewHtml += '<p style="margin: 0 0 10px 0;"><strong>Categories:</strong> ' + 
                          courseData.categories.map(cat => jQuery('<div>').text(cat).html()).join(', ') + '</p>';
        }
        
        if (courseData.tags && courseData.tags.length > 0) {
            previewHtml += '<p style="margin: 0;"><strong>Tags:</strong> ' + 
                          courseData.tags.map(tag => jQuery('<div>').text(tag).html()).join(', ') + '</p>';
        }
        
        previewHtml += '</div>';
    }
    
    previewHtml += '</div>';
    
    // Update the preview content
    $previewContent.html(previewHtml);
    
    // Ensure the preview pane is visible
    jQuery('#mpcc-preview-pane').show();
    
    // Enable the create course button if we have enough data
    if (courseData.title && courseData.sections && courseData.sections.length > 0) {
        jQuery('#mpcc-create-course').prop('disabled', false);
        jQuery('#mpcc-save-draft').prop('disabled', false);
    }
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