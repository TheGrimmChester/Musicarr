<?php

declare(strict_types=1);

namespace App\Configuration;

interface ConfigurationServiceInterface
{
    /**
     * Get a configuration value.
     */
    public function get(string $key, mixed $defaultValue = null): mixed;

    /**
     * Set a configuration value.
     */
    public function set(string $key, mixed $value, ?string $description = ''): void;

    /**
     * Delete a configuration.
     */
    public function delete(string $key): bool;

    /**
     * Get all configuration values for a specific prefix.
     */
    public function getConfigByPrefix(string $prefix): array;

    /**
     * Get all configuration values for a specific prefix (alias for getConfigByPrefix).
     */
    public function getByPrefix(string $prefix): array;

    /**
     * Get downloader configuration.
     */
    public function getDownloaderConfig(): array;

    /**
     * Get association configuration.
     */
    public function getAssociationConfig(): array;

    /**
     * Get metadata configuration.
     */
    public function getMetadataConfig(): array;

    /**
     * Get album import configuration.
     */
    public function getAlbumImportConfig(): array;

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool;

    /**
     * Get all configuration keys.
     */
    public function getAllKeys(): array;

    /**
     * Get all configuration values.
     */
    public function getAll(): array;

    /**
     * Clear all configuration (useful for testing).
     */
    public function clearAll(): void;
}
