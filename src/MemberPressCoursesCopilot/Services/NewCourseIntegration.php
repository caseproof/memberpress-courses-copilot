<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * New Course Integration Service
 * 
 * Fresh, simple implementation of AI chat for course pages
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
class NewCourseIntegration extends BaseService
{
    /**
     * Initialize the service
     */
    public function init(): void
    {
        // Add button and modal to course edit pages
        add_action('edit_form_after_title', [$this, 'addAIButton'], 5); // Classic Editor
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']); // Block Editor
        add_action('admin_footer', [$this, 'addAIModal']);
        
        // Register AJAX handlers
        add_action('wp_ajax_mpcc_new_ai_chat', [$this, 'handleAIChat']);
        add_action('wp_ajax_mpcc_update_course_content', [$this, 'handleUpdateCourseContent']);
    }

    /**
     * Add AI button after course title
     */
    public function addAIButton(): void
    {
        global $post;
        
        // Only add to course post type
        if (!$post || $post->post_type !== 'mpcs-course') {
            return;
        }
        
        ?>
        <div id="mpcc-ai-button-container" style="margin: 10px 0;">
            <button type="button" id="mpcc-open-ai-modal" class="button button-primary" style="background: #6B4CE6; border-color: #6B4CE6;">
                <span class="dashicons dashicons-lightbulb" style="margin: 3px 5px 0 0;"></span>
                Create with AI
            </button>
        </div>
        <?php
    }
    
