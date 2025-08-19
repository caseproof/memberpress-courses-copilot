<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\CourseTemplate;
use MemberPressCoursesCopilot\Models\GeneratedCourse;
use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * LLM Service
 * 
 * Production-ready LLM service that integrates with LiteLLM proxy
 * through existing MemberPress Copilot settings for AI-powered course generation.
 */
class LLMService
{
    private CourseContentRouter $contentRouter;
    private CopilotProxyService $proxyService;
    private Logger $logger;
    private ErrorHandlingService $errorHandler;
    private TokenUsageService $tokenUsage;
    private PromptTemplateService $promptTemplates;
    private array $config;

    public function __construct(
        CourseContentRouter $contentRouter,
        CopilotProxyService $proxyService,
        Logger $logger,
        ErrorHandlingService $errorHandler,
        TokenUsageService $tokenUsage,
        PromptTemplateService $promptTemplates,
        array $config = []
    ) {
        $this->contentRouter = $contentRouter;
        $this->proxyService = $proxyService;
        $this->logger = $logger;
        $this->errorHandler = $errorHandler;
        $this->tokenUsage = $tokenUsage;
        $this->promptTemplates = $promptTemplates;
        $this->config = array_merge([
            'max_retries' => 3,
            'retry_delay' => 1,
            'timeout' => 60
        ], $config);
    }

    /**
     * Determine course template type from user input using AI analysis
     */
    public function determineTemplateType(string $userInput): ?string
    {
        try {
            $prompt = $this->buildTemplateAnalysisPrompt($userInput);
            
            $response = $this->makeAIRequest('content_analysis', $prompt, [
                'temperature' => 0.3,
                'max_tokens' => 100
            ]);

            if ($response && isset($response['content'])) {
                return $this->parseTemplateResponse($response['content']);
            }

            $this->logger->warning('LLMService: AI template analysis failed, using fallback', [
                'user_input' => substr($userInput, 0, 100)
            ]);

            return $this->fallbackTemplateDetection($userInput);

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Template type determination failed', [
                'error' => $e->getMessage(),
                'user_input' => substr($userInput, 0, 100)
            ]);

