<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\ConversationSession;

/**
 * Conversation Manager Service
 * 
 * Manages conversation session lifecycle, state validation and transitions,
 * context preservation, conversation history, and user progress tracking.
 * Provides comprehensive session management with auto-save, timeout handling,
 * and multi-device synchronization capabilities.
 */
class ConversationManager extends BaseService
{
    private DatabaseService $databaseService;
    private array $activeSessions = [];
    private array $sessionCache = [];
    
    // Session configuration
    private const MAX_ACTIVE_SESSIONS_PER_USER = 5;
    private const SESSION_CLEANUP_INTERVAL = 3600; // 1 hour
    private const CACHE_TTL = 900; // 15 minutes
    private const MAX_MESSAGE_HISTORY = 1000;
    private const AUTO_SAVE_THRESHOLD = 10; // messages
    
    // Session states for management
    private const MANAGEABLE_STATES = [
        'active', 'paused', 'completed', 'error', 'abandoned'
    ];

    public function __construct(?DatabaseService $databaseService = null)
    {
        parent::__construct();
        $this->databaseService = $databaseService ?: new DatabaseService();
    }
    
    /**
     * Initialize the service
     *
     * @return void
     */
    public function init(): void
    {
        // Schedule cleanup tasks
        add_action('init', [$this, 'scheduleCleanupTasks']);
        add_action('mpcc_cleanup_sessions', [$this, 'cleanupExpiredSessions']);
    }

    /**
     * Create a new conversation session
     */
    public function createSession(array $sessionData): ConversationSession
    {
        $userId = $sessionData['user_id'] ?? get_current_user_id();
        
        // Validate user session limits
        $this->enforceSessionLimits($userId);
        
        // Use provided session ID or generate unique session ID
        $sessionId = $sessionData['session_id'] ?? $this->generateSessionId();
        
        // Prepare session data
        $sessionRecord = [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'state' => 'active',
            'context' => $sessionData['context'] ?? 'course_creation',
            'title' => $sessionData['title'] ?? 'New Course (Draft)',
            'messages' => json_encode([]),
            'metadata' => json_encode($sessionData['initial_data'] ?? []),
            'step_data' => json_encode([
                'current_state' => $sessionData['state'] ?? 'initial',
                'state_history' => [],
                'context' => $sessionData['initial_data'] ?? [],
                'progress' => 0.0,
                'confidence_score' => 0.0
            ]),
            'total_tokens' => 0,
            'total_cost' => 0.0
        ];
        
        // Save to database
        $conversationId = $this->databaseService->insertConversation($sessionRecord);
        
        if (!$conversationId) {
            throw new \Exception('Failed to create conversation session in database');
        }
        
        // Create session object
        $session = new ConversationSession($sessionId, $userId, $sessionData['context'] ?? 'course_creation');
        $session->setDatabaseId($conversationId);
        $session->setCurrentState($sessionData['state'] ?? 'initial');
        // Pass array as first parameter with null as second to set entire context
        $session->setContext($sessionData['initial_data'] ?? [], null);
        
        // Add to active sessions and cache
        $this->activeSessions[$sessionId] = $session;
        $this->cacheSession($session);
        
        // Log session creation
        $this->log("Created new conversation session: {$sessionId} for user: {$userId}");
        
        return $session;
    }

    /**
     * Load an existing conversation session
     */
    public function loadSession(string $sessionId): ?ConversationSession
    {
        // Check cache first
        if (isset($this->sessionCache[$sessionId])) {
            $cached = $this->sessionCache[$sessionId];
            if ($cached['expires'] > time()) {
                return $cached['session'];
            } else {
                unset($this->sessionCache[$sessionId]);
            }
        }
        
        // Load from database
        $conversationId = $this->getConversationIdBySessionId($sessionId);
        
        // Check if conversation ID exists before trying to load
        if (!$conversationId) {
            return null;
        }
        
        $sessionData = $this->databaseService->getConversation($conversationId);
        
        if (!$sessionData) {
            return null;
        }
        
        // Create session object from database data
        $session = $this->createSessionFromDatabaseRecord($sessionData);
        
        // Cache the session
        $this->cacheSession($session);
        
        return $session;
    }

