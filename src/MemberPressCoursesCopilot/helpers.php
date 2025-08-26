<?php

declare(strict_types=1);

use MemberPressCoursesCopilot\Container\Container;
use MemberPressCoursesCopilot\Plugin;

/**
 * Get service from the DI container
 *
 * This is a helper function for backward compatibility
 * It provides easy access to services from anywhere in the codebase
 *
 * @param string $service Service class name or alias
 * @return object
 * @throws Exception If service not found
 */
function mpcc_get_service(string $service): object
{
    return Plugin::instance()->getContainer()->get($service);
}

/**
 * Get the DI container instance
 *
 * This is a helper function for backward compatibility
 *
 * @return Container
 */
function mpcc_container(): Container
{
    return Plugin::instance()->getContainer();
}

/**
 * Check if a service is registered in the container
 *
 * @param string $service Service class name or alias
 * @return bool
 */
function mpcc_has_service(string $service): bool
{
    return Plugin::instance()->getContainer()->has($service);
}