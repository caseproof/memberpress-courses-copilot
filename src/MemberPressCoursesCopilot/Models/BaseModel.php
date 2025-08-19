<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Models;

/**
 * Base Model class
 * 
 * Abstract base class for all models in the plugin
 * 
 * @package MemberPressCoursesCopilot\Models
 * @since 1.0.0
 */
abstract class BaseModel
{
    /**
     * Model data
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Original data (before modifications)
     *
     * @var array<string, mixed>
     */
    protected array $originalData = [];

    /**
     * Model constructor
     *
     * @param array<string, mixed> $data Initial data
     */
    public function __construct(array $data = [])
    {
        $this->fill($data);
        $this->originalData = $this->data;
    }

    /**
     * Fill model with data
     *
     * @param array<string, mixed> $data Data to fill
     * @return static
     */
    public function fill(array $data): static
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Get a data attribute
     *
     * @param string $key The attribute key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set a data attribute
     *
     * @param string $key The attribute key
     * @param mixed $value The attribute value
     * @return static
     */
    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Check if attribute exists
     *
     * @param string $key The attribute key
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Remove an attribute
     *
     * @param string $key The attribute key
     * @return static
     */
    public function unset(string $key): static
    {
        unset($this->data[$key]);
        return $this;
    }

    /**
     * Get all data as array
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get data as JSON string
     *
     * @param int $flags JSON encoding flags
     * @return string
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->data, $flags) ?: '{}';
    }

    /**
     * Check if model has been modified
     *
     * @return bool
     */
    public function isDirty(): bool
    {
        return $this->data !== $this->originalData;
    }

    /**
     * Get changed attributes
     *
     * @return array<string, mixed>
     */
    public function getDirtyAttributes(): array
    {
        $dirty = [];
        
        foreach ($this->data as $key => $value) {
            if (!array_key_exists($key, $this->originalData) || $this->originalData[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Sync original data with current data
     *
     * @return static
     */
    public function syncOriginal(): static
    {
        $this->originalData = $this->data;
        return $this;
    }

    /**
     * Validate model data
     * 
     * This method should be implemented by child models
     * to handle their specific validation logic
     *
     * @return bool
     */
    abstract public function validate(): bool;

    /**
     * Save the model
     * 
     * This method should be implemented by child models
     * to handle their specific save logic
     *
     * @return bool
     */
    abstract public function save(): bool;

    /**
     * Delete the model
     * 
     * This method should be implemented by child models
     * to handle their specific delete logic
     *
     * @return bool
     */
    abstract public function delete(): bool;

    /**
     * Magic getter
     *
     * @param string $key The attribute key
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Magic setter
     *
     * @param string $key The attribute key
     * @param mixed $value The attribute value
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Magic isset
     *
     * @param string $key The attribute key
     * @return bool
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Magic unset
     *
     * @param string $key The attribute key
     * @return void
     */
    public function __unset(string $key): void
    {
        $this->unset($key);
    }
}