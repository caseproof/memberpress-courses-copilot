/**
 * Accessibility Utilities for MemberPress Courses Copilot
 * 
 * Provides comprehensive accessibility features including:
 * - ARIA management
 * - Keyboard navigation
 * - Focus management
 * - Screen reader announcements
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */

window.MPCCAccessibility = {
    /**
     * Live region for screen reader announcements
     */
    liveRegion: null,
    
    /**
     * Initialize accessibility utilities
     */
    init: function() {
        this.createLiveRegion();
        this.setupGlobalKeyboardHandlers();
        console.log('MPCC Accessibility utilities initialized');
    },
    
    /**
     * Create ARIA live region for announcements
     */
    createLiveRegion: function() {
        if (!this.liveRegion) {
            this.liveRegion = jQuery('<div>', {
                'class': 'sr-only',
                'aria-live': 'polite',
                'aria-atomic': 'true',
                'id': 'mpcc-aria-live-region'
            }).appendTo('body');
        }
    },
    
    /**
     * Announce message to screen readers
     * @param {string} message - Message to announce
     * @param {string} priority - 'polite' or 'assertive'
     */
    announce: function(message, priority = 'polite') {
        if (!this.liveRegion) {
            this.createLiveRegion();
        }
        
        // Update aria-live priority if needed
        if (priority !== this.liveRegion.attr('aria-live')) {
            this.liveRegion.attr('aria-live', priority);
        }
        
        // Clear and set message
        this.liveRegion.empty();
        setTimeout(() => {
            this.liveRegion.text(message);
        }, 100);
    },
    
    /**
     * Trap focus within an element (for modals)
     * @param {jQuery|Element} container - Container element
     * @returns {Object} - Focus trap controller
     */
    trapFocus: function(container) {
        const $container = jQuery(container);
        const focusableElements = 'a[href], button, textarea, input[type="text"], input[type="radio"], input[type="checkbox"], select, [tabindex]:not([tabindex="-1"])';
        let $focusableContent = $container.find(focusableElements).filter(':visible');
        let firstFocusableElement = $focusableContent[0];
        let lastFocusableElement = $focusableContent[$focusableContent.length - 1];
        
        // Store the element that triggered the modal
        const triggerElement = document.activeElement;
        
        // Function to handle tab key
        const handleTab = function(e) {
            if (e.key !== 'Tab') return;
            
            // Update focusable elements list (in case content changed)
            $focusableContent = $container.find(focusableElements).filter(':visible');
            firstFocusableElement = $focusableContent[0];
            lastFocusableElement = $focusableContent[$focusableContent.length - 1];
            
            if (e.shiftKey) {
                // Shift + Tab
                if (document.activeElement === firstFocusableElement) {
                    lastFocusableElement.focus();
                    e.preventDefault();
                }
            } else {
                // Tab
                if (document.activeElement === lastFocusableElement) {
                    firstFocusableElement.focus();
                    e.preventDefault();
                }
            }
        };
        
        // Add event listener
        $container.on('keydown.focustrap', handleTab);
        
        // Focus first element
        if (firstFocusableElement) {
            firstFocusableElement.focus();
        }
        
        // Return controller object
        return {
            release: function() {
                $container.off('keydown.focustrap');
                // Return focus to trigger element
                if (triggerElement && jQuery(triggerElement).is(':visible')) {
                    triggerElement.focus();
                }
            },
            update: function() {
                // Refresh the focusable elements list
                $focusableContent = $container.find(focusableElements).filter(':visible');
                firstFocusableElement = $focusableContent[0];
                lastFocusableElement = $focusableContent[$focusableContent.length - 1];
            }
        };
    },
    
    /**
     * Setup global keyboard handlers
     */
    setupGlobalKeyboardHandlers: function() {
        // Already handled in modal manager for ESC key
        // Add additional global handlers here if needed
    },
    
    /**
     * Make an element keyboard navigable
     * @param {jQuery|Element} element - Element to make navigable
     * @param {Object} handlers - Key handlers { enter: fn, space: fn, arrow: fn }
     */
    makeKeyboardNavigable: function(element, handlers) {
        const $element = jQuery(element);
        
        // Ensure element is focusable
        if (!$element.attr('tabindex')) {
            $element.attr('tabindex', '0');
        }
        
        $element.on('keydown', function(e) {
            switch(e.key) {
                case 'Enter':
                    if (handlers.enter) {
                        e.preventDefault();
                        handlers.enter.call(this, e);
                    }
                    break;
                case ' ':
                case 'Space':
                    if (handlers.space) {
                        e.preventDefault();
                        handlers.space.call(this, e);
                    }
                    break;
                case 'ArrowUp':
                    if (handlers.up) {
                        e.preventDefault();
                        handlers.up.call(this, e);
                    }
                    break;
                case 'ArrowDown':
                    if (handlers.down) {
                        e.preventDefault();
                        handlers.down.call(this, e);
                    }
                    break;
                case 'ArrowLeft':
                    if (handlers.left) {
                        e.preventDefault();
                        handlers.left.call(this, e);
                    }
                    break;
                case 'ArrowRight':
                    if (handlers.right) {
                        e.preventDefault();
                        handlers.right.call(this, e);
                    }
                    break;
            }
        });
    },
    
    /**
     * Add proper ARIA attributes to a button
     * @param {jQuery|Element} button - Button element
     * @param {Object} options - ARIA options
     */
    enhanceButton: function(button, options = {}) {
        const $button = jQuery(button);
        
        // Add role if not a native button
        if (!$button.is('button') && !$button.attr('role')) {
            $button.attr('role', 'button');
        }
        
        // Add aria-label if provided
        if (options.label && !$button.attr('aria-label')) {
            $button.attr('aria-label', options.label);
        }
        
        // Add aria-pressed for toggle buttons
        if (options.isToggle) {
            $button.attr('aria-pressed', options.pressed || 'false');
        }
        
        // Add aria-expanded for buttons that control collapsible content
        if (options.controls) {
            $button.attr('aria-controls', options.controls);
            $button.attr('aria-expanded', options.expanded || 'false');
        }
        
        // Make keyboard accessible if not a native button
        if (!$button.is('button')) {
            this.makeKeyboardNavigable($button, {
                enter: function() { $button.click(); },
                space: function() { $button.click(); }
            });
        }
    },
    
    /**
     * Enhance a modal dialog for accessibility
     * @param {jQuery|Element} modal - Modal element
     * @param {Object} options - Modal options
     */
    enhanceModal: function(modal, options = {}) {
        const $modal = jQuery(modal);
        const modalId = $modal.attr('id') || 'mpcc-modal-' + Date.now();
        
        // Set ID if not present
        if (!$modal.attr('id')) {
            $modal.attr('id', modalId);
        }
        
        // Add role and aria attributes
        $modal.attr({
            'role': 'dialog',
            'aria-modal': 'true',
            'aria-labelledby': options.labelledby || modalId + '-title',
            'aria-describedby': options.describedby || modalId + '-description'
        });
        
        // Find or create title element
        let $title = $modal.find('h1, h2, h3, h4, h5, h6').first();
        if ($title.length && !$title.attr('id')) {
            $title.attr('id', modalId + '-title');
        }
        
        // Add close button attributes
        $modal.find('.mpcc-modal-close').each(function() {
            MPCCAccessibility.enhanceButton(this, {
                label: options.closeLabel || 'Close dialog'
            });
        });
        
        return modalId;
    },
    
    /**
     * Enhance form fields for accessibility
     * @param {jQuery|Element} form - Form element
     */
    enhanceForm: function(form) {
        const $form = jQuery(form);
        
        // Add aria-describedby for fields with help text
        $form.find('.mpcc-field-help').each(function() {
            const $help = jQuery(this);
            const helpId = $help.attr('id') || 'help-' + Date.now();
            $help.attr('id', helpId);
            
            // Find associated input
            const $field = $help.siblings('input, textarea, select');
            if ($field.length) {
                $field.attr('aria-describedby', helpId);
            }
        });
        
        // Add aria-invalid and aria-describedby for error messages
        $form.find('.mpcc-field-error').each(function() {
            const $error = jQuery(this);
            const errorId = $error.attr('id') || 'error-' + Date.now();
            $error.attr('id', errorId);
            
            // Find associated input
            const $field = $error.siblings('input, textarea, select');
            if ($field.length) {
                $field.attr({
                    'aria-invalid': 'true',
                    'aria-describedby': function(i, existing) {
                        return existing ? existing + ' ' + errorId : errorId;
                    }
                });
            }
        });
        
        // Add required attribute and aria-required
        $form.find('input[required], textarea[required], select[required]').attr('aria-required', 'true');
    },
    
    /**
     * Enhance chat interface for accessibility
     * @param {jQuery|Element} chatContainer - Chat container element
     */
    enhanceChatInterface: function(chatContainer) {
        const $container = jQuery(chatContainer);
        
        // Add role to chat area
        const $messages = $container.find('.mpcc-chat-messages, #mpcc-chat-messages');
        $messages.attr({
            'role': 'log',
            'aria-label': 'Chat messages',
            'aria-live': 'polite'
        });
        
        // Enhance input field
        const $input = $container.find('input[type="text"], textarea').first();
        const $sendButton = $container.find('button[type="submit"], button.send-message').first();
        
        if ($input.length) {
            $input.attr({
                'aria-label': 'Type your message',
                'placeholder': 'Type your message...'
            });
        }
        
        if ($sendButton.length) {
            this.enhanceButton($sendButton, {
                label: 'Send message'
            });
        }
    },
    
    /**
     * Create skip links for better navigation
     */
    createSkipLinks: function() {
        const skipLinks = [
            { href: '#mpcc-main-content', text: 'Skip to main content' },
            { href: '#mpcc-ai-chat', text: 'Skip to AI chat' }
        ];
        
        const $skipNav = jQuery('<nav class="mpcc-skip-links" aria-label="Skip links">');
        
        skipLinks.forEach(link => {
            jQuery('<a>', {
                href: link.href,
                class: 'screen-reader-text',
                text: link.text
            }).appendTo($skipNav);
        });
        
        $skipNav.prependTo('body');
    },
    
    /**
     * Helper function to manage aria-busy state
     * @param {jQuery|Element} element - Element to update
     * @param {boolean} isBusy - Busy state
     */
    setBusy: function(element, isBusy) {
        jQuery(element).attr('aria-busy', isBusy ? 'true' : 'false');
    },
    
    /**
     * Focus management utilities
     */
    focus: {
        /**
         * Save current focus
         */
        save: function() {
            return document.activeElement;
        },
        
        /**
         * Restore focus to element
         * @param {Element} element - Element to focus
         */
        restore: function(element) {
            if (element && jQuery(element).is(':visible')) {
                element.focus();
            }
        },
        
        /**
         * Move focus to next focusable element
         * @param {jQuery|Element} container - Container to search within
         */
        next: function(container) {
            const $container = jQuery(container || document);
            const $current = jQuery(document.activeElement);
            const $focusable = $container.find('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
            const currentIndex = $focusable.index($current);
            
            if (currentIndex < $focusable.length - 1) {
                $focusable.eq(currentIndex + 1).focus();
            }
        },
        
        /**
         * Move focus to previous focusable element
         * @param {jQuery|Element} container - Container to search within
         */
        previous: function(container) {
            const $container = jQuery(container || document);
            const $current = jQuery(document.activeElement);
            const $focusable = $container.find('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
            const currentIndex = $focusable.index($current);
            
            if (currentIndex > 0) {
                $focusable.eq(currentIndex - 1).focus();
            }
        }
    }
};

// Initialize on document ready
jQuery(document).ready(function() {
    MPCCAccessibility.init();
});

// Add screen reader only CSS if not already present
if (!jQuery('#mpcc-accessibility-styles').length) {
    jQuery('<style id="mpcc-accessibility-styles">')
        .text(`
            .sr-only {
                position: absolute !important;
                width: 1px !important;
                height: 1px !important;
                padding: 0 !important;
                margin: -1px !important;
                overflow: hidden !important;
                clip: rect(0,0,0,0) !important;
                white-space: nowrap !important;
                border: 0 !important;
            }
            
            .screen-reader-text {
                position: absolute !important;
                left: -10000px;
                top: auto;
                width: 1px;
                height: 1px;
                overflow: hidden;
            }
            
            .screen-reader-text:focus {
                position: absolute !important;
                left: 6px;
                top: 7px;
                height: auto;
                width: auto;
                display: block;
                font-size: 14px;
                font-weight: 600;
                padding: 15px 20px;
                background: #f1f1f1;
                color: #0073aa;
                z-index: 100000;
                text-decoration: none;
                box-shadow: 0 0 2px 2px rgba(0,0,0,.6);
            }
            
            .mpcc-skip-links a {
                position: absolute !important;
                left: -10000px;
                top: auto;
                width: 1px;
                height: 1px;
                overflow: hidden;
            }
            
            .mpcc-skip-links a:focus {
                position: absolute !important;
                left: 6px;
                top: 7px;
                height: auto;
                width: auto;
                display: block;
                font-size: 14px;
                font-weight: 600;
                padding: 15px 20px;
                background: #f1f1f1;
                color: #0073aa;
                z-index: 100000;
                text-decoration: none;
                box-shadow: 0 0 2px 2px rgba(0,0,0,.6);
            }
            
            /* Focus indicators */
            .mpcc-modal-overlay *:focus,
            .mpcc-chat-interface *:focus,
            .mpcc-editor-ai-modal *:focus {
                outline: 2px solid #0073aa !important;
                outline-offset: 2px !important;
            }
            
            /* High contrast mode support */
            @media (prefers-contrast: high) {
                .mpcc-modal-overlay,
                .mpcc-chat-interface {
                    border: 2px solid;
                }
            }
            
            /* Reduced motion support */
            @media (prefers-reduced-motion: reduce) {
                .mpcc-modal-overlay,
                .mpcc-chat-interface,
                .mpcc-typing-dot {
                    animation: none !important;
                    transition: none !important;
                }
            }
        `)
        .appendTo('head');
}