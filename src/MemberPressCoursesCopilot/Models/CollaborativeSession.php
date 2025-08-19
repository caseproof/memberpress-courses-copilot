<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Models;

/**
 * Collaborative Session Model
 * 
 * Manages multi-user collaborative sessions for course editing
 * 
 * @package MemberPressCoursesCopilot\Models
 * @since 1.0.0
 */
class CollaborativeSession extends BaseModel
{
    /**
     * User roles in collaborative sessions
     */
    public const ROLE_OWNER = 'owner';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_REVIEWER = 'reviewer';
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_GUEST = 'guest';

    /**
     * Session status types
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_LOCKED = 'locked';

    /**
     * Change types for tracking
     */
    public const CHANGE_CREATE = 'create';
    public const CHANGE_UPDATE = 'update';
    public const CHANGE_DELETE = 'delete';
    public const CHANGE_COMMENT = 'comment';
    public const CHANGE_APPROVE = 'approve';
    public const CHANGE_REJECT = 'reject';

    /**
     * Permission types
     */
    public const PERMISSION_READ = 'read';
    public const PERMISSION_WRITE = 'write';
    public const PERMISSION_COMMENT = 'comment';
    public const PERMISSION_APPROVE = 'approve';
    public const PERMISSION_MANAGE_USERS = 'manage_users';
    public const PERMISSION_MANAGE_SETTINGS = 'manage_settings';

    /**
     * Default permissions for each role
     */
    private const ROLE_PERMISSIONS = [
        self::ROLE_OWNER => [
            self::PERMISSION_READ,
            self::PERMISSION_WRITE,
            self::PERMISSION_COMMENT,
            self::PERMISSION_APPROVE,
            self::PERMISSION_MANAGE_USERS,
            self::PERMISSION_MANAGE_SETTINGS
        ],
        self::ROLE_EDITOR => [
            self::PERMISSION_READ,
            self::PERMISSION_WRITE,
            self::PERMISSION_COMMENT
        ],
        self::ROLE_REVIEWER => [
            self::PERMISSION_READ,
            self::PERMISSION_COMMENT,
            self::PERMISSION_APPROVE
        ],
        self::ROLE_VIEWER => [
            self::PERMISSION_READ
        ],
        self::ROLE_GUEST => [
            self::PERMISSION_READ
        ]
    ];

    /**
     * Initialize model with default values
     */
    public function __construct(array $data = [])
    {
        $defaults = [
            'id' => null,
            'course_id' => null,
            'session_name' => '',
            'owner_id' => null,
            'status' => self::STATUS_ACTIVE,
            'participants' => [],
            'change_log' => [],
            'comments' => [],
            'version_history' => [],
            'settings' => [
                'auto_save_interval' => 30, // seconds
                'conflict_resolution' => 'merge', // merge, overwrite, prompt
                'notifications_enabled' => true,
                'chat_enabled' => true,
                'voice_chat_enabled' => false,
                'screen_sharing_enabled' => false
            ],
            'created_at' => null,
            'updated_at' => null,
            'last_activity' => null
        ];

        parent::__construct(array_merge($defaults, $data));
    }

    /**
     * Add a participant to the session
     *
     * @param int $user_id User ID
     * @param string $role User role
     * @param array $custom_permissions Optional custom permissions
     * @return bool
     */
    public function addParticipant(int $user_id, string $role = self::ROLE_VIEWER, array $custom_permissions = []): bool
    {
        if (!$this->isValidRole($role)) {
            return false;
        }

        $participants = $this->get('participants', []);
        
        // Check if user is already a participant
        if (isset($participants[$user_id])) {
            return false;
        }

        $permissions = $custom_permissions ?: $this->getDefaultPermissions($role);

        $participants[$user_id] = [
            'user_id' => $user_id,
            'role' => $role,
            'permissions' => $permissions,
            'joined_at' => current_time('mysql'),
            'last_seen' => current_time('mysql'),
            'is_online' => false,
            'cursor_position' => null,
            'current_section' => null
        ];

        $this->set('participants', $participants);
        $this->logChange($user_id, 'user_joined', ['role' => $role]);

        return true;
    }

    /**
     * Remove a participant from the session
     *
     * @param int $user_id User ID
     * @return bool
     */
    public function removeParticipant(int $user_id): bool
    {
        $participants = $this->get('participants', []);
        
        if (!isset($participants[$user_id])) {
            return false;
        }

        unset($participants[$user_id]);
        $this->set('participants', $participants);
        $this->logChange($user_id, 'user_left');

        return true;
    }

    /**
     * Update participant role
     *
     * @param int $user_id User ID
     * @param string $new_role New role
     * @return bool
     */
    public function updateParticipantRole(int $user_id, string $new_role): bool
    {
        if (!$this->isValidRole($new_role)) {
            return false;
        }

        $participants = $this->get('participants', []);
        
        if (!isset($participants[$user_id])) {
            return false;
        }

        $old_role = $participants[$user_id]['role'];
        $participants[$user_id]['role'] = $new_role;
        $participants[$user_id]['permissions'] = $this->getDefaultPermissions($new_role);

        $this->set('participants', $participants);
        $this->logChange($user_id, 'role_changed', [
            'old_role' => $old_role,
            'new_role' => $new_role
        ]);

        return true;
    }

