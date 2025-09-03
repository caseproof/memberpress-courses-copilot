<?php

namespace MemberPressCoursesCopilot\Services;

use wpdb;
use MemberPressCoursesCopilot\Interfaces\IDatabaseService;

/**
 * Database Service class
 *
 * Handles all database operations including table creation, migrations,
 * data access layer, and query building for MemberPress Courses Copilot
 *
 * @package MemberPressCoursesCopilot\Services
 * @since   1.0.0
 */
class DatabaseService extends BaseService implements IDatabaseService
{
    /**
     * Database version for migrations
     */
    private const DB_VERSION = '1.1.0';

    /**
     * WordPress database instance
     *
     * @var wpdb
     */
    private wpdb $wpdb;

    /**
     * Table prefix for plugin tables
     *
     * @var string
     */
    private string $tablePrefix;

    /**
     * Database constructor
     */
    public function __construct()
    {
        parent::__construct();
        global $wpdb;
        $this->wpdb        = $wpdb;
        $this->tablePrefix = $wpdb->prefix . 'mpcc_';
    }

    /**
     * Initialize the database service
     *
     * @return void
     */
    public function init(): void
    {
        // Hook for handling database operations.
        add_action('init', [$this, 'maybeUpgradeDatabase']);
    }

    /**
     * Install database tables
     *
     * @return boolean True on success, false on failure
     */
    public function installTables(): bool
    {
        try {
            $this->createConversationsTable();
            $this->createTemplatesTable();
            $this->createCoursePatternsTable();
            $this->createUsageAnalyticsTable();
            $this->createQualityMetricsTable();
            $this->createLessonDraftsTable();

            // Update database version.
            $this->updateOption('mpcc_db_version', self::DB_VERSION);

            $this->log('Database tables installed successfully');
            return true;
        } catch (\Exception $e) {
            $this->log('Failed to install database tables: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Create conversations table for chat sessions
     *
     * @return void
     * @throws \Exception
     */
    private function createConversationsTable(): void
    {
        $tableName = $this->tablePrefix . 'conversations';

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            session_id varchar(64) NOT NULL,
            state enum('active', 'paused', 'completed', 'error') DEFAULT 'active',
            context varchar(50) NOT NULL DEFAULT 'course_creation',
            course_id bigint(20) unsigned NULL,
            title varchar(255) NOT NULL DEFAULT '',
            messages longtext NOT NULL,
            metadata json NULL,
            step_data json NULL,
            total_tokens int unsigned DEFAULT 0,
            total_cost decimal(10,6) DEFAULT 0.000000,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at datetime NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_session_id (session_id),
            KEY idx_state (state),
            KEY idx_context (context),
            KEY idx_course_id (course_id),
            KEY idx_created_at (created_at),
            KEY idx_updated_at (updated_at)
        ) {$this->getCharsetCollation()};";

        $this->executeQuery($sql, 'Failed to create conversations table');
    }

    /**
     * Create templates table for reusable course patterns
     *
     * @return void
     * @throws \Exception
     */
    private function createTemplatesTable(): void
    {
        $tableName = $this->tablePrefix . 'templates';

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text NULL,
            category varchar(100) NOT NULL DEFAULT 'general',
            type enum('course', 'section', 'lesson', 'assessment') DEFAULT 'course',
            template_data json NOT NULL,
            prompt_template longtext NULL,
            variables json NULL,
            usage_count int unsigned DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            is_system tinyint(1) DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            version varchar(20) DEFAULT '1.0.0',
            tags json NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category (category),
            KEY idx_type (type),
            KEY idx_is_active (is_active),
            KEY idx_is_system (is_system),
            KEY idx_usage_count (usage_count),
            KEY idx_created_at (created_at),
            KEY idx_created_by (created_by)
        ) {$this->getCharsetCollation()};";

        $this->executeQuery($sql, 'Failed to create templates table');
    }

    /**
     * Create course patterns table for successful patterns with embeddings
     *
     * @return void
     * @throws \Exception
     */
    private function createCoursePatternsTable(): void
    {
        $tableName = $this->tablePrefix . 'course_patterns';

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            pattern_hash varchar(64) NOT NULL,
            course_structure json NOT NULL,
            input_parameters json NOT NULL,
            quality_score decimal(3,2) DEFAULT 0.00,
            success_metrics json NULL,
            embedding_vector json NULL,
            similarity_threshold decimal(3,2) DEFAULT 0.80,
            usage_frequency int unsigned DEFAULT 1,
            last_used_at datetime NULL,
            performance_data json NULL,
            course_category varchar(100) NULL,
            difficulty_level enum('beginner', 'intermediate', 'advanced') NULL,
            estimated_duration int unsigned NULL,
            completion_rate decimal(5,2) NULL,
            user_feedback json NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uk_pattern_hash (pattern_hash),
            KEY idx_quality_score (quality_score),
            KEY idx_usage_frequency (usage_frequency),
            KEY idx_last_used_at (last_used_at),
            KEY idx_course_category (course_category),
            KEY idx_difficulty_level (difficulty_level),
            KEY idx_completion_rate (completion_rate),
            KEY idx_created_at (created_at)
        ) {$this->getCharsetCollation()};";

        $this->executeQuery($sql, 'Failed to create course patterns table');
    }

