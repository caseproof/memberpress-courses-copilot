/**
 * MemberPress Courses Copilot - Course Editor Page JavaScript
 */

(function($) {
    'use strict';

    // Course Editor Manager
    const CourseEditor = {
        sessionId: '',
        currentLessonId: null,
        courseStructure: {},
        conversationHistory: [],
        
        init: function() {
            this.sessionId = mpccEditorSettings.sessionId;
            this.bindEvents();
            this.initializeChat();
            this.loadExistingSession();
        },
        
        bindEvents: function() {
            // Chat events
            $('#mpcc-send-message').on('click', this.sendMessage.bind(this));
            $('#mpcc-chat-input').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            }.bind(this));
            
            // Quick starters
            $('#mpcc-new-session').on('click', this.newSession.bind(this));
            $('#mpcc-session-history').on('click', this.showSessionHistory.bind(this));
            
            // Previous conversations button in header
            $('#mpcc-previous-conversations').on('click', this.toggleSessionDropdown.bind(this));
            
            // Quick starter suggestion buttons
            $(document).on('click', '.mpcc-quick-starter-btn', this.handleQuickStarter.bind(this));
            
            // Close modal when clicking outside
            $(document).on('click', '.mpcc-sessions-modal-overlay', function(e) {
                if ($(e.target).hasClass('mpcc-sessions-modal-overlay')) {
                    mpccEditor.closeSessionModal();
                }
            });
            
            // Close modal with ESC key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('.mpcc-sessions-modal-overlay').hasClass('active')) {
                    mpccEditor.closeSessionModal();
                }
            });
            
            // Course actions
            $('#mpcc-preview-course').on('click', this.previewCourse.bind(this));
            $('#mpcc-create-course').on('click', this.createCourse.bind(this));
            
            // Lesson editor events - use event delegation for dynamic content
            $(document).on('click', '.mpcc-lesson-item', this.handleLessonClick.bind(this));
            $('#mpcc-generate-lesson-content').on('click', this.generateLessonContent.bind(this));
            $('#mpcc-save-lesson').on('click', this.saveLesson.bind(this));
            $('#mpcc-cancel-lesson, #mpcc-close-lesson').on('click', this.closeLessonEditor.bind(this));
            
            // Auto-save on textarea change
            let saveTimeout;
            $('#mpcc-lesson-textarea').on('input', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(this.autoSaveLesson.bind(this), 2000);
            }.bind(this));
        },
        
        initializeChat: function() {
            this.addMessage('assistant', 'Welcome to the AI Course Creator! I\'m here to help you build amazing courses. What kind of course would you like to create today?');
        },
        
        loadExistingSession: function() {
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_load_session',
                    nonce: mpccEditorSettings.nonce,
                    session_id: this.sessionId
                },
                success: (response) => {
                    console.log('Session loaded:', response);
                    if (response.success && response.data) {
                        // Restore conversation history
                        if (response.data.conversation_history) {
                            this.conversationHistory = response.data.conversation_history;
                            this.rebuildChatHistory();
                        }
                        
                        // Restore course structure - check multiple possible locations
                        if (response.data.course_structure) {
                            console.log('Found course structure at root level');
                            this.courseStructure = response.data.course_structure;
                            this.renderCourseStructure();
                        } else if (response.data.conversation_state?.course_structure) {
                            console.log('Found course structure in conversation_state.course_structure');
                            this.courseStructure = response.data.conversation_state.course_structure;
                            this.renderCourseStructure();
                        } else if (response.data.conversation_state?.course_data) {
                            console.log('Found course structure in conversation_state.course_data');
                            this.courseStructure = response.data.conversation_state.course_data;
                            this.renderCourseStructure();
                        } else {
                            console.log('No course structure found in session data');
                        }
                    }
                },
                error: (xhr) => {
                    console.log('No existing session found, initializing new session');
                    // Initialize empty session data
                    this.conversationHistory = [];
                    this.courseStructure = {};
                    // Save initial empty session
                    this.saveConversation();
                }
            });
        },
        
        rebuildChatHistory: function() {
            $('#mpcc-chat-messages').empty();
            this.conversationHistory.forEach(msg => {
                this.addMessage(msg.role, msg.content, false);
            });
        },
        
        sendMessage: function() {
            const input = $('#mpcc-chat-input');
            const message = input.val().trim();
            
            if (!message) return;
            
            // Add user message
            this.addMessage('user', message);
            input.val('').focus();
            
            // Send to AI
            this.sendToAI(message);
        },
        
        addMessage: function(role, content, addToHistory = true) {
            const messagesContainer = $('#mpcc-chat-messages');
            const messageHtml = `
                <div class="mpcc-chat-message ${role}">
                    <div class="message-content">${this.escapeHtml(content)}</div>
                </div>
            `;
            
            messagesContainer.append(messageHtml);
            messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
            
            // Add to conversation history
            if (addToHistory) {
                this.conversationHistory.push({ role, content });
            }
            
            // Hide quick starters after first message
            if (this.conversationHistory.length > 1) {
                $('#mpcc-quick-starter-suggestions').addClass('hidden');
            }
        },
        
        handleQuickStarter: function(e) {
            const button = $(e.currentTarget);
            const prompt = button.data('prompt');
            
            if (prompt) {
                $('#mpcc-chat-input').val(prompt);
                this.sendMessage();
            }
        },
        
        sendToAI: function(message) {
            const button = $('#mpcc-send-message');
            button.prop('disabled', true).text('Thinking...');
            
            // Show typing indicator
            const typingId = 'typing-' + Date.now();
            const typingHtml = `
                <div class="mpcc-chat-message assistant" id="${typingId}">
                    <div class="message-content">
                        <span class="typing-indicator">
                            <span></span><span></span><span></span>
                        </span>
                    </div>
                </div>
            `;
            $('#mpcc-chat-messages').append(typingHtml);
            this.scrollToBottom();
            
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_chat_message',
                    nonce: mpccEditorSettings.nonce,
                    session_id: this.sessionId,
                    message: message,
                    conversation_history: JSON.stringify(this.conversationHistory),
                    course_structure: JSON.stringify(this.courseStructure)
                },
                success: (response) => {
                    $('#' + typingId).remove();
                    
                    if (response.success) {
                        this.addMessage('assistant', response.data.message);
                        
                        if (response.data.course_structure) {
                            // Handle course structure that might be in JSON string format
                            let courseData = response.data.course_structure;
                            console.log('Received course structure:', courseData);
                            console.log('Type of course structure:', typeof courseData);
                            
                            if (typeof courseData === 'string') {
                                try {
                                    // Extract JSON from markdown code block if present
                                    const jsonMatch = courseData.match(/```json\s*([\s\S]*?)```/);
                                    if (jsonMatch) {
                                        courseData = JSON.parse(jsonMatch[1]);
                                    } else {
                                        courseData = JSON.parse(courseData);
                                    }
                                } catch (e) {
                                    console.error('Failed to parse course structure:', e);
                                    return;
                                }
                            }
                            
                            console.log('Parsed course structure:', courseData);
                            this.courseStructure = courseData;
                            this.renderCourseStructure();
                            $('#mpcc-create-course').prop('disabled', false);
                            
                            // Update session title with course name
                            if (courseData.title) {
                                this.updateSessionTitle('Course: ' + courseData.title);
                            }
                        }
                        
                        // Auto-save conversation
                        this.saveConversation();
                    } else {
                        this.showError(response.data || 'An error occurred');
                    }
                },
                error: () => {
                    $('#' + typingId).remove();
                    this.showError('Failed to communicate with the AI. Please try again.');
                },
                complete: () => {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-arrow-right-alt"></span> Send');
                }
            });
        },
        
        renderCourseStructure: function() {
            const container = $('#mpcc-course-structure');
            container.empty();
            
            if (!this.courseStructure.title) {
                container.html($('.mpcc-empty-state').first().clone());
                return;
            }
            
            // Course header
            const headerHtml = `
                <div class="mpcc-course-header">
                    <h2>${this.escapeHtml(this.courseStructure.title)}</h2>
                    <p>${this.escapeHtml(this.courseStructure.description || '')}</p>
                </div>
            `;
            container.append(headerHtml);
            
            // Sections and lessons
            if (this.courseStructure.sections) {
                this.courseStructure.sections.forEach((section, sectionIndex) => {
                    const sectionHtml = this.renderSection(section, sectionIndex);
                    container.append(sectionHtml);
                });
            }
        },
        
        renderSection: function(section, sectionIndex) {
            const sectionId = `section-${sectionIndex}`;
            const lessonsHtml = section.lessons.map((lesson, lessonIndex) => 
                this.renderLesson(lesson, sectionIndex, lessonIndex)
            ).join('');
            
            return `
                <div class="mpcc-section" id="${sectionId}" data-section-index="${sectionIndex}">
                    <div class="mpcc-section-header">
                        <h3 class="mpcc-section-title">${this.escapeHtml(section.title)}</h3>
                        <div class="mpcc-section-actions">
                            <button type="button" class="button-link" title="Edit section">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button type="button" class="button-link" title="Delete section">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <div class="mpcc-lessons">
                        ${lessonsHtml}
                    </div>
                </div>
            `;
        },
        
        renderLesson: function(lesson, sectionIndex, lessonIndex) {
            const lessonId = `${sectionIndex}-${lessonIndex}`;
            const hasDraft = lesson.draft_content ? 'has-draft' : '';
            
            return `
                <div class="mpcc-lesson-item ${hasDraft}" 
                     data-lesson-id="${lessonId}"
                     data-section-index="${sectionIndex}"
                     data-lesson-index="${lessonIndex}">
                    <div class="mpcc-lesson-info">
                        <div class="mpcc-lesson-title">${this.escapeHtml(lesson.title)}</div>
                        <div class="mpcc-lesson-meta">${lesson.duration || 'Duration not set'}</div>
                    </div>
                    <button type="button" class="button-link">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                </div>
            `;
        },
        
        handleLessonClick: function(e) {
            e.preventDefault();
            const $target = $(e.currentTarget);
            const lessonId = $target.data('lesson-id');
            
            if (lessonId) {
                this.editLesson(lessonId);
            }
        },
        
        editLesson: function(lessonId) {
            const [sectionIndex, lessonIndex] = lessonId.split('-').map(Number);
            const lesson = this.courseStructure.sections[sectionIndex].lessons[lessonIndex];
            
            this.currentLessonId = lessonId;
            
            // Update UI
            $('.mpcc-lesson-item').removeClass('editing');
            $(`.mpcc-lesson-item[data-lesson-id="${lessonId}"]`).addClass('editing');
            
            // Show editor
            $('#mpcc-lesson-title').text(lesson.title);
            $('#mpcc-lesson-textarea').val(lesson.draft_content || lesson.content || '');
            $('#mpcc-lesson-editor').fadeIn();
            
            // Update save indicator
            $('.mpcc-save-indicator').text('');
            
            // Load saved content if available
            this.loadLessonContent(lessonId);
        },
        
        loadLessonContent: function(lessonId) {
            const [sectionIndex, lessonIndex] = lessonId.split('-').map(Number);
            const lesson = this.courseStructure.sections[sectionIndex].lessons[lessonIndex];
            
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_load_lesson_content',
                    nonce: mpccEditorSettings.nonce,
                    session_id: this.sessionId,
                    lesson_id: lessonId,
                    lesson_title: lesson.title
                },
                success: (response) => {
                    if (response.success && response.data.content) {
                        $('#mpcc-lesson-textarea').val(response.data.content);
                    }
                }
            });
        },
        
        generateLessonContent: function() {
            if (!this.currentLessonId) return;
            
            const [sectionIndex, lessonIndex] = this.currentLessonId.split('-').map(Number);
            const lesson = this.courseStructure.sections[sectionIndex].lessons[lessonIndex];
            const button = $('#mpcc-generate-lesson-content');
            
            button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Generating...');
            
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_generate_lesson_content',
                    nonce: mpccEditorSettings.nonce,
                    session_id: this.sessionId,
                    lesson_id: this.currentLessonId,
                    lesson_title: lesson.title,
                    course_context: JSON.stringify({
                        title: this.courseStructure.title,
                        description: this.courseStructure.description
                    })
                },
                success: (response) => {
                    if (response.success) {
                        $('#mpcc-lesson-textarea').val(response.data.content);
                        this.autoSaveLesson();
                    } else {
                        this.showError(response.data || 'Failed to generate content');
                    }
                },
                error: () => {
                    this.showError('Failed to generate content. Please try again.');
                },
                complete: () => {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-welcome-write-blog"></span> Generate with AI');
                }
            });
        },
        
        saveLesson: function() {
            if (!this.currentLessonId) return;
            
            const content = $('#mpcc-lesson-textarea').val();
            const [sectionIndex, lessonIndex] = this.currentLessonId.split('-').map(Number);
            
            // Update local structure
            this.courseStructure.sections[sectionIndex].lessons[lessonIndex].draft_content = content;
            
            // Save to server
            this.saveLessonToServer(content, () => {
                this.closeLessonEditor();
                this.renderCourseStructure();
            });
        },
        
        autoSaveLesson: function() {
            if (!this.currentLessonId) return;
            
            const content = $('#mpcc-lesson-textarea').val();
            this.saveLessonToServer(content);
        },
        
        saveLessonToServer: function(content, callback) {
            const [sectionIndex, lessonIndex] = this.currentLessonId.split('-').map(Number);
            const lesson = this.courseStructure.sections[sectionIndex].lessons[lessonIndex];
            
            $('.mpcc-save-indicator').text('Saving...').removeClass('saved error').addClass('saving');
            
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_save_lesson_content',
                    nonce: mpccEditorSettings.nonce,
                    session_id: this.sessionId,
                    lesson_id: this.currentLessonId,
                    lesson_title: lesson.title,
                    content: content
                },
                success: (response) => {
                    if (response.success) {
                        $('.mpcc-save-indicator').text('Saved').removeClass('saving error').addClass('saved');
                        
                        // Update local structure
                        lesson.draft_content = content;
                        
                        // Update UI to show draft indicator
                        $(`.mpcc-lesson-item[data-lesson-id="${this.currentLessonId}"]`).addClass('has-draft');
                        
                        if (callback) callback();
                    } else {
                        $('.mpcc-save-indicator').text('Error saving').removeClass('saving saved').addClass('error');
                    }
                },
                error: () => {
                    $('.mpcc-save-indicator').text('Error saving').removeClass('saving saved').addClass('error');
                }
            });
        },
        
        closeLessonEditor: function() {
            $('#mpcc-lesson-editor').fadeOut();
            $('.mpcc-lesson-item').removeClass('editing');
            this.currentLessonId = null;
        },
        
        saveConversation: function() {
            const conversationState = {
                course_structure: this.courseStructure
            };
            
            console.log('Saving conversation:', {
                session_id: this.sessionId,
                history_length: this.conversationHistory.length,
                has_course_structure: !!this.courseStructure.title,
                course_title: this.courseStructure.title || 'No title'
            });
            
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_save_conversation',
                    nonce: mpccEditorSettings.nonce,
                    session_id: this.sessionId,
                    conversation_history: JSON.stringify(this.conversationHistory),
                    conversation_state: JSON.stringify(conversationState)
                },
                success: (response) => {
                    if (response.success) {
                        console.log('Conversation saved successfully');
                    } else {
                        console.error('Failed to save conversation:', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error saving conversation:', error);
                }
            });
        },
        
        previewCourse: function() {
            console.log('Preview course:', this.courseStructure);
            // TODO: Implement course preview in a modal or new tab
            alert('Course preview functionality coming soon!');
        },
        
        createCourse: function() {
            if (!this.courseStructure.title) {
                this.showError('Please generate a course structure first.');
                return;
            }
            
            const button = $('#mpcc-create-course');
            button.prop('disabled', true).text('Creating...');
            
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_create_course',
                    nonce: mpccEditorSettings.nonce,
                    session_id: this.sessionId,
                    course_data: JSON.stringify(this.courseStructure)
                },
                success: (response) => {
                    if (response.success) {
                        // Redirect to the created course
                        window.location.href = response.data.edit_url;
                    } else {
                        this.showError(response.data || 'Failed to create course');
                    }
                },
                error: () => {
                    this.showError('Failed to create course. Please try again.');
                },
                complete: () => {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Create Course');
                }
            });
        },
        
        newSession: function() {
            if (confirm('Start a new session? Current progress will be saved.')) {
                // Generate new session ID
                const newSessionId = 'mpcc_session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                
                // Save current session first
                this.saveConversation();
                
                // Redirect to new session
                window.location.href = window.location.pathname + '?page=mpcc-course-editor&session=' + newSessionId;
            }
        },
        
        showSessionHistory: function() {
            this.toggleSessionDropdown();
        },
        
        toggleSessionDropdown: function() {
            const modalOverlay = $('.mpcc-sessions-modal-overlay');
            
            if (modalOverlay.length === 0) {
                // Create modal if it doesn't exist
                this.createSessionModal();
            }
            
            this.openSessionModal();
            this.loadSessionList();
        },
        
        openSessionModal: function() {
            const modalOverlay = $('.mpcc-sessions-modal-overlay');
            modalOverlay.addClass('active');
            $('body').css('overflow', 'hidden');
        },
        
        closeSessionModal: function() {
            const modalOverlay = $('.mpcc-sessions-modal-overlay');
            modalOverlay.removeClass('active');
            $('body').css('overflow', '');
        },
        
        createSessionModal: function() {
            const modal = $(`
                <div class="mpcc-sessions-modal-overlay">
                    <div class="mpcc-sessions-modal">
                        <div class="mpcc-sessions-modal-header">
                            <h3>Previous Conversations</h3>
                            <button type="button" class="mpcc-sessions-modal-close" aria-label="Close">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                        <div class="mpcc-sessions-list">
                            <div class="mpcc-sessions-loading">
                                <span class="dashicons dashicons-update spin"></span>
                                Loading...
                            </div>
                        </div>
                    </div>
                </div>
            `);
            
            $('body').append(modal);
            
            // Bind close button
            modal.find('.mpcc-sessions-modal-close').on('click', () => {
                this.closeSessionModal();
            });
        },
        
        loadSessionList: function() {
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_get_sessions',
                    nonce: mpccEditorSettings.nonce
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.renderSessionList(response.data);
                    } else {
                        this.renderEmptySessionList();
                    }
                },
                error: () => {
                    this.renderEmptySessionList();
                }
            });
        },
        
        renderSessionList: function(sessions) {
            const listContainer = $('.mpcc-sessions-list');
            
            if (sessions.length === 0) {
                this.renderEmptySessionList();
                return;
            }
            
            let html = '';
            sessions.forEach(session => {
                const date = new Date(session.last_updated);
                const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                
                html += `
                    <div class="mpcc-session-item" data-session-id="${session.id}">
                        <div class="mpcc-session-title">${session.title || 'Untitled Course'}</div>
                        <div class="mpcc-session-meta">${dateStr}</div>
                    </div>
                `;
            });
            
            listContainer.html(html);
            
            // Bind click events
            $('.mpcc-session-item').on('click', (e) => {
                const $item = $(e.currentTarget);
                const sessionId = $item.data('session-id');
                const sessionTitle = $item.find('.mpcc-session-title').text();
                
                if (confirm('Load this session? Current progress will be saved.')) {
                    // Save current conversation first if there's content to save
                    if (this.sessionId && (this.conversationHistory.length > 0 || this.courseStructure.title)) {
                        this.saveConversation();
                    }
                    
                    // Load the selected session via AJAX
                    this.loadConversation(sessionId, sessionTitle);
                    
                    // Close the modal
                    this.closeSessionModal();
                }
            });
        },
        
        renderEmptySessionList: function() {
            $('.mpcc-sessions-list').html(`
                <div class="mpcc-sessions-empty">
                    <p>No previous conversations found</p>
                </div>
            `);
        },
        
        loadConversation: function(sessionId, sessionTitle) {
            console.log('Loading conversation:', sessionId, sessionTitle);
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_load_session',
                    session_id: sessionId,
                    nonce: mpccEditorSettings.nonce
                },
                success: (response) => {
                    console.log('Load conversation response:', response);
                    if (response.success && response.data) {
                        // Update current session
                        this.sessionId = sessionId;
                        
                        // Clear and populate chat
                        const $messages = $('#mpcc-chat-messages');
                        $messages.empty();
                        
                        // Handle conversation history
                        const conversationHistory = response.data.conversation_history || response.data.messages || [];
                        if (conversationHistory.length > 0) {
                            this.conversationHistory = conversationHistory;
                            conversationHistory.forEach(msg => {
                                this.addMessage(msg.role === 'user' ? 'user' : 'assistant', msg.content, false);
                            });
                        }
                        
                        // Update course structure if exists
                        const courseStructure = response.data.course_structure || 
                                              (response.data.conversation_state && response.data.conversation_state.course_structure);
                        if (courseStructure) {
                            this.displayCourseStructure(courseStructure);
                        }
                        
                        // Scroll to bottom
                        this.scrollToBottom();
                    } else {
                        alert('Failed to load conversation');
                    }
                },
                error: () => {
                    alert('Failed to load conversation. Please try again.');
                }
            });
        },
        
        scrollToBottom: function() {
            const container = $('#mpcc-chat-messages');
            container.scrollTop(container[0].scrollHeight);
        },
        
        displayCourseStructure: function(courseStructure) {
            this.courseStructure = courseStructure;
            this.renderCourseStructure();
            $('#mpcc-create-course').prop('disabled', false);
            
            // Update session title if available
            if (courseStructure.title) {
                this.updateSessionTitle('Course: ' + courseStructure.title);
            }
        },
        
        updateSessionTitle: function(title) {
            // Update the session title in memory
            if (this.sessionId) {
                // Save the updated title to the server
                $.ajax({
                    url: mpccEditorSettings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_update_session_title',
                        session_id: this.sessionId,
                        title: title,
                        nonce: mpccEditorSettings.nonce
                    },
                    success: (response) => {
                        console.log('Session title updated:', title);
                    },
                    error: (xhr, status, error) => {
                        console.error('Failed to update session title:', error);
                    }
                });
            }
        },
        
        showError: function(message) {
            // You could implement a nicer notification system here
            alert(message);
        },
        
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        if ($('#mpcc-editor-container').length) {
            window.CourseEditor = CourseEditor;
            CourseEditor.init();
            
            // Expose updateSessionTitle globally for other scripts
            window.updateSessionTitle = function(sessionId, title) {
                CourseEditor.sessionId = sessionId;
                CourseEditor.updateSessionTitle(title);
            };
        }
    });
    
})(jQuery);