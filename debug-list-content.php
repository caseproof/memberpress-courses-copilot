<?php
/**
 * Debug script to test list content preservation
 * Add this temporarily to track where content is being lost
 */

add_action('init', function() {
    if (!isset($_GET['debug_lists']) || !current_user_can('manage_options')) {
        return;
    }
    
    // Test content
    $test_content = '<!-- wp:list {"ordered":true} -->
<ol>
<li>Market Need</li>
<li>Does your idea solve a real problem?</li>
<li>Are people actively looking for this solution?</li>
</ol>
<!-- /wp:list -->';
    
    echo '<pre>';
    echo "=== DEBUGGING LIST CONTENT STRIPPING ===\n\n";
    
    // Test what CourseGeneratorService does
    $generator = new \MemberPressCoursesCopilot\Services\CourseGeneratorService();
    $reflection = new ReflectionClass($generator);
    $method = $reflection->getMethod('convertToGutenbergBlocks');
    $method->setAccessible(true);
    
    $converted = $method->invoke($generator, $test_content);
    echo "After convertToGutenbergBlocks:\n";
    echo "Input had " . substr_count($test_content, '<li>') . " list items\n";
    echo "Output has " . substr_count($converted, '<li>') . " list items\n";
    echo "Sample output:\n" . htmlspecialchars(substr($converted, 0, 500)) . "\n\n";
    
    // Test wp_insert_post directly
    $post_id = wp_insert_post([
        'post_title' => 'Debug Test',
        'post_content' => $test_content,
        'post_type' => 'mpcs-lesson',
        'post_status' => 'draft'
    ]);
    
    $saved = get_post($post_id);
    echo "After wp_insert_post:\n";
    echo "Saved " . substr_count($saved->post_content, '<li>') . " list items\n";
    echo "Content: " . htmlspecialchars(substr($saved->post_content, 0, 500)) . "\n";
    
    wp_delete_post($post_id, true);
    
    echo '</pre>';
    die();
});