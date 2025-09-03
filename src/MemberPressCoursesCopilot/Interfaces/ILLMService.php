<?php

namespace MemberPressCoursesCopilot\Interfaces;

/**
 * Interface for LLM service implementations
 */
interface ILLMService
{
    /**
     * Send a message to the LLM and get a response
     *
     * @param  string $message             The user message
     * @param  array  $conversationHistory Previous messages in the conversation
     * @return array Response from the LLM
     */
    public function sendMessage(string $message, array $conversationHistory = []): array;

    /**
     * Generate a course based on the provided parameters
     *
     * @param  array $courseData Course generation parameters
     * @return array Generated course content
     */
    public function generateCourse(array $courseData): array;
}
