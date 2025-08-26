# Fix Plan: Published State Bug

## Problem Description
When a user is viewing a published course and asks the AI to create a new course, the new course incorrectly shows as "published" even though it hasn't been created yet. This prevents the user from actually creating the course.

## Root Cause Analysis
The system is keeping the user in the same session/conversation when they should be redirected to a new session for creating a new course. The current behavior:
1. User is viewing a published course (with publishedCourseId set)
2. User asks AI to create a new course
3. AI generates new course structure but stays in same session
4. Published state from previous course carries over

## Correct Behavior
When user asks to "create a course" while viewing an existing course, the system should:
1. Save current session if needed
2. Create a new session
3. Redirect to a fresh course editor page
4. Start the new course creation in the new session

## Fix Implementation Plan

### Solution Option 1: Disable Chat for Published Courses (SIMPLEST)

Since published courses are locked for editing, the chat interface should also be disabled to prevent confusion.

**Benefits:**
- Clear user experience: published = read-only
- Prevents accidental course structure changes
- Forces users to use "Duplicate Course" for iterations
- Simplest implementation

### Solution Option 2: Detect "Create Course" Intent and Start New Session

When the AI detects the user wants to create a new course (not modify the existing one), it should trigger a new session.

## Implementation Steps for Option 1 (Disable Chat)

### Step 1: Disable Chat Input for Published Courses
In `course-editor-page.js` `renderCourseStructure()`:
```javascript
// After rendering course structure
if (this.publishedCourseId) {
    // Disable chat for published courses
    $('#mpcc-chat-input').prop('disabled', true)
        .attr('placeholder', 'This course has been published. Use "Duplicate Course" to create a new version.');
    $('#mpcc-send-message').prop('disabled', true);
    
    // Optionally hide quick starters
    $('.mpcc-quick-starter-suggestions').hide();
}
```

### Step 2: Add Visual Indicator
Add CSS for disabled state:
```css
.mpcc-chat-input:disabled {
    background-color: #f5f5f5;
    cursor: not-allowed;
}

#mpcc-send-message:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
```

### Step 3: Show Helpful Message
When chat is disabled, show a message explaining why:
```javascript
// Add info message above chat input
if (this.publishedCourseId) {
    $('.mpcc-chat-input-wrapper').prepend(
        '<div class="mpcc-chat-disabled-notice">' +
        '<span class="dashicons dashicons-info"></span> ' +
        'This course is published and locked. To make changes, use the "Duplicate Course" button above.' +
        '</div>'
    );
}
```

## Implementation Steps for Option 2 (Auto New Session)

[Previous implementation details remain the same]

## Testing Plan
1. Load a published course
2. Ask AI to create a new course
3. Verify new course shows as draft (not published)
4. Verify "Create Course" button is enabled
5. Test creating the new course works properly
6. Test switching between published and draft courses

## Prevention
- Add validation to ensure course structure from AI never includes published state
- Add unit tests for state management
- Document that published state is session-specific metadata

## Timeline
- Quick fix: 15 minutes
- Comprehensive fix: 1 hour
- Testing: 30 minutes