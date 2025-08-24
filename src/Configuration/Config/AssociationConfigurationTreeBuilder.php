<?php

declare(strict_types=1);

namespace App\Configuration\Config;

class AssociationConfigurationTreeBuilder extends AbstractConfigurationTreeBuilder
{
    protected function getRootNodeName(): string
    {
        return 'association';
    }

    protected function buildTree(): void
    {
        $rootNode = $this->getNodeBuilder();

        $rootNode
            ->booleanNode('auto_association')
            ->info('Enable or disable automatic associations')
            ->defaultTrue()
            ->end()

            ->floatNode('min_score')
            ->info('Minimum score threshold for associations')
            ->min(0.0)
            ->max(100.0)
            ->defaultValue(85.0)
            ->end()

            ->booleanNode('exact_artist_match')
            ->info('Require exact artist name match')
            ->defaultFalse()
            ->end()

            ->booleanNode('exact_album_match')
            ->info('Require exact album name match')
            ->defaultFalse()
            ->end()

            ->booleanNode('exact_duration_match')
            ->info('Require exact duration match')
            ->defaultFalse()
            ->end()

            ->booleanNode('exact_year_match')
            ->info('Require exact year match')
            ->defaultFalse()
            ->end()

            ->booleanNode('exact_title_match')
            ->info('Require exact title match')
            ->defaultFalse()
            ->end()

            ->booleanNode('auto_association')
            ->info('Enable automatic associations')
            ->defaultTrue()
            ->end()
        ;
    }
}
