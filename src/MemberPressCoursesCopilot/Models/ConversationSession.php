<?php

namespace MemberPressCoursesCopilot\Models;

/**
 * Conversation Session Model
 *
 * Represents a conversation session with comprehensive state management,
 * context tracking, message history, progress calculation, and recovery mechanisms.
 * Supports auto-save functionality, session timeout management, and collaborative editing.
 */
class ConversationSession
{
    private string $sessionId;
    private int $userId;
    private string $contextType;
    private ?int $databaseId = null;
    private string $title    = '';

    // Session state management
    private string $currentState     = 'initial';
    private array $stateHistory      = [];
    private ?string $pausedFromState = null;
    private bool $isActive           = true;

    // Context and data
    private array $context  = [];
    private array $metadata = [];
    private array $messages = [];

    // Progress tracking
    private float $progress        = 0.0;
    private float $confidenceScore = 0.0;

    // Timing and persistence
    private int $createdAt;
    private int $lastUpdated;
    private int $lastSaved          = 0;
    private int $lastAutoSave       = 0;
    private bool $hasUnsavedChanges = false;

    // Resource tracking
    private int $totalTokens = 0;
    private float $totalCost = 0.0;

    // Session configuration
    private const MAX_MESSAGE_HISTORY = 1000;
    private const AUTO_SAVE_INTERVAL  = 30; // seconds
    private const MAX_STATE_HISTORY   = 50;

    /**
     * Constructor for ConversationSession
     *
     * @since 1.0.0
     *
     * @param string $sessionId   Unique identifier for the session
     * @param int    $userId      User ID associated with this session
     * @param string $contextType Context type for the session (default: 'course_creation')
     */
    public function __construct(string $sessionId, int $userId, string $contextType = 'course_creation')
    {
        $this->sessionId   = $sessionId;
        $this->userId      = $userId;
        $this->contextType = $contextType;
        $this->createdAt   = time();
        $this->lastUpdated = time();
        $this->title       = 'Course Creation Session - ' . date('Y-m-d H:i:s');
    }

    /**
     * Add a message to the conversation
     *
     * @since 1.0.0
     *
     * @param string $type     Type of message ('user', 'assistant', or 'system')
     * @param string $content  Message content
     * @param array  $metadata Optional metadata for the message
     *                         - tokens_used (int): Number of tokens used
     *                         - cost (float): Cost of the message
     *
     * @return void
     */
    public function addMessage(string $type, string $content, array $metadata = []): void
    {
        $message = [
            'id'        => 'msg_' . wp_generate_uuid4(),
            'type'      => $type, // 'user', 'assistant', 'system'
            'content'   => $content,
            'metadata'  => $metadata,
            'timestamp' => time(),
            'state'     => $this->currentState,
        ];

        $this->messages[] = $message;

        // Trim message history if it gets too long
        if (count($this->messages) > self::MAX_MESSAGE_HISTORY) {
            $this->messages = array_slice($this->messages, -self::MAX_MESSAGE_HISTORY);
        }

        $this->markAsModified();

        // Track tokens if provided in metadata
        if (isset($metadata['tokens_used'])) {
            $this->totalTokens += $metadata['tokens_used'];
        }

        if (isset($metadata['cost'])) {
            $this->totalCost += $metadata['cost'];
        }
    }

    /**
     * Clear all messages from the conversation
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function clearMessages(): void
    {
        $this->messages = [];
        $this->markAsModified();
    }

    /**
     * Get recent messages
     *
     * @since 1.0.0
     *
     * @param int $count Number of recent messages to retrieve (default: 10)
     *
     * @return array Array of recent messages
     */
    public function getRecentMessages(int $count = 10): array
    {
        return array_slice($this->messages, -$count);
    }

