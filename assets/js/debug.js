/**
 * Debug utility for MemberPress Courses Copilot
 * 
 * Provides consistent debug logging that can be toggled on/off
 * based on WordPress debug mode or a debug parameter
 * 
 * @package MemberPressCoursesCopilot
 * @version 1.0.0
 */

(function(window) {
    'use strict';

    /**
     * Debug configuration
     */
    const debugConfig = {
        // Check if WP_DEBUG is enabled (passed from PHP)
        enabled: window.mpcc_debug && window.mpcc_debug.enabled === true,
        
        // Check for debug parameter in URL
        urlDebug: new URLSearchParams(window.location.search).has('mpcc_debug'),
        
        // Prefix for all debug messages
        prefix: 'MPCC',
        
        // Console styling
        styles: {
            info: 'color: #2271b1; font-weight: bold;',
            warn: 'color: #d63638; font-weight: bold;',
            error: 'color: #dc3232; font-weight: bold;',
            success: 'color: #46b450; font-weight: bold;',
            debug: 'color: #666; font-style: italic;'
        }
    };

    /**
     * Check if debug mode is enabled
     */
    function isDebugEnabled() {
        return debugConfig.enabled || debugConfig.urlDebug;
    }

    /**
     * Format message with prefix and timestamp
     */
    function formatMessage(module, message) {
        const timestamp = new Date().toLocaleTimeString();
        return `[${timestamp}] ${debugConfig.prefix} ${module}:`;
    }

    /**
     * Create a debug logger for a specific module
     * 
     * @param {string} moduleName - Name of the module (e.g., 'Quiz AI Modal')
     * @returns {Object} Logger instance with log methods
     */
    function createLogger(moduleName) {
        return {
            /**
             * Log info message
             */
            log: function(...args) {
                if (!isDebugEnabled()) return;
                console.log(
                    `%c${formatMessage(moduleName, args[0])}`,
                    debugConfig.styles.info,
                    ...args
                );
            },

            /**
             * Log warning message
             */
            warn: function(...args) {
                if (!isDebugEnabled()) return;
                console.warn(
                    `%c${formatMessage(moduleName, args[0])}`,
                    debugConfig.styles.warn,
                    ...args
                );
            },

            /**
             * Log error message
             */
            error: function(...args) {
                if (!isDebugEnabled()) return;
                console.error(
                    `%c${formatMessage(moduleName, args[0])}`,
                    debugConfig.styles.error,
                    ...args
                );
            },

            /**
             * Log success message
             */
            success: function(...args) {
                if (!isDebugEnabled()) return;
                console.log(
                    `%c${formatMessage(moduleName, args[0])}`,
                    debugConfig.styles.success,
                    ...args
                );
            },

            /**
             * Log debug message (more verbose)
             */
            debug: function(...args) {
                if (!isDebugEnabled()) return;
                console.log(
                    `%c${formatMessage(moduleName, args[0])}`,
                    debugConfig.styles.debug,
                    ...args
                );
            },

            /**
             * Log group of related messages
             */
            group: function(label, callback) {
                if (!isDebugEnabled()) return;
                console.group(`${debugConfig.prefix} ${moduleName}: ${label}`);
                callback();
                console.groupEnd();
            },

            /**
             * Log table data
             */
            table: function(data, columns) {
                if (!isDebugEnabled()) return;
                console.log(`${formatMessage(moduleName, 'Table Data')}:`);
                console.table(data, columns);
            },

            /**
             * Performance timing
             */
            time: function(label) {
                if (!isDebugEnabled()) return;
                console.time(`${debugConfig.prefix} ${moduleName}: ${label}`);
            },

            /**
             * End performance timing
             */
            timeEnd: function(label) {
                if (!isDebugEnabled()) return;
                console.timeEnd(`${debugConfig.prefix} ${moduleName}: ${label}`);
            },

            /**
             * Check if debug is enabled
             */
            isEnabled: function() {
                return isDebugEnabled();
            }
        };
    }

    /**
     * Global debug object
     */
    const MPCCDebug = {
        /**
         * Create a logger for a module
         */
        createLogger: createLogger,

        /**
         * Check if debug is enabled
         */
        isEnabled: isDebugEnabled,

        /**
         * Enable/disable debug mode temporarily
         */
        setEnabled: function(enabled) {
            debugConfig.enabled = enabled;
        },

        /**
         * Get debug configuration
         */
        getConfig: function() {
            return { ...debugConfig };
        }
    };

    // Expose globally
    window.MPCCDebug = MPCCDebug;

})(window);