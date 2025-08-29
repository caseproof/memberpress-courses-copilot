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
                this.detectLessonContext();
            });
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
            // Check URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            this.currentLessonId = urlParams.get('lesson_id') || urlParams.get('lesson');
            
            // Check for lesson selector in the quiz
            if (!this.currentLessonId) {
                const $lessonSelect = $('select[name="_mpcs_lesson_id"]');
                if ($lessonSelect.length) {
                    this.currentLessonId = $lessonSelect.val();
                }
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
                                    <li>Create questions with varying difficulty levels</li>
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
        }

        /**
         * Load available lessons
         */
        loadLessons() {
            $.get('/wp-json/wp/v2/mpcs-lesson?per_page=100')
                .done((lessons) => {
                    const $select = $('#mpcc-modal-lesson-select');
                    $select.empty().append('<option value="">Select a lesson...</option>');
                    
                    lessons.forEach((lesson) => {
                        const selected = this.currentLessonId == lesson.id ? 'selected' : '';
                        $select.append(`<option value="${lesson.id}" ${selected}>${lesson.title.rendered}</option>`);
                    });
                })
                .fail(() => {
                    $('#mpcc-modal-lesson-select').html('<option value="">Failed to load lessons</option>');
                });
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
            const customPrompt = $('#mpcc-quiz-prompt').val();
            
            $('#mpcc-quiz-results').hide();
            $('#mpcc-quiz-loading').show();
            
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mpcc_generate_quiz',
                    lesson_id: parseInt(lessonId),
                    nonce: mpcc_ajax.nonce,
                    options: {
                        num_questions: questionCount,
                        difficulty: difficulty,
                        custom_prompt: customPrompt
                    }
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
                const questionHtml = `
                    <div class="mpcc-question-preview">
                        <h4>Question ${index + 1}</h4>
                        <p class="mpcc-question-text">${question.question || question.text}</p>
                        <ul class="mpcc-question-options">
                            ${Object.entries(question.options).map(([key, value]) => `
                                <li class="${key === question.correct_answer ? 'correct' : ''}">
                                    ${key}) ${value}
                                </li>
                            `).join('')}
                        </ul>
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
                    
                    // Generate a unique client ID for this block
                    const clientId = wp.blocks.createBlock('memberpress-courses/multiple-choice-question', {}).clientId;
                    
                    // Prepare question data in the format expected by the quiz plugin
                    const questionData = {
                        question: question.question || question.text,
                        type: 'multiple-choice',
                        number: i + 1,
                        required: true,
                        points: 1,
                        options: Object.entries(question.options).map(([key, value]) => ({
                            value: value,
                            isCorrect: key === question.correct_answer
                        })),
                        feedback: question.explanation || ''
                    };
                    
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
                    const block = wp.blocks.createBlock('memberpress-courses/multiple-choice-question', {
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
                return `Question ${i + 1}: ${q.question || q.text}\n` +
                       Object.entries(q.options).map(([key, value]) => 
                           `${key}) ${value} ${key === q.correct_answer ? '(Correct)' : ''}`
                       ).join('\n') +
                       (q.explanation ? `\nExplanation: ${q.explanation}` : '');
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