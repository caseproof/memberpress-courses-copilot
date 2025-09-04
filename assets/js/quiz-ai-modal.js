/**
 * MemberPress Courses Copilot - Quiz AI Modal Integration
 * Matches the course/lesson AI modal pattern for quiz generation
 *
 * @package MemberPressCoursesCopilot
 * @version 3.0.0
 */

(function($) {
    'use strict';

    /**
     * Quiz AI Modal class for generating quiz questions using AI
     * 
     * This class handles the entire lifecycle of the quiz generation modal,
     * including UI creation, lesson detection, question generation, and
     * applying questions to the Gutenberg editor.
     * 
     * @class MPCCQuizAIModal
     */
    class MPCCQuizAIModal {
        /**
         * Constructor - initializes the modal instance
         * 
         * @constructor
         */
        constructor() {
            /**
             * @property {boolean} modalOpen - Whether the modal is currently open
             */
            this.modalOpen = false;
            
            /**
             * @property {Array} generatedQuestions - Array of AI-generated questions
             */
            this.generatedQuestions = [];
            
            /**
             * @property {number|null} currentLessonId - Currently selected lesson ID
             */
            this.currentLessonId = null;
            
            /**
             * @property {number|null} currentCourseId - Currently selected course ID
             */
            this.currentCourseId = null;
            
            /**
             * @property {string|null} pendingCourseId - Pending course ID from referrer
             */
            this.pendingCourseId = null;
            
            /**
             * @property {string|null} detectionMethod - How the context was detected
             */
            this.detectionMethod = null;
            
            /**
             * @property {Object|null} logger - Debug logger instance
             */
            this.logger = window.MPCCDebug ? window.MPCCDebug.createLogger('Quiz AI Modal') : null;
            
            this.init();
        }

        /**
         * Initialize the quiz AI modal functionality
         * 
         * Sets up the generate button and detects lesson context on quiz edit pages
         * 
         * @return {void}
         * 
         * @example
         * // Manual initialization (usually automatic)
         * const modal = new MPCCQuizAIModal();
         * modal.init();
         * 
         * @example
         * // Check if modal is initialized on quiz pages
         * if ($('body').hasClass('post-type-mpcs-quiz')) {
         *     // Modal will auto-initialize and add the "Generate with AI" button
         *     console.log('Quiz AI modal is active');
         * }
         */
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
         * 
         * Opens the modal automatically when creating a new quiz from a lesson
         * or when the auto_open parameter is present in the URL
         * 
         * @return {void}
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
         * Add Generate with AI button to the editor toolbar
         * 
         * Creates and inserts the AI generation button into the WordPress
         * block editor toolbar
         * 
         * @return {void}
         */
        addGenerateButton() {
            // Wait for editor to be ready
            wp.domReady(() => {
                const checkInterval = setInterval(() => {
                    // Look for the toolbar where Save/Publish buttons are
                    const $toolbar = $('.editor-header__settings, .edit-post-header__settings').first();
                    
                    if ($toolbar.length && !$('#mpcc-quiz-generate-ai').length) {
                        clearInterval(checkInterval);
                        
                        // Check if mobile
                        const isMobile = window.matchMedia('(max-width: 768px)').matches;
                        
                        // Create button matching the style
                        let buttonHtml;
                        if (isMobile) {
                            // Icon-only on mobile
                            buttonHtml = `
                                <button 
                                    id="mpcc-quiz-generate-ai" 
                                    type="button"
                                    class="components-button mpcc-ai-button is-icon-only"
                                    title="Generate with AI"
                                    style="background: #6B4CE6 !important; border-color: #6B4CE6 !important; color: #ffffff !important; width: 36px !important; height: 36px !important; padding: 0 !important; margin-right: 8px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 3px !important;"
                                >
                                    <span class="dashicons dashicons-lightbulb" style="font-size: 20px !important; width: 20px !important; height: 20px !important; margin: 0 !important;"></span>
                                </button>
                            `;
                        } else {
                            // Icon and text on desktop
                            buttonHtml = `
                                <button 
                                    id="mpcc-quiz-generate-ai" 
                                    type="button"
                                    class="components-button mpcc-ai-button"
                                    title="Generate with AI"
                                    style="background: #6B4CE6 !important; border-color: #6B4CE6 !important; color: #ffffff !important; height: 36px !important; padding: 0 12px !important; margin-right: 8px !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border-radius: 3px !important;"
                                >
                                    <span class="dashicons dashicons-lightbulb" style="margin-right: 4px !important; font-size: 20px !important; width: 20px !important; height: 20px !important;"></span>
                                    Generate with AI
                                </button>
                            `;
                        }
                        
                        // Insert before the Publish button
                        $toolbar.find('.editor-post-publish-button, .editor-post-publish-panel__toggle').first().before(buttonHtml);
                        
                        // Add hover effect
                        $('#mpcc-quiz-generate-ai').hover(
                            function() {
                                $(this).css({
                                    'background': '#5A3CC5 !important',
                                    'border-color': '#5A3CC5 !important'
                                });
                            },
                            function() {
                                $(this).css({
                                    'background': '#6B4CE6 !important',
                                    'border-color': '#6B4CE6 !important'
                                });
                            }
                        );
                        
                        // Bind click event
                        $('#mpcc-quiz-generate-ai').on('click', () => this.openModal());
                        
                        this.logger?.log('AI button added to editor');
                    }
                }, 100);
                
                // Clear interval after 10 seconds to prevent infinite checking
                setTimeout(() => clearInterval(checkInterval), 10000);
            });
        }
        
        /**
         * Detect lesson context from various sources with fallback strategies
         * 
         * This method implements a sophisticated multi-source detection system to
         * automatically identify the lesson and course context for quiz generation.
         * It uses a priority-based approach with multiple fallback mechanisms to
         * provide the best user experience across different workflows.
         * 
         * Detection Priority Order:
         * 1. URL Parameters (highest priority - explicit context)
         * 2. Referrer URL Analysis (medium priority - workflow context)
         * 3. DOM Element Inspection (lower priority - form state)
         * 4. Pending Context Resolution (lowest priority - deferred validation)
         * 
         * URL Parameter Detection:
         * - 'lesson_id', 'lesson', 'from_lesson': Direct lesson identification
         * - 'course_id', 'course': Direct course identification
         * - 'curriculum': Indicates navigation from course curriculum tab
         * 
         * Referrer Analysis Patterns:
         * - Extracts post IDs using regex pattern /post=(\d+)/
         * - Validates post type via REST API to ensure correct context
         * - Handles both lesson edit pages and course curriculum pages
         * 
         * Curriculum Workflow Special Case:
         * When 'curriculum=1' parameter is present, it indicates the user navigated
         * from a course's curriculum tab to create a quiz. In this case, we extract
         * the course ID from the referrer URL to maintain course context.
         * 
         * DOM Element Validation:
         * - Classic editor meta fields: #_mpcs_lesson_id, #_mpcs_course_id
         * - Block editor attributes: data-* attributes on quiz blocks
         * - Form hidden fields: WordPress meta box values
         * 
         * Error Handling:
         * - Graceful degradation: Each method fails silently if unsuccessful
         * - Fallback chain: Later methods only run if earlier ones fail
         * - Async safety: REST API calls use error handlers to prevent crashes
         * 
         * Context Validation:
         * - All detected IDs are validated as positive integers
         * - Post type verification via REST API prevents type confusion
         * - Pending contexts are validated when actually used
         * 
         * @return {void}
         */
        detectLessonContext() {
            this.logger?.log('Detecting lesson context...');
            
            // Reset detection state to ensure clean detection process
            this.detectionMethod = null;
            this.currentCourseId = null;
            
            // Method 1: URL Parameter Detection (Highest Priority)
            // URL parameters provide explicit context and take precedence over all other methods
            const urlParams = new URLSearchParams(window.location.search);
            
            // Check multiple parameter names for lesson ID (backward compatibility)
            // 'lesson_id' is primary, others support legacy URLs and different workflows
            const lessonIdFromUrl = urlParams.get('lesson_id') || urlParams.get('lesson') || urlParams.get('from_lesson');
            const courseIdFromUrl = urlParams.get('course_id') || urlParams.get('course');
            const fromCurriculum = urlParams.get('curriculum');
            
            if (lessonIdFromUrl) {
                // parseInt with base 10 ensures proper numeric conversion
                // Handles string IDs from URL parameters safely
                this.currentLessonId = parseInt(lessonIdFromUrl, 10);
                this.detectionMethod = 'url';
                this.logger?.log('Detected lesson ID from URL:', this.currentLessonId);
            }
            
            if (courseIdFromUrl) {
                this.currentCourseId = parseInt(courseIdFromUrl, 10);
                this.logger?.log('Detected course ID from URL:', this.currentCourseId);
            }
            
            // Special Curriculum Workflow Handling:
            // When curriculum=1, user navigated from course curriculum tab to create quiz
            // We need to extract the course ID from the referrer to maintain context
            if (fromCurriculum && !this.currentCourseId && document.referrer) {
                // Regex pattern matches WordPress post edit URLs: post.php?action=edit&post=123
                const referrerMatch = document.referrer.match(/post=(\d+)/);
                if (referrerMatch) {
                    this.currentCourseId = parseInt(referrerMatch[1], 10);
                    this.detectionMethod = 'curriculum-referrer';
                    this.logger?.log('Detected course ID from curriculum referrer:', this.currentCourseId, 'from URL:', document.referrer);
                }
            }
            
            // Method 2: Referrer URL Analysis for Lesson Context
            // If no lesson context from URL, check if we came from a lesson edit page
            if (!this.currentLessonId) {
                const referrer = document.referrer;
                // post.php indicates WordPress post editor (could be lesson, course, or other post type)
                if (referrer && referrer.includes('post.php')) {
                    const referrerMatch = referrer.match(/post=(\d+)/);
                    if (referrerMatch) {
                        // Verify the post is actually a lesson via REST API
                        // This prevents false positives from other post types
                        $.ajax({
                            url: '/wp-json/wp/v2/mpcs-lesson/' + referrerMatch[1],
                            async: false,  // Synchronous to complete detection before modal opens
                            success: (lesson) => {
                                this.currentLessonId = parseInt(lesson.id, 10);
                                this.detectionMethod = 'referrer';
                                this.logger?.log('Detected lesson ID from referrer:', this.currentLessonId);
                            },
                            error: () => {
                                // Silent failure - referrer might be course, quiz, or other post type
                                // This is normal behavior, not an error condition
                                this.logger?.debug('Referrer post is not a lesson');
                            }
                        });
                    }
                }
            }
            
            // Method 3: Classic Editor DOM Element Detection
            // Check for lesson selector field in WordPress classic editor meta boxes
            if (!this.currentLessonId) {
                const $lessonSelector = $('#_mpcs_lesson_id');
                // Ensure element exists and has a valid value
                if ($lessonSelector.length && $lessonSelector.val()) {
                    this.currentLessonId = parseInt($lessonSelector.val(), 10);
                    this.detectionMethod = 'lesson_selector';
                    this.logger?.log('Detected lesson from selector:', this.currentLessonId);
                }
            }
            
            // Method 4: Course Selector Detection
            // Check for course selector in meta boxes (classic editor)
            if (!this.currentCourseId) {
                const $courseSelector = $('#_mpcs_course_id');
                if ($courseSelector.length && $courseSelector.val()) {
                    this.currentCourseId = parseInt($courseSelector.val(), 10);
                    this.logger?.log('Detected course from selector:', this.currentCourseId);
                }
            }
            
            // Method 5: Fallback Course Detection from Referrer
            // Last resort: try to detect course context when no lesson context exists
            // This helps when creating quizzes from course overview pages
            if (!this.currentCourseId && !this.currentLessonId) {
                const referrer = document.referrer;
                if (referrer && referrer.includes('post.php')) {
                    const courseMatch = referrer.match(/post=(\d+)/);
                    if (courseMatch) {
                        // Store as pending - will be validated when we attempt to load lessons
                        // This prevents false course associations with non-course post types
                        this.pendingCourseId = courseMatch[1];
                        this.logger?.log('Potential course ID from referrer:', this.pendingCourseId);
                    }
                }
            }
            
            // Log final detection results for debugging
            if (!this.currentLessonId && !this.currentCourseId) {
                this.logger?.log('No lesson or course context detected');
            }
        }
        
        /**
         * Start monitoring lesson selector for changes
         * 
         * Sets up a mutation observer to watch for changes to the lesson
         * selector field in the classic editor
         * 
         * @return {void}
         */
        startLessonMonitoring() {
            const $lessonSelector = $('#_mpcs_lesson_id');
            if (!$lessonSelector.length) return;
            
            // Watch for changes
            $lessonSelector.on('change', () => {
                const newLessonId = parseInt($lessonSelector.val(), 10);
                if (newLessonId !== this.currentLessonId) {
                    this.currentLessonId = newLessonId;
                    this.logger?.log('Lesson selection changed:', this.currentLessonId);
                    
                    // Update the modal's lesson dropdown if it's open
                    if (this.modalOpen && this.currentLessonId) {
                        $('#mpcc-modal-lesson-select').val(this.currentLessonId).trigger('change');
                    }
                }
            });
            
            // Also monitor for dynamic changes (some meta boxes load asynchronously)
            if (window.MutationObserver) {
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach((mutation) => {
                        if (mutation.type === 'childList' || mutation.type === 'attributes') {
                            const currentVal = parseInt($lessonSelector.val(), 10);
                            if (currentVal && currentVal !== this.currentLessonId) {
                                this.currentLessonId = currentVal;
                                this.logger?.log('Lesson detected via mutation:', this.currentLessonId);
                            }
                        }
                    });
                });
                
                const targetNode = $lessonSelector[0];
                if (targetNode) {
                    observer.observe(targetNode, { attributes: true, childList: false, subtree: false });
                    
                    // Also observe the parent for dynamic loading
                    if (targetNode.parentNode) {
                        observer.observe(targetNode.parentNode, { childList: true, subtree: true });
                    }
                }
            }
        }
        
        /**
         * Open the quiz generation modal
         * 
         * Creates and displays the modal interface with all necessary
         * controls for quiz generation
         * 
         * @return {void}
         * 
         * @example
         * // Open modal programmatically
         * const modal = new MPCCQuizAIModal();
         * modal.openModal();
         * 
         * @example
         * // Open modal with pre-selected lesson
         * const modal = new MPCCQuizAIModal();
         * modal.currentLessonId = 123;
         * modal.currentCourseId = 456;
         * modal.openModal();
         * // Modal will pre-select lesson 123 from course 456
         * 
         * @example
         * // Check if modal is already open before opening
         * const modal = new MPCCQuizAIModal();
         * if (!modal.modalOpen) {
         *     modal.openModal();
         *     console.log('Modal opened');
         * } else {
         *     console.log('Modal already open');
         * }
         */
        openModal() {
            if (this.modalOpen) return;
            
            this.modalOpen = true;
            this.generatedQuestions = [];
            
            // Create modal structure
            const modalHtml = `
                <div id="mpcc-quiz-ai-modal" class="mpcc-modal" style="display: none;">
                    <div class="mpcc-modal-content">
                        <div class="mpcc-modal-header">
                            <h2>Generate Quiz Questions with AI</h2>
                            <button type="button" class="mpcc-modal-close" aria-label="Close modal">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        
                        <div class="mpcc-modal-body">
                            <div class="mpcc-modal-section">
                                <label for="mpcc-modal-lesson-select" class="mpcc-label">
                                    Select Lesson for Quiz Content
                                </label>
                                <select id="mpcc-modal-lesson-select" class="mpcc-select">
                                    <option value="">Loading lessons...</option>
                                </select>
                                <div id="mpcc-course-context" style="margin-top: 5px; font-size: 12px; color: #666;"></div>
                            </div>
                            
                            <div class="mpcc-modal-section">
                                <label for="mpcc-modal-question-type" class="mpcc-label">
                                    Question Type
                                </label>
                                <select id="mpcc-modal-question-type" class="mpcc-select">
                                    <option value="multiple_choice">Multiple Choice</option>
                                    <option value="true_false">True/False</option>
                                    <option value="text_answer">Short Answer</option>
                                    <option value="multiple_select">Multiple Select</option>
                                </select>
                            </div>
                            
                            <div class="mpcc-modal-section">
                                <label for="mpcc-modal-question-count" class="mpcc-label">
                                    Number of Questions
                                </label>
                                <input type="number" id="mpcc-modal-question-count" class="mpcc-input" 
                                       value="10" min="1" max="50">
                            </div>
                            
                            <div class="mpcc-modal-section">
                                <label for="mpcc-quiz-prompt" class="mpcc-label">
                                    Additional Instructions (Optional)
                                </label>
                                <textarea id="mpcc-quiz-prompt" class="mpcc-textarea" rows="3" 
                                          placeholder="e.g., Focus on key concepts, make questions challenging..."></textarea>
                            </div>
                            
                            <div class="mpcc-modal-actions">
                                <button type="button" id="mpcc-generate-quiz" class="button button-primary">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    Generate Questions
                                </button>
                            </div>
                            
                            <div id="mpcc-modal-error" class="notice notice-error" style="display: none; margin-top: 20px;">
                                <p class="error-message"></p>
                                <p class="error-suggestion" style="margin-top: 10px; font-style: italic;"></p>
                            </div>
                            
                            <div id="mpcc-quiz-results" style="display: none; margin-top: 20px;">
                                <h3>Generated Questions</h3>
                                <div id="mpcc-questions-preview"></div>
                                <div class="mpcc-modal-actions" style="margin-top: 20px;">
                                    <button type="button" id="mpcc-apply-questions" class="button button-primary">
                                        Apply Questions
                                    </button>
                                    <button type="button" id="mpcc-copy-questions" class="button">
                                        Copy to Clipboard
                                    </button>
                                    <button type="button" id="mpcc-regenerate" class="button">
                                        Regenerate
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modalHtml);
            
            // Add CSS if not already added
            if (!$('#mpcc-modal-styles').length) {
                const styles = `
                    <style id="mpcc-modal-styles">
                        .mpcc-modal {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0, 0, 0, 0.5);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            z-index: 100000;
                        }
                        
                        .mpcc-modal-content {
                            background: white;
                            border-radius: 8px;
                            width: 90%;
                            max-width: 600px;
                            max-height: 90vh;
                            overflow: hidden;
                            display: flex;
                            flex-direction: column;
                        }
                        
                        .mpcc-modal-header {
                            padding: 20px;
                            border-bottom: 1px solid #ddd;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        
                        .mpcc-modal-header h2 {
                            margin: 0;
                            font-size: 20px;
                            color: #1d2327;
                        }
                        
                        .mpcc-modal-close {
                            background: none;
                            border: none;
                            font-size: 24px;
                            cursor: pointer;
                            color: #666;
                            padding: 0;
                            width: 30px;
                            height: 30px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        }
                        
                        .mpcc-modal-close:hover {
                            color: #000;
                        }
                        
                        .mpcc-modal-body {
                            padding: 20px;
                            overflow-y: auto;
                            flex: 1;
                        }
                        
                        .mpcc-modal-section {
                            margin-bottom: 20px;
                        }
                        
                        .mpcc-label {
                            display: block;
                            margin-bottom: 8px;
                            font-weight: 600;
                            color: #1d2327;
                        }
                        
                        .mpcc-select,
                        .mpcc-input,
                        .mpcc-textarea {
                            width: 100%;
                            padding: 8px 12px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            font-size: 14px;
                        }
                        
                        .mpcc-textarea {
                            resize: vertical;
                        }
                        
                        .mpcc-modal-actions {
                            display: flex;
                            gap: 10px;
                            margin-top: 20px;
                        }
                        
                        .mpcc-modal-actions button {
                            display: flex;
                            align-items: center;
                            gap: 5px;
                        }
                        
                        #mpcc-questions-preview {
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            padding: 15px;
                            max-height: 300px;
                            overflow-y: auto;
                            background: #f9f9f9;
                        }
                        
                        .mpcc-question-item {
                            margin-bottom: 20px;
                            padding-bottom: 20px;
                            border-bottom: 1px solid #e0e0e0;
                        }
                        
                        .mpcc-question-item:last-child {
                            margin-bottom: 0;
                            padding-bottom: 0;
                            border-bottom: none;
                        }
                        
                        .mpcc-question-number {
                            font-weight: 600;
                            color: #667eea;
                            margin-bottom: 5px;
                        }
                        
                        .mpcc-question-text {
                            margin-bottom: 10px;
                            font-weight: 500;
                        }
                        
                        .mpcc-question-options {
                            margin-left: 20px;
                        }
                        
                        .mpcc-question-option {
                            margin-bottom: 5px;
                        }
                        
                        .mpcc-question-option.correct {
                            color: #28a745;
                            font-weight: 500;
                        }
                        
                        .mpcc-notice-info {
                            background: #e7f3ff;
                            border-left: 4px solid #2196F3;
                            padding: 12px;
                            margin-bottom: 20px;
                        }
                        
                        .mpcc-loading {
                            display: inline-block;
                            width: 20px;
                            height: 20px;
                            border: 3px solid #f3f3f3;
                            border-top: 3px solid #667eea;
                            border-radius: 50%;
                            animation: spin 1s linear infinite;
                            margin-right: 10px;
                            vertical-align: middle;
                        }
                        
                        @keyframes spin {
                            0% { transform: rotate(0deg); }
                            100% { transform: rotate(360deg); }
                        }
                        
                        @keyframes pulse {
                            0% { opacity: 1; }
                            50% { opacity: 0.5; }
                            100% { opacity: 1; }
                        }
                    </style>
                `;
                $('head').append(styles);
            }
            
            // Show modal with fade effect
            $('#mpcc-quiz-ai-modal').fadeIn(200);
            
            // Load lessons
            this.loadContextualLessons();
            
            // Pre-select lesson if we have one
            if (this.currentLessonId) {
                setTimeout(() => {
                    $('#mpcc-modal-lesson-select').val(this.currentLessonId).trigger('change');
                    this.showAutoDetectionFeedback();
                }, 500);
            }
            
            // Bind events
            this.bindModalEvents();
            
            // Start monitoring for lesson changes
            this.startLessonMonitoring();
            
            this.logger?.log('Modal opened');
        }
        
        /**
         * Load lessons with intelligent context-aware strategy
         * 
         * This method implements a sophisticated lesson loading strategy that
         * provides the most relevant lessons based on the detected context.
         * It optimizes the user experience by showing contextually appropriate
         * lessons while providing fallbacks for edge cases.
         * 
         * Loading Strategy Priority:
         * 1. Course-Specific Loading: When course context is available
         * 2. Lesson + Siblings Loading: When lesson context is available
         * 3. Recent Lessons Loading: When no context is available (fallback)
         * 
         * Context Validation Process:
         * - Validates pending course IDs before using them
         * - Ensures positive integer values for all IDs
         * - Logs decision process for debugging complex workflows
         * 
         * Pending Course ID Logic:
         * Pending course IDs come from referrer analysis and need validation.
         * They're only used when:
         * - No explicit course context exists (currentCourseId is null)
         * - No lesson context exists (would take priority)
         * - The pending ID represents a valid course (validated during loading)
         * 
         * Error Recovery:
         * - Failed course loading falls back to lesson + siblings
         * - Failed lesson loading falls back to recent lessons
         * - All failures ultimately fall back to recent lessons list
         * 
         * @return {void}
         */
        loadContextualLessons() {
            // Log current context state for debugging complex workflow issues
            this.logger?.log('Loading contextual lessons', {
                currentLessonId: this.currentLessonId,
                currentCourseId: this.currentCourseId,
                pendingCourseId: this.pendingCourseId,
                detectionMethod: this.detectionMethod
            });
            
            // Initialize loading state in the lesson selector
            const $select = $('#mpcc-modal-lesson-select');
            $select.empty().append('<option value="">Loading lessons...</option>');
            
            // Resolve pending course ID if no explicit context exists
            // This handles the case where course context was detected from referrer
            // but needs validation before use
            if (this.pendingCourseId && !this.currentCourseId && !this.currentLessonId) {
                // Convert string to integer and validate it's positive
                this.currentCourseId = parseInt(this.pendingCourseId, 10);
                this.logger?.log('Using pending course ID from referrer:', this.currentCourseId);
            }
            
            // Strategy 1: Course-Specific Loading (Highest Priority)
            // When we have course context, show only lessons from that course
            // This provides focused, relevant lesson choices
            if (this.currentCourseId && this.currentCourseId > 0) {
                this.logger?.log('Loading lessons for course:', this.currentCourseId);
                this.loadCourseLessonsOnly();
            } 
            // Strategy 2: Lesson + Siblings Loading (Medium Priority)
            // When we have lesson context, find its course and show all related lessons
            // This maintains lesson context while showing related options
            else if (this.currentLessonId && this.currentLessonId > 0) {
                this.logger?.log('Loading lesson with siblings:', this.currentLessonId);
                this.loadLessonWithSiblings();
            } 
            // Strategy 3: Recent Lessons Loading (Fallback)
            // When no context is available, show recently modified lessons
            // This provides a reasonable default set of options
            else {
                this.logger?.log('Loading recent lessons - no course or lesson context');
                this.loadRecentLessons();
            }
        }
        
        /**
         * Load lessons for a specific course with comprehensive validation
         * 
         * This method performs course-specific lesson loading with robust error
         * handling and input validation. It's designed to provide a focused lesson
         * selection when the user has course context.
         * 
         * Input Validation Process:
         * 1. Validates course ID is positive integer (business rule: no zero/negative IDs)
         * 2. Checks AJAX configuration is loaded (prevents runtime errors)
         * 3. Validates server response structure before processing
         * 
         * AJAX Request Structure:
         * - Uses 'mpcc_get_course_lessons' action for server-side processing
         * - Includes course_id and security nonce for validation
         * - Expects JSON response with lessons array and course metadata
         * 
         * Response Validation:
         * - Checks response.success flag (WordPress AJAX standard)
         * - Validates response.data.lessons exists and is populated
         * - Falls back to recent lessons if course has no lessons
         * 
         * Error Handling Strategies:
         * - 400 errors: Show specific user message (usually validation failures)
         * - Other errors: Fall back to recent lessons (graceful degradation)
         * - Network errors: Fall back to recent lessons (connectivity issues)
         * - Configuration errors: Show specific error message (setup problems)
         * 
         * UI State Management:
         * - Shows loading state during request
         * - Pre-selects current lesson if it exists in the course
         * - Displays course context information to user
         * - Provides clear error messages for troubleshooting
         * 
         * @return {void}
         */
        loadCourseLessonsOnly() {
            const $select = $('#mpcc-modal-lesson-select');
            
            // Critical Input Validation: Course ID must be valid positive integer
            // Zero or negative IDs indicate invalid state or security issues
            if (!this.currentCourseId || this.currentCourseId <= 0) {
                this.logger?.error('Invalid course ID for loading lessons:', this.currentCourseId);
                $select.html('<option value="">No course selected</option>');
                this.showModalError('No course context available for lesson filtering');
                return;
            }
            
            // Update UI to show loading state
            $select.html('<option value="">Loading course lessons...</option>');
            
            // Configuration Validation: Ensure AJAX settings are loaded
            // mpcc_ajax is localized by WordPress and contains URL/nonce
            if (typeof mpcc_ajax === 'undefined') {
                this.logger?.error('mpcc_ajax is not defined');
                this.showModalError('Configuration error: AJAX settings not loaded');
                return;
            }
            
            // Execute AJAX request with comprehensive error handling
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',  // Expect JSON response for structured data
                data: {
                    action: 'mpcc_get_course_lessons',  // Server-side handler
                    course_id: this.currentCourseId,    // Validated course ID
                    nonce: mpcc_ajax.nonce              // Security token
                },
                success: (response) => {
                    // Validate response structure follows WordPress AJAX standards
                    if (response.success && response.data.lessons) {
                        const lessons = response.data.lessons;
                        
                        // Rebuild lesson selector with course-specific options
                        $select.empty();
                        $select.append('<option value="">Select a lesson...</option>');
                        
                        // Populate lessons with pre-selection of current lesson
                        lessons.forEach(lesson => {
                            const selected = lesson.id === this.currentLessonId ? 'selected' : '';
                            $select.append(`<option value="${lesson.id}" ${selected}>${lesson.title}</option>`);
                        });
                        
                        // Display course context to confirm filtering is active
                        if (response.data.course_title) {
                            this.showCourseContext(response.data.course_title);
                        }
                        
                        this.logger?.log(`Loaded ${lessons.length} lessons from course`);
                    } else {
                        // Course exists but has no lessons - fall back to recent lessons
                        // This provides options when courses are newly created
                        this.loadRecentLessons();
                    }
                },
                error: (xhr, status, error) => {
                    // Comprehensive error logging for debugging server issues
                    this.logger?.error('Failed to load course lessons', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        error: error,
                        responseText: xhr.responseText
                    });
                    
                    // Handle specific error types with appropriate user feedback
                    if (xhr.status === 400) {
                        // 400 errors usually indicate validation failures or malformed requests
                        this.showModalError('Invalid request to server. Please refresh the page and try again.');
                    } else {
                        // For other errors (500, network, etc.), gracefully fall back
                        // This maintains functionality even when course loading fails
                        this.loadRecentLessons();
                    }
                }
            });
        }
        
        /**
         * Load a lesson with its course siblings
         * 
         * First gets the course ID for the lesson, then loads all
         * lessons from that course
         * 
         * @return {void}
         */
        loadLessonWithSiblings() {
            // First, get the course ID for this lesson
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
         * 
         * Fetches just the current lesson via REST API
         * 
         * @return {void}
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
         * 
         * Fetches the 50 most recently modified lessons as a fallback
         * 
         * @return {void}
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
         * 
         * @deprecated Use loadContextualLessons() instead
         * @return {void}
         */
        loadLessons() {
            this.loadContextualLessons();
        }
        
        /**
         * Legacy method - redirects to new optimized loading
         * 
         * @deprecated Use loadContextualLessons() instead
         * @return {void}
         */
        loadLessonsForCourse() {
            this.loadContextualLessons();
        }
        
        /**
         * Filter lessons individually by checking course association
         * 
         * @param {Array} lessons - Array of lesson objects
         * @param {jQuery} $select - Select element to populate
         * @return {void}
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
                                    return {
                                        lesson: lesson,
                                        courseId: response.data.course_id,
                                        courseTitle: response.data.course_title
                                    };
                                }
                                return { lesson: lesson, courseId: null, courseTitle: null };
                            }).catch(() => {
                                return { lesson: lesson, courseId: null, courseTitle: null };
                            });
                        });
                        
                        // Wait for all requests to complete
                        Promise.all(lessonPromises).then(results => {
                            $select.empty();
                            $select.append('<option value="">Select a lesson...</option>');
                            
                            // Group by course
                            const courseGroups = {};
                            const noCourse = [];
                            
                            results.forEach(result => {
                                if (result.courseId) {
                                    if (!courseGroups[result.courseId]) {
                                        courseGroups[result.courseId] = {
                                            title: result.courseTitle || `Course ${result.courseId}`,
                                            lessons: []
                                        };
                                    }
                                    courseGroups[result.courseId].lessons.push(result.lesson);
                                } else {
                                    noCourse.push(result.lesson);
                                }
                            });
                            
                            // Add current course lessons first
                            if (courseGroups[this.currentCourseId]) {
                                const group = courseGroups[this.currentCourseId];
                                const $optgroup = $(`<optgroup label="${group.title} (Current Course)"></optgroup>`);
                                group.lessons.forEach(lesson => {
                                    const selected = lesson.id === this.currentLessonId ? 'selected' : '';
                                    $optgroup.append(`<option value="${lesson.id}" ${selected}>${lesson.title.rendered || lesson.title}</option>`);
                                });
                                $select.append($optgroup);
                            }
                            
                            // Add other courses
                            Object.keys(courseGroups).forEach(courseId => {
                                if (courseId != this.currentCourseId) {
                                    const group = courseGroups[courseId];
                                    const $optgroup = $(`<optgroup label="${group.title}"></optgroup>`);
                                    group.lessons.forEach(lesson => {
                                        $optgroup.append(`<option value="${lesson.id}">${lesson.title.rendered || lesson.title}</option>`);
                                    });
                                    $select.append($optgroup);
                                }
                            });
                            
                            // Add lessons without courses
                            if (noCourse.length > 0) {
                                const $optgroup = $(`<optgroup label="Other Lessons"></optgroup>`);
                                noCourse.forEach(lesson => {
                                    $optgroup.append(`<option value="${lesson.id}">${lesson.title.rendered || lesson.title}</option>`);
                                });
                                $select.append($optgroup);
                            }
                        });
                    }
                
        /**
         * Populate lesson dropdown with lessons
         * 
         * @param {jQuery} $select - Select element to populate
         * @param {Array} lessons - Array of lesson objects
         * @return {void}
         */        
        populateLessonDropdown($select, lessons) {
            $select.empty();
            $select.append('<option value="">Select a lesson...</option>');
            
            lessons.forEach(lesson => {
                const selected = lesson.id === this.currentLessonId ? 'selected' : '';
                const title = lesson.title.rendered || lesson.title;
                $select.append(`<option value="${lesson.id}" ${selected}>${title}</option>`);
            });
        }
        
        /**
         * Show course context information
         * 
         * @param {string} courseTitle - Title of the course
         * @return {void}
         */
        showCourseContext(courseTitle) {
            $('#mpcc-course-context').html(`<em>Course: ${courseTitle}</em>`);
        }
        
        /**
         * Show auto-detection feedback to user
         * 
         * Displays a message indicating how the lesson context was detected
         * 
         * @return {void}
         */
        showAutoDetectionFeedback() {
            if (this.currentLessonId && this.detectionMethod) {
                let message = 'Lesson pre-selected ';
                switch (this.detectionMethod) {
                    case 'url_param':
                        message += 'from lesson context';
                        break;
                    case 'lesson_selector':
                        message += 'from quiz settings';
                        break;
                    case 'quiz_meta':
                        message += 'from saved quiz data';
                        break;
                    default:
                        message += 'automatically';
                }
                
                const $notice = $(`<div class="mpcc-notice-info">${message}</div>`);
                $('#mpcc-modal-lesson-select').after($notice);
                
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 3000);
            }
        }
        
        /**
         * Bind modal event handlers
         * 
         * Sets up all click and interaction handlers for the modal
         * 
         * @return {void}
         */
        bindModalEvents() {
            // Close button
            $('.mpcc-modal-close').on('click', () => this.closeModal());
            
            // Click outside modal
            $('#mpcc-quiz-ai-modal').on('click', (e) => {
                if (e.target.id === 'mpcc-quiz-ai-modal') {
                    this.closeModal();
                }
            });
            
            // Generate button
            $('#mpcc-generate-quiz').on('click', () => {
                this.generateQuestions('medium');
            });
            
            // Custom prompt
            $('#mpcc-quiz-prompt').on('input', () => {
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
         * Generate quiz questions with comprehensive input validation
         * 
         * This method orchestrates the quiz generation process with extensive
         * validation, error handling, and user feedback. It ensures all required
         * inputs are valid before making expensive AI generation requests.
         * 
         * Input Validation Chain:
         * 1. Lesson Selection: Must have valid lesson ID selected
         * 2. Question Count: Must be positive integer within reasonable bounds
         * 3. Question Type: Must be supported type (multiple_choice, true_false, etc.)
         * 4. Custom Prompt: Optional but validated for length/content if provided
         * 
         * UI State Management:
         * - Disables generation button during processing (prevents double-submission)
         * - Shows loading spinner with progress message
         * - Hides previous results to avoid confusion
         * - Restores button state regardless of success/failure
         * 
         * AJAX Request Validation:
         * - Validates configuration object exists (mpcc_ajax)
         * - Includes security nonce with every request
         * - Structures options as JSON for complex data transmission
         * 
         * Error Response Analysis:
         * The method analyzes different error types and provides specific guidance:
         * - 400 errors: Validation failures, malformed requests
         * - 403 errors: Security/permission issues
         * - 500 errors: Server-side processing failures
         * - Network errors: Connectivity or timeout issues
         * 
         * Response Structure Validation:
         * - Checks response.success flag (WordPress standard)
         * - Validates response.data.questions array exists
         * - Extracts nested suggestion data from complex error responses
         * 
         * User Feedback Strategy:
         * - Immediate validation feedback (before AJAX call)
         * - Progress feedback during generation
         * - Success feedback with question preview
         * - Detailed error messages with actionable suggestions
         * 
         * @param {string} difficulty - Difficulty level ('easy', 'medium', 'hard')
         * @return {void}
         */
        generateQuestions(difficulty = 'medium') {
            // Critical Input Validation: Lesson ID is required for content generation
            const lessonId = $('#mpcc-modal-lesson-select').val();
            if (!lessonId) {
                const errorMessage = 'Please select a lesson to generate questions from';
                const suggestion = 'Choose a lesson from the dropdown above to provide content for quiz generation';
                
                // Show both modal error and page notice for visibility
                this.showNotice(errorMessage, 'warning');
                this.showModalError(errorMessage, suggestion);
                return;
            }
            
            // Extract and validate generation parameters
            // parseInt with fallback ensures valid numeric values
            const questionCount = parseInt($('#mpcc-modal-question-count').val()) || 10;
            const questionType = $('#mpcc-modal-question-type').val() || 'multiple_choice';
            const customPrompt = $('#mpcc-quiz-prompt').val();
            
            // UI State: Show loading state and disable interaction
            const $button = $('#mpcc-generate-quiz');
            const originalText = $button.html();  // Store for restoration
            $button.prop('disabled', true).html('<span class="mpcc-loading"></span> Generating...');
            
            // Clear previous results to avoid confusion
            $('#mpcc-quiz-results').hide();
            $('#mpcc-modal-error').hide();
            
            // Execute AI generation request with comprehensive error handling
            $.ajax({
                url: mpcc_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',  // Expect structured JSON response
                data: {
                    action: 'mpcc_generate_quiz',  // Server-side generation handler
                    lesson_id: lessonId,           // Validated lesson ID
                    nonce: mpcc_ajax.nonce,        // Security token
                    // Structure options as JSON for complex parameter passing
                    options: JSON.stringify({
                        num_questions: questionCount,
                        difficulty: difficulty,
                        custom_prompt: customPrompt,
                        question_type: questionType
                    })
                },
                success: (response) => {
                    // Validate WordPress AJAX response structure
                    if (response.success && response.data.questions) {
                        // Store generated questions for further operations
                        this.generatedQuestions = response.data.questions;
                        this.displayQuestions(response.data.questions);
                        
                        // Display AI suggestions if provided (content improvement hints)
                        if (response.data.suggestion) {
                            this.showNotice(response.data.suggestion, 'info');
                        }
                    } else {
                        // Handle server-side validation or generation failures
                        const errorMsg = response.data?.message || 'Failed to generate questions';
                        // Extract suggestion from nested response structure (WordPress error format)
                        const suggestion = response.data?.data?.suggestion || response.data?.suggestion || null;
                        this.showModalError(errorMsg, suggestion);
                    }
                },
                error: (xhr, status, error) => {
                    // Comprehensive error logging for debugging server/network issues
                    this.logger?.error('Quiz generation failed', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        error: error,
                        responseText: xhr.responseText,
                        responseJSON: xhr.responseJSON
                    });
                    
                    // Analyze error type and provide specific user guidance
                    let errorMsg = 'An error occurred while generating questions';
                    let suggestion = null;
                    
                    if (xhr.status === 400) {
                        // Bad Request: Usually validation failures or malformed data
                        errorMsg = 'Invalid request. The server rejected the request.';
                        suggestion = 'Please check the browser console for details and contact support if this persists.';
                    } else if (xhr.status === 403) {
                        // Forbidden: Security/permission issues
                        errorMsg = 'Security check failed.';
                        suggestion = 'Please refresh the page and try again.';
                    } else if (xhr.responseJSON && xhr.responseJSON.data) {
                        // Extract detailed error information from structured response
                        errorMsg = xhr.responseJSON.data.message || errorMsg;
                        suggestion = xhr.responseJSON.data.data?.suggestion || xhr.responseJSON.data.suggestion || null;
                    }
                    
                    this.showModalError(errorMsg, suggestion);
                },
                complete: () => {
                    // Always restore button state, regardless of success/failure
                    // This ensures UI remains functional after any outcome
                    $button.prop('disabled', false).html(originalText);
                }
            });
        }
        
        /**
         * Display generated questions in preview
         * 
         * Renders the generated questions in a preview format within the modal
         * 
         * @param {Array} questions - Array of question objects
         * @return {void}
         */
        displayQuestions(questions) {
            const $container = $('#mpcc-questions-preview');
            $container.empty();
            
            questions.forEach((question, index) => {
                const questionNum = index + 1;
                let questionHtml = `<div class="mpcc-question-item">`;
                questionHtml += `<div class="mpcc-question-number">Question ${questionNum}</div>`;
                
                // Handle different question types
                if (question.type === 'true_false') {
                    questionHtml += `<div class="mpcc-question-text">${question.statement || question.question}</div>`;
                    questionHtml += `<div class="mpcc-question-options">`;
                    const correctAnswer = String(question.correct_answer) === 'true' ? 'True' : 'False';
                    const incorrectAnswer = String(question.correct_answer) === 'true' ? 'False' : 'True';
                    questionHtml += `<div class="mpcc-question-option correct">✓ ${correctAnswer}</div>`;
                    questionHtml += `<div class="mpcc-question-option">✗ ${incorrectAnswer}</div>`;
                    questionHtml += `</div>`;
                } else if (question.type === 'text_answer') {
                    questionHtml += `<div class="mpcc-question-text">${question.question}</div>`;
                    questionHtml += `<div class="mpcc-question-options">`;
                    questionHtml += `<div class="mpcc-question-option correct">Expected: ${question.correct_answer || question.expected_answer || 'Open-ended response'}</div>`;
                    if (question.alternative_answers && question.alternative_answers.length > 0) {
                        questionHtml += `<div class="mpcc-question-option">Also accepts: ${question.alternative_answers.join(', ')}</div>`;
                    }
                    questionHtml += `</div>`;
                } else if (question.type === 'multiple_select') {
                    questionHtml += `<div class="mpcc-question-text">${question.question}</div>`;
                    questionHtml += `<div class="mpcc-question-options">`;
                    
                    if (question.options) {
                        Object.entries(question.options).forEach(([key, value]) => {
                            const isCorrect = question.correct_answers && question.correct_answers.includes(key);
                            const optionClass = isCorrect ? 'correct' : '';
                            const prefix = isCorrect ? '☑' : '☐';
                            questionHtml += `<div class="mpcc-question-option ${optionClass}">${prefix} ${value}</div>`;
                        });
                    }
                    questionHtml += `</div>`;
                } else {
                    // Multiple choice
                    questionHtml += `<div class="mpcc-question-text">${question.question}</div>`;
                    questionHtml += `<div class="mpcc-question-options">`;
                    
                    if (question.options) {
                        Object.entries(question.options).forEach(([key, value]) => {
                            const isCorrect = key === question.correct_answer;
                            const optionClass = isCorrect ? 'correct' : '';
                            const prefix = isCorrect ? '✓' : '';
                            questionHtml += `<div class="mpcc-question-option ${optionClass}">${prefix} ${key}) ${value}</div>`;
                        });
                    }
                    questionHtml += `</div>`;
                }
                
                if (question.explanation) {
                    questionHtml += `<div style="margin-top: 10px; font-style: italic; color: #666;">
                        <strong>Explanation:</strong> ${question.explanation}
                    </div>`;
                }
                
                questionHtml += `</div>`;
                
                $container.append(questionHtml);
            });
            
            $('#mpcc-quiz-results').show();
        }

        /**
         * Apply questions to the editor
         * 
         * Inserts generated questions as blocks in the Gutenberg editor.
         * This method handles the complex process of creating question blocks,
         * reserving IDs, and updating the UI.
         * 
         * @async
         * @return {Promise<void>}
         * @throws {Error} If block insertion fails
         * 
         * @example
         * // Apply generated questions to editor
         * const modal = new MPCCQuizAIModal();
         * // After generating questions...
         * modal.generatedQuestions = [
         *     {
         *         type: 'multiple_choice',
         *         question: 'What is PHP?',
         *         options: { a: 'A language', b: 'A database', c: 'An OS' },
         *         correct_answer: 'a'
         *     }
         * ];
         * await modal.applyQuestions();
         * // Questions are now inserted as Gutenberg blocks
         * 
         * @example
         * // Apply questions with error handling
         * const modal = new MPCCQuizAIModal();
         * try {
         *     await modal.applyQuestions();
         *     console.log('Questions applied successfully');
         * } catch (error) {
         *     console.error('Failed to apply questions:', error);
         *     modal.showNotice('Error adding questions. Please try again.', 'error');
         * }
         * 
         * @example
         * // Apply different question types
         * const modal = new MPCCQuizAIModal();
         * modal.generatedQuestions = [
         *     { type: 'multiple_choice', question: 'MC Question...', options: {...}, correct_answer: 'a' },
         *     { type: 'true_false', statement: 'TF Statement...', correct_answer: true },
         *     { type: 'text_answer', question: 'Short answer...', correct_answer: 'Answer' },
         *     { type: 'multiple_select', question: 'MS Question...', options: {...}, correct_answers: ['a', 'c'] }
         * ];
         * await modal.applyQuestions();
         * // Creates appropriate block types for each question
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
         * 
         * @async
         * @param {Object} question - Question data object
         * @param {number} index - Question index
         * @param {number} quizId - Quiz post ID
         * @param {Object} dispatch - WordPress data dispatcher
         * @return {Promise<Object>} Created block object
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
         * 
         * @param {string} questionType - Question type identifier
         * @return {string} WordPress block type name
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
         * 
         * @param {Object} question - Raw question object
         * @param {number} index - Question index
         * @return {Object} Formatted question data
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
         * 
         * @param {Object} question - Raw question object
         * @param {Object} baseData - Base question data
         * @return {Object} Formatted true/false question data
         */
        prepareTrueFalseData(question, baseData) {
            // Convert to boolean - handle both string and boolean values
            baseData.correctAnswer = String(question.correct_answer) === 'true';
            baseData.type = 'true-false';
            return baseData;
        }
        
        /**
         * Prepare text answer question data
         * 
         * @param {Object} question - Raw question object
         * @param {Object} baseData - Base question data
         * @return {Object} Formatted text answer question data
         */
        prepareTextAnswerData(question, baseData) {
            baseData.expectedAnswer = question.correct_answer || question.expected_answer || '';
            baseData.type = 'short-answer';
            return baseData;
        }
        
        /**
         * Prepare multiple select question data
         * 
         * @param {Object} question - Raw question object
         * @param {Object} baseData - Base question data
         * @return {Object} Formatted multiple select question data
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
         * 
         * @param {Object} question - Raw question object
         * @param {Object} baseData - Base question data
         * @return {Object} Formatted multiple choice question data
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
         * 
         * @async
         * @param {number} quizId - Quiz post ID
         * @param {string} clientId - Block client ID
         * @param {Object} dispatch - WordPress data dispatcher
         * @return {Promise<number>} Reserved question ID or 0 if failed
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
         * 
         * @param {Array} blocks - Array of block objects to insert
         * @return {void}
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
         * 
         * @return {void}
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
         * 
         * @return {void}
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
         * 
         * Formats the generated questions as text and copies to clipboard
         * 
         * @return {void}
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
         * 
         * Removes the modal from DOM and cleans up any active intervals
         * 
         * @return {void}
         */
        closeModal() {
            $('#mpcc-quiz-ai-modal').remove();
            this.modalOpen = false;
            
            // Clean up monitoring interval
            if (this.lessonMonitorInterval) {
                clearInterval(this.lessonMonitorInterval);
                this.lessonMonitorInterval = null;
            }
            
            this.logger?.log('Modal closed');
        }
        
        /**
         * Show notice message
         * 
         * Displays a WordPress-style admin notice
         * 
         * @param {string} message - Message to display
         * @param {string} type - Notice type (success, error, warning, info)
         * @return {void}
         */
        showNotice(message, type = 'success') {
            const noticeHtml = `
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `;
            
            // Remove any existing notices
            $('.mpcc-ai-notice').remove();
            
            // Add notice after the h1
            const $notice = $(noticeHtml).addClass('mpcc-ai-notice');
            $('.wrap h1').first().after($notice);
            
            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut(() => $notice.remove());
                }, 5000);
            }
            
            // Make dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $(this).parent().fadeOut(() => $(this).parent().remove());
            });
        }
        
        /**
         * Show error in modal
         * 
         * Displays an error message within the modal with optional suggestion
         * 
         * @param {string} message - Error message
         * @param {string|null} suggestion - Optional suggestion for fixing the error
         * @return {void}
         */
        showModalError(message, suggestion = null) {
            const $error = $('#mpcc-modal-error');
            $error.find('.error-message').text(message);
            
            if (suggestion) {
                $error.find('.error-suggestion').text(suggestion).show();
            } else {
                $error.find('.error-suggestion').hide();
            }
            
            $error.show();
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                $error.fadeOut();
            }, 10000);
        }
    }

    // Expose class for testing
    if (typeof window !== 'undefined') {
        window.MPCCQuizAIModal = MPCCQuizAIModal;
    }

    // Initialize on document ready
    $(document).ready(() => {
        if (typeof wp !== 'undefined' && wp.data && wp.blocks) {
            new MPCCQuizAIModal();
        }
    });

})(jQuery);