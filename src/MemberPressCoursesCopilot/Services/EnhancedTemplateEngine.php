<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\CourseTemplate;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Enhanced Template Engine Service
 * 
 * Provides sophisticated template rendering, selection logic, dynamic adaptation,
 * template mixing and hybridization, custom template creation, JavaScript template management,
 * and template performance analytics.
 */
class EnhancedTemplateEngine extends BaseService
{
    private array $templateCache = [];
    private array $selectionHistory = [];
    private array $performanceMetrics = [];
    private array $jsTemplates = [];
    private array $globalData = [];
    private ?LLMService $llmService;
    
    // Template directories
    private string $templateDir;
    private string $adminTemplateDir;
    private string $componentDir;
    private string $jsTemplateDir;
    
    // Constants
    private const TEMPLATE_EXTENSION = '.php';
    private const JS_TEMPLATE_EXTENSION = '.html';
    private const CACHE_KEY_PREFIX = 'mpcc_template_';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(?LLMService $llmService = null)
    {
        parent::__construct(); // Initialize logger from BaseService
        $this->llmService = $llmService;
        $this->initializeDirectories();
        $this->initializeTemplateCache();
    }
    
    /**
     * Initialize the service (required by BaseService)
     *
     * @return void
     */
    public function init(): void
    {
        // Hook into WordPress to handle template-related actions
        add_action('wp_footer', [$this, 'renderJsTemplates']);
        add_action('admin_footer', [$this, 'renderJsTemplates']);
        add_action('wp_ajax_mpcc_get_template', [$this, 'ajaxGetTemplate']);
    }
    
    /**
     * Initialize template directories
     */
    private function initializeDirectories(): void
    {
        $pluginDir = defined('MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR') 
            ? MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR 
            : plugin_dir_path(dirname(__DIR__, 2));
            
        $this->templateDir = $pluginDir . 'templates/';
        $this->adminTemplateDir = $this->templateDir . 'admin/';
        $this->componentDir = $this->templateDir . 'components/';
        $this->jsTemplateDir = $this->templateDir . 'js-templates/';
        
        $this->logger->debug('Template directories initialized', [
            'template_dir' => $this->templateDir,
            'admin_dir' => $this->adminTemplateDir,
            'component_dir' => $this->componentDir,
            'js_template_dir' => $this->jsTemplateDir
        ]);
    }
    
