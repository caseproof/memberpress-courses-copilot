# AI Chat Functionality Test Checklist

## Test Environment
- URL: http://localhost:10044/wp-admin/admin.php?page=mpcc-course-editor&action=new
- WordPress Admin Credentials: admin/admin

## 1. Course Editor Page - AI Chat Interface

### Initial Load
- [ ] Navigate to the course editor page
- [ ] Verify chat interface loads on the left side
- [ ] Check for welcome message in chat
- [ ] Verify chat input textarea is present
- [ ] Check send button is visible and enabled
- [ ] Verify quick starter suggestions are displayed

### Basic Chat Functionality
- [ ] Type a message: "Create a JavaScript course for beginners"
- [ ] Click Send button
- [ ] Monitor network tab for AJAX request to `admin-ajax.php`
- [ ] Check request payload includes:
  - `action: mpcc_chat_message`
  - `nonce: <value>`
  - `session_id: <value>`
  - `message: <your message>`
- [ ] Verify typing indicator appears
- [ ] Wait for AI response
- [ ] Check response appears in chat
- [ ] Verify course structure appears in preview panel

### Network Request Details
Expected AJAX endpoint: `/wp-admin/admin-ajax.php`
Expected action: `mpcc_chat_message`

### Quick Starter Buttons
- [ ] Click "JavaScript for Beginners" button
- [ ] Verify message populates in input
- [ ] Message automatically sends
- [ ] AI responds with course structure

### Course Preview Panel
- [ ] After AI generates course, check right panel updates
- [ ] Verify course title displays
- [ ] Check sections are listed
- [ ] Verify lessons appear under sections
- [ ] Test clicking on a lesson
- [ ] Lesson editor should open

### Session Management
- [ ] Check session ID in browser console: `sessionStorage.getItem('mpcc_current_session_id')`
- [ ] Reload page
- [ ] Verify conversation history persists
- [ ] Check course structure remains

### Error Scenarios
- [ ] Send empty message - should not send
- [ ] Disable network - check error handling
- [ ] Invalid session - check recovery

## 2. Course Edit Page - AI Assistant Metabox

### Setup
- [ ] Create a new course or edit existing
- [ ] Save as draft
- [ ] Look for "AI Course Assistant" metabox

### Metabox Functionality
- [ ] Verify chat interface in metabox
- [ ] Type: "Help me improve this course description"
- [ ] Send message
- [ ] Check for AI response
- [ ] Verify response is contextual to course

## 3. JavaScript Console Checks

Run these commands in browser console:

```javascript
// Check if chat is initialized
jQuery('#mpcc-chat-input').length > 0

// Check session ID
sessionStorage.getItem('mpcc_current_session_id')

// Check if CourseEditor is loaded
typeof window.CourseEditor

// Manually trigger chat
jQuery('#mpcc-chat-input').val('Test message');
jQuery('#mpcc-send-message').click();
```

## 4. Common Issues to Check

### AJAX Errors
- Check browser console for errors
- Verify nonce is being sent
- Check user permissions

### UI Issues
- Chat messages not displaying
- Course preview not updating
- Buttons not responding

### Session Issues
- Session not persisting
- Conversation history lost
- Course structure not saving

## 5. Debug Information

### Check AJAX Response
1. Open Network tab
2. Filter by XHR
3. Look for admin-ajax.php calls
4. Check response for:
   - Success: true/false
   - Error messages
   - Data structure

### WordPress Debug
1. Enable WP_DEBUG in wp-config.php
2. Check debug.log for PHP errors
3. Look for plugin-specific errors

### Plugin Logs
Check: `/wp-content/plugins/memberpress-courses-copilot/logs/debug.log`

## Test Results Template

```
Date: _____________
Tester: ___________

AI Chat on Course Editor:
- Working: [ ] Yes [ ] No
- Issues: _________________

AI Assistant on Course Edit:
- Working: [ ] Yes [ ] No
- Issues: _________________

Network Requests:
- Status codes: ___________
- Response times: _________

JavaScript Errors:
- Count: _____
- Details: ________________

Overall Status: [ ] Pass [ ] Fail
```

## Automated Test Script

Run automated tests:
```bash
cd /path/to/plugin
node tests/test-ai-chat-functionality.js
```

This will generate screenshots and a detailed report.