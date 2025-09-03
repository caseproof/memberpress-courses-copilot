# MemberPress Courses Copilot - Quiz Functionality Test Suite

This document outlines the comprehensive test suite created for the quiz functionality in MemberPress Courses Copilot, covering both PHP and JavaScript components.

## Test Structure Overview

```
tests/
├── JavaScript/
│   ├── jest.config.js          # Jest configuration
│   ├── setup.js                # Test environment setup
│   └── quiz-ai-modal.test.js   # Complete modal functionality tests
├── Unit/
│   ├── Controllers/
│   │   └── MpccQuizAjaxControllerTest.php  # AJAX controller tests
│   └── Security/
│       ├── QuizSanitizationTest.php        # Input sanitization tests
│       └── QuizPermissionTest.php          # Permission & security tests
└── QUIZ_TEST_DOCUMENTATION.md              # This documentation
```

## PHP Unit Tests (PHPUnit)

### 1. MpccQuizAjaxControllerTest.php

**Location:** `/tests/Unit/Controllers/MpccQuizAjaxControllerTest.php`

**Purpose:** Tests all AJAX endpoints and core controller functionality

**Test Coverage:**
- ✅ Quiz generation with valid input
- ✅ Quiz generation with invalid nonce
- ✅ Quiz generation with insufficient permissions
- ✅ Quiz generation with empty content
- ✅ AI service error handling
- ✅ Question regeneration functionality
- ✅ Quiz validation (valid and invalid data)
- ✅ Quiz creation from lesson
- ✅ Course and lesson data retrieval
- ✅ Input extraction and sanitization
- ✅ Generation options preparation
- ✅ Content retrieval methods
- ✅ Response formatting
- ✅ Exception handling
- ✅ Nonce verification
- ✅ User permission verification

**Key Test Methods:**
- `testGenerateQuizSuccess()` - Full success scenario
- `testGenerateQuizFailsWithInvalidNonce()` - Security validation
- `testValidateQuizWithValidData()` - Quiz structure validation
- `testQuestionTypeValidation()` - Different question type validation
- `testExtractAndSanitizeInput()` - Input sanitization

### 2. QuizSanitizationTest.php

**Location:** `/tests/Unit/Security/QuizSanitizationTest.php`

**Purpose:** Comprehensive testing of all variable sanitization methods

**Test Coverage:**
- ✅ Text sanitization (XSS prevention)
- ✅ Textarea sanitization (preserving line breaks)
- ✅ Email sanitization
- ✅ URL sanitization
- ✅ Integer sanitization
- ✅ Float sanitization
- ✅ Boolean sanitization
- ✅ HTML sanitization (safe tags only)
- ✅ Nested array sanitization
- ✅ Encoding attack prevention
- ✅ SQL injection prevention
- ✅ Performance with large datasets
- ✅ Edge cases and boundary conditions

**Key Test Methods:**
- `testSanitizeArrayTextSanitization()` - XSS prevention
- `testSanitizeArrayWithMaliciousJson()` - JSON input security
- `testSanitizationHandlesEncodingAttacks()` - Various encoding attacks
- `testSqlInjectionPreventionInNumericInputs()` - SQL injection prevention

### 3. QuizPermissionTest.php

**Location:** `/tests/Unit/Security/QuizPermissionTest.php`

**Purpose:** Tests all permission checks and access controls

**Test Coverage:**
- ✅ Permission checks for all AJAX endpoints
- ✅ WordPress capability hierarchy testing
- ✅ Nonce verification scenarios
- ✅ CSRF protection validation
- ✅ Concurrent permission checks
- ✅ Permission check order (nonce before permissions)
- ✅ Edge case capability handling
- ✅ Rate limiting simulation
- ✅ Malformed capability data handling

**Key Test Methods:**
- `testAllAjaxEndpointsRequireEditPostsCapability()` - Systematic permission testing
- `testPermissionHierarchy()` - WordPress role-based access
- `testNonceVerificationScenarios()` - CSRF protection
- `testCsrfProtectionViaNonceValidation()` - Cross-site request forgery prevention

## JavaScript Unit Tests (Jest)

### quiz-ai-modal.test.js

**Location:** `/tests/JavaScript/quiz-ai-modal.test.js`

**Purpose:** Comprehensive testing of the quiz AI modal functionality

**Test Coverage:**
- ✅ Modal initialization and DOM manipulation
- ✅ Context detection (lesson/course from URL, selectors, referrers)
- ✅ Modal opening/closing operations
- ✅ Lesson loading strategies (course-specific, siblings, recent)
- ✅ Question generation with AJAX
- ✅ Question display for all question types
- ✅ Block creation for Gutenberg editor
- ✅ Question data preparation by type
- ✅ Event handling and user interactions
- ✅ Error scenarios and recovery
- ✅ Auto-open functionality
- ✅ Performance with large datasets
- ✅ Memory cleanup and resource management

**Test Categories:**

#### Initialization Tests
- Modal creation on quiz edit pages
- AI button insertion into editor toolbar
- Proper CSS injection

#### Context Detection Tests
- URL parameter detection
- Lesson selector monitoring
- Course context from referrer
- Auto-detection feedback

#### Modal Operations Tests
- Opening/closing modal
- Event binding
- Outside click handling
- Resource cleanup

#### Lesson Loading Tests
- Course-specific lesson loading
- AJAX error handling
- Recent lessons fallback
- Single lesson loading

