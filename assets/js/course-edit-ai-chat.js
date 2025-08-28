/**
 * MemberPress Courses Copilot - Course Edit AI Chat
 * 
 * Handles AI chat functionality on individual course edit pages
 */

(function($) {
    'use strict';

    // Course Edit AI Chat Manager - Make it globally accessible
    window.CourseEditAIChat = {
        courseData: null,
        conversationHistory: [],
        isProcessing: false,
        lastMessageTime: 0,
        messageCache: new Map(),
        
        init: function(courseData) {
            this.courseData = courseData;
            this.bindEvents();
            this.initializeConversation();
        },
        
        bindEvents: function() {
            // Send message on button click
            $(document).off('click.mpcc-course-chat').on('click.mpcc-course-chat', '#mpcc-course-send-message', this.sendMessage.bind(this));
            
            // Send message on Enter key
            $(document).off('keypress.mpcc-course-chat').on('keypress.mpcc-course-chat', '#mpcc-course-chat-input', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            }.bind(this));
            
            // Quick action buttons
            $(document).on('click', '.mpcc-course-quick-action', this.handleQuickAction.bind(this));
        },
        
        initializeConversation: function() {
            // Add initial context message
            const contextMessage = this.buildContextMessage();
            this.addMessage('system', contextMessage, false);
            
            // Add welcome message
            const welcomeMessage = 'Hello! I\'m here to help you improve your course "' + MPCCUtils.escapeHtml(this.courseData.title) + '". ' +
                'I can help you add new content, improve existing lessons, create quizzes, or enhance learning objectives. ' + 
                'What would you like to work on?';
            this.addMessage('assistant', welcomeMessage, false);
            
            // Show quick actions
            this.showQuickActions();
            
            // Announce to screen readers
            if (window.MPCCAccessibility) {
                MPCCAccessibility.announce('AI Chat ready. Welcome message displayed with quick action options.');
            }
        },
        
        buildContextMessage: function() {
            let context = 'Current course information:\n';
            context += '- Title: ' + MPCCUtils.escapeHtml(this.courseData.title) + '\n';
            context += '- Sections: ' + (this.courseData.sections ? this.courseData.sections.length : 0) + '\n';
            
            if (this.courseData.sections && this.courseData.sections.length > 0) {
                let totalLessons = 0;
                this.courseData.sections.forEach(function(section) {
                    if (section.lessons) {
                        totalLessons += section.lessons.length;
                    }
                });
                context += '- Total Lessons: ' + totalLessons + '\n';
            }
            
            context += '- Status: ' + MPCCUtils.escapeHtml(this.courseData.status || 'draft');
            
            return context;
        },
        
        showQuickActions: function() {
            const quickActions = [
                { text: 'Add New Lesson', action: 'add_lesson', description: 'Start creating a new lesson for your course' },
                { text: 'Improve Existing Content', action: 'improve_content', description: 'Get suggestions to enhance your current lessons' },
                { text: 'Create Quiz', action: 'create_quiz', description: 'Generate quiz questions for your course' },
                { text: 'Enhance Learning Objectives', action: 'enhance_objectives', description: 'Improve learning objectives and outcomes' }
            ];
            
            let actionsHtml = '<div class="mpcc-course-quick-actions" role="group" aria-label="Quick actions" style="margin: 15px 0; display: flex; flex-wrap: wrap; gap: 10px;">';
            quickActions.forEach(function(action) {
                actionsHtml += '<button type="button" class="mpcc-course-quick-action button button-secondary" ' +
                    'data-action="' + MPCCUtils.escapeHtml(action.action) + '" ' +
                    'aria-label="' + MPCCUtils.escapeHtml(action.description) + '">' + 
                    MPCCUtils.escapeHtml(action.text) + '</button>';
            });
            actionsHtml += '</div>';
            
            $('#mpcc-course-chat-messages').append(actionsHtml);
            MPCCUtils.ui.scrollToBottom('#mpcc-course-chat-messages');
        },
        
        handleQuickAction: function(e) {
            const $button = $(e.currentTarget);
            const action = $button.data('action');
            const actionText = $button.text();
            
            // Remove quick actions
            $('.mpcc-course-quick-actions').remove();
            
            // Send appropriate message based on action
            let message = '';
            switch(action) {
                case 'add_lesson':
                    message = 'I want to add a new lesson to my course.';
                    break;
                case 'improve_content':
                    message = 'Please help me improve the existing content in my course.';
                    break;
                case 'create_quiz':
                    message = 'I\'d like to create a quiz for my course.';
                    break;
                case 'enhance_objectives':
                    message = 'Help me enhance the learning objectives for my course.';
                    break;
            }
            
            if (message) {
                $('#mpcc-course-chat-input').val(message);
                this.sendMessage();
            }
        },
        
        sendMessage: function() {
            const input = $('#mpcc-course-chat-input');
            const message = input.val().trim();
            
            if (!message || this.isProcessing) return;
            
            // Rate limiting - prevent rapid submissions
            if (this.lastMessageTime && (Date.now() - this.lastMessageTime) < 1000) {
                MPCCUtils.showWarning('Please wait a moment before sending another message');
                return;
            }
            this.lastMessageTime = Date.now();
            
            // Add user message
            this.addMessage('user', message);
            input.val('');
            
            // Announce message sent
            if (window.MPCCAccessibility) {
                MPCCAccessibility.announce('Message sent: ' + message);
                MPCCAccessibility.setBusy('#mpcc-course-chat-messages', true);
            }
            
            // Show typing indicator
            const typingId = 'typing-' + Date.now();
            const typingHtml = '<div class="mpcc-chat-message assistant" id="' + MPCCUtils.escapeHtml(typingId) + '" role="status" aria-live="polite">' +
                '<div class="message-content">' +
                '<span class="typing-indicator" aria-label="AI is typing">' +
                '<span></span><span></span><span></span>' +
                '</span></div></div>';
            
            $('#mpcc-course-chat-messages').append(typingHtml);
            MPCCUtils.ui.scrollToBottom('#mpcc-course-chat-messages');
            
            // Check cache for similar messages
            const cacheKey = this.generateCacheKey(message);
            if (this.messageCache.has(cacheKey)) {
                const cachedResponse = this.messageCache.get(cacheKey);
                $('#' + typingId).remove();
                this.addMessage('assistant', cachedResponse);
                if (window.MPCCAccessibility) {
                    MPCCAccessibility.setBusy('#mpcc-course-chat-messages', false);
                    MPCCAccessibility.announce('AI Assistant has responded (from cache)');
                }
                return;
            }
            
            // Send to AI
            this.isProcessing = true;
            const button = $('#mpcc-course-send-message');
            MPCCUtils.ui.setButtonLoading(button, true, 'Thinking...');
            
            MPCCUtils.ajax.request('mpcc_course_chat_message', {
                message: message,
                course_id: this.courseData.id,
                course_data: JSON.stringify(this.courseData),
                conversation_history: JSON.stringify(this.conversationHistory),
                nonce: mpccCourseChat.nonce
            }, {
                success: (response) => {
                    $('#' + typingId).remove();
                    
                    if (window.MPCCAccessibility) {
                        MPCCAccessibility.setBusy('#mpcc-course-chat-messages', false);
                    }
                    
                    if (response.success) {
                        // Cache the response
                        this.messageCache.set(cacheKey, response.data.message);
                        
                        // Limit cache size to prevent memory issues
                        if (this.messageCache.size > 50) {
                            const firstKey = this.messageCache.keys().next().value;
                            this.messageCache.delete(firstKey);
                        }
                        
                        this.addMessage('assistant', response.data.message);
                        
                        // Announce AI response
                        if (window.MPCCAccessibility) {
                            MPCCAccessibility.announce('AI Assistant has responded');
                        }
                        
                        // Handle any course updates returned by AI
                        if (response.data.course_updates) {
                            this.handleCourseUpdates(response.data.course_updates);
                        }
                    } else {
                        this.showError(response.data || 'An error occurred');
                    }
                },
                error: () => {
                    $('#' + typingId).remove();
                    this.showError('Failed to communicate with the AI. Please try again.');
                },
                complete: () => {
                    this.isProcessing = false;
                    MPCCUtils.ui.setButtonLoading(button, false);
                }
            });
        },
        
        addMessage: function(role, content, addToHistory = true) {
            const messageHtml = this.formatMessage(role, content);
            $('#mpcc-course-chat-messages').append(messageHtml);
            MPCCUtils.ui.scrollToBottom('#mpcc-course-chat-messages');
            
            if (addToHistory) {
                this.conversationHistory.push({ role, content });
            }
        },
        
        formatMessage: function(role, content) {
            const messageClass = role === 'user' ? 'user' : 'assistant';
            const ariaLabel = role === 'user' ? 'Your message' : 'AI Assistant message';
            
            // Convert content to HTML if it contains markdown-like formatting
            const formattedContent = this.formatContent(content);
            
            return '<div class="mpcc-chat-message ' + MPCCUtils.escapeHtml(messageClass) + '" role="article" aria-label="' + ariaLabel + '">' +
                '<div class="message-content">' + formattedContent + '</div>' +
                '</div>';
        },
        
        formatContent: function(content) {
            return MPCCUtils.formatContent(content);
        },
        
        handleCourseUpdates: function(updates) {
            // Show notification about course updates
            this.showNotification('Course updated successfully!', 'success');
            
            // Update local course data
            if (updates.sections) {
                this.courseData.sections = updates.sections;
            }
            
            // Optionally refresh the page or update specific UI elements
            if (updates.require_refresh) {
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
        },
        
        showError: function(message) {
            const errorHtml = '<div class="mpcc-chat-error" role="alert" style="background: #fee; color: #d63638; padding: 10px; ' +
                'margin: 10px 0; border-radius: 4px; border-left: 4px solid #d63638;">' +
                '<strong>Error:</strong> ' + MPCCUtils.escapeHtml(message) + '</div>';
            
            $('#mpcc-course-chat-messages').append(errorHtml);
            MPCCUtils.ui.scrollToBottom('#mpcc-course-chat-messages');
            
            // Announce error to screen readers
            if (window.MPCCAccessibility) {
                MPCCAccessibility.announce('Error: ' + message, 'assertive');
            }
        },
        
        showNotification: function(message, type = 'info') {
            if (type === 'success') {
                MPCCUtils.showSuccess(message);
            } else if (type === 'error') {
                MPCCUtils.showError(message);
            } else {
                MPCCUtils.showNotification(message, type);
            }
        },
        
        scrollToBottom: function() {
            MPCCUtils.ui.scrollToBottom('#mpcc-course-chat-messages');
        }
    };
    
        generateCacheKey: function(message) {
            // Generate a simple cache key based on message content and course context
            const courseContext = this.courseData.id + '-' + (this.courseData.sections ? this.courseData.sections.length : 0);
            return courseContext + '-' + message.toLowerCase().trim().substring(0, 100);
        },
        
        // Cleanup method
        destroy: function() {
            // Remove event handlers
            $(document).off('click.mpcc-course-chat');
            $(document).off('keypress.mpcc-course-chat');
            $(document).off('click', '.mpcc-course-quick-action');
            
            // Clear cache and references
            this.messageCache.clear();
            this.courseData = null;
            this.conversationHistory = null;
        }
    };
    
    // Global function to initialize the chat
    window.initializeCourseAIChat = function(courseData) {
        CourseEditAIChat.init(courseData);
    };
    
    // Cleanup on page unload
    $(window).on('beforeunload.mpcc-course-chat', function() {
        if (window.CourseEditAIChat && window.CourseEditAIChat.destroy) {
            window.CourseEditAIChat.destroy();
        }
    });
    
})(jQuery);