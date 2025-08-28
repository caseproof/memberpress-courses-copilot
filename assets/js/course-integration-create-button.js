/**
 * Course Integration - Create with AI Button
 * 
 * Handles the "Create with AI" button functionality on the courses listing page
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Add "Create with AI" button next to "Add New Course"
        var createWithAIButton = '<a href="#" id="mpcc-create-with-ai" class="page-title-action">' + 
            '<span class="dashicons dashicons-admin-generic"></span>' +
            mpccCreateButton.strings.createWithAI +
            '</a>';
        
        $('.wrap .wp-header-end').before(createWithAIButton);
        
        // Handle click event - redirect to standalone page
        $('#mpcc-create-with-ai').on('click', function(e) {
            e.preventDefault();
            // Redirect to standalone AI Course Editor page
            window.location.href = mpccCreateButton.editorUrl;
        });
    });

})(jQuery);