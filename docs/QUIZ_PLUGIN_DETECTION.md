# Quiz Plugin Detection Implementation

## Overview
Implemented detection for the MemberPress Course Quizzes plugin to conditionally show/hide quiz-related functionality based on whether the plugin is active.

## Changes Made

### 1. AssetManager.php
- Added `isQuizPluginActive()` method that checks:
  - If `\memberpress\quizzes\VERSION` constant is defined (primary check)
  - Falls back to `is_plugin_active('memberpress-course-quizzes/main.php')`
- Modified `enqueueLessonEditorAssets()` to only enqueue quiz integration scripts when quiz plugin is active
- Modified `enqueueQuizEditorAssets()` to only enqueue when quiz plugin is active

### 2. lesson-quiz-integration.js
- No changes needed - script simply won't be loaded if quiz plugin is inactive

### 3. MpccQuizAjaxController.php
- Added quiz plugin active checks to:
  - `generate_quiz()` method
  - `create_quiz_from_lesson()` method
- Throws exception with user-friendly message if quiz plugin is not active

## How It Works

1. **PHP Detection**: The `isQuizPluginActive()` method checks if the MemberPress Course Quizzes plugin is active
2. **Script Control**: Quiz-related scripts are only enqueued when the plugin is active
3. **UI Hiding**: No quiz UI elements are shown because the scripts aren't loaded
4. **AJAX Protection**: Server-side AJAX handlers verify plugin is active before processing

## Testing

### Manual Testing Steps:
1. **With Quiz Plugin Active**:
   - Navigate to lesson edit page
   - Verify "Create Quiz" button appears in toolbar
   - Click button and verify quiz creation works

2. **With Quiz Plugin Inactive**:
   - Deactivate MemberPress Course Quizzes plugin
   - Navigate to lesson edit page
   - Verify "Create Quiz" button does NOT appear
   - Check browser console for "Quiz plugin not active, skipping button" message

### Test Script:
Run `tests/manual-quiz-plugin-check-test.js` in browser console on lesson edit page to verify:
- `mpcc_ajax.is_quiz_plugin_active` value
- Create Quiz button presence/absence
- Console messages

## Benefits

1. **Clean UI**: Users don't see quiz functionality they can't use
2. **Error Prevention**: Prevents JavaScript errors when quiz plugin is missing
3. **Clear Messaging**: Provides clear error messages if quiz actions are attempted without plugin
4. **Performance**: Doesn't load unnecessary quiz assets when plugin is inactive

## Future Considerations

- Could add an admin notice on lesson pages suggesting quiz plugin installation
- Could show a disabled button with tooltip explaining quiz plugin requirement
- Could check for specific quiz plugin version compatibility