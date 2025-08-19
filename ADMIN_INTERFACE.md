# MemberPress Courses Copilot - Admin Interface

## Overview

The admin interface provides a comprehensive solution for AI-powered course creation with the following key components:

## File Structure

```
src/MemberPressCoursesCopilot/Admin/
├── AdminMenu.php          # Main admin menu integration
└── SettingsPage.php       # Settings page handler

templates/admin/
├── course-generator.php   # Course generation interface
└── settings.php          # Settings page template

assets/
├── css/admin.css         # Admin interface styles
└── js/course-generator.js # Course generation JavaScript
```

## Components

### 1. AdminMenu.php
- **Purpose**: Integrates with MemberPress admin menu structure
- **Features**:
  - Adds "AI Copilot" submenu under MemberPress Courses
  - Proper capability checks (`edit_courses`)
  - Asset enqueuing for admin pages
  - Course template configuration

### 2. SettingsPage.php
- **Purpose**: Handles LiteLLM proxy configuration
- **Features**:
  - Form handling with WordPress nonces
  - Settings validation and sanitization
  - Connection testing functionality
  - WordPress options API integration

### 3. Course Generator Interface (course-generator.php)
- **Purpose**: Dual-panel AI conversation interface
- **Features**:
  - **Left Panel**: Chat interface with template selection
  - **Right Panel**: Real-time course preview
  - Course template cards (Technical, Business, Creative, Academic)
  - Responsive design for mobile/tablet

### 4. Settings Interface (settings.php)
- **Purpose**: Configuration management
- **Features**:
  - LiteLLM proxy settings form
  - Connection status indicators
  - System requirements check
  - Documentation links sidebar

## Key Features

### Template System
Four pre-configured course templates:
- **Technical Training**: Programming, software, technical skills
- **Business & Professional**: Management, professional development
- **Creative Arts**: Design, arts, music, creative skills
- **Academic & Educational**: Academic subjects, research

### Chat Interface
- Real-time AI conversation
- Message history persistence (sessionStorage)
- Typing indicators
- Word count tracking
- Template-based responses

### Course Preview
- Live course structure preview
- Section and lesson breakdown
- Learning objectives display
- Course metadata (duration, difficulty, lesson count)

### Settings Management
- **Proxy URL**: LiteLLM proxy server endpoint
- **Master Key**: Authentication credentials
- **Timeout**: Request timeout configuration (10-300 seconds)
- **Temperature**: AI creativity setting (0.0-2.0)

## WordPress Integration

### Menu Structure
```
MemberPress
├── Courses
│   ├── AI Copilot          # Course generator (edit_courses capability)
│   └── AI Copilot Settings # Settings page (manage_options capability)
```

### Capability Requirements
- **Course Generation**: `edit_courses` capability
- **Settings Management**: `manage_options` capability
- **Dependency Check**: Requires MemberPress Courses active

### Asset Management
- **CSS**: Enqueued with `wp-components` dependency
- **JavaScript**: Includes WordPress API dependencies (`wp-element`, `wp-components`, `wp-api-fetch`)
- **Localization**: Full i18n support with `memberpress-courses-copilot` text domain

## Security Features

### Input Validation
- URL validation for proxy settings
- Numeric range validation for timeout/temperature
- Nonce verification for all form submissions
- Capability checks before page access

### Data Sanitization
- `esc_url_raw()` for URLs
- `sanitize_text_field()` for text inputs
- `esc_attr()` and `esc_html()` for output

### Error Handling
- WordPress error objects for API failures
- User-friendly error messages
- Connection testing with timeout handling

## UI/UX Features

### Responsive Design
- Mobile-first approach
- Flexible grid layouts
- Touch-friendly interface
- Adaptive navigation

### WordPress Admin Consistency
- Native WordPress admin styles
- Dashicons integration
- Standard button patterns
- WordPress color scheme compliance

### Real-time Feedback
- Loading states with animations
- Progress indicators
- Status notifications
- Connection status displays

## JavaScript Architecture

### Course Generator (course-generator.js)
- **Class-based structure**: `CourseGenerator` main class
- **Event handling**: Template selection, chat input, form submission
- **AJAX integration**: WordPress AJAX with nonce security
- **State management**: Chat history, course data, template selection
- **Session persistence**: SessionStorage for chat continuity

### Key Methods
- `handleTemplateSelection()`: Template card interaction
- `generateAIResponse()`: AJAX communication with backend
- `updateCoursePreview()`: Real-time preview updates
- `saveChatHistory()`: Session persistence
- `handleBeforeUnload()`: Unsaved changes warning

## Configuration Constants

The interface expects these constants to be defined:
- `MPCC_PLUGIN_DIR`: Plugin directory path
- `MPCC_PLUGIN_URL`: Plugin URL for assets
- `MPCC_VERSION`: Plugin version for cache busting

## Future Enhancements

### Planned Features
- React/Vue.js app integration (mount point prepared)
- Advanced template customization
- Course export/import functionality
- Collaborative editing features
- Advanced analytics dashboard

### Integration Points
- `#mpcc-app-root`: Reserved for React/Vue app mounting
- Template system ready for dynamic loading
- Extensible AJAX endpoint structure
- Plugin-ready architecture for additional features

This admin interface provides a solid foundation for AI-powered course creation while maintaining WordPress standards and providing an intuitive user experience.