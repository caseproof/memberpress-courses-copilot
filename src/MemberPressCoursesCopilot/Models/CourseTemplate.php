<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Models;

/**
 * Course Template Model
 *
 * Represents a course template with predefined structure, questions, and quality checks.
 * Used to generate course outlines based on different template types.
 */
class CourseTemplate
{
    // Template type constants
    const TEMPLATE_TECHNICAL     = 'technical';
    const TEMPLATE_BUSINESS      = 'business';
    const TEMPLATE_CREATIVE      = 'creative';
    const TEMPLATE_ACADEMIC      = 'academic';
    const TEMPLATE_SKILL_BASED   = 'skill_based';
    const TEMPLATE_CERTIFICATION = 'certification';

    // Assessment type constants
    const ASSESSMENT_QUIZ        = 'quiz';
    const ASSESSMENT_PRACTICAL   = 'practical';
    const ASSESSMENT_PROJECT     = 'project';
    const ASSESSMENT_EXAM        = 'exam';
    const ASSESSMENT_PEER_REVIEW = 'peer_review';

    private string $templateType;
    private array $defaultStructure;
    private array $suggestedQuestions;
    private array $qualityChecks;
    private array $aiPrompts;
    private array $learningProgression;
    private array $assessmentMethods;
    private array $prerequisites;
    private array $customizationOptions;
    private array $industryTerminology;
    private array $resourceRecommendations;
    private int $optimalLessonLength;
    private array $pacingGuidelines;

    public function __construct(
        string $templateType,
        array $defaultStructure = [],
        array $suggestedQuestions = [],
        array $qualityChecks = [],
        array $aiPrompts = [],
        array $learningProgression = [],
        array $assessmentMethods = [],
        array $prerequisites = [],
        array $customizationOptions = [],
        array $industryTerminology = [],
        array $resourceRecommendations = [],
        int $optimalLessonLength = 30,
        array $pacingGuidelines = []
    ) {
        $this->templateType            = $templateType;
        $this->defaultStructure        = $defaultStructure;
        $this->suggestedQuestions      = $suggestedQuestions;
        $this->qualityChecks           = $qualityChecks;
        $this->aiPrompts               = $aiPrompts;
        $this->learningProgression     = $learningProgression;
        $this->assessmentMethods       = $assessmentMethods;
        $this->prerequisites           = $prerequisites;
        $this->customizationOptions    = $customizationOptions;
        $this->industryTerminology     = $industryTerminology;
        $this->resourceRecommendations = $resourceRecommendations;
        $this->optimalLessonLength     = $optimalLessonLength;
        $this->pacingGuidelines        = $pacingGuidelines;
    }

    /**
     * Get all available template types
     */
    public static function getTemplateTypes(): array
    {
        return [
            self::TEMPLATE_TECHNICAL,
            self::TEMPLATE_BUSINESS,
            self::TEMPLATE_CREATIVE,
            self::TEMPLATE_ACADEMIC,
            self::TEMPLATE_SKILL_BASED,
            self::TEMPLATE_CERTIFICATION,
        ];
    }

    /**
     * Get predefined templates
     */
    public static function getPredefinedTemplates(): array
    {
        return [
            self::TEMPLATE_TECHNICAL     => self::getTechnicalTemplate(),
            self::TEMPLATE_BUSINESS      => self::getBusinessTemplate(),
            self::TEMPLATE_CREATIVE      => self::getCreativeTemplate(),
            self::TEMPLATE_ACADEMIC      => self::getAcademicTemplate(),
            self::TEMPLATE_SKILL_BASED   => self::getSkillBasedTemplate(),
            self::TEMPLATE_CERTIFICATION => self::getCertificationTemplate(),
        ];
    }