    /**
     * Check if user has specific permission
     *
     * @param int $user_id User ID
     * @param string $permission Permission to check
     * @return bool
     */
    public function userHasPermission(int $user_id, string $permission): bool
    {
        $participants = $this->get('participants', []);
        
        if (!isset($participants[$user_id])) {
            return false;
        }

        return in_array($permission, $participants[$user_id]['permissions']);
    }

    /**
     * Update user presence (online status and cursor position)
     *
     * @param int $user_id User ID
     * @param array $presence_data Presence data
     * @return bool
     */
    public function updatePresence(int $user_id, array $presence_data): bool
    {
        $participants = $this->get('participants', []);
        
        if (!isset($participants[$user_id])) {
            return false;
        }

        $participants[$user_id]['last_seen'] = current_time('mysql');
        $participants[$user_id]['is_online'] = $presence_data['is_online'] ?? true;
        
        if (isset($presence_data['cursor_position'])) {
            $participants[$user_id]['cursor_position'] = $presence_data['cursor_position'];
        }
        
        if (isset($presence_data['current_section'])) {
            $participants[$user_id]['current_section'] = $presence_data['current_section'];
        }

        $this->set('participants', $participants);
        $this->set('last_activity', current_time('mysql'));

        return true;
    }

    /**
     * Add a comment to the session
     *
     * @param int $user_id User ID
     * @param string $content Comment content
     * @param array $metadata Additional metadata
     * @return string|null Comment ID
     */
    public function addComment(int $user_id, string $content, array $metadata = []): ?string
    {
        if (!$this->userHasPermission($user_id, self::PERMISSION_COMMENT)) {
            return null;
        }

        $comment_id = wp_generate_uuid4();
        $comments = $this->get('comments', []);

        $comments[$comment_id] = [
            'id' => $comment_id,
            'user_id' => $user_id,
            'content' => $content,
            'metadata' => $metadata,
            'replies' => [],
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'is_resolved' => false
        ];

        $this->set('comments', $comments);
        $this->logChange($user_id, self::CHANGE_COMMENT, [
            'comment_id' => $comment_id,
            'content' => $content
        ]);

        return $comment_id;
    }

    /**
     * Reply to a comment
     *
     * @param int $user_id User ID
     * @param string $comment_id Parent comment ID
     * @param string $content Reply content
     * @return string|null Reply ID
     */
    public function replyToComment(int $user_id, string $comment_id, string $content): ?string
    {
        if (!$this->userHasPermission($user_id, self::PERMISSION_COMMENT)) {
            return null;
        }

        $comments = $this->get('comments', []);
        
        if (!isset($comments[$comment_id])) {
            return null;
        }

        $reply_id = wp_generate_uuid4();

        $comments[$comment_id]['replies'][$reply_id] = [
            'id' => $reply_id,
            'user_id' => $user_id,
            'content' => $content,
            'created_at' => current_time('mysql')
        ];

        $this->set('comments', $comments);

        return $reply_id;
    }

    /**
     * Resolve a comment
     *
     * @param int $user_id User ID
     * @param string $comment_id Comment ID
     * @return bool
     */
    public function resolveComment(int $user_id, string $comment_id): bool
    {
        if (!$this->userHasPermission($user_id, self::PERMISSION_APPROVE)) {
            return false;
        }

        $comments = $this->get('comments', []);
        
        if (!isset($comments[$comment_id])) {
            return false;
        }

        $comments[$comment_id]['is_resolved'] = true;
        $comments[$comment_id]['resolved_by'] = $user_id;
        $comments[$comment_id]['resolved_at'] = current_time('mysql');

        $this->set('comments', $comments);

        return true;
    }

    /**
     * Log a change to the session
     *
     * @param int $user_id User ID who made the change
     * @param string $action Action performed
     * @param array $details Additional details
     * @return void
     */
    public function logChange(int $user_id, string $action, array $details = []): void
    {
        $change_log = $this->get('change_log', []);
        $change_id = wp_generate_uuid4();

        $change_log[$change_id] = [
            'id' => $change_id,
            'user_id' => $user_id,
            'action' => $action,
            'details' => $details,
            'timestamp' => current_time('mysql'),
            'session_state_hash' => $this->getStateHash()
        ];

        $this->set('change_log', $change_log);
        $this->set('last_activity', current_time('mysql'));
    }

    /**
     * Create a version snapshot
     *
     * @param int $user_id User ID
     * @param string $description Version description
     * @param array $course_data Current course data
     * @return string Version ID
     */
    public function createVersion(int $user_id, string $description, array $course_data): string
    {
        $version_id = wp_generate_uuid4();
        $version_history = $this->get('version_history', []);

        $version_history[$version_id] = [
            'id' => $version_id,
            'created_by' => $user_id,
            'description' => $description,
            'course_data' => $course_data,
            'created_at' => current_time('mysql'),
            'change_count' => count($this->get('change_log', []))
        ];

        $this->set('version_history', $version_history);

        return $version_id;
    }

