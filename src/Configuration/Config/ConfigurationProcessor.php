<?php

declare(strict_types=1);

namespace App\Configuration\Config;

use Exception;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationProcessor
{
    private Processor $processor;
    private array $treeBuilders = [];

    public function __construct()
    {
        $this->processor = new Processor();
    }

    /**
     * Add a configuration tree builder for a domain.
     */
    public function addTreeBuilder(string $domain, AbstractConfigurationTreeBuilder $treeBuilder): void
    {
        $this->treeBuilders[$domain] = $treeBuilder;
    }

    /**
     * Process configuration for a specific domain.
     */
    public function processDomainConfiguration(string $domain, array $configs): array
    {
        if (!isset($this->treeBuilders[$domain])) {
            throw new InvalidArgumentException("No tree builder found for domain: {$domain}");
        }

        try {
            return $this->treeBuilders[$domain]->processConfiguration($configs);
        } catch (InvalidConfigurationException $e) {
            throw new InvalidArgumentException("Invalid configuration for domain {$domain}: " . $e->getMessage());
        }
    }

    /**
     * Validate configuration for a specific domain.
     */
    public function validateDomainConfiguration(string $domain, array $configs): bool
    {
        try {
            $this->processDomainConfiguration($domain, $configs);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get default values for a domain.
     */
    public function getDomainDefaults(string $domain): array
    {
        if (!isset($this->treeBuilders[$domain])) {
            throw new InvalidArgumentException("No tree builder found for domain: {$domain}");
        }

        $treeBuilder = $this->treeBuilders[$domain]->getConfigTreeBuilder();
        $rootNode = $treeBuilder->buildTree();

        // Extract default values from the tree
        return $this->extractDefaults($rootNode);
    }

    /**
     * Get validation rules for a domain.
     */
    public function getDomainValidationRules(string $domain): array
    {
        if (!isset($this->treeBuilders[$domain])) {
            throw new InvalidArgumentException("No tree builder found for domain: {$domain}");
        }

        $treeBuilder = $this->treeBuilders[$domain]->getConfigTreeBuilder();
        $rootNode = $treeBuilder->buildTree();

        // Extract validation rules from the tree
        return $this->extractValidationRules($rootNode);
    }

    /**
     * Get configuration info for a domain.
     */
    public function getDomainConfigurationInfo(string $domain): array
    {
        if (!isset($this->treeBuilders[$domain])) {
            throw new InvalidArgumentException("No tree builder found for domain: {$domain}");
        }

        $treeBuilder = $this->treeBuilders[$domain]->getConfigTreeBuilder();
        $rootNode = $treeBuilder->buildTree();

        // Extract configuration info from the tree
        return $this->extractConfigurationInfo($rootNode);
    }

    /**
     * Extract default values from a node.
     */
    private function extractDefaults($node): array
    {
        $defaults = [];

        // For the root node, we don't want to include a 'default' key
        // Only extract defaults from children
        if (method_exists($node, 'getChildren')) {
            foreach ($node->getChildren() as $name => $child) {
                $defaults[$name] = $this->extractDefaults($child);
            }
        }

        return $defaults;
    }

    /**
     * Extract validation rules from a node.
     */
    private function extractValidationRules($node): array
    {
        $rules = [];

        if (method_exists($node, 'getMin')) {
            $rules['min'] = $node->getMin();
        }

        if (method_exists($node, 'getMax')) {
            $rules['max'] = $node->getMax();
        }

        if (method_exists($node, 'getValues')) {
            $rules['values'] = $node->getValues();
        }

        if (method_exists($node, 'getChildren')) {
            foreach ($node->getChildren() as $name => $child) {
                $rules[$name] = $this->extractValidationRules($child);
            }
        }

        return $rules;
    }

    /**
     * Extract configuration info from a node.
     */
    private function extractConfigurationInfo($node): array
    {
        $info = [];

        if (method_exists($node, 'getInfo')) {
            $info['description'] = $node->getInfo();
        }

        if (method_exists($node, 'getChildren')) {
            foreach ($node->getChildren() as $name => $child) {
                $info[$name] = $this->extractConfigurationInfo($child);
            }
        }

        return $info;
    }

    /**
     * Get all available domains.
     */
    public function getAvailableDomains(): array
    {
        return array_keys($this->treeBuilders);
    }

    /**
     * Check if a domain is supported.
     */
    public function isDomainSupported(string $domain): bool
    {
        return isset($this->treeBuilders[$domain]);
    }
}
