<?php

declare(strict_types=1);

namespace App\Configuration\Config;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;
use Symfony\Component\Config\Definition\Builder\EnumNodeDefinition;
use Symfony\Component\Config\Definition\Builder\FloatNodeDefinition;
use Symfony\Component\Config\Definition\Builder\IntegerNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Builder\VariableNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;

abstract class AbstractConfigurationTreeBuilder implements ConfigurationInterface
{
    protected TreeBuilder $treeBuilder;
    protected Processor $processor;

    public function __construct()
    {
        $this->treeBuilder = new TreeBuilder($this->getRootNodeName());
        $this->processor = new Processor();
    }

    /**
     * Get the root node name for this configuration.
     */
    abstract protected function getRootNodeName(): string;

    /**
     * Build the configuration tree.
     */
    abstract protected function buildTree(): void;

    /**
     * Get the configuration tree.
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $this->buildTree();

        return $this->treeBuilder;
    }

    /**
     * Process configuration array and return validated configuration.
     */
    public function processConfiguration(array $configs): array
    {
        return $this->processor->processConfiguration($this, $configs);
    }

    /**
     * Get the root node.
     */
    protected function getRootNode(): NodeDefinition
    {
        return $this->treeBuilder->getRootNode();
    }

    /**
     * Get the node builder for building the tree.
     */
    protected function getNodeBuilder(): NodeBuilder
    {
        return $this->getRootNode()->children();
    }

    /**
     * Get the scalar node builder.
     */
    protected function getScalarNode(string $name): ScalarNodeDefinition
    {
        return $this->getNodeBuilder()->scalarNode($name);
    }

    /**
     * Get the boolean node builder.
     */
    protected function getBooleanNode(string $name): BooleanNodeDefinition
    {
        return $this->getNodeBuilder()->booleanNode($name);
    }

    /**
     * Get the integer node builder.
     */
    protected function getIntegerNode(string $name): IntegerNodeDefinition
    {
        return $this->getNodeBuilder()->integerNode($name);
    }

    /**
     * Get the float node builder.
     */
    protected function getFloatNode(string $name): FloatNodeDefinition
    {
        return $this->getNodeBuilder()->floatNode($name);
    }

    /**
     * Get the enum node builder.
     */
    protected function getEnumNode(string $name, array $values): EnumNodeDefinition
    {
        return $this->getNodeBuilder()->enumNode($name)->values($values);
    }

    /**
     * Get the array node builder.
     */
    protected function getArrayNodeBuilder(string $name): ArrayNodeDefinition
    {
        return $this->getNodeBuilder()->arrayNode($name);
    }

    /**
     * Get the variable node builder.
     */
    protected function getVariableNode(string $name): VariableNodeDefinition
    {
        return $this->getNodeBuilder()->variableNode($name);
    }
}
