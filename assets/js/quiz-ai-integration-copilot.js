/**
 * MemberPress Courses Copilot - Quiz AI Integration
 * Proper integration matching Courses Copilot design system
 *
 * @package MemberPressCoursesCopilot
 * @version 2.0.0
 */

(function($) {
    'use strict';

    // Quiz AI Integration class
    class MPCCQuizAICopilot {
        constructor() {
            this.isGenerating = false;
            this.currentQuizId = null;
            this.currentLessonId = null;
            this.init();
        }

        init() {
            console.log('MPCC Quiz AI: Initializing Copilot version...');
            
            // Only run on quiz edit pages
            if (!$('body').hasClass('post-type-mpcs-quiz')) {
                console.log('MPCC Quiz AI: Not on quiz page');
                return;
            }
            
            // Wait for page to load
            $(document).ready(() => {
                this.setupInterface();
                this.detectLessonId();
            });
        }

        /**
         * Setup the AI interface
         */
        setupInterface() {
            // Get the current quiz ID
            this.currentQuizId = $('#post_ID').val() || $('input[name="post_ID"]').val();
            
            // Create the AI Assistant metabox if it doesn't exist
            this.createMetabox();
            
            // Add Generate with AI button to toolbar
            this.addToolbarButton();
        }

        /**
         * Create AI Assistant metabox
         */
        createMetabox() {
            // Check if metabox already exists
            if ($('#mpcc-quiz-ai-metabox').length > 0) {
                return;
            }

            // Find the quiz settings metabox area
            const $metaboxArea = $('#postbox-container-1, .metabox-holder').first();
            if (!$metaboxArea.length) {
                console.error('MPCC Quiz AI: Could not find metabox area');
                return;
            }

            const metaboxHtml = `
                <div id="mpcc-quiz-ai-metabox" class="postbox">
                    <div class="postbox-header">
                        <h2 class="hndle">
                            <span class="dashicons dashicons-admin-generic"></span>
                            AI Quiz Generator
                        </h2>
                        <div class="handle-actions">
                            <button type="button" class="handlediv" aria-expanded="true">
                                <span class="screen-reader-text">Toggle panel</span>
                                <span class="toggle-indicator" aria-hidden="true"></span>
                            </button>
                        </div>
                    </div>
                    <div class="inside">
                        <div class="mpcc-quiz-ai-content">
                            <p class="mpcc-text-muted">Generate multiple-choice questions from lesson content using AI.</p>
                            
                            <div class="mpcc-form-group">
                                <label class="mpcc-form-label">Lesson Selection</label>
                                <select id="mpcc-lesson-select" class="mpcc-form-input" style="width: 100%;">
                                    <option value="">Select a lesson...</option>
                                </select>
                            </div>
                            
                            <div class="mpcc-form-group">
                                <label class="mpcc-form-label">Number of Questions</label>
                                <input type="number" id="mpcc-question-count" class="mpcc-form-input" value="10" min="1" max="20" style="width: 100px;">
                            </div>
                            
                            <button id="mpcc-generate-quiz-btn" class="button button-primary mpcc-btn mpcc-btn--primary" style="width: 100%;">
                                <span class="dashicons dashicons-sparkles"></span>
                                Generate Quiz Questions
                            </button>
                            
                            <div id="mpcc-quiz-results" class="mpcc-mt-md" style="display: none;">
                                <h4>Generated Questions</h4>
                                <div class="mpcc-quiz-questions"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $metaboxArea.append(metaboxHtml);

            // Load available lessons
            this.loadAvailableLessons();
            
            // Bind events
            this.bindMetaboxEvents();

            // Handle postbox toggle
            $('#mpcc-quiz-ai-metabox').on('click', '.handlediv', function() {
                $(this).closest('.postbox').toggleClass('closed');
            });
        }

        /**
         * Add button to editor toolbar
         */
        addToolbarButton() {
            // Find editor toolbar
            const $toolbar = $('.edit-post-header__toolbar, .editor-header__toolbar').first();
            if (!$toolbar.length) {
                console.log('MPCC Quiz AI: Toolbar not found, button will be in metabox only');
                return;
            }

            // Check if button already exists
            if ($('#mpcc-generate-quiz-toolbar').length > 0) {
                return;
            }

            const buttonHtml = `
                <div class="mpcc-quiz-ai-toolbar-wrapper" style="margin-left: 8px;">
                    <button id="mpcc-generate-quiz-toolbar" class="components-button is-secondary" type="button">
                        <span class="dashicons dashicons-admin-generic" style="margin-right: 4px;"></span>
                        Generate with AI
                    </button>
                </div>
            `;

            $toolbar.append(buttonHtml);

            // Bind click event
            $('#mpcc-generate-quiz-toolbar').on('click', (e) => {
                e.preventDefault();
                // Scroll to AI metabox and open it
                const $metabox = $('#mpcc-quiz-ai-metabox');
                $metabox.removeClass('closed');
                $('html, body').animate({
                    scrollTop: $metabox.offset().top - 50
                }, 500);
            });
        }

        /**
         * Load available lessons
         */
        loadAvailableLessons() {
            // Get lessons from the current course or all lessons
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wp_ajax_posts_filter_function', // Use WordPress built-in
                    post_type: 'mpcs-lesson',
                    posts_per_page: -1
                },
                success: (response) => {
                    // For now, get all lessons
                    this.loadAllLessons();
                },
                error: () => {
                    // Fallback to loading all lessons
                    this.loadAllLessons();
                }
            });
        }

        /**
         * Load all lessons (fallback)
         */
        loadAllLessons() {
            // Get lessons using REST API
            $.get('/wp-json/wp/v2/mpcs-lesson?per_page=100', (lessons) => {
                const $select = $('#mpcc-lesson-select');
                $select.empty().append('<option value="">Select a lesson...</option>');
                
                lessons.forEach((lesson) => {
                    $select.append(`<option value="${lesson.id}">${lesson.title.rendered}</option>`);
                });

                // If we detected a lesson ID, select it
                if (this.currentLessonId) {
                    $select.val(this.currentLessonId);
                }
            }).fail(() => {
                console.error('MPCC Quiz AI: Failed to load lessons');
            });
        }

        /**
         * Detect lesson ID from various sources
         */
        detectLessonId() {
            // Check URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const lessonId = urlParams.get('lesson_id') || urlParams.get('lesson');
            
            if (lessonId) {
                this.currentLessonId = parseInt(lessonId);
                console.log('MPCC Quiz AI: Detected lesson ID from URL:', this.currentLessonId);
                return;
            }

            // Check for lesson meta field
            const $lessonField = $('input[name="_mpcs_lesson_id"], select[name="_mpcs_lesson_id"]');
            if ($lessonField.length && $lessonField.val()) {
                this.currentLessonId = parseInt($lessonField.val());
                console.log('MPCC Quiz AI: Detected lesson ID from meta field:', this.currentLessonId);
            }
        }

        /**
         * Bind metabox events
         */
        bindMetaboxEvents() {
            // Generate button click
            $('#mpcc-generate-quiz-btn').on('click', (e) => {
                e.preventDefault();
                this.generateQuiz();
            });

            // Enter key on question count
            $('#mpcc-question-count').on('keypress', (e) => {
                if (e.which === 13) {
                    e.preventDefault();
                    this.generateQuiz();
                }
            });
        }

        /**
         * Generate quiz questions
         */
        generateQuiz() {
            if (this.isGenerating) {
                return;
            }

            // Get selected lesson
            const lessonId = $('#mpcc-lesson-select').val();
            if (!lessonId) {
                this.showNotice('Please select a lesson first', 'warning');
                return;
            }

            // Get question count
            const questionCount = parseInt($('#mpcc-question-count').val()) || 10;

            this.isGenerating = true;
            const $button = $('#mpcc-generate-quiz-btn');
            const originalHtml = $button.html();

            // Show loading state
            $button.prop('disabled', true)
                   .html('<span class="spinner is-active" style="float: none; margin: 0;"></span> Generating...');

            // Make AJAX request
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mpcc_generate_quiz',
                    lesson_id: parseInt(lessonId),
                    nonce: mpcc_ajax.nonce,
                    options: {
                        num_questions: questionCount
                    }
                },
                success: (response) => {
                    if (response.success && response.data.questions) {
                        this.displayQuestions(response.data.questions);
                        this.showNotice('Questions generated successfully!', 'success');
                    } else {
                        this.showNotice(response.data?.message || 'Failed to generate questions', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('MPCC Quiz AI: AJAX Error', error);
                    this.showNotice(`Error: ${error}`, 'error');
                },
                complete: () => {
                    this.isGenerating = false;
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        }

        /**
         * Display generated questions
         */
        displayQuestions(questions) {
            const $results = $('#mpcc-quiz-results');
            const $container = $('.mpcc-quiz-questions');

            // Clear previous results
            $container.empty();

            // Display each question
            questions.forEach((question, index) => {
                const questionHtml = `
                    <div class="mpcc-card mpcc-mb-md">
                        <div class="mpcc-card__header">
                            <h5 class="mpcc-card__title">Question ${index + 1}</h5>
                        </div>
                        <div class="mpcc-card__body">
                            <p><strong>${question.question || question.text}</strong></p>
                            <ul style="list-style-type: none; padding-left: 0;">
                                ${Object.entries(question.options).map(([key, value]) => `
                                    <li style="padding: 4px 0;">
                                        ${key === question.correct_answer ? 
                                            '<span class="dashicons dashicons-yes" style="color: var(--mpcc-success);"></span>' : 
                                            '<span class="dashicons dashicons-marker" style="color: var(--mpcc-text-muted);"></span>'}
                                        ${key}) ${value}
                                    </li>
                                `).join('')}
                            </ul>
                            ${question.explanation ? `
                                <div class="mpcc-mt-sm" style="padding: 8px; background: var(--mpcc-bg-secondary); border-radius: 4px;">
                                    <em>Explanation: ${question.explanation}</em>
                                </div>
                            ` : ''}
                            <div class="mpcc-mt-sm">
                                <button class="button button-small mpcc-insert-question" data-index="${index}">
                                    <span class="dashicons dashicons-plus-alt2"></span>
                                    Insert into Quiz
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $container.append(questionHtml);
            });

            // Store questions for insertion
            this.generatedQuestions = questions;

            // Show results section
            $results.slideDown();

            // Bind insert buttons
            $('.mpcc-insert-question').on('click', (e) => {
                const index = $(e.currentTarget).data('index');
                this.insertQuestion(this.generatedQuestions[index]);
            });

            // Add insert all button
            const insertAllHtml = `
                <div class="mpcc-mt-lg mpcc-text-center">
                    <button id="mpcc-insert-all-questions" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        Insert All Questions
                    </button>
                </div>
            `;
            $container.append(insertAllHtml);

            $('#mpcc-insert-all-questions').on('click', () => {
                this.insertAllQuestions();
            });
        }

        /**
         * Insert a single question into the editor
         */
        insertQuestion(question) {
            // For now, show instructions
            this.showInsertInstructions(question);
        }

        /**
         * Insert all questions
         */
        insertAllQuestions() {
            this.showInsertInstructions(this.generatedQuestions, true);
        }

        /**
         * Show insert instructions (temporary until block editor integration)
         */
        showInsertInstructions(questions, isMultiple = false) {
            const modalHtml = `
                <div class="mpcc-modal-overlay" id="mpcc-insert-modal">
                    <div class="mpcc-modal">
                        <div class="mpcc-modal__header">
                            <h3>Insert Questions</h3>
                            <button class="mpcc-modal__close" type="button">×</button>
                        </div>
                        <div class="mpcc-modal__body">
                            <p>To add ${isMultiple ? 'these questions' : 'this question'} to your quiz:</p>
                            <ol>
                                <li>Copy the question text below</li>
                                <li>In the quiz editor, add a new Question block</li>
                                <li>Paste the question and configure the options</li>
                            </ol>
                            <div class="mpcc-mt-md" style="max-height: 300px; overflow-y: auto;">
                                ${isMultiple ? 
                                    questions.map((q, i) => this.formatQuestionText(q, i + 1)).join('<hr style="margin: 20px 0;">') :
                                    this.formatQuestionText(questions)
                                }
                            </div>
                        </div>
                        <div class="mpcc-modal__footer">
                            <button class="button button-primary mpcc-modal__close">Close</button>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);

            // Close handlers
            $('#mpcc-insert-modal .mpcc-modal__close, #mpcc-insert-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#mpcc-insert-modal').remove();
                }
            });
        }

        /**
         * Format question text for copying
         */
        formatQuestionText(question, number = null) {
            return `
                <div style="background: #f5f5f5; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px;">
                    ${number ? `<strong>Question ${number}:</strong><br>` : ''}
                    <strong>${question.question || question.text}</strong><br><br>
                    Options:<br>
                    ${Object.entries(question.options).map(([key, value]) => 
                        `${key}) ${value} ${key === question.correct_answer ? '✓' : ''}`
                    ).join('<br>')}
                    ${question.explanation ? `<br><br>Explanation: ${question.explanation}` : ''}
                </div>
            `;
        }

        /**
         * Show notice message
         */
        showNotice(message, type = 'info') {
            // Use WordPress admin notices
            const noticeHtml = `
                <div class="notice notice-${type} is-dismissible mpcc-admin-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;

            const $notice = $(noticeHtml);
            $('.wp-header-end, #wpbody-content > .wrap > h1').first().after($notice);

            // Auto dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);

            // Handle manual dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(() => $notice.remove());
            });
        }
    }

    // Modal styles (injected inline for simplicity)
    const modalStyles = `
        <style>
        .mpcc-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mpcc-modal {
            background: white;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.2);
        }
        
        .mpcc-modal__header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .mpcc-modal__header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .mpcc-modal__close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mpcc-modal__body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        
        .mpcc-modal__footer {
            padding: 15px 20px;
            border-top: 1px solid #e0e0e0;
            text-align: right;
        }
        
        .mpcc-admin-notice {
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        </style>
    `;

    // Initialize
    $(document).ready(() => {
        $('head').append(modalStyles);
        new MPCCQuizAICopilot();
    });

})(jQuery);