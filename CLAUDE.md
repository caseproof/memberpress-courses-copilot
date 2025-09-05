# MemberPress Courses Copilot Development Guidelines

## Project Overview
AI-powered conversational course creation assistant that reduces course development time from 6-10 hours to 10-30 minutes using LiteLLM proxy infrastructure.

## CRITICAL: Parent Plugin Dependencies
Before making ANY assumptions about capabilities, permissions, or functionality:
1. **ALWAYS check parent MemberPress plugins first**:
   - `../memberpress/` - Core MemberPress plugin
   - `../memberpress-courses/` - MemberPress Courses addon
   - `../memberpress-course-quizzes/` - MemberPress Course Quizzes addon
2. **Search for existing implementations** before assuming how things work
3. **Never assume standard WordPress capabilities** - MemberPress may use custom ones

## File Organization Rules
### Documentation Files
- **ALL documentation files (*.md) MUST go in `/docs/` directory**
- **NEVER create .md files in root directory**

### Test Files  
- **ALL test files MUST go in `/tests/` directory**
- **Follow directory structure that mirrors source code**

### Enforcement
- **DELETE any misplaced files immediately**
- **NO EXCEPTIONS** - Keep the project organized!

## Core Development Principles

### KISS (Keep It Simple, Stupid)
- **One service for AI**: Just `LLMService.php` with hardcoded credentials
- **No user configuration**: Everything works out of the box
- **Simple interfaces**: Methods do one thing well

### DRY (Don't Repeat Yourself)
- **Single source of truth**: Proxy credentials in ONE place (LLMService constants)
- **Reusable components**: One AI service used everywhere
- **No duplicate functionality**: One way to do each task

### YAGNI (You Aren't Gonna Need It)
- **No settings UI**: Users don't need to configure AI
- **No provider switching**: Hardcoded to use what works
- **No complex DI**: Simple instantiation where needed

## CRITICAL: NO FAKE/MOCK DATA EVER
- **NEVER USE FALLBACKS** - If API fails, FAIL PROPERLY with error logging
- **NEVER CREATE MOCK DATA** - No fake responses, fake content, fake anything
- **FAIL FAST AND LOUD** - When something breaks, let it break visibly
- **LOG EVERYTHING** - Use error_log() extensively to debug real issues
- **REAL OR NOTHING** - Either it works with real data/APIs or it doesn't work at all

## Architecture & Code Standards

### Namespace & Structure (PSR-4)
- **Primary Namespace**: `MemberPressCoursesCopilot`
- **Text Domain**: `memberpress-courses-copilot`
- **Plugin Prefix**: `mpcc_`

### Directory Structure
```
memberpress-courses-copilot/
├── memberpress-courses-copilot.php (Main plugin file)
├── src/MemberPressCoursesCopilot/
│   ├── Plugin.php
│   ├── Controllers/
│   ├── Services/
│   │   ├── LLMService.php (Single AI service with hardcoded credentials)
│   │   ├── ConversationManager.php (THE session handler)
│   │   └── CourseGeneratorService.php
│   ├── Models/
│   ├── Admin/
│   └── Utilities/
├── composer.json (PSR-4 autoloading)
├── assets/css/, assets/js/
└── docs/ (ALL documentation here)
```

### Coding Standards (Caseproof-WP)
- **PHPCS Integration**: Mandatory for all code
- **Methods**: camelCase for methods, snake_case for WordPress hooks
- **Security**: Always verify nonces, sanitize inputs, escape outputs
- **Permissions**: Use WordPress capability checks before actions

### Simple LLMService Implementation
```php
class LLMService {
    private const PROXY_URL = 'https://wp-ai-proxy-production-9a5aceb50dde.herokuapp.com';
    private const MASTER_KEY = 'sk-litellm-EkFY6Wgp9MaDGjbrkCQx4qmbSH4wa0XrEVJmklFcYgw=';
    
    public function generateContent($prompt) {
        return wp_remote_post(self::PROXY_URL . '/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . self::MASTER_KEY],
            'body' => json_encode([
                'model' => 'anthropic/claude-3-sonnet',
                'messages' => [['role' => 'user', 'content' => $prompt]]
            ])
        ]);
    }
}
```

## Testing Requirements (MANDATORY)
- **Run tests before changes**: `npm run test:all`
- **Test after modifications**: Run specific test files after editing services  
- **Never commit broken tests**: All tests must pass before committing
- **Add tests for new features**: Every new method needs tests

### Quick Test Commands
```bash
# Before starting work
npm run test:all

# After modifying a service
vendor/bin/phpunit tests/Services/ServiceNameTest.php

# Before committing  
npm run test:all && npm run test:coverage
```

## Build & Development
- Install dependencies: `composer install` (PSR-4 autoloading required)
- Code standards: `composer run cs-check` and `composer run cs-fix`
- Debug output: `error_log('MPCC: debug info here');`

## Session Management Architecture
### ConversationManager - The ONLY Session Handler
Following KISS principles, we use ONE service for all session management:

```php
// GOOD: Simple, direct session management
$conversationManager = new ConversationManager();
$session = $conversationManager->loadSession($sessionId);
$session->addMessage('user', $content);
$conversationManager->saveSession($session);
```

**Key Design**: NO SessionService - ConversationManager handles everything directly.

## Security & Integration
- **API Security**: All API keys managed at LiteLLM proxy level
- **WordPress Security**: Use `edit_courses` capability, required nonces for AJAX
- **MemberPress Integration**: Hook into existing course creation with `mpcs-course` post type

## What NOT to Build (YAGNI)
### ❌ Don't Create These
- Settings pages for AI configuration
- Multiple AI service classes  
- Dependency injection containers
- Provider switchers
- Complex error handlers
- Abstract base classes

### ✅ Do This Instead
```php
// Simple instantiation wherever needed
$llm = new LLMService();
$result = $llm->generateContent($prompt);

if ($result['error']) {
    wp_send_json_error($result['message']);
}
```

## The Golden Rule
**If you're creating more than one file to solve a problem, you're probably overcomplicating it.**

Always prioritize simplicity, user experience, and getting things done over architectural perfection.