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
     * @var wpdb WordPress database instance for executing SQL queries
     */
    private wpdb $wpdb;

    /**
     * @var string Table prefix for plugin-specific database tables (e.g., 'wp_mpcc_')
     */
    private string $tablePrefix;

    /**
     * Database constructor
     *
     * @since 1.0.0
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
     * @since  1.0.0
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
     * @since  1.0.0
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
     * @since  1.0.0
     * @return void
     * @throws \Exception If table creation fails
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
     * @since  1.0.0
     * @return void
     * @throws \Exception If table creation fails
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
     * @since  1.0.0
     * @return void
     * @throws \Exception If table creation fails
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
     * @since  1.0.0
     * @return void
     * @throws \Exception If table creation fails
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
            FOREIGN KEY fk_conversation (conversation_id)
                REFERENCES {$this->tablePrefix}conversations(id) ON DELETE SET NULL
        ) {$this->getCharsetCollation()};";

        $this->executeQuery($sql, 'Failed to create usage analytics table');
    }

    /**
     * Create quality metrics table for course validation results
     *
     * @since  1.0.0
     * @return void
     * @throws \Exception If table creation fails
     */
    private function createQualityMetricsTable(): void
    {
        $tableName = $this->tablePrefix . 'quality_metrics';

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            course_id bigint(20) unsigned NULL,
            conversation_id bigint(20) unsigned NULL,
            pattern_id bigint(20) unsigned NULL,
            metric_type enum('content_quality', 'structure_coherence', 'learning_objectives',
                            'assessment_alignment', 'overall') NOT NULL,
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
            FOREIGN KEY fk_quality_conversation (conversation_id)
                REFERENCES {$this->tablePrefix}conversations(id) ON DELETE SET NULL,
            FOREIGN KEY fk_quality_pattern (pattern_id)
                REFERENCES {$this->tablePrefix}course_patterns(id) ON DELETE SET NULL
        ) {$this->getCharsetCollation()};";

        $this->executeQuery($sql, 'Failed to create quality metrics table');
    }

    /**
     * Create lesson drafts table for course preview editing
     *
     * @since  1.0.0
     * @return void
     * @throws \Exception If table creation fails
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
     * @since  1.0.0
     * @return boolean True on success, false on failure
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
     * @since  1.0.0
     * @return void
     */
    public function maybeUpgradeDatabase(): void
    {
        $currentVersion = $this->getOption('mpcc_db_version', '0.0.0');

        if (version_compare($currentVersion, self::DB_VERSION, '<')) {
            $this->upgradeDatabase($currentVersion);
        }
    }

    /**
     * Upgrade database from one version to another
     *
     * @since  1.0.0
     * @param  string $fromVersion The previous database version to upgrade from.
     * @return boolean True on success, false on failure
     */
    private function upgradeDatabase(string $fromVersion): bool
    {
        try {
            $this->log("Upgrading database from version {$fromVersion} to " . self::DB_VERSION);

            // Version-specific migrations.
            if (version_compare($fromVersion, '1.1.0', '<')) {
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
     * @since  1.0.0
     * @return void
     * @throws \Exception If migration fails
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
     * @since  1.0.0
     * @param  string $tableName   The name of the database table.
     * @param  string $indexName  The name of the index to create.
     * @param  string $columnName The column name to index.
     * @return void
     * @throws \Exception If adding index fails
     */
    private function addIndexIfNotExists(string $tableName, string $indexName, string $columnName): void
    {
        // Check if index already exists.
        $indexExists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND index_name = %s',
                DB_NAME,
                $tableName,
                $indexName
            )
        );

        if (!$indexExists) {
            $sql = "ALTER TABLE {$tableName} ADD INDEX {$indexName} ({$columnName})";
            $this->executeQuery($sql, "Failed to add index {$indexName} to table {$tableName}");
            $this->log("Added index {$indexName} to table {$tableName}");
        } else {
            $this->log("Index {$indexName} already exists on table {$tableName}");
        }
    }

    /**
     * Seed default data
     *
     * @since  1.0.0
     * @return boolean True on success, false on failure
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
     * @since  1.0.0
     * @return void
     */
    private function seedDefaultTemplates(): void
    {
        $defaultTemplates = [
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
                'prompt_template' => 'Design a skill-building course for {{skill}} '
                                  . 'that progresses from beginner to advanced...',
                'is_system'       => 1,
                'version'         => '1.0.0',
                'tags'            => wp_json_encode(['skill-building', 'progressive', 'hands-on']),
            ],
        ];

        foreach ($defaultTemplates as $template) {
            $this->insertTemplate($template);
        }
    }

    // DATA ACCESS LAYER METHODS.

    /**
     * Insert a new conversation
     *
     * @since  1.0.0
     * @param  array<string, mixed> $data The conversation data to insert.
     *                                    Accepted keys:
     *                                    - user_id (int, required): User ID
     *                                    - session_id (string, required): Session identifier
     *                                    - state (string): 'active'|'paused'|'completed'|'error'
     *                                    - context (string): Context like 'course_creation'
     *                                    - course_id (int|null): Related course ID
     *                                    - title (string): Conversation title
     *                                    - messages (string): JSON encoded messages array
     *                                    - metadata (string|null): JSON metadata
     *                                    - step_data (string|null): JSON step data
     *                                    - total_tokens (int): Total tokens used
     *                                    - total_cost (float): Total cost
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
     * @since  1.0.0
     * @param  integer              $conversationId The ID of the conversation to update.
     * @param  array<string, mixed> $data           The conversation data to update.
     *                                              See insertConversation for accepted keys.
     * @return boolean True on success, false on failure
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
     * @since  1.0.0
     * @param  integer $conversationId The ID of the conversation to retrieve.
     * @return object|null Conversation object on success, null if not found
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
     * @since  1.0.0
     * @param  integer $userId The ID of the user whose conversations to retrieve.
     * @param  integer $limit  The maximum number of conversations to retrieve.
     * @param  integer $offset The number of conversations to skip.
     * @return array<object> Array of conversation objects
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
     * @since  1.0.0
     * @param  array<string, mixed> $data The template data to insert.
     *                                    Accepted keys:
     *                                    - name (string, required): Template name
     *                                    - description (string|null): Template description
     *                                    - category (string): Template category
     *                                    - type (string): 'course'|'section'|'lesson'|'assessment'
     *                                    - template_data (string, required): JSON encoded template data
     *                                    - prompt_template (string|null): Prompt template text
     *                                    - variables (string|null): JSON encoded variables
     *                                    - usage_count (int): Usage count
     *                                    - is_active (int): 1 for active, 0 for inactive
     *                                    - is_system (int): 1 for system template, 0 for user template
     *                                    - created_by (int|null): Creator user ID
     *                                    - version (string): Template version
     *                                    - tags (string|null): JSON encoded tags array
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
     * @since  1.0.0
     * @param  string $category The template category to filter by.
     * @param  string $type     The template type to filter by.
     * @return array<object> Array of template objects
     */
    public function getTemplates(string $category = '', string $type = ''): array
    {
        $tableName = $this->tablePrefix . 'templates';

        $whereConditions = ['is_active = 1'];
        $params           = [];

        if (!empty($category)) {
            $whereConditions[] = 'category = %s';
            $params[]           = $category;
        }

        if (!empty($type)) {
            $whereConditions[] = 'type = %s';
            $params[]           = $type;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT * FROM {$tableName} WHERE {$whereClause} ORDER BY usage_count DESC, name ASC";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, ...$params);
        }

        $results = $this->wpdb->get_results($sql);

        return $results ?: [];
    }

    /**
     * Record usage analytics
     *
     * @since  1.0.0
     * @param  array<string, mixed> $data The analytics data to record.
     *                                    Accepted keys:
     *                                    - user_id (int, required): User ID
     *                                    - conversation_id (int|null): Related conversation ID
     *                                    - action_type (string, required): Type of action performed
     *                                    - api_endpoint (string|null): API endpoint used
     *                                    - model_used (string|null): AI model used
     *                                    - tokens_used (int): Number of tokens used
     *                                    - cost_amount (float): Cost of the operation
     *                                    - response_time_ms (int|null): Response time in milliseconds
     *                                    - success (int): 1 for success, 0 for failure
     *                                    - error_message (string|null): Error message if failed
     *                                    - request_data (string|null): JSON encoded request data
     *                                    - response_data (string|null): JSON encoded response data
     *                                    - user_agent (string|null): User agent string
     *                                    - ip_address (string|null): IP address
     *                                    - session_data (string|null): JSON encoded session data
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
     * @since  1.0.0
     * @param  string       $startDate The start date for analytics range (Y-m-d format).
     * @param  string       $endDate   The end date for analytics range (Y-m-d format).
     * @param  integer|null $userId     Optional user ID to filter analytics by.
     * @return array<object> Array of analytics summary objects with keys:
     *                       - date (string): Date (Y-m-d)
     *                       - total_requests (int): Total number of requests
     *                       - total_tokens (int): Total tokens used
     *                       - total_cost (float): Total cost
     *                       - avg_response_time (float): Average response time
     *                       - successful_requests (int): Number of successful requests
     */
    public function getUsageAnalytics(string $startDate, string $endDate, ?int $userId = null): array
    {
        $tableName = $this->tablePrefix . 'usage_analytics';

        $whereConditions = ['created_at BETWEEN %s AND %s'];
        $params           = [$startDate, $endDate];

        if ($userId !== null) {
            $whereConditions[] = 'user_id = %d';
            $params[]           = $userId;
        }

        $whereClause = implode(' AND ', $whereConditions);

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
                WHERE {$whereClause}
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
     * @since  1.0.0
     * @param  integer|null $courseId The ID of the course to get metrics for.
     * @return object|null Quality metrics summary object with keys:
     *                     - average_score (float): Average quality score
     *                     - min_score (float): Minimum quality score
     *                     - max_score (float): Maximum quality score
     *                     - total_assessments (int): Total number of assessments
     *                     - human_reviewed_count (int): Number of human-reviewed assessments
     */
    public function getQualityMetricsSummary(?int $courseId = null): ?object
    {
        $tableName = $this->tablePrefix . 'quality_metrics';

        $whereClause = $courseId ? 'WHERE course_id = %d' : '';
        $params       = $courseId ? [$courseId] : [];

        $sql = "SELECT 
                    AVG(score) as average_score,
                    MIN(score) as min_score,
                    MAX(score) as max_score,
                    COUNT(*) as total_assessments,
                    SUM(CASE WHEN human_reviewed = 1 THEN 1 ELSE 0 END) as human_reviewed_count
                FROM {$tableName} {$whereClause}";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, ...$params);
        }

        $result = $this->wpdb->get_row($sql);

        return $result ?: null;
    }

    /**
     * Execute a database query with error handling
     *
     * @since  1.0.0
     * @param  string $sql           The SQL query to execute.
     * @param  string $errorMessage The error message to log on failure.
     * @return void
     * @throws \Exception If query execution fails
     */
    private function executeQuery(string $sql, string $errorMessage): void
    {
        $result = $this->wpdb->query($sql);

        if ($result === false) {
            throw new \Exception($errorMessage . ': ' . $this->wpdb->last_error);
        }
    }

    /**
     * Get charset and collation for table creation
     *
     * @since  1.0.0
     * @return string Charset and collation string for SQL queries
     */
    private function getCharsetCollation(): string
    {
        $charsetCollate = '';

        if (!empty($this->wpdb->charset)) {
            $charsetCollate = "DEFAULT CHARACTER SET {$this->wpdb->charset}";
        }

        if (!empty($this->wpdb->collate)) {
            $charsetCollate .= " COLLATE {$this->wpdb->collate}";
        }

        return $charsetCollate;
    }

    /**
     * Get conversation by session ID
     *
     * @since  1.0.0
     * @param  string $sessionId The unique session identifier.
     * @return object|null Conversation object on success, null if not found
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
     * @since  1.0.0
     * @param  array<string> $sessionIds Array of session IDs
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
        $preparedSql = $this->wpdb->prepare($sql, ...$sessionIds);

        $results = $this->wpdb->get_results($preparedSql);

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
     * @since  1.0.0
     * @param  array<int> $conversationIds Array of conversation IDs
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
        $preparedSql = $this->wpdb->prepare($sql, ...$conversationIds);

        $results = $this->wpdb->get_results($preparedSql);

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
     * @since  1.0.0
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
     * @since  1.0.0
     * @return array<array{table: string, index_name: string, column: string}> List of missing indexes
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
     * @since  1.0.0
     * @param  string $tableName  The name of the database table.
     * @param  string $indexName The name of the index to check.
     * @return boolean True if index exists, false otherwise
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SELECT COUNT(1) FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND index_name = %s',
                DB_NAME,
                $tableName,
                $indexName
            )
        );

        return (bool)$result;
    }

    /**
     * Get status of all plugin tables
     *
     * @since  1.0.0
     * @return array<string, bool> Table name => exists (bool)
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
     * @since  1.0.0
     * @return boolean True on success, false on failure
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
     * @since  1.0.0
     * @param  string|null $targetVersion Target version to migrate to
     * @return array<array{version: string, description: string, method: string}> List of pending migrations
     */
    public function getPendingMigrations(?string $targetVersion = null): array
    {
        $currentVersion = $this->getOption('mpcc_db_version', '0.0.0');
        $target          = $targetVersion ?: self::DB_VERSION;

        $migrations = [];

        // Define all migrations.
        $allMigrations = [
            '1.1.0' => [
                'version'     => '1.1.0',
                'description' => 'Add missing indexes for foreign key columns',
                'method'      => 'migrateTo110',
            ],
        ];

        // Get only pending migrations.
        foreach ($allMigrations as $version => $migration) {
            if (
                version_compare($currentVersion, $version, '<') &&
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
     * @since  1.0.0
     * @param  string $version The database version to migrate to.
     * @return boolean True on success, false on failure
     * @throws \Exception If migration version is unknown
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
     * @since  1.0.0
     * @param  integer $userId The ID of the user to count active sessions for.
     * @return integer Number of active sessions
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
     * @since  1.0.0
     * @param  integer $userId The ID of the user to get the oldest active session for.
     * @return object|null Conversation object on success, null if no active sessions
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
     * @since  1.0.0
     * @param  integer $expiredBeforeTimestamp The timestamp before which sessions are considered expired.
     * @return array<object> Array of expired conversation objects
     */
    public function getExpiredSessions(int $expiredBeforeTimestamp): array
    {
        $tableName = $this->tablePrefix . 'conversations';

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE updated_at < %s AND state IN ('active', 'paused')",
                date('Y-m-d H:i:s', $expiredBeforeTimestamp)
            )
        );

        return $results ?: [];
    }

    /**
     * Delete a conversation
     *
     * @since  1.0.0
     * @param  integer $conversationId The ID of the conversation to delete.
     * @return boolean True on success, false on failure
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
     * @since  1.0.0
     * @param  integer $minutesSinceUpdate Sessions not updated in this many minutes
     * @param  integer $limit                Maximum number of sessions to return
     * @return array<string> Array of session IDs
     */
    public function getActiveSessionsNeedingSave(int $minutesSinceUpdate = 5, int $limit = 100): array
    {
        $tableName = $this->tablePrefix . 'conversations';

        // Calculate the timestamp for comparison.
        $cutoffTime = date('Y-m-d H:i:s', time() - ($minutesSinceUpdate * 60));

        $results = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT session_id FROM {$tableName} 
                WHERE state = 'active' 
                AND updated_at < %s 
                ORDER BY updated_at ASC 
                LIMIT %d",
                $cutoffTime,
                $limit
            )
        );

        return $results ?: [];
    }

    /**
     * Batch update conversations to abandoned state
     * Avoids N+1 queries by updating all expired sessions in a single query
     *
     * @since  1.0.0
     * @param  array<int> $conversationIds Array of conversation IDs to update
     * @param  string     $abandonedAt    Timestamp when sessions were abandoned (Y-m-d H:i:s format)
     * @return integer Number of rows updated
     */
    public function batchAbandonConversations(array $conversationIds, string $abandonedAt): int
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
        $params       = array_merge([$abandonedAt], $conversationIds);
        $preparedSql = $this->wpdb->prepare($sql, ...$params);

        $result = $this->wpdb->query($preparedSql);

        return $result !== false ? $result : 0;
    }

    /**
     * Save a conversation to the database (IDatabaseService interface)
     *
     * @since  1.0.0
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
     * @since  1.0.0
     * @param  integer $userId User ID
     * @param  integer $limit  Number of messages to retrieve
     * @return array<array{id: int, session_id: string, messages: array, created_at: string, updated_at: string}> Conversation history
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
