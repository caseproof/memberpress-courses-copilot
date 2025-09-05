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
        messageHistory: [],
        messageHistoryIndex: -1,
        
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
            // Store debounced functions as properties for cleanup
            this.debouncedAutoSave = MPCCUtils.debounce(this.autoSaveLesson.bind(this), 2000);
            // Use event delegation for chat events to avoid conflicts
            $(document).off('click.mpcc-editor-send').on('click.mpcc-editor-send', '#mpcc-send-message', this.sendMessage.bind(this));
            $(document).off('keypress.mpcc-editor-input').on('keypress.mpcc-editor-input', '#mpcc-chat-input', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            }.bind(this));
            
            // Initialize mobile tabs
            this.initializeMobileTabs();
            
            // Initialize keyboard navigation
            this.initializeKeyboardNavigation();
            
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
            
            // Close lesson editor when clicking overlay
            $('#mpcc-lesson-editor-overlay').on('click', this.closeLessonEditor.bind(this));
            
            // Auto-save on textarea change with debouncing
            $('#mpcc-lesson-textarea').off('input.autosave').on('input.autosave', this.debouncedAutoSave);
            
            // Section action buttons - delegated for dynamic content
            $(document).on('click', '.mpcc-section-actions button', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $button = $(e.currentTarget);
                const $section = $button.closest('.mpcc-section');
                const sectionIndex = parseInt($section.data('section-index'));
                
                if ($button.find('.dashicons-edit').length) {
                    this.handleEditSection(sectionIndex);
                } else if ($button.find('.dashicons-trash').length) {
                    this.handleDeleteSection(sectionIndex);
                }
            });
            
            // Lesson edit button - override the one from courses-integration.js
            $(document).on('click', '.mpcc-edit-lesson', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $lesson = $(e.currentTarget).closest('.mpcc-lesson-item');
                const lessonId = $lesson.data('lesson-id');
                if (lessonId) {
                    this.editLesson(lessonId);
                }
            });
            
            // Lesson delete button - handle in course editor context
            $(document).on('click', '.mpcc-delete-lesson', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const $button = $(e.currentTarget);
                const sectionIndex = parseInt($button.data('section'));
                const lessonIndex = parseInt($button.data('index'));
                this.handleDeleteLesson(sectionIndex, lessonIndex);
            });
        },
        
        initializeSortable: function() {
            // Only initialize if course is not published and sortable is available
            if (this.publishedCourseId || !$.fn.sortable) {
                return;
            }
            
            // Make sections sortable
            $('#mpcc-course-structure').sortable({
                items: '.mpcc-section',
                handle: '.mpcc-section-header',
                placeholder: 'mpcc-section-placeholder',
                cursor: 'move',
                tolerance: 'pointer',
                update: (event, ui) => {
                    this.updateSectionOrder();
                }
            });
            
            // Make lessons sortable within each section
            $('.mpcc-lessons').sortable({
                items: '.mpcc-lesson-item',
                placeholder: 'mpcc-lesson-placeholder',
                cursor: 'move',
                tolerance: 'pointer',
                connectWith: '.mpcc-lessons',
                update: (event, ui) => {
                    this.updateLessonOrder();
                }
            });
        },
        
        updateSectionOrder: function() {
            const newOrder = [];
            $('.mpcc-section').each((index, el) => {
                const originalIndex = parseInt($(el).attr('id').replace('section-', ''));
                newOrder.push(originalIndex);
            });
            
            // Reorder sections in the data structure
            const reorderedSections = newOrder.map(index => this.courseStructure.sections[index]);
            this.courseStructure.sections = reorderedSections;
            
            // Re-render to update indices
            this.renderCourseStructure();
            
            // Re-initialize sortable after re-render
            this.initializeSortable();
            
            // Save the updated structure
            MPCCAccessibility.announce('Saving section order...');
            this.saveConversation();
            
            MPCCUtils.showSuccess('Section order updated');
            MPCCAccessibility.announce('Section order saved successfully');
        },
        
        updateLessonOrder: function() {
            const newStructure = [];
            
            $('.mpcc-section').each((sectionIndex, sectionEl) => {
                const sectionData = this.courseStructure.sections[sectionIndex];
                const section = {
                    title: sectionData.title,
                    lessons: []
                };
                
                $(sectionEl).find('.mpcc-lesson-item').each((lessonIndex, lessonEl) => {
                    const lessonId = $(lessonEl).attr('data-lesson-id');
                    const [origSection, origLesson] = lessonId.split('-').map(n => parseInt(n));
                    const lesson = this.courseStructure.sections[origSection].lessons[origLesson];
                    section.lessons.push(lesson);
                });
                
                newStructure.push(section);
            });
            
            this.courseStructure.sections = newStructure;
            
            // Re-render to update indices
            this.renderCourseStructure();
            
            // Re-initialize sortable after re-render
            this.initializeSortable();
            
            // Save the updated structure
            MPCCAccessibility.announce('Saving lesson order...');
            this.saveConversation();
            
            MPCCUtils.showSuccess('Lesson order updated');
            MPCCAccessibility.announce('Lesson order saved successfully');
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
                        
                        // Load all lesson drafts for this session
                        if (this.courseStructure && this.courseStructure.sections) {
                            this.loadAllLessonDrafts();
                        }
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
        
        loadAllLessonDrafts: function() {
            // Load all lesson drafts for the current session
            $.ajax({
                url: mpccEditorSettings.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_get_session_drafts',
                    nonce: mpccEditorSettings.nonce,
                    session_id: this.sessionId
                },
                success: (response) => {
                    if (response.success && response.data) {
                        // Map drafts to lessons
                        response.data.forEach(draft => {
                            const sectionIndex = parseInt(draft.section_id);
                            const lessonIndex = parseInt(draft.lesson_id);
                            
                            if (this.courseStructure.sections[sectionIndex] && 
                                this.courseStructure.sections[sectionIndex].lessons[lessonIndex]) {
                                this.courseStructure.sections[sectionIndex].lessons[lessonIndex].draft_content = draft.content;
                            }
                        });
                        
                        // Re-render course structure with draft indicators
                        this.renderCourseStructure();
                    }
                }
            });
        },
        
        rebuildChatHistory: function() {
            $('#mpcc-chat-messages').empty();
            
            // Rebuild message history from conversation history
            this.messageHistory = [];
            this.conversationHistory.forEach(msg => {
                this.addMessage(msg.role, msg.content, false);
                // Add user messages to message history for arrow navigation
                if (msg.role === 'user') {
                    this.messageHistory.push(msg.content);
                }
            });
            
            // Reset message history index
            this.messageHistoryIndex = this.messageHistory.length;
            
            // Hide quickstart suggestions if there are any AI responses in history
            const hasAssistantMessages = this.conversationHistory.some(msg => msg.role === 'assistant');
            if (hasAssistantMessages) {
                $('#mpcc-quick-starter-suggestions').addClass('hidden');
            }
        },
        
        sendMessage: function() {
            const input = $('#mpcc-chat-input');
            const message = input.val().trim();
            
            if (!message) return;
            
            // Add message to history
            this.messageHistory.push(message);
            this.messageHistoryIndex = this.messageHistory.length;
            
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
            
            // Skip if content is undefined or null
            if (content === undefined || content === null) {
                console.warn('Attempted to add message with undefined/null content', { role });
                return;
            }
            
            // Convert to string if needed
            content = String(content);
            
            // Format content based on role
            let formattedContent;
            if (role === 'assistant') {
                // Use formatMessageToHTML for AI messages to preserve formatting
                formattedContent = MPCCUtils.formatMessageToHTML(content);
            } else {
                // For user messages, just escape HTML
                formattedContent = MPCCUtils.escapeHtml(content);
            }
            
            const messageHtml = `
                <div class="mpcc-chat-message ${this.escapeHtml(role)}">
                    <div class="message-content">${formattedContent}</div>
                </div>
            `;
            
            messagesContainer.append(messageHtml);
            // Use requestAnimationFrame to ensure DOM has been rendered
            requestAnimationFrame(() => {
                messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
            });
            
            // Add to conversation history
            if (addToHistory) {
                this.conversationHistory.push({ role, content });
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
                <div class="mpcc-chat-message assistant" id="${this.escapeHtml(typingId)}">
                    <div class="message-content">
                        <div class="mpcc-typing-indicator">
                            <div class="mpcc-typing-dot"></div>
                            <div class="mpcc-typing-dot"></div>
                            <div class="mpcc-typing-dot"></div>
                        </div>
                    </div>
                </div>
            `;
            $('#mpcc-chat-messages').append(typingHtml);
            this.scrollToBottom();
            
            // Set aria-busy and announce generation start
            $('#mpcc-chat-messages').attr('aria-busy', 'true');
            MPCCAccessibility.announce('Starting AI generation. Processing your request.');
            
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
                        
                        // Hide quick starter suggestions after AI responds
                        $('#mpcc-quick-starter-suggestions').addClass('hidden');
                        
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
                            
                            // Auto-switch to Course Preview tab on mobile
                            if (window.innerWidth <= 960) {
                                this.switchToPreviewTab();
                                // Announce tab switch to screen readers
                                MPCCAccessibility.announce('Switched to course preview tab');
                            }
                        }
                        
                        // Auto-save conversation
                        this.saveConversation();
                        
                        // Announce completion
                        if (response.data.course_structure) {
                            MPCCAccessibility.announce('AI generation complete. Course structure has been created.');
                        } else {
                            MPCCAccessibility.announce('AI response received successfully.');
                        }
                    } else {
                        const errorMsg = response.data || 'An error occurred';
                        MPCCUtils.showError(errorMsg);
                        // Announce error
                        MPCCAccessibility.announce(`Error during AI generation: ${errorMsg}`);
                    }
                },
                error: () => {
                    $('#' + typingId).remove();
                    const errorMsg = 'Failed to communicate with the AI. Please try again.';
                    MPCCUtils.showError(errorMsg);
                    // Announce error
                    MPCCAccessibility.announce(`Error during AI generation: ${errorMsg}`);
                },
                complete: () => {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-arrow-right-alt"></span> Send');
                    // Remove aria-busy
                    $('#mpcc-chat-messages').attr('aria-busy', 'false');
                }
            });
        },
        
        renderCourseStructure: function() {
            const container = $('#mpcc-course-structure');
            
            // Use document fragment for better performance
            const fragment = document.createDocumentFragment();
            const tempWrapper = document.createElement('div');
            
            container.empty();
            
            if (!this.courseStructure.title) {
                container.html($('.mpcc-empty-state').first().clone());
                $('#mpcc-create-course').prop('disabled', true);
                return;
            }
            
            // Build the entire structure in memory first
            let structureHtml = '';
            
            // Course header with published badge and locked message if applicable
            const publishedBadge = this.publishedCourseId ? 
                '<span class="mpcc-published-badge" role="status"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> Published</span>' : '';
            
            const lockedMessage = this.publishedCourseId ? `
                <div class="mpcc-course-locked-notice" role="alert">
                    <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                    <span>This course has been published and is locked for editing.</span>
                </div>` : '';
            
            structureHtml += `
                <div class="mpcc-course-header">
                    <h2>${this.escapeHtml(this.courseStructure.title)} ${publishedBadge}</h2>
                    <p>${this.escapeHtml(this.courseStructure.description || '')}</p>
                    ${lockedMessage}
                </div>
            `;
            
            // Sections and lessons with proper list structure
            if (this.courseStructure.sections) {
                structureHtml += '<div role="list" aria-label="Course sections">';
                this.courseStructure.sections.forEach((section, sectionIndex) => {
                    structureHtml += this.renderSection(section, sectionIndex);
                });
                structureHtml += '</div>';
            }
            
            // Append all at once for better performance
            tempWrapper.innerHTML = structureHtml;
            fragment.appendChild(tempWrapper.firstChild);
            while (tempWrapper.firstChild) {
                fragment.appendChild(tempWrapper.firstChild);
            }
            container[0].appendChild(fragment);
            
            // Update create button state based on published status
            if (this.publishedCourseId) {
                $('#mpcc-create-course').prop('disabled', true).html('<span class="dashicons dashicons-yes-alt"></span> Course Created');
            } else {
                $('#mpcc-create-course').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Create Course');
            }
            
            // Update view course button visibility and functionality
            this.updateViewCourseButton();
            
            // Initialize sortable functionality
            this.initializeSortable();
            
            // Disable chat for published courses
            if (this.publishedCourseId) {
                // Disable chat input and send button
                $('#mpcc-chat-input').prop('disabled', true)
                    .attr('placeholder', 'This course has been published. Use "Duplicate Course" to create a new version.');
                $('#mpcc-send-message').prop('disabled', true);
                
                // Hide quick starter suggestions
                $('#mpcc-quick-starter-suggestions').hide();
                
                // Add info message if not already present
                if (!$('.mpcc-chat-disabled-notice').length) {
                    $('.mpcc-chat-input-wrapper').prepend(
                        '<div class="mpcc-chat-disabled-notice">' +
                        '<span class="dashicons dashicons-info"></span> ' +
                        'This course is published and locked. To make changes, use the "Duplicate Course" button above.' +
                        '</div>'
                    );
                }
            } else {
                // Re-enable chat for draft courses
                $('#mpcc-chat-input').prop('disabled', false)
                    .attr('placeholder', 'Type a message...');
                $('#mpcc-send-message').prop('disabled', false);
                
                // Show quick starter suggestions only if no AI responses yet
                const hasAssistantMessages = this.conversationHistory.some(msg => msg.role === 'assistant');
                if (!hasAssistantMessages) {
                    $('#mpcc-quick-starter-suggestions').removeClass('hidden').show();
                }
                
                // Remove disabled notice
                $('.mpcc-chat-disabled-notice').remove();
            }
            
        },
        
        renderSection: function(section, sectionIndex) {
            const sectionId = `section-${this.escapeHtml(String(sectionIndex))}`;
            const sectionNumber = sectionIndex + 1;
            const lessonsHtml = section.lessons.map((lesson, lessonIndex) => 
                this.renderLesson(lesson, sectionIndex, lessonIndex)
            ).join('');
            
            // Hide section editing actions if course is published
            const sectionActions = this.publishedCourseId ? '' : `
                <div class="mpcc-section-actions">
                    <button type="button" class="button-link" title="Edit section" aria-label="Edit section ${this.escapeHtml(section.title)}">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="button-link" title="Delete section" aria-label="Delete section ${this.escapeHtml(section.title)}">
                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    </button>
                </div>`;
            
            const lockedClass = this.publishedCourseId ? ' mpcc-section-locked' : '';
            
            return `
                <div class="mpcc-section${lockedClass}" id="${sectionId}" data-section-index="${this.escapeHtml(String(sectionIndex))}" role="listitem" aria-label="Section ${sectionNumber}: ${this.escapeHtml(section.title)}">
                    <div class="mpcc-section-header">
                        <h3 class="mpcc-section-title" id="${sectionId}-title">${this.escapeHtml(section.title)}</h3>
                        ${sectionActions}
                    </div>
                    <div class="mpcc-lessons" role="list" aria-label="Lessons in ${this.escapeHtml(section.title)}">
                        ${lessonsHtml}
                    </div>
                </div>
            `;
        },
        
        renderLesson: function(lesson, sectionIndex, lessonIndex) {
            const lessonId = `${this.escapeHtml(String(sectionIndex))}-${this.escapeHtml(String(lessonIndex))}`;
            const hasDraft = lesson.draft_content ? 'has-draft' : '';
            const lessonNumber = lessonIndex + 1;
            
            // Hide edit button and add locked class if course is published
            const isLocked = this.publishedCourseId;
            const lockedClass = isLocked ? ' mpcc-lesson-locked' : '';
            const actionButtons = isLocked ? '' : `
                <div class="mpcc-lesson-actions">
                    <button type="button" class="button-link mpcc-edit-lesson" title="Edit lesson" aria-label="Edit lesson: ${this.escapeHtml(lesson.title)}">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="button-link mpcc-delete-lesson" title="Delete lesson" data-section="${this.escapeHtml(String(sectionIndex))}" data-index="${this.escapeHtml(String(lessonIndex))}" aria-label="Delete lesson: ${this.escapeHtml(lesson.title)}">
                        <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                    </button>
                </div>`;
                
            const lockIcon = isLocked ? `
                <span class="mpcc-lesson-lock-icon" title="Course is published - editing disabled" aria-label="Locked">
                    <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                </span>` : '';
            
            const draftStatus = hasDraft ? ' - has draft content' : '';
            
            return `
                <div class="mpcc-lesson-item${hasDraft ? ' has-draft' : ''}${lockedClass}" 
                     data-lesson-id="${lessonId}"
                     data-section-index="${this.escapeHtml(String(sectionIndex))}"
                     data-lesson-index="${this.escapeHtml(String(lessonIndex))}"
                     role="listitem"
                     aria-label="Lesson ${lessonNumber}: ${this.escapeHtml(lesson.title)}${draftStatus}">
                    <div class="mpcc-lesson-info">
                        <div class="mpcc-lesson-title">${this.escapeHtml(lesson.title)}</div>
                        <div class="mpcc-lesson-meta" aria-label="Duration">${lesson.duration || 'Duration not set'}</div>
                    </div>
                    ${lockIcon}
                    ${actionButtons}
                </div>
            `;
        },
        
        handleLessonClick: function(e) {
            // Don't trigger if clicking on action buttons
            if ($(e.target).closest('.mpcc-lesson-actions, .button-link').length) {
                return;
            }
            
            e.preventDefault();
            const $target = $(e.currentTarget);
            const lessonId = $target.data('lesson-id');
            
            // Prevent editing if course is published
            if (this.publishedCourseId) {
                MPCCUtils.showWarning('This course has been published and cannot be edited.');
                return;
            }
            
            if (lessonId) {
                this.editLesson(lessonId);
            }
        },
        
        editLesson: function(lessonId) {
            // Prevent editing if course is published
            if (this.publishedCourseId) {
                MPCCUtils.showWarning('This course has been published and cannot be edited.');
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
            
            // Show overlay on mobile
            if (window.innerWidth <= 960) {
                $('#mpcc-lesson-editor-overlay').fadeIn(200).attr('aria-hidden', 'false');
            }
            
            $('#mpcc-lesson-editor').fadeIn(() => {
                // Set up focus trap after the modal is visible
                if (typeof MPCCAccessibility !== 'undefined') {
                    this.lessonEditorFocusTrap = MPCCAccessibility.trapFocus('#mpcc-lesson-editor', {
                        initialFocus: '#mpcc-lesson-textarea',
                        escapeDeactivates: true,
                        onEscape: () => {
                            this.closeLessonEditor();
                        }
                    });
                }
                
                // Focus the textarea if no focus trap
                if (!this.lessonEditorFocusTrap) {
                    $('#mpcc-lesson-textarea').focus();
                }
            }).attr('aria-hidden', 'false');
            
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
                MPCCUtils.showWarning('This course has been published and cannot be edited.');
                return;
            }
            
            if (!this.currentLessonId) return;
            
            const [sectionIndex, lessonIndex] = this.currentLessonId.split('-').map(Number);
            const lesson = this.courseStructure.sections[sectionIndex].lessons[lessonIndex];
            const button = $('#mpcc-generate-lesson-content');
            
            button.prop('disabled', true).html('<span class="dashicons dashicons-update spin" aria-hidden="true"></span> Generating...');
            
            // Set aria-busy and announce generation start
            $('#mpcc-lesson-editor').attr('aria-busy', 'true');
            MPCCAccessibility.announce('Starting AI generation. Processing your request for lesson content.');
            
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
                        // Announce successful generation
                        MPCCAccessibility.announce('AI generation complete. Lesson content has been generated successfully.');
                    } else {
                        const errorMsg = response.data || 'Failed to generate content';
                        MPCCUtils.showError(errorMsg);
                        // Announce error
                        MPCCAccessibility.announce(`Error during AI generation: ${errorMsg}`);
                    }
                },
                error: () => {
                    const errorMsg = 'Failed to generate content. Please try again.';
                    MPCCUtils.showError(errorMsg);
                    // Announce error
                    MPCCAccessibility.announce(`Error during AI generation: ${errorMsg}`);
                },
                complete: () => {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-welcome-write-blog" aria-hidden="true"></span> Generate');
                    // Remove aria-busy
                    $('#mpcc-lesson-editor').attr('aria-busy', 'false');
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
                MPCCUtils.showError('Invalid lesson selection');
                return;
            }
            
            const lesson = this.courseStructure.sections[sectionIndex].lessons[lessonIndex];
            
            this.isSaving = true;
            $('.mpcc-save-indicator').text('Saving...').removeClass('saved error').addClass('saving');
            MPCCAccessibility.announce('Saving lesson content...');
            
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
                        MPCCUtils.ui.updateSaveIndicator('saved');
                        MPCCAccessibility.announce('Lesson saved successfully');
                        
                        // Update local structure
                        lesson.draft_content = content;
                        
                        // Update UI to show draft indicator
                        $(`.mpcc-lesson-item[data-lesson-id="${this.currentLessonId}"]`).addClass('has-draft');
                        
                        if (callback) callback();
                    } else {
                        MPCCUtils.ui.updateSaveIndicator('error');
                        MPCCUtils.showError(response.data || 'Failed to save');
                        MPCCAccessibility.announce('Error: Failed to save lesson. ' + (response.data || 'Please try again'), 'assertive');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Save lesson AJAX error:', {status, error, response: xhr.responseText});
                    MPCCUtils.ui.updateSaveIndicator('error');
                    MPCCUtils.showError('Failed to save: ' + error);
                    MPCCAccessibility.announce('Error: Failed to save lesson. ' + error, 'assertive');
                },
                complete: () => {
                    this.isSaving = false;
                }
            });
        },
        
        closeLessonEditor: function() {
            // Cleanup event handlers before closing
            $('#mpcc-lesson-textarea').off('input.autosave');
            
            // Move focus back to the lesson item that was being edited
            const $editingLesson = $('.mpcc-lesson-item.editing');
            
            // Set aria-hidden before hiding to prevent focus issues
            $('#mpcc-lesson-editor').attr('aria-hidden', 'true').fadeOut(() => {
                // Remove focus trap after fade completes
                if (this.lessonEditorFocusTrap) {
                    this.lessonEditorFocusTrap.deactivate();
                    this.lessonEditorFocusTrap = null;
                }
            });
            $('#mpcc-lesson-editor-overlay').attr('aria-hidden', 'true').fadeOut();
            
            // Restore focus to the lesson item
            if ($editingLesson.length) {
                $editingLesson.focus();
            }
            
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
                MPCCUtils.showWarning('Please generate a course structure first.');
                return;
            }
            
            const button = $('#mpcc-create-course');
            button.prop('disabled', true).text('Creating...');
            MPCCAccessibility.announce('Creating course...');
            
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
                        
                        MPCCUtils.showSuccess('Course created successfully! Redirecting...');
                        MPCCAccessibility.announce('Course created successfully! Redirecting to course editor.');
                        
                        // Redirect to the created course after short delay
                        setTimeout(() => {
                            window.location.href = response.data.edit_url;
                        }, 2000);
                    } else {
                        MPCCUtils.showError(response.data || 'Failed to create course');
                        MPCCAccessibility.announce('Error: Failed to create course. ' + (response.data || 'Please try again'), 'assertive');
                    }
                },
                error: () => {
                    MPCCUtils.showError('Failed to create course. Please try again.');
                    MPCCAccessibility.announce('Error: Failed to create course. Please try again.', 'assertive');
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
                <div class="mpcc-sessions-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="mpcc-sessions-modal-title">
                    <div class="mpcc-sessions-modal">
                        <div class="mpcc-sessions-modal-header">
                            <h3 id="mpcc-sessions-modal-title">Previous Conversations</h3>
                            <button type="button" class="mpcc-sessions-modal-close" aria-label="Close session history dialog">
                                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                            </button>
                        </div>
                        <div class="mpcc-sessions-list" role="region" aria-label="Session history list" aria-live="polite">
                            <div class="mpcc-sessions-loading" role="status" aria-label="Loading sessions">
                                <span class="dashicons dashicons-update spin" aria-hidden="true"></span>
                                <span aria-live="polite">Loading sessions...</span>
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
            
            // Enhance modal for accessibility
            MPCCAccessibility.enhanceModal(modal.find('.mpcc-sessions-modal'), {
                labelledby: 'mpcc-sessions-modal-title',
                closeLabel: 'Close session history'
            });
            
            // Add ID to modal title
            modal.find('h3').attr('id', 'mpcc-sessions-modal-title');
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
                const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
                
                // Add a unique identifier to help debug timestamp issues
                const sessionDebugInfo = `Session ID: ${session.id.substring(0, 8)}... | DB ID: ${session.database_id || 'N/A'}`;
                
                html += `
                    <div class="mpcc-session-item" data-session-id="${this.escapeHtml(session.id)}" title="${this.escapeHtml(sessionDebugInfo)}">
                        <div class="mpcc-session-info">
                            <div class="mpcc-session-title">${this.escapeHtml(session.title || 'Untitled Course')}</div>
                            <div class="mpcc-session-meta">${this.escapeHtml(dateStr)}</div>
                        </div>
                        <button type="button" class="mpcc-session-delete" data-session-id="${this.escapeHtml(session.id)}" title="Delete conversation">
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
                        MPCCUtils.showError('Failed to load conversation');
                    }
                },
                error: () => {
                    MPCCUtils.showError('Failed to load conversation. Please try again.');
                }
            });
        },
        
        scrollToBottom: function() {
            const container = $('#mpcc-chat-messages');
            // Use requestAnimationFrame to ensure DOM has been rendered
            requestAnimationFrame(() => {
                container.scrollTop(container[0].scrollHeight);
            });
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
                        
                        MPCCUtils.showSuccess('Conversation deleted successfully');
                        
                        // If we deleted the current session, redirect to a new session
                        if (sessionId === this.sessionId) {
                            setTimeout(() => {
                                window.location.href = window.location.pathname + '?page=mpcc-course-editor';
                            }, 1000);
                        }
                    } else {
                        MPCCUtils.showError(response.data || 'Failed to delete conversation');
                        // Restore button
                        $deleteButton.html(originalHtml).prop('disabled', false);
                    }
                },
                error: () => {
                    MPCCUtils.showError('Failed to delete conversation. Please try again.');
                    // Restore button
                    $deleteButton.html(originalHtml).prop('disabled', false);
                }
            });
        },
        
        showError: function(message) {
            MPCCUtils.showError(message);
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
                MPCCUtils.showWarning('No published course available to duplicate.');
                return;
            }
            
            if (confirm('Duplicate this course as a draft? This will create a new editing session with all the course content.')) {
                const button = $('#mpcc-duplicate-course');
                button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Duplicating...');
                MPCCAccessibility.announce('Duplicating course...');
                
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
                            MPCCUtils.showSuccess('Course duplicated successfully! Redirecting to new session...');
                            MPCCAccessibility.announce('Course duplicated successfully! Redirecting to new session.');
                            
                            // Redirect to the new session
                            setTimeout(() => {
                                window.location.href = window.location.pathname + '?page=mpcc-course-editor&session=' + response.data.new_session_id;
                            }, 1500);
                        } else {
                            MPCCUtils.showError(response.data || 'Failed to duplicate course');
                            MPCCAccessibility.announce('Error: Failed to duplicate course. ' + (response.data || 'Please try again'), 'assertive');
                        }
                    },
                    error: () => {
                        MPCCUtils.showError('Failed to duplicate course. Please try again.');
                        MPCCAccessibility.announce('Error: Failed to duplicate course. Please try again.', 'assertive');
                    },
                    complete: () => {
                        button.prop('disabled', false).html('<span class="dashicons dashicons-admin-page"></span> Duplicate Course');
                    }
                });
            }
        },
        
        escapeHtml: function(text) {
            return MPCCUtils.escapeHtml(text);
        },
        
        handleEditSection: function(sectionIndex) {
            if (!this.courseStructure || !this.courseStructure.sections) {
                MPCCUtils.showError('No course structure found');
                return;
            }
            
            const section = this.courseStructure.sections[sectionIndex];
            if (!section) {
                MPCCUtils.showError('Section not found');
                return;
            }
            
            const newTitle = prompt('Edit section title:', section.title);
            if (newTitle && newTitle.trim() && newTitle !== section.title) {
                this.courseStructure.sections[sectionIndex].title = newTitle.trim();
                this.renderCourseStructure();
                MPCCAccessibility.announce('Saving section title...');
                this.saveConversation();
                MPCCUtils.showSuccess('Section title updated');
                MPCCAccessibility.announce('Section title saved successfully');
            }
        },
        
        handleDeleteSection: function(sectionIndex) {
            if (!this.courseStructure || !this.courseStructure.sections) {
                MPCCUtils.showError('No course structure found');
                return;
            }
            
            const section = this.courseStructure.sections[sectionIndex];
            if (!section) {
                MPCCUtils.showError('Section not found');
                return;
            }
            
            if (!confirm(`Delete section "${section.title}" and all its lessons?`)) {
                return;
            }
            
            // Delete locally and save the updated structure
            this.courseStructure.sections.splice(sectionIndex, 1);
            this.renderCourseStructure();
            this.initializeSortable();
            MPCCAccessibility.announce('Deleting section...');
            this.saveConversation(); // This saves the entire updated structure
            MPCCUtils.showSuccess('Section deleted');
            MPCCAccessibility.announce('Section deleted successfully');
        },
        
        handleDeleteLesson: function(sectionIndex, lessonIndex) {
            if (!this.courseStructure || !this.courseStructure.sections || 
                !this.courseStructure.sections[sectionIndex] || 
                !this.courseStructure.sections[sectionIndex].lessons[lessonIndex]) {
                MPCCUtils.showError('Lesson not found');
                return;
            }
            
            const lesson = this.courseStructure.sections[sectionIndex].lessons[lessonIndex];
            
            if (!confirm(`Delete lesson "${lesson.title}"?`)) {
                return;
            }
            
            // Delete locally and save the updated structure
            this.courseStructure.sections[sectionIndex].lessons.splice(lessonIndex, 1);
            this.renderCourseStructure();
            this.initializeSortable();
            MPCCAccessibility.announce('Deleting lesson...');
            this.saveConversation(); // This saves the entire updated structure
            MPCCUtils.showSuccess('Lesson deleted');
            MPCCAccessibility.announce('Lesson deleted successfully');
        },
        
        // Mobile tabs functionality
        initializeMobileTabs: function() {
            // Only initialize for mobile screens
            if (window.innerWidth > 960) {
                return;
            }
            
            // Create mobile tab navigation if it doesn't exist
            if (!$('.mpcc-mobile-tabs').length) {
                const tabsHtml = `
                    <div class="mpcc-mobile-tabs">
                        <div class="mpcc-mobile-tab-buttons">
                            <button class="mpcc-mobile-tab-button active" data-tab="chat">
                                <span class="dashicons dashicons-format-chat"></span>
                                AI Assistant
                            </button>
                            <button class="mpcc-mobile-tab-button" data-tab="preview">
                                <span class="dashicons dashicons-welcome-widgets-menus"></span>
                                Course Preview
                            </button>
                        </div>
                    </div>
                `;
                $('.mpcc-editor-layout').before(tabsHtml);
            }
            
            // Set initial active state
            $('.mpcc-editor-sidebar').addClass('mobile-active');
            
            // Bind tab click events
            $(document).off('click.mpcc-mobile-tabs').on('click.mpcc-mobile-tabs', '.mpcc-mobile-tab-button', this.handleMobileTabClick.bind(this));
            
            // Handle window resize
            $(window).off('resize.mpcc-mobile-tabs').on('resize.mpcc-mobile-tabs', MPCCUtils.debounce(() => {
                if (window.innerWidth > 960) {
                    // Remove mobile classes when switching back to desktop
                    $('.mpcc-editor-sidebar, .mpcc-editor-main').removeClass('mobile-active');
                    $('.mpcc-mobile-tab-button').removeClass('active');
                } else {
                    // Re-initialize mobile tabs if needed
                    if (!$('.mpcc-mobile-tabs').length) {
                        this.initializeMobileTabs();
                    }
                }
            }, 250));
        },
        
        handleMobileTabClick: function(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const tab = $button.data('tab');
            
            // Update active button
            $('.mpcc-mobile-tab-button').removeClass('active');
            $button.addClass('active');
            
            // Show/hide appropriate content
            if (tab === 'chat') {
                $('.mpcc-editor-sidebar').addClass('mobile-active');
                $('.mpcc-editor-main').removeClass('mobile-active');
            } else if (tab === 'preview') {
                $('.mpcc-editor-sidebar').removeClass('mobile-active');
                $('.mpcc-editor-main').addClass('mobile-active');
            }
        },
        
        switchToPreviewTab: function() {
            // Update ARIA attributes when switching tabs
            $('.mpcc-tab-button').attr('aria-selected', 'false');
            $('.mpcc-tab-button[data-tab="main"]').attr('aria-selected', 'true');
            
            $('.mpcc-editor-sidebar').attr('aria-hidden', 'true');
            $('.mpcc-editor-main').attr('aria-hidden', 'false');
            
            // Only switch if on mobile
            if (window.innerWidth <= 960 && $('.mpcc-mobile-tabs').length) {
                // Click the preview tab button
                $('.mpcc-mobile-tab-button[data-tab="preview"]').click();
                
                // Show a toast notification
                MPCCUtils.showSuccess('Course structure updated! Showing preview.');
            }
        },
        
        /**
         * Initialize comprehensive keyboard navigation
         */
        initializeKeyboardNavigation: function() {
            // Global keyboard handlers for the editor
            $(document).on('keydown.mpcc-editor-keyboard', (e) => {
                // ESC key handling
                if (e.key === 'Escape') {
                    // Close lesson editor if open
                    if ($('#mpcc-lesson-editor').is(':visible')) {
                        e.preventDefault();
                        this.closeLessonEditor();
                        MPCCAccessibility.announce('Lesson editor closed');
                    }
                    // Close session modal if open
                    else if ($('.mpcc-sessions-modal-overlay').hasClass('active')) {
                        e.preventDefault();
                        this.closeSessionModal();
                        MPCCAccessibility.announce('Session history closed');
                    }
                }
            });
            
            // Chat input keyboard navigation
            $('#mpcc-chat-input').on('keydown', (e) => {
                // Ctrl/Cmd + Enter to send
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    this.sendMessage();
                    MPCCAccessibility.announce('Message sent');
                    return false;
                }
                
                // Shift+Enter for new line in chat (normal Enter already sends)
                if (e.key === 'Enter' && e.shiftKey) {
                    // Default behavior creates new line
                    return true;
                }
                
                // Arrow key navigation for message history
                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.navigateMessageHistory('up');
                    return false;
                }
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.navigateMessageHistory('down');
                    return false;
                }
                
                // Enter key to confirm selection when navigating history
                if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
                    // Check if we're in history navigation mode
                    if (this.messageHistoryIndex < this.messageHistory.length && this.messageHistoryIndex >= 0) {
                        // We have a selected message from history
                        const currentValue = e.target.value;
                        const expectedHistoryValue = this.messageHistory[this.messageHistoryIndex];
                        
                        // Only prevent default if the current value matches our history value
                        if (currentValue === expectedHistoryValue) {
                            // Reset history index to allow normal send
                            this.messageHistoryIndex = this.messageHistory.length;
                            $('.mpcc-chat-message').removeClass('mpcc-history-highlight');
                            MPCCAccessibility.announce('Message copied from history');
                        }
                    }
                    // Normal enter behavior will send the message
                }
            });
            
            // Lesson textarea keyboard navigation
            $('#mpcc-lesson-textarea').on('keydown', (e) => {
                // Shift+Enter for new line
                if (e.key === 'Enter' && e.shiftKey) {
                    // Default behavior creates new line
                    return true;
                }
                // Ctrl/Cmd+S to save
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    this.saveLesson();
                    MPCCAccessibility.announce('Lesson saved');
                }
            });
            
            // Course structure keyboard navigation
            this.initializeCourseStructureKeyboard();
            
            // Session list keyboard navigation
            this.initializeSessionListKeyboard();
            
            // Quick starter buttons keyboard navigation
            this.enhanceQuickStarterKeyboard();
            
            // Form elements keyboard enhancement
            this.enhanceFormKeyboard();
        },
        
        /**
         * Initialize keyboard navigation for course structure
         */
        initializeCourseStructureKeyboard: function() {
            // Make sections and lessons keyboard navigable
            $(document).on('focusin', '.mpcc-section, .mpcc-lesson-item', function() {
                const $item = $(this);
                if (!$item.attr('tabindex')) {
                    $item.attr('tabindex', '0');
                }
            });
            
            // Keyboard handlers for sections
            $(document).on('keydown', '.mpcc-section', (e) => {
                const $section = $(e.currentTarget);
                
                switch(e.key) {
                    case 'Enter':
                    case ' ':
                        // Toggle section expand/collapse if applicable
                        e.preventDefault();
                        const $header = $section.find('.mpcc-section-header');
                        if ($header.length) {
                            $header.click();
                        }
                        break;
                        
                    case 'ArrowDown':
                        e.preventDefault();
                        this.navigateToNextItem($section, '.mpcc-section');
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        this.navigateToPreviousItem($section, '.mpcc-section');
                        break;
                        
                    case 'Delete':
                        if (!this.publishedCourseId && e.shiftKey) {
                            e.preventDefault();
                            const sectionIndex = parseInt($section.data('section-index'));
                            this.handleDeleteSection(sectionIndex);
                        }
                        break;
                }
            });
            
            // Keyboard handlers for lessons
            $(document).on('keydown', '.mpcc-lesson-item', (e) => {
                const $lesson = $(e.currentTarget);
                
                switch(e.key) {
                    case 'Enter':
                        e.preventDefault();
                        if (!this.publishedCourseId) {
                            const lessonId = $lesson.data('lesson-id');
                            this.editLesson(lessonId);
                            MPCCAccessibility.announce('Opening lesson editor');
                        }
                        break;
                        
                    case ' ':
                        e.preventDefault();
                        // Select/deselect lesson if in selection mode
                        $lesson.toggleClass('selected');
                        const isSelected = $lesson.hasClass('selected');
                        MPCCAccessibility.announce(isSelected ? 'Lesson selected' : 'Lesson deselected');
                        break;
                        
                    case 'ArrowDown':
                        e.preventDefault();
                        this.navigateToNextLesson($lesson);
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        this.navigateToPreviousLesson($lesson);
                        break;
                        
                    case 'ArrowLeft':
                        e.preventDefault();
                        // Navigate to parent section
                        const $parentSection = $lesson.closest('.mpcc-section');
                        if ($parentSection.length) {
                            $parentSection.focus();
                            MPCCAccessibility.announce('Navigated to section');
                        }
                        break;
                        
                    case 'Delete':
                        if (!this.publishedCourseId && e.shiftKey) {
                            e.preventDefault();
                            const $deleteBtn = $lesson.find('.mpcc-delete-lesson');
                            if ($deleteBtn.length) {
                                $deleteBtn.click();
                            }
                        }
                        break;
                }
            });
        },
        
        /**
         * Navigate to next lesson
         */
        navigateToNextLesson: function($currentLesson) {
            const $allLessons = $('.mpcc-lesson-item');
            const currentIndex = $allLessons.index($currentLesson);
            
            if (currentIndex < $allLessons.length - 1) {
                const $nextLesson = $allLessons.eq(currentIndex + 1);
                $nextLesson.focus();
                MPCCAccessibility.announce('Navigated to next lesson: ' + $nextLesson.find('.mpcc-lesson-title').text());
            } else {
                // Wrap to first lesson
                const $firstLesson = $allLessons.first();
                $firstLesson.focus();
                MPCCAccessibility.announce('Navigated to first lesson: ' + $firstLesson.find('.mpcc-lesson-title').text());
            }
        },
        
        /**
         * Navigate to previous lesson
         */
        navigateToPreviousLesson: function($currentLesson) {
            const $allLessons = $('.mpcc-lesson-item');
            const currentIndex = $allLessons.index($currentLesson);
            
            if (currentIndex > 0) {
                const $prevLesson = $allLessons.eq(currentIndex - 1);
                $prevLesson.focus();
                MPCCAccessibility.announce('Navigated to previous lesson: ' + $prevLesson.find('.mpcc-lesson-title').text());
            } else {
                // Wrap to last lesson
                const $lastLesson = $allLessons.last();
                $lastLesson.focus();
                MPCCAccessibility.announce('Navigated to last lesson: ' + $lastLesson.find('.mpcc-lesson-title').text());
            }
        },
        
        /**
         * Navigate to next item of given type
         */
        navigateToNextItem: function($current, selector) {
            const $items = $(selector);
            const currentIndex = $items.index($current);
            
            if (currentIndex < $items.length - 1) {
                $items.eq(currentIndex + 1).focus();
            } else {
                $items.first().focus();
            }
        },
        
        /**
         * Navigate to previous item of given type
         */
        navigateToPreviousItem: function($current, selector) {
            const $items = $(selector);
            const currentIndex = $items.index($current);
            
            if (currentIndex > 0) {
                $items.eq(currentIndex - 1).focus();
            } else {
                $items.last().focus();
            }
        },
        
        /**
         * Initialize keyboard navigation for session list
         */
        initializeSessionListKeyboard: function() {
            // Session modal keyboard navigation
            $(document).on('keydown', '.mpcc-sessions-modal', (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeSessionModal();
                }
            });
            
            // Session items keyboard navigation
            $(document).on('keydown', '.mpcc-session-item', (e) => {
                const $item = $(e.currentTarget);
                
                switch(e.key) {
                    case 'Enter':
                        e.preventDefault();
                        $item.click();
                        break;
                        
                    case 'Delete':
                        if (e.shiftKey) {
                            e.preventDefault();
                            const $deleteBtn = $item.find('.mpcc-session-delete');
                            if ($deleteBtn.length) {
                                $deleteBtn.click();
                            }
                        }
                        break;
                        
                    case 'ArrowDown':
                        e.preventDefault();
                        this.navigateToNextItem($item, '.mpcc-session-item');
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        this.navigateToPreviousItem($item, '.mpcc-session-item');
                        break;
                }
            });
            
            // Make session items focusable
            $(document).on('focusin', '.mpcc-session-item', function() {
                if (!$(this).attr('tabindex')) {
                    $(this).attr('tabindex', '0');
                }
            });
        },
        
        /**
         * Enhance quick starter buttons keyboard navigation
         */
        enhanceQuickStarterKeyboard: function() {
            // Arrow key navigation between quick starter buttons
            $(document).on('keydown', '.mpcc-quick-starter-btn', (e) => {
                const $btn = $(e.currentTarget);
                const $buttons = $('.mpcc-quick-starter-btn');
                const currentIndex = $buttons.index($btn);
                
                switch(e.key) {
                    case 'ArrowRight':
                        e.preventDefault();
                        if (currentIndex < $buttons.length - 1) {
                            $buttons.eq(currentIndex + 1).focus();
                        } else {
                            $buttons.first().focus();
                        }
                        break;
                        
                    case 'ArrowLeft':
                        e.preventDefault();
                        if (currentIndex > 0) {
                            $buttons.eq(currentIndex - 1).focus();
                        } else {
                            $buttons.last().focus();
                        }
                        break;
                        
                    case 'ArrowDown':
                        e.preventDefault();
                        // Move focus to chat input
                        $('#mpcc-chat-input').focus();
                        break;
                }
            });
        },
        
        /**
         * Enhance form elements keyboard navigation
         */
        enhanceFormKeyboard: function() {
            // Tab navigation hints for lesson editor
            $('#mpcc-lesson-editor').on('shown', () => {
                MPCCAccessibility.announce('Lesson editor opened. Press Tab to navigate fields, Shift+Tab to go back');
            });
            
            // Focus management when opening modals
            $('.mpcc-sessions-modal-overlay').on('shown', () => {
                const $firstFocusable = $('.mpcc-sessions-list').find('button, [tabindex="0"]').first();
                if ($firstFocusable.length) {
                    $firstFocusable.focus();
                }
            });
        },
        
        /**
         * Navigate through message history using arrow keys
         */
        navigateMessageHistory: function(direction) {
            if (this.messageHistory.length === 0) return;
            
            const $input = $('#mpcc-chat-input');
            const $messages = $('.mpcc-chat-message.user');
            
            if (direction === 'up') {
                // Move up in history (older messages)
                if (this.messageHistoryIndex > 0) {
                    this.messageHistoryIndex--;
                    const historicalMessage = this.messageHistory[this.messageHistoryIndex];
                    $input.val(historicalMessage);
                    
                    // Highlight the corresponding message
                    this.highlightHistoryMessage(this.messageHistoryIndex);
                    
                    // Announce to screen reader
                    MPCCAccessibility.announce(`Navigated to previous message: ${historicalMessage.substring(0, 50)}...`);
                }
            } else if (direction === 'down') {
                // Move down in history (newer messages)
                if (this.messageHistoryIndex < this.messageHistory.length - 1) {
                    this.messageHistoryIndex++;
                    const historicalMessage = this.messageHistory[this.messageHistoryIndex];
                    $input.val(historicalMessage);
                    
                    // Highlight the corresponding message
                    this.highlightHistoryMessage(this.messageHistoryIndex);
                    
                    // Announce to screen reader
                    MPCCAccessibility.announce(`Navigated to next message: ${historicalMessage.substring(0, 50)}...`);
                } else if (this.messageHistoryIndex === this.messageHistory.length - 1) {
                    // At the end of history, clear input
                    this.messageHistoryIndex = this.messageHistory.length;
                    $input.val('');
                    
                    // Remove all highlights
                    $('.mpcc-chat-message').removeClass('mpcc-history-highlight');
                    
                    MPCCAccessibility.announce('Cleared message input');
                }
            }
        },
        
        /**
         * Highlight a message in the chat history
         */
        highlightHistoryMessage: function(index) {
            // Remove all existing highlights
            $('.mpcc-chat-message').removeClass('mpcc-history-highlight');
            
            // Find and highlight the user message at the given index
            const $userMessages = $('.mpcc-chat-message.user');
            if (index >= 0 && index < $userMessages.length) {
                const $targetMessage = $userMessages.eq(index);
                $targetMessage.addClass('mpcc-history-highlight');
                
                // Scroll the message into view
                const messagesContainer = $('#mpcc-chat-messages')[0];
                const messageElement = $targetMessage[0];
                
                if (messageElement && messagesContainer) {
                    const messageTop = messageElement.offsetTop;
                    const messageHeight = messageElement.offsetHeight;
                    const containerHeight = messagesContainer.clientHeight;
                    const scrollTop = messagesContainer.scrollTop;
                    
                    // Check if message is not fully visible
                    if (messageTop < scrollTop || messageTop + messageHeight > scrollTop + containerHeight) {
                        // Scroll to center the message in view
                        messagesContainer.scrollTop = messageTop - (containerHeight / 2) + (messageHeight / 2);
                    }
                }
            }
        }
    };
    
    // Destroy/cleanup method for teardown
    CourseEditor.destroy = function() {
        // Remove all event handlers
        $(document).off('click.mpcc-editor-send');
        $(document).off('keypress.mpcc-editor-input');
        $(document).off('keydown.mpcc-editor-keyboard');
        $(document).off('click.mpcc-mobile-tabs');
        $(window).off('resize.mpcc-mobile-tabs');
        $('#mpcc-new-session').off('click');
        $('#mpcc-session-history').off('click');
        $('#mpcc-previous-conversations').off('click');
        $(document).off('click', '.mpcc-quick-starter-btn');
        $('#mpcc-create-course').off('click');
        $('#mpcc-duplicate-course').off('click');
        $(document).off('click', '.mpcc-lesson-item');
        $('#mpcc-generate-lesson-content').off('click');
        $('#mpcc-save-lesson').off('click');
        $('#mpcc-cancel-lesson, #mpcc-close-lesson').off('click');
        $('#mpcc-lesson-textarea').off('input.autosave');
        $(document).off('click', '.mpcc-section-actions button');
        $(document).off('click', '.mpcc-edit-lesson');
        $(document).off('click', '.mpcc-delete-lesson');
        $(document).off('keydown', '.mpcc-section');
        $(document).off('keydown', '.mpcc-lesson-item');
        $(document).off('keydown', '.mpcc-sessions-modal');
        $(document).off('keydown', '.mpcc-session-item');
        $(document).off('keydown', '.mpcc-quick-starter-btn');
        $(document).off('focusin', '.mpcc-section, .mpcc-lesson-item');
        $(document).off('focusin', '.mpcc-session-item');
        
        // Clear any active timers
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
        }
        
        // Destroy sortable if it exists
        if ($.fn.sortable) {
            $('#mpcc-course-structure').sortable('destroy');
            $('.mpcc-lessons').sortable('destroy');
        }
        
        // Clear references to prevent memory leaks
        this.conversationHistory = null;
        this.courseStructure = null;
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
            
            // Cleanup on page unload
            $(window).on('beforeunload', function() {
                if (window.CourseEditor && window.CourseEditor.destroy) {
                    window.CourseEditor.destroy();
                }
            });
        }
    });
    
})(jQuery);