    /**
     * Recommend template based on course description
     */
    public static function recommendTemplate(string $courseDescription): array
    {
        $description     = strtolower($courseDescription);
        $recommendations = [];

        // Technical keywords
        $technicalKeywords = ['programming', 'coding', 'software', 'development', 'algorithm', 'database', 'api', 'framework', 'javascript', 'python'];
        $technicalScore    = self::calculateKeywordScore($description, $technicalKeywords);
        if ($technicalScore > 0) {
            $recommendations[self::TEMPLATE_TECHNICAL] = $technicalScore;
        }

        // Business keywords
        $businessKeywords = ['business', 'management', 'strategy', 'marketing', 'finance', 'leadership', 'entrepreneurship', 'sales', 'operations'];
        $businessScore    = self::calculateKeywordScore($description, $businessKeywords);
        if ($businessScore > 0) {
            $recommendations[self::TEMPLATE_BUSINESS] = $businessScore;
        }

        // Creative keywords
        $creativeKeywords = ['design', 'creative', 'art', 'music', 'writing', 'photography', 'video', 'graphics', 'illustration', 'animation'];
        $creativeScore    = self::calculateKeywordScore($description, $creativeKeywords);
        if ($creativeScore > 0) {
            $recommendations[self::TEMPLATE_CREATIVE] = $creativeScore;
        }

        // Academic keywords
        $academicKeywords = ['theory', 'research', 'academic', 'study', 'analysis', 'literature', 'history', 'science', 'mathematics'];
        $academicScore    = self::calculateKeywordScore($description, $academicKeywords);
        if ($academicScore > 0) {
            $recommendations[self::TEMPLATE_ACADEMIC] = $academicScore;
        }

        // Skill-based keywords
        $skillKeywords = ['hands-on', 'practical', 'skill', 'workshop', 'tutorial', 'how-to', 'step-by-step', 'practice', 'exercise'];
        $skillScore    = self::calculateKeywordScore($description, $skillKeywords);
        if ($skillScore > 0) {
            $recommendations[self::TEMPLATE_SKILL_BASED] = $skillScore;
        }

        // Certification keywords
        $certKeywords = ['certification', 'exam', 'preparation', 'test', 'certificate', 'qualification', 'credential', 'accreditation'];
        $certScore    = self::calculateKeywordScore($description, $certKeywords);
        if ($certScore > 0) {
            $recommendations[self::TEMPLATE_CERTIFICATION] = $certScore;
        }

        // Sort recommendations by score
        arsort($recommendations);

        return $recommendations;
    }

    /**
     * Calculate keyword score
     */
    private static function calculateKeywordScore(string $text, array $keywords): float
    {
        $score = 0;
        foreach ($keywords as $keyword) {
            $score += substr_count($text, $keyword);
        }
        return $score / count($keywords);
    }

    /**
     * Get technical course template
     */
    private static function getTechnicalTemplate(): array
    {
        return [
            'default_structure'     => [
                'sections' => [
                    [
                        'title'   => 'Introduction and Setup',
                        'lessons' => 3,
                    ],
                    [
                        'title'   => 'Core Concepts',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Implementation',
                        'lessons' => 6,
                    ],
                    [
                        'title'   => 'Best Practices',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Advanced Topics',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Real-world Projects',
                        'lessons' => 3,
                    ],
                ],
            ],
            'suggested_questions'   => [
                'What prerequisites do students need?',
                'What development environment will they use?',
                'What specific technologies/frameworks are covered?',
                'What projects will students build?',
                'How will code quality be assessed?',
            ],
            'quality_checks'        => [
                'Include hands-on coding exercises',
                'Provide code examples and repositories',
                'Cover debugging techniques',
                'Include performance optimization topics',
                'Add security considerations',
            ],
            'assessment_methods'    => [
                self::ASSESSMENT_PRACTICAL,
                self::ASSESSMENT_PROJECT,
                self::ASSESSMENT_QUIZ,
            ],
            'optimal_lesson_length' => 25,
            'learning_progression'  => [
                'beginner'     => [
                    'duration_weeks' => 4,
                    'focus'          => 'Fundamentals',
                ],
                'intermediate' => [
                    'duration_weeks' => 6,
                    'focus'          => 'Application',
                ],
                'advanced'     => [
                    'duration_weeks' => 4,
                    'focus'          => 'Optimization',
                ],
            ],
        ];
    }

    /**
     * Get business course template
     */
    private static function getBusinessTemplate(): array
    {
        return [
            'default_structure'     => [
                'sections' => [
                    [
                        'title'   => 'Course Overview and Objectives',
                        'lessons' => 2,
                    ],
                    [
                        'title'   => 'Foundational Concepts',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Strategic Planning',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Implementation Strategies',
                        'lessons' => 6,
                    ],
                    [
                        'title'   => 'Case Studies',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Measuring Success',
                        'lessons' => 3,
                    ],
                ],
            ],
            'suggested_questions'   => [
                'What business challenges does this address?',
                'Who is the target audience (executives, managers, entrepreneurs)?',
                'What industry examples will be used?',
                'What tools/frameworks will be taught?',
                'How will ROI be demonstrated?',
            ],
            'quality_checks'        => [
                'Include real-world case studies',
                'Provide templates and frameworks',
                'Cover financial implications',
                'Include change management aspects',
                'Add measurable outcomes',
            ],
            'assessment_methods'    => [
                self::ASSESSMENT_PROJECT,
                self::ASSESSMENT_QUIZ,
                self::ASSESSMENT_PEER_REVIEW,
            ],
            'optimal_lesson_length' => 30,
            'learning_progression'  => [
                'foundational' => [
                    'duration_weeks' => 3,
                    'focus'          => 'Core Concepts',
                ],
                'practical'    => [
                    'duration_weeks' => 5,
                    'focus'          => 'Application',
                ],
                'strategic'    => [
                    'duration_weeks' => 4,
                    'focus'          => 'Leadership',
                ],
            ],
        ];
    }

