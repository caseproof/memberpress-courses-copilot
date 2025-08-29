<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Interfaces\IQuizAIService;
use MemberPressCoursesCopilot\Interfaces\ILLMService;

/**
 * Quiz AI Service Implementation
 * 
 * Handles AI-powered quiz question generation with focus on multiple-choice questions
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
class MpccQuizAIService extends BaseService implements IQuizAIService
{
    private ILLMService $llmService;

    /**
     * Constructor
     * 
     * @param ILLMService $llmService LLM service for AI operations
     */
    public function __construct(ILLMService $llmService)
    {
        parent::__construct();
        $this->llmService = $llmService;
    }

    /**
     * Initialize the service
     */
    public function init(): void
    {
        $this->logger->info('MpccQuizAIService initialized');
    }

    /**
     * Generate quiz questions based on content
     * Currently redirects to multiple-choice generation
     * 
     * @param string $content Content to generate questions from
     * @param array $options Generation options
     * @return array Generated questions
     */
    public function generateQuestions(string $content, array $options = []): array
    {
        $count = $options['count'] ?? 5;
        return $this->generateMultipleChoiceQuestions($content, $count);
    }

    /**
     * Generate multiple-choice questions from content
     * 
     * @param string $content Content to generate questions from
     * @param int $count Number of questions to generate
     * @return array Generated questions
     */
    public function generateMultipleChoiceQuestions(string $content, int $count = 5): array
    {
        $this->logger->info('Generating multiple-choice questions', [
            'content_length' => strlen($content),
            'question_count' => $count
        ]);

        $prompt = $this->buildMultipleChoicePrompt($content, $count);
        
        $response = $this->llmService->generateContent($prompt, 'quiz_generation');
        
        if ($response['error']) {
            $this->logger->error('Failed to generate questions', ['error' => $response['message']]);
            return [];
        }

        $questions = $this->parseMultipleChoiceQuestions($response['content']);
        
        $this->logger->info('Generated questions successfully', [
            'requested_count' => $count,
            'generated_count' => count($questions)
        ]);

        return $questions;
    }

    /**
     * Build prompt for multiple-choice question generation
     * 
     * @param string $content Content to base questions on
     * @param int $count Number of questions to generate
     * @return string Generated prompt
     */
    private function buildMultipleChoicePrompt(string $content, int $count): string
    {
        return "Generate {$count} multiple-choice questions based on the following content. 
        
For each question, provide:
1. Question text
2. Four answer options (A, B, C, D)
3. The correct answer letter
4. Brief explanation of why the answer is correct

Format the output as JSON array with this structure:
[
    {
        \"question\": \"Question text here\",
        \"options\": {
            \"A\": \"First option\",
            \"B\": \"Second option\",
            \"C\": \"Third option\",
            \"D\": \"Fourth option\"
        },
        \"correct_answer\": \"A\",
        \"explanation\": \"Explanation text\"
    }
]

Content to create questions from:
{$content}";
    }

    /**
     * Parse AI response into structured question array
     * 
     * @param string $response AI response containing questions
     * @return array Parsed questions
     */
    private function parseMultipleChoiceQuestions(string $response): array
    {
        $questions = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Failed to parse JSON response', [
                'error' => json_last_error_msg(),
                'response' => substr($response, 0, 500)
            ]);
            return [];
        }

        return array_map(function ($q) {
            return [
                'type' => 'multiple_choice',
                'question' => $q['question'] ?? '',
                'options' => $q['options'] ?? [],
                'correct_answer' => $q['correct_answer'] ?? '',
                'explanation' => $q['explanation'] ?? ''
            ];
        }, $questions ?: []);
    }

    // Stub implementations for interface methods
    public function generateQuizFromLesson(int $lesson_id, array $options = []): array { return []; }
    public function generateQuizFromCourse(int $course_id, array $options = []): array { return []; }
    public function generateQuestion(string $content, string $question_type, array $options = []): array { return []; }
    public function regenerateQuestion(array $question, array $options = []): array { return []; }
    public function validateQuestions(array $questions): array { return []; }
    public function getQuizTemplates(): array { return []; }
    public function applyTemplate($template, string $content): array { return []; }
    public function generateQuizAnalytics(int $quiz_id): array { return []; }
    public function optimizeQuiz(int $quiz_id, array $performance_data): array { return []; }
    public function generateFeedback(array $question, string $user_answer): string { return ''; }
    public function getSupportedQuestionTypes(): array { return ['multiple_choice']; }
    public function estimateDifficulty(array $questions): string { return 'medium'; }
}