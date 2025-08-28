/**
 * Editor AI Integration - Modal Functionality
 * 
 * Handles the AI modal interaction for course and lesson editing
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        var postType = mpccEditorModal.postType;
        var contentTag = mpccEditorModal.contentTag;
        var modalId = '#' + mpccEditorModal.modalId;
        
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
            var contextData = mpccEditorModal.contextData;
            
            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: mpccEditorModal.ajaxAction,
                    nonce: $('#mpcc_editor_ai_nonce').val(),
                    message: message,
                    post_id: mpccEditorModal.postId,
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
                                '<p style="margin: 0 0 10px 0; font-weight: bold;">Apply this content to your ' + (postType === 'mpcs-lesson' ? 'lesson' : 'course') + '?</p>' +
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
                    
                    MPCCUtils.ui.scrollToBottom('#mpcc-editor-ai-messages');
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
                    action: mpccEditorModal.updateAction,
                    nonce: $('#mpcc_editor_ai_nonce').val(),
                    post_id: mpccEditorModal.postId,
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
                                    id: mpccEditorModal.postId,
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

})(jQuery);