    /**
     * Get all messages
     *
     * @since 1.0.0
     *
     * @return array Array of all messages
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get messages by type
     *
     * @since 1.0.0
     *
     * @param string $type Message type to filter by ('user', 'assistant', or 'system')
     *
     * @return array Array of messages matching the specified type
     */
    public function getMessagesByType(string $type): array
    {
        return array_filter($this->messages, fn($msg) => $msg['type'] === $type);
    }

    /**
     * Set current conversation state
     *
     * @since 1.0.0
     *
     * @param string $state New conversation state
     *
     * @return void
     */
    public function setCurrentState(string $state): void
    {
        if ($state !== $this->currentState) {
            $this->currentState = $state;
            $this->markAsModified();

            // Auto-update progress based on state
            $this->updateProgressFromState($state);
        }
    }

    /**
     * Get current conversation state
     *
     * @since 1.0.0
     *
     * @return string Current conversation state
     */
    public function getCurrentState(): string
    {
        return $this->currentState;
    }

    /**
     * Save current state to history for backtracking
     *
     * @since 1.0.0
     *
     * @param string $state          State to save to history
     * @param array  $transitionData Optional transition data
     *
     * @return void
     */
    public function saveStateToHistory(string $state, array $transitionData = []): void
    {
        $historyItem = [
            'state'           => $state,
            'timestamp'       => time(),
            'context'         => $this->context,
            'transition_data' => $transitionData,
            'progress'        => $this->progress,
        ];

        $this->stateHistory[] = $historyItem;

        // Trim state history if it gets too long
        if (count($this->stateHistory) > self::MAX_STATE_HISTORY) {
            $this->stateHistory = array_slice($this->stateHistory, -self::MAX_STATE_HISTORY);
        }

        $this->markAsModified();
    }

    /**
     * Get state history for backtracking
     *
     * @since 1.0.0
     *
     * @return array Array of state history items
     */
    public function getStateHistory(): array
    {
        return $this->stateHistory;
    }

    /**
     * Set state history (for restoration)
     *
     * @since 1.0.0
     *
     * @param array $history State history array to set
     *
     * @return void
     */
    public function setStateHistory(array $history): void
    {
        $this->stateHistory = $history;
        $this->markAsModified();
    }

    /**
     * Set conversation context data
     *
     * @since 1.0.0
     *
     * @param string|array $key   Context key or entire context array
     * @param mixed        $value Value to set (null when setting entire array)
     *
     * @return void
     */
    public function setContext(string|array $key, mixed $value = null): void
    {
        if (is_array($key) && $value === null) {
            // Setting entire context array
            $this->context = $key;
        } else {
            // Setting individual key-value pair
            $this->context[$key] = $value;
        }

        $this->markAsModified();
    }

    /**
     * Get conversation context data
     *
     * @since 1.0.0
     *
     * @param string|null $key     Optional context key to retrieve
     * @param mixed       $default Default value if key not found
     *
     * @return mixed Context value, entire context array if no key specified, or default value
     */
    public function getContext(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->context;
        }