    /**
     * Get creative course template
     */
    private static function getCreativeTemplate(): array
    {
        return [
            'default_structure'     => [
                'sections' => [
                    [
                        'title'   => 'Creative Foundation',
                        'lessons' => 3,
                    ],
                    [
                        'title'   => 'Tools and Techniques',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Creative Process',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Style Development',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Portfolio Building',
                        'lessons' => 3,
                    ],
                    [
                        'title'   => 'Sharing Your Work',
                        'lessons' => 2,
                    ],
                ],
            ],
            'suggested_questions'   => [
                'What creative medium is being taught?',
                'What tools/software will students need?',
                'What style or approach is emphasized?',
                'How will creativity be nurtured?',
                'What portfolio pieces will be created?',
            ],
            'quality_checks'        => [
                'Include visual examples',
                'Provide creative exercises',
                'Cover both technique and expression',
                'Include peer feedback opportunities',
                'Add inspiration resources',
            ],
            'assessment_methods'    => [
                self::ASSESSMENT_PROJECT,
                self::ASSESSMENT_PEER_REVIEW,
                self::ASSESSMENT_PRACTICAL,
            ],
            'optimal_lesson_length' => 35,
            'learning_progression'  => [
                'exploration' => [
                    'duration_weeks' => 3,
                    'focus'          => 'Discovery',
                ],
                'development' => [
                    'duration_weeks' => 5,
                    'focus'          => 'Skill Building',
                ],
                'mastery'     => [
                    'duration_weeks' => 4,
                    'focus'          => 'Personal Style',
                ],
            ],
        ];
    }

    /**
     * Get academic course template
     */
    private static function getAcademicTemplate(): array
    {
        return [
            'default_structure'     => [
                'sections' => [
                    [
                        'title'   => 'Course Introduction',
                        'lessons' => 2,
                    ],
                    [
                        'title'   => 'Theoretical Foundations',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Literature Review',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Research Methods',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Critical Analysis',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Synthesis and Conclusions',
                        'lessons' => 3,
                    ],
                ],
            ],
            'suggested_questions'   => [
                'What academic discipline is this for?',
                'What level of prior knowledge is assumed?',
                'What primary sources will be used?',
                'What research skills will be developed?',
                'How will critical thinking be assessed?',
            ],
            'quality_checks'        => [
                'Include scholarly references',
                'Provide research assignments',
                'Cover citation standards',
                'Include discussion topics',
                'Add academic writing guidance',
            ],
            'assessment_methods'    => [
                self::ASSESSMENT_EXAM,
                self::ASSESSMENT_PROJECT,
                self::ASSESSMENT_QUIZ,
            ],
            'optimal_lesson_length' => 45,
            'learning_progression'  => [
                'foundational' => [
                    'duration_weeks' => 4,
                    'focus'          => 'Theory',
                ],
                'analytical'   => [
                    'duration_weeks' => 6,
                    'focus'          => 'Research',
                ],
                'synthesis'    => [
                    'duration_weeks' => 4,
                    'focus'          => 'Application',
                ],
            ],
        ];
    }

    /**
     * Get skill-based course template
     */
    private static function getSkillBasedTemplate(): array
    {
        return [
            'default_structure'     => [
                'sections' => [
                    [
                        'title'   => 'Getting Started',
                        'lessons' => 2,
                    ],
                    [
                        'title'   => 'Basic Techniques',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Intermediate Skills',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Advanced Techniques',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Practice Projects',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Mastery and Beyond',
                        'lessons' => 3,
                    ],
                ],
            ],
            'suggested_questions'   => [
                'What specific skill is being taught?',
                'What equipment/materials are needed?',
                'How will progress be measured?',
                'What practice exercises are included?',
                'How long to achieve proficiency?',
            ],
            'quality_checks'        => [
                'Include step-by-step instructions',
                'Provide practice exercises',
                'Cover common mistakes',
                'Include progress milestones',
                'Add troubleshooting guides',
            ],
            'assessment_methods'    => [
                self::ASSESSMENT_PRACTICAL,
                self::ASSESSMENT_PROJECT,
                self::ASSESSMENT_PEER_REVIEW,
            ],
            'optimal_lesson_length' => 20,
            'learning_progression'  => [
                'beginner'     => [
                    'duration_weeks' => 3,
                    'focus'          => 'Basics',
                ],
                'intermediate' => [
                    'duration_weeks' => 5,
                    'focus'          => 'Practice',
                ],
                'advanced'     => [
                    'duration_weeks' => 4,
                    'focus'          => 'Mastery',
                ],
            ],
        ];
    }

