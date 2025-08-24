<?php

declare(strict_types=1);

namespace App\Configuration\Domain;

use App\Configuration\Config\AbstractConfigurationTreeBuilder;
use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

abstract class AbstractConfigurationDomain implements ConfigurationDomainInterface
{
    protected AbstractConfigurationTreeBuilder $treeBuilder;
    protected EntityManagerInterface $entityManager;

    public function __construct(AbstractConfigurationTreeBuilder $treeBuilder)
    {
        $this->treeBuilder = $treeBuilder;
    }

    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    public function getConfigurationTreeBuilder(): AbstractConfigurationTreeBuilder
    {
        return $this->treeBuilder;
    }

    public function validateConfiguration(array $data): bool
    {
        try {
            $result = $this->treeBuilder->processConfiguration([$data]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function processConfiguration(array $data): array
    {
        return $this->treeBuilder->processConfiguration([$data]);
    }

    /**
     * Get default values for a domain.
     */
    public function getDefaultValues(): array
    {
        // Instead of trying to extract from the tree, return hardcoded defaults
        // that match what's defined in the tree builder
        return $this->getHardcodedDefaults();
    }

    /**
     * Get hardcoded default values for this domain
     * This is a fallback when tree extraction doesn't work.
     */
    protected function getHardcodedDefaults(): array
    {
        // This should be overridden by subclasses
        return [];
    }

    public function getValidationRules(): array
    {
        $treeBuilder = $this->treeBuilder->getConfigTreeBuilder();
        $rootNode = $treeBuilder->buildTree();

        // Extract validation rules from the tree
        return $this->extractValidationRules($rootNode);
    }

    public function getConfigurationDescriptions(): array
    {
        $treeBuilder = $this->treeBuilder->getConfigTreeBuilder();
        $rootNode = $treeBuilder->buildTree();

        // Extract configuration info from the tree
        return $this->extractConfigurationInfo($rootNode);
    }

    /**
     * Clear all configuration values for this domain.
     */
    public function clearAllConfig(): void
    {
        $prefix = $this->getDomainPrefix();
        $configurations = $this->entityManager->getRepository(Configuration::class)
            ->findByKeyPrefix($prefix);

        foreach ($configurations as $config) {
            $this->entityManager->remove($config);
        }

        $this->entityManager->flush();
    }

    /**
     * Extract default values from a node.
     */
    private function extractDefaults($node): array
    {
        $defaults = [];

        // For nodes with children, extract defaults from children
        if (method_exists($node, 'getChildren')) {
            foreach ($node->getChildren() as $name => $child) {
                $childDefaults = $this->extractDefaults($child);
                $defaults[$name] = $childDefaults;
            }
        } else {
            // Check if this node has a default value (scalar nodes)
            if (method_exists($node, 'getDefaultValue')) {
                // For scalar nodes, we need to return an array
                // Since we don't have the node name here, we'll return an empty array
                // The caller should handle scalar nodes differently
                return [];
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
     * Set configuration value in the database.
     */
    abstract protected function set(string $key, mixed $value, ?string $description = null): void;

    /*
     * Get configuration value from the database
     */
}
