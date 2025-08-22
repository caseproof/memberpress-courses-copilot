# MemberPress Courses Copilot - Logging Configuration

## Overview

The plugin includes a comprehensive logging system that helps with debugging and monitoring. Logging is controlled via WordPress configuration constants in your `wp-config.php` file.

## Configuration

### Enable/Disable Logging

Logging is automatically enabled when WordPress debug mode is active:

```php
// In wp-config.php
define( 'WP_DEBUG', true );
```

When `WP_DEBUG` is `false`, all logging is completely disabled with zero performance overhead.

### Set Log Level

You can control the verbosity of logging by adding the `MPCC_LOG_LEVEL` constant to your `wp-config.php`:

```php
// In wp-config.php - Add one of these:
define( 'MPCC_LOG_LEVEL', 'debug' );    // Most verbose - includes API requests/responses
define( 'MPCC_LOG_LEVEL', 'info' );     // Default - general information and errors
define( 'MPCC_LOG_LEVEL', 'warning' );  // Warnings and errors only
define( 'MPCC_LOG_LEVEL', 'error' );    // Errors and critical issues only
define( 'MPCC_LOG_LEVEL', 'critical' ); // Only critical failures
```

## Log Levels Explained

### DEBUG
- Detailed diagnostic information
- API request/response payloads
- Variable states and execution flow
- Best for development and troubleshooting

### INFO (Default)
- General informational messages
- Successful operations (course created, etc.)
- User actions and milestones
- Normal application flow

### WARNING
- Potentially problematic situations
- Failed nonce checks
- Missing permissions
- Non-critical issues that should be investigated

### ERROR
- Error conditions that don't stop execution
- Failed API calls
- Validation failures
- Recoverable problems

### CRITICAL
- Critical conditions requiring immediate attention
- System failures
- Security breaches
- Application crashes

## Log File Location

Logs are stored in: `/wp-content/uploads/memberpress-courses-copilot/logs/copilot.log`

The log directory is protected from web access via `.htaccess` for security.

## Example Configuration

For development/debugging:
```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'MPCC_LOG_LEVEL', 'debug' );
```

For production with error tracking:
```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'MPCC_LOG_LEVEL', 'error' );
```

For production with logging disabled:
```php
// wp-config.php
define( 'WP_DEBUG', false );
// MPCC_LOG_LEVEL doesn't matter when WP_DEBUG is false
```

## Log Rotation

- Maximum log file size: 10MB
- Keeps up to 5 rotated log files
- Older logs are automatically deleted
- Log files rotate to `.1.log`, `.2.log`, etc.

## Security Notes

1. Logs may contain sensitive information (API requests, user data)
2. Log directory is protected from web access
3. Only enable DEBUG level in development environments
4. Consider log retention policies for compliance

## Troubleshooting

If logging isn't working:

1. Verify `WP_DEBUG` is set to `true` in wp-config.php
2. Check file permissions on `/wp-content/uploads/`
3. Ensure the plugin can create directories
4. Look for PHP errors in your server's error log

## Performance Considerations

- When `WP_DEBUG` is `false`, logging has zero overhead
- Each log method checks if logging is enabled before processing
- Log files are only written when necessary
- Consider using higher log levels (ERROR, CRITICAL) in production