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
        // Add metabox to course edit pages
        add_action('add_meta_boxes', [$this, 'addAIChatMetabox']);
    }

    /**
     * Add AI Chat metabox to course edit pages
     */
    public function addAIChatMetabox(): void
    {
        global $post;
        
        // Only add to course post type
        if (!$post || $post->post_type !== 'mpcs-course') {
            return;
        }

        add_meta_box(
            'mpcc-ai-chat-metabox',
            'AI Course Assistant',
            [$this, 'renderAIChatMetabox'],
            'mpcs-course',
            'side',
            'high'
        );
    }

    /**
     * Render AI Chat metabox content
     */
    public function renderAIChatMetabox(\WP_Post $post): void
    {
        // Add nonce for security
        NonceConstants::field(NonceConstants::AI_ASSISTANT, 'mpcc_ai_nonce');
        
        ?>
        <div id="mpcc-new-ai-chat" style="padding: 10px;">
            <p><strong>AI Course Assistant</strong></p>
            <p>Get help with your course content, structure, and lessons.</p>
            
            <button type="button" id="mpcc-open-ai-chat" class="button button-primary" style="width: 100%; margin-bottom: 10px;">
                <span class="dashicons dashicons-format-chat"></span>
                Open AI Chat
            </button>
            
            <div id="mpcc-ai-chat-container" style="display: none; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                <div id="mpcc-ai-messages" style="height: 300px; overflow-y: auto; padding: 10px; background: white; border-bottom: 1px solid #ddd;">
                    <div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">
                        <strong>AI Assistant:</strong> Hi! I'm here to help you improve your course overview and description. I can:
                        <br>• <strong>Update your course description</strong> - Just ask me to rewrite or enhance it
                        <br>• Provide compelling content that attracts students
                        <br>• Suggest improvements to your course overview
                        <br>• Help you highlight key benefits and learning outcomes
                        <br><br><em>Note: I focus on the main course content. For lessons and curriculum structure, use the Curriculum tab above.</em>
                        <br><br>Would you like me to enhance your course description?
                    </div>
                </div>
                
                <div style="padding: 10px;">
                    <textarea id="mpcc-ai-input" 
                              placeholder="Ask me anything about your course..." 
                              style="width: 100%; height: 60px; border: 1px solid #ddd; border-radius: 3px; padding: 5px; resize: vertical;"></textarea>
                    <button type="button" id="mpcc-ai-send" class="button button-primary" style="margin-top: 5px; width: 100%;">
                        Send Message
                    </button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('MPCC: New AI Chat metabox loaded');
            
            // Toggle chat visibility
            $('#mpcc-open-ai-chat').on('click', function() {
                var container = $('#mpcc-ai-chat-container');
                var button = $(this);
                
                if (container.is(':visible')) {
                    container.slideUp();
                    button.html('<span class="dashicons dashicons-format-chat"></span> Open AI Chat');
                } else {
                    container.slideDown();
                    button.html('<span class="dashicons dashicons-dismiss"></span> Close AI Chat');
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
                var aiContent = $(this).closest('.mpcc-content-update-buttons').prev('.mpcc-ai-message').find('.ai-content').html();
                // Convert br tags back to newlines
                aiContent = aiContent.replace(/<br\s*\/?>/gi, '\n');
                
                // Update the course content via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_update_course_content',
                        nonce: $('#mpcc_ai_nonce').val(),
                        post_id: <?php echo $post->ID; ?>,
                        content: aiContent
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update the WordPress editor if it exists
                            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                                // For block editor
                                wp.data.dispatch('core/editor').editPost({content: aiContent});
                            } else if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                                // For classic editor
                                tinyMCE.get('content').setContent(aiContent);
                            } else if ($('#content').length) {
                                // For text editor
                                $('#content').val(aiContent);
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
}