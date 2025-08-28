/**
 * Session Manager for MemberPress Courses Copilot
 * Centralized session management to avoid duplicates
 */

window.MPCCSessionManager = (function($) {
    'use strict';
    
    // Private variables
    let currentSessionId = null;
    let isDirty = false;
    let saveDebounceTimer = null;
    const DEBOUNCE_DELAY = 1000;
    const SESSION_STORAGE_KEY = 'mpcc_current_session_id';
    
    // Private functions
    function getAjaxSettings() {
        return MPCCUtils.getAjaxSettings();
    }
    
    function markDirty() {
        isDirty = true;
        $(document).trigger('mpcc:session-dirty');
        debouncedSave();
    }
    
    function debouncedSave() {
        if (saveDebounceTimer) {
            clearTimeout(saveDebounceTimer);
        }
        
        saveDebounceTimer = setTimeout(function() {
            module.saveConversation();
        }, DEBOUNCE_DELAY);
    }
    
    // Public module
    const module = {
        /**
         * Initialize session manager
         */
        init: function() {
            // Check for existing session in various sources
            currentSessionId = sessionStorage.getItem(SESSION_STORAGE_KEY) ||
                             window.CourseEditor?.sessionId ||
                             window.currentSessionId ||
                             null;
            
            // Set up beforeunload warning
            $(window).on('beforeunload.mpcc-session', function() {
                if (isDirty) {
                    module.saveConversation(true); // Synchronous save
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
            
            console.log('MPCC Session Manager initialized with session:', currentSessionId);
        },
        
        /**
         * Get current session ID
         */
        getCurrentSessionId: function() {
            return currentSessionId;
        },
        
        /**
         * Set current session ID
         */
        setCurrentSessionId: function(sessionId) {
            currentSessionId = sessionId;
            sessionStorage.setItem(SESSION_STORAGE_KEY, sessionId);
            
            // Update other components
            if (window.CourseEditor) {
                window.CourseEditor.sessionId = sessionId;
            }
            if (window.currentSessionId !== undefined) {
                window.currentSessionId = sessionId;
            }
            
            // Trigger event
            $(document).trigger('mpcc:session-changed', { sessionId: sessionId });
        },
        
        /**
         * Check if there are unsaved changes
         */
        hasUnsavedChanges: function() {
            return isDirty;
        },
        
        /**
         * Mark session as clean (saved)
         */
        markClean: function() {
            isDirty = false;
            $(document).trigger('mpcc:session-saved');
        },
        
        /**
         * Create new conversation/session
         */
        createNewConversation: function(options = {}) {
            const settings = getAjaxSettings();
            const defaults = {
                context: 'course_creation',
                title: 'New Course (Draft)'
            };
            const data = $.extend({}, defaults, options, {
                action: 'mpcc_create_conversation',
                nonce: settings.nonce
            });
            
            return MPCCUtils.ajax.request('mpcc_create_conversation', data, {
                success: function(response) {
                    if (response.success && response.data.session_id) {
                        module.setCurrentSessionId(response.data.session_id);
                    }
                }
            });
        },
        
        /**
         * Load existing conversation/session
         */
        loadConversation: function(sessionId) {
            const settings = getAjaxSettings();
            
            return MPCCUtils.ajax.request('mpcc_load_conversation', {
                session_id: sessionId
            }, {
                success: function(response) {
                    if (response.success) {
                        module.setCurrentSessionId(sessionId);
                    }
                }
            });
        },
        
        /**
         * Save current conversation
         */
        saveConversation: function(synchronous = false) {
            if (!currentSessionId || !isDirty) {
                return $.Deferred().resolve();
            }
            
            const settings = getAjaxSettings();
            const conversationHistory = window.mpccConversationHistory || 
                                      (window.CourseEditor ? window.CourseEditor.conversationHistory : []);
            const conversationState = window.mpccConversationState ||
                                    (window.CourseEditor ? { course_structure: window.CourseEditor.courseStructure } : {});
            
            const saveData = {
                action: 'mpcc_save_conversation',
                nonce: settings.nonce,
                session_id: currentSessionId,
                conversation_history: conversationHistory,
                conversation_state: conversationState
            };
            
            // Handle different components' data formats
            if (window.CourseEditor && window.CourseEditor.courseStructure) {
                if (!saveData.conversation_state.course_structure) {
                    saveData.conversation_state.course_structure = window.CourseEditor.courseStructure;
                }
            }
            
            if (synchronous) {
                // For synchronous saves (beforeunload), use native jQuery
                return $.ajax({
                    url: settings.url,
                    type: 'POST',
                    data: saveData,
                    async: false
                }).done(function(response) {
                    if (response.success) {
                        module.markClean();
                        console.log('Conversation saved successfully');
                    }
                });
            } else {
                return MPCCUtils.ajax.saveConversation(
                    currentSessionId,
                    conversationHistory,
                    conversationState,
                    {
                        success: function(response) {
                            if (response.success) {
                                module.markClean();
                                console.log('Conversation saved successfully');
                            }
                        }
                    }
                );
            }
        },
        
        /**
         * List all conversations/sessions
         */
        listConversations: function() {
            const settings = getAjaxSettings();
            
            return $.ajax({
                url: settings.url,
                type: 'POST',
                data: {
                    action: 'mpcc_list_conversations',
                    nonce: settings.nonce
                }
            });
        },
        
        /**
         * Delete a conversation/session
         */
        deleteSession: function(sessionId) {
            const settings = getAjaxSettings();
            
            return $.ajax({
                url: settings.url,
                type: 'POST',
                data: {
                    action: 'mpcc_delete_session',
                    nonce: settings.nonce,
                    session_id: sessionId
                }
            });
        },
        
        /**
         * Update session title
         */
        updateSessionTitle: function(sessionId, title) {
            const settings = getAjaxSettings();
            
            return $.ajax({
                url: settings.url,
                type: 'POST',
                data: {
                    action: 'mpcc_update_session_title',
                    nonce: settings.nonce,
                    session_id: sessionId || currentSessionId,
                    title: title
                }
            });
        },
        
        /**
         * Mark session as dirty (needs saving)
         */
        markDirty: markDirty
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        module.init();
    });
    
    return module;
})(jQuery);

// Make functions globally accessible for backwards compatibility
window.mpccLoadConversation = window.mpccLoadConversation || function(sessionId) {
    return window.MPCCSessionManager.loadConversation(sessionId);
};

window.createNewConversation = window.createNewConversation || function(options) {
    return window.MPCCSessionManager.createNewConversation(options);
};

window.saveConversation = window.saveConversation || function(synchronous) {
    return window.MPCCSessionManager.saveConversation(synchronous);
};