<?php
/**
 * Test script for lesson content generation
 * 
 * This demonstrates how to use the enhanced LLMService to generate
 * educational content for individual lessons.
 */

// Load WordPress
require_once '/Users/sethshoultes/Local Sites/memberpress-testing/app/public/wp-load.php';

use MemberPressCoursesCopilot\Services\LLMService;

// Initialize the LLM service
$llmService = new LLMService();

// Example 1: Generate content for a technical programming lesson
echo "=== Example 1: Technical Programming Lesson ===\n\n";

try {
    $technicalLesson = $llmService->generateLessonContent(
        'Introduction to JavaScript', // Section title
        3, // Lesson number
        [
            'course_title' => 'Web Development Fundamentals',
            'lesson_title' => 'Variables and Data Types',
            'difficulty_level' => 'beginner',
            'target_audience' => 'aspiring web developers with no prior programming experience',
            'course_context' => 'This course teaches the fundamentals of web development, starting with HTML/CSS and progressing to JavaScript programming.',
            'prerequisites' => ['Basic computer skills', 'Understanding of HTML structure'],
            'learning_objectives' => [
                'Understand what variables are and why they are used',
                'Learn the different data types in JavaScript',
                'Practice declaring and using variables',
                'Understand type conversion and coercion'
            ],
            'add_navigation' => true,
            'previous_lesson' => 'Introduction to JavaScript Syntax',
            'next_lesson' => 'Working with Operators'
        ]
    );
    
    echo $technicalLesson . "\n\n";
} catch (Exception $e) {
    echo "Error generating technical lesson: " . $e->getMessage() . "\n\n";
}

// Example 2: Generate content for a business course lesson
echo "\n\n=== Example 2: Business Course Lesson ===\n\n";

try {
    $businessLesson = $llmService->generateLessonContent(
        'Digital Marketing Strategies', // Section title
        5, // Lesson number
        [
            'course_title' => 'Modern Marketing for Small Businesses',
            'lesson_title' => 'Social Media Marketing Fundamentals',
            'difficulty_level' => 'intermediate',
            'target_audience' => 'small business owners and marketing professionals',
            'course_context' => 'This course covers modern marketing techniques for small businesses, focusing on cost-effective digital strategies.',
            'prerequisites' => ['Basic understanding of marketing concepts', 'Familiarity with social media platforms'],
            'learning_objectives' => [
                'Identify the right social media platforms for your business',
                'Create an effective content strategy',
                'Understand engagement metrics and analytics',
                'Develop a consistent brand voice across platforms'
            ]
        ]
    );
    
    echo $businessLesson . "\n\n";
} catch (Exception $e) {
    echo "Error generating business lesson: " . $e->getMessage() . "\n\n";
}

// Example 3: Generate content with streaming (simulated)
echo "\n\n=== Example 3: Streaming Content Generation ===\n\n";

try {
    echo "Generating lesson content with streaming...\n";
    
    $streamedContent = $llmService->generateLessonContentStream(
        'Advanced Photography Techniques', // Section title
        2, // Lesson number
        [
            'course_title' => 'Professional Photography Masterclass',
            'lesson_title' => 'Understanding Manual Camera Settings',
            'difficulty_level' => 'intermediate',
            'target_audience' => 'photography enthusiasts ready to move beyond auto mode',
            'learning_objectives' => [
                'Master the exposure triangle (aperture, shutter speed, ISO)',
                'Choose appropriate settings for different scenarios',
                'Create specific artistic effects using manual controls'
            ]
        ],
        function($chunk) {
            // Callback function for streaming chunks
            echo $chunk;
            flush(); // Flush output buffer to display chunks in real-time
        }
    );
    
    echo "\n\nStreaming complete!\n\n";
} catch (Exception $e) {
    echo "Error with streaming generation: " . $e->getMessage() . "\n\n";
}

// Example 4: Generate content for different difficulty levels
echo "\n\n=== Example 4: Difficulty Level Variations ===\n\n";

$lessonTopic = 'Data Analysis with Python';
$difficulties = ['beginner', 'intermediate', 'advanced'];

foreach ($difficulties as $difficulty) {
    echo "--- {$difficulty} Level ---\n";
    
    try {
        $content = $llmService->generateLessonContent(
            'Python for Data Science', // Section title
            1, // Lesson number
            [
                'course_title' => 'Data Science Bootcamp',
                'lesson_title' => $lessonTopic,
                'difficulty_level' => $difficulty,
                'target_audience' => $difficulty === 'beginner' 
                    ? 'complete beginners to programming and data analysis'
                    : ($difficulty === 'intermediate' 
                        ? 'programmers new to data science'
                        : 'experienced data analysts looking to master Python'),
                'learning_objectives' => [
                    'Understand data analysis concepts',
                    'Use Python libraries for data manipulation',
                    'Create basic visualizations'
                ]
            ]
        );
        
        // Show just the first 300 characters for comparison
        echo substr($content, 0, 300) . "...\n\n";
    } catch (Exception $e) {
        echo "Error generating {$difficulty} lesson: " . $e->getMessage() . "\n\n";
    }
}

// Display usage statistics
echo "\n\n=== Usage Information ===\n";
echo "The LLMService uses different AI models based on content type:\n";
echo "- Lesson content: Claude 3.5 Sonnet (via Anthropic)\n";
echo "- Structured analysis: GPT-4 or GPT-3.5 (via OpenAI)\n";
echo "- Content is optimized for educational purposes with:\n";
echo "  - Clear learning objectives\n";
echo "  - Structured content flow\n";
echo "  - Practical examples\n";
echo "  - Appropriate difficulty scaling\n";
echo "  - Engagement-focused writing\n";

echo "\n\nTest completed!\n";