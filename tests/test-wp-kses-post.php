<?php
/**
 * Test script to understand how wp_kses_post handles Gutenberg lists
 * Run this with: wp eval-file test-wp-kses-post.php
 */

// Test content with proper Gutenberg list format
$test_content_1 = '<!-- wp:list -->
<ul>
<li>Item 1</li>
<li>Item 2</li>
<li>Item 3</li>
</ul>
<!-- /wp:list -->';

// Test content with nested ul tags (incorrect format)
$test_content_2 = '<!-- wp:list -->
<ul><ul>
<li>Item 1</li>
<li>Item 2</li>
<li>Item 3</li>
</ul></ul>
<!-- /wp:list -->';

// Test content with just ul tags without Gutenberg comments
$test_content_3 = '<ul>
<li>Item 1</li>
<li>Item 2</li>
<li>Item 3</li>
</ul>';

echo "=== Test 1: Proper Gutenberg list ===\n";
echo "Original:\n" . $test_content_1 . "\n\n";
echo "After wp_kses_post:\n" . wp_kses_post($test_content_1) . "\n\n";

echo "=== Test 2: Nested ul tags (incorrect) ===\n";
echo "Original:\n" . $test_content_2 . "\n\n";
echo "After wp_kses_post:\n" . wp_kses_post($test_content_2) . "\n\n";

echo "=== Test 3: Plain ul tags ===\n";
echo "Original:\n" . $test_content_3 . "\n\n";
echo "After wp_kses_post:\n" . wp_kses_post($test_content_3) . "\n\n";

// Test what happens when we have markdown converted to HTML
$markdown_converted = '<ul><li>Item 1</li><li>Item 2</li></ul>';
echo "=== Test 4: Markdown converted to HTML ===\n";
echo "Original:\n" . $markdown_converted . "\n\n";
echo "After wp_kses_post:\n" . wp_kses_post($markdown_converted) . "\n\n";

// Test with line breaks between li tags
$test_content_4 = '<!-- wp:list -->
<ul>
<li>Item 1</li>
<li>Item 2</li>
</ul>
<!-- /wp:list -->';

echo "=== Test 5: With line breaks between li tags ===\n";
echo "Original:\n" . $test_content_4 . "\n\n";
echo "After wp_kses_post:\n" . wp_kses_post($test_content_4) . "\n\n";