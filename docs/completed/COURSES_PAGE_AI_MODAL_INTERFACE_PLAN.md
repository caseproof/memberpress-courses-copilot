# AI Modal Interface Plan

## Overview
Replace the current sidebar metabox AI chat interface with a modal window activated by a "Create with AI" button in the course editor header area, matching the existing MemberPress Courses design language.

## Design Specifications

### 1. Button Placement & Styling
- **Location**: In the editor header area, next to the course title
- **Style**: Match existing MemberPress purple button style (#6B4CE6 or similar)
- **Text**: "Create with AI" with gear/sparkle icon
- **Class**: Use existing button classes (e.g., `button button-primary` or MemberPress-specific classes)
- **Icon**: WordPress dashicon or similar (e.g., `dashicons-lightbulb` or `dashicons-admin-generic`)

### 2. Modal Window Design
- **Width**: 600-800px (responsive)
- **Height**: 80% of viewport height
- **Position**: Centered on screen
- **Background**: White with subtle shadow
- **Overlay**: Semi-transparent dark backdrop (rgba(0,0,0,0.5))
- **Border**: Subtle border or shadow for depth
- **Animation**: Smooth fade-in (200-300ms)

### 3. Modal Layout Structure
```
+----------------------------------------+
| AI Course Assistant              [X]   |
| ────────────────────────────────────── |
|                                        |
| [Chat Messages Area]                   |
| • Scrollable message history           |
| • AI and user messages                 |
| • Apply/Copy buttons for content       |
|                                        |
|                                        |
| ────────────────────────────────────── |
| [Input Textarea]                       |
| [Send] [Close]                         |
+----------------------------------------+
```

### 4. Technical Implementation Details

#### WordPress Hooks
- Use `edit_form_after_title` hook to add button after title
- Or use `edit_form_top` for placement at the very top
- Ensure proper check for `mpcs-course` post type

#### Modal HTML Structure
```html
<div id="mpcc-ai-modal" class="mpcc-modal" style="display: none;">
    <div class="mpcc-modal-overlay"></div>
    <div class="mpcc-modal-content">
        <div class="mpcc-modal-header">
            <h2>AI Course Assistant</h2>
            <button class="mpcc-modal-close" aria-label="Close modal">×</button>
        </div>
        <div class="mpcc-modal-body">
            <div id="mpcc-ai-messages">[Chat messages]</div>
        </div>
        <div class="mpcc-modal-footer">
            <textarea id="mpcc-ai-input"></textarea>
            <button class="button button-primary">Send</button>
        </div>
    </div>
</div>
```

#### CSS Requirements
- Use WordPress admin CSS variables where possible
- Ensure z-index is high enough to overlay admin interface (z-index: 100000+)
- Make responsive for mobile devices
- Smooth transitions for open/close

#### JavaScript Functionality
- Open modal on button click
- Close on X button, ESC key, or overlay click
- Maintain all existing AI chat functionality
- Auto-focus on input field when opened
- Preserve chat history during session

### 5. Migration from Metabox
- Remove metabox registration from `NewCourseIntegration.php`
- Move all chat functionality to modal
- Preserve all AJAX endpoints and functionality
- Keep session management intact

### 6. User Experience Enhancements
- **Keyboard shortcuts**: ESC to close, Enter to send (Shift+Enter for new line)
- **Loading states**: Show spinner while AI is processing
- **Error handling**: Display errors within modal
- **Success feedback**: Visual confirmation when content is applied
- **Accessibility**: Proper ARIA labels and keyboard navigation

### 7. Benefits of Modal Approach
- **More space**: Larger chat area for better readability
- **Focus**: Modal creates focused environment for AI interaction
- **Clean interface**: Doesn't clutter the course editor sidebar
- **Mobile friendly**: Better experience on smaller screens
- **Familiar pattern**: Users are accustomed to modal interactions

## Implementation Steps

1. **Create button hook** - Add button to course editor header
2. **Build modal HTML** - Create modal structure and styling
3. **Move chat logic** - Transfer functionality from metabox to modal
4. **Style with MemberPress theme** - Match existing design patterns
5. **Test interactions** - Ensure all features work in modal context
6. **Remove old metabox** - Clean up previous implementation

## File Changes Required

### Modified Files
- `/src/MemberPressCoursesCopilot/Services/NewCourseIntegration.php`
  - Change from metabox to button/modal approach
  - Add `edit_form_after_title` hook
  - Generate modal HTML instead of metabox

### New Files
- `/assets/css/ai-modal.css` - Modal-specific styles
- `/assets/js/ai-modal.js` - Modal interaction logic (if separating from inline)

### Assets to Register
- Ensure modal CSS/JS are enqueued on course edit pages
- Use `admin_enqueue_scripts` with proper post type check

## Testing Checklist
- [ ] Button appears in correct location
- [ ] Modal opens/closes properly
- [ ] AI chat functionality works
- [ ] Content apply feature works
- [ ] Mobile responsive
- [ ] Keyboard shortcuts work
- [ ] Accessibility compliance
- [ ] No JavaScript errors
- [ ] Proper styling match

## Future Enhancements
- Remember modal size/position preferences
- Resizable modal window
- Minimize to corner option
- Quick templates/prompts menu
- Export chat history