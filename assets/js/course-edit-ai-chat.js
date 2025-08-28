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
                { text: 'Add New Lesson', action: 'add_lesson' },
                { text: 'Improve Existing Content', action: 'improve_content' },
                { text: 'Create Quiz', action: 'create_quiz' },
                { text: 'Enhance Learning Objectives', action: 'enhance_objectives' }
            ];
            
            let actionsHtml = '<div class="mpcc-course-quick-actions" style="margin: 15px 0; display: flex; flex-wrap: wrap; gap: 10px;">';
            quickActions.forEach(function(action) {
                actionsHtml += '<button type="button" class="mpcc-course-quick-action button button-secondary" data-action="' + 
                    MPCCUtils.escapeHtml(action.action) + '">' + MPCCUtils.escapeHtml(action.text) + '</button>';
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
            
            // Add user message
            this.addMessage('user', message);
            input.val('');
            
            // Show typing indicator
            const typingId = 'typing-' + Date.now();
            const typingHtml = '<div class="mpcc-chat-message assistant" id="' + MPCCUtils.escapeHtml(typingId) + '">' +
                '<div class="message-content">' +
                '<span class="typing-indicator">' +
                '<span></span><span></span><span></span>' +
                '</span></div></div>';
            
            $('#mpcc-course-chat-messages').append(typingHtml);
            MPCCUtils.ui.scrollToBottom('#mpcc-course-chat-messages');
            
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
                    
                    if (response.success) {
                        this.addMessage('assistant', response.data.message);
                        
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
            
            // Convert content to HTML if it contains markdown-like formatting
            const formattedContent = this.formatContent(content);
            
            return '<div class="mpcc-chat-message ' + MPCCUtils.escapeHtml(messageClass) + '">' +
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
            const errorHtml = '<div class="mpcc-chat-error" style="background: #fee; color: #d63638; padding: 10px; ' +
                'margin: 10px 0; border-radius: 4px; border-left: 4px solid #d63638;">' +
                '<strong>Error:</strong> ' + MPCCUtils.escapeHtml(message) + '</div>';
            
            $('#mpcc-course-chat-messages').append(errorHtml);
            MPCCUtils.ui.scrollToBottom('#mpcc-course-chat-messages');
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
    
    // Global function to initialize the chat
    window.initializeCourseAIChat = function(courseData) {
        CourseEditAIChat.init(courseData);
    };
    
})(jQuery);