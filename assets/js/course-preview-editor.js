/**
 * MemberPress Courses Copilot - Course Preview Editor
 * Handles inline editing functionality for course preview
 *
 * @package MemberPressCoursesCopilot
 * @version 1.0.0
 */

(function($) {
    'use strict';
    
    if (typeof $ === 'undefined') {
        console.error('CoursePreviewEditor: jQuery is not loaded!');
        return;
    }

    class CoursePreviewEditor {
        constructor() {
            console.log('CoursePreviewEditor: Constructor called');
            this.currentEditingLesson = null;
            this.unsavedChanges = {};
            this.autoSaveTimer = null;
            this.isEditing = false;
            this.sessionId = null;
            
            this.init();
        }

        init() {
            // Get session ID from global storage
            this.sessionId = sessionStorage.getItem('mpcc_current_session_id');
            
            // Initialize event handlers
            this.bindEvents();
            
            // Load any existing drafts
            this.loadDrafts();
        }

        bindEvents() {
            console.log('CoursePreviewEditor: Binding events...');
            // Handle lesson click for editing
            $(document).on('click', '.mpcc-lesson-item', this.handleLessonClick.bind(this));
            console.log('CoursePreviewEditor: Found', $('.mpcc-lesson-item').length, 'lesson items on init');
            
            // Handle save button
            $(document).on('click', '.mpcc-editor-save', this.saveCurrentEdit.bind(this));
            
            // Handle cancel button
            $(document).on('click', '.mpcc-editor-cancel', this.cancelCurrentEdit.bind(this));
            
            // Handle generate with AI button
            $(document).on('click', '.mpcc-editor-generate', this.generateContent.bind(this));
            
            // Handle auto-save on input
            $(document).on('input', '.mpcc-editor-textarea', this.handleContentChange.bind(this));
            
            // Handle lesson switching with unsaved changes
            $(window).on('beforeunload', this.handleBeforeUnload.bind(this));
        }

        handleLessonClick(e) {
            console.log('CoursePreviewEditor: Lesson clicked!', e.currentTarget);
            e.preventDefault();
            e.stopPropagation();
            
            const $lesson = $(e.currentTarget);
            const sectionId = $lesson.data('section-id');
            const lessonId = $lesson.data('lesson-id');
            
            // Check for unsaved changes
            if (this.isEditing && this.hasUnsavedChanges()) {
                if (!confirm('You have unsaved changes. Do you want to save them before switching lessons?')) {
                    this.cancelCurrentEdit();
                } else {
                    this.saveCurrentEdit();
                }
            }
            
            // Start editing the clicked lesson
            this.startEditing(sectionId, lessonId, $lesson);
        }

        startEditing(sectionId, lessonId, $lessonElement) {
            // Set current editing state
            this.currentEditingLesson = {
                sectionId: sectionId,
                lessonId: lessonId,
                element: $lessonElement
            };
            this.isEditing = true;
            
            // Add editing class to lesson
            $lessonElement.addClass('mpcc-editing');
            
            // Get lesson content from data or load from server
            const existingContent = this.unsavedChanges[`${sectionId}_${lessonId}`] || 
                                  $lessonElement.data('content') || '';
            
            // Create editor UI
            const editorHtml = this.createEditorUI(existingContent);
            
            // Insert editor after lesson title
            const $lessonContent = $lessonElement.find('.mpcc-lesson-content');
            if ($lessonContent.length) {
                $lessonContent.html(editorHtml);
            } else {
                $lessonElement.append(`<div class="mpcc-lesson-content">${editorHtml}</div>`);
            }
            
            // Focus on textarea
            $lessonElement.find('.mpcc-editor-textarea').focus();
            
            // Show save indicator
            this.updateSaveIndicator('editing');
        }

        createEditorUI(content) {
            return `
                <div class="mpcc-editor-container">
                    <div class="mpcc-editor-toolbar">
                        <button type="button" class="button button-small mpcc-editor-generate">
                            <span class="dashicons dashicons-welcome-write-blog"></span> Generate with AI
                        </button>
                        <div class="mpcc-editor-status">
                            <span class="mpcc-save-indicator"></span>
                        </div>
                    </div>
                    <textarea class="mpcc-editor-textarea" rows="10" placeholder="Enter lesson content...">${this.escapeHtml(content)}</textarea>
                    <div class="mpcc-editor-actions">
                        <button type="button" class="button button-primary button-small mpcc-editor-save">Save</button>
                        <button type="button" class="button button-small mpcc-editor-cancel">Cancel</button>
                    </div>
                </div>
            `;
        }

        handleContentChange(e) {
            const $textarea = $(e.target);
            const content = $textarea.val();
            
            if (this.currentEditingLesson) {
                const key = `${this.currentEditingLesson.sectionId}_${this.currentEditingLesson.lessonId}`;
                this.unsavedChanges[key] = content;
                
                // Update save indicator
                this.updateSaveIndicator('unsaved');
                
                // Setup auto-save
                this.setupAutoSave();
            }
        }

        setupAutoSave() {
            // Clear existing timer
            if (this.autoSaveTimer) {
                clearTimeout(this.autoSaveTimer);
            }
            
            // Set new timer for auto-save (1 second after stopping typing)
            this.autoSaveTimer = setTimeout(() => {
                this.autoSave();
            }, 1000);
        }

        autoSave() {
            if (!this.currentEditingLesson || !this.hasUnsavedChanges()) {
                return;
            }
            
            const key = `${this.currentEditingLesson.sectionId}_${this.currentEditingLesson.lessonId}`;
            const content = this.unsavedChanges[key];
            
            if (content !== undefined) {
                this.saveLessonContent(
                    this.currentEditingLesson.sectionId,
                    this.currentEditingLesson.lessonId,
                    content,
                    true // isAutoSave
                );
            }
        }

        saveCurrentEdit() {
            if (!this.currentEditingLesson) {
                return;
            }
            
            const key = `${this.currentEditingLesson.sectionId}_${this.currentEditingLesson.lessonId}`;
            const content = this.unsavedChanges[key] || '';
            
            this.saveLessonContent(
                this.currentEditingLesson.sectionId,
                this.currentEditingLesson.lessonId,
                content,
                false // not auto-save
            );
        }

        saveLessonContent(sectionId, lessonId, content, isAutoSave = false) {
            this.updateSaveIndicator('saving');
            
            $.ajax({
                url: mpccCoursesIntegration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_save_lesson_content',
                    nonce: mpccCoursesIntegration.nonce,
                    session_id: this.sessionId,
                    section_id: sectionId,
                    lesson_id: lessonId,
                    content: content
                },
                success: (response) => {
                    if (response.success) {
                        this.updateSaveIndicator('saved');
                        
                        // Clear from unsaved changes
                        const key = `${sectionId}_${lessonId}`;
                        delete this.unsavedChanges[key];
                        
                        // Update the lesson element data
                        if (this.currentEditingLesson && this.currentEditingLesson.element) {
                            this.currentEditingLesson.element.data('content', content);
                        }
                        
                        if (!isAutoSave) {
                            // Close editor for manual save
                            this.closeEditor();
                        }
                    } else {
                        this.updateSaveIndicator('error');
                        if (!isAutoSave) {
                            alert('Failed to save: ' + (response.data || 'Unknown error'));
                        }
                    }
                },
                error: (xhr, status, error) => {
                    this.updateSaveIndicator('error');
                    console.error('Save failed:', error);
                    if (!isAutoSave) {
                        alert('Failed to save lesson content. Please try again.');
                    }
                }
            });
        }

        cancelCurrentEdit() {
            if (this.currentEditingLesson) {
                const key = `${this.currentEditingLesson.sectionId}_${this.currentEditingLesson.lessonId}`;
                delete this.unsavedChanges[key];
                this.closeEditor();
            }
        }

        closeEditor() {
            if (this.currentEditingLesson && this.currentEditingLesson.element) {
                // Remove editing class
                this.currentEditingLesson.element.removeClass('mpcc-editing');
                
                // Remove editor UI
                this.currentEditingLesson.element.find('.mpcc-lesson-content').empty();
            }
            
            this.currentEditingLesson = null;
            this.isEditing = false;
            this.updateSaveIndicator('');
        }

        generateContent() {
            if (!this.currentEditingLesson) {
                return;
            }
            
            const $lesson = this.currentEditingLesson.element;
            const lessonTitle = $lesson.find('.mpcc-lesson-title').text();
            const sectionTitle = $lesson.closest('.mpcc-section').find('.mpcc-section-title').first().text();
            
            // Show loading state
            const $generateBtn = $('.mpcc-editor-generate');
            const originalText = $generateBtn.html();
            $generateBtn.prop('disabled', true).html('<span class="spinner is-active"></span> Generating...');
            
            $.ajax({
                url: mpccCoursesIntegration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_generate_lesson_content',
                    nonce: mpccCoursesIntegration.nonce,
                    session_id: this.sessionId,
                    section_id: this.currentEditingLesson.sectionId,
                    lesson_id: this.currentEditingLesson.lessonId,
                    lesson_title: lessonTitle,
                    section_title: sectionTitle,
                    course_title: window.mpccCurrentCourse ? window.mpccCurrentCourse.title : ''
                },
                success: (response) => {
                    if (response.success && response.data.content) {
                        // Update textarea with generated content
                        $('.mpcc-editor-textarea').val(response.data.content).trigger('input');
                        this.showNotification('Content generated successfully!', 'success');
                    } else {
                        this.showNotification('Failed to generate content: ' + (response.data || 'Unknown error'), 'error');
                    }
                    $generateBtn.prop('disabled', false).html(originalText);
                },
                error: (xhr, status, error) => {
                    console.error('Generation failed:', error);
                    this.showNotification('Failed to generate content. Please try again.', 'error');
                    $generateBtn.prop('disabled', false).html(originalText);
                }
            });
        }

        loadDrafts() {
            if (!this.sessionId) {
                return;
            }
            
            $.ajax({
                url: mpccCoursesIntegration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mpcc_load_all_drafts',
                    nonce: mpccCoursesIntegration.nonce,
                    session_id: this.sessionId
                },
                success: (response) => {
                    if (response.success && response.data.drafts) {
                        // Store drafts in memory
                        response.data.drafts.forEach(draft => {
                            const key = `${draft.section_id}_${draft.lesson_id}`;
                            this.unsavedChanges[key] = draft.content;
                            
                            // Update lesson elements with draft indicator
                            const $lesson = $(`.mpcc-lesson-item[data-section-id="${draft.section_id}"][data-lesson-id="${draft.lesson_id}"]`);
                            if ($lesson.length) {
                                $lesson.addClass('has-draft');
                                $lesson.data('content', draft.content);
                            }
                        });
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Failed to load drafts:', error);
                }
            });
        }

        updateSaveIndicator(status) {
            const $indicator = $('.mpcc-save-indicator');
            
            switch (status) {
                case 'editing':
                    $indicator.html('<span class="dashicons dashicons-edit"></span> Editing');
                    break;
                case 'unsaved':
                    $indicator.html('<span class="dashicons dashicons-warning"></span> Unsaved changes');
                    break;
                case 'saving':
                    $indicator.html('<span class="spinner is-active"></span> Saving...');
                    break;
                case 'saved':
                    $indicator.html('<span class="dashicons dashicons-yes"></span> Saved');
                    setTimeout(() => {
                        if ($indicator.text().includes('Saved')) {
                            $indicator.empty();
                        }
                    }, 3000);
                    break;
                case 'error':
                    $indicator.html('<span class="dashicons dashicons-no"></span> Save failed');
                    break;
                default:
                    $indicator.empty();
            }
        }

        showNotification(message, type = 'info') {
            // Use global notification function if available
            if (typeof window.showNotification === 'function') {
                window.showNotification(message, type);
            } else {
                // Fallback to simple alert
                alert(message);
            }
        }

        hasUnsavedChanges() {
            if (!this.currentEditingLesson) {
                return false;
            }
            
            const key = `${this.currentEditingLesson.sectionId}_${this.currentEditingLesson.lessonId}`;
            return this.unsavedChanges[key] !== undefined;
        }

        handleBeforeUnload(e) {
            if (this.hasUnsavedChanges()) {
                const message = 'You have unsaved changes. Are you sure you want to leave?';
                e.returnValue = message;
                return message;
            }
        }

        escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    }

    // Expose the class globally
    window.CoursePreviewEditor = CoursePreviewEditor;

    // Initialize when DOM is ready
    $(document).ready(() => {
        console.log('CoursePreviewEditor: DOM ready, checking for preview content...');
        // Only initialize on course generator page
        if ($('#mpcc-preview-content').length > 0) {
            console.log('CoursePreviewEditor: Initializing editor...');
            window.mpccPreviewEditor = new CoursePreviewEditor();
        } else {
            console.log('CoursePreviewEditor: No preview content found, skipping initialization');
        }
    });

})(jQuery);