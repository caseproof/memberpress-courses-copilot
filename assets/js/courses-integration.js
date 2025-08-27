/**
 * MemberPress Courses Copilot - Courses Integration
 * Handles integration with MemberPress Courses admin interface
 *
 * @package MemberPressCoursesCopilot
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Main integration class
    class MPCCCoursesIntegration {
        constructor() {
            this.init();
        }

        init() {
            // Initialize when DOM is ready
            $(document).ready(() => {
                this.bindEvents();
                this.enhanceInterface();
                this.initializeFeatures();
            });
        }

        bindEvents() {
            // Handle quick start button clicks
            $(document).on('click', '.mpcc-quick-start', this.handleQuickStart.bind(this));
            
            // Handle suggestion button clicks
            $(document).on('click', '.mpcc-suggestion-btn', this.handleSuggestion.bind(this));
            
            // Handle action button clicks
            $(document).on('click', '.mpcc-action-btn', this.handleActionButton.bind(this));
            
            // Handle course creation
            $(document).on('click', '#mpcc-create-course', this.handleCreateCourse.bind(this));
            
            // Handle course actions
            $(document).on('click', '.mpcc-edit-section', this.handleEditSection.bind(this));
            $(document).on('click', '.mpcc-delete-section', this.handleDeleteSection.bind(this));
            $(document).on('click', '.mpcc-edit-lesson', this.handleEditLesson.bind(this));
            $(document).on('click', '.mpcc-delete-lesson', this.handleDeleteLesson.bind(this));
            $(document).on('click', '.mpcc-add-lesson', this.handleAddLesson.bind(this));
        }

        enhanceInterface() {
            // Add tooltips
            this.initTooltips();
            
            // Enhance form fields
            this.enhanceFormFields();
            
            // Add keyboard shortcuts
            this.initKeyboardShortcuts();
        }

        initializeFeatures() {
            // Initialize preview pane if it exists
            if ($('#mpcc-preview-pane').length > 0) {
                $('#mpcc-preview-pane').addClass('active');
            }
            
            // Initialize any existing chat interfaces
            this.initializeChatInterfaces();
            
            // Setup auto-save
            this.setupAutoSave();
        }

        handleQuickStart(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const message = $button.data('message') || $button.data('prompt');
            
            // Add the message to the chat input and send
            $('#mpcc-chat-input').val(message);
            $('#mpcc-send-message').trigger('click');
            
            // Ensure input is cleared after triggering send
            setTimeout(function() {
                $('#mpcc-chat-input').val('');
            }, 100);
        }

        handleSuggestion(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const suggestion = $button.data('suggestion');
            
            // Add the suggestion to the chat input and trigger send event
            $('#mpcc-chat-input').val(suggestion);
            
            // Trigger the click event with the proper namespace to ensure the right handler is called
            $('#mpcc-send-message').trigger('click.mpcc-chat-send');
            
            // Ensure input is cleared after triggering send
            setTimeout(function() {
                $('#mpcc-chat-input').val('');
            }, 100);
        }

        handleActionButton(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const action = $button.data('action');
            
            switch(action) {
                case 'create_course':
                    this.handleCreateCourse(e);
                    break;
                case 'modify':
                    this.showModifyDialog();
                    break;
                case 'retry':
                    this.retryLastMessage();
                    break;
                case 'contact_support':
                    this.showSupportDialog();
                    break;
            }
        }

        handleCreateCourse(e) {
            e.preventDefault();
            
            // Use the unified course creation function
            const courseData = window.mpccCurrentCourse || (window.mpccCopilot && window.mpccCopilot.currentCourse);
            
            if (courseData) {
                // Call the global course creation function
                if (typeof window.mpccCreateCourse === 'function') {
                    window.mpccCreateCourse(courseData);
                } else {
                    console.error('mpccCreateCourse function not found');
                    this.showError('Course creation function not available. Please refresh the page.');
                }
            } else {
                this.showError('No course data available. Please complete the course creation process first.');
            }
        }

        handleEditSection(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const sectionIndex = $button.data('index');
            
            // Show edit dialog for section
            this.showSectionEditDialog(sectionIndex);
        }

        handleDeleteSection(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const sectionIndex = $button.data('index');
            
            if (confirm('Are you sure you want to delete this section?')) {
                // Update the course data
                if (window.mpccCopilot && window.mpccCopilot.currentCourse) {
                    window.mpccCopilot.currentCourse.sections.splice(sectionIndex, 1);
                    window.mpccCopilot.updateCoursePreview(window.mpccCopilot.currentCourse, true);
                }
            }
        }

        handleEditLesson(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const sectionIndex = $button.data('section');
            const lessonIndex = $button.data('index');
            
            // Show edit dialog for lesson
            this.showLessonEditDialog(sectionIndex, lessonIndex);
        }

        handleDeleteLesson(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const sectionIndex = $button.data('section');
            const lessonIndex = $button.data('index');
            
            if (confirm('Are you sure you want to delete this lesson?')) {
                // Update the course data
                if (window.mpccCopilot && window.mpccCopilot.currentCourse) {
                    window.mpccCopilot.currentCourse.sections[sectionIndex].lessons.splice(lessonIndex, 1);
                    window.mpccCopilot.updateCoursePreview(window.mpccCopilot.currentCourse, true);
                }
            }
        }

        handleAddLesson(e) {
            e.preventDefault();
            const $button = $(e.currentTarget);
            const sectionIndex = $button.data('section');
            
            // Show dialog to add new lesson
            this.showAddLessonDialog(sectionIndex);
        }

        showSectionEditDialog(sectionIndex) {
            const section = window.mpccCopilot.currentCourse.sections[sectionIndex];
            
            const dialogHTML = `
                <div class="mpcc-modal-overlay">
                    <div class="mpcc-modal mpcc-edit-modal">
                        <div class="mpcc-modal-header">
                            <h3>Edit Section</h3>
                            <button class="mpcc-modal-close">&times;</button>
                        </div>
                        <div class="mpcc-modal-body">
                            <label>
                                <span>Section Title</span>
                                <input type="text" id="mpcc-section-title" value="${window.MPCCUtils.escapeHtml(section.title)}" />
                            </label>
                            <label>
                                <span>Section Description</span>
                                <textarea id="mpcc-section-description">${window.MPCCUtils.escapeHtml(section.description || '')}</textarea>
                            </label>
                        </div>
                        <div class="mpcc-modal-footer">
                            <button class="button button-primary" id="mpcc-save-section">Save Changes</button>
                            <button class="button mpcc-modal-close">Cancel</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(dialogHTML);
            
            // Bind events
            $('.mpcc-modal-close').on('click', () => $('.mpcc-modal-overlay').remove());
            $('#mpcc-save-section').on('click', () => {
                section.title = $('#mpcc-section-title').val();
                section.description = $('#mpcc-section-description').val();
                window.mpccCopilot.updateCoursePreview(window.mpccCopilot.currentCourse, true);
                $('.mpcc-modal-overlay').remove();
            });
        }

        showLessonEditDialog(sectionIndex, lessonIndex) {
            const lesson = window.mpccCopilot.currentCourse.sections[sectionIndex].lessons[lessonIndex];
            
            const dialogHTML = `
                <div class="mpcc-modal-overlay">
                    <div class="mpcc-modal mpcc-edit-modal">
                        <div class="mpcc-modal-header">
                            <h3>Edit Lesson</h3>
                            <button class="mpcc-modal-close">&times;</button>
                        </div>
                        <div class="mpcc-modal-body">
                            <label>
                                <span>Lesson Title</span>
                                <input type="text" id="mpcc-lesson-title" value="${window.MPCCUtils.escapeHtml(lesson.title)}" />
                            </label>
                            <label>
                                <span>Lesson Type</span>
                                <select id="mpcc-lesson-type">
                                    <option value="video" ${lesson.type === 'video' ? 'selected' : ''}>Video</option>
                                    <option value="text" ${lesson.type === 'text' ? 'selected' : ''}>Text</option>
                                    <option value="quiz" ${lesson.type === 'quiz' ? 'selected' : ''}>Quiz</option>
                                    <option value="assignment" ${lesson.type === 'assignment' ? 'selected' : ''}>Assignment</option>
                                </select>
                            </label>
                            <label>
                                <span>Duration (minutes)</span>
                                <input type="number" id="mpcc-lesson-duration" value="${window.MPCCUtils.escapeHtml(lesson.duration || 10)}" />
                            </label>
                            <label>
                                <span>Lesson Content</span>
                                <textarea id="mpcc-lesson-content" rows="6">${window.MPCCUtils.escapeHtml(lesson.content || '')}</textarea>
                            </label>
                        </div>
                        <div class="mpcc-modal-footer">
                            <button class="button button-primary" id="mpcc-save-lesson">Save Changes</button>
                            <button class="button mpcc-modal-close">Cancel</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(dialogHTML);
            
            // Bind events
            $('.mpcc-modal-close').on('click', () => $('.mpcc-modal-overlay').remove());
            $('#mpcc-save-lesson').on('click', () => {
                lesson.title = $('#mpcc-lesson-title').val();
                lesson.type = $('#mpcc-lesson-type').val();
                lesson.duration = $('#mpcc-lesson-duration').val();
                lesson.content = $('#mpcc-lesson-content').val();
                window.mpccCopilot.updateCoursePreview(window.mpccCopilot.currentCourse, true);
                $('.mpcc-modal-overlay').remove();
            });
        }

        showAddLessonDialog(sectionIndex) {
            const dialogHTML = `
                <div class="mpcc-modal-overlay">
                    <div class="mpcc-modal mpcc-edit-modal">
                        <div class="mpcc-modal-header">
                            <h3>Add New Lesson</h3>
                            <button class="mpcc-modal-close">&times;</button>
                        </div>
                        <div class="mpcc-modal-body">
                            <label>
                                <span>Lesson Title</span>
                                <input type="text" id="mpcc-lesson-title" placeholder="Enter lesson title" />
                            </label>
                            <label>
                                <span>Lesson Type</span>
                                <select id="mpcc-lesson-type">
                                    <option value="video">Video</option>
                                    <option value="text">Text</option>
                                    <option value="quiz">Quiz</option>
                                    <option value="assignment">Assignment</option>
                                </select>
                            </label>
                            <label>
                                <span>Duration (minutes)</span>
                                <input type="number" id="mpcc-lesson-duration" value="10" />
                            </label>
                            <label>
                                <span>Lesson Content</span>
                                <textarea id="mpcc-lesson-content" rows="6" placeholder="Enter lesson content"></textarea>
                            </label>
                        </div>
                        <div class="mpcc-modal-footer">
                            <button class="button button-primary" id="mpcc-add-lesson-save">Add Lesson</button>
                            <button class="button mpcc-modal-close">Cancel</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(dialogHTML);
            
            // Bind events
            $('.mpcc-modal-close').on('click', () => $('.mpcc-modal-overlay').remove());
            $('#mpcc-add-lesson-save').on('click', () => {
                const newLesson = {
                    title: $('#mpcc-lesson-title').val(),
                    type: $('#mpcc-lesson-type').val(),
                    duration: $('#mpcc-lesson-duration').val(),
                    content: $('#mpcc-lesson-content').val()
                };
                
                if (newLesson.title) {
                    window.mpccCopilot.currentCourse.sections[sectionIndex].lessons.push(newLesson);
                    window.mpccCopilot.updateCoursePreview(window.mpccCopilot.currentCourse, true);
                    $('.mpcc-modal-overlay').remove();
                } else {
                    alert('Please enter a lesson title');
                }
            });
        }

        initTooltips() {
            // Add tooltips to buttons and interactive elements
            $('[title]').each(function() {
                const $element = $(this);
                const title = $element.attr('title');
                $element.attr('data-tooltip', title);
                $element.removeAttr('title');
            });
        }

        enhanceFormFields() {
            // Add character counters to text fields
            $('textarea[maxlength]').each(function() {
                const $textarea = $(this);
                const maxLength = $textarea.attr('maxlength');
                const $counter = $('<div class="mpcc-char-counter"></div>');
                $textarea.after($counter);
                
                const updateCounter = () => {
                    const length = $textarea.val().length;
                    $counter.text(`${length}/${maxLength}`);
                };
                
                $textarea.on('input', updateCounter);
                updateCounter();
            });
        }

        initKeyboardShortcuts() {
            $(document).on('keydown', (e) => {
                // Ctrl/Cmd + Enter to send message
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    $('#mpcc-send-message').trigger('click');
                }
                
                // Escape to close modals
                if (e.key === 'Escape') {
                    $('.mpcc-modal-overlay').remove();
                }
            });
        }

        initializeChatInterfaces() {
            // Initialize any chat interfaces that are already in the DOM
            if ($('#mpcc-ai-chat-interface').length > 0 && !window.mpccCopilot) {
                // Initialize the AI copilot if it hasn't been initialized yet
                const script = document.createElement('script');
                script.src = mpccCoursesIntegration.pluginUrl + 'assets/js/ai-copilot.js';
                document.head.appendChild(script);
            }
        }

        setupAutoSave() {
            // Auto-save course data every 60 seconds
            setInterval(() => {
                if (window.mpccCopilot && window.mpccCopilot.currentCourse) {
                    this.saveDraft();
                }
            }, 60000);
        }

        saveDraft() {
            const courseData = window.mpccCopilot.currentCourse;
            
            $.ajax({
                url: mpccCoursesIntegration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_save_course_draft',
                    nonce: mpccCoursesIntegration.nonce,
                    course_data: courseData
                },
                success: (response) => {
                    if (response.success) {
                        console.log('Draft saved successfully');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to save draft:', error);
                }
            });
        }

        showLoading(message = 'Loading...') {
            const loadingHTML = `
                <div class="mpcc-loading-overlay">
                    <div class="mpcc-loading-content">
                        <div class="spinner is-active"></div>
                        <p>${window.MPCCUtils.escapeHtml(message)}</p>
                    </div>
                </div>
            `;
            $('body').append(loadingHTML);
        }

        hideLoading() {
            $('.mpcc-loading-overlay').remove();
        }

        showSuccess(message) {
            this.showNotification(message, 'success');
        }

        showError(message) {
            this.showNotification(message, 'error');
        }

        showNotification(message, type = 'info') {
            const notificationHTML = `
                <div class="mpcc-notification mpcc-notification-${window.MPCCUtils.escapeHtml(type)}">
                    <p>${window.MPCCUtils.escapeHtml(message)}</p>
                </div>
            `;
            
            const $notification = $(notificationHTML);
            $('body').append($notification);
            
            setTimeout(() => {
                $notification.fadeOut(() => $notification.remove());
            }, 5000);
        }

        retryLastMessage() {
            // Trigger retry of the last AI message
            if (window.mpccCopilot) {
                const lastUserMessage = window.mpccCopilot.chatHistory
                    .filter(msg => msg.type === 'user')
                    .pop();
                
                if (lastUserMessage) {
                    window.mpccCopilot.generateAIResponse(lastUserMessage.content);
                }
            }
        }

        showSupportDialog() {
            const dialogHTML = `
                <div class="mpcc-modal-overlay">
                    <div class="mpcc-modal mpcc-support-modal">
                        <div class="mpcc-modal-header">
                            <h3>Contact Support</h3>
                            <button class="mpcc-modal-close">&times;</button>
                        </div>
                        <div class="mpcc-modal-body">
                            <p>Need help? Here are your support options:</p>
                            <ul>
                                <li><a href="https://memberpress.com/docs" target="_blank">Documentation</a></li>
                                <li><a href="https://memberpress.com/support" target="_blank">Submit a Support Ticket</a></li>
                                <li><a href="https://community.memberpress.com" target="_blank">Community Forum</a></li>
                            </ul>
                        </div>
                        <div class="mpcc-modal-footer">
                            <button class="button mpcc-modal-close">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(dialogHTML);
            $('.mpcc-modal-close').on('click', () => $('.mpcc-modal-overlay').remove());
        }

        showModifyDialog() {
            const message = 'What would you like to modify about the course structure?';
            $('#mpcc-chat-input').val(message).focus();
        }
    }

    // Initialize the integration
    window.mpccCoursesIntegration = new MPCCCoursesIntegration();
    
    // Log initialization
    const loadTime = performance.now();
    console.log(`MPCC Interface Load: ${loadTime} ms`);

})(jQuery);