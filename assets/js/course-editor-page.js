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
        isSaving: false,
        publishedCourseId: null,
        publishedCourseUrl: null,
        
        init: function() {
            this.sessionId = mpccEditorSettings.sessionId;
            
            // Handle 'pending' session ID - create a new one only if needed
            if (this.sessionId === 'pending' || !this.sessionId) {
                // Check URL for session parameter
                const urlParams = new URLSearchParams(window.location.search);
                const urlSessionId = urlParams.get('session');
                
                if (urlSessionId) {
                    // Use session from URL
                    this.sessionId = urlSessionId;
                } else {
                    // Generate new session ID only when user starts interacting
                    this.sessionId = null; // Will be created on first message
                }
            }
            
            // Store session ID in sessionStorage if available
            if (this.sessionId && this.sessionId !== 'pending') {
                sessionStorage.setItem('mpcc_current_session_id', this.sessionId);
                // Trigger event to notify other components
                $(document).trigger('mpcc:session-changed', { sessionId: this.sessionId });
            }
            
            this.bindEvents();
            this.initializeChat();
            if (this.sessionId && this.sessionId !== 'pending') {
                this.loadExistingSession();
            }
        },
        
        bindEvents: function() {
            // Use event delegation for chat events to avoid conflicts
            $(document).off('click.mpcc-editor-send').on('click.mpcc-editor-send', '#mpcc-send-message', this.sendMessage.bind(this));
            $(document).off('keypress.mpcc-editor-input').on('keypress.mpcc-editor-input', '#mpcc-chat-input', function(e) {
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
            
            // Course actions
            // Preview button is now handled dynamically
            $('#mpcc-create-course').on('click', this.createCourse.bind(this));
            $('#mpcc-duplicate-course').on('click', this.duplicateCourse.bind(this));
            
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
            // Don't add to conversation history yet - this is just UI
            this.addMessage('assistant', 'Welcome to the AI Course Creator! I\'m here to help you build amazing courses. What kind of course would you like to create today?', false);
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
                        
                        // Check if course has been published
                        if (response.data.published_course_id) {
                            this.publishedCourseId = response.data.published_course_id;
                            this.publishedCourseUrl = response.data.published_course_url || null;
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
                        
                        // Update view course button based on published status
                        this.updateViewCourseButton();
                    }
                },
                error: (xhr) => {
                    console.log('No existing session found, initializing new session');
                    // Initialize empty session data
                    this.conversationHistory = [];
                    this.courseStructure = {};
                    this.publishedCourseId = null;
                    this.publishedCourseUrl = null;
                    // Don't save empty sessions - wait until there's actual content
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
            
            // Create session ID if we don't have one yet
            if (!this.sessionId || this.sessionId === 'pending') {
                this.sessionId = 'mpcc_session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem('mpcc_current_session_id', this.sessionId);
                $(document).trigger('mpcc:session-changed', { sessionId: this.sessionId });
            }
            
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
                // Create session ID if we don't have one yet
                if (!this.sessionId || this.sessionId === 'pending') {
                    this.sessionId = 'mpcc_session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    sessionStorage.setItem('mpcc_current_session_id', this.sessionId);
                    $(document).trigger('mpcc:session-changed', { sessionId: this.sessionId });
                }
                
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
                            
                            // Enable/disable create button based on published status
                            if (this.publishedCourseId) {
                                $('#mpcc-create-course').prop('disabled', true).html('<span class="dashicons dashicons-yes-alt"></span> Course Created');
                            } else {
                                $('#mpcc-create-course').prop('disabled', false);
                            }
                            
                            // Update session title with course name
                            if (courseData.title) {
                                this.updateSessionTitle('Course: ' + courseData.title);
                            }
                        }
                        
                        // Auto-save conversation
                        this.saveConversation();
                    } else {
                        mpccToast.error(response.data || 'An error occurred');
                    }
                },
                error: () => {
                    $('#' + typingId).remove();
                    mpccToast.error('Failed to communicate with the AI. Please try again.');
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
                $('#mpcc-create-course').prop('disabled', true);
                return;
            }
            
            // Course header with published badge and locked message if applicable
            const publishedBadge = this.publishedCourseId ? 
                '<span class="mpcc-published-badge"><span class="dashicons dashicons-yes-alt"></span> Published</span>' : '';
            
            const lockedMessage = this.publishedCourseId ? `
                <div class="mpcc-course-locked-notice">
                    <span class="dashicons dashicons-lock"></span>
                    <span>This course has been published and is locked for editing.</span>
                </div>` : '';
            
            const headerHtml = `
                <div class="mpcc-course-header">
                    <h2>${this.escapeHtml(this.courseStructure.title)} ${publishedBadge}</h2>
                    <p>${this.escapeHtml(this.courseStructure.description || '')}</p>
                    ${lockedMessage}
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
            
            // Update create button state based on published status
            if (this.publishedCourseId) {
                $('#mpcc-create-course').prop('disabled', true).html('<span class="dashicons dashicons-yes-alt"></span> Course Created');
            } else {
                $('#mpcc-create-course').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Create Course');
            }
            
            // Update view course button visibility and functionality
            this.updateViewCourseButton();
            
        },
        
        renderSection: function(section, sectionIndex) {
            const sectionId = `section-${sectionIndex}`;
            const lessonsHtml = section.lessons.map((lesson, lessonIndex) => 
                this.renderLesson(lesson, sectionIndex, lessonIndex)
            ).join('');
            
            // Hide section editing actions if course is published
            const sectionActions = this.publishedCourseId ? '' : `
                <div class="mpcc-section-actions">
                    <button type="button" class="button-link" title="Edit section">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button-link" title="Delete section">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>`;
            
            const lockedClass = this.publishedCourseId ? ' mpcc-section-locked' : '';
            
            return `
                <div class="mpcc-section${lockedClass}" id="${sectionId}" data-section-index="${sectionIndex}">
                    <div class="mpcc-section-header">
                        <h3 class="mpcc-section-title">${this.escapeHtml(section.title)}</h3>
                        ${sectionActions}
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
            
            // Hide edit button and add locked class if course is published
            const isLocked = this.publishedCourseId;
            const lockedClass = isLocked ? ' mpcc-lesson-locked' : '';
            const editButton = isLocked ? '' : `
                <button type="button" class="button-link">
                    <span class="dashicons dashicons-edit"></span>
                </button>`;
                
            const lockIcon = isLocked ? `
                <span class="mpcc-lesson-lock-icon" title="Course is published - editing disabled">
                    <span class="dashicons dashicons-lock"></span>
                </span>` : '';
            
            return `
                <div class="mpcc-lesson-item ${hasDraft}${lockedClass}" 
                     data-lesson-id="${lessonId}"
                     data-section-index="${sectionIndex}"
                     data-lesson-index="${lessonIndex}">
                    <div class="mpcc-lesson-info">
                        <div class="mpcc-lesson-title">${this.escapeHtml(lesson.title)}</div>
                        <div class="mpcc-lesson-meta">${lesson.duration || 'Duration not set'}</div>
                    </div>
                    ${lockIcon}
                    ${editButton}
                </div>
            `;
        },
        
        handleLessonClick: function(e) {
            e.preventDefault();
            const $target = $(e.currentTarget);
            const lessonId = $target.data('lesson-id');
            
            // Prevent editing if course is published
            if (this.publishedCourseId) {
                mpccToast.warning('This course has been published and cannot be edited.');
                return;
            }
            
            if (lessonId) {
                this.editLesson(lessonId);
            }
        },
        
        editLesson: function(lessonId) {
            // Prevent editing if course is published
            if (this.publishedCourseId) {
                mpccToast.warning('This course has been published and cannot be edited.');
                return;
            }
            
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
                    section_id: String(sectionIndex),
                    lesson_id: String(lessonIndex),
                    lesson_title: lesson.title
                },
                success: (response) => {
                    if (response.success && response.data.draft && response.data.draft.content) {
                        $('#mpcc-lesson-textarea').val(response.data.draft.content);
                    }
                }
            });
        },
        
        generateLessonContent: function() {
            // Prevent AI generation if course is published
            if (this.publishedCourseId) {
                mpccToast.warning('This course has been published and cannot be edited.');
                return;
            }
            
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
                    section_id: String(sectionIndex),
                    lesson_id: String(lessonIndex),
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
                        mpccToast.error(response.data || 'Failed to generate content');
                    }
                },
                error: () => {
                    mpccToast.error('Failed to generate content. Please try again.');
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
            if (!this.currentLessonId) {
                console.error('No current lesson ID');
                return;
            }
            
            if (this.isSaving) {
                console.log('Save already in progress, skipping');
                return;
            }
            
            const [sectionIndex, lessonIndex] = this.currentLessonId.split('-').map(Number);
            
            if (isNaN(sectionIndex) || isNaN(lessonIndex)) {
                console.error('Invalid lesson indices:', {sectionIndex, lessonIndex});
                mpccToast.error('Invalid lesson selection');
                return;
            }
            
            const lesson = this.courseStructure.sections[sectionIndex].lessons[lessonIndex];
            
            this.isSaving = true;
            $('.mpcc-save-indicator').text('Saving...').removeClass('saved error').addClass('saving');
            
            const saveData = {
                action: 'mpcc_save_lesson_content',
                nonce: mpccEditorSettings.nonce,
                session_id: this.sessionId,
                section_id: String(sectionIndex),
                lesson_id: String(lessonIndex),
                lesson_title: lesson.title,
                content: content
            };
            
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: saveData,
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
                        mpccToast.error(response.data || 'Failed to save');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Save lesson AJAX error:', {status, error, response: xhr.responseText});
                    $('.mpcc-save-indicator').text('Error saving').removeClass('saving saved').addClass('error');
                    mpccToast.error('Failed to save: ' + error);
                },
                complete: () => {
                    this.isSaving = false;
                }
            });
        },
        
        closeLessonEditor: function() {
            $('#mpcc-lesson-editor').fadeOut();
            $('.mpcc-lesson-item').removeClass('editing');
            this.currentLessonId = null;
        },
        
        saveConversation: function() {
            // Don't save if we don't have a real session ID
            if (!this.sessionId || this.sessionId === 'pending') {
                console.log('Skipping save - no session ID');
                return;
            }
            
            // Don't save empty conversations
            // Only count as having content if there's more than just the welcome message
            const hasUserMessages = this.conversationHistory.filter(msg => msg.role === 'user').length > 0;
            const hasCourseStructure = this.courseStructure && this.courseStructure.title;
            
            if (!hasUserMessages && !hasCourseStructure) {
                console.log('Skipping save - no meaningful content to save');
                return;
            }
            
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
        
        
        createCourse: function() {
            if (!this.courseStructure.title) {
                mpccToast.warning('Please generate a course structure first.');
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
                        // Store the published course ID and URL
                        this.publishedCourseId = response.data.course_id;
                        this.publishedCourseUrl = response.data.edit_url;
                        
                        // Update the UI to show published badge and link
                        this.renderCourseStructure();
                        
                        // Update button state
                        button.html('<span class="dashicons dashicons-yes-alt"></span> Course Created').prop('disabled', true);
                        
                        mpccToast.success('Course created successfully! Redirecting...');
                        
                        // Redirect to the created course after short delay
                        setTimeout(() => {
                            window.location.href = response.data.edit_url;
                        }, 2000);
                    } else {
                        mpccToast.error(response.data || 'Failed to create course');
                    }
                },
                error: () => {
                    mpccToast.error('Failed to create course. Please try again.');
                },
                complete: () => {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Create Course');
                }
            });
        },
        
        newSession: function() {
            if (confirm('Start a new session? Current progress will be saved.')) {
                // Save current session first if there's meaningful content and we have a real session ID
                if (this.sessionId && this.sessionId !== 'pending') {
                    const hasUserMessages = this.conversationHistory.filter(msg => msg.role === 'user').length > 0;
                    const hasCourseStructure = this.courseStructure && this.courseStructure.title;
                    
                    if (hasUserMessages || hasCourseStructure) {
                        this.saveConversation();
                    }
                }
                
                // Just redirect to the base page without session parameter
                // This will let the page create a new session when needed
                window.location.href = window.location.pathname + '?page=mpcc-course-editor';
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
            if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                window.MPCCUtils.modalManager.close('.mpcc-sessions-modal-overlay');
            } else {
                // Fallback
                const modalOverlay = $('.mpcc-sessions-modal-overlay');
                modalOverlay.removeClass('active');
                $('body').css('overflow', '');
            }
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
                        <div class="mpcc-session-info">
                            <div class="mpcc-session-title">${session.title || 'Untitled Course'}</div>
                            <div class="mpcc-session-meta">${dateStr}</div>
                        </div>
                        <button type="button" class="mpcc-session-delete" data-session-id="${session.id}" title="Delete conversation">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                `;
            });
            
            listContainer.html(html);
            
            // Bind click events for loading sessions
            $('.mpcc-session-item').on('click', (e) => {
                // Don't trigger if clicking on delete button
                if ($(e.target).closest('.mpcc-session-delete').length) {
                    return;
                }
                
                const $item = $(e.currentTarget);
                const sessionId = $item.data('session-id');
                const sessionTitle = $item.find('.mpcc-session-title').text();
                
                if (confirm('Load this session? Current progress will be saved.')) {
                    // Only save if we have actual user-generated content
                    if (this.sessionId && this.sessionId !== 'pending') {
                        const hasUserMessages = this.conversationHistory.filter(msg => msg.role === 'user').length > 0;
                        const hasCourseStructure = this.courseStructure && this.courseStructure.title;
                        
                        if (hasUserMessages || hasCourseStructure) {
                            this.saveConversation();
                        }
                    }
                    
                    // Close the modal before redirecting
                    this.closeSessionModal();
                    
                    // Redirect to the selected session to ensure proper URL
                    window.location.href = window.location.pathname + '?page=mpcc-course-editor&session=' + sessionId;
                }
            });
            
            // Bind delete button events
            $('.mpcc-session-delete').on('click', (e) => {
                e.stopPropagation(); // Prevent session from loading
                const $button = $(e.currentTarget);
                const sessionId = $button.data('session-id');
                const sessionTitle = $button.closest('.mpcc-session-item').find('.mpcc-session-title').text();
                
                if (confirm(`Are you sure you want to delete the conversation "${sessionTitle}"? This action cannot be undone.`)) {
                    this.deleteSession(sessionId);
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
                        
                        // Store session ID in sessionStorage for other components
                        sessionStorage.setItem('mpcc_current_session_id', sessionId);
                        
                        // Trigger event to notify other components (like CoursePreviewEditor) that session changed
                        $(document).trigger('mpcc:session-changed', { sessionId: sessionId });
                        
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
                        
                        // Check if course has been published
                        if (response.data.published_course_id) {
                            this.publishedCourseId = response.data.published_course_id;
                            this.publishedCourseUrl = response.data.published_course_url || null;
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
                        mpccToast.error('Failed to load conversation');
                    }
                },
                error: () => {
                    mpccToast.error('Failed to load conversation. Please try again.');
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
        
        deleteSession: function(sessionId) {
            // Show loading state
            const $deleteButton = $(`.mpcc-session-delete[data-session-id="${sessionId}"]`);
            const originalHtml = $deleteButton.html();
            $deleteButton.html('<span class="dashicons dashicons-update spin"></span>').prop('disabled', true);
            
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_delete_session',
                    nonce: mpccEditorSettings.nonce,
                    session_id: sessionId
                },
                success: (response) => {
                    if (response.success) {
                        // Remove the session item with animation
                        const $sessionItem = $(`.mpcc-session-item[data-session-id="${sessionId}"]`);
                        $sessionItem.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Check if list is now empty
                            if ($('.mpcc-session-item').length === 0) {
                                this.renderEmptySessionList();
                            }
                        }.bind(this));
                        
                        mpccToast.success('Conversation deleted successfully');
                        
                        // If we deleted the current session, redirect to a new session
                        if (sessionId === this.sessionId) {
                            setTimeout(() => {
                                window.location.href = window.location.pathname + '?page=mpcc-course-editor';
                            }, 1000);
                        }
                    } else {
                        mpccToast.error(response.data || 'Failed to delete conversation');
                        // Restore button
                        $deleteButton.html(originalHtml).prop('disabled', false);
                    }
                },
                error: () => {
                    mpccToast.error('Failed to delete conversation. Please try again.');
                    // Restore button
                    $deleteButton.html(originalHtml).prop('disabled', false);
                }
            });
        },
        
        showError: function(message) {
            mpccToast.error(message);
        },
        
        updateViewCourseButton: function() {
            const $viewBtn = $('#mpcc-view-course');
            const $duplicateBtn = $('#mpcc-duplicate-course');
            
            if (this.publishedCourseId && this.publishedCourseUrl) {
                // Show and setup View Course button
                $viewBtn
                    .show()
                    .off('click')
                    .on('click', () => {
                        window.open(this.publishedCourseUrl, '_blank');
                    });
                
                // Show duplicate button for published courses
                $duplicateBtn.show();
            } else {
                // Hide both buttons
                $viewBtn.hide();
                $duplicateBtn.hide();
            }
        },
        
        duplicateCourse: function() {
            if (!this.publishedCourseId || !this.courseStructure.title) {
                mpccToast.warning('No published course available to duplicate.');
                return;
            }
            
            if (confirm('Duplicate this course as a draft? This will create a new editing session with all the course content.')) {
                const button = $('#mpcc-duplicate-course');
                button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Duplicating...');
                
                $.ajax({
                    url: mpccEditorSettings.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_duplicate_course',
                        nonce: mpccEditorSettings.nonce,
                        session_id: this.sessionId,
                        course_data: JSON.stringify(this.courseStructure)
                    },
                    success: (response) => {
                        if (response.success) {
                            mpccToast.success('Course duplicated successfully! Redirecting to new session...');
                            
                            // Redirect to the new session
                            setTimeout(() => {
                                window.location.href = window.location.pathname + '?page=mpcc-course-editor&session=' + response.data.new_session_id;
                            }, 1500);
                        } else {
                            mpccToast.error(response.data || 'Failed to duplicate course');
                        }
                    },
                    error: () => {
                        mpccToast.error('Failed to duplicate course. Please try again.');
                    },
                    complete: () => {
                        button.prop('disabled', false).html('<span class="dashicons dashicons-admin-page"></span> Duplicate Course');
                    }
                });
            }
        },
        
        escapeHtml: function(text) {
            // Use shared utility if available
            if (window.MPCCUtils && window.MPCCUtils.escapeHtml) {
                return window.MPCCUtils.escapeHtml(text);
            }
            // Fallback
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