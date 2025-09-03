# Data Flow Documentation

## Table of Contents

- [Overview](#overview)
- [Course Creation Workflow](#course-creation-workflow)
- [Quiz Generation Workflow](#quiz-generation-workflow)
- [Session Management Flow](#session-management-flow)
- [Editor AI Integration Flow](#editor-ai-integration-flow)
- [Error Handling Flow](#error-handling-flow)
- [Data Persistence Patterns](#data-persistence-patterns)
- [State Management](#state-management)
- [Performance Optimizations](#performance-optimizations)

## Overview

This document details the data flow patterns throughout the MemberPress Courses Copilot plugin, showing how data moves between components, how state is managed, and how different workflows interact.

### Data Flow Principles

1. **Unidirectional Flow**: Data flows in one direction through the system
2. **Immutable Data**: Data objects are immutable where possible
3. **Centralized State**: Critical state is managed centrally
4. **Event-Driven Updates**: Components react to data changes via events
5. **Validation at Boundaries**: Data is validated at system boundaries

## Course Creation Workflow

### High-Level Flow

```
User Input → Frontend → AJAX Controller → Services → AI API → Database → Response
```

### Detailed Course Creation Flow

#### 1. User Initiates Course Creation

**Entry Points:**
- Standalone Course Editor (`/wp-admin/admin.php?page=mpcc-course-editor`)
- Courses Integration Modal (triggered from courses listing)

**Initial Data:**
```javascript
{
    user_input: "Create a Python programming course",
    context: "course_creation",
    session_id: null // New session
}
```

#### 2. Frontend Processing

**File:** `assets/js/course-editor-page.js`

```javascript
function sendChatMessage(message) {
    // 1. Validate user input
    if (!validateMessage(message)) {
        showError('Invalid message');
        return;
    }
    
    // 2. Prepare request data
    const requestData = {
        action: 'mpcc_chat_message',
        nonce: mpcc_editor.nonce,
        message: message,
        session_id: getCurrentSessionId(),
        conversation_history: getConversationHistory(),
        course_structure: getCurrentCourseStructure()
    };
    
    // 3. Send AJAX request
    makeAjaxRequest(requestData, handleChatResponse);
}
```

#### 3. AJAX Controller Processing

**File:** `Controllers/SimpleAjaxController.php`

```php
public function handleChatMessage(): void {
    // 1. Security validation
    $this->validateNonce('mpcc_editor_nonce');
    $this->validateCapability('edit_posts');
    
    // 2. Input sanitization
    $message = sanitize_textarea_field($_POST['message']);
    $sessionId = sanitize_text_field($_POST['session_id']);
    $history = $this->sanitizeArray($_POST['conversation_history']);
    
    // 3. Load or create session
    $session = $this->loadOrCreateSession($sessionId);
    
    // 4. Delegate to conversation flow handler
    $response = $this->conversationFlowHandler->processMessage(
        $message, 
        $session, 
        $history
    );
    
    // 5. Return formatted response
    wp_send_json_success($response);
}
```

#### 4. Service Layer Processing

**File:** `Services/ConversationFlowHandler.php`

```php
public function processMessage(string $message, ConversationSession $session, array $history): array {
    // 1. Determine conversation stage
    $stage = $this->determineConversationStage($session, $history);
    
    // 2. Process based on stage
    switch ($stage) {
        case 'initial':
            return $this->handleInitialRequest($message, $session);
        case 'course_structure':
            return $this->handleCourseStructuring($message, $session);
        case 'content_generation':
            return $this->handleContentGeneration($message, $session);
        case 'finalization':
            return $this->handleFinalization($message, $session);
    }
}

private function handleCourseStructuring(string $message, ConversationSession $session): array {
    // 1. Prepare AI prompt with context
    $prompt = $this->buildStructurePrompt($message, $session->getContext());
    
    // 2. Send to LLM service
    $aiResponse = $this->llmService->sendMessage($prompt, $session->getHistory());
    
    // 3. Parse course structure
    $courseStructure = $this->parseCourseStructure($aiResponse['message']);
    
    // 4. Validate structure
    $validation = $this->courseGenerator->validateCourseData($courseStructure);
    if (!$validation['valid']) {
        return $this->handleValidationError($validation);
    }
    
    // 5. Update session state
    $session->setCourseStructure($courseStructure);
    $session->setState('ready_to_create');
    $this->conversationManager->saveSession($session);
    
    return [
        'message' => $aiResponse['message'],
        'course_structure' => $courseStructure,
        'session_id' => $session->getSessionId(),
        'conversation_state' => $session->getState()
    ];
}
```

#### 5. AI Service Communication

**File:** `Services/LLMService.php`

```php
public function sendMessage(string $message, array $conversationHistory = []): array {
    // 1. Prepare request payload
    $payload = [
        'messages' => $this->formatMessages($message, $conversationHistory),
        'model' => $this->getConfiguredModel(),
        'temperature' => $this->getTemperature(),
        'max_tokens' => $this->getMaxTokens()
    ];
    
    // 2. Make API request to gateway
    $response = $this->makeGatewayRequest('/chat/completions', $payload);
    
    // 3. Parse and validate response
    $parsed = $this->parseAIResponse($response);
    
    // 4. Log interaction
    $this->logInteraction($payload, $parsed);
    
    return $parsed;
}

private function makeGatewayRequest(string $endpoint, array $payload): array {
    // 1. Add authentication headers
    $headers = $this->getAuthHeaders();
    
    // 2. Make HTTP request with retry logic
    $attempts = 0;
    do {
        $response = wp_remote_post($this->gatewayUrl . $endpoint, [
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => $this->timeout
        ]);
        
        if (!is_wp_error($response)) {
            break;
        }
        
        $attempts++;
        sleep(pow(2, $attempts)); // Exponential backoff
    } while ($attempts < $this->maxRetries);
    
    return json_decode(wp_remote_retrieve_body($response), true);
}
```

#### 6. Database Persistence

**File:** `Services/ConversationManager.php`

```php
public function saveSession(ConversationSession $session): bool {
    // 1. Serialize session data
    $data = [
        'session_id' => $session->getSessionId(),
        'user_id' => $session->getUserId(),
        'title' => $session->getTitle(),
        'messages' => json_encode($session->getMessages()),
        'context' => json_encode($session->getContext()),
        'current_state' => $session->getState(),
        'metadata' => json_encode($session->getMetadata()),
        'updated_at' => current_time('mysql')
    ];
    
    // 2. Update or insert
    if ($session->exists()) {
        return $this->databaseService->update(
            'mpcc_conversations',
            $data,
            ['session_id' => $session->getSessionId()]
        ) > 0;
    } else {
        $result = $this->databaseService->insert('mpcc_conversations', $data);
        $session->markAsExisting();
        return $result > 0;
    }
}
```

#### 7. Frontend Response Handling

**File:** `assets/js/course-editor-page.js`

```javascript
function handleChatResponse(error, data) {
    if (error) {
        handleChatError(error);
        return;
    }
    
    // 1. Update conversation history
    addMessageToHistory({
        role: 'assistant',
        content: data.message,
        timestamp: Date.now()
    });
    
    // 2. Update course structure if provided
    if (data.course_structure) {
        updateCourseStructure(data.course_structure);
        showCoursePreview(data.course_structure);
    }
    
    // 3. Update UI state
    if (data.conversation_state === 'ready_to_create') {
        showCreateCourseButton();
    }
    
    // 4. Save session state
    saveSessionState(data.session_id, {
        history: getConversationHistory(),
        structure: getCurrentCourseStructure(),
        state: data.conversation_state
    });
}
```

### Course Creation Finalization

When user decides to create the course:

#### 1. Frontend Triggers Creation

```javascript
function createCourse() {
    const requestData = {
        action: 'mpcc_create_course',
        nonce: mpcc_editor.nonce,
        session_id: getCurrentSessionId(),
        course_data: getCurrentCourseStructure()
    };
    
    makeAjaxRequest(requestData, handleCourseCreated);
}
```

#### 2. Course Generator Service

**File:** `Services/CourseGeneratorService.php`

```php
public function generateCourse(array $courseData): array {
    // 1. Begin transaction
    $this->beginTransaction();
    
    try {
        // 2. Create course post
        $courseId = wp_insert_post([
            'post_title' => $courseData['title'],
            'post_content' => $courseData['description'],
            'post_type' => 'mpcs-course',
            'post_status' => 'draft',
            'post_author' => get_current_user_id()
        ]);
        
        // 3. Create sections and lessons
        $sectionsCreated = 0;
        $lessonsCreated = 0;
        
        foreach ($courseData['sections'] as $order => $section) {
            $sectionId = $this->createSection($section, $courseId, $order);
            $sectionsCreated++;
            
            foreach ($section['lessons'] as $lessonOrder => $lesson) {
                $this->createLesson($lesson, $sectionId, $lessonOrder);
                $lessonsCreated++;
            }
        }
        
        // 4. Set course metadata
        $this->setCourseMetadata($courseId, $courseData);
        
        // 5. Commit transaction
        $this->commitTransaction();
        
        return [
            'course_id' => $courseId,
            'sections_created' => $sectionsCreated,
            'lessons_created' => $lessonsCreated,
            'edit_url' => get_edit_post_link($courseId),
            'preview_url' => get_permalink($courseId)
        ];
        
    } catch (Exception $e) {
        // 6. Rollback on error
        $this->rollbackTransaction();
        throw $e;
    }
}
```

## Quiz Generation Workflow

### Quiz Generation Flow

```
Content Source → Validation → AI Processing → Question Generation → Response
```

#### 1. Quiz Generation Initiation

**Entry Points:**
- Lesson edit page "Create Quiz" button
- Course edit page quiz generation
- Quiz editor AI modal

**Content Sources:**
- Lesson content (post_content)
- Course content (aggregated lessons)
- Custom provided content

#### 2. Content Extraction and Validation

**File:** `Controllers/MpccQuizAjaxController.php`

```php
public function generate_quiz(): void {
    // 1. Extract content based on source
    $lessonId = absint($_POST['lesson_id'] ?? 0);
    $courseId = absint($_POST['course_id'] ?? 0);
    $customContent = sanitize_textarea_field($_POST['content'] ?? '');
    
    // 2. Get content from appropriate source
    $content = $this->getQuizContent($customContent, $lessonId, $courseId);
    
    // 3. Validate content
    if (empty($content)) {
        ApiResponse::errorMessage('No content available to generate quiz from');
        return;
    }
    
    // 4. Parse options
    $options = $this->parseQuizOptions($_POST['options'] ?? []);
    
    // 5. Delegate to quiz service
    $result = $this->quizAIService->generateQuestions($content, $options);
    
    // 6. Return formatted response
    wp_send_json_success($result);
}

private function getQuizContent(string $content, int $lessonId, int $courseId): string {
    if (!empty($content)) {
        return $content;
    }
    
    if ($lessonId > 0) {
        return $this->getLessonContent($lessonId);
    }
    
    if ($courseId > 0) {
        return $this->getCourseContent($courseId);
    }
    
    return '';
}
```

#### 3. Quiz AI Service Processing

**File:** `Services/MpccQuizAIService.php`

```php
public function generateQuestions(string $content, array $options = []): array {
    // 1. Validate content quality
    $validation = $this->validateContent($content);
    if (!$validation['valid']) {
        return [
            'error' => true,
            'message' => $validation['message'],
            'suggestion' => $validation['suggestion']
        ];
    }
    
    // 2. Prepare generation options
    $generationOptions = [
        'type' => $options['question_type'] ?? 'multiple_choice',
        'count' => min(max(intval($options['num_questions'] ?? 10), 1), 20),
        'difficulty' => $options['difficulty'] ?? 'medium',
        'custom_prompt' => $options['custom_prompt'] ?? ''
    ];
    
    // 3. Build AI prompt
    $prompt = $this->buildQuizPrompt($content, $generationOptions);
    
    // 4. Generate via LLM
    $response = $this->llmService->sendMessage($prompt);
    
    // 5. Parse questions
    $questions = $this->parseQuestions($response['message']);
    
    // 6. Validate generated questions
    $validated = $this->validateGeneratedQuestions($questions);
    
    return [
        'questions' => $validated['questions'],
        'total' => count($validated['questions']),
        'type' => $generationOptions['type'],
        'quality_score' => $validated['quality_score']
    ];
}

private function validateContent(string $content): array {
    $length = strlen($content);
    $words = str_word_count($content);
    
    // Minimum content requirements
    if ($length < 200) {
        return [
            'valid' => false,
            'message' => 'Content is too short to generate meaningful questions',
            'suggestion' => 'Please provide at least 200 characters of content for quiz generation.'
        ];
    }
    
    if ($words < 50) {
        return [
            'valid' => false,
            'message' => 'Content has insufficient word count',
            'suggestion' => 'Please provide at least 50 words of content for better quiz generation.'
        ];
    }
    
    // Content quality checks
    $sentences = preg_split('/[.!?]+/', $content);
    if (count($sentences) < 3) {
        return [
            'valid' => false,
            'message' => 'Content lacks sufficient detail',
            'suggestion' => 'Consider adding more explanatory sentences to improve quiz generation quality.'
        ];
    }
    
    return ['valid' => true];
}
```

#### 4. AI Prompt Engineering

```php
private function buildQuizPrompt(string $content, array $options): string {
    $questionType = $options['type'];
    $count = $options['count'];
    $difficulty = $options['difficulty'];
    
    $prompt = "Based on the following content, generate {$count} {$difficulty} {$questionType} questions:\n\n";
    $prompt .= "CONTENT:\n{$content}\n\n";
    
    switch ($questionType) {
        case 'multiple_choice':
            $prompt .= "For each question, provide:\n";
            $prompt .= "- A clear question\n";
            $prompt .= "- 4 answer options (A, B, C, D)\n";
            $prompt .= "- The correct answer letter\n";
            $prompt .= "- A brief explanation\n";
            break;
            
        case 'true_false':
            $prompt .= "For each question, provide:\n";
            $prompt .= "- A statement to evaluate\n";
            $prompt .= "- Whether it's true or false\n";
            $prompt .= "- A brief explanation\n";
            break;
    }
    
    if (!empty($options['custom_prompt'])) {
        $prompt .= "\nAdditional Instructions: " . $options['custom_prompt'];
    }
    
    $prompt .= "\nPlease format your response as valid JSON.";
    
    return $prompt;
}
```

#### 5. Question Parsing and Validation

```php
private function parseQuestions(string $response): array {
    // 1. Extract JSON from response
    $json = $this->extractJsonFromResponse($response);
    
    // 2. Parse questions
    $parsed = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from AI');
    }
    
    // 3. Normalize question structure
    $questions = [];
    foreach ($parsed['questions'] ?? [] as $q) {
        $questions[] = $this->normalizeQuestion($q);
    }
    
    return $questions;
}

private function normalizeQuestion(array $question): array {
    return [
        'type' => $question['type'] ?? 'multiple_choice',
        'question' => trim($question['question'] ?? ''),
        'options' => $question['options'] ?? [],
        'correct_answer' => $question['correct_answer'] ?? '',
        'explanation' => trim($question['explanation'] ?? ''),
        'difficulty' => $question['difficulty'] ?? 'medium',
        'points' => intval($question['points'] ?? 1)
    ];
}
```

## Session Management Flow

### Session Lifecycle

```
Session Creation → Active Use → Auto-Save → Cleanup/Expiration
```

#### 1. Session Creation

**Triggers:**
- New conversation started
- User opens course editor
- First message sent without session

**File:** `Services/ConversationManager.php`

```php
public function createSession(array $data): ConversationSession {
    // 1. Generate unique session ID
    $sessionId = $this->generateSessionId();
    
    // 2. Create session object
    $session = new ConversationSession([
        'session_id' => $sessionId,
        'user_id' => get_current_user_id(),
        'title' => $data['title'] ?? 'New Conversation',
        'messages' => [],
        'context' => $data['context'] ?? [],
        'current_state' => 'initial',
        'metadata' => $data['metadata'] ?? [],
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ]);
    
    // 3. Persist to database
    if ($this->saveSession($session)) {
        $this->logger->info('Session created', ['session_id' => $sessionId]);
        return $session;
    }
    
    throw new DatabaseException('Failed to create session');
}
```

#### 2. Session State Management

**File:** `Models/ConversationSession.php`

```php
class ConversationSession {
    private string $sessionId;
    private int $userId;
    private string $title;
    private array $messages;
    private array $context;
    private string $currentState;
    private array $metadata;
    
    public function addMessage(array $message): void {
        $message['timestamp'] = current_time('mysql');
        $message['id'] = uniqid();
        
        $this->messages[] = $message;
        $this->updated_at = current_time('mysql');
        $this->markAsDirty();
    }
    
    public function setCourseStructure(array $structure): void {
        $this->context['course_structure'] = $structure;
        $this->updated_at = current_time('mysql');
        $this->markAsDirty();
    }
    
    public function setState(string $state): void {
        $this->currentState = $state;
        $this->updated_at = current_time('mysql');
        $this->markAsDirty();
    }
}
```

#### 3. Auto-Save Mechanism

**Frontend Auto-Save:**
```javascript
// Auto-save every 30 seconds
setInterval(function() {
    if (sessionNeedsSaving()) {
        autoSaveSession();
    }
}, 30000);

function autoSaveSession() {
    const requestData = {
        action: 'mpcc_auto_save_session',
        nonce: mpcc_ajax.auto_save_nonce,
        session_id: getCurrentSessionId(),
        session_data: {
            conversation_history: getConversationHistory(),
            course_structure: getCurrentCourseStructure(),
            ui_state: getCurrentUIState()
        }
    };
    
    makeAjaxRequest(requestData, function(error, data) {
        if (!error) {
            markSessionAsSaved();
        }
    });
}
```

**Backend Auto-Save Handler:**
```php
public function handleAjaxAutoSave(): void {
    // 1. Validate request
    $this->validateNonce('mpcc_auto_save_nonce');
    
    // 2. Load session
    $sessionId = sanitize_text_field($_POST['session_id']);
    $session = $this->conversationManager->loadSession($sessionId);
    
    if (!$session) {
        wp_send_json_error('Session not found');
        return;
    }
    
    // 3. Update session data
    $sessionData = $_POST['session_data'];
    if (isset($sessionData['conversation_history'])) {
        $session->setMessages($sessionData['conversation_history']);
    }
    
    if (isset($sessionData['course_structure'])) {
        $session->setCourseStructure($sessionData['course_structure']);
    }
    
    // 4. Save session
    if ($this->conversationManager->saveSession($session)) {
        wp_send_json_success(['auto_saved' => true]);
    } else {
        wp_send_json_error('Auto-save failed');
    }
}
```

#### 4. Session Cleanup

**Scheduled Cleanup:**
```php
// Register cleanup hook
add_action('mpcc_cleanup_sessions', [$this, 'cleanupExpiredSessions']);

// Schedule cleanup if not already scheduled
if (!wp_next_scheduled('mpcc_cleanup_sessions')) {
    wp_schedule_event(time(), 'daily', 'mpcc_cleanup_sessions');
}

public function cleanupExpiredSessions(): void {
    // Delete sessions older than 30 days
    $expiredDate = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    $deletedCount = $this->databaseService->delete(
        'mpcc_conversations',
        ['updated_at <' => $expiredDate]
    );
    
    // Also cleanup associated lesson drafts
    $this->lessonDraftService->cleanupOrphanedDrafts();
    
    $this->logger->info('Session cleanup completed', [
        'deleted_sessions' => $deletedCount
    ]);
}
```

## Editor AI Integration Flow

### Integration Flow

```
Post Editor → Metabox → AI Chat → Content Suggestions → Post Update
```

#### 1. Metabox Registration

**File:** `Services/EditorAIIntegrationService.php`

```php
public function init(): void {
    add_action('add_meta_boxes', [$this, 'addAIMetabox']);
    add_action('wp_ajax_mpcc_editor_ai_chat', [$this, 'handleAIChat']);
}

public function addAIMetabox(): void {
    $post_types = ['mpcs-course', 'mpcs-lesson'];
    
    foreach ($post_types as $post_type) {
        add_meta_box(
            'mpcc-ai-assistant',
            'AI Course Assistant',
            [$this, 'renderAIMetabox'],
            $post_type,
            'side',
            'high'
        );
    }
}
```

#### 2. AI Chat Processing

```php
public function handleAIChat(): void {
    // 1. Security and validation
    $this->validateNonce('mpcc_ai_assistant');
    $this->validateCapability('edit_posts');
    
    // 2. Extract context
    $postId = absint($_POST['post_id'] ?? 0);
    $postType = sanitize_text_field($_POST['post_type'] ?? '');
    $message = sanitize_textarea_field($_POST['message']);
    
    // 3. Get post context
    $postContext = $this->getPostContext($postId, $postType);
    
    // 4. Build AI prompt with context
    $prompt = $this->buildEditorPrompt($message, $postContext);
    
    // 5. Get AI response
    $aiResponse = $this->llmService->sendMessage($prompt);
    
    // 6. Parse suggestions
    $suggestions = $this->parseSuggestions($aiResponse['message']);
    
    wp_send_json_success([
        'message' => $aiResponse['message'],
        'suggestions' => $suggestions,
        'context' => $postContext
    ]);
}

private function getPostContext(int $postId, string $postType): array {
    if ($postId === 0) {
        return ['type' => 'new_' . $postType];
    }
    
    $post = get_post($postId);
    $context = [
        'type' => $postType,
        'title' => $post->post_title,
        'content' => $post->post_content,
        'status' => $post->post_status
    ];
    
    // Add course-specific context
    if ($postType === 'mpcs-course') {
        $context['sections'] = $this->getCourseSections($postId);
        $context['lesson_count'] = $this->getLessonCount($postId);
    }
    
    // Add lesson-specific context
    if ($postType === 'mpcs-lesson') {
        $context['course_id'] = get_post_meta($postId, '_mpcs_course_id', true);
        $context['section_id'] = get_post_meta($postId, '_mpcs_lesson_section_id', true);
    }
    
    return $context;
}
```

## Error Handling Flow

### Error Propagation Chain

```
Service Layer → Controller → Frontend → User Feedback
```

#### 1. Service Level Errors

**File:** `Services/LLMService.php`

```php
public function sendMessage(string $message, array $conversationHistory = []): array {
    try {
        // API call
        $response = $this->makeGatewayRequest('/chat', $payload);
        
        if (isset($response['error'])) {
            throw new AIServiceException($response['error']['message']);
        }
        
        return $this->parseResponse($response);
        
    } catch (AIServiceException $e) {
        $this->logger->error('AI service error', [
            'message' => $message,
            'error' => $e->getMessage()
        ]);
        throw $e;
        
    } catch (Exception $e) {
        $this->logger->error('Unexpected error in LLM service', [
            'message' => $message,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw new AIServiceException('AI service temporarily unavailable');
    }
}
```

#### 2. Controller Error Handling

**File:** `Controllers/SimpleAjaxController.php`

```php
public function handleChatMessage(): void {
    try {
        // Process message
        $response = $this->processMessage();
        wp_send_json_success($response);
        
    } catch (ValidationException $e) {
        ApiResponse::errorMessage(
            $e->getMessage(),
            ApiResponse::ERROR_INVALID_PARAMETER,
            400
        );
        
    } catch (AIServiceException $e) {
        ApiResponse::errorMessage(
            'AI service is temporarily unavailable. Please try again.',
            ApiResponse::ERROR_AI_SERVICE,
            503
        );
        
    } catch (DatabaseException $e) {
        $this->logger->error('Database error in chat handler', [
            'error' => $e->getMessage()
        ]);
        
        ApiResponse::errorMessage(
            'Unable to save conversation. Please try again.',
            ApiResponse::ERROR_DATABASE_ERROR,
            500
        );
        
    } catch (Exception $e) {
        $this->logger->error('Unexpected error in chat handler', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        ApiResponse::errorMessage(
            'An unexpected error occurred. Please try again.',
            ApiResponse::ERROR_GENERAL,
            500
        );
    }
}
```

#### 3. Frontend Error Handling

```javascript
function handleChatError(error) {
    // Log error for debugging
    console.error('Chat error:', error);
    
    // Show user-appropriate message
    switch (error.code) {
        case 'mpcc_invalid_nonce':
            showToast('Session expired. Refreshing page...', 'warning');
            setTimeout(() => location.reload(), 2000);
            break;
            
        case 'mpcc_ai_service_error':
            showToast('AI service is temporarily unavailable. Please try again in a few minutes.', 'error');
            enableRetryButton();
            break;
            
        case 'mpcc_invalid_parameter':
            showToast('Please check your input and try again.', 'warning');
            break;
            
        default:
            showToast('Something went wrong. Please try again.', 'error');
            enableRetryButton();
    }
    
    // Update UI state
    setLoadingState(false);
    enableChatInput();
}
```

## Data Persistence Patterns

### Database Operations

#### 1. Conversation Persistence

```sql
-- Insert new conversation
INSERT INTO wp_mpcc_conversations (
    session_id, user_id, title, messages, context, 
    current_state, metadata, created_at, updated_at
) VALUES (
    %s, %d, %s, %s, %s, %s, %s, NOW(), NOW()
);

-- Update existing conversation
UPDATE wp_mpcc_conversations 
SET messages = %s, context = %s, current_state = %s, 
    metadata = %s, updated_at = NOW()
WHERE session_id = %s AND user_id = %d;
```

#### 2. Lesson Draft Persistence

```sql
-- Insert/Update lesson draft (ON DUPLICATE KEY UPDATE)
INSERT INTO wp_mpcc_lesson_drafts (
    session_id, section_id, lesson_id, content, 
    order_index, created_at, updated_at
) VALUES (
    %s, %s, %s, %s, %d, NOW(), NOW()
) ON DUPLICATE KEY UPDATE
    content = VALUES(content),
    order_index = VALUES(order_index),
    updated_at = VALUES(updated_at);
```

### Data Serialization

#### JSON Serialization for Complex Data

```php
// Serialize conversation messages
$messages = json_encode($session->getMessages(), JSON_UNESCAPED_UNICODE);

// Serialize course structure
$context = json_encode([
    'course_structure' => $courseStructure,
    'user_preferences' => $userPrefs,
    'generation_options' => $options
], JSON_UNESCAPED_UNICODE);

// Deserialize with error handling
$messages = json_decode($messagesJson, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    $this->logger->error('JSON decode error', [
        'error' => json_last_error_msg(),
        'data' => $messagesJson
    ]);
    $messages = [];
}
```

## State Management

### Frontend State Management

#### 1. Session State

```javascript
const SessionState = {
    // Current session data
    current: {
        sessionId: null,
        conversationHistory: [],
        courseStructure: null,
        state: 'initial'
    },
    
    // UI state
    ui: {
        isLoading: false,
        currentView: 'chat',
        expandedSections: [],
        activeLesson: null
    },
    
    // Methods
    updateSession(updates) {
        Object.assign(this.current, updates);
        this.saveToStorage();
        this.notifyListeners();
    },
    
    saveToStorage() {
        localStorage.setItem('mpcc_session_state', JSON.stringify(this.current));
    },
    
    loadFromStorage() {
        const stored = localStorage.getItem('mpcc_session_state');
        if (stored) {
            try {
                this.current = JSON.parse(stored);
            } catch (e) {
                console.warn('Failed to load session state from storage');
            }
        }
    }
};
```

#### 2. Course Structure State

```javascript
const CourseStructureState = {
    structure: null,
    
    updateStructure(newStructure) {
        this.structure = newStructure;
        this.render();
        SessionState.updateSession({ courseStructure: newStructure });
    },
    
    addSection(section) {
        if (!this.structure) {
            this.structure = { sections: [] };
        }
        
        this.structure.sections.push({
            id: generateId(),
            title: section.title,
            lessons: section.lessons || [],
            order: this.structure.sections.length
        });
        
        this.updateStructure(this.structure);
    },
    
    updateLesson(sectionId, lessonId, updates) {
        const section = this.findSection(sectionId);
        if (section) {
            const lesson = section.lessons.find(l => l.id === lessonId);
            if (lesson) {
                Object.assign(lesson, updates);
                this.updateStructure(this.structure);
            }
        }
    }
};
```

## Performance Optimizations

### 1. Database Query Optimization

```php
// Use prepared statements
$stmt = $this->wpdb->prepare(
    "SELECT * FROM {$this->table} WHERE user_id = %d AND updated_at > %s ORDER BY updated_at DESC LIMIT %d",
    $userId,
    $sinceDate,
    $limit
);

// Use indexes for frequent queries
$this->createIndex('mpcc_conversations', ['user_id', 'updated_at']);
$this->createIndex('mpcc_lesson_drafts', ['session_id', 'section_id']);
```

### 2. Response Caching

```php
class LLMService {
    private array $responseCache = [];
    private const CACHE_TTL = 300; // 5 minutes
    
    public function sendMessage(string $message, array $history = []): array {
        $cacheKey = $this->getCacheKey($message, $history);
        
        // Check cache first
        if (isset($this->responseCache[$cacheKey])) {
            $cached = $this->responseCache[$cacheKey];
            if (time() - $cached['timestamp'] < self::CACHE_TTL) {
                return $cached['response'];
            }
        }
        
        // Make request
        $response = $this->makeRequest($message, $history);
        
        // Cache response
        $this->responseCache[$cacheKey] = [
            'response' => $response,
            'timestamp' => time()
        ];
        
        return $response;
    }
}
```

### 3. Frontend Optimizations

```javascript
// Debounce auto-save
const debouncedAutoSave = debounce(function() {
    autoSaveSession();
}, 2000);

// Throttle UI updates
const throttledUpdatePreview = throttle(function(structure) {
    updateCoursePreview(structure);
}, 500);

// Lazy load components
async function loadCoursePreview() {
    if (!coursePreviewLoaded) {
        const module = await import('./course-preview-editor.js');
        coursePreviewLoaded = true;
        return module;
    }
}
```

This comprehensive data flow documentation shows how information moves through the MemberPress Courses Copilot plugin, providing developers with a clear understanding of the system's behavior and state management.