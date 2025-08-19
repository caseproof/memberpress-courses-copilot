<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\CourseTemplate;
use MemberPressCoursesCopilot\Models\GeneratedCourse;
use MemberPressCoursesCopilot\Models\CourseSection;
use MemberPressCoursesCopilot\Models\CourseLesson;
use MemberPressCoursesCopilot\Models\ConversationSession;
use MemberPressCoursesCopilot\Models\QualityReport;

/**
 * Course Generator Service
 * 
 * Enhanced service for managing the AI-powered course generation workflow.
 * Features sophisticated conversation state machine, session persistence,
 * context tracking, and multi-step refinement process.
 */
class CourseGeneratorService
{
    private ?LLMService $llmService;
    private ?TemplateEngine $templateEngine;
    private ?ConversationManager $conversationManager;
    private ?DatabaseService $databaseService;
    private ?ConversationSession $currentSession;
    private ?QualityAssuranceService $qualityService;
    private ?QualityFeedbackService $feedbackService;
    private ?QualityGatesService $gatesService;

    // Enhanced conversation states with sophisticated flow
    public const STATE_INITIAL = 'initial';
    public const STATE_WELCOME = 'welcome';
    public const STATE_TEMPLATE_SELECTION = 'template_selection';
    public const STATE_REQUIREMENTS_GATHERING = 'requirements_gathering';
    public const STATE_REQUIREMENTS_REFINEMENT = 'requirements_refinement';
    public const STATE_STRUCTURE_GENERATION = 'structure_generation';
    public const STATE_STRUCTURE_REVIEW = 'structure_review';
    public const STATE_STRUCTURE_REFINEMENT = 'structure_refinement';
    public const STATE_CONTENT_GENERATION = 'content_generation';
    public const STATE_CONTENT_REVIEW = 'content_review';
    public const STATE_CONTENT_ENHANCEMENT = 'content_enhancement';
    public const STATE_QUALITY_VALIDATION = 'quality_validation';
    public const STATE_FINAL_REVIEW = 'final_review';
    public const STATE_WORDPRESS_CREATION = 'wordpress_creation';
    public const STATE_COMPLETED = 'completed';
    public const STATE_ERROR = 'error';
    public const STATE_PAUSED = 'paused';
    public const STATE_ABANDONED = 'abandoned';

    // State transition mappings
    private const STATE_TRANSITIONS = [
        self::STATE_INITIAL => [self::STATE_WELCOME],
        self::STATE_WELCOME => [self::STATE_TEMPLATE_SELECTION, self::STATE_PAUSED],
        self::STATE_TEMPLATE_SELECTION => [self::STATE_REQUIREMENTS_GATHERING, self::STATE_WELCOME, self::STATE_PAUSED],
        self::STATE_REQUIREMENTS_GATHERING => [self::STATE_REQUIREMENTS_REFINEMENT, self::STATE_STRUCTURE_GENERATION, self::STATE_TEMPLATE_SELECTION, self::STATE_PAUSED],
        self::STATE_REQUIREMENTS_REFINEMENT => [self::STATE_REQUIREMENTS_GATHERING, self::STATE_STRUCTURE_GENERATION, self::STATE_PAUSED],
        self::STATE_STRUCTURE_GENERATION => [self::STATE_STRUCTURE_REVIEW, self::STATE_REQUIREMENTS_GATHERING, self::STATE_ERROR],
        self::STATE_STRUCTURE_REVIEW => [self::STATE_STRUCTURE_REFINEMENT, self::STATE_CONTENT_GENERATION, self::STATE_REQUIREMENTS_GATHERING, self::STATE_PAUSED],
        self::STATE_STRUCTURE_REFINEMENT => [self::STATE_STRUCTURE_GENERATION, self::STATE_STRUCTURE_REVIEW, self::STATE_PAUSED],
        self::STATE_CONTENT_GENERATION => [self::STATE_CONTENT_REVIEW, self::STATE_STRUCTURE_REVIEW, self::STATE_ERROR],
        self::STATE_CONTENT_REVIEW => [self::STATE_CONTENT_ENHANCEMENT, self::STATE_QUALITY_VALIDATION, self::STATE_STRUCTURE_REVIEW, self::STATE_PAUSED],
        self::STATE_CONTENT_ENHANCEMENT => [self::STATE_CONTENT_GENERATION, self::STATE_CONTENT_REVIEW, self::STATE_PAUSED],
        self::STATE_QUALITY_VALIDATION => [self::STATE_FINAL_REVIEW, self::STATE_CONTENT_REVIEW, self::STATE_ERROR],
        self::STATE_FINAL_REVIEW => [self::STATE_WORDPRESS_CREATION, self::STATE_CONTENT_REVIEW, self::STATE_ABANDONED, self::STATE_PAUSED],
        self::STATE_WORDPRESS_CREATION => [self::STATE_COMPLETED, self::STATE_ERROR],
        self::STATE_COMPLETED => [],
        self::STATE_ERROR => [self::STATE_WELCOME, self::STATE_ABANDONED],
        self::STATE_PAUSED => [], // Can resume to any previous state
        self::STATE_ABANDONED => []
    ];

