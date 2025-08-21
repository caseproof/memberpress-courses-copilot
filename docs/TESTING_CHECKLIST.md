# MemberPress Courses Copilot - Testing Checklist

## Before Every Change

### 1. Pre-Change Testing
- [ ] Open browser console and check for JavaScript errors
- [ ] Verify all buttons are visible and clickable:
  - [ ] Quick start buttons (Programming, Business, Creative Arts)
  - [ ] Send button
  - [ ] Previous Conversations button
  - [ ] New Conversation button
- [ ] Test basic chat functionality (send a message)
- [ ] Check session persistence (refresh page, message history should remain)

### 2. Common Breaking Points to Check

#### JavaScript Loading Issues
- [ ] Check if scripts are loading in correct order
- [ ] Verify no duplicate script loading
- [ ] Confirm AJAX localization variables are set

#### DOM Element Dependencies
- [ ] Verify all selectors exist before binding events
- [ ] Check for timing issues with AJAX-loaded content
- [ ] Ensure event delegation is used for dynamic content

#### Session Management
- [ ] Test Previous Conversations button opens list
- [ ] Test New Conversation button clears current session
- [ ] Verify session persistence across page reloads

## After Making Changes

### 1. Regression Testing
- [ ] All pre-change tests still pass
- [ ] No new console errors introduced
- [ ] All buttons still functional
- [ ] Chat messages still send and display correctly

### 2. Specific Areas by Change Type

#### CSS Changes
- [ ] Buttons remain visible
- [ ] Layout doesn't break on different screen sizes
- [ ] Z-index issues don't hide elements

#### JavaScript Changes
- [ ] Event handlers don't duplicate
- [ ] AJAX calls use proper URL and nonce
- [ ] Error handling doesn't break flow

#### PHP Template Changes
- [ ] Elements have correct IDs and classes
- [ ] JavaScript can find all required elements
- [ ] AJAX responses return expected format

## Debug Commands

```javascript
// Check if initialization completed
console.log('MPCC initialized:', window.MPCC?.initialized);

// Check for required elements
['#mpcc-chat-input', '#mpcc-send-message', '#mpcc-session-manager-btn'].forEach(sel => {
    console.log(sel, ':', jQuery(sel).length);
});

// Check AJAX configuration
console.log('AJAX config:', window.mpccAISettings);

// Manually trigger initialization
if (window.MPCC) window.MPCC.initialized = false;
jQuery(document).trigger('mpcc:interface-loaded');
```

## Common Fixes

### Buttons Not Working
1. Check console for JavaScript errors
2. Verify elements exist: `jQuery('#button-id').length`
3. Check event handlers: `jQuery._data(jQuery('#button-id')[0], 'events')`
4. Try manual initialization: `initializeUIComponents()`

### AJAX Errors
1. Check nonce: `jQuery('#mpcc-ajax-nonce').val()`
2. Verify AJAX URL: `window.mpccAISettings?.ajaxUrl || window.ajaxurl`
3. Check network tab for failed requests

### Session Issues
1. Check sessionStorage: `sessionStorage.getItem('mpcc_current_session_id')`
2. Verify conversation state: `window.mpccConversationHistory`
3. Check for dirty state: Look for unsaved changes warnings

## Prevention Strategies

1. **Use Event Delegation**: For dynamically loaded content
2. **Add Fallbacks**: Multiple ways to find AJAX URL and nonce
3. **Check Element Existence**: Before binding events
4. **Use Namespaced Events**: Prevent duplicate handlers
5. **Console Logging**: Add debug logging for troubleshooting
6. **Defensive Coding**: Check if functions exist before calling

## Release Checklist

- [ ] Clear browser cache and test fresh
- [ ] Test in Chrome, Firefox, Safari
- [ ] Test with WordPress debug mode on
- [ ] Check for console errors
- [ ] Verify all features work as expected
- [ ] Test on slow connection (throttle in DevTools)
- [ ] Document any new dependencies or requirements