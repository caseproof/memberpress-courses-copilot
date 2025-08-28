/**
 * Shared Utilities for MemberPress Courses Copilot
 * Common functions used across multiple JavaScript files
 */

window.MPCCUtils = {
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml: function(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
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
     * Format message to HTML with proper structure
     */
    formatMessageToHTML: function(message) {
        // Handle both escaped sequences and actual escape characters
        let formatted = message;
        
        // First pass: Handle double-escaped sequences (from multiple JSON encode/decode cycles)
        formatted = formatted
            .replace(/\\\\n/g, '\n')   // Replace double-escaped newlines
            .replace(/\\\\r/g, '\r')   // Replace double-escaped carriage returns
            .replace(/\\\\t/g, '\t');  // Replace double-escaped tabs
            
        // Second pass: Handle single-escaped sequences
        formatted = formatted
            .replace(/\\n/g, '\n')     // Replace escaped newlines with actual newlines
            .replace(/\\r/g, '\r')     // Replace escaped carriage returns
            .replace(/\\t/g, '\t')     // Replace escaped tabs
            .replace(/\\'/g, "'")      // Replace escaped single quotes
            .replace(/\\"/g, '"');     // Replace escaped double quotes
        
        // Escape HTML to prevent XSS
        formatted = jQuery('<div>').text(formatted).html();
        
        // Convert numbered lists (e.g., "1. Item" or "1) Item")
        formatted = formatted.replace(/^(\d+)[\.\)]\s+(.+)$/gm, '<li value="$1">$2</li>');
        
        // Wrap consecutive list items in <ol> tags
        formatted = formatted.replace(/(<li.*?<\/li>\n?)+/g, function(match) {
            return '<ol>' + match + '</ol>';
        });
        
        // Convert bullet points (-, *, •) to unordered lists
        formatted = formatted.replace(/^\s*[-*•]\s+(.+)$/gm, '<li>$1</li>');
        
        // Handle indented lines that should be list items (2+ spaces at start)
        formatted = formatted.replace(/^\s{2,}(?!<li>)(.+)$/gm, '<li>$1</li>');
        
        // Wrap consecutive unordered list items
        formatted = formatted.replace(/(<li>.*?<\/li>\n?)+/g, function(match) {
            // Only wrap if not already in an ordered list
            if (!match.includes('value=')) {
                return '<ul>' + match + '</ul>';
            }
            return match;
        });
        
        // Convert double line breaks to paragraphs
        const paragraphs = formatted.split(/\n\n+/);
        formatted = paragraphs.map(para => {
            para = para.trim();
            // Don't wrap lists or empty strings in paragraphs
            if (para && !para.startsWith('<ul>') && !para.startsWith('<ol>')) {
                // Replace single line breaks with spaces within paragraphs
                para = para.replace(/\n/g, ' ');
                return '<p>' + para + '</p>';
            }
            return para;
        }).join('');
        
        // Only add <br> for line breaks within lists
        formatted = formatted.replace(/(<li[^>]*>)(.*?)(<\/li>)/g, function(match, start, content, end) {
            return start + content.replace(/\n/g, '<br>') + end;
        });
        
        // Clean up any double-wrapped lists
        formatted = formatted.replace(/<p>(<[uo]l>.*?<\/[uo]l>)<\/p>/g, '$1');
        
        return formatted;
    },
    
    /**
     * Get AJAX settings
     */
    getAjaxSettings: function() {
        return {
            url: window.mpccEditorSettings?.ajaxUrl || 
                 window.mpccAISettings?.ajaxUrl || 
                 window.mpccCoursesIntegration?.ajaxUrl || 
                 window.ajaxurl || 
                 '/wp-admin/admin-ajax.php',
            nonce: window.mpccEditorSettings?.nonce || 
                   window.mpccAISettings?.nonce || 
                   window.mpccCoursesIntegration?.nonce || 
                   jQuery('#mpcc-ajax-nonce').val() || 
                   ''
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
            $modal.removeClass('active mpcc-modal-open').fadeOut(0); // Instant hide to ensure it works
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
            $modal.addClass('active mpcc-modal-open').fadeIn();
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
                element.scrollTop = element.scrollHeight;
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
                        <span class="typing-indicator">
                            <span></span><span></span><span></span>
                        </span>
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