    // Conversation context and progress tracking
    private const MIN_CONFIDENCE_THRESHOLD = 0.7;
    private const SESSION_TIMEOUT_MINUTES = 60;
    private const AUTO_SAVE_INTERVAL_SECONDS = 30;

    public function __construct(
        ?LLMService $llmService = null,
        ?TemplateEngine $templateEngine = null,
        ?ConversationManager $conversationManager = null,
        ?DatabaseService $databaseService = null,
        ?QualityAssuranceService $qualityService = null,
        ?QualityFeedbackService $feedbackService = null,
        ?QualityGatesService $gatesService = null
    ) {
        $this->llmService = $llmService;
        $this->templateEngine = $templateEngine ?: new TemplateEngine($llmService);
        $this->conversationManager = $conversationManager ?: new ConversationManager($databaseService);
        $this->databaseService = $databaseService ?: new DatabaseService();
        $this->qualityService = $qualityService ?: new QualityAssuranceService();
        $this->feedbackService = $feedbackService ?: new QualityFeedbackService();
        $this->gatesService = $gatesService ?: new QualityGatesService();
        $this->currentSession = null;
        
        // Initialize quality services
        $this->qualityService->init();
        $this->feedbackService->init();
        $this->gatesService->init();
    }

    /**
     * Start a new course generation conversation with enhanced state management
     */
    public function startConversation(array $initialData = [], ?int $userId = null): array
    {
        // Create new conversation session
        $this->currentSession = $this->conversationManager->createSession([
            'user_id' => $userId ?: get_current_user_id(),
            'context' => 'course_creation',
            'initial_data' => $initialData,
            'state' => self::STATE_INITIAL
        ]);

        // Transition to welcome state
        $this->transitionToState(self::STATE_WELCOME);
        
        $response = [
            'session_id' => $this->currentSession->getSessionId(),
            'state' => self::STATE_WELCOME,
            'message' => $this->getWelcomeMessage(),
            'suggested_questions' => $this->getInitialQuestions(),
            'available_templates' => $this->getAvailableTemplates(),
            'progress' => $this->currentSession->getProgress(),
            'context' => $this->currentSession->getContext(),
            'can_backtrack' => $this->canBacktrack(),
            'next_possible_states' => $this->getNextPossibleStates()
        ];

        $this->currentSession->addMessage('system', 'Conversation started', $response);
        $this->autoSaveSession();
        
        return $response;
    }

    /**
     * Resume an existing conversation session
     */
    public function resumeConversation(string $sessionId): array
    {
        $this->currentSession = $this->conversationManager->loadSession($sessionId);
        
        if (!$this->currentSession) {
            throw new \Exception('Session not found or expired');
        }

        // Check for session timeout
        if ($this->currentSession->isExpired(self::SESSION_TIMEOUT_MINUTES)) {
            return $this->handleSessionTimeout();
        }

        // Resume from current state
        $currentState = $this->currentSession->getCurrentState();
        
        $response = [
            'session_id' => $sessionId,
            'state' => $currentState,
            'message' => $this->getResumeMessage(),
            'context' => $this->currentSession->getContext(),
            'progress' => $this->currentSession->getProgress(),
            'conversation_history' => $this->currentSession->getRecentMessages(5),
            'can_backtrack' => $this->canBacktrack(),
            'next_possible_states' => $this->getNextPossibleStates()
        ];

        $this->currentSession->addMessage('system', 'Conversation resumed', $response);
        $this->autoSaveSession();
        
        return $response;
    }

