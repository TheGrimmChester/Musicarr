<?php

declare(strict_types=1);

namespace App\Configuration\Config;

class AudioQualityConfigurationTreeBuilder extends AbstractConfigurationTreeBuilder
{
    protected function getRootNodeName(): string
    {
        return 'audio_quality';
    }

    protected function buildTree(): void
    {
        $rootNode = $this->getNodeBuilder();

        $rootNode
            ->booleanNode('enabled')
            ->info('Enable audio quality analysis')
            ->defaultTrue()
            ->end()

            ->integerNode('min_bitrate')
            ->info('Minimum acceptable bitrate (kbps)')
            ->min(32)
            ->max(320)
            ->defaultValue(192)
            ->end()

            ->enumNode('preferred_format')
            ->info('Preferred audio format')
            ->values(['mp3', 'flac', 'aac', 'ogg'])
            ->defaultValue('mp3')
            ->end()

            ->booleanNode('analyze_existing')
            ->info('Analyze existing tracks')
            ->defaultFalse()
            ->end()

            ->floatNode('quality_threshold')
            ->info('Quality threshold for analysis (0-1)')
            ->min(0.0)
            ->max(1.0)
            ->defaultValue(0.8)
            ->end()

            ->booleanNode('auto_convert')
            ->info('Automatically convert low quality files')
            ->defaultFalse()
            ->end()

            ->enumNode('convert_to_format')
            ->info('Format to convert files to')
            ->values(['mp3', 'flac', 'aac', 'ogg'])
            ->defaultValue('mp3')
            ->end()
        ;
    }
}