    /**
     * Create usage analytics table for API usage, costs, and performance
     *
     * @return void
     * @throws \Exception
     */
    private function createUsageAnalyticsTable(): void
    {
        $tableName = $this->tablePrefix . 'usage_analytics';

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            conversation_id bigint(20) unsigned NULL,
            action_type varchar(50) NOT NULL,
            api_endpoint varchar(100) NULL,
            model_used varchar(50) NULL,
            tokens_used int unsigned DEFAULT 0,
            cost_amount decimal(10,6) DEFAULT 0.000000,
            response_time_ms int unsigned NULL,
            success tinyint(1) DEFAULT 1,
            error_message text NULL,
            request_data json NULL,
            response_data json NULL,
            user_agent varchar(255) NULL,
            ip_address varchar(45) NULL,
            session_data json NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_conversation_id (conversation_id),
            KEY idx_action_type (action_type),
            KEY idx_api_endpoint (api_endpoint),
            KEY idx_model_used (model_used),
            KEY idx_success (success),
            KEY idx_created_at (created_at),
            KEY idx_cost_amount (cost_amount),
            KEY idx_response_time (response_time_ms),
            FOREIGN KEY fk_conversation (conversation_id) REFERENCES {$this->tablePrefix}conversations(id) ON DELETE SET NULL
        ) {$this->getCharsetCollation()};";

