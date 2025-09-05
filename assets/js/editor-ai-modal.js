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

    // Lazy load the modal functionality
    function initializeModal() {
        var postType = mpccEditorModal.postType;
        var contentTag = mpccEditorModal.contentTag;
        var modalId = '#' + mpccEditorModal.modalId;
        
        // Cache commonly used DOM elements
        var $modal = $(modalId);
        var $input = $('#mpcc-editor-ai-input');
        var $messages = $('#mpcc-editor-ai-messages');
        var $sendButton = $('#mpcc-editor-ai-send');
        
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
        
        // Enhance modal for accessibility
        if (window.MPCCAccessibility) {
            const $modal = $(modalId);
            MPCCAccessibility.enhanceModal($modal, {
                labelledby: 'mpcc-editor-ai-title',
                describedby: 'mpcc-editor-ai-description',
                closeLabel: 'Close AI assistant dialog'
            });
        }
        
        // Close modal using existing modal manager - use event delegation
        $modal.off('click.modal-close').on('click.modal-close', '.mpcc-modal-close', function() {
            if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                window.MPCCUtils.modalManager.close(modalId);
            } else {
                $(modalId).fadeOut();
                $('body').css('overflow', '');
            }
        });
        
        // Close on overlay click
        $modal.off('click.overlay').on('click.overlay', function(e) {
            if (e.target === this) {
                if (window.MPCCUtils && window.MPCCUtils.modalManager) {
                    window.MPCCUtils.modalManager.close(modalId);
                } else {
                    $(this).fadeOut();
                    $('body').css('overflow', '');
                }
            }
        });
        
        // Handle quick-start button clicks - use event delegation
        $modal.off('click.quick-start').on('click.quick-start', '.mpcc-quick-start-btn', function() {
            var prompt = $(this).data('prompt');
            
            // Set the prompt text
            $input.val(prompt);
            
            // Focus the input field
            $input.focus();
            
            // Optional: Scroll to input area
            $input[0].scrollIntoView({ 
                behavior: 'smooth', 
                block: 'nearest' 
            });
            
            // Auto-resize the textarea if needed
            $input.css('height', 'auto');
            $input.css('height', $input[0].scrollHeight + 'px');
            
            // Announce to screen readers
            if (window.MPCCAccessibility) {
                MPCCAccessibility.announce('Prompt loaded: ' + prompt);
            }
        });
        
        // Enhance quick-start buttons for accessibility
        $(modalId + ' .mpcc-quick-start-btn').each(function() {
            const $btn = $(this);
            const prompt = $btn.data('prompt');
            $btn.attr({
                'aria-label': 'Use prompt: ' + prompt,
                'role': 'button'
            });
        });
        
        // Handle send message with throttling to prevent rapid submissions
        var isSending = false;
        $sendButton.off('click.send').on('click.send', function() {
            if (isSending) return;
            
            var message = $input.val().trim();
            
            if (!message) {
                if (window.MPCCToast) {
                    window.MPCCToast.error('Please enter a message');
                } else {
                    console.error('Please enter a message');
                }
                return;
            }
            
            // Add user message to chat
            var userMsg = '<div class="mpcc-ai-message" role="article" aria-label="Your message" style="margin-bottom: 10px; padding: 8px; background: #f0f0f0; border-radius: 4px; text-align: right;">' +
                '<strong>You:</strong> ' + $('<div>').text(message).html() + '</div>';
            $('#mpcc-editor-ai-messages').append(userMsg);
            
            // Announce message sent
            if (window.MPCCAccessibility) {
                MPCCAccessibility.announce('Message sent: ' + message);
            }
            
            // Clear input
            $input.val('');
            
            // Scroll to bottom
            var messages = $('#mpcc-editor-ai-messages');
            // Use requestAnimationFrame to ensure DOM has been rendered
            requestAnimationFrame(() => {
                messages.scrollTop(messages[0].scrollHeight);
            });
            
            // Show typing indicator using utility function
            var typingId = MPCCUtils.ui.addTypingIndicator($messages);
            isSending = true;
            
            // Set aria-busy on messages container and announce generation start
            if (window.MPCCAccessibility) {
                MPCCAccessibility.setARIA('#mpcc-editor-ai-messages', {'aria-busy': 'true'});
                MPCCAccessibility.announce('Starting AI generation. Processing your request.');
            }
            
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
                    MPCCUtils.ui.removeTypingIndicator(typingId);
                    isSending = false;
                    
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
                        
                        var aiMsg = '<div class="mpcc-ai-message" role="article" aria-label="AI Assistant message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                            '<strong>AI Assistant:</strong> <div class="ai-content">' + displayText + '</div></div>';
                        $('#mpcc-editor-ai-messages').append(aiMsg);
                        
                        // Announce AI response
                        if (window.MPCCAccessibility) {
                            MPCCAccessibility.announce('AI generation complete. Response received successfully.');
                            MPCCAccessibility.setARIA('#mpcc-editor-ai-messages', {'aria-busy': 'false'});
                        }
                        
                        // If content update is provided, show apply button
                        if (hasContentUpdate) {
                            var applyButtons = '<div class="mpcc-editor-content-update-buttons" role="group" aria-label="Content update actions" style="margin: 10px 0; padding: 10px; background: #e8f5e9; border: 1px solid #4caf50; border-radius: 4px;">' +
                                '<p style="margin: 0 0 10px 0; font-weight: bold;">Apply this content to your ' + (postType === 'mpcs-lesson' ? 'lesson' : 'course') + '?</p>' +
                                '<button type="button" class="button button-primary mpcc-apply-editor-content" aria-label="Apply AI-generated content to editor" style="margin-right: 5px;">Apply Content</button>' +
                                '<button type="button" class="button mpcc-copy-editor-content" aria-label="Copy AI-generated content to clipboard" style="margin-right: 5px;">Copy to Clipboard</button>' +
                                '<button type="button" class="button mpcc-cancel-editor-update" aria-label="Cancel content update" >Cancel</button>' +
                                '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">This will update your content in the editor.</p>' +
                            '</div>';
                            $('#mpcc-editor-ai-messages').append(applyButtons);
                            
                            // Announce action available
                            if (window.MPCCAccessibility) {
                                MPCCAccessibility.announce('AI has generated content. You can apply it to your editor or copy it to clipboard.');
                            }
                        }
                    } else {
                        var errorText = response.data || 'Failed to get AI response';
                        var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                            '<strong>Error:</strong> ' + errorText + '</div>';
                        $('#mpcc-editor-ai-messages').append(errorMsg);
                        
                        // Announce error
                        if (window.MPCCAccessibility) {
                            MPCCAccessibility.announce('Error during AI generation: ' + errorText);
                            MPCCAccessibility.setARIA('#mpcc-editor-ai-messages', {'aria-busy': 'false'});
                        }
                    }
                    
                    MPCCUtils.ui.scrollToBottom($messages);
                },
                error: function() {
                    MPCCUtils.ui.removeTypingIndicator(typingId);
                    isSending = false;
                    var errorText = 'Network error. Please try again.';
                    var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                        '<strong>Error:</strong> ' + errorText + '</div>';
                    $('#mpcc-editor-ai-messages').append(errorMsg);
                    
                    // Announce error
                    if (window.MPCCAccessibility) {
                        MPCCAccessibility.announce('Error during AI generation: ' + errorText);
                        MPCCAccessibility.setARIA('#mpcc-editor-ai-messages', {'aria-busy': 'false'});
                    }
                    
                    MPCCUtils.ui.scrollToBottom('#mpcc-editor-ai-messages');
                }
            });
        });
        
        // Handle Enter key
        $input.off('keypress.send').on('keypress.send', function(e) {
            if (e.which === 13 && !e.shiftKey) {
                e.preventDefault();
                $sendButton.click();
            }
        });
        
        // Enhance input field for accessibility
        $('#mpcc-editor-ai-input').attr({
            'aria-label': 'Type your message to AI Assistant',
            'placeholder': 'Ask the AI Assistant for help with your content...'
        });
        
        // Enhance send button
        $('#mpcc-editor-ai-send').attr({
            'aria-label': 'Send message to AI Assistant'
        });
        
        // Enhance messages area
        $('#mpcc-editor-ai-messages').attr({
            'role': 'log',
            'aria-label': 'AI conversation history',
            'aria-live': 'polite',
            'tabindex': '0'
        });
        
        // Handle apply content button - use event delegation
        $(document).off('click.apply-content').on('click.apply-content', '.mpcc-apply-editor-content', function() {
            var $button = $(this);
            $button.prop('disabled', true).text('Applying...');
            
            // Announce applying content
            if (window.MPCCAccessibility) {
                MPCCAccessibility.announce('Applying AI-generated content to editor.');
            }
            
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
                        
                        // Announce success
                        if (window.MPCCAccessibility) {
                            MPCCAccessibility.announce('Content applied successfully to editor.');
                        }
                        
                        setTimeout(function() {
                            $('.mpcc-editor-content-update-buttons').fadeOut();
                        }, 2000);
                    } else {
                        $button.text('Failed').addClass('button-disabled');
                        var errorText = response.data || 'Failed to update content';
                        
                        // Announce error
                        if (window.MPCCAccessibility) {
                            MPCCAccessibility.announce('Error applying content: ' + errorText);
                        }
                        
                        if (window.MPCCToast) {
                            window.MPCCToast.error('Error: ' + errorText);
                        } else {
                            console.error('Error: ' + errorText);
                        }
                    }
                },
                error: function() {
                    $button.text('Failed').addClass('button-disabled');
                    var errorText = 'Network error. Please try again.';
                    
                    // Announce error
                    if (window.MPCCAccessibility) {
                        MPCCAccessibility.announce('Error applying content: ' + errorText);
                    }
                    
                    if (window.MPCCToast) {
                        window.MPCCToast.error(errorText);
                    } else {
                        console.error(errorText);
                    }
                }
            });
        });
        
        // Handle copy content button - use event delegation
        $(document).off('click.copy-content').on('click.copy-content', '.mpcc-copy-editor-content', function() {
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
        
        // Handle cancel button - use event delegation
        $(document).off('click.cancel-update').on('click.cancel-update', '.mpcc-cancel-editor-update', function() {
            $(this).closest('.mpcc-editor-content-update-buttons').fadeOut();
        });
    }
    
    // Initialize modal on demand when needed
    $(document).ready(function() {
        // Check if modal exists and should be initialized
        if ($('#' + mpccEditorModal.modalId).length) {
            // Defer initialization until modal is first opened
            var isInitialized = false;
            $(document).on('mpcc:modal-opened', function(e, data) {
                if (!isInitialized && data.modal === '#' + mpccEditorModal.modalId) {
                    isInitialized = true;
                    initializeModal();
                }
            });
            
            // If modal is already visible, initialize immediately
            if ($('#' + mpccEditorModal.modalId).is(':visible')) {
                isInitialized = true;
                initializeModal();
            }
        }
    });
    
    // Cleanup function
    window.MPCCEditorModal = {
        destroy: function() {
            var modalId = '#' + mpccEditorModal.modalId;
            var $modal = $(modalId);
            
            // Remove all event handlers
            $modal.off('click.modal-close');
            $modal.off('click.overlay');
            $modal.off('click.quick-start');
            $('#mpcc-editor-ai-send').off('click.send');
            $('#mpcc-editor-ai-input').off('keypress.send');
            $(document).off('click.apply-content');
            $(document).off('click.copy-content');
            $(document).off('click.cancel-update');
        }
    };
    
    // Cleanup on page unload
    $(window).on('beforeunload.mpcc-editor-modal', function() {
        if (window.MPCCEditorModal && window.MPCCEditorModal.destroy) {
            window.MPCCEditorModal.destroy();
        }
    });

})(jQuery);