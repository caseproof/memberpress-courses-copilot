# MemberPress Courses Copilot Documentation

Welcome to the MemberPress Courses Copilot documentation. This directory contains comprehensive documentation for developers, administrators, and contributors.

## Quick Start

- [Installation & Configuration](../README.md#installation)
- [API Reference](API.md)
- [Architecture Overview](ARCHITECTURE.md)

## Documentation Structure

### Core Documentation

- **[API.md](API.md)** - Complete AJAX endpoint reference with examples
- **[ARCHITECTURE.md](ARCHITECTURE.md)** - System design, components, and data flow
- **[AUTH_GATEWAY_CONFIGURATION.md](AUTH_GATEWAY_CONFIGURATION.md)** - Authentication gateway setup
- **[STYLE_GUIDE.md](STYLE_GUIDE.md)** - Coding standards and best practices

### Development Guides

- **[BUILD_CONFIG.md](BUILD_CONFIG.md)** - Build process and asset compilation
- **[JAVASCRIPT_EXTRACTION_SUMMARY.md](JAVASCRIPT_EXTRACTION_SUMMARY.md)** - Frontend architecture
- **[PERFORMANCE_OPTIMIZATIONS.md](PERFORMANCE_OPTIMIZATIONS.md)** - Performance best practices
- **[ERROR_HANDLING_STANDARDIZATION.md](ERROR_HANDLING_STANDARDIZATION.md)** - Error handling patterns

### Testing & Quality

- **[AI_CHAT_TEST_CHECKLIST.md](AI_CHAT_TEST_CHECKLIST.md)** - Manual testing procedures
- **[code_reviews/](code_reviews/)** - Code review history and findings
- **[../tests/README.md](../tests/README.md)** - Automated testing guide

### Implementation Plans

- **[planning/](planning/)** - Original project planning documents
- **[completed/](completed/)** - Completed feature documentation
- **[todo/](todo/)** - Pending features and improvements

## Key Concepts

### Service Architecture
The plugin uses a service-oriented architecture with dependency injection. Key services include:
- **LLMService** - AI integration through auth gateway
- **ConversationManager** - Session and state management
- **CourseGeneratorService** - WordPress course creation
- **LessonDraftService** - Content draft management

### Security Model
- All AJAX endpoints require nonce verification
- Capabilities-based permission system
- API keys stored securely on gateway server
- Input sanitization and output escaping

### Data Flow
1. User interaction → JavaScript frontend
2. AJAX request → Controller validation
3. Service processing → AI/Database operations
4. Response formatting → Frontend update
5. State persistence → Session storage

## Common Tasks

### Adding a New AJAX Endpoint
1. Add handler method to appropriate controller
2. Register action in controller's `init()` method
3. Implement nonce verification and capability checks
4. Document in [API.md](API.md)

### Extending AI Capabilities
1. Update prompt engineering in service methods
2. Add new content type routing if needed
3. Test with various inputs
4. Update documentation

### Debugging Issues
1. Enable debug mode in `wp-config.php`
2. Check logs in `/wp-content/debug.log`
3. Use browser developer tools for JavaScript
4. Review [ERROR_HANDLING_STANDARDIZATION.md](ERROR_HANDLING_STANDARDIZATION.md)

## Contributing

When adding or updating documentation:
1. Keep it simple and practical
2. Include code examples where helpful
3. Update the table of contents if adding files
4. Follow the existing format and style
5. Don't create documentation that won't be maintained

## Support

For questions not covered in the documentation:
- Review code comments and PHPDoc blocks
- Check the [completed/](completed/) folder for historical context
- Contact the development team