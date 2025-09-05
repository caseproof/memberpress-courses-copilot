/**
 * MemberPress Courses Copilot Accessibility Core Utility
 * Phase 1.1 WCAG Implementation - Core accessibility utility class
 * 
 * Provides core accessibility features including:
 * - Focus management (trapFocus, restoreFocus)
 * - Keyboard event handling
 * - Screen reader announcements
 * - ARIA attribute management
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Core Accessibility Utility Class
     * Singleton pattern for global accessibility management
     */
    window.MPCCAccessibility = (function() {
        
        // Private variables
        let instance = null;
        let liveRegions = {};
        let focusStack = [];
        let keyboardHandlers = new Map();
        
        /**
         * Private constructor
         * @private
         */
        function AccessibilityCore() {
            // Private properties
            this._initialized = false;
            this._focusTrapInstances = new Map();
            
            // Initialize on instantiation
            this._init();
        }
        
        /**
         * Initialize the accessibility core
         * @private
         */
        AccessibilityCore.prototype._init = function() {
            if (this._initialized) {
                return;
            }
            
            // Create default live regions
            this._createDefaultLiveRegions();
            
            // Inject accessibility CSS
            this._injectAccessibilityStyles();
            
            // Set up global keyboard listeners
            this._setupGlobalKeyboardListeners();
            
            this._initialized = true;
            
            // Log initialization
            if (window.MPCCLogger) {
                window.MPCCLogger.log('MPCCAccessibility initialized');
            }
        };
        
        /**
         * Create default ARIA live regions
         * @private
         */
        AccessibilityCore.prototype._createDefaultLiveRegions = function() {
            const priorities = ['polite', 'assertive', 'status'];
            
            priorities.forEach(priority => {
                const regionId = `mpcc-live-region-${priority}`;
                
                if (!document.getElementById(regionId)) {
                    const $region = $('<div>', {
                        id: regionId,
                        class: 'mpcc-sr-only',
                        'aria-live': priority === 'status' ? 'polite' : priority,
                        'aria-atomic': 'true',
                        role: priority === 'status' ? 'status' : null
                    }).appendTo('body');
                    
                    liveRegions[priority] = $region;
                }
            });
        };
        
        /**
         * Inject necessary accessibility styles
         * @private
         */
        AccessibilityCore.prototype._injectAccessibilityStyles = function() {
            if (!document.getElementById('mpcc-accessibility-styles')) {
                const styles = `
                    .mpcc-sr-only {
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
                    
                    /* Remove outline from focus trap - it's for internal use only */
                    .mpcc-focus-trap-active {
                        /* Internal class for focus management - no visual styling */
                    }
                `;
                
                $('<style>')
                    .attr('id', 'mpcc-accessibility-styles')
                    .text(styles)
                    .appendTo('head');
            }
        };
        
        /**
         * Set up global keyboard event listeners
         * @private
         */
        AccessibilityCore.prototype._setupGlobalKeyboardListeners = function() {
            $(document).off('keydown.mpccA11y').on('keydown.mpccA11y', (e) => {
                // Build shortcut string
                let shortcut = '';
                if (e.ctrlKey) shortcut += 'ctrl+';
                if (e.metaKey) shortcut += 'cmd+';
                if (e.altKey) shortcut += 'alt+';
                if (e.shiftKey) shortcut += 'shift+';
                shortcut += e.key.toLowerCase();
                
                // Check if handler exists
                if (keyboardHandlers.has(shortcut)) {
                    const handler = keyboardHandlers.get(shortcut);
                    if (handler.enabled) {
                        e.preventDefault();
                        handler.callback(e);
                    }
                }
            });
        };
        
        /**
         * Trap focus within a container
         * @param {string|jQuery|HTMLElement} container - Container element
         * @param {Object} options - Configuration options
         * @param {string} options.initialFocus - Selector for initial focus element
         * @param {boolean} options.escapeDeactivates - Whether ESC key deactivates trap
         * @param {Function} options.onEscape - Callback when ESC is pressed
         * @returns {Object} Focus trap instance with deactivate method
         */
        AccessibilityCore.prototype.trapFocus = function(container, options = {}) {
            const $container = $(container);
            if (!$container.length) return null;
            
            const defaults = {
                initialFocus: null,
                escapeDeactivates: true,
                onEscape: null
            };
            
            const settings = $.extend({}, defaults, options);
            const containerEl = $container[0];
            
            // Save current focus for restoration
            const previouslyFocused = document.activeElement;
            focusStack.push(previouslyFocused);
            
            // Set initial focus
            if (settings.initialFocus) {
                const $initialFocus = $container.find(settings.initialFocus);
                if ($initialFocus.length) {
                    $initialFocus.focus();
                }
            } else {
                // Find first focusable element
                const $firstFocusable = $container.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').first();
                if ($firstFocusable.length) {
                    $firstFocusable.focus();
                }
            }
            
            // Trap focus handler
            const trapHandler = (e) => {
                if (e.key === 'Tab') {
                    const focusableElements = $container.find('button:visible, [href]:visible, input:visible, select:visible, textarea:visible, [tabindex]:not([tabindex="-1"]):visible');
                    const $focusable = focusableElements.filter(':not([disabled])');
                    
                    if ($focusable.length === 0) return;
                    
                    const firstFocusable = $focusable[0];
                    const lastFocusable = $focusable[$focusable.length - 1];
                    
                    if (e.shiftKey) {
                        if (document.activeElement === firstFocusable) {
                            e.preventDefault();
                            lastFocusable.focus();
                        }
                    } else {
                        if (document.activeElement === lastFocusable) {
                            e.preventDefault();
                            firstFocusable.focus();
                        }
                    }
                } else if (e.key === 'Escape' && settings.escapeDeactivates) {
                    e.preventDefault();
                    if (settings.onEscape) {
                        settings.onEscape(e);
                    }
                    instance.deactivate();
                }
            };
            
            // Add event listener
            containerEl.addEventListener('keydown', trapHandler);
            
            // Mark container as trap active
            $container.addClass('mpcc-focus-trap-active');
            
            // Create trap instance
            const instance = {
                deactivate: () => {
                    containerEl.removeEventListener('keydown', trapHandler);
                    $container.removeClass('mpcc-focus-trap-active');
                    
                    // Restore focus
                    const toFocus = focusStack.pop();
                    if (toFocus && toFocus.focus) {
                        toFocus.focus();
                    }
                    
                    // Remove from instances
                    this._focusTrapInstances.delete(containerEl);
                }
            };
            
            // Store instance
            this._focusTrapInstances.set(containerEl, instance);
            
            return instance;
        };
        
        /**
         * Restore focus to previously focused element
         * @param {HTMLElement} element - Element to restore focus to (optional)
         */
        AccessibilityCore.prototype.restoreFocus = function(element) {
            if (element && element.focus) {
                element.focus();
            } else if (focusStack.length > 0) {
                const toFocus = focusStack.pop();
                if (toFocus && toFocus.focus) {
                    toFocus.focus();
                }
            }
        };
        
        /**
         * Make an element keyboard navigable with custom handlers
         * @param {string|jQuery|HTMLElement} element - Element to enhance
         * @param {Object} handlers - Keyboard event handlers
         * @param {Object} options - Additional options
         * @returns {Function} Cleanup function to remove handlers
         */
        AccessibilityCore.prototype.makeKeyboardNavigable = function(element, handlers = {}, options = {}) {
            const $element = $(element);
            if (!$element.length) return () => {};
            
            const defaults = {
                preventDefault: true,
                stopPropagation: false
            };
            
            const settings = $.extend({}, defaults, options);
            
            // Handler function
            const keyHandler = (e) => {
                let handled = false;
                
                switch(e.key) {
                    case 'Enter':
                        if (handlers.enter) {
                            handlers.enter(e);
                            handled = true;
                        }
                        break;
                    case ' ':
                    case 'Spacebar':
                        if (handlers.space) {
                            handlers.space(e);
                            handled = true;
                        }
                        break;
                    case 'ArrowUp':
                        if (handlers.up) {
                            handlers.up(e);
                            handled = true;
                        }
                        break;
                    case 'ArrowDown':
                        if (handlers.down) {
                            handlers.down(e);
                            handled = true;
                        }
                        break;
                    case 'ArrowLeft':
                        if (handlers.left) {
                            handlers.left(e);
                            handled = true;
                        }
                        break;
                    case 'ArrowRight':
                        if (handlers.right) {
                            handlers.right(e);
                            handled = true;
                        }
                        break;
                    case 'Escape':
                        if (handlers.escape) {
                            handlers.escape(e);
                            handled = true;
                        }
                        break;
                    case 'Tab':
                        if (handlers.tab) {
                            handlers.tab(e);
                            handled = true;
                        }
                        break;
                    default:
                        if (handlers[e.key]) {
                            handlers[e.key](e);
                            handled = true;
                        }
                }
                
                if (handled) {
                    if (settings.preventDefault) {
                        e.preventDefault();
                    }
                    if (settings.stopPropagation) {
                        e.stopPropagation();
                    }
                }
            };
            
            // Add event listener with namespace
            $element.off('keydown.mpccNav').on('keydown.mpccNav', keyHandler);
            
            // Return cleanup function
            return () => {
                $element.off('keydown.mpccNav');
            };
        };
        
        /**
         * Register a keyboard shortcut handler
         * @param {string} shortcut - Keyboard shortcut (e.g., 'ctrl+s', 'shift+enter')
         * @param {Function} callback - Handler function
         * @param {Object} options - Additional options
         * @returns {string} Handler ID for removal
         */
        AccessibilityCore.prototype.handleKeyboardShortcuts = function(shortcut, callback, options = {}) {
            const defaults = {
                enabled: true,
                description: ''
            };
            
            const settings = $.extend({}, defaults, options);
            const handlerId = `handler-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            
            keyboardHandlers.set(shortcut.toLowerCase(), {
                id: handlerId,
                callback: callback,
                enabled: settings.enabled,
                description: settings.description
            });
            
            return handlerId;
        };
        
        /**
         * Remove a keyboard shortcut handler
         * @param {string} handlerId - Handler ID returned from handleKeyboardShortcuts
         */
        AccessibilityCore.prototype.removeKeyboardShortcut = function(handlerId) {
            for (const [key, handler] of keyboardHandlers.entries()) {
                if (handler.id === handlerId) {
                    keyboardHandlers.delete(key);
                    break;
                }
            }
        };
        
        /**
         * Announce a message to screen readers
         * @param {string} message - Message to announce
         * @param {Object} options - Configuration options
         */
        AccessibilityCore.prototype.announce = function(message, options = {}) {
            const defaults = {
                priority: 'polite',
                clear: true
            };
            
            const settings = $.extend({}, defaults, options);
            
            // Get or create live region
            let $region = liveRegions[settings.priority];
            if (!$region) {
                this.createLiveRegion(settings.priority);
                $region = liveRegions[settings.priority];
            }
            
            if (settings.clear) {
                $region.empty();
            }
            
            // Use timeout to ensure screen readers pick up the change
            setTimeout(() => {
                $region.text(message);
            }, 100);
            
            // Clear after announcement
            setTimeout(() => {
                $region.empty();
            }, 3000);
        };
        
        /**
         * Create a custom live region
         * @param {string} priority - Priority level (polite, assertive, status)
         * @param {string} id - Custom ID for the region
         * @returns {jQuery} The created live region
         */
        AccessibilityCore.prototype.createLiveRegion = function(priority = 'polite', id = null) {
            const regionId = id || `mpcc-live-region-${priority}-${Date.now()}`;
            
            const $region = $('<div>', {
                id: regionId,
                class: 'mpcc-sr-only',
                'aria-live': priority === 'status' ? 'polite' : priority,
                'aria-atomic': 'true',
                role: priority === 'status' ? 'status' : null
            }).appendTo('body');
            
            if (!id) {
                liveRegions[priority] = $region;
            }
            
            return $region;
        };
        
        /**
         * Set ARIA attributes on an element
         * @param {string|jQuery|HTMLElement} element - Target element
         * @param {Object} attributes - ARIA attributes to set
         */
        AccessibilityCore.prototype.setARIA = function(element, attributes) {
            const $element = $(element);
            if (!$element.length) return;
            
            Object.keys(attributes).forEach(attr => {
                if (attr.startsWith('aria-') || ['role', 'tabindex'].includes(attr)) {
                    $element.attr(attr, attributes[attr]);
                }
            });
        };
        
        /**
         * Toggle ARIA attribute value
         * @param {string|jQuery|HTMLElement} element - Target element
         * @param {string} attribute - ARIA attribute name
         * @param {string} value1 - First value
         * @param {string} value2 - Second value
         */
        AccessibilityCore.prototype.toggleARIA = function(element, attribute, value1, value2) {
            const $element = $(element);
            if (!$element.length) return;
            
            const currentValue = $element.attr(attribute);
            const newValue = currentValue === value1 ? value2 : value1;
            $element.attr(attribute, newValue);
        };
        
        /**
         * Update multiple ARIA attributes with validation
         * @param {string|jQuery|HTMLElement} element - Target element
         * @param {Object} updates - Attributes to update
         * @param {Object} options - Update options
         */
        AccessibilityCore.prototype.updateARIA = function(element, updates, options = {}) {
            const $element = $(element);
            if (!$element.length) return;
            
            const defaults = {
                validate: true,
                announce: false
            };
            
            const settings = $.extend({}, defaults, options);
            
            // Valid ARIA attribute values for validation
            const validValues = {
                'aria-expanded': ['true', 'false'],
                'aria-pressed': ['true', 'false', 'mixed'],
                'aria-checked': ['true', 'false', 'mixed'],
                'aria-disabled': ['true', 'false'],
                'aria-hidden': ['true', 'false'],
                'aria-invalid': ['true', 'false', 'grammar', 'spelling'],
                'aria-selected': ['true', 'false'],
                'aria-modal': ['true', 'false']
            };
            
            Object.keys(updates).forEach(attr => {
                const value = String(updates[attr]);
                
                // Validate if required
                if (settings.validate && validValues[attr]) {
                    if (!validValues[attr].includes(value)) {
                        console.warn(`Invalid value "${value}" for ${attr}`);
                        return;
                    }
                }
                
                $element.attr(attr, value);
            });
            
            // Announce change if requested
            if (settings.announce) {
                const label = $element.attr('aria-label') || $element.text() || 'Element';
                this.announce(`${label} updated`);
            }
        };
        
        /**
         * Check if element is visible to screen readers
         * @param {string|jQuery|HTMLElement} element - Element to check
         * @returns {boolean} Whether element is screen reader visible
         */
        AccessibilityCore.prototype.isScreenReaderVisible = function(element) {
            const $element = $(element);
            if (!$element.length) return false;
            
            const el = $element[0];
            
            // Check if hidden via ARIA
            if ($element.attr('aria-hidden') === 'true') {
                return false;
            }
            
            // Check if visually hidden
            const style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden') {
                return false;
            }
            
            // Check if clipped
            if (style.position === 'absolute' && 
                style.clip === 'rect(0px, 0px, 0px, 0px)' && 
                style.width === '1px' && 
                style.height === '1px') {
                return true; // Screen reader only
            }
            
            return true;
        };
        
        /**
         * Get accessible text for an element
         * @param {string|jQuery|HTMLElement} element - Element to get text from
         * @returns {string} Accessible text content
         */
        AccessibilityCore.prototype.getAccessibleText = function(element) {
            const $element = $(element);
            if (!$element.length) return '';
            
            // Priority order for accessible text
            // 1. aria-labelledby
            const labelledBy = $element.attr('aria-labelledby');
            if (labelledBy) {
                const labels = labelledBy.split(' ').map(id => $(`#${id}`).text()).join(' ');
                if (labels) return labels.trim();
            }
            
            // 2. aria-label
            const ariaLabel = $element.attr('aria-label');
            if (ariaLabel) return ariaLabel;
            
            // 3. Associated label
            const id = $element.attr('id');
            if (id) {
                const $label = $(`label[for="${id}"]`);
                if ($label.length) return $label.text().trim();
            }
            
            // 4. Text content
            return $element.text().trim();
        };
        
        /**
         * Enhance modal dialog accessibility
         * @param {string|jQuery|HTMLElement} modal - Modal element
         * @param {Object} options - Enhancement options
         */
        AccessibilityCore.prototype.enhanceModal = function(modal, options = {}) {
            const $modal = $(modal);
            if (!$modal.length) return;
            
            const defaults = {
                role: 'dialog',
                closeOnEscape: true,
                focusOnOpen: true,
                restoreFocusOnClose: true,
                labelledBy: null,
                describedBy: null
            };
            
            const settings = $.extend({}, defaults, options);
            
            // Set ARIA attributes
            const ariaAttrs = {
                role: settings.role,
                'aria-modal': 'true'
            };
            
            if (settings.labelledBy) {
                ariaAttrs['aria-labelledby'] = settings.labelledBy;
            }
            
            if (settings.describedBy) {
                ariaAttrs['aria-describedby'] = settings.describedBy;
            }
            
            this.setARIA($modal, ariaAttrs);
            
            // Set up focus trap if requested
            if (settings.focusOnOpen) {
                const focusTrap = this.trapFocus($modal, {
                    escapeDeactivates: settings.closeOnEscape,
                    onEscape: () => {
                        if (settings.onClose) {
                            settings.onClose();
                        }
                    }
                });
                
                // Store trap for cleanup
                $modal.data('mpcc-focus-trap', focusTrap);
            }
            
            return {
                destroy: () => {
                    const trap = $modal.data('mpcc-focus-trap');
                    if (trap) {
                        trap.deactivate();
                        $modal.removeData('mpcc-focus-trap');
                    }
                }
            };
        };
        
        // Singleton pattern
        return {
            /**
             * Get singleton instance
             * @returns {AccessibilityCore} The singleton instance
             */
            getInstance: function() {
                if (!instance) {
                    instance = new AccessibilityCore();
                }
                return instance;
            }
        };
    })();
    
    // Create and expose the singleton instance
    const mpccAccessibility = window.MPCCAccessibility.getInstance();
    
    // Expose convenience methods on the MPCCAccessibility object
    Object.getOwnPropertyNames(Object.getPrototypeOf(mpccAccessibility)).forEach(method => {
        if (method !== 'constructor' && !method.startsWith('_')) {
            window.MPCCAccessibility[method] = mpccAccessibility[method].bind(mpccAccessibility);
        }
    });
    
    // Auto-initialize when DOM is ready
    $(document).ready(function() {
        // Ensure initialization
        mpccAccessibility._init();
    });
    
})(jQuery);