# Nonce Standardization Proposal

## Current Nonce Usage Analysis

After analyzing the codebase, I found the following unique nonce names currently in use:

### 1. **mpcc_cleanup_sessions**
- Used in: memberpress-courses-copilot.php (line 134)
- Purpose: Admin action for cleaning up empty sessions

### 2. **mpcc_courses_integration** 
- Used in: Multiple locations
  - ai-chat-interface.php template
  - CourseAjaxService.php (multiple methods)
  - CourseAssetService.php
  - CourseUIService.php
  - SimpleAjaxController.php
  - EnhancedTemplateEngine.php
  - AjaxController.php
- Purpose: General AJAX operations for course integration

### 3. **mpcc_ai_interface**
- Used in: 
  - CourseAjaxService.php (handleAIResponse method)
  - CourseIntegrationService.php
  - SimpleAjaxController.php
- Purpose: AI-specific AJAX operations

### 4. **mpcc_editor_nonce**
- Used in:
  - CourseEditorPage.php
  - SimpleAjaxController.php (all editor methods)
  - AjaxController.php
  - CourseAjaxService.php (as fallback)
- Purpose: Course editor page operations

### 5. **mpcc_ajax_nonce**
- Used in: AjaxController.php (as fallback option)
- Purpose: Generic AJAX fallback

### 6. **mpcc_nonce**
- Used in: AjaxController.php (as fallback option)
- Purpose: Generic fallback

### 7. **mpcc_export**
- Used in: AjaxController.php and RestApiController.php
- Purpose: Export functionality

### 8. **mpcc_generate_course**
- Used in: CLAUDE.md documentation
- Purpose: Course generation (mentioned in docs only)

### 9. **mpcc_quality_feedback**
- Used in: QualityFeedbackService.php
- Purpose: Quality feedback operations

### 10. **mpcc_apply_improvement**
- Used in: QualityFeedbackService.php
- Purpose: Applying improvements

### 11. **mpcc_auto_save_nonce**
- Used in: SessionFeaturesService.php
- Purpose: Auto-save functionality

### 12. **mpcc_extend_session_nonce**
- Used in: SessionFeaturesService.php
- Purpose: Session extension

### 13. **mpcc_quality_gates**
- Used in: QualityGatesService.php
- Purpose: Quality gates validation

### 14. **mpcc_request_review**
- Used in: QualityGatesService.php
- Purpose: Review requests

### 15. **mpcc_certify_quality**
- Used in: QualityGatesService.php
- Purpose: Quality certification

### 16. **wp_rest**
- Used in: RestApiController.php
- Purpose: WordPress REST API standard

### 17. **mp_ai_copilot_nonce**
- Used in: Documentation only
- Purpose: Legacy/documentation reference

## Standardization Proposal

I propose consolidating these 17 different nonces into 3 standard ones:

### 1. **mpcc_ajax_nonce**
- **Purpose**: All AJAX operations (frontend and backend)
- **Replace**: 
  - mpcc_courses_integration
  - mpcc_ai_interface
  - mpcc_generate_course
  - mpcc_quality_feedback
  - mpcc_apply_improvement
  - mpcc_auto_save_nonce
  - mpcc_extend_session_nonce
  - mpcc_quality_gates
  - mpcc_request_review
  - mpcc_certify_quality
  - mpcc_nonce
  - mp_ai_copilot_nonce

### 2. **mpcc_admin_nonce**
- **Purpose**: All admin-only actions (non-AJAX)
- **Replace**:
  - mpcc_cleanup_sessions
  - mpcc_export
  - mpcc_editor_nonce (when used for page rendering)

### 3. **wp_rest** (keep as is)
- **Purpose**: WordPress REST API standard
- **Keep**: This is a WordPress standard and should not be changed

## Benefits of This Approach

1. **Simplicity**: Reduces 17 nonces to 3
2. **Maintainability**: Easier to manage and understand
3. **Security**: Still provides proper nonce verification
4. **Consistency**: Clear distinction between AJAX and admin actions
5. **Standards Compliance**: Keeps WordPress REST API standard intact

## Implementation Notes

- The multiple fallback checks in AjaxController.php and SimpleAjaxController.php can be removed
- JavaScript can use a single nonce variable across all AJAX calls
- Admin actions remain properly segregated with their own nonce