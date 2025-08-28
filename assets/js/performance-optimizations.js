/**
 * Performance Optimizations for MemberPress Courses Copilot
 * 
 * Handles lazy loading, resource management, and performance monitoring
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */
(function($) {
    'use strict';

    window.MPCCPerformance = {
        /**
         * Lazy load modules on demand
         */
        lazyLoadModule: function(moduleName, callback) {
            // Check if module is already loaded
            if (this.loadedModules[moduleName]) {
                if (callback) callback();
                return;
            }
            
            // Module loading configuration
            const moduleConfig = {
                'ai-chat': {
                    script: mpccPerformance.pluginUrl + 'assets/js/ai-chat-interface.js',
                    check: () => window.MPCCChatInterface,
                    init: () => {
                        if (window.MPCCChatInterface && window.MPCCChatInterface.init) {
                            window.MPCCChatInterface.init();
                        }
                    }
                },
                'editor-modal': {
                    script: mpccPerformance.pluginUrl + 'assets/js/editor-ai-modal.js',
                    check: () => window.MPCCEditorModal,
                    init: () => {
                        if (window.initializeModal) {
                            window.initializeModal();
                        }
                    }
                },
                'course-preview': {
                    script: mpccPerformance.pluginUrl + 'assets/js/course-preview-editor.js',
                    check: () => window.CoursePreviewEditor
                }
            };
            
            const config = moduleConfig[moduleName];
            if (!config) {
                console.error('MPCC: Unknown module:', moduleName);
                return;
            }
            
            // Check if already loaded
            if (config.check()) {
                this.loadedModules[moduleName] = true;
                if (callback) callback();
                return;
            }
            
            // Load the script
            $.getScript(config.script)
                .done(() => {
                    this.loadedModules[moduleName] = true;
                    if (config.init) config.init();
                    if (callback) callback();
                })
                .fail((jqxhr, settings, exception) => {
                    console.error('MPCC: Failed to load module:', moduleName, exception);
                });
        },
        
        /**
         * Track loaded modules
         */
        loadedModules: {},
        
        /**
         * Optimize scroll event handlers
         */
        optimizeScrollHandlers: function() {
            let scrollTimeout;
            const scrollHandlers = [];
            
            // Replace window scroll with throttled version
            $(window).off('scroll.mpcc-optimized').on('scroll.mpcc-optimized', MPCCUtils.throttle(function() {
                scrollHandlers.forEach(handler => handler());
            }, 100));
            
            // Provide method to add scroll handlers
            this.addScrollHandler = function(handler) {
                scrollHandlers.push(handler);
            };
        },
        
        /**
         * Optimize resize event handlers
         */
        optimizeResizeHandlers: function() {
            let resizeTimeout;
            const resizeHandlers = [];
            
            // Replace window resize with debounced version
            $(window).off('resize.mpcc-optimized').on('resize.mpcc-optimized', MPCCUtils.debounce(function() {
                resizeHandlers.forEach(handler => handler());
            }, 250));
            
            // Provide method to add resize handlers
            this.addResizeHandler = function(handler) {
                resizeHandlers.push(handler);
            };
        },
        
        /**
         * Preload critical resources
         */
        preloadResources: function() {
            // Preload critical fonts
            if (mpccPerformance.criticalFonts) {
                mpccPerformance.criticalFonts.forEach(font => {
                    const link = document.createElement('link');
                    link.rel = 'preload';
                    link.as = 'font';
                    link.href = font.url;
                    link.crossOrigin = 'anonymous';
                    document.head.appendChild(link);
                });
            }
            
            // Preconnect to external domains
            if (mpccPerformance.preconnectDomains) {
                mpccPerformance.preconnectDomains.forEach(domain => {
                    const link = document.createElement('link');
                    link.rel = 'preconnect';
                    link.href = domain;
                    document.head.appendChild(link);
                });
            }
        },
        
        /**
         * Monitor performance metrics
         */
        monitorPerformance: function() {
            if (!window.performance || !window.performance.getEntriesByType) return;
            
            // Monitor long tasks
            if ('PerformanceObserver' in window) {
                try {
                    const observer = new PerformanceObserver((list) => {
                        for (const entry of list.getEntries()) {
                            if (entry.duration > 50) {
                                console.warn('MPCC: Long task detected:', entry.duration + 'ms');
                            }
                        }
                    });
                    observer.observe({ entryTypes: ['longtask'] });
                } catch (e) {
                    // Long task observer not supported
                }
            }
            
            // Log page load metrics
            window.addEventListener('load', () => {
                setTimeout(() => {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    if (perfData) {
                        console.log('MPCC Performance Metrics:', {
                            'DOM Content Loaded': perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart,
                            'Load Complete': perfData.loadEventEnd - perfData.loadEventStart,
                            'Total Load Time': perfData.loadEventEnd - perfData.fetchStart
                        });
                    }
                }, 0);
            });
        },
        
        /**
         * Optimize images with lazy loading
         */
        setupImageLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });
                
                // Observe all images with data-src attribute
                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            } else {
                // Fallback for browsers without IntersectionObserver
                $('img[data-src]').each(function() {
                    $(this).attr('src', $(this).data('src'));
                });
            }
        },
        
        /**
         * Clean up unused resources
         */
        cleanup: function() {
            // Remove event listeners
            $(window).off('scroll.mpcc-optimized');
            $(window).off('resize.mpcc-optimized');
            
            // Clear references
            this.loadedModules = {};
        },
        
        /**
         * Initialize performance optimizations
         */
        init: function() {
            // Start monitoring
            this.monitorPerformance();
            
            // Preload resources
            MPCCUtils.performance.defer(() => {
                this.preloadResources();
            });
            
            // Setup optimizations
            this.optimizeScrollHandlers();
            this.optimizeResizeHandlers();
            
            // Setup lazy loading
            MPCCUtils.performance.defer(() => {
                this.setupImageLazyLoading();
            });
            
            // Listen for lazy load requests
            $(document).on('mpcc:load-module', (e, data) => {
                if (data && data.module) {
                    this.lazyLoadModule(data.module, data.callback);
                }
            });
            
            console.log('MPCC Performance optimizations initialized');
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        // Only initialize if the global config exists
        if (window.mpccPerformance) {
            MPCCPerformance.init();
        }
    });
    
    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (window.MPCCPerformance) {
            MPCCPerformance.cleanup();
        }
    });

})(jQuery);