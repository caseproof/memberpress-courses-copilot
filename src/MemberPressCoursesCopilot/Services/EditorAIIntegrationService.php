<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Editor AI Integration Service
 *
 * Unified AI integration for both course and lesson editor pages
 *
 * @package MemberPressCoursesCopilot\Services
 * @since   1.0.0
 */
class EditorAIIntegrationService extends BaseService
{
    /**
     * Supported post types
     */
    private const POST_TYPE_COURSE = 'mpcs-course';
    private const POST_TYPE_LESSON = 'mpcs-lesson';

    /**
     * Post type configurations
     */
    private array $postTypeConfig = [
        self::POST_TYPE_COURSE => [
            'button_text'     => 'Generate',
            'modal_title'     => 'AI Course Assistant',
            'ajax_action'     => 'mpcc_editor_ai_chat',
            'update_action'   => 'mpcc_update_post_content',
            'modal_id'        => 'mpcc-editor-ai-modal-overlay',
            'button_id'       => 'mpcc-editor-ai-button',
            'button_id_block' => 'mpcc-editor-ai-button-block',
            'content_tag'     => 'COURSE_CONTENT',
            'assistant_intro' => "Hi! I'm here to help you improve your course overview and description. I can:
            <br>• <strong>Update your course description</strong> - Just ask me to rewrite or enhance it
            <br>• Provide compelling content that attracts students
            <br>• Suggest improvements to your course overview
            <br>• Help you highlight key benefits and learning outcomes
            <br><br><em>Note: I focus on the main course content. For lessons and curriculum structure,
            use the Curriculum tab above.</em>
            <br><br>Would you like me to enhance your course description?",
            'quick_prompts'   => [
                [
                    'icon'   => 'edit-large',
                    'label'  => 'Course Description',
                    'prompt' => 'Write a compelling course description that highlights '
                              . 'the key benefits and learning outcomes for students.',
                ],
                [
                    'icon'   => 'yes-alt',
                    'label'  => 'Learning Objectives',
                    'prompt' => 'Create clear and specific learning objectives that describe '
                              . 'what students will be able to do after completing this course.',
                ],
                [
                    'icon'   => 'visibility',
                    'label'  => 'Improve Overview',
                    'prompt' => 'Improve the course overview to better communicate '
                              . 'the value proposition and attract potential students.',
                ],
                [
                    'icon'   => 'awards',
                    'label'  => 'Benefits & Outcomes',
                    'prompt' => 'Add specific benefits and outcomes students will gain '
                              . 'from taking this course, focusing on practical results.',
                ],
                [
                    'icon'   => 'list-view',
                    'label'  => 'Prerequisites',
                    'prompt' => 'Write clear course prerequisites that help students '
                              . 'understand if this course is right for their skill level.',
                ],
                [
                    'icon'   => 'megaphone',
                    'label'  => 'Call-to-Action',
                    'prompt' => 'Create a compelling call-to-action that motivates students to enroll in this course.',
                ],
            ],
        ],
        self::POST_TYPE_LESSON => [
            'button_text'     => 'Generate',
            'modal_title'     => 'AI Lesson Assistant',
            'ajax_action'     => 'mpcc_editor_ai_chat',
            'update_action'   => 'mpcc_update_post_content',
            'modal_id'        => 'mpcc-editor-ai-modal-overlay',
            'button_id'       => 'mpcc-editor-ai-button',
            'button_id_block' => 'mpcc-editor-ai-button-block',
            'content_tag'     => 'LESSON_CONTENT',
            'assistant_intro' => "Hi! I'm here to help you create engaging lesson content. I can:
            <br>• <strong>Generate complete lesson content</strong> based on your topic
            <br>• Add interactive elements and examples
            <br>• Create exercises and practice activities
            <br>• Suggest multimedia resources to enhance learning",
            'quick_prompts'   => [
                [
                    'icon'   => 'edit-page',
                    'label'  => 'Write Lesson Content',
                    'prompt' => 'Write comprehensive lesson content for this topic, '
                              . 'including clear explanations, examples, and key takeaways.',
                ],
                [
                    'icon'   => 'yes-alt',
                    'label'  => 'Create Learning Objectives',
                    'prompt' => 'Create 3-5 specific, measurable learning objectives '
                              . 'that students should achieve after completing this lesson.',
                ],
                [
                    'icon'   => 'clipboard',
                    'label'  => 'Add Practice Activities',
                    'prompt' => 'Design engaging practice activities, exercises, '
                              . 'or assignments that reinforce the lesson concepts.',
                ],
                [
                    'icon'   => 'welcome-learn-more',
                    'label'  => 'Write Introduction',
                    'prompt' => 'Write an engaging lesson introduction that hooks students '
                              . 'and clearly explains what they\'ll learn.',
                ],
                [
                    'icon'   => 'portfolio',
                    'label'  => 'Create Summary',
                    'prompt' => 'Create a comprehensive lesson summary that reinforces '
                              . 'key concepts and provides clear next steps.',
                ],
                [
                    'icon'   => 'admin-settings',
                    'label'  => 'Add Interactive Elements',
                    'prompt' => 'Add interactive elements like quizzes, polls, discussions, '
                              . 'or multimedia to enhance student engagement.',
                ],
            ],
        ],
    ];

