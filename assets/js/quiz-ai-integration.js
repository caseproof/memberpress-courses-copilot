/**
 * MemberPress Courses Copilot - Quiz AI Integration
 * Handles AI-powered quiz generation for MemberPress Courses
 *
 * @package MemberPressCoursesCopilot
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Quiz AI Integration class
    class MPCCQuizAI {
        constructor() {
            this.isGenerating = false;
            this.buttonSelector = '#mpcc-generate-quiz';
            this.init();
        }

        init() {
            console.log('MPCC Quiz AI: Initializing...');
            
            // Wait for block editor to be ready
            if (window.wp && window.wp.domReady) {
                wp.domReady(() => {
                    console.log('MPCC Quiz AI: Block editor ready');
                    // Try multiple times to ensure the editor is fully loaded
                    let attempts = 0;
                    const tryAddButton = () => {
                        console.log(`MPCC Quiz AI: Attempt ${attempts + 1} to add button`);
                        if (this.addGenerateButton() || attempts >= 10) {
                            return;
                        }
                        attempts++;
                        setTimeout(tryAddButton, 500);
                    };
                    
                    // Start trying after a short delay
                    setTimeout(tryAddButton, 1000);
                });
            } else {
                console.log('MPCC Quiz AI: wp.domReady not available, using jQuery');
                $(document).ready(() => {
                    setTimeout(() => this.addGenerateButton(), 2000);
                });
            }
        }

        /**
         * Add the AI generate button to quiz editor
         */
        addGenerateButton() {
            console.log('MPCC Quiz AI: addGenerateButton called');
            
            // Check if we're on a quiz edit page
            const postType = document.querySelector('input[name="post_type"]')?.value || 
                            document.querySelector('body').className.match(/post-type-(\S+)/)?.[1];
            
            console.log('MPCC Quiz AI: Post type:', postType);
            
            if (postType !== 'mpcs-quiz') {
                console.log('MPCC Quiz AI: Not on quiz page, exiting');
                return false;
            }

            // Check if button already exists
            if ($(this.buttonSelector).length > 0) {
                console.log('MPCC Quiz AI: Button already exists');
                return true;
            }

            // Try to find the editor header toolbar
            const toolbarSelectors = [
                '.edit-post-header__toolbar',
                '.edit-post-header-toolbar', 
                '.editor-header__toolbar',
                '.edit-post-header__settings'
            ];

            let $toolbar = null;
            for (const selector of toolbarSelectors) {
                const $el = $(selector);
                if ($el.length > 0) {
                    $toolbar = $el;
                    break;
                }
            }

            // If no toolbar found, create a custom location
            if (!$toolbar || $toolbar.length === 0) {
                // Try to add after the title
                const titleWrapper = document.querySelector('.editor-post-title__block');
                if (titleWrapper) {
                    const customToolbar = $('<div class="mpcc-quiz-toolbar"></div>');
                    $(titleWrapper).after(customToolbar);
                    $toolbar = customToolbar;
                } else {
                    console.warn('MPCC Quiz AI: Could not find suitable location for Generate button');
                    return false;
                }
            }

            // Create button HTML
            const buttonHtml = `
                <div class="mpcc-quiz-ai-wrapper">
                    <button id="mpcc-generate-quiz" class="components-button is-secondary mpcc-ai-button" type="button">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <span class="button-text">${mpcc_ajax.strings.generate_button}</span>
                    </button>
                </div>
            `;

            // Add button to toolbar
            $toolbar.append(buttonHtml);
            this.bindEvents();
            return true;
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            $(document).off('click', this.buttonSelector).on('click', this.buttonSelector, (e) => {
                e.preventDefault();
                this.generateQuiz();
            });
        }

        /**
         * Generate quiz from lesson content
         */
        generateQuiz() {
            if (this.isGenerating) {
                return;
            }

            this.isGenerating = true;
            this.showGeneratingState();

            // Get lesson ID - try multiple methods
            const lessonId = this.getLessonId();
            
            if (!lessonId) {
                this.showError(mpcc_ajax.strings.error + ': Could not determine lesson ID');
                this.hideGeneratingState();
                this.isGenerating = false;
                return;
            }

            // Make AJAX request
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mpcc_generate_quiz',
                    lesson_id: lessonId,
                    nonce: mpcc_ajax.nonce,
                    options: {
                        num_questions: 10
                    }
                },
                success: (response) => {
                    if (response.success && response.data.questions) {
                        this.addQuestionsToEditor(response.data.questions);
                        this.showSuccess('Quiz questions generated successfully!');
                    } else {
                        this.showError(response.data?.message || mpcc_ajax.strings.error);
                    }
                },
                error: (xhr, status, error) => {
                    this.showError(`${mpcc_ajax.strings.error}: ${error}`);
                },
                complete: () => {
                    this.hideGeneratingState();
                    this.isGenerating = false;
                }
            });
        }

        /**
         * Get the lesson ID from various possible sources
         */
        getLessonId() {
            // Try to get from the quiz's parent lesson
            // Method 1: Check URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const lessonParam = urlParams.get('lesson_id') || urlParams.get('lesson');
            if (lessonParam) {
                return parseInt(lessonParam);
            }

            // Method 2: Check for lesson selector in the quiz settings
            const $lessonSelect = $('select[name="lesson_id"], #lesson_id, .lesson-selector');
            if ($lessonSelect.length > 0) {
                const selectedLesson = $lessonSelect.val();
                if (selectedLesson) {
                    return parseInt(selectedLesson);
                }
            }

            // Method 3: Check post meta or custom fields
            const $lessonMeta = $('input[name="_lesson_id"], input[name="mpcs_lesson_id"]');
            if ($lessonMeta.length > 0) {
                const lessonId = $lessonMeta.val();
                if (lessonId) {
                    return parseInt(lessonId);
                }
            }

            // Method 4: If this quiz is being created from a lesson, check referrer
            const referrer = document.referrer;
            if (referrer) {
                const match = referrer.match(/post=(\d+)|p=(\d+)/);
                if (match) {
                    const postId = match[1] || match[2];
                    // We'd need to verify this is actually a lesson
                    return parseInt(postId);
                }
            }

            // If no lesson ID found, we might need to let user select content
            // For now, return null
            return null;
        }

        /**
         * Show generating state
         */
        showGeneratingState() {
            const $button = $(this.buttonSelector);
            $button.prop('disabled', true);
            $button.find('.button-text').text(mpcc_ajax.strings.generating);
            $button.addClass('is-busy');
        }

        /**
         * Hide generating state
         */
        hideGeneratingState() {
            const $button = $(this.buttonSelector);
            $button.prop('disabled', false);
            $button.find('.button-text').text(mpcc_ajax.strings.generate_button);
            $button.removeClass('is-busy');
        }

        /**
         * Add questions to the block editor
         */
        addQuestionsToEditor(questions) {
            // Get the block editor data
            const blocks = wp.data.select('core/block-editor').getBlocks();
            const newBlocks = [];

            // Create question blocks for each generated question
            questions.forEach((question, index) => {
                // Create a multiple choice question block
                const questionBlock = wp.blocks.createBlock('memberpress-courses/quiz-question', {
                    questionType: 'multiple-choice',
                    questionText: question.question,
                    options: question.options,
                    correctAnswer: question.correct_answer,
                    explanation: question.explanation || '',
                    points: 1
                });

                newBlocks.push(questionBlock);
            });

            // Insert the new blocks
            wp.data.dispatch('core/block-editor').insertBlocks(newBlocks);
        }

        /**
         * Show success message
         */
        showSuccess(message) {
            // Use WordPress admin notices if available
            if (wp.data && wp.data.dispatch('core/notices')) {
                wp.data.dispatch('core/notices').createSuccessNotice(message, {
                    type: 'snackbar',
                    isDismissible: true
                });
            } else {
                // Fallback to custom notice
                this.showNotice(message, 'success');
            }
        }

        /**
         * Show error message
         */
        showError(message) {
            // Use WordPress admin notices if available
            if (wp.data && wp.data.dispatch('core/notices')) {
                wp.data.dispatch('core/notices').createErrorNotice(message, {
                    type: 'snackbar',
                    isDismissible: true
                });
            } else {
                // Fallback to custom notice
                this.showNotice(message, 'error');
            }
        }

        /**
         * Show custom notice (fallback)
         */
        showNotice(message, type = 'info') {
            const noticeHtml = `
                <div class="mpcc-notice mpcc-notice-${type}">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;

            const $notice = $(noticeHtml);
            $('.edit-post-layout__metaboxes, .edit-post-layout__content').first().prepend($notice);

            // Auto dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => {
                    $notice.remove();
                });
            }, 5000);

            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(() => {
                    $notice.remove();
                });
            });
        }
    }

    // Initialize when ready
    if (window.wp && window.wp.domReady) {
        new MPCCQuizAI();
    } else {
        // Fallback for older versions
        $(document).ready(() => {
            new MPCCQuizAI();
        });
    }

})(jQuery);