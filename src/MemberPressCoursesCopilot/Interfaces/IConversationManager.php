<?php

namespace MemberPressCoursesCopilot\Interfaces;

/**
 * Interface for managing conversations between users and AI
 */
interface IConversationManager {
    /**
     * Process a user message and return the AI response
     *
     * @param string $message User message
     * @param int $userId User ID
     * @return array Response containing the AI message and any metadata
     */
    public function processMessage(string $message, int $userId): array;

    /**
     * Get conversation history for a user
     *
     * @param int $userId User ID
     * @param int $limit Number of messages to retrieve
     * @return array Formatted conversation history
     */
    public function getHistory(int $userId, int $limit = 50): array;

    /**
     * Clear conversation history for a user
     *
     * @param int $userId User ID
     * @return bool Success status
     */
    public function clearHistory(int $userId): bool;
}