# MemberPress Courses Copilot - Frontend Assets Documentation

This directory contains all the frontend assets for the MemberPress Courses Copilot plugin, providing a comprehensive AI-powered course creation interface.

## Overview

The frontend assets include:

- **JavaScript Files**: Interactive functionality for chat interface, course preview, and admin settings
- **CSS Files**: Modern, responsive styling with dark/light theme support
- **Documentation**: Comprehensive guides for developers and users

## File Structure

```
assets/
├── css/
│   ├── admin.css          # Legacy admin styles (existing)
│   └── ai-copilot.css     # Modern AI copilot interface styles
├── js/
│   ├── course-generator.js # Legacy course generator (existing)
│   ├── ai-copilot.js      # Enhanced AI chat interface
│   └── admin-settings.js  # Settings page functionality
└── README.md              # This documentation file
```

## JavaScript Files

### 1. ai-copilot.js

**Purpose**: Main AI chat interface with advanced features

**Key Features**:
- Real-time chat interface with typing indicators
- Voice input support (Web Speech API + MediaRecorder)
- Drag-and-drop course structure reordering
- Progress tracking with visual indicators
- Session persistence and conversation history
- Dark/light theme support
- Responsive design for mobile devices
- Keyboard shortcuts and accessibility features
- Auto-save functionality
- Connection status monitoring

**Classes**:
- `AICopilot`: Main class handling all chat functionality

**Dependencies**:
- jQuery
- WordPress admin scripts
- Modern browser APIs (Speech Recognition, MediaRecorder)

**Usage**:
```javascript
// Auto-initializes on document ready if chat interface elements are present
$(document).ready(function() {
    if ($('#mpcc-chat-messages').length > 0) {
        window.mpccCopilot = new AICopilot();
    }
});
```

**Key Methods**:
- `init()`: Initialize the interface
- `handleSendMessage()`: Process user messages
- `generateAIResponse()`: Send requests to AI backend
- `updateCoursePreview()`: Update course structure display
- `startVoiceRecording()`: Handle voice input
- `updateProgress()`: Track creation progress

### 2. admin-settings.js

**Purpose**: Settings page functionality with real-time testing

**Key Features**:
- Real-time connection testing for LiteLLM proxy
- Form validation with instant feedback
- Provider status monitoring
- Auto-save functionality
- Settings import/export
- Debug tools and logging
- Progress indicators for long operations
- Comprehensive error handling

**Classes**:
- `AdminSettings`: Main settings management class

**Key Methods**:
- `handleTestConnection()`: Test LiteLLM connection
- `validateConnectionSettings()`: Real-time validation
- `loadProviders()`: Load and display provider status
- `autoSaveSettings()`: Automatic settings backup

### 3. course-generator.js (Legacy)

**Purpose**: Original course generator functionality (maintained for compatibility)

**Note**: This file is kept for backward compatibility. New features should use `ai-copilot.js`.

## CSS Files

### 1. ai-copilot.css

**Purpose**: Modern, comprehensive styling for the AI copilot interface

**Key Features**:
- CSS Custom Properties for easy theming
- Dark/light theme support
- Mobile-first responsive design
- Modern animations and transitions
- Accessibility support (reduced motion, high contrast)
- Print styles
- Comprehensive component library

**Theming System**:
```css
/* Light theme (default) */
:root {
  --mpcc-primary: #0073aa;
  --mpcc-bg-primary: #ffffff;
  --mpcc-text-primary: #2c3338;
  /* ... */
}

/* Dark theme */
.mpcc-theme-dark {
  --mpcc-bg-primary: #1e1e1e;
  --mpcc-text-primary: #e0e0e0;
  /* ... */
}
```

**Components Styled**:
- Chat interface (messages, input, typing indicators)
- Course preview panel (sections, lessons, drag-drop)
- Progress tracking system
- Template selection cards
- Notifications and modals
- Voice input controls
- Connection status indicators

**Responsive Breakpoints**:
- Desktop: 1200px+
- Tablet: 768px - 1199px
- Mobile: < 768px
- Small mobile: < 480px

### 2. admin.css (Legacy)

**Purpose**: Original admin styling (maintained for compatibility)

## Browser Support

### Minimum Requirements
- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

### Progressive Enhancement Features
- **Voice Input**: Requires Web Speech API or MediaRecorder support
- **Drag and Drop**: HTML5 drag/drop API
- **Advanced CSS**: CSS Grid, Custom Properties, modern animations

### Fallbacks
- Voice input gracefully degrades to text-only input
- Animations can be disabled for users with `prefers-reduced-motion`
- CSS Grid falls back to flexbox where needed

## Performance Considerations

### JavaScript
- Lazy loading of non-critical features
- Debounced input validation
- Efficient DOM manipulation
- Memory leak prevention

