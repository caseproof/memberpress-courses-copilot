<?php
/**
 * Manual test script for quiz AI integration
 * 
 * Usage: wp eval-file wp-content/plugins/memberpress-courses-copilot/tests/manual-quiz-test.php
 */

// Test the quiz AI service
require_once __DIR__ . '/../vendor/autoload.php';

use MemberPressCoursesCopilot\Services\MpccQuizAIService;
use MemberPressCoursesCopilot\Services\LLMService;

echo "Testing Quiz AI Service Integration\n";
echo "===================================\n\n";

try {
    // Create the service
    $llmService = new LLMService();
    $quizService = new MpccQuizAIService($llmService);
    
    // Test content
    $content = "The water cycle, also known as the hydrologic cycle, describes the continuous movement of water on, above, and below Earth's surface. The cycle includes evaporation, where water transforms from liquid to vapor; condensation, where water vapor forms clouds; and precipitation, where water falls back to Earth as rain, snow, or hail. This process is driven by solar energy and gravity.";
    
    echo "Generating 5 multiple-choice questions...\n\n";
    
    // Generate questions
    $questions = $quizService->generateMultipleChoiceQuestions($content, 5);
    
    if (empty($questions)) {
        echo "ERROR: No questions generated!\n";
        exit(1);
    }
    
    echo "Successfully generated " . count($questions) . " questions:\n\n";
    
    foreach ($questions as $i => $question) {
        echo "Question " . ($i + 1) . ": " . $question['question'] . "\n";
        echo "Options:\n";
        foreach ($question['options'] as $key => $option) {
            echo "  $key) $option\n";
        }
        echo "Correct Answer: " . $question['correct_answer'] . "\n";
        if (!empty($question['explanation'])) {
            echo "Explanation: " . $question['explanation'] . "\n";
        }
        echo "\n";
    }
    
    echo "Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}