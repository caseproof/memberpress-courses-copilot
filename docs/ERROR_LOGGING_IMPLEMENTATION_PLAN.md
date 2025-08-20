# Error Logging System Implementation Plan

## Overview
Replace all scattered `error_log()` calls with a centralized, configurable logging system that can be controlled via WordPress settings. The existing Logger class needs updates to add enable/disable functionality.

## Current State Analysis
- ✅ Logger class exists at `src/MemberPressCoursesCopilot/Utilities/Logger.php`
- ✅ Has log levels: DEBUG, INFO, WARNING, ERROR, CRITICAL
- ✅ File-based logging with rotation
- ✅ Cost tracking and API statistics
- ❌ Missing enable/disable toggle functionality
- ❌ Scattered `error_log()` calls throughout codebase need replacement

## 1. Core Logger Updates Needed

### 1.1 Add Enable/Disable Toggle
```php
// Configuration via WordPress options
private $loggingEnabled;

// Check in constructor:
$this->loggingEnabled = get_option('mpcc_logging_enabled', defined('WP_DEBUG') && WP_DEBUG);

// Or via constants in wp-config.php
define('MPCC_LOGGING_ENABLED', true);
```

### 1.2 Performance Optimization
- Add early return in `shouldLog()` method when logging is disabled
- Zero overhead when logging is off
- Cache configuration to avoid repeated database calls

### 1.3 Configuration Methods
```php
public function setLoggingEnabled(bool $enabled): void;
public function isLoggingEnabled(): bool;
public static function isLoggingEnabledGlobally(): bool; // Static check
```

## 2. Migration Strategy

### 2.1 Files Containing error_log() Calls to Replace

#### High Priority (Active Code)
1. **LLMService.php**
   - Lines ~34-96: API request/response logging
   - Replace with: `$logger->debug()`, `$logger->error()`

2. **CourseIntegrationService.php** 
   - Lines ~568-610: AJAX handler logging
   - Replace with: `$logger->info()`, `$logger->error()`

#### Medium Priority (Less Active)
3. **Other Services** (if any exist with error_log calls)

### 2.2 Migration Steps
1. Update Logger class with enable/disable functionality
2. Create singleton pattern for global logger access
3. Replace error_log() calls one service at a time
4. Test each replacement
5. Remove old debugging code

## 3. Usage Patterns

### 3.1 Service Integration
```php
class LLMService {
    private Logger $logger;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
    }
    
    public function generateContent($prompt) {
        $this->logger->info('Making LLM request', ['prompt_length' => strlen($prompt)]);
        
        // API call
        if ($response['error']) {
            $this->logger->error('LLM API failed', ['error' => $response['message']]);
            throw new Exception('LLM service failed: ' . $response['message']);
        }
        
        $this->logger->debug('LLM response received', ['content_length' => strlen($response['content'])]);
    }
}
```

### 3.2 AJAX Handler Logging
```php
public function handleAIChat(): void {
    $this->logger->info('AI chat request received', [
        'user_id' => get_current_user_id(),
        'message_length' => strlen($message)
    ]);
    
    try {
        // Processing...
        $this->logger->debug('Processing complete', ['response_size' => strlen($response)]);
    } catch (Exception $e) {
        $this->logger->error('AI chat processing failed', [
            'error' => $e->getMessage(),
            'user_id' => get_current_user_id()
        ]);
        throw $e;
    }
}
```

## 4. Admin Interface (Future Enhancement)

### 4.1 Settings Page
- Toggle logging on/off
- Set log level (DEBUG, INFO, ERROR, etc.)
- View current log files
- Download/clear logs
- Show cost tracking data

### 4.2 WordPress Integration
- Add to existing plugin admin menu
- Use WordPress Settings API
- Proper capability checks

## 5. Configuration Options

### 5.1 WordPress Options
```php
// Primary toggle
mpcc_logging_enabled (boolean)

// Log level
mpcc_log_level (string: debug|info|warning|error|critical)

// Log destination
mpcc_log_destination (string: file|wp_debug|database)

// Performance settings
mpcc_log_max_size (integer: bytes)
mpcc_log_rotation_count (integer)
```

### 5.2 Constants (wp-config.php)
```php
// Override WordPress options
define('MPCC_LOGGING_ENABLED', true);
define('MPCC_LOG_LEVEL', 'ERROR'); // Only errors and critical
define('MPCC_LOG_TO_WP_DEBUG', true);
```

## 6. Implementation Priority

### Phase 1: Core Logger Updates (High Priority)
- [x] Logger class exists
- [ ] Add enable/disable toggle
- [ ] Add singleton pattern
- [ ] Performance optimization (early returns)

### Phase 2: Service Migration (High Priority)  
- [ ] Replace LLMService error_log() calls
- [ ] Replace CourseIntegrationService error_log() calls
- [ ] Test all functionality still works

### Phase 3: Admin Interface (Low Priority)
- [ ] Settings page for logging control
- [ ] Log viewer interface
- [ ] Cost tracking dashboard

## 7. Testing Strategy

### 7.1 Functionality Testing
- Test logging when enabled vs disabled
- Verify log levels work correctly
- Test file rotation
- Verify performance impact when disabled

### 7.2 Integration Testing
- Ensure all services log appropriately
- Test AJAX error handling with logging
- Verify no functionality breaks during migration

## 8. Benefits

### 8.1 Immediate Benefits
- **Control**: Turn logging on/off without code changes
- **Performance**: Zero overhead when disabled
- **Debugging**: Rich context instead of generic error_log()
- **Professional**: Structured logging system

### 8.2 Long-term Benefits
- **Monitoring**: Track API costs and usage
- **Troubleshooting**: Detailed error context
- **Analytics**: Usage patterns and performance metrics
- **Compliance**: Proper log management and rotation

## 9. Risks and Mitigation

### 9.1 Performance Risk
- **Risk**: Logging overhead affecting user experience
- **Mitigation**: Early returns when disabled, efficient shouldLog() checks

### 9.2 Migration Risk
- **Risk**: Breaking existing functionality during error_log() replacement
- **Mitigation**: Replace one service at a time, thorough testing

### 9.3 Storage Risk
- **Risk**: Log files growing too large
- **Mitigation**: Existing log rotation, cleanup methods

## 10. Success Criteria

### 10.1 Technical Success
- [ ] All error_log() calls replaced with Logger methods
- [ ] Logging can be disabled with zero performance impact
- [ ] No functionality breaks during migration
- [ ] Log files rotate properly and don't consume excessive storage

### 10.2 User Experience Success  
- [ ] Error debugging is easier with rich context
- [ ] Site performance unaffected when logging disabled
- [ ] Clear admin interface for controlling logging (future)

---

This plan provides a systematic approach to replacing scattered error logging with a professional, configurable system while maintaining the existing Logger class's advanced features.