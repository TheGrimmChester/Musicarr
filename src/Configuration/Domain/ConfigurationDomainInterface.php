<?php

declare(strict_types=1);

namespace App\Configuration\Domain;

use App\Configuration\Config\AbstractConfigurationTreeBuilder;

interface ConfigurationDomainInterface
{
    /**
     * Get the domain prefix for this configuration domain.
     */
    public function getDomainPrefix(): string;

    /**
     * Get the configuration tree builder for this domain.
     */
    public function getConfigurationTreeBuilder(): AbstractConfigurationTreeBuilder;

    /**
     * Get all configuration keys for this domain.
     */
    public function getConfigurationKeys(): array;

    /**
     * Get default values for this domain.
     */
    public function getDefaultValues(): array;

    /**
     * Get validation rules for this domain.
     */
    public function getValidationRules(): array;

    /**
     * Get configuration descriptions for this domain.
     */
    public function getConfigurationDescriptions(): array;

    /**
     * Initialize default configurations for this domain.
     */
    public function initializeDefaults(): void;

    /**
     * Get all configuration values for this domain.
     */
    public function getAllConfig(): array;

    /**
     * Validate configuration data for this domain.
     */
    public function validateConfiguration(array $data): bool;

    /**
     * Process configuration data for this domain.
     */
    public function processConfiguration(array $data): array;

    /**
     * Clear all configuration values for this domain.
     */
    public function clearAllConfig(): void;
}
