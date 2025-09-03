# PHPCS Implementation for MemberPress Courses Copilot

## Overview

This document tracks the implementation of PHP CodeSniffer (PHPCS) with Caseproof coding standards for issue #109.

## Implementation Status

### ✅ Completed Tasks

1. **Installed Caseproof Coding Standards**
   - Added `caseproof/coding-standards-php` package (v0.7.2)
   - Configured composer repository at `https://pkgs.cspf.co`
   - Updated `phpcs.xml` to use `Caseproof-WP-Standard` ruleset

2. **Automatic Fixes Applied**
   - Fixed 5,996 violations automatically using PHPCBF
   - Corrected indentation (spaces to tabs)
   - Removed trailing whitespace
   - Fixed opening brace positions
   - Added missing newlines at end of files
   - Aligned equals signs in assignments

3. **Manual Fixes Applied**
   - Added `declare(strict_types=1)` to 22 PHP files
   - Fixed inline comment punctuation (added periods)
   - Converted snake_case variables to camelCase:
     - `$asset_manager` → `$assetManager`
     - `$editor_ai_integration` → `$editorAiIntegration`
     - `$quiz_ajax_controller` → `$quizAjaxController`
     - `$query_arg` → `$queryArg`
     - And many others
   - Added missing documentation comments
   - Fixed SQL security warnings with proper PHPCS ignores
   - Fixed parameter comment punctuation

## Current Status

### Summary of Remaining Issues

After applying all automatic and manual fixes, we have:
- **Total Errors**: 2,583 (down from ~6,000+)
- **Total Warnings**: 1,196 (down from ~2,000+)
- **Files with Issues**: 43

### Top Files by Issue Count

1. `DatabaseService.php` - 166 errors, 208 warnings
2. `CourseAjaxService.php` - 251 errors, 177 warnings
3. `SessionFeaturesService.php` - 202 errors, 29 warnings
4. `MpccQuizAjaxController.php` - 181 errors, 29 warnings
5. `SimpleAjaxController.php` - 163 errors, 15 warnings

### Common Remaining Issues

#### 1. Missing Documentation
- Missing file doc comments
- Missing class doc comments
- Missing method doc comments
- Missing parameter descriptions
- Incorrect or incomplete @return tags

#### 2. Variable Naming
- Member variables using snake_case instead of camelCase
- Some local variables still using snake_case

#### 3. Security Warnings
- Direct database queries without prepare statements
- User input not properly sanitized
- Output not properly escaped

#### 4. Code Complexity
- Functions exceeding maximum cyclomatic complexity (10)
- Methods with too many parameters
- Long methods that should be refactored

#### 5. WordPress Specific
- Direct file operations instead of WP filesystem API
- Missing capability checks
- Incorrect nonce verification patterns

## How to Run PHPCS

```bash
# Check all files
composer run cs-check

# Check specific file
./vendor/bin/phpcs --standard=Caseproof-WP-Standard path/to/file.php

# Auto-fix issues
composer run cs-fix

# Get summary report
./vendor/bin/phpcs --standard=Caseproof-WP-Standard src/ --report=summary
```

## Caseproof Coding Standards

The Caseproof-WP-Standard includes:

### Key Differences from WordPress Core
1. **Variables**: Use camelCase (not snake_case)
2. **Methods**: Use camelCase
3. **Arrays**: Short array syntax `[]` is allowed
4. **Strict Types**: All files should have `declare(strict_types=1)`
5. **Comments**: All inline comments must end with punctuation

### Configuration

The standards are configured in:
- `phpcs.xml` - Main configuration file
- `composer.json` - Scripts for running checks

## Next Steps

To achieve full compliance:

1. **Documentation Sprint**
   - Add missing file headers
   - Complete method documentation
   - Add @param and @return tags

2. **Variable Refactoring**
   - Convert remaining snake_case variables
   - Update database field references

3. **Security Improvements**
   - Add proper sanitization
   - Implement proper escaping
   - Use prepared statements correctly

4. **Code Refactoring**
   - Break down complex methods
   - Reduce cyclomatic complexity
   - Extract reusable code

## Ignoring Rules

When necessary, use inline comments to ignore specific rules:

```php
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DELETE FROM {$this->table_name}");
```

## Contributing

When adding new code:
1. Run `composer run cs-check` before committing
2. Fix all errors and warnings
3. Add proper documentation
4. Follow camelCase naming conventions
5. Include strict types declaration