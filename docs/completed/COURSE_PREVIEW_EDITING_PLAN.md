# Course Preview Editing Capabilities Implementation Plan

## Overview
This document outlines the implementation plan for adding inline editing capabilities to the course preview interface. Users will be able to edit lesson content, reorder sections/lessons, delete items, and generate content with AI - all within the preview pane.

## Core Requirements
1. **Inline Content Editing**: Click-to-edit functionality for lesson content
2. **AI Content Generation**: Generate lesson content using AI for individual lessons
3. **Auto-Save**: Automatically save content when switching between lessons
4. **Database Persistence**: Store draft content in database
5. **Reordering**: Drag-and-drop to reorder lessons and sections
6. **Deletion**: Remove lessons/sections with confirmation

## Technical Architecture

### Database Schema
```sql
CREATE TABLE {$wpdb->prefix}mpcc_lesson_drafts (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id VARCHAR(255) NOT NULL,
    section_id VARCHAR(255) NOT NULL,
    lesson_id VARCHAR(255) NOT NULL,
    content LONGTEXT,
    order_index INT(11) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_session (session_id),
    INDEX idx_section_lesson (section_id, lesson_id)
);
```

### AJAX Endpoints
1. `mpcc_save_lesson_content` - Save/update lesson content
2. `mpcc_load_lesson_content` - Load saved content for a lesson
3. `mpcc_generate_lesson_content` - Generate AI content for a lesson
4. `mpcc_reorder_course_items` - Update order of sections/lessons
5. `mpcc_delete_course_item` - Delete a section or lesson
6. `mpcc_load_all_drafts` - Load all drafts for a session

### Frontend Components

#### 1. Inline Editor Component
- Triggered by clicking on lesson in preview
- Textarea with formatting toolbar
- "Generate with AI" button
- Auto-save on blur
- Visual save indicator

#### 2. Course Item Controls
- Edit icon (pencil) - visible on hover
- Delete icon (trash) - with confirmation modal
- Drag handle (grip) - for reordering
- Loading spinner during operations

#### 3. State Management
- Track current editing lesson
- Maintain unsaved changes
- Handle concurrent operations
- Preserve content during reordering

## Implementation Phases

### Phase 1: Backend Infrastructure
**Files to create/modify:**
- `src/MemberPressCoursesCopilot/Services/LessonDraftService.php` (new)
- `src/MemberPressCoursesCopilot/Database/LessonDraftTable.php` (new)
- Update `CourseGeneratorService.php` with draft methods

**Key tasks:**
- Create database table on plugin activation
- Implement CRUD operations for drafts
- Add AJAX handlers for all endpoints
- Ensure proper nonce verification and permissions

### Phase 2: Frontend Foundation
**Files to create/modify:**
- `assets/js/course-preview-editor.js` (new)
- `assets/css/course-preview-editor.css` (new)
- Update `course-preview.js` to integrate editor
- Update `CourseAssetService.php` to enqueue new assets

**Key tasks:**
- Create inline editor UI component
- Implement click-to-edit functionality
- Add auto-save mechanism
- Create visual feedback system

### Phase 3: AI Integration
**Files to modify:**
- Update `AIService.php` with lesson content generation
- Add prompt templates for lesson content
- Implement streaming response handling

**Key tasks:**
- Create lesson-specific AI prompts
- Handle AI response streaming
- Integrate with inline editor
- Add error handling for AI failures

### Phase 4: Reordering & Deletion
**Files to modify:**
- Extend `course-preview-editor.js` with drag-drop
- Add jQuery UI sortable dependency
- Update preview rendering logic

**Key tasks:**
- Implement drag-and-drop with jQuery UI
- Add delete confirmation modals
- Update order indices in database
- Maintain content associations during reorder

### Phase 5: Course Creation Integration
**Files to modify:**
- Update `CourseGeneratorService::createCourseFromStructure()`
- Modify preview integration to include content

