<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Editor AI Integration Service
 * 
 * Unified AI integration for both course and lesson editor pages
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
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
            'button_text' => 'Create with AI',
            'modal_title' => 'AI Course Assistant',
            'ajax_action' => 'mpcc_editor_ai_chat',
            'update_action' => 'mpcc_update_post_content',
            'modal_id' => 'mpcc-editor-ai-modal-overlay',
            'button_id' => 'mpcc-editor-ai-button',
            'button_id_block' => 'mpcc-editor-ai-button-block',
            'content_tag' => 'COURSE_CONTENT',
            'assistant_intro' => "Hi! I'm here to help you improve your course overview and description. I can:
            <br>• <strong>Update your course description</strong> - Just ask me to rewrite or enhance it
            <br>• Provide compelling content that attracts students
            <br>• Suggest improvements to your course overview
            <br>• Help you highlight key benefits and learning outcomes
            <br><br><em>Note: I focus on the main course content. For lessons and curriculum structure, use the Curriculum tab above.</em>
            <br><br>Would you like me to enhance your course description?",
            'quick_prompts' => [
                [
                    'icon' => 'edit-large',
                    'label' => 'Course Description',
                    'prompt' => 'Write a compelling course description that highlights the key benefits and learning outcomes for students.'
                ],
                [
                    'icon' => 'yes-alt',
                    'label' => 'Learning Objectives',
                    'prompt' => 'Create clear and specific learning objectives that describe what students will be able to do after completing this course.'
                ],
                [
                    'icon' => 'visibility',
                    'label' => 'Improve Overview',
                    'prompt' => 'Improve the course overview to better communicate the value proposition and attract potential students.'
                ],
                [
                    'icon' => 'awards',
                    'label' => 'Benefits & Outcomes',
                    'prompt' => 'Add specific benefits and outcomes students will gain from taking this course, focusing on practical results.'
                ],
                [
                    'icon' => 'list-view',
                    'label' => 'Prerequisites',
                    'prompt' => 'Write clear course prerequisites that help students understand if this course is right for their skill level.'
                ],
                [
                    'icon' => 'megaphone',
                    'label' => 'Call-to-Action',
                    'prompt' => 'Create a compelling call-to-action that motivates students to enroll in this course.'
                ]
            ]
        ],
        self::POST_TYPE_LESSON => [
            'button_text' => 'Generate with AI',
            'modal_title' => 'AI Lesson Assistant',
            'ajax_action' => 'mpcc_editor_ai_chat',
            'update_action' => 'mpcc_update_post_content',
            'modal_id' => 'mpcc-editor-ai-modal-overlay',
            'button_id' => 'mpcc-editor-ai-button',
            'button_id_block' => 'mpcc-editor-ai-button-block',
            'content_tag' => 'LESSON_CONTENT',
            'assistant_intro' => "Hi! I'm here to help you create engaging lesson content. I can:
            <br>• <strong>Generate complete lesson content</strong> based on your topic
            <br>• Add interactive elements and examples
            <br>• Create exercises and practice activities
            <br>• Suggest multimedia resources to enhance learning",
            'quick_prompts' => [
                [
                    'icon' => 'edit-page',
                    'label' => 'Write Lesson Content',
                    'prompt' => 'Write comprehensive lesson content for this topic, including clear explanations, examples, and key takeaways.'
                ],
                [
                    'icon' => 'yes-alt',
                    'label' => 'Create Learning Objectives',
                    'prompt' => 'Create 3-5 specific, measurable learning objectives that students should achieve after completing this lesson.'
                ],
                [
                    'icon' => 'clipboard',
                    'label' => 'Add Practice Activities',
                    'prompt' => 'Design engaging practice activities, exercises, or assignments that reinforce the lesson concepts.'
                ],
                [
                    'icon' => 'welcome-learn-more',
                    'label' => 'Write Introduction',
                    'prompt' => 'Write an engaging lesson introduction that hooks students and clearly explains what they\'ll learn.'
                ],
                [
                    'icon' => 'portfolio',
                    'label' => 'Create Summary',
                    'prompt' => 'Create a comprehensive lesson summary that reinforces key concepts and provides clear next steps.'
                ],
                [
                    'icon' => 'admin-settings',
                    'label' => 'Add Interactive Elements',
                    'prompt' => 'Add interactive elements like quizzes, polls, discussions, or multimedia to enhance student engagement.'
                ]
            ]
        ]
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
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add button after the page title
            function addEditorAIButton() {
                // Check if button already exists
                if ($('#<?php echo esc_attr($config['button_id']); ?>').length > 0) {
                    return;
                }
                
                // Find the page title
                var $pageTitle = $('h1.wp-heading-inline').first();
                if ($pageTitle.length === 0) {
                    return;
                }
                
                // Create the button styled like WordPress "Add New" buttons
                var $aiButton = $('<a href="#" id="<?php echo esc_attr($config['button_id']); ?>" class="page-title-action">' +
                    '<span class="dashicons dashicons-lightbulb" style="margin: 3px 5px 0 -2px; font-size: 16px;"></span>' +
                    '<?php echo esc_html($config['button_text']); ?>' +
                    '</a>');
                
                // Style it with our brand color
                $aiButton.css({
                    'background': '#6B4CE6',
                    'border-color': '#6B4CE6',
                    'color': '#ffffff',
                    'margin-left': '10px'
                });
                
                // Add hover effect
                $aiButton.hover(
                    function() {
                        $(this).css({
                            'background': '#5A3CC5',
                            'border-color': '#5A3CC5',
                            'color': '#ffffff'
                        });
                    },
                    function() {
                        $(this).css({
                            'background': '#6B4CE6',
                            'border-color': '#6B4CE6',
                            'color': '#ffffff'
                        });
                    }
                );
                
                // Insert after title or after existing action buttons
                var $existingActions = $pageTitle.siblings('.page-title-action');
                if ($existingActions.length > 0) {
                    $existingActions.last().after($aiButton);
                } else {
                    $pageTitle.after($aiButton);
                }
                
                // Handle click
                $aiButton.on('click', function(e) {
                    e.preventDefault();
                    
                    // Open modal using existing modal manager
                    if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                        window.MPCCUtils.modalManager.open('#<?php echo esc_attr($config['modal_id']); ?>');
                    } else {
                        $('#<?php echo esc_attr($config['modal_id']); ?>').fadeIn();
                        $('body').css('overflow', 'hidden');
                    }
                    
                    // Focus on input
                    setTimeout(function() {
                        $('#mpcc-editor-ai-input').focus();
                    }, 300);
                });
            }
            
            // Add button on page load
            addEditorAIButton();
            
            // Also add button if page structure changes (for Gutenberg compatibility)
            var observer = new MutationObserver(function(mutations) {
                addEditorAIButton();
            });
            
            // Observe changes to the editor header
            var targetNode = document.querySelector('.edit-post-header, .editor-header, .wrap');
            if (targetNode) {
                observer.observe(targetNode, { childList: true, subtree: true });
            }
        });
        </script>
        <?php
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
                const unsubscribe = wp.data.subscribe(() => {
                    // Try multiple selectors for better compatibility
                    const editorWrapper = document.querySelector('.editor-header__settings') || 
                                         document.querySelector('.editor-document-tools') ||
                                         document.querySelector('.edit-post-header__toolbar');
                    const existingButton = document.getElementById('" . esc_js($config['button_id_block']) . "');
                    
                    if (editorWrapper && !existingButton) {
                        console.log('MPCC: Creating AI button');
                        // Create button container
                        const buttonContainer = document.createElement('div');
                        buttonContainer.style.marginLeft = '10px';
                        buttonContainer.style.display = 'inline-flex';
                        buttonContainer.style.alignItems = 'center';
                        
                        // Create button
                        const aiButton = document.createElement('button');
                        aiButton.id = '" . esc_js($config['button_id_block']) . "';
                        aiButton.className = 'components-button is-primary';
                        aiButton.style.background = '#6B4CE6';
                        aiButton.style.borderColor = '#6B4CE6';
                        aiButton.style.height = '36px';
                        aiButton.style.whiteSpace = 'nowrap';
                        aiButton.innerHTML = '<span class=\"dashicons dashicons-lightbulb\" style=\"margin: 3px 5px 0 0; vertical-align: middle;\"></span>" . esc_js($config['button_text']) . "';
                        
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
                        
                        buttonContainer.appendChild(aiButton);
                        editorWrapper.appendChild(buttonContainer);
                        
                        console.log('MPCC: AI button added to toolbar');
                        
                        // Unsubscribe once button is added
                        unsubscribe();
                    }
                });
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
        $parent_course = null;
        if ($post->post_type === self::POST_TYPE_LESSON) {
            $parent_course = $this->getParentCourse($post);
        }
        
        ?>
        <style>
        /* Override the CSS pseudo-element X */
        #<?php echo esc_attr($config['modal_id']); ?> .mpcc-modal-close::before {
            content: none !important;
        }
        
        /* Quick-start buttons styling - scoped to modal only */
        #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-section {
            background: #f8f9fa !important;
            border-top: 1px solid #e1e1e1 !important;
            border-bottom: 1px solid #e1e1e1 !important;
            padding: 15px 20px !important;
        }
        
        #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-buttons {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
        }
        
        #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-btn {
            display: inline-flex !important;
            align-items: center !important;
            padding: 6px 12px !important;
            font-size: 13px !important;
            line-height: 1.4 !important;
            border-radius: 3px !important;
            border: 1px solid #c3c4c7 !important;
            background: #fff !important;
            color: #2c3338 !important;
            cursor: pointer !important;
            transition: all 0.15s ease-in-out !important;
            white-space: nowrap !important;
            text-decoration: none !important;
            box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04) !important;
        }
        
        #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-btn:hover {
            background: #f6f7f7 !important;
            border-color: #8c8f94 !important;
            color: #1d2327 !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }
        
        #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-btn:active {
            transform: translateY(0) !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
        }
        
        #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-btn .dashicons {
            vertical-align: middle !important;
            margin-right: 4px !important;
            color: #50575e !important;
        }
        
        #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-btn:hover .dashicons {
            color: #135e96 !important;
        }
        
        /* Responsive Design */
        @media (max-width: 782px) {
            #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-buttons {
                flex-direction: column !important;
            }
            
            #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-btn {
                width: 100% !important;
                justify-content: flex-start !important;
                padding: 10px 12px !important;
                font-size: 14px !important;
            }
            
            #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-section {
                padding: 12px 15px !important;
            }
        }
        
        @media (max-width: 480px) {
            #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-btn {
                padding: 8px 10px !important;
                font-size: 12px !important;
            }
            
            #<?php echo esc_attr($config['modal_id']); ?> .mpcc-quick-start-btn .dashicons {
                font-size: 14px !important;
                margin-right: 3px !important;
            }
        }
        </style>
        
        <!-- Using existing modal styles from ai-copilot.css -->
        <div class="mpcc-modal-overlay" id="<?php echo esc_attr($config['modal_id']); ?>" style="display: none;">
            <div class="mpcc-modal" style="max-width: 700px; width: 90%;">
                <div class="mpcc-modal-header">
                    <h3><?php echo esc_html($config['modal_title']); ?></h3>
                    <button type="button" class="mpcc-modal-close" aria-label="Close" style="font-size: 0;">
                        <span class="dashicons dashicons-no-alt" style="font-size: 20px;"></span>
                    </button>
                </div>
                <div class="mpcc-modal-body" style="display: flex; flex-direction: column; height: 500px; padding: 0;">
                    <div id="mpcc-editor-ai-messages" style="flex: 1; overflow-y: auto; padding: 20px; background: #f9f9f9;">
                        <div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 12px; background: #e7f3ff; border-radius: 4px;">
                            <strong>AI Assistant:</strong> <div class="ai-content"><?php echo $config['assistant_intro']; ?>
                            <?php if ($parent_course && $post->post_type === self::POST_TYPE_LESSON): ?>
                            <br><br>I see this lesson is part of "<strong><?php echo esc_html($parent_course->post_title); ?></strong>". I'll make sure the content aligns with the course objectives.
                            <?php endif; ?>
                            <br><br>What would you like <?php echo $post->post_type === self::POST_TYPE_LESSON ? 'this lesson to cover' : 'me to help with'; ?>?</div>
                        </div>
                    </div>
                    
                    <!-- Quick-Start Buttons Section -->
                    <div class="mpcc-quick-start-section">
                        <div style="margin-bottom: 10px;">
                            <span style="font-size: 12px; color: #666; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Quick Start</span>
                        </div>
                        <div class="mpcc-quick-start-buttons">
                            <?php foreach ($config['quick_prompts'] as $prompt): ?>
                            <button type="button" class="mpcc-quick-start-btn button" data-prompt="<?php echo esc_attr($prompt['prompt']); ?>">
                                <span class="dashicons dashicons-<?php echo esc_attr($prompt['icon']); ?>" style="font-size: 16px;"></span>
                                <?php echo esc_html($prompt['label']); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="padding: 20px; background: white; border-top: 1px solid #ddd;">
                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                            <textarea id="mpcc-editor-ai-input" 
                                      placeholder="<?php echo esc_attr($post->post_type === self::POST_TYPE_LESSON ? 'Describe what you want this lesson to teach...' : 'Ask me anything about your course...'); ?>" 
                                      style="flex: 1; min-height: 80px; border: 1px solid #ddd; border-radius: 3px; padding: 10px; resize: vertical; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"></textarea>
                            <button type="button" id="mpcc-editor-ai-send" class="button button-primary" style="height: 36px; padding: 0 20px; white-space: nowrap;">
                                Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('MPCC: Editor AI Modal initialized for <?php echo esc_js($post->post_type); ?>');
            
            var postType = '<?php echo esc_js($post->post_type); ?>';
            var contentTag = '<?php echo esc_js($config['content_tag']); ?>';
            var modalId = '#<?php echo esc_js($config['modal_id']); ?>';
            
            // Simple markdown to HTML converter
            function markdownToHtml(markdown) {
                var html = markdown;
                
                // Headers
                html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
                html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
                html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
                
                // Bold and italic
                html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');
                
                // Bullet lists
                html = html.replace(/^\* (.+)$/gm, '<li>$1</li>');
                html = html.replace(/(<li>.*<\/li>\n?)+/g, function(match) {
                    return '<ul>' + match + '</ul>';
                });
                
                // Numbered lists  
                html = html.replace(/^\d+\. (.+)$/gm, '<li>$1</li>');
                
                // Paragraphs - wrap lines that aren't already wrapped in tags
                var lines = html.split('\n');
                html = lines.map(function(line) {
                    line = line.trim();
                    if (line && !line.match(/^<[^>]+>/)) {
                        return '<p>' + line + '</p>';
                    }
                    return line;
                }).join('\n');
                
                // Clean up extra newlines
                html = html.replace(/\n{2,}/g, '\n\n');
                
                return html;
            }
            
            // Close modal using existing modal manager
            $('.mpcc-modal-close', modalId).on('click', function() {
                if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                    window.MPCCUtils.modalManager.close(modalId);
                } else {
                    $(modalId).fadeOut();
                    $('body').css('overflow', '');
                }
            });
            
            // Close on overlay click
            $(modalId).on('click', function(e) {
                if (e.target === this) {
                    if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                        window.MPCCUtils.modalManager.close(modalId);
                    } else {
                        $(this).fadeOut();
                        $('body').css('overflow', '');
                    }
                }
            });
            
            // Handle quick-start button clicks
            $(modalId + ' .mpcc-quick-start-btn').on('click', function() {
                var prompt = $(this).data('prompt');
                var input = $('#mpcc-editor-ai-input');
                
                // Set the prompt text
                input.val(prompt);
                
                // Focus the input field
                input.focus();
                
                // Optional: Scroll to input area
                input[0].scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'nearest' 
                });
                
                // Auto-resize the textarea if needed
                input.css('height', 'auto');
                input.css('height', input[0].scrollHeight + 'px');
            });
            
            // Handle send message
            $('#mpcc-editor-ai-send').on('click', function() {
                var input = $('#mpcc-editor-ai-input');
                var message = input.val().trim();
                
                if (!message) {
                    alert('Please enter a message');
                    return;
                }
                
                // Add user message to chat
                var userMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #f0f0f0; border-radius: 4px; text-align: right;">' +
                    '<strong>You:</strong> ' + $('<div>').text(message).html() + '</div>';
                $('#mpcc-editor-ai-messages').append(userMsg);
                
                // Clear input
                input.val('');
                
                // Scroll to bottom
                var messages = $('#mpcc-editor-ai-messages');
                messages.scrollTop(messages[0].scrollHeight);
                
                // Show typing indicator
                var typingMsg = '<div id="mpcc-editor-typing" class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                    '<strong>AI Assistant:</strong> <em>Typing...</em></div>';
                $('#mpcc-editor-ai-messages').append(typingMsg);
                messages.scrollTop(messages[0].scrollHeight);
                
                // Prepare context data
                var contextData = <?php echo json_encode($this->getContextData($post, $parent_course)); ?>;
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: '<?php echo esc_js($config['ajax_action']); ?>',
                        nonce: $('#mpcc_editor_ai_nonce').val(),
                        message: message,
                        post_id: <?php echo $post->ID; ?>,
                        post_type: postType,
                        context_data: contextData
                    },
                    success: function(response) {
                        $('#mpcc-editor-typing').remove();
                        
                        if (response.success) {
                            var messageText = response.data.message;
                            var hasContentUpdate = response.data.has_content_update || false;
                            
                            // Debug: Log the raw AI response
                            console.log('MPCC: Raw AI response:', messageText);
                            console.log('MPCC: Has content update:', hasContentUpdate);
                            
                            // Check if the message contains markdown content tags
                            var contentRegex = new RegExp('\\[' + contentTag + '\\]([\\s\\S]*?)\\[\\/' + contentTag + '\\]');
                            var contentMatch = messageText.match(contentRegex);
                            var displayText = messageText;
                            
                            if (contentMatch) {
                                // Format the markdown content for display
                                var markdownContent = contentMatch[1].trim();
                                var htmlContent = markdownToHtml(markdownContent);
                                displayText = htmlContent;
                            } else {
                                // Regular message formatting
                                displayText = messageText.replace(/\n/g, '<br>');
                            }
                            
                            var aiMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                                '<strong>AI Assistant:</strong> <div class="ai-content">' + displayText + '</div></div>';
                            $('#mpcc-editor-ai-messages').append(aiMsg);
                            
                            // If content update is provided, show apply button
                            if (hasContentUpdate) {
                                var applyButtons = '<div class="mpcc-editor-content-update-buttons" style="margin: 10px 0; padding: 10px; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 4px;">' +
                                    '<p style="margin: 0 0 10px 0; font-weight: bold;">Apply this content to your ' + (postType === '<?php echo self::POST_TYPE_LESSON; ?>' ? 'lesson' : 'course') + '?</p>' +
                                    '<button type="button" class="button button-primary mpcc-apply-editor-content" style="margin-right: 5px;">Apply Content</button>' +
                                    '<button type="button" class="button mpcc-copy-editor-content" style="margin-right: 5px;">Copy to Clipboard</button>' +
                                    '<button type="button" class="button mpcc-cancel-editor-update">Cancel</button>' +
                                    '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">This will update your content in the editor.</p>' +
                                '</div>';
                                $('#mpcc-editor-ai-messages').append(applyButtons);
                            }
                        } else {
                            var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                                '<strong>Error:</strong> ' + (response.data || 'Failed to get AI response') + '</div>';
                            $('#mpcc-editor-ai-messages').append(errorMsg);
                        }
                        
                        var messages = $('#mpcc-editor-ai-messages');
                        messages.scrollTop(messages[0].scrollHeight);
                    },
                    error: function() {
                        $('#mpcc-editor-typing').remove();
                        var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                            '<strong>Error:</strong> Network error. Please try again.</div>';
                        $('#mpcc-editor-ai-messages').append(errorMsg);
                        
                        var messages = $('#mpcc-editor-ai-messages');
                        messages.scrollTop(messages[0].scrollHeight);
                    }
                });
            });
            
            // Handle Enter key
            $('#mpcc-editor-ai-input').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    $('#mpcc-editor-ai-send').click();
                }
            });
            
            // Handle apply content button
            $(document).on('click', '.mpcc-apply-editor-content', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Applying...');
                
                // Get the AI-generated content
                var $aiMessage = $(this).closest('.mpcc-editor-content-update-buttons').prev('.mpcc-ai-message').find('.ai-content');
                var fullContent = $aiMessage.text(); // Use .text() to get raw content without HTML
                
                console.log('MPCC: Extracting content from:', fullContent);
                
                var editorContent = '';
                
                // Look for content between content tags
                var contentRegex = new RegExp('\\[' + contentTag + '\\]([\\s\\S]*?)\\[\\/' + contentTag + '\\]');
                var contentMatch = fullContent.match(contentRegex);
                
                if (contentMatch && contentMatch[1]) {
                    // Found markdown content
                    var markdownContent = contentMatch[1].trim();
                    console.log('MPCC: Found markdown content:', markdownContent);
                    
                    // Convert markdown to HTML
                    editorContent = markdownToHtml(markdownContent);
                    console.log('MPCC: Converted to HTML:', editorContent);
                } else {
                    // Fallback: use the full content if no tags found
                    console.log('MPCC: No content tags found, using full content');
                    editorContent = $aiMessage.html()
                        .replace(/<br\s*\/?>/gi, '\n')
                        .replace(/\n{3,}/g, '\n\n')
                        .trim();
                }
                    
                console.log('MPCC: Final content to apply (length: ' + editorContent.length + '):', editorContent);
                
                // Update the post content via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: '<?php echo esc_js($config['update_action']); ?>',
                        nonce: $('#mpcc_editor_ai_nonce').val(),
                        post_id: <?php echo $post->ID; ?>,
                        content: editorContent
                    },
                    success: function(response) {
                        console.log('MPCC: AJAX response:', response);
                        
                        if (response.success) {
                            // For Block Editor - we need to reload the post data
                            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                                console.log('MPCC: Updating Block Editor');
                                // Force refresh the post content
                                wp.data.dispatch('core').receiveEntityRecords('postType', postType, [
                                    {
                                        id: <?php echo $post->ID; ?>,
                                        content: { raw: editorContent, rendered: editorContent }
                                    }
                                ]);
                                // Also update via editPost
                                wp.data.dispatch('core/editor').editPost({content: editorContent});
                            } else if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                                // For classic editor
                                console.log('MPCC: Updating Classic Editor (TinyMCE)');
                                tinyMCE.get('content').setContent(editorContent);
                            } else if ($('#content').length) {
                                // For text editor
                                console.log('MPCC: Updating Text Editor');
                                $('#content').val(editorContent);
                            } else {
                                console.log('MPCC: No editor found to update');
                            }
                            
                            $button.text('Applied!');
                            setTimeout(function() {
                                $('.mpcc-editor-content-update-buttons').fadeOut();
                            }, 2000);
                        } else {
                            $button.text('Failed').addClass('button-disabled');
                            alert('Error: ' + (response.data || 'Failed to update content'));
                        }
                    },
                    error: function() {
                        $button.text('Failed').addClass('button-disabled');
                        alert('Network error. Please try again.');
                    }
                });
            });
            
            // Handle copy content button
            $(document).on('click', '.mpcc-copy-editor-content', function() {
                // Get the AI-generated content
                var $aiMessage = $(this).closest('.mpcc-editor-content-update-buttons').prev('.mpcc-ai-message').find('.ai-content');
                var fullContent = $aiMessage.text();
                var contentToCopy = '';
                
                // Look for markdown content
                var contentRegex = new RegExp('\\[' + contentTag + '\\]([\\s\\S]*?)\\[\\/' + contentTag + '\\]');
                var contentMatch = fullContent.match(contentRegex);
                
                if (contentMatch && contentMatch[1]) {
                    // Copy just the markdown content
                    contentToCopy = contentMatch[1].trim();
                } else {
                    // Copy the full text content
                    contentToCopy = fullContent;
                }
                
                console.log('MPCC: Copy button - Content to copy:', contentToCopy);
                
                // Create temporary textarea to copy
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(contentToCopy).select();
                document.execCommand('copy');
                $temp.remove();
                
                $(this).text('Copied!').prop('disabled', true);
                setTimeout(() => {
                    $(this).text('Copy to Clipboard').prop('disabled', false);
                }, 2000);
            });
            
            // Handle cancel button
            $(document).on('click', '.mpcc-cancel-editor-update', function() {
                $(this).closest('.mpcc-editor-content-update-buttons').fadeOut();
            });
        });
        </script>
        <?php
    }

    /**
     * Get parent course for a lesson
     */
    private function getParentCourse(\WP_Post $lesson): ?\WP_Post
    {
        // Check if lesson has a parent course meta
        $course_id = get_post_meta($lesson->ID, '_mpcs_course_id', true);
        if ($course_id) {
            return get_post($course_id);
        }
        
        // Alternative: Check if lesson is referenced in any course
        $courses = get_posts([
            'post_type' => 'mpcs-course',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_mpcs_sections',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        
        foreach ($courses as $course) {
            $sections = get_post_meta($course->ID, '_mpcs_sections', true);
            if (is_array($sections)) {
                foreach ($sections as $section) {
                    if (isset($section['lessons']) && is_array($section['lessons'])) {
                        foreach ($section['lessons'] as $section_lesson) {
                            if (isset($section_lesson['ID']) && $section_lesson['ID'] == $lesson->ID) {
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
    private function getContextData(\WP_Post $post, ?\WP_Post $parent_course = null): array
    {
        if ($post->post_type === self::POST_TYPE_LESSON) {
            return $this->getLessonContextData($post, $parent_course);
        } else {
            return $this->getCourseContextData($post);
        }
    }

    /**
     * Get comprehensive lesson context data for AI
     */
    private function getLessonContextData(\WP_Post $post, ?\WP_Post $parent_course = null): array
    {
        // Basic lesson information
        $lessonData = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'excerpt' => $post->post_excerpt
        ];

        // Get lesson metadata
        $lessonData['objectives'] = get_post_meta($post->ID, '_mpcs_lesson_objectives', true) ?: [];
        $lessonData['duration'] = get_post_meta($post->ID, '_mpcs_lesson_duration', true) ?: 0;
        $lessonData['video_url'] = get_post_meta($post->ID, '_mpcs_lesson_video_url', true) ?: '';
        $lessonData['downloads'] = get_post_meta($post->ID, '_mpcs_lesson_downloads', true) ?: [];

        // Include parent course information
        if ($parent_course) {
            $lessonData['course'] = [
                'id' => $parent_course->ID,
                'title' => $parent_course->post_title,
                'description' => substr($parent_course->post_content, 0, 500),
                'learning_objectives' => get_post_meta($parent_course->ID, '_mpcs_course_learning_objectives', true) ?: [],
                'target_audience' => get_post_meta($parent_course->ID, '_mpcs_course_target_audience', true) ?: ''
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
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'status' => $post->post_status,
            'excerpt' => $post->post_excerpt
        ];

        // Get course metadata
        $courseData['learning_objectives'] = get_post_meta($post->ID, '_mpcs_course_learning_objectives', true) ?: [];
        $courseData['difficulty_level'] = get_post_meta($post->ID, '_mpcs_course_difficulty_level', true) ?: '';
        $courseData['target_audience'] = get_post_meta($post->ID, '_mpcs_course_target_audience', true) ?: '';
        $courseData['prerequisites'] = get_post_meta($post->ID, '_mpcs_course_prerequisites', true) ?: [];
        $courseData['estimated_duration'] = get_post_meta($post->ID, '_mpcs_course_estimated_duration', true) ?: '';
        $courseData['course_category'] = get_post_meta($post->ID, '_mpcs_course_category', true) ?: '';
        $courseData['template_type'] = get_post_meta($post->ID, '_mpcs_course_template_type', true) ?: '';

        // Get sections data
        $sections = get_post_meta($post->ID, '_mpcs_sections', true) ?: [];
        $courseData['sections'] = [];
        $courseData['section_count'] = 0;
        $courseData['lesson_count'] = 0;

        if (is_array($sections)) {
            $courseData['section_count'] = count($sections);
            
            foreach ($sections as $index => $section) {
                $sectionData = [
                    'title' => $section['section_title'] ?? 'Untitled Section',
                    'description' => $section['section_description'] ?? '',
                    'order' => $index,
                    'lessons' => []
                ];

                if (isset($section['lessons']) && is_array($section['lessons'])) {
                    $courseData['lesson_count'] += count($section['lessons']);
                    
                    foreach ($section['lessons'] as $lessonIndex => $lesson) {
                        $lessonData = [
                            'title' => $lesson['post_title'] ?? 'Untitled Lesson',
                            'content' => isset($lesson['post_content']) ? substr($lesson['post_content'], 0, 200) . '...' : '',
                            'order' => $lessonIndex,
                            'objectives' => $lesson['meta_input']['_mpcs_lesson_objectives'] ?? [],
                            'duration' => $lesson['meta_input']['_mpcs_lesson_duration'] ?? 0
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
            
            $message = sanitize_textarea_field($_POST['message'] ?? '');
            $postId = intval($_POST['post_id'] ?? 0);
            $postType = sanitize_key($_POST['post_type'] ?? '');
            
            // Handle context_data - it may come as an array or JSON string
            $contextDataRaw = $_POST['context_data'] ?? '{}';
            $contextData = is_array($contextDataRaw) ? $contextDataRaw : json_decode(stripslashes($contextDataRaw), true);
            
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
            $container = function_exists('mpcc_container') ? mpcc_container() : null;
            $llmService = $container ? $container->get(\MemberPressCoursesCopilot\Services\LLMService::class) : new \MemberPressCoursesCopilot\Services\LLMService();
            
            // Build prompt based on post type
            if ($postType === self::POST_TYPE_LESSON) {
                $prompt = $this->buildLessonPrompt($message, $contextData);
            } else {
                $prompt = $this->buildCoursePrompt($message, $contextData);
            }
            
            // Generate AI response
            $response = $llmService->generateContent($prompt);
            $aiContent = $response['content'] ?? 'I apologize, but I encountered an error. Please try again.';
            
            // Check if the response contains a content update
            $hasContentUpdate = $this->detectContentUpdate($message, $aiContent, $postType);
            
            wp_send_json_success([
                'message' => $aiContent,
                'has_content_update' => $hasContentUpdate
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
            
            $postId = intval($_POST['post_id'] ?? 0);
            $content = wp_kses_post($_POST['content'] ?? '');
            
            if (empty($postId)) {
                throw new \Exception('Post ID is required');
            }
            
            // Update the post content
            $result = wp_update_post([
                'ID' => $postId,
                'post_content' => $content
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
            $prompt .= "Course Context: " . substr($lessonData['course']['description'], 0, 200) . "...\n";
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
            $prompt .= "INSTRUCTION: Provide the lesson content in Markdown format wrapped between [LESSON_CONTENT] and [/LESSON_CONTENT] tags. ";
            $prompt .= "Include an engaging introduction, clear explanations with examples, practice activities, and a summary. ";
            $prompt .= "Use proper Markdown formatting with headers, bullet points, numbered lists, and emphasis where appropriate. ";
            $prompt .= "Make the content educational, practical, and engaging for online learners. ";
            $prompt .= "Do not include any text outside the [LESSON_CONTENT] tags.";
        } else {
            $prompt .= "Provide helpful guidance about creating effective lesson content.";
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
            $prompt .= "Learning Objectives: " . implode(', ', $courseData['learning_objectives']) . "\n";
        }
        
        if (!empty($courseData['target_audience'])) {
            $prompt .= "Target Audience: {$courseData['target_audience']}\n";
        }
        
        if (!empty($courseData['section_count']) && !empty($courseData['lesson_count'])) {
            $prompt .= "Course Structure: {$courseData['section_count']} sections with {$courseData['lesson_count']} lessons\n";
        }
        
        $prompt .= "\nUser Request: {$message}\n\n";
        
        // Check if user is asking for a new description
        $userWantsDescription = preg_match('/\b(write|create|update|improve|enhance|rewrite|new)\b/i', $message);
        
        if ($userWantsDescription) {
            $prompt .= "INSTRUCTION: Provide the course description in Markdown format wrapped between [COURSE_CONTENT] and [/COURSE_CONTENT] tags. ";
            $prompt .= "Include 3-5 paragraphs covering the overview, benefits, learning outcomes, target audience, and call-to-action. ";
            $prompt .= "Use proper Markdown formatting with headers, bullet points, and emphasis where appropriate. ";
            $prompt .= "Do not include any text outside the [COURSE_CONTENT] tags.";
        } else {
            $prompt .= "Provide helpful guidance about the course description.";
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
        if (strpos($aiResponse, "[{$contentTag}]") !== false && 
            strpos($aiResponse, "[/{$contentTag}]") !== false) {
            return true;
        }
        
        // Fallback: Check if user is requesting content and response seems substantial
        $requestKeywords = ['write', 'create', 'generate', 'make', 'build', 'develop', 'update', 'rewrite', 'improve', 'enhance'];
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