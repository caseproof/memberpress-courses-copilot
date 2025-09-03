/**
 * Jest setup file for MemberPress Courses Copilot tests
 * 
 * Sets up global mocks and utilities needed for testing
 * 
 * @package MemberPressCoursesCopilot\Tests\JavaScript
 */

// Mock jQuery
global.$ = global.jQuery = require('jquery');

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
    nonce: 'test-nonce-123'
};

// Mock browser APIs
global.navigator = {
    clipboard: {
        writeText: jest.fn().mockResolvedValue()
    }
};

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