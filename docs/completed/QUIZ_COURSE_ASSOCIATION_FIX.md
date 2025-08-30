# Quiz Course Association Fix

## Problem
Quizzes created from lessons were not appearing in the course curriculum because they were missing the proper section association.

## Root Cause
MemberPress Courses uses a three-tier hierarchy:
```
Course
  └── Sections (stored in wp_mpcs_sections table)
      └── Lessons/Quizzes/Assignments (WordPress posts)
```

For a quiz to appear in a course, it must be associated with a **section**, not just the course.

## The Fix

### Required Meta Fields for Quizzes

1. **`_mpcs_lesson_section_id`** (REQUIRED)
   - Links the quiz to a section within the course
   - Without this, the quiz won't appear in the course curriculum
   - Value: Section ID from the lesson

2. **`_mpcs_lesson_lesson_order`** (REQUIRED)
   - Determines the order within the section
   - We set it to lesson's order + 1 to place quiz after the lesson

3. **`_mpcs_lesson_id`** (Optional)
   - Links quiz to the specific lesson it was created from
   - Useful for tracking relationships

4. **`_mpcs_course_id`** (Optional)
   - Direct course association for quick lookups
   - Not used by core MemberPress Courses for display

### Implementation

```php
// Get section ID from lesson - THIS IS CRUCIAL
$sectionId = get_post_meta($lessonId, '_mpcs_lesson_section_id', true);
if ($sectionId) {
    update_post_meta($quizId, '_mpcs_lesson_section_id', $sectionId);
    
    // Get the lesson's order and place quiz right after it
    $lessonOrder = get_post_meta($lessonId, '_mpcs_lesson_lesson_order', true);
    $quizOrder = $lessonOrder ? (int)$lessonOrder + 1 : 1;
    update_post_meta($quizId, '_mpcs_lesson_lesson_order', $quizOrder);
}
```

## How MemberPress Retrieves Course Content

1. **Get Course Sections**
   ```php
   $sections = Section::find_all_by_course($course_id);
   ```

2. **For Each Section, Get Content**
   ```php
   $lessons = Lesson::find_all_by_section($section_id);
   // This includes lessons, quizzes, and assignments
   ```

3. **Query Pattern**
   - Looks for posts with `_mpcs_lesson_section_id` = section ID
   - Orders by `_mpcs_lesson_lesson_order`
   - Includes post types: `mpcs-lesson`, `mpcs-quiz`, `mpcs-assignment`

## Testing

After creating a quiz from a lesson:
1. Go to the course page
2. The quiz should appear in the same section as the lesson
3. The quiz should be positioned right after the lesson
4. Check the course curriculum sidebar - quiz should be listed

## Additional Notes

- Sections are NOT WordPress posts - they're stored in a custom table
- The course structure is flexible - any content type can be in any section
- Order numbers don't need to be sequential - they're sorted numerically
- Multiple items can have the same order number (they'll be sub-sorted by ID)