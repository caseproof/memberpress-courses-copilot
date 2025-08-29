# Course-Filtered Lessons Fix

## Problem
When creating quizzes from the course context (not from a specific lesson), users were seeing ALL lessons from ALL courses in the dropdown. This made it difficult to select the correct lesson when courses had many lessons.

## Solution
Implemented course context detection and filtering so that when creating a quiz from a course page, only lessons from that specific course are shown in the dropdown.

## Implementation Details

### 1. Course Context Detection
Added course detection to `quiz-ai-modal.js`:
- Detects `course_id` from URL parameters
- Detects course from referrer URL
- Stores detected course ID in `this.currentCourseId`

### 2. Lesson Filtering
Modified the `loadLessons()` method to:
- Check if a course context exists
- If course ID is detected, make AJAX calls to verify which lessons belong to that course
- Filter the lesson dropdown to show only lessons from the current course

### 3. Visual Feedback
Added a course context indicator:
- Blue banner showing "Creating quiz for course: [Course Name]"
- Appears at the top of the modal when course context is detected
- CSS styling with info blue colors and icon

### 4. AJAX Endpoint
Created new `get_lesson_course` endpoint in `MpccQuizAjaxController`:
- Returns the course ID and title for a given lesson
- Used to filter lessons by course in the frontend

## Files Modified

1. **assets/js/quiz-ai-modal.js**
   - Added `currentCourseId` property
   - Enhanced `detectLessonContext()` to detect course context
   - Added `loadLessonsForCourse()` method
   - Added `populateLessonDropdown()` method
   - Added `showCourseContext()` method

2. **src/MemberPressCoursesCopilot/Controllers/MpccQuizAjaxController.php**
   - Added `get_lesson_course()` method
   - Registered new AJAX action

3. **assets/css/quiz-ai-modal.css**
   - Added `.mpcc-course-context` styles
   - Blue info banner styling

4. **src/MemberPressCoursesCopilot/Services/AssetManager.php**
   - Updated version to 1.0.6 for cache busting

## Testing

1. Navigate to a course in the admin
2. Click to add a new quiz from the course curriculum
3. Click "Generate with AI" button
4. Verify that:
   - A blue banner appears showing the course name
   - The lesson dropdown only shows lessons from that course
   - Lesson selection works correctly

## User Experience Improvements

- Users no longer need to scroll through hundreds of lessons
- Clear visual indication of which course they're creating a quiz for
- Faster, more intuitive quiz creation workflow
- Maintains consistency with the existing auto-detection features