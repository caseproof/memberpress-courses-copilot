# Quiz Answer Save Flow Analysis

## Overview

This document explains the complete flow of how quiz question data (especially answers) gets saved from the Redux store to the database in MemberPress Courses.

## Architecture Components

### 1. **MemberPress Course Quizzes Plugin**
- Separate plugin: `memberpress-course-quizzes`
- Must be installed and activated for quiz functionality
- Provides the backend API and database structure

### 2. **Redux Store**
- Store name: `memberpress/course/question`
- Manages question state during editing
- Located in: `/memberpress-course-quizzes/build/index.js`

### 3. **Database Structure**
- Questions are stored in a custom table: `mepr_questions`
- Question model: `/app/models/Question.php`
- Fields include: id, quiz_id, number, text, options, answer, type, required, points, feedback, settings

## Save Flow Process

### 1. **Block Editor Integration**

When a quiz is being edited in the block editor:

1. Each question block has a `questionId` attribute
2. Questions can be placeholders (temporary) or have actual IDs from the database

### 2. **Redux Store Actions**

Key actions in the question store:

```javascript
// Add a placeholder question before it gets a real ID
addPlaceholder: (clientId, values) => ({
    type: "ADD_PLACEHOLDER",
    clientId: clientId,
    values: values
})

// Reserve a real question ID from the database
*getNextQuestionId(quizId, clientId) {
    const path = MPCS_Course_Data.api.reserveId + quizId;
    return {
        type: "RESERVE_ID",
        id: yield fetchFromAPI({path: path}),
        clientId: clientId
    }
}

// Save all questions for a quiz
*saveQuestions(quizId, questions, order) {
    const response = yield pushToApi(
        `mpcs/courses/quiz/${quizId}/questions`,
        {
            questions: questions,
            order: order
        }
    );
    return {
        type: "SAVE_QUESTIONS",
        errors: response.errors,
        ids: response.ids
    }
}
```

### 3. **Save Process**

The save process is triggered automatically when the post is saved:

```javascript
// Subscribe to WordPress save events
subscribe(() => {
    const wasSaving = isSavingPost && !isAutosavingPost && !isSavingPost();
    
    if (wasSaving) {
        // Get all question blocks
        const questionIds = getBlocks()
            .filter(block => block.name.indexOf("memberpress-course-quizzes") === 0)
            .map(block => block.attributes.questionId);
        
        // Save questions to database
        saveQuestions(getCurrentPostId(), getQuestions(), questionIds)
            .then(response => {
                // Update block IDs if any were replaced
                if (response.ids && response.ids.length) {
                    // Update blocks with new IDs...
                }
            });
    }
});
```

### 4. **API Endpoints**

The save process uses these REST API endpoints:

- **Reserve Question ID**: `GET /mpcs/courses/reserveQuestionId/{quiz_id}`
  - Creates a placeholder row in the database
  - Returns the new question ID

- **Save Questions**: `POST /mpcs/courses/quiz/{quiz_id}/questions`
  - Saves all question data for a quiz
  - Parameters: `questions` (object), `order` (array)
  - Returns: errors and replaced IDs

- **Release Question**: `POST /mpcs/courses/releaseQuestion/{id}`
  - Deletes placeholder or orphans unused questions

### 5. **Question Data Structure**

Questions are saved with this structure:

```php
// For multiple choice questions
[
    'questionId' => 123,
    'question' => 'What is the answer?',
    'number' => 1,
    'type' => 'multiple-choice',
    'required' => true,
    'points' => 1,
    'options' => [
        ['value' => 'Option A', 'isCorrect' => false],
        ['value' => 'Option B', 'isCorrect' => true],
        ['value' => 'Option C', 'isCorrect' => false]
    ],
    'feedback' => 'Explanation text'
]
```

### 6. **Data Processing**

The `Questions::save_question()` helper processes the data:

1. Sanitizes all input fields
2. Converts the frontend format to database format
3. For multiple choice: extracts correct answer index from options
4. For multiple answer: builds array of correct answer indices
5. Serializes arrays (options, settings) before database storage

### 7. **Database Storage**

The `Question` model handles database operations:

```php
public function store($validate = true) {
    // Validate the question
    if ($validate) {
        $this->validate();
    }
    
    // Serialize array fields
    $attrs['answer'] = is_array($attrs['answer']) ? 
        serialize($attrs['answer']) : $attrs['answer'];
    $attrs['options'] = serialize($attrs['options']);
    $attrs['settings'] = serialize($attrs['settings']);
    
    // Insert or update
    if ($this->id > 0) {
        $db->update_record($db->questions, $this->id, $attrs);
    } else {
        $this->id = $db->create_record($db->questions, $attrs);
    }
    
    return $this->id;
}
```

## Important Notes

### 1. **Two-Stage Save Process**
- Questions are first created as placeholders when blocks are added
- Actual data is saved when the post is saved
- This prevents orphaned questions if the user doesn't save

### 2. **Answer Format Variations**
- **Multiple Choice**: answer is a single index (e.g., "1")
- **Multiple Answer**: answer is an array of indices (e.g., [0, 2])
- **True/False**: answer is 0 or 1
- **Text/Essay**: answer contains the expected text
- **Fill Blank**: answer contains text with [brackets] for blanks

### 3. **Client-Side State Management**
- Redux store maintains question state during editing
- Changes are batched and saved when the post saves
- Block attributes only store the question ID reference

### 4. **Error Handling**
- Save errors are captured and returned in the response
- If a question ID changes (due to deletion/recreation), the new ID is returned
- Blocks are automatically updated with new IDs

## Troubleshooting

If quiz answers aren't being saved:

1. **Check Plugin Installation**
   - Ensure `memberpress-course-quizzes` is installed and activated
   - Check for JavaScript console errors

2. **Verify REST API**
   - Check if the REST API endpoints are accessible
   - Look for authentication/permission issues

3. **Inspect Redux Store**
   - Use Redux DevTools to monitor the question store
   - Check if questions have proper structure before save

4. **Database Issues**
   - Verify the `mepr_questions` table exists
   - Check for database write permissions

5. **Block Registration**
   - Ensure question blocks are properly registered
   - Verify block attributes include `questionId`

## Integration with AI Quiz Generator

The AI Quiz Generator in the copilot plugin integrates by:

1. Creating question blocks with proper attributes
2. Using `addPlaceholder` to add questions to the Redux store
3. Optionally calling `getNextQuestionId` to reserve IDs
4. Letting the normal save process persist the data

The key is ensuring the question data structure matches what the quiz plugin expects, particularly the format of answers and options.