    /**
     * Add AI Modal to admin footer
     */
    public function addAIModal(): void
    {
        global $post;
        
        // Only add to course post type
        if (!$post || $post->post_type !== 'mpcs-course' || get_current_screen()->base !== 'post') {
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
        
        // Only add to course post type
        if (!$post || $post->post_type !== 'mpcs-course') {
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
                console.log('MPCC: Block Editor AI button script loaded');
                
                // Wait for editor to be ready
                const unsubscribe = wp.data.subscribe(() => {
                    // Try multiple selectors for better compatibility
                    const editorWrapper = document.querySelector('.editor-header__settings') || 
                                         document.querySelector('.editor-document-tools') ||
                                         document.querySelector('.edit-post-header__toolbar');
                    const existingButton = document.getElementById('mpcc-open-ai-modal-block');
                    
                    console.log('MPCC: Toolbar check - wrapper:', !!editorWrapper, 'button exists:', !!existingButton);
                    
                    if (editorWrapper && !existingButton) {
                        console.log('MPCC: Creating AI button');
                        // Create button container
                        const buttonContainer = document.createElement('div');
                        buttonContainer.style.marginLeft = '10px';
                        buttonContainer.style.display = 'inline-flex';
                        buttonContainer.style.alignItems = 'center';
                        
                        // Create button
                        const aiButton = document.createElement('button');
                        aiButton.id = 'mpcc-open-ai-modal-block';
                        aiButton.className = 'components-button is-primary';
                        aiButton.style.background = '#6B4CE6';
                        aiButton.style.borderColor = '#6B4CE6';
                        aiButton.style.height = '36px';
                        aiButton.style.whiteSpace = 'nowrap';
                        aiButton.innerHTML = '<span class=\"dashicons dashicons-lightbulb\" style=\"margin: 3px 5px 0 0; vertical-align: middle;\"></span>Create with AI';
                        
                        // Add click handler
                        aiButton.onclick = function(e) {
                            e.preventDefault();
                            console.log('AI button clicked');
                            
                            // Use modal manager if available, otherwise fallback
                            if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                                console.log('Using MPCCUtils modal manager');
                                window.MPCCUtils.modalManager.open('#mpcc-ai-modal-overlay');
                            } else {
                                console.log('Using fallback modal open');
                                const modal = document.getElementById('mpcc-ai-modal-overlay');
                                if (modal) {
                                    modal.style.display = 'block';
                                    document.body.style.overflow = 'hidden';
                                    setTimeout(() => {
                                        const input = document.getElementById('mpcc-ai-input');
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
        // Add nonce for security
        NonceConstants::field(NonceConstants::AI_ASSISTANT, 'mpcc_ai_nonce');
        
        ?>
        <style>
        /* Override the CSS pseudo-element X */
        #mpcc-ai-modal-overlay .mpcc-modal-close::before {
            content: none !important;
        }
        </style>
        
        <!-- Using existing modal styles from ai-copilot.css -->
        <div class="mpcc-modal-overlay" id="mpcc-ai-modal-overlay" style="display: none;">
            <div class="mpcc-modal" style="max-width: 700px; width: 90%;">
                <div class="mpcc-modal-header">
                    <h3>AI Course Assistant</h3>
                    <button type="button" class="mpcc-modal-close" aria-label="Close" style="font-size: 0;">
                        <span class="dashicons dashicons-no-alt" style="font-size: 20px;"></span>
                    </button>
                </div>
                <div class="mpcc-modal-body" style="display: flex; flex-direction: column; height: 500px; padding: 0;">
                    <div id="mpcc-ai-messages" style="flex: 1; overflow-y: auto; padding: 20px; background: #f9f9f9;">
                        <div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 12px; background: #e7f3ff; border-radius: 4px;">
                            <strong>AI Assistant:</strong> <div class="ai-content">Hi! I'm here to help you improve your course overview and description. I can:
                            <br>• <strong>Update your course description</strong> - Just ask me to rewrite or enhance it
                            <br>• Provide compelling content that attracts students
                            <br>• Suggest improvements to your course overview
                            <br>• Help you highlight key benefits and learning outcomes
                            <br><br><em>Note: I focus on the main course content. For lessons and curriculum structure, use the Curriculum tab above.</em>
                            <br><br>Would you like me to enhance your course description?</div>
                        </div>
                    </div>
                    
                    <div style="padding: 20px; background: white; border-top: 1px solid #ddd;">
                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                            <textarea id="mpcc-ai-input" 
                                      placeholder="Ask me anything about your course..." 
                                      style="flex: 1; min-height: 80px; border: 1px solid #ddd; border-radius: 3px; padding: 10px; resize: vertical; font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;"></textarea>
                            <button type="button" id="mpcc-ai-send" class="button button-primary" style="height: 36px; padding: 0 20px; white-space: nowrap;">
                                Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('MPCC: AI Modal initialized');
            
            // Open modal on button click
            $('#mpcc-open-ai-modal').on('click', function() {
                // Use existing modal manager if available
                if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                    window.MPCCUtils.modalManager.open('#mpcc-ai-modal-overlay');
                } else {
                    // Fallback
                    $('#mpcc-ai-modal-overlay').fadeIn();
                    $('body').css('overflow', 'hidden');
                }
                
                // Focus on input
                setTimeout(function() {
                    $('#mpcc-ai-input').focus();
                }, 300);
            });
            
            // Close modal using existing modal manager
            $('.mpcc-modal-close').on('click', function() {
                if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                    window.MPCCUtils.modalManager.close('#mpcc-ai-modal-overlay');
                } else {
                    $('#mpcc-ai-modal-overlay').fadeOut();
                    $('body').css('overflow', '');
                }
            });
            
            // Close on overlay click
            $('#mpcc-ai-modal-overlay').on('click', function(e) {
                if (e.target === this) {
                    if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                        window.MPCCUtils.modalManager.close('#mpcc-ai-modal-overlay');
                    } else {
                        $(this).fadeOut();
                        $('body').css('overflow', '');
                    }
                }
            });
            
            // Handle send message
            $('#mpcc-ai-send').on('click', function() {
                var input = $('#mpcc-ai-input');
                var message = input.val().trim();
                
                if (!message) {
                    alert('Please enter a message');
                    return;
                }
                
                // Add user message to chat
                var userMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #f0f0f0; border-radius: 4px; text-align: right;">' +
                    '<strong>You:</strong> ' + $('<div>').text(message).html() + '</div>';
                $('#mpcc-ai-messages').append(userMsg);
                
                // Clear input
                input.val('');
                
                // Scroll to bottom
                var messages = $('#mpcc-ai-messages');
                messages.scrollTop(messages[0].scrollHeight);
                
                // Show typing indicator
                var typingMsg = '<div id="mpcc-typing" class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                    '<strong>AI Assistant:</strong> <em>Typing...</em></div>';
                $('#mpcc-ai-messages').append(typingMsg);
                messages.scrollTop(messages[0].scrollHeight);
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_new_ai_chat',
                        nonce: $('#mpcc_ai_nonce').val(),
                        message: message,
                        post_id: <?php echo $post->ID; ?>,
                        course_data: <?php echo json_encode($this->getCourseContextData($post)); ?>
                    },
                    success: function(response) {
                        $('#mpcc-typing').remove();
                        
                        if (response.success) {
                            var messageText = response.data.message;
                            var hasContentUpdate = response.data.has_content_update || false;
                            
                            var aiMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                                '<strong>AI Assistant:</strong> <div class="ai-content">' + messageText.replace(/\n/g, '<br>') + '</div></div>';
                            $('#mpcc-ai-messages').append(aiMsg);
                            
                            // If content update is provided, show apply button
                            if (hasContentUpdate) {
                                var applyButtons = '<div class="mpcc-content-update-buttons" style="margin: 10px 0; padding: 10px; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 4px;">' +
                                    '<p style="margin: 0 0 10px 0; font-weight: bold;">Apply this content to your course?</p>' +
                                    '<button type="button" class="button button-primary mpcc-apply-content" style="margin-right: 5px;">Apply Content</button>' +
                                    '<button type="button" class="button mpcc-copy-content" style="margin-right: 5px;">Copy to Clipboard</button>' +
                                    '<button type="button" class="button mpcc-cancel-update">Cancel</button>' +
                                    '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">This will update your course description in the editor above.</p>' +
                                '</div>';
                                $('#mpcc-ai-messages').append(applyButtons);
                            }
                        } else {
                            var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                                '<strong>Error:</strong> ' + (response.data || 'Failed to get AI response') + '</div>';
                            $('#mpcc-ai-messages').append(errorMsg);
                        }
                        
                        var messages = $('#mpcc-ai-messages');
                        messages.scrollTop(messages[0].scrollHeight);
                    },
                    error: function() {
                        $('#mpcc-typing').remove();
                        var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                            '<strong>Error:</strong> Network error. Please try again.</div>';
                        $('#mpcc-ai-messages').append(errorMsg);
                        
                        var messages = $('#mpcc-ai-messages');
                        messages.scrollTop(messages[0].scrollHeight);
                    }
                });
            });
            
            // Handle Enter key
            $('#mpcc-ai-input').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    $('#mpcc-ai-send').click();
                }
            });
            
