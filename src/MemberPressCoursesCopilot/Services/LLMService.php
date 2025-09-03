<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Interfaces\ILLMService;
use MemberPressCoursesCopilot\Utilities\ApiResponse;
use WP_Error;

/**
 * LLM Service
 *
 * Provides a secure interface to AI language models through an authentication gateway.
 * This service handles all AI-related operations including content generation,
 * course structure creation, and lesson content writing.
 *
 * Features:
 * - Secure API key management (keys stored on gateway, not in plugin)
 * - Intelligent model routing based on content type
 * - Automatic retry logic for failed requests
 * - Comprehensive error handling and logging
 *
 * @package MemberPressCoursesCopilot\Services
 * @since   1.0.0
 */
class LLMService extends BaseService implements ILLMService
{
    // Auth gateway URL - can be overridden via wp-config.php constant.
    private string $authGatewayUrl;

    // License key for authentication with the gateway.
    // In production, this should come from MemberPress license system.
    // See /docs/todo/LICENSING_IMPLEMENTATION.md for implementation details
    private const LICENSE_KEY = 'dev-license-key-001'; // Placeholder - not a real credential.

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(); // Initialize logger from BaseService.

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
     * Generate content using AI language models
     *
     * Routes requests to the appropriate AI model based on content type and
     * sends them through the authentication gateway for processing.
     *
     * @param string $prompt      The text prompt to send to the AI
     * @param string $contentType Type of content being generated (e.g., 'course_structure', 'lesson_content')
     * @param array  $options     Additional options including:
     *                           - temperature (float): Creativity level 0-1, default 0.7
     *                           - max_tokens (int): Maximum response length, default 2000
     *                           - timeout (int): Request timeout in seconds, default 60
     *                           - messages (array): Full conversation history for chat completions
     *
     * @return array Response array with keys:
     *               - success (bool): Whether the request succeeded
     *               - content (string): The generated content
     *               - error (bool): Whether an error occurred
     *               - message (string): Error message if applicable
     *               - usage (array): Token usage statistics
     *
     * @throws \Exception When gateway URL is not configured
     *
     * @example
     * // Generate course structure with specific temperature
     * $llmService = new LLMService();
     * $response = $llmService->generateContent(
     *     'Create a comprehensive course outline for advanced PHP programming',
     *     'course_structure',
     *     ['temperature' => 0.5, 'max_tokens' => 3000]
     * );
     * if (!$response['error']) {
     *     echo $response['content']; // Generated course outline
     *     echo "Tokens used: " . $response['usage']['total_tokens'];
     * }
     *
     * @example
     * // Generate lesson content with conversation context
     * $conversationHistory = [
     *     ['role' => 'assistant', 'content' => 'What topic would you like to cover?'],
     *     ['role' => 'user', 'content' => 'I want to teach about PHP arrays']
     * ];
     * $response = $llmService->generateContent(
     *     'Create detailed lesson content about PHP arrays including examples',
     *     'lesson_content',
     *     [
     *         'temperature' => 0.7,
     *         'max_tokens' => 4000,
     *         'messages' => $conversationHistory
     *     ]
     * );
     *
     * @example
     * // Handle errors with timeout
     * try {
     *     $response = $llmService->generateContent(
     *         'Generate a quiz about machine learning',
     *         'quiz_questions',
     *         ['timeout' => 30] // 30 second timeout
     *     );
     *
     *     if ($response['error']) {
     *         error_log('LLM Error: ' . $response['message']);
     *         return false;
     *     }
     * } catch (Exception $e) {
     *     error_log('LLM Exception: ' . $e->getMessage());
     * }
     *
     * @example
     * // Different content types use different models
     * $responses = [];
     * $responses['structure'] = $llmService->generateContent('Course outline', 'course_structure'); // Uses Claude
     * $responses['quiz'] = $llmService->generateContent('Quiz questions', 'quiz_questions'); // Uses GPT-4
     * $responses['content'] = $llmService->generateContent('Lesson content', 'lesson_content'); // Uses Claude
     */
    public function generateContent(string $prompt, string $contentType = 'general', array $options = []): array
    {
        $provider = $this->getProviderForContentType($contentType);
        $model    = $this->getModelForProvider($provider, $contentType);

        $payload = [
            'model'       => $model,
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['max_tokens'] ?? 2000,
        ];

        $this->logger->debug('Making API request', [
            'endpoint'     => $this->authGatewayUrl . '/v1/chat/completions',
            'model'        => $model,
            'provider'     => $provider,
            'payload'      => $payload,
            'content_type' => $contentType,
        ]);

        $response = wp_remote_post($this->authGatewayUrl . '/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::LICENSE_KEY,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($payload),
            'timeout' => $options['timeout'] ?? 60,
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->logger->error('WordPress HTTP request failed', [
                'error_message' => $error_message,
                'endpoint'      => $this->authGatewayUrl . '/v1/chat/completions',
                'model'         => $model,
                'provider'      => $provider,
            ]);
            return [
                'error'   => true,
                'message' => $error_message,
                'content' => '',
            ];
        }

        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        $this->logger->debug('API response received', [
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'model'         => $model,
            'provider'      => $provider,
        ]);

        if ($responseCode >= 400) {
            $error_message = "API error {$responseCode}: {$responseBody}";
            $this->logger->error('API request failed', [
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'error_message' => $error_message,
                'model'         => $model,
                'provider'      => $provider,
            ]);
            return [
                'error'   => true,
                'message' => $error_message,
                'content' => '',
            ];
        }

        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Invalid JSON response: ' . json_last_error_msg();
            $this->logger->error('JSON parsing failed', [
                'error_message'     => $error_message,
                'json_error'        => json_last_error_msg(),
                'raw_response_body' => $responseBody,
                'model'             => $model,
                'provider'          => $provider,
            ]);
            return [
                'error'   => true,
                'message' => $error_message,
                'content' => '',
            ];
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            $error_message = 'Unexpected response format - no content in choices';
            $this->logger->error('Unexpected API response format', [
                'error_message'      => $error_message,
                'response_structure' => $data,
                'model'              => $model,
                'provider'           => $provider,
            ]);
            return [
                'error'   => true,
                'message' => $error_message,
                'content' => '',
            ];
        }

