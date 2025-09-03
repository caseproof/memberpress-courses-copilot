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
    EnhancedTemplateEngine,
    ConversationFlowHandler,
    CourseAjaxService,
    AssetManager,
    CourseIntegrationService,
    CourseUIService,
    SessionFeaturesService,
    EditorAIIntegrationService,
    MpccQuizAIService
};
use MemberPressCoursesCopilot\Interfaces\{
    IDatabaseService,
    ILLMService,
    IConversationManager,
    ICourseGenerator,
    IQuizAIService
};
use MemberPressCoursesCopilot\Controllers\{
    SimpleAjaxController,
    MpccQuizAjaxController
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
 * @since   1.0.0
 */
class ServiceProvider
{
    /**
     * Register all services with the container
     *
     * @param  Container $container
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

        // Register interface bindings
        self::registerInterfaceBindings($container);
    }

    /**
     * Register utility services
     *
     * @param  Container $container
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
     * @param  Container $container
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
            $logger     = $container->get(Logger::class);
            return new ContentGenerationService($llmService, $logger);
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

        // Enhanced Template Engine (singleton)
        $container->register(EnhancedTemplateEngine::class, function (Container $container) {
            $llmService = $container->get(LLMService::class);
            return new EnhancedTemplateEngine($llmService);
        }, true);

        // Conversation Flow Handler (singleton)
        $container->register(ConversationFlowHandler::class, function (Container $container) {
            $llmService          = $container->get(LLMService::class);
            $conversationManager = $container->get(ConversationManager::class);
            $courseGenerator     = $container->get(CourseGeneratorService::class);
            return new ConversationFlowHandler($llmService, $conversationManager, $courseGenerator);
        }, true);

        // Session Features Service (singleton)
        $container->register(SessionFeaturesService::class, SessionFeaturesService::class, true);

        // Course Integration Service (singleton)
        $container->register(CourseIntegrationService::class, CourseIntegrationService::class, true);

        // Course UI Service (singleton)
        $container->register(CourseUIService::class, CourseUIService::class, true);

        // Course Ajax Service (singleton)
        $container->register(CourseAjaxService::class, function (Container $container) {
            $llmService          = $container->get(LLMService::class);
            $conversationManager = $container->get(ConversationManager::class);
            $courseGenerator     = $container->get(CourseGeneratorService::class);
            $lessonDraftService  = $container->get(LessonDraftService::class);
            return new CourseAjaxService($llmService, $conversationManager, $courseGenerator, $lessonDraftService);
        }, true);

        // Editor AI Integration Service (singleton) - Unified service for both courses and lessons
        $container->register(EditorAIIntegrationService::class, EditorAIIntegrationService::class, true);

        // Asset Manager (singleton)
        $container->register(AssetManager::class, AssetManager::class, true);

        // Quiz AI Service (singleton)
        $container->register(MpccQuizAIService::class, function (Container $container) {
            $llmService = $container->get(LLMService::class);
            return new MpccQuizAIService($llmService);
        }, true);
    }

    /**
     * Register admin services
     *
     * @param  Container $container
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
     * @param  Container $container
     * @return void
     */
    private static function registerControllers(Container $container): void
    {
        // Simple Ajax Controller (singleton)
        $container->register(SimpleAjaxController::class, SimpleAjaxController::class, true);

        // Quiz Ajax Controller (singleton)
        $container->register(MpccQuizAjaxController::class, function (Container $container) {
            $quizAIService = $container->get(MpccQuizAIService::class);
            return new MpccQuizAjaxController($quizAIService);
        }, true);
    }

    /**
     * Register service aliases
     *
     * @param  Container $container
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
        $container->alias('template', EnhancedTemplateEngine::class);
    }

    /**
     * Register interface bindings
     *
     * @param  Container $container
     * @return void
     */
    private static function registerInterfaceBindings(Container $container): void
    {
        // Bind interfaces to their implementations
        $container->bind(IDatabaseService::class, DatabaseService::class);
        $container->bind(ILLMService::class, LLMService::class);
        $container->bind(IConversationManager::class, ConversationManager::class);
        $container->bind(ICourseGenerator::class, CourseGeneratorService::class);
        $container->bind(IQuizAIService::class, MpccQuizAIService::class);
    }
}
