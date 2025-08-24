<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation\Internal;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.association_step')]
abstract class AbstractAssociationStep implements AssociationStepInterface
{
    /**
     * Default implementation - always run unless overridden.
     */
    public function shouldRun(array $context): bool
    {
        return true;
    }

    /**
     * Merge results from multiple steps.
     */
    protected function mergeResults(array $results): array
    {
        $merged = [
            'artist' => null,
            'album' => null,
            'track' => null,
            'metadata' => [],
            'audio_analysis_count' => 0,
            'errors' => [],
        ];

        foreach ($results as $result) {
            $merged['artist'] = $result['artist'] ?? $merged['artist'];
            $merged['album'] = $result['album'] ?? $merged['album'];
            $merged['track'] = $result['track'] ?? $merged['track'];
            $merged['metadata'] = array_merge($merged['metadata'], $result['metadata'] ?? []);
            $merged['audio_analysis_count'] += $result['audio_analysis_count'] ?? 0;
            $merged['errors'] = array_merge($merged['errors'], $result['errors'] ?? []);
        }

        return $merged;
    }
}
