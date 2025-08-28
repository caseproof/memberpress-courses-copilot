/**
 * Editor AI Integration - Add AI Button
 * 
 * Handles adding the AI button to the classic editor after the page title
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Add button after the page title
        function addEditorAIButton() {
            // Check if button already exists
            if ($('#' + mpccEditorAI.buttonId).length > 0) {
                return;
            }
            
            // Find the page title
            var $pageTitle = $('h1.wp-heading-inline').first();
            if ($pageTitle.length === 0) {
                return;
            }
            
            // Create the button styled like WordPress "Add New" buttons
            var $aiButton = $('<a href="#" id="' + mpccEditorAI.buttonId + '" class="page-title-action">' +
                '<span class="dashicons dashicons-lightbulb" style="margin: 3px 5px 0 -2px; font-size: 16px;"></span>' +
                mpccEditorAI.buttonText +
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
                    window.MPCCUtils.modalManager.open('#' + mpccEditorAI.modalId);
                } else {
                    $('#' + mpccEditorAI.modalId).fadeIn();
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

})(jQuery);