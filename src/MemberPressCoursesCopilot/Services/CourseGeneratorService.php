<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\CourseTemplate;
use MemberPressCoursesCopilot\Models\GeneratedCourse;
use MemberPressCoursesCopilot\Models\CourseSection;
use MemberPressCoursesCopilot\Models\CourseLesson;

/**
 * Course Generator Service
 * 
 * Main service for managing the AI-powered course generation workflow.
 * Handles conversation state, template selection, and course creation.
 */
class CourseGeneratorService
{
    private ?LLMService $llmService;
    private array $conversationState;
    private string $sessionId;

    // Conversation states
    public const STATE_INITIAL = 'initial';
    public const STATE_TEMPLATE_SELECTION = 'template_selection';
    public const STATE_GATHERING_REQUIREMENTS = 'gathering_requirements';
    public const STATE_STRUCTURE_REVIEW = 'structure_review';
    public const STATE_CONTENT_GENERATION = 'content_generation';
    public const STATE_FINAL_REVIEW = 'final_review';
    public const STATE_COMPLETED = 'completed';

    public function __construct(?LLMService $llmService = null)
    {
        $this->llmService = $llmService;
        $this->sessionId = $this->generateSessionId();
        $this->initializeConversationState();
    }

    /**
     * Start a new course generation conversation
     */
    public function startConversation(array $initialData = []): array
    {
        $this->initializeConversationState();
        $this->conversationState['user_data'] = $initialData;
        
        $response = [
            'session_id' => $this->sessionId,
            'state' => self::STATE_INITIAL,
            'message' => $this->getWelcomeMessage(),
            'suggested_questions' => $this->getInitialQuestions(),
            'available_templates' => $this->getAvailableTemplates()
        ];

        $this->updateConversationState(self::STATE_TEMPLATE_SELECTION, $response);
        return $response;
    }

    /**
     * Process user message and continue conversation
     */
    public function processMessage(string $message, array $context = []): array
    {
        $currentState = $this->conversationState['current_state'];
        $response = [];

        try {
            switch ($currentState) {
                case self::STATE_TEMPLATE_SELECTION:
                    $response = $this->handleTemplateSelection($message, $context);
                    break;

                case self::STATE_GATHERING_REQUIREMENTS:
                    $response = $this->handleRequirementsGathering($message, $context);
                    break;

                case self::STATE_STRUCTURE_REVIEW:
                    $response = $this->handleStructureReview($message, $context);
                    break;

                case self::STATE_CONTENT_GENERATION:
                    $response = $this->handleContentGeneration($message, $context);
                    break;

                case self::STATE_FINAL_REVIEW:
                    $response = $this->handleFinalReview($message, $context);
                    break;

                default:
                    $response = $this->handleUnknownState($message);
                    break;
            }

        } catch (\Exception $e) {
            $response = $this->handleError($e);
        }

        return $response;
    }

    /**
     * Generate course structure based on gathered requirements
     */
    public function generateCourseStructure(): GeneratedCourse
    {
        $requirements = $this->conversationState['requirements'];
        $template = $this->conversationState['selected_template'];

        if (!$template instanceof CourseTemplate) {
            throw new \Exception('No template selected');
        }

        // Create course with basic information
        $course = new GeneratedCourse(
            $requirements['title'] ?? 'Untitled Course',
            $requirements['description'] ?? '',
            $requirements['learning_objectives'] ?? [],
            [],
            $requirements['metadata'] ?? [],
            $template->getTemplateType()
        );

        // Generate sections based on template structure
        $templateStructure = $template->getDefaultStructure();
        if (isset($templateStructure['sections'])) {
            foreach ($templateStructure['sections'] as $sectionData) {
                $section = $this->generateSection(
                    $sectionData['title'],
                    $sectionData['lessons'],
                    $requirements
                );
                $course->addSection($section);
            }
        }

        return $course;
    }

