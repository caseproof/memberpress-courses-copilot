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
        $('#mpcc-toggle-ai-chat').on('click', function() {
            var $container = $('#mpcc-ai-chat-container');
            var $button = $(this);
            
            if ($container.is(':visible')) {
                $container.slideUp();
                $button.find('.dashicons').removeClass('dashicons-format-chat').addClass('dashicons-format-chat');
                $button.find('span:not(.dashicons)').text(mpccMetabox.strings.openAIChat);
            } else {
                $container.slideDown();
                $button.find('.dashicons').removeClass('dashicons-format-chat').addClass('dashicons-dismiss');
                $button.find('span:not(.dashicons)').text(mpccMetabox.strings.closeAIChat);
                
                // Load AI interface if not already loaded
                if (!$container.data('loaded')) {
                    MPCCUtils.ajax.request('mpcc_load_ai_interface', {
                        context: 'course_editing',
                        post_id: mpccMetabox.postId,
                        nonce: mpccMetabox.nonce
                    }, {
                        success: function(response) {
                            if (response.success) {
                                $('#mpcc-ai-chat-container').html(response.data.html).data('loaded', true);
                                // The interface will initialize itself when ready
                            } else {
                                $('#mpcc-ai-chat-container').html('<div style="text-align: center; color: #d63638;"><p>' + MPCCUtils.escapeHtml(response.data || mpccMetabox.strings.failedToLoad) + '</p></div>');
                            }
                        },
                        error: function() {
                            $('#mpcc-ai-chat-container').html('<div style="text-align: center; color: #d63638;"><p>' + MPCCUtils.escapeHtml(mpccMetabox.strings.failedToLoad) + '</p></div>');
                        }
                    });
                }
            }
        });
    });

})(jQuery);