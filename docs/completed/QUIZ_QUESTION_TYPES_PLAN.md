# Quiz Question Types Enhancement Plan

## Overview
Extend the AI Quiz Generator to support multiple question types beyond just multiple-choice. MemberPress Course Quizzes already supports several question types, and we'll integrate the most commonly used ones.

## Current State
- ✅ Multiple-choice questions fully implemented
- ✅ AI generation working with lesson content
- ✅ Questions properly inserted into quiz editor
- ❌ Only one question type available
- ❌ No UI to select question types

## Supported Question Types in MemberPress Course Quizzes

### Core Types (To Implement):
1. **multiple-choice** - Radio button, single correct answer (✅ Already done)
2. **true-false** - Simple boolean questions
3. **short-answer** - Text input, exact match grading
4. **multiple-answer** - Checkboxes, multiple correct answers

### Advanced Types (Future consideration):
- **essay** - Long text, requires manual grading
- **likert-scale** - Rating scale (1-5, agree/disagree)
- **sort-values** - Drag to order items

## Implementation Plan

### Phase 1: UI Enhancement
1. **Add Question Type Selector**
   - Location: After "Select Lesson" dropdown
   - Options:
     - "Mixed Types (Recommended)" - default
     - "Multiple Choice Only"
     - "True/False Only" 
     - "Short Answer Only"
     - "Multiple Choice & True/False"
   
2. **Update Quick Action Buttons**
   - Current: "Generate Easy/Medium/Hard Questions"
   - New: Keep same buttons but respect type selection
   - Tooltip: "Generates [type] questions at [difficulty] level"

3. **Update AI Assistant Intro**
   ```
   Hi! I'm here to help you create quiz questions. I can:
   • Generate multiple question types (multiple-choice, true/false, short answer)
   • Create questions with varying difficulty levels
   • Mix question types for variety
   • Add explanations for correct answers
   • Insert questions directly into your quiz
   ```

### Phase 2: Backend Implementation

#### 1. Update LLMService Prompts
Create type-specific prompts:

```php
// Multiple Choice (existing)
"Generate {count} multiple-choice questions with 4 options each..."

// True/False
"Generate {count} true/false questions. Each should test a specific fact or concept..."

// Short Answer
"Generate {count} short-answer questions with brief (1-3 word) answers..."

// Mixed Types
"Generate {count} quiz questions mixing multiple-choice, true/false, and short-answer..."
```

#### 2. Response Format Updates
Standardize AI response format:

```json
{
  "questions": [
    {
      "type": "multiple-choice",
      "question": "What is the capital of France?",
      "options": {
        "A": "London",
        "B": "Paris", 
        "C": "Berlin",
        "D": "Madrid"
      },
      "correct_answer": "B",
      "explanation": "Paris is the capital of France."
    },
    {
      "type": "true-false",
      "question": "The Earth revolves around the Sun.",
      "options": {
        "A": "True",
        "B": "False"
      },
      "correct_answer": "A",
      "explanation": "The Earth orbits the Sun once per year."
    },
    {
      "type": "short-answer",
      "question": "What year did World War II end?",
      "correct_answer": "1945",
      "explanation": "World War II ended in 1945."
    }
  ]
}
```

#### 3. Update MpccQuizAIService
- Add `generateTrueFalseQuestions()` method
- Add `generateShortAnswerQuestions()` method
- Add `generateMixedQuestions()` method
- Update `generateQuestions()` to route based on type

#### 4. Update Question Block Creation
Modify `applyQuestions()` in quiz-ai-modal.js:

```javascript
// Detect question type and create appropriate block
let blockType;
switch (question.type) {
  case 'multiple-choice':
    blockType = 'memberpress-courses/multiple-choice-question';
    break;
  case 'true-false':
    blockType = 'memberpress-courses/true-false-question';
    break;
  case 'short-answer':
    blockType = 'memberpress-courses/short-answer-question';
    break;
}
```

### Phase 3: Question Display Enhancement

#### Preview Updates
Show questions appropriately in preview:
- Multiple-choice: Radio buttons with options
- True/False: Two radio buttons
- Short Answer: Text input field preview

#### Styling
- Use consistent styling across question types
- Clear visual distinction between types
- Maintain accessibility standards

### Phase 4: Testing & Validation

#### Test Scenarios
1. Generate each question type individually
2. Generate mixed question sets
3. Verify correct block insertion
4. Test with various lesson content types
5. Validate answer formats

#### Edge Cases
- Empty lesson content
- Very short lessons
- Technical vs non-technical content
- Different difficulty levels per type

## Technical Considerations

### Block Registration
Each question type has its own Gutenberg block:
- `memberpress-courses/multiple-choice-question`
- `memberpress-courses/true-false-question`
- `memberpress-courses/short-answer-question`
- `memberpress-courses/multiple-answer-question`

### Data Structure
Questions stored in `wp_mpcs_questions` table with:
- `type` field determining question type
- `options` as serialized array (for choice-based)
- `answer` as string or array (for multiple-answer)
- `settings` for type-specific configuration

### Grading Considerations
- Multiple-choice: Exact match
- True/False: Exact match
- Short Answer: Case-insensitive exact match
- Multiple Answer: All correct selections required

## User Experience Improvements

### Smart Defaults
- "Mixed Types" as default for variety
- Automatic type distribution (40% MC, 40% TF, 20% SA)
- Difficulty-appropriate type selection

### Helpful Hints
- Tooltip explaining each question type
- Recommendation based on lesson content
- Preview showing example of each type

## Success Metrics
- Users can generate all three main question types
- Question insertion works flawlessly
- Type selection is intuitive
- AI generates appropriate questions for each type
- Preview accurately represents final quiz

## Timeline
- Phase 1 (UI): 2-3 hours
- Phase 2 (Backend): 4-5 hours
- Phase 3 (Display): 2 hours
- Phase 4 (Testing): 2 hours
- **Total: ~11-12 hours**

## Next Steps
1. Get approval on UI design
2. Implement question type selector
3. Create type-specific AI prompts
4. Update question insertion logic
5. Test with real content
6. Document for users