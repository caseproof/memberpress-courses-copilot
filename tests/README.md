# MemberPress Courses Copilot Test Suite

This test suite provides comprehensive coverage for the MemberPress Courses Copilot plugin, following CLAUDE.md principles with real tests (no mocks or fake data).

## Test Coverage

### Unit Tests
- **LLMService** - Real API integration tests
- **DatabaseService** - Real SQLite database operations
- **ConversationManager** - Session management tests
- **CourseGeneratorService** - Course creation tests
- **LessonDraftService** - Draft management tests

### Security Tests
- **AjaxSecurityTest** - Nonce verification, permissions, authentication
- **XssPreventionTest** - XSS attack vector prevention
- **InputSanitizationTest** - Input validation and sanitization

### Integration Tests
- **AjaxIntegrationTest** - Complete AJAX workflow tests

## Running Tests

### Prerequisites
1. PHP 7.4+ with SQLite3 extension
2. Composer dependencies installed: `composer install`

### Running All Tests
```bash
./vendor/bin/phpunit
```

### Running Specific Test Suites
```bash
# Unit tests only
./vendor/bin/phpunit --testsuite Unit

# Security tests only
./vendor/bin/phpunit tests/Security/

# Integration tests only
./vendor/bin/phpunit --testsuite Integration
```

### Running Individual Tests
```bash
# Run a specific test file
./vendor/bin/phpunit tests/Services/DatabaseServiceTest.php

# Run a specific test method
./vendor/bin/phpunit --filter testGenerateCompleteCourse
```

### Code Coverage
```bash
# Generate HTML coverage report
./vendor/bin/phpunit --coverage-html coverage/

# Generate text coverage report
./vendor/bin/phpunit --coverage-text
```

## Environment Setup

### API Tests
To run real API tests (LLMService), set these environment variables:
```bash
export MPCC_TEST_LICENSE_KEY="your-test-license-key"
export MPCC_AUTH_GATEWAY_URL="https://your-gateway-url.com"
```

Without these, API tests will be skipped.

### Database Tests
Tests use SQLite in-memory databases for isolation. No setup required.

## Test Structure

```
tests/
├── bootstrap.php           # Test environment setup
├── TestCase.php           # Base test class with helpers
├── Mocks/
│   └── wpdb.php          # WordPress database mock
├── Unit/                  # Unit tests
├── Services/              # Service tests
├── Security/              # Security tests
├── Integration/           # Integration tests
└── README.md             # This file
```

## Writing Tests

### Principles
1. **No fake data** - Use real database operations, real API calls
2. **Test real functionality** - Tests should fail if code is broken
3. **Security first** - Always test authentication, authorization, sanitization
4. **Clean state** - Each test should clean up after itself

### Example Test
```php
public function testRealDatabaseOperation(): void
{
    // Arrange
    $service = new DatabaseService();
    $sessionId = 'test_' . uniqid();
    
    // Act
    $result = $service->saveConversation($sessionId, 1, ['data']);
    
    // Assert
    $this->assertTrue($result);
    
    // Verify with real database query
    $saved = $service->getConversation($sessionId);
    $this->assertNotNull($saved);
}
```

## Continuous Integration

Add to your CI pipeline:
```yaml
steps:
  - name: Install dependencies
    run: composer install
    
  - name: Run tests
    run: ./vendor/bin/phpunit
    
  - name: Generate coverage
    run: ./vendor/bin/phpunit --coverage-clover coverage.xml
```

## Troubleshooting

### Common Issues

1. **SQLite not available**
   ```
   apt-get install php-sqlite3
   ```

2. **Memory limit exceeded**
   ```
   php -d memory_limit=512M ./vendor/bin/phpunit
   ```

3. **Tests timing out**
   - Check API connectivity
   - Increase timeout in phpunit.xml

## Coverage Goals

Current coverage: ~25%
Target coverage: 80%

### Priority Areas
1. Security-critical code - 100% coverage
2. Core services - 90% coverage
3. AJAX handlers - 85% coverage
4. Utilities - 70% coverage

## Contributing

When adding new features:
1. Write tests FIRST (TDD)
2. Ensure all tests pass
3. Add security tests for any user input
4. Document any new test requirements