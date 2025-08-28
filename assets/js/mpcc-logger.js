/**
 * MemberPress Courses Copilot Logger
 * Centralized logging utility for development and debugging
 * 
 * @package MemberPressCoursesCopilot
 * @version 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Logger utility with configurable debug levels
     */
    window.MPCCLogger = {
        // Log levels
        LEVELS: {
            ERROR: 0,
            WARN: 1,
            INFO: 2,
            DEBUG: 3
        },

        // Current log level (set from localized data or default to WARN)
        currentLevel: 1,

        // Whether logging is enabled
        enabled: false,

        /**
         * Initialize the logger
         */
        init: function() {
            // Check if debug mode is enabled
            if (window.mpccSettings && window.mpccSettings.debug) {
                this.enabled = true;
                this.currentLevel = this.LEVELS.DEBUG;
            }

            // Allow override via URL parameter for testing
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('mpcc_debug') === '1') {
                this.enabled = true;
                this.currentLevel = this.LEVELS.DEBUG;
            }
        },

        /**
         * Log an error message
         * @param {string} message - The error message
         * @param {*} data - Additional data to log
         */
        error: function(message, ...data) {
            if (this.enabled && this.currentLevel >= this.LEVELS.ERROR) {
                console.error(`[MPCC Error] ${message}`, ...data);
            }
        },

        /**
         * Log a warning message
         * @param {string} message - The warning message
         * @param {*} data - Additional data to log
         */
        warn: function(message, ...data) {
            if (this.enabled && this.currentLevel >= this.LEVELS.WARN) {
                console.warn(`[MPCC Warning] ${message}`, ...data);
            }
        },

        /**
         * Log an info message
         * @param {string} message - The info message
         * @param {*} data - Additional data to log
         */
        info: function(message, ...data) {
            if (this.enabled && this.currentLevel >= this.LEVELS.INFO) {
                console.info(`[MPCC Info] ${message}`, ...data);
            }
        },

        /**
         * Log a debug message
         * @param {string} message - The debug message
         * @param {*} data - Additional data to log
         */
        debug: function(message, ...data) {
            if (this.enabled && this.currentLevel >= this.LEVELS.DEBUG) {
                console.log(`[MPCC Debug] ${message}`, ...data);
            }
        },

        /**
         * Log a group of related messages
         * @param {string} label - The group label
         * @param {Function} callback - Function containing log statements
         */
        group: function(label, callback) {
            if (this.enabled && this.currentLevel >= this.LEVELS.DEBUG) {
                console.group(`[MPCC] ${label}`);
                callback();
                console.groupEnd();
            }
        },

        /**
         * Log performance timing
         * @param {string} label - The timing label
         */
        time: function(label) {
            if (this.enabled && this.currentLevel >= this.LEVELS.DEBUG) {
                console.time(`[MPCC] ${label}`);
            }
        },

        /**
         * End performance timing
         * @param {string} label - The timing label
         */
        timeEnd: function(label) {
            if (this.enabled && this.currentLevel >= this.LEVELS.DEBUG) {
                console.timeEnd(`[MPCC] ${label}`);
            }
        },

        /**
         * Assert a condition
         * @param {boolean} condition - The condition to assert
         * @param {string} message - The assertion message
         */
        assert: function(condition, message) {
            if (this.enabled && !condition) {
                console.assert(condition, `[MPCC Assert] ${message}`);
            }
        },

        /**
         * Create a table log
         * @param {Array|Object} data - The data to display in table format
         */
        table: function(data) {
            if (this.enabled && this.currentLevel >= this.LEVELS.DEBUG) {
                console.table(data);
            }
        }
    };

    // Initialize the logger
    $(document).ready(function() {
        MPCCLogger.init();
    });

})(jQuery);