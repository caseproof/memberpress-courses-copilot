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
    private string $backupDir;

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
        $this->wpdb        = $wpdb;
        $this->tablePrefix = $wpdb->prefix . 'mpcc_';

        // Set up backup directory
        $uploadDir       = wp_upload_dir();
        $this->backupDir = $uploadDir['basedir'] . '/mpcc-backups';

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
            $backupId   = $this->generateBackupId();
            $backupFile = $this->backupDir . '/' . $backupId . '.sql';

            $this->log('Creating database backup: ' . $backupId);

            // Get all plugin tables
            $tables = $this->getPluginTables();

            if (empty($tables)) {
                $this->log('No tables found to backup', 'warning');
                return false;
            }

            // Create backup file
            $handle = fopen($backupFile, 'w');
            if (!$handle) {
                throw new \Exception('Failed to create backup file');
            }

            // Write backup metadata
            fwrite($handle, "-- MemberPress Courses Copilot Database Backup\n");
            fwrite($handle, "-- Backup ID: {$backupId}\n");
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
            $this->saveBackupMetadata($backupId, [
                'type'        => $type,
                'description' => $description,
                'tables'      => $tables,
                'size'        => filesize($backupFile),
                'created_at'  => current_time('mysql'),
            ]);

            $this->log('Backup created successfully: ' . $backupId);

            // Clean up old backups
            $this->cleanupOldBackups(30, 5);

            return $backupId;
        } catch (\Exception $e) {
            $this->log('Failed to create backup: ' . $e->getMessage(), 'error');

            // Clean up partial backup file
            if (isset($backupFile) && file_exists($backupFile)) {
                @unlink($backupFile);
            }

            return false;
        }
    }

    /**
     * Restore from a backup
     *
     * @param  string $backupId
     * @return boolean
     */
    public function restoreBackup(string $backupId): bool
    {
        try {
            $backupFile = $this->backupDir . '/' . $backupId . '.sql';

            if (!file_exists($backupFile)) {
                throw new \Exception('Backup file not found: ' . $backupId);
            }

            $this->log('Restoring from backup: ' . $backupId);

            // Read backup file
            $sql = file_get_contents($backupFile);
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

                $this->log('Backup restored successfully: ' . $backupId);
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
     * @param  string $backupId
     * @return array|null
     */
    public function getBackupInfo(string $backupId): ?array
    {
        $metadataFile = $this->backupDir . '/' . $backupId . '.json';

        if (!file_exists($metadataFile)) {
            return null;
        }

        $metadata = json_decode(file_get_contents($metadataFile), true);

        if (!$metadata) {
            return null;
        }

        $backupFile = $this->backupDir . '/' . $backupId . '.sql';

        return [
            'id'          => $backupId,
            'file'        => $backupFile,
            'date'        => $metadata['created_at'],
            'type'        => $metadata['type'],
            'description' => $metadata['description'],
            'tables'      => $metadata['tables'],
            'size'        => file_exists($backupFile) ? filesize($backupFile) : 0,
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
        $files = glob($this->backupDir . '/*.json');

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
            $backupId = basename($file, '.json');
            $info      = $this->getBackupInfo($backupId);

            if ($info) {
                $backups[] = $info;
            }
        }

        return $backups;
    }

    /**
     * Clean up old backups
     *
     * @param  integer $olderThanDays Delete backups older than this many days
     * @param  integer $keepMinimum    Minimum number of backups to keep
     * @param  boolean $dryRun         Preview without deleting
     * @return integer Number of backups deleted
     */
    public function cleanupOldBackups(int $olderThanDays = 30, int $keepMinimum = 5, bool $dryRun = false): int
    {
        $deleted = 0;

        // Get all backup files
        $files = glob($this->backupDir . '/*.json');

        if (empty($files)) {
            return 0;
        }

        // Sort by modification time (newest first)
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Keep minimum number of backups
        $filesToCheck = array_slice($files, $keepMinimum);

        $cutoffTime = time() - ($olderThanDays * 86400);

        foreach ($filesToCheck as $metadataFile) {
            if (filemtime($metadataFile) < $cutoffTime) {
                $backupId = basename($metadataFile, '.json');

                if (!$dryRun) {
                    // Delete backup files
                    $sqlFile = $this->backupDir . '/' . $backupId . '.sql';

                    if (file_exists($sqlFile)) {
                        @unlink($sqlFile);
                    }

                    @unlink($metadataFile);

                    $this->log('Deleted old backup: ' . $backupId);
                }

                ++$deleted;
            }
        }

        // Also enforce maximum backup limit
        $totalBackups = count($files);
        if ($totalBackups > self::MAX_BACKUPS) {
            $filesToDelete = array_slice($files, self::MAX_BACKUPS);

            foreach ($filesToDelete as $metadataFile) {
                $backupId = basename($metadataFile, '.json');

                if (!$dryRun) {
                    $sqlFile = $this->backupDir . '/' . $backupId . '.sql';

                    if (file_exists($sqlFile)) {
                        @unlink($sqlFile);
                    }

                    @unlink($metadataFile);

                    $this->log('Deleted excess backup: ' . $backupId);
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
        if (!file_exists($this->backupDir)) {
            wp_mkdir_p($this->backupDir);
        }

        // Add .htaccess to prevent direct access
        $htaccess = $this->backupDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            file_put_contents($htaccess, "Deny from all\n");
        }

        // Add index.php to prevent directory listing
        $index = $this->backupDir . '/index.php';
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
        $allTables = $this->wpdb->get_col("SHOW TABLES LIKE '{$this->tablePrefix}%'");

        foreach ($allTables as $table) {
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
        $createTable = $this->wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        if ($createTable) {
            fwrite($handle, $createTable[1] . ";\n\n");
        }

        // Get table data
        $rows = $this->wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);

        if (!empty($rows)) {
            // Build insert statements
            $columns      = array_keys($rows[0]);
            $columnsList = '`' . implode('`, `', $columns) . '`';

            fwrite($handle, "INSERT INTO `{$table}` ({$columnsList}) VALUES\n");

            $values = [];
            foreach ($rows as $row) {
                $rowValues = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $rowValues[] = 'NULL';
                    } else {
                        $rowValues[] = "'" . $this->wpdb->_real_escape($value) . "'";
                    }
                }
                $values[] = '(' . implode(', ', $rowValues) . ')';
            }

            fwrite($handle, implode(",\n", $values) . ";\n");
        }

        fwrite($handle, "\n");
    }

    /**
     * Save backup metadata
     *
     * @param string $backupId
     * @param array  $metadata
     */
    private function saveBackupMetadata(string $backupId, array $metadata): void
    {
        $metadataFile = $this->backupDir . '/' . $backupId . '.json';
        file_put_contents($metadataFile, wp_json_encode($metadata, JSON_PRETTY_PRINT));
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
        $currentQuery = '';
        $inString     = false;
        $stringChar   = '';

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
                $prevChar = $i > 0 ? $line[$i - 1] : '';

                if (!$inString && ($char === '"' || $char === "'")) {
                    $inString   = true;
                    $stringChar = $char;
                } elseif ($inString && $char === $stringChar && $prevChar !== '\\') {
                    $inString = false;
                }

                $currentQuery .= $char;

                if (!$inString && $char === ';') {
                    $queries[]     = trim($currentQuery);
                    $currentQuery = '';
                }
            }

            $currentQuery .= "\n";
        }

        // Add last query if exists
        if (!empty(trim($currentQuery))) {
            $queries[] = trim($currentQuery);
        }

        return $queries;
    }
}
