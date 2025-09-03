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
     * Handles multiple question types based on options
     * 
     * @param string $content Content to generate questions from
     * @param array $options Generation options including 'type' and 'count'
     * @return array Generated questions with error handling
     */
    public function generateQuestions(string $content, array $options = []): array
    {
        $type = $options['type'] ?? 'multiple_choice';
        $count = $options['count'] ?? 5;
        
        $this->logger->info('Generating questions', [
            'type' => $type,
            'count' => $count,
            'content_length' => strlen($content)
        ]);
        
        // Validate content suitability
        $validation = $this->validateContentForType($content, $type);
        if (!$validation['suitable']) {
            $this->logger->warning('Content not suitable for question type', [
                'type' => $type,
                'reason' => $validation['reason']
            ]);
            return [
                'error' => true,
                'message' => $validation['reason'],
                'suggestion' => $validation['suggestion'] ?? ''
            ];
        }
        
        switch ($type) {
            case 'multiple_choice':
                return $this->generateMultipleChoiceQuestions($content, $count);
                
            case 'true_false':
                return $this->generateTrueFalseQuestions($content, $count);
                
            case 'text_answer':
                return $this->generateTextAnswerQuestions($content, $count);
                
            case 'multiple_select':
                return $this->generateMultipleSelectQuestions($content, $count);
                
            default:
                $this->logger->error('Unsupported question type', ['type' => $type]);
                return [
                    'error' => true,
                    'message' => "Unsupported question type: {$type}",
                    'supported_types' => $this->getSupportedQuestionTypes()
                ];
        }
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

IMPORTANT: Return ONLY the JSON array, no introductory text or explanations outside the JSON structure.

Format the output as a valid JSON array with this exact structure:
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
        $questions = $this->extractJsonFromResponse($response);
        
        if (!$questions) {
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
        }, $questions);
    }

    /**
     * Generate true/false questions from content
     * 
     * @param string $content Content to generate questions from
     * @param int $count Number of questions to generate
     * @return array Generated questions
     */
    public function generateTrueFalseQuestions(string $content, int $count = 5): array
    {
        $this->logger->info('Generating true/false questions', [
            'content_length' => strlen($content),
            'question_count' => $count
        ]);

        $prompt = $this->buildTrueFalsePrompt($content, $count);
        
        $response = $this->llmService->generateContent($prompt, 'quiz_generation');
        
        if ($response['error']) {
            $this->logger->error('Failed to generate true/false questions', ['error' => $response['message']]);
            return [];
        }

        $questions = $this->parseTrueFalseQuestions($response['content']);
        
        $this->logger->info('Generated true/false questions successfully', [
            'requested_count' => $count,
            'generated_count' => count($questions)
        ]);

        return $questions;
    }

    /**
     * Generate text answer questions from content
     * 
     * @param string $content Content to generate questions from
     * @param int $count Number of questions to generate
     * @return array Generated questions
     */
    public function generateTextAnswerQuestions(string $content, int $count = 5): array
    {
        $this->logger->info('Generating text answer questions', [
            'content_length' => strlen($content),
            'question_count' => $count
        ]);

        $prompt = $this->buildTextAnswerPrompt($content, $count);
        
        $response = $this->llmService->generateContent($prompt, 'quiz_generation');
        
        if ($response['error']) {
            $this->logger->error('Failed to generate text answer questions', ['error' => $response['message']]);
            return [];
        }

        $questions = $this->parseTextAnswerQuestions($response['content']);
        
        $this->logger->info('Generated text answer questions successfully', [
            'requested_count' => $count,
            'generated_count' => count($questions)
        ]);

        return $questions;
    }

    /**
     * Generate multiple select questions from content
     * 
     * @param string $content Content to generate questions from
     * @param int $count Number of questions to generate
     * @return array Generated questions
     */
    public function generateMultipleSelectQuestions(string $content, int $count = 5): array
    {
        $this->logger->info('Generating multiple select questions', [
            'content_length' => strlen($content),
            'question_count' => $count
        ]);

        $prompt = $this->buildMultipleSelectPrompt($content, $count);
        
        $response = $this->llmService->generateContent($prompt, 'quiz_generation');
        
        if ($response['error']) {
            $this->logger->error('Failed to generate multiple select questions', ['error' => $response['message']]);
            return [];
        }

        $questions = $this->parseMultipleSelectQuestions($response['content']);
        
        $this->logger->info('Generated multiple select questions successfully', [
            'requested_count' => $count,
            'generated_count' => count($questions)
        ]);

        return $questions;
    }

    /**
     * Validate if content is suitable for specific question type
     * 
     * @param string $content Content to validate
     * @param string $type Question type
     * @return array Validation result with 'suitable', 'reason', and 'suggestion'
     */
    private function validateContentForType(string $content, string $type): array
    {
        $contentLength = strlen($content);
        
        // Basic length validation
        if ($contentLength < 100) {
            return [
                'suitable' => false,
                'reason' => 'Content is too short to generate meaningful questions',
                'suggestion' => 'Please provide at least 100 characters of content'
            ];
        }
        
        // Type-specific validation
        switch ($type) {
            case 'true_false':
                if ($contentLength < 200) {
                    return [
                        'suitable' => false,
                        'reason' => 'True/False questions require more detailed content to create clear statements',
                        'suggestion' => 'Provide content with clear facts or statements that can be verified as true or false'
                    ];
                }
                break;
                
            case 'text_answer':
                // Check if content has specific facts, numbers, dates, or names
                if (!preg_match('/(\d+|[A-Z][a-z]+|\b(?:is|are|was|were|called|named)\b)/i', $content)) {
                    return [
                        'suitable' => false,
                        'reason' => 'Text answer questions require content with specific facts, names, dates, or numbers',
                        'suggestion' => 'Provide content with concrete information that can have short, specific answers'
                    ];
                }
                break;
                
            case 'multiple_select':
                // For multiple select, we just need enough content to work with
                // The AI can determine if there are multiple items to select from
                if ($contentLength < 200) {
                    return [
                        'suitable' => false,
                        'reason' => 'Multiple select questions require more detailed content to identify multiple correct answers',
                        'suggestion' => 'Provide content with at least 200 characters that covers various aspects or elements of the topic'
                    ];
                }
                
                // Log for debugging
                $this->logger->info('Multiple select validation passed', [
                    'content_length' => $contentLength
                ]);
                break;
        }
        
        return ['suitable' => true];
    }

    /**
     * Build prompt for true/false question generation
     * 
     * @param string $content Content to base questions on
     * @param int $count Number of questions to generate
     * @return string Generated prompt
     */
    private function buildTrueFalsePrompt(string $content, int $count): string
    {
        return "Generate {$count} true/false questions based on the following content. 
        
For each question, provide:
1. A clear statement that is definitively true or false based on the content
2. The correct answer (true or false)
3. Brief explanation referencing the content

IMPORTANT: 
- Make statements clear and unambiguous
- Avoid statements that could be interpreted multiple ways
- Base all statements directly on the provided content
- Return ONLY the JSON array, no introductory text

Format the output as a valid JSON array with this exact structure:
[
    {
        \"statement\": \"Clear statement that is true or false\",
        \"correct_answer\": true,
        \"explanation\": \"This is true/false because [reference to content]\"
    }
]

Content to create questions from:
{$content}";
    }

    /**
     * Build prompt for text answer question generation
     * 
     * @param string $content Content to base questions on
     * @param int $count Number of questions to generate
     * @return string Generated prompt
     */
    private function buildTextAnswerPrompt(string $content, int $count): string
    {
        return "Generate {$count} short answer questions based on the following content. 
        
For each question, provide:
1. A question that has a specific, short answer (1-5 words)
2. The primary correct answer
3. Alternative acceptable answers (if any)
4. Brief explanation

IMPORTANT: 
- Questions should have concrete, factual answers
- Answers should be specific terms, names, numbers, or short phrases
- Include common variations or acceptable alternatives
- Return ONLY the JSON array, no introductory text

Format the output as a valid JSON array with this exact structure:
[
    {
        \"question\": \"Question requiring a short, specific answer\",
        \"correct_answer\": \"Primary answer\",
        \"alternative_answers\": [\"Alternative 1\", \"Alternative 2\"],
        \"explanation\": \"The answer is X because...\"
    }
]

Content to create questions from:
{$content}";
    }

    /**
     * Build prompt for multiple select question generation
     * 
     * @param string $content Content to base questions on
     * @param int $count Number of questions to generate
     * @return string Generated prompt
     */
    private function buildMultipleSelectPrompt(string $content, int $count): string
    {
        return "Generate {$count} multiple select questions based on the following content. 
        
For each question, provide:
1. A question that has multiple correct answers
2. 4-6 answer options
3. 2-4 correct answers from those options
4. Brief explanation of why each correct answer is right

IMPORTANT: 
- Questions should naturally have multiple correct answers
- Make sure incorrect options are plausible but clearly wrong
- Balance the number of correct vs incorrect options
- Return ONLY the JSON array, no introductory text

Format the output as a valid JSON array with this exact structure:
[
    {
        \"question\": \"Select all that apply: [question text]\",
        \"options\": {
            \"A\": \"First option\",
            \"B\": \"Second option\",
            \"C\": \"Third option\",
            \"D\": \"Fourth option\",
            \"E\": \"Fifth option\"
        },
        \"correct_answers\": [\"A\", \"C\", \"E\"],
        \"explanation\": \"Options A, C, and E are correct because...\"
    }
]