    /**
     * Get certification preparation template
     */
    private static function getCertificationTemplate(): array
    {
        return [
            'default_structure'     => [
                'sections' => [
                    [
                        'title'   => 'Certification Overview',
                        'lessons' => 2,
                    ],
                    [
                        'title'   => 'Domain 1 Concepts',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Domain 2 Concepts',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Domain 3 Concepts',
                        'lessons' => 5,
                    ],
                    [
                        'title'   => 'Practice Exams',
                        'lessons' => 4,
                    ],
                    [
                        'title'   => 'Exam Strategies',
                        'lessons' => 2,
                    ],
                ],
            ],
            'suggested_questions'   => [
                'Which certification is this for?',
                'What are the exam domains/objectives?',
                'What is the passing score?',
                'How many practice questions are included?',
                'What study resources are recommended?',
            ],
            'quality_checks'        => [
                'Align with official exam objectives',
                'Include practice questions',
                'Cover exam-taking strategies',
                'Provide study schedules',
                'Add quick reference guides',
            ],
            'assessment_methods'    => [
                self::ASSESSMENT_QUIZ,
                self::ASSESSMENT_EXAM,
                self::ASSESSMENT_PRACTICAL,
            ],
            'optimal_lesson_length' => 40,
            'learning_progression'  => [
                'foundation' => [
                    'duration_weeks' => 4,
                    'focus'          => 'Core Knowledge',
                ],
                'practice'   => [
                    'duration_weeks' => 4,
                    'focus'          => 'Application',
                ],
                'review'     => [
                    'duration_weeks' => 2,
                    'focus'          => 'Exam Prep',
                ],
            ],
        ];
    }

    /**
     * Customize template with specific adaptations
     */
    public function customize(array $customizations): self
    {
        $newTemplate = clone $this;

        foreach ($customizations as $key => $value) {
            if (property_exists($newTemplate, $key)) {
                $newTemplate->$key = $value;
            }
        }

        return $newTemplate;
    }

    /**
     * Validate template data
     */
    public function validate(): array
    {
        $errors = [];

        if (empty($this->templateType)) {
            $errors[] = 'Template type is required';
        }

        if (empty($this->defaultStructure)) {
            $errors[] = 'Default structure is required';
        }

        if ($this->optimalLessonLength < 5 || $this->optimalLessonLength > 120) {
            $errors[] = 'Optimal lesson length must be between 5 and 120 minutes';
        }

        return $errors;
    }

    /**
     * Convert template to array
     */
    public function toArray(): array
    {
        return [
            'template_type'            => $this->templateType,
            'default_structure'        => $this->defaultStructure,
            'suggested_questions'      => $this->suggestedQuestions,
            'quality_checks'           => $this->qualityChecks,
            'ai_prompts'               => $this->aiPrompts,
            'learning_progression'     => $this->learningProgression,
            'assessment_methods'       => $this->assessmentMethods,
            'prerequisites'            => $this->prerequisites,
            'customization_options'    => $this->customizationOptions,
            'industry_terminology'     => $this->industryTerminology,
            'resource_recommendations' => $this->resourceRecommendations,
            'optimal_lesson_length'    => $this->optimalLessonLength,
            'pacing_guidelines'        => $this->pacingGuidelines,
        ];
    }

    // Getters
    public function getTemplateType(): string
    {
        return $this->templateType;
    }

    public function getDefaultStructure(): array
    {
        return $this->defaultStructure;
    }

    public function getSuggestedQuestions(): array
    {
        return $this->suggestedQuestions;
    }

    public function getQualityChecks(): array
    {
        return $this->qualityChecks;
    }

    public function getAiPrompts(): array
    {
        return $this->aiPrompts;
    }

    public function getLearningProgression(): array
    {
        return $this->learningProgression;
    }

    public function getAssessmentMethods(): array
    {
        return $this->assessmentMethods;
    }

    public function getPrerequisites(): array
    {
        return $this->prerequisites;
    }

    public function getCustomizationOptions(): array
    {
        return $this->customizationOptions;
    }

    public function getIndustryTerminology(): array
    {
        return $this->industryTerminology;
    }

    public function getResourceRecommendations(): array
    {
        return $this->resourceRecommendations;
    }

    public function getOptimalLessonLength(): int
    {
        return $this->optimalLessonLength;
    }

    public function getPacingGuidelines(): array
    {
        return $this->pacingGuidelines;
    }
}
