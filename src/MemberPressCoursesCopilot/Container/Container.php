<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Container;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionParameter;

/**
 * Simple Dependency Injection Container
 * 
 * Provides a lightweight DI container with singleton support
 * Following KISS principle - keeping it simple and focused
 * 
 * @package MemberPressCoursesCopilot\Container
 * @since 1.0.0
 */
class Container
{
    /**
     * Container instance (singleton)
     *
     * @var Container|null
     */
    private static ?Container $instance = null;

    /**
     * Registered services
     *
     * @var array<string, Closure>
     */
    private array $services = [];

    /**
     * Singleton instances
     *
     * @var array<string, object>
     */
    private array $singletons = [];

    /**
     * Service aliases
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * Private constructor to enforce singleton
     */
    private function __construct()
    {
        // Private constructor
    }

    /**
     * Get container instance
     *
     * @return Container
     */
    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a service
     *
     * @param string $id Service identifier
     * @param Closure|string $concrete Service factory or class name
     * @param bool $singleton Whether to create as singleton
     * @return void
     */
    public function register(string $id, Closure|string $concrete, bool $singleton = true): void
    {
        if (is_string($concrete)) {
            // Convert class name to factory closure
            $concrete = function () use ($concrete) {
                return $this->build($concrete);
            };
        }

        $this->services[$id] = $concrete;
        
        // Mark as singleton if specified
        if ($singleton && !isset($this->singletons[$id])) {
            $this->singletons[$id] = null;
        }
    }

    /**
     * Register an alias for a service
     *
     * @param string $alias The alias name
     * @param string $service The service ID to alias
     * @return void
     */
    public function alias(string $alias, string $service): void
    {
        $this->aliases[$alias] = $service;
    }

    /**
     * Get a service from the container
     *
     * @param string $id Service identifier
     * @return object
     * @throws Exception If service not found
     */
    public function get(string $id): object
    {
        // Check if it's an alias
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        // Check if it's a singleton and already instantiated
        if (isset($this->singletons[$id]) && $this->singletons[$id] !== null) {
            return $this->singletons[$id];
        }

        // Check if service is registered
        if (!isset($this->services[$id])) {
            throw new Exception("Service '{$id}' not found in container");
        }

        // Create instance
        $instance = $this->services[$id]($this);

        // Store singleton if applicable
        if (array_key_exists($id, $this->singletons)) {
            $this->singletons[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Check if a service is registered
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->aliases[$id]);
    }

    /**
     * Build a class with automatic dependency injection
     *
     * @param string $className The class to build
     * @return object
     * @throws Exception
     */
    public function build(string $className): object
    {
        $reflector = new ReflectionClass($className);

        if (!$reflector->isInstantiable()) {
            throw new Exception("Class '{$className}' is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $className();
        }

        $dependencies = array_map(
            fn(ReflectionParameter $param) => $this->resolveDependency($param),
            $constructor->getParameters()
        );

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve a dependency
     *
     * @param ReflectionParameter $param
     * @return mixed
     * @throws Exception
     */
    private function resolveDependency(ReflectionParameter $param): mixed
    {
        $type = $param->getType();
        
        if ($type === null || $type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            
            throw new Exception("Cannot resolve parameter '{$param->getName()}'");
        }

        $typeName = $type->getName();
        
        // Try to resolve from container
        if ($this->has($typeName)) {
            return $this->get($typeName);
        }

        // Try to auto-resolve if it's a class
        if (class_exists($typeName)) {
            return $this->build($typeName);
        }

        // Check if parameter has default value
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new Exception("Cannot resolve dependency '{$typeName}'");
    }

    /**
     * Bind an interface to a concrete implementation
     *
     * @param string $interface Interface name
     * @param string $concrete Concrete class name
     * @return void
     */
    public function bind(string $interface, string $concrete): void
    {
        $this->alias($interface, $concrete);
    }
    
    /**
     * Reset the container (mainly for testing)
     *
     * @return void
     */
    public function reset(): void
    {
        $this->services = [];
        $this->singletons = [];
        $this->aliases = [];
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
        // Prevent cloning
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup(): void
    {
        throw new Exception('Cannot unserialize singleton');
    }
}