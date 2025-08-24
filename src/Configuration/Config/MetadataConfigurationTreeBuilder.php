<?php

declare(strict_types=1);

namespace App\Configuration\Config;

class MetadataConfigurationTreeBuilder extends AbstractConfigurationTreeBuilder
{
    protected function getRootNodeName(): string
    {
        return 'metadata';
    }

    protected function buildTree(): void
    {
        $rootNode = $this->getNodeBuilder();

        $rootNode
            ->scalarNode('base_dir')
            ->info('Base directory for metadata storage')
            ->defaultValue('/app/public/metadata')
            ->validate()
            ->ifTrue(function ($v) {
                return !\is_string($v) || empty($v);
            })
            ->thenInvalid('Base directory must be a non-empty string')
            ->end()
            ->end()

            ->booleanNode('save_in_library')
            ->info('Save metadata inside library folders')
            ->defaultFalse()
            ->end()

            ->scalarNode('image_path')
            ->info('Path for storing images')
            ->defaultValue('images')
            ->end()

            ->scalarNode('library_image_path')
            ->info('Path for storing library images')
            ->defaultValue('library')
            ->end()
        ;
    }
}
