/**
 * Toast Notification Utility
 */
window.mpccToast = (function($) {
    'use strict';
    
    let container = null;
    
    function init() {
        if (!container) {
            container = $('<div class="mpcc-toast-container"></div>');
            $('body').append(container);
        }
    }
    
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
    
    function removeToast(toast) {
        toast.removeClass('show').addClass('hide');
        setTimeout(() => toast.remove(), 300);
    }
    
    return {
        success: (message, duration) => show(message, 'success', duration),
        error: (message, duration) => show(message, 'error', duration),
        warning: (message, duration) => show(message, 'warning', duration),
        info: (message, duration) => show(message, 'info', duration)
    };
    
})(jQuery);