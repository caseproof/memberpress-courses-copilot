# WP-CLI Commands Implementation Plan for MemberPress Courses Copilot

## Overview
Adding WP-CLI commands will provide powerful automation capabilities for course management, allowing administrators to perform bulk operations, integrate with CI/CD pipelines, and manage courses programmatically.

## Proposed Commands Structure

### 1. **Course Generation Commands**
```bash
# Generate a new course with AI
wp mpcc generate-course "JavaScript Basics" --sections=5 --lessons=20 --difficulty=beginner --publish

# Generate course from template
wp mpcc generate-course "Marketing 101" --template=business --target-audience="small business owners"

# Generate course from existing outline file
wp mpcc generate-course --from-file=course-outline.json --author=2
```

### 2. **Session Management Commands**
```bash
# List all conversation sessions
wp mpcc list-sessions --user=123 --status=active --format=table

# View specific session details
wp mpcc show-session mpcc_session_123456 --format=json

# Clean up old sessions
wp mpcc cleanup-sessions --days=30 --dry-run
wp mpcc cleanup-sessions --days=30 --yes

# Export session for backup/migration
wp mpcc export-session mpcc_session_123456 --output=session-backup.json
```

### 3. **Content Management Commands**
```bash
# Generate lesson content for existing lessons
wp mpcc generate-lesson-content 456 --style=conversational --length=1500

# Regenerate all empty lessons in a course
wp mpcc fill-empty-lessons --course=123 --batch-size=5

# Generate quizzes for lessons
wp mpcc generate-quiz --lesson=789 --questions=10 --type=multiple_choice
```

### 4. **Analytics & Reporting Commands**
```bash
# Show AI usage statistics
wp mpcc stats --period=month --format=table

# Show cost breakdown
wp mpcc costs --from="2024-01-01" --to="2024-01-31" --group-by=day

# Export usage report
wp mpcc export-usage --format=csv --output=usage-report.csv
```

## Technical Implementation Plan

### Phase 1: Infrastructure Setup (Week 1)

#### 1.1 Create WP-CLI Command Structure
```php
// src/MemberPressCoursesCopilot/Commands/MpccCommand.php
namespace MemberPressCoursesCopilot\Commands;

use WP_CLI;
use WP_CLI_Command;

class MpccCommand extends WP_CLI_Command {
    protected ConversationManager $conversationManager;
    protected CourseGeneratorService $courseGenerator;
    protected LLMService $llmService;
    
    public function __construct() {
        $this->conversationManager = new ConversationManager();
        $this->courseGenerator = new CourseGeneratorService(
            new LLMService(),
            new CourseIntegrationService()
        );
    }
}
```

#### 1.2 Register Commands
```php
// In Plugin.php or separate CLI bootstrap
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('mpcc', MpccCommand::class);
    WP_CLI::add_command('mpcc generate-course', [MpccCommand::class, 'generate_course']);
    WP_CLI::add_command('mpcc list-sessions', [MpccCommand::class, 'list_sessions']);
    // etc...
}
```

### Phase 2: Core Command Implementation (Week 2-3)