#### Question Generation Tests
- Successful generation workflow
- Error handling and display
- Loading states
- Form validation

#### Question Display Tests
- Multiple choice questions
- True/false questions
- Text answer questions
- Multiple select questions
- Question preview formatting

#### Block Creation Tests
- WordPress block type mapping
- Question data preparation
- Block insertion into editor
- Question ID reservation
- Editor state management

## Test Configuration

### Jest Configuration (jest.config.js)

```javascript
module.exports = {
    testEnvironment: 'jsdom',
    setupFilesAfterEnv: ['<rootDir>/tests/JavaScript/setup.js'],
    collectCoverage: true,
    transform: { '^.+\\.js$': 'babel-jest' }
};
```

### Test Environment Setup (setup.js)

**Mocks Provided:**
- jQuery and DOM manipulation
- WordPress globals (wp.data, wp.blocks, wp.domReady)
- AJAX settings (mpcc_ajax)
- Browser APIs (navigator.clipboard, URLSearchParams)
- Console methods (to reduce test noise)

## Running the Tests

### PHP Tests
```bash
# Run all PHP tests
npm run test:php

# Run specific test suite
vendor/bin/phpunit --testsuite=Security
vendor/bin/phpunit tests/Unit/Controllers/MpccQuizAjaxControllerTest.php
```

### JavaScript Tests
```bash
# Run all JavaScript tests
npm run test

# Run with coverage
npm run test:coverage

# Watch mode for development
npm run test:watch
```

### All Tests
```bash
# Run both PHP and JavaScript tests
npm run test:all
```

## Test Data and Mocking

### PHP Mocking Strategy

**WordPress Functions:** Mocked in bootstrap.php and individual test files
- `get_post()` - Returns mock post data
- `get_post_meta()` - Returns mock metadata
- `current_user_can()` - Uses test capability system
- `wp_verify_nonce()` - Simple hash comparison
- `wp_send_json_*()` - Throws exception to capture output

**Database:** Uses mock wpdb class with SQLite for real database operations when needed

**Dependencies:** All external services (LLM, Logger) are mocked using PHPUnit mocks

### JavaScript Mocking Strategy

**WordPress APIs:** Comprehensive mocks for:
- `wp.data` - Redux-style data layer
- `wp.blocks` - Block creation utilities
- `wp.domReady` - DOM ready callback

**AJAX:** jQuery AJAX methods are mocked to return configurable responses

**Browser APIs:** Navigator, History, URLSearchParams all mocked

## Security Testing Focus

### XSS Prevention
- Tests malicious script injection in all input fields
- Validates HTML sanitization preserves safe content
- Checks encoding attack prevention

### SQL Injection Prevention  
- Tests numeric input sanitization
- Validates that SQL keywords are stripped
- Ensures type casting prevents injection

### CSRF Protection
- Tests nonce verification for all endpoints
- Validates cross-session nonce rejection
- Checks nonce action specificity

### Access Control
- Tests WordPress capability requirements
- Validates permission hierarchy
- Checks edge cases in capability handling

## Test Isolation and Independence

### Setup/Teardown
- Each test starts with clean state
- Mock dependencies are reset between tests
- Global variables are cleared after each test
- No test affects others (isolated)

### Mock Management
- Dependencies injected via constructor
- External calls intercepted and mocked
- No real API calls or database changes
- Predictable test data

## Coverage Goals

### PHP Code Coverage
- **Target:** 95%+ coverage for controller methods
- **Includes:** All public and protected methods
- **Excludes:** Utility classes (as configured in phpunit.xml)

### JavaScript Code Coverage
- **Target:** 90%+ coverage for modal functionality
- **Includes:** All methods and event handlers
- **Focus:** Critical paths and error scenarios

## Testing Best Practices Applied

### Test Naming
- Descriptive test method names explaining what is being tested
- Test classes named after the component being tested
- Clear separation between success and failure scenarios

### Test Structure
- **Arrange-Act-Assert** pattern consistently applied
- Clear test data setup
- Single assertion focus per test method
- Proper use of data providers for parametrized tests

### Mock Strategy
- Mock external dependencies, not internal logic
- Use real objects where possible for integration testing
- Provide predictable test data
- Avoid over-mocking that hides real bugs

### Error Testing
- Test both success and failure paths
- Validate error messages and codes
- Test edge cases and boundary conditions
- Ensure graceful degradation

## Integration with CI/CD

The test suite is designed to be CI/CD friendly:

- **Fast execution:** Isolated tests with minimal setup
- **Reliable:** No external dependencies or network calls
- **Comprehensive:** High coverage of critical functionality
- **Maintainable:** Clear test structure and documentation

Tests can be integrated into GitHub Actions or other CI systems using the npm scripts provided in package.json.

## Maintenance Guidelines

### Adding New Tests
1. Follow existing naming conventions
2. Use appropriate test base classes
3. Mock external dependencies properly
4. Include both success and failure scenarios
5. Update this documentation

### Modifying Existing Tests
1. Ensure changes don't break test isolation
2. Update related tests if changing shared mocks
3. Maintain test coverage levels
4. Update documentation if test purpose changes

### Debugging Failed Tests
1. Check mock setup in setUp() methods
2. Verify test data matches expected format
3. Ensure WordPress function mocks are complete
4. Check for global state pollution between tests