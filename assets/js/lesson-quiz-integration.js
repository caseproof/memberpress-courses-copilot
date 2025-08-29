/**
 * MemberPress Courses Copilot - Lesson Quiz Integration
 * Adds "Create Quiz" functionality to lesson pages with auto-context detection
 *
 * @package MemberPressCoursesCopilot
 * @version 3.0.0
 */

(function($) {
    'use strict';

    class MPCCLessonQuizIntegration {
        constructor() {
            this.init();
        }

        init() {
            console.log('MPCC Lesson Quiz Integration: Initializing...');
            
            // Only run on lesson edit pages
            if (!this.isLessonEditPage()) {
                return;
            }
            
            $(document).ready(() => {
                this.addCreateQuizButton();
            });
        }

        /**
         * Check if we're on a lesson edit page
         */
        isLessonEditPage() {
            // Check body class
            if ($('body').hasClass('post-type-mpcs-lesson')) {
                return true;
            }
            
            // Check post type in admin
            const postType = $('#post_type').val();
            return postType === 'mpcs-lesson';
        }

        /**
         * Add Create Quiz button to lesson editor
         */
        addCreateQuizButton() {
            // Wait for editor to be ready
            wp.domReady(() => {
                const checkInterval = setInterval(() => {
                    // Look for the toolbar where Save/Publish buttons are
                    const $toolbar = $('.editor-header__settings, .edit-post-header__settings').first();
                    
                    if ($toolbar.length && !$('#mpcc-lesson-create-quiz').length) {
                        clearInterval(checkInterval);
                        
                        // Get current lesson ID
                        const lessonId = this.getCurrentLessonId();
                        if (!lessonId) {
                            console.warn('MPCC Lesson Quiz Integration: Could not determine lesson ID');
                            return;
                        }
                        
                        // Create button matching the style
                        const buttonHtml = `
                            <button 
                                id="mpcc-lesson-create-quiz" 
                                type="button"
                                class="components-button is-secondary"
                                style="margin-right: 8px;"
                            >
                                <span class="dashicons dashicons-welcome-learn-more" style="margin-right: 4px; font-size: 18px; line-height: 1.2;"></span>
                                Create Quiz
                            </button>
                        `;
                        
                        // Insert before the Publish button
                        $toolbar.find('.editor-post-publish-button, .editor-post-publish-panel__toggle').first().before(buttonHtml);
                        
                        // Bind click event
                        $('#mpcc-lesson-create-quiz').on('click', (e) => {
                            e.preventDefault();
                            this.createQuizFromLesson(lessonId);
                        });
                    }
                }, 500);
                
                // Stop checking after 10 seconds
                setTimeout(() => clearInterval(checkInterval), 10000);
            });
        }

        /**
         * Get current lesson ID
         */
        getCurrentLessonId() {
            // Try multiple methods to get lesson ID
            
            // Method 1: From Gutenberg data
            if (wp && wp.data && wp.data.select('core/editor')) {
                const postId = wp.data.select('core/editor').getCurrentPostId();
                if (postId) return postId;
            }
            
            // Method 2: From hidden input
            const $postId = $('#post_ID');
            if ($postId.length) {
                return $postId.val();
            }
            
            // Method 3: From URL
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('post');
        }

        /**
         * Create quiz from current lesson
         */
        createQuizFromLesson(lessonId) {
            // Show confirmation
            if (!confirm('Create a new quiz for this lesson?')) {
                return;
            }
            
            // Get lesson title for quiz title
            let lessonTitle = '';
            if (wp && wp.data && wp.data.select('core/editor')) {
                lessonTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
            }
            
            // Create quiz with lesson context
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'mpcc_create_quiz_from_lesson',
                    lesson_id: lessonId,
                    nonce: mpcc_ajax.nonce
                },
                success: (response) => {
                    if (response.success && response.data.quiz_id) {
                        // Redirect to quiz editor with lesson context
                        const quizUrl = response.data.edit_url || 
                                      `${mpcc_ajax.admin_url}post.php?post=${response.data.quiz_id}&action=edit&lesson_id=${lessonId}`;
                        
                        this.showNotice('Quiz created! Redirecting to quiz editor...', 'success');
                        
                        setTimeout(() => {
                            window.location.href = quizUrl;
                        }, 1500);
                    } else {
                        this.showNotice(response.data?.message || 'Failed to create quiz', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showNotice(`Error creating quiz: ${error}`, 'error');
                }
            });
        }

        /**
         * Show notice using WordPress admin notices
         */
        showNotice(message, type = 'info') {
            // Use WordPress notices API if available
            if (wp && wp.data && wp.data.dispatch('core/notices')) {
                wp.data.dispatch('core/notices').createNotice(
                    type,
                    message,
                    {
                        type: 'snackbar',
                        isDismissible: true
                    }
                );
            } else {
                // Fallback to alert
                alert(message);
            }
        }
    }

    // Initialize on DOM ready
    $(function() {
        new MPCCLessonQuizIntegration();
    });

})(jQuery);