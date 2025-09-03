<?php

namespace MemberPressCoursesCopilot\Commands;

use MemberPressCoursesCopilot\Services\DatabaseService;
use MemberPressCoursesCopilot\Services\DatabaseBackupService;
use WP_CLI;

/**
 * Database management commands for MemberPress Courses Copilot
 *
 * @package MemberPressCoursesCopilot\Commands
 * @since   1.1.0
 */
class DatabaseCommand
{
    /**
     * @var DatabaseService
     */
    private DatabaseService $databaseService;

    /**
     * @var DatabaseBackupService
     */
    private DatabaseBackupService $backupService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->databaseService = new DatabaseService();
        $this->backupService   = new DatabaseBackupService();
    }

    /**
     * Check if the current user has the required permissions
     *
     * @return boolean
     */
    private function checkPermissions(): bool
    {
        // For CLI commands, check if user is super admin (multisite) or administrator
        if (is_multisite()) {
            return is_super_admin();
        }

        $meprCap = function_exists('MeprUtils::get_mepr_admin_capability')
            ? \MeprUtils::get_mepr_admin_capability()
            : 'remove_users';
        return current_user_can($meprCap);
    }

    /**
     * Validate command parameters
     *
     * @param  array $assoc_args
     * @param  array $allowed_params
     * @return array Validated parameters
     */
    private function validateParameters(array $assoc_args, array $allowed_params): array
    {
        $validated = [];

        foreach ($allowed_params as $param => $config) {
            if (isset($assoc_args[$param])) {
                $value = $assoc_args[$param];

                // Validate type
                if (isset($config['type'])) {
                    switch ($config['type']) {
                        case 'bool':
                            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                            break;
                        case 'int':
                            $value = filter_var($value, FILTER_VALIDATE_INT);
                            if ($value === false) {
                                WP_CLI::error("Invalid integer value for parameter '{$param}'.");
                            }
                            break;
                        case 'string':
                            $value = sanitize_text_field($value);
                            break;
                    }
                }

                // Validate allowed values
                if (isset($config['allowed']) && !in_array($value, $config['allowed'], true)) {
                    WP_CLI::error("Invalid value for parameter '{$param}'. Allowed values: " . implode(', ', $config['allowed']));
                }

                $validated[$param] = $value;
            } elseif (isset($config['default'])) {
                $validated[$param] = $config['default'];
            } elseif (isset($config['required']) && $config['required']) {
                WP_CLI::error("Required parameter '{$param}' is missing.");
            }
        }

        return $validated;
    }

    /**
     * Prompt for confirmation on destructive operations
     *
     * @param  string $message
     * @return boolean
     */
    private function confirmAction(string $message): bool
    {
        WP_CLI::warning($message);

        $response = \cli\prompt(
            'Are you sure you want to continue? Type "yes" to confirm',
            false,
            ''
        );

        return strtolower($response) === 'yes';
    }

    /**
     * Add missing indexes to database tables
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview changes without executing them
     *
     * [--backup]
     * : Create a backup before adding indexes (default: true)
     *
     * ## EXAMPLES
     *
     *     # Add indexes with backup
     *     wp mpcc database add-indexes
     *
     *     # Preview changes without executing
     *     wp mpcc database add-indexes --dry-run
     *
     *     # Add indexes without backup
     *     wp mpcc database add-indexes --backup=false
     *
     * @when after_wp_load
     */
    public function addIndexes($args, $assoc_args): void
    {
        // Check permissions
        if (!$this->checkPermissions()) {
            WP_CLI::error('You must have administrator privileges to run database commands.');
        }

        // Validate parameters
        $params = $this->validateParameters($assoc_args, [
            'dry-run' => [
                'type'    => 'bool',
                'default' => false,
            ],
            'backup'  => [
                'type'    => 'bool',
                'default' => true,
            ],
        ]);

        WP_CLI::line('Checking for missing indexes in database tables...');

        if ($params['dry-run']) {
            WP_CLI::line('DRY RUN MODE: Showing what would be done without making changes.');

            $missing_indexes = $this->databaseService->getMissingIndexes();

            if (empty($missing_indexes)) {
                WP_CLI::success('No missing indexes found.');
                return;
            }

            WP_CLI::line('');
            WP_CLI::line('The following indexes would be added:');
            foreach ($missing_indexes as $index) {
                WP_CLI::line(sprintf(
                    '  - Table: %s, Index: %s, Column: %s',
                    $index['table'],
                    $index['index_name'],
                    $index['column']
                ));
            }

            return;
        }

        // Create backup if requested
        if ($params['backup']) {
            WP_CLI::line('Creating database backup...');

            $backup_id = $this->backupService->createBackup('add_indexes');

            if (!$backup_id) {
                WP_CLI::error('Failed to create database backup. Aborting operation.');
            }

            WP_CLI::line(sprintf('Backup created successfully (ID: %s)', $backup_id));
        }

        // Execute the operation
        $success = $this->databaseService->addMissingIndexes();

        if ($success) {
            WP_CLI::success('Missing indexes have been added successfully.');
        } else {
            WP_CLI::error('Failed to add missing indexes. Check the logs for more details.');

            if ($params['backup']) {
                WP_CLI::warning('You can restore the backup using: wp mpcc database restore --backup-id=' . $backup_id);
            }
        }
    }

    /**
     * Install or upgrade database tables
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview changes without executing them
     *
     * [--backup]
     * : Create a backup before installing/upgrading (default: true)
     *
     * [--force]
     * : Force installation even if tables already exist
     *
     * ## EXAMPLES
     *
     *     # Install tables with backup
     *     wp mpcc database install
     *
     *     # Preview changes without executing
     *     wp mpcc database install --dry-run
     *
     *     # Force reinstall without backup
     *     wp mpcc database install --force --backup=false
     *
     * @when after_wp_load
     */
    public function install($args, $assoc_args): void
    {
        // Check permissions
        if (!$this->checkPermissions()) {
            WP_CLI::error('You must have administrator privileges to run database commands.');
        }

        // Validate parameters
        $params = $this->validateParameters($assoc_args, [
            'dry-run' => [
                'type'    => 'bool',
                'default' => false,
            ],
            'backup'  => [
                'type'    => 'bool',
                'default' => true,
            ],
            'force'   => [
                'type'    => 'bool',
                'default' => false,
            ],
        ]);

        WP_CLI::line('Checking database tables...');

        if ($params['dry-run']) {
            WP_CLI::line('DRY RUN MODE: Showing what would be done without making changes.');

            $tables = $this->databaseService->getTableStatus();

            WP_CLI::line('');
            WP_CLI::line('Table status:');
            foreach ($tables as $table => $exists) {
                $status = $exists ? 'exists' : 'missing';
                $action = $exists ? ($params['force'] ? 'recreate' : 'skip') : 'create';
                WP_CLI::line(sprintf('  - %s: %s (action: %s)', $table, $status, $action));
            }

            return;
        }

        // Confirm destructive operation if force is used
        if ($params['force']) {
            if (!$this->confirmAction('Force installation will DROP and recreate existing tables. All data will be lost!')) {
                WP_CLI::line('Operation cancelled.');
                return;
            }
        }

        // Create backup if requested
        if ($params['backup']) {
            WP_CLI::line('Creating database backup...');

            $backup_id = $this->backupService->createBackup('install_tables');

            if (!$backup_id) {
                WP_CLI::error('Failed to create database backup. Aborting operation.');
            }

            WP_CLI::line(sprintf('Backup created successfully (ID: %s)', $backup_id));
        }

        // Execute the operation
        WP_CLI::line('Installing/upgrading database tables...');

        try {
            if ($params['force']) {
                $success = $this->databaseService->reinstallTables();
            } else {
                $success = $this->databaseService->installTables();
            }

            if ($success) {
                WP_CLI::success('Database tables have been installed/upgraded successfully.');
            } else {
                throw new \Exception('Installation failed');
            }
        } catch (\Exception $e) {
            WP_CLI::error('Failed to install/upgrade database tables: ' . $e->getMessage());

            if ($params['backup']) {
                WP_CLI::warning('You can restore the backup using: wp mpcc database restore --backup-id=' . $backup_id);
            }
        }
    }

    /**
     * Check and run database migrations if needed
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview migrations without executing them
     *
     * [--backup]
     * : Create a backup before running migrations (default: true)
     *
     * [--target-version]
     * : Migrate to a specific version
     *
     * ## EXAMPLES
     *
     *     # Run pending migrations with backup
     *     wp mpcc database migrate
     *
     *     # Preview migrations without executing
     *     wp mpcc database migrate --dry-run
     *
     *     # Migrate to specific version
     *     wp mpcc database migrate --target-version=1.2.0
     *
     * @when after_wp_load
     */
    public function migrate($args, $assoc_args): void
    {
        // Check permissions
        if (!$this->checkPermissions()) {
            WP_CLI::error('You must have administrator privileges to run database commands.');
        }

        // Validate parameters
        $params = $this->validateParameters($assoc_args, [
            'dry-run'        => [
                'type'    => 'bool',
                'default' => false,
            ],
            'backup'         => [
                'type'    => 'bool',
                'default' => true,
            ],
            'target-version' => [
                'type'    => 'string',
                'default' => null,
            ],
        ]);

        WP_CLI::line('Checking for database migrations...');

        $pending_migrations = $this->databaseService->getPendingMigrations($params['target-version']);

        if (empty($pending_migrations)) {
            WP_CLI::success('Database is up to date. No migrations needed.');
            return;
        }

        if ($params['dry-run']) {
            WP_CLI::line('DRY RUN MODE: Showing migrations that would be applied.');
            WP_CLI::line('');
            WP_CLI::line('Pending migrations:');
            foreach ($pending_migrations as $migration) {
                WP_CLI::line(sprintf(
                    '  - Version %s: %s',
                    $migration['version'],
                    $migration['description']
                ));
            }

            return;
        }

        // Confirm migration
        $count   = count($pending_migrations);
        $message = sprintf(
            '%d migration%s will be applied. This may modify your database structure.',
            $count,
            $count > 1 ? 's' : ''
        );

        if (!$this->confirmAction($message)) {
            WP_CLI::line('Migration cancelled.');
            return;
        }

        // Create backup if requested
        $backup_id = null;
        if ($params['backup']) {
            WP_CLI::line('Creating database backup...');

            $backup_id = $this->backupService->createBackup('migration');

            if (!$backup_id) {
                WP_CLI::error('Failed to create database backup. Aborting migration.');
            }

            WP_CLI::line(sprintf('Backup created successfully (ID: %s)', $backup_id));
        }

        // Execute migrations
        WP_CLI::line('Running migrations...');

        try {
            foreach ($pending_migrations as $migration) {
                WP_CLI::line(sprintf('Applying migration %s...', $migration['version']));

                $success = $this->databaseService->runMigration($migration['version']);

                if (!$success) {
                    throw new \Exception(sprintf('Migration %s failed', $migration['version']));
                }

                WP_CLI::line(sprintf('Migration %s completed.', $migration['version']));
            }

            WP_CLI::success('All migrations completed successfully.');
        } catch (\Exception $e) {
            WP_CLI::error('Migration failed: ' . $e->getMessage());

            if ($backup_id) {
                WP_CLI::warning('The database may be in an inconsistent state.');
                WP_CLI::warning('You can restore the backup using: wp mpcc database restore --backup-id=' . $backup_id);
            }
        }
    }

    /**
     * Create a database backup
     *
     * ## OPTIONS
     *
     * [--description=<description>]
     * : Description for the backup
     *
     * ## EXAMPLES
     *
     *     # Create a manual backup
     *     wp mpcc database backup
     *
     *     # Create a backup with description
     *     wp mpcc database backup --description="Before major update"
     *
     * @when after_wp_load
     */
    public function backup($args, $assoc_args): void
    {
        // Check permissions
        if (!$this->checkPermissions()) {
            WP_CLI::error('You must have administrator privileges to run database commands.');
        }

        // Validate parameters
        $params = $this->validateParameters($assoc_args, [
            'description' => [
                'type'    => 'string',
                'default' => 'Manual backup',
            ],
        ]);

        WP_CLI::line('Creating database backup...');

        $backup_id = $this->backupService->createBackup('manual', $params['description']);

        if ($backup_id) {
            $backup_info = $this->backupService->getBackupInfo($backup_id);

            WP_CLI::success(sprintf(
                'Backup created successfully!\nID: %s\nLocation: %s\nSize: %s',
                $backup_id,
                $backup_info['file'],
                size_format($backup_info['size'])
            ));
        } else {
            WP_CLI::error('Failed to create backup.');
        }
    }

    /**
     * Restore from a database backup
     *
     * ## OPTIONS
     *
     * <backup-id>
     * : The backup ID to restore from
     *
     * [--force]
     * : Skip confirmation prompt
     *
     * ## EXAMPLES
     *
     *     # Restore from backup
     *     wp mpcc database restore backup_20240115_123456
     *
     *     # Restore without confirmation
     *     wp mpcc database restore backup_20240115_123456 --force
     *
     * @when after_wp_load
     */
    public function restore($args, $assoc_args): void
    {
        // Check permissions
        if (!$this->checkPermissions()) {
            WP_CLI::error('You must have administrator privileges to run database commands.');
        }

        // Get backup ID
        if (empty($args[0])) {
            WP_CLI::error('Please provide a backup ID.');
        }

        $backup_id = $args[0];

        // Validate parameters
        $params = $this->validateParameters($assoc_args, [
            'force' => [
                'type'    => 'bool',
                'default' => false,
            ],
        ]);

        // Check if backup exists
        $backup_info = $this->backupService->getBackupInfo($backup_id);

        if (!$backup_info) {
            WP_CLI::error(sprintf('Backup with ID "%s" not found.', $backup_id));
        }

        // Display backup information
        WP_CLI::line('Backup information:');
        WP_CLI::line(sprintf('  - ID: %s', $backup_info['id']));
        WP_CLI::line(sprintf('  - Date: %s', $backup_info['date']));
        WP_CLI::line(sprintf('  - Description: %s', $backup_info['description']));
        WP_CLI::line(sprintf('  - Size: %s', size_format($backup_info['size'])));

        // Confirm restore
        if (!$params['force']) {
            $message = 'Restoring this backup will REPLACE ALL CURRENT DATA in the plugin tables.';

            if (!$this->confirmAction($message)) {
                WP_CLI::line('Restore cancelled.');
                return;
            }
        }

        // Perform restore
        WP_CLI::line('Restoring from backup...');

        $success = $this->backupService->restoreBackup($backup_id);

        if ($success) {
            WP_CLI::success('Database restored successfully from backup.');
        } else {
            WP_CLI::error('Failed to restore database from backup.');
        }
    }

    /**
     * List available database backups
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Maximum number of backups to display (default: 10)
     *
     * [--format=<format>]
     * : Output format (default: table)
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     * ---
     *
     * ## EXAMPLES
     *
     *     # List recent backups
     *     wp mpcc database list-backups
     *
     *     # List all backups as JSON
     *     wp mpcc database list-backups --limit=100 --format=json
     *
     * @when after_wp_load
     */
    public function listBackups($args, $assoc_args): void
    {
        // Check permissions
        if (!$this->checkPermissions()) {
            WP_CLI::error('You must have administrator privileges to run database commands.');
        }

        // Validate parameters
        $params = $this->validateParameters($assoc_args, [
            'limit'  => [
                'type'    => 'int',
                'default' => 10,
            ],
            'format' => [
                'type'    => 'string',
                'default' => 'table',
                'allowed' => ['table', 'json', 'csv'],
            ],
        ]);

        $backups = $this->backupService->listBackups($params['limit']);

        if (empty($backups)) {
            WP_CLI::line('No backups found.');
            return;
        }

        // Format backup data for display
        $formatted_backups = array_map(function ($backup) {
            return [
                'ID'          => $backup['id'],
                'Date'        => $backup['date'],
                'Type'        => $backup['type'],
                'Description' => $backup['description'],
                'Size'        => size_format($backup['size']),
                'Tables'      => implode(', ', array_keys($backup['tables'])),
            ];
        }, $backups);

        WP_CLI\Utils\format_items(
            $params['format'],
            $formatted_backups,
            ['ID', 'Date', 'Type', 'Description', 'Size']
        );
    }

    /**
     * Delete old database backups
     *
     * ## OPTIONS
     *
     * [--older-than=<days>]
     * : Delete backups older than specified days (default: 30)
     *
     * [--keep-minimum=<count>]
     * : Minimum number of backups to keep (default: 5)
     *
     * [--dry-run]
     * : Preview what would be deleted
     *
     * ## EXAMPLES
     *
     *     # Delete backups older than 30 days
     *     wp mpcc database cleanup-backups
     *
     *     # Delete backups older than 7 days, keeping at least 10
     *     wp mpcc database cleanup-backups --older-than=7 --keep-minimum=10
     *
     * @when after_wp_load
     */
    public function cleanupBackups($args, $assoc_args): void
    {
        // Check permissions
        if (!$this->checkPermissions()) {
            WP_CLI::error('You must have administrator privileges to run database commands.');
        }

        // Validate parameters
        $params = $this->validateParameters($assoc_args, [
            'older-than'   => [
                'type'    => 'int',
                'default' => 30,
            ],
            'keep-minimum' => [
                'type'    => 'int',
                'default' => 5,
            ],
            'dry-run'      => [
                'type'    => 'bool',
                'default' => false,
            ],
        ]);

        $deleted = $this->backupService->cleanupOldBackups(
            $params['older-than'],
            $params['keep-minimum'],
            $params['dry-run']
        );

        if ($params['dry-run']) {
            if ($deleted > 0) {
                WP_CLI::line(sprintf(
                    'DRY RUN: Would delete %d backup%s.',
                    $deleted,
                    $deleted > 1 ? 's' : ''
                ));
            } else {
                WP_CLI::line('DRY RUN: No backups would be deleted.');
            }
        } else {
            if ($deleted > 0) {
                WP_CLI::success(sprintf(
                    'Deleted %d old backup%s.',
                    $deleted,
                    $deleted > 1 ? 's' : ''
                ));
            } else {
                WP_CLI::line('No old backups to delete.');
            }
        }
    }
}

// Register WP-CLI commands if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('mpcc database', DatabaseCommand::class);
}
