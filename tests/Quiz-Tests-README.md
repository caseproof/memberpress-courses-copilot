# Quiz Functionality Test Suite

## Overview

This directory contains comprehensive unit tests for the MemberPress Courses Copilot quiz functionality, including both PHP (PHPUnit) and JavaScript (Jest) tests.

## Quick Start

### Prerequisites

1. **PHP Dependencies:**
   ```bash
   composer install
   ```

2. **JavaScript Dependencies:**
   ```bash
   npm install
   ```

### Running Tests

#### Run All Quiz Tests
```bash
# PHP tests only
npm run test:php

# JavaScript tests only  
npm run test

# Both PHP and JavaScript
npm run test:all
```

#### Run Specific Test Suites
```bash
# Run quiz-specific tests only
php tests/run-quiz-tests.php

# Run security tests only
vendor/bin/phpunit --testsuite=Security

# Run with coverage
npm run test:coverage
```

### Test Results

Tests validate:
- ✅ **Security:** XSS prevention, SQL injection protection, CSRF protection
- ✅ **Permissions:** WordPress capability checks, access controls
- ✅ **Sanitization:** Input validation for all data types
- ✅ **Functionality:** AJAX endpoints, modal operations, block creation
- ✅ **Error Handling:** Graceful failure scenarios
- ✅ **Performance:** Large dataset handling

## Test Files Created

### PHP Tests (PHPUnit)

| File | Purpose | Test Count | Coverage |
|------|---------|------------|----------|
| `Unit/Controllers/MpccQuizAjaxControllerTest.php` | AJAX controller functionality | 15+ tests | All public methods |
| `Unit/Security/QuizSanitizationTest.php` | Input sanitization validation | 12+ tests | All sanitization methods |
| `Unit/Security/QuizPermissionTest.php` | Permission and security checks | 10+ tests | All permission validations |

### JavaScript Tests (Jest)

| File | Purpose | Test Count | Coverage |
|------|---------|------------|----------|
| `JavaScript/quiz-ai-modal.test.js` | Modal functionality and UI | 25+ tests | Complete modal lifecycle |

### Configuration Files

| File | Purpose |
|------|---------|
| `JavaScript/jest.config.js` | Jest test configuration |
| `JavaScript/setup.js` | Test environment setup |
| `Mocks/wordpress-functions.php` | WordPress function mocks |
| `.babelrc` | Babel configuration for Jest |

## Test Structure

```
tests/
├── JavaScript/
│   ├── jest.config.js              # Jest configuration
│   ├── setup.js                    # Test environment setup & mocks
│   └── quiz-ai-modal.test.js       # Complete modal functionality tests
├── Unit/
│   ├── Controllers/
│   │   └── MpccQuizAjaxControllerTest.php  # AJAX controller tests
│   └── Security/
│       ├── QuizSanitizationTest.php        # Input sanitization tests
│       └── QuizPermissionTest.php          # Permission & security tests
├── Mocks/
│   └── wordpress-functions.php             # Extended WordPress mocks
├── run-quiz-tests.php                      # Test runner script
├── Quiz-Tests-README.md                    # This file
└── QUIZ_TEST_DOCUMENTATION.md              # Detailed documentation
```

## What Each Test Validates

### Security Tests

**XSS Prevention:**
- Script tag injection attempts
- Event handler injection (onclick, onerror)
- HTML entity encoding attacks
- Unicode-based attacks
- Nested content attacks

**SQL Injection Prevention:**
- Numeric field injection attempts
- String-based SQL injection
- Union select attempts
- Drop table attempts

**CSRF Protection:**
- Nonce verification for all endpoints
- Cross-session nonce validation
- Action-specific nonce checking

### Permission Tests

**WordPress Capabilities:**
- Administrator access (full permissions)
- Editor access (edit_posts capability)
- Author access (edit_posts capability)
- Contributor access (edit_posts capability)
- Subscriber denial (no edit_posts)
- Anonymous user denial

**Access Control:**
- Method-level permission checks
- Endpoint-specific validations
- Permission hierarchy testing
- Edge case capability handling

### Functionality Tests

**AJAX Endpoints:**
- `mpcc_generate_quiz` - Quiz generation
- `mpcc_regenerate_question` - Question regeneration
- `mpcc_validate_quiz` - Quiz validation
- `mpcc_create_quiz_from_lesson` - Quiz creation
- `mpcc_get_lesson_course` - Course retrieval
- `mpcc_get_course_lessons` - Lesson listing

**Modal Operations:**
- Modal creation and destruction
- Context detection (URL, selectors, referrers)
- Lesson loading strategies
- Question type handling
- Block creation for Gutenberg

**Data Processing:**
- Input sanitization across all types
- Option parsing (array and JSON)
- Content extraction from lessons/courses
- Response formatting

## Mock Strategy

### PHP Mocking
- **WordPress Functions:** Complete set of WP functions mocked
- **Database Operations:** Mock wpdb with SQLite backend
- **User System:** Configurable capability testing
- **AJAX Responses:** Captured via output buffering

### JavaScript Mocking
- **WordPress APIs:** wp.data, wp.blocks, wp.domReady
- **jQuery:** Full jQuery object with AJAX mocking
- **Browser APIs:** navigator, history, URLSearchParams
- **DOM:** jsdom environment with realistic HTML

## Test Isolation

Each test is completely isolated:
- ✅ No shared state between tests
- ✅ Clean setup/teardown for each test
- ✅ Mocked external dependencies
- ✅ No real database or API calls
- ✅ Predictable test data

## Performance Considerations

Tests are optimized for speed:
- ✅ Minimal setup overhead
- ✅ Efficient mocking strategy
- ✅ Parallel test execution support
- ✅ Fast feedback loops

## Debugging Tests

### PHP Test Debugging
```bash
# Run with verbose output
vendor/bin/phpunit --verbose tests/Unit/Controllers/MpccQuizAjaxControllerTest.php

# Run specific test method
vendor/bin/phpunit --filter testGenerateQuizSuccess tests/Unit/Controllers/MpccQuizAjaxControllerTest.php

# Enable debug output
export XDEBUG_MODE=debug
vendor/bin/phpunit tests/Unit/Controllers/MpccQuizAjaxControllerTest.php
```

### JavaScript Test Debugging
```bash
# Run with verbose output
npm run test -- --verbose

# Run specific test file
npm run test -- quiz-ai-modal.test.js

# Run in watch mode
npm run test:watch
```

## Contributing

When adding new quiz functionality:

1. **Add corresponding tests** for any new methods
2. **Follow existing patterns** for test structure and naming
3. **Mock external dependencies** properly
4. **Test both success and failure scenarios**
5. **Update this documentation** with new test information

## Troubleshooting

### Common Issues

**"Class not found" errors:**
- Run `composer install` to install PHPUnit dependencies
- Check autoloader configuration in `composer.json`

**JavaScript test failures:**
- Run `npm install` to install Jest dependencies  
- Check Node.js version (requires Node 16+)

**WordPress function not found:**
- Add missing functions to `tests/Mocks/wordpress-functions.php`
- Update `tests/bootstrap.php` if needed

**Mock-related issues:**
- Check mock setup in test `setUp()` methods
- Verify mock data matches expected format
- Ensure mocks are reset between tests

## Performance Benchmarks

Test suite performance targets:
- **PHP tests:** < 30 seconds for full suite
- **JavaScript tests:** < 10 seconds for full suite
- **Individual tests:** < 1 second each
- **Memory usage:** < 512MB for PHP tests

Current performance meets all targets with room for expansion.