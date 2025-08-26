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
            console.log(`[${type.toUpperCase()}]`, message);
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
        // First, handle escaped characters from the server
        let formatted = message
            .replace(/\\'/g, "'")      // Replace escaped single quotes
            .replace(/\\"/g, '"')      // Replace escaped double quotes
            .replace(/\\\\/g, '\\')    // Replace escaped backslashes
            .replace(/\\n/g, '\n')     // Replace escaped newlines with actual newlines
            .replace(/\\r/g, '\r')     // Replace escaped carriage returns
            .replace(/\\t/g, '\t');    // Replace escaped tabs
        
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
        /**
         * Close any open modal
         */
        close: function(modalSelector) {
            const $modal = modalSelector ? jQuery(modalSelector) : jQuery('.mpcc-modal-overlay, .mpcc-sessions-modal-overlay');
            $modal.removeClass('active mpcc-modal-open').fadeOut();
            jQuery('body').css('overflow', '');
            
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
            
            // Trigger custom event
            jQuery(document).trigger('mpcc:modal-opened', { modal: modalSelector });
        },
        
        /**
         * Initialize modal event handlers
         */
        init: function() {
            // Use event delegation for all modal close buttons
            jQuery(document).off('click.mpcc-modal-close').on('click.mpcc-modal-close', '.mpcc-modal-close, .mpcc-sessions-modal-close', function(e) {
                e.preventDefault();
                e.stopPropagation();
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
     * Initialize all shared utilities
     */
    init: function() {
        // Initialize modal manager
        this.modalManager.init();
        
        // Make showNotification globally available
        window.showNotification = this.showNotification;
        
        console.log('MPCC Shared Utilities initialized');
    }
};

// Initialize on document ready
jQuery(document).ready(function() {
    MPCCUtils.init();
});