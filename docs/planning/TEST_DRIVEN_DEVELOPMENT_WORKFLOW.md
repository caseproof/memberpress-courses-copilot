# Test-Driven Development Workflow for Claude

## Overview

This document outlines how to integrate testing into the development cycle when working with Claude to prevent regressions and ensure code quality.

## Core Principles

1. **Test Before Commit** - Run relevant tests before any code changes
2. **Fix Breaks Immediately** - Never commit code that breaks existing tests
3. **Add Tests for New Features** - Every new feature needs corresponding tests
4. **Refactor with Confidence** - Tests enable safe refactoring

## Development Workflow with Claude

### 1. Before Making Changes

```bash
# Always start by running tests to ensure clean baseline
npm run test:all

# For targeted work, run specific test suites
vendor/bin/phpunit --testsuite Unit       # When working on services
vendor/bin/phpunit --testsuite Security   # When touching auth/permissions
npm run test:watch                         # For JavaScript development
```

### 2. During Development

#### When Claude Modifies a Service:
```bash
# Example: After editing CourseGeneratorService.php
vendor/bin/phpunit tests/Services/CourseGeneratorServiceTest.php

# If test fails, ask Claude to fix it:
# "The test for CourseGeneratorService is failing with [error]. Please fix the implementation to pass the test."
```

#### When Adding New Features:
```markdown
# Request to Claude:
"Add a new method `validateCourseStructure()` to CourseGeneratorService.
Also create/update the test in CourseGeneratorServiceTest.php to cover:
1. Valid structure passes
2. Invalid structure throws exception
3. Edge cases are handled"
```

### 3. After Changes

```bash
# Run full test suite before committing
npm run test:all

# Check coverage hasn't decreased
npm run test:coverage
vendor/bin/phpunit --coverage-text
```

## Automated Checks for Claude

### Pre-Commit Checklist

When asking Claude to commit code, include:
```markdown
Before committing, please:
1. Run `npm run test:all` and confirm all tests pass
2. If any tests fail, fix them before proceeding
3. For new features, confirm test coverage was added
4. Include test status in commit message
```

### Example Claude Prompts

#### Safe Refactoring
```markdown
"Refactor the LLMService to use a strategy pattern for different providers.
Run the LLMServiceTest after each change to ensure nothing breaks.
The test file is at tests/Services/LLMServiceTest.php"
```

#### Adding Features with Tests
```markdown
"Add a retry mechanism to LLMService for failed API calls.
1. First, write a failing test in LLMServiceTest for the retry behavior
2. Then implement the feature to make the test pass
3. Ensure all existing tests still pass"
```

#### Fixing Bugs
```markdown
"Fix the bug where course generation fails with special characters.
1. First, add a test that reproduces the bug
2. Fix the implementation
3. Verify the test now passes
4. Run full test suite to check for regressions"
```

## Test Categories and When to Run Them

### Quick Checks (< 30 seconds)
Run after every change:
```bash
# Single test file
vendor/bin/phpunit tests/Services/TheServiceYouChanged.php

# JavaScript watch mode (auto-runs on save)
npm run test:watch
```

### Standard Checks (2-3 minutes)
Run before commits:
```bash
# Full unit test suite
vendor/bin/phpunit --testsuite Unit

# All JavaScript tests
npm run test
```

### Comprehensive Checks (5+ minutes)
Run before merging PRs:
```bash
# Everything including integration tests
npm run test:all

# With coverage reports
npm run test:coverage
vendor/bin/phpunit --coverage-html tests/coverage/php
```

## Regression Prevention Strategies

### 1. Test Lockdown for Critical Features
```php
/**
 * @critical This test protects core course generation logic
 * DO NOT modify without updating the test
 */
public function test_generates_course_with_ai()
{
    // Critical test implementation
}
```

### 2. Snapshot Testing for Complex Outputs
```javascript
// For complex UI or data structures
test('course structure matches snapshot', () => {
    const course = generateCourseStructure(testData);
    expect(course).toMatchSnapshot();
});
```

### 3. Integration Test Guards
```php
public function test_complete_course_creation_flow()
{
    // This test ensures all services work together
    // If this breaks, something critical changed
}
```

## Common Scenarios and Solutions

### Scenario 1: Refactoring Breaks Tests
```markdown
Claude: "I refactored X and now tests are failing"
Response: "Please analyze why the tests are failing. Either:
1. Fix the refactoring to maintain the original behavior, OR
2. If the behavior change is intentional, update the tests to match the new behavior and explain why"
```

### Scenario 2: Adding Breaking Changes
```markdown
When breaking changes are necessary:
1. Create new method/class alongside old one
2. Add tests for new implementation
3. Migrate usages with tests passing at each step
4. Remove old implementation only after full migration
```

### Scenario 3: Performance Optimizations
```markdown
"Optimize the CourseGeneratorService for large courses.
1. Run existing tests to establish baseline
2. Add performance test if missing
3. Make optimizations
4. Ensure functional tests still pass
5. Verify performance improvement"
```

## Git Hooks Integration (Optional)

### Pre-commit Hook
```bash
#!/bin/sh
# .git/hooks/pre-commit

# Run quick tests
vendor/bin/phpunit --testsuite Unit --stop-on-failure
if [ $? -ne 0 ]; then
    echo "Unit tests failed. Commit aborted."
    exit 1
fi

echo "Tests passed. Proceeding with commit."
```

### Pre-push Hook
```bash
#!/bin/sh
# .git/hooks/pre-push

# Run comprehensive tests
npm run test:all
if [ $? -ne 0 ]; then
    echo "Tests failed. Push aborted."
    exit 1
fi
```

## Claude Instructions Template

When starting a new session with Claude, include:

```markdown
## Testing Requirements
- Run relevant tests after making changes
- Never commit code that breaks existing tests  
- Add tests for new features/methods
- Include test results in your response when relevant
- If tests fail, fix them before proceeding

Test commands:
- PHP: `vendor/bin/phpunit [file]`
- JS: `npm run test`
- All: `npm run test:all`
```

## Monitoring Test Health

### Weekly Review Checklist
- [ ] Coverage hasn't decreased
- [ ] No tests have been skipped/commented out
- [ ] New features have corresponding tests
- [ ] Integration tests still pass
- [ ] No flaky tests need attention

### Monthly Deep Dive
- [ ] Review coverage reports for gaps
- [ ] Update snapshot tests if needed
- [ ] Performance test baselines current
- [ ] Remove obsolete tests
- [ ] Document any testing pain points

## Benefits of This Workflow

1. **Confidence in Changes** - Know immediately if something breaks
2. **Faster Development** - Catch issues early, not in production
3. **Better Code Quality** - Tests encourage better design
4. **Knowledge Preservation** - Tests document expected behavior
5. **Safe Refactoring** - Change implementation without fear

## Remember

- Tests are not overhead, they're insurance
- A failing test is better than a production bug
- Time spent writing tests saves debugging time later
- Good tests enable fearless refactoring
- When in doubt, add a test