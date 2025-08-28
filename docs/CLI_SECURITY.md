# CLI Security Documentation

## Overview

The MemberPress Courses Copilot CLI commands have been enhanced with comprehensive security measures to ensure safe database operations in production environments.

## Security Features

### 1. Permission Checks

All database commands now require administrator privileges:

```php
// Single site WordPress
current_user_can('manage_options')

// Multisite WordPress
is_super_admin()
```

**Example:**
```bash
# Non-admin user attempting to run command
$ wp mpcc database migrate
[ERROR] You must have administrator privileges to run database commands.
```

### 2. Input Validation

All command parameters are validated:

- **Type validation**: bool, int, string
- **Allowed values**: Enforced for enum-type parameters
- **Sanitization**: All string inputs are sanitized

```php
$params = $this->validateParameters($assoc_args, [
    'dry-run' => ['type' => 'bool', 'default' => false],
    'backup' => ['type' => 'bool', 'default' => true],
    'limit' => ['type' => 'int', 'default' => 10]
]);
```

### 3. Confirmation Prompts

Destructive operations require explicit confirmation:

```bash
$ wp mpcc database install --force
[WARNING] Force installation will DROP and recreate existing tables. All data will be lost!
Are you sure you want to continue? Type "yes" to confirm: _
```

### 4. Dry-Run Mode

Preview changes without executing them:

```bash
$ wp mpcc database migrate --dry-run
[LINE] DRY RUN MODE: Showing migrations that would be applied.
[LINE] Pending migrations:
[LINE]   - Version 1.1.0: Add missing indexes for foreign key columns
```

### 5. Automatic Backups

Backups are created automatically before risky operations:

```bash
$ wp mpcc database migrate
[LINE] Creating database backup...
[LINE] Backup created successfully (ID: backup_20240115_120000_abc123)
[LINE] Running migrations...
```

Disable with `--backup=false` if needed.

### 6. Secure Backup Storage

Backups are stored securely:

- Location: `wp-content/uploads/mpcc-backups/`
- Protected by `.htaccess` (Deny from all)
- Directory listing prevented with `index.php`
- Unique IDs prevent filename guessing

## Command Reference

### Database Migration

```bash
# Preview migrations
wp mpcc database migrate --dry-run

# Run migrations with backup
wp mpcc database migrate

# Run without backup (not recommended)
wp mpcc database migrate --backup=false
```

### Index Management

```bash
# Preview missing indexes
wp mpcc database add-indexes --dry-run

# Add missing indexes
wp mpcc database add-indexes
```

### Table Installation

```bash
# Preview table status
wp mpcc database install --dry-run

# Install/upgrade tables
wp mpcc database install

# Force reinstall (destructive!)
wp mpcc database install --force
```

### Backup Management

```bash
# Create manual backup
wp mpcc database backup
wp mpcc database backup --description="Before major update"

# List backups
wp mpcc database list-backups
wp mpcc database list-backups --limit=20 --format=json

# Restore from backup
wp mpcc database restore backup_20240115_120000_abc123
wp mpcc database restore backup_20240115_120000_abc123 --force

# Clean up old backups
wp mpcc database cleanup-backups
wp mpcc database cleanup-backups --older-than=7 --keep-minimum=10
```

## Backup System Details

### Backup Structure

Each backup consists of:
- SQL dump file: `backup_[timestamp]_[random].sql`
- Metadata file: `backup_[timestamp]_[random].json`

### Backup Contents

- Complete table structure (CREATE TABLE statements)
- All table data (INSERT statements)
- Metadata: date, type, description, table list, size

### Automatic Cleanup

- Keeps last 30 days of backups by default
- Maintains minimum of 5 backups
- Maximum 50 backups total

### Restoration Process

1. Validates backup exists
2. Shows backup details
3. Requires confirmation (unless --force)
4. Wraps restore in transaction
5. Rollback on error

## Best Practices

### Production Usage

1. **Always use dry-run first**:
   ```bash
   wp mpcc database migrate --dry-run
   ```

2. **Keep backups enabled**:
   ```bash
   wp mpcc database migrate --backup=true
   ```

3. **Regular backup cleanup**:
   ```bash
   wp mpcc database cleanup-backups --older-than=30
   ```

4. **Test restore procedure**:
   ```bash
   # List available backups
   wp mpcc database list-backups
   
   # Test restore on staging
   wp mpcc database restore [backup-id]
   ```

### Security Checklist

- [ ] Verify user has admin privileges before running commands
- [ ] Review dry-run output before executing
- [ ] Confirm backup was created successfully
- [ ] Keep backup retention policy updated
- [ ] Test restore procedure regularly
- [ ] Monitor disk space for backup storage
- [ ] Review logs for failed operations

## Error Handling

### Common Errors

1. **Permission Denied**:
   ```
   [ERROR] You must have administrator privileges to run database commands.
   ```
   Solution: Run as admin user or grant manage_options capability

2. **Invalid Parameter**:
   ```
   [ERROR] Invalid integer value for parameter 'limit'.
   ```
   Solution: Check parameter types in command help

3. **Backup Failed**:
   ```
   [ERROR] Failed to create database backup. Aborting operation.
   ```
   Solution: Check disk space and write permissions

4. **Migration Failed**:
   ```
   [ERROR] Migration failed: [error message]
   [WARNING] You can restore the backup using: wp mpcc database restore --backup-id=...
   ```
   Solution: Review error, restore from backup if needed

## Logging

All operations are logged:

- Location: `wp-content/uploads/mpcc-logs/`
- Includes: timestamp, user, operation, result
- Retention: 30 days by default

## Support

For issues or questions:
1. Check the error log
2. Review this documentation
3. Test with --dry-run
4. Contact support with backup ID if restore needed