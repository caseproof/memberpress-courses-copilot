<?php

namespace MemberPressCoursesCopilot\Database;

class LessonDraftTable
{
    private $table_name;
    private $charset_collate;

    public function __construct()
    {
        global $wpdb;
        $this->table_name      = $wpdb->prefix . 'mpcc_lesson_drafts';
        $this->charset_collate = $wpdb->get_charset_collate();
    }

    /**
     * Create the lesson drafts table
     */
    public function create()
    {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(255) NOT NULL,
            section_id VARCHAR(255) NOT NULL,
            lesson_id VARCHAR(255) NOT NULL,
            content LONGTEXT,
            order_index INT(11) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_session (session_id),
            INDEX idx_section_lesson (section_id, lesson_id),
            UNIQUE KEY unique_lesson (session_id, section_id, lesson_id)
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Log table creation
        if (class_exists('\MemberPressCoursesCopilot\Services\Logger')) {
            $logger = new \MemberPressCoursesCopilot\Services\Logger();
            $logger->info('Lesson drafts table created or verified', ['table' => $this->table_name]);
        }
    }

    /**
     * Drop the lesson drafts table
     */
    public function drop()
    {
        global $wpdb;
        $sql = "DROP TABLE IF EXISTS {$this->table_name}";
        $wpdb->query($sql);
    }

    /**
     * Get the table name
     */
    public function getTableName()
    {
        return $this->table_name;
    }

    /**
     * Clean up old drafts (older than 30 days)
     */
    public function cleanupOldDrafts($days = 30)
    {
        global $wpdb;

        $sql = $wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );

        $deleted = $wpdb->query($sql);

        if (class_exists('\MemberPressCoursesCopilot\Services\Logger')) {
            $logger = new \MemberPressCoursesCopilot\Services\Logger();
            $logger->info('Cleaned up old drafts', [
                'deleted' => $deleted,
                'days'    => $days,
            ]);
        }

        return $deleted;
    }
}
