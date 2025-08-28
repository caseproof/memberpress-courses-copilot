<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Interfaces\ILLMService;
use MemberPressCoursesCopilot\Utilities\ApiResponse;
use WP_Error;

/**
 * LLM Service
 * 
 * Provides a secure interface to the LiteLLM proxy through an authentication gateway.
 * API keys are stored securely on the gateway server, never exposed in the plugin code.
 */
class LLMService extends BaseService implements ILLMService
{
    // Auth gateway URL - can be overridden via wp-config.php constant
    private string $authGatewayUrl;
    
    // License key for authentication with the gateway
    // In production, this should come from MemberPress license system
    // See /docs/todo/LICENSING_IMPLEMENTATION.md for implementation details
    private const LICENSE_KEY = 'dev-license-key-001'; // Placeholder - not a real credential
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(); // Initialize logger from BaseService
        
        // Set auth gateway URL from constant if defined, otherwise use default
        $this->authGatewayUrl = defined('MPCC_AUTH_GATEWAY_URL') 
            ? MPCC_AUTH_GATEWAY_URL 
            : 'https://memberpress-auth-gateway-49bbf7ff52ea.herokuapp.com';
    }

    /**
     * Initialize the service (required by BaseService)
     *
     * @return void
     */
    public function init(): void
    {
        // LLMService doesn't need specific initialization
        // The parent constructor already initializes the logger
    }
    
    /**
     * Make a simple request to the AI service
     */
    public function generateContent(string $prompt, string $contentType = 'general', array $options = []): array
    {
        $provider = $this->getProviderForContentType($contentType);
        $model = $this->getModelForProvider($provider, $contentType);
        
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000
        ];
        
        $this->logger->debug('Making API request', [
            'endpoint' => $this->authGatewayUrl . '/v1/chat/completions',
            'model' => $model,
            'provider' => $provider,
            'payload' => $payload,
            'content_type' => $contentType
        ]);
        
        $response = wp_remote_post($this->authGatewayUrl . '/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::LICENSE_KEY,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => $options['timeout'] ?? 60
        ]);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('WordPress HTTP request failed', [
                'error_message' => $error_message,
                'endpoint' => $this->authGatewayUrl . '/v1/chat/completions',
                'model' => $model,
                'provider' => $provider
            ]);
            return [
                'error' => true,
                'message' => $error_message,
                'content' => ''
            ];
        }
        
        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        
        $this->logger->debug('API response received', [
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'model' => $model,
            'provider' => $provider
        ]);
        
        if ($responseCode >= 400) {
            $error_message = "API error {$responseCode}: {$responseBody}";
            $this->logger->error('API request failed', [
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'error_message' => $error_message,
                'model' => $model,
                'provider' => $provider
            ]);
            return [
                'error' => true,
                'message' => $error_message,
                'content' => ''
            ];
        }
        
        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Invalid JSON response: ' . json_last_error_msg();
            $this->logger->error('JSON parsing failed', [
                'error_message' => $error_message,
                'json_error' => json_last_error_msg(),
                'raw_response_body' => $responseBody,
                'model' => $model,
                'provider' => $provider
            ]);
            return [
                'error' => true,
                'message' => $error_message,
                'content' => ''
            ];
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            $error_message = 'Unexpected response format - no content in choices';
            $this->logger->error('Unexpected API response format', [
                'error_message' => $error_message,
                'response_structure' => $data,
                'model' => $model,
                'provider' => $provider
            ]);
            return [
                'error' => true,
                'message' => $error_message,
                'content' => ''
            ];
        }
        
        $this->logger->info('Content generated successfully', [
            'content_length' => strlen($data['choices'][0]['message']['content']),
            'model' => $model,
            'provider' => $provider,
            'content_type' => $contentType,
            'usage' => $data['usage'] ?? []
        ]);
        
        return [
            'error' => false,
            'content' => $data['choices'][0]['message']['content'],
            'usage' => $data['usage'] ?? [],
            'model' => $model,
            'provider' => $provider
        ];
    }
    
    /**
     * Determine course template type from user input
     */
    public function determineTemplateType(string $userInput): ?string
    {
        $prompt = "Analyze the following course description and determine which template type best fits:\n\nCourse Description: {$userInput}\n\nAvailable template types:\n- technical: Programming, software development, technical skills\n- business: Marketing, management, entrepreneurship, business skills\n- creative: Art, design, music, creative writing, visual arts\n- academic: Research, theory, formal education, scholarly content\n\nRespond with only the template type name (technical, business, creative, or academic). If none fit well, respond with 'general'.";
        
        $response = $this->generateContent($prompt, 'content_analysis', [
            'temperature' => 0.3,
            'max_tokens' => 100
        ]);
        
        if ($response['error']) {
            $this->logger->error('Failed to determine template type', [
                'error_message' => $response['message'],
                'user_input' => $userInput,
                'method' => 'determineTemplateType'
            ]);
            throw new \Exception('Failed to determine template type: ' . $response['message']);
        }
        
        return $this->parseTemplateResponse($response['content']);
    }
    
    /**
     * Extract course requirements from user message
     */
    public function extractCourseRequirements(string $message): array
    {
        $prompt = "Extract course requirements from this user message:\n\nMessage: {$message}\n\nExtract and format as JSON:\n{\n  \"title\": \"Course title\",\n  \"description\": \"Course description\",\n  \"difficulty_level\": \"beginner|intermediate|advanced\",\n  \"target_audience\": \"Who this course is for\",\n  \"learning_objectives\": [\"objective1\", \"objective2\"],\n  \"estimated_duration\": \"X hours/weeks\",\n  \"prerequisites\": [\"prerequisite1\", \"prerequisite2\"],\n  \"topics\": [\"topic1\", \"topic2\"]\n}\n\nRespond with only the JSON object.";
        
        $response = $this->generateContent($prompt, 'structured_analysis', [
            'temperature' => 0.2,
            'max_tokens' => 1500
        ]);
        
        if ($response['error']) {
            $this->logger->error('Failed to extract course requirements', [
                'error_message' => $response['message'],
                'user_message' => $message,
                'method' => 'extractCourseRequirements'
            ]);
            throw new \Exception('Failed to extract course requirements: ' . $response['message']);
        }
        
        $extracted = json_decode(trim($response['content']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($extracted)) {
            return array_merge($extracted, [
                'raw_input' => $message,
                'timestamp' => current_time('timestamp'),
                'extraction_method' => 'ai'
            ]);
        }
        
        $this->logger->error('Failed to parse AI response as JSON', [
            'ai_response' => $response['content'],
            'json_error' => json_last_error_msg(),
            'method' => 'extractCourseRequirements',
            'user_message' => $message
        ]);
        throw new \Exception('Failed to parse AI response as valid JSON');
    }
    
    /**
     * Generate lesson content with streaming support
     */
    public function generateLessonContentStream(string $sectionTitle, int $lessonNumber, array $requirements, callable $onChunk = null): string
    {
        $courseTitle = $requirements['course_title'] ?? 'Course';
        $lessonTitle = $requirements['lesson_title'] ?? "Lesson {$lessonNumber}";
        $difficulty = $requirements['difficulty_level'] ?? 'intermediate';
        $audience = $requirements['target_audience'] ?? 'general learners';
        
        $prompt = $this->buildLessonContentPrompt([
            'course_title' => $courseTitle,
            'section_title' => $sectionTitle,
            'lesson_number' => $lessonNumber,
            'lesson_title' => $lessonTitle,
            'difficulty' => $difficulty,
            'audience' => $audience,
            'course_context' => $requirements['course_context'] ?? '',
            'prerequisites' => $requirements['prerequisites'] ?? [],
            'learning_objectives' => $requirements['learning_objectives'] ?? []
        ]);
        
        // If streaming callback is provided, use streaming endpoint
        if ($onChunk !== null) {
            return $this->streamContent($prompt, 'lesson_content', $onChunk, [
                'temperature' => 0.7,
                'max_tokens' => 6000
            ]);
        }
        
        // Otherwise use regular generation
        return $this->generateLessonContent($sectionTitle, $lessonNumber, $requirements);
    }
    
    /**
     * Stream content generation with callback
     */
    private function streamContent(string $prompt, string $contentType, callable $onChunk, array $options = []): string
    {
        $provider = $this->getProviderForContentType($contentType);
        $model = $this->getModelForProvider($provider, $contentType);
        
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000,
            'stream' => true
        ];
        
        // For now, return a placeholder as streaming requires special handling
        // In production, this would use server-sent events or WebSockets
        $this->logger->info('Streaming content generation requested', [
            'content_type' => $contentType,
            'model' => $model
        ]);
        
        // Simulate streaming by calling the callback with chunks
        $fullContent = $this->generateContent($prompt, $contentType, $options);
        
        if (!$fullContent['error']) {
            $chunks = str_split($fullContent['content'], 100);
            foreach ($chunks as $chunk) {
                $onChunk($chunk);
                usleep(50000); // 50ms delay to simulate streaming
            }
        }
        
        return $fullContent['error'] ? '' : $fullContent['content'];
    }
    
    /**
     * Generate lesson content
     */
    public function generateLessonContent(string $sectionTitle, int $lessonNumber, array $requirements): string
    {
        $courseTitle = $requirements['course_title'] ?? 'Course';
        $lessonTitle = $requirements['lesson_title'] ?? "Lesson {$lessonNumber}";
        $difficulty = $requirements['difficulty_level'] ?? 'intermediate';
        $audience = $requirements['target_audience'] ?? 'general learners';
        $courseContext = $requirements['course_context'] ?? '';
        $prerequisites = $requirements['prerequisites'] ?? [];
        $learningObjectives = $requirements['learning_objectives'] ?? [];
        
        // Build a comprehensive prompt for educational content
        $prompt = $this->buildLessonContentPrompt([
            'course_title' => $courseTitle,
            'section_title' => $sectionTitle,
            'lesson_number' => $lessonNumber,
            'lesson_title' => $lessonTitle,
            'difficulty' => $difficulty,
            'audience' => $audience,
            'course_context' => $courseContext,
            'prerequisites' => $prerequisites,
            'learning_objectives' => $learningObjectives
        ]);
        
        $response = $this->generateContent($prompt, 'lesson_content', [
            'temperature' => 0.7,
            'max_tokens' => 6000
        ]);
        
        if ($response['error']) {
            $this->logger->error('Failed to generate lesson content', [
                'error_message' => $response['message'],
                'section_title' => $sectionTitle,
                'lesson_number' => $lessonNumber,
                'requirements' => $requirements,
                'method' => 'generateLessonContent'
            ]);
            throw new \Exception('Failed to generate lesson content: ' . $response['message']);
        }
        
        // Process and format the content
        $content = $this->formatLessonContent($response['content'], $requirements);
        
        // Log successful generation
        $this->logger->info('Lesson content generated successfully', [
            'section_title' => $sectionTitle,
            'lesson_number' => $lessonNumber,
            'lesson_title' => $lessonTitle,
            'content_length' => strlen($content),
            'model_used' => $response['model'] ?? 'unknown',
            'tokens_used' => $response['usage']['total_tokens'] ?? 0
        ]);
        
        return $content;
    }
    
    /**
     * Get provider for content type
     */
    private function getProviderForContentType(string $contentType): string
    {
        switch ($contentType) {
            case 'content_analysis':
            case 'lesson_content':
            case 'course_outline':
            case 'advanced_content':
            case 'case_studies':
            case 'project_content':
            case 'content_optimization':
            case 'personalized_content':
                return 'anthropic';
                
            case 'structured_analysis':
            case 'quiz_questions':
            case 'interactive_exercises':
            case 'assessment_rubric':
            case 'learning_activities':
                return 'openai';
                
            default:
                return 'anthropic';
        }
    }
    
    /**
     * Get model for provider and content type
     */
    private function getModelForProvider(string $provider, string $contentType): string
    {
        switch ($provider) {
            case 'anthropic':
                // Use Claude 3.5 Sonnet which is available on the LiteLLM proxy
                return 'claude-3-5-sonnet-20241022';
                
            case 'openai':
                if (in_array($contentType, ['structured_analysis', 'assessment_rubric'])) {
                    return 'gpt-4';
                } else {
                    return 'gpt-3.5-turbo';
                }
                
            default:
                return 'gpt-3.5-turbo'; // Safe default that's available
        }
    }
    
    /**
     * Parse template response
     */
    private function parseTemplateResponse(string $response): ?string
    {
        $response = trim(strtolower($response));
        $validTypes = ['technical', 'business', 'creative', 'academic', 'general'];
        
        foreach ($validTypes as $type) {
            if (strpos($response, $type) !== false) {
                return $type === 'general' ? null : $type;
            }
        }
        
        return null;
    }
    
    /**
     * Build a comprehensive prompt for lesson content generation
     */
    private function buildLessonContentPrompt(array $params): string
    {
        $prompt = "You are an expert educational content creator. Generate comprehensive, engaging lesson content with the following specifications:\n\n";
        
        // Course context
        $prompt .= "COURSE INFORMATION:\n";
        $prompt .= "- Course Title: {$params['course_title']}\n";
        $prompt .= "- Section: {$params['section_title']}\n";
        $prompt .= "- Lesson {$params['lesson_number']}: {$params['lesson_title']}\n";
        
        if (!empty($params['course_context'])) {
            $prompt .= "- Course Context: {$params['course_context']}\n";
        }
        
        $prompt .= "\nTARGET AUDIENCE:\n";
        $prompt .= "- Audience: {$params['audience']}\n";
        $prompt .= "- Difficulty Level: {$params['difficulty']}\n";
        
        if (!empty($params['prerequisites'])) {
            $prompt .= "- Prerequisites: " . implode(', ', $params['prerequisites']) . "\n";
        }
        
        if (!empty($params['learning_objectives'])) {
            $prompt .= "\nSPECIFIC LEARNING OBJECTIVES:\n";
            foreach ($params['learning_objectives'] as $objective) {
                $prompt .= "- {$objective}\n";
            }
        }
        
        $prompt .= "\nCONTENT REQUIREMENTS:\n";
        $prompt .= "Create educational content (200-500 words) that includes:\n\n";
        
        $prompt .= "1. **Introduction** (2-3 sentences)\n";
        $prompt .= "   - Hook the learner's attention\n";
        $prompt .= "   - Explain why this lesson matters\n";
        $prompt .= "   - Preview what they'll learn\n\n";
        
        $prompt .= "2. **Learning Objectives** (3-5 bullet points)\n";
        $prompt .= "   - Clear, measurable outcomes\n";
        $prompt .= "   - Use action verbs (understand, apply, create, analyze)\n\n";
        
        $prompt .= "3. **Core Content** (main body)\n";
        $prompt .= "   - Break down complex concepts into digestible parts\n";
        $prompt .= "   - Use clear headings and subheadings\n";
        $prompt .= "   - Include relevant examples\n";
        $prompt .= "   - Add practical applications\n\n";
        
        $prompt .= "4. **Examples & Applications**\n";
        $prompt .= "   - Real-world scenarios\n";
        $prompt .= "   - Industry-relevant examples\n";
        $prompt .= "   - Step-by-step walkthroughs when applicable\n\n";
        
        $prompt .= "5. **Key Takeaways** (3-5 bullet points)\n";
        $prompt .= "   - Summarize main concepts\n";
        $prompt .= "   - Reinforce learning objectives\n\n";
        
        $prompt .= "6. **Practice Activity** (optional)\n";
        $prompt .= "   - Simple exercise to apply the concepts\n";
        $prompt .= "   - Self-check questions\n\n";
        
        $prompt .= "WRITING GUIDELINES:\n";
        $prompt .= "- Use clear, conversational language appropriate for the {$params['difficulty']} level\n";
        $prompt .= "- Include practical examples and analogies\n";
        $prompt .= "- Maintain an encouraging, supportive tone\n";
        $prompt .= "- Format with proper Markdown (headings, lists, emphasis)\n";
        $prompt .= "- Ensure content flows logically from simple to complex\n";
        $prompt .= "- Make it engaging and interactive where possible\n";
        
        return $prompt;
    }
    
    /**
     * Generate quiz questions for a lesson
     */
    public function generateLessonQuiz(string $lessonTitle, string $lessonContent, array $options = []): array
    {
        $numQuestions = $options['num_questions'] ?? 5;
        $questionTypes = $options['question_types'] ?? ['multiple_choice', 'true_false'];
        $difficulty = $options['difficulty'] ?? 'intermediate';
        
        $prompt = "Based on the following lesson content, generate {$numQuestions} quiz questions.\n\n";
        $prompt .= "Lesson Title: {$lessonTitle}\n\n";
        $prompt .= "Lesson Content:\n{$lessonContent}\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Question types: " . implode(', ', $questionTypes) . "\n";
        $prompt .= "- Difficulty: {$difficulty}\n";
        $prompt .= "- Test understanding of key concepts\n";
        $prompt .= "- Include explanations for correct answers\n\n";
        $prompt .= "Format as JSON array with structure:\n";
        $prompt .= '{"questions": [{"type": "multiple_choice", "question": "...", "options": ["A", "B", "C", "D"], "correct": 0, "explanation": "..."}]}';
        
        $response = $this->generateContent($prompt, 'quiz_questions', [
            'temperature' => 0.3,
            'max_tokens' => 2000
        ]);
        
        if ($response['error']) {
            $this->logger->error('Failed to generate quiz questions', [
                'error' => $response['message'],
                'lesson_title' => $lessonTitle
            ]);
            return [];
        }
        
        $quiz = json_decode($response['content'], true);
        return $quiz['questions'] ?? [];
    }
    
    /**
     * Generate practice exercises for a lesson
     */
    public function generatePracticeExercises(string $lessonTitle, array $learningObjectives, array $options = []): string
    {
        $numExercises = $options['num_exercises'] ?? 3;
        $exerciseType = $options['exercise_type'] ?? 'hands-on';
        $difficulty = $options['difficulty'] ?? 'intermediate';
        
        $prompt = "Create {$numExercises} practice exercises for the lesson: {$lessonTitle}\n\n";
        $prompt .= "Learning Objectives:\n";
        foreach ($learningObjectives as $objective) {
            $prompt .= "- {$objective}\n";
        }
        $prompt .= "\nExercise Requirements:\n";
        $prompt .= "- Type: {$exerciseType} exercises\n";
        $prompt .= "- Difficulty: {$difficulty}\n";
        $prompt .= "- Include clear instructions\n";
        $prompt .= "- Provide example solutions or hints\n";
        $prompt .= "- Make exercises progressively challenging\n";
        $prompt .= "- Include real-world applications where possible\n";
        
        $response = $this->generateContent($prompt, 'interactive_exercises', [
            'temperature' => 0.7,
            'max_tokens' => 3000
        ]);
        
        if ($response['error']) {
            $this->logger->error('Failed to generate practice exercises', [
                'error' => $response['message'],
                'lesson_title' => $lessonTitle
            ]);
            throw new \Exception('Failed to generate practice exercises: ' . $response['message']);
        }
        
        return $this->formatExercises($response['content']);
    }
    
    /**
     * Generate a lesson summary
     */
    public function generateLessonSummary(string $lessonContent, array $options = []): string
    {
        $summaryLength = $options['length'] ?? 'medium'; // short, medium, long
        $includeKeyPoints = $options['include_key_points'] ?? true;
        $includeNextSteps = $options['include_next_steps'] ?? true;
        
        $prompt = "Create a {$summaryLength} summary of the following lesson content:\n\n";
        $prompt .= $lessonContent . "\n\n";
        $prompt .= "Summary requirements:\n";
        
        if ($includeKeyPoints) {
            $prompt .= "- Include 3-5 key takeaways as bullet points\n";
        }
        
        if ($includeNextSteps) {
            $prompt .= "- Suggest next steps for learners\n";
        }
        
        $prompt .= "- Use clear, concise language\n";
        $prompt .= "- Reinforce main concepts\n";
        $prompt .= "- Maintain an encouraging tone\n";
        
        $response = $this->generateContent($prompt, 'content_analysis', [
            'temperature' => 0.5,
            'max_tokens' => 1000
        ]);
        
        if ($response['error']) {
            $this->logger->error('Failed to generate lesson summary', [
                'error' => $response['message']
            ]);
            return "Unable to generate summary.";
        }
        
        return trim($response['content']);
    }
    
    /**
     * Format practice exercises for display
     */
    private function formatExercises(string $content): string
    {
        // Ensure exercises are properly formatted with markdown
        $content = trim($content);
        
        // Add exercise header if not present
        if (!str_starts_with($content, '#')) {
            $content = "## Practice Exercises\n\n" . $content;
        }
        
        // Ensure proper numbering for exercises
        $content = preg_replace('/Exercise (\d+):/i', '### Exercise $1:', $content);
        
        return $content;
    }
    
    /**
     * Format lesson content for proper display
     */
    private function formatLessonContent(string $content, array $requirements): string
    {
        // Ensure proper markdown formatting
        $content = trim($content);
        
        // Add lesson metadata header if not present
        if (!str_starts_with($content, '#')) {
            $lessonTitle = $requirements['lesson_title'] ?? 'Lesson';
            $content = "# {$lessonTitle}\n\n" . $content;
        }
        
        // Ensure proper spacing between sections
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // Add navigation hints if this is part of a course
        if (!empty($requirements['add_navigation'])) {
            $navigation = "\n\n---\n\n";
            
            if (!empty($requirements['previous_lesson'])) {
                $navigation .= "← Previous: {$requirements['previous_lesson']} | ";
            }
            
            $navigation .= "**Current: {$requirements['lesson_title']}**";
            
            if (!empty($requirements['next_lesson'])) {
                $navigation .= " | Next: {$requirements['next_lesson']} →";
            }
            
            $content .= $navigation;
        }
        
        // Validate content length
        $wordCount = str_word_count(strip_tags($content));
        if ($wordCount < 200) {
            $this->logger->warning('Generated lesson content is too short', [
                'word_count' => $wordCount,
                'lesson_title' => $requirements['lesson_title'] ?? 'Unknown'
            ]);
        } elseif ($wordCount > 500) {
            $this->logger->info('Generated lesson content exceeds target length', [
                'word_count' => $wordCount,
                'lesson_title' => $requirements['lesson_title'] ?? 'Unknown'
            ]);
        }
        
        return $content;
    }
    
    /**
     * Send a message to the LLM and get a response (ILLMService interface)
     *
     * @param string $message The user message
     * @param array $conversationHistory Previous messages in the conversation
     * @return array Response from the LLM
     */
    public function sendMessage(string $message, array $conversationHistory = []): array
    {
        $messages = $conversationHistory;
        $messages[] = ['role' => 'user', 'content' => $message];
        
        $provider = $this->getProviderForContentType('general');
        $model = $this->getModelForProvider($provider, 'general');
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];
        
        $response = wp_remote_post($this->authGatewayUrl . '/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::LICENSE_KEY,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 60
        ]);
        
        if (is_wp_error($response)) {
            return [
                'error' => true,
                'message' => $response->get_error_message(),
                'content' => ''
            ];
        }
        
        $responseBody = wp_remote_retrieve_body($response);
        $data = json_decode($responseBody, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return [
                'error' => true,
                'message' => 'Unexpected response format',
                'content' => ''
            ];
        }
        
        return [
            'error' => false,
            'content' => $data['choices'][0]['message']['content'],
            'usage' => $data['usage'] ?? [],
            'model' => $model
        ];
    }
    
    /**
     * Generate a course based on the provided parameters (ILLMService interface)
     *
     * @param array $courseData Course generation parameters
     * @return array Generated course content
     */
    public function generateCourse(array $courseData): array
    {
        try {
            // Extract requirements from course data
            $requirements = $this->extractCourseRequirements($courseData['description'] ?? '');
            
            // Determine template type
            $templateType = $this->determineTemplateType($courseData['description'] ?? '');
            
            // Generate course outline
            $prompt = "Create a comprehensive course outline for: {$courseData['title']}\n\n";
            $prompt .= "Description: {$courseData['description']}\n";
            $prompt .= "Target Audience: {$requirements['target_audience']}\n";
            $prompt .= "Difficulty: {$requirements['difficulty_level']}\n\n";
            $prompt .= "Generate a structured course with sections and lessons.";
            
            $response = $this->generateContent($prompt, 'course_outline', [
                'temperature' => 0.7,
                'max_tokens' => 4000
            ]);
            
            if ($response['error']) {
                return [
                    'error' => true,
                    'message' => $response['message']
                ];
            }
            
            return [
                'error' => false,
                'content' => $response['content'],
                'requirements' => $requirements,
                'template_type' => $templateType
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate course', [
                'error' => $e->getMessage(),
                'course_data' => $courseData
            ]);
            
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }
    
}