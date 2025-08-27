<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Lesson AI Integration Service
 * 
 * Handles AI integration for individual lesson pages
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
class LessonAIIntegration extends BaseService
{
    /**
     * Initialize the service
     */
    public function init(): void
    {
        // Add button and modal to lesson edit pages
        add_action('edit_form_after_title', [$this, 'addAIButton'], 5); // Classic Editor
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']); // Block Editor
        add_action('admin_footer', [$this, 'addAIModal']);
        
        // Register AJAX handlers
        add_action('wp_ajax_mpcc_lesson_ai_chat', [$this, 'handleAIChat']);
        add_action('wp_ajax_mpcc_update_lesson_content', [$this, 'handleUpdateLessonContent']);
    }

    /**
     * Add AI button after lesson title
     */
    public function addAIButton(): void
    {
        global $post;
        
        // Only add to lesson post type
        if (!$post || $post->post_type !== 'mpcs-lesson') {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add button after the page title
            function addLessonAIButton() {
                // Check if button already exists
                if ($('#mpcc-lesson-ai-button').length > 0) {
                    return;
                }
                
                // Find the page title
                var $pageTitle = $('h1.wp-heading-inline').first();
                if ($pageTitle.length === 0) {
                    return;
                }
                
                // Create the button styled like WordPress "Add New" buttons
                var $aiButton = $('<a href="#" id="mpcc-lesson-ai-button" class="page-title-action">' +
                    '<span class="dashicons dashicons-lightbulb" style="margin: 3px 5px 0 -2px; font-size: 16px;"></span>' +
                    'Generate with AI' +
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
                        window.MPCCUtils.modalManager.open('#mpcc-lesson-ai-modal-overlay');
                    } else {
                        $('#mpcc-lesson-ai-modal-overlay').fadeIn();
                        $('body').css('overflow', 'hidden');
                    }
                    
                    // Focus on input
                    setTimeout(function() {
                        $('#mpcc-lesson-ai-input').focus();
                    }, 300);
                });
            }
            
            // Add button on page load
            addLessonAIButton();
            
