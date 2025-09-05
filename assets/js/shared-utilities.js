/**
 * Shared Utilities for MemberPress Courses Copilot
 * Common functions used across multiple JavaScript files
 */

window.MPCCUtils = {
    /**
     * Check if user prefers reduced motion
     * @returns {boolean}
     */
    prefersReducedMotion: function() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    },

    /**
     * Escape HTML to prevent XSS attacks with comprehensive character mapping
     * 
     * This method provides essential security protection by encoding dangerous
     * HTML characters that could be used for cross-site scripting (XSS) attacks.
     * It's used throughout the application to safely display user-generated content.
     * 
     * Character Mapping Strategy:
     * - '&': Must be escaped first to prevent double-encoding issues
     * - '<': Prevents opening HTML tags (script, iframe, etc.)
     * - '>': Prevents closing HTML tags
     * - '"': Prevents attribute value injection in double-quoted attributes
     * - "'": Prevents attribute value injection in single-quoted attributes
     * 
     * Security Impact:
     * - Prevents script injection via HTML tags
     * - Blocks event handler injection via attributes
     * - Stops CSS injection that could modify page layout
     * - Protects against data URI schemes and other exotic attacks
     * 
     * Usage Pattern:
     * Should be called on ALL user-generated content before display.
     * This includes chat messages, course titles, lesson content, etc.
     * 
     * Performance Considerations:
     * - Uses single regex replace for efficiency
     * - Character map lookup is faster than multiple replace operations
     * - Minimal overhead suitable for real-time content processing
     */
    escapeHtml: function(text) {
        // Character entity mapping for dangerous HTML characters
        // Order matters: & must be first to prevent double-encoding
        const map = {
            '&': '&amp;',    // Ampersand: prevents entity injection
            '<': '&lt;',     // Less than: prevents opening tag injection
            '>': '&gt;',     // Greater than: prevents closing tag injection
            '"': '&quot;',  // Double quote: prevents attribute injection
            "'": '&#039;'   // Single quote: prevents attribute injection
        };
        
        // Single regex operation for performance
        // Matches any dangerous character and replaces with safe entity
        return text.replace(/[&<>"']/g, m => map[m]);
    },
    
    /**
     * Show toast notification
     */
    showToast: function(message, type = 'info') {
        if (window.mpccToast && window.mpccToast[type]) {
            window.mpccToast[type](message);
        } else {
            if (window.MPCCLogger) {
                window.MPCCLogger[type](message);
            }
        }
    },
    
    /**
     * Show notification (non-blocking)
     */
    showNotification: function(message, type = 'info') {
        // Remove any existing notifications
        jQuery('.mpcc-notification').remove();
        
        // Create notification element
        const notification = jQuery('<div class="mpcc-notification mpcc-notification-' + type + '">' +
            '<div class="mpcc-notification-content">' +
                '<span class="mpcc-notification-icon dashicons dashicons-' + 
                (type === 'success' ? 'yes-alt' : type === 'error' ? 'dismiss' : 'info-outline') + 
                '"></span>' +
                '<span class="mpcc-notification-text">' + message + '</span>' +
            '</div>' +
        '</div>');
        
        // Add to page
        jQuery('body').append(notification);
        
        // Animate in
        setTimeout(function() {
            notification.addClass('mpcc-notification-show');
        }, 10);
        
        // Auto-hide after 5 seconds (except for info during processing)
        if (type !== 'info' || !message.includes('Creating')) {
            setTimeout(function() {
                notification.removeClass('mpcc-notification-show');
                setTimeout(function() {
                    notification.remove();
                }, 300);
            }, 5000);
        }
    },
    
    /**
     * Format message content to HTML with comprehensive text processing
     * 
     * This method converts plain text messages (potentially with markdown-like
     * formatting) into safe HTML for display in the course editor interface.
     * It handles complex formatting while maintaining security through XSS prevention.
     * 
     * Input Validation:
     * - Validates input is string type (prevents object/array injection)
     * - Handles null/undefined gracefully (returns empty string)
     * - Preserves original content structure for accurate formatting
     * 
     * Escape Sequence Processing:
     * The method handles multiple levels of escaping that can occur when content
     * passes through multiple encoding/decoding cycles (PHP -> JSON -> JavaScript):
     * 
     * Double-Escaped Sequences (from multiple JSON operations):
     * - \\\\n -> \n (newlines)
     * - \\\\r -> \r (carriage returns) 
     * - \\\\t -> \t (tabs)
     * 
     * Single-Escaped Sequences (from single JSON operation):
     * - \\n -> \n (actual newline characters)
     * - \\r -> \r (actual carriage returns)
     * - \\t -> \t (actual tabs)
     * - \\' -> ' (single quotes)
     * - \\" -> " (double quotes)
     * 
     * XSS Prevention:
     * Uses jQuery's .text().html() technique for safe HTML escaping.
     * This prevents all script injection while preserving text content.
     * 
     * List Processing Logic:
     * 
     * Numbered Lists:
     * - Regex: /^(\d+)[\.\)]\s+(.+)$/gm
     * - Matches: "1. Item" or "1) Item" patterns at line start
     * - Creates: <li value="1">Item</li> with explicit numbering
     * 
     * Bullet Points:
     * - Regex: /^\s*[-*•]\s+(.+)$/gm
     * - Matches: "- Item", "* Item", "• Item" with optional indentation
     * - Creates: <li>Item</li> for unordered lists
     * 
     * Indented Content:
     * - Regex: /^\s{2,}(?!<li>)(.+)$/gm
     * - Matches: Lines with 2+ spaces (indented content)
     * - Converts to list items for better formatting
     * 
     * Paragraph Processing:
     * - Splits on double line breaks (\n\n+)
     * - Wraps non-list content in <p> tags
     * - Preserves list structures outside paragraphs
     * - Converts single line breaks to spaces within paragraphs
     * 
     * Edge Case Handling:
     * - Prevents double-wrapping of lists in paragraphs
     * - Handles mixed ordered/unordered list combinations
     * - Preserves line breaks within list items using <br> tags
     * - Cleans up malformed HTML structure from processing
     * 
     * @param {string} message Raw message content to format
     * @return {string} Safe HTML formatted content
     */
    formatMessageToHTML: function(message) {
        // Input validation: ensure we have a valid string to process
        if (!message || typeof message !== 'string') {
            return '';
        }
        
        // Initialize processing with original message content
        let formatted = message;
        
        // Phase 1: Handle Double-Escaped Sequences
        // These occur when content goes through multiple JSON encode/decode cycles
        // Common in AJAX -> PHP -> Database -> PHP -> JSON -> JavaScript workflows
        formatted = formatted
            .replace(/\\\\n/g, '\n')   // Double-escaped newlines become single newlines
            .replace(/\\\\r/g, '\r')   // Double-escaped carriage returns
            .replace(/\\\\t/g, '\t');  // Double-escaped tabs
            
        // Phase 2: Handle Single-Escaped Sequences
        // These occur from single JSON encode/decode operations
        formatted = formatted
            .replace(/\\n/g, '\n')     // Escaped newlines become actual newlines
            .replace(/\\r/g, '\r')     // Escaped carriage returns
            .replace(/\\t/g, '\t')     // Escaped tabs
            .replace(/\\'/g, "'")      // Escaped single quotes
            .replace(/\\"/g, '"');     // Escaped double quotes
        
        // Phase 3: XSS Prevention via Safe HTML Escaping
        // jQuery's .text() method safely escapes all HTML entities
        // .html() then retrieves the escaped content
        formatted = jQuery('<div>').text(formatted).html();
        
        // Phase 4: Structured Content Recognition and Conversion
        
        // Convert numbered list patterns to HTML ordered list items
        // Regex explanation: ^(\d+)[\.\)]\s+(.+)$
        // - ^ : Start of line
        // - (\d+) : Capture group for number
        // - [\.\)] : Match either period or closing parenthesis
        // - \s+ : One or more whitespace characters
        // - (.+) : Capture group for list item content
        // - $ : End of line
        formatted = formatted.replace(/^(\d+)[\.\)]\s+(.+)$/gm, '<li value="$1">$2</li>');
        
        // Wrap consecutive numbered list items in ordered list container
        formatted = formatted.replace(/(<li.*?<\/li>\n?)+/g, function(match) {
            return '<ol>' + match + '</ol>';
        });
        
        // Convert bullet point patterns to HTML unordered list items
        // Regex: ^\s*[-*•]\s+(.+)$
        // - ^\s* : Start with optional whitespace (handles indentation)
        // - [-*•] : Any of the common bullet characters
        // - \s+ : Required whitespace after bullet
        // - (.+) : Capture the item content
        formatted = formatted.replace(/^\s*[-*•]\s+(.+)$/gm, '<li>$1</li>');
        
        // Handle indented content as implicit list items
        // Regex: ^\s{2,}(?!<li>)(.+)$
        // - ^\s{2,} : 2 or more spaces at line start
        // - (?!<li>) : Negative lookahead to avoid double-processing
        // - (.+) : Capture the indented content
        formatted = formatted.replace(/^\s{2,}(?!<li>)(.+)$/gm, '<li>$1</li>');
        
        // Wrap consecutive unordered list items in container
        formatted = formatted.replace(/(<li>.*?<\/li>\n?)+/g, function(match) {
            // Only wrap if not already in an ordered list (check for value attribute)
            if (!match.includes('value=')) {
                return '<ul>' + match + '</ul>';
            }
            return match;
        });
        
        // Phase 5: Paragraph Structure Creation
        // Split content on double line breaks to identify paragraph boundaries
        const paragraphs = formatted.split(/\n\n+/);
        formatted = paragraphs.map(para => {
            para = para.trim();
            // Don't wrap existing HTML structures (lists) in paragraphs
            if (para && !para.startsWith('<ul>') && !para.startsWith('<ol>')) {
                // Within paragraphs, convert single line breaks to spaces
                // This prevents awkward line breaks in continuous text
                para = para.replace(/\n/g, ' ');
                return '<p>' + para + '</p>';
            }
            return para;
        }).join('');
        
        // Phase 6: List-Specific Line Break Handling
        // Preserve intentional line breaks within list items using <br> tags
        formatted = formatted.replace(/(<li[^>]*>)(.*?)(<\/li>)/g, function(match, start, content, end) {
            return start + content.replace(/\n/g, '<br>') + end;
        });
        
        // Phase 7: Cleanup Double-Wrapped Structures
        // Remove paragraph tags that were incorrectly wrapped around lists
        formatted = formatted.replace(/<p>(<[uo]l>.*?<\/[uo]l>)<\/p>/g, '$1');
        
        return formatted;
    },
    
    /**
     * Get AJAX settings with comprehensive fallback validation
     * 
     * This method provides centralized AJAX configuration with multiple fallback
     * sources to ensure requests work across different plugin contexts and page types.
     * It implements a priority-based selection system for both URL and nonce values.
     * 
     * URL Source Priority (Fallback Chain):
     * 1. mpccEditorSettings.ajaxUrl - Course editor specific endpoint
     * 2. mpccAISettings.ajaxUrl - AI interface specific endpoint  
     * 3. mpccCoursesIntegration.ajaxUrl - Course integration endpoint
     * 4. window.ajaxurl - WordPress global AJAX URL
     * 5. '/wp-admin/admin-ajax.php' - Hardcoded fallback (WordPress default)
     * 
     * Nonce Source Priority (Security Token Chain):
     * 1. mpccEditorSettings.nonce - Editor-specific security token
     * 2. mpccAISettings.nonce - AI interface security token
     * 3. mpccCoursesIntegration.nonce - Integration security token
     * 4. #mpcc-ajax-nonce DOM element - Hidden form field
     * 5. '' (empty string) - Last resort (will cause security failure)
     * 
     * Fallback Strategy Rationale:
     * - Different pages localize different settings objects
     * - Plugin contexts may override default WordPress settings
     * - DOM elements provide backup when JavaScript objects aren't available
     * - Empty nonce triggers proper security error rather than undefined behavior
     * 
     * Error Prevention:
     * - Uses optional chaining (?.) to handle undefined objects safely
     * - Provides working defaults to prevent AJAX call failures
     * - Logs configuration issues when debug mode is enabled
     * 
     * Usage Context:
     * This method is called by all AJAX operations to ensure consistent
     * configuration regardless of which page or context initiates the request.
     * 
     * @return {Object} Configuration object with 'url' and 'nonce' properties
     */
    getAjaxSettings: function() {
        // Get AJAX URL from various possible sources
        const ajaxUrl = window.mpccEditorSettings?.ajaxUrl ||      // Course editor context
                       window.mpccAISettings?.ajaxUrl ||          // AI interface context
                       window.mpccCoursesIntegration?.ajaxUrl ||  // Integration context
                       window.ajaxurl ||                          // WordPress global
                       window.mpcc_ajax?.ajax_url ||              // Quiz context
                       null;
        
        // If no AJAX URL found, throw error instead of using hardcoded fallback
        if (!ajaxUrl) {
            console.error('MPCC: No AJAX URL available. WordPress localization may have failed.');
            throw new Error('AJAX URL not available');
        }
        
        return {
            url: ajaxUrl,
            
            // Nonce fallback chain - ensures security token is available
            nonce: window.mpccEditorSettings?.nonce ||       // Editor security token
                   window.mpccAISettings?.nonce ||          // AI interface token
                   window.mpccCoursesIntegration?.nonce ||  // Integration token
                   window.mpcc_ajax?.nonce ||               // Quiz context token
                   jQuery('#mpcc-ajax-nonce').val() ||     // DOM fallback
                   ''                                       // Empty string triggers security error
        };
    },
    
    /**
     * Centralized modal management
     */
    modalManager: {
        focusTrap: null,
        
        /**
         * Close any open modal
         */
        close: function(modalSelector) {
            const $modal = modalSelector ? jQuery(modalSelector) : jQuery('.mpcc-modal-overlay, .mpcc-sessions-modal-overlay');
            if (window.MPCCLogger) {
                window.MPCCLogger.debug('Closing modal', $modal.length ? 'found' : 'not found');
            }
            
            // Check for reduced motion preference
            if (MPCCUtils.prefersReducedMotion()) {
                $modal.removeClass('active mpcc-modal-open').hide();
            } else {
                $modal.removeClass('active mpcc-modal-open').fadeOut(200);
            }
            jQuery('body').css('overflow', '');
            
            // Release focus trap
            if (this.focusTrap) {
                this.focusTrap.release();
                this.focusTrap = null;
            }
            
            // Announce to screen readers
            if (window.MPCCAccessibility) {
                MPCCAccessibility.announce('Dialog closed');
            }
            
            // Trigger custom event
            jQuery(document).trigger('mpcc:modal-closed', { modal: modalSelector });
        },
        
        /**
         * Open a modal
         */
        open: function(modalSelector) {
            const $modal = jQuery(modalSelector);
            
            // Check for reduced motion preference
            if (MPCCUtils.prefersReducedMotion()) {
                $modal.addClass('active mpcc-modal-open').show();
            } else {
                $modal.addClass('active mpcc-modal-open').fadeIn(200);
            }
            jQuery('body').css('overflow', 'hidden');
            
            // Enhance modal for accessibility
            if (window.MPCCAccessibility) {
                MPCCAccessibility.enhanceModal($modal);
                this.focusTrap = MPCCAccessibility.trapFocus($modal);
                MPCCAccessibility.announce('Dialog opened');
            }
            
            // Trigger custom event
            jQuery(document).trigger('mpcc:modal-opened', { modal: modalSelector });
        },
        
        /**
         * Initialize modal event handlers
         */
        init: function() {
            if (window.MPCCLogger) {
                window.MPCCLogger.debug('Initializing modal manager');
            }
            
            // Use event delegation for all modal close buttons
            jQuery(document).off('click.mpcc-modal-close').on('click.mpcc-modal-close', '.mpcc-modal-close, .mpcc-sessions-modal-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (window.MPCCLogger) {
                    window.MPCCLogger.debug('Close button clicked');
                }
                const $modal = jQuery(this).closest('.mpcc-modal-overlay, .mpcc-sessions-modal-overlay');
                MPCCUtils.modalManager.close($modal);
            });
            
            // Close on overlay click
            jQuery(document).off('click.mpcc-modal-overlay').on('click.mpcc-modal-overlay', '.mpcc-modal-overlay, .mpcc-sessions-modal-overlay', function(e) {
                if (e.target === this) {
                    MPCCUtils.modalManager.close(this);
                }
            });
            
            // Close on ESC key
            jQuery(document).off('keydown.mpcc-modal').on('keydown.mpcc-modal', function(e) {
                if (e.key === 'Escape') {
                    const $activeModal = jQuery('.mpcc-modal-overlay.active, .mpcc-sessions-modal-overlay.active, .mpcc-modal-overlay.mpcc-modal-open');
                    if ($activeModal.length) {
                        MPCCUtils.modalManager.close($activeModal);
                    }
                }
            });
        }
    },
    
    /**
     * Session management utilities
     */
    sessionManager: {
        /**
         * Get current session ID
         */
        getCurrentSessionId: function() {
            return window.currentSessionId || 
                   window.CourseEditor?.sessionId || 
                   sessionStorage.getItem('mpcc_current_session_id');
        },
        
        /**
         * Set current session ID
         */
        setCurrentSessionId: function(sessionId) {
            sessionStorage.setItem('mpcc_current_session_id', sessionId);
            jQuery(document).trigger('mpcc:session-changed', { sessionId: sessionId });
        },
        
        /**
         * Check if there are unsaved changes
         */
        hasUnsavedChanges: function() {
            return window.isDirty || 
                   (window.CourseEditor && window.CourseEditor.isDirty) ||
                   false;
        }
    },
    
    /**
     * AJAX utilities
     */
    ajax: {
        /**
         * Make standardized AJAX request
         */
        request: function(action, data, callbacks) {
            const settings = MPCCUtils.getAjaxSettings();
            
            // Ensure we have required data
            data = data || {};
            data.action = action;
            data.nonce = data.nonce || settings.nonce;
            
            // Default callbacks
            callbacks = callbacks || {};
            const onSuccess = callbacks.success || function() {};
            const onError = callbacks.error || function() {};
            const onComplete = callbacks.complete || function() {};
            
            return jQuery.ajax({
                url: settings.url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        onSuccess(response);
                    } else {
                        MPCCUtils.showError(response.data || 'An error occurred');
                        onError(response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    MPCCUtils.showError('Request failed: ' + error);
                    onError(xhr, status, error);
                },
                complete: onComplete
            });
        },
        
        /**
         * Save lesson content
         */
        saveLessonContent: function(sessionId, sectionId, lessonId, content, lessonTitle, callbacks) {
            return this.request('mpcc_save_lesson_content', {
                session_id: sessionId,
                section_id: String(sectionId),
                lesson_id: String(lessonId),
                lesson_title: lessonTitle,
                content: content
            }, callbacks);
        },
        
        /**
         * Generate lesson content
         */
        generateLessonContent: function(sessionId, sectionId, lessonId, lessonTitle, courseContext, callbacks) {
            return this.request('mpcc_generate_lesson_content', {
                session_id: sessionId,
                section_id: String(sectionId),
                lesson_id: String(lessonId),
                lesson_title: lessonTitle,
                course_context: courseContext
            }, callbacks);
        },
        
        /**
         * Load session
         */
        loadSession: function(sessionId, callbacks) {
            return this.request('mpcc_load_session', {
                session_id: sessionId
            }, callbacks);
        },
        
        /**
         * Save conversation
         */
        saveConversation: function(sessionId, conversationHistory, conversationState, callbacks) {
            return this.request('mpcc_save_conversation', {
                session_id: sessionId,
                conversation_history: JSON.stringify(conversationHistory),
                conversation_state: JSON.stringify(conversationState)
            }, callbacks);
        }
    },
    
    /**
     * UI utilities
     */
    ui: {
        /**
         * Show/hide loading state on button
         */
        setButtonLoading: function($button, isLoading, loadingText) {
            if (isLoading) {
                const originalText = $button.html();
                $button.data('original-text', originalText);
                $button.prop('disabled', true);
                
                if (loadingText) {
                    $button.html('<span class="dashicons dashicons-update spin"></span> ' + loadingText);
                } else {
                    $button.html('<span class="spinner is-active"></span>');
                }
            } else {
                const originalText = $button.data('original-text');
                $button.prop('disabled', false);
                if (originalText) {
                    $button.html(originalText);
                }
            }
        },
        
        /**
         * Scroll element to bottom
         */
        scrollToBottom: function(selector) {
            const element = jQuery(selector)[0];
            if (element) {
                // Use requestAnimationFrame to ensure DOM has been rendered
                requestAnimationFrame(() => {
                    element.scrollTop = element.scrollHeight;
                });
            }
        },
        
        /**
         * Add typing indicator
         */
        addTypingIndicator: function(container) {
            const typingId = 'typing-' + Date.now();
            const typingHtml = `
                <div class="mpcc-chat-message assistant" id="${typingId}">
                    <div class="message-content">
                        <div class="mpcc-typing-indicator">
                            <div class="mpcc-typing-dot"></div>
                            <div class="mpcc-typing-dot"></div>
                            <div class="mpcc-typing-dot"></div>
                        </div>
                    </div>
                </div>
            `;
            jQuery(container).append(typingHtml);
            this.scrollToBottom(container);
            return typingId;
        },
        
        /**
         * Remove typing indicator
         */
        removeTypingIndicator: function(typingId) {
            jQuery('#' + typingId).remove();
        },
        
        /**
         * Update save indicator
         */
        updateSaveIndicator: function(status) {
            const $indicator = jQuery('.mpcc-save-indicator');
            
            switch (status) {
                case 'editing':
                    $indicator.html('<span class="dashicons dashicons-edit"></span> Editing');
                    break;
                case 'unsaved':
                    $indicator.html('<span class="dashicons dashicons-warning"></span> Unsaved changes');
                    break;
                case 'saving':
                    $indicator.html('<span class="spinner is-active"></span> Saving...');
                    break;
                case 'saved':
                    $indicator.html('<span class="dashicons dashicons-yes"></span> Saved');
                    setTimeout(() => {
                        if ($indicator.text().includes('Saved')) {
                            $indicator.empty();
                        }
                    }, 3000);
                    break;
                case 'error':
                    $indicator.html('<span class="dashicons dashicons-no"></span> Save failed');
                    break;
                default:
                    $indicator.empty();
            }
        }
    },
    
    /**
     * Error handling utilities
     */
    showError: function(message) {
        this.showNotification(message, 'error');
        if (window.mpccToast && window.mpccToast.error) {
            window.mpccToast.error(message);
        }
    },
    
    showSuccess: function(message) {
        this.showNotification(message, 'success');
        if (window.mpccToast && window.mpccToast.success) {
            window.mpccToast.success(message);
        }
    },
    
    showWarning: function(message) {
        this.showNotification(message, 'warning');
        if (window.mpccToast && window.mpccToast.warning) {
            window.mpccToast.warning(message);
        }
    },
    
    /**
     * Debounce function for auto-save and input handlers
     */
    debounce: function(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const context = this;
            const later = () => {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },
    
    /**
     * Throttle function for scroll and resize handlers
     */
    throttle: function(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    /**
     * Format content with basic markdown support
     */
    formatContent: function(content) {
        // Escape HTML first
        let formatted = this.escapeHtml(content);
        
        // Basic markdown-like formatting
        formatted = formatted
            .replace(/\n\n/g, '</p><p>')
            .replace(/^/, '<p>')
            .replace(/$/, '</p>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/^- (.+)$/gm, '<li>$1</li>')
            .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
            .replace(/^(\d+)\. (.+)$/gm, '<li>$2</li>')
            .replace(/(<li>.*<\/li>)/s, '<ol>$1</ol>');
            
        return formatted;
    },
    
    /**
     * Check for unsaved changes before unload
     */
    setupUnloadWarning: function(checkFunction) {
        jQuery(window).on('beforeunload.mpcc', function(e) {
            if (checkFunction && checkFunction()) {
                const message = 'You have unsaved changes. Are you sure you want to leave?';
                e.returnValue = message;
                return message;
            }
        });
    },
    
    /**
     * Memory management utilities
     */
    memory: {
        /**
         * Clean up event handlers for an element and its children
         */
        cleanupElement: function($element) {
            if (!$element || !$element.length) return;
            
            // Remove all event handlers
            $element.off();
            $element.find('*').off();
            
            // Clear data
            $element.removeData();
            $element.find('*').removeData();
        },
        
        /**
         * Clear all timers in a given object
         */
        clearTimers: function(obj) {
            if (!obj) return;
            
            Object.keys(obj).forEach(key => {
                if (key.includes('timeout') || key.includes('interval')) {
                    if (typeof obj[key] === 'number') {
                        clearTimeout(obj[key]);
                        clearInterval(obj[key]);
                        obj[key] = null;
                    }
                }
            });
        },
        
        /**
         * Safely remove element and clean up references
         */
        removeElement: function($element) {
            if (!$element || !$element.length) return;
            
            this.cleanupElement($element);
            $element.remove();
        }
    },
    
    /**
     * Performance monitoring
     */
    performance: {
        /**
         * Measure function execution time
         */
        measureTime: function(name, func) {
            const start = performance.now();
            const result = func();
            const end = performance.now();
            if (window.MPCCLogger) {
                window.MPCCLogger.info(`Performance: ${name} took ${(end - start).toFixed(2)}ms`);
            }
            return result;
        },
        
        /**
         * Defer non-critical operations
         */
        defer: function(func) {
            if ('requestIdleCallback' in window) {
                requestIdleCallback(func);
            } else {
                setTimeout(func, 0);
            }
        }
    },
    
    /**
     * Initialize all shared utilities
     */
    init: function() {
        // Initialize modal manager
        this.modalManager.init();
        
        // Make showNotification globally available
        window.showNotification = this.showNotification;
        
        // Create global shortcuts
        window.mpccAjax = this.ajax;
        window.mpccUI = this.ui;
        window.mpccMemory = this.memory;
        window.mpccPerformance = this.performance;
        
        if (window.MPCCLogger) {
            window.MPCCLogger.info('Shared Utilities initialized');
        }
    }
};

// Initialize on document ready
jQuery(document).ready(function() {
    MPCCUtils.init();
});