    /**
     * Create WordPress course from generated course
     */
    public function createWordPressCourse(GeneratedCourse $course): int
    {
        // Validate course before creation
        $errors = $course->validate();
        if (!empty($errors)) {
            throw new \Exception('Course validation failed: ' . implode(', ', $errors));
        }

        // Create the course in WordPress
        $courseId = $course->createWordPressCourse();

        // Update conversation state
        $this->conversationState['created_course_id'] = $courseId;
        $this->updateConversationState(self::STATE_COMPLETED);

        return $courseId;
    }

    /**
     * Handle template selection
     */
    private function handleTemplateSelection(string $message, array $context): array
    {
        $selectedTemplate = null;
        $templateType = strtolower(trim($message));

        // Try to match template type
        $availableTemplates = CourseTemplate::getPredefinedTemplates();
        if (isset($availableTemplates[$templateType])) {
            $selectedTemplate = $availableTemplates[$templateType];
        } else {
            // Use LLM to determine intent if available
            if ($this->llmService) {
                $templateType = $this->llmService->determineTemplateType($message);
                if ($templateType && isset($availableTemplates[$templateType])) {
                    $selectedTemplate = $availableTemplates[$templateType];
                }
            }
        }

        if ($selectedTemplate) {
            $this->conversationState['selected_template'] = $selectedTemplate;
            
            $response = [
                'state' => self::STATE_GATHERING_REQUIREMENTS,
                'message' => "Great! I'll help you create a {$selectedTemplate->getTemplateType()} course. Let me ask you some questions to understand your requirements better.",
                'template' => $selectedTemplate->toArray(),
                'questions' => $selectedTemplate->getSuggestedQuestions()
            ];

            $this->updateConversationState(self::STATE_GATHERING_REQUIREMENTS, $response);
        } else {
            $response = [
                'state' => self::STATE_TEMPLATE_SELECTION,
                'message' => "I didn't understand which template you'd like to use. Please choose from: Technical, Business, Creative, or Academic.",
                'available_templates' => $this->getAvailableTemplates()
            ];
        }

        return $response;
    }

    /**
     * Handle requirements gathering
     */
    private function handleRequirementsGathering(string $message, array $context): array
    {
        // Initialize requirements if not exists
        if (!isset($this->conversationState['requirements'])) {
            $this->conversationState['requirements'] = [];
        }

        // Use LLM to extract requirements from message
        if ($this->llmService) {
            $extractedData = $this->llmService->extractCourseRequirements(
                $message,
                $this->conversationState['selected_template']
            );
            
            $this->conversationState['requirements'] = array_merge(
                $this->conversationState['requirements'],
                $extractedData
            );
        } else {
            // Basic parsing without LLM
            $this->conversationState['requirements']['raw_input'] = $message;
        }

        // Check if we have enough information to proceed
        $hasMinimumRequirements = $this->hasMinimumRequirements();
        
        if ($hasMinimumRequirements) {
            $courseStructure = $this->generateCourseStructure();
            
            $response = [
                'state' => self::STATE_STRUCTURE_REVIEW,
                'message' => "Based on your requirements, I've created a course structure. Please review it and let me know if you'd like any changes.",
                'course_structure' => $courseStructure->toArray(),
                'requirements' => $this->conversationState['requirements']
            ];

            $this->conversationState['generated_course'] = $courseStructure;
            $this->updateConversationState(self::STATE_STRUCTURE_REVIEW, $response);
        } else {
            $response = [
                'state' => self::STATE_GATHERING_REQUIREMENTS,
                'message' => $this->getNextQuestion(),
                'current_requirements' => $this->conversationState['requirements']
            ];
        }

        return $response;
    }

    /**
     * Handle structure review
     */
    private function handleStructureReview(string $message, array $context): array
    {
        $approval = $this->determineApproval($message);
        
        if ($approval === 'approved') {
            $response = [
                'state' => self::STATE_CONTENT_GENERATION,
                'message' => "Perfect! I'll now generate the detailed content for your course. This may take a few moments.",
                'status' => 'generating_content'
            ];

            $this->updateConversationState(self::STATE_CONTENT_GENERATION, $response);
            
            // Trigger content generation
            $this->generateDetailedContent();
        } else {
            // Handle requested changes
            $changes = $this->extractRequestedChanges($message);
            $this->applyStructureChanges($changes);
            
            $response = [
                'state' => self::STATE_STRUCTURE_REVIEW,
                'message' => "I've updated the course structure based on your feedback. Please review the changes.",
                'course_structure' => $this->conversationState['generated_course']->toArray(),
                'applied_changes' => $changes
            ];
        }

        return $response;
    }

