# PHPCS Remaining Issues Summary

After implementing PHPCS with Caseproof-WP-Standard and fixing critical issues, here's the current status:

## Progress Made

### ✅ Completed
1. **Installed Caseproof coding standards** (v0.7.2)
2. **Fixed 5,996 automatic violations** with PHPCBF
3. **Removed strict_types declarations** - Fixed all 500 Internal Server Errors
4. **Added type casting** for database numeric fields
5. **Fixed 39 additional automatic issues** in second PHPCBF run
6. **Started fixing nonce verification warnings** in AJAX handlers

### 📊 Current Status
- **Total Errors**: ~2,546 (down from 2,585)
- **Total Warnings**: ~1,196
- **Files with Issues**: 42

## Top Priority Issues to Fix

### 1. Security Issues (HIGH PRIORITY)
- **Nonce Verification** (~87 instances)
  - Need to add `phpcs:ignore` comments where nonce is already verified
  - Use proper pattern: Get nonce first, then verify, then access other $_POST data
- **Escape Output** - All output must use esc_html(), esc_attr(), etc.
- **SQL Injection Prevention** - Use $wpdb->prepare() for all queries

### 2. Variable Naming (MEDIUM PRIORITY)
- **Snake_case to camelCase** (~134 instances)
  - Change `$post_id` to `$postId`
  - Change `$user_id` to `$userId`
  - Change `$template_path` to `$templatePath`
  - etc.

### 3. Documentation Issues (LOW PRIORITY)
- **Inline Comments** (~135 instances) - Must end with proper punctuation
- **Missing @param tags** - Add PHPDoc for all parameters
- **Missing @return tags** - Document return types
- **Missing @throws tags** - Document exceptions

## Files with Most Issues
1. **CourseAjaxService.php** - 429 issues (251 errors, 177 warnings)
2. **DatabaseService.php** - 375 issues (166 errors, 208 warnings)
3. **SessionFeaturesService.php** - 232 issues (202 errors, 29 warnings)
4. **MpccQuizAjaxController.php** - 211 issues (181 errors, 29 warnings)
5. **DatabaseBackupService.php** - 204 issues (55 errors, 148 warnings)

## Recommended Next Steps

1. **Fix nonce verification pattern** across all AJAX handlers:
   ```php
   // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below
   $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
   if (!NonceConstants::verify($nonce, NonceConstants::YOUR_NONCE, false)) {
       // Handle error
   }
   // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified
   $otherData = isset($_POST['data']) ? sanitize_text_field(wp_unslash($_POST['data'])) : '';
   ```

2. **Mass replace variable names** using search/replace:
   - `$post_id` → `$postId`
   - `$user_id` → `$userId`
   - `$session_id` → `$sessionId`
   - `$course_id` → `$courseId`
   - etc.

3. **Add proper escaping** to all output:
   - Use `esc_html()` for text
   - Use `esc_attr()` for attributes
   - Use `esc_url()` for URLs
   - Use `wp_kses_post()` for content with allowed HTML

4. **Fix inline comments** - Add periods at the end

5. **Add missing documentation** - Focus on public methods first

## Notes
- The plugin is functional despite these issues
- Most issues are coding standard violations, not bugs
- Security issues should be addressed first
- Variable naming can be done with mass search/replace
- Documentation can be added incrementally