Content to create questions from:
{$content}";
    }

    /**
     * Parse true/false questions from AI response
     * 
     * @param string $response AI response
     * @return array Parsed questions
     */
    private function parseTrueFalseQuestions(string $response): array
    {
        $questions = $this->extractJsonFromResponse($response);
        
        if (!$questions) {
            return [];
        }

        return array_map(function ($q) {
            return [
                'type' => 'true_false',
                'statement' => $q['statement'] ?? '',
                'correct_answer' => $q['correct_answer'] ?? false,
                'explanation' => $q['explanation'] ?? ''
            ];
        }, $questions);
    }

    /**
     * Parse text answer questions from AI response
     * 
     * @param string $response AI response
     * @return array Parsed questions
     */
    private function parseTextAnswerQuestions(string $response): array
    {
        $questions = $this->extractJsonFromResponse($response);
        
        if (!$questions) {
            return [];
        }

        return array_map(function ($q) {
            return [
                'type' => 'text_answer',
                'question' => $q['question'] ?? '',
                'correct_answer' => $q['correct_answer'] ?? '',
                'alternative_answers' => $q['alternative_answers'] ?? [],
                'explanation' => $q['explanation'] ?? ''
            ];
        }, $questions);
    }

    /**
     * Parse multiple select questions from AI response
     * 
     * @param string $response AI response
     * @return array Parsed questions
     */
    private function parseMultipleSelectQuestions(string $response): array
    {
        $questions = $this->extractJsonFromResponse($response);
        
        if (!$questions) {
            return [];
        }

        return array_map(function ($q) {
            return [
                'type' => 'multiple_select',
                'question' => $q['question'] ?? '',
                'options' => $q['options'] ?? [],
                'correct_answers' => $q['correct_answers'] ?? [],
                'explanation' => $q['explanation'] ?? ''
            ];
        }, $questions);
    }

    /**
     * Extract JSON from AI response
     * 
     * @param string $response AI response
     * @return array|null Extracted JSON data
     */
    private function extractJsonFromResponse(string $response): ?array
    {
        // Try to extract JSON array from the response
        $jsonStart = strpos($response, '[');
        if ($jsonStart !== false) {
            $jsonEnd = strrpos($response, ']');
            if ($jsonEnd !== false) {
                $response = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
            }
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->warning('Failed to parse JSON response', [
                'error' => json_last_error_msg(),
                'response' => substr($response, 0, 500)
            ]);
            return null;
        }

        return $data;
    }

    // Stub implementations for interface methods
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function generateQuizFromLesson(int $lesson_id, array $options = []): array { 
        throw new \BadMethodCallException('generateQuizFromLesson() is not implemented. This method must generate quiz questions from lesson content.');
    }
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function generateQuizFromCourse(int $course_id, array $options = []): array { 
        throw new \BadMethodCallException('generateQuizFromCourse() is not implemented. This method must generate quiz questions from course content.');
    }
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function generateQuestion(string $content, string $question_type, array $options = []): array { 
        throw new \BadMethodCallException('generateQuestion() is not implemented. This method must generate a question of the specified type from the provided content.');
    }
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function regenerateQuestion(array $question, array $options = []): array { 
        throw new \BadMethodCallException('regenerateQuestion() is not implemented. This method must regenerate a question with variations based on the original.');
    }
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function validateQuestions(array $questions): array { 
        throw new \BadMethodCallException('validateQuestions() is not implemented. This method must validate quiz questions for correctness and completeness.');
    }
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function getQuizTemplates(): array { 
        throw new \BadMethodCallException('getQuizTemplates() is not implemented. This method must return available quiz templates.');
    }
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function applyTemplate($template, string $content): array { 
        throw new \BadMethodCallException('applyTemplate() is not implemented. This method must apply a quiz template to generate questions from content.');
    }
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function generateQuizAnalytics(int $quiz_id): array { 
        throw new \BadMethodCallException('generateQuizAnalytics() is not implemented. This method must generate analytics for the specified quiz.');
    }
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function optimizeQuiz(int $quiz_id, array $performance_data): array { 
        throw new \BadMethodCallException('optimizeQuiz() is not implemented. This method must optimize quiz based on performance data.');
    }
    /**
     * @throws \BadMethodCallException
     */
    /**
     * @throws \BadMethodCallException
     */
    public function generateFeedback(array $question, string $user_answer): string { 
        throw new \BadMethodCallException('generateFeedback() is not implemented. This method must generate feedback for user answers.');
    }
    public function getSupportedQuestionTypes(): array { 
        return [
            'multiple_choice',
            'true_false',
            'text_answer',
            'multiple_select'
        ]; 
    }
    /**
     * @throws \BadMethodCallException
     */
    public function estimateDifficulty(array $questions): string { 
        throw new \BadMethodCallException('estimateDifficulty() is not implemented. This method must estimate the difficulty level of quiz questions.');
    }
}