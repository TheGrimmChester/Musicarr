<?php

declare(strict_types=1);

namespace App\Configuration\Config;

class AlbumImportConfigurationTreeBuilder extends AbstractConfigurationTreeBuilder
{
    protected function getRootNodeName(): string
    {
        return 'album_import';
    }

    protected function buildTree(): void
    {
        $rootNode = $this->getNodeBuilder();

        $rootNode
            ->arrayNode('primary_types')
            ->info('Primary album types to import')
            ->scalarPrototype()->end()
            ->defaultValue(['Album', 'EP', 'Single'])
            ->validate()
            ->ifTrue(function ($v) {
                return !\is_array($v) || empty($v);
            })
            ->thenInvalid('Primary types must be a non-empty array')
            ->end()
            ->end()

            ->arrayNode('secondary_types')
            ->info('Secondary album types to import')
            ->scalarPrototype()->end()
            ->defaultValue(['Studio', 'Remix', 'Live', 'Compilation'])
            ->end()

            ->arrayNode('release_statuses')
            ->info('Release statuses to import')
            ->scalarPrototype()->end()
            ->defaultValue(['official', 'promotion', 'bootleg'])
            ->end()

            ->arrayNode('allowed_album_types')
            ->info('All allowed album types')
            ->scalarPrototype()->end()
            ->defaultValue(['Album', 'EP', 'Single', 'Studio', 'Remix', 'Live', 'Compilation', 'Soundtrack'])
            ->end()
        ;
    }
}
