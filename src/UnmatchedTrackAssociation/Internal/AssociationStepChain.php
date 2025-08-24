<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation\Internal;

use App\Entity\UnmatchedTrack;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class AssociationStepChain
{
    /**
     * @param AssociationStepInterface[] $steps
     */
    public function __construct(
        #[TaggedIterator('app.association_step', defaultPriorityMethod: 'getPriority')]
        private iterable $steps
    ) {
    }

    /**
     * Execute the complete chain of steps for a single unmatched track.
     */
    public function executeChain(UnmatchedTrack $unmatchedTrack, array $context, LoggerInterface $logger): array
    {
        $results = [];
        $currentContext = $context;

        foreach ($this->steps as $step) {
            if (!$step->shouldRun($currentContext)) {
                continue;
            }

            $result = $step->process($unmatchedTrack, $currentContext, $logger);
            $results[] = $result;

            // Update context with results for next steps
            $currentContext = array_merge($currentContext, $result);
        }

        return $this->mergeResults($results);
    }

    /**
     * Merge results from multiple steps.
     */
    private function mergeResults(array $results): array
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