#### 2.1 Course Generation Command
```php
/**
 * Generate a new course using AI
 *
 * ## OPTIONS
 *
 * <title>
 * : The course title
 *
 * [--sections=<number>]
 * : Number of sections to generate
 * default: 5
 *
 * [--lessons=<number>]
 * : Total number of lessons
 * default: 15
 *
 * [--difficulty=<level>]
 * : Course difficulty (beginner|intermediate|advanced)
 * default: intermediate
 *
 * [--template=<type>]
 * : Course template type
 * default: auto-detect
 *
 * [--author=<id>]
 * : WordPress user ID for course author
 * default: 1
 *
 * [--publish]
 * : Publish the course immediately
 *
 * [--dry-run]
 * : Show what would be created without creating it
 *
 * ## EXAMPLES
 *
 *     # Generate a basic course
 *     $ wp mpcc generate-course "Introduction to PHP"
 *     Success: Course created with ID 123
 *
 *     # Generate with specific parameters
 *     $ wp mpcc generate-course "Advanced JavaScript" --sections=8 --lessons=40 --publish
 *     Success: Course created and published with ID 124
 */
public function generate_course($args, $assoc_args) {
    list($title) = $args;
    
    $sections = intval($assoc_args['sections'] ?? 5);
    $lessons = intval($assoc_args['lessons'] ?? 15);
    $difficulty = $assoc_args['difficulty'] ?? 'intermediate';
    $template = $assoc_args['template'] ?? null;
    $author_id = intval($assoc_args['author'] ?? 1);
    $publish = WP_CLI\Utils\get_flag_value($assoc_args, 'publish', false);
    $dry_run = WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
    
    WP_CLI::log("Generating course: $title");
    
    if ($dry_run) {
        WP_CLI::log("DRY RUN - Would create:");
        WP_CLI::log("- Title: $title");
        WP_CLI::log("- Sections: $sections");
        WP_CLI::log("- Lessons: $lessons");
        WP_CLI::log("- Difficulty: $difficulty");
        return;
    }
    
    // Create progress bar
    $progress = \WP_CLI\Utils\make_progress_bar('Generating course', 4);
    
    try {
        // Step 1: Create session
        $progress->tick();
        $session = $this->conversationManager->createSession($author_id);
        
        // Step 2: Generate course structure
        $progress->tick();
        $requirements = [
            'title' => $title,
            'sections' => $sections,
            'lessons' => $lessons,
            'difficulty' => $difficulty,
            'template' => $template
        ];
        
        $course = $this->courseGenerator->generateCourseStructure($requirements);
        
        // Step 3: Create WordPress posts
        $progress->tick();
        $course_id = $this->createCoursePosts($course, $author_id, $publish);
        
        // Step 4: Save session
        $progress->tick();
        $this->conversationManager->saveSession($session);
        
        $progress->finish();
        
        WP_CLI::success("Course created with ID $course_id");
        
        if (!$publish) {
            WP_CLI::log("View draft: " . get_edit_post_link($course_id));
        }
        
    } catch (\Exception $e) {
        WP_CLI::error($e->getMessage());
    }
}
```

#### 2.2 Session Management Commands
```php
/**
 * List conversation sessions
 *
 * ## OPTIONS
 *
 * [--user=<id>]
 * : Filter by user ID
 *
 * [--status=<status>]
 * : Filter by status (active|completed|abandoned)
 *
 * [--format=<format>]
 * : Output format (table|json|csv|ids)
 * default: table
 *
 * ## EXAMPLES
 *
 *     $ wp mpcc list-sessions --user=2
 *     +------------------------+------------------+--------+---------+
 *     | Session ID             | Title            | User   | Status  |
 *     +------------------------+------------------+--------+---------+
 *     | mpcc_session_12345     | JavaScript Course| user_2 | active  |
 *     | mpcc_session_67890     | PHP Basics       | user_2 | complete|
 *     +------------------------+------------------+--------+---------+
 */
public function list_sessions($args, $assoc_args) {
    $user_id = isset($assoc_args['user']) ? intval($assoc_args['user']) : null;
    $status = $assoc_args['status'] ?? null;
    $format = $assoc_args['format'] ?? 'table';
    
    $sessions = $this->conversationManager->getUserSessions($user_id);
    
    // Filter by status if specified
    if ($status) {
        $sessions = array_filter($sessions, function($session) use ($status) {
            return $session->getCurrentState() === $status;
        });
    }
    
    // Format for display
    $items = array_map(function($session) {
        return [
            'session_id' => $session->getSessionId(),
            'title' => $session->getTitle(),
            'user' => 'user_' . $session->getUserId(),
            'status' => $session->getCurrentState(),
            'created' => date('Y-m-d H:i', $session->getCreatedAt()),
            'updated' => date('Y-m-d H:i', $session->getLastUpdated())
        ];
    }, $sessions);
    
    WP_CLI\Utils\format_items($format, $items, ['session_id', 'title', 'user', 'status']);
}
```

