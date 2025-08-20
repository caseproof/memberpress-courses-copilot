/**
 * MemberPress Courses Copilot - AI Chat Interface
 * Advanced real-time chat interface with course preview and voice support
 *
 * @package MemberPressCoursesCopilot
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Debug logging
    console.log('AI Copilot loading...', {
        mpccCoursesIntegration: typeof mpccCoursesIntegration !== 'undefined',
        mpccAISettings: typeof mpccAISettings !== 'undefined',
        jQuery: typeof jQuery !== 'undefined',
        $: typeof $ !== 'undefined'
    });

    class AICopilot {
        constructor() {
            console.log('AICopilot constructor called');
            
            // Core state
            this.selectedTemplate = null;
            this.currentCourse = null;
            this.chatHistory = [];
            this.isGenerating = false;
            this.sessionId = this.generateSessionId();
            
            // Voice support
            this.isRecording = false;
            this.mediaRecorder = null;
            this.audioChunks = [];
            this.speechRecognition = null;
            
            // Drag and drop
            this.draggedElement = null;
            this.dragPlaceholder = null;
            
            // Progress tracking
            this.currentStep = 0;
            this.totalSteps = 5;
            this.progressSteps = [
                'Template Selection',
                'Course Overview',
                'Content Structure',
                'Lesson Details',
                'Final Review'
            ];
            
            // Theme state
            this.currentTheme = localStorage.getItem('mpcc_theme') || 'light';
            
            // Connection state
            this.connectionStatus = 'disconnected';
            this.lastPingTime = null;
            
            this.init();
        }

        init() {
            this.initializeInterface();
            this.bindEvents();
            // this.setupWordCounter();
            this.loadChatHistory();
            // Removed unnecessary features
            // this.initializeVoiceSupport();
            // this.initializeDragDrop();
            // this.setupTheme();
            this.startConnectionMonitoring();
            // this.setupKeyboardShortcuts();
            // this.initializeProgressTracking();
        }

        initializeInterface() {
            // Create basic chat interface elements only
            // Removed unnecessary UI elements like voice, theme toggle, etc.
            this.createConnectionStatus();
        }

        bindEvents() {
            // Only bind essential events
            
            // Chat input and actions
            $('#mpcc-chat-input').on('keydown', this.handleChatKeydown.bind(this));
            $('#mpcc-send-message').on('click', this.handleSendMessage.bind(this));

            // Auto-save
            setInterval(this.autoSave.bind(this), 30000); // Auto-save every 30 seconds
        }

        createVoiceButton() {
            if (!this.isVoiceSupported()) return;

            const voiceButton = `
                <button id="mpcc-voice-button" class="button mpcc-voice-btn" type="button" title="Voice Input">
                    <span class="dashicons dashicons-microphone"></span>
                    <span class="mpcc-voice-indicator"></span>
                </button>
            `;
            $('.mpcc-chat-input-wrapper').append(voiceButton);
        }

        createProgressIndicator() {
            const progressHTML = `
                <div class="mpcc-progress-container">
                    <div class="mpcc-progress-bar">
                        <div class="mpcc-progress-fill" style="width: 0%"></div>
                    </div>
                    <div class="mpcc-progress-steps">
                        ${this.progressSteps.map((step, index) => `
                            <div class="mpcc-progress-step" data-step="${index}">
                                <div class="mpcc-step-indicator">${index + 1}</div>
                                <div class="mpcc-step-label">${step}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            $('.mpcc-chat-header').after(progressHTML);
        }

        createConnectionStatus() {
            const statusHTML = `
                <div id="mpcc-connection-status" class="mpcc-connection-status disconnected">
                    <span class="mpcc-status-indicator"></span>
                    <span class="mpcc-status-text">Connecting...</span>
                </div>
            `;
            $('.mpcc-chat-header').prepend(statusHTML);
        }

        createThemeToggle() {
            const themeToggle = `
                <button id="mpcc-theme-toggle" class="button mpcc-icon-btn" type="button" title="Toggle Theme">
                    <span class="dashicons dashicons-admin-appearance"></span>
                </button>
            `;
            $('.mpcc-preview-actions').append(themeToggle);
        }

        createFullscreenToggle() {
            const fullscreenToggle = `
                <button id="mpcc-fullscreen-toggle" class="button mpcc-icon-btn" type="button" title="Fullscreen">
                    <span class="dashicons dashicons-fullscreen-alt"></span>
                </button>
            `;
            $('.mpcc-preview-actions').append(fullscreenToggle);
        }

        enhanceChatInput() {
            // Add emoji picker, formatting options, file upload
            const enhancedInputHTML = `
                <div class="mpcc-input-enhancements">
                    <button type="button" class="mpcc-input-tool" data-tool="bold" title="Bold">
                        <strong>B</strong>
                    </button>
                    <button type="button" class="mpcc-input-tool" data-tool="italic" title="Italic">
                        <em>I</em>
                    </button>
                    <button type="button" class="mpcc-input-tool" data-tool="emoji" title="Emoji">
                        😊
                    </button>
                    <button type="button" class="mpcc-input-tool" data-tool="attachment" title="Attach File">
                        📎
                    </button>
                </div>
            `;
            $('.mpcc-chat-input-wrapper').before(enhancedInputHTML);

            // Add character counter and suggestions
            const inputFooterHTML = `
                <div class="mpcc-input-footer">
                    <div class="mpcc-input-suggestions"></div>
                    <div class="mpcc-input-stats">
                        <span class="mpcc-char-count">0/2000</span>
                        <span class="mpcc-word-count">0 words</span>
                    </div>
                </div>
            `;
            $('.mpcc-chat-input-wrapper').after(inputFooterHTML);

            // Bind enhancement events
            $(document).on('click', '.mpcc-input-tool', this.handleInputTool.bind(this));
        }

        handleTemplateSelection(e) {
            const $card = $(e.currentTarget);
            const template = $card.data('template');
            const templateName = $card.find('h4').text();

            // Update selection with animation
            $('.mpcc-template-card').removeClass('selected').addClass('mpcc-card-fade');
            setTimeout(() => {
                $('.mpcc-template-card').removeClass('mpcc-card-fade');
                $card.addClass('selected');
            }, 150);

            this.selectedTemplate = template;
            this.updateProgress(1);

            // Add template selection message with enhanced formatting
            const templateMessage = `I'd like to create a **${templateName}** course. Let's build something amazing together! 🚀`;
            this.addMessage('user', templateMessage);

            // Generate contextual AI response
            const contextualPrompt = this.getTemplateContextualPrompt(template);
            this.generateAIResponse(contextualPrompt);
        }

        handleTemplatePreview(e) {
            const $card = $(e.currentTarget);
            const template = $card.data('template');
            this.showTemplatePreview(template);
        }

        showTemplatePreview(template) {
            // Create modal preview of template
            const previewData = this.getTemplatePreviewData(template);
            const modalHTML = `
                <div class="mpcc-modal-overlay">
                    <div class="mpcc-modal mpcc-template-preview-modal">
                        <div class="mpcc-modal-header">
                            <h3>${previewData.title}</h3>
                            <button class="mpcc-modal-close">&times;</button>
                        </div>
                        <div class="mpcc-modal-body">
                            <div class="mpcc-template-preview-content">
                                ${previewData.content}
                            </div>
                        </div>
                        <div class="mpcc-modal-footer">
                            <button class="button button-primary mpcc-select-template" data-template="${template}">
                                Select This Template
                            </button>
                            <button class="button mpcc-modal-close">Cancel</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHTML);
            
            // Bind modal events
            $('.mpcc-modal-close').on('click', () => $('.mpcc-modal-overlay').remove());
            $('.mpcc-select-template').on('click', (e) => {
                const template = $(e.target).data('template');
                $(`.mpcc-template-card[data-template="${template}"]`).trigger('click');
                $('.mpcc-modal-overlay').remove();
            });
        }

        handleChatKeydown(e) {
            // Simple keyboard handling - only handle Enter key
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.handleSendMessage();
            }
        }

        handleSendMessage() {
            const message = $('#mpcc-chat-input').val().trim();
            
            if (!message || this.isGenerating) {
                return;
            }
            
            // Prevent duplicate messages
            const $sendButton = $('#mpcc-send-message');
            if ($sendButton.prop('disabled')) {
                return;
            }
            $sendButton.prop('disabled', true);

            // Validate message length
            if (message.length > 2000) {
                this.showNotification('Message too long. Please keep it under 2000 characters.', 'error');
                $sendButton.prop('disabled', false);
                return;
            }

            // Add user message with timestamp and enhanced formatting
            this.addMessage('user', message);

            // Clear input and update counters
            $('#mpcc-chat-input').val('');
            this.updateInputStats();

            // Show typing indicator
            this.showTypingIndicator();

            // Generate AI response with context
            this.generateAIResponse(message);
        }

        handleVoiceToggle() {
            if (this.isRecording) {
                this.stopVoiceRecording();
            } else {
                this.startVoiceRecording();
            }
        }

        startVoiceRecording() {
            if (!this.isVoiceSupported()) {
                this.showNotification('Voice input is not supported in your browser', 'error');
                return;
            }

            this.isRecording = true;
            $('#mpcc-voice-button').addClass('recording');
            $('.mpcc-voice-indicator').addClass('active');

            // Use Web Speech API if available
            if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
                this.startSpeechRecognition();
            } else {
                this.startMediaRecording();
            }
        }

        startSpeechRecognition() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.speechRecognition = new SpeechRecognition();
            
            this.speechRecognition.continuous = true;
            this.speechRecognition.interimResults = true;
            this.speechRecognition.lang = 'en-US';

            this.speechRecognition.onresult = (event) => {
                let finalTranscript = '';
                let interimTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; i++) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }

                $('#mpcc-chat-input').val(finalTranscript);
                this.updateInputStats();
            };

            this.speechRecognition.onerror = (event) => {
                console.error('Speech recognition error:', event.error);
                this.stopVoiceRecording();
                this.showNotification('Voice recognition error. Please try again.', 'error');
            };

            this.speechRecognition.start();
        }

        stopVoiceRecording() {
            this.isRecording = false;
            $('#mpcc-voice-button').removeClass('recording');
            $('.mpcc-voice-indicator').removeClass('active');

            if (this.speechRecognition) {
                this.speechRecognition.stop();
            }

            if (this.mediaRecorder && this.mediaRecorder.state === 'recording') {
                this.mediaRecorder.stop();
            }
        }

        generateAIResponse(userMessage) {
            console.log('MPCC Debug: generateAIResponse called with:', userMessage);
            
            if (this.isGenerating) {
                console.log('MPCC Debug: Already generating, skipping');
                return;
            }

            this.isGenerating = true;
            this.updateConnectionStatus('generating');
            console.log('MPCC Debug: Starting AI request');

            const requestData = {
                action: 'mpcc_ai_chat',
                nonce: this.getNonce(),
                message: userMessage,
                context: 'course_creation',
                conversation_history: this.chatHistory.slice(-20), // Increased context window
                conversation_state: {
                    current_step: this.currentStep || 'initial',
                    collected_data: this.currentCourse || {}
                },
                template: this.selectedTemplate,
                session_id: this.sessionId,
                user_preferences: this.getUserPreferences()
            };

            $.ajax({
                url: this.getAjaxUrl(),
                type: 'POST',
                data: requestData,
                timeout: 90000, // 90 seconds for complex generation
                success: this.handleAIResponse.bind(this),
                error: this.handleAIError.bind(this),
                complete: () => {
                    this.isGenerating = false;
                    this.hideTypingIndicator();
                    this.updateConnectionStatus('connected');
                    // Re-enable send button
                    $('#mpcc-send-message').prop('disabled', false);
                }
            });
        }

        handleAIResponse(response) {
            console.log('MPCC Debug: AI Response received', response);
            
            if (response.success) {
                const data = response.data;
                console.log('MPCC Debug: Success response data', data);
                console.log('MPCC Debug: AI message content', data.message);

                // Add AI message with enhanced formatting
                this.addMessage('assistant', data.message, null, {
                    suggestions: data.suggestions,
                    actions: data.actions
                });

                // Update course preview with animations
                if (data.course_data) {
                    this.updateCoursePreview(data.course_data, true);
                    this.currentCourse = data.course_data;
                }
                
                // Trigger preview panel update if flagged
                if (data.update_preview && data.course_data) {
                    this.triggerPreviewUpdate(data.course_data);
                }
                
                // Update conversation state
                if (data.conversation_state) {
                    this.currentStep = data.conversation_state.current_step;
                    if (data.conversation_state.collected_data) {
                        this.currentCourse = {...this.currentCourse, ...data.conversation_state.collected_data};
                    }
                }

                // Update progress tracking
                if (data.progress) {
                    this.updateProgress(data.progress.step, data.progress.percentage);
                }

                // Handle special response types
                if (data.type === 'course_complete') {
                    this.handleCourseCompletion(data);
                } else if (data.type === 'clarification_needed') {
                    this.showClarificationDialog(data.clarifications);
                }

                // Enable action buttons if course is ready
                if (data.ready_to_create || (data.course_data && data.course_data.sections)) {
                    this.enableCourseActions();
                }
                
                // Handle action buttons from server
                if (data.actions && data.actions.length > 0) {
                    this.showCourseActions(data.actions);
                }

                // Auto-save progress
                this.autoSave();

            } else {
                console.log('MPCC Debug: AI Response failed', response);
                this.handleAIError(null, 'server_error', response.data);
            }
        }

        handleAIError(xhr, status, error) {
            console.error('AI Response Error:', { xhr, status, error });
            
            let errorMessage = 'I encountered an error. Please try again.';
            let suggestion = null;

            // Enhanced error handling with specific messages and suggestions
            if (status === 'timeout') {
                errorMessage = 'The request took too long. Let me try to help you with a shorter response.';
                suggestion = 'Try breaking your request into smaller parts';
            } else if (xhr && xhr.status === 429) {
                errorMessage = 'Too many requests. Please wait a moment before trying again.';
                suggestion = 'Rate limit exceeded - try again in 30 seconds';
            } else if (xhr && xhr.status === 500) {
                errorMessage = 'Server error occurred. Our team has been notified.';
                suggestion = 'Try refreshing the page if the problem persists';
            } else if (xhr && xhr.status === 0) {
                errorMessage = 'Connection lost. Please check your internet connection.';
                suggestion = 'Check your network connection';
            }

            this.addMessage('assistant', errorMessage, null, {
                type: 'error',
                suggestion: suggestion,
                actions: ['retry', 'contact_support']
            });

            this.updateConnectionStatus('error');
        }

        addMessage(type, content, timestamp = null, options = {}) {
            const time = timestamp || this.formatTime(new Date());
            const messageId = this.generateMessageId();
            const avatar = type === 'user' ? 'admin-users' : 'superhero-alt';

            // Enhanced message with actions and rich content
            const messageHtml = `
                <div class="mpcc-message mpcc-message-${type}" data-message-id="${messageId}">
                    <div class="mpcc-message-avatar">
                        <span class="dashicons dashicons-${avatar}"></span>
                    </div>
                    <div class="mpcc-message-content">
                        ${this.formatMessageContent(content)}
                        ${this.renderMessageOptions(options)}
                    </div>
                    <div class="mpcc-message-meta">
                        <div class="mpcc-message-time">${time}</div>
                        <div class="mpcc-message-actions">
                            ${this.renderMessageActions(type, options)}
                        </div>
                    </div>
                </div>
            `;

            $('#mpcc-chat-messages').append(messageHtml);
            this.animateMessageEntry(messageId);
            this.scrollToBottom();

            // Store in history with enhanced metadata
            this.chatHistory.push({
                id: messageId,
                type: type,
                content: content,
                timestamp: timestamp || Date.now(),
                session_id: this.sessionId,
                options: options
            });

            this.saveChatHistory();
        }

        formatMessageContent(content) {
            // Enhanced markdown-like formatting with syntax highlighting
            return content
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`(.*?)`/g, '<code>$1</code>')
                .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>')
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>')
                .replace(/^### (.*$)/gm, '<h4>$1</h4>')
                .replace(/^## (.*$)/gm, '<h3>$1</h3>')
                .replace(/^# (.*$)/gm, '<h2>$1</h2>')
                .replace(/^\- (.*$)/gm, '<li>$1</li>')
                .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
                .replace(/\n/g, '<br>')
                .replace(/:([\w+-]+):/g, (match, emoji) => this.convertEmoji(emoji));
        }

        renderMessageOptions(options) {
            if (!options.suggestions && !options.actions) return '';

            let html = '';

            if (options.suggestions && options.suggestions.length > 0) {
                html += `
                    <div class="mpcc-message-suggestions">
                        <div class="mpcc-suggestions-label">Quick replies:</div>
                        <div class="mpcc-suggestions-list">
                            ${options.suggestions.map(suggestion => `
                                <button class="mpcc-suggestion-btn" data-suggestion="${suggestion}">
                                    ${suggestion}
                                </button>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            if (options.actions && options.actions.length > 0) {
                html += `
                    <div class="mpcc-message-actions-list">
                        ${options.actions.map(action => `
                            <button class="mpcc-action-btn" data-action="${action}">
                                ${this.getActionLabel(action)}
                            </button>
                        `).join('')}
                    </div>
                `;
            }

            return html;
        }

        renderMessageActions(type, options) {
            const actions = ['copy'];
            
            if (type === 'assistant') {
                actions.push('regenerate', 'thumbs_up', 'thumbs_down');
            }
            
            if (type === 'user') {
                actions.push('edit');
            }

            return actions.map(action => `
                <button class="mpcc-message-action" data-action="${action}" title="${this.getActionTitle(action)}">
                    <span class="dashicons dashicons-${this.getActionIcon(action)}"></span>
                </button>
            `).join('');
        }

        updateCoursePreview(courseData, animated = false) {
            this.currentCourse = courseData;

            if (animated) {
                $('#mpcc-preview-content').addClass('mpcc-updating');
            }

            const template = $('#mpcc-course-preview-template').html();
            
            if (template) {
                let html = this.processTemplate(template, courseData);
                
                setTimeout(() => {
                    $('#mpcc-preview-content').html(html);
                    $('#mpcc-preview-content').removeClass('mpcc-updating');
                    this.initializeDragDropForPreview();
                    this.updateCourseMetrics(courseData);
                }, animated ? 300 : 0);
            }
        }

        triggerPreviewUpdate(courseData) {
            // Trigger the global event system that preview-integration.js listens for
            $(document).trigger('mpcc:courseUpdated', [courseData, {
                source: 'ai_chat',
                animated: true,
                context: 'course_creation'
            }]);
            
            // Also update via the global preview instance if available
            if (window.mpcc && window.mpcc.coursePreview) {
                window.mpcc.coursePreview.updateCourse(courseData);
            }
            
            // Update the preview panel directly via AJAX if it exists
            const previewContainer = $('#mpcc-preview-content');
            if (previewContainer.length > 0) {
                // Call the update preview AJAX handler
                $.ajax({
                    url: mpccAdmin.ajaxUrl || (typeof mpccCoursePreview !== 'undefined' ? mpccCoursePreview.ajaxUrl : ajaxurl),
                    type: 'POST',
                    data: {
                        action: 'mpcc_update_course_preview',
                        nonce: (typeof mpccCoursePreview !== 'undefined' ? mpccCoursePreview.nonce : mpccAdmin.nonce),
                        course_data: courseData,
                        context: 'course_creation',
                        update_type: 'full'
                    },
                    success: function(response) {
                        if (response.success && response.data.html) {
                            previewContainer.html(response.data.html);
                            // Reinitialize any JavaScript in the updated content
                            if (window.MPCCCoursePreview && window.MPCCCoursePreview.init) {
                                window.MPCCCoursePreview.init();
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.warn('Preview update failed:', error);
                    }
                });
            }
            
            // Update course metrics in any existing metric displays
            this.updateCourseMetrics(courseData);
        }

        processTemplate(template, courseData) {
            // Enhanced template processing with better handling
            let html = template
                .replace(/{{title}}/g, courseData.title || 'Untitled Course')
                .replace(/{{description}}/g, courseData.description || '')
                .replace(/{{duration}}/g, courseData.duration || 'TBD')
                .replace(/{{difficulty}}/g, courseData.difficulty || 'Beginner')
                .replace(/{{price}}/g, courseData.price || 'Free')
                .replace(/{{category}}/g, courseData.category || 'General');

            // Calculate metrics
            const totalLessons = (courseData.sections || []).reduce((total, section) => 
                total + (section.lessons || []).length, 0);
            const estimatedHours = Math.ceil(totalLessons * 0.5); // 30 min per lesson average

            html = html
                .replace(/{{lessonsCount}}/g, totalLessons)
                .replace(/{{estimatedHours}}/g, estimatedHours);

            // Process objectives
            if (courseData.objectives && courseData.objectives.length > 0) {
                const objectivesList = courseData.objectives.map(obj => 
                    `<li class="mpcc-objective-item">${obj}</li>`).join('');
                html = html.replace(/{{#each objectives}}.*?{{\/each}}/s, 
                    `<ul class="mpcc-objectives-list">${objectivesList}</ul>`);
            }

            // Process sections with drag-drop capability
            if (courseData.sections && courseData.sections.length > 0) {
                const sectionsHtml = this.renderCourseSections(courseData.sections);
                html = html.replace(/{{#each sections}}.*?{{\/each}}/s, sectionsHtml);
            }

            return html;
        }

        renderCourseSections(sections) {
            return sections.map((section, sectionIndex) => `
                <div class="mpcc-section mpcc-draggable" data-type="section" data-index="${sectionIndex}" draggable="true">
                    <div class="mpcc-section-header">
                        <div class="mpcc-drag-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                        <h5 class="mpcc-section-title">${section.title}</h5>
                        <div class="mpcc-section-actions">
                            <button class="mpcc-edit-section" data-index="${sectionIndex}">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <button class="mpcc-delete-section" data-index="${sectionIndex}">
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                        </div>
                    </div>
                    <p class="mpcc-section-description">${section.description}</p>
                    <div class="mpcc-section-lessons mpcc-drop-zone" data-section="${sectionIndex}">
                        ${this.renderSectionLessons(section.lessons || [], sectionIndex)}
                    </div>
                    <button class="mpcc-add-lesson" data-section="${sectionIndex}">
                        <span class="dashicons dashicons-plus"></span> Add Lesson
                    </button>
                </div>
            `).join('');
        }

        renderSectionLessons(lessons, sectionIndex) {
            return lessons.map((lesson, lessonIndex) => `
                <div class="mpcc-lesson mpcc-draggable" data-type="lesson" data-section="${sectionIndex}" data-index="${lessonIndex}" draggable="true">
                    <div class="mpcc-lesson-content">
                        <div class="mpcc-drag-handle">
                            <span class="dashicons dashicons-menu"></span>
                        </div>
                        <div class="mpcc-lesson-info">
                            <span class="mpcc-lesson-title">${lesson.title}</span>
                            <span class="mpcc-lesson-type">${lesson.type || 'Video'}</span>
                        </div>
                        <div class="mpcc-lesson-meta">
                            <span class="mpcc-lesson-duration">${lesson.duration || '10 min'}</span>
                            <div class="mpcc-lesson-actions">
                                <button class="mpcc-edit-lesson" data-section="${sectionIndex}" data-index="${lessonIndex}">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="mpcc-delete-lesson" data-section="${sectionIndex}" data-index="${lessonIndex}">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Drag and Drop Implementation
        initializeDragDropForPreview() {
            // Enable drag and drop for course structure reordering
            $('.mpcc-draggable').off('dragstart dragend');
            $('.mpcc-drop-zone').off('dragover drop');

            $('.mpcc-draggable').on('dragstart', this.handleDragStart.bind(this));
            $('.mpcc-draggable').on('dragend', this.handleDragEnd.bind(this));
            $('.mpcc-drop-zone').on('dragover', this.handleDragOver.bind(this));
            $('.mpcc-drop-zone').on('drop', this.handleDrop.bind(this));
        }

        handleDragStart(e) {
            this.draggedElement = e.target.closest('.mpcc-draggable');
            $(this.draggedElement).addClass('dragging');
            
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', this.draggedElement.outerHTML);
        }

        handleDragOver(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            
            const dropZone = e.target.closest('.mpcc-drop-zone');
            if (dropZone) {
                $(dropZone).addClass('drag-over');
            }
        }

        handleDrop(e) {
            e.preventDefault();
            
            const dropZone = e.target.closest('.mpcc-drop-zone');
            if (!dropZone || !this.draggedElement) return;

            $(dropZone).removeClass('drag-over');
            
            // Determine drop position
            const dropPosition = this.calculateDropPosition(e, dropZone);
            
            // Update course structure
            this.reorderCourseStructure(this.draggedElement, dropZone, dropPosition);
            
            // Update UI
            this.updateCoursePreview(this.currentCourse, true);
        }

        handleDragEnd(e) {
            $('.mpcc-draggable').removeClass('dragging');
            $('.mpcc-drop-zone').removeClass('drag-over');
            this.draggedElement = null;
        }

        // Voice Support Methods
        isVoiceSupported() {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) ||
                   'webkitSpeechRecognition' in window || 'SpeechRecognition' in window;
        }

        initializeVoiceSupport() {
            if (!this.isVoiceSupported()) {
                $('#mpcc-voice-button').hide();
                return;
            }

            // Request microphone permission
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(() => {
                    console.log('Microphone access granted');
                })
                .catch((err) => {
                    console.warn('Microphone access denied:', err);
                    $('#mpcc-voice-button').addClass('disabled');
                });
        }

        // Progress Tracking
        initializeProgressTracking() {
            this.updateProgress(0, 0);
        }

        updateProgress(step, percentage = null) {
            this.currentStep = Math.max(0, Math.min(step, this.totalSteps - 1));
            
            // Calculate percentage if not provided
            if (percentage === null) {
                percentage = (this.currentStep / (this.totalSteps - 1)) * 100;
            }

            // Update progress bar
            $('.mpcc-progress-fill').css('width', `${percentage}%`);
            
            // Update step indicators
            $('.mpcc-progress-step').each((index, element) => {
                const $step = $(element);
                $step.removeClass('active completed');
                
                if (index < this.currentStep) {
                    $step.addClass('completed');
                } else if (index === this.currentStep) {
                    $step.addClass('active');
                }
            });

            // Show completion celebration if at 100%
            if (percentage >= 100) {
                this.showCompletionCelebration();
            }
        }

        showCompletionCelebration() {
            // Add confetti or celebration animation
            const celebrationHTML = `
                <div class="mpcc-celebration-overlay">
                    <div class="mpcc-celebration-content">
                        <div class="mpcc-celebration-icon">🎉</div>
                        <h3>Course Complete!</h3>
                        <p>Your course structure is ready to be created.</p>
                    </div>
                </div>
            `;
            
            $('body').append(celebrationHTML);
            
            setTimeout(() => {
                $('.mpcc-celebration-overlay').fadeOut(() => {
                    $('.mpcc-celebration-overlay').remove();
                });
            }, 3000);
        }

        // Session Management
        generateSessionId() {
            return 'mpcc_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        // Helper methods for AJAX
        getAjaxUrl() {
            if (typeof ajaxurl !== 'undefined') {
                return ajaxurl;
            }
            if (typeof mpccCoursesIntegration !== 'undefined' && mpccCoursesIntegration.ajaxUrl) {
                return mpccCoursesIntegration.ajaxUrl;
            }
            if (typeof mpccAISettings !== 'undefined' && mpccAISettings.ajaxUrl) {
                return mpccAISettings.ajaxUrl;
            }
            return '/wp-admin/admin-ajax.php';
        }

        getNonce() {
            if (typeof mpccCoursesIntegration !== 'undefined' && mpccCoursesIntegration.nonce) {
                return mpccCoursesIntegration.nonce;
            }
            if (typeof mpccAISettings !== 'undefined' && mpccAISettings.nonce) {
                return mpccAISettings.nonce;
            }
            // Try to get from the page if available
            const nonceElement = $('#mpcc-ajax-nonce');
            if (nonceElement.length > 0) {
                return nonceElement.val();
            }
            console.warn('MPCC Debug: No nonce found!');
            return '';
        }

        autoSave() {
            if (this.chatHistory.length > 0 || this.currentCourse) {
                this.saveChatHistory();
                
                // Show auto-save indicator
                this.showNotification('Auto-saved', 'success', 2000);
            }
        }

        saveChatHistory() {
            try {
                const saveData = {
                    chatHistory: this.chatHistory,
                    currentCourse: this.currentCourse,
                    selectedTemplate: this.selectedTemplate,
                    sessionId: this.sessionId,
                    currentStep: this.currentStep,
                    timestamp: Date.now()
                };

                sessionStorage.setItem('mpcc_session_data', JSON.stringify(saveData));
                localStorage.setItem('mpcc_last_session', this.sessionId);
                
                return true;
            } catch (e) {
                console.error('Failed to save chat history:', e);
                return false;
            }
        }

        loadChatHistory() {
            try {
                const savedData = sessionStorage.getItem('mpcc_session_data');
                if (!savedData) return;

                const data = JSON.parse(savedData);
                
                this.chatHistory = data.chatHistory || [];
                this.currentCourse = data.currentCourse;
                this.selectedTemplate = data.selectedTemplate;
                this.sessionId = data.sessionId || this.generateSessionId();
                this.currentStep = data.currentStep || 0;

                // Rebuild interface
                this.rebuildChatInterface();
                this.updateProgress(this.currentStep);

                if (this.currentCourse) {
                    this.updateCoursePreview(this.currentCourse);
                    if (this.currentCourse.complete) {
                        this.enableCourseActions();
                    }
                }

                if (this.selectedTemplate) {
                    $(`.mpcc-template-card[data-template="${this.selectedTemplate}"]`).addClass('selected');
                }

            } catch (e) {
                console.warn('Failed to load chat history:', e);
            }
        }

        rebuildChatInterface() {
            $('#mpcc-chat-messages').empty();
            
            if (this.chatHistory.length === 0) {
                this.addWelcomeMessage();
            } else {
                this.chatHistory.forEach(msg => {
                    this.addMessage(msg.type, msg.content, 
                        this.formatTime(new Date(msg.timestamp)), msg.options || {});
                });
            }
        }

        addWelcomeMessage() {
            const welcomeMessage = `
                Hello! I'm your AI course creation assistant. 🤖
                
                I'll help you build an amazing course step by step. Here's what we can do together:
                
                - **Choose a template** - Select from our proven course structures
                - **Design your content** - Create engaging lessons and sections  
                - **Structure your course** - Organize everything perfectly
                - **Review and refine** - Make sure everything is just right
                
                Let's start by selecting a template above, or tell me about your course idea!
            `;
            
            this.addMessage('assistant', welcomeMessage, null, {
                suggestions: [
                    'Help me choose a template',
                    'I have a specific course idea',
                    'Show me course examples',
                    'What makes a great course?'
                ]
            });
        }

        // Theme Management
        setupTheme() {
            $('body').addClass(`mpcc-theme-${this.currentTheme}`);
        }

        handleThemeToggle() {
            this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
            
            $('body').removeClass('mpcc-theme-light mpcc-theme-dark')
                     .addClass(`mpcc-theme-${this.currentTheme}`);
            
            localStorage.setItem('mpcc_theme', this.currentTheme);
            
            // Animate theme transition
            $('.mpcc-generator-container').addClass('theme-transitioning');
            setTimeout(() => {
                $('.mpcc-generator-container').removeClass('theme-transitioning');
            }, 300);
        }

        // Connection Monitoring
        startConnectionMonitoring() {
            this.updateConnectionStatus('connecting');
            
            // Initial connection test
            this.testConnection();
            
            // Monitor connection every 30 seconds
            setInterval(() => {
                this.testConnection();
            }, 30000);
        }

        testConnection() {
            $.ajax({
                url: this.getAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'mpcc_ping',
                    nonce: this.getNonce()
                },
                timeout: 10000,
                success: () => {
                    this.updateConnectionStatus('connected');
                    this.lastPingTime = Date.now();
                },
                error: () => {
                    this.updateConnectionStatus('disconnected');
                }
            });
        }

        updateConnectionStatus(status) {
            this.connectionStatus = status;
            
            const statusElement = $('#mpcc-connection-status');
            statusElement.removeClass('connected disconnected error generating')
                        .addClass(status);
            
            const statusTexts = {
                connected: 'Connected',
                disconnected: 'Disconnected',
                error: 'Connection Error',
                generating: 'Generating...',
                connecting: 'Connecting...'
            };
            
            statusElement.find('.mpcc-status-text').text(statusTexts[status]);
        }

        // Utility Methods
        setupKeyboardShortcuts() {
            $(document).on('keydown', (e) => {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.key) {
                        case 'Enter':
                            e.preventDefault();
                            this.handleSendMessage();
                            break;
                        case 'k':
                            e.preventDefault();
                            this.handleClearChat();
                            break;
                        case 's':
                            e.preventDefault();
                            this.handleSaveDraft();
                            break;
                        case '/':
                            e.preventDefault();
                            $('#mpcc-chat-input').focus();
                            break;
                    }
                }
            });
        }

        handleKeyboardShortcuts(e) {
            // Handle additional keyboard shortcuts in chat input
            if (e.key === 'b') {
                e.preventDefault();
                this.insertFormatting('**', '**');
            } else if (e.key === 'i') {
                e.preventDefault();
                this.insertFormatting('*', '*');
            }
        }

        insertFormatting(before, after) {
            const input = $('#mpcc-chat-input')[0];
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const text = input.value;
            
            const selectedText = text.substring(start, end);
            const replacement = before + selectedText + after;
            
            input.value = text.substring(0, start) + replacement + text.substring(end);
            input.setSelectionRange(start + before.length, start + before.length + selectedText.length);
            input.focus();
        }

        updateInputStats() {
            const text = $('#mpcc-chat-input').val();
            const charCount = text.length;
            const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
            
            $('.mpcc-char-count').text(`${charCount}/2000`);
            $('.mpcc-word-count').text(`${wordCount} words`);
            
            // Warning for long messages
            if (charCount > 1800) {
                $('.mpcc-char-count').addClass('warning');
            } else {
                $('.mpcc-char-count').removeClass('warning');
            }
        }

        showNotification(message, type = 'info', duration = 5000) {
            const notificationHTML = `
                <div class="mpcc-notification mpcc-notification-${type}">
                    <div class="mpcc-notification-content">
                        <span class="mpcc-notification-icon dashicons dashicons-${this.getNotificationIcon(type)}"></span>
                        <span class="mpcc-notification-text">${message}</span>
                    </div>
                    <button class="mpcc-notification-close">×</button>
                </div>
            `;
            
            const $notification = $(notificationHTML);
            $('.mpcc-generator-container').prepend($notification);
            
            // Auto-remove after duration
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, duration);
            
            // Manual close
            $notification.find('.mpcc-notification-close').on('click', () => {
                $notification.fadeOut(() => $notification.remove());
            });
        }

        getNotificationIcon(type) {
            const icons = {
                success: 'yes-alt',
                error: 'warning',
                warning: 'flag',
                info: 'info'
            };
            return icons[type] || 'info';
        }

        scrollToBottom() {
            const $messages = $('#mpcc-chat-messages');
            $messages.animate({ scrollTop: $messages[0].scrollHeight }, 300);
        }

        formatTime(date) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        enableCourseActions() {
            $('#mpcc-save-draft, #mpcc-create-course, #mpcc-preview-course').prop('disabled', false);
        }
        
        showCourseActions(actions) {
            // Display action buttons in the chat interface
            const actionButtons = actions.map(action => {
                const btnClass = action.type === 'primary' ? 'button-primary' : 'button-secondary';
                return `<button class="button ${btnClass} mpcc-course-action" data-action="${action.action}">${action.label}</button>`;
            }).join(' ');
            
            const actionHtml = `
                <div class="mpcc-action-container" style="margin: 15px 0; padding: 15px; background: #f0f7ff; border-radius: 8px; text-align: center;">
                    <p style="margin: 0 0 10px 0; font-weight: bold;">Your course is ready!</p>
                    ${actionButtons}
                </div>
            `;
            
            $('#mpcc-chat-messages').append(actionHtml);
            this.scrollToBottom();
            
            // Bind action handlers
            $('.mpcc-course-action').on('click', (e) => {
                const action = $(e.target).data('action');
                if (action === 'create_course') {
                    this.createCourse();
                } else if (action === 'modify') {
                    this.addMessage('user', 'I want to modify the course structure');
                    this.generateAIResponse('I want to modify the course structure');
                }
            });
        }
        
        createCourse() {
            if (!this.currentCourse) {
                this.showNotification('No course data available', 'error');
                return;
            }
            
            this.showNotification('Creating your course...', 'info');
            
            $.ajax({
                url: this.getAjaxUrl(),
                type: 'POST',
                data: {
                    action: 'mpcc_create_course_with_ai',
                    nonce: this.getNonce(),
                    course_data: this.currentCourse
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotification('Course created successfully!', 'success');
                        // Redirect to edit course page
                        if (response.data.edit_url) {
                            setTimeout(() => {
                                window.location.href = response.data.edit_url;
                            }, 2000);
                        }
                    } else {
                        this.showNotification('Failed to create course: ' + response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showNotification('Failed to create course', 'error');
                }
            });
        }

        // Additional utility methods would be implemented here...
        
        getUserPreferences() {
            return {
                theme: this.currentTheme,
                language: 'en',
                notifications: true
            };
        }

        getContextualData() {
            return {
                currentStep: this.currentStep,
                sessionDuration: Date.now() - (this.sessionStartTime || Date.now()),
                messageCount: this.chatHistory.length
            };
        }

        // Event handlers for new features...
        handleCourseCompletion(data) {
            this.updateProgress(this.totalSteps - 1, 100);
            this.showCompletionCelebration();
            this.enableCourseActions();
        }

        handleMessageAction(e) {
            const action = $(e.target).closest('.mpcc-message-action').data('action');
            const messageId = $(e.target).closest('.mpcc-message').data('message-id');
            
            switch(action) {
                case 'copy':
                    this.copyMessage(messageId);
                    break;
                case 'regenerate':
                    this.regenerateResponse(messageId);
                    break;
                case 'edit':
                    this.editMessage(messageId);
                    break;
                case 'thumbs_up':
                case 'thumbs_down':
                    this.rateMessage(messageId, action);
                    break;
            }
        }

        // Implementation of action handlers would continue...
    }

    // Export AICopilot class to global scope
    window.AICopilot = AICopilot;
    
    // Initialize when document is ready
    $(document).ready(function() {
        console.log('AI Copilot document ready, checking for chat interface...');
        // Check if we're on the course generator page
        if ($('#mpcc-chat-messages').length > 0) {
            console.log('Chat interface found, initializing AI Copilot...');
            window.mpccCopilot = new AICopilot();
        } else {
            console.log('No chat interface found on this page');
        }
    });

})(jQuery);