        $this->logger->info('Content generated successfully', [
            'content_length' => strlen($data['choices'][0]['message']['content']),
            'model'          => $model,
            'provider'       => $provider,
            'content_type'   => $contentType,
            'usage'          => $data['usage'] ?? [],
        ]);

        return [
            'error'    => false,
            'content'  => $data['choices'][0]['message']['content'],
            'usage'    => $data['usage'] ?? [],
            'model'    => $model,
            'provider' => $provider,
        ];
    }

    /**
     * Determine course template type from user input
     *
     * @param  string $userInput User's course description or input
     * @return string|null Template type or null for general template
     *
     * @example
     * // Analyze technical course description
     * $llmService = new LLMService();
     * $templateType = $llmService->determineTemplateType(
     *     'Create a course about JavaScript programming and web development'
     * );
     * // Returns: 'technical'
     *
     * @example
     * // Analyze business course description
     * $templateType = $llmService->determineTemplateType(
     *     'I want to teach people about digital marketing strategies and customer acquisition'
     * );
     * // Returns: 'business'
     *
     * @example
     * // Handle ambiguous descriptions
     * $templateType = $llmService->determineTemplateType(
     *     'A course about general life skills'
     * );
     * // Returns: null (falls back to general template)
     *
     * @example
     * // Error handling
     * try {
     *     $templateType = $llmService->determineTemplateType($userInput);
     *     if ($templateType) {
     *         echo "Using template: " . $templateType;
     *     } else {
     *         echo "Using general template";
     *     }
     * } catch (Exception $e) {
     *     error_log('Template determination failed: ' . $e->getMessage());
     *     $templateType = null; // Fallback to general
     * }
     */
    public function determineTemplateType(string $userInput): ?string
    {
        $prompt = "Analyze the following course description and determine which template type best fits:\n\nCourse Description: {$userInput}\n\nAvailable template types:\n- technical: Programming, software development, technical skills\n- business: Marketing, management, entrepreneurship, business skills\n- creative: Art, design, music, creative writing, visual arts\n- academic: Research, theory, formal education, scholarly content\n\nRespond with only the template type name (technical, business, creative, or academic). If none fit well, respond with 'general'.";

        $response = $this->generateContent($prompt, 'content_analysis', [
            'temperature' => 0.3,
            'max_tokens'  => 100,
        ]);

        if ($response['error']) {
            $this->logger->error('Failed to determine template type', [
                'error_message' => $response['message'],
                'user_input'    => $userInput,
                'method'        => 'determineTemplateType',
            ]);
            throw new \Exception('Failed to determine template type: ' . $response['message']);
        }

        return $this->parseTemplateResponse($response['content']);
    }

    /**
     * Extract course requirements from user message
     *
     * @param  string $message User's course description message
     * @return array Extracted course requirements
     * @throws \Exception When extraction fails
     *
     * @example
     * // Extract requirements from detailed course description
     * $llmService = new LLMService();
     * $requirements = $llmService->extractCourseRequirements(
     *     'I want to create a beginner-friendly course about React.js for new developers. ' .
     *     'It should cover components, hooks, and state management. ' .
     *     'The course should take about 20 hours and require basic JavaScript knowledge.'
     * );
     * // Returns:
     * // [
     * //     'title' => 'React.js for Beginners',
     * //     'description' => 'A comprehensive introduction to React.js...',
     * //     'difficulty_level' => 'beginner',
     * //     'target_audience' => 'New developers',
     * //     'learning_objectives' => ['Learn React components', 'Understand hooks', 'Master state management'],
     * //     'estimated_duration' => '20 hours',
     * //     'prerequisites' => ['Basic JavaScript knowledge'],
     * //     'topics' => ['React components', 'Hooks', 'State management'],
     * //     'raw_input' => $message,
     * //     'timestamp' => 1234567890,
     * //     'extraction_method' => 'ai'
     * // ]
     *
     * @example
     * // Extract from minimal input
     * $requirements = $llmService->extractCourseRequirements('Python for data science');
     * // Returns structured data with AI-inferred details
     *
     * @example
     * // Handle extraction errors
     * try {
     *     $requirements = $llmService->extractCourseRequirements($userMessage);
     *     $courseTitle = $requirements['title'];
     *     $difficulty = $requirements['difficulty_level'];
     * } catch (Exception $e) {
     *     error_log('Course requirement extraction failed: ' . $e->getMessage());
     *     // Provide fallback requirements
     *     $requirements = [
     *         'title' => 'Custom Course',
     *         'difficulty_level' => 'intermediate',
     *         'target_audience' => 'General learners'
     *     ];
     * }
     */
    public function extractCourseRequirements(string $message): array
    {
        $prompt = "Extract course requirements from this user message:\n\nMessage: {$message}\n\nExtract and format as JSON:\n{\n  \"title\": \"Course title\",\n  \"description\": \"Course description\",\n  \"difficulty_level\": \"beginner|intermediate|advanced\",\n  \"target_audience\": \"Who this course is for\",\n  \"learning_objectives\": [\"objective1\", \"objective2\"],\n  \"estimated_duration\": \"X hours/weeks\",\n  \"prerequisites\": [\"prerequisite1\", \"prerequisite2\"],\n  \"topics\": [\"topic1\", \"topic2\"]\n}\n\nRespond with only the JSON object.";

        $response = $this->generateContent($prompt, 'structured_analysis', [
            'temperature' => 0.2,
            'max_tokens'  => 1500,
        ]);

        if ($response['error']) {
            $this->logger->error('Failed to extract course requirements', [
                'error_message' => $response['message'],
                'user_message'  => $message,
                'method'        => 'extractCourseRequirements',
            ]);
            throw new \Exception('Failed to extract course requirements: ' . $response['message']);
        }

        $extracted = json_decode(trim($response['content']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($extracted)) {
            return array_merge($extracted, [
                'raw_input'         => $message,
                'timestamp'         => current_time('timestamp'),
                'extraction_method' => 'ai',
            ]);
        }

        $this->logger->error('Failed to parse AI response as JSON', [
            'ai_response'  => $response['content'],
            'json_error'   => json_last_error_msg(),
            'method'       => 'extractCourseRequirements',
            'user_message' => $message,
        ]);
        throw new \Exception('Failed to parse AI response as valid JSON');
    }

    /**
     * Generate lesson content with streaming support
     *
     * @param  string        $sectionTitle Section title for context
     * @param  integer       $lessonNumber Lesson number in sequence
     * @param  array         $requirements Course and lesson requirements
     * @param  callable|null $onChunk      Optional callback for streaming chunks
     * @return string Generated lesson content
     *
     * @example
     * // Generate lesson content without streaming
     * $llmService = new LLMService();
     * $requirements = [
     *     'course_title' => 'Advanced PHP Programming',
     *     'lesson_title' => 'Object-Oriented Programming',
     *     'difficulty_level' => 'intermediate',
     *     'target_audience' => 'PHP developers',
     *     'learning_objectives' => ['Understand OOP concepts', 'Create classes and objects'],
     *     'prerequisites' => ['Basic PHP knowledge']
     * ];
     * $content = $llmService->generateLessonContentStream(
     *     'PHP Fundamentals',
     *     3,
     *     $requirements
     * );
     * // Returns: Complete lesson content as string
     *
     * @example
     * // Generate with streaming callback
     * $content = $llmService->generateLessonContentStream(
     *     'Web Development Basics',
     *     1,
     *     $requirements,
     *     function($chunk) {
     *         echo $chunk; // Output each chunk as it's generated
     *         flush();     // Send to browser immediately
     *     }
     * );
     *
     * @example
     * // Generate with full context
     * $requirements = [
     *     'course_title' => 'React Development Bootcamp',
     *     'lesson_title' => 'Component Lifecycle',
     *     'difficulty_level' => 'advanced',
     *     'target_audience' => 'Experienced JavaScript developers',
     *     'course_context' => 'This bootcamp covers modern React development patterns',
     *     'prerequisites' => ['ES6 JavaScript', 'HTML/CSS', 'Basic React knowledge'],
     *     'learning_objectives' => [
     *         'Understand component lifecycle methods',
     *         'Implement useEffect hooks properly',
     *         'Optimize component performance'
     *     ]
     * ];
     * $content = $llmService->generateLessonContentStream('Advanced React', 5, $requirements);
     */
    public function generateLessonContentStream(string $sectionTitle, int $lessonNumber, array $requirements, callable $onChunk = null): string
    {
        $courseTitle = $requirements['course_title'] ?? 'Course';
        $lessonTitle = $requirements['lesson_title'] ?? "Lesson {$lessonNumber}";
        $difficulty  = $requirements['difficulty_level'] ?? 'intermediate';
        $audience    = $requirements['target_audience'] ?? 'general learners';

        $prompt = $this->buildLessonContentPrompt([
            'course_title'        => $courseTitle,
            'section_title'       => $sectionTitle,
            'lesson_number'       => $lessonNumber,
            'lesson_title'        => $lessonTitle,
            'difficulty'          => $difficulty,
            'audience'            => $audience,
            'course_context'      => $requirements['course_context'] ?? '',
            'prerequisites'       => $requirements['prerequisites'] ?? [],
            'learning_objectives' => $requirements['learning_objectives'] ?? [],
        ]);

        // If streaming callback is provided, use streaming endpoint
        if ($onChunk !== null) {
            return $this->streamContent($prompt, 'lesson_content', $onChunk, [
                'temperature' => 0.7,
                'max_tokens'  => 6000,
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
        $model    = $this->getModelForProvider($provider, $contentType);

        $payload = [
            'model'       => $model,
            'messages'    => [
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['max_tokens'] ?? 2000,
            'stream'      => true,
        ];

        // For now, return a placeholder as streaming requires special handling
        // In production, this would use server-sent events or WebSockets
        $this->logger->info('Streaming content generation requested', [
            'content_type' => $contentType,
            'model'        => $model,
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
     *
     * @param  string  $sectionTitle Section title for context
     * @param  integer $lessonNumber Lesson number in sequence
     * @param  array   $requirements Course and lesson requirements
     * @return string Generated lesson content
     * @throws \Exception When content generation fails
     *
     * @example
     * // Generate standard lesson content
     * $llmService = new LLMService();
     * $requirements = [
     *     'course_title' => 'Introduction to Python',
     *     'lesson_title' => 'Variables and Data Types',
     *     'difficulty_level' => 'beginner',
     *     'target_audience' => 'Programming newcomers',
     *     'learning_objectives' => ['Understand variables', 'Use different data types'],
     *     'prerequisites' => ['Basic computer literacy']
     * ];
     * $content = $llmService->generateLessonContent('Python Basics', 2, $requirements);
     * // Returns: Formatted lesson content with headings, examples, and exercises
     *
     * @example
     * // Generate with extensive context
     * $requirements = [
     *     'course_title' => 'Full Stack Web Development',
     *     'lesson_title' => 'REST API Design',
     *     'difficulty_level' => 'advanced',
     *     'target_audience' => 'Experienced developers',
     *     'course_context' => 'Building modern web applications with React and Node.js',
     *     'prerequisites' => ['JavaScript ES6+', 'Node.js basics', 'Database concepts'],
     *     'learning_objectives' => [
     *         'Design RESTful APIs',
     *         'Implement proper HTTP methods',
     *         'Handle authentication and authorization',
     *         'Optimize API performance'
     *     ]
     * ];
     * $content = $llmService->generateLessonContent('Backend Development', 8, $requirements);
     *
     * @example
     * // Error handling in course generation workflow
     * try {
     *     $content = $llmService->generateLessonContent($sectionTitle, $lessonNum, $requirements);
     *
     *     // Validate content length
     *     if (strlen($content) < 500) {
     *         throw new Exception('Generated content too short');
     *     }
     *
     *     return $content;
     * } catch (Exception $e) {
     *     $this->logger->error('Lesson generation failed', [
     *         'section' => $sectionTitle,
     *         'lesson_number' => $lessonNum,
     *         'error' => $e->getMessage()
     *     ]);
     *     throw $e;
     * }
     */
    public function generateLessonContent(string $sectionTitle, int $lessonNumber, array $requirements): string
    {
        $courseTitle        = $requirements['course_title'] ?? 'Course';
        $lessonTitle        = $requirements['lesson_title'] ?? "Lesson {$lessonNumber}";
        $difficulty         = $requirements['difficulty_level'] ?? 'intermediate';
        $audience           = $requirements['target_audience'] ?? 'general learners';
        $courseContext      = $requirements['course_context'] ?? '';
        $prerequisites      = $requirements['prerequisites'] ?? [];
        $learningObjectives = $requirements['learning_objectives'] ?? [];

        // Build a comprehensive prompt for educational content
        $prompt = $this->buildLessonContentPrompt([
            'course_title'        => $courseTitle,
            'section_title'       => $sectionTitle,
            'lesson_number'       => $lessonNumber,
            'lesson_title'        => $lessonTitle,
            'difficulty'          => $difficulty,
            'audience'            => $audience,
            'course_context'      => $courseContext,
            'prerequisites'       => $prerequisites,
            'learning_objectives' => $learningObjectives,
        ]);

        $response = $this->generateContent($prompt, 'lesson_content', [
            'temperature' => 0.7,
            'max_tokens'  => 6000,
        ]);

        if ($response['error']) {
            $this->logger->error('Failed to generate lesson content', [
                'error_message' => $response['message'],
                'section_title' => $sectionTitle,
                'lesson_number' => $lessonNumber,
                'requirements'  => $requirements,
                'method'        => 'generateLessonContent',
            ]);
            throw new \Exception('Failed to generate lesson content: ' . $response['message']);
        }

        // Process and format the content
        $content = $this->formatLessonContent($response['content'], $requirements);

        // Log successful generation
        $this->logger->info('Lesson content generated successfully', [
            'section_title'  => $sectionTitle,
            'lesson_number'  => $lessonNumber,
            'lesson_title'   => $lessonTitle,
            'content_length' => strlen($content),
            'model_used'     => $response['model'] ?? 'unknown',
            'tokens_used'    => $response['usage']['total_tokens'] ?? 0,
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
        $response   = trim(strtolower($response));
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
            $prompt .= '- Prerequisites: ' . implode(', ', $params['prerequisites']) . "\n";
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
     *
     * @param  string $lessonTitle   Title of the lesson
     * @param  string $lessonContent Content to generate questions from
     * @param  array  $options       Generation options
     * @return array Array of generated questions
     *
     * @example
     * // Generate multiple choice questions
     * $llmService = new LLMService();
     * $questions = $llmService->generateLessonQuiz(
     *     'PHP Variables',
     *     'Variables in PHP are used to store data. PHP variables start with $ sign...',
     *     [
     *         'num_questions' => 5,
     *         'question_types' => ['multiple_choice'],
     *         'difficulty' => 'beginner'
     *     ]
     * );
     * // Returns:
     * // [
     * //     {
     * //         'type' => 'multiple_choice',
     * //         'question' => 'How do PHP variables start?',
     * //         'options' => ['$', '@', '#', '%'],
     * //         'correct' => 0,
     * //         'explanation' => 'PHP variables always start with the $ symbol'
     * //     },
     * //     ...
     * // ]
     *
     * @example
     * // Generate mixed question types
     * $questions = $llmService->generateLessonQuiz(
     *     'JavaScript Fundamentals',
     *     $lessonContent,
     *     [
     *         'num_questions' => 8,
     *         'question_types' => ['multiple_choice', 'true_false'],
     *         'difficulty' => 'intermediate'
     *     ]
     * );
     *
     * @example
     * // Handle generation errors
     * try {
     *     $questions = $llmService->generateLessonQuiz($title, $content, $options);
     *     if (empty($questions)) {
     *         throw new Exception('No questions generated');
     *     }
     *     return $questions;
     * } catch (Exception $e) {
     *     error_log('Quiz generation failed: ' . $e->getMessage());
     *     return [];
     * }
     */
    public function generateLessonQuiz(string $lessonTitle, string $lessonContent, array $options = []): array
    {
        $numQuestions  = $options['num_questions'] ?? 5;
        $questionTypes = $options['question_types'] ?? ['multiple_choice', 'true_false'];
        $difficulty    = $options['difficulty'] ?? 'intermediate';

        $prompt  = "Based on the following lesson content, generate {$numQuestions} quiz questions.\n\n";
        $prompt .= "Lesson Title: {$lessonTitle}\n\n";
        $prompt .= "Lesson Content:\n{$lessonContent}\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= '- Question types: ' . implode(', ', $questionTypes) . "\n";
        $prompt .= "- Difficulty: {$difficulty}\n";
        $prompt .= "- Test understanding of key concepts\n";
        $prompt .= "- Include explanations for correct answers\n\n";
        $prompt .= "Format as JSON array with structure:\n";
        $prompt .= '{"questions": [{"type": "multiple_choice", "question": "...", "options": ["A", "B", "C", "D"], "correct": 0, "explanation": "..."}]}';

        $response = $this->generateContent($prompt, 'quiz_questions', [
            'temperature' => 0.3,
            'max_tokens'  => 2000,
        ]);

        if ($response['error']) {
            $this->logger->error('Failed to generate quiz questions', [
                'error'        => $response['message'],
                'lesson_title' => $lessonTitle,
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
        $difficulty   = $options['difficulty'] ?? 'intermediate';

        $prompt  = "Create {$numExercises} practice exercises for the lesson: {$lessonTitle}\n\n";
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
            'max_tokens'  => 3000,
        ]);

        if ($response['error']) {
            $this->logger->error('Failed to generate practice exercises', [
                'error'        => $response['message'],
                'lesson_title' => $lessonTitle,
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
        $summaryLength    = $options['length'] ?? 'medium'; // short, medium, long
        $includeKeyPoints = $options['include_key_points'] ?? true;
        $includeNextSteps = $options['include_next_steps'] ?? true;

        $prompt  = "Create a {$summaryLength} summary of the following lesson content:\n\n";
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
            'max_tokens'  => 1000,
        ]);

        if ($response['error']) {
            $this->logger->error('Failed to generate lesson summary', [
                'error' => $response['message'],
            ]);
            return 'Unable to generate summary.';
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
            $content     = "# {$lessonTitle}\n\n" . $content;
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
                'word_count'   => $wordCount,
                'lesson_title' => $requirements['lesson_title'] ?? 'Unknown',
            ]);
        } elseif ($wordCount > 500) {
            $this->logger->info('Generated lesson content exceeds target length', [
                'word_count'   => $wordCount,
                'lesson_title' => $requirements['lesson_title'] ?? 'Unknown',
            ]);
        }

        return $content;
    }

    /**
     * Send a message to the LLM and get a response (ILLMService interface)
     *
     * @param  string $message             The user message
     * @param  array  $conversationHistory Previous messages in the conversation
     * @return array Response from the LLM
     *
     * @example
     * // Start a new conversation
     * $llmService = new LLMService();
     * $response = $llmService->sendMessage(
     *     'Help me create a course about web development',
     *     []
     * );
     * if (!$response['error']) {
     *     echo $response['content']; // AI assistant response
     *     echo "Model used: " . $response['model'];
     * }
     *
     * @example
     * // Continue an existing conversation
     * $conversationHistory = [
     *     ['role' => 'user', 'content' => 'I want to create a course'],
     *     ['role' => 'assistant', 'content' => 'What subject would you like to teach?'],
     *     ['role' => 'user', 'content' => 'JavaScript programming']
     * ];
     * $response = $llmService->sendMessage(
     *     'Make it focus on modern ES6+ features',
     *     $conversationHistory
     * );
     *
     * @example
     * // Handle conversation in a loop
     * $history = [];
     * $userMessages = ['Hello', 'I want to learn PHP', 'Focus on web development'];
     *
     * foreach ($userMessages as $message) {
     *     $response = $llmService->sendMessage($message, $history);
     *
     *     if (!$response['error']) {
     *         // Add user message and response to history
     *         $history[] = ['role' => 'user', 'content' => $message];
     *         $history[] = ['role' => 'assistant', 'content' => $response['content']];
     *
     *         echo "User: " . $message . "\\n";
     *         echo "AI: " . $response['content'] . "\\n\\n";
     *     }
     * }
     */
    public function sendMessage(string $message, array $conversationHistory = []): array
    {
        $messages   = $conversationHistory;
        $messages[] = [
            'role'    => 'user',
            'content' => $message,
        ];

        $provider = $this->getProviderForContentType('general');
        $model    = $this->getModelForProvider($provider, 'general');

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.7,
            'max_tokens'  => 2000,
        ];

        $response = wp_remote_post($this->authGatewayUrl . '/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::LICENSE_KEY,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($payload),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return [
                'error'   => true,
                'message' => $response->get_error_message(),
                'content' => '',
            ];
        }

        $responseBody = wp_remote_retrieve_body($response);
        $data         = json_decode($responseBody, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            return [
                'error'   => true,
                'message' => 'Unexpected response format',
                'content' => '',
            ];
        }

        return [
            'error'   => false,
            'content' => $data['choices'][0]['message']['content'],
            'usage'   => $data['usage'] ?? [],
            'model'   => $model,
        ];
    }

    /**
     * Generate a course based on the provided parameters (ILLMService interface)
     *
     * @param  array $courseData Course generation parameters
     * @return array Generated course content
     *
     * @example
     * // Generate a complete course
     * $llmService = new LLMService();
     * $courseData = [
     *     'title' => 'Modern JavaScript Development',
     *     'description' => 'Learn modern JavaScript including ES6+, async programming, and frameworks'
     * ];
     * $result = $llmService->generateCourse($courseData);
     *
     * if (!$result['error']) {
     *     echo "Generated course content: " . $result['content'];
     *     echo "Template type: " . $result['template_type'];
     *     $requirements = $result['requirements'];
     *     echo "Target audience: " . $requirements['target_audience'];
     *     echo "Difficulty: " . $requirements['difficulty_level'];
     * }
     *
     * @example
     * // Generate course with minimal data
     * $result = $llmService->generateCourse([
     *     'title' => 'Python Basics',
     *     'description' => 'Introduction to Python programming'
     * ]);
     * // AI will infer missing details like target audience, difficulty level
     *
     * @example
     * // Error handling in course generation
     * try {
     *     $result = $llmService->generateCourse($courseData);
     *
     *     if ($result['error']) {
     *         throw new Exception($result['message']);
     *     }
     *
     *     // Process successful generation
     *     $courseContent = $result['content'];
     *     $extractedRequirements = $result['requirements'];
     *
     * } catch (Exception $e) {
     *     error_log('Course generation failed: ' . $e->getMessage());
     *     return ['error' => true, 'message' => 'Failed to generate course'];
     * }
     */
    public function generateCourse(array $courseData): array
    {
        try {
            // Extract requirements from course data
            $requirements = $this->extractCourseRequirements($courseData['description'] ?? '');

            // Determine template type
            $templateType = $this->determineTemplateType($courseData['description'] ?? '');

            // Generate course outline
            $prompt  = "Create a comprehensive course outline for: {$courseData['title']}\n\n";
            $prompt .= "Description: {$courseData['description']}\n";
            $prompt .= "Target Audience: {$requirements['target_audience']}\n";
            $prompt .= "Difficulty: {$requirements['difficulty_level']}\n\n";
            $prompt .= 'Generate a structured course with sections and lessons.';

            $response = $this->generateContent($prompt, 'course_outline', [
                'temperature' => 0.7,
                'max_tokens'  => 4000,
            ]);

            if ($response['error']) {
                return [
                    'error'   => true,
                    'message' => $response['message'],
                ];
            }

            return [
                'error'         => false,
                'content'       => $response['content'],
                'requirements'  => $requirements,
                'template_type' => $templateType,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate course', [
                'error'       => $e->getMessage(),
                'course_data' => $courseData,
            ]);

            return [
                'error'   => true,
                'message' => $e->getMessage(),
            ];
        }
    }
}
