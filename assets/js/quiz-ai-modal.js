/**
 * MemberPress Courses Copilot - Quiz AI Modal Integration
 * Matches the course/lesson AI modal pattern for quiz generation
 *
 * @package MemberPressCoursesCopilot
 * @version 3.0.0
 */

(function($) {
    'use strict';

    class MPCCQuizAIModal {
        constructor() {
            this.modalOpen = false;
            this.generatedQuestions = [];
            this.currentLessonId = null;
            this.logger = window.MPCCDebug ? window.MPCCDebug.createLogger('Quiz AI Modal') : null;
            this.init();
        }

        init() {
            this.logger?.log('Initializing...');
            
            // Only run on quiz edit pages
            if (!$('body').hasClass('post-type-mpcs-quiz')) {
                return;
            }
            
            $(document).ready(() => {
                this.addGenerateButton();
                // Wait a bit for any dynamic fields to load
                setTimeout(() => {
                    this.detectLessonContext();
                    
                    // Auto-open modal if coming from lesson context
                    this.checkAutoOpenModal();
                }, 500);
            });
        }
        
        /**
         * Check if we should auto-open the modal
         */
        checkAutoOpenModal() {
            // Check if we have lesson context from URL and it's a new quiz
            const urlParams = new URLSearchParams(window.location.search);
            const hasLessonContext = urlParams.get('lesson_id') || urlParams.get('from_lesson');
            const isNewQuiz = $('body').hasClass('post-new-php') || urlParams.get('auto_open') === 'true';
            
            this.logger?.debug('Auto-open check', { hasLessonContext, isNewQuiz });
            
            if (hasLessonContext && isNewQuiz) {
                this.logger?.log('Auto-opening modal from lesson context');
                
                // Show loading message
                this.showNotice('Opening AI Quiz Generator...', 'info');
                
                // Small delay to ensure button is ready
                setTimeout(() => {
                    this.openModal();
                    
                    // Remove auto_open from URL to prevent reopening on refresh
                    if (window.history.replaceState) {
                        const newUrl = window.location.href.replace(/[?&]auto_open=true/, '');
                        window.history.replaceState({}, document.title, newUrl);
                    }
                }, 1000);
            }
        }

        /**
         * Add Generate with AI button to match course/lesson pattern
         */
        addGenerateButton() {
            // Wait for editor to be ready
            wp.domReady(() => {
                const checkInterval = setInterval(() => {
                    // Look for the toolbar where Save/Publish buttons are
                    const $toolbar = $('.editor-header__settings, .edit-post-header__settings').first();
                    
                    if ($toolbar.length && !$('#mpcc-quiz-generate-ai').length) {
                        clearInterval(checkInterval);
                        
                        // Create button matching the style
                        const buttonHtml = `
                            <button 
                                id="mpcc-quiz-generate-ai" 
                                type="button"
                                class="components-button editor-post-publish-button editor-post-publish-button__button is-primary"
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); margin-right: 8px; border: none;"
                            >
                                <span class="dashicons dashicons-admin-generic" style="margin-right: 4px; font-size: 18px; line-height: 1.2;"></span>
                                Generate with AI
                            </button>
                        `;
                        
                        // Insert before the Publish button
                        $toolbar.find('.editor-post-publish-button, .editor-post-publish-panel__toggle').first().before(buttonHtml);
                        
                        // Bind click event
                        $('#mpcc-quiz-generate-ai').on('click', (e) => {
                            e.preventDefault();
                            this.openModal();
                        });
                    }
                }, 500);
                
                // Stop checking after 10 seconds
                setTimeout(() => clearInterval(checkInterval), 10000);
            });
        }

        /**
         * Detect lesson context from various sources
         */
        detectLessonContext() {
            this.logger?.log('Detecting lesson context...');
            this.detectionMethod = null;
            this.currentCourseId = null;
            
            // Method 1: Check URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const lessonIdFromUrl = urlParams.get('lesson_id') || urlParams.get('lesson') || urlParams.get('from_lesson');
            const courseIdFromUrl = urlParams.get('course_id') || urlParams.get('course');
            const fromCurriculum = urlParams.get('curriculum');
            
            if (lessonIdFromUrl) {
                this.currentLessonId = parseInt(lessonIdFromUrl, 10);
                this.detectionMethod = 'url';
                this.logger?.log('Detected lesson ID from URL:', this.currentLessonId, 'Type:', typeof this.currentLessonId);
            }
            
            if (courseIdFromUrl) {
                this.currentCourseId = parseInt(courseIdFromUrl, 10);
                this.logger?.log('Detected course ID from URL:', this.currentCourseId, 'Type:', typeof this.currentCourseId);
            }
            
            // If we have curriculum parameter, try to get course ID from referrer
            if (fromCurriculum && !this.currentCourseId && document.referrer) {
                const referrerMatch = document.referrer.match(/post=(\d+)/);
                if (referrerMatch) {
                    this.currentCourseId = parseInt(referrerMatch[1], 10);
                    this.detectionMethod = 'curriculum-referrer';
                    this.logger?.log('Detected course ID from curriculum referrer:', this.currentCourseId);
                }
            }
            
            // Method 2: Check referrer URL for lesson edit page
            const referrer = document.referrer;
            if (referrer && referrer.includes('post.php')) {
                const referrerMatch = referrer.match(/post=(\d+)/);
                if (referrerMatch) {
                    // Verify it's a lesson by checking post type
                    $.ajax({
                        url: '/wp-json/wp/v2/mpcs-lesson/' + referrerMatch[1],
                        async: false,
                        success: (lesson) => {
                            this.currentLessonId = parseInt(lesson.id, 10);
                            this.detectionMethod = 'referrer';
                            this.logger?.log('Detected lesson ID from referrer:', this.currentLessonId);
                        },
                        error: () => {
                            // Silently fail - referrer might not be a lesson
                            this.logger?.debug('Referrer post is not a lesson');
                        }
                    });
                }
            }
            
            // Method 3: Check for lesson selector in the quiz form
            if (!this.currentLessonId) {
                const $lessonSelect = $('select[name="_mpcs_lesson_id"], select[name="lesson_id"], #lesson_id, .lesson-selector');
                this.logger?.debug('Looking for lesson selectors, found:', $lessonSelect.length);
                if ($lessonSelect.length) {
                    this.logger?.debug('Lesson selector value:', $lessonSelect.val());
                    if ($lessonSelect.val()) {
                        this.currentLessonId = parseInt($lessonSelect.val(), 10);
                        this.detectionMethod = 'form';
                        this.logger?.log('Detected lesson ID from form field:', this.currentLessonId);
                    }
                }
            }
            
            // Method 4: Check post meta fields
            if (!this.currentLessonId) {
                const $metaInput = $('input[name="_lesson_id"], input[name="mpcs_lesson_id"]');
                if ($metaInput.length && $metaInput.val()) {
                    this.currentLessonId = parseInt($metaInput.val(), 10);
                    this.detectionMethod = 'meta';
                    this.logger?.log('Detected lesson ID from meta field:', this.currentLessonId);
                }
            }
            
            // Method 5: Check if quiz already has associated lesson (for existing quizzes)
            if (!this.currentLessonId && wp && wp.data) {
                const postId = wp.data.select('core/editor').getCurrentPostId();
                if (postId) {
                    const postMeta = wp.data.select('core/editor').getEditedPostAttribute('meta');
                    if (postMeta && postMeta._mpcs_lesson_id) {
                        this.currentLessonId = parseInt(postMeta._mpcs_lesson_id, 10);
                        this.detectionMethod = 'existing';
                        this.logger?.log('Detected lesson ID from existing quiz meta:', this.currentLessonId);
                    }
                    // Also check for course ID in meta
                    if (!this.currentCourseId && postMeta && postMeta._mpcs_course_id) {
                        this.currentCourseId = parseInt(postMeta._mpcs_course_id, 10);
                        this.logger?.log('Detected course ID from existing quiz meta:', this.currentCourseId);
                    }
                }
            }
            
            // Method 6: Try to detect course from page header or referrer
            if (!this.currentCourseId) {
                // Check if we're coming from a course curriculum page by looking at the referrer
                const referrer = document.referrer;
                if (referrer && referrer.includes('post.php')) {
                    const courseMatch = referrer.match(/post=(\d+)/);
                    if (courseMatch) {
                        // We'll verify this is a course when we load lessons
                        this.pendingCourseId = courseMatch[1];
                        this.logger?.log('Potential course ID from referrer:', this.pendingCourseId);
                    }
                }
            }
            
            if (!this.currentLessonId && !this.currentCourseId) {
                this.logger?.log('No lesson or course context detected');
            }
        }

        /**
         * Open the AI modal
         */
        openModal() {
            if (this.modalOpen) return;
            
            this.modalOpen = true;
            
            const modalHtml = `
                <div id="mpcc-quiz-ai-modal" class="mpcc-modal-overlay">
                    <div class="mpcc-modal-container">
                        <div class="mpcc-modal-header">
                            <h2>AI Quiz Generator</h2>
                            <button class="mpcc-modal-close" type="button">
                                <span class="dashicons dashicons-no"></span>
                            </button>
                        </div>
                        
                        <div class="mpcc-modal-body">
                            <div class="mpcc-ai-intro">
                                <p><strong>AI Assistant:</strong></p>
                                <p>Hi! I'm here to help you create quiz questions. I can:</p>
                                <ul>
                                    <li>Generate multiple-choice questions from lesson content</li>
                                    <li>Create true/false questions for quick assessment</li>
                                    <li>Generate text answer questions for deeper understanding</li>
                                    <li>Create multiple select questions for complex topics</li>
                                    <li>Add explanations for correct answers</li>
                                    <li>Insert questions directly into your quiz</li>
                                </ul>
                                <p>What lesson would you like to create quiz questions from?</p>
                            </div>
                            
                            <div class="mpcc-form-section">
                                <label>Select Lesson:</label>
                                <select id="mpcc-modal-lesson-select" class="components-select-control__input">
                                    <option value="">Loading lessons...</option>
                                </select>
                            </div>
                            
                            <div class="mpcc-form-section">
                                <label>Question Type:</label>
                                <select id="mpcc-modal-question-type" class="components-select-control__input">
                                    <option value="multiple_choice" selected>Multiple Choice</option>
                                    <option value="true_false">True/False</option>
                                    <option value="text_answer">Text Answer</option>
                                    <option value="multiple_select">Multiple Select</option>
                                </select>
                            </div>
                            
                            <div class="mpcc-form-section">
                                <label>Number of Questions:</label>
                                <input type="number" id="mpcc-modal-question-count" value="10" min="1" max="20" class="components-text-control__input">
                            </div>
                            
                            <div class="mpcc-quick-actions">
                                <h3>QUICK START</h3>
                                <div class="mpcc-action-buttons">
                                    <button class="mpcc-action-button" data-action="generate-easy">
                                        <span class="dashicons dashicons-smiley"></span>
                                        Generate Easy Questions
                                    </button>
                                    <button class="mpcc-action-button" data-action="generate-medium">
                                        <span class="dashicons dashicons-awards"></span>
                                        Generate Medium Questions
                                    </button>
                                    <button class="mpcc-action-button" data-action="generate-hard">
                                        <span class="dashicons dashicons-superhero"></span>
                                        Generate Hard Questions
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mpcc-chat-section">
                                <textarea 
                                    id="mpcc-quiz-prompt" 
                                    class="mpcc-chat-input"
                                    placeholder="Ask for specific types of questions or provide additional context..."
                                    rows="3"
                                ></textarea>
                                <button id="mpcc-generate-custom" class="components-button is-primary">
                                    Generate Questions
                                </button>
                            </div>
                            
                            <div id="mpcc-quiz-results" style="display: none;">
                                <h3>Generated Questions</h3>
                                <div class="mpcc-questions-preview"></div>
                                <div class="mpcc-apply-section">
                                    <p>Apply these questions to your quiz?</p>
                                    <button id="mpcc-apply-questions" class="components-button is-primary">
                                        Apply Questions
                                    </button>
                                    <button id="mpcc-copy-questions" class="components-button is-secondary">
                                        Copy to Clipboard
                                    </button>
                                    <button id="mpcc-regenerate" class="components-button is-link">
                                        Regenerate
                                    </button>
                                    <p class="mpcc-helper-text">This will add question blocks to your quiz editor.</p>
                                </div>
                            </div>
                            
                            <div id="mpcc-quiz-loading" style="display: none;">
                                <div class="mpcc-loading-spinner">
                                    <span class="spinner is-active"></span>
                                    <p>Generating quiz questions...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            this.loadContextualLessons();
            this.bindModalEvents();
            this.startLessonMonitoring();
        }
        
        /**
         * Monitor for lesson changes in the quiz editor
         */
        startLessonMonitoring() {
            // Monitor form field changes
            this.lessonMonitorInterval = setInterval(() => {
                if (!this.modalOpen) {
                    clearInterval(this.lessonMonitorInterval);
                    return;
                }
                
                const $lessonSelect = $('select[name="_mpcs_lesson_id"], select[name="lesson_id"], #lesson_id, .lesson-selector');
                if ($lessonSelect.length) {
                    const newLessonId = $lessonSelect.val();
                    if (newLessonId && newLessonId !== this.currentLessonId) {
                        this.logger?.log('Lesson changed to:', newLessonId);
                        this.currentLessonId = newLessonId;
                        this.detectionMethod = 'form-update';
                        $('#mpcc-modal-lesson-select').val(newLessonId);
                        this.showAutoDetectionFeedback();
                    }
                }
            }, 1000);
        }

        /**
         * Load lessons based on context - optimized to only load what's needed
         */
        loadContextualLessons() {
            this.logger?.log('Loading contextual lessons', {
                currentLessonId: this.currentLessonId,
                currentCourseId: this.currentCourseId,
                detectionMethod: this.detectionMethod
            });
            
            const $select = $('#mpcc-modal-lesson-select');
            $select.empty().append('<option value="">Loading lessons...</option>');
            
            // Case 1: We have a course ID - load only that course's lessons
            if (this.currentCourseId) {
                this.logger?.log('Loading lessons for specific course:', this.currentCourseId);
                this.loadCourseLessonsOnly();
                return;
            }
            
            // Case 2: We have a lesson ID - load just that lesson and its course siblings
            if (this.currentLessonId) {
                this.logger?.log('Loading lessons based on current lesson:', this.currentLessonId);
                this.loadLessonWithSiblings();
                return;
            }
            
            // Case 3: No context - load recent lessons with pagination
            this.logger?.log('No context found, loading recent lessons');
            this.loadRecentLessons();
        }
        
        /**
         * Load only lessons from a specific course
         */
        loadCourseLessonsOnly() {
            const $select = $('#mpcc-modal-lesson-select');
            
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mpcc_get_course_lessons',
                    course_id: this.currentCourseId,
                    nonce: mpcc_ajax.nonce
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.logger?.log('Course lessons response:', response.data);
                        
                        if (response.data.lessons && response.data.lessons.length > 0) {
                            this.logger?.log('Loaded course lessons:', response.data.lessons.length);
                            
                            // Convert to expected format
                            const lessons = response.data.lessons.map(lesson => ({
                                id: lesson.id,
                                title: { rendered: lesson.title }
                            }));
                            
                            this.populateLessonDropdown($select, lessons);
                        } else {
                            // No lessons in this course
                            $select.empty().append('<option value="">No lessons found in this course</option>');
                        }
                        
                        if (response.data.course_title) {
                            this.showCourseContext(response.data.course_title);
                        }
                    } else {
                        $select.empty().append('<option value="">Failed to load lessons</option>');
                    }
                },
                error: (xhr, status, error) => {
                    this.logger?.error('Failed to load course lessons:', {
                        status: status,
                        error: error,
                        response: xhr.responseText
                    });
                    $select.empty().append('<option value="">Failed to load course lessons</option>');
                }
            });
        }
        
        /**
         * Load a specific lesson and its course siblings
         */
        loadLessonWithSiblings() {
            const $select = $('#mpcc-modal-lesson-select');
            
            // First get the lesson's course
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mpcc_get_lesson_course',
                    lesson_id: this.currentLessonId,
                    nonce: mpcc_ajax.nonce
                },
                success: (response) => {
                    if (response.success && response.data && response.data.course_id) {
                        // We found the course, now load its lessons
                        this.currentCourseId = response.data.course_id;
                        this.loadCourseLessonsOnly();
                    } else {
                        // No course found, just load the single lesson
                        this.loadSingleLesson();
                    }
                },
                error: () => {
                    // Fallback to loading just the single lesson
                    this.loadSingleLesson();
                }
            });
        }
        
        /**
         * Load a single lesson when no course context is found
         */
        loadSingleLesson() {
            const $select = $('#mpcc-modal-lesson-select');
            
            $.get(`/wp-json/wp/v2/mpcs-lesson/${this.currentLessonId}`)
                .done((lesson) => {
                    const lessons = [{
                        id: lesson.id,
                        title: { rendered: lesson.title.rendered }
                    }];
                    
                    $select.empty();
                    $select.append('<option value="">Select a lesson...</option>');
                    $select.append(`<option value="${lesson.id}" selected>${lesson.title.rendered}</option>`);
                    
                    // Add option to load more lessons
                    $select.append('<option value="_load_more" style="font-style: italic;">Load more lessons...</option>');
                    
                    // Handle load more
                    $select.on('change', (e) => {
                        if ($(e.target).val() === '_load_more') {
                            this.loadRecentLessons();
                        }
                    });
                })
                .fail(() => {
                    this.loadRecentLessons();
                });
        }
        
        /**
         * Load recent lessons when no context is available
         */
        loadRecentLessons() {
            const $select = $('#mpcc-modal-lesson-select');
            
            $.get('/wp-json/wp/v2/mpcs-lesson?per_page=50&orderby=modified&order=desc')
                .done((lessons) => {
                    this.populateLessonDropdown($select, lessons);
                    
                    // Add note about limited results
                    if (lessons.length >= 50) {
                        $select.append('<option value="" disabled style="font-style: italic;">Showing 50 most recent lessons</option>');
                    }
                })
                .fail(() => {
                    $select.empty().append('<option value="">Failed to load lessons</option>');
                });
        }
        
        /**
         * Legacy method for backward compatibility
         */
        loadLessons() {
            this.loadContextualLessons();
        }
        
        /**
         * Legacy method - redirects to new optimized loading
         */
        loadLessonsForCourse() {
            this.loadContextualLessons();
        }
        
        /**
         * Filter lessons individually by checking course association
         */
        filterLessonsIndividually(lessons, $select) {
                        const filteredLessons = [];
                        
                        // Get lessons for this specific course by checking meta
                        const courseIdStr = String(this.currentCourseId);
                        
                        // We need to check each lesson's course association
                        // Since the REST API might not expose meta directly, we'll make individual requests
                        // For now, show all lessons but with course indication
                        const lessonPromises = lessons.map(lesson => {
                            return $.ajax({
                                url: mpcc_ajax.ajax_url,
                                type: 'POST',
                                data: {
                                    action: 'mpcc_get_lesson_course',
                                    lesson_id: lesson.id,
                                    nonce: mpcc_ajax.nonce
                                }
                            }).then(response => {
                                if (response.success && response.data) {
                                    // Debug logging
                                    this.logger?.debug('Lesson course check', {
                                        lessonId: lesson.id,
                                        courseid: response.data.course_id,
                                        currentCourse: this.currentCourseId
                                    });
                                    
                                    // Compare as strings to avoid type mismatch
                                    if (String(response.data.course_id) === String(this.currentCourseId)) {
                                        this.logger?.debug('Lesson matches course!', { lessonId: lesson.id });
                                        filteredLessons.push(lesson);
                                    }
                                }
                            }).catch((error) => {
                                // Log errors for debugging
                                this.logger?.error('Error checking lesson', { lessonId: lesson.id, error });
                            });
                        });
                        
                        // Wait for all lesson checks to complete
                        Promise.all(lessonPromises).then(() => {
                            this.logger?.log(`Found ${filteredLessons.length} lessons for course`);
                            this.populateLessonDropdown($select, filteredLessons);
                        });
        }
        
        /**
         * Populate lesson dropdown
         */
        populateLessonDropdown($select, lessons) {
            // Clear the select first
            $select.empty();
            
            if (lessons.length === 0) {
                $select.append('<option value="">No lessons found</option>');
                return;
            }
            
            $select.append('<option value="">Select a lesson...</option>');
            
            let lessonFound = false;
            
            // If we have a current lesson ID but it's not in the list, fetch it separately
            if (this.currentLessonId && !lessons.find(l => l.id == this.currentLessonId)) {
                this.logger?.log('Current lesson not in loaded list, fetching separately', this.currentLessonId);
                
                // Add the current lesson first if we can fetch it
                $.ajax({
                    url: `/wp-json/wp/v2/mpcs-lesson/${this.currentLessonId}`,
                    async: false,
                    success: (lesson) => {
                        $select.append(`<option value="${lesson.id}" selected>${lesson.title.rendered} *</option>`);
                        lessonFound = true;
                        this.logger?.log('Added current lesson from separate fetch', lesson);
                    },
                    error: () => {
                        this.logger?.error('Failed to fetch current lesson', this.currentLessonId);
                    }
                });
            }
            
            // Add all the loaded lessons
            lessons.forEach((lesson) => {
                // Convert both to strings for comparison to handle type mismatches
                const isSelected = String(this.currentLessonId) === String(lesson.id);
                const selected = isSelected ? 'selected' : '';
                if (isSelected) {
                    lessonFound = true;
                    this.logger?.log('Found matching lesson', {
                        currentLessonId: this.currentLessonId,
                        currentLessonIdType: typeof this.currentLessonId,
                        lessonId: lesson.id,
                        lessonIdType: typeof lesson.id,
                        title: lesson.title.rendered
                    });
                }
                $select.append(`<option value="${lesson.id}" ${selected}>${lesson.title.rendered}</option>`);
            });
            
            this.logger?.debug('Lesson selection status', { lessonFound, detectionMethod: this.detectionMethod });
            
            // Show auto-detection feedback
            if (lessonFound && this.detectionMethod) {
                this.logger?.debug('Showing auto-detection feedback');
                this.showAutoDetectionFeedback();
            }
        }
        
        /**
         * Show course context indicator
         */
        showCourseContext(courseTitle) {
            // Add course context indicator above the form
            const $modalBody = $('.mpcc-modal-body');
            
            // Remove any existing course context
            $modalBody.find('.mpcc-course-context').remove();
            
            // Add course context banner
            const $courseContext = $(`
                <div class="mpcc-course-context">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                    <span>Creating quiz for course: <strong>${courseTitle}</strong></span>
                </div>
            `);
            
            $modalBody.find('.mpcc-form-section').first().before($courseContext);
        }
        
        /**
         * Show visual feedback when lesson is auto-detected
         */
        showAutoDetectionFeedback() {
            const $formSection = $('#mpcc-modal-lesson-select').closest('.mpcc-form-section');
            
            // Remove any existing indicators
            $formSection.find('.mpcc-auto-detected').remove();
            
            // Add auto-detected indicator
            const detectionMessages = {
                'url': 'Auto-detected from URL',
                'referrer': 'Auto-detected from previous page',
                'form': 'Auto-detected from quiz form',
                'meta': 'Auto-detected from quiz settings',
                'existing': 'Previously selected lesson',
                'form-update': 'Updated from quiz form'
            };
            
            const message = detectionMessages[this.detectionMethod] || 'Auto-detected';
            
            const $indicator = $(`
                <div class="mpcc-auto-detected">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span class="mpcc-auto-detected-text">${message}</span>
                </div>
            `);
            
            $formSection.append($indicator);
            
            // Add highlight animation to the select field
            $formSection.addClass('mpcc-highlight');
            setTimeout(() => {
                $formSection.removeClass('mpcc-highlight');
            }, 2000);
        }

        /**
         * Bind modal events
         */
        bindModalEvents() {
            // Close modal
            $('.mpcc-modal-close, #mpcc-quiz-ai-modal').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal();
                }
            });
            
            // Quick action buttons
            $('.mpcc-action-button').on('click', (e) => {
                const action = $(e.currentTarget).data('action');
                const difficulty = action.split('-')[1];
                this.generateQuestions(difficulty);
            });
            
            // Custom generation
            $('#mpcc-generate-custom').on('click', () => {
                this.generateQuestions('custom');
            });
            
            // Apply questions
            $('#mpcc-apply-questions').on('click', () => {
                this.applyQuestions();
            });
            
            // Copy questions
            $('#mpcc-copy-questions').on('click', () => {
                this.copyQuestions();
            });
            
            // Regenerate
            $('#mpcc-regenerate').on('click', () => {
                this.generateQuestions('medium');
            });
        }

        /**
         * Generate quiz questions
         */
        generateQuestions(difficulty = 'medium') {
            const lessonId = $('#mpcc-modal-lesson-select').val();
            if (!lessonId) {
                const errorMessage = 'Please select a lesson to generate questions from';
                const suggestion = 'Choose a lesson from the dropdown above to provide content for quiz generation';
                
                this.showNotice(errorMessage, 'warning');
                this.showModalError(errorMessage, suggestion);
                return;
            }
            
            const questionCount = parseInt($('#mpcc-modal-question-count').val()) || 10;
            const questionType = $('#mpcc-modal-question-type').val() || 'multiple_choice';
            const customPrompt = $('#mpcc-quiz-prompt').val();
            
            this.logger?.log('Generating questions', { type: questionType, count: questionCount, difficulty });
            
            // Clear any previous errors
            $('#mpcc-modal-error').hide().empty();
            
            $('#mpcc-quiz-results').hide();
            $('#mpcc-quiz-loading').show();
            
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mpcc_generate_quiz',
                    lesson_id: parseInt(lessonId),
                    nonce: mpcc_ajax.nonce,
                    'options[num_questions]': questionCount,
                    'options[question_type]': questionType,
                    'options[difficulty]': difficulty,
                    'options[custom_prompt]': customPrompt
                },
                success: (response) => {
                    if (response.success && response.data.questions) {
                        this.generatedQuestions = response.data.questions;
                        this.displayQuestions();
                        
                        // Clear any previous errors
                        $('#mpcc-modal-error').hide();
                    } else {
                        // Handle validation errors that come back as success response
                        const errorMessage = response.data?.message || 'Failed to generate questions';
                        const suggestion = response.data?.suggestion || '';
                        
                        this.showNotice(errorMessage, 'error');
                        this.showModalError(errorMessage, suggestion);
                    }
                },
                error: (xhr, status, error) => {
                    this.logger?.error('AJAX error', { status, error });
                    
                    let errorMessage = 'Failed to generate questions';
                    let suggestion = '';
                    
                    // Try to extract error message from response
                    if (xhr.responseJSON) {
                        // Check for the new error structure with nested data
                        if (xhr.responseJSON.data && xhr.responseJSON.data.error) {
                            const errorObj = xhr.responseJSON.data.error;
                            errorMessage = errorObj.message || errorMessage;
                            
                            // Extract suggestion from error data
                            if (errorObj.data && errorObj.data.suggestion) {
                                suggestion = errorObj.data.suggestion;
                            }
                        } 
                        // Check for WordPress error structure
                        else if (xhr.responseJSON.error) {
                            const errorObj = xhr.responseJSON.error;
                            errorMessage = errorObj.message || errorMessage;
                            
                            // Extract suggestion from error data
                            if (errorObj.data && errorObj.data.suggestion) {
                                suggestion = errorObj.data.suggestion;
                            }
                        }
                        // Fall back to old structure
                        else if (xhr.responseJSON.data) {
                            if (xhr.responseJSON.data.message) {
                                errorMessage = xhr.responseJSON.data.message;
                            }
                            if (xhr.responseJSON.data.suggestion) {
                                suggestion = xhr.responseJSON.data.suggestion;
                            }
                        }
                    } else if (xhr.responseText) {
                        // Try to parse response text if JSON parsing failed
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            }
                        } catch (e) {
                            // If all else fails, show the status text
                            errorMessage = xhr.statusText || error || 'Unknown error occurred';
                        }
                    }
                    
                    // Show the error notice with suggestion if available
                    const fullMessage = suggestion ? `${errorMessage}\n\n${suggestion}` : errorMessage;
                    this.showNotice(fullMessage, 'error');
                    
                    // Also show error in the modal for better visibility
                    this.showModalError(errorMessage, suggestion);
                },
                complete: () => {
                    $('#mpcc-quiz-loading').hide();
                }
            });
        }

        /**
         * Display generated questions
         */
        displayQuestions() {
            const $container = $('.mpcc-questions-preview');
            $container.empty();
            
            this.generatedQuestions.forEach((question, index) => {
                // Get the question text based on question type
                let questionText = '';
                if (question.type === 'true_false') {
                    questionText = question.statement || question.question || '';
                } else {
                    questionText = question.question || question.text || '';
                }
                
                let questionHtml = `
                    <div class="mpcc-question-preview">
                        <h4>Question ${index + 1}</h4>
                        <p class="mpcc-question-text">${questionText}</p>
                `;
                
                // Handle different question types
                if (question.type === 'true_false') {
                    // Convert boolean to string for comparison
                    const correctAnswer = String(question.correct_answer);
                    questionHtml += `
                        <ul class="mpcc-question-options">
                            <li class="${correctAnswer === 'true' ? 'correct' : ''}">True</li>
                            <li class="${correctAnswer === 'false' ? 'correct' : ''}">False</li>
                        </ul>
                    `;
                } else if (question.type === 'text_answer') {
                    questionHtml += `
                        <div class="mpcc-text-answer">
                            <p><strong>Expected Answer:</strong> ${question.correct_answer || question.expected_answer || 'Open-ended response'}</p>
                        </div>
                    `;
                } else if (question.type === 'multiple_select') {
                    questionHtml += `
                        <ul class="mpcc-question-options">
                            ${Object.entries(question.options).map(([key, value]) => {
                                const isCorrect = question.correct_answers ? question.correct_answers.includes(key) : false;
                                return `<li class="${isCorrect ? 'correct' : ''}">${key}) ${value}</li>`;
                            }).join('')}
                        </ul>
                    `;
                } else {
                    // Default to multiple-choice display
                    questionHtml += `
                        <ul class="mpcc-question-options">
                            ${Object.entries(question.options).map(([key, value]) => `
                                <li class="${key === question.correct_answer ? 'correct' : ''}">
                                    ${key}) ${value}
                                </li>
                            `).join('')}
                        </ul>
                    `;
                }
                
                questionHtml += `
                        ${question.explanation ? `<p class="mpcc-explanation"><em>Explanation: ${question.explanation}</em></p>` : ''}
                    </div>
                `;
                
                $container.append(questionHtml);
            });
            
            $('#mpcc-quiz-results').show();
        }

        /**
         * Apply questions to the editor
         */
        async applyQuestions() {
            if (!this.generatedQuestions.length) return;
            
            // Get the current post ID (quiz ID)
            const quizId = wp.data.select('core/editor').getCurrentPostId();
            this.logger?.log('Current quiz ID:', quizId);
            
            // Show loading state
            this.showNotice('Adding questions to editor...', 'info');
            
            try {
                const dispatch = wp.data.dispatch('memberpress/course/question');
                const blocks = [];
                
                // Process each question
                for (let i = 0; i < this.generatedQuestions.length; i++) {
                    const block = await this.createQuestionBlock(this.generatedQuestions[i], i, quizId, dispatch);
                    if (block) {
                        blocks.push(block);
                    }
                }
                
                // Insert all blocks at once
                if (blocks.length > 0) {
                    this.insertBlocksAndUpdateUI(blocks);
                }
                
            } catch (error) {
                this.logger?.error('Error adding questions:', error);
                this.showNotice('Error adding questions. Please try again.', 'error');
            }
            
            // Close modal
            this.closeModal();
        }
        
        /**
         * Create a question block from question data
         */
        async createQuestionBlock(question, index, quizId, dispatch) {
            // Determine the block type
            const blockType = this.getBlockTypeForQuestion(question.type);
            
            // Generate a unique client ID for this block
            const clientId = wp.blocks.createBlock(blockType, {}).clientId;
            
            // Prepare question data
            const questionData = this.prepareQuestionData(question, index);
            
            this.logger?.debug(`Adding placeholder for question ${index + 1}:`, questionData);
            
            // Add placeholder to store with the client ID
            if (dispatch && dispatch.addPlaceholder) {
                dispatch.addPlaceholder(clientId, questionData);
            }
            
            // Reserve a real question ID from the API
            const questionId = await this.reserveQuestionId(quizId, clientId, dispatch);
            
            // Create the block with just the question ID
            // The actual question data is stored in the Redux store via addPlaceholder
            const block = wp.blocks.createBlock(blockType, {
                questionId: questionId || 0
            });
            
            // If we didn't get a reserved ID, ensure the block uses our clientId
            // so it matches the placeholder we added to the store
            if (questionId === 0) {
                block.clientId = clientId;
            }
            
            return block;
        }
        
        /**
         * Get the block type for a question type
         */
        getBlockTypeForQuestion(questionType) {
            const type = questionType || 'multiple_choice';
            
            switch (type) {
                case 'true_false':
                    return 'memberpress-courses/true-false-question';
                case 'text_answer':
                    return 'memberpress-courses/short-answer-question';
                case 'multiple_select':
                    return 'memberpress-courses/multiple-answer-question';
                default:
                    return 'memberpress-courses/multiple-choice-question';
            }
        }
        
        /**
         * Prepare question data based on type
         */
        prepareQuestionData(question, index) {
            const questionType = question.type || 'multiple_choice';
            
            // Get question text based on type
            let questionText = '';
            if (questionType === 'true_false') {
                questionText = question.statement || question.question || '';
            } else {
                questionText = question.question || question.text || '';
            }
            
            // Base question data
            let questionData = {
                question: questionText,
                type: questionType,
                number: index + 1,
                required: true,
                points: 1,
                feedback: question.explanation || ''
            };
            
            // Add type-specific data
            switch (questionType) {
                case 'true_false':
                    return this.prepareTrueFalseData(question, questionData);
                case 'text_answer':
                    return this.prepareTextAnswerData(question, questionData);
                case 'multiple_select':
                    return this.prepareMultipleSelectData(question, questionData);
                default:
                    return this.prepareMultipleChoiceData(question, questionData);
            }
        }
        
        /**
         * Prepare true/false question data
         */
        prepareTrueFalseData(question, baseData) {
            // Convert to boolean - handle both string and boolean values
            baseData.correctAnswer = String(question.correct_answer) === 'true';
            baseData.type = 'true-false';
            return baseData;
        }
        
        /**
         * Prepare text answer question data
         */
        prepareTextAnswerData(question, baseData) {
            baseData.expectedAnswer = question.correct_answer || question.expected_answer || '';
            baseData.type = 'short-answer';
            return baseData;
        }
        
        /**
         * Prepare multiple select question data
         */
        prepareMultipleSelectData(question, baseData) {
            // For multiple select, we need answers array with indices of correct answers
            const options = Object.entries(question.options);
            baseData.options = options.map(([key, value]) => ({
                value: value,
                isCorrect: question.correct_answers ? question.correct_answers.includes(key) : false
            }));
            // Create answer array with indices of correct options
            baseData.answer = [];
            options.forEach(([key, value], index) => {
                if (question.correct_answers && question.correct_answers.includes(key)) {
                    baseData.answer.push(index);
                }
            });
            baseData.type = 'multiple-answer';
            return baseData;
        }
        
        /**
         * Prepare multiple choice question data
         */
        prepareMultipleChoiceData(question, baseData) {
            // Multiple choice - single correct answer
            const options = Object.entries(question.options);
            baseData.options = options.map(([key, value]) => ({
                value: value,
                isCorrect: key === question.correct_answer
            }));
            // Find the index of the correct answer
            baseData.answer = options.findIndex(([key, value]) => key === question.correct_answer);
            baseData.type = 'multiple-choice';
            return baseData;
        }
        
        /**
         * Reserve a question ID from the API
         */
        async reserveQuestionId(quizId, clientId, dispatch) {
            let questionId = 0;
            if (dispatch && dispatch.getNextQuestionId) {
                try {
                    const result = await dispatch.getNextQuestionId(quizId, clientId);
                    if (result && result.id) {
                        questionId = result.id;
                        this.logger?.log(`Reserved question ID ${questionId} for client ${clientId}`);
                    }
                } catch (err) {
                    this.logger?.warn('Could not reserve question ID:', err);
                }
            }
            return questionId;
        }
        
        /**
         * Insert blocks into editor and update UI
         */
        insertBlocksAndUpdateUI(blocks) {
            wp.data.dispatch('core/block-editor').insertBlocks(blocks);
            
            // Mark the post as dirty to enable the save button
            wp.data.dispatch('core/editor').editPost({ meta: { _edit_lock: Date.now() } });
            
            // Log inserted blocks for debugging
            this.logger?.debug('Inserted blocks:', blocks);
            
            // Debug logging if enabled
            if (this.logger?.isEnabled()) {
                this.logDebugInfo();
            }
            
            this.showNotice(`Successfully added ${blocks.length} questions! Click "Update" to save them.`, 'success');
            
            // Highlight the save button to draw user attention
            this.highlightSaveButton();
        }
        
        /**
         * Log debug information about inserted blocks
         */
        logDebugInfo() {
            setTimeout(() => {
                const allBlocks = wp.data.select('core/block-editor').getBlocks();
                this.logger?.debug('All blocks after insertion:', allBlocks);
                
                // Check specifically for our question blocks
                const questionBlocks = allBlocks.filter(block => 
                    block.name && block.name.includes('question')
                );
                this.logger?.debug('Question blocks found:', questionBlocks);
                
                // Log the attributes of each question block
                questionBlocks.forEach((block, index) => {
                    this.logger?.debug(`Question ${index + 1} attributes:`, block.attributes);
                });
                
                // Also check the question store state
                if (wp.data.select('memberpress/course/question')) {
                    const questionStore = wp.data.select('memberpress/course/question');
                    this.logger?.debug('Question store state:', {
                        placeholders: questionStore.getPlaceholders ? questionStore.getPlaceholders() : 'No getPlaceholders method',
                        questions: questionStore.getQuestions ? questionStore.getQuestions() : 'No getQuestions method'
                    });
                }
                
                // Add a save listener to see what happens when saving
                const saveListener = wp.data.subscribe(() => {
                    const isSaving = wp.data.select('core/editor').isSavingPost();
                    const isAutosaving = wp.data.select('core/editor').isAutosavingPost();
                    
                    if (isSaving && !isAutosaving) {
                        this.logger?.debug('Saving post, checking question blocks...');
                        const blocksBeforeSave = wp.data.select('core/block-editor').getBlocks()
                            .filter(block => block.name && block.name.includes('question'));
                        
                        blocksBeforeSave.forEach((block, index) => {
                            this.logger?.debug(`Block ${index + 1} before save:`, {
                                name: block.name,
                                attributes: block.attributes
                            });
                        });
                        
                        // Unsubscribe after logging once
                        saveListener();
                    }
                });
            }, 500);
        }
        
        /**
         * Highlight the save button to draw user attention
         */
        highlightSaveButton() {
            setTimeout(() => {
                const saveButton = document.querySelector('.editor-post-publish-button, .editor-post-publish-panel__toggle');
                if (saveButton) {
                    saveButton.style.animation = 'pulse 2s ease-in-out 3';
                    saveButton.style.boxShadow = '0 0 0 4px rgba(102, 126, 234, 0.5)';
                    setTimeout(() => {
                        saveButton.style.animation = '';
                        saveButton.style.boxShadow = '';
                    }, 6000);
                }
            }, 500);
        }

        /**
         * Copy questions to clipboard
         */
        copyQuestions() {
            const questionsText = this.generatedQuestions.map((q, i) => {
                // Get question text based on type
                let questionText = '';
                if (q.type === 'true_false') {
                    questionText = q.statement || q.question || '';
                } else {
                    questionText = q.question || q.text || '';
                }
                
                let text = `Question ${i + 1}: ${questionText}\n`;
                
                // Handle different question types
                if (q.type === 'true_false') {
                    // Convert to string for comparison
                    text += `Answer: ${String(q.correct_answer) === 'true' ? 'True' : 'False'}`;
                } else if (q.type === 'text_answer') {
                    text += `Expected Answer: ${q.correct_answer || q.expected_answer || 'Open-ended response'}`;
                } else if (q.type === 'multiple_select') {
                    text += Object.entries(q.options).map(([key, value]) => {
                        const isCorrect = q.correct_answers ? q.correct_answers.includes(key) : false;
                        return `${key}) ${value} ${isCorrect ? '(Correct)' : ''}`;
                    }).join('\n');
                } else {
                    // Multiple choice
                    text += Object.entries(q.options).map(([key, value]) => 
                        `${key}) ${value} ${key === q.correct_answer ? '(Correct)' : ''}`
                    ).join('\n');
                }
                
                if (q.explanation) {
                    text += `\nExplanation: ${q.explanation}`;
                }
                
                return text;
            }).join('\n\n');
            
            navigator.clipboard.writeText(questionsText).then(() => {
                this.showNotice('Questions copied to clipboard!', 'success');
            });
        }

        /**
         * Close modal
         */
        closeModal() {
            $('#mpcc-quiz-ai-modal').remove();
            this.modalOpen = false;
            
            // Clean up monitoring interval
            if (this.lessonMonitorInterval) {
                clearInterval(this.lessonMonitorInterval);
                this.lessonMonitorInterval = null;
            }
        }

        /**
         * Show notice
         */
        showNotice(message, type = 'info') {
            wp.data.dispatch('core/notices').createNotice(
                type,
                message,
                {
                    type: 'snackbar',
                    isDismissible: true
                }
            );
        }

        /**
         * Show error directly in the modal
         */
        showModalError(errorMessage, suggestion = '') {
            // Hide loading state
            $('#mpcc-quiz-loading').hide();
            
            // Check if error container exists, if not create it
            let $errorContainer = $('#mpcc-modal-error');
            if (!$errorContainer.length) {
                $errorContainer = $('<div id="mpcc-modal-error" class="mpcc-modal-error"></div>');
                $('.mpcc-modal-body').prepend($errorContainer);
            }
            
            // Build error HTML
            let errorHtml = `
                <div class="notice notice-error">
                    <p><strong>Error:</strong> ${errorMessage}</p>
                    ${suggestion ? `<p><em>${suggestion}</em></p>` : ''}
                </div>
            `;
            
            // Show error
            $errorContainer.html(errorHtml).show();
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                $errorContainer.fadeOut();
            }, 10000);
        }
    }

    // Initialize
    new MPCCQuizAIModal();

})(jQuery);