### CSS
- Minimal repaints and reflows
- Hardware-accelerated animations
- Optimized selector specificity
- Compressed assets in production

## Accessibility Features

### WCAG 2.1 AA Compliance
- Proper heading structure
- Keyboard navigation support
- Screen reader compatibility
- Color contrast ratios
- Focus indicators
- Alternative text for icons

### Keyboard Shortcuts
- `Ctrl/Cmd + Enter`: Send message
- `Ctrl/Cmd + K`: Clear chat
- `Ctrl/Cmd + S`: Save draft
- `Ctrl/Cmd + /`: Focus chat input
- `Tab`: Navigate through elements

### Screen Reader Support
- ARIA labels and descriptions
- Live regions for dynamic content
- Semantic HTML structure
- Alternative text for visual indicators

## Theme Customization

### CSS Custom Properties
The styling system uses CSS custom properties for easy customization:

```css
/* Custom theme example */
.mpcc-theme-custom {
  --mpcc-primary: #your-brand-color;
  --mpcc-bg-primary: #your-background;
  --mpcc-text-primary: #your-text-color;
}
```

### JavaScript Theme API
```javascript
// Toggle theme
mpccCopilot.handleThemeToggle();

// Set specific theme
mpccCopilot.currentTheme = 'dark';
mpccCopilot.setupTheme();
```

## API Integration

### AJAX Endpoints
The JavaScript files integrate with these WordPress AJAX actions:

1. **mpcc_generate_response**: Generate AI responses
2. **mpcc_test_connection**: Test LiteLLM connection
3. **mpcc_save_settings**: Save plugin settings
4. **mpcc_get_providers**: Get provider status
5. **mpcc_ping_connection**: Health check ping

### Request Format
```javascript
const requestData = {
    action: 'mpcc_generate_response',
    nonce: mpccAdmin.nonce,
    message: userMessage,
    template: selectedTemplate,
    chat_history: chatHistory,
    current_course: currentCourse,
    session_id: sessionId
};
```

### Response Format
```javascript
{
    success: true,
    data: {
        message: "AI response text",
        course: { /* course structure */ },
        progress: { step: 3, percentage: 60 },
        suggestions: ["suggestion 1", "suggestion 2"],
        actions: ["action1", "action2"]
    }
}
```

## Error Handling

### User-Friendly Error Messages
- Connection timeouts: "Request took too long"
- Rate limits: "Too many requests, please wait"
- Network errors: "Please check your connection"
- Server errors: "Server issue, our team has been notified"

### Developer Error Information
- Console logging for debugging
- Detailed error objects
- Stack traces in development mode
- Error reporting integration

## Security Considerations

### Input Sanitization
- All user inputs are escaped before display
- XSS prevention through proper encoding
- CSRF protection via WordPress nonces

### Data Validation
- Client-side validation for UX
- Server-side validation for security
- Input length limits
- URL validation

## Performance Monitoring

### Metrics Tracked
- Chat response times
- Connection test durations
- Voice recording quality
- UI interaction delays

### Optimization Techniques
- Lazy loading of features
- Image optimization
- Code splitting
- Caching strategies

## Development Guidelines

### Code Standards
- ESLint configuration for JavaScript
- Stylelint for CSS
- JSDoc comments for documentation
- Semantic versioning

### Testing Approach
- Unit tests for core functions
- Integration tests for AJAX calls
- Accessibility testing
- Cross-browser testing

### Build Process
- Webpack for JavaScript bundling
- PostCSS for CSS processing
- Minification for production
- Source maps for debugging

## Troubleshooting

### Common Issues

1. **Voice input not working**
   - Check browser permissions
   - Verify HTTPS connection
   - Test microphone access

2. **Drag and drop not responsive**
   - Ensure proper event binding
   - Check for JavaScript errors
   - Verify browser support

3. **Theme not switching**
   - Check localStorage permissions
   - Verify CSS custom property support
   - Clear browser cache

4. **Connection test failing**
   - Verify LiteLLM proxy URL
   - Check API key validity
   - Test network connectivity

### Debug Mode
Enable debug mode by adding to wp-config.php:
```php
define('MPCC_DEBUG', true);
```

This enables:
- Verbose console logging
- Detailed error messages
- Performance timing information
- Network request debugging

## Contributing

### Adding New Features
1. Follow existing code patterns
2. Add proper documentation
3. Include accessibility considerations
4. Test across browsers
5. Update this README

### Reporting Issues
When reporting issues, include:
- Browser and version
- Steps to reproduce
- Console errors
- Expected vs actual behavior

## License

This code is part of the MemberPress Courses Copilot plugin and is subject to the plugin's license terms.

## Changelog

### Version 1.0.0
- Initial release
- Complete AI copilot interface
- Dark/light theme support
- Voice input functionality
- Drag-and-drop course editing
- Comprehensive admin settings
- Mobile-responsive design
- Accessibility features