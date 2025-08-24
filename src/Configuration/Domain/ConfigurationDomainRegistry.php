<?php

declare(strict_types=1);

namespace App\Configuration\Domain;

use App\Configuration\Config\ConfigurationProcessor;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class ConfigurationDomainRegistry
{
    private iterable $domains;
    private ConfigurationProcessor $configurationProcessor;
    private array $initializedDomains = [];
    private bool $initialized = false;

    public function __construct(
        #[TaggedIterator('app.configuration.domain')]
        iterable $domains,
        ConfigurationProcessor $configurationProcessor
    ) {
        $this->domains = $domains;
        $this->configurationProcessor = $configurationProcessor;
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Initialize all domains
        foreach ($this->domains as $domain) {
            $prefix = $domain->getDomainPrefix();
            $treeBuilder = $domain->getConfigurationTreeBuilder();

            $this->configurationProcessor->addTreeBuilder(
                $prefix,
                $treeBuilder
            );
            $this->initializedDomains[$prefix] = $domain;
        }

        $this->initialized = true;
    }

    public function initializeAllDomains(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        foreach ($this->initializedDomains as $domain) {
            $domain->initializeDefaults();
        }
    }

    public function getDomain(string $prefix): ?ConfigurationDomainInterface
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->initializedDomains[$prefix] ?? null;
    }

    public function getAllDomains(): array
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return array_values($this->initializedDomains);
    }

    public function getAllValidationRules(): array
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $rules = [];
        foreach ($this->initializedDomains as $prefix => $domain) {
            $rules[$prefix] = $this->configurationProcessor->getDomainValidationRules($prefix);
        }

        return $rules;
    }

    public function getAllConfigurationKeys(): array
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $keys = [];
        foreach ($this->initializedDomains as $prefix => $domain) {
            $keys[$prefix] = $domain->getConfigurationKeys();
        }

        return $keys;
    }

    public function getAllDefaultValues(): array
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $defaults = [];
        foreach ($this->initializedDomains as $prefix => $domain) {
            $defaults[$prefix] = $domain->getDefaultValues();
        }

        return $defaults;
    }

    public function getAllConfigurationDescriptions(): array
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $rules = [];
        foreach ($this->initializedDomains as $prefix => $domain) {
            $rules[$prefix] = $domain->getConfigurationDescriptions();
        }

        return $rules;
    }

    /**
     * Process configuration for a specific domain.
     */
    public function processDomainConfiguration(string $domain, array $configs): array
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->configurationProcessor->processDomainConfiguration($domain, $configs);
    }

    /**
     * Validate configuration for a specific domain.
     */
    public function validateDomainConfiguration(string $domain, array $configs): bool
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->configurationProcessor->validateDomainConfiguration($domain, $configs);
    }

    /**
     * Get the configuration processor.
     */
    public function getConfigurationProcessor(): ConfigurationProcessor
    {
        return $this->configurationProcessor;
    }

    /**
     * Check if the registry is initialized.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Check if a domain is supported.
     */
    public function isDomainSupported(string $domain): bool
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return isset($this->initializedDomains[$domain]);
    }
}
