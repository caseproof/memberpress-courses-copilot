<?php

namespace MemberPressCoursesCopilot\Models;

/**
 * Course Template Model
 * 
 * Manages sophisticated course templates with advanced structures,
 * AI prompt engineering, adaptive questioning, and quality validation
 * for different learning types and pedagogical approaches.
 */
class CourseTemplate
{
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

    // Predefined template types
    public const TEMPLATE_TECHNICAL = 'technical';
    public const TEMPLATE_BUSINESS = 'business';
    public const TEMPLATE_CREATIVE = 'creative';
    public const TEMPLATE_ACADEMIC = 'academic';
    public const TEMPLATE_SKILL_BASED = 'skill_based';
    public const TEMPLATE_CERTIFICATION = 'certification';

    // Learning progression levels
    public const LEVEL_BEGINNER = 'beginner';
    public const LEVEL_INTERMEDIATE = 'intermediate';
    public const LEVEL_ADVANCED = 'advanced';
    public const LEVEL_EXPERT = 'expert';

    // Assessment types
    public const ASSESSMENT_QUIZ = 'quiz';
    public const ASSESSMENT_PROJECT = 'project';
    public const ASSESSMENT_PRACTICAL = 'practical';
    public const ASSESSMENT_PEER_REVIEW = 'peer_review';
    public const ASSESSMENT_CERTIFICATION_EXAM = 'certification_exam';
    public const ASSESSMENT_PORTFOLIO = 'portfolio';

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
        $this->templateType = $templateType;
        $this->defaultStructure = $defaultStructure;
        $this->suggestedQuestions = $suggestedQuestions;
        $this->qualityChecks = $qualityChecks;
        $this->aiPrompts = $aiPrompts;
        $this->learningProgression = $learningProgression;
        $this->assessmentMethods = $assessmentMethods;
        $this->prerequisites = $prerequisites;
        $this->customizationOptions = $customizationOptions;
        $this->industryTerminology = $industryTerminology;
        $this->resourceRecommendations = $resourceRecommendations;
        $this->optimalLessonLength = $optimalLessonLength;
        $this->pacingGuidelines = $pacingGuidelines;
    }

    /**
     * Get predefined template by type
     */
    public static function getTemplate(string $templateType): ?self
    {
        $templates = self::getPredefinedTemplates();
        return $templates[$templateType] ?? null;
    }

    /**
     * Get all available predefined templates
     */
    public static function getPredefinedTemplates(): array
    {
        return [
            self::TEMPLATE_TECHNICAL => new self(
                self::TEMPLATE_TECHNICAL,
                [
                    'sections' => [
                        ['title' => 'Introduction & Prerequisites', 'lessons' => 3],
                        ['title' => 'Core Concepts', 'lessons' => 5],
                        ['title' => 'Hands-on Implementation', 'lessons' => 7],
                        ['title' => 'Advanced Topics', 'lessons' => 4],
                        ['title' => 'Best Practices & Troubleshooting', 'lessons' => 3],
                        ['title' => 'Project & Assessment', 'lessons' => 2]
                    ]
                ],
                [
                    'What specific technology or skill will this course teach?',
                    'What prerequisites should learners have?',
                    'What real-world project will students build?',
                    'What are the key technical concepts to master?',
                    'What tools and software will be used?'
                ],
                [
                    'practical_examples' => 'Each lesson includes hands-on examples',
                    'code_quality' => 'Code examples follow industry best practices',
                    'progression' => 'Concepts build logically from basic to advanced',
                    'tools_setup' => 'Clear setup instructions for required tools'
                ]
            ),

            self::TEMPLATE_BUSINESS => new self(
                self::TEMPLATE_BUSINESS,
                [
                    'sections' => [
                        ['title' => 'Business Fundamentals', 'lessons' => 4],
                        ['title' => 'Strategy & Planning', 'lessons' => 5],
                        ['title' => 'Implementation & Execution', 'lessons' => 6],
                        ['title' => 'Metrics & Analysis', 'lessons' => 3],
                        ['title' => 'Case Studies & Applications', 'lessons' => 4]
                    ]
                ],
                [
                    'What business challenge or opportunity does this course address?',
                    'Who is the target audience (entrepreneurs, managers, executives)?',
                    'What specific business outcomes will students achieve?',
                    'What frameworks or methodologies will be taught?',
                    'What real business scenarios will be analyzed?'
                ],
                [
                    'real_world_relevance' => 'Examples from actual business scenarios',
                    'actionable_content' => 'Students can immediately apply concepts',
                    'roi_focus' => 'Clear value proposition and ROI examples',
                    'industry_current' => 'Content reflects current market conditions'
                ]
            ),

            self::TEMPLATE_CREATIVE => new self(
                self::TEMPLATE_CREATIVE,
                [
                    'sections' => [
                        ['title' => 'Creative Foundation & Inspiration', 'lessons' => 3],
                        ['title' => 'Techniques & Methods', 'lessons' => 6],
                        ['title' => 'Practice & Experimentation', 'lessons' => 5],
                        ['title' => 'Style Development', 'lessons' => 4],
                        ['title' => 'Portfolio & Presentation', 'lessons' => 3]
                    ]
                ],
                [
                    'What creative medium or art form will be taught?',
                    'What skill level is this course designed for?',
                    'What creative projects will students complete?',
                    'What style or approach will be emphasized?',
                    'How will students develop their unique voice?'
                ],
                [
                    'creative_freedom' => 'Balance structure with creative exploration',
                    'visual_learning' => 'Rich visual examples and demonstrations',
                    'portfolio_building' => 'Students create portfolio-worthy work',
                    'feedback_culture' => 'Constructive critique and peer review'
                ]
            ),

            self::TEMPLATE_ACADEMIC => new self(
                self::TEMPLATE_ACADEMIC,
                [
                    'sections' => [
                        ['title' => 'Course Overview & Learning Objectives', 'lessons' => 2],
                        ['title' => 'Theoretical Foundations', 'lessons' => 6],
                        ['title' => 'Critical Analysis & Discussion', 'lessons' => 5],
                        ['title' => 'Research & Methodology', 'lessons' => 4],
                        ['title' => 'Synthesis & Application', 'lessons' => 3],
                        ['title' => 'Assessment & Reflection', 'lessons' => 2]
                    ]
                ],
                [
                    'What academic discipline or subject area does this course cover?',
                    'What learning objectives align with educational standards?',
                    'What research methods or analytical skills will be developed?',
                    'How will critical thinking be fostered?',
                    'What assessment methods will measure student progress?'
                ],
                [
                    'academic_rigor' => 'Content meets educational standards',
                    'citation_quality' => 'Proper academic references and sources',
                    'critical_thinking' => 'Encourages analysis and evaluation',
                    'assessment_alignment' => 'Assessments match learning objectives'
                ]
            ),

            self::TEMPLATE_SKILL_BASED => new self(
                self::TEMPLATE_SKILL_BASED,
                [
                    'sections' => [
                        [
                            'title' => 'Skill Assessment & Goal Setting',
                            'lessons' => 2,
                            'focus' => 'baseline_establishment',
                            'objectives' => ['Assess current skill level', 'Set realistic goals', 'Plan learning pathway']
                        ],
                        [
                            'title' => 'Fundamental Techniques & Safety',
                            'lessons' => 4,
                            'focus' => 'basic_competency',
                            'objectives' => ['Learn proper techniques', 'Understand safety protocols', 'Build muscle memory']
                        ],
                        [
                            'title' => 'Progressive Skill Building',
                            'lessons' => 6,
                            'focus' => 'incremental_improvement',
                            'objectives' => ['Master intermediate techniques', 'Increase complexity', 'Build confidence']
                        ],
                        [
                            'title' => 'Advanced Applications & Specializations',
                            'lessons' => 4,
                            'focus' => 'specialization',
                            'objectives' => ['Explore specialized applications', 'Develop expertise areas', 'Master advanced techniques']
                        ],
                        [
                            'title' => 'Problem Solving & Troubleshooting',
                            'lessons' => 3,
                            'focus' => 'independence',
                            'objectives' => ['Diagnose common problems', 'Develop solutions', 'Work independently']
                        ],
                        [
                            'title' => 'Mastery Demonstration & Certification',
                            'lessons' => 3,
                            'focus' => 'competency_validation',
                            'objectives' => ['Demonstrate proficiency', 'Complete certification', 'Plan continued development']
                        ]
                    ],
                    'difficulty_curve' => 'skill_progression',
                    'hands_on_ratio' => 0.8
                ],
                [
                    'What specific hands-on skill or practical competency will students develop?',
                    'What is the target proficiency level students should achieve?',
                    'What tools, equipment, or materials are essential for skill development?',
                    'What safety considerations or protocols must be emphasized?',
                    'How will students demonstrate mastery and competency?',
                    'What real-world applications will students be prepared for?',
                    'What ongoing practice or maintenance does this skill require?'
                ],
                [
                    'practical_focus' => 'Minimum 80% hands-on practice and application',
                    'safety_emphasis' => 'Comprehensive safety training and protocol adherence',
                    'progressive_difficulty' => 'Systematic skill building from basic to advanced',
                    'competency_based' => 'Clear criteria for skill mastery and proficiency',
                    'real_world_application' => 'Direct connection to workplace or practical needs',
                    'individual_pacing' => 'Flexible progression based on individual skill development',
                    'ongoing_support' => 'Resources for continued skill maintenance and improvement'
                ],
                [
                    'conversation_starters' => [
                        'What practical skill will transform your students capabilities?',
                        'Describe what mastery of this skill looks like in practice',
                        'What makes someone truly proficient at this skill?'
                    ],
                    'follow_up_questions' => [
                        'What are the most common mistakes beginners make?',
                        'How do you know when someone has truly mastered this skill?',
                        'What safety considerations are absolutely critical?'
                    ],
                    'content_generation_prompts' => [
                        'Create a hands-on exercise that builds {specific_skill} through progressive practice',
                        'Design a safety checklist and protocol for {skill_application}',
                        'Develop a competency assessment that validates {skill_mastery}'
                    ],
                    'quality_validation_prompts' => [
                        'Ensure this exercise provides adequate hands-on practice time',
                        'Verify all safety protocols are clearly explained and emphasized',
                        'Confirm this assessment accurately measures practical competency'
                    ]
                ],
                [
                    self::LEVEL_BEGINNER => [
                        'focus' => 'basic_competency',
                        'duration_weeks' => 6,
                        'supervision_ratio' => 0.8
                    ],
                    self::LEVEL_INTERMEDIATE => [
                        'focus' => 'independent_application',
                        'duration_weeks' => 8,
                        'supervision_ratio' => 0.5
                    ],
                    self::LEVEL_ADVANCED => [
                        'focus' => 'mastery_and_teaching',
                        'duration_weeks' => 10,
                        'supervision_ratio' => 0.2
                    ]
                ],
                [
                    self::ASSESSMENT_PRACTICAL => 'Hands-on skill demonstration',
                    self::ASSESSMENT_PROJECT => 'Complete skill-based projects',
                    'competency_checklist' => 'Skills validation checklist',
                    'time_trials' => 'Efficiency and speed assessments',
                    'safety_certification' => 'Safety protocol compliance verification'
                ],
                [
                    'physical_capability' => 'Physical ability to perform required activities',
                    'basic_tools' => 'Access to necessary tools and equipment',
                    'practice_space' => 'Appropriate space for skill practice',
                    'time_commitment' => '10-15 hours per week for practice and skill development',
                    'learning_attitude' => 'Patience and persistence for skill development'
                ],
                [
                    'skill_complexity' => ['basic_motor_skills', 'intermediate_techniques', 'advanced_specializations'],
                    'equipment_level' => ['basic_tools', 'professional_equipment', 'specialized_instruments'],
                    'certification_type' => ['completion_certificate', 'competency_validation', 'professional_certification'],
                    'practice_environment' => ['home_practice', 'workshop_lab', 'field_application']
                ],
                [
                    'competency' => 'Demonstrated ability to perform skill effectively',
                    'proficiency' => 'High level of skill and efficiency',
                    'technique' => 'Specific method or approach to performing skill',
                    'precision' => 'Accuracy and exactness in skill execution',
                    'efficiency' => 'Ability to perform skill quickly and effectively',
                    'troubleshooting' => 'Problem-solving when skill application encounters issues'
                ],
                [
                    'practice_guides' => 'Step-by-step skill development resources',
                    'video_demonstrations' => 'Visual examples of proper technique',
                    'equipment_guides' => 'Tool selection and maintenance information',
                    'communities' => 'Practitioner networks and skill-sharing groups',
                    'certifications' => 'Professional certification and credentialing programs'
                ],
                20,
                [
                    'demonstration_time' => '5-10 minutes',
                    'guided_practice' => '15-25 minutes',
                    'independent_practice' => '20-30 minutes',
                    'skill_assessment' => '10-15 minutes'
                ]
            ),

            self::TEMPLATE_CERTIFICATION => new self(
                self::TEMPLATE_CERTIFICATION,
                [
                    'sections' => [
                        [
                            'title' => 'Certification Overview & Requirements',
                            'lessons' => 2,
                            'focus' => 'program_understanding',
                            'objectives' => ['Understand certification requirements', 'Review exam format', 'Set study schedule']
                        ],
                        [
                            'title' => 'Core Knowledge Domains',
                            'lessons' => 8,
                            'focus' => 'comprehensive_coverage',
                            'objectives' => ['Master all exam domains', 'Understand key concepts', 'Practice application']
                        ],
                        [
                            'title' => 'Practical Application & Case Studies',
                            'lessons' => 6,
                            'focus' => 'real_world_application',
                            'objectives' => ['Apply knowledge practically', 'Analyze case studies', 'Solve complex problems']
                        ],
                        [
                            'title' => 'Exam Preparation & Test-Taking Strategies',
                            'lessons' => 4,
                            'focus' => 'exam_readiness',
                            'objectives' => ['Practice exam questions', 'Learn test strategies', 'Build confidence']
                        ],
                        [
                            'title' => 'Mock Exams & Performance Review',
                            'lessons' => 3,
                            'focus' => 'assessment_preparation',
                            'objectives' => ['Take practice exams', 'Identify knowledge gaps', 'Refine study approach']
                        ],
                        [
                            'title' => 'Final Review & Certification Planning',
                            'lessons' => 2,
                            'focus' => 'certification_completion',
                            'objectives' => ['Complete final review', 'Schedule certification exam', 'Plan career next steps']
                        ]
                    ],
                    'difficulty_curve' => 'exam_focused',
                    'practice_exam_ratio' => 0.3
                ],
                [
                    'What specific professional certification will students prepare for?',
                    'What are the official exam requirements and format?',
                    'What knowledge domains and competencies are tested?',
                    'What is the typical pass rate and difficulty level?',
                    'What career benefits does this certification provide?',
                    'What continuing education or renewal requirements exist?',
                    'What study timeline is realistic for most students?'
                ],
                [
                    'exam_alignment' => 'Content directly aligned with official certification requirements',
                    'comprehensive_coverage' => 'All exam domains and objectives thoroughly addressed',
                    'practice_emphasis' => 'Extensive practice questions and mock exams',
                    'current_standards' => 'Content reflects most current certification standards',
                    'pass_rate_focus' => 'Strategies proven to improve certification pass rates',
                    'career_relevance' => 'Clear connection between certification and career advancement',
                    'renewal_preparation' => 'Foundation for ongoing professional development'
                ],
                [
                    'conversation_starters' => [
                        'What professional certification will advance your students careers?',
                        'Describe the ideal outcome after students earn this certification',
                        'What makes this certification valuable in the current job market?'
                    ],
                    'follow_up_questions' => [
                        'What are the most challenging aspects of this certification exam?',
                        'How does this certification connect to real-world job responsibilities?',
                        'What study approach has proven most effective for this certification?'
                    ],
                    'content_generation_prompts' => [
                        'Create practice questions that test {knowledge_domain} at certification exam level',
                        'Design a case study that demonstrates {competency} in professional context',
                        'Develop a study guide that covers {exam_objective} comprehensively'
                    ],
                    'quality_validation_prompts' => [
                        'Verify this content aligns with official certification exam objectives',
                        'Ensure practice questions match the format and difficulty of actual exam',
                        'Confirm this material reflects current industry standards and practices'
                    ]
                ],
                [
                    self::LEVEL_BEGINNER => [
                        'focus' => 'foundational_knowledge',
                        'duration_weeks' => 12,
                        'guided_study_ratio' => 0.7
                    ],
                    self::LEVEL_INTERMEDIATE => [
                        'focus' => 'comprehensive_preparation',
                        'duration_weeks' => 8,
                        'guided_study_ratio' => 0.5
                    ],
                    self::LEVEL_ADVANCED => [
                        'focus' => 'exam_mastery',
                        'duration_weeks' => 6,
                        'guided_study_ratio' => 0.3
                    ]
                ],
                [
                    self::ASSESSMENT_QUIZ => 'Chapter and domain knowledge quizzes',
                    self::ASSESSMENT_CERTIFICATION_EXAM => 'Full-length practice certification exams',
                    'knowledge_checks' => 'Regular competency validation assessments',
                    'case_analysis' => 'Professional scenario analysis and application',
                    'time_management' => 'Timed practice sessions for exam preparation'
                ],
                [
                    'baseline_knowledge' => 'Foundational knowledge in certification subject area',
                    'work_experience' => 'Relevant professional experience (requirements vary by certification)',
                    'study_commitment' => '15-20 hours per week for focused exam preparation',
                    'exam_eligibility' => 'Meeting official prerequisites for certification exam',
                    'financial_planning' => 'Budget for certification exam fees and materials'
                ],
                [
                    'certification_level' => ['entry_level', 'associate', 'professional', 'expert'],
                    'industry_focus' => ['technology', 'healthcare', 'finance', 'project_management', 'marketing'],
                    'exam_format' => ['multiple_choice', 'performance_based', 'mixed_format', 'practical_assessment'],
                    'renewal_cycle' => ['annual', 'biennial', 'triennial', 'continuing_education']
                ],
                [
                    'certification_body' => 'Organization that issues and maintains certification',
                    'knowledge_domain' => 'Major subject area covered in certification exam',
                    'competency' => 'Specific skill or ability validated by certification',
                    'continuing_education' => 'Ongoing learning required to maintain certification',
                    'recertification' => 'Process of renewing expired certification',
                    'credential' => 'Formal recognition of professional qualification'
                ],
                [
                    'official_study_guides' => 'Certification body approved study materials',
                    'practice_exams' => 'Simulated certification exams and question banks',
                    'boot_camps' => 'Intensive preparation courses and workshops',
                    'study_groups' => 'Peer study networks and professional communities',
                    'exam_centers' => 'Authorized testing locations and online proctoring'
                ],
                45,
                [
                    'content_review' => '20-30 minutes',
                    'practice_questions' => '15-25 minutes',
                    'case_study_analysis' => '20-30 minutes',
                    'exam_strategy_review' => '10-15 minutes'
                ]
            )
        ];
    }

    /**
     * Validate template structure and content
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

        if (empty($this->suggestedQuestions)) {
            $errors[] = 'Suggested questions are required';
        }

        if (empty($this->qualityChecks)) {
            $errors[] = 'Quality checks are required';
        }

        // Validate structure format
        if (isset($this->defaultStructure['sections']) && is_array($this->defaultStructure['sections'])) {
            foreach ($this->defaultStructure['sections'] as $index => $section) {
                if (!isset($section['title']) || !isset($section['lessons'])) {
                    $errors[] = "Section {$index} must have 'title' and 'lessons' properties";
                }
                if (!is_numeric($section['lessons']) || $section['lessons'] < 1) {
                    $errors[] = "Section {$index} must have at least 1 lesson";
                }
            }
        }

        // Validate new required fields
        if (empty($this->aiPrompts)) {
            $errors[] = 'AI prompts are required for content generation';
        }
        
        if (empty($this->learningProgression)) {
            $errors[] = 'Learning progression structure is required';
        }
        
        if (empty($this->assessmentMethods)) {
            $errors[] = 'Assessment methods are required';
        }
        
        // Validate structure format
        if (isset($this->defaultStructure['sections']) && is_array($this->defaultStructure['sections'])) {
            foreach ($this->defaultStructure['sections'] as $index => $section) {
                if (isset($section['objectives']) && !is_array($section['objectives'])) {
                    $errors[] = "Section {$index} objectives must be an array";
                }
            }
        }
        
        // Validate AI prompts structure
        if (!empty($this->aiPrompts)) {
            $requiredPromptKeys = ['conversation_starters', 'follow_up_questions', 'content_generation_prompts'];
            foreach ($requiredPromptKeys as $key) {
                if (!isset($this->aiPrompts[$key]) || !is_array($this->aiPrompts[$key])) {
                    $errors[] = "AI prompts must include '{$key}' as an array";
                }
            }
        }
        
        // Validate learning progression
        if (!empty($this->learningProgression)) {
            $validLevels = [self::LEVEL_BEGINNER, self::LEVEL_INTERMEDIATE, self::LEVEL_ADVANCED, self::LEVEL_EXPERT];
            foreach ($this->learningProgression as $level => $data) {
                if (!in_array($level, $validLevels)) {
                    $errors[] = "Invalid learning progression level: {$level}";
                }
                if (!isset($data['focus']) || !isset($data['duration_weeks'])) {
                    $errors[] = "Learning progression level {$level} must have 'focus' and 'duration_weeks'";
                }
            }
        }
        
        // Validate optimal lesson length
        if ($this->optimalLessonLength < 5 || $this->optimalLessonLength > 180) {
            $errors[] = 'Optimal lesson length must be between 5 and 180 minutes';
        }

        return $errors;
    }

    /**
     * Validate template completeness and pedagogical soundness
     */
    public function validatePedagogicalSoundness(): array
    {
        $issues = [];
        
        // Check for learning progression
        if (empty($this->learningProgression)) {
            $issues[] = 'Missing learning progression structure';
        }
        
        // Validate section progression makes sense
        $sections = $this->defaultStructure['sections'] ?? [];
        if (count($sections) < 3) {
            $issues[] = 'Insufficient course structure - minimum 3 sections recommended';
        }
        
        // Check for assessment variety
        if (count($this->assessmentMethods) < 2) {
            $issues[] = 'Limited assessment methods - multiple assessment types recommended';
        }
        
        // Validate lesson length is reasonable
        if ($this->optimalLessonLength < 10 || $this->optimalLessonLength > 90) {
            $issues[] = 'Optimal lesson length should be between 10-90 minutes';
        }
        
        // Check for prerequisites clarity
        if (empty($this->prerequisites)) {
            $issues[] = 'Missing prerequisite information';
        }
        
        return $issues;
    }

    /**
     * Get template customized for specific course requirements
     */
    public function customize(array $customizations): self
    {
        $customizedTemplate = clone $this;

        if (isset($customizations['structure'])) {
            $customizedTemplate->defaultStructure = array_merge(
                $this->defaultStructure,
                $customizations['structure']
            );
        }

        if (isset($customizations['questions'])) {
            $customizedTemplate->suggestedQuestions = array_merge(
                $this->suggestedQuestions,
                $customizations['questions']
            );
        }

        if (isset($customizations['quality_checks'])) {
            $customizedTemplate->qualityChecks = array_merge(
                $this->qualityChecks,
                $customizations['quality_checks']
            );
        }

        return $customizedTemplate;
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

    /**
     * Get estimated total lessons for template
     */
    public function getTotalLessons(): int
    {
        $total = 0;
        if (isset($this->defaultStructure['sections'])) {
            foreach ($this->defaultStructure['sections'] as $section) {
                $total += $section['lessons'] ?? 0;
            }
        }
        return $total;
    }

    /**
     * Get estimated course duration in weeks
     */
    public function getEstimatedDuration(string $skillLevel = self::LEVEL_INTERMEDIATE): int
    {
        if (isset($this->learningProgression[$skillLevel]['duration_weeks'])) {
            return $this->learningProgression[$skillLevel]['duration_weeks'];
        }
        
        // Fallback calculation based on lesson count and optimal lesson length
        $totalLessons = $this->getTotalLessons();
        $hoursPerWeek = 5; // Assume 5 hours study per week
        $minutesPerWeek = $hoursPerWeek * 60;
        $totalMinutes = $totalLessons * $this->optimalLessonLength;
        
        return max(1, ceil($totalMinutes / $minutesPerWeek));
    }

    /**
     * Get difficulty rating for template
     */
    public function getDifficultyRating(): string
    {
        $totalLessons = $this->getTotalLessons();
        $avgDuration = $this->getEstimatedDuration();
        
        if ($totalLessons < 15 && $avgDuration < 6) {
            return 'Beginner';
        } elseif ($totalLessons < 25 && $avgDuration < 10) {
            return 'Intermediate';
        } else {
            return 'Advanced';
        }
    }

    /**
     * Get template as array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'template_type' => $this->templateType,
            'default_structure' => $this->defaultStructure,
            'suggested_questions' => $this->suggestedQuestions,
            'quality_checks' => $this->qualityChecks,
            'ai_prompts' => $this->aiPrompts,
            'learning_progression' => $this->learningProgression,
            'assessment_methods' => $this->assessmentMethods,
            'prerequisites' => $this->prerequisites,
            'customization_options' => $this->customizationOptions,
            'industry_terminology' => $this->industryTerminology,
            'resource_recommendations' => $this->resourceRecommendations,
            'optimal_lesson_length' => $this->optimalLessonLength,
            'pacing_guidelines' => $this->pacingGuidelines,
            'total_lessons' => $this->getTotalLessons(),
            'estimated_duration' => $this->getEstimatedDuration(),
            'difficulty_rating' => $this->getDifficultyRating()
        ];
    }

    /**
     * Get adaptive questioning based on current context
     */
    public function getAdaptiveQuestions(array $context = []): array
    {
        $questions = $this->suggestedQuestions;
        
        // Add context-specific questions from AI prompts
        if (!empty($this->aiPrompts['follow_up_questions'])) {
            $followUpQuestions = $this->aiPrompts['follow_up_questions'];
            
            // Select relevant questions based on context
            if (!empty($context['skill_level'])) {
                $skillLevel = $context['skill_level'];
                if (isset($this->learningProgression[$skillLevel])) {
                    $progressionData = $this->learningProgression[$skillLevel];
                    // Add skill-level specific questions
                    $questions = array_merge($questions, $followUpQuestions);
                }
            }
        }
        
        return $questions;
    }

    /**
     * Get AI prompt for content generation
     */
    public function getContentGenerationPrompt(string $contentType, array $variables = []): string
    {
        if (!isset($this->aiPrompts['content_generation_prompts'])) {
            return "Generate {$contentType} content for this lesson.";
        }
        
        $prompts = $this->aiPrompts['content_generation_prompts'];
        $selectedPrompt = $prompts[array_rand($prompts)] ?? "Generate {$contentType} content.";
        
        // Replace variables in prompt
        foreach ($variables as $key => $value) {
            $selectedPrompt = str_replace('{' . $key . '}', $value, $selectedPrompt);
        }
        
        return $selectedPrompt;
    }

    /**
     * Get quality validation prompt
     */
    public function getQualityValidationPrompt(string $contentType): string
    {
        if (!isset($this->aiPrompts['quality_validation_prompts'])) {
            return "Review this {$contentType} for quality and accuracy.";
        }
        
        $prompts = $this->aiPrompts['quality_validation_prompts'];
        return $prompts[array_rand($prompts)] ?? "Review this {$contentType} for quality.";
    }

    /**
     * Get recommended learning progression for skill level
     */
    public function getProgressionForLevel(string $level): ?array
    {
        return $this->learningProgression[$level] ?? null;
    }

    /**
     * Get assessment methods for specific learning objectives
     */
    public function getAssessmentsForObjectives(array $objectives): array
    {
        $recommendations = [];
        
        foreach ($objectives as $objective) {
            // Match assessment types to objective types
            if (stripos($objective, 'practical') !== false || stripos($objective, 'hands-on') !== false) {
                $recommendations[] = self::ASSESSMENT_PRACTICAL;
            } elseif (stripos($objective, 'project') !== false || stripos($objective, 'build') !== false) {
                $recommendations[] = self::ASSESSMENT_PROJECT;
            } elseif (stripos($objective, 'understand') !== false || stripos($objective, 'knowledge') !== false) {
                $recommendations[] = self::ASSESSMENT_QUIZ;
            } elseif (stripos($objective, 'portfolio') !== false || stripos($objective, 'creative') !== false) {
                $recommendations[] = self::ASSESSMENT_PORTFOLIO;
            }
        }
        
        return array_unique($recommendations);
    }

    /**
     * Get template mixing recommendations for hybrid approaches
     */
    public function getMixingRecommendations(): array
    {
        $mixing = [];
        
        switch ($this->templateType) {
            case self::TEMPLATE_TECHNICAL:
                $mixing = [
                    self::TEMPLATE_SKILL_BASED => 'Add hands-on skill validation',
                    self::TEMPLATE_CERTIFICATION => 'Include certification preparation'
                ];
                break;
                
            case self::TEMPLATE_BUSINESS:
                $mixing = [
                    self::TEMPLATE_ACADEMIC => 'Add theoretical framework depth',
                    self::TEMPLATE_SKILL_BASED => 'Include practical skill development'
                ];
                break;
                
            case self::TEMPLATE_CREATIVE:
                $mixing = [
                    self::TEMPLATE_SKILL_BASED => 'Add technical skill focus',
                    self::TEMPLATE_BUSINESS => 'Include career and business aspects'
                ];
                break;
                
            case self::TEMPLATE_ACADEMIC:
                $mixing = [
                    self::TEMPLATE_BUSINESS => 'Add practical application focus',
                    self::TEMPLATE_SKILL_BASED => 'Include hands-on components'
                ];
                break;
                
            case self::TEMPLATE_SKILL_BASED:
                $mixing = [
                    self::TEMPLATE_CERTIFICATION => 'Add formal credentialing',
                    self::TEMPLATE_ACADEMIC => 'Include theoretical foundation'
                ];
                break;
                
            case self::TEMPLATE_CERTIFICATION:
                $mixing = [
                    self::TEMPLATE_SKILL_BASED => 'Add practical application',
                    self::TEMPLATE_TECHNICAL => 'Include hands-on practice'
                ];
                break;
        }
        
        return $mixing;
    }

    /**
     * Get all available template types with descriptions
     */
    public static function getTemplateTypes(): array
    {
        return [
            self::TEMPLATE_TECHNICAL => [
                'name' => 'Technical Course',
                'description' => 'Programming, software, IT skills with hands-on practice',
                'best_for' => 'Coding, software development, technical skills'
            ],
            self::TEMPLATE_BUSINESS => [
                'name' => 'Business Course', 
                'description' => 'Leadership, marketing, sales, management with real-world application',
                'best_for' => 'Business strategy, leadership, entrepreneurship'
            ],
            self::TEMPLATE_CREATIVE => [
                'name' => 'Creative Course',
                'description' => 'Design, writing, arts, media with portfolio development',
                'best_for' => 'Art, design, writing, creative expression'
            ],
            self::TEMPLATE_ACADEMIC => [
                'name' => 'Academic Course',
                'description' => 'Research, theory, formal education with scholarly rigor',
                'best_for' => 'Academic subjects, research, theoretical learning'
            ],
            self::TEMPLATE_SKILL_BASED => [
                'name' => 'Skill-Based Course',
                'description' => 'Hands-on practical skills with competency validation',
                'best_for' => 'Practical skills, trades, hands-on learning'
            ],
            self::TEMPLATE_CERTIFICATION => [
                'name' => 'Certification Course',
                'description' => 'Professional certifications and compliance preparation',
                'best_for' => 'Professional certification, compliance training'
            ]
        ];
    }

    /**
     * Recommend template based on course description
     */
    public static function recommendTemplate(string $description): array
    {
        $description = strtolower($description);
        $recommendations = [];
        
        // Technical keywords
        $technicalKeywords = ['programming', 'coding', 'software', 'development', 'technical', 'api', 'database', 'framework'];
        if (self::containsKeywords($description, $technicalKeywords)) {
            $recommendations[] = ['type' => self::TEMPLATE_TECHNICAL, 'confidence' => 0.9];
        }
        
        // Business keywords
        $businessKeywords = ['business', 'leadership', 'management', 'strategy', 'marketing', 'sales', 'entrepreneurship'];
        if (self::containsKeywords($description, $businessKeywords)) {
            $recommendations[] = ['type' => self::TEMPLATE_BUSINESS, 'confidence' => 0.8];
        }
        
        // Creative keywords
        $creativeKeywords = ['design', 'art', 'creative', 'writing', 'photography', 'video', 'graphics', 'portfolio'];
        if (self::containsKeywords($description, $creativeKeywords)) {
            $recommendations[] = ['type' => self::TEMPLATE_CREATIVE, 'confidence' => 0.8];
        }
        
        // Academic keywords
        $academicKeywords = ['research', 'academic', 'theory', 'analysis', 'scholarly', 'education', 'literature'];
        if (self::containsKeywords($description, $academicKeywords)) {
            $recommendations[] = ['type' => self::TEMPLATE_ACADEMIC, 'confidence' => 0.7];
        }
        
        // Skill-based keywords
        $skillKeywords = ['hands-on', 'practical', 'skill', 'technique', 'craft', 'build', 'make', 'practice'];
        if (self::containsKeywords($description, $skillKeywords)) {
            $recommendations[] = ['type' => self::TEMPLATE_SKILL_BASED, 'confidence' => 0.8];
        }
        
        // Certification keywords
        $certificationKeywords = ['certification', 'certified', 'credential', 'exam', 'professional', 'compliance', 'license'];
        if (self::containsKeywords($description, $certificationKeywords)) {
            $recommendations[] = ['type' => self::TEMPLATE_CERTIFICATION, 'confidence' => 0.9];
        }
        
        // Sort by confidence
        usort($recommendations, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return $recommendations;
    }
    
    /**
     * Helper method to check if description contains keywords
     */
    private static function containsKeywords(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}