            return $this->fallbackTemplateDetection($userInput);
        }
    }

    /**
     * Extract course requirements from user message using AI
     */
    public function extractCourseRequirements(string $message, ?CourseTemplate $template): array
    {
        try {
            $prompt = $this->buildRequirementsExtractionPrompt($message, $template);
            
            $response = $this->makeAIRequest('structured_analysis', $prompt, [
                'temperature' => 0.2,
                'max_tokens' => 1500
            ]);

            if ($response && isset($response['content'])) {
                $extracted = $this->parseRequirementsResponse($response['content']);
                
                $this->logger->info('LLMService: Successfully extracted course requirements', [
                    'extracted_fields' => array_keys($extracted),
                    'template_type' => $template ? $template->getType() : 'none'
                ]);

                return array_merge($extracted, [
                    'raw_input' => $message,
                    'timestamp' => current_time('timestamp'),
                    'extraction_method' => 'ai'
                ]);
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Requirements extraction failed', [
                'error' => $e->getMessage(),
                'message' => substr($message, 0, 200)
            ]);

            return [
                'raw_input' => $message,
                'timestamp' => current_time('timestamp'),
                'extraction_method' => 'fallback',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate lesson content based on context using AI
     */
    public function generateLessonContent(string $sectionTitle, int $lessonNumber, array $requirements): string
    {
        try {
            $prompt = $this->buildLessonContentPrompt($sectionTitle, $lessonNumber, $requirements);
            
            $response = $this->makeAIRequest('lesson_content', $prompt, [
                'temperature' => 0.7,
                'max_tokens' => 6000
            ]);

            if ($response && isset($response['content'])) {
                $content = trim($response['content']);
                
                $this->logger->info('LLMService: Generated lesson content', [
                    'section' => $sectionTitle,
                    'lesson_number' => $lessonNumber,
                    'content_length' => strlen($content),
                    'word_count' => str_word_count($content)
                ]);

                return $content;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Lesson content generation failed', [
                'error' => $e->getMessage(),
                'section' => $sectionTitle,
                'lesson_number' => $lessonNumber
            ]);

            return $this->generateFallbackLessonContent($sectionTitle, $lessonNumber, $requirements);
        }
    }

    /**
     * Generate detailed content for all course lessons
     */
    public function generateDetailedCourseContent(GeneratedCourse $course): void
    {
        $totalLessons = 0;
        $generatedLessons = 0;
        $startTime = microtime(true);

        try {
            foreach ($course->getSections() as $section) {
                foreach ($section->getLessons() as $lesson) {
                    $totalLessons++;
                    
                    if (empty($lesson->getContent())) {
                        $requirements = [
                            'course_title' => $course->getTitle(),
                            'course_description' => $course->getDescription(),
                            'section_title' => $section->getTitle(),
                            'lesson_title' => $lesson->getTitle(),
                            'difficulty_level' => $course->getDifficultyLevel() ?? 'intermediate',
                            'target_audience' => $course->getTargetAudience() ?? 'general'
                        ];

                        $content = $this->generateLessonContent(
                            $section->getTitle(),
                            $lesson->getOrder() + 1,
                            $requirements
                        );
                        
                        $lesson->setContent($content);
                        $generatedLessons++;

                        // Add small delay to avoid rate limiting
                        if ($generatedLessons % 5 === 0) {
                            sleep(1);
                        }
                    }
                }
            }

            $duration = round(microtime(true) - $startTime, 2);
            
            $this->logger->info('LLMService: Course content generation completed', [
                'course_title' => $course->getTitle(),
                'total_lessons' => $totalLessons,
                'generated_lessons' => $generatedLessons,
                'duration_seconds' => $duration
            ]);

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Course content generation failed', [
                'error' => $e->getMessage(),
                'course_title' => $course->getTitle(),
                'total_lessons' => $totalLessons,
                'generated_lessons' => $generatedLessons
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate course outline using AI
     */
    public function generateCourseOutline(array $requirements): array
    {
        try {
            $prompt = $this->promptTemplates->getCourseOutlinePrompt($requirements);
            
            $response = $this->makeAIRequest('course_outline', $prompt, [
                'temperature' => 0.7,
                'max_tokens' => 4000,
                'requirements' => $requirements
            ]);

            if ($response && isset($response['content'])) {
                $outline = $this->parseCourseOutlineResponse($response['content']);
                
                $this->logger->info('LLMService: Generated course outline', [
                    'sections_count' => count($outline['sections'] ?? []),
                    'total_lessons' => array_sum(array_map('count', array_column($outline['sections'] ?? [], 'lessons'))),
                    'provider' => $response['provider'],
                    'model' => $response['model']
                ]);

                return $outline;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $errorInfo = $this->errorHandler->handleContentError($e, 'course_outline', $requirements);
            
            $this->logger->error('LLMService: Course outline generation failed', [
                'error' => $e->getMessage(),
                'requirements' => array_keys($requirements),
                'error_code' => $errorInfo['error_code']
            ]);
            
            throw $e;
        }
    }

    /**
     * Generate quiz questions for a lesson
     */
    public function generateQuizQuestions(string $lessonContent, array $options = []): array
    {
        try {
            $prompt = $this->buildQuizPrompt($lessonContent, $options);
            
            $response = $this->makeAIRequest('quiz_questions', $prompt, [
                'temperature' => 0.4,
                'max_tokens' => 3000
            ]);

            if ($response && isset($response['content'])) {
                $questions = $this->parseQuizResponse($response['content']);
                
                $this->logger->info('LLMService: Generated quiz questions', [
                    'questions_count' => count($questions),
                    'content_length' => strlen($lessonContent)
                ]);

                return $questions;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Quiz generation failed', [
                'error' => $e->getMessage(),
                'content_length' => strlen($lessonContent)
            ]);
            
            return [];
        }
    }

    /**
     * Generate advanced lesson content with multimedia suggestions
     */
    public function generateAdvancedLessonContent(array $context): array
    {
        try {
            $prompt = $this->buildAdvancedContentPrompt($context);
            
            $response = $this->makeAIRequest('advanced_content', $prompt, [
                'temperature' => 0.7,
                'max_tokens' => 8000
            ]);

            if ($response && isset($response['content'])) {
                $content = $this->parseAdvancedContentResponse($response['content']);
                
                $this->logger->info('LLMService: Generated advanced lesson content', [
                    'lesson_title' => $context['lesson_title'] ?? 'Unknown',
                    'content_blocks' => count($content['content_blocks'] ?? []),
                    'multimedia_suggestions' => count($content['multimedia_suggestions'] ?? [])
                ]);

                return $content;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Advanced content generation failed', [
                'error' => $e->getMessage(),
                'lesson_title' => $context['lesson_title'] ?? 'Unknown'
            ]);
            
            return $this->generateFallbackAdvancedContent($context);
        }
    }

    /**
     * Generate interactive exercises and assignments
     */
    public function generateInteractiveExercises(array $context): array
    {
        try {
            $prompt = $this->buildExercisePrompt($context);
            
            $response = $this->makeAIRequest('interactive_exercises', $prompt, [
                'temperature' => 0.6,
                'max_tokens' => 5000
            ]);

            if ($response && isset($response['content'])) {
                $exercises = $this->parseExerciseResponse($response['content']);
                
                $this->logger->info('LLMService: Generated interactive exercises', [
                    'lesson_title' => $context['lesson_title'] ?? 'Unknown',
                    'exercise_count' => count($exercises),
                    'exercise_types' => array_unique(array_column($exercises, 'type'))
                ]);

                return $exercises;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Exercise generation failed', [
                'error' => $e->getMessage(),
                'lesson_title' => $context['lesson_title'] ?? 'Unknown'
            ]);
            
            return [];
        }
    }

    /**
     * Generate case studies and scenarios
     */
    public function generateCaseStudies(array $context): array
    {
        try {
            $prompt = $this->buildCaseStudyPrompt($context);
            
            $response = $this->makeAIRequest('case_studies', $prompt, [
                'temperature' => 0.8,
                'max_tokens' => 6000
            ]);

            if ($response && isset($response['content'])) {
                $caseStudies = $this->parseCaseStudyResponse($response['content']);
                
                $this->logger->info('LLMService: Generated case studies', [
                    'topic' => $context['topic'] ?? 'Unknown',
                    'case_study_count' => count($caseStudies)
                ]);

                return $caseStudies;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Case study generation failed', [
                'error' => $e->getMessage(),
                'topic' => $context['topic'] ?? 'Unknown'
            ]);
            
            return [];
        }
    }

    /**
     * Generate project-based learning content
     */
    public function generateProjectContent(array $context): array
    {
        try {
            $prompt = $this->buildProjectPrompt($context);
            
            $response = $this->makeAIRequest('project_content', $prompt, [
                'temperature' => 0.7,
                'max_tokens' => 7000
            ]);

            if ($response && isset($response['content'])) {
                $project = $this->parseProjectResponse($response['content']);
                
                $this->logger->info('LLMService: Generated project content', [
                    'project_title' => $project['title'] ?? 'Unknown',
                    'phases' => count($project['phases'] ?? []),
                    'deliverables' => count($project['deliverables'] ?? [])
                ]);

                return $project;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Project content generation failed', [
                'error' => $e->getMessage(),
                'course_title' => $context['course_title'] ?? 'Unknown'
            ]);
            
            return [];
        }
    }

    /**
     * Generate assessment rubrics
     */
    public function generateAssessmentRubric(array $context): array
    {
        try {
            $prompt = $this->buildRubricPrompt($context);
            
            $response = $this->makeAIRequest('assessment_rubric', $prompt, [
                'temperature' => 0.3,
                'max_tokens' => 4000
            ]);

            if ($response && isset($response['content'])) {
                $rubric = $this->parseRubricResponse($response['content']);
                
                $this->logger->info('LLMService: Generated assessment rubric', [
                    'assessment_type' => $context['assessment_type'] ?? 'Unknown',
                    'criteria_count' => count($rubric['criteria'] ?? [])
                ]);

                return $rubric;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Rubric generation failed', [
                'error' => $e->getMessage(),
                'assessment_type' => $context['assessment_type'] ?? 'Unknown'
            ]);
            
            return [];
        }
    }

    /**
     * Generate learning activity content (discussions, group work, simulations)
     */
    public function generateLearningActivities(array $context): array
    {
        try {
            $prompt = $this->buildActivityPrompt($context);
            
            $response = $this->makeAIRequest('learning_activities', $prompt, [
                'temperature' => 0.8,
                'max_tokens' => 5000
            ]);

            if ($response && isset($response['content'])) {
                $activities = $this->parseActivityResponse($response['content']);
                
                $this->logger->info('LLMService: Generated learning activities', [
                    'lesson_title' => $context['lesson_title'] ?? 'Unknown',
                    'activity_count' => count($activities),
                    'activity_types' => array_unique(array_column($activities, 'type'))
                ]);

                return $activities;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Learning activity generation failed', [
                'error' => $e->getMessage(),
                'lesson_title' => $context['lesson_title'] ?? 'Unknown'
            ]);
            
            return [];
        }
    }

    /**
     * Optimize content for reading level and audience
     */
    public function optimizeContentForAudience(string $content, array $options): string
    {
        try {
            $prompt = $this->buildContentOptimizationPrompt($content, $options);
            
            $response = $this->makeAIRequest('content_optimization', $prompt, [
                'temperature' => 0.4,
                'max_tokens' => 6000
            ]);

            if ($response && isset($response['content'])) {
                $optimizedContent = trim($response['content']);
                
                $this->logger->info('LLMService: Optimized content for audience', [
                    'target_reading_level' => $options['reading_level'] ?? 'Unknown',
                    'original_length' => strlen($content),
                    'optimized_length' => strlen($optimizedContent)
                ]);

                return $optimizedContent;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Content optimization failed', [
                'error' => $e->getMessage(),
                'reading_level' => $options['reading_level'] ?? 'Unknown'
            ]);
            
            return $content; // Return original content as fallback
        }
    }

    /**
     * Generate personalized content based on learner profiles
     */
    public function generatePersonalizedContent(array $context, array $learnerProfile): array
    {
        try {
            $prompt = $this->buildPersonalizationPrompt($context, $learnerProfile);
            
            $response = $this->makeAIRequest('personalized_content', $prompt, [
                'temperature' => 0.7,
                'max_tokens' => 6000
            ]);

            if ($response && isset($response['content'])) {
                $personalizedContent = $this->parsePersonalizedContentResponse($response['content']);
                
                $this->logger->info('LLMService: Generated personalized content', [
                    'learner_type' => $learnerProfile['learning_style'] ?? 'Unknown',
                    'experience_level' => $learnerProfile['experience_level'] ?? 'Unknown',
                    'content_variations' => count($personalizedContent['variations'] ?? [])
                ]);

                return $personalizedContent;
            }

            throw new \Exception('Invalid response format from AI');

        } catch (\Exception $e) {
            $this->logger->error('LLMService: Personalized content generation failed', [
                'error' => $e->getMessage(),
                'learner_type' => $learnerProfile['learning_style'] ?? 'Unknown'
            ]);
            
            return [];
        }
    }

    /**
     * Make AI request with proper routing, error handling, and token tracking
     */
    private function makeAIRequest(string $contentType, string $prompt, array $options = []): ?array
    {
        $retries = 0;
        $maxRetries = $this->config['max_retries'];
        $startTime = microtime(true);

        while ($retries <= $maxRetries) {
            try {
                // Get optimal provider and model for content type
                $providerConfig = $this->contentRouter->getProviderForContentType($contentType, $options);
                
                // Check budget limits before making request
                $estimatedTokens = $this->estimateTokenCount($prompt);
                $costEstimate = $this->tokenUsage->estimateRequestCost(
                    $providerConfig['provider'],
                    $providerConfig['model'],
                    $estimatedTokens
                );

                if (!$costEstimate['within_budget']) {
                    throw new \Exception('Request would exceed budget limits');
                }

                $payload = [
                    'model' => $providerConfig['model'],
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => $options['temperature'] ?? $providerConfig['options']['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? $providerConfig['options']['max_tokens'] ?? 2000
                ];

                // Add provider-specific options
                if (isset($providerConfig['options']['functions'])) {
                    $payload['functions'] = $providerConfig['options']['functions'];
                }

                $this->logger->debug('LLMService: Making AI request', [
                    'content_type' => $contentType,
                    'provider' => $providerConfig['provider'],
                    'model' => $providerConfig['model'],
                    'attempt' => $retries + 1,
                    'estimated_cost' => $costEstimate['total_cost']
                ]);

                $requestStartTime = microtime(true);
                $response = $this->proxyService->makeLLMRequest('/chat/completions', $payload);
                $responseTime = microtime(true) - $requestStartTime;

                if (is_wp_error($response)) {
                    throw new \Exception('Request failed: ' . $response->get_error_message());
                }

                $responseCode = wp_remote_retrieve_response_code($response);
                $responseBody = wp_remote_retrieve_body($response);

                if ($responseCode >= 400) {
                    // Handle specific error types
                    if ($responseCode === 429) {
                        $errorInfo = $this->errorHandler->handleRateLimitError(
                            new \Exception("Rate limit exceeded: {$responseBody}"),
                            $providerConfig['provider']
                        );
                        
                        if ($errorInfo['should_retry'] && $retries < $maxRetries) {
                            sleep($errorInfo['retry_delay']);
                            $retries++;
                            continue;
                        }
                    } elseif ($responseCode === 401 || $responseCode === 403) {
                        $this->errorHandler->handleAuthError(
                            new \Exception("Authentication error: {$responseBody}"),
                            ['provider' => $providerConfig['provider']]
                        );
                    }
                    
                    throw new \Exception("API error {$responseCode}: {$responseBody}");
                }

                $data = json_decode($responseBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
                }

                if (!isset($data['choices'][0]['message']['content'])) {
                    throw new \Exception('Unexpected response format');
                }

                $content = $data['choices'][0]['message']['content'];
                $usage = $data['usage'] ?? [];

                // Track token usage
                $this->tokenUsage->trackUsage([
                    'provider' => $providerConfig['provider'],
                    'model' => $providerConfig['model'],
                    'content_type' => $contentType,
                    'input_tokens' => $usage['prompt_tokens'] ?? $estimatedTokens,
                    'output_tokens' => $usage['completion_tokens'] ?? strlen($content) / 4,
                    'total_tokens' => $usage['total_tokens'] ?? $estimatedTokens + (strlen($content) / 4),
                    'response_time' => $responseTime,
                    'estimated_cost' => $costEstimate['total_cost']
                ]);

                // Log provider performance
                $this->contentRouter->logProviderPerformance(
                    $providerConfig['provider'],
                    $providerConfig['model'],
                    $responseTime,
                    $usage['total_tokens'] ?? 0
                );

                $totalTime = microtime(true) - $startTime;

                $this->logger->info('LLMService: AI request successful', [
                    'content_type' => $contentType,
                    'provider' => $providerConfig['provider'],
                    'model' => $providerConfig['model'],
                    'response_length' => strlen($content),
                    'usage' => $usage,
                    'response_time' => $responseTime,
                    'total_time' => $totalTime,
                    'retries' => $retries
                ]);

                return [
                    'content' => $content,
                    'usage' => $usage,
                    'provider' => $providerConfig['provider'],
                    'model' => $providerConfig['model'],
                    'response_time' => $responseTime,
                    'cost_estimate' => $costEstimate
                ];

            } catch (\Exception $e) {
                $retries++;
                
                // Handle the error with our error handling service
                $errorInfo = $this->errorHandler->handleAIServiceError($e, $contentType, [
                    'provider' => $providerConfig['provider'] ?? 'unknown',
                    'model' => $providerConfig['model'] ?? 'unknown',
                    'attempt' => $retries,
                    'options' => $options
                ]);
                
                $this->logger->warning('LLMService: AI request failed', [
                    'content_type' => $contentType,
                    'attempt' => $retries,
                    'error' => $e->getMessage(),
                    'error_code' => $errorInfo['error_code'],
                    'should_retry' => $errorInfo['should_retry']
                ]);

                if ($errorInfo['should_retry'] && $retries <= $maxRetries) {
                    sleep($errorInfo['retry_delay'] ?? $this->config['retry_delay'] * $retries);
                } else {
                    throw $e;
                }
            }
        }

        return null;
    }

    /**
     * Build template analysis prompt
     */
    private function buildTemplateAnalysisPrompt(string $userInput): string
    {
        return "Analyze the following course description and determine which template type best fits:

Course Description: {$userInput}

Available template types:
- technical: Programming, software development, technical skills
- business: Marketing, management, entrepreneurship, business skills
- creative: Art, design, music, creative writing, visual arts
- academic: Research, theory, formal education, scholarly content

Respond with only the template type name (technical, business, creative, or academic). If none fit well, respond with 'general'.";
    }

    /**
     * Build requirements extraction prompt
     */
    private function buildRequirementsExtractionPrompt(string $message, ?CourseTemplate $template): string
    {
        $templateInfo = $template ? "Template: {$template->getType()}\n" : "";
        
        return "Extract course requirements from this user message:

{$templateInfo}Message: {$message}

Extract and format as JSON:
{
  \"title\": \"Course title\",
  \"description\": \"Course description\",
  \"difficulty_level\": \"beginner|intermediate|advanced\",
  \"target_audience\": \"Who this course is for\",
  \"learning_objectives\": [\"objective1\", \"objective2\"],
  \"estimated_duration\": \"X hours/weeks\",
  \"prerequisites\": [\"prerequisite1\", \"prerequisite2\"],
  \"topics\": [\"topic1\", \"topic2\"]
}

Respond with only the JSON object.";
    }

    /**
     * Build lesson content prompt
     */
    private function buildLessonContentPrompt(string $sectionTitle, int $lessonNumber, array $requirements): string
    {
        $courseTitle = $requirements['course_title'] ?? 'Course';
        $lessonTitle = $requirements['lesson_title'] ?? "Lesson {$lessonNumber}";
        $difficulty = $requirements['difficulty_level'] ?? 'intermediate';
        $audience = $requirements['target_audience'] ?? 'general learners';

        return "Generate comprehensive lesson content for:

Course: {$courseTitle}
Section: {$sectionTitle}
Lesson {$lessonNumber}: {$lessonTitle}
Difficulty: {$difficulty}
Audience: {$audience}

Create engaging, educational content that includes:
1. Learning objectives for this lesson
2. Key concepts and explanations
3. Practical examples or case studies
4. Step-by-step instructions where applicable
5. Summary of key takeaways

Make the content engaging, clear, and appropriate for the difficulty level. Include practical applications and real-world examples.";
    }

    /**
     * Build course outline prompt
     */
    private function buildCourseOutlinePrompt(array $requirements): string
    {
        $title = $requirements['title'] ?? 'New Course';
        $description = $requirements['description'] ?? '';
        $difficulty = $requirements['difficulty_level'] ?? 'intermediate';
        $objectives = implode(', ', $requirements['learning_objectives'] ?? []);

        return "Create a detailed course outline for:

Title: {$title}
Description: {$description}
Difficulty: {$difficulty}
Learning Objectives: {$objectives}

Generate a structured course outline with 4-8 sections, each containing 3-6 lessons. Format as JSON:

{
  \"course_title\": \"{$title}\",
  \"sections\": [
    {
      \"title\": \"Section Title\",
      \"description\": \"Section description\",
      \"lessons\": [
        {
          \"title\": \"Lesson Title\",
          \"description\": \"Lesson description\",
          \"duration\": \"30 minutes\"
        }
      ]
    }
  ]
}