### Phase 3: Advanced Features (Week 4)

#### 3.1 Batch Processing with Progress
```php
public function fill_empty_lessons($args, $assoc_args) {
    $course_id = intval($assoc_args['course'] ?? 0);
    $batch_size = intval($assoc_args['batch-size'] ?? 5);
    
    if (!$course_id) {
        WP_CLI::error('Course ID is required');
    }
    
    // Get all empty lessons
    $empty_lessons = $this->getEmptyLessons($course_id);
    $count = count($empty_lessons);
    
    if ($count === 0) {
        WP_CLI::success('No empty lessons found');
        return;
    }
    
    WP_CLI::log("Found $count empty lessons");
    
    // Process in batches
    $batches = array_chunk($empty_lessons, $batch_size);
    $progress = \WP_CLI\Utils\make_progress_bar('Generating lesson content', count($batches));
    
    foreach ($batches as $batch) {
        try {
            $this->processBatch($batch);
            $progress->tick();
            
            // Rate limiting
            sleep(2);
        } catch (\Exception $e) {
            WP_CLI::warning("Batch failed: " . $e->getMessage());
        }
    }
    
    $progress->finish();
    WP_CLI::success("Generated content for $count lessons");
}
```

#### 3.2 Interactive Mode
```php
public function interactive_course($args, $assoc_args) {
    WP_CLI::log("Welcome to the Interactive Course Creator!");
    
    // Get course title
    $title = $this->prompt("Course title");
    
    // Get target audience
    $audience = $this->prompt("Target audience", "general learners");
    
    // Get difficulty
    $difficulty = $this->promptSelect("Difficulty level", [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate', 
        'advanced' => 'Advanced'
    ]);
    
    // Confirm
    WP_CLI::log("\nCourse Summary:");
    WP_CLI::log("Title: $title");
    WP_CLI::log("Audience: $audience");
    WP_CLI::log("Difficulty: $difficulty");
    
    if (!$this->promptConfirm("Create this course?")) {
        WP_CLI::log("Course creation cancelled");
        return;
    }
    
    // Create course...
}
```

### Phase 4: Error Handling & Recovery (Week 5)

#### 4.1 Resume Failed Operations
```php
public function resume_generation($args, $assoc_args) {
    $session_id = $args[0] ?? null;
    
    if (!$session_id) {
        // Show recent failed operations
        $this->showFailedOperations();
        return;
    }
    
    try {
        $session = $this->conversationManager->loadSession($session_id);
        
        // Check where it failed
        $lastState = $session->getCurrentState();
        
        WP_CLI::log("Resuming from state: $lastState");
        
        // Resume from last checkpoint
        $this->courseGenerator->resumeFromCheckpoint($session);
        
        WP_CLI::success("Generation resumed successfully");
        
    } catch (\Exception $e) {
        WP_CLI::error("Failed to resume: " . $e->getMessage());
    }
}
```

## Implementation Architecture

### Command Class Structure
```
src/MemberPressCoursesCopilot/Commands/
├── MpccCommand.php              # Main command class
├── BaseCommand.php              # Shared functionality
├── CourseCommands.php           # Course-specific commands
├── SessionCommands.php          # Session management
├── ContentCommands.php          # Content generation
├── AnalyticsCommands.php        # Stats and reporting
└── Traits/
    ├── InteractiveTrait.php     # Interactive prompts
    ├── ProgressTrait.php        # Progress bars
    └── ValidationTrait.php      # Input validation
```

### Service Integration
- Leverage existing services (ConversationManager, CourseGeneratorService, etc.)
- No duplicate logic - commands are thin wrappers
- Follow KISS principle - simple command methods

## Benefits of WP-CLI Integration

