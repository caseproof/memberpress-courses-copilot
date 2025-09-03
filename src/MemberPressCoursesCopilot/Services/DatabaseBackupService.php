<?php

namespace MemberPressCoursesCopilot\Services;

use wpdb;

/**
 * Database Backup Service
 *
 * Handles database backup and restore operations for MemberPress Courses Copilot
 *
 * @package MemberPressCoursesCopilot\Services
 * @since   1.1.0
 */
class DatabaseBackupService extends BaseService
{
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
     * Backup directory path
     *
     * @var string
     */
    private string $backup_dir;

    /**
     * Maximum number of backups to keep
     *
     * @var int
     */
    private const MAX_BACKUPS = 50;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        global $wpdb;
        $this->wpdb         = $wpdb;
        $this->tablePrefix = $wpdb->prefix . 'mpcc_';

        // Set up backup directory
        $upload_dir       = wp_upload_dir();
        $this->backup_dir = $upload_dir['basedir'] . '/mpcc-backups';

        // Ensure backup directory exists and is protected
        $this->ensureBackupDirectory();
    }

    /**
     * Create a backup of all plugin tables
     *
     * @param  string $type        Backup type (manual, migration, etc.)
     * @param  string $description Optional description
     * @return string|false Backup ID on success, false on failure
     */
    public function createBackup(string $type = 'manual', string $description = ''): string|false
    {
        try {
            $backup_id   = $this->generateBackupId();
            $backup_file = $this->backup_dir . '/' . $backup_id . '.sql';

            $this->log('Creating database backup: ' . $backup_id);

            // Get all plugin tables
            $tables = $this->getPluginTables();

            if (empty($tables)) {
                $this->log('No tables found to backup', 'warning');
                return false;
            }

            // Create backup file
            $handle = fopen($backup_file, 'w');
            if (!$handle) {
                throw new \Exception('Failed to create backup file');
            }

            // Write backup metadata
            fwrite($handle, "-- MemberPress Courses Copilot Database Backup\n");
            fwrite($handle, "-- Backup ID: {$backup_id}\n");
            fwrite($handle, '-- Date: ' . current_time('mysql') . "\n");
            fwrite($handle, "-- Type: {$type}\n");
            fwrite($handle, "-- Description: {$description}\n");
            fwrite($handle, '-- WordPress Version: ' . get_bloginfo('version') . "\n");
            fwrite($handle, '-- Plugin Version: ' . $this->getOption('mpcc_version', 'unknown') . "\n");
            fwrite($handle, '-- Tables: ' . implode(', ', $tables) . "\n");
            fwrite($handle, "\n");

            // Backup each table
            foreach ($tables as $table) {
                $this->backupTable($table, $handle);
            }

            fclose($handle);

            // Create metadata file
            $this->saveBackupMetadata($backup_id, [
                'type'        => $type,
                'description' => $description,
                'tables'      => $tables,
                'size'        => filesize($backup_file),
                'created_at'  => current_time('mysql'),
            ]);

            $this->log('Backup created successfully: ' . $backup_id);

            // Clean up old backups
            $this->cleanupOldBackups(30, 5);

            return $backup_id;
        } catch (\Exception $e) {
            $this->log('Failed to create backup: ' . $e->getMessage(), 'error');

            // Clean up partial backup file
            if (isset($backup_file) && file_exists($backup_file)) {
                @unlink($backup_file);
            }

            return false;
        }
    }

    /**
     * Restore from a backup
     *
     * @param  string $backup_id
     * @return boolean
     */
    public function restoreBackup(string $backup_id): bool
    {
        try {
            $backup_file = $this->backup_dir . '/' . $backup_id . '.sql';

            if (!file_exists($backup_file)) {
                throw new \Exception('Backup file not found: ' . $backup_id);
            }

            $this->log('Restoring from backup: ' . $backup_id);

            // Read backup file
            $sql = file_get_contents($backup_file);
            if ($sql === false) {
                throw new \Exception('Failed to read backup file');
            }

            // Split SQL into individual queries
            $queries = $this->splitSqlFile($sql);

            // Begin transaction
            $this->wpdb->query('START TRANSACTION');

            try {
                // Execute each query
                foreach ($queries as $query) {
                    $query = trim($query);
                    if (empty($query) || strpos($query, '--') === 0) {
                        continue;
                    }

                    $result = $this->wpdb->query($query);
                    if ($result === false) {
                        throw new \Exception('Query failed: ' . $this->wpdb->last_error);
                    }
                }

                // Commit transaction
                $this->wpdb->query('COMMIT');

                $this->log('Backup restored successfully: ' . $backup_id);
                return true;
            } catch (\Exception $e) {
                // Rollback on error
                $this->wpdb->query('ROLLBACK');
                throw $e;
            }
        } catch (\Exception $e) {
            $this->log('Failed to restore backup: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Get backup information
     *
     * @param  string $backup_id
     * @return array|null
     */
    public function getBackupInfo(string $backup_id): ?array
    {
        $metadata_file = $this->backup_dir . '/' . $backup_id . '.json';

        if (!file_exists($metadata_file)) {
            return null;
        }

        $metadata = json_decode(file_get_contents($metadata_file), true);

        if (!$metadata) {
            return null;
        }

        $backup_file = $this->backup_dir . '/' . $backup_id . '.sql';

        return [
            'id'          => $backup_id,
            'file'        => $backup_file,
            'date'        => $metadata['created_at'],
            'type'        => $metadata['type'],
            'description' => $metadata['description'],
            'tables'      => $metadata['tables'],
            'size'        => file_exists($backup_file) ? filesize($backup_file) : 0,
        ];
    }

    /**
     * List available backups
     *
     * @param  integer $limit
     * @return array
     */
    public function listBackups(int $limit = 10): array
    {
        $backups = [];

        // Get all backup files
        $files = glob($this->backup_dir . '/*.json');

        if (empty($files)) {
            return [];
        }

        // Sort by modification time (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Limit results
        $files = array_slice($files, 0, $limit);

        // Get backup info for each file
        foreach ($files as $file) {
            $backup_id = basename($file, '.json');
            $info      = $this->getBackupInfo($backup_id);

            if ($info) {
                $backups[] = $info;
            }
        }

        return $backups;
    }

    /**
     * Clean up old backups
     *
     * @param  integer $older_than_days Delete backups older than this many days
     * @param  integer $keep_minimum    Minimum number of backups to keep
     * @param  boolean $dry_run         Preview without deleting
     * @return integer Number of backups deleted
     */
    public function cleanupOldBackups(int $older_than_days = 30, int $keep_minimum = 5, bool $dry_run = false): int
    {
        $deleted = 0;

        // Get all backup files
        $files = glob($this->backup_dir . '/*.json');

        if (empty($files)) {
            return 0;
        }

        // Sort by modification time (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Keep minimum number of backups
        $files_to_check = array_slice($files, $keep_minimum);

        $cutoff_time = time() - ($older_than_days * 86400);

        foreach ($files_to_check as $metadata_file) {
            if (filemtime($metadata_file) < $cutoff_time) {
                $backup_id = basename($metadata_file, '.json');

                if (!$dry_run) {
                    // Delete backup files
                    $sql_file = $this->backup_dir . '/' . $backup_id . '.sql';

                    if (file_exists($sql_file)) {
                        @unlink($sql_file);
                    }

                    @unlink($metadata_file);

                    $this->log('Deleted old backup: ' . $backup_id);
                }

                ++$deleted;
            }
        }

        // Also enforce maximum backup limit
        $total_backups = count($files);
        if ($total_backups > self::MAX_BACKUPS) {
            $files_to_delete = array_slice($files, self::MAX_BACKUPS);

            foreach ($files_to_delete as $metadata_file) {
                $backup_id = basename($metadata_file, '.json');

                if (!$dry_run) {
                    $sql_file = $this->backup_dir . '/' . $backup_id . '.sql';

                    if (file_exists($sql_file)) {
                        @unlink($sql_file);
                    }

                    @unlink($metadata_file);

                    $this->log('Deleted excess backup: ' . $backup_id);
                }

                ++$deleted;
            }
        }

        return $deleted;
    }

    /**
     * Ensure backup directory exists and is protected
     */
    private function ensureBackupDirectory(): void
    {
        // Create directory if it doesn't exist
        if (!file_exists($this->backup_dir)) {
            wp_mkdir_p($this->backup_dir);
        }

        // Add .htaccess to prevent direct access
        $htaccess = $this->backup_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }

        // Add index.php to prevent directory listing
        $index = $this->backup_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, "<?php // Silence is golden\n");
        }
    }

    /**
     * Generate unique backup ID
     *
     * @return string
     */
    private function generateBackupId(): string
    {
        return 'backup_' . date('Ymd_His') . '_' . wp_generate_password(6, false);
    }

    /**
     * Get all plugin tables
     *
     * @return array
     */
    private function getPluginTables(): array
    {
        $tables = [];

        // Get all tables with our prefix
        $all_tables = $this->wpdb->get_col("SHOW TABLES LIKE '{$this->tablePrefix}%'");

        foreach ($all_tables as $table) {
            $tables[] = $table;
        }

        return $tables;
    }

    /**
     * Backup a single table
     *
     * @param string   $table
     * @param resource $handle File handle
     */
    private function backupTable(string $table, $handle): void
    {
        fwrite($handle, "\n-- Table: {$table}\n");

        // Drop table if exists
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

        // Get create table statement
        $create_table = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        if ($create_table) {
            fwrite($handle, $create_table[1] . ";\n\n");
        }

        // Get table data
        $rows = $this->wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);

        if (!empty($rows)) {
            // Build insert statements
            $columns      = array_keys($rows[0]);
            $columns_list = '`' . implode('`, `', $columns) . '`';

            fwrite($handle, "INSERT INTO `{$table}` ({$columns_list}) VALUES\n");

            $values = [];
            foreach ($rows as $row) {
                $row_values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $row_values[] = 'NULL';
                    } else {
                        $row_values[] = "'" . $this->wpdb->_real_escape($value) . "'";
                    }
                }
                $values[] = '(' . implode(', ', $row_values) . ')';
            }

            fwrite($handle, implode(",\n", $values) . ";\n");
        }

        fwrite($handle, "\n");
    }

    /**
     * Save backup metadata
     *
     * @param string $backup_id
     * @param array  $metadata
     */
    private function saveBackupMetadata(string $backup_id, array $metadata): void
    {
        $metadata_file = $this->backup_dir . '/' . $backup_id . '.json';
        file_put_contents($metadata_file, wp_json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Split SQL file into individual queries
     *
     * @param  string $sql
     * @return array
     */
    private function splitSqlFile(string $sql): array
    {
        $queries       = [];
        $current_query = '';
        $in_string     = false;
        $string_char   = '';

        $lines = explode("\n", $sql);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }

            // Handle strings to avoid splitting on semicolons inside strings
            for ($i = 0; $i < strlen($line); $i++) {
                $char      = $line[$i];
                $prev_char = $i > 0 ? $line[$i - 1] : '';

                if (!$in_string && ($char === '"' || $char === "'")) {
                    $in_string   = true;
                    $string_char = $char;
                } elseif ($in_string && $char === $string_char && $prev_char !== '\\') {
                    $in_string = false;
                }

                $current_query .= $char;

                if (!$in_string && $char === ';') {
                    $queries[]     = trim($current_query);
                    $current_query = '';
                }
            }

            $current_query .= "\n";
        }

        // Add last query if exists
        if (!empty(trim($current_query))) {
            $queries[] = trim($current_query);
        }

        return $queries;
    }
}
