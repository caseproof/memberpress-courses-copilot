# MemberPress Courses Copilot CSS Architecture

## Overview

This directory contains all CSS stylesheets for the MemberPress Courses Copilot plugin. The styles follow a modular architecture with a common base stylesheet and specific component styles.

## File Structure

### Core Files

- `mpcc-common.css` - Common styles, variables, utilities, and shared components
- `toast.css` - Toast notification styles
- `ai-copilot.css` - AI chat interface and copilot UI styles
- `accessibility.css` - Accessibility enhancements and WCAG compliance styles

### Feature-Specific Files

- `course-edit-ai-chat.css` - Styles for AI chat in course editing
- `course-editor-page.css` - Course editor page layout and components
- `course-preview-editor.css` - Course preview and inline editing styles
- `courses-integration.css` - MemberPress Courses integration styles
- `admin-settings.css` - Admin settings page styles
- `editor-ai-modal.css` - WordPress editor AI modal styles

## CSS Architecture

### CSS Variables

All common values are defined as CSS custom properties in `mpcc-common.css`:

```css
:root {
  /* Colors */
  --mpcc-primary: #0073aa;
  --mpcc-primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  
  /* Spacing */
  --mpcc-spacing-sm: 0.5rem;
  --mpcc-spacing-md: 1rem;
  --mpcc-spacing-lg: 1.5rem;
  
  /* Typography */
  --mpcc-font-size-sm: 0.875rem;
  --mpcc-font-size-base: 1rem;
  
  /* Transitions */
  --mpcc-transition-fast: 150ms ease-in-out;
  --mpcc-transition-base: 250ms ease-in-out;
}
```

### Naming Convention

The plugin uses a prefix-based naming convention:

- `mpcc-` - Main prefix for all classes
- `mpcc-btn` - Component name
- `mpcc-btn--primary` - Component modifier
- `mpcc-btn__icon` - Component element

### Utility Classes

Common utility classes are available in `mpcc-common.css`:

- Layout: `mpcc-hidden`, `mpcc-flex`, `mpcc-block`
- Text: `mpcc-text-center`, `mpcc-text-primary`, `mpcc-text-muted`
- Spacing: `mpcc-mt-sm`, `mpcc-p-lg`, `mpcc-mb-xl`

### Responsive Design

Breakpoints:
- Mobile: max-width: 768px
- Tablet: 769px - 1024px
- Desktop: min-width: 1025px

### Accessibility

- Focus states with proper outline and offset
- High contrast mode support
- Reduced motion support
- Screen reader utilities

## Best Practices

1. **Import Common Styles**: Always import `mpcc-common.css` at the top of feature files
2. **Use CSS Variables**: Prefer variables over hard-coded values
3. **Maintain Specificity**: Avoid overly specific selectors
4. **Component Isolation**: Keep component styles isolated and reusable
5. **Performance**: Minimize reflows and repaints

## Development

When adding new styles:

1. Check if the style can use existing utilities from `mpcc-common.css`
2. Use CSS variables for colors, spacing, and transitions
3. Follow the naming convention
4. Add appropriate comments for complex styles
5. Test across different browsers and screen sizes
6. Ensure accessibility compliance

## Browser Support

- Chrome/Edge: Latest 2 versions
- Firefox: Latest 2 versions
- Safari: Latest 2 versions
- Mobile browsers: iOS Safari, Chrome for Android

## Notes

- All animations respect `prefers-reduced-motion`
- Dark mode styles are included where applicable
- Print styles remove unnecessary UI elements
- RTL support is handled by WordPress core