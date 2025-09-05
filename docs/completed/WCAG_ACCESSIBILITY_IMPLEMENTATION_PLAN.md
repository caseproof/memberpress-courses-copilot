# WCAG Accessibility Implementation Plan

## Overview
This document outlines the comprehensive plan to implement WCAG 2.1 Level AA accessibility compliance across the MemberPress Courses Copilot plugin.

## Phase 1: Core Accessibility Infrastructure (HIGH PRIORITY)

### 1.1 Create MPCCAccessibility Utility Class
Create `/assets/js/mpcc-accessibility.js` with methods for:
- **Focus management and trapping**: `trapFocus()`, `restoreFocus()`
- **Keyboard event handling**: `makeKeyboardNavigable()`, `handleKeyboardShortcuts()`
- **Screen reader announcements**: `announce()`, `createLiveRegion()`
- **ARIA attribute helpers**: `setARIA()`, `toggleARIA()`, `updateARIA()`

### 1.2 Implement Keyboard Navigation
Across all components, implement:
- **ESC key**: Closes modals and cancels operations
- **Tab/Shift+Tab**: Cycles through interactive elements
- **Enter**: Activates buttons and submits forms
- **Shift+Enter**: Creates new lines in textareas
- **Arrow keys**: Navigate through lists and menus

### 1.3 Add Comprehensive ARIA Support
- **Semantic roles**: `role="dialog"`, `role="log"`, `role="button"`
- **Labels and descriptions**: `aria-label`, `aria-describedby`, `aria-labelledby`
- **Live regions**: `aria-live="polite"` for status updates, `aria-live="assertive"` for errors
- **State attributes**: `aria-expanded`, `aria-hidden`, `aria-selected`

## Phase 2: Component-Specific Enhancements (HIGH PRIORITY)

### 2.1 Course Editor Page (`/templates/admin/course-editor-page.php`)
- **Focus trap**: Implement in lesson editing modal
- **Screen reader announcements**: For save states ("Saving...", "Saved", "Error")
- **Keyboard navigation**: For course sections and lessons
- **Skip links**: Allow users to jump to main content areas

### 2.2 Quiz AI Modal (`/assets/js/quiz-ai-modal.js`)
- **Proper modal semantics**: `role="dialog"`, `aria-modal="true"`
- **Form accessibility**: Associate labels with inputs, add `aria-describedby` for help text
- **Validation messages**: Use `aria-invalid` and `aria-describedby` for errors
- **Progress announcements**: During AI question generation

### 2.3 Chat Interface (`/assets/js/course-editor-page.js`)
- **Live region**: For new messages (`aria-live="polite"`)
- **Keyboard shortcuts**: Common actions (Ctrl+Enter to send)
- **Message navigation**: Arrow keys to navigate through chat history
- **Input enhancement**: Proper labeling and placeholder text

### 2.4 Previous Conversations Modal
- **Focus management**: Trap focus within modal
- **Keyboard navigation**: Arrow keys to navigate sessions
- **Screen reader support**: Announce session details

## Phase 3: Visual and Motion Accessibility (MEDIUM PRIORITY)

### 3.1 CSS Media Queries
Add to `/assets/css/mpcc-accessibility.css`:

```css
/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}

/* High contrast support */
@media (prefers-contrast: high) {
    .mpcc-modal, .mpcc-chat-interface, .mpcc-button {
        border: 2px solid !important;
    }
}

/* Enhanced focus indicators */
*:focus {
    outline: 2px solid #0073aa !important;
    outline-offset: 2px !important;
}
```

### 3.2 Color Contrast Compliance
- **Audit existing colors**: Ensure all text meets 4.5:1 contrast ratio
- **Button colors**: Verify accessibility of purple theme (#6B4CE6)
- **Status indicators**: Ensure green "Ready" dot has sufficient contrast
- **Error messages**: Use high-contrast red for errors

## Phase 4: Form and Input Accessibility (HIGH PRIORITY)

### 4.1 Form Enhancements
- **Required fields**: Mark with `aria-required="true"`
- **Error states**: Use `aria-invalid="true"` for invalid fields
- **Help text**: Connect via `aria-describedby`
- **Labels**: Ensure all inputs have associated labels
- **Fieldset grouping**: Group related form controls

### 4.2 Input Validation
- **Real-time feedback**: Announce validation errors
- **Clear instructions**: Provide format requirements
- **Error recovery**: Help users fix validation issues

## Phase 5: Testing and Quality Assurance (MEDIUM PRIORITY)

### 5.1 Automated Testing
- **ESLint accessibility plugin**: Add to build process
- **axe-core integration**: Automated accessibility testing
- **Keyboard navigation tests**: Automated Tab key simulation

### 5.2 Manual Testing Checklist
- **Screen reader testing**: NVDA (Windows), VoiceOver (macOS)
- **Keyboard-only navigation**: Complete user flows
- **High contrast mode**: Windows High Contrast, browser extensions
- **Zoom testing**: 200% zoom functionality
- **Color blindness**: Test with various color vision simulations

### 5.3 Testing Tools
- **Browser extensions**: axe DevTools, WAVE
- **Built-in tools**: Chrome Lighthouse, Firefox Accessibility Inspector
- **Screen readers**: NVDA (free), JAWS (trial), VoiceOver (built-in macOS)

## Implementation Files to Create/Modify

### New Files:
1. `/assets/js/mpcc-accessibility.js` - Core accessibility utilities
2. `/assets/css/mpcc-accessibility.css` - Accessibility-specific styles
3. `/docs/accessibility-testing.md` - Testing procedures and checklists

### Files to Modify:
1. `/assets/js/course-editor-page.js` - Add keyboard navigation and ARIA
2. `/assets/js/quiz-ai-modal.js` - Enhance modal accessibility
3. `/templates/admin/course-editor-page.php` - Add semantic HTML and ARIA
4. `/assets/css/course-editor-page.css` - Add focus indicators and media queries

## Success Criteria

### Technical Requirements:
- [ ] All interactive elements keyboard accessible
- [ ] Proper ARIA labels on all components
- [ ] Focus management in modals and dialogs
- [ ] Screen reader announcements for dynamic content
- [ ] Color contrast ratios meet WCAG AA standards
- [ ] Reduced motion preferences respected

### Testing Requirements:
- [ ] Passes automated axe-core tests
- [ ] Complete keyboard-only navigation possible
- [ ] Screen reader compatibility verified
- [ ] High contrast mode support confirmed
- [ ] No accessibility violations in browser dev tools

## Timeline Estimate
- **Phase 1**: 2-3 days (Core infrastructure)
- **Phase 2**: 3-4 days (Component enhancements)
- **Phase 3**: 1-2 days (Visual accessibility)
- **Phase 4**: 1-2 days (Forms)
- **Phase 5**: 2-3 days (Testing and refinement)

**Total Estimated Time**: 9-14 days

## Resources and References
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [ARIA Authoring Practices Guide](https://www.w3.org/WAI/ARIA/apg/)
- [WebAIM Accessibility Resources](https://webaim.org/)
- [WordPress Accessibility Handbook](https://make.wordpress.org/accessibility/handbook/)