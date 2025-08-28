# CSS Migration Guide

This guide helps you migrate from the old CSS structure to the new optimized architecture.

## Quick Start

### 1. Update CSS Imports in PHP

Replace all individual CSS file imports with the main stylesheet:

```php
// OLD - Remove these
wp_enqueue_style('mpcc-common', $css_url . 'mpcc-common.css', [], MPCC_VERSION);
wp_enqueue_style('mpcc-ai-copilot', $css_url . 'ai-copilot.css', [], MPCC_VERSION);
wp_enqueue_style('mpcc-toast', $css_url . 'toast.css', [], MPCC_VERSION);
// ... other individual files

// NEW - Use this instead
wp_enqueue_style('mpcc-main', $css_url . 'mpcc-main.css', [], MPCC_VERSION);
```

### 2. Update HTML Classes

The following table shows common class mappings:

| Old Class | New Class | Notes |
|-----------|-----------|-------|
| `mpcc-spacing-sm` | `mpcc-p-2` | Use specific spacing utilities |
| `mpcc-spacing-md` | `mpcc-p-4` | |
| `mpcc-spacing-lg` | `mpcc-p-6` | |
| `mpcc-mt-sm` | `mpcc-mt-2` | |
| `mpcc-mb-md` | `mpcc-mb-4` | |
| `mpcc-chat-message` | `mpcc-message` | |
| `mpcc-chat-messages` | `mpcc-ai-interface__messages` | |
| `mpcc-chat-input` | `mpcc-form-textarea mpcc-chat-input` | |
| `mpcc-btn--primary` | `mpcc-btn mpcc-btn--primary` | Ensure base class is included |
| `mpcc-loading` | `mpcc-loading` | Unchanged |
| `mpcc-spinner` | `mpcc-spinner` | Unchanged |

### 3. Component Updates

#### Buttons
```html
<!-- OLD -->
<button class="button button-primary">Submit</button>

<!-- NEW -->
<button class="mpcc-btn mpcc-btn--primary">Submit</button>
```

#### Cards
```html
<!-- OLD -->
<div class="mpcc-settings-card">
  <h3>Title</h3>
  <p>Content</p>
</div>

<!-- NEW -->
<div class="mpcc-card">
  <div class="mpcc-card__header">
    <h3 class="mpcc-card__title">Title</h3>
  </div>
  <div class="mpcc-card__body">
    <p>Content</p>
  </div>
</div>
```

#### Forms
```html
<!-- OLD -->
<input type="text" class="regular-text" />
<textarea class="mpcc-chat-input"></textarea>

<!-- NEW -->
<input type="text" class="mpcc-form-input" />
<textarea class="mpcc-form-textarea mpcc-chat-input"></textarea>
```

#### Messages
```html
<!-- OLD -->
<div class="mpcc-chat-message user">
  <div class="message-content">Hello</div>
</div>

<!-- NEW -->
<div class="mpcc-message mpcc-message--user">
  <div class="mpcc-message__avatar">
    <span class="dashicons dashicons-admin-users"></span>
  </div>
  <div class="mpcc-message__content">Hello</div>
</div>
```

### 4. Layout Updates

#### AI Chat Interface
```html
<!-- OLD -->
<div class="mpcc-chat-interface">
  <div class="mpcc-chat-messages">...</div>
  <div class="mpcc-chat-input-container">...</div>
</div>

<!-- NEW -->
<div class="mpcc-ai-interface">
  <div class="mpcc-ai-interface__header">...</div>
  <div class="mpcc-ai-interface__messages">...</div>
  <div class="mpcc-ai-interface__input-area">...</div>
  <div class="mpcc-ai-interface__controls">...</div>
</div>
```