### 1. **Automation**
- Schedule course generation via cron
- Bulk operations on multiple courses
- Integration with CI/CD pipelines

### 2. **Performance**
- No timeout issues (CLI has no execution time limit)
- Process large batches efficiently
- Better memory management

### 3. **Administration**
- Remote management via SSH
- Scriptable operations
- Easy backup/restore procedures

### 4. **Development**
- Quick testing of AI features
- Generate test data
- Debug production issues

## Usage Examples

### Daily Operations
```bash
# Morning routine
wp mpcc cleanup-sessions --days=30
wp mpcc stats --period=yesterday

# Generate weekly report
wp mpcc export-usage --period=week --format=csv --email=admin@example.com
```

### Bulk Operations
```bash
# Generate multiple courses from CSV
while IFS=, read -r title audience difficulty; do
    wp mpcc generate-course "$title" \
        --target-audience="$audience" \
        --difficulty="$difficulty" \
        --author=2
done < courses.csv
```

### Integration with Scripts
```bash
#!/bin/bash
# backup-sessions.sh

DATE=$(date +%Y%m%d)
BACKUP_DIR="/backups/mpcc/$DATE"

mkdir -p $BACKUP_DIR

# Export all active sessions
wp mpcc list-sessions --status=active --format=ids | while read session_id; do
    wp mpcc export-session $session_id --output="$BACKUP_DIR/$session_id.json"
done

# Compress
tar -czf "$BACKUP_DIR.tar.gz" $BACKUP_DIR
```

## Security Considerations

1. **Authentication**: Commands respect WordPress capabilities
2. **Validation**: All inputs sanitized and validated
3. **Logging**: All operations logged for audit trail
4. **Dry-run**: Support --dry-run for testing
5. **Confirmation**: Destructive operations require --yes flag

## Testing Strategy

### Unit Tests
```php
class MpccCommandTest extends WP_CLI_UnitTestCase {
    public function test_generate_course_dry_run() {
        $output = $this->runCommand(['generate-course', 'Test Course', '--dry-run']);
        $this->assertContains('DRY RUN', $output);
        $this->assertNoCourseCreated();
    }
}
```

### Integration Tests
- Test with real AI service
- Verify WordPress post creation
- Check session persistence

### Manual Testing Checklist
- [ ] Generate course with default options
- [ ] Generate course with all options
- [ ] Dry run mode works
- [ ] Error handling for invalid inputs
- [ ] Progress bars display correctly
- [ ] Batch processing handles failures

## Performance Considerations

### Rate Limiting
- Implement delays between AI calls
- Respect API rate limits
- Queue system for large batches

### Memory Management
- Process in chunks
- Clear object cache periodically
- Use generators for large datasets

### Database Optimization
- Batch database operations
- Use direct database queries where appropriate
- Index session tables for quick lookups

## Future Enhancements

### Version 2.0
- **Parallel processing**: Generate multiple lessons simultaneously
- **Queue integration**: Background job processing
- **Remote API**: REST endpoints for CLI commands
- **Templates**: Save and reuse course templates

### Version 3.0
- **Multi-site support**: Network-wide commands
- **Import/Export**: Full course migration tools
- **AI fine-tuning**: Train on successful courses
- **Analytics dashboard**: Web interface for CLI stats

## Next Steps

1. **Priority 1**: Implement basic course generation command
2. **Priority 2**: Add session management commands
3. **Priority 3**: Implement batch processing features
4. **Priority 4**: Add analytics/reporting commands
5. **Priority 5**: Create interactive mode for complex operations

## Estimated Timeline

- **Week 1**: Infrastructure setup, basic command structure
- **Week 2-3**: Core command implementation
- **Week 4**: Advanced features and error handling
- **Week 5**: Testing and documentation
- **Week 6**: Beta release and feedback collection

This WP-CLI integration will make MemberPress Courses Copilot a powerful tool for both administrators and developers, enabling efficient course management at scale.