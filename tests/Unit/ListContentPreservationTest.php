<?php

namespace Tests\Unit;

use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Controllers\SimpleAjaxController;
use Tests\TestCase;

/**
 * Test to identify where list content is being stripped
 */
class ListContentPreservationTest extends TestCase
{
    private $sampleGutenbergContent = '<!-- wp:list {"ordered":true} -->
<ol>
<li>Market Need</li>
<li>Does your idea solve a real problem?</li>
<li>Are people actively looking for this solution?</li>
<li>Is the problem frequent and urgent enough?</li>
</ol>
<!-- /wp:list -->';

    private $sampleListWithEmptyItems = '<!-- wp:list {"ordered":true} -->
<ol>
<li></li>
<li></li>
<li></li>
<li></li>
</ol>
<!-- /wp:list -->';

    /**
     * Test that CourseGeneratorService preserves list content
     */
    public function test_course_generator_preserves_list_content()
    {
        $courseGenerator = new CourseGeneratorService();
        
        $courseData = [
            'title' => 'Test Course',
            'description' => 'Test Description',
            'sections' => [
                [
                    'title' => 'Test Section',
                    'lessons' => [
                        [
                            'title' => 'Test Lesson',
                            'content' => $this->sampleGutenbergContent
                        ]
                    ]
                ]
            ]
        ];
        
        // Test the conversion logic
        $reflection = new \ReflectionClass($courseGenerator);
        $method = $reflection->getMethod('convertToGutenbergBlocks');
        $method->setAccessible(true);
        
        // Content should not be converted if it already has Gutenberg blocks
        $result = $method->invoke($courseGenerator, $this->sampleGutenbergContent);
        
        $this->assertEquals($this->sampleGutenbergContent, $result, 'Content with Gutenberg blocks should not be modified');
        $this->assertStringContainsString('Market Need', $result);
        $this->assertStringContainsString('Does your idea solve a real problem?', $result);
    }

    /**
     * Test JSON encoding/decoding preserves content
     */
    public function test_json_encoding_preserves_list_content()
    {
        $courseData = [
            'sections' => [
                [
                    'lessons' => [
                        [
                            'content' => $this->sampleGutenbergContent
                        ]
                    ]
                ]
            ]
        ];
        
        // Simulate what happens in AJAX
        $jsonEncoded = json_encode($courseData);
        $jsonDecoded = json_decode($jsonEncoded, true);
        
        $content = $jsonDecoded['sections'][0]['lessons'][0]['content'];
        
        $this->assertEquals($this->sampleGutenbergContent, $content);
        $this->assertStringContainsString('Market Need', $content);
        $this->assertStringContainsString('Does your idea solve a real problem?', $content);
    }

    /**
     * Test stripslashes preserves content
     */
    public function test_stripslashes_preserves_list_content()
    {
        // Simulate POST data with slashes
        $postData = addslashes(json_encode([
            'content' => $this->sampleGutenbergContent
        ]));
        
        $decoded = json_decode(stripslashes($postData), true);
        $content = $decoded['content'];
        
        $this->assertEquals($this->sampleGutenbergContent, $content);
        $this->assertStringContainsString('Market Need', $content);
    }

    /**
     * Test that wp_insert_post might strip content
     * Note: This test requires WordPress to be loaded
     */
    public function test_wp_insert_post_content_handling()
    {
        if (!function_exists('wp_insert_post')) {
            $this->markTestSkipped('WordPress functions not available');
        }
        
        // Create a test post
        $postData = [
            'post_title' => 'Test Lesson',
            'post_content' => $this->sampleGutenbergContent,
            'post_status' => 'draft',
            'post_type' => 'post', // Use standard post type for testing
        ];
        
        $postId = wp_insert_post($postData);
        $this->assertIsInt($postId);
        
        // Retrieve the saved post
        $savedPost = get_post($postId);
        $savedContent = $savedPost->post_content;
        
        // Clean up
        wp_delete_post($postId, true);
        
        // Check if content was preserved
        $this->assertStringContainsString('Market Need', $savedContent);
        $this->assertStringContainsString('Does your idea solve a real problem?', $savedContent);
        
        // Count list items
        $savedListItems = substr_count($savedContent, '<li>');
        $originalListItems = substr_count($this->sampleGutenbergContent, '<li>');
        $this->assertEquals($originalListItems, $savedListItems, 'Number of list items should be preserved');
    }

    /**
     * Test sanitize_textarea_field effect on content
     */
    public function test_sanitize_textarea_field_strips_html()
    {
        if (!function_exists('sanitize_textarea_field')) {
            $this->markTestSkipped('WordPress functions not available');
        }
        
        $sanitized = sanitize_textarea_field($this->sampleGutenbergContent);
        
        // This will strip all HTML
        $this->assertStringNotContainsString('<li>', $sanitized);
        $this->assertStringNotContainsString('<!-- wp:list', $sanitized);
        
        // But text content should remain
        $this->assertStringContainsString('Market Need', $sanitized);
    }

    /**
     * Test wp_kses_post effect on Gutenberg blocks
     */
    public function test_wp_kses_post_strips_gutenberg_comments()
    {
        if (!function_exists('wp_kses_post')) {
            $this->markTestSkipped('WordPress functions not available');
        }
        
        $filtered = wp_kses_post($this->sampleGutenbergContent);
        
        // wp_kses_post strips HTML comments
        $this->assertStringNotContainsString('<!-- wp:list', $filtered);
        $this->assertStringNotContainsString('<!-- /wp:list -->', $filtered);
        
        // But list HTML should remain
        $this->assertStringContainsString('<ol>', $filtered);
        $this->assertStringContainsString('<li>Market Need</li>', $filtered);
    }

    /**
     * Test the actual flow from AJAX to save
     */
    public function test_complete_flow_preserves_content()
    {
        // Simulate the course data as it comes from frontend
        $courseDataJson = json_encode([
            'title' => 'Test Course',
            'sections' => [
                [
                    'title' => 'Section 1',
                    'lessons' => [
                        [
                            'title' => 'Lesson 1',
                            'content' => $this->sampleGutenbergContent
                        ]
                    ]
                ]
            ]
        ]);
        
        // Simulate what happens in SimpleAjaxController
        $courseData = json_decode(stripslashes($courseDataJson), true);
        
        // The content should still be intact at this point
        $lessonContent = $courseData['sections'][0]['lessons'][0]['content'];
        $this->assertEquals($this->sampleGutenbergContent, $lessonContent);
        $this->assertStringContainsString('Market Need', $lessonContent);
        $this->assertStringContainsString('Does your idea solve a real problem?', $lessonContent);
    }
}