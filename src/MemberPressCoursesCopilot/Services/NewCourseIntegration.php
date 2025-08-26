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
                        <strong>AI Assistant:</strong> Hi! I can help you improve your course structure and suggest content. I can:
                        <br>• Suggest course outlines and lesson topics
                        <br>• Help plan your curriculum structure  
                        <br>• Provide content ideas and learning objectives
                        <br>• Guide you on best practices
                        <br><br><em>Note: To actually add content, you'll use the Curriculum tab above for sections and the Lessons menu for creating individual lessons.</em>
                        <br><br>What would you like to work on?
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
                            var hasAction = messageText.includes('[ACTION_REQUIRED:');
                            
                            // Remove the action tag from display
                            messageText = messageText.replace(/\[ACTION_REQUIRED:.*?\]/g, '');
                            
                            var aiMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                                '<strong>AI Assistant:</strong> ' + messageText + '</div>';
                            $('#mpcc-ai-messages').append(aiMsg);
                            
                            // If action is required, show implementation options
                            if (hasAction) {
                                var actionButtons = '<div class="mpcc-action-buttons" style="margin: 10px 0; padding: 10px; background: #fff8dc; border: 1px solid #ffd700; border-radius: 4px;">' +
                                    '<p style="margin: 0 0 10px 0; font-weight: bold;">Would you like me to help implement these changes?</p>' +
                                    '<button type="button" class="button button-primary mpcc-implement-changes" style="margin-right: 5px;">Yes, Show Me How</button>' +
                                    '<button type="button" class="button mpcc-cancel-action">No Thanks</button>' +
                                    '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">Note: I\'ll provide step-by-step instructions to update your course.</p>' +
                                '</div>';
                                $('#mpcc-ai-messages').append(actionButtons);
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
            
            // Handle action button clicks
            $(document).on('click', '.mpcc-implement-changes', function() {
                $(this).prop('disabled', true);
                var $container = $(this).parent();
                
                // Show implementation guide
                var guide = '<div class="mpcc-implementation-guide" style="margin-top: 10px; padding: 10px; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 4px;">' +
                    '<h4 style="margin: 0 0 10px 0;">How to implement these changes:</h4>' +
                    '<ol style="margin: 0; padding-left: 20px;">' +
                    '<li><strong>For the Course Structure:</strong> Use the "Curriculum" tab above to add/edit sections</li>' +
                    '<li><strong>For Individual Lessons:</strong> Go to Courses → Lessons to create new lesson content</li>' +
                    '<li><strong>Quick Actions:</strong>' +
                    '<ul style="margin: 5px 0;">' +
                    '<li><a href="' + window.location.href + '#mpcs-curriculum" style="text-decoration: underline;">Go to Curriculum Tab</a> to manage sections</li>' +
                    '<li><a href="<?php echo admin_url('edit.php?post_type=mpcs-lesson'); ?>" target="_blank" style="text-decoration: underline;">Open Lessons Manager</a> in new tab</li>' +
                    '<li><a href="<?php echo admin_url('post-new.php?post_type=mpcs-lesson'); ?>" target="_blank" style="text-decoration: underline;">Create New Lesson</a> in new tab</li>' +
                    '</ul></li>' +
                    '<li><strong>Pro Tip:</strong> Copy the AI suggestions above and use them as a checklist while creating content</li>' +
                    '</ol>' +
                    '<button type="button" class="button mpcc-copy-suggestions" style="margin-top: 10px;">Copy AI Suggestions</button>' +
                    '</div>';
                
                $container.html(guide);
            });
            
            $(document).on('click', '.mpcc-cancel-action', function() {
                $(this).parent().remove();
            });
            
            $(document).on('click', '.mpcc-copy-suggestions', function() {
                // Find the last AI message
                var lastAIMessage = $('.mpcc-ai-message:has(strong:contains("AI Assistant"))').last().text();
                lastAIMessage = lastAIMessage.replace('AI Assistant:', '').trim();
                
                // Create temporary textarea to copy
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(lastAIMessage).select();
                document.execCommand('copy');
                $temp.remove();
                
                $(this).text('Copied!').prop('disabled', true);
                setTimeout(() => {
                    $(this).text('Copy AI Suggestions').prop('disabled', false);
                }, 2000);
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