            // Also add button if page structure changes (for Gutenberg compatibility)
            var observer = new MutationObserver(function(mutations) {
                addLessonAIButton();
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
        
        // Only add to lesson post type
        if (!$post || $post->post_type !== 'mpcs-lesson' || get_current_screen()->base !== 'post') {
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
        
        // Only add to lesson post type
        if (!$post || $post->post_type !== 'mpcs-lesson') {
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
                console.log('MPCC: Block Editor Lesson AI button script loaded');
                
                // Wait for editor to be ready
                const unsubscribe = wp.data.subscribe(() => {
                    // Try multiple selectors for better compatibility
                    const editorWrapper = document.querySelector('.editor-header__settings') || 
                                         document.querySelector('.editor-document-tools') ||
                                         document.querySelector('.edit-post-header__toolbar');
                    const existingButton = document.getElementById('mpcc-lesson-ai-button-block');
                    
                    if (editorWrapper && !existingButton) {
                        console.log('MPCC: Creating Lesson AI button');
                        // Create button container
                        const buttonContainer = document.createElement('div');
                        buttonContainer.style.marginLeft = '10px';
                        buttonContainer.style.display = 'inline-flex';
                        buttonContainer.style.alignItems = 'center';
                        
                        // Create button
                        const aiButton = document.createElement('button');
                        aiButton.id = 'mpcc-lesson-ai-button-block';
                        aiButton.className = 'components-button is-primary';
                        aiButton.style.background = '#6B4CE6';
                        aiButton.style.borderColor = '#6B4CE6';
                        aiButton.style.height = '36px';
                        aiButton.style.whiteSpace = 'nowrap';
                        aiButton.innerHTML = '<span class=\"dashicons dashicons-lightbulb\" style=\"margin: 3px 5px 0 0; vertical-align: middle;\"></span>Generate with AI';
                        
                        // Add click handler
                        aiButton.onclick = function(e) {
                            e.preventDefault();
                            console.log('Lesson AI button clicked');
                            
                            // Use modal manager if available, otherwise fallback
                            if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                                console.log('Using MPCCUtils modal manager');
                                window.MPCCUtils.modalManager.open('#mpcc-lesson-ai-modal-overlay');
                            } else {
                                console.log('Using fallback modal open');
                                const modal = document.getElementById('mpcc-lesson-ai-modal-overlay');
                                if (modal) {
                                    modal.style.display = 'block';
                                    document.body.style.overflow = 'hidden';
                                    setTimeout(() => {
                                        const input = document.getElementById('mpcc-lesson-ai-input');
                                        if (input) input.focus();
                                    }, 300);
                                }
                            }
                        };
                        
                        buttonContainer.appendChild(aiButton);
                        editorWrapper.appendChild(buttonContainer);
                        
                        console.log('MPCC: Lesson AI button added to toolbar');
                        
                        // Unsubscribe once button is added
                        unsubscribe();
                    }
                });
            });
            "
        );
    }

    /**
     * Render AI Modal content for lessons
     */
    private function renderAIModal(\WP_Post $post): void
    {
        // Add nonce for security
        NonceConstants::field(NonceConstants::AI_ASSISTANT, 'mpcc_lesson_ai_nonce');
        
        // Get parent course information
        $parent_course = $this->getParentCourse($post);
        
        ?>
        <style>
        /* Override the CSS pseudo-element X */
        #mpcc-lesson-ai-modal-overlay .mpcc-modal-close::before {
            content: none !important;
        }
        </style>
        
        <!-- Using existing modal styles from ai-copilot.css -->
        <div class="mpcc-modal-overlay" id="mpcc-lesson-ai-modal-overlay" style="display: none;">
            <div class="mpcc-modal" style="max-width: 700px; width: 90%;">
                <div class="mpcc-modal-header">
                    <h3>AI Lesson Assistant</h3>
                    <button type="button" class="mpcc-modal-close" aria-label="Close" style="font-size: 0;">
                        <span class="dashicons dashicons-no-alt" style="font-size: 20px;"></span>
                    </button>
                </div>
                <div class="mpcc-modal-body" style="display: flex; flex-direction: column; height: 500px; padding: 0;">
                    <div id="mpcc-lesson-ai-messages" style="flex: 1; overflow-y: auto; padding: 20px; background: #f9f9f9;">
                        <div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 12px; background: #e7f3ff; border-radius: 4px;">
                            <strong>AI Assistant:</strong> <div class="ai-content">Hi! I'm here to help you create engaging lesson content. I can:
                            <br>• <strong>Generate complete lesson content</strong> based on your topic
                            <br>• Add interactive elements and examples
                            <br>• Create exercises and practice activities
                            <br>• Suggest multimedia resources to enhance learning
                            <?php if ($parent_course): ?>
                            <br><br>I see this lesson is part of "<strong><?php echo esc_html($parent_course->post_title); ?></strong>". I'll make sure the content aligns with the course objectives.
                            <?php endif; ?>
                            <br><br>What would you like this lesson to cover?</div>
                        </div>
                    </div>
                    
                    <div style="padding: 20px; background: white; border-top: 1px solid #ddd;">
                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                            <textarea id="mpcc-lesson-ai-input" 
                                      placeholder="Describe what you want this lesson to teach..." 
                                      style="flex: 1; min-height: 80px; border: 1px solid #ddd; border-radius: 3px; padding: 10px; resize: vertical; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"></textarea>
                            <button type="button" id="mpcc-lesson-ai-send" class="button button-primary" style="height: 36px; padding: 0 20px; white-space: nowrap;">
                                Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('MPCC: Lesson AI Modal initialized');
            
            // Simple markdown to HTML converter
            function lessonMarkdownToHtml(markdown) {
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
            $('.mpcc-modal-close', '#mpcc-lesson-ai-modal-overlay').on('click', function() {
                if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                    window.MPCCUtils.modalManager.close('#mpcc-lesson-ai-modal-overlay');
                } else {
                    $('#mpcc-lesson-ai-modal-overlay').fadeOut();
                    $('body').css('overflow', '');
                }
            });
            
            // Close on overlay click
            $('#mpcc-lesson-ai-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                        window.MPCCUtils.modalManager.close('#mpcc-lesson-ai-modal-overlay');
                    } else {
                        $(this).fadeOut();
                        $('body').css('overflow', '');
                    }
                }
            });
            
            // Handle send message
            $('#mpcc-lesson-ai-send').on('click', function() {
                var input = $('#mpcc-lesson-ai-input');
                var message = input.val().trim();
                
                if (!message) {
                    alert('Please enter a message');
                    return;
                }
                
                // Add user message to chat
                var userMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #f0f0f0; border-radius: 4px; text-align: right;">' +
                    '<strong>You:</strong> ' + $('<div>').text(message).html() + '</div>';
                $('#mpcc-lesson-ai-messages').append(userMsg);
                
                // Clear input
                input.val('');
                
                // Scroll to bottom
                var messages = $('#mpcc-lesson-ai-messages');
                messages.scrollTop(messages[0].scrollHeight);
                
                // Show typing indicator
                var typingMsg = '<div id="mpcc-lesson-typing" class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                    '<strong>AI Assistant:</strong> <em>Typing...</em></div>';
                $('#mpcc-lesson-ai-messages').append(typingMsg);
                messages.scrollTop(messages[0].scrollHeight);
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_lesson_ai_chat',
                        nonce: $('#mpcc_lesson_ai_nonce').val(),
                        message: message,
                        post_id: <?php echo $post->ID; ?>,
                        lesson_data: <?php echo json_encode($this->getLessonContextData($post, $parent_course)); ?>
                    },
                    success: function(response) {
                        $('#mpcc-lesson-typing').remove();
                        
                        if (response.success) {
                            var messageText = response.data.message;
                            var hasContentUpdate = response.data.has_content_update || false;
                            
                            // Debug: Log the raw AI response
                            console.log('MPCC: Raw Lesson AI response:', messageText);
                            console.log('MPCC: Has content update:', hasContentUpdate);
                            
                            // Check if the message contains markdown content tags
                            var contentMatch = messageText.match(/\[LESSON_CONTENT\]([\s\S]*?)\[\/LESSON_CONTENT\]/);
                            var displayText = messageText;
                            
                            if (contentMatch) {
                                // Format the markdown content for display as WordPress-ready content
                                var markdownContent = contentMatch[1].trim();
                                var htmlContent = lessonMarkdownToHtml(markdownContent);
                                displayText = htmlContent;
                            } else {
                                // Regular message formatting
                                displayText = messageText.replace(/\n/g, '<br>');
                            }
                            
                            var aiMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                                '<strong>AI Assistant:</strong> <div class="ai-content">' + displayText + '</div></div>';
                            $('#mpcc-lesson-ai-messages').append(aiMsg);
                            
                            // If content update is provided, show apply button
                            if (hasContentUpdate) {
                                var applyButtons = '<div class="mpcc-lesson-content-update-buttons" style="margin: 10px 0; padding: 10px; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 4px;">' +
                                    '<p style="margin: 0 0 10px 0; font-weight: bold;">Apply this content to your lesson?</p>' +
                                    '<button type="button" class="button button-primary mpcc-apply-lesson-content" style="margin-right: 5px;">Apply Content</button>' +
                                    '<button type="button" class="button mpcc-copy-lesson-content" style="margin-right: 5px;">Copy to Clipboard</button>' +
                                    '<button type="button" class="button mpcc-cancel-lesson-update">Cancel</button>' +
                                    '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">This will update your lesson content in the editor below.</p>' +
                                '</div>';
                                $('#mpcc-lesson-ai-messages').append(applyButtons);
                            }
                        } else {
                            var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                                '<strong>Error:</strong> ' + (response.data || 'Failed to get AI response') + '</div>';
                            $('#mpcc-lesson-ai-messages').append(errorMsg);
                        }
                        
                        var messages = $('#mpcc-lesson-ai-messages');
                        messages.scrollTop(messages[0].scrollHeight);
                    },
                    error: function() {
                        $('#mpcc-lesson-typing').remove();
                        var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                            '<strong>Error:</strong> Network error. Please try again.</div>';
                        $('#mpcc-lesson-ai-messages').append(errorMsg);
                        
                        var messages = $('#mpcc-lesson-ai-messages');
                        messages.scrollTop(messages[0].scrollHeight);
                    }
                });
            });
            
            // Handle Enter key
            $('#mpcc-lesson-ai-input').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    $('#mpcc-lesson-ai-send').click();
                }
            });
            
            // Handle apply content button
            $(document).on('click', '.mpcc-apply-lesson-content', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Applying...');
                
                // Get the AI-generated content
                var $aiMessage = $(this).closest('.mpcc-lesson-content-update-buttons').prev('.mpcc-ai-message').find('.ai-content');
                var fullContent = $aiMessage.text(); // Use .text() to get raw content without HTML
                
                console.log('MPCC: Extracting lesson content from:', fullContent);
                
                var lessonContent = '';
                
                // Look for content between [LESSON_CONTENT] tags
                var contentMatch = fullContent.match(/\[LESSON_CONTENT\]([\s\S]*?)\[\/LESSON_CONTENT\]/);
                
                if (contentMatch && contentMatch[1]) {
                    // Found markdown content
                    var markdownContent = contentMatch[1].trim();
                    console.log('MPCC: Found lesson markdown content:', markdownContent);
                    
                    // Convert markdown to HTML
                    lessonContent = lessonMarkdownToHtml(markdownContent);
                    console.log('MPCC: Converted to HTML:', lessonContent);
                } else {
                    // Fallback: use the full content if no tags found
                    console.log('MPCC: No [LESSON_CONTENT] tags found, using full content');
                    lessonContent = $aiMessage.html()
                        .replace(/<br\s*\/?>/gi, '\n')
                        .replace(/\n{3,}/g, '\n\n')
                        .trim();
                }
                    
                console.log('MPCC: Final lesson content to apply (length: ' + lessonContent.length + '):', lessonContent);
                
                // Update the lesson content via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_update_lesson_content',
                        nonce: $('#mpcc_lesson_ai_nonce').val(),
                        post_id: <?php echo $post->ID; ?>,
                        content: lessonContent
                    },
                    success: function(response) {
                        console.log('MPCC: Lesson AJAX response:', response);
                        
                        if (response.success) {
                            // For Block Editor - we need to reload the post data
                            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                                console.log('MPCC: Updating Block Editor');
                                // Force refresh the post content
                                wp.data.dispatch('core').receiveEntityRecords('postType', 'mpcs-lesson', [
                                    {
                                        id: <?php echo $post->ID; ?>,
                                        content: { raw: lessonContent, rendered: lessonContent }
                                    }
                                ]);
                                // Also update via editPost
                                wp.data.dispatch('core/editor').editPost({content: lessonContent});
                            } else if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                                // For classic editor
                                console.log('MPCC: Updating Classic Editor (TinyMCE)');
                                tinyMCE.get('content').setContent(lessonContent);
                            } else if ($('#content').length) {
                                // For text editor
                                console.log('MPCC: Updating Text Editor');
                                $('#content').val(lessonContent);
                            } else {
                                console.log('MPCC: No editor found to update');
                            }
                            
                            $button.text('Applied!');
                            setTimeout(function() {
                                $('.mpcc-lesson-content-update-buttons').fadeOut();
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
            $(document).on('click', '.mpcc-copy-lesson-content', function() {
                // Get the AI-generated content
                var $aiMessage = $(this).closest('.mpcc-lesson-content-update-buttons').prev('.mpcc-ai-message').find('.ai-content');
                var fullContent = $aiMessage.text();
                var contentToCopy = '';
                
                // Look for markdown content
                var contentMatch = fullContent.match(/\[LESSON_CONTENT\]([\s\S]*?)\[\/LESSON_CONTENT\]/);
                
                if (contentMatch && contentMatch[1]) {
                    // Copy just the markdown content
                    contentToCopy = contentMatch[1].trim();
                } else {
                    // Copy the full text content
                    contentToCopy = fullContent;
                }
                
                console.log('MPCC: Lesson copy button - Content to copy:', contentToCopy);
                
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
            $(document).on('click', '.mpcc-cancel-lesson-update', function() {
                $(this).closest('.mpcc-lesson-content-update-buttons').fadeOut();
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
     * Handle AI chat AJAX request for lessons
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
            // Handle lesson_data - it may come as an array or JSON string depending on how jQuery sends it
            $lessonDataRaw = $_POST['lesson_data'] ?? '{}';
            $lessonData = is_array($lessonDataRaw) ? $lessonDataRaw : json_decode(stripslashes($lessonDataRaw), true);
            
            if (empty($message)) {
                throw new \Exception('Message is required');
            }
            
            if (empty($postId)) {
                throw new \Exception('Post ID is required');
            }
            
            // Get LLM service from container with graceful fallback
            $container = function_exists('mpcc_container') ? mpcc_container() : null;
            $llmService = $container ? $container->get(\MemberPressCoursesCopilot\Services\LLMService::class) : new \MemberPressCoursesCopilot\Services\LLMService();
            
            // Build prompt focused on lesson content generation
            $prompt = $this->buildLessonPrompt($message, $lessonData);
            
            // Generate AI response
            $response = $llmService->generateContent($prompt);
            $aiContent = $response['content'] ?? 'I apologize, but I encountered an error. Please try again.';
            
            // Check if the response contains a lesson content update
            $hasContentUpdate = $this->detectLessonContentUpdate($message, $aiContent);
            
            wp_send_json_success([
                'message' => $aiContent,
                'has_content_update' => $hasContentUpdate
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle update lesson content AJAX request
     */
    public function handleUpdateLessonContent(): void
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
     * Detect if AI response contains a content update
     */
    private function detectLessonContentUpdate(string $userMessage, string $aiResponse): bool
    {
        // Check if AI response contains the [LESSON_CONTENT] tags
        if (strpos($aiResponse, '[LESSON_CONTENT]') !== false && 
            strpos($aiResponse, '[/LESSON_CONTENT]') !== false) {
            return true;
        }
        
        // Fallback: Check if user is requesting content and response seems substantial
        $requestKeywords = ['write', 'create', 'generate', 'make', 'build', 'develop'];
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
        if ($userRequestsContent && $wordCount > 150) {
            return true;
        }
        
        return false;
    }
}