    /**
     * Load multiple conversation sessions in a single query
     * Batch loading to avoid N+1 queries
     *
     * @param array $sessionIds Array of session IDs to load
     * @return array<string, ConversationSession> Array of sessions keyed by session_id
     */
    public function loadMultipleSessions(array $sessionIds): array
    {
        if (empty($sessionIds)) {
            return [];
        }
        
        $sessions = [];
        $sessionIdsToLoad = [];
        
        // First check cache for any already loaded sessions
        foreach ($sessionIds as $sessionId) {
            if (isset($this->sessionCache[$sessionId])) {
                $cached = $this->sessionCache[$sessionId];
                if ($cached['expires'] > time()) {
                    $sessions[$sessionId] = $cached['session'];
                    continue;
                } else {
                    unset($this->sessionCache[$sessionId]);
                }
            }
            $sessionIdsToLoad[] = $sessionId;
        }
        
        // If all sessions were cached, return early
        if (empty($sessionIdsToLoad)) {
            return $sessions;
        }
        
        // Load remaining sessions from database in a single query
        $conversations = $this->databaseService->getConversationsBySessionIds($sessionIdsToLoad);
        
        // Create session objects from database records
        foreach ($conversations as $sessionId => $conversationData) {
            $session = $this->createSessionFromDatabaseRecord($conversationData);
            $sessions[$sessionId] = $session;
            
            // Cache the session
            $this->cacheSession($session);
        }
        
        return $sessions;
    }

