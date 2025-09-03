# MemberPress Courses Copilot Documentation

Welcome to the MemberPress Courses Copilot documentation. This directory contains comprehensive documentation for developers, administrators, and contributors working with the AI-powered course creation plugin.

## Quick Start

### For Developers
1. **[Developer Guide](DEVELOPER_GUIDE.md)** - Complete development guide
2. **[API Reference](API_REFERENCE.md)** - Comprehensive API documentation
3. **[Service Layer](SERVICE_LAYER.md)** - Understanding the service architecture
4. **[Integration Guide](INTEGRATION_GUIDE.md)** - MemberPress integration details

### For Administrators
- [Installation & Configuration](../README.md#installation)
- [Auth Gateway Configuration](AUTH_GATEWAY_CONFIGURATION.md)
- [Troubleshooting Guide](TROUBLESHOOTING.md)

### For Contributors
- [Style Guide](STYLE_GUIDE.md)
- [Testing Guide](../tests/README.md)
- [Code Review Process](code_reviews/CODE_REVIEW.md)

## Documentation Structure

### Essential Reading

| Document | Purpose | Audience |
|----------|---------|----------|
| **[DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md)** | Complete development guide with architecture, workflows, and best practices | Developers |
| **[API_REFERENCE.md](API_REFERENCE.md)** | Comprehensive API documentation with examples and error codes | Developers, Integrators |
| **[SERVICE_LAYER.md](SERVICE_LAYER.md)** | Service architecture, dependency injection, and testing | Developers |
| **[DATA_FLOW.md](DATA_FLOW.md)** | Data flow patterns and state management | Developers |
| **[INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md)** | MemberPress integration points and WordPress hooks | Developers, Integrators |

### Core Documentation

#### Architecture & Design
- **[ARCHITECTURE.md](ARCHITECTURE.md)** - System design and component overview
- **[SERVICE_LAYER.md](SERVICE_LAYER.md)** - Service-oriented architecture details
- **[DATA_FLOW.md](DATA_FLOW.md)** - Data flow and state management patterns

#### API Documentation
- **[API_REFERENCE.md](API_REFERENCE.md)** - Complete API reference (⭐ **Primary API Docs**)
- **[API.md](API.md)** - Quick API overview and reference
- **[API_QUIZ.md](API_QUIZ.md)** - Quiz-specific API endpoints

#### Integration & Configuration
- **[INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md)** - WordPress and MemberPress integration
- **[AUTH_GATEWAY_CONFIGURATION.md](AUTH_GATEWAY_CONFIGURATION.md)** - Authentication setup
- **[NONCE_SECURITY.md](NONCE_SECURITY.md)** - Security implementation details

### Development Resources

#### Code Quality & Standards
- **[STYLE_GUIDE.md](STYLE_GUIDE.md)** - Coding standards and conventions
- **[ERROR_HANDLING_STANDARDIZATION.md](ERROR_HANDLING_STANDARDIZATION.md)** - Error handling patterns
- **[CLI_SECURITY.md](CLI_SECURITY.md)** - Command-line security practices

#### Build & Deployment
- **[BUILD_CONFIG.md](BUILD_CONFIG.md)** - Build process and asset compilation
- **[EXTENDING.md](EXTENDING.md)** - Plugin extension guidelines

#### Performance & Security
- **[RATE_LIMITING_IMPLEMENTATION_PLAN.md](RATE_LIMITING_IMPLEMENTATION_PLAN.md)** - Rate limiting strategy
- **[NONCE_SECURITY.md](NONCE_SECURITY.md)** - Security best practices

### Feature-Specific Documentation

#### Quiz System
- **[QUIZ_IMPLEMENTATION_GUIDE.md](QUIZ_IMPLEMENTATION_GUIDE.md)** - Quiz system implementation
- **[QUIZ_INTEGRATION_TECHNICAL.md](QUIZ_INTEGRATION_TECHNICAL.md)** - Technical quiz integration
- **[QUIZ_QUESTION_TYPES_IMPLEMENTATION.md](QUIZ_QUESTION_TYPES_IMPLEMENTATION.md)** - Question type system
- **[QUIZ_USER_GUIDE.md](QUIZ_USER_GUIDE.md)** - User guide for quiz features
- **[QUIZ_SAVE_FLOW_ANALYSIS.md](QUIZ_SAVE_FLOW_ANALYSIS.md)** - Quiz save workflow analysis

### Testing & Quality Assurance

#### Testing Documentation
- **[AI_CHAT_TEST_CHECKLIST.md](AI_CHAT_TEST_CHECKLIST.md)** - Manual testing procedures
- **[../tests/README.md](../tests/README.md)** - Automated testing guide
- **[../tests/COVERAGE_REPORT.md](../tests/COVERAGE_REPORT.md)** - Test coverage analysis

#### Code Reviews
- **[code_reviews/](code_reviews/)** - Code review history and findings
- **[code_reviews/COMPREHENSIVE_CODE_REVIEW_REPORT.md](code_reviews/COMPREHENSIVE_CODE_REVIEW_REPORT.md)** - Latest review

### Project Documentation

#### Planning & Implementation
- **[completed/](completed/)** - Completed feature documentation
- **[planning/](planning/)** - Original project planning documents
- **[todo/](todo/)** - Pending features and improvements

#### Design Resources
- **[design/](design/)** - UI/UX design documentation and ASCII diagrams

## Navigation Guide

### New to the Project?
1. Start with **[DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md)** for complete architecture overview
2. Review **[API_REFERENCE.md](API_REFERENCE.md)** for endpoint documentation
3. Understand **[SERVICE_LAYER.md](SERVICE_LAYER.md)** for service architecture
4. Check **[INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md)** for MemberPress integration

### Working on Specific Features?
- **AI Chat System**: [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) + [DATA_FLOW.md](DATA_FLOW.md)
- **Quiz Generation**: [API_QUIZ.md](API_QUIZ.md) + [QUIZ_IMPLEMENTATION_GUIDE.md](QUIZ_IMPLEMENTATION_GUIDE.md)
- **Course Creation**: [ARCHITECTURE.md](ARCHITECTURE.md) + [DATA_FLOW.md](DATA_FLOW.md)
- **Database Operations**: [SERVICE_LAYER.md](SERVICE_LAYER.md) + [ARCHITECTURE.md](ARCHITECTURE.md)

### Need Integration Help?
- **WordPress Hooks**: [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md)
- **MemberPress Integration**: [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md)
- **Frontend Integration**: [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#frontend-architecture)
- **API Integration**: [API_REFERENCE.md](API_REFERENCE.md)

## Key Concepts

### Service Architecture
The plugin uses a service-oriented architecture with dependency injection. For detailed information, see [SERVICE_LAYER.md](SERVICE_LAYER.md).

**Core Services:**
- **LLMService** - AI integration through auth gateway ([DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#llmservice))
- **ConversationManager** - Session and state management ([SERVICE_LAYER.md](SERVICE_LAYER.md#conversationmanager-implementation))
- **CourseGeneratorService** - WordPress course creation ([DATA_FLOW.md](DATA_FLOW.md#course-creation-workflow))
- **MpccQuizAIService** - AI-powered quiz generation ([API_QUIZ.md](API_QUIZ.md))

### Security Model
Comprehensive security implementation detailed in [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md#security-model):
- Nonce verification for all AJAX endpoints
- Capabilities-based permission system
- API keys stored securely on gateway server
- Input sanitization and output escaping

### Data Flow
Complete data flow documentation in [DATA_FLOW.md](DATA_FLOW.md):
1. User interaction → JavaScript frontend
2. AJAX request → Controller validation  
3. Service processing → AI/Database operations
4. Response formatting → Frontend update
5. State persistence → Session storage

## Common Development Tasks

### Adding a New AJAX Endpoint
See [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#adding-new-features) for complete guide:
1. Define endpoint in appropriate controller
2. Register AJAX action in controller's `init()` method
3. Implement security checks and validation
4. Document in [API_REFERENCE.md](API_REFERENCE.md)

### Extending AI Capabilities
Detailed in [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md#adding-custom-ai-providers):
1. Implement `ILLMService` interface
2. Register in dependency injection container
3. Update prompt engineering as needed
4. Add tests and documentation

### Debugging Issues
Complete debugging guide in [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#debugging-guide):
1. Enable debug mode in `wp-config.php`
2. Check logs (locations in debugging guide)
3. Use browser developer tools for JavaScript
4. Review [ERROR_HANDLING_STANDARDIZATION.md](ERROR_HANDLING_STANDARDIZATION.md)

### Testing Changes
Testing strategy in [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#testing-strategy):
1. Run unit tests: `./vendor/bin/phpunit`
2. Check manual test checklist: [AI_CHAT_TEST_CHECKLIST.md](AI_CHAT_TEST_CHECKLIST.md)
3. Review test coverage: [../tests/COVERAGE_REPORT.md](../tests/COVERAGE_REPORT.md)

## Documentation Maintenance

### Adding New Documentation
1. Follow format established in existing docs
2. Add entry to this README.md navigation
3. Cross-reference related documentation
4. Include practical code examples
5. Update table of contents where applicable

### Updating Existing Documentation
1. Maintain backward compatibility in examples
2. Update cross-references when moving content
3. Keep documentation synchronized with code changes
4. Archive outdated documentation to [completed/](completed/) folder

## Cross-Reference Index

### By Development Task

| Task | Primary Docs | Supporting Docs |
|------|-------------|-----------------|
| **Setting up development** | [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#development-workflow) | [BUILD_CONFIG.md](BUILD_CONFIG.md) |
| **Understanding architecture** | [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#plugin-architecture) | [ARCHITECTURE.md](ARCHITECTURE.md), [SERVICE_LAYER.md](SERVICE_LAYER.md) |
| **Working with APIs** | [API_REFERENCE.md](API_REFERENCE.md) | [API.md](API.md), [API_QUIZ.md](API_QUIZ.md) |
| **Adding quiz features** | [QUIZ_IMPLEMENTATION_GUIDE.md](QUIZ_IMPLEMENTATION_GUIDE.md) | [API_QUIZ.md](API_QUIZ.md), [DATA_FLOW.md](DATA_FLOW.md#quiz-generation-workflow) |
| **MemberPress integration** | [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md) | [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#integration-points) |
| **Debugging problems** | [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#debugging-guide) | [ERROR_HANDLING_STANDARDIZATION.md](ERROR_HANDLING_STANDARDIZATION.md), [TROUBLESHOOTING.md](TROUBLESHOOTING.md) |
| **Writing tests** | [../tests/README.md](../tests/README.md) | [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#testing-strategy) |
| **Code reviews** | [STYLE_GUIDE.md](STYLE_GUIDE.md) | [code_reviews/CODE_REVIEW.md](code_reviews/CODE_REVIEW.md) |

### By Component

| Component | Architecture | API | Data Flow | Integration |
|-----------|-------------|-----|-----------|------------|
| **Course Creation** | [ARCHITECTURE.md](ARCHITECTURE.md#key-services) | [API_REFERENCE.md](API_REFERENCE.md#course-editor-endpoints) | [DATA_FLOW.md](DATA_FLOW.md#course-creation-workflow) | [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md#memberpress-courses-integration) |
| **Quiz Generation** | [SERVICE_LAYER.md](SERVICE_LAYER.md#mpccquizaiservice-implementation) | [API_QUIZ.md](API_QUIZ.md) | [DATA_FLOW.md](DATA_FLOW.md#quiz-generation-workflow) | [QUIZ_INTEGRATION_TECHNICAL.md](QUIZ_INTEGRATION_TECHNICAL.md) |
| **Session Management** | [ARCHITECTURE.md](ARCHITECTURE.md#conversationmanager) | [API_REFERENCE.md](API_REFERENCE.md#session-management-endpoints) | [DATA_FLOW.md](DATA_FLOW.md#session-management-flow) | [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md#state-management) |
| **AI Services** | [SERVICE_LAYER.md](SERVICE_LAYER.md#llmservice-implementation) | [API_REFERENCE.md](API_REFERENCE.md#authentication) | [DATA_FLOW.md](DATA_FLOW.md#ai-service-communication) | [AUTH_GATEWAY_CONFIGURATION.md](AUTH_GATEWAY_CONFIGURATION.md) |

## Support

### For Development Questions
1. Check [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) for architectural questions
2. Review [API_REFERENCE.md](API_REFERENCE.md) for endpoint documentation  
3. Examine code comments and PHPDoc blocks in source files
4. Check [completed/](completed/) folder for historical implementation context

### For Integration Questions
1. Review [INTEGRATION_GUIDE.md](INTEGRATION_GUIDE.md) for WordPress/MemberPress integration
2. Check [ARCHITECTURE.md](ARCHITECTURE.md) for component relationships
3. See [DATA_FLOW.md](DATA_FLOW.md) for workflow understanding

### For Testing and Quality
1. Follow [AI_CHAT_TEST_CHECKLIST.md](AI_CHAT_TEST_CHECKLIST.md) for manual testing
2. Use [../tests/README.md](../tests/README.md) for automated testing
3. Reference [STYLE_GUIDE.md](STYLE_GUIDE.md) for code quality standards

This documentation provides comprehensive coverage of the MemberPress Courses Copilot plugin. The documents are cross-referenced and organized by development task and component for easy navigation.