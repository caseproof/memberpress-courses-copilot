# Test Coverage Improvement Plan

## Current State Assessment

### ✅ Existing Test Coverage (What's Already Good)

#### PHP Tests - Well Covered Services:
- **ConversationManager** ✓ Full test suite
- **CourseGeneratorService** ✓ Comprehensive tests
- **DatabaseService** ✓ Database operation tests
- **LLMService** ✓ API integration tests
- **LessonDraftService** ✓ Draft management tests
- **MpccQuizAIService** ✓ Quiz generation tests
- **EnhancedTemplateEngine** ✓ Template rendering tests
- **CourseUIService** ✓ UI service tests

#### Security Testing (Excellent Coverage):
- AJAX endpoint security ✓
- Input sanitization ✓
- XSS prevention ✓
- Permission checks ✓
- Quiz-specific security ✓

#### Test Infrastructure:
- PHPUnit properly configured ✓
- Test database isolation ✓
- No mocks (real operations) ✓
- Integration test suite ✓

### ❌ Current Gaps

#### PHP Coverage Gaps:
1. **ContentGenerationService** - Critical gap, handles AI content generation
2. **CourseAjaxService** - Major gap, handles all course AJAX operations
3. **EditorAIIntegrationService** - Important for editor AI features
4. **CourseIntegrationService** - Core integration logic missing tests
5. **ConversationFlowHandler** - Chat flow management untested
6. **SessionFeaturesService** - Session features untested
7. **DatabaseBackupService** - Backup functionality untested

#### JavaScript Coverage:
- **Current: 12.99%** (Critical - needs immediate attention)
- Most UI components untested
- AJAX handlers lack coverage
- Modal interactions untested

## Priority-Based Implementation Plan

### Phase 1: Critical Service Tests (Week 1-2)

#### 1. ContentGenerationService Tests
```php
// tests/Unit/Services/ContentGenerationServiceTest.php
class ContentGenerationServiceTest extends TestCase {
    public function test_generates_lesson_content_from_outline()
    public function test_handles_ai_generation_errors()
    public function test_applies_content_templates()
    public function test_validates_generated_content()
    public function test_formats_content_for_wordpress()
}
```

#### 2. CourseAjaxService Tests
```php
// tests/Unit/Services/CourseAjaxServiceTest.php
class CourseAjaxServiceTest extends TestCase {
    public function test_creates_course_from_ajax_request()
    public function test_updates_course_structure()
    public function test_handles_lesson_reordering()
    public function test_validates_ajax_permissions()
    public function test_returns_proper_json_responses()
}
```

#### 3. EditorAIIntegrationService Tests
```php
// tests/Unit/Services/EditorAIIntegrationServiceTest.php
class EditorAIIntegrationServiceTest extends TestCase {
    public function test_integrates_with_block_editor()
    public function test_generates_content_suggestions()
    public function test_handles_editor_ajax_requests()
    public function test_preserves_existing_content()
}
```

### Phase 2: JavaScript Test Expansion (Week 3-4)

#### Priority JavaScript Tests Needed:
1. **Course Editor UI Tests**
   - Test course structure manipulation
   - Drag-and-drop functionality
   - Real-time preview updates

2. **AI Chat Interface Tests**
   - Message sending/receiving
   - Session management
   - Error handling

3. **Quiz Generation Modal Tests**
   - Modal lifecycle
   - Form validation
   - Question preview

Example test structure:
```javascript
// tests/js/course-editor.test.js
describe('CourseEditor', () => {
    test('adds new section to course', async () => {
        const editor = new CourseEditor();
        await editor.addSection('New Section');
        expect(editor.getSections()).toHaveLength(1);
    });

    test('handles drag and drop reordering', async () => {
        // Test implementation
    });
});
```

### Phase 3: Integration Tests (Week 5)

#### End-to-End Course Creation Flow
```php
// tests/Integration/CourseCreationFlowTest.php
class CourseCreationFlowTest extends TestCase {
    public function test_complete_course_creation_workflow() {
        // 1. Start AI conversation
        // 2. Generate course structure
        // 3. Create WordPress posts
        // 4. Generate lesson content
        // 5. Verify final course
    }
}
```

#### Quiz Generation Integration
```php
// tests/Integration/QuizGenerationFlowTest.php
class QuizGenerationFlowTest extends TestCase {
    public function test_generates_quiz_from_lesson_content() {
        // Complete quiz generation flow
    }
}
```

### Phase 4: Performance & Edge Case Tests (Week 6)

#### Performance Tests
- Large course generation (100+ lessons)
- Concurrent user sessions
- Rate limiting behavior
- Memory usage under load

#### Edge Case Tests
- Network failures during AI calls
- Partial content generation
- Session recovery
- Invalid AI responses

## Testing Standards & Best Practices

### 1. **Follow Existing Patterns**
- No mocks - use real services
- Test against real database
- Clean up after each test
- Use TestDataFactory for consistency

### 2. **Naming Conventions**
```php
// Good test names
public function test_generates_course_with_custom_template()
public function test_throws_exception_when_ai_service_unavailable()
public function test_sanitizes_user_input_before_generation()
```

### 3. **Test Structure**
```php
public function test_feature_behavior() {
    // Arrange
    $service = new ServiceUnderTest();
    $input = $this->createTestInput();
    
    // Act
    $result = $service->performAction($input);
    
    // Assert
    $this->assertEquals($expected, $result);
    $this->assertDatabaseHas('table', ['field' => 'value']);
}
```

### 4. **JavaScript Testing Setup**
- Use Jest for consistency
- Mock WordPress globals appropriately
- Test both success and error paths
- Use async/await for clarity

## Coverage Goals

### Immediate Goals (6 weeks):
- **PHP Coverage**: From ~25% to 60%
- **JavaScript Coverage**: From 13% to 40%
- **Critical Services**: 100% coverage

### Long-term Goals (3 months):
- **Overall Coverage**: 80%+ (as per COVERAGE_REPORT.md target)
- **Integration Tests**: Full workflow coverage
- **Performance Tests**: Automated benchmarks

## Implementation Checklist

### Week 1-2: Critical Services
- [ ] ContentGenerationService tests
- [ ] CourseAjaxService tests  
- [ ] EditorAIIntegrationService tests
- [ ] Update coverage reports

### Week 3-4: JavaScript
- [ ] Course editor tests
- [ ] AI chat interface tests
- [ ] Quiz modal tests
- [ ] AJAX handler tests

### Week 5: Integration
- [ ] Course creation flow
- [ ] Quiz generation flow
- [ ] Session management flow
- [ ] Error recovery tests

### Week 6: Polish
- [ ] Performance tests
- [ ] Edge case tests
- [ ] Documentation updates
- [ ] CI/CD integration

## Success Metrics

1. **Coverage Metrics**
   - PHP: 60%+ coverage
   - JavaScript: 40%+ coverage
   - No untested critical paths

2. **Quality Metrics**
   - All tests pass in < 5 minutes
   - No flaky tests
   - Clear test documentation

3. **Maintenance Metrics**
   - New features include tests
   - Tests updated with refactors
   - Regular coverage monitoring

## Conclusion

The plugin has a solid testing foundation with excellent security test coverage and good practices in place. The main gaps are in service-level unit tests and JavaScript coverage. By following this plan, we can achieve comprehensive test coverage while maintaining the existing high standards for test quality.