    /**
     * Render a template with data
     *
     * @param string $template Template path relative to templates directory
     * @param array $data Data to pass to template
     * @param bool $return Whether to return or echo the output
     * @return string|void
     */
    public function render(string $template, array $data = [], bool $return = true)
    {
        $startTime = microtime(true);
        
        try {
            // Merge with global data
            $data = array_merge($this->globalData, $data);
            
            // Get template path
            $templatePath = $this->getTemplatePath($template);
            
            if (!file_exists($templatePath)) {
                throw new \Exception("Template not found: {$template}");
            }
            
            // Start output buffering
            ob_start();
            
            // Extract data to make available in template
            extract($data, EXTR_SKIP);
            
            // Include template
            include $templatePath;
            
            // Get output
            $output = ob_get_clean();
            
            // Track performance
            $this->trackTemplatePerformance($template, microtime(true) - $startTime);
            
            $this->logger->debug('Template rendered', [
                'template' => $template,
                'data_keys' => array_keys($data),
                'output_length' => strlen($output),
                'render_time' => microtime(true) - $startTime
            ]);
            
            if ($return) {
                return $output;
            } else {
                echo $output;
            }
            
        } catch (\Exception $e) {
            ob_end_clean();
            $this->logger->error('Template rendering failed', [
                'template' => $template,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($return) {
                return $this->renderError($e->getMessage());
            } else {
                echo $this->renderError($e->getMessage());
            }
        }
    }
    
    /**
     * Render a component template
     *
     * @param string $component Component path relative to components directory
     * @param array $data Data to pass to component
     * @return string
     */
    public function renderComponent(string $component, array $data = []): string
    {
        return $this->render('components/' . $component, $data);
    }
    
    /**
     * Include a partial template
     *
     * @param string $partial Partial path relative to partials directory
     * @param array $data Data to pass to partial
     * @return void
     */
    public function partial(string $partial, array $data = []): void
    {
        $this->render('partials/' . $partial, $data, false);
    }
    
    /**
     * Get template path
     *
     * @param string $template Template name
     * @return string Full template path
     */
    private function getTemplatePath(string $template): string
    {
        // Remove .php if provided
        $template = str_replace(self::TEMPLATE_EXTENSION, '', $template);
        
        // Build full path
        $path = $this->templateDir . $template . self::TEMPLATE_EXTENSION;
        
        // Allow theme override
        $themePath = get_stylesheet_directory() . '/memberpress-courses-copilot/' . $template . self::TEMPLATE_EXTENSION;
        if (file_exists($themePath)) {
            return $themePath;
        }
        
        return $path;
    }
    
    /**
     * Set global data available to all templates
     *
     * @param string $key Data key
     * @param mixed $value Data value
     * @return void
     */
    public function setGlobalData(string $key, $value): void
    {
        $this->globalData[$key] = $value;
    }
    
    /**
     * Set multiple global data at once
     *
     * @param array $data Data array
     * @return void
     */
    public function setGlobalDataArray(array $data): void
    {
        $this->globalData = array_merge($this->globalData, $data);
    }
    
    /**
     * Enqueue JavaScript template
     *
     * @param string $id Template ID
     * @param string $template Template path relative to js-templates directory
     * @return void
     */
    public function enqueueJsTemplate(string $id, string $template): void
    {
        try {
            $content = $this->loadJsTemplate($template);
            $this->jsTemplates[$id] = $content;
            
            $this->logger->debug('JS template enqueued', [
                'id' => $id,
                'template' => $template
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to enqueue JS template', [
                'id' => $id,
                'template' => $template,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Load JavaScript template content
     *
     * @param string $template Template name
     * @return string Template content
     * @throws \Exception
     */
    private function loadJsTemplate(string $template): string
    {
        $template = str_replace(self::JS_TEMPLATE_EXTENSION, '', $template);
        $path = $this->jsTemplateDir . $template . self::JS_TEMPLATE_EXTENSION;
        
        if (!file_exists($path)) {
            throw new \Exception("JS template not found: {$template}");
        }
        
        return file_get_contents($path);
    }
    
    /**
     * Render all enqueued JS templates
     *
     * @return void
     */
    public function renderJsTemplates(): void
    {
        if (empty($this->jsTemplates)) {
            return;
        }
        
        echo "\n<!-- MemberPress Courses Copilot JS Templates -->\n";
        foreach ($this->jsTemplates as $id => $content) {
            echo sprintf(
                '<script type="text/template" id="%s">%s</script>',
                esc_attr($id),
                $content
            ) . "\n";
        }
        echo "<!-- /MemberPress Courses Copilot JS Templates -->\n";
    }
    
    /**
     * Pass data to JavaScript
     *
     * @param string $handle Script handle
     * @param string $objectName JavaScript object name
     * @param array $data Data to localize
     * @return void
     */
    public function localizeScript(string $handle, string $objectName, array $data): void
    {
        wp_localize_script($handle, $objectName, $this->sanitizeForJs($data));
    }
    
    /**
     * Sanitize data for JavaScript
     *
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    private function sanitizeForJs(array $data): array
    {
        array_walk_recursive($data, function(&$item) {
            if (is_string($item)) {
                $item = esc_js($item);
            }
        });
        return $data;
    }
    
    /**
     * AJAX handler to get template
     *
     * @return void
     */
    public function ajaxGetTemplate(): void
    {
        NonceConstants::verifyAjax(NonceConstants::COURSES_INTEGRATION, 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $template = sanitize_text_field($_POST['template'] ?? '');
        $data = $_POST['data'] ?? [];
        
        // Sanitize data array
        array_walk_recursive($data, function(&$item) {
            if (is_string($item)) {
                $item = sanitize_text_field($item);
            }
        });
        
        try {
            $html = $this->render($template, $data);
            wp_send_json_success(['html' => $html]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Cache a rendered template
     *
     * @param string $key Cache key
     * @param string $content Template content
     * @param int $ttl Time to live in seconds
     * @return void
     */
    public function cacheTemplate(string $key, string $content, int $ttl = self::CACHE_TTL): void
    {
        set_transient(self::CACHE_KEY_PREFIX . $key, $content, $ttl);
    }
    
    /**
     * Get cached template
     *
     * @param string $key Cache key
     * @return string|false Cached content or false
     */
    public function getCachedTemplate(string $key)
    {
        return get_transient(self::CACHE_KEY_PREFIX . $key);
    }
    
    /**
     * Clear template cache
     *
     * @param string|null $key Specific key to clear, or null for all
     * @return void
     */
    public function clearCache(?string $key = null): void
    {
        if ($key) {
            delete_transient(self::CACHE_KEY_PREFIX . $key);
        } else {
            // Clear all template cache
            global $wpdb;
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    '_transient_' . self::CACHE_KEY_PREFIX . '%'
                )
            );
        }
    }
    
    /**
     * Track template performance
     *
     * @param string $template Template name
     * @param float $renderTime Render time in seconds
     * @return void
     */
    private function trackTemplatePerformance(string $template, float $renderTime): void
    {
        if (!isset($this->performanceMetrics[$template])) {
            $this->performanceMetrics[$template] = [
                'count' => 0,
                'total_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'max_time' => 0
            ];
        }
        
        $metrics = &$this->performanceMetrics[$template];
        $metrics['count']++;
        $metrics['total_time'] += $renderTime;
        $metrics['min_time'] = min($metrics['min_time'], $renderTime);
        $metrics['max_time'] = max($metrics['max_time'], $renderTime);
    }
    
    /**
     * Get template performance metrics
     *
     * @return array Performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $metrics = [];
        foreach ($this->performanceMetrics as $template => $data) {
            $metrics[$template] = [
                'count' => $data['count'],
                'avg_time' => $data['total_time'] / $data['count'],
                'min_time' => $data['min_time'],
                'max_time' => $data['max_time'],
                'total_time' => $data['total_time']
            ];
        }
        return $metrics;
    }
    
    /**
     * Render error template
     *
     * @param string $message Error message
     * @return string Error HTML
     */
    private function renderError(string $message): string
    {
        return sprintf(
            '<div class="mpcc-template-error" style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">
                <strong>Template Error:</strong> %s
            </div>',
            esc_html($message)
        );
    }
    
    /**
     * Create template directory structure
     *
     * @return bool Success status
     */
    public function createTemplateDirectories(): bool
    {
        $directories = [
            $this->templateDir,
            $this->adminTemplateDir,
            $this->adminTemplateDir . 'partials/',
            $this->componentDir,
            $this->componentDir . 'modal/',
            $this->componentDir . 'chat/',
            $this->componentDir . 'metabox/',
            $this->componentDir . 'buttons/',
            $this->jsTemplateDir
        ];
        
        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    $this->logger->error('Failed to create template directory', [
                        'directory' => $dir
                    ]);
                    return false;
                }
            }
        }
        
        return true;
    }
    
    // Maintain existing methods from original TemplateEngine...
    
    /**
     * Initialize template cache with all available templates
     */
    private function initializeTemplateCache(): void
    {
        $this->templateCache = CourseTemplate::getPredefinedTemplates();
    }

    /**
     * Intelligent template selection based on course description and context
     */
    public function selectOptimalTemplate(
        string $courseDescription,
        array $userPreferences = [],
        array $context = []
    ): array {
        // Get AI-powered recommendations
        $aiRecommendations = $this->getAIRecommendations($courseDescription, $context);
        
        // Get keyword-based recommendations
        $keywordRecommendations = CourseTemplate::recommendTemplate($courseDescription);
        
        // Combine and weight recommendations
        $combinedRecommendations = $this->combineRecommendations(
            $aiRecommendations,
            $keywordRecommendations,
            $userPreferences
        );
        
        // Track selection for future optimization
        $this->trackSelection($courseDescription, $combinedRecommendations);
        
        return $combinedRecommendations;
    }

    /**
     * Get AI recommendations for template selection
     */
    private function getAIRecommendations(string $description, array $context): array
    {
        if (!$this->llmService) {
            return [];
        }
        
        $prompt = $this->buildAIRecommendationPrompt($description, $context);
        
        try {
            $aiResponse = $this->llmService->generateResponse($prompt);
            return $this->parseAIRecommendations($aiResponse);
        } catch (\Exception $e) {
            $this->logger->error('AI template recommendation failed', [
                'error_message' => $e->getMessage(),
                'course_description' => $description,
                'context' => $context,
                'method' => 'getAIRecommendations'
            ]);
            return [];
        }
    }

    /**
     * Build AI prompt for template recommendation
     */
    private function buildAIRecommendationPrompt(string $description, array $context): string
    {
        $templateTypes = CourseTemplate::getTemplateTypes();
        $templateDescriptions = array_map(function($type) {
            return $type['name'] . ': ' . $type['description'];
        }, $templateTypes);
        
        return sprintf(
            "Based on this course description: '%s'\n\n" .
            "Available template types:\n%s\n\n" .
            "Context: %s\n\n" .
            "Recommend the top 3 most suitable template types and explain why each is appropriate. " .
            "Return as JSON array with 'type', 'score' (0-100), and 'reason' for each recommendation.",
            $description,
            implode("\n", $templateDescriptions),
            json_encode($context)
        );
    }

    /**
     * Parse AI recommendations response
     */
    private function parseAIRecommendations($aiResponse): array
    {
        // Implementation depends on AI response format
        // This is a placeholder implementation
        return [];
    }

    /**
     * Combine recommendations from different sources
     */
    private function combineRecommendations(
        array $aiRecommendations,
        array $keywordRecommendations,
        array $userPreferences
    ): array {
        // Weighted combination logic
        $combined = [];
        
        // AI recommendations get 50% weight
        foreach ($aiRecommendations as $rec) {
            $type = $rec['type'];
            $combined[$type] = ($rec['score'] ?? 0) * 0.5;
        }
        
        // Keyword recommendations get 30% weight
        foreach ($keywordRecommendations as $rec) {
            $type = $rec['type'];
            $score = ($rec['score'] ?? 0) * 0.3;
            $combined[$type] = ($combined[$type] ?? 0) + $score;
        }
        
        // User preferences get 20% weight
        foreach ($userPreferences as $pref) {
            if (isset($combined[$pref])) {
                $combined[$pref] += 20;
            }
        }
        
        // Sort by score
        arsort($combined);
        
        // Format results
        $results = [];
        foreach ($combined as $type => $score) {
            $results[] = [
                'type' => $type,
                'score' => min(100, $score),
                'template' => $this->templateCache[$type] ?? null
            ];
        }
        
        return array_slice($results, 0, 3); // Top 3
    }

    /**
     * Track template selection for analytics
     */
    private function trackSelection(string $description, array $recommendations): void
    {
        $this->selectionHistory[] = [
            'timestamp' => time(),
            'description' => $description,
            'recommendations' => $recommendations,
            'selected' => $recommendations[0]['type'] ?? null
        ];
        
        // Keep only last 100 selections
        if (count($this->selectionHistory) > 100) {
            array_shift($this->selectionHistory);
        }
    }
}