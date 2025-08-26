# MemberPress Courses Copilot

AI-powered conversational course creation assistant for MemberPress Courses that reduces course development time from 6-10 hours to 10-30 minutes.

## Recent Updates (2025-08-26)

### Architecture Changes
- **Removed SessionService completely** - ConversationManager is now the single session handler
- **Fixed message history persistence** - Proper field mapping between frontend/backend
- **Fixed timestamp issues** - Timestamps only update on content changes, not on load
- **Disabled chat for published courses** - Published courses are now read-only

### Previous Updates (2025-08-21)
- Fixed AI response formatting to use proper CSS classes for better visual presentation
- Moved conversation management buttons (Previous Conversations/New Conversation) below the chat area
- Added course preview restoration when loading saved conversations
- Increased chat window vertical space for better usability
- See [STATUS.md](STATUS.md) for detailed technical status

## Overview

MemberPress Courses Copilot is a WordPress plugin that integrates directly with MemberPress Courses to provide an intelligent, conversational AI assistant for course creation. Using advanced language models through LiteLLM proxy infrastructure, it guides users through course development with natural language conversations.

## Features

### 🤖 Conversational Course Creation
- Natural language chat interface embedded in WordPress admin
- Intelligent questioning to gather course requirements
- Real-time course structure preview as you build
- Template-driven approach (Technical, Business, Creative, Academic)

### 📚 Intelligent Course Generation
- Complete course hierarchy (Course → Sections → Lessons)
- Learning objectives aligned with Bloom's taxonomy
- Content outlines and draft lesson text
- Assessment and quiz suggestions
- Pedagogical best practices built-in

### ✨ Advanced Capabilities
- Pattern recognition for successful course structures
- Institutional knowledge capture and sharing
- Content-aware AI provider routing
- Quality assurance and validation checks
- Mobile-responsive interface with voice input

### 🔧 Seamless Integration
- Direct integration with MemberPress Courses
- Uses existing course custom post types
- Compatible with drip content and certificates
- Maintains all existing MemberPress features

## Requirements

- WordPress 5.9 or higher
- PHP 8.0 or higher
- MemberPress plugin (active license)
- MemberPress Courses add-on
- LiteLLM proxy access (configured)

## Installation

1. Upload the `memberpress-courses-copilot` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to MemberPress → Courses → AI Copilot to configure settings
4. Enter your LiteLLM proxy credentials
5. Start creating courses with AI assistance!

## Configuration

### LiteLLM Proxy Settings
```php
// wp-config.php (optional)
define('MPCC_LITELLM_PROXY_URL', 'https://your-proxy-url.herokuapp.com');
define('MPCC_LITELLM_MASTER_KEY', 'your-master-key');
```

### Provider Configuration
The plugin intelligently routes requests to the optimal AI provider:
- **Anthropic Claude**: Course content, lesson writing, creative tasks
- **OpenAI GPT**: Structured data, quizzes, validation
- **DocsBot**: User help and documentation queries

## Usage

### Creating a Course
1. Go to **MemberPress → Courses**
2. Click **"Create with AI"** button
3. Choose a course template
4. Have a conversation with the AI about your course
5. Review and refine the generated structure
6. Click **"Create Course"** to generate in WordPress

### Workflow States
- **Initial**: Welcome and template selection
- **Gathering Info**: Topic, audience, objectives collection
- **Generating**: AI creates course outline
- **Reviewing**: Modify and approve structure
- **Refining**: Optional enhancement loop
- **Creating**: Generate WordPress course entities

## Development

### Project Structure
```
memberpress-courses-copilot/
├── src/MemberPressCoursesCopilot/
│   ├── Controllers/
│   ├── Services/
│   ├── Models/
│   ├── Admin/
│   └── Utilities/
├── assets/
├── templates/
└── docs/
```

### Coding Standards
This project follows:
- PSR-4 autoloading
- Caseproof-WP coding standard
- WordPress coding standards
- Modern PHP 8.0+ features

### Running Tests
```bash
composer install
composer run test
composer run cs-check
composer run cs-fix
```

## API Reference

### REST Endpoints
- `/wp-json/mpcc/v1/conversation` - Chat management
- `/wp-json/mpcc/v1/generate-course` - Course generation
- `/wp-json/mpcc/v1/templates` - Template operations
- `/wp-json/mpcc/v1/patterns` - Pattern matching

### Hooks & Filters

#### Actions
- `mpcc_course_generated` - Fired after course generation
- `mpcc_conversation_started` - New conversation initiated
- `mpcc_pattern_captured` - Successful pattern identified

#### Filters
- `mpcc_course_templates` - Modify available templates
- `mpcc_ai_providers` - Customize AI provider routing
- `mpcc_quality_checks` - Add custom validation rules

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure:
- All tests pass
- Code follows Caseproof-WP standards
- Documentation is updated
- Commits are descriptive

## Support

- **Documentation**: See `/docs` folder
- **Issues**: [GitHub Issues](https://github.com/sethshoultes/memberpress-courses-copilot/issues)
- **Community**: MemberPress community forums
- **Email**: support@memberpress.com

## Roadmap

### Phase 1: Foundation (Weeks 1-3) ✅
- Project setup and standards
- Basic WordPress integration

### Phase 2: AI Engine (Weeks 4-6) 🚧
- LiteLLM proxy integration
- Conversational interface

### Phase 3: Course Generation (Weeks 7-9) 📅
- Template system
- MemberPress integration

### Phase 4: Advanced Features (Weeks 10-12) 📅
- Pattern recognition
- Quality assurance

### Phase 5: Polish (Weeks 13-14) 📅
- Mobile optimization
- User experience refinement

## License

This plugin is proprietary software. All rights reserved by MemberPress.

## Credits

Developed by the MemberPress team with AI assistance.

---

**Note**: This plugin requires active MemberPress and MemberPress Courses licenses. Visit [memberpress.com](https://memberpress.com) for more information.