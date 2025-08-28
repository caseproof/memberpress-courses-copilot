<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Commands;

use MemberPressCoursesCopilot\Services\DatabaseService;
use WP_CLI;

/**
 * Database management commands for MemberPress Courses Copilot
 * 
 * @package MemberPressCoursesCopilot\Commands
 * @since 1.1.0
 */
class DatabaseCommand
{
    /**
     * @var DatabaseService
     */
    private DatabaseService $databaseService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->databaseService = new DatabaseService();
    }

    /**
     * Add missing indexes to database tables
     *
     * ## EXAMPLES
     *
     *     wp mpcc database add-indexes
     *
     * @when after_wp_load
     */
    public function addIndexes(): void
    {
        WP_CLI::line('Adding missing indexes to database tables...');
        
        $success = $this->databaseService->addMissingIndexes();
        
        if ($success) {
            WP_CLI::success('Missing indexes have been added successfully.');
        } else {
            WP_CLI::error('Failed to add missing indexes. Check the logs for more details.');
        }
    }

    /**
     * Install or upgrade database tables
     *
     * ## EXAMPLES
     *
     *     wp mpcc database install
     *
     * @when after_wp_load
     */
    public function install(): void
    {
        WP_CLI::line('Installing/upgrading database tables...');
        
        $success = $this->databaseService->installTables();
        
        if ($success) {
            WP_CLI::success('Database tables have been installed/upgraded successfully.');
        } else {
            WP_CLI::error('Failed to install/upgrade database tables. Check the logs for more details.');
        }
    }

    /**
     * Check and run database migrations if needed
     *
     * ## EXAMPLES
     *
     *     wp mpcc database migrate
     *
     * @when after_wp_load
     */
    public function migrate(): void
    {
        WP_CLI::line('Checking for database migrations...');
        
        $this->databaseService->maybeUpgradeDatabase();
        
        WP_CLI::success('Database migration check completed.');
    }
}

// Register WP-CLI commands if WP-CLI is available
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('mpcc database', DatabaseCommand::class);
}