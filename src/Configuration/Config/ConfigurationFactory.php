<?php

declare(strict_types=1);

namespace App\Configuration\Config;

use App\Configuration\Domain\ConfigurationDomainRegistry;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigurationFactory
{
    private ConfigurationDomainRegistry $domainRegistry;
    private ConfigurationProcessor $processor;

    public function __construct(ConfigurationDomainRegistry $domainRegistry, ConfigurationProcessor $processor)
    {
        $this->domainRegistry = $domainRegistry;
        $this->processor = $processor;
    }

    /**
     * Create a configuration instance for a specific domain.
     */
    public function createConfiguration(string $domain, array $data = []): array
    {
        if (!$this->domainRegistry->isDomainSupported($domain)) {
            throw new InvalidArgumentException("Domain '{$domain}' is not supported");
        }

        // Ensure the registry is initialized
        if (!$this->domainRegistry->isInitialized()) {
            $this->domainRegistry->initialize();
        }

        try {
            return $this->processor->processDomainConfiguration($domain, [$data]);
        } catch (InvalidConfigurationException $e) {
            throw new InvalidArgumentException("Invalid configuration for domain '{$domain}': " . $e->getMessage());
        }
    }

    /**
     * Validate configuration data for a specific domain.
     */
    public function validateConfiguration(string $domain, array $data): bool
    {
        // Ensure the registry is initialized
        if (!$this->domainRegistry->isInitialized()) {
            $this->domainRegistry->initialize();
        }

        return $this->processor->validateDomainConfiguration($domain, [$data]);
    }

    /**
     * Get default configuration for a specific domain.
     */
    public function getDefaultConfiguration(string $domain): array
    {
        // Ensure the registry is initialized
        if (!$this->domainRegistry->isInitialized()) {
            $this->domainRegistry->initialize();
        }

        return $this->processor->getDomainDefaults($domain);
    }

    /**
     * Get validation rules for a specific domain.
     */
    public function getValidationRules(string $domain): array
    {
        // Ensure the registry is initialized
        if (!$this->domainRegistry->isInitialized()) {
            $this->domainRegistry->initialize();
        }

        return $this->processor->getDomainValidationRules($domain);
    }

    /**
     * Get configuration info for a specific domain.
     */
    public function getConfigurationInfo(string $domain): array
    {
        // Ensure the registry is initialized
        if (!$this->domainRegistry->isInitialized()) {
            $this->domainRegistry->initialize();
        }

        return $this->processor->getDomainConfigurationInfo($domain);
    }

    /**
     * Merge configuration data with defaults.
     */
    public function mergeWithDefaults(string $domain, array $data): array
    {
        $defaults = $this->getDefaultConfiguration($domain);

        return array_merge($defaults, $data);
    }

    /**
     * Create a complete configuration for all domains.
     */
    public function createCompleteConfiguration(array $data = []): array
    {
        $completeConfig = [];
        $availableDomains = $this->processor->getAvailableDomains();

        foreach ($availableDomains as $domain) {
            $domainData = $data[$domain] ?? [];
            $completeConfig[$domain] = $this->createConfiguration($domain, $domainData);
        }

        return $completeConfig;
    }

    /**
     * Validate complete configuration for all domains.
     */
    public function validateCompleteConfiguration(array $data): array
    {
        $errors = [];
        $availableDomains = $this->processor->getAvailableDomains();

        foreach ($availableDomains as $domain) {
            $domainData = $data[$domain] ?? [];
            if (!$this->validateConfiguration($domain, $domainData)) {
                $errors[$domain] = "Invalid configuration for domain '{$domain}'";
            }
        }

        return $errors;
    }

    /**
     * Get all available domains.
     */
    public function getAvailableDomains(): array
    {
        return $this->processor->getAvailableDomains();
    }

    /**
     * Check if a domain is supported.
     */
    public function isDomainSupported(string $domain): bool
    {
        return $this->processor->isDomainSupported($domain);
    }

    /**
     * Get configuration schema for a domain.
     */
    public function getConfigurationSchema(string $domain): array
    {
        if (!$this->isDomainSupported($domain)) {
            throw new InvalidArgumentException("Domain '{$domain}' is not supported");
        }

        return [
            'defaults' => $this->getDefaultConfiguration($domain),
            'validation_rules' => $this->getValidationRules($domain),
            'info' => $this->getConfigurationInfo($domain),
        ];
    }

    /**
     * Get configuration schema for all domains.
     */
    public function getAllConfigurationSchemas(): array
    {
        $schemas = [];
        $availableDomains = $this->getAvailableDomains();

        foreach ($availableDomains as $domain) {
            $schemas[$domain] = $this->getConfigurationSchema($domain);
        }

        return $schemas;
    }
}
