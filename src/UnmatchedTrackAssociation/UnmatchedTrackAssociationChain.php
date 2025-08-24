<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class UnmatchedTrackAssociationChain
{
    /**
     * @param UnmatchedTrackAssociationProcessorInterface[] $processors
     */
    public function __construct(
        #[TaggedIterator('app.unmatched_track_association_processor', defaultPriorityMethod: 'getPriority')]
        private iterable $processors
    ) {
    }

    /**
     * Execute the complete chain of processors for unmatched track association.
     */
    public function executeChain(array $unmatchedTracks, array $options, LoggerInterface $logger): array
    {
        $results = [];
        $currentOptions = $options;

        foreach ($this->processors as $processor) {
            if (!$processor->shouldRun($currentOptions)) {
                continue;
            }

            $result = $processor->process($unmatchedTracks, $currentOptions, $logger);
            $results[] = $result;

            // Update options with results for next processors
            $currentOptions = array_merge($currentOptions, $result);
        }

        return $this->mergeResults($results);
    }

    /**
     * Merge results from multiple processors.
     */
    private function mergeResults(array $results): array
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
