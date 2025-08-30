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
            this.init();
        }

        init() {
            console.log('MPCC Quiz AI Modal: Initializing...');
            
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
            
            console.log('MPCC Quiz AI: Auto-open check - hasLessonContext:', hasLessonContext, 'isNewQuiz:', isNewQuiz);
            
            if (hasLessonContext && isNewQuiz) {
                console.log('MPCC Quiz AI: Auto-opening modal from lesson context');
                
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
            console.log('MPCC Quiz AI: Detecting lesson context...');
            this.detectionMethod = null;
            this.currentCourseId = null;
            
            // Method 1: Check URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const lessonIdFromUrl = urlParams.get('lesson_id') || urlParams.get('lesson') || urlParams.get('from_lesson');
            const courseIdFromUrl = urlParams.get('course_id') || urlParams.get('course');
            
            if (lessonIdFromUrl) {
                this.currentLessonId = lessonIdFromUrl;
                this.detectionMethod = 'url';
                console.log('MPCC Quiz AI: Detected lesson ID from URL:', this.currentLessonId);
            }
            
            if (courseIdFromUrl) {
                this.currentCourseId = courseIdFromUrl;
                console.log('MPCC Quiz AI: Detected course ID from URL:', this.currentCourseId);
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
                            this.currentLessonId = lesson.id;
                            this.detectionMethod = 'referrer';
                            console.log('MPCC Quiz AI: Detected lesson ID from referrer:', this.currentLessonId);
                        },
                        error: () => {
                            // Silently fail - referrer might not be a lesson
                            console.log('MPCC Quiz AI: Referrer post is not a lesson');
                        }
                    });
                }
            }
            
            // Method 3: Check for lesson selector in the quiz form
            if (!this.currentLessonId) {
                const $lessonSelect = $('select[name="_mpcs_lesson_id"], select[name="lesson_id"], #lesson_id, .lesson-selector');
                console.log('MPCC Quiz AI: Looking for lesson selectors, found:', $lessonSelect.length);
                if ($lessonSelect.length) {
                    console.log('MPCC Quiz AI: Lesson selector value:', $lessonSelect.val());
                    if ($lessonSelect.val()) {
                        this.currentLessonId = $lessonSelect.val();
                        this.detectionMethod = 'form';
                        console.log('MPCC Quiz AI: Detected lesson ID from form field:', this.currentLessonId);
                    }
                }
            }
            
            // Method 4: Check post meta fields
            if (!this.currentLessonId) {
                const $metaInput = $('input[name="_lesson_id"], input[name="mpcs_lesson_id"]');
                if ($metaInput.length && $metaInput.val()) {
                    this.currentLessonId = $metaInput.val();
                    this.detectionMethod = 'meta';
                    console.log('MPCC Quiz AI: Detected lesson ID from meta field:', this.currentLessonId);
                }
            }
            
            // Method 5: Check if quiz already has associated lesson (for existing quizzes)
            if (!this.currentLessonId && wp && wp.data) {
                const postId = wp.data.select('core/editor').getCurrentPostId();
                if (postId) {
                    const postMeta = wp.data.select('core/editor').getEditedPostAttribute('meta');
                    if (postMeta && postMeta._mpcs_lesson_id) {
                        this.currentLessonId = postMeta._mpcs_lesson_id;
                        this.detectionMethod = 'existing';
                        console.log('MPCC Quiz AI: Detected lesson ID from existing quiz meta:', this.currentLessonId);
                    }
                    // Also check for course ID in meta
                    if (!this.currentCourseId && postMeta && postMeta._mpcs_course_id) {
                        this.currentCourseId = postMeta._mpcs_course_id;
                        console.log('MPCC Quiz AI: Detected course ID from existing quiz meta:', this.currentCourseId);
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
                        console.log('MPCC Quiz AI: Potential course ID from referrer:', this.pendingCourseId);
                    }
                }
            }
            
            if (!this.currentLessonId && !this.currentCourseId) {
                console.log('MPCC Quiz AI: No lesson or course context detected');
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
                                    <option value="multiple-choice" selected>Multiple Choice</option>
                                    <option value="true-false">True/False</option>
                                    <option value="text-answer">Text Answer</option>
                                    <option value="multiple-select">Multiple Select</option>
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
            this.loadLessons();
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
                        console.log('MPCC Quiz AI: Lesson changed to:', newLessonId);
                        this.currentLessonId = newLessonId;
                        this.detectionMethod = 'form-update';
                        $('#mpcc-modal-lesson-select').val(newLessonId);
                        this.showAutoDetectionFeedback();
                    }
                }
            }, 1000);
        }

        /**
         * Load available lessons
         */
        loadLessons() {
            console.log('MPCC Quiz AI: Loading lessons, current lesson ID:', this.currentLessonId, 'current course ID:', this.currentCourseId, 'detection method:', this.detectionMethod);
            
            // Build API endpoint with course filter if available
            let apiUrl = '/wp-json/wp/v2/mpcs-lesson?per_page=100';
            
            // If we have a pending course ID (from referrer), verify it first
            if (this.pendingCourseId && !this.currentCourseId) {
                $.get(`/wp-json/wp/v2/mpcs-course/${this.pendingCourseId}`)
                    .done((course) => {
                        this.currentCourseId = course.id;
                        console.log('MPCC Quiz AI: Verified course from referrer:', course.id, course.title.rendered);
                        this.showCourseContext(course.title.rendered);
                        this.loadLessonsForCourse();
                    })
                    .fail(() => {
                        console.log('MPCC Quiz AI: Pending course ID was not a valid course');
                        this.loadLessonsForCourse();
                    });
                return;
            }
            
            this.loadLessonsForCourse();
        }
        
        /**
         * Load lessons, optionally filtered by course
         */
        loadLessonsForCourse() {
            console.log('MPCC Quiz AI: Loading lessons for course:', this.currentCourseId || 'all courses');
            
            // Get all lessons first
            $.get('/wp-json/wp/v2/mpcs-lesson?per_page=100')
                .done((lessons) => {
                    const $select = $('#mpcc-modal-lesson-select');
                    $select.empty();
                    
                    let filteredLessons = lessons;
                    let courseTitle = '';
                    
                    // If we have a course ID, filter lessons
                    if (this.currentCourseId) {
                        console.log('MPCC Quiz AI: Filtering lessons for course ID:', this.currentCourseId);
                        
                        // First try to get course sections to find lessons more efficiently
                        $.ajax({
                            url: mpcc_ajax.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'mpcc_get_course_lessons',
                                course_id: this.currentCourseId,
                                nonce: mpcc_ajax.nonce
                            },
                            success: (response) => {
                                if (response.success && response.data && response.data.lessons) {
                                    console.log('MPCC Quiz AI: Got course lessons directly:', response.data.lessons.length);
                                    // Filter the loaded lessons to match the course lessons
                                    const courseLessonIds = response.data.lessons.map(l => String(l.id));
                                    filteredLessons = lessons.filter(lesson => 
                                        courseLessonIds.includes(String(lesson.id))
                                    );
                                    this.populateLessonDropdown($select, filteredLessons);
                                    return;
                                }
                                
                                // Fallback to individual checks
                                console.log('MPCC Quiz AI: Falling back to individual lesson checks');
                                this.filterLessonsIndividually(lessons, $select);
                            },
                            error: () => {
                                // Fallback to individual checks
                                console.log('MPCC Quiz AI: Error getting course lessons, falling back to individual checks');
                                this.filterLessonsIndividually(lessons, $select);
                            }
                        });
                        
                        return;
                    }
                    
                    // No course filter, show all lessons
                    this.populateLessonDropdown($select, filteredLessons);
                })
                .fail(() => {
                    $('#mpcc-modal-lesson-select').html('<option value="">Failed to load lessons</option>');
                });
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
                                    console.log('MPCC Quiz AI: Lesson', lesson.id, 'course_id:', response.data.course_id, 'current course:', this.currentCourseId);
                                    
                                    // Compare as strings to avoid type mismatch
                                    if (String(response.data.course_id) === String(this.currentCourseId)) {
                                        console.log('MPCC Quiz AI: Lesson', lesson.id, 'matches course!');
                                        filteredLessons.push(lesson);
                                    }
                                }
                            }).catch((error) => {
                                // Log errors for debugging
                                console.log('MPCC Quiz AI: Error checking lesson', lesson.id, error);
                            });
                        });
                        
                        // Wait for all lesson checks to complete
                        Promise.all(lessonPromises).then(() => {
                            console.log('MPCC Quiz AI: Found', filteredLessons.length, 'lessons for course');
                            this.populateLessonDropdown($select, filteredLessons);
                        });
        }
        
        /**
         * Populate lesson dropdown
         */
        populateLessonDropdown($select, lessons) {
            if (lessons.length === 0) {
                $select.append('<option value="">No lessons found</option>');
                return;
            }
            
            $select.append('<option value="">Select a lesson...</option>');
            
            let lessonFound = false;
            lessons.forEach((lesson) => {
                const selected = this.currentLessonId == lesson.id ? 'selected' : '';
                if (selected) {
                    lessonFound = true;
                    console.log('MPCC Quiz AI: Found matching lesson:', lesson.id, lesson.title.rendered);
                }
                $select.append(`<option value="${lesson.id}" ${selected}>${lesson.title.rendered}</option>`);
            });
            
            console.log('MPCC Quiz AI: Lesson found:', lessonFound, 'detection method:', this.detectionMethod);
            
            // Show auto-detection feedback
            if (lessonFound && this.detectionMethod) {
                console.log('MPCC Quiz AI: Showing auto-detection feedback');
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
                this.showNotice('Please select a lesson first', 'warning');
                return;
            }
            
            const questionCount = parseInt($('#mpcc-modal-question-count').val()) || 10;
            const questionType = $('#mpcc-modal-question-type').val() || 'multiple-choice';
            const customPrompt = $('#mpcc-quiz-prompt').val();
            
            console.log('MPCC Quiz AI: Generating questions with type:', questionType);
            
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
                    } else {
                        this.showNotice(response.data?.message || 'Failed to generate questions', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotice(`Error: ${error}`, 'error');
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
                let questionHtml = `
                    <div class="mpcc-question-preview">
                        <h4>Question ${index + 1}</h4>
                        <p class="mpcc-question-text">${question.question || question.text}</p>
                `;
                
                // Handle different question types
                if (question.type === 'true-false') {
                    questionHtml += `
                        <ul class="mpcc-question-options">
                            <li class="${question.correct_answer === 'true' ? 'correct' : ''}">True</li>
                            <li class="${question.correct_answer === 'false' ? 'correct' : ''}">False</li>
                        </ul>
                    `;
                } else if (question.type === 'text-answer') {
                    questionHtml += `
                        <div class="mpcc-text-answer">
                            <p><strong>Expected Answer:</strong> ${question.correct_answer || question.expected_answer || 'Open-ended response'}</p>
                        </div>
                    `;
                } else if (question.type === 'multiple-select') {
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
            console.log('Current quiz ID:', quizId);
            
            // Show loading state
            this.showNotice('Adding questions to editor...', 'info');
            
            try {
                const dispatch = wp.data.dispatch('memberpress/course/question');
                const blocks = [];
                
                // Process each question
                for (let i = 0; i < this.generatedQuestions.length; i++) {
                    const question = this.generatedQuestions[i];
                    
                    // Determine the block type based on question type
                    const questionType = question.type || 'multiple-choice';
                    let blockType = 'memberpress-courses/multiple-choice-question';
                    
                    switch (questionType) {
                        case 'true-false':
                            blockType = 'memberpress-courses/true-false-question';
                            break;
                        case 'text-answer':
                            blockType = 'memberpress-courses/text-answer-question';
                            break;
                        case 'multiple-select':
                            blockType = 'memberpress-courses/multiple-select-question';
                            break;
                    }
                    
                    // Generate a unique client ID for this block
                    const clientId = wp.blocks.createBlock(blockType, {}).clientId;
                    
                    // Prepare question data based on type
                    let questionData = {
                        question: question.question || question.text,
                        type: questionType,
                        number: i + 1,
                        required: true,
                        points: 1,
                        feedback: question.explanation || ''
                    };
                    
                    // Add type-specific data
                    if (questionType === 'true-false') {
                        questionData.correctAnswer = question.correct_answer === 'true';
                    } else if (questionType === 'text-answer') {
                        questionData.expectedAnswer = question.correct_answer || question.expected_answer || '';
                    } else if (questionType === 'multiple-select') {
                        questionData.options = Object.entries(question.options).map(([key, value]) => ({
                            value: value,
                            isCorrect: question.correct_answers ? question.correct_answers.includes(key) : false
                        }));
                    } else {
                        // Multiple choice
                        questionData.options = Object.entries(question.options).map(([key, value]) => ({
                            value: value,
                            isCorrect: key === question.correct_answer
                        }));
                    }
                    
                    console.log(`Adding placeholder for question ${i + 1}:`, questionData);
                    
                    // Add placeholder to store with the client ID
                    if (dispatch && dispatch.addPlaceholder) {
                        dispatch.addPlaceholder(clientId, questionData);
                    }
                    
                    // Now reserve a real question ID from the API
                    let questionId = 0;
                    if (dispatch && dispatch.getNextQuestionId) {
                        try {
                            const result = await dispatch.getNextQuestionId(quizId, clientId);
                            if (result && result.id) {
                                questionId = result.id;
                                console.log(`Reserved question ID ${questionId} for client ${clientId}`);
                            }
                        } catch (err) {
                            console.warn('Could not reserve question ID:', err);
                        }
                    }
                    
                    // Create the block with the reserved question ID
                    const block = wp.blocks.createBlock(blockType, {
                        questionId: questionId
                    });
                    
                    // If we didn't get a reserved ID, ensure the block uses our clientId
                    // so it matches the placeholder we added to the store
                    if (questionId === 0) {
                        block.clientId = clientId;
                    }
                    
                    blocks.push(block);
                }
                
                // Insert all blocks at once
                if (blocks.length > 0) {
                    wp.data.dispatch('core/block-editor').insertBlocks(blocks);
                    
                    // Mark the post as dirty to enable the save button
                    wp.data.dispatch('core/editor').editPost({ meta: { _edit_lock: Date.now() } });
                    
                    this.showNotice(`Successfully added ${blocks.length} questions! Click "Update" to save them.`, 'success');
                    
                    // Highlight the save button to draw user attention
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
                
            } catch (error) {
                console.error('Error adding questions:', error);
                this.showNotice('Error adding questions. Please try again.', 'error');
            }
            
            // Close modal
            this.closeModal();
        }

        /**
         * Copy questions to clipboard
         */
        copyQuestions() {
            const questionsText = this.generatedQuestions.map((q, i) => {
                let text = `Question ${i + 1}: ${q.question || q.text}\n`;
                
                // Handle different question types
                if (q.type === 'true-false') {
                    text += `Answer: ${q.correct_answer === 'true' ? 'True' : 'False'}`;
                } else if (q.type === 'text-answer') {
                    text += `Expected Answer: ${q.correct_answer || q.expected_answer || 'Open-ended response'}`;
                } else if (q.type === 'multiple-select') {
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
    }

    // Initialize
    new MPCCQuizAIModal();

})(jQuery);