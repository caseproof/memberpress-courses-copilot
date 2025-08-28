# Licensing Implementation TODO

## Overview
The MemberPress Courses Copilot plugin will implement a licensing system that integrates with MemberPress's existing license management infrastructure. Currently, the plugin uses a placeholder license key (`dev-license-key-001`) for development purposes.

## Current State
- **Location**: `src/MemberPressCoursesCopilot/Services/LLMService.php` line 23
- **Placeholder**: `private const LICENSE_KEY = 'dev-license-key-001';`
- **Purpose**: Authenticates with the auth gateway server to access LLM services
- **Note**: This is a development placeholder, not a real credential. The actual implementation will integrate with MemberPress's licensing system.

## Implementation Requirements

### 1. MemberPress License Integration
- Hook into MemberPress's existing license validation system
- Use the same license key that activates MemberPress for this add-on
- Validate license status before allowing AI features

### 2. External License Server
The licensing will be validated through an external MemberPress license server:
- License validation endpoint: TBD by MemberPress team
- Response should include:
  - License validity status
  - Feature access levels (if applicable)
  - Usage limits (if applicable)

### 3. Implementation Steps
1. **Remove hardcoded license key** from `LLMService.php`
2. **Add license retrieval method**:
   ```php
   private function getLicenseKey(): string {
       // Get from MemberPress license system
       $mepr_options = get_option('mepr_options');
       return $mepr_options['license_key'] ?? '';
   }
   ```
3. **Add license validation**:
   ```php
   private function validateLicense(): bool {
       $license = $this->getLicenseKey();
       // Call MemberPress license server API
       // Cache validation result in transient
   }
   ```

## Timeline
- **Priority**: Medium (not blocking initial development)
- **Target**: Before public release
- **Dependencies**: MemberPress license server API documentation

## Notes
- Current placeholder allows full development and testing
- Auth gateway already handles API key security
- License implementation won't affect core functionality, only access control