    /**
     * Handle content generation
     */
    private function handleContentGeneration(string $message, array $context): array
    {
        // This is typically a background process
        $course = $this->conversationState['generated_course'];
        
        if ($this->llmService) {
            $this->llmService->generateDetailedCourseContent($course);
        }

        $response = [
            'state' => self::STATE_FINAL_REVIEW,
            'message' => "Your course content has been generated! Here's the complete course for your final review.",
            'complete_course' => $course->toArray(),
            'actions' => [
                'create_course' => 'Create this course in WordPress',
                'request_changes' => 'Request changes to the content',
                'start_over' => 'Start with a different template'
            ]
        ];

        $this->updateConversationState(self::STATE_FINAL_REVIEW, $response);
        return $response;
    }

    /**
     * Handle final review
     */
    private function handleFinalReview(string $message, array $context): array
    {
        $action = $this->determineUserAction($message);
        
        switch ($action) {
            case 'create_course':
                $course = $this->conversationState['generated_course'];
                $courseId = $this->createWordPressCourse($course);
                
                $response = [
                    'state' => self::STATE_COMPLETED,
                    'message' => "Congratulations! Your course has been created successfully.",
                    'course_id' => $courseId,
                    'course_url' => admin_url("post.php?post={$courseId}&action=edit"),
                    'next_steps' => [
                        'Review and edit the course content',
                        'Set up course pricing and access rules',
                        'Publish the course when ready'
                    ]
                ];
                break;

            case 'request_changes':
                $response = [
                    'state' => self::STATE_CONTENT_GENERATION,
                    'message' => "What changes would you like me to make to the course content?",
                    'current_course' => $this->conversationState['generated_course']->toArray()
                ];
                break;

            case 'start_over':
                $response = $this->startConversation();
                break;

            default:
                $response = [
                    'state' => self::STATE_FINAL_REVIEW,
                    'message' => "I didn't understand your request. Would you like to create the course, request changes, or start over?",
                    'actions' => [
                        'create_course' => 'Create this course in WordPress',
                        'request_changes' => 'Request changes to the content',
                        'start_over' => 'Start with a different template'
                    ]
                ];
                break;
        }

        return $response;
    }

    /**
     * Generate a course section with lessons
     */
    private function generateSection(string $title, int $lessonCount, array $requirements): CourseSection
    {
        $section = new CourseSection($title);

        // Generate lessons for this section
        for ($i = 1; $i <= $lessonCount; $i++) {
            $lesson = new CourseLesson(
                "Lesson {$i}: {$title}",
                $this->generateLessonContent($title, $i, $requirements),
                $this->generateLessonObjectives($title, $i),
                $this->estimateLessonDuration($title),
                $i - 1
            );
            
            $section->addLesson($lesson);
        }

        return $section;
    }

    /**
     * Generate lesson content based on context
     */
    private function generateLessonContent(string $sectionTitle, int $lessonNumber, array $requirements): string
    {
        if ($this->llmService) {
            return $this->llmService->generateLessonContent($sectionTitle, $lessonNumber, $requirements);
        }

        // Basic content generation without LLM
        return "This is lesson {$lessonNumber} of the {$sectionTitle} section. Content will be generated based on your course requirements.";
    }

    /**
     * Generate lesson objectives
     */
    private function generateLessonObjectives(string $sectionTitle, int $lessonNumber): array
    {
        return [
            "Understand the key concepts in {$sectionTitle}",
            "Apply the knowledge from this lesson",
            "Complete practical exercises related to {$sectionTitle}"
        ];
    }

    /**
     * Estimate lesson duration
     */
    private function estimateLessonDuration(string $sectionTitle): int
    {
        // Basic estimation - can be enhanced with LLM
        return rand(15, 45); // 15-45 minutes
    }

    /**
     * Helper methods
     */
    private function generateSessionId(): string
    {
        return 'mpcc_' . uniqid() . '_' . time();
    }

