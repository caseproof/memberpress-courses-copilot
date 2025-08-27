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
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add button after the page title
            function addAIButtonToPage() {
                // Check if button already exists
                if ($('#mpcc-create-ai-button').length > 0) {
                    return;
                }
                
                // Find the page title
                var $pageTitle = $('h1.wp-heading-inline').first();
                if ($pageTitle.length === 0) {
                    return;
                }
                
                // Create the button styled like WordPress "Add New" buttons
                var $aiButton = $('<a href="#" id="mpcc-create-ai-button" class="page-title-action">' +
                    '<span class="dashicons dashicons-lightbulb" style="margin: 3px 5px 0 -2px; font-size: 16px;"></span>' +
                    'Create with AI' +
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
                        window.MPCCUtils.modalManager.open('#mpcc-ai-modal-overlay');
                    } else {
                        $('#mpcc-ai-modal-overlay').fadeIn();
                        $('body').css('overflow', 'hidden');
                    }
                    
                    // Focus on input
                    setTimeout(function() {
                        $('#mpcc-ai-input').focus();
                    }, 300);
                });
            }
            
            // Add button on page load
            addAIButtonToPage();
            
            // Also add button if page structure changes (for Gutenberg compatibility)
            var observer = new MutationObserver(function(mutations) {
                addAIButtonToPage();
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
                        
                        // No need for tab checking - button is always visible in header
                        
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
        
        /* Course modal quick-start buttons styling - scoped to modal only */
        #mpcc-ai-modal-overlay .mpcc-quickstart-section {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        #mpcc-ai-modal-overlay .mpcc-quickstart-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        #mpcc-ai-modal-overlay .mpcc-quickstart-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #f9f9f9;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            color: #2c3338;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }
        
        #mpcc-ai-modal-overlay .mpcc-quickstart-btn:hover {
            background: #6B4CE6;
            border-color: #6B4CE6;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(107, 76, 230, 0.3);
        }
        
        #mpcc-ai-modal-overlay .mpcc-quickstart-btn:focus {
            outline: 2px solid #6B4CE6;
            outline-offset: 2px;
        }
        
        #mpcc-ai-modal-overlay .mpcc-quickstart-btn .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }
        
        #mpcc-ai-modal-overlay .mpcc-quickstart-btn.mpcc-quickstart-active {
            background: #5A3CC5 !important;
            border-color: #5A3CC5 !important;
            color: white !important;
            transform: translateY(1px) !important;
        }
        
        /* Responsive design for modal quick-start buttons */
        @media (max-width: 600px) {
            #mpcc-ai-modal-overlay .mpcc-quickstart-buttons {
                gap: 6px;
            }
            
            #mpcc-ai-modal-overlay .mpcc-quickstart-btn {
                padding: 6px 8px;
                font-size: 11px;
            }
            
            #mpcc-ai-modal-overlay .mpcc-quickstart-btn .dashicons {
                font-size: 12px;
                width: 12px;
                height: 12px;
            }
        }
        
        @media (max-width: 480px) {
            #mpcc-ai-modal-overlay .mpcc-quickstart-btn {
                flex: 1 1 45%;
                justify-content: center;
                min-width: 0;
            }
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
                        <!-- Quick-start buttons section -->
                        <div class="mpcc-quickstart-section" style="margin-bottom: 15px;">
                            <div class="mpcc-quickstart-label" style="font-size: 12px; color: #646970; margin-bottom: 8px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">
                                Quick Start Prompts
                            </div>
                            <div class="mpcc-quickstart-buttons" style="display: flex; flex-wrap: wrap; gap: 8px;">
                                <button type="button" class="mpcc-quickstart-btn" data-prompt="Write a compelling course description that highlights the key benefits and learning outcomes for students.">
                                    <span class="dashicons dashicons-edit-large"></span>
                                    Course Description
                                </button>
                                <button type="button" class="mpcc-quickstart-btn" data-prompt="Create clear and specific learning objectives that describe what students will be able to do after completing this course.">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    Learning Objectives
                                </button>
                                <button type="button" class="mpcc-quickstart-btn" data-prompt="Improve the course overview to better communicate the value proposition and attract potential students.">
                                    <span class="dashicons dashicons-visibility"></span>
                                    Improve Overview
                                </button>
                                <button type="button" class="mpcc-quickstart-btn" data-prompt="Add specific benefits and outcomes students will gain from taking this course, focusing on practical results.">
                                    <span class="dashicons dashicons-awards"></span>
                                    Benefits & Outcomes
                                </button>
                                <button type="button" class="mpcc-quickstart-btn" data-prompt="Write clear course prerequisites that help students understand if this course is right for their skill level.">
                                    <span class="dashicons dashicons-list-view"></span>
                                    Prerequisites
                                </button>
                                <button type="button" class="mpcc-quickstart-btn" data-prompt="Create a compelling call-to-action that motivates students to enroll in this course.">
                                    <span class="dashicons dashicons-megaphone"></span>
                                    Call-to-Action
                                </button>
                            </div>
                        </div>
                        
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
                            
                            // Debug: Log the raw AI response
                            console.log('MPCC: Raw AI response:', messageText);
                            console.log('MPCC: Has content update:', hasContentUpdate);
                            
                            // Check if the message contains markdown content tags
                            var contentMatch = messageText.match(/\[COURSE_CONTENT\]([\s\S]*?)\[\/COURSE_CONTENT\]/);
                            var displayText = messageText;
                            
                            if (contentMatch) {
                                // Format the markdown content for display
                                var markdownContent = contentMatch[1].trim();
                                var htmlContent = markdownToHtml(markdownContent);
                                displayText = '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 10px 0;">' +
                                    '<strong style="display: block; margin-bottom: 10px;">Course Description:</strong>' +
                                    htmlContent +
                                    '</div>';
                            } else {
                                // Regular message formatting
                                displayText = messageText.replace(/\n/g, '<br>');
                            }
                            
                            var aiMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                                '<strong>AI Assistant:</strong> <div class="ai-content">' + displayText + '</div></div>';
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
            
            // Handle quick-start button clicks - scoped to course modal only
            $(document).on('click', '#mpcc-ai-modal-overlay .mpcc-quickstart-btn', function(e) {
                e.preventDefault();
                
                var prompt = $(this).data('prompt');
                var $input = $('#mpcc-ai-input');
                
                // Set the prompt text
                $input.val(prompt);
                
                // Focus the input and place cursor at end
                $input.focus();
                var inputElement = $input[0];
                if (inputElement.setSelectionRange) {
                    var length = prompt.length;
                    inputElement.setSelectionRange(length, length);
                } else if (inputElement.createTextRange) {
                    var range = inputElement.createTextRange();
                    range.collapse(true);
                    range.moveEnd('character', prompt.length);
                    range.moveStart('character', prompt.length);
                    range.select();
                }
                
                // Optional: Add visual feedback
                $(this).addClass('mpcc-quickstart-active');
                setTimeout(function() {
                    $('#mpcc-ai-modal-overlay .mpcc-quickstart-btn').removeClass('mpcc-quickstart-active');
                }, 200);
            });
            
            // Handle apply content button
            $(document).on('click', '.mpcc-apply-content', function() {
                var $button = $(this);
                $button.prop('disabled', true).text('Applying...');
                
                // Get the AI-generated content
                var $aiMessage = $(this).closest('.mpcc-content-update-buttons').prev('.mpcc-ai-message').find('.ai-content');
                var fullContent = $aiMessage.text(); // Use .text() to get raw content without HTML
                
                console.log('MPCC: Extracting content from:', fullContent);
                
                var courseContent = '';
                
                // Look for content between [COURSE_CONTENT] tags
                var contentMatch = fullContent.match(/\[COURSE_CONTENT\]([\s\S]*?)\[\/COURSE_CONTENT\]/);
                
                if (contentMatch && contentMatch[1]) {
                    // Found markdown content
                    var markdownContent = contentMatch[1].trim();
                    console.log('MPCC: Found markdown content:', markdownContent);
                    
                    // Convert markdown to HTML
                    courseContent = markdownToHtml(markdownContent);
                    console.log('MPCC: Converted to HTML:', courseContent);
                } else {
                    // Fallback: use the full content if no tags found
                    console.log('MPCC: No [COURSE_CONTENT] tags found, using full content');
                    courseContent = $aiMessage.html()
                        .replace(/<br\s*\/?>/gi, '\n')
                        .replace(/\n{3,}/g, '\n\n')
                        .trim();
                }
                    
                console.log('MPCC: Final content to apply (length: ' + courseContent.length + '):', courseContent);
                
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
                var $aiMessage = $(this).closest('.mpcc-content-update-buttons').prev('.mpcc-ai-message').find('.ai-content');
                var fullContent = $aiMessage.text();
                var contentToCopy = '';
                
                // Look for markdown content
                var contentMatch = fullContent.match(/\[COURSE_CONTENT\]([\s\S]*?)\[\/COURSE_CONTENT\]/);
                
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
    private function detectContentUpdate(string $userMessage, string $aiResponse): bool
    {
        // Check if AI response contains the [COURSE_CONTENT] tags
        if (strpos($aiResponse, '[COURSE_CONTENT]') !== false && 
            strpos($aiResponse, '[/COURSE_CONTENT]') !== false) {
            return true;
        }
        
        // Fallback: Check if user is requesting an update and response seems substantial
        $requestKeywords = ['update', 'rewrite', 'improve', 'enhance', 'revise', 'create', 'write'];
        $userRequestsUpdate = false;
        
        $lowerMessage = strtolower($userMessage);
        foreach ($requestKeywords as $keyword) {
            if (strpos($lowerMessage, $keyword) !== false) {
                $userRequestsUpdate = true;
                break;
            }
        }
        
        // If user requested update and response is substantial, consider it an update
        $wordCount = str_word_count($aiResponse);
        if ($userRequestsUpdate && $wordCount > 100) {
            return true;
        }
        
        return false;
    }
}