    /**
     * Save conversation session to database
     */
    public function saveSession(ConversationSession $session): bool
    {
        $sessionData = [
            'state' => $session->isActive() ? 'active' : 'paused',
            'title' => $session->getTitle(),
            'messages' => json_encode($session->getMessages(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'metadata' => json_encode($session->getMetadata()),
            'step_data' => json_encode([
                'current_state' => $session->getCurrentState(),
                'state_history' => $session->getStateHistory(),
                'context' => $session->getContext(),
                'progress' => $session->getProgress(),
                'confidence_score' => $session->getConfidenceScore(),
                'paused_from_state' => $session->getPausedFromState()
            ]),
            'total_tokens' => $session->getTotalTokens(),
            'total_cost' => $session->getTotalCost()
        ];
        
        $success = $this->databaseService->updateConversation($session->getDatabaseId(), $sessionData);
        
        if ($success) {
            // Update cache
            $this->cacheSession($session);
            $session->markAsSaved();
            
            $this->log("Saved conversation session: " . $session->getSessionId());
        } else {
            $this->log("Failed to save conversation session: " . $session->getSessionId(), 'error');
        }
        
        return $success;
    }

    /**
     * Create session from exported data
     */
    public function createSessionFromData(array $sessionData): ConversationSession
    {
        $session = new ConversationSession(
            $sessionData['session_id'],
            $sessionData['user_id'],
            $sessionData['context']
        );
        
        // Restore session state
        $session->setCurrentState($sessionData['current_state']);
        $session->setContext($sessionData['context_data'], null);
        $session->setStateHistory($sessionData['state_history']);
        $session->setProgress($sessionData['progress']);
        
        // Restore messages
        foreach ($sessionData['messages'] as $message) {
            $session->addMessage($message['type'], $message['content'], $message['metadata']);
        }
        
        // Save to database
        $conversationId = $this->databaseService->insertConversation([
            'user_id' => $session->getUserId(),
            'session_id' => $session->getSessionId(),
            'state' => 'active',
            'context' => $session->getContextType(),
            'title' => 'Imported Session',
            'messages' => json_encode($session->getMessages()),
            'metadata' => json_encode($sessionData['metadata'] ?? []),
            'step_data' => json_encode([
                'current_state' => $session->getCurrentState(),
                'state_history' => $session->getStateHistory(),
                'context' => $session->getContext(),
                'progress' => $session->getProgress()
            ])
        ]);
        
        $session->setDatabaseId($conversationId);
        $this->cacheSession($session);
        
        return $session;
    }

    /**
     * Get all active sessions for a user
     */
    public function getUserSessions(int $userId, int $limit = 20, int $offset = 0): array
    {
        $sessionRecords = $this->databaseService->getConversationsByUser($userId, $limit, $offset);
        $sessions = [];
        
        foreach ($sessionRecords as $record) {
            $sessions[] = $this->createSessionFromDatabaseRecord($record);
        }
        
        return $sessions;
    }

    /**
     * Pause a conversation session
     */
    public function pauseSession(string $sessionId, string $reason = ''): bool
    {
        $session = $this->loadSession($sessionId);
        
        if (!$session) {
            return false;
        }
        
        $session->pause($reason);
        return $this->saveSession($session);
    }

    /**
     * Resume a paused conversation session
     */
    public function resumeSession(string $sessionId): bool
    {
        $session = $this->loadSession($sessionId);
        
        if (!$session) {
            return false;
        }
        
        $session->resume();
        return $this->saveSession($session);
    }

    /**
     * Complete a conversation session
     */
    public function completeSession(string $sessionId, array $completionData = []): bool
    {
        $session = $this->loadSession($sessionId);
        
        if (!$session) {
            return false;
        }
        
        $session->complete($completionData);
        
        // Remove from active sessions
        unset($this->activeSessions[$sessionId]);
        
        return $this->saveSession($session);
    }

    /**
     * Abandon a conversation session
     */
    public function abandonSession(string $sessionId, string $reason = ''): bool
    {
        $session = $this->loadSession($sessionId);
        
        if (!$session) {
            return false;
        }
        
        $session->abandon($reason);
        
        // Remove from active sessions
        unset($this->activeSessions[$sessionId]);
        
        return $this->saveSession($session);
    }

    /**
     * Delete a conversation session permanently
     */
    public function deleteSession(string $sessionId): bool
    {
        $session = $this->loadSession($sessionId);
        
        if (!$session) {
            return false;
        }
        
        // Delete from database
        $conversationId = $session->getDatabaseId();
        $deleted = $this->databaseService->deleteConversation($conversationId);
        
        if ($deleted) {
            // Remove from cache and active sessions
            unset($this->activeSessions[$sessionId]);
            unset($this->sessionCache[$sessionId]);
            
            $this->log("Deleted conversation session: {$sessionId}");
        }
        
        return $deleted;
    }

    /**
     * Sync session across multiple devices/browsers
     */
    public function syncSession(string $sessionId, array $clientState): array
    {
        $session = $this->loadSession($sessionId);
        
        if (!$session) {
            throw new \Exception('Session not found for sync');
        }
        
        // Get server state
        $serverState = [
            'current_state' => $session->getCurrentState(),
            'progress' => $session->getProgress(),
            'last_updated' => $session->getLastUpdated(),
            'message_count' => count($session->getMessages()),
            'context_hash' => md5(json_encode($session->getContext()))
        ];
        
        // Compare with client state
        $clientLastUpdated = $clientState['last_updated'] ?? 0;
        $serverLastUpdated = $session->getLastUpdated();
        
        $syncResponse = [
            'needs_update' => $serverLastUpdated > $clientLastUpdated,
            'server_state' => $serverState,
            'conflict_detected' => false
        ];
        
        if ($syncResponse['needs_update']) {
            $syncResponse['updated_data'] = [
                'current_state' => $session->getCurrentState(),
                'progress' => $session->getProgress(),
                'context' => $session->getContext(),
                'recent_messages' => $session->getRecentMessages(10),
                'last_updated' => $serverLastUpdated
            ];
        }
        
        // Check for conflicts (if client has newer data)
        if (isset($clientState['last_modified']) && $clientState['last_modified'] > $serverLastUpdated) {
            $syncResponse['conflict_detected'] = true;
            $syncResponse['conflict_resolution_options'] = [
                'use_server' => 'Use server version (recommended)',
                'use_client' => 'Use your local changes',
                'merge' => 'Try to merge changes'
            ];
        }
        
        return $syncResponse;
    }

    /**
     * Export session data for backup or transfer
     */
    public function exportSession(string $sessionId): array
    {
        $session = $this->loadSession($sessionId);
        
        if (!$session) {
            throw new \Exception('Session not found for export');
        }
        
        return [
            'export_version' => '1.0',
            'export_timestamp' => current_time('timestamp'),
            'session_id' => $session->getSessionId(),
            'user_id' => $session->getUserId(),
            'context' => $session->getContextType(),
            'current_state' => $session->getCurrentState(),
            'state_history' => $session->getStateHistory(),
            'context_data' => $session->getContext(),
            'progress' => $session->getProgress(),
            'messages' => $session->getMessages(),
            'metadata' => $session->getMetadata(),
            'created_at' => $session->getCreatedAt(),
            'last_updated' => $session->getLastUpdated(),
            'total_tokens' => $session->getTotalTokens(),
            'total_cost' => $session->getTotalCost()
        ];
    }

    /**
     * Get session analytics and statistics
     */
    public function getSessionAnalytics(string $sessionId): array
    {
        $session = $this->loadSession($sessionId);
        
        if (!$session) {
            return [];
        }
        
        $messages = $session->getMessages();
        $userMessages = array_filter($messages, fn($msg) => $msg['type'] === 'user');
        $assistantMessages = array_filter($messages, fn($msg) => $msg['type'] === 'assistant');
        
        $sessionDuration = $session->getLastUpdated() - $session->getCreatedAt();
        $avgResponseTime = $sessionDuration / max(1, count($assistantMessages));
        
        return [
            'session_id' => $sessionId,
            'duration_seconds' => $sessionDuration,
            'total_messages' => count($messages),
            'user_messages' => count($userMessages),
            'assistant_messages' => count($assistantMessages),
            'system_messages' => count($messages) - count($userMessages) - count($assistantMessages),
            'average_response_time' => $avgResponseTime,
            'progress_percentage' => $session->getProgress(),
            'current_state' => $session->getCurrentState(),
            'state_transitions' => count($session->getStateHistory()),
            'total_tokens_used' => $session->getTotalTokens(),
            'total_cost' => $session->getTotalCost(),
            'engagement_score' => $this->calculateEngagementScore($session),
            'completion_likelihood' => $this->predictCompletionLikelihood($session)
        ];
    }

    /**
     * Schedule cleanup tasks
     */
    public function scheduleCleanupTasks(): void
    {
        if (!wp_next_scheduled('mpcc_cleanup_sessions')) {
            wp_schedule_event(time(), 'hourly', 'mpcc_cleanup_sessions');
        }
    }

    /**
     * Clean up expired and abandoned sessions
     */
    public function cleanupExpiredSessions(): void
    {
        $expiredTime = time() - self::SESSION_CLEANUP_INTERVAL;
        
        try {
            // Get expired sessions
            $expiredSessions = $this->databaseService->getExpiredSessions($expiredTime);
            
            // Collect active session IDs that need to be abandoned
            $activeSessionIds = [];
            foreach ($expiredSessions as $sessionData) {
                if ($sessionData->state === 'active') {
                    $activeSessionIds[] = $sessionData->id;
                }
            }
            
            // Batch update all active sessions to abandoned state (avoids N+1 queries)
            if (!empty($activeSessionIds)) {
                $abandonedCount = $this->databaseService->batchAbandonConversations(
                    $activeSessionIds,
                    current_time('mysql')
                );
                $this->log("Auto-abandoned {$abandonedCount} active sessions");
            }
            
            // Clean up cache
            foreach ($this->sessionCache as $sessionId => $cached) {
                if ($cached['expires'] <= time()) {
                    unset($this->sessionCache[$sessionId]);
                }
            }
            
            $this->log("Cleaned up " . count($expiredSessions) . " expired sessions");
            
        } catch (\Exception $e) {
            $this->log("Error during session cleanup: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Get conversation ID by session ID
     */
    private function getConversationIdBySessionId(string $sessionId): ?int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mpcc_conversations';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE session_id = %s",
            $sessionId
        ));
        
        return $result ? (int)$result : null;
    }

    /**
     * Create session object from database record
     */
    private function createSessionFromDatabaseRecord(object $record): ConversationSession
    {
        $stepData = json_decode($record->step_data, true) ?: [];
        $metadata = json_decode($record->metadata, true) ?: [];
        $messages = json_decode($record->messages, true) ?: [];
        
        $session = new ConversationSession(
            $record->session_id,
            $record->user_id,
            $record->context
        );
        
        $session->setDatabaseId($record->id);
        $session->setTitle($record->title);
        $session->restoreState($stepData['current_state'] ?? 'initial');
        $session->restoreContext($stepData['context'] ?? []);
        $session->restoreStateHistory($stepData['state_history'] ?? []);
        $session->restoreProgress($stepData['progress'] ?? 0.0);
        $session->restoreConfidenceScore($stepData['confidence_score'] ?? 0.0);
        $session->restoreMetadata($metadata);
        $session->setTotalTokens($record->total_tokens ?? 0);
        $session->setTotalCost($record->total_cost ?? 0.0);
        $session->setCreatedAt(strtotime($record->created_at));
        $session->setLastUpdated(strtotime($record->updated_at));
        
        if (isset($stepData['paused_from_state'])) {
            $session->setPausedFromState($stepData['paused_from_state']);
        }
        
        // Restore messages without triggering markAsModified
        $session->restoreMessages($messages);
        
        // Mark as saved since it's from database
        $session->markAsSaved();
        
        return $session;
    }

    /**
     * Cache session in memory
     */
    private function cacheSession(ConversationSession $session): void
    {
        $this->sessionCache[$session->getSessionId()] = [
            'session' => $session,
            'expires' => time() + self::CACHE_TTL
        ];
    }

    /**
     * Generate unique session ID
     */
    private function generateSessionId(): string
    {
        return 'mpcc_session_' . wp_generate_uuid4() . '_' . time();
    }

    /**
     * Enforce session limits per user
     */
    private function enforceSessionLimits(int $userId): void
    {
        $activeSessions = $this->databaseService->getActiveSessionCount($userId);
        
        if ($activeSessions >= self::MAX_ACTIVE_SESSIONS_PER_USER) {
            // Auto-abandon oldest session
            $oldestSession = $this->databaseService->getOldestActiveSession($userId);
            if ($oldestSession) {
                $this->abandonSession($oldestSession->session_id, 'Auto-abandoned due to session limit');
            }
        }
    }

    /**
     * Calculate engagement score for session
     */
    private function calculateEngagementScore(ConversationSession $session): float
    {
        $messages = $session->getMessages();
        $userMessages = array_filter($messages, fn($msg) => $msg['type'] === 'user');
        
        $messageCount = count($userMessages);
        $sessionDuration = max(1, $session->getLastUpdated() - $session->getCreatedAt());
        $progress = $session->getProgress();
        
        // Calculate based on message frequency, session duration, and progress
        $messageFrequency = $messageCount / ($sessionDuration / 3600); // messages per hour
        $progressRate = $progress / max(1, $sessionDuration / 3600); // progress per hour
        
        $engagementScore = min(1.0, ($messageFrequency * 0.4) + ($progressRate * 0.6));
        
        return round($engagementScore, 2);
    }

    /**
     * Predict likelihood of session completion
     */
    private function predictCompletionLikelihood(ConversationSession $session): float
    {
        $progress = $session->getProgress();
        $engagementScore = $this->calculateEngagementScore($session);
        $stateTransitions = count($session->getStateHistory());
        
        // Simple prediction model based on current progress and engagement
        $completionLikelihood = ($progress * 0.5) + ($engagementScore * 0.3) + (min(1.0, $stateTransitions / 10) * 0.2);
        
        return round(min(1.0, $completionLikelihood), 2);
    }
}