Ensure logical progression and comprehensive coverage of the topic.";
    }

    /**
     * Build quiz generation prompt
     */
    private function buildQuizPrompt(string $lessonContent, array $options): string
    {
        $questionCount = $options['question_count'] ?? 5;
        $questionTypes = $options['question_types'] ?? ['multiple_choice', 'true_false'];
        
        return "Generate {$questionCount} quiz questions based on this lesson content:

{$lessonContent}

Question types to include: " . implode(', ', $questionTypes) . "

Format as JSON:
{
  \"questions\": [
    {
      \"question\": \"Question text\",
      \"type\": \"multiple_choice|true_false|short_answer\",
      \"options\": [\"A\", \"B\", \"C\", \"D\"],
      \"correct_answer\": \"A\",
      \"explanation\": \"Why this is correct\"
    }
  ]
}

Make questions challenging but fair, testing understanding rather than memorization.";
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
     * Parse requirements response
     */
    private function parseRequirementsResponse(string $response): array
    {
        $decoded = json_decode(trim($response), true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        
        // Fallback parsing if JSON is malformed
        return ['parsing_error' => 'Failed to parse AI response as JSON'];
    }

    /**
     * Parse course outline response
     */
    private function parseCourseOutlineResponse(string $response): array
    {
        $decoded = json_decode(trim($response), true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        
        throw new \Exception('Failed to parse course outline response as JSON');
    }

    /**
     * Parse quiz response
     */
    private function parseQuizResponse(string $response): array
    {
        $decoded = json_decode(trim($response), true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['questions'])) {
            return $decoded['questions'];
        }
        
        return [];
    }

    /**
     * Fallback template detection using keywords
     */
    private function fallbackTemplateDetection(string $input): ?string
    {
        $input = strtolower($input);
        
        if (strpos($input, 'technical') !== false || strpos($input, 'programming') !== false || strpos($input, 'coding') !== false) {
            return CourseTemplate::TEMPLATE_TECHNICAL;
        } elseif (strpos($input, 'business') !== false || strpos($input, 'marketing') !== false || strpos($input, 'management') !== false) {
            return CourseTemplate::TEMPLATE_BUSINESS;
        } elseif (strpos($input, 'creative') !== false || strpos($input, 'art') !== false || strpos($input, 'design') !== false) {
            return CourseTemplate::TEMPLATE_CREATIVE;
        } elseif (strpos($input, 'academic') !== false || strpos($input, 'research') !== false || strpos($input, 'theory') !== false) {
            return CourseTemplate::TEMPLATE_ACADEMIC;
        }
        
        return null;
    }

    /**
     * Generate fallback lesson content
     */
    private function generateFallbackLessonContent(string $sectionTitle, int $lessonNumber, array $requirements): string
    {
        $lessonTitle = $requirements['lesson_title'] ?? "Lesson {$lessonNumber}";
        
        return "# {$lessonTitle}\n\n## Learning Objectives\n\nBy the end of this lesson, you will be able to:\n\n- Understand the key concepts covered in this lesson\n- Apply the knowledge to practical situations\n- Build upon this foundation for future lessons\n\n## Content\n\nThis lesson covers important concepts related to {$sectionTitle}. The content will help you develop a solid understanding of the topic and prepare you for more advanced material.\n\n## Key Takeaways\n\n- Review the main concepts covered\n- Practice applying what you've learned\n- Prepare for the next lesson in the sequence\n\n*Note: This content was generated using a fallback method due to AI service unavailability.*";
    }

    /**
     * Estimate token count for a prompt
     */
    private function estimateTokenCount(string $text): int
    {
        // Rough estimation: 1 token ≈ 4 characters for English text
        // This is a conservative estimate that works for most AI providers
        return intval(strlen($text) / 4);
    }

    /**
     * Get enhanced usage statistics
     */
    public function getUsageStatistics(int $days = 7): array
    {
        return $this->tokenUsage->getUsageStatistics($days, []);
    }

    /**
     * Get cost optimization recommendations
     */
    public function getCostOptimizationRecommendations(): array
    {
        $stats = $this->getUsageStatistics(30);
        return $this->tokenUsage->getCostOptimizationRecommendations($stats);
    }

    /**
     * Check current budget status
     */
    public function getBudgetStatus(): array
    {
        return $this->tokenUsage->checkBudgetLimits([]);
    }

    /**
     * Get service health status
     */
    public function getServiceHealth(): array
    {
        $errorStats = $this->errorHandler->getErrorStatistics(24);
        $routerStats = $this->contentRouter->getRoutingStats();
        $performanceAnalytics = $this->contentRouter->getPerformanceAnalytics(7);
        
        return [
            'service_status' => 'operational',
            'error_statistics' => $errorStats,
            'routing_statistics' => $routerStats,
            'performance_analytics' => $performanceAnalytics,
            'last_updated' => time()
        ];
    }
}