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
            
            // Listen for session changes
            this.listenForSessionChanges();
            
            // Load any existing drafts
            this.loadDrafts();
        }
        
        listenForSessionChanges() {
            // Listen for custom event when session changes
            $(document).on('mpcc:session-changed', (e, data) => {
                console.log('CoursePreviewEditor: Session changed to:', data.sessionId);
                this.sessionId = data.sessionId;
                sessionStorage.setItem('mpcc_current_session_id', data.sessionId);
                
                // Reload drafts for new session
                this.loadDrafts();
            });
            
            // Also check periodically for session changes
            setInterval(() => {
                const currentSessionId = sessionStorage.getItem('mpcc_current_session_id');
                if (currentSessionId && currentSessionId !== this.sessionId) {
                    console.log('CoursePreviewEditor: Detected session change:', currentSessionId);
                    this.sessionId = currentSessionId;
                    this.loadDrafts();
                }
            }, 2000);
        }

        bindEvents() {
            console.log('CoursePreviewEditor: Binding events...');
            
            // Unbind any existing event handlers first to prevent duplicates
            $(document).off('click.mpccEditor');
            $(document).off('input.mpccEditor');
            $(window).off('beforeunload.mpccEditor');
            
            // Handle lesson click for editing
            $(document).on('click.mpccEditor', '.mpcc-lesson-item', this.handleLessonClick.bind(this));
            console.log('CoursePreviewEditor: Found', $('.mpcc-lesson-item').length, 'lesson items on init');
            
            // Handle save button
            $(document).on('click.mpccEditor', '.mpcc-editor-save', this.saveCurrentEdit.bind(this));
            
            // Handle cancel button
            $(document).on('click.mpccEditor', '.mpcc-editor-cancel', this.cancelCurrentEdit.bind(this));
            
            // Handle generate with AI button
            $(document).on('click.mpccEditor', '.mpcc-editor-generate', this.generateContent.bind(this));
            
            // Handle auto-save on input
            $(document).on('input.mpccEditor', '.mpcc-editor-textarea', this.handleContentChange.bind(this));
            
            // Handle lesson switching with unsaved changes
            $(window).on('beforeunload.mpccEditor', this.handleBeforeUnload.bind(this));
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
            
            // Get session ID and create one if needed
            let sessionId = sessionStorage.getItem('mpcc_current_session_id') || this.sessionId;
            
            if (!sessionId) {
                console.warn('No session ID available, creating temporary session');
                // Create a temporary session ID
                sessionId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem('mpcc_current_session_id', sessionId);
                this.sessionId = sessionId;
            }
            
            $.ajax({
                url: window.mpccCoursesIntegration?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'mpcc_save_lesson_content',
                    nonce: window.mpccCoursesIntegration?.nonce || $('#mpcc-ajax-nonce').val() || window.mpccAISettings?.nonce || '',
                    session_id: sessionId,
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
                console.error('No lesson currently being edited');
                this.showNotification('Error: No lesson selected for editing', 'error');
                return;
            }
            
            console.log('Generating content for:', this.currentEditingLesson);
            
            const $lesson = this.currentEditingLesson.element;
            const lessonTitle = this.currentEditingLesson.lessonTitle || $lesson.data('lesson-title') || $lesson.text().replace(/Lesson \d+\.\d+:/, '').trim();
            const sectionTitle = this.currentEditingLesson.sectionTitle || $lesson.data('section-title') || '';
            
            // Verify textarea exists before making request
            const $textarea = $lesson.find('.mpcc-editor-textarea');
            if (!$textarea.length) {
                console.error('Textarea not found before generation');
                this.showNotification('Error: Editor not found', 'error');
                return;
            }
            
            console.log('Found textarea, proceeding with generation');
            
            // Show loading state
            const $generateBtn = $('.mpcc-editor-generate');
            const originalText = $generateBtn.html();
            $generateBtn.prop('disabled', true).html('<span class="spinner is-active"></span> Generating...');
            
            // Get session ID and create one if needed
            let sessionId = sessionStorage.getItem('mpcc_current_session_id') || this.sessionId;
            
            if (!sessionId) {
                console.warn('No session ID available for content generation, creating temporary session');
                // Create a temporary session ID
                sessionId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem('mpcc_current_session_id', sessionId);
                this.sessionId = sessionId;
            }
            
            $.ajax({
                url: window.mpccCoursesIntegration?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'mpcc_generate_lesson_content',
                    nonce: window.mpccCoursesIntegration?.nonce || $('#mpcc-ajax-nonce').val() || window.mpccAISettings?.nonce || '',
                    session_id: sessionId,
                    section_id: this.currentEditingLesson.sectionId,
                    lesson_id: this.currentEditingLesson.lessonId,
                    lesson_title: lessonTitle,
                    section_title: sectionTitle,
                    course_title: window.mpccCurrentCourse ? window.mpccCurrentCourse.title : ''
                },
                success: (response) => {
                    console.log('Generate content response:', response);
                    
                    if (response.success && response.data && response.data.content) {
                        // Find the textarea in the current lesson element
                        const $lessonElement = $(`.mpcc-lesson-item[data-section-id="${this.currentEditingLesson.sectionId}"][data-lesson-id="${this.currentEditingLesson.lessonId}"]`);
                        const $textarea = $lessonElement.find('.mpcc-editor-textarea');
                        
                        console.log('Lesson element found:', $lessonElement.length);
                        console.log('Textarea element found:', $textarea.length);
                        console.log('Content to set:', response.data.content);
                        
                        // Try multiple methods to find and update the textarea
                        let updated = false;
                        
                        // Method 1: Find in lesson element
                        if ($textarea.length) {
                            $textarea.val(response.data.content);
                            $textarea[0].value = response.data.content;
                            $textarea.trigger('input').trigger('change');
                            updated = true;
                            console.log('Method 1 successful - Textarea value:', $textarea.val());
                        }
                        
                        // Method 2: Use current editing lesson element
                        if (!updated && this.currentEditingLesson && this.currentEditingLesson.element) {
                            const $editorTextarea = this.currentEditingLesson.element.find('.mpcc-editor-textarea');
                            if ($editorTextarea.length) {
                                $editorTextarea.val(response.data.content);
                                $editorTextarea[0].value = response.data.content;
                                $editorTextarea.trigger('input').trigger('change');
                                updated = true;
                                console.log('Method 2 successful - Textarea value:', $editorTextarea.val());
                            }
                        }
                        
                        // Method 3: Global selector as last resort
                        if (!updated) {
                            const $globalTextarea = $('.mpcc-editor-textarea');
                            if ($globalTextarea.length) {
                                $globalTextarea.val(response.data.content);
                                if ($globalTextarea[0]) {
                                    $globalTextarea[0].value = response.data.content;
                                }
                                $globalTextarea.trigger('input').trigger('change');
                                updated = true;
                                console.log('Method 3 successful - Textarea value:', $globalTextarea.val());
                            }
                        }
                        
                        if (updated) {
                            // Focus the textarea to ensure it's active
                            $('.mpcc-editor-textarea').focus();
                            this.showNotification('Content generated successfully!', 'success');
                            
                            // Mark as having unsaved changes
                            if (this.currentEditingLesson) {
                                const key = `${this.currentEditingLesson.sectionId}_${this.currentEditingLesson.lessonId}`;
                                this.unsavedChanges[key] = response.data.content;
                                this.updateSaveIndicator('unsaved');
                            }
                        } else {
                            console.error('Failed to update textarea with generated content');
                            this.showNotification('Error: Could not update editor with generated content', 'error');
                        }
                    } else {
                        console.error('Invalid response structure:', response);
                        this.showNotification('Failed to generate content: ' + (response.data?.message || 'Unknown error'), 'error');
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
            // Always get the latest session ID
            this.sessionId = sessionStorage.getItem('mpcc_current_session_id');
            
            if (!this.sessionId) {
                console.log('CoursePreviewEditor: No session ID available for loading drafts, creating temporary session');
                // Create a temporary session ID
                this.sessionId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                sessionStorage.setItem('mpcc_current_session_id', this.sessionId);
            }
            
            console.log('CoursePreviewEditor: Loading drafts for session:', this.sessionId);
            
            $.ajax({
                url: window.mpccCoursesIntegration?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'mpcc_load_all_drafts',
                    nonce: window.mpccCoursesIntegration?.nonce || $('#mpcc-ajax-nonce').val() || window.mpccAISettings?.nonce || '',
                    session_id: this.sessionId || sessionStorage.getItem('mpcc_current_session_id')
                },
                success: (response) => {
                    if (response.success && response.data.drafts) {
                        // Store drafts in memory
                        // The drafts object has keys in format "section_id::lesson_id"
                        Object.entries(response.data.drafts).forEach(([key, content]) => {
                            const [sectionId, lessonId] = key.split('::');
                            const storageKey = `${sectionId}_${lessonId}`;
                            this.unsavedChanges[storageKey] = content;
                            
                            // Update lesson elements with draft indicator
                            const $lesson = $(`.mpcc-lesson-item[data-section-id="${sectionId}"][data-lesson-id="${lessonId}"]`);
                            if ($lesson.length) {
                                $lesson.addClass('has-draft');
                                $lesson.data('content', content);
                            }
                        });
                        
                        console.log('CoursePreviewEditor: Loaded', response.data.count, 'drafts');
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
        
        // Try immediate initialization only if not already initialized
        if (!window.mpccPreviewEditor && $('#mpcc-preview-content').length > 0) {
            console.log('CoursePreviewEditor: Initializing editor immediately...');
            window.mpccPreviewEditor = new CoursePreviewEditor();
        } else if (!window.mpccPreviewEditor) {
            console.log('CoursePreviewEditor: No preview content found on ready, will wait for interface load');
        }
    });
    
    // Also listen for the interface load event
    $(document).on('mpcc:interface-loaded', () => {
        console.log('CoursePreviewEditor: Interface loaded event received');
        // Only initialize if not already initialized
        if (!window.mpccPreviewEditor && $('#mpcc-preview-content').length > 0) {
            console.log('CoursePreviewEditor: Initializing editor after interface load...');
            window.mpccPreviewEditor = new CoursePreviewEditor();
        } else if (window.mpccPreviewEditor) {
            console.log('CoursePreviewEditor: Already initialized, skipping...');
        }
    });

})(jQuery);