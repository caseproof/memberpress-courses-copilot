/**
 * Course Integration - AI Assistant Metabox
 * 
 * Handles the AI Assistant metabox functionality in the course editor
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Cache DOM elements
        var $toggleButton = $('#mpcc-toggle-ai-chat');
        var $container = null; // Lazy load when needed
        
        // Use event delegation and lazy loading
        $toggleButton.off('click.toggle-ai').on('click.toggle-ai', function() {
            // Lazy load container reference
            if (!$container) {
                $container = $('#mpcc-ai-chat-container');
            }
            var $button = $(this);
            
            if ($container.is(':visible')) {
                $container.slideUp();
                $button.find('.dashicons').removeClass('dashicons-format-chat').addClass('dashicons-format-chat');
                $button.find('span:not(.dashicons)').text(mpccMetabox.strings.openAIChat);
            } else {
                $container.slideDown();
                $button.find('.dashicons').removeClass('dashicons-format-chat').addClass('dashicons-dismiss');
                $button.find('span:not(.dashicons)').text(mpccMetabox.strings.closeAIChat);
                
                // Load AI interface if not already loaded - debounce to prevent rapid clicks
                if (!$container.data('loaded') && !$container.data('loading')) {
                    $container.data('loading', true);
                    MPCCUtils.ajax.request('mpcc_load_ai_interface', {
                        context: 'course_editing',
                        post_id: mpccMetabox.postId,
                        nonce: mpccMetabox.nonce
                    }, {
                        success: function(response) {
                            if (response.success) {
                                $container.html(response.data.html).data('loaded', true).data('loading', false);
                                // The interface will initialize itself when ready
                            } else {
                                $container.html('<div role="alert" style="text-align: center; color: #d63638;"><p>' + MPCCUtils.escapeHtml(response.data || mpccMetabox.strings.failedToLoad) + '</p></div>').data('loading', false);
                                if (window.MPCCAccessibility) {
                                    MPCCAccessibility.announce('Failed to load AI chat: ' + (response.data || mpccMetabox.strings.failedToLoad), 'assertive');
                                }
                            }
                        },
                        error: function() {
                            $container.html('<div role="alert" style="text-align: center; color: #d63638;"><p>' + MPCCUtils.escapeHtml(mpccMetabox.strings.failedToLoad) + '</p></div>').data('loading', false);
                            if (window.MPCCAccessibility) {
                                MPCCAccessibility.announce('Failed to load AI chat: ' + mpccMetabox.strings.failedToLoad, 'assertive');
                            }
                        }
                    });
                }
            }
        });
    });
    
    // Cleanup function
    window.MPCCMetaboxAI = {
        destroy: function() {
            $('#mpcc-toggle-ai-chat').off('click.toggle-ai');
        }
    };

})(jQuery);