    /**
     * Initialize the service
     */
    public function init(): void
    {
        // Add button and modal to edit pages
        add_action('edit_form_after_title', [$this, 'addAIButton'], 5); // Classic Editor
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']); // Block Editor
        add_action('admin_footer', [$this, 'addAIModal']);

        // Register AJAX handlers
        add_action('wp_ajax_mpcc_editor_ai_chat', [$this, 'handleAIChat']);
        add_action('wp_ajax_mpcc_update_post_content', [$this, 'handleUpdatePostContent']);
    }

    /**
     * Get configuration for current post type
     */
    private function getPostTypeConfig(\WP_Post $post): ?array
    {
        if (isset($this->postTypeConfig[$post->post_type])) {
            return $this->postTypeConfig[$post->post_type];
        }
        return null;
    }

    /**
     * Add AI button after post title
     */
    public function addAIButton(): void
    {
        global $post;

        if (!$post || !in_array($post->post_type, [self::POST_TYPE_COURSE, self::POST_TYPE_LESSON])) {
            return;
        }

        $config = $this->getPostTypeConfig($post);
        if (!$config) {
            return;
        }

        // Enqueue editor AI button script and styles
        wp_enqueue_script('mpcc-editor-ai-button');
        wp_enqueue_style('mpcc-editor-ai-button');

        // Pass data to JavaScript
        wp_localize_script('mpcc-editor-ai-button', 'mpccEditorAI', [
            'buttonId'   => $config['button_id'],
            'modalId'    => $config['modal_id'],
            'buttonText' => $config['button_text'],
        ]);
    }

    /**
     * Add AI Modal to admin footer
     */
    public function addAIModal(): void
    {
        global $post;

        if (!$post || !in_array($post->post_type, [self::POST_TYPE_COURSE, self::POST_TYPE_LESSON])) {
            return;
        }

        if (get_current_screen()->base !== 'post') {
            return;
        }

        $this->renderAIModal($post);
    }

