# Gutenberg Compatibility Implementation Plan
## MemberPress Courses Copilot - Issue #96

### Executive Summary
This document outlines the implementation plan for adding Gutenberg block editor compatibility to the MemberPress Courses Copilot plugin while maintaining backward compatibility with the Classic Editor. The goal is to generate content in a format that works seamlessly with both editors.

---

## 1. Current State Analysis

### 1.1 Content Generation Flow
- **LLMService**: Generates content via AI prompts requesting Markdown format
- **EditorAIIntegrationService**: Handles AI integration with content wrapped in custom tags (`[LESSON_CONTENT]`, `[COURSE_CONTENT]`)
- **CourseGeneratorService**: Creates WordPress posts with HTML content
- **Output Format**: HTML generated from Markdown conversion

### 1.2 Problem Statement
- Content is currently generated in Classic Editor format (HTML)
- Gutenberg editor requires block-formatted content with HTML comments
- Users need content that works in both editors without manual conversion

---

## 2. Technical Requirements

### 2.1 Gutenberg Block Format
```html
<!-- wp:paragraph -->
<p>Content here</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Section Title</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
    <li>Item 1</li>
    <li>Item 2</li>
</ul>
<!-- /wp:list -->
```

### 2.2 Detection Requirements
- Detect active editor type (Gutenberg vs Classic)
- Support WordPress 5.0+ with Gutenberg
- Handle Classic Editor plugin scenarios

### 2.3 Compatibility Requirements
- Content must work in both editors
- No data loss when switching editors
- Maintain existing content quality

---

## 3. Implementation Architecture

### 3.1 New Components

#### 3.1.1 Editor Detection Service
```php
namespace MemberPressCoursesCopilot\Services;

class EditorDetectionService extends BaseService {
    public function isGutenbergActive($post = null): bool;
    public function isGutenbergActiveForPostType($postType): bool;
    public function getActiveEditor(): string; // 'gutenberg' | 'classic'
}
```

#### 3.1.2 Content Format Converter
```php
namespace MemberPressCoursesCopilot\Services\ContentConverter;

interface IContentConverter {
    public function convert(string $content, array $options = []): string;
    public function supports(string $content): bool;
    public function getSourceFormat(): string;
    public function getTargetFormat(): string;
}

class HtmlToGutenbergConverter extends AbstractContentConverter {
    // Converts HTML to Gutenberg block format
}
```

#### 3.1.3 Content Format Manager
```php
namespace MemberPressCoursesCopilot\Services;

class ContentFormatManager extends BaseService {
    public function formatForEditor(string $content, string $editorType): string;
    public function detectFormat(string $content): string;
    public function convertBetweenFormats(string $content, string $from, string $to): string;
}
```

### 3.2 Modified Components

#### 3.2.1 LLMService Modifications
- Add `buildGutenbergPrompt()` method
- Modify `buildLessonContentPrompt()` to support both formats
- Add format parameter to content generation methods

#### 3.2.2 EditorAIIntegrationService Modifications
- Detect active editor in `handleAIChat()`
- Generate appropriate format based on editor
- Update content insertion logic

#### 3.2.3 JavaScript Modifications
- Update `editor-ai-modal.js` to handle Gutenberg format
- Modify content application for Block Editor
- Ensure proper format detection

---

## 4. Implementation Phases

### Phase 1: Foundation (Week 1)
1. Create EditorDetectionService
2. Implement basic editor detection logic
3. Add unit tests for detection
4. Create interfaces for content conversion

### Phase 2: Content Conversion (Week 2)
1. Implement HtmlToGutenbergConverter
2. Create ContentFormatManager service
3. Add HTML parsing logic
4. Implement Gutenberg block generation
5. Add comprehensive unit tests

### Phase 3: AI Prompt Modifications (Week 3)
1. Modify LLMService prompts
2. Add conditional prompt generation
3. Update EditorAIIntegrationService
4. Test AI-generated content in both formats