#### Modal
```html
<!-- OLD -->
<div class="mpcc-modal-overlay">
  <div class="mpcc-modal">
    <div class="mpcc-modal-header">...</div>
    <div class="mpcc-modal-body">...</div>
  </div>
</div>

<!-- NEW -->
<div class="mpcc-modal-backdrop mpcc-modal-backdrop--active">
  <div class="mpcc-modal mpcc-modal--active">
    <div class="mpcc-modal__header">
      <h2 class="mpcc-modal__title">Title</h2>
      <button class="mpcc-modal__close">×</button>
    </div>
    <div class="mpcc-modal__body">...</div>
    <div class="mpcc-modal__footer">...</div>
  </div>
</div>
```

### 5. Utility Usage

Instead of custom CSS, use utility classes:

```html
<!-- OLD -->
<div style="margin-top: 20px; padding: 10px;">

<!-- NEW -->
<div class="mpcc-mt-5 mpcc-p-3">

<!-- OLD -->
<div style="display: flex; gap: 10px; align-items: center;">

<!-- NEW -->
<div class="mpcc-flex mpcc-gap-3 mpcc-items-center">

<!-- OLD -->
<div style="display: none;">

<!-- NEW -->
<div class="mpcc-hidden">
```

### 6. JavaScript Updates

Update selectors in JavaScript files:

```javascript
// OLD
document.querySelector('.mpcc-chat-messages');
document.querySelector('.mpcc-chat-input');

// NEW
document.querySelector('.mpcc-ai-interface__messages');
document.querySelector('.mpcc-form-textarea.mpcc-chat-input');

// OLD - Multiple classes
element.classList.add('mpcc-btn--primary');

// NEW - Ensure base class
element.classList.add('mpcc-btn', 'mpcc-btn--primary');
```

### 7. CSS Variable Updates

Update any custom CSS using variables:

```css
/* OLD */
.custom-element {
  padding: var(--mpcc-spacing-md);
  color: var(--mpcc-text-primary);
}

/* NEW - Same variable names work */
.custom-element {
  padding: var(--mpcc-spacing-4); /* or keep --mpcc-spacing-md alias */
  color: var(--mpcc-text-primary);
}
```

## Testing Checklist

After migration, test the following:

- [ ] All buttons have proper styling and hover states
- [ ] Forms are properly styled with focus states
- [ ] Modals open/close with proper backdrop
- [ ] Chat interface scrolls properly
- [ ] Messages display with correct alignment
- [ ] Toast notifications appear correctly
- [ ] Loading states show spinners
- [ ] Responsive layout works on mobile
- [ ] Dark mode (if implemented) works
- [ ] Print styles hide unnecessary elements
- [ ] Accessibility features (focus, screen readers) work

## Gradual Migration

You can migrate gradually:

1. **Phase 1**: Import both old and new styles temporarily
   ```php
   wp_enqueue_style('mpcc-main', $css_url . 'mpcc-main.css', [], MPCC_VERSION);
   // Keep old files temporarily for testing
   ```

2. **Phase 2**: Update HTML classes file by file
   - Start with admin pages
   - Then frontend components
   - Finally, JavaScript-heavy sections

3. **Phase 3**: Remove old CSS imports
   - Test thoroughly
   - Remove old CSS file imports
   - Delete unused CSS files

## Common Issues

### Spacing looks different
- Old spacing variables had different values
- Adjust using the spacing scale (1-16)

### Buttons too small/large
- Add size modifiers: `mpcc-btn--small`, `mpcc-btn--large`
- Or adjust padding with utilities: `mpcc-px-6 mpcc-py-3`

### Missing styles
- Check if component needs base class + modifier
- Some WordPress admin styles may need `!important`

### JavaScript not finding elements
- Update selectors to use new class names
- Use data attributes for JS hooks if needed

## Help & Support

If you encounter issues:

1. Check browser console for CSS errors
2. Verify all new CSS files are loading
3. Use browser DevTools to inspect applied styles
4. Check the README.md for component documentation

Remember: The new system is more maintainable and performant. The migration effort will pay off in easier future development!