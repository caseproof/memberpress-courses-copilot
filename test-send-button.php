<?php
/**
 * Test script to debug send button functionality
 * 
 * Usage: Navigate to this file directly to see debug output
 */

// Load WordPress
$wp_load_path = dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die('Could not find wp-load.php');
}

// Check if user is logged in
if (!is_user_logged_in()) {
    die('You must be logged in to run this test.');
}

// Check if the plugin is active
if (!defined('MEMBERPRESS_COURSES_COPILOT_VERSION')) {
    die('MemberPress Courses Copilot plugin is not active.');
}

echo "<h1>MemberPress Courses Copilot - Send Button Debug</h1>";

// Check AJAX handlers
echo "<h2>AJAX Handler Registration:</h2>";
global $wp_filter;

$ajax_actions = [
    'mpcc_ai_chat',
    'mpcc_load_ai_interface',
    'mpcc_ping'
];

foreach ($ajax_actions as $action) {
    $hook = 'wp_ajax_' . $action;
    if (isset($wp_filter[$hook])) {
        echo "<p>✓ <strong>{$action}</strong> handler is registered</p>";
    } else {
        echo "<p>✗ <strong>{$action}</strong> handler is NOT registered</p>";
    }
}

// Check if CourseIntegrationService is initialized
echo "<h2>Service Initialization:</h2>";
$integration_service = new \MemberPressCoursesCopilot\Services\CourseIntegrationService();
echo "<p>✓ CourseIntegrationService can be instantiated</p>";

// Check LLMService
try {
    $llm_service = new \MemberPressCoursesCopilot\Services\LLMService();
    echo "<p>✓ LLMService can be instantiated</p>";
    
    // Test a simple AI request
    echo "<h2>Testing AI Service:</h2>";
    $test_response = $llm_service->generateContent("Say 'Hello World' in one sentence.", 'general', ['max_tokens' => 50]);
    
    if ($test_response['error']) {
        echo "<p>✗ AI Service Error: " . esc_html($test_response['message']) . "</p>";
    } else {
        echo "<p>✓ AI Service Response: " . esc_html($test_response['content']) . "</p>";
    }
} catch (Exception $e) {
    echo "<p>✗ LLMService Error: " . esc_html($e->getMessage()) . "</p>";
}

// Generate test nonce
$nonce = wp_create_nonce('mpcc_courses_integration');
echo "<h2>Nonce for Testing:</h2>";
echo "<p>Nonce: <code>" . esc_html($nonce) . "</code></p>";

// JavaScript test
?>
<h2>JavaScript Console Test:</h2>
<p>Open browser console to see JavaScript debug output.</p>

<div id="test-chat-interface" style="border: 1px solid #ccc; padding: 20px; margin: 20px 0;">
    <h3>Test Chat Interface</h3>
    <textarea id="test-message" rows="3" style="width: 100%;">Hello, can you help me create a course?</textarea>
    <br><br>
    <button id="test-send" class="button button-primary">Test Send Button</button>
    <div id="test-output" style="margin-top: 20px; padding: 10px; background: #f5f5f5; min-height: 100px;">
        <em>Response will appear here...</em>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    console.log('Test Script Loaded');
    
    // Check for required globals
    console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'NOT DEFINED');
    console.log('mpccCoursesIntegration:', typeof mpccCoursesIntegration !== 'undefined' ? mpccCoursesIntegration : 'NOT DEFINED');
    console.log('mpccAISettings:', typeof mpccAISettings !== 'undefined' ? mpccAISettings : 'NOT DEFINED');
    console.log('AICopilot class:', typeof window.AICopilot !== 'undefined' ? 'AVAILABLE' : 'NOT AVAILABLE');
    
    $('#test-send').on('click', function() {
        var message = $('#test-message').val();
        var $output = $('#test-output');
        
        $output.html('<em>Sending request...</em>');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'mpcc_ai_chat',
                nonce: '<?php echo $nonce; ?>',
                message: message,
                context: 'course_creation'
            },
            success: function(response) {
                console.log('Success response:', response);
                if (response.success) {
                    $output.html('<strong>Success!</strong><br>AI Response: ' + response.data.message);
                } else {
                    $output.html('<strong>Error:</strong> ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr, status, error});
                $output.html('<strong>AJAX Error:</strong> ' + status + ' - ' + error);
            }
        });
    });
});
</script>

<?php
echo "<h2>Debug Information:</h2>";
echo "<p>WordPress Version: " . get_bloginfo('version') . "</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Plugin Version: " . MEMBERPRESS_COURSES_COPILOT_VERSION . "</p>";
echo "<p>Current User Can Edit Posts: " . (current_user_can('edit_posts') ? 'Yes' : 'No') . "</p>";
?>