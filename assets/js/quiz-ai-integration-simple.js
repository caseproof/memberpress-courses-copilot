/**
 * MemberPress Courses Copilot - Quiz AI Integration (Simple Version)
 * Adds button below the title for better visibility
 *
 * @package MemberPressCoursesCopilot
 * @version 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('MPCC Quiz AI Simple: Initializing...');
        
        // Only run on quiz pages
        if (!$('body').hasClass('post-type-mpcs-quiz')) {
            console.log('MPCC Quiz AI Simple: Not on quiz page');
            return;
        }
        
        // Wait a bit for the editor to fully load
        setTimeout(function() {
            addQuizAIButton();
        }, 2000);
    });
    
    function addQuizAIButton() {
        console.log('MPCC Quiz AI Simple: Adding button...');
        
        // Check if button already exists
        if ($('#mpcc-quiz-ai-panel').length > 0) {
            console.log('MPCC Quiz AI Simple: Button already exists');
            return;
        }
        
        // Create a panel below the title
        const panel = `
            <div id="mpcc-quiz-ai-panel" style="
                background: #f0f0f1;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 16px;
                margin: 20px 20px 20px 20px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            ">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span class="dashicons dashicons-lightbulb" style="font-size: 20px; color: #6B4CE6;"></span>
                    <div>
                        <strong style="font-size: 14px;">AI Quiz Generator</strong>
                        <p style="margin: 4px 0 0 0; color: #646970; font-size: 13px;">
                            Generate multiple-choice questions from lesson content
                        </p>
                    </div>
                </div>
                <button id="mpcc-generate-quiz-simple" class="button button-primary" type="button">
                    <span class="dashicons dashicons-sparkles" style="margin-right: 4px;"></span>
                    Generate Quiz Questions
                </button>
            </div>
        `;
        
        // Try multiple insertion points
        const insertionPoints = [
            '.editor-header',
            '.interface-interface-skeleton__header',
            '.edit-post-header',
            '.editor-post-title',
            '.block-editor-block-list__layout'
        ];
        
        let inserted = false;
        for (const selector of insertionPoints) {
            const $element = $(selector).first();
            if ($element.length > 0) {
                console.log('MPCC Quiz AI Simple: Inserting after', selector);
                $element.after(panel);
                inserted = true;
                break;
            }
        }
        
        // If no good insertion point found, prepend to the main editor
        if (!inserted) {
            const $editorArea = $('.edit-post-layout__content, .editor-styles-wrapper, #editor').first();
            if ($editorArea.length > 0) {
                console.log('MPCC Quiz AI Simple: Prepending to editor area');
                $editorArea.prepend(panel);
                inserted = true;
            }
        }
        
        if (!inserted) {
            console.error('MPCC Quiz AI Simple: Could not find insertion point');
            return;
        }
        
        // Bind click handler
        $('#mpcc-generate-quiz-simple').on('click', function() {
            generateQuiz();
        });
    }
    
    function generateQuiz() {
        console.log('MPCC Quiz AI Simple: Generate button clicked');
        
        const $button = $('#mpcc-generate-quiz-simple');
        const originalText = $button.html();
        
        // Show loading state
        $button.prop('disabled', true);
        $button.html('<span class="dashicons dashicons-update spin"></span> Generating...');
        
        // Get lesson ID - for now, we'll prompt the user
        const lessonId = prompt('Please enter the lesson ID to generate quiz from:');
        
        if (!lessonId) {
            $button.prop('disabled', false);
            $button.html(originalText);
            return;
        }
        
        // Make AJAX request
        $.ajax({
            url: mpcc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'mpcc_generate_quiz',
                lesson_id: parseInt(lessonId),
                nonce: mpcc_ajax.nonce,
                options: {
                    num_questions: 10
                }
            },
            success: function(response) {
                console.log('MPCC Quiz AI Simple: Response', response);
                
                if (response.success && response.data.questions) {
                    displayGeneratedQuestions(response.data.questions);
                    showNotice('Quiz questions generated successfully! Copy and paste them into the editor.', 'success');
                } else {
                    showNotice(response.data?.message || 'Error generating questions', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('MPCC Quiz AI Simple: Error', error);
                showNotice('Error: ' + error, 'error');
            },
            complete: function() {
                $button.prop('disabled', false);
                $button.html(originalText);
            }
        });
    }
    
    function displayGeneratedQuestions(questions) {
        console.log('MPCC Quiz AI Simple: Displaying questions', questions);
        
        // Create a modal-like display
        const questionsHtml = questions.map((q, i) => `
            <div style="margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                <h4 style="margin-top: 0;">Question ${i + 1}</h4>
                <p><strong>${q.question || q.text}</strong></p>
                <ul style="list-style-type: none; padding-left: 0;">
                    ${Object.entries(q.options).map(([key, value]) => `
                        <li style="padding: 4px 0;">
                            ${key === q.correct_answer ? '✓' : '○'} ${key}) ${value}
                        </li>
                    `).join('')}
                </ul>
                ${q.explanation ? `<p><em>Explanation: ${q.explanation}</em></p>` : ''}
            </div>
        `).join('');
        
        const modal = `
            <div id="mpcc-quiz-results" style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                border: 1px solid #ccc;
                border-radius: 8px;
                padding: 20px;
                max-width: 600px;
                max-height: 80vh;
                overflow-y: auto;
                z-index: 100000;
                box-shadow: 0 0 20px rgba(0,0,0,0.3);
            ">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">Generated Quiz Questions</h3>
                    <button id="mpcc-close-results" class="button" style="font-size: 20px; padding: 0 10px;">×</button>
                </div>
                <div>${questionsHtml}</div>
                <p style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
                    <strong>Note:</strong> Copy these questions and manually add them to your quiz using the question blocks.
                </p>
            </div>
            <div id="mpcc-quiz-overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 99999;
            "></div>
        `;
        
        $('body').append(modal);
        
        // Close handlers
        $('#mpcc-close-results, #mpcc-quiz-overlay').on('click', function() {
            $('#mpcc-quiz-results, #mpcc-quiz-overlay').remove();
        });
    }
    
    function showNotice(message, type) {
        const notice = `
            <div class="notice notice-${type} is-dismissible" style="
                position: fixed;
                top: 50px;
                right: 20px;
                z-index: 100001;
                max-width: 400px;
            ">
                <p>${message}</p>
            </div>
        `;
        
        const $notice = $(notice);
        $('body').append($notice);
        
        setTimeout(function() {
            $notice.fadeOut(function() {
                $notice.remove();
            });
        }, 5000);
    }
    
    // Add spinning animation
    const style = `
        <style>
        .spin {
            animation: mpcc-spin 1s linear infinite;
        }
        @keyframes mpcc-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
    `;
    $('head').append(style);

})(jQuery);