# Troubleshooting Guide

## Common Issues and Solutions

### Installation Issues

#### Plugin activation fails
**Symptoms:**
- White screen when activating
- "Plugin could not be activated" error
- PHP fatal errors

**Solutions:**
1. Check PHP version (requires 8.0+):
   ```php
   <?php phpinfo(); ?>
   ```

2. Verify MemberPress and MemberPress Courses are active

3. Check error logs:
   ```bash
   tail -f /wp-content/debug.log
   ```

4. Increase PHP memory limit in `wp-config.php`:
   ```php
   define('WP_MEMORY_LIMIT', '256M');
   ```

#### Database tables not created
**Symptoms:**
- "Table doesn't exist" errors
- Sessions not saving

**Solutions:**
1. Manually trigger table creation:
   ```bash
   wp mpcc database create
   ```

2. Check database permissions for CREATE TABLE

3. Verify table prefix in `wp-config.php`

### AI Connection Issues

#### AI not responding
**Symptoms:**
- "Failed to connect to AI service" errors
- Timeouts on chat messages
- Empty responses

**Solutions:**
1. Check authentication gateway URL:
   ```php
   // In wp-config.php
   define('MPCC_AUTH_GATEWAY_URL', 'https://memberpress-auth-gateway-49bbf7ff52ea.herokuapp.com');
   ```

2. Test gateway connectivity:
   ```bash
   curl -I https://memberpress-auth-gateway-49bbf7ff52ea.herokuapp.com/health
   ```

3. Check firewall rules - ensure outbound HTTPS (port 443) is allowed

4. Increase timeout in requests:
   ```php
   add_filter('http_request_timeout', function() {
       return 60; // 60 seconds
   });
   ```

#### Rate limiting errors
**Symptoms:**
- "Too many requests" errors
- 429 status codes

**Solutions:**
1. Wait 60 seconds between requests
2. Check if multiple users are sharing same session
3. Clear browser cache and cookies

### Session Management Issues

#### Sessions not saving
**Symptoms:**
- Conversations lost on page refresh
- "Session not found" errors
- Progress not persisting

**Solutions:**
1. Check JavaScript console for errors:
   ```javascript
   // Should see periodic save messages
   console.log('Session saved:', sessionId);
   ```

2. Verify user is logged in and has proper capabilities

3. Check database write permissions:
   ```sql
   SHOW GRANTS FOR CURRENT_USER;
   ```

4. Clear session cache:
   ```php
   delete_transient('mpcc_session_cache_' . $userId);
   ```

#### Duplicate sessions appearing
**Symptoms:**
- Same conversation appears multiple times
- Session list shows duplicates

**Solutions:**
1. Check for unique index on session_id column
2. Clear corrupted sessions:
   ```sql
   DELETE FROM wp_mpcc_conversations 
   WHERE session_id NOT IN (
       SELECT MIN(id) FROM wp_mpcc_conversations 
       GROUP BY session_id
   );
   ```

### Course Creation Issues

#### Course creation fails
**Symptoms:**
- "Failed to create course" error
- Course created but missing sections/lessons
- Timeout during creation

**Solutions:**
1. Check user permissions:
   ```php
   current_user_can('publish_posts'); // Should return true
   ```

2. Verify MemberPress Courses post types are registered:
   ```php
   post_type_exists('mpcs-course'); // Should return true
   ```

3. Check for theme/plugin conflicts by temporarily switching to default theme

4. Increase execution time for course creation:
   ```php
   set_time_limit(300); // 5 minutes
   ```

#### Lesson content not saving
**Symptoms:**
- Draft content lost
- "Generate with AI" not working
- Content reverts to previous version

**Solutions:**
1. Check lesson draft table exists:
   ```sql
   SHOW TABLES LIKE '%mpcc_lesson_drafts%';
   ```

2. Verify AJAX nonce is valid
3. Check browser network tab for failed requests
4. Clear browser local storage

### UI/JavaScript Issues

#### Chat interface not loading
**Symptoms:**
- Blank modal window
- JavaScript errors in console
- Buttons not responding

**Solutions:**
1. Check for JavaScript conflicts:
   ```javascript
   // In browser console
   typeof jQuery !== 'undefined' && jQuery.fn.jquery
   ```

2. Verify scripts are enqueued:
   ```php
   wp_script_is('mpcc-ai-chat-interface', 'enqueued');
   ```

3. Check for Content Security Policy issues

4. Disable browser extensions temporarily

#### Course preview not updating
**Symptoms:**
- Preview shows old data
- Changes not reflected
- Structure corrupted

**Solutions:**
1. Force refresh preview:
   ```javascript
   mpccCourseEditor.refreshPreview();
   ```

2. Check for malformed JSON in course structure
3. Clear browser cache
4. Verify WebSocket connection if using real-time updates

### Performance Issues

#### Slow AI responses
**Symptoms:**
- Long wait times for responses
- Timeouts frequently
- UI freezing

**Solutions:**
1. Check server resources:
   ```bash
   top
   free -m
   df -h
   ```

2. Optimize database queries:
   ```sql
   EXPLAIN SELECT * FROM wp_mpcc_conversations WHERE user_id = X;
   ```

3. Enable object caching (Redis/Memcached)

4. Reduce conversation history sent:
   ```javascript
   conversationHistory.slice(-10) // Last 10 messages only
   ```

#### Database queries slow
**Symptoms:**
- Page load delays
- Timeout errors
- High CPU usage

**Solutions:**
1. Add indexes:
   ```sql
   ALTER TABLE wp_mpcc_conversations ADD INDEX idx_user_updated (user_id, updated_at);
   ```

2. Clean old sessions:
   ```sql
   DELETE FROM wp_mpcc_conversations 
   WHERE updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
   ```

3. Optimize table:
   ```sql
   OPTIMIZE TABLE wp_mpcc_conversations;
   ```

## Debug Mode

Enable comprehensive debugging:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('MPCC_DEBUG', true);
```

Check logs at:
- WordPress: `/wp-content/debug.log`
- Plugin: `/wp-content/plugins/memberpress-courses-copilot/logs/debug.log`

## Getting Help

### Information to Provide

When reporting issues, include:

1. **Environment details:**
   ```
   WordPress version: X.X
   PHP version: X.X
   MemberPress version: X.X
   Plugin version: X.X
   ```

2. **Error messages:**
   - Full error text
   - Stack trace if available
   - Browser console errors

3. **Steps to reproduce:**
   - Exact actions taken
   - Expected vs actual behavior

4. **Debug information:**
   - Relevant log entries
   - Network requests/responses
   - Database query results

### Support Channels

- MemberPress support portal
- Community forums
- GitHub issues (for development)

## Emergency Recovery

### Reset plugin state
```bash
# Deactivate and reactivate
wp plugin deactivate memberpress-courses-copilot
wp plugin activate memberpress-courses-copilot

# Reset database tables
wp mpcc database reset --force

# Clear all caches
wp cache flush
```

### Manual cleanup
```sql
-- Remove all sessions for a user
DELETE FROM wp_mpcc_conversations WHERE user_id = X;

-- Remove all lesson drafts
TRUNCATE TABLE wp_mpcc_lesson_drafts;

-- Reset auto-increment
ALTER TABLE wp_mpcc_conversations AUTO_INCREMENT = 1;
```

### Safe mode
Add to `wp-config.php` to disable AI features temporarily:
```php
define('MPCC_SAFE_MODE', true);
```