    /**
     * Enqueue assets for Block Editor
     */
    public function enqueueBlockEditorAssets(): void
    {
        global $post;

        if (!$post || !in_array($post->post_type, [self::POST_TYPE_COURSE, self::POST_TYPE_LESSON])) {
            return;
        }

        $config = $this->getPostTypeConfig($post);
        if (!$config) {
            return;
        }

        // Enqueue required CSS and JS for modal functionality
        wp_enqueue_style('mpcc-ai-copilot');
        wp_enqueue_style('mpcc-editor-ai-button');
        wp_enqueue_script('mpcc-shared-utilities');
        wp_enqueue_style('mpcc-toast');
        wp_enqueue_script('mpcc-toast');

        // Add inline script to create button in Block Editor
        wp_add_inline_script(
            'wp-edit-post',
            "
            wp.domReady(function() {
                console.log('MPCC: Block Editor AI button script loaded for " . esc_js($post->post_type) . "');
                
                // Wait for editor to be ready
                const checkInterval = setInterval(() => {
                    // Look for the toolbar where Save/Publish buttons are
                    const toolbar = document.querySelector('.editor-header__settings') || 
                                   document.querySelector('.editor-document-tools__right') ||
                                   document.querySelector('.edit-post-header__settings');
                    const existingButton = document.getElementById('" . esc_js($config['button_id_block']) . "');
                    
                    if (toolbar && !existingButton) {
                        // Find the Publish button
                        const publishButton = toolbar.querySelector('.editor-post-publish-button, .editor-post-publish-panel__toggle');
                        
                        if (publishButton) {
                            console.log('MPCC: Creating AI button');
                            
                            // Create button
                            const aiButton = document.createElement('button');
                            aiButton.id = '" . esc_js($config['button_id_block']) . "';
                            aiButton.className = 'components-button mpcc-ai-button-block';
                            aiButton.title = 'Generate';
                            
                            // Determine if mobile
                            const isMobile = window.matchMedia('(max-width: 768px)').matches;
                            
                            if (isMobile) {
                                // Icon only on mobile
                                aiButton.innerHTML = '<span class=\"dashicons dashicons-lightbulb\"></span>';
                                aiButton.classList.add('is-icon-only');
                            } else {
                                // Icon and text on desktop
                                aiButton.innerHTML = '<span class=\"dashicons dashicons-lightbulb\" style=\"margin-right: 4px;\"></span>' + '" . esc_js($config['button_text']) . "';
                            }
                            
                            // Apply inline styles
                            aiButton.style.background = '#6B4CE6';
                            aiButton.style.borderColor = '#6B4CE6';
                            aiButton.style.color = '#ffffff';
                            aiButton.style.height = '36px';
                            aiButton.style.marginRight = '8px';
                            aiButton.style.display = 'inline-flex';
                            aiButton.style.alignItems = 'center';
                            aiButton.style.justifyContent = 'center';
                            aiButton.style.padding = isMobile ? '0' : '0 12px';
                            aiButton.style.width = isMobile ? '36px' : 'auto';
                            aiButton.style.minWidth = '36px';
                            aiButton.style.borderRadius = '3px';
                            
                            // Add hover effect
                            aiButton.onmouseover = function() {
                                this.style.background = '#5A3CC5';
                                this.style.borderColor = '#5A3CC5';
                            };
                            aiButton.onmouseout = function() {
                                this.style.background = '#6B4CE6';
                                this.style.borderColor = '#6B4CE6';
                            };
                            
                            // Add click handler
                            aiButton.onclick = function(e) {
                                e.preventDefault();
                                console.log('AI button clicked');
                                
                                // Use modal manager if available, otherwise fallback
                                if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                                    console.log('Using MPCCUtils modal manager');
                                    window.MPCCUtils.modalManager.open('#" . esc_js($config['modal_id']) . "');
                                } else {
                                    console.log('Using fallback modal open');
                                    const modal = document.getElementById('" . esc_js($config['modal_id']) . "');
                                    if (modal) {
                                        modal.style.display = 'block';
                                        document.body.style.overflow = 'hidden';
                                        setTimeout(() => {
                                            const input = document.getElementById('mpcc-editor-ai-input');
                                            if (input) input.focus();
                                        }, 300);
                                    }
                                }
                            };
                            
                            // Insert before publish button
                            publishButton.parentNode.insertBefore(aiButton, publishButton);
                            
                            console.log('MPCC: AI button added before publish button');
                            clearInterval(checkInterval);
                        }
                    }
                }, 100);
                
                // Clear interval after 10 seconds to prevent infinite checking
                setTimeout(() => clearInterval(checkInterval), 10000);
            });
            "
        );
    }

    /**
     * Render AI Modal content
     */
    private function renderAIModal(\WP_Post $post): void
    {
        $config = $this->getPostTypeConfig($post);
        if (!$config) {
            return;
        }

        // Add nonce for security
        NonceConstants::field(NonceConstants::AI_ASSISTANT, 'mpcc_editor_ai_nonce');

        // Get parent course information for lessons
        $parentCourse = null;
        if ($post->post_type === self::POST_TYPE_LESSON) {
            $parentCourse = $this->getParentCourse($post);
        }

        // Enqueue modal styles
        wp_enqueue_style('mpcc-editor-ai-modal');
        ?>
        
        <!-- Using existing modal styles from ai-copilot.css -->
        <div class="mpcc-modal-overlay mpcc-editor-ai-modal" id="<?php echo esc_attr($config['modal_id']); ?>"
             role="dialog" aria-modal="true" aria-labelledby="mpcc-editor-ai-title"
             aria-describedby="mpcc-editor-ai-description" style="display: none;">
            <div class="mpcc-modal" style="max-width: 700px; width: 90%;">
                <div class="mpcc-modal-header">
                    <h3 id="mpcc-editor-ai-title"><?php echo esc_html($config['modal_title']); ?></h3>
                    <button type="button" class="mpcc-modal-close"
                            aria-label="Close <?php echo esc_attr($config['modal_title']); ?> dialog"
                            style="font-size: 0;">
                        <span class="dashicons dashicons-no-alt" style="font-size: 20px;" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="mpcc-modal-body" style="display: flex; flex-direction: column; padding: 0;">
                    <div id="mpcc-editor-ai-messages" role="log" aria-label="AI conversation history"
                         aria-live="polite" style="flex: 1; overflow-y: auto; padding: 20px; background: #f9f9f9;"
                         tabindex="0">
                        <div class="mpcc-ai-message" role="article" aria-label="AI Assistant introduction"
                             style="margin-bottom: 10px; padding: 12px; background: #e7f3ff; border-radius: 4px;">
                            <strong>AI Assistant:</strong>
                            <div class="ai-content" id="mpcc-editor-ai-description">
                                <?php echo wp_kses_post($config['assistant_intro']); ?>
                            <?php if ($parentCourse && $post->post_type === self::POST_TYPE_LESSON) : ?>
                            <br><br>I see this lesson is part of
                            "<strong><?php echo esc_html($parentCourse->post_title); ?></strong>".
                            I'll make sure the content aligns with the course objectives.
                            <?php endif; ?>
                            <br><br>What would you like
                            <?php echo esc_html($post->post_type === self::POST_TYPE_LESSON
                                ? 'this lesson to cover'
                                : 'me to help with'); ?>?</div>
                        </div>
                    </div>
                    
                    <!-- Quick-Start Buttons Section -->
                    <div class="mpcc-quick-start-section" role="region" aria-label="Quick start options">
                        <div style="margin-bottom: 10px;">
                            <span id="mpcc-quick-start-label"
                                  style="font-size: 12px; color: #666; font-weight: 500;
                                         text-transform: uppercase; letter-spacing: 0.5px;">Quick Start</span>
                        </div>
                        <div class="mpcc-quick-start-buttons" role="group" aria-labelledby="mpcc-quick-start-label">
                            <?php foreach ($config['quick_prompts'] as $prompt) : ?>
                            <button type="button" class="mpcc-quick-start-btn button"
                                    data-prompt="<?php echo esc_attr($prompt['prompt']); ?>"
                                    aria-label="Use prompt: <?php echo esc_attr($prompt['label']); ?>">
                                <span class="dashicons dashicons-<?php echo esc_attr($prompt['icon']); ?>"
                                      style="font-size: 16px;" aria-hidden="true"></span>
                                <?php echo esc_html($prompt['label']); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="padding: 20px; background: white; border-top: 1px solid #ddd;">
                        <form id="mpcc-editor-ai-form" style="display: flex; gap: 10px; align-items: flex-end;">
                            <label for="mpcc-editor-ai-input" class="screen-reader-text">
                                Enter your message to AI Assistant
                            </label>
                            <textarea id="mpcc-editor-ai-input" 
                                      aria-label="Type your message to AI Assistant"
                                      aria-describedby="mpcc-editor-ai-help"
                                      placeholder="<?php echo esc_attr($post->post_type === self::POST_TYPE_LESSON
                                          ? 'Describe what you want this lesson to teach...'
                                          : 'Ask me anything about your course...'); ?>"
                                      style="flex: 1; min-height: 80px; border: 1px solid #ddd; border-radius: 3px;
                                             padding: 10px; resize: vertical; font-size: 14px;
                                             font-family: -apple-system, BlinkMacSystemFont,
                                                         'Segoe UI', Roboto, sans-serif;"></textarea>
                            <button type="button" id="mpcc-editor-ai-send" class="button button-primary"
                                    aria-label="Send message to AI Assistant"
                                    style="height: 36px; padding: 0 20px; white-space: nowrap;">
                                Send
                            </button>
                        </form>
                        <span id="mpcc-editor-ai-help" class="screen-reader-text">
                            Press Enter to send message, Shift+Enter for new line
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Enqueue editor modal script
        wp_enqueue_script('mpcc-editor-ai-modal');

        // Pass data to JavaScript
        wp_localize_script('mpcc-editor-ai-modal', 'mpccEditorModal', [
            'postType'       => $post->post_type,
            'postId'         => $post->ID,
            'contentTag'     => $config['content_tag'],
            'modalId'        => $config['modal_id'],
            'ajaxAction'     => $config['ajax_action'],
            'updateAction'   => $config['update_action'],
            'contextData'    => $this->getContextData($post, $parentCourse),
            'lessonPostType' => self::POST_TYPE_LESSON,
        ]);
    }

    /**
     * Get parent course for a lesson
     */
    private function getParentCourse(\WP_Post $lesson): ?\WP_Post
    {
        // Check if lesson has a parent course meta
        $courseId = get_post_meta($lesson->ID, '_mpcs_course_id', true);
        if ($courseId) {
            return get_post($courseId);
        }

        // Alternative: Check if lesson is referenced in any course
        $courses = get_posts([
            'post_type'      => 'mpcs-course',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_mpcs_sections',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        foreach ($courses as $course) {
            $sections = get_post_meta($course->ID, '_mpcs_sections', true);
            if (is_array($sections)) {
                foreach ($sections as $section) {
                    if (isset($section['lessons']) && is_array($section['lessons'])) {
                        foreach ($section['lessons'] as $sectionLesson) {
                            if (isset($sectionLesson['ID']) && $sectionLesson['ID'] == $lesson->ID) {
                                return $course;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get comprehensive context data for AI
     */
    private function getContextData(\WP_Post $post, ?\WP_Post $parentCourse = null): array
    {
        if ($post->post_type === self::POST_TYPE_LESSON) {
            return $this->getLessonContextData($post, $parentCourse);
        } else {
            return $this->getCourseContextData($post);
        }
    }

    /**
     * Get comprehensive lesson context data for AI
     */
    private function getLessonContextData(\WP_Post $post, ?\WP_Post $parentCourse = null): array
    {
        // Basic lesson information
        $lessonData = [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $post->post_content,
            'status'  => $post->post_status,
            'excerpt' => $post->post_excerpt,
        ];

        // Get lesson metadata
        $lessonData['objectives'] = get_post_meta($post->ID, '_mpcs_lesson_objectives', true) ?: [];
        $lessonData['duration']   = get_post_meta($post->ID, '_mpcs_lesson_duration', true) ?: 0;
        $lessonData['video_url']  = get_post_meta($post->ID, '_mpcs_lesson_video_url', true) ?: '';
        $lessonData['downloads']  = get_post_meta($post->ID, '_mpcs_lesson_downloads', true) ?: [];

        // Include parent course information
        if ($parentCourse) {
            $lessonData['course'] = [
                'id'                  => $parentCourse->ID,
                'title'               => $parentCourse->post_title,
                'description'         => substr($parentCourse->post_content, 0, 500),
                'learning_objectives' => get_post_meta(
                    $parentCourse->ID,
                    '_mpcs_course_learning_objectives',
                    true
                ) ?: [],
                'target_audience'     => get_post_meta($parentCourse->ID, '_mpcs_course_target_audience', true) ?: '',
            ];
        }

        return $lessonData;
    }

    /**
     * Get comprehensive course context data for AI
     */
    private function getCourseContextData(\WP_Post $post): array
    {
        // Basic course information
        $courseData = [
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'content' => $post->post_content,
            'status'  => $post->post_status,
            'excerpt' => $post->post_excerpt,
        ];

        // Get course metadata
        $courseData['learning_objectives'] = get_post_meta($post->ID, '_mpcs_course_learning_objectives', true) ?: [];
        $courseData['difficulty_level']    = get_post_meta($post->ID, '_mpcs_course_difficulty_level', true) ?: '';
        $courseData['target_audience']     = get_post_meta($post->ID, '_mpcs_course_target_audience', true) ?: '';
        $courseData['prerequisites']       = get_post_meta($post->ID, '_mpcs_course_prerequisites', true) ?: [];
        $courseData['estimated_duration']  = get_post_meta($post->ID, '_mpcs_course_estimated_duration', true) ?: '';
        $courseData['course_category']     = get_post_meta($post->ID, '_mpcs_course_category', true) ?: '';
        $courseData['template_type']       = get_post_meta($post->ID, '_mpcs_course_template_type', true) ?: '';

        // Get sections data
        $sections                    = get_post_meta($post->ID, '_mpcs_sections', true) ?: [];
        $courseData['sections']      = [];
        $courseData['section_count'] = 0;
        $courseData['lesson_count']  = 0;

        if (is_array($sections)) {
            $courseData['section_count'] = count($sections);

            foreach ($sections as $index => $section) {
                $sectionData = [
                    'title'       => $section['section_title'] ?? 'Untitled Section',
                    'description' => $section['section_description'] ?? '',
                    'order'       => $index,
                    'lessons'     => [],
                ];

                if (isset($section['lessons']) && is_array($section['lessons'])) {
                    $courseData['lesson_count'] += count($section['lessons']);

                    foreach ($section['lessons'] as $lessonIndex => $lesson) {
                        $lessonData               = [
                            'title'      => $lesson['post_title'] ?? 'Untitled Lesson',
                            'content'    => isset($lesson['post_content'])
                                ? substr($lesson['post_content'], 0, 200) . '...'
                                : '',
                            'order'      => $lessonIndex,
                            'objectives' => $lesson['meta_input']['_mpcs_lesson_objectives'] ?? [],
                            'duration'   => $lesson['meta_input']['_mpcs_lesson_duration'] ?? 0,
                        ];
                        $sectionData['lessons'][] = $lessonData;
                    }
                }

                $courseData['sections'][] = $sectionData;
            }
        }

        // Get course tags and categories
        $terms = wp_get_post_terms($post->ID, ['course_category', 'course_tag'], ['fields' => 'names']);
        if (!is_wp_error($terms)) {
            $courseData['tags'] = $terms;
        }

        // Calculate total estimated time
        $totalDuration = 0;
        foreach ($courseData['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                $totalDuration += intval($lesson['duration']);
            }
        }
        $courseData['total_estimated_duration'] = $totalDuration;

        return $courseData;
    }

    /**
     * Handle AI chat AJAX request
     */
    public function handleAIChat(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::AI_ASSISTANT)) {
                throw new \Exception('Security check failed');
            }

            $message  = sanitize_textarea_field($_POST['message'] ?? '');
            $postId   = intval($_POST['post_id'] ?? 0);
            $postType = sanitize_key($_POST['post_type'] ?? '');

            // Handle context_data - it may come as an array or JSON string
            $contextDataRaw = $_POST['context_data'] ?? '{}';
            $contextData    = is_array($contextDataRaw)
                ? $contextDataRaw
                : json_decode(stripslashes($contextDataRaw), true);

            if (empty($message)) {
                throw new \Exception('Message is required');
            }

            if (empty($postId)) {
                throw new \Exception('Post ID is required');
            }

            if (!in_array($postType, [self::POST_TYPE_COURSE, self::POST_TYPE_LESSON])) {
                throw new \Exception('Invalid post type');
            }

            // Get LLM service from container with graceful fallback
            $container  = function_exists('mpcc_container') ? mpcc_container() : null;
            $llmService = $container
                ? $container->get(\MemberPressCoursesCopilot\Services\LLMService::class)
                : new \MemberPressCoursesCopilot\Services\LLMService();

            // Build prompt based on post type
            if ($postType === self::POST_TYPE_LESSON) {
                $prompt = $this->buildLessonPrompt($message, $contextData);
            } else {
                $prompt = $this->buildCoursePrompt($message, $contextData);
            }

            // Generate AI response
            $response  = $llmService->generateContent($prompt);
            $aiContent = $response['content'] ?? 'I apologize, but I encountered an error. Please try again.';

            // Check if the response contains a content update
            $hasContentUpdate = $this->detectContentUpdate($message, $aiContent, $postType);

            wp_send_json_success([
                'message'            => $aiContent,
                'has_content_update' => $hasContentUpdate,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle update post content AJAX request
     */
    public function handleUpdatePostContent(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::AI_ASSISTANT)) {
                throw new \Exception('Security check failed');
            }

            $postId  = intval($_POST['post_id'] ?? 0);
            $content = $_POST['content'] ?? '';
            $convertToBlocks = !empty($_POST['convert_to_blocks']);

            if (empty($postId)) {
                throw new \Exception('Post ID is required');
            }

            // Verify the user can edit this post
            if (!current_user_can('edit_post', $postId)) {
                throw new \Exception('You do not have permission to edit this post');
            }

            // Convert HTML to Gutenberg blocks if requested
            if ($convertToBlocks && !empty($content)) {
                // Get CourseGeneratorService to use its conversion method
                $container = function_exists('mpcc_container') ? mpcc_container() : null;
                if ($container && $container->has(\MemberPressCoursesCopilot\Services\CourseGeneratorService::class)) {
                    $courseGenerator = $container->get(\MemberPressCoursesCopilot\Services\CourseGeneratorService::class);
                    
                    // Use reflection to access private method
                    $reflection = new \ReflectionClass($courseGenerator);
                    $method = $reflection->getMethod('convertToGutenbergBlocks');
                    $method->setAccessible(true);
                    
                    $content = $method->invoke($courseGenerator, $content);
                } else {
                    // Fallback conversion if service not available
                    $content = $this->simpleConvertToGutenbergBlocks($content);
                }
            }

            // Update the post content
            $result = wp_update_post([
                'ID'           => $postId,
                'post_content' => $content,
            ], true);

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            wp_send_json_success(['updated' => true]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Simple HTML to Gutenberg blocks conversion
     * Fallback method when CourseGeneratorService is not available
     */
    private function simpleConvertToGutenbergBlocks(string $content): string
    {
        // If content already has Gutenberg blocks, return as-is
        if (strpos($content, '<!-- wp:') !== false) {
            return $content;
        }

        $blocks = [];
        
        // Parse HTML content
        $dom = new \DOMDocument();
        @$dom->loadHTML('<div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $wrapper = $dom->getElementsByTagName('div')->item(0);
        if (!$wrapper) {
            return $content;
        }

        foreach ($wrapper->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            $tagName = strtolower($node->tagName);
            $nodeHtml = $dom->saveHTML($node);

            switch ($tagName) {
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    $level = intval(substr($tagName, 1));
                    $blocks[] = sprintf(
                        "<!-- wp:heading {\"level\":%d} -->\n%s\n<!-- /wp:heading -->",
                        $level,
                        $nodeHtml
                    );
                    break;

                case 'p':
                    $blocks[] = sprintf(
                        "<!-- wp:paragraph -->\n%s\n<!-- /wp:paragraph -->",
                        $nodeHtml
                    );
                    break;

                case 'ul':
                    $blocks[] = sprintf(
                        "<!-- wp:list -->\n%s\n<!-- /wp:list -->",
                        $nodeHtml
                    );
                    break;

                case 'ol':
                    $blocks[] = sprintf(
                        "<!-- wp:list {\"ordered\":true} -->\n%s\n<!-- /wp:list -->",
                        $nodeHtml
                    );
                    break;

                case 'blockquote':
                    $blocks[] = sprintf(
                        "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">%s</blockquote>\n<!-- /wp:quote -->",
                        trim(str_replace(['<blockquote>', '</blockquote>'], '', $nodeHtml))
                    );
                    break;

                default:
                    if (trim($node->textContent)) {
                        $blocks[] = sprintf(
                            "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->",
                            trim($node->textContent)
                        );
                    }
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * Build prompt for lesson content generation
     */
    private function buildLessonPrompt(string $message, array $lessonData): string
    {
        $prompt = "You are an AI assistant helping to create engaging lesson content for online courses.\n\n";

        if (!empty($lessonData['title'])) {
            $prompt .= "Lesson Title: {$lessonData['title']}\n";
        }

        if (!empty($lessonData['content'])) {
            $prompt .= "Current Content:\n{$lessonData['content']}\n\n";
        }

        if (!empty($lessonData['course']['title'])) {
            $prompt .= "Parent Course: {$lessonData['course']['title']}\n";
        }

        if (!empty($lessonData['course']['description'])) {
            $prompt .= 'Course Context: ' . substr($lessonData['course']['description'], 0, 200) . "...\n";
        }

        if (!empty($lessonData['objectives']) && is_array($lessonData['objectives'])) {
            $prompt .= "Lesson Objectives:\n";
            foreach ($lessonData['objectives'] as $objective) {
                $prompt .= "- {$objective}\n";
            }
        }

        if (!empty($lessonData['course']['target_audience'])) {
            $prompt .= "Target Audience: {$lessonData['course']['target_audience']}\n";
        }

        $prompt .= "\nUser Request: {$message}\n\n";

        // Check if user is asking for new content
        $userWantsContent = preg_match('/\b(write|create|generate|make|build|develop)\b/i', $message);

        if ($userWantsContent) {
            $prompt .= 'INSTRUCTION: Generate lesson content in WordPress Gutenberg block format '
                    . 'wrapped between [LESSON_CONTENT] and [/LESSON_CONTENT] tags. ';
            $prompt .= 'Use Gutenberg block comments with proper HTML inside. ';
            $prompt .= 'Example format:\n'
                    . '<!-- wp:heading -->\n<h2>Introduction</h2>\n<!-- /wp:heading -->\n\n'
                    . '<!-- wp:paragraph -->\n<p>Your paragraph content here.</p>\n<!-- /wp:paragraph -->\n\n'
                    . '<!-- wp:list -->\n<ul>\n<li>First item</li>\n<li>Second item</li>\n</ul>\n<!-- /wp:list -->\n\n'
                    . '<!-- wp:list {"ordered":true} -->\n<ol>\n<li>Step one</li>\n<li>Step two</li>\n</ol>\n<!-- /wp:list -->\n';
            $prompt .= 'Include an engaging introduction, clear explanations with examples, '
                    . 'practice activities, and a summary. ';
            $prompt .= 'Make the content educational, practical, and engaging for online learners. ';
            $prompt .= 'Do not include any text outside the [LESSON_CONTENT] tags.';
        } else {
            $prompt .= 'Provide helpful guidance about creating effective lesson content.';
        }

        return $prompt;
    }

    /**
     * Build prompt for course description enhancement
     */
    private function buildCoursePrompt(string $message, array $courseData): string
    {
        $prompt = "You are an AI assistant helping to improve course descriptions and overviews.\n\n";

        if (!empty($courseData['title'])) {
            $prompt .= "Course Title: {$courseData['title']}\n";
        }

        if (!empty($courseData['content'])) {
            $prompt .= "Current Description:\n{$courseData['content']}\n\n";
        }

        if (!empty($courseData['learning_objectives'])) {
            $prompt .= 'Learning Objectives: ' . implode(', ', $courseData['learning_objectives']) . "\n";
        }

        if (!empty($courseData['target_audience'])) {
            $prompt .= "Target Audience: {$courseData['target_audience']}\n";
        }

        if (!empty($courseData['section_count']) && !empty($courseData['lesson_count'])) {
            $prompt .= "Course Structure: {$courseData['section_count']} sections "
                    . "with {$courseData['lesson_count']} lessons\n";
        }

        $prompt .= "\nUser Request: {$message}\n\n";

        // Check if user is asking for a new description
        $userWantsDescription = preg_match('/\b(write|create|update|improve|enhance|rewrite|new)\b/i', $message);

        if ($userWantsDescription) {
            $prompt .= 'INSTRUCTION: Provide the course description in clean HTML format '
                    . 'wrapped between [COURSE_CONTENT] and [/COURSE_CONTENT] tags. ';
            $prompt .= 'Use standard HTML tags: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>. ';
            $prompt .= 'CRITICAL for lists: Use proper HTML with a SINGLE <ul> or <ol> tag containing <li> items. '
                    . 'NEVER nest multiple <ul> tags. ';
            $prompt .= 'Include 3-5 paragraphs covering the overview, benefits, '
                    . 'learning outcomes, target audience, and call-to-action. ';
            $prompt .= 'Example format:\n'
                    . '<h2>Course Overview</h2>\n'
                    . '<p>Content here...</p>\n'
                    . '<ul>\n<li>First benefit</li>\n<li>Second benefit</li>\n</ul>\n';
            $prompt .= 'Do not include any text outside the [COURSE_CONTENT] tags.';
        } else {
            $prompt .= 'Provide helpful guidance about the course description.';
        }

        return $prompt;
    }

    /**
     * Detect if AI response contains a content update
     */
    private function detectContentUpdate(string $userMessage, string $aiResponse, string $postType): bool
    {
        $contentTag = $this->postTypeConfig[$postType]['content_tag'] ?? '';

        // Check if AI response contains the content tags
        if (
            strpos($aiResponse, "[{$contentTag}]") !== false &&
            strpos($aiResponse, "[/{$contentTag}]") !== false
        ) {
            return true;
        }

        // Fallback: Check if user is requesting content and response seems substantial
        $requestKeywords     = [
            'write',
            'create',
            'generate',
            'make',
            'build',
            'develop',
            'update',
            'rewrite',
            'improve',
            'enhance',
        ];
        $userRequestsContent = false;

        $lowerMessage = strtolower($userMessage);
        foreach ($requestKeywords as $keyword) {
            if (strpos($lowerMessage, $keyword) !== false) {
                $userRequestsContent = true;
                break;
            }
        }

        // If user requested content and response is substantial, consider it an update
        $wordCount = str_word_count($aiResponse);
        if ($userRequestsContent && $wordCount > 100) {
            return true;
        }

        return false;
    }
}