        $this->executeQuery($sql, 'Failed to create usage analytics table');
    }

    /**
     * Create quality metrics table for course validation results
     *
     * @return void
     * @throws \Exception
     */
    private function createQualityMetricsTable(): void
    {
        $tableName = $this->tablePrefix . 'quality_metrics';

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NULL,
            conversation_id bigint(20) unsigned NULL,
            pattern_id bigint(20) unsigned NULL,
            metric_type enum('content_quality', 'structure_coherence', 'learning_objectives', 'assessment_alignment', 'overall') NOT NULL,
            score decimal(5,2) NOT NULL DEFAULT 0.00,
            max_score decimal(5,2) NOT NULL DEFAULT 100.00,
            criteria json NOT NULL,
            validation_results json NULL,
            feedback_text text NULL,
            improvement_suggestions json NULL,
            validated_by varchar(50) DEFAULT 'system',
            validation_model varchar(50) NULL,
            confidence_score decimal(3,2) DEFAULT 0.00,
            human_reviewed tinyint(1) DEFAULT 0,
            human_reviewer_id bigint(20) unsigned NULL,
            review_notes text NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_course_id (course_id),
            KEY idx_conversation_id (conversation_id),
            KEY idx_pattern_id (pattern_id),
            KEY idx_metric_type (metric_type),
            KEY idx_score (score),
            KEY idx_validated_by (validated_by),
            KEY idx_human_reviewed (human_reviewed),
            KEY idx_human_reviewer_id (human_reviewer_id),
            KEY idx_created_at (created_at),
            FOREIGN KEY fk_quality_conversation (conversation_id) REFERENCES {$this->tablePrefix}conversations(id) ON DELETE SET NULL,
            FOREIGN KEY fk_quality_pattern (pattern_id) REFERENCES {$this->tablePrefix}course_patterns(id) ON DELETE SET NULL
        ) {$this->getCharsetCollation()};";

        $this->executeQuery($sql, 'Failed to create quality metrics table');
    }

    /**
     * Create lesson drafts table for course preview editing
     *
     * @return void
     * @throws \Exception
     */
    private function createLessonDraftsTable(): void
    {
        $draftTable = new \MemberPressCoursesCopilot\Database\LessonDraftTable();
        $draftTable->create();

        $this->log('Lesson drafts table created successfully');
    }

    /**
     * Drop all plugin tables (for uninstall)
     *
     * @return boolean
     */
    public function dropTables(): bool
    {
        try {
            $tables = [
                'quality_metrics',
                'usage_analytics',
                'course_patterns',
                'templates',
                'conversations',
                'lesson_drafts',
            ];

            foreach ($tables as $table) {
                $tableName = $this->tablePrefix . $table;
                $sql       = "DROP TABLE IF EXISTS {$tableName}";
                $this->wpdb->query($sql);
            }

            // Delete database version option.
            $this->deleteOption('mpcc_db_version');

            $this->log('Database tables dropped successfully');
            return true;
        } catch (\Exception $e) {
            $this->log('Failed to drop database tables: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Check if database needs upgrade and perform if necessary
     *
     * @return void
     */
    public function maybeUpgradeDatabase(): void
    {
        $current_version = $this->getOption('mpcc_db_version', '0.0.0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->upgradeDatabase($current_version);
        }
    }

    /**
     * Upgrade database from one version to another
     *
     * @param  string $from_version
     * @return boolean
     */
    private function upgradeDatabase(string $from_version): bool
    {
        try {
            $this->log("Upgrading database from version {$from_version} to " . self::DB_VERSION);

            // Version-specific migrations.
            if (version_compare($from_version, '1.1.0', '<')) {
                $this->migrateTo110();
            }

            // Update database version.
            $this->updateOption('mpcc_db_version', self::DB_VERSION);

            $this->log('Database upgrade completed successfully');
            return true;
        } catch (\Exception $e) {
            $this->log('Database upgrade failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Migrate database to version 1.1.0
     * Adds missing indexes for foreign key columns
     *
     * @return void
     * @throws \Exception
     */
    private function migrateTo110(): void
    {
        $this->log('Running migration to version 1.1.0 - Adding missing indexes');

        // Add index for created_by in templates table.
        $this->addIndexIfNotExists(
            $this->tablePrefix . 'templates',
            'idx_created_by',
            'created_by'
        );

        // Add index for human_reviewer_id in quality_metrics table.
        $this->addIndexIfNotExists(
            $this->tablePrefix . 'quality_metrics',
            'idx_human_reviewer_id',
            'human_reviewer_id'
        );

        $this->log('Migration to version 1.1.0 completed');
    }

    /**
     * Add an index to a table if it doesn't already exist
     *
     * @param  string $tableName
     * @param  string $index_name
     * @param  string $column_name
     * @return void
     * @throws \Exception
     */
    private function addIndexIfNotExists(string $tableName, string $index_name, string $column_name): void
    {
        // Check if index already exists.
        $index_exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND index_name = %s',
                DB_NAME,
                $tableName,
                $index_name
            )
        );

        if (!$index_exists) {
            $sql = "ALTER TABLE {$tableName} ADD INDEX {$index_name} ({$column_name})";
            $this->executeQuery($sql, "Failed to add index {$index_name} to table {$tableName}");
            $this->log("Added index {$index_name} to table {$tableName}");
        } else {
            $this->log("Index {$index_name} already exists on table {$tableName}");
        }
    }

    /**
     * Seed default data
     *
     * @return boolean
     */
    public function seedDefaultData(): bool
    {
        try {
            $this->seedDefaultTemplates();
            $this->log('Default data seeding completed successfully');
            return true;
        } catch (\Exception $e) {
            $this->log('Failed to seed default data: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Seed default course templates
     *
     * @return void
     */
    private function seedDefaultTemplates(): void
    {
        $default_templates = [
            [
                'name'            => 'Basic Course Structure',
                'description'     => 'A standard course template with introduction, main content, and conclusion',
                'category'        => 'general',
                'type'            => 'course',
                'template_data'   => wp_json_encode([
                    'sections' => [
                        [
                            'title'   => 'Introduction',
                            'lessons' => ['Course Overview', 'Learning Objectives'],
                        ],
                        [
                            'title'   => 'Main Content',
                            'lessons' => ['Core Concepts', 'Practical Examples'],
                        ],
                        [
                            'title'   => 'Conclusion',
                            'lessons' => ['Summary', 'Next Steps'],
                        ],
                    ],
                ]),
                'prompt_template' => 'Create a course about {{topic}} with the following structure...',
                'is_system'       => 1,
                'version'         => '1.0.0',
                'tags'            => wp_json_encode(['basic', 'structure', 'default']),
            ],
            [
                'name'            => 'Skill-Based Learning Path',
                'description'     => 'Template for courses focused on building specific skills progressively',
                'category'        => 'skill-building',
                'type'            => 'course',
                'template_data'   => wp_json_encode([
                    'sections' => [
                        [
                            'title'   => 'Foundations',
                            'lessons' => ['Prerequisites', 'Basic Concepts'],
                        ],
                        [
                            'title'   => 'Core Skills',
                            'lessons' => ['Skill Development', 'Practice Exercises'],
                        ],
                        [
                            'title'   => 'Advanced Applications',
                            'lessons' => ['Real-world Projects', 'Mastery Assessment'],
                        ],
                    ],
                ]),
                'prompt_template' => 'Design a skill-building course for {{skill}} that progresses from beginner to advanced...',
                'is_system'       => 1,
                'version'         => '1.0.0',
                'tags'            => wp_json_encode(['skill-building', 'progressive', 'hands-on']),
            ],
        ];

        foreach ($default_templates as $template) {
            $this->insertTemplate($template);
        }
    }

    // DATA ACCESS LAYER METHODS.

    /**
     * Insert a new conversation
     *
     * @param  array<string, mixed> $data
     * @return integer|false Conversation ID on success, false on failure
     */
    public function insertConversation(array $data): int|false
    {
        $tableName = $this->tablePrefix . 'conversations';

        // Add a small random delay to ensure unique timestamps.
        usleep(wp_rand(1000, 5000)); // Sleep for 1-5 milliseconds.

        $defaults = [
            'state'        => 'active',
            'context'      => 'course_creation',
            'title'        => '',
            'messages'     => wp_json_encode([]),
            'metadata'     => null,
            'step_data'    => null,
            'total_tokens' => 0,
            'total_cost'   => 0.000000,
            'created_at'   => current_time('mysql'),
            'updated_at'   => current_time('mysql'),
        ];

        $data = array_merge($defaults, $data);

        $result = $this->wpdb->insert($tableName, $data);

        return $result !== false ? $this->wpdb->insert_id : false;
    }

    /**
     * Update a conversation
     *
     * @param  integer              $conversationId
     * @param  array<string, mixed> $data
     * @return boolean
     */
    public function updateConversation(int $conversationId, array $data): bool
    {
        $tableName = $this->tablePrefix . 'conversations';

        // Only update timestamp if we're updating actual content, not just metadata or state.
        $contentFields     = ['messages', 'title', 'step_data'];
        $hasContentChanges = false;

        foreach ($contentFields as $field) {
            if (isset($data[$field])) {
                $hasContentChanges = true;
                break;
            }
        }

        // Only update timestamp for content changes.
        if ($hasContentChanges) {
            // Use microtime to ensure unique timestamps.
            // Add a small random delay to ensure uniqueness even for rapid updates.
            usleep(wp_rand(1000, 5000)); // Sleep for 1-5 milliseconds.
            $data['updated_at'] = current_time('mysql');
        }

        $result = $this->wpdb->update(
            $tableName,
            $data,
            ['id' => $conversationId]
        );

        return $result !== false;
    }

    /**
     * Get a conversation by ID
     *
     * @param  integer $conversationId
     * @return object|null
     */
    public function getConversation(int $conversationId): ?object
    {
        $tableName = $this->tablePrefix . 'conversations';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE id = %d",
                $conversationId
            )
        );

        return $result ?: null;
    }

    /**
     * Get conversations by user ID
     *
     * @param  integer $userId
     * @param  integer $limit
     * @param  integer $offset
     * @return array<object>
     */
    public function getConversationsByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $tableName = $this->tablePrefix . 'conversations';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is safe.
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE user_id = %d ORDER BY updated_at DESC, id DESC LIMIT %d OFFSET %d",
                $userId,
                $limit,
                $offset
            )
        );

        return $results ?: [];
    }

    /**
     * Insert a new template
     *
     * @param  array<string, mixed> $data
     * @return integer|false Template ID on success, false on failure
     */
    public function insertTemplate(array $data): int|false
    {
        $tableName = $this->tablePrefix . 'templates';

        $defaults = [
            'category'    => 'general',
            'type'        => 'course',
            'usage_count' => 0,
            'is_active'   => 1,
            'is_system'   => 0,
            'version'     => '1.0.0',
            'created_at'  => current_time('mysql'),
            'updated_at'  => current_time('mysql'),
        ];

        $data = array_merge($defaults, $data);

        $result = $this->wpdb->insert($tableName, $data);

        return $result !== false ? $this->wpdb->insert_id : false;
    }

    /**
     * Get templates by category and type
     *
     * @param  string $category
     * @param  string $type
     * @return array<object>
     */
    public function getTemplates(string $category = '', string $type = ''): array
    {
        $tableName = $this->tablePrefix . 'templates';

        $where_conditions = ['is_active = 1'];
        $params           = [];

        if (!empty($category)) {
            $where_conditions[] = 'category = %s';
            $params[]           = $category;
        }

        if (!empty($type)) {
            $where_conditions[] = 'type = %s';
            $params[]           = $type;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = "SELECT * FROM {$tableName} WHERE {$where_clause} ORDER BY usage_count DESC, name ASC";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, ...$params);
        }

        $results = $this->wpdb->get_results($sql);

        return $results ?: [];
    }

    /**
     * Record usage analytics
     *
     * @param  array<string, mixed> $data
     * @return integer|false Analytics ID on success, false on failure
     */
    public function recordUsage(array $data): int|false
    {
        $tableName = $this->tablePrefix . 'usage_analytics';

        $defaults = [
            'tokens_used' => 0,
            'cost_amount' => 0.000000,
            'success'     => 1,
            'created_at'  => current_time('mysql'),
        ];

        $data = array_merge($defaults, $data);

        $result = $this->wpdb->insert($tableName, $data);

        return $result !== false ? $this->wpdb->insert_id : false;
    }

    // QUERY BUILDERS FOR ANALYTICS AND REPORTING.

    /**
     * Get usage analytics for a date range
     *
     * @param  string       $start_date
     * @param  string       $end_date
     * @param  integer|null $userId
     * @return array<object>
     */
    public function getUsageAnalytics(string $start_date, string $end_date, ?int $userId = null): array
    {
        $tableName = $this->tablePrefix . 'usage_analytics';

        $where_conditions = ['created_at BETWEEN %s AND %s'];
        $params           = [$start_date, $end_date];

        if ($userId !== null) {
            $where_conditions[] = 'user_id = %d';
            $params[]           = $userId;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_requests,
                    SUM(tokens_used) as total_tokens,
                    SUM(cost_amount) as total_cost,
                    AVG(response_time_ms) as avg_response_time,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_requests
                FROM {$tableName} 
                WHERE {$where_clause}
                GROUP BY DATE(created_at)
                ORDER BY date ASC",
                ...$params
            )
        );

        return $results ?: [];
    }

    /**
     * Get quality metrics summary
     *
     * @param  integer|null $courseId
     * @return object|null
     */
    public function getQualityMetricsSummary(?int $courseId = null): ?object
    {
        $tableName = $this->tablePrefix . 'quality_metrics';

        $where_clause = $courseId ? 'WHERE course_id = %d' : '';
        $params       = $courseId ? [$courseId] : [];

        $sql = "SELECT 
                    AVG(score) as average_score,
                    MIN(score) as min_score,
                    MAX(score) as max_score,
                    COUNT(*) as total_assessments,
                    SUM(CASE WHEN human_reviewed = 1 THEN 1 ELSE 0 END) as human_reviewed_count
                FROM {$tableName} {$where_clause}";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, ...$params);
        }

        $result = $this->wpdb->get_row($sql);

        return $result ?: null;
    }

    /**
     * Execute a database query with error handling
     *
     * @param  string $sql
     * @param  string $error_message
     * @return void
     * @throws \Exception
     */
    private function executeQuery(string $sql, string $error_message): void
    {
        $result = $this->wpdb->query($sql);

        if ($result === false) {
            throw new \Exception($error_message . ': ' . $this->wpdb->last_error);
        }
    }

    /**
     * Get charset and collation for table creation
     *
     * @return string
     */
    private function getCharsetCollation(): string
    {
        $charset_collate = '';

        if (!empty($this->wpdb->charset)) {
            $charset_collate = "DEFAULT CHARACTER SET {$this->wpdb->charset}";
        }

        if (!empty($this->wpdb->collate)) {
            $charset_collate .= " COLLATE {$this->wpdb->collate}";
        }

        return $charset_collate;
    }

    /**
     * Get conversation by session ID
     *
     * @param  string $sessionId
     * @return object|null
     */
    public function getConversationBySessionId(string $sessionId): ?object
    {
        $tableName = $this->tablePrefix . 'conversations';

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE session_id = %s",
                $sessionId
            )
        );

        return $result ?: null;
    }

    /**
     * Get multiple conversations by session IDs
     * Batch loading to avoid N+1 queries
     *
     * @param  array $sessionIds Array of session IDs
     * @return array<string, object> Array keyed by session_id
     */
    public function getConversationsBySessionIds(array $sessionIds): array
    {
        if (empty($sessionIds)) {
            return [];
        }

        $tableName = $this->tablePrefix . 'conversations';

        // Prepare placeholders for the IN clause.
        $placeholders = implode(',', array_fill(0, count($sessionIds), '%s'));

        // Build and prepare the query.
        $sql          = "SELECT * FROM {$tableName} WHERE session_id IN ({$placeholders})";
        $prepared_sql = $this->wpdb->prepare($sql, ...$sessionIds);

        $results = $this->wpdb->get_results($prepared_sql);

        // Key results by session_id for easy lookup.
        $conversations = [];
        foreach ($results as $row) {
            $conversations[$row->session_id] = $row;
        }

        return $conversations;
    }

    /**
     * Get multiple conversations by IDs
     * Batch loading to avoid N+1 queries
     *
     * @param  array $conversationIds Array of conversation IDs
     * @return array<int, object> Array keyed by conversation ID
     */
    public function getConversationsByIds(array $conversationIds): array
    {
        if (empty($conversationIds)) {
            return [];
        }

        $tableName = $this->tablePrefix . 'conversations';

        // Prepare placeholders for the IN clause.
        $placeholders = implode(',', array_fill(0, count($conversationIds), '%d'));

        // Build and prepare the query.
        $sql          = "SELECT * FROM {$tableName} WHERE id IN ({$placeholders})";
        $prepared_sql = $this->wpdb->prepare($sql, ...$conversationIds);

        $results = $this->wpdb->get_results($prepared_sql);

        // Key results by ID for easy lookup.
        $conversations = [];
        foreach ($results as $row) {
            $conversations[$row->id] = $row;
        }

        return $conversations;
    }

    /**
     * Manually add missing indexes to existing tables
     * This can be called to immediately update indexes without waiting for version check
     *
     * @return boolean True if successful, false if any errors occurred
     */
    public function addMissingIndexes(): bool
    {
        try {
            $this->log('Manually adding missing indexes to database tables');

            // Add index for created_by in templates table.
            $this->addIndexIfNotExists(
                $this->tablePrefix . 'templates',
                'idx_created_by',
                'created_by'
            );

            // Add index for human_reviewer_id in quality_metrics table.
            $this->addIndexIfNotExists(
                $this->tablePrefix . 'quality_metrics',
                'idx_human_reviewer_id',
                'human_reviewer_id'
            );

            $this->log('Successfully added missing indexes');
            return true;
        } catch (\Exception $e) {
            $this->log('Failed to add missing indexes: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get list of missing indexes without applying them
     *
     * @return array List of missing indexes
     */
    public function getMissingIndexes(): array
    {
        $missing = [];

        // Check templates table indexes.
        if (!$this->indexExists($this->tablePrefix . 'templates', 'idx_created_by')) {
            $missing[] = [
                'table'      => $this->tablePrefix . 'templates',
                'index_name' => 'idx_created_by',
                'column'     => 'created_by',
            ];
        }

        // Check quality_metrics table indexes.
        if (!$this->indexExists($this->tablePrefix . 'quality_metrics', 'idx_human_reviewer_id')) {
            $missing[] = [
                'table'      => $this->tablePrefix . 'quality_metrics',
                'index_name' => 'idx_human_reviewer_id',
                'column'     => 'human_reviewer_id',
            ];
        }

        return $missing;
    }

    /**
     * Check if an index exists on a table
     *
     * @param  string $tableName
     * @param  string $index_name
     * @return boolean
     */
    private function indexExists(string $tableName, string $index_name): bool
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND index_name = %s',
                DB_NAME,
                $tableName,
                $index_name
            )
        );

        return (bool)$result;
    }

    /**
     * Get status of all plugin tables
     *
     * @return array Table name => exists (bool)
     */
    public function getTableStatus(): array
    {
        $tables = [
            'conversations',
            'templates',
            'course_patterns',
            'usage_analytics',
            'quality_metrics',
            'lesson_drafts',
        ];

        $status = [];

        foreach ($tables as $table) {
            $tableName = $this->tablePrefix . $table;
            $exists    = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    'SELECT COUNT(1) FROM INFORMATION_SCHEMA.TABLES 
                    WHERE table_schema = %s 
                    AND table_name = %s',
                    DB_NAME,
                    $tableName
                )
            );

            $status[$tableName] = (bool)$exists;
        }

        return $status;
    }

    /**
     * Reinstall all tables (drop and recreate)
     *
     * @return boolean
     */
    public function reinstallTables(): bool
    {
        try {
            // First drop all tables.
            $this->dropTables();

            // Then install fresh tables.
            return $this->installTables();
        } catch (\Exception $e) {
            $this->log('Failed to reinstall tables: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get pending migrations
     *
     * @param  string|null $target_version Target version to migrate to
     * @return array List of pending migrations
     */
    public function getPendingMigrations(?string $target_version = null): array
    {
        $current_version = $this->getOption('mpcc_db_version', '0.0.0');
        $target          = $target_version ?: self::DB_VERSION;

        $migrations = [];

        // Define all migrations.
        $all_migrations = [
            '1.1.0' => [
                'version'     => '1.1.0',
                'description' => 'Add missing indexes for foreign key columns',
                'method'      => 'migrateTo110',
            ],
        ];

        // Get only pending migrations.
        foreach ($all_migrations as $version => $migration) {
            if (
                version_compare($current_version, $version, '<') &&
                version_compare($version, $target, '<=')
            ) {
                $migrations[] = $migration;
            }
        }

        return $migrations;
    }

    /**
     * Run a specific migration
     *
     * @param  string $version
     * @return boolean
     */
    public function runMigration(string $version): bool
    {
        try {
            switch ($version) {
                case '1.1.0':
                    $this->migrateTo110();
                    break;
                default:
                    throw new \Exception("Unknown migration version: {$version}");
            }

            // Update database version.
            $this->updateOption('mpcc_db_version', $version);

            return true;
        } catch (\Exception $e) {
            $this->log('Migration failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get active session count for user
     *
     * @param  integer $userId
     * @return integer
     */
    public function getActiveSessionCount(int $userId): int
    {
        $tableName = $this->tablePrefix . 'conversations';

        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE user_id = %d AND state = 'active'",
                $userId
            )
        );

        return (int)($result ?: 0);
    }

    /**
     * Get oldest active session for user
     *
     * @param  integer $userId
     * @return object|null
     */
    public function getOldestActiveSession(int $userId): ?object
    {
        $tableName = $this->tablePrefix . 'conversations';

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE user_id = %d AND state = 'active' ORDER BY created_at ASC LIMIT 1",
                $userId
            )
        );

        return $result ?: null;
    }

    /**
     * Get expired sessions
     *
     * @param  integer $expired_before_timestamp
     * @return array<object>
     */
    public function getExpiredSessions(int $expired_before_timestamp): array
    {
        $tableName = $this->tablePrefix . 'conversations';

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE updated_at < %s AND state IN ('active', 'paused')",
                date('Y-m-d H:i:s', $expired_before_timestamp)
            )
        );

        return $results ?: [];
    }

    /**
     * Delete a conversation
     *
     * @param  integer $conversationId
     * @return boolean
     */
    public function deleteConversation(int $conversationId): bool
    {
        $tableName = $this->tablePrefix . 'conversations';

        $result = $this->wpdb->delete(
            $tableName,
            ['id' => $conversationId],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get active sessions that need saving
     * Returns session IDs for active sessions updated more than specified minutes ago
     *
     * @param  integer $minutes_since_update Sessions not updated in this many minutes
     * @param  integer $limit                Maximum number of sessions to return
     * @return array<string> Array of session IDs
     */
    public function getActiveSessionsNeedingSave(int $minutes_since_update = 5, int $limit = 100): array
    {
        $tableName = $this->tablePrefix . 'conversations';

        // Calculate the timestamp for comparison.
        $cutoff_time = date('Y-m-d H:i:s', time() - ($minutes_since_update * 60));

        $results = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT session_id FROM {$tableName} 
                WHERE state = 'active' 
                AND updated_at < %s 
                ORDER BY updated_at ASC 
                LIMIT %d",
                $cutoff_time,
                $limit
            )
        );

        return $results ?: [];
    }

    /**
     * Batch update conversations to abandoned state
     * Avoids N+1 queries by updating all expired sessions in a single query
     *
     * @param  array  $conversationIds Array of conversation IDs to update
     * @param  string $abandoned_at    Timestamp when sessions were abandoned
     * @return integer Number of rows updated
     */
    public function batchAbandonConversations(array $conversationIds, string $abandoned_at): int
    {
        if (empty($conversationIds)) {
            return 0;
        }

        $tableName = $this->tablePrefix . 'conversations';

        // Prepare placeholders for the IN clause.
        $placeholders = implode(',', array_fill(0, count($conversationIds), '%d'));

        // Build the update query.
        $sql = "UPDATE {$tableName} 
                SET state = 'abandoned',
                    metadata = JSON_SET(
                        COALESCE(metadata, '{}'),
                        '$.auto_abandoned_at',
                        %s
                    ),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id IN ({$placeholders})
                AND state = 'active'";

        // Prepare the query with all parameters.
        $params       = array_merge([$abandoned_at], $conversationIds);
        $prepared_sql = $this->wpdb->prepare($sql, ...$params);

        $result = $this->wpdb->query($prepared_sql);

        return $result !== false ? $result : 0;
    }

    /**
     * Save a conversation to the database (IDatabaseService interface)
     *
     * @param  integer $userId   User ID
     * @param  string  $message  User message
     * @param  string  $response AI response
     * @return integer|false Conversation ID on success, false on failure
     */
    public function saveConversation(int $userId, string $message, string $response)
    {
        // Create a new conversation with the message and response.
        $data = [
            'user_id'    => $userId,
            'session_id' => wp_generate_password(32, false),
            'messages'   => wp_json_encode([
                [
                    'role'    => 'user',
                    'content' => $message,
                ],
                [
                    'role'    => 'assistant',
                    'content' => $response,
                ],
            ]),
        ];

        return $this->insertConversation($data);
    }

    /**
     * Get conversation history for a user (IDatabaseService interface)
     *
     * @param  integer $userId User ID
     * @param  integer $limit  Number of messages to retrieve
     * @return array Conversation history
     */
    public function getConversationHistory(int $userId, int $limit = 50): array
    {
        $conversations = $this->getConversationsByUser($userId, $limit);

        $history = [];
        foreach ($conversations as $conversation) {
            $messages  = json_decode($conversation->messages, true) ?: [];
            $history[] = [
                'id'         => $conversation->id,
                'session_id' => $conversation->session_id,
                'messages'   => $messages,
                'created_at' => $conversation->created_at,
                'updated_at' => $conversation->updated_at,
            ];
        }

        return $history;
    }
}