        return $this->context[$key] ?? $default;
    }


    /**
     * Set progress percentage
     *
     * @since 1.0.0
     *
     * @param float $progress Progress percentage (0.0 to 100.0)
     *
     * @return void
     */
    public function updateProgress(float $progress): void
    {
        $this->progress = max(0.0, min(100.0, $progress));
        $this->markAsModified();
    }

    /**
     * Get progress percentage
     *
     * @since 1.0.0
     *
     * @return float Progress percentage (0.0 to 100.0)
     */
    public function getProgress(): float
    {
        return $this->progress;
    }

    /**
     * Set progress value directly
     *
     * @since 1.0.0
     *
     * @param float $progress Progress percentage to set
     *
     * @return void
     */
    public function setProgress(float $progress): void
    {
        $this->progress = $progress;
        $this->markAsModified();
    }

    /**
     * Set confidence score
     *
     * @since 1.0.0
     *
     * @param float $score Confidence score (0.0 to 1.0)
     *
     * @return void
     */
    public function setConfidenceScore(float $score): void
    {
        $this->confidenceScore = max(0.0, min(1.0, $score));
        $this->markAsModified();
    }

    /**
     * Get confidence score
     *
     * @since 1.0.0
     *
     * @return float Confidence score (0.0 to 1.0)
     */
    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    /**
     * Pause the conversation session
     *
     * @since 1.0.0
     *
     * @param string $reason Optional reason for pausing
     *
     * @return void
     */
    public function pause(string $reason = ''): void
    {
        $this->pausedFromState = $this->currentState;
        $this->isActive        = false;

        $this->addMessage('system', 'Conversation paused', [
            'reason'            => $reason,
            'paused_from_state' => $this->pausedFromState,
        ]);
    }

    /**
     * Resume the conversation session
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function resume(): void
    {
        $this->isActive = true;

        $this->addMessage('system', 'Conversation resumed', [
            'resumed_to_state' => $this->pausedFromState ?? $this->currentState,
        ]);

        if ($this->pausedFromState) {
            $this->currentState    = $this->pausedFromState;
            $this->pausedFromState = null;
        }
    }

    /**
     * Complete the conversation session
     *
     * @since 1.0.0
     *
     * @param array $completionData Optional completion data to store
     *
     * @return void
     */
    public function complete(array $completionData = []): void
    {
        $this->isActive = false;
        $this->progress = 100.0;

        $this->addMessage('system', 'Conversation completed', $completionData);
        $this->setMetadata('completed_at', time());
        $this->setMetadata('completion_data', $completionData);
    }

    /**
     * Abandon the conversation session
     *
     * @since 1.0.0
     *
     * @param string $reason Optional reason for abandoning
     *
     * @return void
     */
    public function abandon(string $reason = ''): void
    {
        $this->isActive = false;

        $this->addMessage('system', 'Conversation abandoned', ['reason' => $reason]);
        $this->setMetadata('abandoned_at', time());
        $this->setMetadata('abandon_reason', $reason);
    }

    /**
     * Check if session is active
     *
     * @since 1.0.0
     *
     * @return bool True if session is active, false otherwise
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Check if session is expired
     *
     * @since 1.0.0
     *
     * @param int $timeoutMinutes Timeout duration in minutes (default: 60)
     *
     * @return bool True if session is expired, false otherwise
     */
    public function isExpired(int $timeoutMinutes = 60): bool
    {
        return (time() - $this->lastUpdated) > ($timeoutMinutes * 60);
    }

    /**
     * Get time until expiry
     *
     * @since 1.0.0
     *
     * @param int $timeoutMinutes Timeout duration in minutes (default: 60)
     *
     * @return int Time remaining in seconds until expiry
     */
    public function getTimeUntilExpiry(int $timeoutMinutes = 60): int
    {
        $expiryTime = $this->lastUpdated + ($timeoutMinutes * 60);
        return max(0, $expiryTime - time());
    }

    /**
     * Get pause duration if paused
     *
     * @since 1.0.0
     *
     * @return int Pause duration in seconds (0 if not paused)
     */
    public function getPauseDuration(): int
    {
        if ($this->isActive) {
            return 0;
        }

        // Find the last pause message
        $pauseMessage = null;
        foreach (array_reverse($this->messages) as $message) {
            if ($message['type'] === 'system' && strpos($message['content'], 'paused') !== false) {
                $pauseMessage = $message;
                break;
            }
        }

        return $pauseMessage ? (time() - $pauseMessage['timestamp']) : 0;
    }

    /**
     * Set paused from state
     *
     * @since 1.0.0
     *
     * @param string $state State the session was paused from
     *
     * @return void
     */
    public function setPausedFromState(string $state): void
    {
        $this->pausedFromState = $state;
        $this->markAsModified();
    }

    /**
     * Get paused from state
     *
     * @since 1.0.0
     *
     * @return string|null State the session was paused from, or null
     */
    public function getPausedFromState(): ?string
    {
        return $this->pausedFromState;
    }

    /**
     * Set session metadata
     *
     * @since 1.0.0
     *
     * @param string $key   Metadata key
     * @param mixed  $value Metadata value
     *
     * @return void
     */
    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
        $this->markAsModified();
    }

    /**
     * Set entire metadata array
     *
     * @since 1.0.0
     *
     * @param array $metadata Complete metadata array to set
     *
     * @return void
     */
    public function setMetadataArray(array $metadata): void
    {
        $this->metadata = $metadata;
        $this->markAsModified();
    }

    /**
     * Get session metadata
     *
     * @since 1.0.0
     *
     * @param string|null $key Optional metadata key to retrieve
     *
     * @return mixed Metadata value, entire metadata array if no key specified, or null
     */
    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Check if auto-save is needed
     *
     * @since 1.0.0
     *
     * @param int|null $intervalSeconds Optional auto-save interval in seconds
     *
     * @return bool True if auto-save is needed, false otherwise
     */
    public function shouldAutoSave(int $intervalSeconds = null): bool
    {
        $interval = $intervalSeconds ?? self::AUTO_SAVE_INTERVAL;
        return $this->hasUnsavedChanges && (time() - $this->lastAutoSave) >= $interval;
    }

    /**
     * Mark session as saved
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function markAsSaved(): void
    {
        $this->hasUnsavedChanges = false;
        $this->lastSaved         = time();
        $this->lastAutoSave      = time();
    }

    /**
     * Mark session as modified
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function markAsModified(): void
    {
        $this->hasUnsavedChanges = true;
        $this->lastUpdated       = time();
    }

    /**
     * Check if session has unsaved changes
     *
     * @since 1.0.0
     *
     * @return bool True if session has unsaved changes, false otherwise
     */
    public function hasUnsavedChanges(): bool
    {
        return $this->hasUnsavedChanges;
    }

    /**
     * Restore messages from database without marking as modified
     *
     * @since 1.0.0
     *
     * @param array $messages Array of message data
     *
     * @return void
     */
    public function restoreMessages(array $messages): void
    {
        $this->messages = $messages;
        // Don't call markAsModified() during restoration
    }

    /**
     * Restore state from database without marking as modified
     *
     * @since 1.0.0
     *
     * @param string $state State to restore
     *
     * @return void
     */
    public function restoreState(string $state): void
    {
        $this->currentState = $state;
        // Don't call markAsModified() during restoration
    }

    /**
     * Restore context from database without marking as modified
     *
     * @since 1.0.0
     *
     * @param array $context Context array to restore
     *
     * @return void
     */
    public function restoreContext(array $context): void
    {
        $this->context = $context;
        // Don't call markAsModified() during restoration
    }

    /**
     * Restore progress from database without marking as modified
     *
     * @since 1.0.0
     *
     * @param float $progress Progress percentage to restore
     *
     * @return void
     */
    public function restoreProgress(float $progress): void
    {
        $this->progress = $progress;
        // Don't call markAsModified() during restoration
    }

    /**
     * Restore confidence score from database without marking as modified
     *
     * @since 1.0.0
     *
     * @param float $confidenceScore Confidence score to restore
     *
     * @return void
     */
    public function restoreConfidenceScore(float $confidenceScore): void
    {
        $this->confidenceScore = $confidenceScore;
        // Don't call markAsModified() during restoration
    }

    /**
     * Restore metadata from database without marking as modified
     *
     * @since 1.0.0
     *
     * @param array $metadata Metadata array to restore
     *
     * @return void
     */
    public function restoreMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
        // Don't call markAsModified() during restoration
    }

    /**
     * Restore state history from database without marking as modified
     *
     * @since 1.0.0
     *
     * @param array $stateHistory State history array to restore
     *
     * @return void
     */
    public function restoreStateHistory(array $stateHistory): void
    {
        $this->stateHistory = $stateHistory;
        // Don't call markAsModified() during restoration
    }

    /**
     * Calculate session statistics
     *
     * @since 1.0.0
     *
     * @return array Session statistics including:
     *               - total_messages (int)
     *               - user_messages (int)
     *               - assistant_messages (int)
     *               - system_messages (int)
     *               - duration_seconds (int)
     *               - average_response_time (float)
     *               - state_transitions (int)
     *               - progress_percentage (float)
     *               - confidence_score (float)
     *               - total_tokens (int)
     *               - total_cost (float)
     *               - is_active (bool)
     *               - has_unsaved_changes (bool)
     */
    public function getStatistics(): array
    {
        $userMessages      = $this->getMessagesByType('user');
        $assistantMessages = $this->getMessagesByType('assistant');
        $systemMessages    = $this->getMessagesByType('system');

        $duration        = $this->lastUpdated - $this->createdAt;
        $avgResponseTime = count($assistantMessages) > 0 ? $duration / count($assistantMessages) : 0;

        return [
            'total_messages'        => count($this->messages),
            'user_messages'         => count($userMessages),
            'assistant_messages'    => count($assistantMessages),
            'system_messages'       => count($systemMessages),
            'duration_seconds'      => $duration,
            'average_response_time' => $avgResponseTime,
            'state_transitions'     => count($this->stateHistory),
            'progress_percentage'   => $this->progress,
            'confidence_score'      => $this->confidenceScore,
            'total_tokens'          => $this->totalTokens,
            'total_cost'            => $this->totalCost,
            'is_active'             => $this->isActive,
            'has_unsaved_changes'   => $this->hasUnsavedChanges,
        ];
    }

    /**
     * Export session data
     *
     * @since 1.0.0
     *
     * @return array Complete session data array
     */
    public function toArray(): array
    {
        return [
            'session_id'          => $this->sessionId,
            'user_id'             => $this->userId,
            'context_type'        => $this->contextType,
            'database_id'         => $this->databaseId,
            'title'               => $this->title,
            'current_state'       => $this->currentState,
            'state_history'       => $this->stateHistory,
            'paused_from_state'   => $this->pausedFromState,
            'is_active'           => $this->isActive,
            'context'             => $this->context,
            'metadata'            => $this->metadata,
            'messages'            => $this->messages,
            'progress'            => $this->progress,
            'confidence_score'    => $this->confidenceScore,
            'created_at'          => $this->createdAt,
            'last_updated'        => $this->lastUpdated,
            'last_saved'          => $this->lastSaved,
            'has_unsaved_changes' => $this->hasUnsavedChanges,
            'total_tokens'        => $this->totalTokens,
            'total_cost'          => $this->totalCost,
            'statistics'          => $this->getStatistics(),
        ];
    }

    /**
     * Create session from array data
     *
     * @since 1.0.0
     *
     * @param array $data Session data array containing:
     *                    - session_id (string): Required session identifier
     *                    - user_id (int): Required user ID
     *                    - context_type (string): Optional context type
     *                    - database_id (int|null): Optional database ID
     *                    - title (string): Optional session title
     *                    - current_state (string): Optional current state
     *                    - state_history (array): Optional state history
     *                    - paused_from_state (string|null): Optional paused state
     *                    - is_active (bool): Optional active status
     *                    - context (array): Optional context data
     *                    - metadata (array): Optional metadata
     *                    - messages (array): Optional messages array
     *                    - progress (float): Optional progress percentage
     *                    - confidence_score (float): Optional confidence score
     *                    - created_at (int): Optional creation timestamp
     *                    - last_updated (int): Optional last update timestamp
     *                    - last_saved (int): Optional last save timestamp
     *                    - has_unsaved_changes (bool): Optional unsaved changes flag
     *                    - total_tokens (int): Optional total tokens
     *                    - total_cost (float): Optional total cost
     *
     * @return self New ConversationSession instance
     */
    public static function fromArray(array $data): self
    {
        $session = new self($data['session_id'], $data['user_id'], $data['context_type'] ?? 'course_creation');

        $session->databaseId        = $data['database_id'] ?? null;
        $session->title             = $data['title'] ?? '';
        $session->currentState      = $data['current_state'] ?? 'initial';
        $session->stateHistory      = $data['state_history'] ?? [];
        $session->pausedFromState   = $data['paused_from_state'] ?? null;
        $session->isActive          = $data['is_active'] ?? true;
        $session->context           = $data['context'] ?? [];
        $session->metadata          = $data['metadata'] ?? [];
        $session->progress          = $data['progress'] ?? 0.0;
        $session->confidenceScore   = $data['confidence_score'] ?? 0.0;
        $session->createdAt         = $data['created_at'] ?? time();
        $session->lastUpdated       = $data['last_updated'] ?? time();
        $session->lastSaved         = $data['last_saved'] ?? 0;
        $session->hasUnsavedChanges = $data['has_unsaved_changes'] ?? false;
        $session->totalTokens       = $data['total_tokens'] ?? 0;
        $session->totalCost         = $data['total_cost'] ?? 0.0;

        // Restore messages
        foreach ($data['messages'] ?? [] as $message) {
            $session->messages[] = $message;
        }

        return $session;
    }

    // GETTERS AND SETTERS

    /**
     * Get session ID
     *
     * @since 1.0.0
     *
     * @return string Session identifier
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Get user ID
     *
     * @since 1.0.0
     *
     * @return int User ID
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Get context type
     *
     * @since 1.0.0
     *
     * @return string Context type
     */
    public function getContextType(): string
    {
        return $this->contextType;
    }

    /**
     * Get database ID
     *
     * @since 1.0.0
     *
     * @return int|null Database ID or null if not saved
     */
    public function getDatabaseId(): ?int
    {
        return $this->databaseId;
    }

    /**
     * Set database ID
     *
     * @since 1.0.0
     *
     * @param int $id Database ID
     *
     * @return void
     */
    public function setDatabaseId(int $id): void
    {
        $this->databaseId = $id;
    }

    /**
     * Get session title
     *
     * @since 1.0.0
     *
     * @return string Session title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Set session title
     *
     * @since 1.0.0
     *
     * @param string $title Session title
     *
     * @return void
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->markAsModified();
    }

    /**
     * Get creation timestamp
     *
     * @since 1.0.0
     *
     * @return int Unix timestamp of creation
     */
    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    /**
     * Set creation timestamp
     *
     * @since 1.0.0
     *
     * @param int $timestamp Unix timestamp
     *
     * @return void
     */
    public function setCreatedAt(int $timestamp): void
    {
        $this->createdAt = $timestamp;
    }

    /**
     * Get last updated timestamp
     *
     * @since 1.0.0
     *
     * @return int Unix timestamp of last update
     */
    public function getLastUpdated(): int
    {
        return $this->lastUpdated;
    }

    /**
     * Set last updated timestamp
     *
     * @since 1.0.0
     *
     * @param int $timestamp Unix timestamp
     *
     * @return void
     */
    public function setLastUpdated(int $timestamp): void
    {
        $this->lastUpdated = $timestamp;
    }

    /**
     * Get last saved timestamp
     *
     * @since 1.0.0
     *
     * @return int Unix timestamp of last save
     */
    public function getLastSaved(): int
    {
        return $this->lastSaved;
    }

    /**
     * Get total tokens used
     *
     * @since 1.0.0
     *
     * @return int Total number of tokens
     */
    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    /**
     * Set total tokens
     *
     * @since 1.0.0
     *
     * @param int $tokens Total number of tokens
     *
     * @return void
     */
    public function setTotalTokens(int $tokens): void
    {
        $this->totalTokens = $tokens;
        $this->markAsModified();
    }

    /**
     * Add tokens to total
     *
     * @since 1.0.0
     *
     * @param int $tokens Number of tokens to add
     *
     * @return void
     */
    public function addTokens(int $tokens): void
    {
        $this->totalTokens += $tokens;
        $this->markAsModified();
    }

    /**
     * Get total cost
     *
     * @since 1.0.0
     *
     * @return float Total cost amount
     */
    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    /**
     * Set total cost
     *
     * @since 1.0.0
     *
     * @param float $cost Total cost amount
     *
     * @return void
     */
    public function setTotalCost(float $cost): void
    {
        $this->totalCost = $cost;
        $this->markAsModified();
    }

    /**
     * Add cost to total
     *
     * @since 1.0.0
     *
     * @param float $cost Cost amount to add
     *
     * @return void
     */
    public function addCost(float $cost): void
    {
        $this->totalCost += $cost;
        $this->markAsModified();
    }

    /**
     * Create a recovery checkpoint
     *
     * @since 1.0.0
     *
     * @param string $name Optional checkpoint name
     *
     * @return array Checkpoint data containing:
     *               - name (string)
     *               - timestamp (int)
     *               - state (string)
     *               - context (array)
     *               - progress (float)
     *               - message_count (int)
     *               - confidence_score (float)
     */
    public function createCheckpoint(string $name = ''): array
    {
        $checkpointName = $name ?: 'checkpoint_' . time();

        $checkpoint = [
            'name'             => $checkpointName,
            'timestamp'        => time(),
            'state'            => $this->currentState,
            'context'          => $this->context,
            'progress'         => $this->progress,
            'message_count'    => count($this->messages),
            'confidence_score' => $this->confidenceScore,
        ];

        $this->setMetadata('checkpoints', array_merge(
            $this->getMetadata('checkpoints') ?: [],
            [$checkpointName => $checkpoint]
        ));

        return $checkpoint;
    }

    /**
     * Restore from a checkpoint
     *
     * @since 1.0.0
     *
     * @param string $checkpointName Name of checkpoint to restore
     *
     * @return bool True if checkpoint restored successfully, false otherwise
     */
    public function restoreFromCheckpoint(string $checkpointName): bool
    {
        $checkpoints = $this->getMetadata('checkpoints') ?: [];

        if (!isset($checkpoints[$checkpointName])) {
            return false;
        }

        $checkpoint = $checkpoints[$checkpointName];

        $this->currentState    = $checkpoint['state'];
        $this->context         = $checkpoint['context'];
        $this->progress        = $checkpoint['progress'];
        $this->confidenceScore = $checkpoint['confidence_score'];

        // Trim messages to checkpoint count
        if (isset($checkpoint['message_count']) && count($this->messages) > $checkpoint['message_count']) {
            $this->messages = array_slice($this->messages, 0, $checkpoint['message_count']);
        }

        $this->markAsModified();

        return true;
    }

    /**
     * Get available checkpoints
     *
     * @since 1.0.0
     *
     * @return array Array of checkpoint data indexed by checkpoint name
     */
    public function getCheckpoints(): array
    {
        return $this->getMetadata('checkpoints') ?: [];
    }

    /**
     * Update progress based on current state
     *
     * @since 1.0.0
     *
     * @param string $state Current state to map to progress
     *
     * @return void
     */
    private function updateProgressFromState(string $state): void
    {
        // Define progress percentages for each state
        $stateProgress = [
            'initial'                => 0,
            'welcome'                => 0,
            'template_selection'     => 10,
            'requirements_gathering' => 20,
            'structure_generation'   => 35,
            'structure_review'       => 45,
            'content_generation'     => 60,
            'content_review'         => 75,
            'final_review'           => 90,
            'wordpress_creation'     => 95,
            'completed'              => 100,
            'complete'               => 100,
        ];

        // Update progress if state is recognized
        if (isset($stateProgress[$state])) {
            $this->updateProgress($stateProgress[$state]);
        }
    }
}
