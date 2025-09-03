# Test Isolation Framework

This framework provides proper test isolation to prevent interference between tests in the MemberPress Courses Copilot plugin.

## Components

### 1. MockManager

The `MockManager` class handles WordPress function mocks on a per-test basis:

- **Isolated Mocks**: Each test can define its own mocks without affecting other tests
- **Call Tracking**: Track how many times mocked functions are called and with what arguments
- **Global State Management**: Automatically backs up and restores global variables between tests

### 2. TestDataFactory

The `TestDataFactory` class creates realistic test data:

- **Consistent Data**: Generate predictable test data with incrementing IDs
- **Customizable**: Override any default values as needed
- **Multiple Types**: Create users, posts, courses, lessons, quizzes, and more

### 3. Enhanced TestCase

The base `TestCase` class now includes:

- Automatic mock cleanup in `tearDown()`
- Helper methods for mocking functions
- Assertion methods for verifying mock calls

## Usage Examples

### Basic Mock Usage

```php
public function testWithMock(): void
{
    // Mock a single function
    $this->mockFunction('get_option', 'mocked_value');
    
    // Use the mocked function
    $value = get_option('any_key');
    $this->assertEquals('mocked_value', $value);
    
    // Verify it was called
    $this->assertFunctionCalled('get_option');
}
```

### Mock with Callback

```php
public function testWithCallback(): void
{
    // Mock with dynamic return value
    $this->mockFunction('get_post_meta', function($post_id, $key, $single) {
        if ($key === 'custom_field') {
            return 'custom_value';
        }
        return null;
    });
    
    $value = get_post_meta(123, 'custom_field', true);
    $this->assertEquals('custom_value', $value);
}
```

### Multiple Mocks

```php
public function testMultipleMocks(): void
{
    $this->mockFunctions([
        'current_user_can' => true,
        'is_admin' => false,
        'get_current_user_id' => 42
    ]);
    
    $this->assertTrue(current_user_can('edit_posts'));
    $this->assertFalse(is_admin());
    $this->assertEquals(42, get_current_user_id());
}
```

### Using TestDataFactory

```php
public function testWithTestData(): void
{
    // Create a course
    $course = TestDataFactory::createCourse([
        'post_title' => 'Test Course',
        'post_status' => 'publish'
    ]);
    
    // Create multiple lessons
    $lessons = TestDataFactory::createMany(
        [TestDataFactory::class, 'createLesson'],
        5,
        ['post_parent' => $course['ID']]
    );
    
    $this->assertCount(5, $lessons);
}
```

### Complex Test Scenario

```php
public function testAjaxHandler(): void
{
    // Set up test data
    $request = TestDataFactory::createAjaxRequest([
        'action' => 'my_ajax_action',
        'data' => ['test' => 'value']
    ]);
    
    // Mock security functions
    $this->mockFunctions([
        'current_user_can' => true,
        'check_ajax_referer' => true
    ]);
    
    // Mock database response
    $this->mockFunction('get_option', [
        'setting1' => 'value1',
        'setting2' => 'value2'
    ]);
    
    // Set request data
    $this->setPostData($request);
    
    // Call the handler
    $response = $this->captureJsonOutput(function() {
        my_ajax_handler();
    });
    
    // Assert response
    $this->assertTrue($response['success']);
    $this->assertFunctionCalled('check_ajax_referer');
}
```

## Benefits

1. **Test Isolation**: Each test runs in a clean environment
2. **No Function Conflicts**: Avoid "function already defined" errors
3. **Better Test Reliability**: Tests don't affect each other
4. **Easier Debugging**: Clear which mocks are active for each test
5. **Realistic Test Data**: Consistent, predictable test data generation

## Best Practices

1. **Always use mocks** for WordPress functions instead of relying on global state
2. **Create specific test data** using TestDataFactory instead of hardcoding
3. **Verify mock calls** to ensure your code interacts with WordPress correctly
4. **Keep tests focused** - mock only what's necessary for each test
5. **Use descriptive test names** that explain what's being tested

## Migration Guide

To migrate existing tests:

1. Extend from the updated `TestCase` class
2. Replace direct function definitions with `mockFunction()` calls
3. Use `TestDataFactory` instead of hardcoded test data
4. Add assertions for mock function calls
5. Remove any manual cleanup code (it's handled automatically)

## Troubleshooting

### "Function already defined" errors
- Make sure you're using `mockFunction()` instead of defining functions directly
- Check that all tests extend the proper `TestCase` class

### Mocks not working
- Verify the function name is spelled correctly
- Ensure the mock is registered before the function is called
- Check that the function uses `callMockOrDefault()` in bootstrap.php

### Test data inconsistency
- Call `TestDataFactory::reset()` if you need to restart ID counters
- Use the factory methods consistently instead of mixing approaches