    /**
     * Process user message with advanced state management and context tracking
     */
    public function processMessage(string $message, array $context = []): array
    {
        if (!$this->currentSession) {
            throw new \Exception('No active session found');
        }

        $currentState = $this->currentSession->getCurrentState();
        $this->currentSession->addMessage('user', $message, $context);
        
        try {
            // Parse user intent and extract any special commands
            $intent = $this->parseUserIntent($message);
            
            // Handle special commands (backtrack, restart, etc.)
            if ($intent['command']) {
                return $this->handleSpecialCommand($intent['command'], $intent['parameters']);
            }

            // Process message based on current state
            $response = match ($currentState) {
                self::STATE_WELCOME => $this->handleWelcome($message, $context),
                self::STATE_TEMPLATE_SELECTION => $this->handleTemplateSelection($message, $context),
                self::STATE_REQUIREMENTS_GATHERING => $this->handleRequirementsGathering($message, $context),
                self::STATE_REQUIREMENTS_REFINEMENT => $this->handleRequirementsRefinement($message, $context),
                self::STATE_STRUCTURE_GENERATION => $this->handleStructureGeneration($message, $context),
                self::STATE_STRUCTURE_REVIEW => $this->handleStructureReview($message, $context),
                self::STATE_STRUCTURE_REFINEMENT => $this->handleStructureRefinement($message, $context),
                self::STATE_CONTENT_GENERATION => $this->handleContentGeneration($message, $context),
                self::STATE_CONTENT_REVIEW => $this->handleContentReview($message, $context),
                self::STATE_CONTENT_ENHANCEMENT => $this->handleContentEnhancement($message, $context),
                self::STATE_QUALITY_VALIDATION => $this->handleQualityValidation($message, $context),
                self::STATE_FINAL_REVIEW => $this->handleFinalReview($message, $context),
                self::STATE_WORDPRESS_CREATION => $this->handleWordPressCreation($message, $context),
                self::STATE_PAUSED => $this->handlePausedState($message, $context),
                default => $this->handleUnknownState($message)
            };

            // Add conversation metadata
            $response = array_merge($response, [
                'session_id' => $this->currentSession->getSessionId(),
                'progress' => $this->currentSession->getProgress(),
                'confidence_score' => $this->calculateConfidenceScore(),
                'can_backtrack' => $this->canBacktrack(),
                'next_possible_states' => $this->getNextPossibleStates(),
                'auto_save_enabled' => true,
                'session_timeout' => $this->currentSession->getTimeUntilExpiry(self::SESSION_TIMEOUT_MINUTES)
            ]);

            $this->currentSession->addMessage('assistant', $response['message'], $response);
            $this->autoSaveSession();
            
            return $response;

        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Transition to a new conversation state with validation
     */
    public function transitionToState(string $newState, array $transitionData = []): bool
    {
        if (!$this->currentSession) {
            throw new \Exception('No active session found');
        }

        $currentState = $this->currentSession->getCurrentState();
        
        // Validate state transition
        if (!$this->isValidTransition($currentState, $newState)) {
            throw new \Exception("Invalid state transition from {$currentState} to {$newState}");
        }

        // Save current state to history for backtracking
        $this->currentSession->saveStateToHistory($currentState, $transitionData);
        
        // Update current state
        $this->currentSession->setCurrentState($newState);
        $this->currentSession->updateProgress($this->calculateProgressForState($newState));
        
        // Log transition
        $this->currentSession->addMessage('system', "State transitioned from {$currentState} to {$newState}", $transitionData);
        
        return true;
    }

    /**
     * Backtrack to a previous conversation state
     */
    public function backtrackToState(?string $targetState = null): array
    {
        if (!$this->currentSession) {
            throw new \Exception('No active session found');
        }

        $stateHistory = $this->currentSession->getStateHistory();
        
        if (empty($stateHistory)) {
            throw new \Exception('No previous states available for backtracking');
        }

        // If no target state specified, go back one step
        if (!$targetState) {
            $previousState = array_pop($stateHistory);
            $targetState = $previousState['state'];
        }

        // Validate target state exists in history
        $targetStateData = null;
        foreach (array_reverse($stateHistory) as $historyItem) {
            if ($historyItem['state'] === $targetState) {
                $targetStateData = $historyItem;
                break;
            }
        }

        if (!$targetStateData) {
            throw new \Exception("Target state {$targetState} not found in conversation history");
        }

        // Restore state
        $this->currentSession->setCurrentState($targetState);
        $this->currentSession->restoreContext($targetStateData['context']);
        $this->currentSession->updateProgress($this->calculateProgressForState($targetState));
        
        $response = [
            'state' => $targetState,
            'message' => "Let's go back to {$targetState}. What would you like to do differently?",
            'restored_context' => $targetStateData['context'],
            'backtrack_successful' => true,
            'available_states' => array_unique(array_column($stateHistory, 'state'))
        ];

        $this->currentSession->addMessage('system', 'Backtracked to previous state', $response);
        $this->autoSaveSession();
        
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

        // Provide real-time quality feedback during structure generation
        if ($this->feedbackService) {
            $this->feedbackService->provideRealTimeFeedback($course, 'structure_generation');
        }

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

        // Provide section-level feedback
        if ($this->feedbackService) {
            foreach ($course->getSections() as $index => $section) {
                $this->feedbackService->provideSectionFeedback($course, $index);
            }
        }

        // Preliminary quality validation for structure
        if ($this->qualityService) {
            $qualityReport = $this->qualityService->assessCourse($course);
            $course->addMetadata('preliminary_quality_score', $qualityReport->get('overall_score'));
            $course->addMetadata('quality_indicators', $this->feedbackService->getQualityIndicators($course));
        }

        return $course;
    }

    /**
     * Export conversation session data
     */
    public function exportSession(): array
    {
        if (!$this->currentSession) {
            throw new \Exception('No active session found');
        }

        return [
            'session_id' => $this->currentSession->getSessionId(),
            'export_timestamp' => current_time('timestamp'),
            'session_data' => $this->currentSession->toArray(),
            'conversation_history' => $this->currentSession->getMessages(),
            'state_history' => $this->currentSession->getStateHistory(),
            'context' => $this->currentSession->getContext(),
            'metadata' => $this->currentSession->getMetadata()
        ];
    }

    /**
     * Import conversation session data
     */
    public function importSession(array $sessionData): ConversationSession
    {
        $session = $this->conversationManager->createSessionFromData($sessionData);
        $this->currentSession = $session;
        
        return $session;
    }

    /**
     * Pause current conversation
     */
    public function pauseConversation(string $reason = ''): array
    {
        if (!$this->currentSession) {
            throw new \Exception('No active session found');
        }

        $previousState = $this->currentSession->getCurrentState();
        $this->currentSession->setPausedFromState($previousState);
        $this->transitionToState(self::STATE_PAUSED, ['reason' => $reason]);
        
        $response = [
            'state' => self::STATE_PAUSED,
            'message' => 'Conversation paused. You can resume anytime.',
            'paused_from_state' => $previousState,
            'pause_reason' => $reason,
            'resume_token' => $this->currentSession->getSessionId()
        ];

        $this->currentSession->addMessage('system', 'Conversation paused', $response);
        $this->autoSaveSession();
        
        return $response;
    }

    /**
     * Create WordPress course from generated course
     */
    public function createWordPressCourse(GeneratedCourse $course): int
    {
        if (!$this->currentSession) {
            throw new \Exception('No active session found');
        }

        // Transition to WordPress creation state
        $this->transitionToState(self::STATE_WORDPRESS_CREATION);
        
        // Validate course before creation
        $errors = $course->validate();
        if (!empty($errors)) {
            $this->transitionToState(self::STATE_ERROR, ['validation_errors' => $errors]);
            throw new \Exception('Course validation failed: ' . implode(', ', $errors));
        }

        // Enforce quality gates before creation
        if ($this->gatesService) {
            try {
                $this->gatesService->enforceProductionGates($course);
            } catch (\Exception $e) {
                $this->transitionToState(self::STATE_ERROR, ['quality_gates_error' => $e->getMessage()]);
                throw new \Exception('Quality gates validation failed: ' . $e->getMessage());
            }
        }

        // Final quality assessment and improvement suggestions
        if ($this->qualityService && $this->feedbackService) {
            $finalQualityReport = $this->qualityService->assessCourse($course);
            $course->addMetadata('final_quality_report', $finalQualityReport->toArray());
            
            // Generate improvement suggestions if needed
            if ($finalQualityReport->get('overall_score') < 90) {
                $improvements = $this->feedbackService->generateImprovementSuggestions($course, $finalQualityReport);
                $course->addMetadata('improvement_suggestions', $improvements);
            }

            // Provide final feedback
            $this->feedbackService->provideFinalFeedback($course);
        }

        try {
            // Create the course in WordPress
            $courseId = $course->createWordPressCourse();

            // Update session with created course
            $this->currentSession->setContext('created_course_id', $courseId);
            $this->currentSession->setContext('course_url', admin_url("post.php?post={$courseId}&action=edit"));
            
            // Transition to completed state
            $this->transitionToState(self::STATE_COMPLETED, ['course_id' => $courseId]);
            
            return $courseId;
            
        } catch (\Exception $e) {
            $this->transitionToState(self::STATE_ERROR, ['creation_error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Handle template selection
     */
    private function handleTemplateSelection(string $message, array $context): array
    {
        // Use TemplateEngine for sophisticated template selection
        $userPreferences = $context['user_preferences'] ?? [];
        $sessionContext = $this->currentSession ? $this->currentSession->getContext() : [];
        
        // Get intelligent template recommendations from TemplateEngine
        $templateRecommendations = $this->templateEngine->selectOptimalTemplate(
            $message,
            $userPreferences,
            array_merge($context, $sessionContext)
        );
        
        $primaryRecommendation = $templateRecommendations['primary_recommendation'] ?? null;
        
        if ($primaryRecommendation && $primaryRecommendation['confidence'] >= self::MIN_CONFIDENCE_THRESHOLD) {
            // High confidence - proceed with recommended template
            $templateType = $primaryRecommendation['type'];
            $selectedTemplate = CourseTemplate::getTemplate($templateType);
            
            if ($selectedTemplate) {
                // Store template selection with metadata
                $this->currentSession->setData('selected_template', $selectedTemplate);
                $this->currentSession->setData('template_selection_metadata', [
                    'recommendations' => $templateRecommendations,
                    'selection_reasoning' => $templateRecommendations['reasoning'],
                    'confidence_score' => $templateRecommendations['confidence_score'],
                    'alternative_options' => $templateRecommendations['alternative_recommendations']
                ]);
                
                // Get adaptive questions based on context
                $adaptiveQuestions = $selectedTemplate->getAdaptiveQuestions([
                    'skill_level' => $context['skill_level'] ?? 'intermediate',
                    'user_experience' => $context['user_experience'] ?? null,
                    'course_type' => $templateType
                ]);
                
                $response = [
                    'state' => self::STATE_REQUIREMENTS_GATHERING,
                    'message' => $this->generateTemplateSelectionMessage($selectedTemplate, $templateRecommendations),
                    'template' => $selectedTemplate->toArray(),
                    'questions' => $adaptiveQuestions,
                    'recommendations_metadata' => [
                        'confidence' => $templateRecommendations['confidence_score'],
                        'reasoning' => $templateRecommendations['reasoning'],
                        'alternatives' => $templateRecommendations['alternative_recommendations'],
                        'mixing_suggestions' => $templateRecommendations['mixing_suggestions']
                    ],
                    'progress_indicators' => [
                        'estimated_duration' => $selectedTemplate->getEstimatedDuration(),
                        'total_lessons' => $selectedTemplate->getTotalLessons(),
                        'difficulty_rating' => $selectedTemplate->getDifficultyRating()
                    ]
                ];

                $this->transitionToState(self::STATE_REQUIREMENTS_GATHERING);
                return $response;
            }
        }
        
        // Lower confidence or no clear match - show options
        $response = [
            'state' => self::STATE_TEMPLATE_SELECTION,
            'message' => $this->generateTemplateOptionsMessage($templateRecommendations),
            'available_templates' => $this->getEnhancedTemplateOptions($templateRecommendations),
            'recommendations' => $templateRecommendations,
            'help_text' => 'Describe your course idea and I\'ll recommend the best template for you.'
        ];
        
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
     * Handle quality validation stage
     */
    private function handleQualityValidation(string $message, array $context): array
    {
        $course = $this->conversationState['generated_course'] ?? null;
        
        if (!$course instanceof GeneratedCourse) {
            return [
                'state' => self::STATE_ERROR,
                'message' => 'No course found for quality validation',
                'error' => 'Missing course data'
            ];
        }

        // Perform comprehensive quality assessment
        if ($this->qualityService) {
            $qualityReport = $this->qualityService->assessCourse($course);
            
            // Check quality gates
            $gateResult = $this->gatesService ? 
                $this->gatesService->checkQualityGate($course, 'production_ready') : 
                ['passed' => true, 'failed_criteria' => []];

            if (!$gateResult['passed']) {
                // Apply automatic improvements if possible
                if ($this->feedbackService && !empty($gateResult['auto_fixable_issues'])) {
                    $improvements = $this->feedbackService->applyAutomaticImprovements($course, $gateResult['auto_fixable_issues']);
                    
                    // Re-assess after improvements
                    if ($improvements['applied_count'] > 0) {
                        $qualityReport = $this->qualityService->assessCourse($course);
                        $gateResult = $this->gatesService->checkQualityGate($course, 'production_ready');
                    }
                }

                if (!$gateResult['passed']) {
                    return [
                        'state' => self::STATE_QUALITY_VALIDATION,
                        'message' => 'Your course needs some improvements before it can be published. Here\'s what needs attention:',
                        'quality_report' => $qualityReport->toApiResponse(),
                        'failed_criteria' => $gateResult['failed_criteria'],
                        'improvements_needed' => $gateResult['improvements_needed'],
                        'auto_fixes_applied' => $improvements['applied_count'] ?? 0,
                        'actions' => [
                            'apply_suggestions' => 'Apply recommended improvements',
                            'manual_review' => 'Review and fix manually',
                            'proceed_anyway' => 'Proceed with current quality (not recommended)'
                        ]
                    ];
                }
            }

            // Quality validation passed
            return [
                'state' => self::STATE_FINAL_REVIEW,
                'message' => '🎉 Excellent! Your course has passed all quality checks and is ready for final review.',
                'quality_report' => $qualityReport->toApiResponse(),
                'quality_score' => $qualityReport->get('overall_score'),
                'quality_level' => $qualityReport->getOverallQualityLevel(),
                'next_step' => 'final_review'
            ];
        }

        // Fallback if quality service not available
        return [
            'state' => self::STATE_FINAL_REVIEW,
            'message' => 'Quality validation completed. Your course is ready for final review.',
            'next_step' => 'final_review'
        ];
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
        $content = '';
        
        if ($this->llmService) {
            $content = $this->llmService->generateLessonContent($sectionTitle, $lessonNumber, $requirements);
        } else {
            // Basic content generation without LLM
            $content = "This is lesson {$lessonNumber} of the {$sectionTitle} section. Content will be generated based on your course requirements.";
        }

        // Provide real-time feedback on lesson content generation
        if ($this->feedbackService) {
            $lessonData = [
                'title' => "Lesson {$lessonNumber}: {$sectionTitle}",
                'content' => $content,
                'section_title' => $sectionTitle,
                'lesson_number' => $lessonNumber
            ];
            
            $this->feedbackService->provideLessonFeedback($this->conversationState['generated_course'] ?? null, $lessonData);
        }

        return $content;
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
        $templateTypes = CourseTemplate::getTemplateTypes();
        $templates = CourseTemplate::getPredefinedTemplates();
        $result = [];
        
        foreach ($templateTypes as $type => $typeInfo) {
            $template = $templates[$type] ?? null;
            
            if ($template) {
                $result[$type] = [
                    'type' => $type,
                    'name' => $typeInfo['name'],
                    'title' => $typeInfo['name'],
                    'description' => $typeInfo['description'],
                    'best_for' => $typeInfo['best_for'],
                    'lesson_count' => $template->getTotalLessons(),
                    'estimated_duration' => $template->getEstimatedDuration(),
                    'difficulty_rating' => $template->getDifficultyRating(),
                    'optimal_lesson_length' => $template->getOptimalLessonLength(),
                    'assessment_methods' => array_keys($template->getAssessmentMethods()),
                    'prerequisites' => $template->getPrerequisites(),
                    'learning_progression' => array_keys($template->getLearningProgression())
                ];
            }
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

    /**
     * Generate personalized template selection message
     */
    private function generateTemplateSelectionMessage(CourseTemplate $template, array $recommendations): string
    {
        $message = "Perfect! Based on your description, I've selected the {$template->getTemplateType()} template ";
        $message .= "with {$this->formatConfidence($recommendations['confidence_score'])} confidence. ";
        
        if (!empty($recommendations['reasoning'])) {
            $message .= $recommendations['reasoning'] . " ";
        }
        
        $message .= "\n\nThis template includes:\n";
        $message .= "• {$template->getTotalLessons()} lessons\n";
        $message .= "• Estimated duration: {$template->getEstimatedDuration()} weeks\n";
        $message .= "• Difficulty level: {$template->getDifficultyRating()}\n\n";
        
        $message .= "Let me ask you some questions to customize the course for your specific needs.";
        
        return $message;
    }

    /**
     * Generate template options message when confidence is low
     */
    private function generateTemplateOptionsMessage(array $recommendations): string
    {
        $message = "I can see several possible directions for your course. ";
        
        if (!empty($recommendations['primary_recommendation'])) {
            $primary = $recommendations['primary_recommendation'];
            $message .= "My top recommendation is the {$primary['type']} template ";
            $message .= "({$this->formatConfidence($primary['confidence'])} confidence), ";
            
            if (!empty($primary['reasoning'])) {
                $message .= "because {$primary['reasoning']}. ";
            }
        }
        
        $message .= "\n\nYou can either:\n";
        $message .= "1. Choose from the recommended templates below\n";
        $message .= "2. Provide more details about your course for a better recommendation\n";
        $message .= "3. Explore hybrid approaches combining multiple templates";
        
        return $message;
    }

    /**
     * Get enhanced template options with recommendations
     */
    private function getEnhancedTemplateOptions(array $recommendations): array
    {
        $templateTypes = CourseTemplate::getTemplateTypes();
        $options = [];
        
        foreach ($templateTypes as $type => $info) {
            $template = CourseTemplate::getTemplate($type);
            if (!$template) continue;
            
            // Find recommendation data for this template
            $recommendationData = null;
            foreach ([$recommendations['primary_recommendation']] + ($recommendations['alternative_recommendations'] ?? []) as $rec) {
                if ($rec && $rec['type'] === $type) {
                    $recommendationData = $rec;
                    break;
                }
            }
            
            $option = [
                'type' => $type,
                'name' => $info['name'],
                'description' => $info['description'],
                'best_for' => $info['best_for'],
                'total_lessons' => $template->getTotalLessons(),
                'estimated_duration' => $template->getEstimatedDuration(),
                'difficulty_rating' => $template->getDifficultyRating(),
                'optimal_lesson_length' => $template->getOptimalLessonLength()
            ];
            
            if ($recommendationData) {
                $option['recommendation_confidence'] = $recommendationData['confidence'];
                $option['recommendation_reasoning'] = $recommendationData['reasoning'] ?? '';
                $option['is_recommended'] = true;
            } else {
                $option['is_recommended'] = false;
            }
            
            $options[] = $option;
        }
        
        // Sort by recommendation confidence (recommended first)
        usort($options, function($a, $b) {
            if ($a['is_recommended'] && !$b['is_recommended']) return -1;
            if (!$a['is_recommended'] && $b['is_recommended']) return 1;
            
            $aConfidence = $a['recommendation_confidence'] ?? 0;
            $bConfidence = $b['recommendation_confidence'] ?? 0;
            
            return $bConfidence <=> $aConfidence;
        });
        
        return $options;
    }

    /**
     * Format confidence score as percentage
     */
    private function formatConfidence(float $confidence): string
    {
        return round($confidence * 100) . '%';
    }

    /**
     * Get template analytics for reporting
     */
    public function getTemplateAnalytics(): array
    {
        return $this->templateEngine->getTemplateAnalytics();
    }

    /**
     * Create hybrid template based on user requirements
     */
    public function createHybridTemplate(
        string $primaryType,
        array $secondaryTypes,
        array $customizations = []
    ): CourseTemplate {
        return $this->templateEngine->createHybridTemplate(
            $primaryType,
            $secondaryTypes,
            $customizations
        );
    }

    /**
     * Adapt template based on user feedback and performance
     */
    public function adaptTemplate(
        CourseTemplate $template,
        array $userFeedback,
        array $performanceData = []
    ): CourseTemplate {
        return $this->templateEngine->adaptTemplate($template, $userFeedback, $performanceData);
    }

    /**
     * Get improvement recommendations for existing courses
     */
    public function getImprovementRecommendations(
        CourseTemplate $template,
        array $performanceData,
        array $userFeedback = []
    ): array {
        return $this->templateEngine->getImprovementRecommendations(
            $template,
            $performanceData,
            $userFeedback
        );
    }
}