<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Container;

use MemberPressCoursesCopilot\Services\{
    DatabaseService,
    LLMService,
    ConversationManager,
    ContentGenerationService,
    CourseGeneratorService,
    LessonDraftService,
    TemplateEngine,
    EnhancedTemplateEngine,
    ConversationFlowHandler,
    CourseAjaxService,
    AssetManager,
    CourseIntegrationService,
    CourseUIService,
    SessionFeaturesService,
    NewCourseIntegration,
    LessonAIIntegration
};
use MemberPressCoursesCopilot\Controllers\{
    SimpleAjaxController,
    AjaxController,
    RestApiController
};
use MemberPressCoursesCopilot\Admin\{
    AdminMenu,
    SettingsPage,
    CourseEditorPage
};
use MemberPressCoursesCopilot\Utilities\{
    Logger,
    Helper
};

/**
 * Service Provider
 * 
 * Registers all services with the DI container
 * Maintains single responsibility - only handles service registration
 * 
 * @package MemberPressCoursesCopilot\Container
 * @since 1.0.0
 */
class ServiceProvider
{
    /**
     * Register all services with the container
     *
     * @param Container $container
     * @return void
     */
    public static function register(Container $container): void
    {
        // Register utilities as singletons
        self::registerUtilities($container);
        
        // Register core services as singletons
        self::registerCoreServices($container);
        
        // Register admin services
        self::registerAdminServices($container);
        
        // Register controllers
        self::registerControllers($container);
        
        // Register aliases for convenience
        self::registerAliases($container);
    }

    /**
     * Register utility services
     *
     * @param Container $container
     * @return void
     */
    private static function registerUtilities(Container $container): void
    {
        // Logger (singleton)
        $container->register(Logger::class, function () {
            return Logger::getInstance();
        }, true);
        
        // Helper (singleton)
        $container->register(Helper::class, Helper::class, true);
    }

    /**
     * Register core services
     *
     * @param Container $container
     * @return void
     */
    private static function registerCoreServices(Container $container): void
    {
        // Database Service (singleton)
        $container->register(DatabaseService::class, DatabaseService::class, true);
        
        // LLM Service (singleton)
        $container->register(LLMService::class, function (Container $container) {
            return new LLMService();
        }, true);
        
        // Conversation Manager (singleton)
        $container->register(ConversationManager::class, function (Container $container) {
            $databaseService = $container->get(DatabaseService::class);
            return new ConversationManager($databaseService);
        }, true);
        
        // Content Generation Service (singleton)
        $container->register(ContentGenerationService::class, function (Container $container) {
            $llmService = $container->get(LLMService::class);
            $templateEngine = $container->get(TemplateEngine::class);
            return new ContentGenerationService($llmService, $templateEngine);
        }, true);
        
        // Course Generator Service (singleton)
        $container->register(CourseGeneratorService::class, function (Container $container) {
            $logger = $container->get(Logger::class);
            return new CourseGeneratorService($logger);
        }, true);
        
        // Lesson Draft Service (singleton)
        $container->register(LessonDraftService::class, function (Container $container) {
            $databaseService = $container->get(DatabaseService::class);
            return new LessonDraftService($databaseService);
        }, true);
        
        // Template Engine (singleton)
        $container->register(TemplateEngine::class, TemplateEngine::class, true);
        
        // Enhanced Template Engine (singleton)
        $container->register(EnhancedTemplateEngine::class, function (Container $container) {
            $templateEngine = $container->get(TemplateEngine::class);
            return new EnhancedTemplateEngine();
        }, true);
        
        // Conversation Flow Handler (singleton)
        $container->register(ConversationFlowHandler::class, function (Container $container) {
            $llmService = $container->get(LLMService::class);
            $conversationManager = $container->get(ConversationManager::class);
            $courseGenerator = $container->get(CourseGeneratorService::class);
            return new ConversationFlowHandler($llmService, $conversationManager, $courseGenerator);
        }, true);
        
        // Session Features Service (singleton)
        $container->register(SessionFeaturesService::class, SessionFeaturesService::class, true);
        
        // Course Integration Service (singleton)
        $container->register(CourseIntegrationService::class, CourseIntegrationService::class, true);
        
        // Course UI Service (singleton)
        $container->register(CourseUIService::class, CourseUIService::class, true);
        
        // Course Ajax Service (singleton)
        $container->register(CourseAjaxService::class, CourseAjaxService::class, true);
        
        // New Course Integration Service (singleton)
        $container->register(NewCourseIntegration::class, NewCourseIntegration::class, true);
        
        // Lesson AI Integration Service (singleton)
        $container->register(LessonAIIntegration::class, LessonAIIntegration::class, true);
        
        // Asset Manager (singleton)
        $container->register(AssetManager::class, AssetManager::class, true);
    }

    /**
     * Register admin services
     *
     * @param Container $container
     * @return void
     */
    private static function registerAdminServices(Container $container): void
    {
        // Settings Page (singleton)
        $container->register(SettingsPage::class, SettingsPage::class, true);
        
        // Course Editor Page (singleton)
        $container->register(CourseEditorPage::class, CourseEditorPage::class, true);
        
        // Admin Menu (singleton)
        $container->register(AdminMenu::class, function (Container $container) {
            $settingsPage = $container->get(SettingsPage::class);
            return new AdminMenu($settingsPage);
        }, true);
    }

    /**
     * Register controllers
     *
     * @param Container $container
     * @return void
     */
    private static function registerControllers(Container $container): void
    {
        // Simple Ajax Controller (singleton)
        $container->register(SimpleAjaxController::class, SimpleAjaxController::class, true);
        
        // Ajax Controller (singleton)
        $container->register(AjaxController::class, AjaxController::class, true);
        
        // REST API Controller (singleton)
        $container->register(RestApiController::class, RestApiController::class, true);
    }

    /**
     * Register service aliases
     *
     * @param Container $container
     * @return void
     */
    private static function registerAliases(Container $container): void
    {
        // Shorter aliases for commonly used services
        $container->alias('logger', Logger::class);
        $container->alias('database', DatabaseService::class);
        $container->alias('llm', LLMService::class);
        $container->alias('conversation', ConversationManager::class);
        $container->alias('content', ContentGenerationService::class);
        $container->alias('course_generator', CourseGeneratorService::class);
        $container->alias('template', TemplateEngine::class);
    }
}