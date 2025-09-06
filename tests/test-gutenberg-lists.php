<?php
/**
 * Test Gutenberg list content preservation
 * Run this in the WordPress environment to debug list stripping issue
 */

// Test content with lists that have items
$test_content = '<!-- wp:list {"ordered":true} -->
<ol>
<li>Market Need</li>
<li>Does your idea solve a real problem?</li>
<li>Are people actively looking for this solution?</li>
<li>Is the problem frequent and urgent enough?</li>
</ol>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Example: Netflix identified that people were frustrated with late fees.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul>
<li>Client-side scripting language</li>
<li>Object-oriented programming</li>
<li>Runs in all modern web browsers</li>
<li>Can also run on servers (Node.js)</li>
<li>Dynamic and flexible</li>
</ul>
<!-- /wp:list -->';

echo "=== GUTENBERG LIST CONTENT PRESERVATION TEST ===\n\n";

// Test 1: Direct content check
echo "TEST 1: Original Content\n";
echo "List items found: " . substr_count($test_content, '<li>') . "\n";
echo "Content length: " . strlen($test_content) . "\n\n";

// Test 2: JSON encoding/decoding (simulating AJAX)
echo "TEST 2: JSON Encoding/Decoding\n";
$json_encoded = json_encode(['content' => $test_content]);
$json_decoded = json_decode($json_encoded, true);
echo "After JSON: List items found: " . substr_count($json_decoded['content'], '<li>') . "\n";
echo "Content preserved: " . (strpos($json_decoded['content'], 'Market Need') !== false ? 'YES' : 'NO') . "\n\n";

// Test 3: stripslashes (used in AJAX handlers)
echo "TEST 3: Stripslashes\n";
$with_slashes = addslashes($test_content);
$stripped = stripslashes($with_slashes);
echo "After stripslashes: List items found: " . substr_count($stripped, '<li>') . "\n";
echo "Content preserved: " . (strpos($stripped, 'Market Need') !== false ? 'YES' : 'NO') . "\n\n";

// Test 4: Various sanitization functions
echo "TEST 4: WordPress Sanitization Functions\n";

// wp_kses_post
$kses_filtered = wp_kses_post($test_content);
echo "wp_kses_post:\n";
echo "  - Gutenberg comments: " . (strpos($kses_filtered, '<!-- wp:') !== false ? 'PRESERVED' : 'STRIPPED') . "\n";
echo "  - List HTML: " . (strpos($kses_filtered, '<li>') !== false ? 'PRESERVED' : 'STRIPPED') . "\n";
echo "  - List items found: " . substr_count($kses_filtered, '<li>') . "\n";
echo "  - Sample: " . substr($kses_filtered, 0, 100) . "...\n\n";

// sanitize_textarea_field
$textarea_sanitized = sanitize_textarea_field($test_content);
echo "sanitize_textarea_field:\n";
echo "  - HTML stripped: " . (strpos($textarea_sanitized, '<li>') !== false ? 'NO' : 'YES') . "\n";
echo "  - Text preserved: " . (strpos($textarea_sanitized, 'Market Need') !== false ? 'YES' : 'NO') . "\n\n";

// Test 5: wp_insert_post
echo "TEST 5: wp_insert_post\n";
$post_data = [
    'post_title' => 'Test Gutenberg Lists',
    'post_content' => $test_content,
    'post_status' => 'draft',
    'post_type' => 'post'
];

$post_id = wp_insert_post($post_data);
if ($post_id) {
    $saved_post = get_post($post_id);
    echo "Post created with ID: " . $post_id . "\n";
    echo "Saved content length: " . strlen($saved_post->post_content) . "\n";
    echo "List items in saved content: " . substr_count($saved_post->post_content, '<li>') . "\n";
    echo "Gutenberg comments preserved: " . (strpos($saved_post->post_content, '<!-- wp:') !== false ? 'YES' : 'NO') . "\n";
    echo "Content 'Market Need' preserved: " . (strpos($saved_post->post_content, 'Market Need') !== false ? 'YES' : 'NO') . "\n";
    
    // Clean up
    wp_delete_post($post_id, true);
} else {
    echo "Failed to create post\n";
}

// Test 6: Check if the issue is with the MemberPress custom post type
echo "\nTEST 6: MemberPress Lesson Post Type\n";
if (post_type_exists('mpcs-lesson')) {
    $lesson_data = [
        'post_title' => 'Test Lesson',
        'post_content' => $test_content,
        'post_status' => 'publish',
        'post_type' => 'mpcs-lesson'
    ];
    
    $lesson_id = wp_insert_post($lesson_data);
    if ($lesson_id) {
        $saved_lesson = get_post($lesson_id);
        echo "Lesson created with ID: " . $lesson_id . "\n";
        echo "List items in saved lesson: " . substr_count($saved_lesson->post_content, '<li>') . "\n";
        echo "Content 'Market Need' preserved: " . (strpos($saved_lesson->post_content, 'Market Need') !== false ? 'YES' : 'NO') . "\n";
        
        // Check if there are any filters modifying content
        $filters = $GLOBALS['wp_filter']['content_save_pre'] ?? [];
        if (!empty($filters)) {
            echo "Filters on content_save_pre: " . count($filters) . "\n";
        }
        
        wp_delete_post($lesson_id, true);
    }
} else {
    echo "mpcs-lesson post type not found\n";
}

// Test 7: Direct database check
echo "\nTEST 7: What happens with direct processing\n";
$test_array = [
    'sections' => [
        [
            'lessons' => [
                [
                    'content' => $test_content
                ]
            ]
        ]
    ]
];

// Simulate the AJAX data flow
$ajax_json = json_encode($test_array);
echo "Original JSON length: " . strlen($ajax_json) . "\n";

// Simulate POST with magic quotes (if enabled)
$_POST['course_data'] = $ajax_json;
$decoded = json_decode(stripslashes($_POST['course_data']), true);
$lesson_content = $decoded['sections'][0]['lessons'][0]['content'];

echo "After AJAX simulation:\n";
echo "  - List items: " . substr_count($lesson_content, '<li>') . "\n";
echo "  - Content preserved: " . (strpos($lesson_content, 'Market Need') !== false ? 'YES' : 'NO') . "\n";

// Find first list block
$list_start = strpos($lesson_content, '<!-- wp:list');
if ($list_start !== false) {
    $list_end = strpos($lesson_content, '<!-- /wp:list -->', $list_start);
    if ($list_end !== false) {
        $list_block = substr($lesson_content, $list_start, $list_end - $list_start + strlen('<!-- /wp:list -->'));
        echo "\nFirst list block:\n" . $list_block . "\n";
    }
}

echo "\n=== END TEST ===\n";