### Phase 4: JavaScript Integration (Week 4)
1. Update editor-ai-modal.js
2. Add format detection in JavaScript
3. Implement content application for Block Editor
4. Add client-side error handling

### Phase 5: Testing & Refinement (Week 5)
1. Comprehensive integration testing
2. User acceptance testing
3. Performance optimization
4. Documentation updates

---

## 5. AI Prompt Modifications

### 5.1 Gutenberg Format Prompt Addition
```
When generating content for Gutenberg editor, use WordPress block format:

BLOCK FORMAT EXAMPLES:
- Paragraphs: <!-- wp:paragraph --><p>Content</p><!-- /wp:paragraph -->
- Headings: <!-- wp:heading {"level":2} --><h2>Title</h2><!-- /wp:heading -->
- Lists: <!-- wp:list --><ul><li>Item</li></ul><!-- /wp:list -->
- Code blocks: <!-- wp:code --><pre class="wp-block-code"><code>Code</code></pre><!-- /wp:code -->
- Quotes: <!-- wp:quote --><blockquote class="wp-block-quote"><p>Quote</p></blockquote><!-- /wp:quote -->

Structure your content using these block formats, ensuring each content element is properly wrapped.
```

### 5.2 Conditional Prompt Logic
```php
if ($this->editorDetection->isGutenbergActive()) {
    $prompt .= $this->getGutenbergFormatInstructions();
} else {
    $prompt .= $this->getClassicFormatInstructions();
}
```

---

## 6. Testing Strategy

### 6.1 Unit Tests
- EditorDetectionService tests
- Content converter tests
- Format manager tests
- AI prompt generation tests

### 6.2 Integration Tests
- End-to-end content generation
- Editor switching scenarios
- Format conversion accuracy
- Backward compatibility

### 6.3 Manual Testing Checklist
- [ ] Generate lesson content in Gutenberg
- [ ] Generate course content in Gutenberg
- [ ] Generate content in Classic Editor
- [ ] Switch between editors with existing content
- [ ] Verify formatting preservation
- [ ] Test with various content types
- [ ] Verify accessibility features
- [ ] Test error handling

### 6.4 Performance Testing
- Measure content generation time
- Compare format conversion performance
- Monitor memory usage
- Test with large content blocks

---

## 7. Risk Mitigation

### 7.1 Identified Risks
1. **Breaking existing functionality**: Mitigate with comprehensive testing
2. **Performance degradation**: Optimize conversion algorithms
3. **AI prompt confusion**: Provide clear examples in prompts
4. **Editor detection failures**: Implement fallback mechanisms

### 7.2 Rollback Strategy
1. Feature flag for Gutenberg support
2. Ability to disable via settings
3. Maintain Classic Editor as default fallback
4. Version control for easy reversion

---

## 8. Success Criteria

### 8.1 Functional Requirements
- ✓ Content generates correctly in both editors
- ✓ No formatting loss when switching editors
- ✓ AI understands and generates proper format
- ✓ Existing functionality remains intact

### 8.2 Performance Requirements
- Content generation time < 10% increase
- Format conversion < 100ms for typical content
- No noticeable UI lag

### 8.3 Quality Requirements
- 100% backward compatibility
- No regression in existing tests
- Clean, maintainable code following standards
- Comprehensive documentation

---

## 9. Timeline

| Phase | Duration | Start Date | End Date | Status |
|-------|----------|------------|----------|---------|
| Phase 1: Foundation | 1 week | TBD | TBD | Not Started |
| Phase 2: Conversion | 1 week | TBD | TBD | Not Started |
| Phase 3: AI Prompts | 1 week | TBD | TBD | Not Started |
| Phase 4: JavaScript | 1 week | TBD | TBD | Not Started |
| Phase 5: Testing | 1 week | TBD | TBD | Not Started |

---

## 10. Previous Implementation Issues

### 10.1 What Went Wrong
- Incomplete implementation causing 500 errors
- Missing class dependencies
- Autoloading issues
- Lack of error handling

