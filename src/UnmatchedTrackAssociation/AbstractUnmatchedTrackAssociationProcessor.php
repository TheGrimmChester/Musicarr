<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.unmatched_track_association_processor')]
abstract class AbstractUnmatchedTrackAssociationProcessor implements UnmatchedTrackAssociationProcessorInterface
{
    /**
     * Default implementation - always run unless overridden.
     */
    public function shouldRun(array $options): bool
    {
        return true;
    }

    /**
     * Merge results from multiple processors.
     */
    protected function mergeResults(array $results): array
    {
        $merged = [
            'associated_count' => 0,
            'not_found_count' => 0,
            'no_artist_count' => 0,
            'audio_analysis_dispatched' => 0,
            'errors' => [],
        ];

        foreach ($results as $result) {
            $merged['associated_count'] += $result['associated_count'] ?? 0;
            $merged['not_found_count'] += $result['not_found_count'] ?? 0;
            $merged['no_artist_count'] += $result['no_artist_count'] ?? 0;
            $merged['audio_analysis_dispatched'] += $result['audio_analysis_dispatched'] ?? 0;
            $merged['errors'] = array_merge($merged['errors'], $result['errors'] ?? []);
        }

        return $merged;
    }
}
