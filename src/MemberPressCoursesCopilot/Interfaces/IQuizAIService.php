<?php

declare(strict_types=1);

/**
 * Quiz AI Service Interface
 *
 * @package MemberPressCoursesCopilot
 */

namespace MemberPressCoursesCopilot\Interfaces;

use MemberPressCoursesCopilot\Models\QuizTemplate;

interface IQuizAIService
{
    /**
     * Generate a quiz based on lesson content
     *
     * @param  integer $lesson_id The lesson ID to generate quiz from
     * @param  array   $options   Quiz generation options
     * @return array Generated quiz data
     */
    public function generateQuizFromLesson(int $lesson_id, array $options = []): array;

    /**
     * Generate a quiz based on course content
     *
     * @param  integer $course_id The course ID to generate quiz from
     * @param  array   $options   Quiz generation options
     * @return array Generated quiz data
     */
    public function generateQuizFromCourse(int $course_id, array $options = []): array;

    /**
     * Generate quiz questions based on provided content
     *
     * @param  string $content The content to generate questions from
     * @param  array  $options Question generation options
     * @return array Generated questions
     */
    public function generateQuestions(string $content, array $options = []): array;

    /**
     * Generate a single question of a specific type
     *
     * @param  string $content       The content to base question on
     * @param  string $question_type The type of question to generate
     * @param  array  $options       Additional options
     * @return array Generated question data
     */
    public function generateQuestion(string $content, string $question_type, array $options = []): array;

    /**
     * Regenerate a specific question
     *
     * @param  array $question The original question
     * @param  array $options  Regeneration options
     * @return array Regenerated question
     */
    public function regenerateQuestion(array $question, array $options = []): array;

    /**
     * Validate quiz questions and answers
     *
     * @param  array $questions The questions to validate
     * @return array Validation results
     */
    public function validateQuestions(array $questions): array;

    /**
     * Get available quiz templates
     *
     * @return QuizTemplate[] Array of quiz templates
     */
    public function getQuizTemplates(): array;

    /**
     * Apply a template to generate a quiz
     *
     * @param  QuizTemplate $template The template to apply
     * @param  string       $content  The content to generate from
     * @return array Generated quiz data
     */
    public function applyTemplate(QuizTemplate $template, string $content): array;

    /**
     * Generate quiz analytics and insights
     *
     * @param  integer $quiz_id The quiz ID to analyze
     * @return array Analytics data
     */
    public function generateQuizAnalytics(int $quiz_id): array;

    /**
     * Optimize quiz based on performance data
     *
     * @param  integer $quiz_id          The quiz to optimize
     * @param  array   $performance_data Student performance data
     * @return array Optimization suggestions
     */
    public function optimizeQuiz(int $quiz_id, array $performance_data): array;

    /**
     * Generate feedback for quiz answers
     *
     * @param  array  $question    The question data
     * @param  string $user_answer The user's answer
     * @return string Generated feedback
     */
    public function generateFeedback(array $question, string $user_answer): string;

    /**
     * Get supported question types
     *
     * @return array List of supported question types
     */
    public function getSupportedQuestionTypes(): array;

    /**
     * Estimate quiz difficulty
     *
     * @param  array $questions The quiz questions
     * @return string Difficulty level (easy, medium, hard)
     */
    public function estimateDifficulty(array $questions): string;
}