### 10.2 Lessons Learned
1. Implement incrementally with testing at each step
2. Ensure all dependencies are properly registered
3. Add comprehensive error handling
4. Test in isolation before integration
5. Follow existing codebase patterns exactly

---

## 11. Implementation Checklist

### 11.1 Pre-Implementation
- [ ] Review existing codebase patterns
- [ ] Set up testing environment
- [ ] Create feature branch
- [ ] Document dependencies

### 11.2 Implementation
- [ ] Create EditorDetectionService
- [ ] Implement content converters
- [ ] Modify AI prompts
- [ ] Update JavaScript
- [ ] Add error handling
- [ ] Write unit tests
- [ ] Write integration tests

### 11.3 Post-Implementation
- [ ] Code review
- [ ] Performance testing
- [ ] User acceptance testing
- [ ] Documentation update
- [ ] Deployment planning

---

## 12. Code Examples

### 12.1 Editor Detection
```php
public function isGutenbergActive($post = null): bool {
    // Check if Gutenberg is available
    if (!function_exists('use_block_editor_for_post')) {
        return false;
    }
    
    // Check specific post
    if ($post !== null) {
        return use_block_editor_for_post($post);
    }
    
    // Check current screen
    $current_screen = get_current_screen();
    if ($current_screen && method_exists($current_screen, 'is_block_editor')) {
        return $current_screen->is_block_editor();
    }
    
    return false;
}
```

### 12.2 Content Conversion Example
```php
public function convertParagraph(string $content): string {
    return sprintf(
        "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->",
        $content
    );
}

public function convertHeading(string $content, int $level = 2): string {
    return sprintf(
        '<!-- wp:heading {"level":%d} -->' . "\n" .
        '<h%d>%s</h%d>' . "\n" .
        '<!-- /wp:heading -->',
        $level,
        $level,
        $content,
        $level
    );
}
```

---

## 13. Dependencies

### 13.1 WordPress Core
- WordPress 5.0+ (Gutenberg introduction)
- Block Editor API
- Classic Editor plugin compatibility

### 13.2 External Libraries
- None required (using WordPress core functions)

### 13.3 Internal Dependencies
- BaseService class
- Logger trait
- Service container
- Existing AI services

---

## 14. Security Considerations

1. **Input Sanitization**: All content must be sanitized
2. **Output Escaping**: Proper escaping for block attributes
3. **Nonce Verification**: Maintain existing security measures
4. **Capability Checks**: Ensure proper user permissions

---

## 15. Documentation Updates

### 15.1 User Documentation
- How to use with Gutenberg
- Switching between editors
- Troubleshooting guide

### 15.2 Developer Documentation
- API changes
- New service descriptions
- Integration examples

### 15.3 Code Documentation
- PHPDoc blocks for new methods
- Inline comments for complex logic
- README updates

---

## Appendix A: Gutenberg Block Reference

### Common Block Types
1. `core/paragraph` - Basic text blocks
2. `core/heading` - H1-H6 headings
3. `core/list` - Ordered and unordered lists
4. `core/quote` - Blockquotes
5. `core/code` - Code blocks
6. `core/image` - Image blocks
7. `core/separator` - Horizontal rules
8. `core/group` - Container blocks

### Block Attributes
- `level` - Heading levels (1-6)
- `ordered` - List type (true/false)
- `align` - Text alignment
- `className` - CSS classes
- `anchor` - HTML anchor

---

## Appendix B: Testing Scenarios

1. **New Content Generation**
   - Generate lesson in Gutenberg
   - Generate lesson in Classic
   - Compare output quality

2. **Content Migration**
   - Classic content in Gutenberg
   - Gutenberg content in Classic
   - Multiple editor switches

3. **Edge Cases**
   - Empty content
   - Very large content
   - Special characters
   - Nested structures

4. **Error Conditions**
   - Network failures
   - AI timeout
   - Invalid format
   - Missing dependencies