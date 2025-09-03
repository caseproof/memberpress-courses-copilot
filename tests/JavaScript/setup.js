/**
 * Jest setup file for MemberPress Courses Copilot tests
 * 
 * Sets up global mocks and utilities needed for testing
 * 
 * @package MemberPressCoursesCopilot\Tests\JavaScript
 */

// Mock jQuery
global.$ = global.jQuery = require('jquery');

// Add support for :visible and :hidden pseudo-selectors in jsdom
$.expr.pseudos.visible = function(elem) {
    return elem.offsetWidth > 0 || elem.offsetHeight > 0 || elem.getClientRects().length > 0;
};

$.expr.pseudos.hidden = function(elem) {
    return !$.expr.pseudos.visible(elem);
};

// Mock WordPress globals that quiz-ai-modal.js expects
global.wp = {
    data: {
        select: jest.fn(() => ({
            getCurrentPostId: jest.fn(() => 123),
            getBlocks: jest.fn(() => [])
        })),
        dispatch: jest.fn(() => ({
            insertBlocks: jest.fn(),
            editPost: jest.fn(),
            addPlaceholder: jest.fn(),
            getNextQuestionId: jest.fn().mockResolvedValue({ id: 456 })
        })),
        subscribe: jest.fn(() => jest.fn()) // Returns unsubscribe function
    },
    blocks: {
        createBlock: jest.fn((type, attributes) => ({
            name: type,
            attributes: attributes || {},
            clientId: `client-${Math.random().toString(36).substr(2, 9)}`
        }))
    },
    domReady: jest.fn(callback => {
        // Execute callback immediately in tests
        if (typeof callback === 'function') {
            callback();
        }
    })
};

// Mock AJAX settings that would be localized by WordPress
global.mpcc_ajax = {
    ajax_url: '/wp-admin/admin-ajax.php',
    nonce: 'test-nonce-123',
    security: 'test-nonce-123' // Some scripts may use 'security' instead of 'nonce'
};

// Mock browser APIs
Object.defineProperty(global, 'navigator', {
    value: {
        clipboard: {
            writeText: jest.fn(() => Promise.resolve())
        }
    },
    writable: true
});

// Mock URLSearchParams for older browsers
if (!global.URLSearchParams) {
    global.URLSearchParams = class URLSearchParams {
        constructor(search = '') {
            this.params = new Map();
            if (search) {
                // Simple parsing for tests
                search.replace(/^\?/, '').split('&').forEach(pair => {
                    const [key, value] = pair.split('=');
                    if (key) {
                        this.params.set(decodeURIComponent(key), decodeURIComponent(value || ''));
                    }
                });
            }
        }
        
        get(key) {
            return this.params.get(key) || null;
        }
        
        set(key, value) {
            this.params.set(key, value);
        }
        
        has(key) {
            return this.params.has(key);
        }
    };
}

// Mock history API
global.history = {
    replaceState: jest.fn()
};

// Mock document properties
Object.defineProperty(document, 'referrer', {
    value: '',
    writable: true
});

// Mock console methods to avoid noise in tests
global.console = {
    ...console,
    log: jest.fn(),
    error: jest.fn(),
    warn: jest.fn(),
    debug: jest.fn()
};

// Helper function to create mock jQuery elements
global.createMockElement = (tagName = 'div', attributes = {}) => {
    const element = document.createElement(tagName);
    Object.keys(attributes).forEach(attr => {
        element.setAttribute(attr, attributes[attr]);
    });
    
    const $element = $(element);
    return $element;
};

// Helper function to simulate AJAX responses
global.mockAjaxResponse = (success = true, data = {}) => {
    return {
        success,
        data,
        responseJSON: { success, data }
    };
};

// Helper function to create mock lesson data
global.createMockLesson = (id, title, courseId = null) => {
    return {
        id: id,
        title: { rendered: title },
        course_id: courseId
    };
};

// Helper function to create mock course data
global.createMockCourse = (id, title, lessons = []) => {
    return {
        id: id,
        title: { rendered: title },
        lessons: lessons
    };
};

// Mock window.location for URL tests
delete window.location;
window.location = {
    href: 'http://test.local/wp-admin/post.php?post=123&action=edit',
    search: '?post=123&action=edit',
    pathname: '/wp-admin/post.php',
    host: 'test.local',
    protocol: 'http:'
};

// Setup DOM for tests
document.body.innerHTML = `
    <div class="wrap">
        <h1>Edit Quiz</h1>
        <div class="editor-header__settings"></div>
    </div>
`;

// Add body classes that the modal checks for
document.body.classList.add('post-type-mpcs-quiz');

// Mock timers for setTimeout/setInterval tests
jest.useFakeTimers();

// Add custom jQuery matchers for testing
expect.extend({
    toHaveText(received, expected) {
        const text = received.text();
        const pass = text.includes(expected);
        return {
            pass,
            message: () => `expected ${received.selector} to have text "${expected}", but got "${text}"`
        };
    },
    toContainText(received, expected) {
        const text = received.text();
        const pass = text.includes(expected);
        return {
            pass,
            message: () => `expected ${received.selector} to contain text "${expected}", but got "${text}"`
        };
    },
    toHaveClass(received, expected) {
        const pass = received.hasClass(expected);
        return {
            pass,
            message: () => `expected ${received.selector} to have class "${expected}"`
        };
    },
    toBeVisible(received) {
        // Check if element exists and is not hidden
        const exists = received.length > 0;
        const isHidden = received.css('display') === 'none' || received.is(':hidden');
        const pass = exists && !isHidden;
        return {
            pass,
            message: () => pass 
                ? `expected element to not be visible` 
                : `expected element to be visible (exists: ${exists}, display: ${received.css('display')})`
        };
    }
});

// Mock jQuery's $.get to return a proper jQuery promise
const mockGetImplementation = () => {
    const callbacks = {
        done: [],
        fail: [],
        always: []
    };
    
    const promise = {
        done: function(callback) {
            if (callback) callbacks.done.push(callback);
            return this;
        },
        fail: function(callback) {
            if (callback) callbacks.fail.push(callback);
            return this;
        },
        always: function(callback) {
            if (callback) callbacks.always.push(callback);
            return this;
        },
        // Method to trigger success
        _resolve: function(data) {
            callbacks.done.forEach(cb => cb(data));
            callbacks.always.forEach(cb => cb(data));
        },
        // Method to trigger error
        _reject: function(error) {
            callbacks.fail.forEach(cb => cb(error));
            callbacks.always.forEach(cb => cb(error));
        }
    };
    
    // Store reference for test manipulation
    $.get._lastPromise = promise;
    
    // Auto-resolve with empty array if nothing else is set
    setTimeout(() => {
        if (callbacks.done.length > 0 && !promise._resolved && !promise._rejected) {
            promise._resolve([]);
        }
    }, 0);
    
    return promise;
};

$.get = jest.fn(mockGetImplementation);