    private function initializeConversationState(): void
    {
        $this->conversationState = [
            'session_id' => $this->sessionId,
            'current_state' => self::STATE_INITIAL,
            'created_at' => current_time('timestamp'),
            'updated_at' => current_time('timestamp'),
            'messages' => [],
            'selected_template' => null,
            'requirements' => [],
            'generated_course' => null,
            'user_data' => []
        ];
    }

    private function updateConversationState(string $newState, array $responseData = []): void
    {
        $this->conversationState['current_state'] = $newState;
        $this->conversationState['updated_at'] = current_time('timestamp');
        $this->conversationState['last_response'] = $responseData;
    }

    private function getWelcomeMessage(): string
    {
        return "Welcome to the MemberPress Courses AI Copilot! I'll help you create a comprehensive course. First, let's choose a template that best fits your course type.";
    }

    private function getInitialQuestions(): array
    {
        return [
            "What type of course would you like to create?",
            "Who is your target audience?",
            "What should students be able to do after completing the course?"
        ];
    }

    private function getAvailableTemplates(): array
    {
        $templates = CourseTemplate::getPredefinedTemplates();
        $result = [];
        
        foreach ($templates as $type => $template) {
            $result[$type] = [
                'type' => $type,
                'title' => ucfirst($type),
                'description' => "Designed for {$type} courses",
                'lesson_count' => $template->getTotalLessons()
            ];
        }
        
        return $result;
    }

    private function hasMinimumRequirements(): bool
    {
        $requirements = $this->conversationState['requirements'];
        return !empty($requirements['title']) || !empty($requirements['raw_input']);
    }

    private function getNextQuestion(): string
    {
        $template = $this->conversationState['selected_template'];
        if ($template && $template instanceof CourseTemplate) {
            $questions = $template->getSuggestedQuestions();
            return $questions[array_rand($questions)] ?? "Tell me more about your course requirements.";
        }
        
        return "Please provide more details about your course.";
    }

    private function determineApproval(string $message): string
    {
        $lowerMessage = strtolower($message);
        if (strpos($lowerMessage, 'approve') !== false || strpos($lowerMessage, 'looks good') !== false || strpos($lowerMessage, 'yes') !== false) {
            return 'approved';
        }
        return 'changes_requested';
    }

    private function extractRequestedChanges(string $message): array
    {
        // Basic change extraction - can be enhanced with LLM
        return ['raw_feedback' => $message];
    }

    private function applyStructureChanges(array $changes): void
    {
        // Apply changes to generated course structure
        // This would typically use LLM to interpret and apply changes
    }

    private function generateDetailedContent(): void
    {
        // Generate detailed content for all lessons
        if ($this->llmService && isset($this->conversationState['generated_course'])) {
            $course = $this->conversationState['generated_course'];
            $this->llmService->generateDetailedCourseContent($course);
        }
    }

    private function determineUserAction(string $message): string
    {
        $lowerMessage = strtolower($message);
        
        if (strpos($lowerMessage, 'create') !== false || strpos($lowerMessage, 'publish') !== false) {
            return 'create_course';
        } elseif (strpos($lowerMessage, 'change') !== false || strpos($lowerMessage, 'modify') !== false) {
            return 'request_changes';
        } elseif (strpos($lowerMessage, 'start over') !== false || strpos($lowerMessage, 'restart') !== false) {
            return 'start_over';
        }
        
        return 'unknown';
    }

    private function handleUnknownState(string $message): array
    {
        return [
            'state' => self::STATE_INITIAL,
            'message' => "I'm sorry, something went wrong. Let's start over.",
            'error' => 'Unknown conversation state'
        ];
    }

    private function handleError(\Exception $e): array
    {
        return [
            'state' => $this->conversationState['current_state'],
            'message' => "I encountered an error: " . $e->getMessage() . ". Please try again.",
            'error' => $e->getMessage()
        ];
    }

    // Getters
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getConversationState(): array
    {
        return $this->conversationState;
    }

    public function getCurrentState(): string
    {
        return $this->conversationState['current_state'];
    }
}