    /**
     * Get online participants
     *
     * @return array
     */
    public function getOnlineParticipants(): array
    {
        $participants = $this->get('participants', []);
        $online = [];

        foreach ($participants as $user_id => $participant) {
            if ($participant['is_online']) {
                $online[$user_id] = $participant;
            }
        }

        return $online;
    }

    /**
     * Get session activity summary
     *
     * @return array
     */
    public function getActivitySummary(): array
    {
        $change_log = $this->get('change_log', []);
        $comments = $this->get('comments', []);
        $participants = $this->get('participants', []);

        return [
            'total_changes' => count($change_log),
            'total_comments' => count($comments),
            'total_participants' => count($participants),
            'online_participants' => count($this->getOnlineParticipants()),
            'last_activity' => $this->get('last_activity'),
            'created_at' => $this->get('created_at')
        ];
    }

    /**
     * Get default permissions for a role
     *
     * @param string $role User role
     * @return array
     */
    private function getDefaultPermissions(string $role): array
    {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    /**
     * Check if role is valid
     *
     * @param string $role Role to check
     * @return bool
     */
    private function isValidRole(string $role): bool
    {
        return in_array($role, [
            self::ROLE_OWNER,
            self::ROLE_EDITOR,
            self::ROLE_REVIEWER,
            self::ROLE_VIEWER,
            self::ROLE_GUEST
        ]);
    }

    /**
     * Get current session state hash for conflict detection
     *
     * @return string
     */
    private function getStateHash(): string
    {
        $state_data = [
            'participants' => $this->get('participants'),
            'settings' => $this->get('settings'),
            'updated_at' => $this->get('updated_at')
        ];

        return md5(serialize($state_data));
    }

    /**
     * Validate the collaborative session
     *
     * @return bool
     */
    public function validate(): bool
    {
        $required_fields = ['course_id', 'owner_id', 'session_name'];
        
        foreach ($required_fields as $field) {
            if (!$this->get($field)) {
                return false;
            }
        }

        // Validate owner exists in participants
        $participants = $this->get('participants', []);
        $owner_id = $this->get('owner_id');
        
        if (!isset($participants[$owner_id])) {
            return false;
        }

        // Validate owner has owner role
        if ($participants[$owner_id]['role'] !== self::ROLE_OWNER) {
            return false;
        }

        return true;
    }

    /**
     * Save the collaborative session
     *
     * @return bool
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mp_copilot_collaborative_sessions';
        $now = current_time('mysql');

        $data = [
            'course_id' => $this->get('course_id'),
            'session_name' => $this->get('session_name'),
            'owner_id' => $this->get('owner_id'),
            'status' => $this->get('status'),
            'participants' => json_encode($this->get('participants')),
            'change_log' => json_encode($this->get('change_log')),
            'comments' => json_encode($this->get('comments')),
            'version_history' => json_encode($this->get('version_history')),
            'settings' => json_encode($this->get('settings')),
            'updated_at' => $now,
            'last_activity' => $this->get('last_activity') ?: $now
        ];

        if ($this->get('id')) {
            // Update existing session
            $result = $wpdb->update(
                $table_name,
                $data,
                ['id' => $this->get('id')],
                ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Create new session
            $data['created_at'] = $now;
            
            $result = $wpdb->insert(
                $table_name,
                $data,
                ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if ($result !== false) {
                $this->set('id', $wpdb->insert_id);
            }
        }

        if ($result !== false) {
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Delete the collaborative session
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->get('id')) {
            return false;
        }

        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mp_copilot_collaborative_sessions';
        
        $result = $wpdb->delete(
            $table_name,
            ['id' => $this->get('id')],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Load session by ID
     *
     * @param int $session_id Session ID
     * @return static|null
     */
    public static function find(int $session_id): ?static
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mp_copilot_collaborative_sessions';
        
        $session_data = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $session_id),
            ARRAY_A
        );

        if (!$session_data) {
            return null;
        }

        // Decode JSON fields
        $json_fields = ['participants', 'change_log', 'comments', 'version_history', 'settings'];
        foreach ($json_fields as $field) {
            if (isset($session_data[$field])) {
                $session_data[$field] = json_decode($session_data[$field], true) ?: [];
            }
        }

        return new static($session_data);
    }

    /**
     * Find sessions by course ID
     *
     * @param int $course_id Course ID
     * @return array
     */
    public static function findByCourse(int $course_id): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mp_copilot_collaborative_sessions';
        
        $sessions_data = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE course_id = %d ORDER BY created_at DESC", $course_id),
            ARRAY_A
        );

        $sessions = [];
        foreach ($sessions_data as $session_data) {
            // Decode JSON fields
            $json_fields = ['participants', 'change_log', 'comments', 'version_history', 'settings'];
            foreach ($json_fields as $field) {
                if (isset($session_data[$field])) {
                    $session_data[$field] = json_decode($session_data[$field], true) ?: [];
                }
            }
            
            $sessions[] = new static($session_data);
        }

        return $sessions;
    }
}