            // Handle apply content button
            $(document).on('click', '.mpcc-apply-content', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Applying...');
                
                // Get the AI-generated content
                var $aiMessage = $(this).closest('.mpcc-content-update-buttons').prev('.mpcc-ai-message').find('.ai-content');
                var fullContent = $aiMessage.html();
                
                console.log('MPCC: Extracting content from:', fullContent);
                
                // Extract just the course description part
                var courseContent = '';
                
                // Method 1: Try to extract content after "suggested rewrite:" or similar markers
                var rewriteMatch = fullContent.match(/(?:suggested rewrite:|Here['']s (?:a |the )?(?:suggested |updated |new )?(?:rewrite|description|content|course description):?)\s*<br>(?:<br>)?([\s\S]*?)(?:<br><br>(?:Would you|What do you|Is there|Let me know)|$)/i);
                
                if (rewriteMatch && rewriteMatch[1]) {
                    courseContent = rewriteMatch[1];
                    console.log('MPCC: Found content after marker:', courseContent);
                } else {
                    // Method 2: Look for the main content after a title or intro
                    var contentBlocks = fullContent.split('<br><br>');
                    var contentParts = [];
                    var foundMainContent = false;
                    
                    // Collect all content blocks that look like course description
                    for (var i = 0; i < contentBlocks.length; i++) {
                        var block = contentBlocks[i].trim();
                        
                        // Skip obvious non-content blocks
                        if (block.match(/^(Would you|What do you|Is there|Let me know|Do you want)/i) ||
                            block.length < 50) {
                            continue;
                        }
                        
                        // Check if this looks like the start of main content
                        if (!foundMainContent && (
                            block.match(/^(Transform|Welcome|Business Startup|A comprehensive)/i) ||
                            block.length > 150
                        )) {
                            foundMainContent = true;
                        }
                        
                        // Collect content blocks
                        if (foundMainContent) {
                            // Stop at ending phrases
                            if (block.match(/^(Don't let another|Join thousands|Enroll now|Sign up)/i)) {
                                contentParts.push(block);
                                break;
                            }
                            contentParts.push(block);
                        }
                    }
                    
                    if (contentParts.length > 0) {
                        courseContent = contentParts.join('<br><br>');
                        console.log('MPCC: Found ' + contentParts.length + ' content blocks');
                    } else {
                        // Method 3: Fallback - use everything except obvious chat elements
                        courseContent = fullContent
                            .replace(/^[\s\S]*?(?:Here['']s (?:a |the )?(?:suggested |updated |new )?(?:rewrite|description|content):?\s*<br>)/i, '')
                            .replace(/<br><br>(?:Would you|What do you|Is there|Let me know)[\s\S]*$/i, '');
                        console.log('MPCC: Using fallback content');
                    }
                }
                
                // Remove any title line if it exists
                var lines = courseContent.split('<br>');
                if (lines.length > 0 && lines[0].match(/^[^.!?,]+:?\s*$/) && lines[0].length < 100) {
                    courseContent = lines.slice(1).join('<br>').trim();
                    console.log('MPCC: Removed title line');
                }
                
                // Clean up the content
                courseContent = courseContent
                    .replace(/<br\s*\/?>/gi, '\n')
                    .replace(/\n{3,}/g, '\n\n')
                    .trim();
                    
                console.log('MPCC: Final content to apply:', courseContent);
                
                // Update the course content via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_update_course_content',
                        nonce: $('#mpcc_ai_nonce').val(),
                        post_id: <?php echo $post->ID; ?>,
                        content: courseContent
                    },
                    success: function(response) {
                        console.log('MPCC: AJAX response:', response);
                        
                        if (response.success) {
                            // For Block Editor - we need to reload the post data
                            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                                console.log('MPCC: Updating Block Editor');
                                // Force refresh the post content
                                wp.data.dispatch('core').receiveEntityRecords('postType', 'mpcs-course', [
                                    {
                                        id: <?php echo $post->ID; ?>,
                                        content: { raw: courseContent, rendered: courseContent }
                                    }
                                ]);
                                // Also update via editPost
                                wp.data.dispatch('core/editor').editPost({content: courseContent});
                            } else if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                                // For classic editor
                                console.log('MPCC: Updating Classic Editor (TinyMCE)');
                                tinyMCE.get('content').setContent(courseContent);
                            } else if ($('#content').length) {
                                // For text editor
                                console.log('MPCC: Updating Text Editor');
                                $('#content').val(courseContent);
                            } else {
                                console.log('MPCC: No editor found to update');
                            }
                            
                            $button.text('Applied!');
                            setTimeout(function() {
                                $('.mpcc-content-update-buttons').fadeOut();
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
            $(document).on('click', '.mpcc-copy-content', function() {
                // Get the AI-generated content
                var aiContent = $(this).closest('.mpcc-content-update-buttons').prev('.mpcc-ai-message').find('.ai-content').text();
                
                // Create temporary textarea to copy
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(aiContent).select();
                document.execCommand('copy');
                $temp.remove();
                
                $(this).text('Copied!').prop('disabled', true);
                setTimeout(() => {
                    $(this).text('Copy to Clipboard').prop('disabled', false);
                }, 2000);
            });
            
            // Handle cancel button
            $(document).on('click', '.mpcc-cancel-update', function() {
                $(this).closest('.mpcc-content-update-buttons').fadeOut();
            });
        });
        </script>
        <?php
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
            $courseData = json_decode(stripslashes($_POST['course_data'] ?? '{}'), true);
            
            if (empty($message)) {
                throw new \Exception('Message is required');
            }
            
            if (empty($postId)) {
                throw new \Exception('Post ID is required');
            }
            
            // Get LLM service from container
            $container = function_exists('mpcc_container') ? mpcc_container() : null;
            $llmService = $container ? $container->get(\MemberPressCoursesCopilot\Services\LLMService::class) : new \MemberPressCoursesCopilot\Services\LLMService();
            
            // Build prompt focused on course description enhancement
            $prompt = $this->buildCourseDescriptionPrompt($message, $courseData);
            
            // Generate AI response
            $response = $llmService->generateContent($prompt);
            $aiContent = $response['content'] ?? 'I apologize, but I encountered an error. Please try again.';
            
            // Check if the response contains a course description update
            $hasContentUpdate = $this->detectContentUpdate($message, $aiContent);
            
            wp_send_json_success([
                'message' => $aiContent,
                'has_content_update' => $hasContentUpdate
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle update course content AJAX request
     */
    public function handleUpdateCourseContent(): void
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
     * Build prompt for course description enhancement
     */
    private function buildCourseDescriptionPrompt(string $message, array $courseData): string
    {
        $prompt = "You are an AI assistant helping to improve course descriptions and overviews. ";
        $prompt .= "Focus on creating compelling, informative content that attracts students.\n\n";
        
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
        $prompt .= "Please provide a response that helps improve the course description. ";
        $prompt .= "If you're providing a new or updated description, make it engaging, clear, and focused on the value students will receive.";
        
        return $prompt;
    }
    
    /**
     * Detect if AI response contains a content update
     */
    private function detectContentUpdate(string $userMessage, string $aiResponse): bool
    {
        // Keywords that suggest content generation/update
        $updateKeywords = [
            'here is', 'here\'s', 'updated', 'revised', 'enhanced', 'improved',
            'description:', 'overview:', 'rewritten', 'new version'
        ];
        
        $userRequestsUpdate = false;
        $aiProvidesUpdate = false;
        
        // Check if user is requesting an update
        $requestKeywords = ['update', 'rewrite', 'improve', 'enhance', 'revise', 'create', 'write'];
        $lowerMessage = strtolower($userMessage);
        foreach ($requestKeywords as $keyword) {
            if (strpos($lowerMessage, $keyword) !== false) {
                $userRequestsUpdate = true;
                break;
            }
        }
        
        // Check if AI response contains update indicators
        $lowerResponse = strtolower($aiResponse);
        foreach ($updateKeywords as $keyword) {
            if (strpos($lowerResponse, $keyword) !== false) {
                $aiProvidesUpdate = true;
                break;
            }
        }
        
        // Also check if response is long enough to be a full description
        $wordCount = str_word_count($aiResponse);
        if ($wordCount > 50 && $userRequestsUpdate) {
            $aiProvidesUpdate = true;
        }
        
        return $userRequestsUpdate && $aiProvidesUpdate;
    }
}