<?php

namespace MemberPressCoursesCopilot\Models;

/**
 * Course Template Model
 * 
 * Manages predefined course templates with default structures,
 * suggested questions, and quality checks for different course types.
 */
class CourseTemplate
{
    private string $templateType;
    private array $defaultStructure;
    private array $suggestedQuestions;
    private array $qualityChecks;

    // Predefined template types
    public const TEMPLATE_TECHNICAL = 'technical';
    public const TEMPLATE_BUSINESS = 'business';
    public const TEMPLATE_CREATIVE = 'creative';
    public const TEMPLATE_ACADEMIC = 'academic';

    public function __construct(string $templateType, array $defaultStructure = [], array $suggestedQuestions = [], array $qualityChecks = [])
    {
        $this->templateType = $templateType;
        $this->defaultStructure = $defaultStructure;
        $this->suggestedQuestions = $suggestedQuestions;
        $this->qualityChecks = $qualityChecks;
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

        return $errors;
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
     * Get template as array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'template_type' => $this->templateType,
            'default_structure' => $this->defaultStructure,
            'suggested_questions' => $this->suggestedQuestions,
            'quality_checks' => $this->qualityChecks,
            'total_lessons' => $this->getTotalLessons()
        ];
    }
}