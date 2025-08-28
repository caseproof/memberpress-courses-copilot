# MemberPress Courses Copilot

AI-powered conversational course creation assistant for MemberPress Courses that reduces course development time from 6-10 hours to 10-30 minutes.

## Overview

MemberPress Courses Copilot is a WordPress plugin that integrates directly with MemberPress Courses to provide an intelligent, conversational AI assistant for course creation. Using advanced language models through a secure authentication gateway, it guides users through course development with natural language conversations.

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
- Authentication gateway access (automatically configured)

## Installation

1. Upload the `memberpress-courses-copilot` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to MemberPress → Courses → AI Copilot
4. The plugin automatically connects to the authentication gateway
5. Start creating courses with AI assistance!

## Configuration

### Auth Gateway Configuration
The plugin uses a secure authentication gateway to access AI services. By default, it connects to the production gateway, but you can customize this for development:

```php
// wp-config.php (optional)
define('MPCC_AUTH_GATEWAY_URL', 'https://your-custom-gateway-url.com');
```

For detailed configuration options, see [Auth Gateway Configuration](docs/AUTH_GATEWAY_CONFIGURATION.md).

### Development Mode
For development environments, you can override the authentication gateway URL:

```php
// wp-config.php
define('MPCC_AUTH_GATEWAY_URL', 'http://localhost:5000');
```

## Usage

### Creating a Course
1. Go to **MemberPress → Courses → AI Copilot**
2. Click **"New Conversation"** to start
3. Describe your course idea in natural language
4. The AI will ask clarifying questions and suggest a course structure
5. Preview and edit individual lessons in the course preview
6. Click **"Create Course"** when satisfied with the structure

### Managing Conversations
- **Save Progress**: Your conversations are automatically saved
- **Previous Conversations**: Load and continue past course designs
- **Duplicate Course**: Create variations of existing course structures
- **Edit Lessons**: Use the preview editor to customize content before creation

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

### Building Assets
```bash
npm install
npm run build        # Production build
npm run dev          # Development build
npm run watch        # Watch for changes
```

### Running Tests
```bash
composer install
composer run test
composer run cs-check
composer run cs-fix
```

### Database Tables
The plugin creates these custom tables:
- `{prefix}_mpcc_conversations` - Stores conversation sessions
- `{prefix}_mpcc_lesson_drafts` - Stores lesson content drafts

## API Reference

For detailed API documentation, see [docs/API.md](docs/API.md).

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

## Troubleshooting

### Common Issues

**AI not responding**
- Check WordPress error logs for connection issues
- Verify AUTH_GATEWAY_URL is accessible
- Check browser console for JavaScript errors

**Course creation fails**
- Ensure MemberPress Courses is active
- Verify user has `publish_posts` capability
- Check for conflicting plugins

**Sessions not saving**
- Verify database tables were created
- Check write permissions on database
- Clear browser cache and cookies

## Support

- **Documentation**: See `/docs` folder
- **Community**: MemberPress community forums
- **Email**: support@memberpress.com

## License

This plugin is proprietary software. All rights reserved by MemberPress.

## Credits

Developed by the MemberPress team with AI assistance.

---

**Note**: This plugin requires active MemberPress and MemberPress Courses licenses. Visit [memberpress.com](https://memberpress.com) for more information.