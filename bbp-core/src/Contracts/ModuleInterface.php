<?php

namespace BBPCore\Contracts;

/**
 * Module Interface
 * 
 * Defines the contract that all BBP Core modules must implement
 */
interface ModuleInterface
{
    /**
     * Get module name
     */
    public function getName(): string;

    /**
     * Get module version
     */
    public function getVersion(): string;

    /**
     * Get module description
     */
    public function getDescription(): string;

    /**
     * Get module dependencies
     */
    public function getDependencies(): array;

    /**
     * Check if module is enabled
     */
    public function isEnabled(): bool;

    /**
     * Initialize the module
     */
    public function initialize(): void;

    /**
     * Boot the module
     */
    public function boot(): void;

    /**
     * Register module services
     */
    public function register(): void;

    /**
     * Get module configuration
     */
    public function getConfig(): array;

    /**
     * Set module configuration
     */
    public function setConfig(array $config): void;
}
