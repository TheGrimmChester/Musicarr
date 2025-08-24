<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator\Strategy;

class ExactTrackNumberScoringStrategy implements TrackNumberScoringStrategyInterface
{
    public function calculateScore(string $trackNumber, string $unmatchedTrackNumber): float
    {
        // Exact track number match
        if ($unmatchedTrackNumber === $trackNumber) {
            return 15.0;
        }

        return 0.0;
    }

    public function getScoreReason(string $trackNumber, string $unmatchedTrackNumber): ?string
    {
        // Exact track number match
        if ($unmatchedTrackNumber === $trackNumber) {
            return 'Track number match';
        }

        return null;
    }
}
