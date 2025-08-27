# MemberPress Courses Copilot — Code Review (2025-08-27)

## Summary

Overall architecture is modular with clear separation of concerns (services, controllers, utilities). Security fundamentals (nonces, capability checks, sanitization) appear in
many endpoints. However, several inconsistencies and gaps could impact reliability, security, and the developer experience.

## Key Findings

- Missing/incorrect classes: References to Models/CourseTemplate exist but no implementation is present. Affects Controllers/RestApiController.php, Services/TemplateEngine.php,
Services/EnhancedTemplateEngine.php.
- Build config mismatch: Webpack and npm scripts target src/assets/js|scss, but only built assets exist under assets/. Entries like ./src/assets/js/admin.js will fail; lint
targets in package.json won’t match.
- Duplicated AJAX logic: Overlap among Controllers/AjaxController.php, Controllers/SimpleAjaxController.php, and Services/CourseAjaxService.php creates competing hooks and
routing ambiguity.
- Security/config management: Services/LLMService.php hardcodes AUTH_GATEWAY_URL and a dev license key. Move to wp-config constants/options; avoid logging sensitive payloads.
Prefer post-specific caps (e.g., current_user_can('edit_post', $post_id)) over broad edit_posts.
- REST sessions: Controllers/RestApiController uses session_start; prefer options/transients/DB (already available) in WordPress.
- Asset enqueues: Services/AssetManager::enqueueCourseListingAssets() enqueues unregistered handles (mpcc-modal-styles, mpcc-modal-component), causing admin notices/broken UX.
- JS XSS risk: assets/js/course-edit-ai-chat.js injects user text via string concatenation; use text() or escape before insert.
- Nonce handling: Good centralization via NonceConstants, but several raw $POST reads rely on stripslashes; use wp_unslash() and wp_json* consistently.
- UI selector mismatches: In CourseAjaxService fallback UI, mixed IDs (#mpcc-chat-messages vs #mpcc-course-chat-messages) break behavior.
- Tests: tests/bootstrap.php doesn’t stub wp_verify_nonce but tests assume true; phpunit.xml references tests/Integration which doesn’t exist.

## File-Specific Notes

- Services/LLMService.php: Externalize URL/key (MPCC_LITELLM_PROXY_URL, MPCC_LIC_KEY). Reduce logging of full bodies; add timeouts/retries/backoff.
- Services/AssetManager.php: Remove enqueue of unregistered handles; ensure localized nonces match consumer scripts.
- Controllers/AjaxController.php: Router is very broad; consider deprecating in favor of CourseAjaxService + SimpleAjaxController. Use Utilities\Logger instead of error_log.
- Services/DatabaseService.php and Database/LessonDraftTable.php: Prefer dbDelta across creates/alter paths; document MySQL JSON requirements; verify FK support.
- assets/js/course-edit-ai-chat.js: Escape all user-inserted content; normalize selectors; ensure nonce usage from mpccCourseChat.nonce.

## Recommendations (Prioritized)

1. Add Models/CourseTemplate or refactor usages to existing models.
2. Fix build pipeline: align Webpack entries or add missing src/assets sources; update lint paths.
3. Consolidate AJAX controllers per surface (course editor/generator/lessons).
4. Externalize LLM config; scrub sensitive logs; strengthen error handling.
5. Remove PHP session usage in REST controller; rely on WP storage.
6. Harden frontend DOM insertion; fix selector inconsistencies.
7. Repair tests: stub wp_verify_nonce, align suites, add focused unit tests (DB, nonce utilities, LLM stubs).