**Key tasks:**
- Map draft content to created lessons
- Clean up drafts after successful creation
- Handle content in course duplication
- Add content to preview display

## UI/UX Specifications

### Edit Mode
```
┌─────────────────────────────────────┐
│ Lesson 2.1: Variables and Data Types│ ← Click to edit
├─────────────────────────────────────┤
│ ┌─────────────────────────────────┐ │
│ │ [Editing mode - textarea]       │ │
│ │                                 │ │
│ │ Content goes here...            │ │
│ │                                 │ │
│ └─────────────────────────────────┘ │
│ [Generate with AI] [Save] [Cancel]  │
└─────────────────────────────────────┘
```

### Hover State
```
┌─────────────────────────────────────┐
│ ≡ Lesson 2.1: Variables...    ✏️ 🗑️ │
└─────────────────────────────────────┘
  ↑                             ↑   ↑
  Drag handle                Edit Delete
```

## Error Handling

### Save Failures
- Retry with exponential backoff
- Show error message with retry option
- Preserve unsaved content locally
- Log errors for debugging

### AI Generation Failures
- Fallback to manual editing
- Show helpful error message
- Provide example content structure
- Allow retry with modified prompt

### Concurrent Editing
- Warn about unsaved changes
- Prevent navigation with unsaved content
- Handle session timeouts gracefully
- Merge conflicts resolution

## Security Considerations

1. **Nonce Verification**: All AJAX calls must verify nonces
2. **Capability Checks**: Ensure user can edit courses
3. **Input Sanitization**: Sanitize all content before saving
4. **XSS Prevention**: Escape all output properly
5. **SQL Injection**: Use prepared statements

## Performance Optimizations

1. **Debounced Auto-save**: Wait 1 second after typing stops
2. **Lazy Loading**: Load drafts only when needed
3. **Batch Operations**: Save multiple changes together
4. **Client-side Caching**: Cache loaded content
5. **Optimistic Updates**: Update UI before server confirms

## Testing Requirements

### Unit Tests
- Draft CRUD operations
- Content sanitization
- Order calculation logic
- AI prompt generation

### Integration Tests
- Auto-save functionality
- Drag-and-drop reordering
- Content persistence
- Course creation with drafts

### User Acceptance Tests
- Edit lesson content inline
- Generate AI content
- Reorder lessons
- Delete sections
- Create course with edited content

## Migration & Cleanup

### Database Migration
- Create table on plugin activation
- Add cleanup on plugin deactivation
- Handle table updates for future versions

### Draft Cleanup
- Delete old drafts (30 days)
- Clean up after course creation
- Remove orphaned drafts
- Provide manual cleanup option

## Future Enhancements

1. **Rich Text Editor**: Add formatting options
2. **Media Uploads**: Support images/videos
3. **Collaborative Editing**: Multiple users
4. **Version History**: Track content changes
5. **Templates**: Pre-built lesson templates
6. **Import/Export**: Backup and restore drafts

## Dependencies

### JavaScript Libraries
- jQuery (already available)
- jQuery UI Sortable (to be added)

### WordPress APIs
- AJAX API
- Database API ($wpdb)
- Nonce API
- Capabilities API

### Existing Services
- AIService
- CourseGeneratorService
- Logger

## Success Metrics

1. **Performance**: Auto-save completes < 500ms
2. **Reliability**: 99.9% save success rate
3. **Usability**: Edit mode activation < 100ms
4. **AI Generation**: Content generation < 5 seconds
5. **Data Integrity**: Zero content loss incidents

## Implementation Timeline

- **Week 1**: Backend infrastructure (Phase 1)
- **Week 2**: Frontend foundation (Phase 2)
- **Week 3**: AI integration (Phase 3)
- **Week 4**: Reordering & deletion (Phase 4)
- **Week 5**: Integration & testing (Phase 5)
- **Week 6**: Polish & optimization

---

This plan ensures we add powerful editing capabilities while maintaining the existing functionality and user experience. All changes will be implemented incrementally with thorough testing at each phase.