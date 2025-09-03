# Rate Limiting Implementation Plan for MemberPress Courses Copilot

## Overview
This document outlines the implementation plan for rate limiting in the MemberPress Courses Copilot plugin to prevent API abuse, manage costs, and ensure fair usage across all users.

## 1. Rate Limiting Strategy

### 1.1 Multi-Level Rate Limiting
1. **Per-User Limits**
   - 50 AI requests per hour per user
   - 500 AI requests per day per user
   - 10 concurrent quiz generation requests per user

2. **Per-Site Limits**
   - 1000 AI requests per hour for the entire site
   - 10,000 AI requests per day for the entire site

3. **Per-Feature Limits**
   - Course generation: 5 per hour, 20 per day
   - Quiz generation: 20 per hour, 100 per day
   - Content regeneration: 30 per hour, 200 per day

### 1.2 Rate Limit Headers
Following industry standards (similar to GitHub API):
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Requests remaining in current window
- `X-RateLimit-Reset`: Unix timestamp when limit resets
- `X-RateLimit-Used`: Requests used in current window

## 2. Technical Implementation

### 2.1 Storage Mechanism
Use WordPress transients for rate limit tracking:
```php
// Example key structure
$rateKey = 'mpcc_rate_limit_' . $userId . '_' . $feature . '_' . $window;
set_transient($rateKey, $count, $windowDuration);
```

### 2.2 Rate Limiter Service
Create a dedicated `RateLimiterService` class:

```php
class RateLimiterService extends BaseService {
    private const LIMITS = [
        'user_hourly' => 50,
        'user_daily' => 500,
        'site_hourly' => 1000,
        'site_daily' => 10000,
        'course_generation_hourly' => 5,
        'course_generation_daily' => 20,
        'quiz_generation_hourly' => 20,
        'quiz_generation_daily' => 100,
    ];
    
    public function checkLimit(string $feature, int $userId): array;
    public function incrementUsage(string $feature, int $userId): void;
    public function getRemainingLimits(int $userId): array;
    public function resetUserLimits(int $userId): void;
}
```

### 2.3 Integration Points

1. **LLMService Integration**
   - Check rate limits before making API calls
   - Increment counters after successful calls
   - Handle rate limit exceeded errors gracefully

2. **AJAX Controllers**
   - Add rate limit checks to all AI-powered endpoints
   - Return appropriate error responses with retry information

3. **Frontend Integration**
   - Display remaining limits in UI
   - Show countdown timers when limits are reached
   - Provide user-friendly error messages

## 3. Implementation Phases

### Phase 1: Core Rate Limiting (Week 1)
- [ ] Create RateLimiterService class
- [ ] Implement transient-based storage
- [ ] Add basic per-user hourly limits
- [ ] Integrate with LLMService

### Phase 2: Advanced Features (Week 2)
- [ ] Add per-feature rate limiting
- [ ] Implement site-wide limits
- [ ] Add rate limit headers to AJAX responses
- [ ] Create admin dashboard for monitoring

### Phase 3: UI Integration (Week 3)
- [ ] Add rate limit indicators to UI
- [ ] Implement countdown timers
- [ ] Add user notifications for approaching limits
- [ ] Create rate limit status widget

### Phase 4: Testing & Optimization (Week 4)
- [ ] Unit tests for RateLimiterService
- [ ] Integration tests with real API calls
- [ ] Performance optimization for transient operations
- [ ] Documentation and user guides

## 4. Configuration Options

### 4.1 Admin Settings
Add rate limiting configuration to admin panel:
- Enable/disable rate limiting
- Adjust limits per feature
- Set custom limits for specific users/roles
- View usage statistics

### 4.2 Filter Hooks
Provide filters for developers to customize limits:
```php
apply_filters('mpcc_rate_limit_user_hourly', 50, $userId);
apply_filters('mpcc_rate_limit_feature', $limit, $feature, $userId);
```

## 5. Error Handling

### 5.1 User-Friendly Messages
```php
$messages = [
    'rate_limit_exceeded' => __('You\'ve reached your hourly limit. Please try again in %d minutes.', 'memberpress-courses-copilot'),
    'daily_limit_reached' => __('Daily limit reached. Your limits will reset at midnight.', 'memberpress-courses-copilot'),
    'concurrent_limit' => __('Too many simultaneous requests. Please wait for current operations to complete.', 'memberpress-courses-copilot'),
];
```

### 5.2 Graceful Degradation
- Queue requests when possible
- Suggest alternative actions
- Provide upgrade options for higher limits

## 6. Monitoring & Analytics

### 6.1 Usage Tracking
Track and log:
- Total API calls per user
- Peak usage times
- Most used features
- Rate limit violations

### 6.2 Admin Dashboard Widget
Display:
- Current usage statistics
- Top users by usage
- Rate limit effectiveness
- Cost projections

## 7. Future Enhancements

### 7.1 Tiered Limits
- Different limits based on user roles
- Premium tier with higher limits
- Usage-based pricing integration

### 7.2 Smart Rate Limiting
- Dynamic limits based on server load
- Predictive limiting based on usage patterns
- Automatic limit adjustments

### 7.3 Advanced Features
- Request queuing system
- Priority queues for premium users
- Burst allowances for occasional spikes

## 8. Database Schema

### 8.1 Usage History Table (Optional)
For detailed analytics beyond transients:
```sql
CREATE TABLE {$wpdb->prefix}mpcc_rate_limit_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    feature VARCHAR(50) NOT NULL,
    timestamp DATETIME NOT NULL,
    request_count INT NOT NULL DEFAULT 1,
    response_time INT,
    PRIMARY KEY (id),
    KEY user_feature_time (user_id, feature, timestamp)
);
```

## 9. Testing Strategy

### 9.1 Unit Tests
- Test rate limit calculations
- Test transient operations
- Test limit reset functionality

### 9.2 Integration Tests
- Test with actual API calls
- Test concurrent request handling
- Test edge cases (clock changes, etc.)

### 9.3 Load Testing
- Simulate high usage scenarios
- Test transient performance at scale
- Verify accurate counting under load

## 10. Implementation Timeline

**Total Duration**: 4 weeks

1. **Week 1**: Core implementation
2. **Week 2**: Advanced features
3. **Week 3**: UI integration
4. **Week 4**: Testing and optimization

## 11. Success Metrics

- Zero API overage charges
- < 1% of users hitting rate limits
- No performance degradation from rate checking
- Positive user feedback on limit transparency

## 12. Rollback Plan

If issues arise:
1. Feature flag to disable rate limiting
2. Immediate transient cleanup
3. Fallback to previous unlimited behavior
4. Post-mortem and fixes before re-enabling