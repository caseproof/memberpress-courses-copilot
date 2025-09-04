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
                console.log('MPCC Lesson Quiz Integration: Not on lesson edit page, exiting');
                return;
            }
            
            console.log('MPCC Lesson Quiz Integration: On lesson edit page, setting up button...');
            $(document).ready(() => {
                this.addCreateQuizButton();
            });
        }

        /**
         * Check if we're on a lesson edit page
         * 
         * @return {boolean} True if on lesson edit page
         * 
         * @example
         * // Check page type in your own code
         * const integration = new MPCCLessonQuizIntegration();
         * if (integration.isLessonEditPage()) {
         *     console.log('On lesson edit page - quiz integration will be active');
         *     // Add your lesson-specific functionality here
         * }
         * 
         * @example
         * // Use for conditional loading
         * if (new MPCCLessonQuizIntegration().isLessonEditPage()) {
         *     // Load lesson-specific scripts
         *     loadLessonSpecificFeatures();
         * }
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
         * 
         * @return {void}
         * 
         * @example
         * // Manual button addition (usually automatic)
         * const integration = new MPCCLessonQuizIntegration();
         * integration.addCreateQuizButton();
         * // Adds "Create Quiz" button to lesson editor toolbar
         * 
         * @example
         * // Check if button was added successfully
         * const integration = new MPCCLessonQuizIntegration();
         * integration.addCreateQuizButton();
         * 
         * setTimeout(() => {
         *     if ($('#mpcc-lesson-create-quiz').length) {
         *         console.log('Create Quiz button added successfully');
         *     } else {
         *         console.log('Button not added - may not be on lesson page');
         *     }
         * }, 1000);
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
                        
                        // Determine if mobile
                        const isMobile = window.matchMedia('(max-width: 768px)').matches;
                        
                        // Create button matching the style
                        let buttonHtml;
                        if (isMobile) {
                            // Icon-only on mobile
                            buttonHtml = `
                                <button 
                                    id="mpcc-lesson-create-quiz" 
                                    type="button"
                                    class="components-button mpcc-create-quiz-button is-icon-only"
                                    title="Create Quiz"
                                    style="border: 2px solid #6B4CE6 !important; background: transparent !important; color: #6B4CE6 !important; width: 36px !important; height: 36px !important; padding: 0 !important; margin-right: 8px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 3px !important;"
                                >
                                    <span class="dashicons dashicons-welcome-learn-more" style="font-size: 20px !important; width: 20px !important; height: 20px !important; margin: 0 !important; color: #6B4CE6 !important;"></span>
                                </button>
                            `;
                        } else {
                            // Icon and text on desktop
                            buttonHtml = `
                                <button 
                                    id="mpcc-lesson-create-quiz" 
                                    type="button"
                                    class="components-button mpcc-create-quiz-button"
                                    title="Create Quiz"
                                    style="border: 2px solid #6B4CE6 !important; background: transparent !important; color: #6B4CE6 !important; height: 36px !important; padding: 0 12px !important; margin-right: 8px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 3px !important;"
                                >
                                    <span class="dashicons dashicons-welcome-learn-more" style="margin-right: 4px !important; font-size: 20px !important; width: 20px !important; height: 20px !important; color: #6B4CE6 !important;"></span>
                                    Create Quiz
                                </button>
                            `;
                        }
                        
                        // Insert before the Publish button
                        $toolbar.find('.editor-post-publish-button, .editor-post-publish-panel__toggle').first().before(buttonHtml);
                        
                        // Add hover effect
                        $('#mpcc-lesson-create-quiz').hover(
                            function() {
                                $(this).css({
                                    'background': '#6B4CE6 !important',
                                    'color': '#ffffff !important'
                                });
                                $(this).find('.dashicons').css('color', '#ffffff !important');
                            },
                            function() {
                                $(this).css({
                                    'background': 'transparent !important',
                                    'color': '#6B4CE6 !important'
                                });
                                $(this).find('.dashicons').css('color', '#6B4CE6 !important');
                            }
                        );
                        
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
         * 
         * @param {number} lessonId - The lesson ID to create quiz from
         * @return {void}
         * 
         * @example
         * // Create quiz from specific lesson
         * const integration = new MPCCLessonQuizIntegration();
         * integration.createQuizFromLesson(123);
         * // Creates new quiz associated with lesson 123 and redirects to quiz editor
         * 
         * @example
         * // Create quiz with confirmation
         * const integration = new MPCCLessonQuizIntegration();
         * const lessonId = integration.getCurrentLessonId();
         * 
         * if (confirm('Create a new quiz for this lesson?')) {
         *     integration.createQuizFromLesson(lessonId);
         * }
         * 
         * @example
         * // Handle the full workflow
         * const integration = new MPCCLessonQuizIntegration();
         * const lessonId = integration.getCurrentLessonId();
         * 
         * if (lessonId) {
         *     console.log(`Creating quiz for lesson ${lessonId}`);
         *     integration.createQuizFromLesson(lessonId);
         *     // User will be redirected to quiz editor with AI modal auto-opening
         * } else {
         *     console.error('No lesson ID found');
         * }
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
            
            // First, get the course ID from the lesson
            this.getLessonCourseId(lessonId).then(courseId => {
                // Create quiz with lesson and course context
                $.ajax({
                    url: mpcc_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mpcc_create_quiz_from_lesson',
                        lesson_id: lessonId,
                        course_id: courseId || 0, // Pass course ID if available
                        nonce: mpcc_ajax.nonce
                    },
                    success: (response) => {
                        if (response.success && response.data.quiz_id) {
                            // Redirect to quiz editor with lesson context and auto-open flag
                            let quizUrl = response.data.edit_url || 
                                          `${mpcc_ajax.admin_url}post.php?post=${response.data.quiz_id}&action=edit&lesson_id=${lessonId}`;
                            
                            // Add auto_open parameter to trigger modal
                            if (!quizUrl.includes('auto_open=')) {
                                quizUrl += '&auto_open=true';
                            }
                            
                            this.showNotice('Quiz created! Opening AI generator...', 'success');
                            
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
            }).catch(error => {
                console.error('Failed to get course ID:', error);
                // Continue without course ID - backend will try to get it from lesson meta
                this.createQuizWithoutCourseId(lessonId);
            });
        }

        /**
         * Get course ID from lesson metadata
         * 
         * @param {number} lessonId - The lesson ID to get course for
         * @return {Promise<number>} Promise that resolves to course ID
         * 
         * @example
         * // Get course ID for a lesson
         * const integration = new MPCCLessonQuizIntegration();
         * integration.getLessonCourseId(123).then(courseId => {
         *     console.log(`Lesson 123 belongs to course ${courseId}`);
         * }).catch(error => {
         *     console.error('No course found for lesson:', error);
         * });
         * 
         * @example
         * // Use with async/await
         * const integration = new MPCCLessonQuizIntegration();
         * 
         * async function createQuizWithCourse() {
         *     try {
         *         const courseId = await integration.getLessonCourseId(456);
         *         console.log(`Creating quiz for lesson 456 in course ${courseId}`);
         *         // Proceed with quiz creation knowing the course
         *     } catch (error) {
         *         console.log('Lesson not associated with course, proceeding anyway');
         *     }
         * }
         * 
         * @example
         * // Handle both success and failure cases
         * const integration = new MPCCLessonQuizIntegration();
         * const lessonId = 789;
         * 
         * integration.getLessonCourseId(lessonId)
         *     .then(courseId => {
         *         if (courseId) {
         *             console.log(`Lesson ${lessonId} → Course ${courseId}`);
         *             return { lessonId, courseId };
         *         } else {
         *             console.log(`Lesson ${lessonId} has no course association`);
         *             return { lessonId, courseId: null };
         *         }
         *     })
         *     .then(data => {
         *         // Proceed with quiz creation using data.lessonId and data.courseId
         *     });
         */
        getLessonCourseId(lessonId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: mpcc_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'mpcc_get_lesson_course',
                        lesson_id: lessonId,
                        nonce: mpcc_ajax.nonce
                    },
                    success: (response) => {
                        if (response.success && response.data) {
                            console.log('MPCC Lesson Quiz Integration: Got course ID:', response.data.course_id);
                            resolve(response.data.course_id);
                        } else {
                            reject('No course ID found');
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(error);
                    }
                });
            });
        }

        /**
         * Create quiz without explicit course ID (fallback)
         */
        createQuizWithoutCourseId(lessonId) {
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
                        // Redirect to quiz editor with lesson context and auto-open flag
                        let quizUrl = response.data.edit_url || 
                                      `${mpcc_ajax.admin_url}post.php?post=${response.data.quiz_id}&action=edit&lesson_id=${lessonId}`;
                        
                        // Add auto_open parameter to trigger modal
                        if (!quizUrl.includes('auto_open=')) {
                            quizUrl += '&auto_open=true';
                        }
                        
                        this.showNotice('Quiz created! Opening AI generator...', 'success');
                        
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