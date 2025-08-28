/**
 * Toast Notification Utility
 * Provides non-intrusive notifications for user feedback
 * 
 * @package MemberPressCoursesCopilot
 * @version 1.0.0
 */
window.mpccToast = window.MPCCToast = (function($) {
    'use strict';
    
    let container = null;
    
    /**
     * Initialize the toast container
     * @private
     */
    function init() {
        if (!container) {
            container = $('<div class="mpcc-toast-container"></div>');
            $('body').append(container);
        }
    }
    
    /**
     * Show a toast notification
     * @param {string} message - The message to display
     * @param {string} [type='info'] - The type of toast (success, error, warning, info)
     * @param {number} [duration=3000] - How long to show the toast in milliseconds
     * @returns {jQuery} The toast element
     */
    function show(message, type = 'info', duration = 3000) {
        init();
        
        const icons = {
            success: 'dashicons-yes-alt',
            error: 'dashicons-dismiss',
            warning: 'dashicons-warning',
            info: 'dashicons-info'
        };
        
        const toast = $(`
            <div class="mpcc-toast ${type}">
                <span class="mpcc-toast-icon dashicons ${icons[type]}"></span>
                <span class="mpcc-toast-message">${message}</span>
                <span class="mpcc-toast-close dashicons dashicons-no-alt"></span>
            </div>
        `);
        
        container.append(toast);
        
        // Trigger animation
        setTimeout(() => toast.addClass('show'), 10);
        
        // Close button
        toast.find('.mpcc-toast-close').on('click', function() {
            removeToast(toast);
        });
        
        // Auto remove
        if (duration > 0) {
            setTimeout(() => removeToast(toast), duration);
        }
        
        return toast;
    }
    
    /**
     * Remove a toast notification
     * @param {jQuery} toast - The toast element to remove
     * @private
     */
    function removeToast(toast) {
        toast.removeClass('show').addClass('hide');
        setTimeout(() => toast.remove(), 300);
    }
    
    /**
     * Public API
     * @namespace mpccToast
     */
    return {
        /**
         * Show a custom toast
         * @memberof mpccToast
         */
        show: show,
        /**
         * Show a success toast
         * @param {string} message - The success message
         * @param {number} [duration] - Duration in milliseconds
         * @memberof mpccToast
         */
        success: (message, duration) => show(message, 'success', duration),
        /**
         * Show an error toast
         * @param {string} message - The error message
         * @param {number} [duration] - Duration in milliseconds
         * @memberof mpccToast
         */
        error: (message, duration) => show(message, 'error', duration),
        /**
         * Show a warning toast
         * @param {string} message - The warning message
         * @param {number} [duration] - Duration in milliseconds
         * @memberof mpccToast
         */
        warning: (message, duration) => show(message, 'warning', duration),
        /**
         * Show an info toast
         * @param {string} message - The info message
         * @param {number} [duration] - Duration in milliseconds
         * @memberof mpccToast
         */
        info: (message, duration) => show(message, 'info', duration)
    };
    
})(jQuery);